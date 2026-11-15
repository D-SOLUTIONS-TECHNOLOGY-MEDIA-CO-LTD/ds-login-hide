<?php
/**
 * Bộ đếm có hạn dùng, tăng/giảm ATOMIC – dùng cho giới hạn đăng nhập sai.
 *
 * - Có persistent object cache: wp_cache_add + wp_cache_incr (atomic ở Redis/Memcached).
 * - Không có: wp_options, tăng bằng UPDATE … LAST_INSERT_ID(value + n) rồi SELECT LAST_INSERT_ID()
 *   trên cùng kết nối → mỗi request nhận đúng giá trị của riêng mình.
 * - Fail-closed: lỗi DB/cache → incr() trả về false để bên gọi TỪ CHỐI tạm thời, không coi là 0.
 * - Multisite: dữ liệu lưu ở site chính → giới hạn áp dụng chung toàn network.
 * Dữ liệu theo định dạng transient (_transient_ / _transient_timeout_) nên WP tự dọn bản ghi hết hạn.
 */

defined( 'ABSPATH' ) || exit;

class DS_Hide_Login_Counter {

	const GROUP = 'ds_hide_login';

	/** Tiền tố mọi key của plugin, dùng khi xoá hàng loạt. */
	const PREFIX = 'ds_hl_';

	private static function ext() {
		return wp_using_ext_object_cache();
	}

	/** Chạy $fn với bảng options của site chính (multisite), để mọi subsite dùng chung bộ đếm. */
	private static function on_main_site( $fn ) {
		if ( ! is_multisite() || is_main_site() ) {
			return $fn();
		}

		switch_to_blog( get_main_site_id() );
		try {
			return $fn();
		} finally {
			restore_current_blog();
		}
	}

	/** Giá trị hiện tại (null nếu chưa có / hết hạn). Đọc thẳng DB để không dính cache trong request. */
	public static function get( $key ) {
		if ( self::ext() ) {
			$value = wp_cache_get( $key, self::GROUP );

			return false === $value ? null : (int) $value;
		}

		return self::on_main_site( function () use ( $key ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
				'_transient_' . $key,
				'_transient_timeout_' . $key
			), OBJECT_K );

			$timeout = isset( $rows[ '_transient_timeout_' . $key ] ) ? (int) $rows[ '_transient_timeout_' . $key ]->option_value : 0;
			if ( $timeout && $timeout < time() ) {
				DS_Hide_Login_Counter::delete( $key );

				return null;
			}

			return isset( $rows[ '_transient_' . $key ] ) ? (int) $rows[ '_transient_' . $key ]->option_value : null;
		} );
	}

	/**
	 * Tăng atomic, trả về giá trị SAU khi tăng; false nếu lỗi (bên gọi phải xử lý fail-closed).
	 * Hạn dùng tính từ lần tăng đầu tiên.
	 */
	public static function incr( $key, $ttl, $by = 1 ) {
		$by = max( 1, (int) $by );

		if ( self::ext() ) {
			wp_cache_add( $key, 0, self::GROUP, $ttl );
			$value = wp_cache_incr( $key, $by, self::GROUP );

			// Key bị evict giữa add và incr → tạo lại một lần.
			if ( false === $value && wp_cache_add( $key, $by, self::GROUP, $ttl ) ) {
				$value = $by;
			}

			return false === $value ? false : (int) $value;
		}

		return self::on_main_site( function () use ( $key, $ttl, $by ) {
			global $wpdb;
			DS_Hide_Login_Counter::get( $key ); // dọn bản ghi đã hết hạn trước khi tăng

			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '0', 'no'), (%s, %d, 'no')",
				'_transient_' . $key,
				'_transient_timeout_' . $key,
				time() + (int) $ttl
			) );

			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(CAST(option_value AS SIGNED) + %d) WHERE option_name = %s",
				$by,
				'_transient_' . $key
			) );

			// Lỗi truy vấn, hoặc dòng vừa bị xoá bởi request khác → không có giá trị tin cậy.
			if ( ! $updated ) {
				return false;
			}

			$value = $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );

			return null === $value ? false : (int) $value;
		} );
	}

	/** Giảm atomic, không xuống dưới 0. */
	public static function decr( $key, $by = 1 ) {
		$by = max( 1, (int) $by );

		if ( self::ext() ) {
			if ( false !== wp_cache_get( $key, self::GROUP ) ) {
				$value = wp_cache_decr( $key, $by, self::GROUP );
				// Một số drop-in (Redis DECRBY) cho phép số âm → xoá để tránh "tích luỹ" lượt miễn phí.
				if ( false !== $value && (int) $value < 0 ) {
					wp_cache_delete( $key, self::GROUP );
				}
			}

			return;
		}

		self::on_main_site( function () use ( $key, $by ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = GREATEST(0, CAST(option_value AS SIGNED) - %d) WHERE option_name = %s",
				$by,
				'_transient_' . $key
			) );
		} );
	}

	/** Ghi đè giá trị (upsert, idempotent) – dùng cho mốc hết hạn khoá IP. */
	public static function set( $key, $value, $ttl ) {
		if ( self::ext() ) {
			wp_cache_set( $key, (int) $value, self::GROUP, $ttl );

			return;
		}

		self::on_main_site( function () use ( $key, $value, $ttl ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %d, 'no'), (%s, %d, 'no')
				ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
				'_transient_' . $key,
				(int) $value,
				'_transient_timeout_' . $key,
				time() + (int) $ttl
			) );
		} );
	}

	public static function delete( $key ) {
		if ( self::ext() ) {
			wp_cache_delete( $key, self::GROUP );

			return;
		}

		self::on_main_site( function () use ( $key ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
				'_transient_' . $key,
				'_transient_timeout_' . $key
			) );
		} );
	}

	/**
	 * Xoá toàn bộ bộ đếm + khoá lưu trong DB. Với object cache chỉ xoá được nếu backend hỗ trợ flush_group
	 * – vì vậy DS_Hide_Login_Limit_Login::unlock() còn xoá từng key theo danh sách khoá.
	 */
	public static function purge_all() {
		if ( self::ext() ) {
			if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
				wp_cache_flush_group( self::GROUP );
			}

			return;
		}

		self::on_main_site( function () {
			global $wpdb;
			$wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . DS_Hide_Login_Counter::PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . DS_Hide_Login_Counter::PREFIX ) . '%'
			) );
		} );
	}
}
