<?php
/**
 * Giới hạn số lần đăng nhập sai theo IP.
 *
 * - Đếm lần sai bằng transient (cửa sổ = thời gian khoá).
 * - Khi vượt ngưỡng: ghi IP vào option ds_hide_login_lockouts (để admin xem & mở khoá).
 * - Chặn ở filter authenticate priority 99, tức là SAU khi WP kiểm tra mật khẩu,
 *   để kể cả mật khẩu đúng cũng bị từ chối khi IP đang bị khoá.
 * - Áp dụng cho mọi kênh dùng authenticate: wp-login, XML-RPC, form login WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Limit_Login {

	/** Giới hạn số IP lưu trong danh sách khoá để option không phình. */
	const MAX_LOCKOUT_ROWS = 500;

	/** @var array */
	private $opt;

	public function __construct() {
		$this->opt = DS_Hide_Login_Options::protection();

		if ( ! $this->opt['limit_enabled'] ) {
			return;
		}

		add_filter( 'authenticate', array( $this, 'check_lockout' ), 99, 1 );
		add_action( 'wp_login_failed', array( $this, 'record_failure' ) );
		add_action( 'wp_login', array( $this, 'reset_failures' ) );
	}

	/** IP client (tự nhận IP thật khi site chạy sau Cloudflare – xem DS_Hide_Login_Client_IP). */
	public static function client_ip() {
		return DS_Hide_Login_Client_IP::get();
	}

	private function key( $ip ) {
		return 'ds_hl_fail_' . md5( $ip );
	}

	private function window() {
		return max( 1, absint( $this->opt['lockout_minutes'] ) ) * MINUTE_IN_SECONDS;
	}

	private function max_attempts() {
		return max( 1, absint( $this->opt['max_attempts'] ) );
	}

	/** Danh sách khoá còn hiệu lực (đã lọc bản ghi hết hạn). */
	public static function lockouts() {
		$rows = get_option( DS_Hide_Login_Options::OPT_LOCKOUTS, array() );
		$now  = time();

		return array_filter( is_array( $rows ) ? $rows : array(), function ( $row ) use ( $now ) {
			return isset( $row['until'] ) && $row['until'] > $now;
		} );
	}

	private function locked_until( $ip ) {
		$rows = self::lockouts();

		return isset( $rows[ $ip ] ) ? (int) $rows[ $ip ]['until'] : 0;
	}

	public function check_lockout( $user ) {
		$until = $this->locked_until( self::client_ip() );

		if ( $until ) {
			$minutes = max( 1, (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS ) );

			return new WP_Error( 'ds_locked_out', sprintf(
				/* translators: %d: số phút */
				__( '<strong>Lỗi:</strong> Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau %d phút.', 'ds-hide-login' ),
				$minutes
			) );
		}

		// Báo số lần còn lại để người dùng thật biết dừng lại.
		if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'incorrect_password', 'invalid_username', 'invalid_email' ) ) ) {
			$left = $this->max_attempts() - ( (int) get_transient( $this->key( self::client_ip() ) ) + 1 );
			if ( $left > 0 ) {
				/* translators: %d: số lần còn lại */
				$user->add( 'ds_attempts_left', sprintf( __( 'Còn %d lần thử trước khi bị tạm khoá.', 'ds-hide-login' ), $left ) );
			}
		}

		return $user;
	}

	public function record_failure() {
		$ip = self::client_ip();

		// Đang bị khoá thì không đếm tiếp (tránh tự gia hạn khoá vô hạn).
		if ( $this->locked_until( $ip ) ) {
			return;
		}

		$count = (int) get_transient( $this->key( $ip ) ) + 1;

		if ( $count >= $this->max_attempts() ) {
			$this->lock( $ip );
			delete_transient( $this->key( $ip ) );

			return;
		}

		set_transient( $this->key( $ip ), $count, $this->window() );
	}

	private function lock( $ip ) {
		$rows        = self::lockouts();
		$rows[ $ip ] = array(
			'until' => time() + $this->window(),
			'time'  => time(),
		);

		// Giữ các bản ghi mới nhất.
		if ( count( $rows ) > self::MAX_LOCKOUT_ROWS ) {
			uasort( $rows, function ( $a, $b ) {
				return $b['time'] - $a['time'];
			} );
			$rows = array_slice( $rows, 0, self::MAX_LOCKOUT_ROWS, true );
		}

		update_option( DS_Hide_Login_Options::OPT_LOCKOUTS, $rows, false );
		do_action( 'ds_hide_login_locked_out', $ip, $rows[ $ip ]['until'] );
	}

	public function reset_failures() {
		delete_transient( $this->key( self::client_ip() ) );
	}

	/** Mở khoá 1 IP, hoặc tất cả khi $ip rỗng. */
	public static function unlock( $ip = '' ) {
		$rows = $ip ? self::lockouts() : array();
		unset( $rows[ $ip ] );
		update_option( DS_Hide_Login_Options::OPT_LOCKOUTS, $rows, false );
	}
}
