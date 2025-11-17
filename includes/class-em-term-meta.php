<?php
class EM_Term_Meta {
	public static function init() {
		add_action('em_term_add_form_fields', [__CLASS__, 'add_term_fields']);
		add_action('em_term_edit_form_fields', [__CLASS__, 'edit_term_fields'], 10, 2);
		add_action('created_em_term', [__CLASS__, 'save_term_meta']);
		add_action('edited_em_term', [__CLASS__, 'save_term_meta']);
	}

	public static function add_term_fields() {
		wp_nonce_field('em_term_nonce', 'em_term_nonce'); ?>
		<div class="form-field">
			<label for="em_start_date"><?php _e('Start Date', 'exam-mgmt'); ?></label>
			<input type="date" id="em_start_date" name="em_start_date" value="" class="em-date">
			<p class="description"><?php _e('Term start date (e.g., 2025-09-01)', 'exam-mgmt'); ?></p>
		</div>
		<div class="form-field">
			<label for="em_end_date"><?php _e('End Date', 'exam-mgmt'); ?></label>
			<input type="date" id="em_end_date" name="em_end_date" value="" class="em-date">
			<p class="description"><?php _e('Term end date (e.g., 2025-12-20)', 'exam-mgmt'); ?></p>
		</div>
		<?php
	}

	public static function edit_term_fields($term) {
		$start = get_term_meta($term->term_id, 'em_start_date', true);
		$end = get_term_meta($term->term_id, 'em_end_date', true);
		wp_nonce_field('em_term_nonce', 'em_term_nonce'); ?>
		<tr class="form-field">
			<th><label for="em_start_date"><?php _e('Start Date', 'exam-mgmt'); ?></label></th>
			<td>
				<input type="date" id="em_start_date" name="em_start_date" value="<?php echo esc_attr($start); ?>" class="em-date">
				<p class="description"><?php _e('Term start date', 'exam-mgmt'); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th><label for="em_end_date"><?php _e('End Date', 'exam-mgmt'); ?></label></th>
			<td>
				<input type="date" id="em_end_date" name="em_end_date" value="<?php echo esc_attr($end); ?>" class="em-date">
				<p class="description"><?php _e('Term end date', 'exam-mgmt'); ?></p>
			</td>
		</tr>
		<?php
	}

	public static function save_term_meta($term_id) {
		if (!isset($_POST['em_term_nonce']) || !wp_verify_nonce($_POST['em_term_nonce'], 'em_term_nonce')) return;

		if (isset($_POST['em_start_date'])) {
			update_term_meta($term_id, 'em_start_date', sanitize_text_field($_POST['em_start_date']));
		}
		if (isset($_POST['em_end_date'])) {
			update_term_meta($term_id, 'em_end_date', sanitize_text_field($_POST['em_end_date']));
		}
	}
}