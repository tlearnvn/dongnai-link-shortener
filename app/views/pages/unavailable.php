<?php
/**
 * Liên kết tồn tại nhưng hiện không dùng được (hết hạn, tạm dừng, đủ lượt…).
 *
 * @var array<string, mixed> $link
 * @var array{key: string, label: string, tone: string, reason: string} $state
 */
$emoji = match ($state['key']) {
    'expired' => '⌛',
    'paused' => '⏸️',
    'scheduled' => '📅',
    'exhausted' => '🎯',
    default => '⚠️',
};
$heading = match ($state['key']) {
    'expired' => 'Liên kết đã hết hạn',
    'paused' => 'Liên kết đang tạm dừng',
    'scheduled' => 'Liên kết chưa tới giờ hoạt động',
    'exhausted' => 'Liên kết đã dùng hết số lượt cho phép',
    default => 'Liên kết hiện không dùng được',
};
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <div class="card card--glass state-page">
            <span class="state-page__emoji" aria-hidden="true"><?= $emoji ?></span>
            <p class="state-page__code"><span class="badge badge--<?= e($state['tone']) ?>"><?= e($state['label']) ?></span></p>
            <h1 class="state-page__title"><?= e($heading) ?></h1>
            <p class="state-page__message"><?= e($state['reason']) ?></p>
            <p class="state-page__hint">
                Nếu bạn cần nội dung này, vui lòng liên hệ người đã gửi liên kết cho bạn
                (<code>/<?= e((string) $link['code']) ?></code>) để được cấp lại.
            </p>
            <div class="state-page__actions">
                <a class="btn btn--primary" href="<?= e(url('/')) ?>">Về trang chủ</a>
                <?php if (LinkService::canManage($link, Auth::user())): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/lien-ket/' . (int) $link['id'] . '/sua')) ?>">
                        Sửa liên kết này
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
