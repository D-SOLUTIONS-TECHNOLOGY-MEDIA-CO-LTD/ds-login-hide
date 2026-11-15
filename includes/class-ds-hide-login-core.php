<?php
/**
 * Lõi bảo mật: chặn wp-login.php / wp-admin, phục vụ form login tại slug mới,
 * và viết lại mọi link wp-login.php thành URL mới.
 *
 * Không thêm rewrite rule: chỉ chặn request ở plugins_loaded rồi xử lý ở wp_loaded,
 * nên tắt plugin là site trở về nguyên trạng.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Core {

	/** Request đang trỏ vào wp-login.php / wp-register.php gốc → trả trang 404 của theme. */
	private $wp_login_php = false;

	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'route_request' ), 9999 );
		add_action( 'setup_theme', array( $this, 'block_customizer' ), 1 );
		add_action( 'init', array( $this, 'block_signup' ) );
		add_action( 'wp_loaded', array( $this, 'handle_request' ) );
		add_action( 'template_redirect', array( $this, 'redirect_export_data' ) );
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		add_filter( 'site_url', array( $this, 'filter_wp_login_php' ), 10, 3 );
		add_filter( 'network_site_url', array( $this, 'filter_wp_login_php' ), 10, 3 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ), 10, 2 );
		add_filter( 'login_url', array( $this, 'login_url' ), 10, 3 );
		add_filter( 'site_option_welcome_email', array( $this, 'welcome_email' ) );
		add_filter( 'user_request_action_email_content', array( $this, 'user_request_email' ), 999, 2 );
		add_filter( 'site_status_tests', array( $this, 'site_status_tests' ) );

		// Không để lộ URL đăng nhập mới qua link công khai (bình luận, widget Meta, đăng ký...).
		add_filter( 'login_url', array( $this, 'mask_public_login_url' ), 99, 3 );
		add_filter( 'register_url', array( $this, 'mask_public_link' ), 99 );
		add_filter( 'lostpassword_url', array( $this, 'mask_public_link' ), 99 );
	}

	private function request_path() {
		// Gộp dấu "/" đầu: "//wp-login.php" bị parse_url hiểu là host, nhưng web server vẫn chạy wp-login.php.
		$uri     = '/' . ltrim( rawurldecode( $_SERVER['REQUEST_URI'] ), '/' );
		$request = wp_parse_url( $uri );

		return isset( $request['path'] ) ? $request['path'] : '';
	}

	private function query_suffix() {
		return ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '';
	}

	/** Đánh dấu request: slug mới → giả làm wp-login.php; file gốc → giả làm trang 404. */
	public function route_request() {
		global $pagenow;

		$path = untrailingslashit( $this->request_path() );
		$slug = DS_Hide_Login_Options::login_slug();

		$hits_new = home_url( $slug, 'relative' ) === $path
			|| ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $slug ] ) && empty( $_GET[ $slug ] ) );

		// So khớp trên PATH (không tính query string: /?s=wp-login.php là trang tìm kiếm bình thường).
		// stripos: server không phân biệt hoa thường (IIS, macOS) vẫn chạy được /WP-LOGIN.PHP.
		$hits_original = false !== stripos( $path, 'wp-login.php' ) || site_url( 'wp-login', 'relative' ) === $path
			|| false !== stripos( $path, 'wp-register.php' ) || site_url( 'wp-register', 'relative' ) === $path;

		if ( $hits_new ) {
			$_SERVER['SCRIPT_NAME'] = $slug;
			$pagenow                = 'wp-login.php';
		} elseif ( $hits_original && ! is_admin() ) {
			$this->wp_login_php     = true;
			$_SERVER['REQUEST_URI'] = DS_Hide_Login_Options::user_trailingslashit( '/' . str_repeat( '-/', 10 ) );
			$pagenow                = 'index.php';
		}
	}

	public function handle_request() {
		global $pagenow;

		$request = wp_parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ) );
		$path    = isset( $request['path'] ) ? $request['path'] : '';

		do_action( 'ds_hide_login_before_redirect', $request );

		// Form mật khẩu bài viết vẫn post vào wp-login.php?action=postpass.
		$is_postpass = isset( $_REQUEST['action'], $_POST['post_password'] )
			&& is_string( $_REQUEST['action'] )
			&& 'postpass' === wp_unslash( $_REQUEST['action'] )
			&& ( ! isset( $_POST['action'] ) || ( is_string( $_POST['action'] ) && 'postpass' === wp_unslash( $_POST['action'] ) ) );

		if ( $is_postpass ) {
			return;
		}

		$logged_in = is_user_logged_in();

		// Khách vào wp-admin → đẩy về redirect slug.
		if ( is_admin() && ! $logged_in && ! defined( 'WP_CLI' ) && ! wp_doing_ajax() && ! wp_doing_cron()
			&& 'admin-post.php' !== $pagenow && '/wp-admin/options.php' !== $path ) {
			wp_safe_redirect( DS_Hide_Login_Options::redirect_url() );
			die();
		}

		if ( ! $logged_in && ( ( isset( $_GET['wc-ajax'] ) && 'profile.php' === $pagenow ) || '/wp-admin/options.php' === $path ) ) {
			wp_safe_redirect( DS_Hide_Login_Options::redirect_url() );
			die();
		}

		// Chuẩn hoá trailing slash cho URL login mới.
		if ( 'wp-login.php' === $pagenow && $path && $path !== DS_Hide_Login_Options::user_trailingslashit( $path ) && get_option( 'permalink_structure' ) ) {
			wp_safe_redirect( DS_Hide_Login_Options::user_trailingslashit( DS_Hide_Login_Options::login_url() ) . $this->query_suffix() );
			die();
		}

		if ( $this->wp_login_php ) {
			$this->maybe_redirect_after_activation();
			$this->render_404();
		}

		if ( 'wp-login.php' === $pagenow ) {
			// Các global mà wp-login.php cần có sẵn trong scope.
			global $error, $interim_login, $action, $user_login;

			if ( $logged_in && ! isset( $_REQUEST['action'] ) ) {
				$requested = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
				wp_safe_redirect( apply_filters( 'ds_hide_login_logged_in_redirect', admin_url(), $requested, wp_get_current_user() ) );
				die();
			}

			require_once ABSPATH . 'wp-login.php';
			die();
		}
	}

	/** Multisite: sau khi kích hoạt từ wp-activate.php → về trang login mới. */
	private function maybe_redirect_after_activation() {
		$referer = wp_get_referer();
		if ( ! is_multisite() || ! $referer || false === strpos( $referer, 'wp-activate.php' ) ) {
			return;
		}

		$parts = wp_parse_url( $referer );
		if ( empty( $parts['query'] ) ) {
			return;
		}

		parse_str( $parts['query'], $query );
		require_once ABSPATH . WPINC . '/ms-functions.php';

		if ( ! empty( $query['key'] ) ) {
			$result = wpmu_activate_signup( $query['key'] );
			if ( is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'already_active', 'blog_taken' ), true ) ) {
				wp_safe_redirect( DS_Hide_Login_Options::login_url() . $this->query_suffix() );
				die();
			}
		}
	}

	/** Render template 404 của theme thay cho wp-login.php gốc. */
	private function render_404() {
		global $pagenow;

		$pagenow = 'index.php';
		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		wp();
		require_once ABSPATH . WPINC . '/template-loader.php';
		die();
	}

	public function block_customizer() {
		global $pagenow;

		if ( 'customize.php' === $pagenow && ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Tính năng này đã bị tắt.', 'ds-hide-login' ), 403 );
		}
	}

	/** Site đơn: chặn wp-signup.php / wp-activate.php (mở lại bằng filter ds_hide_login_signup_enable). */
	public function block_signup() {
		$path = $this->request_path();

		if ( ! is_multisite()
			&& ( false !== stripos( $path, 'wp-signup.php' ) || false !== stripos( $path, 'wp-activate.php' ) )
			&& false === apply_filters( 'ds_hide_login_signup_enable', false ) ) {
			wp_die( esc_html__( 'Tính năng này chưa được bật.', 'ds-hide-login' ), '', array( 'response' => 403 ) );
		}
	}

	/** Link xác nhận export/erase dữ liệu cá nhân → chuyển sang URL login mới. */
	public function redirect_export_data() {
		if ( isset( $_GET['action'], $_GET['request_id'], $_GET['confirm_key'] ) && 'confirmaction' === $_GET['action'] ) {
			$request_id = (int) $_GET['request_id'];
			$key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );

			if ( ! is_wp_error( wp_validate_user_request_key( $request_id, $key ) ) ) {
				wp_safe_redirect( add_query_arg( array(
					'action'      => 'confirmaction',
					'request_id'  => $request_id,
					'confirm_key' => rawurlencode( $key ),
				), DS_Hide_Login_Options::login_url() ) );
				exit();
			}
		}
	}

	/** Thay link wp-login.php do WP/plugin khác sinh ra bằng URL login mới. */
	public function filter_wp_login_php( $url, $path = '', $scheme = null ) {
		global $pagenow;

		$origin_url = $url;

		if ( false !== strpos( $url, 'wp-login.php?action=postpass' ) || ( is_multisite() && 'install.php' === $pagenow ) ) {
			return $url;
		}

		if ( false !== strpos( $url, 'wp-login.php' ) && false === strpos( (string) wp_get_referer(), 'wp-login.php' ) ) {
			if ( is_ssl() ) {
				$scheme = 'https';
			}

			$parts = explode( '?', $url );

			if ( isset( $parts[1] ) ) {
				parse_str( $parts[1], $args );
				if ( isset( $args['login'] ) ) {
					$args['login'] = rawurlencode( $args['login'] );
				}
				$url = add_query_arg( $args, DS_Hide_Login_Options::login_url( $scheme ) );
			} else {
				$url = DS_Hide_Login_Options::login_url( $scheme );
			}
		}

		if ( isset( $_POST['post_password'] ) && ! is_user_logged_in() ) {
			global $current_user;
			if ( is_wp_error( wp_authenticate_username_password( null, $current_user->user_login, $_POST['post_password'] ) ) ) {
				return $origin_url;
			}
		}

		// Gravity Forms preview/ajax cần URL gốc.
		if ( ! is_user_logged_in() && isset( $_GET['gf_page'] ) && class_exists( 'GFForms' ) ) {
			return $origin_url;
		}

		return $url;
	}

	public function filter_redirect( $location, $status ) {
		if ( false !== strpos( $location, 'https://wordpress.com/wp-login.php' ) ) {
			return $location;
		}

		return $this->filter_wp_login_php( $location );
	}

	/** Không lộ URL login trên trang 404; reauth từ options.php → về admin_url(). */
	public function login_url( $login_url, $redirect, $force_reauth ) {
		if ( is_404() ) {
			return '#';
		}

		if ( false === $force_reauth || empty( $redirect ) ) {
			return $login_url;
		}

		$redirect = explode( '?', $redirect );
		if ( admin_url( 'options.php' ) === $redirect[0] ) {
			$login_url = admin_url();
		}

		return $login_url;
	}

	/**
	 * Khách (chưa đăng nhập) đang xem trang công khai: link trỏ tới URL login mới được thay bằng
	 * trang Tài khoản WooCommerce (nếu có) hoặc trang chủ. Trang login, wp-admin, email reset mật khẩu
	 * không bị ảnh hưởng. Tắt trong cài đặt hoặc đổi đích qua filter ds_hide_login_public_login_url.
	 */
	/** auth_redirect() (force_reauth = true) phải giữ URL login thật, nếu không plugin "bắt đăng nhập" sẽ lặp redirect. */
	public function mask_public_login_url( $url, $redirect = '', $force_reauth = false ) {
		return $force_reauth ? $url : $this->mask_public_link( $url );
	}

	public function mask_public_link( $url ) {
		global $pagenow;

		if ( ! DS_Hide_Login_Options::hide_links() || 'wp-login.php' === $pagenow || is_admin() || is_user_logged_in() ) {
			return $url;
		}

		$slug = DS_Hide_Login_Options::login_slug();
		if ( false === strpos( (string) $url, '/' . $slug ) && false === strpos( (string) $url, '?' . $slug ) ) {
			return $url;
		}

		$target = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );

		return apply_filters( 'ds_hide_login_public_login_url', $target ? $target : home_url( '/' ), $url );
	}

	public function welcome_email( $value ) {
		return str_replace( 'wp-login.php', trailingslashit( DS_Hide_Login_Options::login_slug() ), $value );
	}

	public function user_request_email( $email_text, $email_data ) {
		$confirm = str_replace( DS_Hide_Login_Options::login_slug() . '/', 'wp-login.php', $email_data['confirm_url'] );

		return str_replace( '###CONFIRM_URL###', esc_url_raw( $confirm ), $email_text );
	}

	/** Loopback test của Site Health luôn fail khi wp-login bị ẩn → bỏ test này. */
	public function site_status_tests( $tests ) {
		unset( $tests['async']['loopback_requests'] );

		return $tests;
	}
}
