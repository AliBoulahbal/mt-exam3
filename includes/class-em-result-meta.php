<?php
class EM_Result_Meta {
	public static function init() {
		add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
		add_action('save_post_em_result', [__CLASS__, 'save_meta']);
	}

	public static function add_meta_box() {
		add_meta_box('em_result_meta', __('Result Details', 'exam-mgmt'), [__CLASS__, 'render'], 'em_result', 'normal', 'high');
	}

	public static function render($post) {
		wp_nonce_field('em_result_nonce', 'em_result_nonce');

		$student_id = get_post_meta($post->ID, 'em_student_id', true);
		$exam_id = get_post_meta($post->ID, 'em_exam_id', true);

		$students = get_posts(['post_type' => 'em_student', 'numberposts' => -1, 'orderby' => 'title']);
		$exams = get_posts(['post_type' => 'em_exam', 'numberposts' => -1, 'orderby' => 'title']);

		// Fetch subject marks (JSON stored)
		$marks_json = get_post_meta($post->ID, 'em_subject_marks', true);
		$marks = $marks_json ? json_decode($marks_json, true) : [];
		?>

		<p>
			<label for="em_student_id"><?php _e('Student', 'exam-mgmt'); ?>:</label><br>
			<select name="em_student_id" id="em_student_id" required>
				<option value=""><?php _e('— Select Student —', 'exam-mgmt'); ?></option>
				<?php foreach ($students as $s): ?>
					<option value="<?php echo esc_attr($s->ID); ?>" <?php selected($student_id, $s->ID); ?>>
						<?php echo esc_html($s->post_title); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="em_exam_id"><?php _e('Exam', 'exam-mgmt'); ?>:</label><br>
			<select name="em_exam_id" id="em_exam_id" required>
				<option value=""><?php _e('— Select Exam —', 'exam-mgmt'); ?></option>
				<?php foreach ($exams as $e): ?>
					<option value="<?php echo esc_attr($e->ID); ?>" <?php selected($exam_id, $e->ID); ?>>
						<?php echo esc_html($e->post_title); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<div id="em_subject_marks_container">
			<?php
			if ($exam_id) {
				self::render_subject_marks_fields($exam_id, $marks);
			} else {
				echo '<p class="description">' . __('Select an exam to load subjects.', 'exam-mgmt') . '</p>';
			}
			?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#em_exam_id').on('change', function() {
				const examId = $(this).val();
				if (!examId) {
					$('#em_subject_marks_container').html('<p class="description'><?php echo esc_js(__('Select an exam to load subjects.', 'exam-mgmt')); ?></p>');
					return;
				}
				$.post(ajaxurl, {
					action: 'em_get_exam_subjects',
					exam_id: examId,
					nonce: '<?php echo wp_create_nonce("em_get_exam_subjects"); ?>'
				}, function(response) {
					if (response.success && response.data) {
						$('#em_subject_marks_container').html(response.data);
					} else {
						$('#em_subject_marks_container').html('<p class="error"><?php echo esc_js(__('Failed to load subjects.', 'exam-mgmt')); ?></p>');
					}
				});
			});
		});
		</script>
		<?php
	}

	private static function render_subject_marks_fields($exam_id, $marks) {
		$subject_id = get_post_meta($exam_id, 'em_subject_id', true);
		if (!$subject_id) return;

		$subject = get_post($subject_id);
		if (!$subject) return;

		$current_mark = isset($marks[$subject_id]) ? intval($marks[$subject_id]) : '';
		?>
		<h4><?php echo esc_html($subject->post_title); ?> (ID: <?php echo esc_html($subject_id); ?>)</h4>
		<p>
			<label><?php _e('Marks (out of 100)', 'exam-mgmt'); ?>:</label><br>
			<input type="number" name="em_subject_marks[<?php echo esc_attr($subject_id); ?>]" 
				value="<?php echo esc_attr($current_mark); ?>" min="0" max="100" step="1">
		</p>
		<?php
	}

	public static function save_meta($post_id) {
		if (!isset($_POST['em_result_nonce']) || !wp_verify_nonce($_POST['em_result_nonce'], 'em_result_nonce')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		if (isset($_POST['em_student_id'])) {
			update_post_meta($post_id, 'em_student_id', intval($_POST['em_student_id']));
		}
		if (isset($_POST['em_exam_id'])) {
			update_post_meta($post_id, 'em_exam_id', intval($_POST['em_exam_id']));
		}
		if (isset($_POST['em_subject_marks']) && is_array($_POST['em_subject_marks'])) {
			$clean = [];
			foreach ($_POST['em_subject_marks'] as $subj_id => $mark) {
				$subj_id = intval($subj_id);
				$mark = intval($mark);
				if ($subj_id > 0 && $mark >= 0 && $mark <= 100) {
					$clean[$subj_id] = $mark;
				}
			}
			update_post_meta($post_id, 'em_subject_marks', wp_json_encode($clean));
		}
	}

	// AJAX handler for dynamic subject loading
	public static function ajax_get_exam_subjects() {
		check_ajax_referer('em_get_exam_subjects');

		$exam_id = intval($_POST['exam_id']);
		if (!$exam_id) wp_die();

		// For now: one subject per exam (as per spec: "marks for each subject associated with the exam")
		// If multi-subject later, query exam's associated subjects (e.g., meta or taxonomy)
		ob_start();
		self::render_subject_marks_fields($exam_id, []);
		$html = ob_get_clean();
		wp_send_json_success($html);
	}
}
add_action('wp_ajax_em_get_exam_subjects', ['EM_Result_Meta', 'ajax_get_exam_subjects']);