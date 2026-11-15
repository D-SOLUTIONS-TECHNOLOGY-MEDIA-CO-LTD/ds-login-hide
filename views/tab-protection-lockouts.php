<?php
/**
 * Danh sách IP đang bị khoá + nút mở khoá. Render NGOÀI form options.php
 * (form lồng nhau không hợp lệ). Gửi tới admin-post.php?action=ds_hide_login_unlock.
 */

defined( 'ABSPATH' ) || exit;

$lockouts = DS_Hide_Login_Limit_Login::lockouts();
$unlock   = function ( $ip, $label, $class ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
		<?php wp_nonce_field( 'ds_hide_login_unlock' ); ?>
		<input type="hidden" name="action" value="ds_hide_login_unlock">
		<input type="hidden" name="ip" value="<?php echo esc_attr( $ip ); ?>">
		<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
};
?>
<hr>
<h2><?php esc_html_e( 'IP đang bị khoá', 'ds-hide-login' ); ?></h2>
<?php if ( isset( $_GET['ds-unlocked'] ) ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'Đã mở khoá.', 'ds-hide-login' ); ?></p></div>
<?php endif; ?>
<p class="description">
	<?php
	/* translators: %s: IP hiện tại */
	printf( esc_html__( 'IP của bạn: %s', 'ds-hide-login' ), '<code>' . esc_html( DS_Hide_Login_Limit_Login::client_ip() ) . '</code>' );
	?>
</p>

<?php if ( ! $lockouts ) : ?>
	<p><?php esc_html_e( 'Không có IP nào đang bị khoá.', 'ds-hide-login' ); ?></p>
<?php else : ?>
	<table class="widefat striped" style="max-width:720px">
		<thead><tr>
			<th><?php esc_html_e( 'IP / dải IPv6', 'ds-hide-login' ); ?></th>
			<th><?php esc_html_e( 'Bị khoá lúc', 'ds-hide-login' ); ?></th>
			<th><?php esc_html_e( 'Mở khoá lúc', 'ds-hide-login' ); ?></th>
			<th></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $lockouts as $ip => $row ) : ?>
			<tr>
				<td><code><?php echo esc_html( $ip ); ?></code></td>
				<td><?php echo esc_html( wp_date( 'd/m/Y H:i', $row['time'] ) ); ?></td>
				<td><?php echo esc_html( wp_date( 'd/m/Y H:i', $row['until'] ) ); ?></td>
				<td><?php $unlock( $ip, __( 'Mở khoá', 'ds-hide-login' ), 'button button-small' ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p><?php $unlock( '', __( 'Mở khoá tất cả', 'ds-hide-login' ), 'button' ); ?></p>
<?php endif; ?>
