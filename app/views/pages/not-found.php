<?php
/**
 * Không tìm thấy mã rút gọn.
 *
 * @var string $code
 */
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <div class="card card--glass state-page">
            <span class="state-page__emoji" aria-hidden="true">🧐</span>
            <p class="state-page__code">Không tìm thấy</p>
            <h1 class="state-page__title">Liên kết này không tồn tại</h1>
            <p class="state-page__message">
                Hệ thống không có liên kết nào ứng với
                <code class="state-page__slug">/<?= e(truncate_str($code, 40)) ?></code>.
                Có thể liên kết đã bị xoá, hoặc lúc gõ bị thiếu/thừa một ký tự.
                (Phần tên không phân biệt chữ hoa chữ thường, nên đó không phải nguyên nhân.)
            </p>
            <div class="state-page__actions">
                <a class="btn btn--primary" href="<?= e(url('/')) ?>">Rút gọn một liên kết mới</a>
                <a class="btn btn--ghost" href="<?= e(url('/huong-dan')) ?>">Hướng dẫn</a>
            </div>
        </div>
    </div>
</section>
