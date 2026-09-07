<?php
/**
 * Bảng điều khiển.
 *
 * @var array<string, mixed> $user
 * @var int|null             $scope    null = toàn hệ thống (quản trị viên)
 * @var int                  $days
 * @var array<string, mixed> $overview
 * @var array<int, array>    $series
 * @var array<int, array>    $topLinks
 * @var array<int, array>    $recentLinks
 * @var array<int, array>    $devices
 * @var array<int, array>    $browsers
 * @var array<int, array>    $referers
 * @var int                  $qrClicks
 * @var int                  $botClicks
 * @var array{date: string, clicks: int}|null $busiestDay
 * @var array<string, int>   $tags
 */
$isAdmin = $user['role'] === 'admin';
$sparks = Stats::sparkForLinks(array_map(static fn(array $l): int => (int) $l['id'], $recentLinks), 14);

$tiles = [
    ['🔗', 'Liên kết', n((int) $overview['total_links']), n((int) $overview['active_links']) . ' đang chạy', 'primary'],
    ['👆', 'Tổng lượt nhấp', n((int) $overview['total_clicks']), n((int) $overview['unique_clicks']) . ' khách riêng', 'accent'],
    ['📅', 'Hôm nay', n((int) $overview['clicks_today']), 'lượt nhấp trong ngày', 'success'],
    ['📈', '7 ngày qua', n((int) $overview['clicks_week']), n((int) $overview['clicks_month']) . ' lượt trong 30 ngày', 'info'],
    ['📱', 'Quét mã QR', n($qrClicks), 'lượt đến từ mã QR', 'warning'],
    ['🎯', 'Trung bình', number_format((float) $overview['avg_per_link'], 1, ',', '.'), 'lượt nhấp mỗi liên kết', 'muted'],
];
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">
                    Xin chào, <?= e(Auth::displayName()) ?>
                    <?php if ($isAdmin): ?><span class="badge badge--admin">quản trị viên</span><?php endif; ?>
                </p>
                <h1 class="page-head__title">Bảng điều khiển</h1>
                <p class="page-head__sub">
                    <?= $scope === null ? 'Đang xem số liệu của <strong>toàn hệ thống</strong>.' : 'Đang xem số liệu của <strong>liên kết do bạn tạo</strong>.' ?>
                    Mọi mốc thời gian theo giờ Việt Nam (GMT+7).
                </p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--primary btn--sm" href="<?= e(url('/lien-ket/tao')) ?>">+ Liên kết mới</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/tao-hang-loat')) ?>">Tạo hàng loạt</a>
                <?php if ($isAdmin): ?>
                    <a class="btn btn--ghost btn--sm"
                       href="<?= e(url('/bang-dieu-khien' . ($scope === null ? '' : '?pham-vi=tat-ca'))) ?>">
                        <?= $scope === null ? 'Chỉ xem của tôi' : 'Xem cả hệ thống' ?>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="tiles">
            <?php foreach ($tiles as [$icon, $label, $value, $note, $tone]): ?>
                <article class="tile tile--<?= e($tone) ?>">
                    <span class="tile__icon" aria-hidden="true"><?= $icon ?></span>
                    <p class="tile__label"><?= e($label) ?></p>
                    <p class="tile__value"><?= e($value) ?></p>
                    <p class="tile__note"><?= e($note) ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="card card--chart">
            <header class="card__head">
                <div>
                    <h2 class="card__title">Lượt nhấp theo ngày</h2>
                    <p class="card__sub">
                        Đường đậm là tổng lượt nhấp, đường mảnh là số khách riêng biệt.
                    </p>
                </div>
                <div class="segmented" role="group" aria-label="Chọn khoảng thời gian">
                    <?php foreach ([7 => '7 ngày', 14 => '14 ngày', 30 => '30 ngày', 90 => '90 ngày'] as $value => $label): ?>
                        <a class="segmented__item<?= $days === $value ? ' is-active' : '' ?>"
                           href="<?= e(query_url(['ngay' => $value])) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </header>

            <div class="chart-wrap"><?= Chart::area($series, 'Lượt nhấp theo ngày') ?></div>

            <?php if ($busiestDay !== null): ?>
                <p class="card__foot-note">
                    🏆 Ngày nhiều nhất: <strong><?= e(Clock::formatDate($busiestDay['date'])) ?></strong>
                    với <?= e(n($busiestDay['clicks'])) ?> lượt nhấp.
                    <?php if ($botClicks > 0): ?>
                        · 🤖 <?= e(n($botClicks)) ?> lượt đến từ robot/công cụ quét (đã ghi nhận riêng).
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="grid grid--3">
            <div class="card">
                <h2 class="card__title">Thiết bị người xem</h2>
                <?= Chart::donut($devices, 'lượt nhấp') ?>
            </div>

            <div class="card">
                <h2 class="card__title">Trình duyệt</h2>
                <?= Chart::ranking($browsers, 'Chưa có ai truy cập liên kết của bạn.') ?>
            </div>

            <div class="card">
                <h2 class="card__title">Nguồn giới thiệu</h2>
                <?= Chart::ranking($referers, 'Chưa ghi nhận nguồn nào.') ?>
                <p class="card__foot-note">
                    “Truy cập trực tiếp” là khi người dùng gõ liên kết, quét QR hoặc mở từ
                    ứng dụng không gửi thông tin nguồn (Zalo, Messenger…).
                </p>
            </div>
        </div>

        <?php if ($topLinks !== []): ?>
            <div class="card">
                <header class="card__head">
                    <h2 class="card__title">Liên kết được nhấp nhiều nhất</h2>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/thong-ke')) ?>">Thống kê chi tiết</a>
                </header>

                <div class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Liên kết</th>
                                <th>Địa chỉ đích</th>
                                <th class="table__num">Lượt nhấp</th>
                                <th class="table__num">Khách riêng</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topLinks as $row):
                                $state = LinkService::state($row); ?>
                                <tr>
                                    <td>
                                        <a class="mono-link" href="<?= e(short_url((string) $row['code'])) ?>"
                                           target="_blank" rel="noopener">/<?= e((string) $row['code']) ?></a>
                                        <?php if (!empty($row['title'])): ?>
                                            <small class="table__sub"><?= e(truncate_str((string) $row['title'], 44)) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="table__url" title="<?= e((string) $row['target_url']) ?>">
                                            <?= e(truncate_str((string) $row['target_url'], 46)) ?>
                                        </span>
                                    </td>
                                    <td class="table__num"><strong><?= e(n((int) $row['click_count'])) ?></strong></td>
                                    <td class="table__num"><?= e(n((int) $row['unique_count'])) ?></td>
                                    <td><span class="badge badge--<?= e($state['tone']) ?>"><?= e($state['label']) ?></span></td>
                                    <td class="table__actions">
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/thong-ke/' . (int) $row['id'])) ?>">Chi tiết</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($tags !== []): ?>
            <div class="card">
                <h2 class="card__title">Thẻ phân loại</h2>
                <div class="tag-cloud">
                    <?php foreach (array_slice($tags, 0, 24, true) as $tag => $count): ?>
                        <a class="tag tag--lg" href="<?= e(url('/lien-ket?tag=' . rawurlencode((string) $tag))) ?>">
                            #<?= e((string) $tag) ?><span><?= (int) $count ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($recentLinks !== []): ?>
            <section class="section section--tight">
                <header class="section__head">
                    <h2 class="section__title">Liên kết mới tạo</h2>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/lien-ket')) ?>">Quản lý tất cả</a>
                </header>
                <div class="link-grid">
                    <?php foreach ($recentLinks as $link): ?>
                        <?= render('partials/link-card', [
                            'link' => $link,
                            'spark' => $sparks[(int) $link['id']] ?? null,
                            'showOwner' => $scope === null,
                        ]) ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else: ?>
            <div class="card empty-state">
                <span class="empty-state__emoji" aria-hidden="true">🌱</span>
                <h2>Chưa có liên kết nào</h2>
                <p>Bắt đầu bằng việc rút gọn địa chỉ đầu tiên của bạn — chỉ mất vài giây.</p>
                <a class="btn btn--primary" href="<?= e(url('/lien-ket/tao')) ?>">Tạo liên kết đầu tiên</a>
            </div>
        <?php endif; ?>
    </div>
</section>
