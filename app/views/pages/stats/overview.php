<?php
/**
 * Thống kê tổng hợp.
 *
 * @var array<string, mixed> $user
 * @var int|null             $scope
 * @var int                  $days
 * @var array<string, mixed> $overview
 * @var array<int, array>    $series
 * @var array<int, array>    $hourly
 * @var array<int, array>    $weekday
 * @var array<int, array>    $devices
 * @var array<int, array>    $browsers
 * @var array<int, array>    $systems
 * @var array<int, array>    $referers
 * @var array<int, array>    $countries
 * @var array<int, array>    $sources
 * @var array<int, array>    $topLinks
 * @var int                  $qrClicks
 * @var int                  $botClicks
 * @var array{date: string, clicks: int}|null $busiestDay
 */
$isAdmin = $user['role'] === 'admin';
$peakHour = null;
foreach ($hourly as $item) {
    if ($peakHour === null || $item['value'] > $peakHour['value']) {
        $peakHour = $item;
    }
}
$countryDecorator = static function (string $label): string {
    if ($label === '—' || $label === '') {
        return '🌐 Không xác định';
    }
    return Ua::countryFlag($label) . ' ' . e(Ua::countryName($label));
};
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Thống kê</p>
                <h1 class="page-head__title">Tổng hợp</h1>
                <p class="page-head__sub">
                    <?= $scope === null ? 'Số liệu toàn hệ thống' : 'Số liệu các liên kết do bạn tạo' ?> ·
                    giờ Việt Nam (GMT+7)
                </p>
            </div>
            <div class="page-head__actions">
                <?php if ($isAdmin): ?>
                    <a class="btn btn--ghost btn--sm"
                       href="<?= e(url('/thong-ke' . ($scope === null ? '' : '?pham-vi=tat-ca'))) ?>">
                        <?= $scope === null ? 'Chỉ của tôi' : 'Cả hệ thống' ?>
                    </a>
                <?php endif; ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/xuat-csv')) ?>">Xuất CSV</a>
            </div>
        </header>

        <div class="tiles">
            <?php
            $tiles = [
                ['🔗', 'Liên kết', n((int) $overview['total_links']), n((int) $overview['active_links']) . ' đang chạy', 'primary'],
                ['👆', 'Lượt nhấp', n((int) $overview['total_clicks']), 'từ khi bắt đầu dùng', 'accent'],
                ['🧍', 'Khách riêng', n((int) $overview['unique_clicks']), 'tính theo dấu vết ẩn danh', 'info'],
                ['📅', 'Hôm nay', n((int) $overview['clicks_today']), n((int) $overview['clicks_week']) . ' lượt trong 7 ngày', 'success'],
                ['📱', 'Quét QR', n($qrClicks), 'lượt từ mã QR', 'warning'],
                ['🤖', 'Robot', n($botClicks), 'lượt do máy quét tự động', 'muted'],
            ];
            foreach ($tiles as [$icon, $label, $value, $note, $tone]): ?>
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
                    <h2 class="card__title">Diễn biến theo ngày</h2>
                    <?php if ($busiestDay !== null): ?>
                        <p class="card__sub">
                            Cao nhất: <?= e(Clock::formatDate($busiestDay['date'])) ?>
                            (<?= e(n($busiestDay['clicks'])) ?> lượt)
                        </p>
                    <?php endif; ?>
                </div>
                <div class="segmented" role="group" aria-label="Chọn khoảng thời gian">
                    <?php foreach ([7 => '7 ngày', 14 => '14 ngày', 30 => '30 ngày', 90 => '90 ngày', 365 => '1 năm'] as $value => $label): ?>
                        <a class="segmented__item<?= $days === $value ? ' is-active' : '' ?>"
                           href="<?= e(query_url(['ngay' => $value])) ?>"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
            </header>
            <div class="chart-wrap"><?= Chart::area($series, 'Lượt nhấp theo ngày') ?></div>
        </div>

        <div class="grid grid--2">
            <div class="card card--chart">
                <h2 class="card__title">Giờ nào đông nhất?</h2>
                <p class="card__sub">
                    <?php if ($peakHour !== null && $peakHour['value'] > 0): ?>
                        Đông nhất vào khoảng <strong><?= e($peakHour['label']) ?></strong>
                        với <?= e(n((int) $peakHour['value'])) ?> lượt nhấp.
                    <?php else: ?>
                        Chưa có dữ liệu theo giờ.
                    <?php endif; ?>
                </p>
                <div class="chart-wrap"><?= Chart::bars($hourly) ?></div>
            </div>

            <div class="card card--chart">
                <h2 class="card__title">Theo thứ trong tuần</h2>
                <p class="card__sub">Giúp bạn chọn thời điểm gửi thông báo cho hiệu quả.</p>
                <div class="chart-wrap"><?= Chart::bars($weekday) ?></div>
            </div>
        </div>

        <div class="grid grid--3">
            <div class="card">
                <h2 class="card__title">Thiết bị</h2>
                <?= Chart::donut($devices, 'lượt nhấp') ?>
            </div>
            <div class="card">
                <h2 class="card__title">Trình duyệt</h2>
                <?= Chart::ranking($browsers) ?>
            </div>
            <div class="card">
                <h2 class="card__title">Hệ điều hành</h2>
                <?= Chart::ranking($systems) ?>
            </div>
        </div>

        <div class="grid grid--3">
            <div class="card">
                <h2 class="card__title">Nguồn giới thiệu</h2>
                <?= Chart::ranking($referers) ?>
            </div>
            <div class="card">
                <h2 class="card__title">Cách tiếp cận</h2>
                <?= Chart::ranking($sources) ?>
                <p class="card__foot-note">
                    Lượt quét mã QR được nhận diện nhờ dấu <code>?s=qr</code> gắn trong mã.
                </p>
            </div>
            <div class="card">
                <h2 class="card__title">Quốc gia / vùng</h2>
                <?= Chart::ranking($countries, 'Chưa có dữ liệu.', $countryDecorator) ?>
                <p class="card__foot-note">
                    Suy ra từ thông tin do dịch vụ CDN cung cấp, hoặc từ ngôn ngữ trình duyệt —
                    mang tính tham khảo.
                </p>
            </div>
        </div>

        <?php if ($topLinks !== []): ?>
            <div class="card">
                <h2 class="card__title">Bảng xếp hạng liên kết</h2>
                <div class="table-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="table__num">#</th>
                                <th>Liên kết</th>
                                <th>Tiêu đề</th>
                                <th class="table__num">Lượt nhấp</th>
                                <th class="table__num">Khách riêng</th>
                                <th>Nhấp gần nhất</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topLinks as $index => $row): ?>
                                <tr>
                                    <td class="table__num"><?= $index + 1 ?></td>
                                    <td>
                                        <a class="mono-link" href="<?= e(short_url((string) $row['code'])) ?>"
                                           target="_blank" rel="noopener">/<?= e((string) $row['code']) ?></a>
                                    </td>
                                    <td><?= e(truncate_str((string) ($row['title'] ?? '—'), 40)) ?></td>
                                    <td class="table__num"><strong><?= e(n((int) $row['click_count'])) ?></strong></td>
                                    <td class="table__num"><?= e(n((int) $row['unique_count'])) ?></td>
                                    <td><?= e($row['last_click_at'] !== null ? Clock::human((string) $row['last_click_at']) : '—') ?></td>
                                    <td class="table__actions">
                                        <a class="btn btn--ghost btn--xs" href="<?= e(url('/thong-ke/' . (int) $row['id'])) ?>">Chi tiết</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="card empty-state">
                <span class="empty-state__emoji" aria-hidden="true">📊</span>
                <h2>Chưa có số liệu</h2>
                <p>Khi có người bấm vào liên kết của bạn, số liệu sẽ hiện ở đây.</p>
                <a class="btn btn--primary" href="<?= e(url('/lien-ket/tao')) ?>">Tạo liên kết mới</a>
            </div>
        <?php endif; ?>
    </div>
</section>
