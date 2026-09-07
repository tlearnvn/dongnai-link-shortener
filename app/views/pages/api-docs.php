<?php
/**
 * Tài liệu API.
 *
 * @var array<string, mixed>|null $user
 */
$base = base_url();
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head page-head--stack">
            <p class="page-head__eyebrow">Dành cho người phát triển</p>
            <h1 class="page-head__title">Tài liệu API</h1>
            <p class="page-head__sub">
                Tạo liên kết và đọc số liệu từ phần mềm khác. Dữ liệu trả về dạng JSON, mã hoá UTF-8.
            </p>
        </header>

        <div class="card prose">
            <h2>Xác thực</h2>
            <p>Mọi yêu cầu (trừ kiểm tra tên) cần khoá API gửi trong header:</p>
            <pre class="code-block">Authorization: Bearer dnl_xxxxxxxxxxxxxxxxxxxx</pre>
            <p>
                <?php if ($user !== null): ?>
                    Lấy khoá của bạn tại trang <a href="<?= e(url('/tai-khoan')) ?>">Tài khoản</a>
                    → mục <em>Khoá API</em> → bấm <em>Cấp lại khoá API</em>.
                <?php else: ?>
                    <a href="<?= e(url('/dang-nhap')) ?>">Đăng nhập</a> rồi vào trang Tài khoản để lấy khoá API.
                <?php endif; ?>
                Khoá cũng có thể truyền qua tham số <code>token</code> nếu công cụ của bạn không đặt được header.
            </p>

            <h2>1. Kiểm tra tên tuỳ chọn còn trống</h2>
            <p class="endpoint"><span class="method method--get">GET</span> <code>/api/kiem-tra-ten?code=ten-can-kiem-tra</code></p>
            <p>Không cần xác thực. Trả về:</p>
            <pre class="code-block">{
    "ok": true,
    "available": false,
    "code": "tuyen-sinh",
    "message": "Tên tuỳ chọn này đã có người dùng rồi. Bạn thử tên khác xem sao!",
    "suggestion": "tuyen-sinh-2"
}</pre>

            <h2>2. Tạo liên kết rút gọn</h2>
            <p class="endpoint"><span class="method method--post">POST</span> <code>/api/rut-gon</code></p>
            <table class="prose__table">
                <thead><tr><th>Tham số</th><th>Bắt buộc</th><th>Ý nghĩa</th></tr></thead>
                <tbody>
                    <tr><td><code>url</code></td><td>Có</td><td>Địa chỉ đích (http hoặc https)</td></tr>
                    <tr><td><code>code</code></td><td>Không</td><td>Tên tuỳ chọn; bỏ trống thì hệ thống tự sinh</td></tr>
                    <tr><td><code>title</code></td><td>Không</td><td>Tiêu đề gợi nhớ</td></tr>
                    <tr><td><code>tags</code></td><td>Không</td><td>Thẻ, cách nhau bằng dấu phẩy</td></tr>
                    <tr><td><code>expires_at</code></td><td>Không</td><td>Thời điểm hết hạn, dạng <code>2026-12-31 23:59</code> (giờ GMT+7)</td></tr>
                    <tr><td><code>max_clicks</code></td><td>Không</td><td>Số lượt nhấp tối đa; 0 = không giới hạn</td></tr>
                    <tr><td><code>password</code></td><td>Không</td><td>Mật khẩu bảo vệ liên kết</td></tr>
                </tbody>
            </table>

            <h3>Ví dụ với cURL</h3>
            <pre class="code-block">curl -X POST "<?= e($base) ?>/api/rut-gon" \
     -H "Authorization: Bearer dnl_xxxxxxxx" \
     -H "Content-Type: application/json" \
     -d '{"url":"https://sgddt.dongnai.gov.vn/thong-bao.pdf","code":"thong-bao-9","title":"Thông báo số 9"}'</pre>

            <h3>Kết quả</h3>
            <pre class="code-block">{
    "ok": true,
    "link": {
        "code": "thong-bao-9",
        "short_url": "<?= e($base) ?>/thong-bao-9",
        "target_url": "https://sgddt.dongnai.gov.vn/thong-bao.pdf",
        "title": "Thông báo số 9",
        "tags": [],
        "clicks": 0,
        "unique_visitors": 0,
        "state": "active",
        "has_password": false,
        "qr_svg": "<?= e($base) ?>/ma-qr/thong-bao-9.svg",
        "qr_png": "<?= e($base) ?>/ma-qr/thong-bao-9.png",
        "created_at": "<?= e(Clock::nowDt()->format('c')) ?>",
        "expires_at": null,
        "last_click_at": null
    }
}</pre>
            <p>
                Dữ liệu không hợp lệ trả về mã <code>422</code> kèm đối tượng <code>errors</code>
                ghi rõ lỗi của từng trường. Thiếu hoặc sai khoá API trả về <code>401</code>.
            </p>

            <h2>3. Danh sách liên kết của bạn</h2>
            <p class="endpoint"><span class="method method--get">GET</span> <code>/api/lien-ket?page=1&amp;per_page=24&amp;q=&amp;status=&amp;sort=newest</code></p>
            <p>
                Tham số <code>status</code> nhận: <code>active</code>, <code>paused</code>,
                <code>expired</code>, <code>scheduled</code>, <code>exhausted</code>, <code>protected</code>.
            </p>

            <h2>4. Thống kê một liên kết</h2>
            <p class="endpoint"><span class="method method--get">GET</span> <code>/api/thong-ke/{ten-tuy-chon}?ngay=30</code></p>
            <p>Trả về số lượt theo ngày, theo giờ, theo thứ, cùng các bảng phân tích thiết bị / trình duyệt / nguồn:</p>
            <pre class="code-block">{
    "ok": true,
    "link": { "...": "..." },
    "timezone": "UTC+7",
    "daily":   [{ "date": "2026-09-01", "label": "01/09", "clicks": 12, "unique": 9 }],
    "hourly":  [{ "label": "00h", "value": 3 }],
    "weekday": [{ "label": "T2", "value": 18 }],
    "devices": [{ "label": "Điện thoại", "value": 42, "percent": 70.0 }],
    "qr_clicks": 12,
    "bot_clicks": 3
}</pre>

            <h2>Mã QR không cần API</h2>
            <p>Ảnh mã QR truy cập trực tiếp được, tiện nhúng vào trang khác hoặc tài liệu:</p>
            <pre class="code-block"><?= e($base) ?>/ma-qr/{ten}.png?co=12&amp;le=4&amp;mau=%230b1220&amp;nen=%23ffffff&amp;sua-loi=1
<?= e($base) ?>/ma-qr/{ten}.svg</pre>
            <table class="prose__table">
                <thead><tr><th>Tham số</th><th>Ý nghĩa</th></tr></thead>
                <tbody>
                    <tr><td><code>co</code></td><td>Kích thước mỗi ô (2–30 px)</td></tr>
                    <tr><td><code>le</code></td><td>Lề trắng quanh mã (0–8 ô)</td></tr>
                    <tr><td><code>mau</code></td><td>Màu mã, dạng hex — nhớ mã hoá <code>#</code> thành <code>%23</code></td></tr>
                    <tr><td><code>nen</code></td><td>Màu nền, hoặc <code>trong</code> để nền trong suốt (chỉ với SVG)</td></tr>
                    <tr><td><code>sua-loi</code></td><td>Mức sửa lỗi: 0=L, 1=M, 2=Q, 3=H</td></tr>
                    <tr><td><code>nguon</code></td><td><code>0</code> để bỏ dấu <code>?s=qr</code> trong mã</td></tr>
                    <tr><td><code>tai</code></td><td><code>1</code> để trình duyệt tải tệp về thay vì hiển thị</td></tr>
                </tbody>
            </table>

            <h2>Lưu ý</h2>
            <ul>
                <li>Mọi mốc thời gian trả về theo chuẩn ISO 8601 với độ lệch <code>+07:00</code>.</li>
                <li>Khoá API mang toàn quyền trên tài khoản của bạn — đừng đưa vào mã nguồn công khai.</li>
                <li>Cấp lại khoá sẽ vô hiệu khoá cũ ngay lập tức.</li>
            </ul>
        </div>
    </div>
</section>
