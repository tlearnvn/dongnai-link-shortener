<?php
/**
 * Trang thông báo lỗi chung.
 *
 * @var string $title
 * @var string $message
 * @var int    $code
 */
$emoji = match ((int) ($code ?? 0)) {
    403 => '🚫',
    404 => '🔍',
    405 => '🙃',
    419 => '⏱️',
    500 => '🛠️',
    default => '⚠️',
};
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <div class="card card--glass state-page">
            <span class="state-page__emoji" aria-hidden="true"><?= $emoji ?></span>
            <?php if (!empty($code)): ?>
                <p class="state-page__code">Lỗi <?= (int) $code ?></p>
            <?php endif; ?>
            <h1 class="state-page__title"><?= e((string) $title) ?></h1>
            <p class="state-page__message"><?= e((string) $message) ?></p>
            <div class="state-page__actions">
                <a class="btn btn--primary" href="<?= e(url('/')) ?>">Về trang chủ</a>
                <a class="btn btn--ghost" href="<?= e(url('/huong-dan')) ?>">Xem hướng dẫn</a>
            </div>
        </div>
    </div>
</section>
