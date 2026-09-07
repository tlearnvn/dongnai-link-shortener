<?php
/**
 * Thanh tìm kiếm và lọc danh sách liên kết.
 *
 * @var array<string, mixed> $filters
 * @var array<string, int>   $tags
 * @var string               $action    Đường dẫn nhận biểu mẫu
 * @var bool                 $showScope Cho phép chọn phạm vi (quản trị viên)
 */
$action = $action ?? '/lien-ket';
$showScope = $showScope ?? false;

$statuses = [
    '' => 'Mọi trạng thái',
    'active' => 'Đang chạy',
    'paused' => 'Tạm dừng',
    'expired' => 'Đã hết hạn',
    'scheduled' => 'Chờ tới hạn',
    'exhausted' => 'Đã đủ lượt',
    'protected' => 'Có mật khẩu',
];
$sorts = [
    'newest' => 'Mới nhất trước',
    'oldest' => 'Cũ nhất trước',
    'clicks' => 'Nhiều lượt nhấp nhất',
    'least' => 'Ít lượt nhấp nhất',
    'recent_click' => 'Vừa được nhấp',
    'code' => 'Theo tên A → Z',
];
?>
<form class="filters" method="get" action="<?= e(url($action)) ?>">
    <div class="filters__search">
        <svg class="filters__icon" viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="11" cy="11" r="6.4"/><path d="M20 20l-4.2-4.2"/>
        </svg>
        <input type="search" name="q" value="<?= e((string) ($filters['q'] ?? '')) ?>"
               placeholder="Tìm theo tên tuỳ chọn, địa chỉ, tiêu đề, ghi chú…"
               aria-label="Từ khoá tìm kiếm">
    </div>

    <label class="filters__field">
        <span>Trạng thái</span>
        <select name="status">
            <?php foreach ($statuses as $value => $label): ?>
                <option value="<?= e((string) $value) ?>"
                    <?= (string) ($filters['status'] ?? '') === (string) $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="filters__field">
        <span>Sắp xếp</span>
        <select name="sort">
            <?php foreach ($sorts as $value => $label): ?>
                <option value="<?= e($value) ?>"
                    <?= (string) ($filters['sort'] ?? 'newest') === $value ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <?php if ($tags !== []): ?>
        <label class="filters__field">
            <span>Thẻ</span>
            <select name="tag">
                <option value="">Mọi thẻ</option>
                <?php foreach ($tags as $tag => $count): ?>
                    <option value="<?= e((string) $tag) ?>"
                        <?= (string) ($filters['tag'] ?? '') === (string) $tag ? 'selected' : '' ?>>
                        <?= e((string) $tag) ?> (<?= (int) $count ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>

    <label class="filters__field filters__field--narrow">
        <span>Mỗi trang</span>
        <select name="per_page">
            <?php foreach ((array) Config::get('per_page_options', [12]) as $option): ?>
                <option value="<?= (int) $option ?>"
                    <?= (int) ($filters['per_page'] ?? 12) === (int) $option ? 'selected' : '' ?>>
                    <?= (int) $option ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <?php if ($showScope): ?>
        <label class="checkbox checkbox--inline">
            <input type="checkbox" name="pham-vi" value="tat-ca"
                <?= input('pham-vi') === 'tat-ca' ? 'checked' : '' ?>>
            <span>Cả hệ thống</span>
        </label>
    <?php endif; ?>

    <label class="checkbox checkbox--inline">
        <input type="checkbox" name="starred" value="1" <?= !empty($filters['starred']) ? 'checked' : '' ?>>
        <span>Chỉ liên kết đã ghim ⭐</span>
    </label>

    <div class="filters__buttons">
        <button class="btn btn--primary btn--sm" type="submit">Lọc</button>
        <a class="btn btn--ghost btn--sm" href="<?= e(url($action)) ?>">Bỏ lọc</a>
    </div>
</form>
