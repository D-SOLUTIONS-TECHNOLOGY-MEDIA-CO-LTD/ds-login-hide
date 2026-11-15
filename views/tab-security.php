<?php
/**
 * Tab URL đăng nhập – được include từ views/settings-page.php (trong DS_Hide_Login_Settings::render()).
 */

defined( 'ABSPATH' ) || exit;
?>
		<?php settings_fields( self::GROUP_SECURITY ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ds_slug"><?php esc_html_e( 'URL đăng nhập', 'ds-hide-login' ); ?></label></th>
				<td>
					<code><?php echo esc_html( $prefix ); ?></code>
					<input id="ds_slug" type="text" class="regular-text"
						<?php echo DS_Hide_Login_Options::slug_locked() ? 'disabled' : 'name="' . esc_attr( DS_Hide_Login_Options::OPT_SLUG ) . '"'; ?>
						value="<?php echo esc_attr( DS_Hide_Login_Options::login_slug() ); ?>">
					<?php if ( $suffix ) : ?><code>/</code><?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'wp-login.php và wp-admin sẽ không truy cập được khi chưa đăng nhập.', 'ds-hide-login' ); ?>
						<?php if ( DS_Hide_Login_Options::slug_locked() ) : ?><strong><?php esc_html_e( 'Đang khoá bởi hằng số DS_HIDE_LOGIN_SLUG trong wp-config.php.', 'ds-hide-login' ); ?></strong><?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ds_redirect"><?php esc_html_e( 'URL chuyển hướng', 'ds-hide-login' ); ?></label></th>
				<td>
					<code><?php echo esc_html( $prefix ); ?></code>
					<input id="ds_redirect" type="text" class="regular-text"
						<?php echo DS_Hide_Login_Options::redirect_locked() ? 'disabled' : 'name="' . esc_attr( DS_Hide_Login_Options::OPT_REDIRECT ) . '"'; ?>
						value="<?php echo esc_attr( DS_Hide_Login_Options::redirect_slug() ); ?>">
					<?php if ( $suffix ) : ?><code>/</code><?php endif; ?>
					<p class="description"><?php esc_html_e( 'Nơi đưa khách tới khi họ cố vào wp-login.php hoặc wp-admin.', 'ds-hide-login' ); ?></p>
				</td>
			</tr>
		</table>
