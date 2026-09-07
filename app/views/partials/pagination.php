<?php
/**
 * Thanh phân trang.
 *
 * @var array{page: int, pages: int, total: int, per_page: int} $result
 */
if (($result['pages'] ?? 1) <= 1) {
    return;
}

$page = (int) $result['page'];
$pages = (int) $result['pages'];

// Hiện tối đa 7 số trang, có dấu … khi bỏ qua đoạn giữa.
$window = 2;
$numbers = [];
for ($i = 1; $i <= $pages; $i++) {
    if ($i === 1 || $i === $pages || abs($i - $page) <= $window) {
        $numbers[] = $i;
    } elseif (end($numbers) !== '…') {
        $numbers[] = '…';
    }
}
?>
<nav class="pagination" aria-label="Phân trang">
    <a class="pagination__nav<?= $page <= 1 ? ' is-disabled' : '' ?>"
       href="<?= e(query_url(['page' => max(1, $page - 1)])) ?>"
       <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>‹ Trước</a>

    <ul class="pagination__list">
        <?php foreach ($numbers as $number): ?>
            <?php if ($number === '…'): ?>
                <li class="pagination__gap" aria-hidden="true">…</li>
            <?php else: ?>
                <li>
                    <a class="pagination__page<?= $number === $page ? ' is-current' : '' ?>"
                       href="<?= e(query_url(['page' => $number])) ?>"
                       <?= $number === $page ? 'aria-current="page"' : '' ?>><?= (int) $number ?></a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>

    <a class="pagination__nav<?= $page >= $pages ? ' is-disabled' : '' ?>"
       href="<?= e(query_url(['page' => min($pages, $page + 1)])) ?>"
       <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Sau ›</a>

    <p class="pagination__info">
        Trang <?= e(n($page)) ?>/<?= e(n($pages)) ?> · <?= e(n((int) $result['total'])) ?> liên kết
    </p>
</nav>
