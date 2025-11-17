<?php
class EM_Exam_Meta {
	public static function init() {
		add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
		add_action('save_post_em_exam', [__CLASS__, 'save_meta']);
	}

	public static function add_meta_box() {
		add_meta_box('em_exam_meta', __('Exam Details', 'exam-mgmt'), [__CLASS__, 'render'], 'em_exam', 'normal', 'high');
	}

	public static function render($post) {
		wp_nonce_field('em_exam_nonce', 'em_exam_nonce');

		$start = get_post_meta($post->ID, 'em_start_datetime', true);
		$end = get_post_meta($post->ID, 'em_end_datetime', true);
		$subject_id = get_post_meta($post->ID, 'em_subject_id', true);

		$subjects = get_posts([
			'post_type' => 'em_subject',
			'numberposts' => -1,
			'orderby' => 'title',
			'order' => 'ASC'
		]); ?>
		<p>
			<label for="em_start_datetime"><?php _e('Start Datetime', 'exam-mgmt'); ?>:</label><br>
			<input type="datetime-local" id="em_start_datetime" name="em_start_datetime"
				value="<?php echo esc_attr($start); ?>" required>
		</p>
		<p>
			<label for="em_end_datetime"><?php _e('End Datetime', 'exam-mgmt'); ?>:</label><br>
			<input type="datetime-local" id="em_end_datetime" name="em_end_datetime"
				value="<?php echo esc_attr($end); ?>" required>
		</p>
		<p>
			<label for="em_subject_id"><?php _e('Subject', 'exam-mgmt'); ?>:</label><br>
			<select name="em_subject_id" id="em_subject_id" required>
				<option value=""><?php _e('— Select Subject —', 'exam-mgmt'); ?></option>
				<?php foreach ($subjects as $subj): ?>
					<option value="<?php echo esc_attr($subj->ID); ?>" <?php selected($subject_id, $subj->ID); ?>>
						<?php echo esc_html($subj->post_title); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public static function save_meta($post_id) {
		if (!isset($_POST['em_exam_nonce']) || !wp_verify_nonce($_POST['em_exam_nonce'], 'em_exam_nonce')) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		if (isset($_POST['em_start_datetime'])) {
			update_post_meta($post_id, 'em_start_datetime', sanitize_text_field($_POST['em_start_datetime']));
		}
		if (isset($_POST['em_end_datetime'])) {
			update_post_meta($post_id, 'em_end_datetime', sanitize_text_field($_POST['em_end_datetime']));
		}
		if (isset($_POST['em_subject_id'])) {
			$subj_id = intval($_POST['em_subject_id']);
			if ($subj_id > 0) {
				update_post_meta($post_id, 'em_subject_id', $subj_id);
			}
		}
	}
}