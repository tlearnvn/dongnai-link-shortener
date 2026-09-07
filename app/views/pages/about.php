<?php
/**
 * Trang giới thiệu.
 *
 * @var array<string, mixed> $summary
 */
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head page-head--stack">
            <p class="page-head__eyebrow">Giới thiệu</p>
            <h1 class="page-head__title"><?= e((string) Config::get('site_name')) ?></h1>
            <p class="page-head__sub"><?= e((string) Config::get('site_owner')) ?></p>
        </header>

        <div class="card prose">
            <p class="prose__lead">
                Đây là hệ thống rút gọn liên kết dùng nội bộ cho công tác thông tin của
                <strong><?= e((string) Config::get('site_owner')) ?></strong>: biến những địa chỉ dài
                thành liên kết ngắn dễ đọc, kèm mã QR để in lên văn bản và số liệu để biết
                thông báo đã tới được bao nhiêu người.
            </p>

            <h2>Vì sao nên dùng hệ thống của đơn vị?</h2>
            <ul>
                <li>
                    <strong>Liên kết mang tên miền của đơn vị</strong> — người nhận thấy quen, tin tưởng hơn
                    so với các dịch vụ rút gọn nước ngoài.
                </li>
                <li>
                    <strong>Không phụ thuộc bên thứ ba</strong> — dịch vụ miễn phí bên ngoài có thể ngừng hoạt động
                    hoặc xoá liên kết, làm chết mọi văn bản đã in.
                </li>
                <li>
                    <strong>Dữ liệu nằm trong đơn vị</strong> — không gửi thông tin truy cập của ai ra ngoài.
                </li>
                <li>
                    <strong>Sửa được đích đến</strong> — tài liệu cập nhật bản mới thì chỉ cần trỏ lại,
                    không phải in lại văn bản.
                </li>
            </ul>

            <h2>Số liệu hiện tại</h2>
            <div class="tiles tiles--compact">
                <article class="tile tile--primary">
                    <span class="tile__icon" aria-hidden="true">🔗</span>
                    <p class="tile__label">Liên kết</p>
                    <p class="tile__value" data-count-to="<?= (int) $summary['links'] ?>">0</p>
                </article>
                <article class="tile tile--accent">
                    <span class="tile__icon" aria-hidden="true">👆</span>
                    <p class="tile__label">Lượt truy cập</p>
                    <p class="tile__value" data-count-to="<?= (int) $summary['clicks'] ?>">0</p>
                </article>
                <article class="tile tile--info">
                    <span class="tile__icon" aria-hidden="true">👥</span>
                    <p class="tile__label">Người dùng</p>
                    <p class="tile__value" data-count-to="<?= (int) $summary['users'] ?>">0</p>
                </article>
            </div>

            <h2>Về quyền riêng tư</h2>
            <p>Hệ thống ghi nhận lượt truy cập ở mức tối thiểu cần cho thống kê:</p>
            <ul>
                <li>
                    <strong>Không lưu địa chỉ IP</strong> của người truy cập. IP chỉ được dùng ngay lúc đó
                    để tính một mã băm ẩn danh (không thể suy ngược) nhằm phân biệt khách riêng biệt.
                </li>
                <li>Có lưu: thời điểm, loại thiết bị, tên trình duyệt, hệ điều hành, tên miền nguồn giới thiệu.</li>
                <li>Thông tin quốc gia (nếu có) lấy từ dịch vụ CDN hoặc suy từ ngôn ngữ trình duyệt — chỉ để tham khảo.</li>
                <li>Quản trị viên có thể xoá chi tiết lượt truy cập cũ, hoặc đặt hệ thống tự xoá theo số ngày.</li>
            </ul>

            <h2>Công nghệ</h2>
            <p>
                Hệ thống viết bằng PHP thuần, không dùng thư viện ngoài, dữ liệu lưu trong
                <strong>một tệp SQLite duy nhất</strong>. Bộ tạo mã QR và toàn bộ biểu đồ đều
                được viết riêng cho hệ thống này, nên trang chạy nhanh và không gọi ra Internet.
                Mọi mốc thời gian dùng múi giờ <strong><?= e(Clock::tzLabel()) ?></strong>.
            </p>

            <h2>Hỗ trợ</h2>
            <p>
                Cần cấp tài khoản, đặt lại mật khẩu hay báo lỗi, vui lòng liên hệ
                <?= e((string) Config::get('site_owner')) ?>.
                Xem thêm <a href="<?= e(url('/huong-dan')) ?>">hướng dẫn sử dụng</a>.
            </p>
        </div>

        <div class="card credit-card">
            <p class="credit-card__label">Thiết kế và phát triển</p>
            <p class="credit-card__name"><?= e((string) Config::get('footer_credit')) ?></p>
        </div>
    </div>
</section>
