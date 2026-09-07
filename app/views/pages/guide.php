<?php
/** Hướng dẫn sử dụng. */
$basePrefix = preg_replace('#^https?://#', '', base_url()) . '/';
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head page-head--stack">
            <p class="page-head__eyebrow">Tài liệu</p>
            <h1 class="page-head__title">Hướng dẫn sử dụng</h1>
            <p class="page-head__sub">
                Tài liệu ngắn gọn cho cán bộ, giáo viên và nhân viên các đơn vị.
            </p>
        </header>

        <nav class="toc card">
            <h2 class="toc__title">Nội dung</h2>
            <ol>
                <li><a href="#rut-gon-nhanh">Rút gọn một liên kết</a></li>
                <li><a href="#ten-tuy-chon">Đặt tên tuỳ chọn</a></li>
                <li><a href="#ma-qr">Tạo và in mã QR</a></li>
                <li><a href="#thong-ke">Đọc số liệu thống kê</a></li>
                <li><a href="#bao-ve">Bảo vệ và hẹn giờ liên kết</a></li>
                <li><a href="#hang-loat">Tạo nhiều liên kết một lượt</a></li>
                <li><a href="#cau-hoi">Câu hỏi thường gặp</a></li>
            </ol>
        </nav>

        <article class="prose card" id="rut-gon-nhanh">
            <h2>1. Rút gọn một liên kết</h2>
            <ol>
                <li>Ở trang chủ, dán địa chỉ dài vào ô <strong>“Dán địa chỉ cần rút gọn”</strong>.</li>
                <li>Bấm <strong>Rút gọn ngay</strong>.</li>
                <li>Trang kết quả hiện liên kết ngắn, mã QR và các nút chia sẻ. Bấm <strong>Sao chép</strong> là xong.</li>
            </ol>
            <p class="prose__note">
                Không cần đăng nhập vẫn rút gọn được. Nhưng khi <strong>đã đăng nhập</strong>, liên kết
                sẽ được lưu vào danh sách của bạn, sửa được về sau và xem được thống kê đầy đủ.
            </p>
        </article>

        <article class="prose card" id="ten-tuy-chon">
            <h2>2. Đặt tên tuỳ chọn</h2>
            <p>
                Thay vì để hệ thống sinh mã ngẫu nhiên như <code><?= e($basePrefix) ?>k7Bq2x</code>,
                bạn có thể tự đặt tên cho dễ nhớ, dễ đọc khi phổ biến qua điện thoại hoặc in trên văn bản:
            </p>
            <ul>
                <li><code><?= e($basePrefix) ?>tuyen-sinh-10</code></li>
                <li><code><?= e($basePrefix) ?>lich-thi-hk1</code></li>
                <li><code><?= e($basePrefix) ?>bieu-mau-2026</code></li>
            </ul>
            <h3>Quy tắc đặt tên</h3>
            <ul>
                <li>Dài từ <?= (int) Config::get('code_min_length', 3) ?> đến <?= (int) Config::get('code_max_length', 64) ?> ký tự.</li>
                <li>Chỉ dùng chữ không dấu, số và ba dấu <code>-</code> <code>_</code> <code>.</code></li>
                <li>Phải bắt đầu và kết thúc bằng chữ hoặc số.</li>
                <li>Không phân biệt chữ hoa/thường khi truy cập, nên <code>Tuyen-Sinh</code> và <code>tuyen-sinh</code> là một.</li>
                <li>Mỗi tên chỉ dùng cho một liên kết — hệ thống báo ngay khi bạn gõ nếu tên đã có người dùng.</li>
            </ul>
            <p class="prose__note">
                💡 Mẹo: đặt tên theo mẫu <em>chủ-đề-năm</em> để năm sau dễ tạo tên mới mà không trùng,
                ví dụ <code>tuyen-sinh-2026</code>, <code>tuyen-sinh-2027</code>.
            </p>
        </article>

        <article class="prose card" id="ma-qr">
            <h2>3. Tạo và in mã QR</h2>
            <p>Mỗi liên kết đều có mã QR riêng, không cần tạo thêm bước nào.</p>
            <ol>
                <li>Mở liên kết trong danh sách, bấm <strong>Mã QR</strong>.</li>
                <li>Kéo thanh trượt để chọn kích thước, đổi màu mã và màu nền nếu muốn.</li>
                <li>Bấm <strong>Tải ảnh PNG</strong> (dùng cho Word, Zalo) hoặc <strong>Tải vector SVG</strong>
                    (dùng cho in khổ lớn: băng-rôn, pa-nô, standee).</li>
            </ol>
            <h3>Chọn mức sửa lỗi nào?</h3>
            <table class="prose__table">
                <thead><tr><th>Mức</th><th>Chịu hư hỏng</th><th>Nên dùng khi</th></tr></thead>
                <tbody>
                    <tr><td>L</td><td>~7%</td><td>Mã hiện trên màn hình, môi trường sạch</td></tr>
                    <tr><td>M</td><td>~15%</td><td>Mặc định — in giấy, dán trong nhà</td></tr>
                    <tr><td>Q</td><td>~25%</td><td>Dán nơi hay bị chạm, cọ xước</td></tr>
                    <tr><td>H</td><td>~30%</td><td>Ngoài trời, in mờ, hoặc bị che một phần</td></tr>
                </tbody>
            </table>
            <p class="prose__note">
                Mã QR mang thêm dấu <code>?s=qr</code> để hệ thống phân biệt được ai
                <em>quét mã</em> và ai <em>bấm liên kết</em>. Bạn tắt tuỳ chọn này cũng được.
            </p>
        </article>

        <article class="prose card" id="thong-ke">
            <h2>4. Đọc số liệu thống kê</h2>
            <p>Mở <strong>Thống kê</strong> để xem toàn cảnh, hoặc bấm <strong>Thống kê</strong> ở từng liên kết.</p>
            <dl class="prose__dl">
                <dt>Tổng lượt nhấp</dt>
                <dd>Mỗi lần có người mở liên kết là một lượt, kể cả cùng một người mở nhiều lần.</dd>

                <dt>Khách riêng</dt>
                <dd>
                    Số người khác nhau, đếm theo một dấu vết ẩn danh (mã băm không thể suy ngược).
                    Hệ thống <strong>không lưu địa chỉ IP</strong> của người truy cập.
                </dd>

                <dt>Quét mã QR</dt>
                <dd>Số lượt đến từ việc quét mã QR do hệ thống sinh ra.</dd>

                <dt>Nguồn giới thiệu</dt>
                <dd>
                    Trang mà người dùng bấm liên kết. Ghi “Truy cập trực tiếp” khi người dùng gõ tay,
                    quét QR, hoặc mở từ ứng dụng không gửi thông tin nguồn (Zalo, Messenger, phần mềm đọc email…).
                </dd>

                <dt>Robot</dt>
                <dd>Lượt do máy quét tự động sinh ra (trình thu thập của mạng xã hội, công cụ kiểm tra liên kết…).</dd>
            </dl>
            <p class="prose__note">
                Toàn bộ mốc thời gian đều theo <strong>giờ Việt Nam (GMT+7)</strong>,
                kể cả biểu đồ theo giờ và theo thứ trong tuần.
            </p>
        </article>

        <article class="prose card" id="bao-ve">
            <h2>5. Bảo vệ và hẹn giờ liên kết</h2>
            <p>Trong biểu mẫu tạo/sửa liên kết, mục <strong>Điều kiện hoạt động</strong> có bốn công cụ:</p>
            <ul>
                <li><strong>Bắt đầu hoạt động từ</strong> — trước giờ đó, người mở sẽ thấy thông báo “chưa tới giờ”.
                    Tiện khi cần công bố tài liệu đúng thời điểm.</li>
                <li><strong>Hết hạn lúc</strong> — sau giờ đó liên kết tự ngừng, không cần bạn vào tắt.</li>
                <li><strong>Số lượt nhấp tối đa</strong> — ví dụ chỉ cho 100 người đầu tiên tải biểu mẫu.</li>
                <li><strong>Mật khẩu bảo vệ</strong> — người nhận phải nhập mật khẩu mới mở được.</li>
            </ul>
            <p class="prose__note">
                Bạn cũng có thể <strong>Tạm dừng</strong> rồi <strong>Bật lại</strong> một liên kết bất cứ lúc nào
                mà không mất số liệu thống kê đã có.
            </p>
        </article>

        <article class="prose card" id="hang-loat">
            <h2>6. Tạo nhiều liên kết một lượt</h2>
            <p>Vào <strong>Tạo hàng loạt</strong>, dán danh sách, mỗi dòng một địa chỉ:</p>
            <pre class="code-block">https://vidu.vn/tai-lieu-1.pdf
https://vidu.vn/tai-lieu-2.pdf | tai-lieu-2
https://vidu.vn/tai-lieu-3.pdf | tai-lieu-3 | Tài liệu tập huấn số 3</pre>
            <p>
                Dòng thứ nhất để hệ thống tự sinh mã. Dòng thứ hai tự đặt tên. Dòng thứ ba đặt cả tên và tiêu đề.
                Xong thì bấm <strong>Sao chép tất cả</strong> để dán vào văn bản của bạn.
            </p>
        </article>

        <article class="prose card" id="cau-hoi">
            <h2>7. Câu hỏi thường gặp</h2>

            <details class="faq">
                <summary>Đổi được địa chỉ đích mà giữ nguyên liên kết ngắn không?</summary>
                <p>
                    Được. Vào <strong>Sửa</strong> liên kết rồi thay địa chỉ đích. Liên kết ngắn và mã QR
                    đã in ra vẫn dùng bình thường — rất tiện khi tài liệu được cập nhật sang bản mới.
                </p>
            </details>

            <details class="faq">
                <summary>Tôi quên mật khẩu tài khoản thì sao?</summary>
                <p>
                    Dùng <strong>mã dự phòng</strong> nhận được lúc tạo tài khoản, tại trang
                    <a href="<?= e(url('/quen-mat-khau')) ?>">Quên mật khẩu</a>.
                    Nếu mất luôn mã dự phòng, hãy nhờ quản trị viên đặt lại mật khẩu giúp.
                </p>
            </details>

            <details class="faq">
                <summary>Xoá liên kết rồi có lấy lại được không?</summary>
                <p>
                    Không. Xoá liên kết là xoá luôn số liệu thống kê của nó. Nếu chỉ muốn ngừng tạm thời,
                    hãy dùng <strong>Tạm dừng</strong> thay vì xoá.
                </p>
            </details>

            <details class="faq">
                <summary>Liên kết đã in trên văn bản, giờ đổi tên tuỳ chọn được không?</summary>
                <p>
                    Đổi được, nhưng <strong>không nên</strong>: tên cũ sẽ không còn hoạt động, nên mọi bản in
                    và mã QR đã phát hành đều thành liên kết chết. Nếu buộc phải đổi, hãy tạo thêm một liên kết
                    mới với tên mới và giữ nguyên liên kết cũ.
                </p>
            </details>

            <details class="faq">
                <summary>Dữ liệu được lưu ở đâu?</summary>
                <p>
                    Toàn bộ dữ liệu nằm trong <strong>một tệp SQLite duy nhất</strong> trên máy chủ của đơn vị.
                    Sao lưu chỉ cần chép tệp đó. Không có dữ liệu nào được gửi ra dịch vụ bên ngoài.
                </p>
            </details>

            <details class="faq">
                <summary>Có thể gọi từ phần mềm khác không?</summary>
                <p>
                    Có. Xem <a href="<?= e(url('/api')) ?>">tài liệu API</a> — bạn dùng khoá API trong trang
                    Tài khoản để tạo liên kết và đọc số liệu từ phần mềm của mình.
                </p>
            </details>
        </article>

        <div class="card cta-card">
            <h2>Bắt tay vào làm thử?</h2>
            <p>Chỉ mất vài giây cho liên kết đầu tiên.</p>
            <div class="cta-card__actions">
                <a class="btn btn--primary btn--lg" href="<?= e(url('/#rut-gon')) ?>">Rút gọn liên kết</a>
                <a class="btn btn--ghost" href="<?= e(url('/dang-ky')) ?>">Tạo tài khoản</a>
            </div>
        </div>
    </div>
</section>
