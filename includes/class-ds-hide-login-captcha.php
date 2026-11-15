<?php
/**
 * Captcha cho form đăng nhập / quên mật khẩu / đăng ký: Google reCAPTCHA v2
 * hoặc Cloudflare Turnstile (chọn 1 trong cài đặt).
 *
 * Áp dụng cho form wp-login và (tuỳ chọn) form My Account của WooCommerce.
 * Chỉ xác minh request đến từ form có hiện widget, để không chặn XML-RPC / REST /
 * plugin khác gọi register_new_user(), retrieve_password()...
 * Lỗi mạng khi gọi dịch vụ: mặc định cho qua (fail-open) để không khoá admin
 * khỏi site; đổi bằng filter ds_hide_login_captcha_fail_open.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Captcha {

	/** @var array */
	private $opt;

	/** @var string recaptcha | turnstile */
	private $provider;

	/** @var array [site_key, secret_key] */
	private $keys;

	/** @var WP_Error|null Lỗi captcha của form login trong request này. */
	private $login_error = null;

	public function __construct() {
		$this->opt      = DS_Hide_Login_Options::protection();
		$this->provider = $this->opt['captcha_provider'];
		$this->keys     = self::keys( $this->opt, $this->provider );

		if ( ! self::is_active( $this->opt ) ) {
			return;
		}

		add_action( 'login_form', array( $this, 'render' ) );
		// 15: kiểm tra trước bước kiểm tra mật khẩu (20) để bỏ qua băm mật khẩu khi captcha sai;
		// 98: chốt lại lỗi, vì bộ lọc mật khẩu của WP / plugin khác bỏ qua WP_Error truyền vào.
		add_filter( 'authenticate', array( $this, 'verify_login' ), 15 );
		add_filter( 'authenticate', array( $this, 'enforce_login' ), 98 );

		if ( $this->opt['captcha_lostpass'] ) {
			add_action( 'lostpassword_form', array( $this, 'render' ) );
			add_action( 'lostpassword_post', array( $this, 'verify_lostpassword' ) );
		}

		if ( $this->opt['captcha_register'] ) {
			add_action( 'register_form', array( $this, 'render' ) );
			add_filter( 'registration_errors', array( $this, 'verify_register' ) );
		}

		if ( $this->opt['captcha_woocommerce'] ) {
			add_action( 'woocommerce_login_form', array( $this, 'render' ) );
			add_filter( 'woocommerce_process_login_errors', array( $this, 'verify_wc_errors' ) );

			if ( $this->opt['captcha_lostpass'] ) {
				add_action( 'woocommerce_lostpassword_form', array( $this, 'render' ) );
			}
			if ( $this->opt['captcha_register'] ) {
				add_action( 'woocommerce_register_form', array( $this, 'render' ) );
				add_filter( 'woocommerce_process_registration_errors', array( $this, 'verify_wc_errors' ) );
			}
		}
	}

	/** [site_key, secret_key] của 1 provider. */
	public static function keys( $opt, $provider ) {
		$fields = DS_Hide_Login_Options::captcha_key_fields();

		if ( ! isset( $fields[ $provider ] ) ) {
			return array( '', '' );
		}

		return array( $opt[ $fields[ $provider ][0] ], $opt[ $fields[ $provider ][1] ] );
	}

	public static function is_active( $opt ) {
		list( $site, $secret ) = self::keys( $opt, $opt['captcha_provider'] );

		return 'none' !== $opt['captcha_provider'] && $site && $secret;
	}

	/** Thông số từng dịch vụ (cùng giao thức siteverify: secret + response + remoteip). */
	private function service() {
		if ( 'turnstile' === $this->provider ) {
			return array(
				'name'   => 'Turnstile',
				'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
				'class'  => 'cf-turnstile',
				'field'  => 'cf-turnstile-response',
				'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			);
		}

		return array(
			'name'   => 'reCAPTCHA',
			'script' => 'https://www.google.com/recaptcha/api.js?hl=' . rawurlencode( substr( get_locale(), 0, 2 ) ),
			'class'  => 'g-recaptcha',
			'field'  => 'g-recaptcha-response',
			'verify' => 'https://www.google.com/recaptcha/api/siteverify',
		);
	}

	/** In widget; script được nạp ở footer nên dùng được cả trang login lẫn trang WooCommerce. */
	public function render() {
		$s = $this->service();

		wp_enqueue_script( 'ds-captcha', $s['script'], array(), null, true );
		printf(
			'<div class="ds-captcha"><div class="%s" data-sitekey="%s"></div></div>',
			esc_attr( $s['class'] ),
			esc_attr( $this->keys[0] )
		);
	}

	/** true nếu hợp lệ; WP_Error nếu không. */
	private function verify() {
		$s     = $this->service();
		$token = isset( $_POST[ $s['field'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $s['field'] ] ) ) : '';

		if ( '' === $token ) {
			return new WP_Error( 'ds_captcha_missing', __( '<strong>Lỗi:</strong> Vui lòng xác nhận bạn không phải người máy.', 'ds-hide-login' ) );
		}

		$response = wp_remote_post( $s['verify'], array(
			'timeout' => 5,
			'body'    => array(
				'secret'   => $this->keys[1],
				'response' => $token,
				'remoteip' => DS_Hide_Login_Client_IP::get(),
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return apply_filters( 'ds_hide_login_captcha_fail_open', true, $this->provider )
				? true
				/* translators: %s: tên dịch vụ captcha */
				: new WP_Error( 'ds_captcha_unreachable', sprintf( __( '<strong>Lỗi:</strong> Không kết nối được dịch vụ %s, vui lòng thử lại.', 'ds-hide-login' ), $s['name'] ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['success'] ) || ! $this->hostname_ok( $body ) ) {
			/* translators: %s: tên dịch vụ captcha */
			return new WP_Error( 'ds_captcha_invalid', sprintf( __( '<strong>Lỗi:</strong> Xác minh %s không hợp lệ, vui lòng thử lại.', 'ds-hide-login' ), $s['name'] ) );
		}

		return true;
	}

	/**
	 * Tuỳ chọn: token phải được giải trên đúng hostname (chặn token lấy từ site khác dùng chung key).
	 * Mặc định TẮT vì site có alias / domain mapping / wildcard key sẽ bị chặn nhầm; Google ("Verify origin")
	 * và Turnstile (danh sách hostname của widget) đã kiểm tra phía dịch vụ.
	 * Bật: add_filter( 'ds_hide_login_captcha_check_hostname', '__return_true' ).
	 */
	private function hostname_ok( $body ) {
		if ( ! apply_filters( 'ds_hide_login_captcha_check_hostname', false, $this->provider ) ) {
			return true;
		}

		$hosts = array();
		foreach ( array( home_url(), site_url() ) as $url ) {
			$host = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
			if ( $host ) {
				$hosts[] = $host;
				$hosts[] = 'www.' . $host; // site mở được bằng cả có và không có www
			}
		}
		$hosts = array_values( array_unique( $hosts ) );
		$hosts = (array) apply_filters( 'ds_hide_login_captcha_hostnames', $hosts, $this->provider );

		if ( ! $hosts || empty( $body['hostname'] ) ) {
			return true;
		}

		return in_array( strtolower( $body['hostname'] ), $hosts, true );
	}

	private function is_wp_login() {
		return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
	}

	/** Form login wp-login (field "log"); kênh khác (XML-RPC, WooCommerce...) không xử lý ở đây. */
	public function verify_login( $user ) {
		$this->login_error = null;

		if ( ! $this->is_wp_login() || ! isset( $_POST['log'] ) ) {
			return $user;
		}

		$result = $this->verify();
		if ( true === $result ) {
			return $user;
		}

		$this->login_error = $result;
		DS_Hide_Login_Limit_Login::skip_password_check();

		return $result;
	}

	public function enforce_login( $user ) {
		return $this->login_error ? $this->login_error : $user;
	}

	/** Quên mật khẩu: form wp-login, hoặc form WooCommerce khi bật captcha cho WooCommerce. */
	public function verify_lostpassword( $errors ) {
		$from_wc = isset( $_POST['wc_reset_password'] );

		if ( ! ( $this->is_wp_login() && ! $from_wc ) && ! ( $from_wc && $this->opt['captcha_woocommerce'] ) ) {
			return;
		}

		$result = $this->verify();
		if ( is_wp_error( $result ) && $errors instanceof WP_Error ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}
	}

	public function verify_register( $errors ) {
		if ( ! $this->is_wp_login() ) {
			return $errors;
		}

		$result = $this->verify();
		if ( is_wp_error( $result ) ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}

		return $errors;
	}

	/** Form login / đăng ký WooCommerce: thêm lỗi vào WP_Error mà WooCommerce truyền vào. */
	public function verify_wc_errors( $errors ) {
		$result = $this->verify();
		if ( is_wp_error( $result ) && $errors instanceof WP_Error ) {
			$errors->add( $result->get_error_code(), $result->get_error_message() );
		}

		return $errors;
	}
}
