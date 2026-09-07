<?php
/**
 * Trang tài khoản.
 *
 * @var array<string, mixed> $user
 * @var string|null          $recoveryCode
 * @var string|null          $newToken
 * @var array<string, mixed> $overview
 */
$theme = (string) $user['theme'];
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Tài khoản</p>
                <h1 class="page-head__title"><?= e(Auth::displayName()) ?></h1>
                <p class="page-head__sub">
                    @<?= e((string) $user['username']) ?>
                    <?php if ($user['role'] === 'admin'): ?>
                        · <span class="badge badge--admin">quản trị viên</span>
                    <?php endif; ?>
                    · tham gia <?= e(Clock::formatDate((string) $user['created_at'])) ?>
                </p>
            </div>
        </header>

        <?php if ($recoveryCode !== null): ?>
            <div class="callout callout--warning" data-celebrate>
                <h2>🔑 Mã dự phòng của bạn</h2>
                <p>
                    Hệ thống không gửi email, nên đây là cách duy nhất để bạn tự lấy lại mật khẩu khi quên.
                    <strong>Mã này chỉ hiện một lần duy nhất</strong> — hãy chụp ảnh hoặc ghi vào sổ ngay.
                </p>
                <p class="code-box code-box--big">
                    <code><?= e($recoveryCode) ?></code>
                    <button class="btn btn--primary btn--sm" type="button" data-copy="<?= e($recoveryCode) ?>">Sao chép</button>
                </p>
                <p class="callout__foot">Mất mã này thì vẫn còn cách: nhờ quản trị viên đặt lại mật khẩu giúp.</p>
            </div>
        <?php endif; ?>

        <?php if ($newToken !== null): ?>
            <div class="callout callout--info">
                <h2>Khoá API mới</h2>
                <p>Dùng khoá này trong header <code>Authorization: Bearer …</code>. Khoá cũ đã bị vô hiệu.</p>
                <p class="code-box">
                    <code><?= e($newToken) ?></code>
                    <button class="btn btn--ghost btn--sm" type="button" data-copy="<?= e($newToken) ?>">Sao chép</button>
                </p>
            </div>
        <?php endif; ?>

        <div class="tiles tiles--compact">
            <article class="tile tile--primary">
                <span class="tile__icon" aria-hidden="true">🔗</span>
                <p class="tile__label">Liên kết của bạn</p>
                <p class="tile__value"><?= e(n((int) $overview['total_links'])) ?></p>
                <p class="tile__note"><?= e(n((int) $overview['active_links'])) ?> đang chạy</p>
            </article>
            <article class="tile tile--accent">
                <span class="tile__icon" aria-hidden="true">👆</span>
                <p class="tile__label">Tổng lượt nhấp</p>
                <p class="tile__value"><?= e(n((int) $overview['total_clicks'])) ?></p>
                <p class="tile__note"><?= e(n((int) $overview['unique_clicks'])) ?> khách riêng</p>
            </article>
            <article class="tile tile--info">
                <span class="tile__icon" aria-hidden="true">🔐</span>
                <p class="tile__label">Lần đăng nhập</p>
                <p class="tile__value"><?= e(n((int) $user['login_count'])) ?></p>
                <p class="tile__note">
                    gần nhất <?= e($user['last_login_at'] !== null ? Clock::human((string) $user['last_login_at']) : '—') ?>
                </p>
            </article>
        </div>

        <div class="card form-card">
            <h2 class="card__title">Thông tin cá nhân</h2>
            <form method="post" action="<?= e(url('/tai-khoan/thong-tin')) ?>">
                <?= csrf_field() ?>
                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="full_name">Họ và tên</label>
                        <input class="input" type="text" id="full_name" name="full_name" maxlength="100"
                               value="<?= e((string) ($user['full_name'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label class="field__label" for="email">Email</label>
                        <input class="input" type="email" id="email" name="email"
                               value="<?= e((string) ($user['email'] ?? '')) ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="field__label" for="unit">Đơn vị công tác</label>
                    <input class="input" type="text" id="unit" name="unit" maxlength="120"
                           value="<?= e((string) ($user['unit'] ?? '')) ?>">
                </div>
                <button class="btn btn--primary" type="submit">Lưu thông tin</button>
            </form>
        </div>

        <div class="card form-card">
            <h2 class="card__title">Đổi mật khẩu</h2>
            <form method="post" action="<?= e(url('/tai-khoan/mat-khau')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="current_password">Mật khẩu hiện tại</label>
                    <div class="password-field">
                        <input class="input" type="password" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                        <button class="password-field__toggle" type="button" data-toggle-password
                                aria-label="Hiện / ẩn mật khẩu">👁️</button>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="new_password">Mật khẩu mới</label>
                        <input class="input" type="password" id="new_password" name="new_password"
                               autocomplete="new-password" required minlength="8" data-password-meter>
                        <div class="meter" data-meter hidden>
                            <span class="meter__bar"></span>
                            <span class="meter__label"></span>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field__label" for="new_password_confirm">Nhập lại mật khẩu mới</label>
                        <input class="input" type="password" id="new_password_confirm" name="new_password_confirm"
                               autocomplete="new-password" required minlength="8">
                    </div>
                </div>
                <p class="field__hint">Sau khi đổi, các thiết bị đang “ghi nhớ đăng nhập” sẽ phải đăng nhập lại.</p>
                <button class="btn btn--primary" type="submit">Đổi mật khẩu</button>
            </form>
        </div>

        <div class="card form-card">
            <h2 class="card__title">Giao diện</h2>
            <form method="post" action="<?= e(url('/tai-khoan/giao-dien')) ?>">
                <?= csrf_field() ?>
                <div class="radio-row">
                    <?php foreach (['auto' => '🌗 Theo hệ thống', 'light' => '☀️ Luôn sáng', 'dark' => '🌙 Luôn tối'] as $value => $label): ?>
                        <label class="radio-card">
                            <input type="radio" name="theme" value="<?= e($value) ?>" <?= $theme === $value ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field__hint">
                    Lựa chọn này lưu vào tài khoản nên áp dụng trên mọi thiết bị bạn đăng nhập.
                    (Nút 🌗 ở đầu trang chỉ đổi tạm cho máy đang dùng.)
                </p>
                <button class="btn btn--primary" type="submit">Lưu giao diện</button>
            </form>
        </div>

        <div class="card form-card">
            <h2 class="card__title">Khoá API</h2>
            <p class="card__sub">
                Dùng để tạo liên kết từ phần mềm khác. Xem
                <a href="<?= e(url('/api')) ?>">tài liệu API</a> để biết cách gọi.
            </p>
            <p class="code-box">
                <code><?= e(substr((string) ($user['api_token'] ?? ''), 0, 12)) ?>••••••••••••••••••••</code>
                <span class="code-box__note">(chỉ hiện đầy đủ khi bạn cấp lại khoá mới)</span>
            </p>
            <form method="post" action="<?= e(url('/tai-khoan/api-token')) ?>"
                  data-confirm="Cấp khoá API mới? Phần mềm đang dùng khoá cũ sẽ ngừng hoạt động.">
                <?= csrf_field() ?>
                <button class="btn btn--ghost" type="submit">Cấp lại khoá API</button>
            </form>
        </div>
    </div>
</section>
