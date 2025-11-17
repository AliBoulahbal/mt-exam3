<?php
class EM_Shortcodes {
	public static function init() {
		add_shortcode('em_top_students', [__CLASS__, 'render']);
	}

	public static function render($atts) {
		$atts = shortcode_atts(['limit_per_term' => 3], $atts);
		$limit = intval($atts['limit_per_term']);

		// Get all terms, ordered by start date DESC (latest first)
		$terms = get_terms([
			'taxonomy' => 'em_term',
			'hide_empty' => false,
			'meta_key' => 'em_start_date',
			'orderby' => 'meta_value',
			'order' => 'DESC',
		]);

		if (empty($terms)) return '<p>' . __('No terms found.', 'exam-mgmt') . '</p>';

		$output = '<div class="em-top-students">';
		foreach ($terms as $term) {
			$students = self::get_top_students_in_term($term->term_id, $limit);
			if (empty($students)) continue;

			$output .= "<h3>" . esc_html($term->name) . "</h3><ul>";
			foreach ($students as $s) {
				$output .= "<li><strong>" . esc_html($s['name']) . "</strong>: " . esc_html($s['total']) . " / " . esc_html($s['max']) . " (" . round($s['avg'], 1) . "%)</li>";
			}
			$output .= "</ul>";
		}
		$output .= '</div>';

		return $output;
	}

	private static function get_top_students_in_term($term_id, $limit = 3) {
		$cache_key = 'em_top_students_' . $term_id . '_' . $limit;
		$cached = get_transient($cache_key);
		if ($cached !== false) return $cached;

		global $wpdb;

		$results = $wpdb->get_results($wpdb->prepare("
			SELECT 
				r.post_id AS result_id,
				r.meta_value AS marks_json,
				s.post_title AS student_name,
				e.ID AS exam_id
			FROM {$wpdb->posts} r
			INNER JOIN {$wpdb->postmeta} r_meta ON r.ID = r_meta.post_id AND r_meta.meta_key = 'em_student_id'
			INNER JOIN {$wpdb->posts} s ON r_meta.meta_value = s.ID
			INNER JOIN {$wpdb->postmeta} r_exam ON r.ID = r_exam.post_id AND r_exam.meta_key = 'em_exam_id'
			INNER JOIN {$wpdb->posts} e ON r_exam.meta_value = e.ID
			INNER JOIN {$wpdb->term_relationships} tr ON e.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE 
				r.post_type = 'em_result' 
				AND s.post_type = 'em_student'
				AND e.post_type = 'em_exam'
				AND tt.term_id = %d
			", $term_id), ARRAY_A);

		$student_totals = [];
		foreach ($results as $row) {
			$marks = json_decode($row['marks_json'], true);
			if (!is_array($marks)) continue;

			$student_id = $row['result_id']; // Not ideal — better to use student post ID
			// But student_id is in meta — restructure:
			$student_id = get_post_meta($row['result_id'], 'em_student_id', true);
			if (!$student_id) continue;

			if (!isset($student_totals[$student_id])) {
				$student_totals[$student_id] = [
					'name' => $row['student_name'],
					'total' => 0,
					'count' => 0,
					'max_possible' => 0,
				];
			}

			foreach ($marks as $subj_id => $mark) {
				$student_totals[$student_id]['total'] += $mark;
				$student_totals[$student_id]['count']++;
				$student_totals[$student_id]['max_possible'] += 100;
			}
		}

		// Compute avg and sort
		$top = [];
		foreach ($student_totals as $id => $data) {
			$data['avg'] = $data['max_possible'] > 0 ? ($data['total'] / $data['max_possible']) * 100 : 0;
			$top[] = $data;
		}

		usort($top, function($a, $b) {
			return $b['avg'] <=> $a['avg'];
		});

		$top = array_slice($top, 0, $limit);
		set_transient($cache_key, $top, HOUR_IN_SECONDS);
		return $top;
	}
}