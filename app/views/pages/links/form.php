<?php
/**
 * Biểu mẫu tạo / sửa liên kết (đầy đủ tuỳ chọn).
 *
 * @var array<string, mixed>|null $link  null = tạo mới
 * @var array<string, mixed>      $user
 * @var array<string, int>        $tags
 */
$isEdit = $link !== null;
$action = $isEdit ? url('/lien-ket/' . (int) $link['id'] . '/sua') : url('/lien-ket/tao');
$basePrefix = preg_replace('#^https?://#', '', base_url()) . '/';

/** Lấy giá trị: ưu tiên dữ liệu vừa nhập (khi có lỗi), rồi tới dữ liệu đã lưu. */
$value = static function (string $key, string $fallback = '') use ($isEdit, $link): string {
    if (has_old($key)) {
        return old($key);
    }
    if ($isEdit && array_key_exists($key, $link)) {
        return (string) ($link[$key] ?? '');
    }
    return $fallback;
};
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow"><?= $isEdit ? 'Sửa liên kết' : 'Tạo mới' ?></p>
                <h1 class="page-head__title">
                    <?= $isEdit ? '/' . e((string) $link['code']) : 'Tạo liên kết rút gọn' ?>
                </h1>
                <?php if ($isEdit): ?>
                    <p class="page-head__sub">
                        Đã có <strong><?= e(n((int) $link['click_count'])) ?></strong> lượt nhấp ·
                        tạo lúc <?= e(Clock::formatShort((string) $link['created_at'])) ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($isEdit): ?>
                <div class="page-head__actions">
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/thong-ke/' . (int) $link['id'])) ?>">Thống kê</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/ma-qr/' . rawurlencode((string) $link['code']))) ?>">Mã QR</a>
                </div>
            <?php endif; ?>
        </header>

        <form class="card card--glass form-card" method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>

            <fieldset class="fieldset">
                <legend class="fieldset__legend">Thông tin chính</legend>

                <div class="field">
                    <label class="field__label" for="target_url">
                        Địa chỉ đích <span class="field__req">*</span>
                    </label>
                    <input class="input input--lg<?= field_error('target_url') !== null ? ' has-error' : '' ?>"
                           type="text" id="target_url" name="target_url" required inputmode="url"
                           value="<?= e($value('target_url')) ?>" placeholder="https://…">
                    <?php if (field_error('target_url') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('target_url')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="code">
                        Tên tuỳ chọn
                        <?php if (!$isEdit): ?><span class="field__optional">(bỏ trống = tự sinh mã)</span><?php endif; ?>
                    </label>
                    <div class="alias-input">
                        <span class="alias-input__prefix"><?= e($basePrefix) ?></span>
                        <input class="input alias-input__field<?= field_error('code') !== null ? ' has-error' : '' ?>"
                               type="text" id="code" name="code" value="<?= e($value('code')) ?>"
                               autocomplete="off" spellcheck="false"
                               pattern="[A-Za-z0-9][A-Za-z0-9._\-]*"
                               maxlength="<?= (int) Config::get('code_max_length', 64) ?>"
                               placeholder="ten-cua-toi"
                               data-alias-check data-check-url="<?= e(url('/api/kiem-tra-ten')) ?>"
                               <?= $isEdit ? 'data-alias-current="' . e((string) $link['code']) . '"' : '' ?>>
                        <span class="alias-input__state" data-alias-state aria-live="polite"></span>
                    </div>
                    <?php if (field_error('code') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('code')) ?></p>
                    <?php else: ?>
                        <p class="field__hint">
                            Dùng chữ không dấu, số và dấu <code>- _ .</code>
                            <?php if ($isEdit): ?>
                                <strong>Lưu ý:</strong> đổi tên sẽ làm các bản in/mã QR cũ không còn dùng được.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="title">Tiêu đề</label>
                    <input class="input" type="text" id="title" name="title" maxlength="200"
                           value="<?= e($value('title')) ?>" placeholder="Tên gợi nhớ cho liên kết này">
                </div>

                <div class="field">
                    <label class="field__label" for="tags">Thẻ phân loại</label>
                    <input class="input" type="text" id="tags" name="tags" maxlength="200"
                           value="<?= e($value('tags')) ?>" list="danh-sach-the"
                           placeholder="tuyển sinh, 2026, nội bộ">
                    <?php if ($tags !== []): ?>
                        <datalist id="danh-sach-the">
                            <?php foreach (array_keys($tags) as $tag): ?>
                                <option value="<?= e((string) $tag) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <div class="tag-suggest">
                            <?php foreach (array_slice(array_keys($tags), 0, 10) as $tag): ?>
                                <button class="tag tag--button" type="button" data-tag-add="<?= e((string) $tag) ?>">
                                    #<?= e((string) $tag) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="field__hint">Cách nhau bằng dấu phẩy, tối đa 8 thẻ, mỗi thẻ ≤ 24 ký tự.</p>
                </div>

                <div class="field">
                    <label class="field__label" for="note">Ghi chú nội bộ</label>
                    <textarea class="input" id="note" name="note" rows="3" maxlength="500"
                              placeholder="Ghi chú chỉ bạn (và quản trị viên) đọc được."><?= e($value('note')) ?></textarea>
                </div>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset__legend">Điều kiện hoạt động</legend>
                <p class="fieldset__hint">Bỏ trống nghĩa là không giới hạn. Giờ nhập theo múi giờ Việt Nam (GMT+7).</p>

                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="starts_at">Bắt đầu hoạt động từ</label>
                        <input class="input<?= field_error('starts_at') !== null ? ' has-error' : '' ?>"
                               type="datetime-local" id="starts_at" name="starts_at"
                               value="<?= e(has_old('starts_at') ? old('starts_at') : ($isEdit ? Clock::forInput($link['starts_at'] ?? null) : '')) ?>">
                        <?php if (field_error('starts_at') !== null): ?>
                            <p class="field__error"><?= e((string) field_error('starts_at')) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="expires_at">Hết hạn lúc</label>
                        <input class="input<?= field_error('expires_at') !== null ? ' has-error' : '' ?>"
                               type="datetime-local" id="expires_at" name="expires_at"
                               value="<?= e(has_old('expires_at') ? old('expires_at') : ($isEdit ? Clock::forInput($link['expires_at'] ?? null) : '')) ?>">
                        <?php if (field_error('expires_at') !== null): ?>
                            <p class="field__error"><?= e((string) field_error('expires_at')) ?></p>
                        <?php endif; ?>
                        <div class="quick-dates">
                            <?php foreach (['+1 day' => '1 ngày', '+7 days' => '1 tuần', '+30 days' => '1 tháng', '+1 year' => '1 năm'] as $modifier => $label): ?>
                                <button class="chip" type="button"
                                        data-set-datetime="#expires_at"
                                        data-value="<?= e(Clock::nowDt()->modify($modifier)->format('Y-m-d\TH:i')) ?>">
                                    <?= e($label) ?>
                                </button>
                            <?php endforeach; ?>
                            <button class="chip chip--clear" type="button" data-clear-field="#expires_at">Xoá</button>
                        </div>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="max_clicks">Số lượt nhấp tối đa</label>
                        <input class="input" type="number" id="max_clicks" name="max_clicks" min="0" step="1"
                               value="<?= e(has_old('max_clicks') ? old('max_clicks') : ($isEdit ? (string) ($link['max_clicks'] ?? '') : '')) ?>"
                               placeholder="0 = không giới hạn">
                    </div>

                    <div class="field">
                        <label class="field__label" for="password">
                            Mật khẩu bảo vệ
                            <?php if ($isEdit && $link['password_hash'] !== null): ?>
                                <span class="field__optional">(đang bật)</span>
                            <?php endif; ?>
                        </label>
                        <input class="input" type="text" id="password" name="password" autocomplete="off"
                               placeholder="<?= $isEdit && $link['password_hash'] !== null ? 'Để trống = giữ mật khẩu cũ' : 'Để trống = không cần mật khẩu' ?>">
                        <?php if ($isEdit && $link['password_hash'] !== null): ?>
                            <label class="checkbox">
                                <input type="checkbox" name="remove_password" value="1">
                                <span>Bỏ mật khẩu bảo vệ</span>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <label class="switch">
                        <input type="checkbox" name="is_active" value="1"
                            <?= (int) $link['is_active'] === 1 ? 'checked' : '' ?>>
                        <span class="switch__track" aria-hidden="true"><span class="switch__dot"></span></span>
                        <span class="switch__label">Liên kết đang hoạt động</span>
                    </label>
                <?php endif; ?>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset__legend">Màu mã QR mặc định</legend>
                <p class="fieldset__hint">Màu này được dùng khi ai đó mở trang mã QR của liên kết.</p>
                <div class="field field--row">
                    <label class="color-field">
                        <span>Màu mã</span>
                        <input type="color" name="qr_dark"
                               value="<?= e($isEdit ? (string) $link['qr_dark'] : '#0b1220') ?>">
                    </label>
                    <label class="color-field">
                        <span>Màu nền</span>
                        <input type="color" name="qr_light"
                               value="<?= e($isEdit ? (string) $link['qr_light'] : '#ffffff') ?>">
                    </label>
                </div>
            </fieldset>

            <div class="form-card__actions">
                <button class="btn btn--primary btn--lg" type="submit">
                    <?= $isEdit ? 'Lưu thay đổi' : 'Tạo liên kết' ?>
                </button>
                <a class="btn btn--ghost" href="<?= e(url('/lien-ket')) ?>">Huỷ</a>

                <?php if ($isEdit): ?>
                    <div class="form-card__danger">
                        <form method="post" action="<?= e(url('/lien-ket/' . (int) $link['id'] . '/xoa-thong-ke')) ?>"
                              data-confirm="Xoá toàn bộ số liệu thống kê của /<?= e((string) $link['code']) ?>? Liên kết vẫn hoạt động bình thường.">
                            <?= csrf_field() ?>
                            <button class="btn btn--ghost btn--sm" type="submit">Xoá số liệu thống kê</button>
                        </form>
                        <form method="post" action="<?= e(url('/lien-ket/' . (int) $link['id'] . '/xoa')) ?>"
                              data-confirm="Xoá hẳn liên kết /<?= e((string) $link['code']) ?>? Không thể lấy lại.">
                            <?= csrf_field() ?>
                            <button class="btn btn--danger btn--sm" type="submit">Xoá liên kết</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</section>
