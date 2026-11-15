<?php
/**
 * Giao diện trang login: logo thương hiệu, template (classic/split/glass), màu sắc.
 *
 * Màu được xuất ra CSS variables (--ds-*) nên 1 file CSS dùng chung cho mọi template;
 * template chỉ là class trên <body> (ds-tpl-{key}).
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Appearance {

	/** @var array */
	private $opt;

	public function __construct() {
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'login_body_class', array( $this, 'body_class' ) );
		add_filter( 'login_headerurl', array( $this, 'header_url' ) );
		add_filter( 'login_headertext', array( $this, 'header_text' ) );
		add_action( 'login_footer', array( $this, 'footer_text' ) );

		// Thay logo WordPress trên admin bar (cả admin lẫn frontend).
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_logo' ), 11 );
		add_action( 'admin_head', array( $this, 'admin_bar_logo_css' ) );
		add_action( 'wp_head', array( $this, 'admin_bar_logo_css' ) );
	}

	private function opt() {
		if ( null === $this->opt ) {
			$this->opt = DS_Hide_Login_Options::appearance();
		}

		return $this->opt;
	}

	public function body_class( $classes ) {
		$classes[] = 'ds-tpl-' . sanitize_html_class( $this->opt()['template'] );

		return $classes;
	}

	public function enqueue() {
		$o = $this->opt();

		wp_register_style( 'ds-hide-login', false, array( 'login' ), DS_HIDE_LOGIN_VERSION );
		if ( 'default' !== $o['template'] ) {
			wp_enqueue_style( 'ds-hide-login-templates', DS_HIDE_LOGIN_URL . 'assets/css/login-templates.css', array( 'login' ), DS_HIDE_LOGIN_VERSION );
		}
		wp_enqueue_style( 'ds-hide-login' );
		wp_add_inline_style( 'ds-hide-login', $this->inline_css( $o ) );
	}

	/** CSS variables + logo. Mọi giá trị đã được sanitize khi lưu, escape lại ở đây cho chắc. */
	private function inline_css( $o ) {
		$bg_image = $o['bg_image_id'] ? wp_get_attachment_image_url( $o['bg_image_id'], 'full' ) : '';

		$vars = array(
			'--ds-primary'   => sanitize_hex_color( $o['primary_color'] ),
			'--ds-bg'        => sanitize_hex_color( $o['bg_color'] ),
			'--ds-form-bg'   => sanitize_hex_color( $o['form_bg_color'] ),
			'--ds-text'      => sanitize_hex_color( $o['text_color'] ),
			'--ds-btn-text'  => sanitize_hex_color( $o['button_text'] ),
			'--ds-radius'    => absint( $o['border_radius'] ) . 'px',
			'--ds-bg-image'  => $bg_image ? 'url("' . esc_url( $bg_image ) . '")' : 'none',
		);

		$css = ':root{';
		foreach ( $vars as $name => $value ) {
			if ( $value ) {
				$css .= $name . ':' . $value . ';';
			}
		}
		$css .= '}';

		$logo_id = DS_Hide_Login_Options::brand_logo_id();
		$logo    = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : false;
		if ( $logo ) {
			$width  = max( 40, min( 400, absint( $o['logo_width'] ) ) );
			$height = $logo[1] ? round( $width * $logo[2] / $logo[1] ) : $width;
			$css   .= sprintf(
				'.login h1 a{background-image:url("%s");background-size:contain;background-position:center;width:%dpx;height:%dpx;max-width:100%%;}',
				esc_url( $logo[0] ),
				$width,
				$height
			);
		}

		// Widget captcha rộng ~300px, form mặc định của WP hẹp hơn → thu nhỏ cho vừa.
		$css .= 'body.ds-tpl-default .ds-captcha>div{transform:scale(.88);transform-origin:0 0;}';
		$css .= '.login .ds-captcha{margin:0 0 16px;}';

		return $css;
	}

	/** Đổi node wp-logo (menu "About WordPress") thành logo thương hiệu trỏ về trang chủ. */
	public function admin_bar_logo( $wp_admin_bar ) {
		$logo_id = DS_Hide_Login_Options::brand_logo_id();
		if ( ! $this->opt()['admin_bar_logo'] || ! $logo_id ) {
			return;
		}

		$src = wp_get_attachment_image_url( $logo_id, 'medium' );
		if ( ! $src ) {
			return;
		}

		$wp_admin_bar->remove_node( 'wp-logo' );
		$wp_admin_bar->add_node( array(
			'id'    => 'ds-brand-logo',
			'title' => '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '">',
			'href'  => home_url( '/' ),
			'meta'  => array( 'title' => get_bloginfo( 'name' ) ),
		) );
	}

	public function admin_bar_logo_css() {
		if ( ! is_admin_bar_showing() || ! $this->opt()['admin_bar_logo'] ) {
			return;
		}
		echo '<style id="ds-hide-login-adminbar">#wpadminbar #wp-admin-bar-ds-brand-logo>.ab-item{display:flex;align-items:center;padding:0 8px;}'
			. '#wpadminbar #wp-admin-bar-ds-brand-logo img{max-height:20px;max-width:90px;width:auto;height:auto;}</style>';
	}

	public function header_url( $url ) {
		$link = $this->opt()['logo_link'];

		return $link ? esc_url( $link ) : home_url( '/' );
	}

	public function header_text( $text ) {
		$title = $this->opt()['logo_title'];

		return $title ? esc_html( $title ) : get_bloginfo( 'name' );
	}

	public function footer_text() {
		$text = $this->opt()['footer_text'];
		if ( $text ) {
			echo '<div class="ds-login-footer">' . wp_kses_post( $text ) . '</div>';
		}
	}
}
