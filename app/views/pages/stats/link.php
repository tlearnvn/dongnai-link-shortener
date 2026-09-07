<?php
/**
 * Thống kê chi tiết một liên kết.
 *
 * @var array<string, mixed> $link
 * @var array<string, mixed> $state
 * @var int                  $days
 * @var array<int, array>    $series
 * @var array<int, array>    $hourly
 * @var array<int, array>    $weekday
 * @var array<int, array>    $devices
 * @var array<int, array>    $browsers
 * @var array<int, array>    $systems
 * @var array<int, array>    $referers
 * @var array<int, array>    $countries
 * @var array<int, array>    $sources
 * @var array<int, array>    $recentClicks
 * @var int                  $qrClicks
 * @var int                  $botClicks
 * @var array{date: string, clicks: int}|null $busiestDay
 */
$code = (string) $link['code'];
$clicks = (int) $link['click_count'];
$maxClicks = $link['max_clicks'] !== null ? (int) $link['max_clicks'] : null;
$tags = LinkService::tagList($link['tags'] ?? null);

// Tỉ lệ đã dùng của giới hạn lượt nhấp và của thời hạn
$clickPercent = $maxClicks !== null && $maxClicks > 0 ? $clicks * 100 / $maxClicks : null;
$timePercent = null;
$expiresDt = Clock::parse($link['expires_at'] ?? null);
if ($expiresDt !== null) {
    $createdTs = (Clock::parse((string) $link['created_at']) ?? Clock::nowDt())->getTimestamp();
    $total = $expiresDt->getTimestamp() - $createdTs;
    $elapsed = Clock::nowDt()->getTimestamp() - $createdTs;
    $timePercent = $total > 0 ? max(0.0, min(100.0, $elapsed * 100 / $total)) : 100.0;
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
                <p class="page-head__eyebrow">
                    Thống kê liên kết
                    <span class="badge badge--<?= e((string) $state['tone']) ?>"><?= e((string) $state['label']) ?></span>
                </p>
                <h1 class="page-head__title">
                    <a class="page-head__link" href="<?= e(short_url($code)) ?>" target="_blank" rel="noopener">/<?= e($code) ?></a>
                </h1>
                <p class="page-head__sub">
                    → <a href="<?= e((string) $link['target_url']) ?>" target="_blank" rel="noopener nofollow">
                        <?= e(truncate_str((string) $link['target_url'], 80)) ?>
                    </a>
                </p>
                <?php if ($tags !== []): ?>
                    <div class="page-head__tags">
                        <?php foreach ($tags as $tag): ?>
                            <a class="tag" href="<?= e(url('/lien-ket?tag=' . rawurlencode($tag))) ?>">#<?= e($tag) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="page-head__actions">
                <button class="btn btn--primary btn--sm" type="button" data-copy="<?= e(short_url($code)) ?>">Sao chép</button>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/ma-qr/' . rawurlencode($code))) ?>">Mã QR</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/lien-ket/' . (int) $link['id'] . '/sua')) ?>">Sửa</a>
            </div>
        </header>

        <?php if ((string) $state['key'] !== 'active'): ?>
            <div class="alert alert--warning">
                <span class="alert__icon" aria-hidden="true">⚠️</span>
                <p class="alert__text"><?= e((string) $state['reason']) ?></p>
            </div>
        <?php endif; ?>

        <div class="tiles">
            <?php
            $tiles = [
                ['👆', 'Tổng lượt nhấp', n($clicks), $maxClicks !== null ? 'giới hạn ' . n($maxClicks) : 'không giới hạn', 'primary'],
                ['🧍', 'Khách riêng', n((int) $link['unique_count']), $clicks > 0 ? number_format((int) $link['unique_count'] * 100 / $clicks, 1, ',', '.') . '% tổng lượt' : '—', 'accent'],
                ['📱', 'Quét mã QR', n($qrClicks), $clicks > 0 ? number_format($qrClicks * 100 / $clicks, 1, ',', '.') . '% tổng lượt' : '—', 'warning'],
                ['🕒', 'Nhấp gần nhất', $link['last_click_at'] !== null ? Clock::human((string) $link['last_click_at']) : 'chưa có', 'tạo ' . Clock::human((string) $link['created_at']), 'info'],
            ];
            foreach ($tiles as [$icon, $label, $value, $note, $tone]): ?>
                <article class="tile tile--<?= e($tone) ?>">
                    <span class="tile__icon" aria-hidden="true"><?= $icon ?></span>
                    <p class="tile__label"><?= e($label) ?></p>
                    <p class="tile__value tile__value--sm"><?= e($value) ?></p>
                    <p class="tile__note"><?= e($note) ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($clickPercent !== null || $timePercent !== null): ?>
            <div class="card gauges">
                <?php if ($clickPercent !== null): ?>
                    <?= Chart::gauge($clickPercent, 'Đã dùng ' . n($clicks) . '/' . n((int) $maxClicks) . ' lượt') ?>
                <?php endif; ?>
                <?php if ($timePercent !== null): ?>
                    <?= Chart::gauge($timePercent, 'Thời hạn còn ' . Clock::human((string) $link['expires_at'])) ?>
                <?php endif; ?>
                <div class="gauges__note">
                    <p>
                        <?php if ($timePercent !== null): ?>
                            Liên kết hết hạn lúc <strong><?= e(Clock::formatShort((string) $link['expires_at'])) ?></strong> (GMT+7).
                        <?php endif; ?>
                        <?php if ($clickPercent !== null && $clickPercent >= 80): ?>
                            Sắp đạt giới hạn lượt nhấp — hãy nâng giới hạn nếu vẫn cần dùng.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card card--chart">
            <header class="card__head">
                <div>
                    <h2 class="card__title">Lượt nhấp theo ngày</h2>
                    <?php if ($busiestDay !== null): ?>
                        <p class="card__sub">
                            Ngày cao nhất: <?= e(Clock::formatDate($busiestDay['date'])) ?>
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
            <div class="chart-wrap"><?= Chart::area($series, 'Lượt nhấp theo ngày của /' . $code) ?></div>
        </div>

        <div class="grid grid--2">
            <div class="card card--chart">
                <h2 class="card__title">Theo giờ trong ngày</h2>
                <div class="chart-wrap"><?= Chart::bars($hourly) ?></div>
            </div>
            <div class="card card--chart">
                <h2 class="card__title">Theo thứ trong tuần</h2>
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
            </div>
            <div class="card">
                <h2 class="card__title">Quốc gia / vùng</h2>
                <?= Chart::ranking($countries, 'Chưa có dữ liệu.', $countryDecorator) ?>
            </div>
        </div>

        <div class="card">
            <header class="card__head">
                <div>
                    <h2 class="card__title">Lượt nhấp gần đây</h2>
                    <p class="card__sub">
                        Hệ thống <strong>không lưu địa chỉ IP</strong> của người truy cập —
                        chỉ lưu dấu vết ẩn danh đã băm để đếm khách riêng biệt.
                    </p>
                </div>
                <form method="post" action="<?= e(url('/lien-ket/' . (int) $link['id'] . '/xoa-thong-ke')) ?>"
                      data-confirm="Xoá toàn bộ số liệu thống kê của /<?= e($code) ?>?">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost btn--sm" type="submit">Xoá số liệu</button>
                </form>
            </header>

            <?php if ($recentClicks === []): ?>
                <p class="chart-empty">Chưa có ai bấm vào liên kết này. Hãy chia sẻ nó đi! 🚀</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Thời điểm (GMT+7)</th>
                                <th>Thiết bị</th>
                                <th>Trình duyệt</th>
                                <th>Hệ điều hành</th>
                                <th>Nguồn</th>
                                <th>Vùng</th>
                                <th>Loại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentClicks as $click): ?>
                                <tr>
                                    <td>
                                        <?= e(Clock::formatShort((string) $click['clicked_at'])) ?>
                                        <small class="table__sub"><?= e(Clock::human((string) $click['clicked_at'])) ?></small>
                                    </td>
                                    <td><?= e((string) ($click['device'] ?? '—')) ?></td>
                                    <td><?= e((string) ($click['browser'] ?? '—')) ?></td>
                                    <td><?= e((string) ($click['os'] ?? '—')) ?></td>
                                    <td>
                                        <?php $refHost = (string) ($click['referer_host'] ?? ''); ?>
                                        <?= $refHost !== '' ? e($refHost) : '<span class="text-muted">trực tiếp</span>' ?>
                                    </td>
                                    <td>
                                        <?php $country = (string) ($click['country'] ?? ''); ?>
                                        <?= $country !== '' ? Ua::countryFlag($country) . ' ' . e(Ua::countryName($country)) : '<span class="text-muted">—</span>' ?>
                                    </td>
                                    <td>
                                        <?php if ((int) $click['is_bot'] === 1): ?>
                                            <span class="badge badge--muted">robot</span>
                                        <?php elseif ((string) ($click['source'] ?? '') === 'qr'): ?>
                                            <span class="badge badge--warning">quét QR</span>
                                        <?php elseif ((int) $click['is_unique'] === 1): ?>
                                            <span class="badge badge--success">khách mới</span>
                                        <?php else: ?>
                                            <span class="badge badge--info">quay lại</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
