<?php
/**
 * Toàn bộ liên kết trong hệ thống.
 *
 * @var array{rows: array, total: int, page: int, pages: int, per_page: int} $result
 * @var array<string, mixed> $filters
 * @var array<string, int>   $tags
 */
$rows = $result['rows'];
$sparks = Stats::sparkForLinks(array_map(static fn(array $l): int => (int) $l['id'], $rows), 14);
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Quản trị</p>
                <h1 class="page-head__title">Toàn bộ liên kết</h1>
                <p class="page-head__sub"><?= e(n((int) $result['total'])) ?> liên kết khớp bộ lọc</p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri')) ?>">← Tổng quan</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/xuat-csv?pham-vi=tat-ca')) ?>">Xuất CSV toàn bộ</a>
            </div>
        </header>

        <?= render('partials/filters', [
            'filters' => $filters,
            'tags' => $tags,
            'action' => '/quan-tri/lien-ket',
        ]) ?>

        <?php if ($rows === []): ?>
            <div class="card empty-state">
                <span class="empty-state__emoji" aria-hidden="true">🔍</span>
                <h2>Không có liên kết nào khớp bộ lọc</h2>
                <a class="btn btn--ghost" href="<?= e(url('/quan-tri/lien-ket')) ?>">Bỏ lọc</a>
            </div>
        <?php else: ?>
            <div class="link-grid">
                <?php foreach ($rows as $link): ?>
                    <?= render('partials/link-card', [
                        'link' => $link,
                        'spark' => $sparks[(int) $link['id']] ?? null,
                        'showOwner' => true,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <?= render('partials/pagination', ['result' => $result]) ?>
        <?php endif; ?>
    </div>
</section>
