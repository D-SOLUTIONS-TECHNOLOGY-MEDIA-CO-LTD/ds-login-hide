<?php
/**
 * Giới hạn số lần đăng nhập sai theo IP (IPv6 tính theo cả dải /64).
 *
 * Cơ chế "đặt chỗ" lượt thử:
 * 1. authenticate priority 1: mỗi lần thử có username + mật khẩu được cấp một số thứ tự
 *    bằng phép tăng ATOMIC (DS_Hide_Login_Counter), trước khi WP kiểm tra mật khẩu.
 * 2. authenticate priority 99: IP đang bị khoá → từ chối kể cả khi mật khẩu đúng.
 *    Số thứ tự vượt ngưỡng (request song song) → từ chối tạm thời, bỏ qua kiểm tra mật khẩu,
 *    trả lại lượt và KHÔNG khoá (để client hợp lệ mở nhiều kết nối không tự khoá IP).
 * 3. Chỉ khoá khi số lần SAI thật sự chạm ngưỡng (wp_login_failed).
 * 4. Đăng nhập đúng chỉ trả lại lượt của chính request đó; các lần sai trước vẫn được tính
 *    đến hết cửa sổ thời gian → không thể đăng nhập tài khoản của mình để "xoá" lượt dò admin.
 * 5. Lỗi DB/cache khi đếm → từ chối tạm thời (fail-closed).
 * Áp dụng cho các kênh gọi wp_authenticate(): wp-login, XML-RPC, form login WooCommerce.
 * (Application password qua REST không đi qua authenticate – mật khẩu ứng dụng 24 ký tự ngẫu nhiên.)
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Limit_Login {

	/** Số IP tối đa giữ trong danh sách hiển thị (khoá thật lưu riêng từng IP, không bị giới hạn này). */
	const MAX_LOCKOUT_ROWS = 500;

	/** @var array */
	private $opt;

	/** @var string Bucket IP của lần thử đang xử lý ('' = request không phải lần thử đăng nhập). */
	private $bucket = '';

	/** @var int|false|null Số thứ tự lượt thử đã cấp (false = lỗi bộ đếm, null = không có lượt). */
	private $attempt = null;

	/** @var bool Lượt bị từ chối tạm thời (vượt ngưỡng do song song / lỗi bộ đếm), không tính là sai. */
	private $rejected = false;

	public function __construct() {
		$this->opt = DS_Hide_Login_Options::protection();

		if ( ! $this->opt['limit_enabled'] ) {
			return;
		}

		add_filter( 'authenticate', array( $this, 'reserve_attempt' ), 1, 3 );
		add_filter( 'authenticate', array( $this, 'enforce' ), 99, 1 );
		add_action( 'wp_login_failed', array( $this, 'record_failure' ) );
	}

	/** IP client (tự nhận IP thật khi site chạy sau Cloudflare – xem DS_Hide_Login_Client_IP). */
	public static function client_ip() {
		return DS_Hide_Login_Client_IP::get();
	}

	/** Đơn vị bị đếm/khoá: IPv4 nguyên IP, IPv6 cả dải /64. */
	public static function bucket( $ip = null ) {
		return DS_Hide_Login_Client_IP::bucket( null === $ip ? self::client_ip() : $ip );
	}

	private static function ip_key( $bucket ) {
		return DS_Hide_Login_Counter::PREFIX . 'ip_' . md5( $bucket );
	}

	private static function lock_key( $bucket ) {
		return DS_Hide_Login_Counter::PREFIX . 'lock_' . md5( $bucket );
	}

	private function window() {
		return max( 1, absint( $this->opt['lockout_minutes'] ) ) * MINUTE_IN_SECONDS;
	}

	private function max_attempts() {
		return max( 1, absint( $this->opt['max_attempts'] ) );
	}

	/**
	 * Bỏ bước kiểm tra mật khẩu của WP cho request này (đang bị khoá / captcha sai):
	 * tiết kiệm CPU băm mật khẩu và không cho kết quả mật khẩu "lọt" ra ngoài.
	 * Các bộ lọc ở priority 98/99 vẫn trả lỗi, nên plugin xác thực khác cũng không vượt qua được.
	 */
	public static function skip_password_check() {
		remove_filter( 'authenticate', 'wp_authenticate_username_password', 20 );
		remove_filter( 'authenticate', 'wp_authenticate_email_password', 20 );
		remove_filter( 'authenticate', 'wp_authenticate_application_password', 20 );
	}

	/** Danh sách khoá còn hiệu lực để hiển thị (đã lọc bản ghi hết hạn). */
	public static function lockouts() {
		$rows = get_option( DS_Hide_Login_Options::OPT_LOCKOUTS, array() );
		$now  = time();

		return array_filter( is_array( $rows ) ? $rows : array(), function ( $row ) use ( $now ) {
			return isset( $row['until'] ) && $row['until'] > $now;
		} );
	}

	/** Mốc hết khoá của bucket, 0 nếu không bị khoá. Danh sách cũ (bản 1.1/1.2.0) vẫn được tôn trọng. */
	public static function locked_until( $bucket ) {
		$until = (int) DS_Hide_Login_Counter::get( self::lock_key( $bucket ) );
		$rows  = self::lockouts();

		if ( isset( $rows[ $bucket ] ) ) {
			$until = max( $until, (int) $rows[ $bucket ]['until'] );
		}

		return $until > time() ? $until : 0;
	}

	public function reserve_attempt( $user, $username, $password ) {
		$this->bucket   = '';
		$this->attempt  = null;
		$this->rejected = false;

		if ( ! is_string( $username ) || ! is_string( $password ) || '' === $username || '' === $password ) {
			return $user;
		}

		$this->bucket = self::bucket();

		if ( self::locked_until( $this->bucket ) ) {
			self::skip_password_check();

			return $user;
		}

		$this->attempt = DS_Hide_Login_Counter::incr( self::ip_key( $this->bucket ), $this->window() );

		if ( false === $this->attempt || $this->attempt > $this->max_attempts() ) {
			$this->rejected = true;
			self::skip_password_check();
		}

		return $user;
	}

	public function enforce( $user ) {
		if ( '' === $this->bucket ) {
			return $user;
		}

		$until = self::locked_until( $this->bucket );

		if ( $until ) {
			$this->attempt = null; // không đếm tiếp khi đã khoá (tránh tự gia hạn khoá vô hạn)

			return new WP_Error( 'ds_locked_out', sprintf(
				/* translators: %d: số phút */
				__( '<strong>Lỗi:</strong> Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau %d phút.', 'ds-hide-login' ),
				max( 1, (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS ) )
			) );
		}

		if ( $this->rejected ) {
			if ( false !== $this->attempt ) {
				DS_Hide_Login_Counter::decr( self::ip_key( $this->bucket ) );
			}
			$this->attempt = null;

			return new WP_Error( 'ds_try_later', __( '<strong>Lỗi:</strong> Có quá nhiều lần đăng nhập cùng lúc. Vui lòng thử lại sau ít giây.', 'ds-hide-login' ) );
		}

		if ( $user instanceof WP_User ) {
			// Chỉ trả lại lượt của chính request này.
			DS_Hide_Login_Counter::decr( self::ip_key( $this->bucket ) );
			$this->attempt = null;

			return $user;
		}

		// Báo số lần còn lại để người dùng thật biết dừng lại.
		if ( is_wp_error( $user ) && is_int( $this->attempt )
			&& array_intersect( $user->get_error_codes(), array( 'incorrect_password', 'invalid_username', 'invalid_email' ) ) ) {
			$left = $this->max_attempts() - $this->attempt;
			if ( $left > 0 ) {
				/* translators: %d: số lần còn lại */
				$user->add( 'ds_attempts_left', sprintf( __( 'Còn %d lần thử trước khi bị tạm khoá.', 'ds-hide-login' ), $left ) );
			}
		}

		return $user;
	}

	/** Lần thử sai thật (đã kiểm tra mật khẩu): chạm ngưỡng thì khoá. */
	public function record_failure() {
		if ( ! is_int( $this->attempt ) ) {
			return;
		}

		if ( $this->attempt >= $this->max_attempts() && ! self::locked_until( $this->bucket ) ) {
			$this->lock( $this->bucket );
		}

		$this->attempt = null;
	}

	/** Khoá bucket; trả về mốc hết khoá. */
	private function lock( $bucket ) {
		$until = time() + $this->window();

		DS_Hide_Login_Counter::set( self::lock_key( $bucket ), $until, $this->window() );
		DS_Hide_Login_Counter::delete( self::ip_key( $bucket ) );

		$rows            = self::lockouts();
		$rows[ $bucket ] = array(
			'until' => $until,
			'time'  => time(),
		);

		// Danh sách chỉ để hiển thị: giữ các bản ghi mới nhất.
		if ( count( $rows ) > self::MAX_LOCKOUT_ROWS ) {
			uasort( $rows, function ( $a, $b ) {
				return $b['time'] - $a['time'];
			} );
			$rows = array_slice( $rows, 0, self::MAX_LOCKOUT_ROWS, true );
		}

		update_option( DS_Hide_Login_Options::OPT_LOCKOUTS, $rows, false );
		do_action( 'ds_hide_login_locked_out', $bucket, $until );

		return $until;
	}

	/** Mở khoá 1 IP/dải, hoặc tất cả khi $ip rỗng. */
	public static function unlock( $ip = '' ) {
		if ( '' === $ip ) {
			// Xoá từng key theo danh sách (object cache không hỗ trợ flush_group), rồi xoá hàng loạt.
			foreach ( array_keys( self::lockouts() ) as $bucket ) {
				DS_Hide_Login_Counter::delete( self::lock_key( $bucket ) );
				DS_Hide_Login_Counter::delete( self::ip_key( $bucket ) );
			}
			DS_Hide_Login_Counter::purge_all();
			update_option( DS_Hide_Login_Options::OPT_LOCKOUTS, array(), false );
		} else {
			$ip = self::bucket( $ip ); // nhập IPv6 đầy đủ vẫn mở khoá đúng dải /64
			DS_Hide_Login_Counter::delete( self::lock_key( $ip ) );
			DS_Hide_Login_Counter::delete( self::ip_key( $ip ) );
			$rows = self::lockouts();
			unset( $rows[ $ip ] );
			update_option( DS_Hide_Login_Options::OPT_LOCKOUTS, $rows, false );
		}

		do_action( 'ds_hide_login_unlocked', $ip );
	}
}
