<?php
/**
 * Plugin Name: No Backdating Posts
 * Plugin URI: https://github.com/dol-lab/no-backdating-posts
 * Description: Prevents backdating of posts (and pages by default).
 * Version: 0.5.0
 * Author: Vitus Schuhwerk
 * Author URI: https://github.com/dol-lab/no-backdating-posts
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: no-backdating
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'NO_BACKDATING_VERSION', '1.0.0' );
add_filter( 'wp_insert_post_data', 'no_backdating_on_insert', 10, 4 );
add_action( 'wp_ajax_check_backdate_notice', 'no_backdating_ajax' );
add_action( 'enqueue_block_editor_assets', 'no_backdating_enqueue_scripts' );


/**
 * Disallow backdating of new and existing posts.
 * If a user tries to backdate a post, the date will be set to the earliest allowed date:
 * - Now, if the post is being created or is changed from future to the past.
 * - The original publish date, if the post is being updated.
 *
 * A warning notice will be displayed in classic editor (<- untested) and gutenberg.
 *
 * @param array $data                An array of slashed, sanitized, and processed post data.
 * @param array $postarr             An array of sanitized (and slashed) but otherwise unmodified post data.
 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed post data as
 *                                   originally passed to wp_insert_post().
 * @param bool  $update              Whether this is an existing post being updated.
 * @return array
 */
function no_backdating_on_insert( $data, $postarr, $unsanitized_postarr, $update ) {

	// the post types that are not allowed to be backdated.
	$post_types = apply_filters( 'no_backdating_post_types', array( 'post', 'page' ) );

	if ( current_user_can( 'backdate_' . $data['post_type'] )
	|| current_user_can( 'backdate_posts' )
	|| ! in_array( $data['post_type'], $post_types ) ) {
		return $data;
	}

	if ( in_array( $data['post_status'], array( 'draft', 'auto-draft', 'pending' ) ) ) {
		return $data;
	}

	$now          = current_time( 'mysql' );
	$grace_period = apply_filters( 'no_backdating_grace_period', 1 * HOUR_IN_SECONDS );
	$compare_date = $postarr['post_date'];

	if ( $update ) {
		$existing_post = get_post( $postarr['ID'] );
		$compare_date  = $existing_post->post_date;
	}

	$earliest_allowed = min(
		strtotime( $compare_date ),
		strtotime( $now ) - $grace_period
	);

	if ( strtotime( $data['post_date'] ) < $earliest_allowed ) {
		$formatted_display = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $earliest_allowed );
		$formatted_db      = date( 'Y-m-d H:i:s', $earliest_allowed );

		$data['post_date']     = $formatted_db;
		$data['post_date_gmt'] = get_gmt_from_date( $formatted_db );

		$message = sprintf(
			/* translators: %s: formatted date and time */
			esc_html__( 'Sorry, you cannot change the date of this post earlier than %s.', 'no-backdating' ),
			$formatted_display
		);

		$notice = array(
			'message' => $message,
			'type'    => 'warning',
		);
		set_transient( 'backdate_notice_' . get_current_user_id(), $notice, 30 );
	}

	return $data;
}

/**
 * AJAX handler to check for backdate notice.
 */
function no_backdating_ajax() {
	check_ajax_referer( 'backdate_notice_nonce', 'nonce' );

	$notice = get_transient( 'backdate_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'backdate_notice_' . get_current_user_id() );
		wp_send_json_success( $notice );
	} else {
		wp_send_json_success(
			array(
				'message' => 'No notice',
				'type'    => 'no-backdate',
			)
		);
	}
}

/**
 * Enqueue the script that will display the backdate notice in Gutenberg.
 */
function no_backdating_enqueue_scripts() {
	wp_enqueue_script(
		'no-backdating',
		plugin_dir_url( __FILE__ ) . 'no-backdating-posts.js',
		array( 'wp-data', 'wp-i18n', 'wp-util' ),
		NO_BACKDATING_VERSION,
		true
	);

	wp_localize_script(
		'no-backdating',
		'noBackdate',
		array(
			'nonce'   => wp_create_nonce( 'backdate_notice_nonce' ),
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'action'  => 'check_backdate_notice',
		)
	);
}
