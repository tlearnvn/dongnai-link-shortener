<?php
/**
 * Nhật ký hoạt động.
 *
 * @var array<int, array> $rows
 * @var int               $page
 * @var int               $pages
 * @var int               $total
 */
$labels = [
    'register' => ['🆕', 'Tạo tài khoản'],
    'login' => ['🔓', 'Đăng nhập'],
    'logout' => ['🚪', 'Đăng xuất'],
    'password_change' => ['🔑', 'Đổi mật khẩu'],
    'password_reset' => ['🔑', 'Đặt lại mật khẩu'],
    'api_token_reset' => ['🧩', 'Cấp lại khoá API'],
    'link_create' => ['➕', 'Tạo liên kết'],
    'link_update' => ['✏️', 'Sửa liên kết'],
    'link_delete' => ['🗑️', 'Xoá liên kết'],
    'link_toggle' => ['🔄', 'Bật/tắt liên kết'],
    'link_reset_stats' => ['📉', 'Xoá số liệu liên kết'],
    'admin_settings' => ['⚙️', 'Đổi cài đặt'],
    'admin_maintenance' => ['🧰', 'Bảo trì'],
];
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Quản trị</p>
                <h1 class="page-head__title">Nhật ký hoạt động</h1>
                <p class="page-head__sub"><?= e(n($total)) ?> bản ghi · giờ Việt Nam (GMT+7)</p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri')) ?>">← Tổng quan</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri/cai-dat')) ?>">Cài đặt</a>
            </div>
        </header>

        <div class="card">
            <?php if ($rows === []): ?>
                <p class="chart-empty">Chưa có hoạt động nào được ghi lại.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="table table--compact">
                        <thead>
                            <tr>
                                <th>Thời điểm</th>
                                <th>Người thực hiện</th>
                                <th>Hoạt động</th>
                                <th>Chi tiết</th>
                                <th>Địa chỉ IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row):
                                $action = (string) $row['action'];
                                [$icon, $label] = $labels[$action] ?? ['•', $action];
                            ?>
                                <tr>
                                    <td>
                                        <?= e(Clock::formatShort((string) $row['created_at'])) ?>
                                        <small class="table__sub"><?= e(Clock::human((string) $row['created_at'])) ?></small>
                                    </td>
                                    <td><strong><?= e((string) ($row['actor'] ?? 'hệ thống')) ?></strong></td>
                                    <td><span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?></td>
                                    <td><?= e(truncate_str((string) ($row['detail'] ?? '—'), 60)) ?></td>
                                    <td><code class="ip"><?= e((string) ($row['ip'] ?? '—')) ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?= render('partials/pagination', [
            'result' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'per_page' => 40],
        ]) ?>
    </div>
</section>
