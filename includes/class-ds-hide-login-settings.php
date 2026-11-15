<?php
/**
 * Trang cài đặt: Settings › DS Hide Login, 2 tab "Bảo mật" và "Giao diện".
 * Lưu qua options.php (Settings API) – mỗi tab là một option group riêng
 * để lưu tab này không ghi đè tab kia.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Settings {

	const PAGE           = 'ds-hide-login';
	const GROUP_SECURITY = 'ds_hide_login_security';
	const GROUP_LOOK     = 'ds_hide_login_look';
	const GROUP_PROTECT  = 'ds_hide_login_protect';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . DS_HIDE_LOGIN_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_post_ds_hide_login_unlock', array( $this, 'handle_unlock' ) );
	}

	public static function page_url( $tab = 'security' ) {
		return add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab ), admin_url( 'options-general.php' ) );
	}

	public function menu() {
		add_options_page( 'DS Hide Login', 'DS Hide Login', 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Cài đặt', 'ds-hide-login' ) . '</a>' );

		return $links;
	}

	public function register() {
		register_setting( self::GROUP_SECURITY, DS_Hide_Login_Options::OPT_SLUG, array( 'sanitize_callback' => array( $this, 'sanitize_slug' ) ) );
		register_setting( self::GROUP_SECURITY, DS_Hide_Login_Options::OPT_REDIRECT, array( 'sanitize_callback' => 'sanitize_title_with_dashes' ) );
		register_setting( self::GROUP_SECURITY, DS_Hide_Login_Options::OPT_HIDE_LINKS, array(
			'sanitize_callback' => function ( $value ) {
				return empty( $value ) ? 0 : 1;
			},
		) );
		register_setting( self::GROUP_LOOK, DS_Hide_Login_Options::OPT_APPEARANCE, array( 'sanitize_callback' => array( $this, 'sanitize_appearance' ) ) );
		register_setting( self::GROUP_PROTECT, DS_Hide_Login_Options::OPT_PROTECTION, array( 'sanitize_callback' => array( $this, 'sanitize_protection' ) ) );
	}

	public function sanitize_protection( $input ) {
		$input = is_array( $input ) ? $input : array();
		$old   = get_option( DS_Hide_Login_Options::OPT_PROTECTION, array() );
		$out   = array();

		foreach ( array( 'limit_enabled', 'captcha_lostpass', 'captcha_register', 'captcha_woocommerce', 'disable_xmlrpc' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$out['max_attempts']    = isset( $input['max_attempts'] ) ? max( 1, min( 50, absint( $input['max_attempts'] ) ) ) : 5;
		$out['lockout_minutes'] = isset( $input['lockout_minutes'] ) ? max( 1, min( 1440, absint( $input['lockout_minutes'] ) ) ) : 20;

		$out['ip_source']        = isset( $input['ip_source'] ) && array_key_exists( $input['ip_source'], DS_Hide_Login_Options::ip_sources() ) ? $input['ip_source'] : 'auto';
		$out['captcha_provider'] = isset( $input['captcha_provider'] ) && array_key_exists( $input['captcha_provider'], DS_Hide_Login_Options::captcha_providers() ) ? $input['captcha_provider'] : 'none';

		// Field bị khoá bằng hằng số không được gửi lên → giữ giá trị cũ.
		foreach ( DS_Hide_Login_Options::captcha_key_fields() as $fields ) {
			foreach ( $fields as $key ) {
				$out[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : ( isset( $old[ $key ] ) ? $old[ $key ] : '' );
			}
		}

		// Kiểm tra key của provider được chọn (tính cả key khai báo bằng hằng số).
		if ( 'none' !== $out['captcha_provider'] ) {
			$fields    = DS_Hide_Login_Options::captcha_key_fields()[ $out['captcha_provider'] ];
			$constants = DS_Hide_Login_Options::captcha_key_constants()[ $out['captcha_provider'] ];
			$has_site  = $out[ $fields[0] ] || ( defined( $constants[0] ) && constant( $constants[0] ) );
			$has_secr  = $out[ $fields[1] ] || ( defined( $constants[1] ) && constant( $constants[1] ) );

			if ( ! $has_site || ! $has_secr ) {
				add_settings_error( DS_Hide_Login_Options::OPT_PROTECTION, 'captcha_keys', sprintf(
					/* translators: %s: tên dịch vụ captcha */
					__( 'Cần nhập đủ Site key và Secret key để bật %s. Captcha đã được tắt.', 'ds-hide-login' ),
					DS_Hide_Login_Options::captcha_providers()[ $out['captcha_provider'] ]
				) );
				$out['captcha_provider'] = 'none';
			}
		}

		return $out;
	}

	/** Mở khoá IP từ trang cài đặt (1 IP hoặc tất cả). */
	public function handle_unlock() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'ds-hide-login' ), 403 );
		}
		check_admin_referer( 'ds_hide_login_unlock' );

		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';
		DS_Hide_Login_Limit_Login::unlock( $ip );
		do_action( 'ds_hide_login_admin_unlocked', $ip, get_current_user_id() );

		wp_safe_redirect( add_query_arg( 'ds-unlocked', 1, self::page_url( 'protection' ) ) );
		exit;
	}

	public function sanitize_slug( $value ) {
		$slug = sanitize_title_with_dashes( $value );

		if ( ! DS_Hide_Login_Options::is_valid_login_slug( $slug ) ) {
			add_settings_error( DS_Hide_Login_Options::OPT_SLUG, 'invalid', __( 'Slug đăng nhập không hợp lệ (trống, chứa wp-login hoặc trùng đường dẫn hệ thống). Đã giữ giá trị cũ.', 'ds-hide-login' ) );

			return get_option( DS_Hide_Login_Options::OPT_SLUG, 'login' );
		}

		return $slug;
	}

	/** Chỉ nhận các key đã biết, ép kiểu từng giá trị. */
	public function sanitize_appearance( $input ) {
		$defaults = DS_Hide_Login_Options::appearance_defaults();
		$input    = is_array( $input ) ? $input : array();
		$out      = array();

		$out['template'] = isset( $input['template'] ) && array_key_exists( $input['template'], DS_Hide_Login_Options::templates() )
			? $input['template'] : $defaults['template'];

		$out['logo_source']    = isset( $input['logo_source'] ) && array_key_exists( $input['logo_source'], DS_Hide_Login_Options::logo_sources() )
			? $input['logo_source'] : $defaults['logo_source'];
		$out['admin_bar_logo'] = empty( $input['admin_bar_logo'] ) ? 0 : 1;

		foreach ( array( 'logo_id', 'bg_image_id' ) as $key ) {
			$out[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : 0;
		}

		$out['logo_width']    = isset( $input['logo_width'] ) ? max( 40, min( 400, absint( $input['logo_width'] ) ) ) : $defaults['logo_width'];
		$out['border_radius'] = isset( $input['border_radius'] ) ? min( 40, absint( $input['border_radius'] ) ) : $defaults['border_radius'];

		foreach ( array( 'primary_color', 'bg_color', 'form_bg_color', 'text_color', 'button_text' ) as $key ) {
			$color       = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : '';
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}

		$out['logo_link']   = isset( $input['logo_link'] ) ? esc_url_raw( $input['logo_link'] ) : '';
		$out['logo_title']  = isset( $input['logo_title'] ) ? sanitize_text_field( $input['logo_title'] ) : '';
		$out['footer_text'] = isset( $input['footer_text'] ) ? wp_kses_post( $input['footer_text'] ) : '';

		return $out;
	}

	public function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'ds-hide-login-admin', DS_HIDE_LOGIN_URL . 'assets/css/admin-settings.css', array(), DS_HIDE_LOGIN_VERSION );
		wp_enqueue_script( 'ds-hide-login-admin', DS_HIDE_LOGIN_URL . 'assets/js/admin-settings.js', array( 'jquery', 'wp-color-picker' ), DS_HIDE_LOGIN_VERSION, true );
		wp_localize_script( 'ds-hide-login-admin', 'dsHideLogin', array(
			'chooseImage' => __( 'Chọn ảnh', 'ds-hide-login' ),
			'useImage'    => __( 'Dùng ảnh này', 'ds-hide-login' ),
		) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'security'   => __( 'URL đăng nhập', 'ds-hide-login' ),
			'protection' => __( 'Chống tấn công', 'ds-hide-login' ),
			'appearance' => __( 'Giao diện & logo', 'ds-hide-login' ),
		);
		$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'security';

		include DS_HIDE_LOGIN_DIR . 'views/settings-page.php';
	}
}
