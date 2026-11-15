=== DS Hide Login ===
Contributors: d-solutions
Tags: login, hide login, custom login url, login logo, login page
Requires at least: 5.3
Requires PHP: 7.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Đổi URL đăng nhập, chặn wp-login.php & wp-admin, giới hạn đăng nhập sai, reCAPTCHA / Cloudflare Turnstile, logo thương hiệu và template trang login.

== Description ==

Plugin nội bộ của D-Solutions, port và mở rộng từ WPS Hide Login 1.9.20 (GPLv2+).

* Đổi URL đăng nhập (mặc định /login), wp-login.php và wp-admin trả 404 / chuyển hướng khi chưa đăng nhập.
* Không thêm rewrite rule, tắt plugin là site trở về như cũ.
* Giới hạn đăng nhập sai theo IP (mặc định 5 lần / khoá 20 phút), xem & mở khoá IP trong admin.
* Tự nhận IP thật của người dùng khi site chạy sau Cloudflare (kiểm tra dải IP Cloudflare, không tin header giả mạo).
* Captcha: Google reCAPTCHA v2 hoặc Cloudflare Turnstile cho form đăng nhập, quên mật khẩu, đăng ký – cả wp-login lẫn WooCommerce.
* Logo thương hiệu tự lấy từ cấu hình site (Custom Logo của theme → Site Icon) hoặc ảnh tải lên riêng; thay cả logo WordPress trên admin bar.
* 3 template: Classic, Split, Glass (+ giữ giao diện mặc định WordPress).
* Tuỳ chỉnh màu chủ đạo, chữ nút, nền trang, nền form, màu chữ, ảnh nền, bo góc, chân trang.

Cài đặt: Settings › DS Hide Login.

= Cấu hình bằng wp-config.php (khoá, khách hàng không đổi được) =

    define( 'DS_HIDE_LOGIN_SLUG', 'dang-nhap' );
    define( 'DS_HIDE_LOGIN_REDIRECT', '404' );
    define( 'DS_HIDE_LOGIN_RECAPTCHA_SITE_KEY', '...' );
    define( 'DS_HIDE_LOGIN_RECAPTCHA_SECRET_KEY', '...' );
    define( 'DS_HIDE_LOGIN_TURNSTILE_SITE_KEY', '...' );
    define( 'DS_HIDE_LOGIN_TURNSTILE_SECRET_KEY', '...' );

Key khai báo bằng hằng số vẫn cần chọn dịch vụ captcha trong trang cài đặt.

= Admin tự bị khoá IP =

Chạy `wp option delete ds_hide_login_lockouts` hoặc xoá option này trong database.

= Site sau Cloudflare =

Tab "Chống tấn công" › Nguồn IP:

* Tự động (mặc định): dùng header CF-Connecting-IP chỉ khi request đến từ dải IP của Cloudflare. An toàn cho cả site có và không dùng Cloudflare.
* Luôn tin CF-Connecting-IP: cho site chạy Cloudflare Tunnel (REMOTE_ADDR là 127.0.0.1). Chỉ dùng khi server không nhận traffic trực tiếp, nếu không header có thể bị giả mạo.
* Chỉ REMOTE_ADDR: site không qua Cloudflare/proxy.

Trang cài đặt hiện REMOTE_ADDR, CF-Connecting-IP và IP plugin đang nhận để kiểm tra nhanh.
Proxy khác (nginx, load balancer...): dùng filter `ds_hide_login_client_ip`.

= Hooks =

* filter `ds_hide_login_home_url` – base URL dựng link login.
* filter `ds_hide_login_signup_enable` – trả true để mở wp-signup.php / wp-activate.php (site đơn).
* filter `ds_hide_login_logged_in_redirect` – nơi đưa user đã đăng nhập khi vào URL login.
* action `ds_hide_login_before_redirect` – trước khi xử lý chuyển hướng.
* filter `ds_hide_login_client_ip` ($ip, $remote_addr) – IP dùng cho giới hạn đăng nhập và captcha.
* filter `ds_hide_login_cloudflare_ranges` – danh sách dải IP Cloudflare (CIDR).
* filter `ds_hide_login_captcha_fail_open` ($fail_open, $provider) – true (mặc định) cho qua khi không gọi được Google / Cloudflare.
* action `ds_hide_login_locked_out` ($ip, $until) – khi một IP bị khoá.
* hàm `ds_hide_login_url()` – lấy URL login mới trong theme.

= Chuyển từ WPS Hide Login =

Kích hoạt DS Hide Login → cấu hình whl_page / whl_redirect_admin được chép sang → tắt WPS Hide Login.
Khi cả hai cùng bật, DS Hide Login tự tạm dừng và báo trong admin.

== Changelog ==

= 1.2.0 =
* Tự nhận IP thật qua Cloudflare (chế độ Tự động / Luôn tin header / Chỉ REMOTE_ADDR).
* Thêm Cloudflare Turnstile, chọn được giữa reCAPTCHA và Turnstile.
* Captcha cho form đăng nhập / đăng ký / quên mật khẩu của WooCommerce.
* Sửa: quên mật khẩu và đăng ký từ plugin khác không còn bị captcha chặn nhầm.

= 1.1.0 =
* Thêm giới hạn đăng nhập sai theo IP + danh sách mở khoá.
* Thêm Google reCAPTCHA v2.
* Logo tự lấy từ Custom Logo / Site Icon của site; thay logo trên admin bar.

= 1.0.0 =
* Phát hành đầu tiên cho D-Solutions: lõi hide login, logo thương hiệu, 3 template, tuỳ chỉnh màu.
