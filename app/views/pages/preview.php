<?php
/**
 * Trang xem trước: cho biết liên kết dẫn tới đâu trước khi mở.
 *
 * @var array<string, mixed> $link
 * @var array<string, mixed> $state
 * @var bool                 $canManage
 */
$code = (string) $link['code'];
$targetHost = host_of((string) $link['target_url']);
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <div class="card card--glass preview">
            <p class="preview__eyebrow">Xem trước liên kết</p>
            <h1 class="preview__code">/<?= e($code) ?></h1>

            <div class="preview__target">
                <span class="host-chip host-chip--lg"
                      style="--host-hue: <?= (int) (hexdec(substr(md5($targetHost), 0, 4)) % 360) ?>"
                      aria-hidden="true"><?= e($targetHost !== '' ? mb_strtoupper(mb_substr($targetHost, 0, 1)) : '?') ?></span>
                <div>
                    <p class="preview__host"><?= e($targetHost) ?></p>
                    <p class="preview__url"><?= e(truncate_str((string) $link['target_url'], 120)) ?></p>
                </div>
            </div>

            <?php if (!empty($link['title'])): ?>
                <p class="preview__title"><?= e((string) $link['title']) ?></p>
            <?php endif; ?>

            <dl class="preview__meta">
                <div>
                    <dt>Trạng thái</dt>
                    <dd><span class="badge badge--<?= e((string) $state['tone']) ?>"><?= e((string) $state['label']) ?></span></dd>
                </div>
                <div>
                    <dt>Lượt nhấp</dt>
                    <dd><?= e(n((int) $link['click_count'])) ?></dd>
                </div>
                <div>
                    <dt>Tạo lúc</dt>
                    <dd><?= e(Clock::formatShort((string) $link['created_at'])) ?></dd>
                </div>
                <?php if ($link['expires_at'] !== null): ?>
                    <div>
                        <dt>Hết hạn</dt>
                        <dd><?= e(Clock::formatShort((string) $link['expires_at'])) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($link['password_hash'] !== null): ?>
                <p class="preview__notice">🔒 Liên kết này có mật khẩu bảo vệ.</p>
            <?php endif; ?>

            <div class="preview__actions">
                <?php if ((string) $state['key'] === 'active'): ?>
                    <a class="btn btn--primary btn--lg" href="<?= e(short_url($code)) ?>">Đi tới trang đích</a>
                <?php else: ?>
                    <p class="preview__notice preview__notice--warn"><?= e((string) $state['reason']) ?></p>
                <?php endif; ?>
                <button class="btn btn--ghost" type="button" data-copy="<?= e(short_url($code)) ?>">Sao chép liên kết</button>
                <a class="btn btn--ghost" href="<?= e(url('/ma-qr/' . rawurlencode($code))) ?>">Mã QR</a>
                <?php if ($canManage): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/thong-ke/' . (int) $link['id'])) ?>">Thống kê</a>
                <?php endif; ?>
            </div>

            <p class="preview__hint">
                Mẹo: thêm <code>/xem/</code> trước mã bất kỳ để xem trước mà không tính vào lượt nhấp.
                Ví dụ: <code><?= e(short_url_display('xem/' . $code)) ?></code>
            </p>
        </div>
    </div>
</section>
