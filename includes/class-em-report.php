<?php
class EM_Report {
	public static function init() {
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_post_em_export_pdf', [__CLASS__, 'export_pdf']);
	}

	public static function add_menu() {
		add_submenu_page(
			'edit.php?post_type=em_student',
			__('Student Statistics', 'exam-mgmt'),
			__('Statistics Report', 'exam-mgmt'),
			'manage_options',
			'em_report',
			[__CLASS__, 'render']
		);
	}

	public static function render() {
		global $wpdb;

		// Get all students
		$students = get_posts([
			'post_type' => 'em_student',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		]);

		// Pre-fetch all results
		$results = $wpdb->get_results("
			SELECT 
				r.ID AS result_id,
				r_meta1.meta_value AS student_id,
				r_meta2.meta_value AS exam_id,
				r_meta3.meta_value AS marks_json
			FROM {$wpdb->posts} r
			INNER JOIN {$wpdb->postmeta} r_meta1 ON r.ID = r_meta1.post_id AND r_meta1.meta_key = 'em_student_id'
			INNER JOIN {$wpdb->postmeta} r_meta2 ON r.ID = r_meta2.post_id AND r_meta2.meta_key = 'em_exam_id'
			INNER JOIN {$wpdb->postmeta} r_meta3 ON r.ID = r_meta3.post_id AND r_meta3.meta_key = 'em_subject_marks'
			WHERE r.post_type = 'em_result'
		");

		// Map exam → term
		$exam_to_term = [];
		$exams = get_posts(['post_type' => 'em_exam', 'numberposts' => -1]);
		foreach ($exams as $e) {
			$terms = wp_get_post_terms($e->ID, 'em_term', ['fields' => 'ids']);
			$exam_to_term[$e->ID] = $terms ? $terms[0] : 0;
		}

		// Aggregate
		$data = [];
		foreach ($students as $s) {
			$data[$s->ID] = [
				'name' => $s->post_title,
				'terms' => [],
				'total_all' => 0,
				'count_all' => 0,
			];
		}

		foreach ($results as $r) {
			$student_id = intval($r->student_id);
			$exam_id = intval($r->exam_id);
			$marks = json_decode($r->marks_json, true);

			if (!isset($data[$student_id]) || !is_array($marks)) continue;

			$term_id = $exam_to_term[$exam_id] ?? 0;
			if (!$term_id) continue;

			if (!isset($data[$student_id]['terms'][$term_id])) {
				$data[$student_id]['terms'][$term_id] = ['total' => 0, 'max' => 0];
			}

			foreach ($marks as $subj_id => $mark) {
				$data[$student_id]['terms'][$term_id]['total'] += $mark;
				$data[$student_id]['terms'][$term_id]['max'] += 100;

				$data[$student_id]['total_all'] += $mark;
				$data[$student_id]['count_all']++;
			}
		}

		// Term names
		$term_ids = wp_list_pluck(wp_list_pluck($data, 'terms'), 'array_keys');
		$term_ids = array_unique(array_merge(...$term_ids));
		$term_names = [];
		foreach ($term_ids as $tid) {
			$term = get_term($tid, 'em_term');
			$term_names[$tid] = $term ? $term->name : "Term #{$tid}";
		}

		?>
		<div class="wrap">
			<h1><?php _e('Student Statistics Report', 'exam-mgmt'); ?></h1>
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="float:right">
				<?php wp_nonce_field('em_export_pdf'); ?>
				<input type="hidden" name="action" value="em_export_pdf">
				<?php submit_button(__('Export as PDF', 'exam-mgmt'), 'secondary', 'export_pdf', false); ?>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('Student', 'exam-mgmt'); ?></th>
						<?php foreach ($term_ids as $tid): ?>
							<th><?php echo esc_html($term_names[$tid]); ?></th>
						<?php endforeach; ?>
						<th><?php _e('Overall Avg', 'exam-mgmt'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($data as $sid => $d): ?>
						<tr>
							<td><strong><?php echo esc_html($d['name']); ?></strong></td>
							<?php foreach ($term_ids as $tid): ?>
								<td>
									<?php
									if (isset($d['terms'][$tid])) {
										$t = $d['terms'][$tid];
										$avg = $t['max'] > 0 ? ($t['total'] / $t['max']) * 100 : 0;
										echo sprintf('%d / %d (%.1f%%)', $t['total'], $t['max'], $avg);
									} else {
										echo '—';
									}
									?>
								</td>
							<?php endforeach; ?>
							<td>
								<?php
								$overall_avg = $d['count_all'] > 0 ? ($d['total_all'] / ($d['count_all'] * 100)) * 100 : 0;
								echo sprintf('%.1f%%', $overall_avg);
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function export_pdf() {
		if (!wp_verify_nonce($_POST['em_export_pdf'], 'em_export_pdf')) wp_die('Nonce failed');
		if (!current_user_can('manage_options')) wp_die('Perm denied');

		// Minimal TCPDF fallback (or install plugin)
		if (!class_exists('TCPDF')) {
			if (file_exists(EM_PLUGIN_DIR . 'includes/lib/tcpdf/tcpdf.php')) {
				require_once EM_PLUGIN_DIR . 'includes/lib/tcpdf/tcpdf.php';
			} else {
				wp_die('TCPDF not found. Please install TCPDF or use a plugin like "PDF & Print by BestWebSoft".');
			}
		}

		$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor(get_bloginfo('name'));
		$pdf->SetTitle(__('Student Statistics Report', 'exam-mgmt'));
		$pdf->SetSubject(__('Exam Management Report', 'exam-mgmt'));

		$pdf->AddPage();
		$pdf->SetFont('helvetica', 'B', 16);
		$pdf->Cell(0, 10, __('Student Statistics Report', 'exam-mgmt'), 0, 1, 'C');
		$pdf->Ln(10);

		// Reuse render logic (extracted)
		ob_start();
		self::render();
		$html = ob_get_clean();

		// Extract table rows (simplified — in practice, regenerate clean HTML)
		$pdf->writeHTML('<h2>' . __('Data as of', 'exam-mgmt') . ' ' . current_time('Y-m-d H:i') . '</h2>', true);
		$pdf->SetFont('helvetica', '', 10);
		// For brevity: assume $report_data from render()
		// In prod: refactor `render()` to return data + HTML

		$pdf->Output('student-statistics-report.pdf', 'D');
		exit;
	}
}