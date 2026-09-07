<?php
/**
 * Trang tổng quan quản trị.
 *
 * @var array<string, mixed> $summary
 * @var array<string, mixed> $overview
 * @var array<int, array>    $series
 * @var array<int, array>    $topLinks
 * @var array<int, array>    $recentAudit
 * @var array<int, array>    $topUsers
 */
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Khu vực quản trị</p>
                <h1 class="page-head__title">Tổng quan hệ thống</h1>
                <p class="page-head__sub">
                    Cài đặt từ <?= e(Clock::formatShort((string) ($summary['installed_at'] ?? null))) ?> ·
                    tệp dữ liệu <?= e(bytes_human((int) $summary['db_size'])) ?>
                </p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/nguoi-dung')) ?>">Người dùng</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/lien-ket')) ?>">Liên kết</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/nhat-ky')) ?>">Nhật ký</a>
                <a class="btn btn--primary btn--sm" href="<?= e(url('/quan-tri/cai-dat')) ?>">Cài đặt</a>
            </div>
        </header>

        <div class="tiles">
            <?php
            $tiles = [
                ['👥', 'Người dùng', n((int) $summary['users']), n((int) $summary['admins']) . ' quản trị viên', 'primary'],
                ['🔗', 'Liên kết', n((int) $summary['links']), n((int) $summary['guest_links']) . ' do khách tạo', 'accent'],
                ['👆', 'Lượt nhấp', n((int) $summary['clicks']), n((int) $overview['clicks_today']) . ' lượt hôm nay', 'success'],
                ['💾', 'Tệp dữ liệu', bytes_human((int) $summary['db_size']), 'một tệp SQLite duy nhất', 'info'],
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

        <div class="card card--chart">
            <h2 class="card__title">Lượt nhấp toàn hệ thống — 30 ngày qua</h2>
            <div class="chart-wrap"><?= Chart::area($series, 'Lượt nhấp toàn hệ thống') ?></div>
        </div>

        <div class="grid grid--2">
            <div class="card">
                <header class="card__head">
                    <h2 class="card__title">Liên kết nhiều lượt nhấp nhất</h2>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/lien-ket')) ?>">Xem tất cả</a>
                </header>
                <div class="table-scroll">
                    <table class="table table--compact">
                        <thead>
                            <tr><th>Liên kết</th><th>Người tạo</th><th class="table__num">Lượt nhấp</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topLinks as $row): ?>
                                <tr>
                                    <td>
                                        <a class="mono-link" href="<?= e(short_url((string) $row['code'])) ?>"
                                           target="_blank" rel="noopener">/<?= e((string) $row['code']) ?></a>
                                    </td>
                                    <td><?= e((string) ($row['owner_username'] ?? 'khách')) ?></td>
                                    <td class="table__num"><strong><?= e(n((int) $row['click_count'])) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($topLinks === []): ?>
                                <tr><td colspan="3" class="table__empty">Chưa có liên kết nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <header class="card__head">
                    <h2 class="card__title">Người dùng tích cực</h2>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/nguoi-dung')) ?>">Quản lý</a>
                </header>
                <div class="table-scroll">
                    <table class="table table--compact">
                        <thead>
                            <tr><th>Người dùng</th><th class="table__num">Liên kết</th><th class="table__num">Lượt nhấp</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topUsers as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e((string) $row['username']) ?></strong>
                                        <?php if (!empty($row['full_name'])): ?>
                                            <small class="table__sub"><?= e((string) $row['full_name']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table__num"><?= e(n((int) $row['link_count'])) ?></td>
                                    <td class="table__num"><?= e(n((int) $row['click_count'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($topUsers === []): ?>
                                <tr><td colspan="3" class="table__empty">Chưa có người dùng nào.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <header class="card__head">
                <h2 class="card__title">Hoạt động gần đây</h2>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/nhat-ky')) ?>">Toàn bộ nhật ký</a>
            </header>
            <ul class="timeline">
                <?php foreach ($recentAudit as $row): ?>
                    <li class="timeline__item">
                        <span class="timeline__time"><?= e(Clock::human((string) $row['created_at'])) ?></span>
                        <span class="timeline__actor"><?= e((string) ($row['actor'] ?? 'hệ thống')) ?></span>
                        <span class="timeline__action"><?= e((string) $row['action']) ?></span>
                        <?php if (!empty($row['detail'])): ?>
                            <span class="timeline__detail"><?= e(truncate_str((string) $row['detail'], 70)) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($recentAudit === []): ?>
                    <li class="timeline__item timeline__item--empty">Chưa có hoạt động nào được ghi lại.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>
