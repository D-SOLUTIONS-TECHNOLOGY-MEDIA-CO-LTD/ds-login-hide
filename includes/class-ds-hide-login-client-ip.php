<?php
/**
 * Xác định IP thật của người dùng, kể cả khi site chạy sau Cloudflare.
 *
 * Header CF-Connecting-IP giả mạo được nếu request đi thẳng vào server,
 * nên ở chế độ "auto" chỉ tin header khi REMOTE_ADDR thuộc dải IP của Cloudflare.
 * Dải IP lấy từ https://www.cloudflare.com/ips/ – cập nhật qua filter
 * ds_hide_login_cloudflare_ranges nếu Cloudflare thêm dải mới.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Client_IP {

	/** Dải IP công bố của Cloudflare (IPv4 + IPv6). */
	const CLOUDFLARE_RANGES = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/** IP kết nối trực tiếp tới server. */
	public static function remote_addr() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** Giá trị header CF-Connecting-IP (rỗng nếu không có / không hợp lệ). */
	public static function cloudflare_header() {
		$ip = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/** IP người dùng theo cấu hình "Nguồn IP"; luôn trả về IP hợp lệ ('0.0.0.0' nếu không xác định được). */
	public static function get() {
		$remote = self::remote_addr();
		$ip     = $remote;
		$source = DS_Hide_Login_Options::protection()['ip_source'];
		$cf     = self::cloudflare_header();

		// "cloudflare": tin thêm request từ mạng nội bộ/loopback (cloudflared chạy cùng máy/mạng nội bộ),
		// nhưng KHÔNG tin request từ IP public lạ – chặn giả mạo header khi origin lộ IP.
		$trusted = self::is_cloudflare( $remote ) || ( 'cloudflare' === $source && self::is_internal( $remote ) );

		if ( $cf && 'remote_addr' !== $source && $trusted ) {
			$ip = $cf;
		}

		$ip = apply_filters( 'ds_hide_login_client_ip', $ip, $remote );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/** IP loopback / mạng riêng / dải dành riêng (127.0.0.1, 10.x, 172.16-31.x, 192.168.x, ::1, fc00::/7...). */
	public static function is_internal( $ip ) {
		return $ip && filter_var( $ip, FILTER_VALIDATE_IP )
			&& ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Đơn vị để đếm/khoá đăng nhập sai: IPv4 giữ nguyên; IPv6 gộp theo dải /64
	 * (mỗi thuê bao IPv6 thường được cấp nguyên /64 – xoay địa chỉ trong đó không vượt được giới hạn).
	 */
	public static function bucket( $ip ) {
		$bin = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $bin || 16 !== strlen( $bin ) ) {
			return (string) $ip;
		}

		// IPv4-mapped (::ffff:a.b.c.d) → coi như IPv4, tránh gộp mọi IPv4 vào chung 1 dải.
		if ( str_repeat( "\0", 10 ) . "\xff\xff" === substr( $bin, 0, 12 ) ) {
			return inet_ntop( substr( $bin, 12 ) );
		}

		return inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}

	public static function is_cloudflare( $ip ) {
		if ( ! $ip ) {
			return false;
		}

		// Socket dual-stack báo IPv4 dạng ::ffff:a.b.c.d → đưa về IPv4 để so với dải IPv4.
		$normalized = self::bucket( $ip );
		if ( false === strpos( $normalized, '/' ) ) {
			$ip = $normalized;
		}

		foreach ( (array) apply_filters( 'ds_hide_login_cloudflare_ranges', self::CLOUDFLARE_RANGES ) as $cidr ) {
			if ( self::in_cidr( $ip, $cidr ) ) {
				return true;
			}
		}

		return false;
	}

	/** So khớp IP với dải CIDR, dùng chung cho IPv4 và IPv6. */
	public static function in_cidr( $ip, $cidr ) {
		$parts = explode( '/', $cidr, 2 );
		$ip    = @inet_pton( $ip );       // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$net   = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $ip || false === $net || strlen( $ip ) !== strlen( $net ) ) {
			return false;
		}

		$bits  = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ip ) * 8;
		$bytes = intdiv( $bits, 8 );
		$rest  = $bits % 8;

		if ( substr( $ip, 0, $bytes ) !== substr( $net, 0, $bytes ) ) {
			return false;
		}
		if ( 0 === $rest ) {
			return true;
		}

		$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;

		return ( ord( $ip[ $bytes ] ) & $mask ) === ( ord( $net[ $bytes ] ) & $mask );
	}
}
