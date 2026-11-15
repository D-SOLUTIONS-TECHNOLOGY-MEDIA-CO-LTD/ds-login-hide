/**
 * DS Hide Login – trang cài đặt: color picker + chọn ảnh từ Media Library.
 */
jQuery(function ($) {
	$('.ds-color').wpColorPicker();

	$('.ds-media-field').each(function () {
		var $field = $(this),
			$id = $field.find('.ds-media-id'),
			$preview = $field.find('.ds-media-preview'),
			$remove = $field.find('.ds-media-remove'),
			frame;

		$field.on('click', '.ds-media-select', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({
					title: dsHideLogin.chooseImage,
					button: { text: dsHideLogin.useImage },
					library: { type: 'image' },
					multiple: false
				});
				frame.on('select', function () {
					var img = frame.state().get('selection').first().toJSON(),
						url = img.sizes && img.sizes.medium ? img.sizes.medium.url : img.url;
					$id.val(img.id);
					$preview.html($('<img alt="">').attr('src', url));
					$remove.prop('hidden', false);
				});
			}
			frame.open();
		});

		$field.on('click', '.ds-media-remove', function (e) {
			e.preventDefault();
			$id.val('');
			$preview.empty();
			$remove.prop('hidden', true);
		});
	});

	// Chỉ hiện ô key của dịch vụ captcha đang chọn.
	var $providers = $('.ds-captcha-provider');
	function toggleCaptchaKeys() {
		var current = $providers.filter(':checked').val();
		$('.ds-captcha-keys').each(function () {
			$(this).prop('hidden', $(this).data('provider') !== current);
		});
	}
	if ($providers.length) {
		$providers.on('change', toggleCaptchaKeys);
		toggleCaptchaKeys();
	}
});
