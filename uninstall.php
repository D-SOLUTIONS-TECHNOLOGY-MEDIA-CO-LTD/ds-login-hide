<?php
/**
 * Gỡ plugin: xoá toàn bộ option của DS Hide Login (không đụng option whl_* của plugin gốc).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ds_hide_login_options = array( 'ds_hide_login_page', 'ds_hide_login_redirect', 'ds_hide_login_appearance', 'ds_hide_login_protection', 'ds_hide_login_lockouts' );

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $ds_blog_id ) {
		switch_to_blog( $ds_blog_id );
		foreach ( $ds_hide_login_options as $ds_option ) {
			delete_option( $ds_option );
		}
		restore_current_blog();
	}
} else {
	foreach ( $ds_hide_login_options as $ds_option ) {
		delete_option( $ds_option );
	}
}
