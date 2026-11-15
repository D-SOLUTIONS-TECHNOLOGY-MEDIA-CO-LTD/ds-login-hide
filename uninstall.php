<?php
/**
 * Gỡ plugin: xoá toàn bộ option của DS Hide Login (không đụng option whl_* của plugin gốc).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Xoá bộ đếm / khoá đăng nhập sai (lưu dạng transient ds_hl_*). */
function ds_hide_login_uninstall_counters() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_ds_hl_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_ds_hl_' ) . '%'
	) );
}

$ds_hide_login_options = array( 'ds_hide_login_page', 'ds_hide_login_redirect', 'ds_hide_login_appearance', 'ds_hide_login_protection', 'ds_hide_login_lockouts', 'ds_hide_login_hide_links' );

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $ds_blog_id ) {
		switch_to_blog( $ds_blog_id );
		foreach ( $ds_hide_login_options as $ds_option ) {
			delete_option( $ds_option );
		}
		ds_hide_login_uninstall_counters();
		restore_current_blog();
	}
} else {
	foreach ( $ds_hide_login_options as $ds_option ) {
		delete_option( $ds_option );
	}
	ds_hide_login_uninstall_counters();
}
