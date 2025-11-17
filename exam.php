<?php
/**
 * Plugin Name: Exam Management
 * Plugin URI: https://example.com
 * Description: A WordPress plugin for screening senior developer applicants with custom post types for students, exams, results.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

define('EM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes (manual for compatibility)
require_once EM_PLUGIN_DIR . 'includes/class-em-term-meta.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-exam-meta.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-result-meta.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-ajax.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-shortcodes.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-import.php';
require_once EM_PLUGIN_DIR . 'includes/class-em-report.php';

class EM_CPT {
	public static function init() {
		add_action('init', [__CLASS__, 'register_cpts']);
		add_action('init', [__CLASS__, 'register_taxonomy']);
		add_action('init', [__CLASS__, 'maybe_flush_rewrite_rules']);
	}

	public static function register_cpts() {
		// Students
		register_post_type('em_student', [
			'labels' => [
				'name' => __('Students', 'exam-mgmt'),
				'singular_name' => __('Student', 'exam-mgmt'),
			],
			'public' => true,
			'capability_type' => 'post',
			'supports' => ['title'], // No editor needed for students
			'menu_icon' => 'dashicons-groups',
			'show_in_rest' => true,
			'has_archive' => true,
			'rewrite' => ['slug' => 'students'],
		]);

		// Subjects
		register_post_type('em_subject', [
			'labels' => [
				'name' => __('Subjects', 'exam-mgmt'),
				'singular_name' => __('Subject', 'exam-mgmt'),
			],
			'public' => true,
			'capability_type' => 'post',
			'supports' => ['title'],
			'menu_icon' => 'dashicons-book-alt',
			'show_in_rest' => true,
			'has_archive' => true,
			'rewrite' => ['slug' => 'subjects'],
		]);

		// Exams
		register_post_type('em_exam', [
			'labels' => [
				'name' => __('Exams', 'exam-mgmt'),
				'singular_name' => __('Exam', 'exam-mgmt'),
			],
			'public' => true,
			'capability_type' => 'post',
			'supports' => ['title'],
			'menu_icon' => 'dashicons-book',
			'show_in_rest' => true,
			'has_archive' => true,
			'rewrite' => ['slug' => 'exams'],
		]);

		// Results
		register_post_type('em_result', [
			'labels' => [
				'name' => __('Results', 'exam-mgmt'),
				'singular_name' => __('Result', 'exam-mgmt'),
			],
			'public' => true,
			'capability_type' => 'post',
			'supports' => ['title'],
			'menu_icon' => 'dashicons-performance',
			'show_in_rest' => true,
			'has_archive' => false,
		]);
	}

	public static function register_taxonomy() {
		register_taxonomy('em_term', ['em_exam'], [
			'labels' => [
				'name' => __('Terms', 'exam-mgmt'),
				'singular_name' => __('Term', 'exam-mgmt'),
			],
			'public' => true,
			'hierarchical' => false,
			'show_ui' => true,
			'show_in_rest' => true,
			'rewrite' => ['slug' => 'term'],
		]);
	}

	public static function maybe_flush_rewrite_rules() {
		if (get_option('em_flushed') !== 'yes') {
			flush_rewrite_rules();
			update_option('em_flushed', 'yes');
		}
	}
}

// ======== Term Meta: Start/End Date Fields =========
EM_Term_Meta::init();

// ======== Exam Meta: Start/End Datetime + Subject =========
EM_Exam_Meta::init();

// ======== Result Meta: Student, Exam, Subject Marks =========
EM_Result_Meta::init();

// ======== AJAX Endpoint =========
EM_AJAX::init();

// ======== Shortcode =========
EM_Shortcodes::init();

// ======== Bulk Import =========
EM_Import::init();

// ======== Admin Reports =========
EM_Report::init();

// Initialize Core CPTs
function em_init() {
	EM_CPT::init();
}
add_action('plugins_loaded', 'em_init');

// Localization
function em_load_textdomain() {
	load_plugin_textdomain('exam-mgmt', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'em_load_textdomain');

// === Optional: Admin CSS ===
function em_admin_enqueue() {
	wp_enqueue_style('em-admin', EM_PLUGIN_URL . 'assets/admin.css', [], '1.0');
}
add_action('admin_enqueue_scripts', 'em_admin_enqueue');