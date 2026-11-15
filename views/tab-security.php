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
			<tr>
				<th scope="row"><?php esc_html_e( 'Link công khai', 'ds-hide-login' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( DS_Hide_Login_Options::OPT_HIDE_LINKS ); ?>" value="0">
					<label><input type="checkbox" name="<?php echo esc_attr( DS_Hide_Login_Options::OPT_HIDE_LINKS ); ?>" value="1" <?php checked( DS_Hide_Login_Options::hide_links() ); ?>>
						<?php esc_html_e( 'Không để lộ URL đăng nhập trong link hiển thị cho khách', 'ds-hide-login' ); ?></label>
					<p class="description"><?php esc_html_e( 'Link "Đăng nhập để bình luận", widget Meta, link Đăng ký… sẽ trỏ tới trang Tài khoản WooCommerce (nếu có) hoặc trang chủ thay vì URL đăng nhập mới.', 'ds-hide-login' ); ?></p>
				</td>
			</tr>
		</table>
