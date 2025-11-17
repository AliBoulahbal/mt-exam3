<?php
class EM_Import {
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_post_em_import_results', [__CLASS__, 'handle_import']);
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=em_result',
			__('Bulk Import Results', 'exam-mgmt'),
			__('Bulk Import', 'exam-mgmt'),
			'manage_options',
			'em_import',
			[__CLASS__, 'render']
		);
	}

	public static function render() {
		if (isset($_GET['success'])) {
			echo '<div class="notice notice-success"><p>' . __('Import successful!', 'exam-mgmt') . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php _e('Bulk Import Results', 'exam-mgmt'); ?></h1>
			<p><?php _e('Upload a CSV file with columns: <code>student_id,exam_id,subject_id,marks</code>', 'exam-mgmt'); ?></p>
			<p><a href="<?php echo EM_PLUGIN_URL; ?>sample-results.csv" class="button"><?php _e('Download Sample CSV', 'exam-mgmt'); ?></a></p>

			<form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
				<?php wp_nonce_field('em_import_nonce'); ?>
				<input type="hidden" name="action" value="em_import_results">
				<table class="form-table">
					<tr>
						<th><label for="csv_file"><?php _e('CSV File', 'exam-mgmt'); ?></label></th>
						<td><input type="file" name="csv_file" id="csv_file" accept=".csv" required></td>
					</tr>
				</table>
				<?php submit_button(__('Import Results', 'exam-mgmt')); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_import() {
		if (!wp_verify_nonce($_POST['em_import_nonce'], 'em_import_nonce')) {
			wp_die(__('Security check failed.', 'exam-mgmt'));
		}

		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have permission.', 'exam-mgmt'));
		}

		if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
			wp_die(__('File upload error.', 'exam-mgmt'));
		}

		$tmp_file = $_FILES['csv_file']['tmp_name'];
		if (!is_uploaded_file($tmp_file)) {
			wp_die(__('Invalid file.', 'exam-mgmt'));
		}

		$handle = fopen($tmp_file, 'r');
		if (!$handle) wp_die(__('Could not read CSV.', 'exam-mgmt'));

		$header = fgetcsv($handle);
		if ($header === false || count($header) < 4) {
			fclose($handle);
			wp_die(__('Invalid CSV format. Expected: student_id,exam_id,subject_id,marks', 'exam-mgmt'));
		}

		$expected = ['student_id', 'exam_id', 'subject_id', 'marks'];
		if (array_slice($header, 0, 4) !== $expected) {
			fclose($handle);
			wp_die(__('CSV header mismatch. Expected: ' . implode(', ', $expected), 'exam-mgmt'));
		}

		$success = 0;
		$errors = [];

		while (($row = fgetcsv($handle)) !== false) {
			if (count($row) < 4) continue;

			$student_id = intval($row[0]);
			$exam_id = intval($row[1]);
			$subject_id = intval($row[2]);
			$marks = intval($row[3]);

			// Validate
			if (!$student_id || !get_post($student_id) || get_post_type($student_id) !== 'em_student') {
				$errors[] = "Invalid student_id: $row[0]";
				continue;
			}
			if (!$exam_id || !get_post($exam_id) || get_post_type($exam_id) !== 'em_exam') {
				$errors[] = "Invalid exam_id: $row[1]";
				continue;
			}
			if (!$subject_id || !get_post($subject_id) || get_post_type($subject_id) !== 'em_subject') {
				$errors[] = "Invalid subject_id: $row[2]";
				continue;
			}
			if ($marks < 0 || $marks > 100) {
				$errors[] = "Marks out of range (0–100): $row[3]";
				continue;
			}

			// Create or update result
			$existing = get_posts([
				'post_type' => 'em_result',
				'meta_query' => [
					['key' => 'em_student_id', 'value' => $student_id],
					['key' => 'em_exam_id', 'value' => $exam_id],
				],
				'posts_per_page' => 1,
			]);

			$result_id = $existing ? $existing[0]->ID : wp_insert_post([
				'post_type' => 'em_result',
				'post_title' => "Result for Student #$student_id, Exam #$exam_id",
				'post_status' => 'publish',
			]);

			if (is_wp_error($result_id)) {
				$errors[] = "Failed to create result: " . $result_id->get_error_message();
				continue;
			}

			update_post_meta($result_id, 'em_student_id', $student_id);
			update_post_meta($result_id, 'em_exam_id', $exam_id);

			$current_marks = get_post_meta($result_id, 'em_subject_marks', true);
			$marks_arr = $current_marks ? json_decode($current_marks, true) : [];
			$marks_arr[$subject_id] = $marks;
			update_post_meta($result_id, 'em_subject_marks', wp_json_encode($marks_arr));

			$success++;
		}

		fclose($handle);
		unlink($tmp_file);

		if (!empty($errors)) {
			wp_die('<h1>' . __('Import completed with errors', 'exam-mgmt') . '</h1><ul><li>' . implode('</li><li>', array_map('esc_html', $errors)) . '</li></ul><a href="' . admin_url('edit.php?post_type=em_result&page=em_import') . '">' . __('Back', 'exam-mgmt') . '</a>');
		}

		wp_redirect(add_query_arg('success', '1', admin_url('edit.php?post_type=em_result&page=em_import')));
		exit;
	}
}

// Add sample CSV to repo root
if (defined('WP_CLI') && WP_CLI) {
	// Not needed — we’ll include sample-results.csv manually
}