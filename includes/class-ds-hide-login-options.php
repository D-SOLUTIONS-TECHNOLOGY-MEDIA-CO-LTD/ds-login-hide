<?php
/**
 * Đọc/ghi cấu hình + dựng URL login/redirect.
 *
 * Thứ tự ưu tiên slug: hằng số wp-config (DS_HIDE_LOGIN_SLUG / DS_HIDE_LOGIN_REDIRECT)
 * > option ds_hide_login_page / ds_hide_login_redirect > mặc định 'login' / '404'.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Options {

	const OPT_SLUG       = 'ds_hide_login_page';
	const OPT_REDIRECT   = 'ds_hide_login_redirect';
	const OPT_APPEARANCE = 'ds_hide_login_appearance';
	const OPT_PROTECTION = 'ds_hide_login_protection';
	const OPT_LOCKOUTS   = 'ds_hide_login_lockouts';

	/** Danh sách template login hợp lệ: key => nhãn. */
	public static function templates() {
		return array(
			'default' => __( 'Mặc định WordPress', 'ds-hide-login' ),
			'classic' => __( 'Classic – thẻ giữa màn hình', 'ds-hide-login' ),
			'split'   => __( 'Split – chia đôi, panel thương hiệu bên trái', 'ds-hide-login' ),
			'glass'   => __( 'Glass – nền ảnh/gradient, form kính mờ', 'ds-hide-login' ),
		);
	}

	/** Nguồn logo: site = lấy từ cấu hình site, custom = ảnh upload riêng, wordpress = giữ logo WP. */
	public static function logo_sources() {
		return array(
			'site'      => __( 'Lấy từ cấu hình site (Custom Logo của theme → Site Icon)', 'ds-hide-login' ),
			'custom'    => __( 'Ảnh tải lên riêng', 'ds-hide-login' ),
			'wordpress' => __( 'Giữ logo WordPress', 'ds-hide-login' ),
		);
	}

	public static function appearance_defaults() {
		return array(
			'template'       => 'classic',
			'logo_source'    => 'site',
			'logo_id'        => 0,
			'admin_bar_logo' => 1,
			'logo_width'     => 120,
			'logo_link'      => '',      // rỗng = trang chủ
			'logo_title'     => '',      // rỗng = tên site
			'bg_image_id'    => 0,
			'primary_color'  => '#2563eb',
			'bg_color'       => '#f1f5f9',
			'form_bg_color'  => '#ffffff',
			'text_color'     => '#0f172a',
			'button_text'    => '#ffffff',
			'border_radius'  => 12,
			'footer_text'    => '',
		);
	}

	public static function appearance() {
		$saved = get_option( self::OPT_APPEARANCE, array() );
		$saved = is_array( $saved ) ? $saved : array();

		// Cấu hình cũ chưa có logo_source nhưng đã upload logo → giữ ảnh upload.
		if ( ! isset( $saved['logo_source'] ) && ! empty( $saved['logo_id'] ) ) {
			$saved['logo_source'] = 'custom';
		}

		return wp_parse_args( $saved, self::appearance_defaults() );
	}

	/**
	 * ID attachment của logo thương hiệu đang dùng, 0 nếu giữ logo WordPress.
	 * custom (chưa chọn ảnh) và site đều rơi về: Custom Logo của theme → Site Icon.
	 */
	public static function brand_logo_id() {
		$o = self::appearance();

		if ( 'wordpress' === $o['logo_source'] ) {
			return 0;
		}
		if ( 'custom' === $o['logo_source'] && $o['logo_id'] ) {
			return absint( $o['logo_id'] );
		}

		$id = absint( get_theme_mod( 'custom_logo' ) );

		return $id ? $id : absint( get_option( 'site_icon' ) );
	}

	public static function protection_defaults() {
		return array(
			'limit_enabled'        => 1,
			'max_attempts'         => 5,
			'lockout_minutes'      => 20,
			'ip_source'            => 'auto',
			'captcha_provider'     => 'none',
			'captcha_site_key'     => '', // reCAPTCHA (giữ tên key cũ để tương thích 1.1.0)
			'captcha_secret_key'   => '',
			'turnstile_site_key'   => '',
			'turnstile_secret_key' => '',
			'captcha_lostpass'     => 1,
			'captcha_register'     => 1,
			'captcha_woocommerce'  => 1,
		);
	}

	/** Dịch vụ captcha hỗ trợ: key => nhãn. */
	public static function captcha_providers() {
		return array(
			'none'      => __( 'Tắt', 'ds-hide-login' ),
			'recaptcha' => __( 'Google reCAPTCHA v2 (checkbox)', 'ds-hide-login' ),
			'turnstile' => __( 'Cloudflare Turnstile', 'ds-hide-login' ),
		);
	}

	/** Cách xác định IP người dùng (dùng cho giới hạn đăng nhập và captcha). */
	public static function ip_sources() {
		return array(
			'auto'        => __( 'Tự động – dùng IP thật từ Cloudflare khi request đến từ dải IP của Cloudflare (khuyên dùng)', 'ds-hide-login' ),
			'cloudflare'  => __( 'Luôn tin header CF-Connecting-IP – chỉ dùng khi server CHỈ nhận traffic qua Cloudflare (vd. Cloudflare Tunnel)', 'ds-hide-login' ),
			'remote_addr' => __( 'Chỉ dùng REMOTE_ADDR – site không dùng Cloudflare/proxy', 'ds-hide-login' ),
		);
	}

	/** Hằng số wp-config khoá key captcha: provider => [site, secret]. */
	public static function captcha_key_constants() {
		return array(
			'recaptcha' => array( 'DS_HIDE_LOGIN_RECAPTCHA_SITE_KEY', 'DS_HIDE_LOGIN_RECAPTCHA_SECRET_KEY' ),
			'turnstile' => array( 'DS_HIDE_LOGIN_TURNSTILE_SITE_KEY', 'DS_HIDE_LOGIN_TURNSTILE_SECRET_KEY' ),
		);
	}

	/** Tên field lưu key captcha: provider => [site, secret]. */
	public static function captcha_key_fields() {
		return array(
			'recaptcha' => array( 'captcha_site_key', 'captcha_secret_key' ),
			'turnstile' => array( 'turnstile_site_key', 'turnstile_secret_key' ),
		);
	}

	/** Cấu hình chống tấn công; key captcha có thể khoá bằng hằng số wp-config. */
	public static function protection() {
		$saved = get_option( self::OPT_PROTECTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		// Cấu hình 1.1.0: captcha_enabled = 1 nghĩa là reCAPTCHA.
		if ( ! isset( $saved['captcha_provider'] ) ) {
			$saved['captcha_provider'] = empty( $saved['captcha_enabled'] ) ? 'none' : 'recaptcha';
		}

		$o      = wp_parse_args( $saved, self::protection_defaults() );
		$fields = self::captcha_key_fields();

		foreach ( self::captcha_key_constants() as $provider => $constants ) {
			foreach ( $constants as $i => $constant ) {
				if ( defined( $constant ) && constant( $constant ) ) {
					$o[ $fields[ $provider ][ $i ] ] = constant( $constant );
				}
			}
		}

		return $o;
	}

	public static function slug_locked() {
		return defined( 'DS_HIDE_LOGIN_SLUG' ) && DS_HIDE_LOGIN_SLUG;
	}

	public static function redirect_locked() {
		return defined( 'DS_HIDE_LOGIN_REDIRECT' ) && DS_HIDE_LOGIN_REDIRECT;
	}

	public static function login_slug() {
		if ( self::slug_locked() ) {
			return sanitize_title_with_dashes( DS_HIDE_LOGIN_SLUG );
		}
		$slug = get_option( self::OPT_SLUG );

		return $slug ? $slug : 'login';
	}

	public static function redirect_slug() {
		if ( self::redirect_locked() ) {
			return sanitize_title_with_dashes( DS_HIDE_LOGIN_REDIRECT );
		}
		$slug = get_option( self::OPT_REDIRECT );

		return $slug ? $slug : '404';
	}

	public static function use_trailing_slashes() {
		return '/' === substr( (string) get_option( 'permalink_structure' ), -1, 1 );
	}

	public static function user_trailingslashit( $string ) {
		return self::use_trailing_slashes() ? trailingslashit( $string ) : untrailingslashit( $string );
	}

	private static function build_url( $slug, $home ) {
		if ( get_option( 'permalink_structure' ) ) {
			return self::user_trailingslashit( $home . $slug );
		}

		return $home . '?' . $slug;
	}

	public static function login_url( $scheme = null ) {
		$home = apply_filters( 'ds_hide_login_home_url', home_url( '/', $scheme ) );

		return self::build_url( self::login_slug(), $home );
	}

	public static function redirect_url( $scheme = null ) {
		return self::build_url( self::redirect_slug(), home_url( '/', $scheme ) );
	}

	/** Slug không được trùng query var của WP hoặc đường dẫn core (sẽ khoá admin). */
	public static function is_valid_login_slug( $slug ) {
		if ( '' === $slug || false !== strpos( $slug, 'wp-login' ) ) {
			return false;
		}
		$wp        = new WP();
		$forbidden = array_merge( $wp->public_query_vars, $wp->private_query_vars, array( 'wp-admin', 'wp-content', 'wp-includes' ) );

		return ! in_array( $slug, $forbidden, true );
	}

	/** Kích hoạt: chuyển cấu hình từ WPS Hide Login (whl_*) nếu có, để giữ nguyên URL login cũ. */
	public static function activate() {
		if ( ! get_option( self::OPT_SLUG ) && get_option( 'whl_page' ) ) {
			update_option( self::OPT_SLUG, get_option( 'whl_page' ) );
		}
		if ( ! get_option( self::OPT_REDIRECT ) && get_option( 'whl_redirect_admin' ) ) {
			update_option( self::OPT_REDIRECT, get_option( 'whl_redirect_admin' ) );
		}
		if ( false === get_option( self::OPT_APPEARANCE ) ) {
			add_option( self::OPT_APPEARANCE, self::appearance_defaults() );
		}
		if ( false === get_option( self::OPT_PROTECTION ) ) {
			add_option( self::OPT_PROTECTION, self::protection_defaults() );
		}
		// Danh sách IP bị khoá: không autoload vì chỉ cần khi đăng nhập.
		if ( false === get_option( self::OPT_LOCKOUTS ) ) {
			add_option( self::OPT_LOCKOUTS, array(), '', 'no' );
		}
	}
}
