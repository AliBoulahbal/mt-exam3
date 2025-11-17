<?php
class EM_AJAX {
	public static function init() {
		add_action('wp_ajax_em_get_exams', [__CLASS__, 'handle']);
		add_action('wp_ajax_nopriv_em_get_exams', [__CLASS__, 'handle']);
	}

	public static function handle() {
		$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
		$per_page = 10;

		// Get current datetime
		$now = current_time('mysql');

		$args = [
			'post_type' => 'em_exam',
			'post_status' => 'publish',
			'posts_per_page' => $per_page,
			'paged' => $page,
			'orderby' => 'meta_value',
			'meta_key' => '_exam_sort_order',
			'order' => 'ASC',
			'suppress_filters' => false,
		];

		// Add sorting clause
		add_filter('posts_orderby', [__CLASS__, 'custom_orderby'], 10, 2);
		add_filter('posts_fields', [__CLASS__, 'add_sort_field'], 10, 2);
		add_filter('posts_join', [__CLASS__, 'join_datetime_meta'], 10, 2);

		$query = new WP_Query($args);

		remove_filter('posts_orderby', [__CLASS__, 'custom_orderby']);
		remove_filter('posts_fields', [__CLASS__, 'add_sort_field']);
		remove_filter('posts_join', [__CLASS__, 'join_datetime_meta']);

		$exams = [];
		foreach ($query->posts as $post) {
			$start = get_post_meta($post->ID, 'em_start_datetime', true);
			$end = get_post_meta($post->ID, 'em_end_datetime', true);
			$subject_id = get_post_meta($post->ID, 'em_subject_id', true);
			$subject = $subject_id ? get_post($subject_id) : null;

			$term = wp_get_post_terms($post->ID, 'em_term', ['fields' => 'names']);
			$term_name = $term ? $term[0] : '';

			$exams[] = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'start' => $start,
				'end' => $end,
				'subject' => $subject ? $subject->post_title : '',
				'term' => $term_name,
				'status' => self::get_exam_status($start, $end, $now),
			];
		}

		wp_send_json([
			'success' => true,
			'data' => $exams,
			'pagination' => [
				'current' => $page,
				'total' => $query->max_num_pages,
				'per_page' => $per_page,
			]
		]);
	}

	private static function get_exam_status($start, $end, $now) {
		if (!$start || !$end) return 'unknown';
		if ($now >= $start && $now <= $end) return 'ongoing';
		if ($now < $start) return 'upcoming';
		return 'past';
	}

	public static function add_sort_field($fields, $query) {
		if ($query->get('orderby') !== 'meta_value' || $query->get('meta_key') !== '_exam_sort_order') {
			return $fields;
		}
		global $wpdb;
		return "$fields, 
			CASE 
				WHEN pm1.meta_value <= %s AND pm2.meta_value >= %s THEN 1
				WHEN pm1.meta_value > %s THEN 2
				ELSE 3
			END AS _exam_sort_order";
	}

	public static function join_datetime_meta($join, $query) {
		if ($query->get('meta_key') !== '_exam_sort_order') return $join;
		global $wpdb;
		$now = current_time('mysql');
		$join .= " 
			LEFT JOIN {$wpdb->postmeta} pm1 ON ({$wpdb->posts}.ID = pm1.post_id AND pm1.meta_key = 'em_start_datetime')
			LEFT JOIN {$wpdb->postmeta} pm2 ON ({$wpdb->posts}.ID = pm2.post_id AND pm2.meta_key = 'em_end_datetime')
		";
		return $join;
	}

	public static function custom_orderby($orderby, $query) {
		if ($query->get('meta_key') !== '_exam_sort_order') return $orderby;
		return '_exam_sort_order ASC, pm1.meta_value ASC';
	}
}