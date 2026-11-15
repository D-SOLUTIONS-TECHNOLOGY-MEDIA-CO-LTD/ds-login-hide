=== DS Hide Login ===
Contributors: d-solutions
Tags: login, hide login, custom login url, login logo, login page
Requires at least: 5.3
Requires PHP: 7.0
Stable tag: 1.2.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Đổi URL đăng nhập, chặn wp-login.php & wp-admin, giới hạn đăng nhập sai, reCAPTCHA / Cloudflare Turnstile, logo thương hiệu và template trang login.

== Description ==

Plugin nội bộ của D-Solutions, port và mở rộng từ WPS Hide Login 1.9.20 (GPLv2+).

* Đổi URL đăng nhập (mặc định /login), wp-login.php và wp-admin trả 404 / chuyển hướng khi chưa đăng nhập.
* Không thêm rewrite rule, tắt plugin là site trở về như cũ.
* Giới hạn đăng nhập sai theo IP (mặc định 5 lần / khoá 20 phút; IPv6 tính theo dải /64), đếm atomic chống bắn request song song, xem & mở khoá IP trong admin.
* Tuỳ chọn tắt đăng nhập qua XML-RPC (mặc định giữ nguyên).
* Không để lộ URL đăng nhập trong link công khai (bình luận, widget Meta) – có thể tắt.
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
* Cloudflare Tunnel: tin thêm header khi request đến từ loopback / mạng nội bộ (cloudflared). Request từ IP public không thuộc Cloudflare vẫn bị bỏ qua header.
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
* action `ds_hide_login_locked_out` ($ip, $until) – khi một IP (hoặc dải IPv6 /64) bị khoá.
* action `ds_hide_login_unlocked` ($ip) – khi mở khoá ('' = tất cả); `ds_hide_login_admin_unlocked` ($ip, $user_id) – admin mở khoá từ trang cài đặt.
* filter `ds_hide_login_captcha_check_hostname` (false) – bật kiểm tra hostname của token captcha; `ds_hide_login_captcha_hostnames` ($hosts, $provider) – danh sách hostname hợp lệ (mặc định home/site URL, có và không có www).
* filter `ds_hide_login_public_login_url` ($target, $url) – đích thay cho URL đăng nhập trong link công khai.
* hàm `ds_hide_login_url()` – lấy URL login mới trong theme.

= Chuyển từ WPS Hide Login =

Kích hoạt DS Hide Login → cấu hình whl_page / whl_redirect_admin được chép sang → tắt WPS Hide Login.
Khi cả hai cùng bật, DS Hide Login tự tạm dừng và báo trong admin.

== Changelog ==

= 1.2.1 =
* Bảo mật: đăng nhập đúng không còn reset bộ đếm sai của cả IP (trước đây có thể dùng 1 tài khoản thường để dò mật khẩu admin không giới hạn). Lần sai được tính đến hết cửa sổ thời gian, kể cả khi sau đó đăng nhập đúng.
* Bảo mật: bộ đếm fail-closed – lỗi DB/cache thì từ chối tạm thời thay vì bỏ qua giới hạn.
* Multisite: giới hạn đăng nhập sai áp dụng chung toàn network.
* Nhiều lần đăng nhập ĐÚNG cùng lúc (app, XML-RPC) không còn làm khoá IP.
* Bảo mật: đếm lần thử atomic + đặt chỗ trước khi kiểm tra mật khẩu – bắn request song song không vượt được ngưỡng.
* Bảo mật: IPv6 bị đếm/khoá theo dải /64.
* Bảo mật: khoá IP lưu riêng từng IP, không bị mất khi danh sách vượt 500 dòng.
* Bảo mật: captcha được kiểm tra trước mật khẩu (không tốn CPU băm khi captcha sai), timeout 5s, tuỳ chọn kiểm tra hostname của token (filter).
* Bảo mật: chế độ Cloudflare Tunnel không còn tin header từ IP public lạ.
* Thêm tuỳ chọn tắt đăng nhập XML-RPC.
* Ẩn URL đăng nhập khỏi link công khai cho khách.
* Sửa: /?s=wp-login.php không còn bị trả 404; bài viết có slug chứa "wp-signup" không còn bị chặn 403; //wp-login.php vẫn bị chặn.

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
