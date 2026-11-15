<?php
/**
 * Tab Chống tấn công – nguồn IP (Cloudflare), giới hạn đăng nhập sai, captcha.
 * Được include từ views/settings-page.php (trong DS_Hide_Login_Settings::render()).
 */

defined( 'ABSPATH' ) || exit;

settings_fields( self::GROUP_PROTECT );
$p        = DS_Hide_Login_Options::protection();
$pname    = DS_Hide_Login_Options::OPT_PROTECTION;
$checkbox = function ( $key, $label ) use ( $p, $pname ) {
	printf(
		'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
		esc_attr( $pname ),
		esc_attr( $key ),
		checked( $p[ $key ], 1, false ),
		esc_html( $label )
	);
};

/** Ô nhập key captcha; bị khoá (disabled, không gửi lên) khi đã khai báo hằng số trong wp-config. */
$key_field = function ( $field, $constant, $label, $secret ) use ( $p, $pname ) {
	$locked = defined( $constant ) && constant( $constant );
	$id     = 'ds_' . $field;
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<?php if ( $locked ) : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="text" class="regular-text" disabled value="<?php echo esc_attr( $secret ? '********' : $p[ $field ] ); ?>">
				<p class="description">
					<?php
					/* translators: %s: tên hằng số */
					printf( esc_html__( 'Đang khoá bởi %s trong wp-config.php.', 'ds-hide-login' ), '<code>' . esc_html( $constant ) . '</code>' );
					?>
				</p>
			<?php else : ?>
				<input id="<?php echo esc_attr( $id ); ?>" type="<?php echo $secret ? 'password' : 'text'; ?>" class="regular-text" autocomplete="<?php echo $secret ? 'new-password' : 'off'; ?>"
					name="<?php echo esc_attr( $pname . '[' . $field . ']' ); ?>" value="<?php echo esc_attr( $p[ $field ] ); ?>">
			<?php endif; ?>
		</td>
	</tr>
	<?php
};

$remote    = DS_Hide_Login_Client_IP::remote_addr();
$cf_header = DS_Hide_Login_Client_IP::cloudflare_header();
$fields    = DS_Hide_Login_Options::captcha_key_fields();
$constants = DS_Hide_Login_Options::captcha_key_constants();
?>
<h2><?php esc_html_e( 'IP người dùng (Cloudflare)', 'ds-hide-login' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Nguồn IP', 'ds-hide-login' ); ?></th>
		<td>
			<fieldset>
				<?php foreach ( DS_Hide_Login_Options::ip_sources() as $key => $label ) : ?>
					<label style="display:block;margin-bottom:6px">
						<input type="radio" name="<?php echo esc_attr( $pname ); ?>[ip_source]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $p['ip_source'], $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description">
				<?php
				printf(
					/* translators: 1: REMOTE_ADDR, 2: CF-Connecting-IP, 3: kết luận */
					esc_html__( 'Request hiện tại: REMOTE_ADDR = %1$s · CF-Connecting-IP = %2$s · %3$s', 'ds-hide-login' ),
					'<code>' . esc_html( $remote ? $remote : '—' ) . '</code>',
					'<code>' . esc_html( $cf_header ? $cf_header : '—' ) . '</code>',
					DS_Hide_Login_Client_IP::is_cloudflare( $remote )
						? '<strong>' . esc_html__( 'đang đi qua Cloudflare', 'ds-hide-login' ) . '</strong>'
						: esc_html__( 'không đi qua Cloudflare', 'ds-hide-login' )
				);
				?>
				<br>
				<?php
				/* translators: %s: IP */
				printf( esc_html__( 'IP plugin đang nhận cho bạn: %s', 'ds-hide-login' ), '<code>' . esc_html( DS_Hide_Login_Client_IP::get() ) . '</code>' );
				?>
			</p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Giới hạn đăng nhập sai', 'ds-hide-login' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Bật', 'ds-hide-login' ); ?></th>
		<td><?php $checkbox( 'limit_enabled', __( 'Tạm khoá IP khi đăng nhập sai nhiều lần', 'ds-hide-login' ) ); ?></td>
	</tr>
	<tr>
		<th scope="row"><label for="ds_max_attempts"><?php esc_html_e( 'Số lần sai tối đa', 'ds-hide-login' ); ?></label></th>
		<td><input id="ds_max_attempts" type="number" min="1" max="50" class="small-text" name="<?php echo esc_attr( $pname ); ?>[max_attempts]" value="<?php echo esc_attr( $p['max_attempts'] ); ?>"></td>
	</tr>
	<tr>
		<th scope="row"><label for="ds_lockout_minutes"><?php esc_html_e( 'Thời gian khoá (phút)', 'ds-hide-login' ); ?></label></th>
		<td><input id="ds_lockout_minutes" type="number" min="1" max="1440" class="small-text" name="<?php echo esc_attr( $pname ); ?>[lockout_minutes]" value="<?php echo esc_attr( $p['lockout_minutes'] ); ?>">
			<p class="description"><?php esc_html_e( 'Số lần sai được đếm trong cùng khoảng thời gian này. Áp dụng cho cả XML-RPC và form login WooCommerce. IPv6 được tính theo cả dải /64.', 'ds-hide-login' ); ?></p></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'XML-RPC', 'ds-hide-login' ); ?></th>
		<td><?php $checkbox( 'disable_xmlrpc', __( 'Tắt đăng nhập qua XML-RPC (xmlrpc.php)', 'ds-hide-login' ) ); ?>
			<p class="description"><?php esc_html_e( 'XML-RPC không có captcha nên là đường brute force phổ biến. Chỉ bật tuỳ chọn này khi site KHÔNG dùng app WordPress trên điện thoại, Jetpack hoặc công cụ đăng bài từ xa. Pingback vẫn hoạt động.', 'ds-hide-login' ); ?></p></td>
	</tr>
</table>

<h2><?php esc_html_e( 'Captcha', 'ds-hide-login' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Dịch vụ', 'ds-hide-login' ); ?></th>
		<td>
			<fieldset>
				<?php foreach ( DS_Hide_Login_Options::captcha_providers() as $key => $label ) : ?>
					<label style="display:block;margin-bottom:6px">
						<input type="radio" class="ds-captcha-provider" name="<?php echo esc_attr( $pname ); ?>[captcha_provider]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $p['captcha_provider'], $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</td>
	</tr>
</table>

<div class="ds-captcha-keys" data-provider="recaptcha">
	<h3><?php esc_html_e( 'Key Google reCAPTCHA', 'ds-hide-login' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Tạo key loại "reCAPTCHA v2 – Checkbox" tại', 'ds-hide-login' ); ?>
		<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="noopener">google.com/recaptcha/admin</a>.
	</p>
	<table class="form-table" role="presentation">
		<?php
		$key_field( $fields['recaptcha'][0], $constants['recaptcha'][0], 'Site key', false );
		$key_field( $fields['recaptcha'][1], $constants['recaptcha'][1], 'Secret key', true );
		?>
	</table>
</div>

<div class="ds-captcha-keys" data-provider="turnstile">
	<h3><?php esc_html_e( 'Key Cloudflare Turnstile', 'ds-hide-login' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Tạo widget (chế độ Managed) trong Cloudflare Dashboard → Turnstile, thêm tên miền của site:', 'ds-hide-login' ); ?>
		<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">dash.cloudflare.com</a>.
	</p>
	<table class="form-table" role="presentation">
		<?php
		$key_field( $fields['turnstile'][0], $constants['turnstile'][0], 'Site key', false );
		$key_field( $fields['turnstile'][1], $constants['turnstile'][1], 'Secret key', true );
		?>
	</table>
</div>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Áp dụng cho', 'ds-hide-login' ); ?></th>
		<td>
			<p class="description" style="margin-bottom:6px"><?php esc_html_e( 'Form đăng nhập luôn có captcha khi bật. Thêm:', 'ds-hide-login' ); ?></p>
			<?php $checkbox( 'captcha_lostpass', __( 'Form quên mật khẩu', 'ds-hide-login' ) ); ?><br>
			<?php $checkbox( 'captcha_register', __( 'Form đăng ký', 'ds-hide-login' ) ); ?><br>
			<?php $checkbox( 'captcha_woocommerce', __( 'Form WooCommerce (đăng nhập / đăng ký / quên mật khẩu ở trang Tài khoản và Thanh toán)', 'ds-hide-login' ) ); ?>
		</td>
	</tr>
</table>
