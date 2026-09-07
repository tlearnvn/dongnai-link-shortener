<?php
/** Thông báo sau mỗi thao tác. */
$flashes = take_flashes();
if ($flashes === []) {
    return;
}

$icons = [
    'success' => '✅',
    'error' => '⚠️',
    'info' => 'ℹ️',
    'warning' => '⚠️',
];
?>
<div class="flash-stack">
    <?php foreach ($flashes as $item):
        $type = in_array($item['type'], ['success', 'error', 'info', 'warning'], true) ? $item['type'] : 'info'; ?>
        <div class="alert alert--<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"
             data-flash="<?= e($type) ?>">
            <span class="alert__icon" aria-hidden="true"><?= $icons[$type] ?></span>
            <p class="alert__text"><?= e($item['message']) ?></p>
            <button class="alert__close" type="button" aria-label="Đóng thông báo" data-dismiss>&times;</button>
        </div>
    <?php endforeach; ?>
</div>
