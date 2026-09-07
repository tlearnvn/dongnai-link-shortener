<?php
/**
 * Thẻ hiển thị một liên kết rút gọn.
 *
 * @var array<string, mixed>   $link
 * @var array<int, int>|null   $spark      Số lượt nhấp 14 ngày gần nhất (nếu có)
 * @var bool                   $showOwner  Hiện tên người tạo (dùng ở trang quản trị)
 */

$state = LinkService::state($link);
$code = (string) $link['code'];
$tags = LinkService::tagList($link['tags'] ?? null);
$canManage = LinkService::canManage($link, Auth::user());
$spark = $spark ?? null;
$showOwner = $showOwner ?? false;
$clicks = (int) $link['click_count'];
$maxClicks = $link['max_clicks'] !== null ? (int) $link['max_clicks'] : null;
?>
<article class="link-card link-card--<?= e($state['tone']) ?>">
    <header class="link-card__top">
        <div class="link-card__identity">
            <a class="link-card__code" href="<?= e(short_url($code)) ?>" target="_blank" rel="noopener">
                <span class="link-card__slash">/</span><?= e($code) ?>
            </a>
            <?php if ((int) $link['is_starred'] === 1): ?>
                <span class="pill pill--star" title="Liên kết quan trọng">⭐</span>
            <?php endif; ?>
            <?php if ($link['password_hash'] !== null): ?>
                <span class="pill pill--lock" title="Có mật khẩu bảo vệ">🔒</span>
            <?php endif; ?>
            <span class="badge badge--<?= e($state['tone']) ?>"><?= e($state['label']) ?></span>
        </div>

        <button class="icon-btn" type="button" data-copy="<?= e(short_url($code)) ?>"
                title="Sao chép liên kết" aria-label="Sao chép liên kết <?= e($code) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="9" y="9" width="11" height="11" rx="2.5"/>
                <path d="M15.5 6.5V5.4A2.4 2.4 0 0 0 13.1 3H5.4A2.4 2.4 0 0 0 3 5.4v7.7A2.4 2.4 0 0 0 5.4 15.5h1.1"/>
            </svg>
        </button>
    </header>

    <?php if (!empty($link['title'])): ?>
        <h3 class="link-card__title"><?= e(truncate_str((string) $link['title'], 70)) ?></h3>
    <?php endif; ?>

<?php
// Nhãn tên miền tự sinh tại chỗ — không gọi dịch vụ favicon bên ngoài để
// tránh tiết lộ danh sách địa chỉ đích cho bên thứ ba.
$targetHost = host_of((string) $link['target_url']);
$hostInitial = $targetHost !== '' ? mb_strtoupper(mb_substr($targetHost, 0, 1)) : '?';
// Màu nhãn suy ra từ tên miền để mỗi trang web có một sắc riêng, ổn định.
$hostHue = $targetHost === '' ? 220 : (int) (hexdec(substr(md5($targetHost), 0, 4)) % 360);
?>
    <a class="link-card__target" href="<?= e((string) $link['target_url']) ?>" target="_blank"
       rel="noopener nofollow" title="<?= e((string) $link['target_url']) ?>">
        <span class="host-chip" style="--host-hue: <?= $hostHue ?>" aria-hidden="true"><?= e($hostInitial) ?></span>
        <span><?= e(truncate_str((string) $link['target_url'], 62)) ?></span>
    </a>

    <?php if ($tags !== []): ?>
        <div class="link-card__tags">
            <?php foreach ($tags as $tag): ?>
                <a class="tag" href="<?= e(url('/lien-ket?tag=' . rawurlencode($tag))) ?>">#<?= e($tag) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="link-card__stats">
        <div class="link-card__metric">
            <strong><?= e(n($clicks)) ?></strong>
            <span>lượt nhấp<?= $maxClicks !== null ? ' / ' . e(n($maxClicks)) : '' ?></span>
        </div>
        <div class="link-card__metric">
            <strong><?= e(n((int) $link['unique_count'])) ?></strong>
            <span>khách riêng</span>
        </div>
        <?php if ($spark !== null): ?>
            <div class="link-card__spark" title="Lượt nhấp 14 ngày gần nhất">
                <?= Chart::sparkline($spark) ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="link-card__foot">
        <span class="link-card__time" title="Tạo lúc <?= e(Clock::formatShort((string) $link['created_at'])) ?>">
            🗓️ <?= e(Clock::human((string) $link['created_at'])) ?>
        </span>
        <?php if ($link['last_click_at'] !== null): ?>
            <span class="link-card__time" title="Lượt nhấp gần nhất: <?= e(Clock::formatShort((string) $link['last_click_at'])) ?>">
                👀 <?= e(Clock::human((string) $link['last_click_at'])) ?>
            </span>
        <?php endif; ?>
        <?php if ($link['expires_at'] !== null): ?>
            <span class="link-card__time" title="Hết hạn <?= e(Clock::formatShort((string) $link['expires_at'])) ?>">
                ⏳ <?= e(Clock::human((string) $link['expires_at'])) ?>
            </span>
        <?php endif; ?>
        <?php if ($showOwner): ?>
            <span class="link-card__time">
                👤 <?= e((string) ($link['owner_username'] ?? 'khách')) ?>
            </span>
        <?php endif; ?>
    </footer>

    <div class="link-card__actions">
        <a class="btn btn--ghost btn--xs" href="<?= e(url('/ma-qr/' . rawurlencode($code))) ?>">Mã QR</a>
        <a class="btn btn--ghost btn--xs" href="<?= e(url('/xem/' . rawurlencode($code))) ?>">Xem trước</a>
        <?php if ($canManage): ?>
            <a class="btn btn--ghost btn--xs" href="<?= e(url('/thong-ke/' . (int) $link['id'])) ?>">Thống kê</a>
            <a class="btn btn--ghost btn--xs" href="<?= e(url('/lien-ket/' . (int) $link['id'] . '/sua')) ?>">Sửa</a>

            <form method="post" action="<?= e(url('/lien-ket/' . (int) $link['id'] . '/danh-dau')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--xs" type="submit">
                    <?= (int) $link['is_starred'] === 1 ? 'Bỏ ghim' : 'Ghim' ?>
                </button>
            </form>

            <form method="post" action="<?= e(url('/lien-ket/' . (int) $link['id'] . '/trang-thai')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--xs" type="submit">
                    <?= (int) $link['is_active'] === 1 ? 'Tạm dừng' : 'Bật lại' ?>
                </button>
            </form>

            <form method="post" action="<?= e(url('/lien-ket/' . (int) $link['id'] . '/xoa')) ?>"
                  data-confirm="Xoá liên kết /<?= e($code) ?>? Toàn bộ số liệu thống kê của nó cũng sẽ mất.">
                <?= csrf_field() ?>
                <button class="btn btn--danger btn--xs" type="submit">Xoá</button>
            </form>
        <?php endif; ?>
    </div>
</article>
