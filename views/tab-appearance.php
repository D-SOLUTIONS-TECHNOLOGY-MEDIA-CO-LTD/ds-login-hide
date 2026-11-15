<?php
/**
 * Tab Giao diện & logo – được include từ views/settings-page.php (trong DS_Hide_Login_Settings::render()).
 */

defined( 'ABSPATH' ) || exit;
?>
		<?php
		settings_fields( self::GROUP_LOOK );
		$o    = DS_Hide_Login_Options::appearance();
		$name = DS_Hide_Login_Options::OPT_APPEARANCE;
		$colors = array(
			'primary_color' => __( 'Màu chủ đạo (nút, link)', 'ds-hide-login' ),
			'button_text'   => __( 'Chữ trên nút', 'ds-hide-login' ),
			'bg_color'      => __( 'Màu nền trang', 'ds-hide-login' ),
			'form_bg_color' => __( 'Màu nền form', 'ds-hide-login' ),
			'text_color'    => __( 'Màu chữ', 'ds-hide-login' ),
		);
		?>
		<h2><?php esc_html_e( 'Template', 'ds-hide-login' ); ?></h2>
		<div class="ds-templates">
			<?php foreach ( DS_Hide_Login_Options::templates() as $key => $label ) : ?>
				<label class="ds-template-card ds-template-<?php echo esc_attr( $key ); ?>">
					<input type="radio" name="<?php echo esc_attr( $name ); ?>[template]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $o['template'], $key ); ?>>
					<span class="ds-template-thumb" aria-hidden="true"><i></i></span>
					<span class="ds-template-label"><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>

		<h2><?php esc_html_e( 'Logo thương hiệu', 'ds-hide-login' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Nguồn logo', 'ds-hide-login' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( DS_Hide_Login_Options::logo_sources() as $key => $label ) : ?>
							<label style="display:block;margin-bottom:6px">
								<input type="radio" name="<?php echo esc_attr( $name ); ?>[logo_source]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $o['logo_source'], $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<?php
					$site_logo = absint( get_theme_mod( 'custom_logo' ) ) ?: absint( get_option( 'site_icon' ) );
					if ( $site_logo ) :
						?>
						<p class="description"><?php esc_html_e( 'Logo đang lấy được từ site:', 'ds-hide-login' ); ?></p>
						<div class="ds-media-preview"><?php echo wp_get_attachment_image( $site_logo, 'medium' ); ?></div>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Site chưa có Custom Logo / Site Icon.', 'ds-hide-login' ); ?>
							<a href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=title_tagline' ) ); ?>"><?php esc_html_e( 'Thiết lập trong Customizer', 'ds-hide-login' ); ?></a>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ảnh tải lên riêng', 'ds-hide-login' ); ?></th>
				<td><?php $ds_media_field( $name . '[logo_id]', $o['logo_id'] ); ?>
					<p class="description"><?php esc_html_e( 'Dùng khi chọn "Ảnh tải lên riêng". Khuyên dùng PNG/SVG nền trong suốt.', 'ds-hide-login' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin bar', 'ds-hide-login' ); ?></th>
				<td><label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[admin_bar_logo]" value="1" <?php checked( $o['admin_bar_logo'], 1 ); ?>>
					<?php esc_html_e( 'Thay logo WordPress trên thanh admin bằng logo thương hiệu', 'ds-hide-login' ); ?>
				</label></td>
			</tr>
			<tr>
				<th scope="row"><label for="ds_logo_width"><?php esc_html_e( 'Chiều rộng logo (px)', 'ds-hide-login' ); ?></label></th>
				<td><input id="ds_logo_width" type="number" min="40" max="400" name="<?php echo esc_attr( $name ); ?>[logo_width]" value="<?php echo esc_attr( $o['logo_width'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ds_logo_link"><?php esc_html_e( 'Link khi bấm logo', 'ds-hide-login' ); ?></label></th>
				<td><input id="ds_logo_link" type="url" class="regular-text" name="<?php echo esc_attr( $name ); ?>[logo_link]" value="<?php echo esc_attr( $o['logo_link'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ds_logo_title"><?php esc_html_e( 'Tiêu đề logo', 'ds-hide-login' ); ?></label></th>
				<td><input id="ds_logo_title" type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[logo_title]" value="<?php echo esc_attr( $o['logo_title'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Màu sắc & nền', 'ds-hide-login' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $colors as $key => $label ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td><input type="text" class="ds-color" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $o[ $key ] ); ?>" data-default-color="<?php echo esc_attr( DS_Hide_Login_Options::appearance_defaults()[ $key ] ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ảnh nền', 'ds-hide-login' ); ?></th>
				<td><?php $ds_media_field( $name . '[bg_image_id]', $o['bg_image_id'] ); ?>
					<p class="description"><?php esc_html_e( 'Classic: nền toàn trang · Split: panel bên trái · Glass: nền phía sau form kính.', 'ds-hide-login' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="ds_radius"><?php esc_html_e( 'Bo góc (px)', 'ds-hide-login' ); ?></label></th>
				<td><input id="ds_radius" type="number" min="0" max="40" name="<?php echo esc_attr( $name ); ?>[border_radius]" value="<?php echo esc_attr( $o['border_radius'] ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ds_footer"><?php esc_html_e( 'Dòng chữ chân trang', 'ds-hide-login' ); ?></label></th>
				<td><input id="ds_footer" type="text" class="large-text" name="<?php echo esc_attr( $name ); ?>[footer_text]" value="<?php echo esc_attr( $o['footer_text'] ); ?>" placeholder="© D-Solutions">
					<p class="description"><?php esc_html_e( 'Cho phép HTML cơ bản (link, in đậm).', 'ds-hide-login' ); ?></p></td>
			</tr>
		</table>
