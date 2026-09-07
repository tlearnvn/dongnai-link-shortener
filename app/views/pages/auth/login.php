<?php
/** Trang đăng nhập. */
$newRecoveryCode = $_SESSION['_new_recovery_code'] ?? null;
unset($_SESSION['_new_recovery_code']);

$adminReset = $_SESSION['_admin_reset'] ?? null;
unset($_SESSION['_admin_reset']);
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <?php if ($newRecoveryCode !== null): ?>
            <div class="callout callout--warning">
                <h2>Mã dự phòng mới của bạn</h2>
                <p>Mã cũ đã dùng xong. Hãy lưu mã mới này lại — nó chỉ hiện đúng một lần:</p>
                <p class="code-box"><code data-copy-text><?= e((string) $newRecoveryCode) ?></code>
                    <button class="btn btn--ghost btn--xs" type="button" data-copy="<?= e((string) $newRecoveryCode) ?>">Sao chép</button>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($adminReset !== null): ?>
            <div class="callout callout--warning">
                <h2>Mật khẩu tạm cho <?= e((string) $adminReset['username']) ?></h2>
                <p>Hãy chuyển mật khẩu này cho người dùng và nhắc họ đổi lại sau khi đăng nhập:</p>
                <p class="code-box"><code><?= e((string) $adminReset['password']) ?></code>
                    <button class="btn btn--ghost btn--xs" type="button" data-copy="<?= e((string) $adminReset['password']) ?>">Sao chép</button>
                </p>
            </div>
        <?php endif; ?>

        <div class="card card--glass auth-card">
            <header class="auth-card__head">
                <span class="auth-card__emoji" aria-hidden="true">👋</span>
                <h1 class="auth-card__title">Đăng nhập</h1>
                <p class="auth-card__sub">Đăng nhập để quản lý liên kết và xem thống kê đầy đủ.</p>
            </header>

            <form method="post" action="<?= e(url('/dang-nhap')) ?>" class="auth-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label class="field__label" for="login">Tên đăng nhập hoặc email</label>
                    <input class="input<?= field_error('login') !== null ? ' has-error' : '' ?>" type="text"
                           id="login" name="login" value="<?= e(old('login')) ?>"
                           autocomplete="username" required autofocus>
                    <?php if (field_error('login') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('login')) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Mật khẩu</label>
                    <div class="password-field">
                        <input class="input" type="password" id="password" name="password"
                               autocomplete="current-password" required>
                        <button class="password-field__toggle" type="button" data-toggle-password
                                aria-label="Hiện / ẩn mật khẩu">👁️</button>
                    </div>
                </div>

                <div class="auth-form__row">
                    <label class="checkbox">
                        <input type="checkbox" name="remember" value="1" checked>
                        <span>Ghi nhớ tôi trên máy này (30 ngày)</span>
                    </label>
                    <a class="auth-form__forgot" href="<?= e(url('/quen-mat-khau')) ?>">Quên mật khẩu?</a>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit">Đăng nhập</button>
            </form>

            <?php if (Settings::bool('allow_registration', true)): ?>
                <p class="auth-card__foot">
                    Chưa có tài khoản? <a href="<?= e(url('/dang-ky')) ?>">Tạo tài khoản mới</a>
                </p>
            <?php else: ?>
                <p class="auth-card__foot">
                    Hệ thống đang tạm ngưng nhận tài khoản mới. Vui lòng liên hệ quản trị viên để được cấp.
                </p>
            <?php endif; ?>
        </div>
    </div>
</section>
