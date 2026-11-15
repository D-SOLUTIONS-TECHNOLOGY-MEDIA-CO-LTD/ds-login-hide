<?php
/**
 * Plugin Name:       DS Hide Login
 * Description:       Đổi URL đăng nhập, chặn wp-login.php & wp-admin, giới hạn đăng nhập sai (nhận IP thật qua Cloudflare), reCAPTCHA / Cloudflare Turnstile, logo thương hiệu (login + admin bar) và template trang login (Classic / Split / Glass) với màu tuỳ chỉnh.
 * Version:           1.2.0
 * Author:            D-Solutions
 * Requires at least: 5.3
 * Requires PHP:      7.0
 * Text Domain:       ds-hide-login
 * Domain Path:       /languages
 * License:           GPLv2 or later
 *
 * Port & mở rộng từ WPS Hide Login 1.9.20 (GPLv2+).
 */

defined( 'ABSPATH' ) || exit;

define( 'DS_HIDE_LOGIN_VERSION', '1.2.0' );
define( 'DS_HIDE_LOGIN_FILE', __FILE__ );
define( 'DS_HIDE_LOGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DS_HIDE_LOGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DS_HIDE_LOGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-options.php';
require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-core.php';
require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-appearance.php';
require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-client-ip.php';
require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-limit-login.php';
require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-captcha.php';
require_once DS_HIDE_LOGIN_DIR . 'includes/class-ds-hide-login-settings.php';

register_activation_hook( __FILE__, array( 'DS_Hide_Login_Options', 'activate' ) );

add_action( 'plugins_loaded', 'ds_hide_login_bootstrap', 0 );

/**
 * Khởi động plugin. Nhường cho WPS Hide Login nếu plugin gốc vẫn bật
 * (2 bộ routing chạy song song sẽ phá nhau).
 */
function ds_hide_login_bootstrap() {
	load_plugin_textdomain( 'ds-hide-login', false, dirname( DS_HIDE_LOGIN_BASENAME ) . '/languages' );

	if ( class_exists( '\WPS\WPS_Hide_Login\Plugin' ) ) {
		add_action( 'admin_notices', 'ds_hide_login_conflict_notice' );

		return;
	}

	new DS_Hide_Login_Core();
	new DS_Hide_Login_Appearance();
	new DS_Hide_Login_Limit_Login();
	new DS_Hide_Login_Captcha();

	if ( is_admin() ) {
		new DS_Hide_Login_Settings();
	}
}

function ds_hide_login_conflict_notice() {
	echo '<div class="notice notice-warning"><p>'
		. esc_html__( 'DS Hide Login đang tạm dừng vì plugin WPS Hide Login vẫn bật. Hãy tắt WPS Hide Login (cấu hình đã được chuyển sang DS Hide Login).', 'ds-hide-login' )
		. '</p></div>';
}

/** Helper cho theme: URL đăng nhập mới. */
function ds_hide_login_url( $scheme = null ) {
	return DS_Hide_Login_Options::login_url( $scheme );
}
