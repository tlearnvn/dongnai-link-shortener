<?php
/**
 * Danh sách liên kết của người dùng.
 *
 * @var array<string, mixed> $user
 * @var array{rows: array, total: int, page: int, pages: int, per_page: int} $result
 * @var array<string, mixed> $filters
 * @var array<string, int>   $tags
 */
$rows = $result['rows'];
$sparks = Stats::sparkForLinks(array_map(static fn(array $l): int => (int) $l['id'], $rows), 14);
$exportQuery = http_build_query(array_filter([
    'q' => $filters['q'] ?? '',
    'status' => $filters['status'] ?? '',
    'tag' => $filters['tag'] ?? '',
    'sort' => $filters['sort'] ?? '',
    'starred' => !empty($filters['starred']) ? '1' : '',
]));
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Quản lý</p>
                <h1 class="page-head__title">Liên kết của tôi</h1>
                <p class="page-head__sub">
                    Tổng cộng <strong><?= e(n((int) $result['total'])) ?></strong> liên kết khớp bộ lọc hiện tại.
                    <?php if ((int) $user['link_quota'] > 0): ?>
                        Hạn mức của bạn: <?= e(n(LinkService::countForUser((int) $user['id']))) ?>/<?= e(n((int) $user['link_quota'])) ?>.
                    <?php endif; ?>
                </p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--primary btn--sm" href="<?= e(url('/lien-ket/tao')) ?>">+ Liên kết mới</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/tao-hang-loat')) ?>">Tạo hàng loạt</a>
                <a class="btn btn--ghost btn--sm"
                   href="<?= e(url('/xuat-csv') . ($exportQuery !== '' ? '?' . $exportQuery : '')) ?>">
                    Xuất CSV
                </a>
            </div>
        </header>

        <?= render('partials/filters', [
            'filters' => $filters,
            'tags' => $tags,
            'action' => '/lien-ket',
        ]) ?>

        <?php if ($rows === []): ?>
            <div class="card empty-state">
                <span class="empty-state__emoji" aria-hidden="true">🔎</span>
                <h2>Không có liên kết nào khớp</h2>
                <p>
                    <?php if (($filters['q'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['tag'] ?? '') !== ''): ?>
                        Thử bỏ bớt điều kiện lọc, hoặc tìm bằng từ khoá khác.
                    <?php else: ?>
                        Bạn chưa tạo liên kết nào. Hãy bắt đầu với liên kết đầu tiên nhé!
                    <?php endif; ?>
                </p>
                <div class="empty-state__actions">
                    <a class="btn btn--primary" href="<?= e(url('/lien-ket/tao')) ?>">Tạo liên kết mới</a>
                    <a class="btn btn--ghost" href="<?= e(url('/lien-ket')) ?>">Bỏ lọc</a>
                </div>
            </div>
        <?php else: ?>
            <div class="link-grid">
                <?php foreach ($rows as $link): ?>
                    <?= render('partials/link-card', [
                        'link' => $link,
                        'spark' => $sparks[(int) $link['id']] ?? null,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <?= render('partials/pagination', ['result' => $result]) ?>
        <?php endif; ?>
    </div>
</section>
