<?php
/**
 * View trang cài đặt. Biến có sẵn từ DS_Hide_Login_Settings::render(): $tab, $tabs.
 */

defined( 'ABSPATH' ) || exit;

$login_url = DS_Hide_Login_Options::login_url();
$prefix    = trailingslashit( home_url() ) . ( get_option( 'permalink_structure' ) ? '' : '?' );
$suffix    = ( get_option( 'permalink_structure' ) && DS_Hide_Login_Options::use_trailing_slashes() ) ? '/' : '';

/** Ô chọn ảnh từ Media Library (logo, ảnh nền). */
$ds_media_field = function ( $name, $attachment_id ) {
	$url = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
	?>
	<div class="ds-media-field">
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $attachment_id ); ?>" class="ds-media-id">
		<div class="ds-media-preview"><?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt=""><?php endif; ?></div>
		<button type="button" class="button ds-media-select"><?php esc_html_e( 'Chọn ảnh', 'ds-hide-login' ); ?></button>
		<button type="button" class="button-link-delete ds-media-remove" <?php echo $url ? '' : 'hidden'; ?>><?php esc_html_e( 'Xoá', 'ds-hide-login' ); ?></button>
	</div>
	<?php
};
?>
<div class="wrap ds-hide-login">
	<h1>DS Hide Login</h1>

	<p class="ds-login-url">
		<?php esc_html_e( 'Trang đăng nhập hiện tại:', 'ds-hide-login' ); ?>
		<a href="<?php echo esc_url( $login_url ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( $login_url ); ?></strong></a>
		— <?php esc_html_e( 'hãy lưu lại (bookmark) địa chỉ này.', 'ds-hide-login' ); ?>
	</p>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $key => $label ) : ?>
			<a href="<?php echo esc_url( self::page_url( $key ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="options.php">
	<?php include DS_HIDE_LOGIN_DIR . 'views/tab-' . $tab . '.php'; ?>

		<p class="submit">
			<?php submit_button( null, 'primary', 'submit', false ); ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', $login_url ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Xem trước giao diện', 'ds-hide-login' ); ?></a>
			<span class="description"><?php esc_html_e( '(mở trang Quên mật khẩu – cùng giao diện, không đăng xuất bạn. Lưu trước khi xem.)', 'ds-hide-login' ); ?></span>
		</p>
	</form>

	<?php
	if ( 'protection' === $tab ) {
		include DS_HIDE_LOGIN_DIR . 'views/tab-protection-lockouts.php';
	}
	?>
</div>
