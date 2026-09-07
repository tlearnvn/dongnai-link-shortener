<?php
/** Đặt lại mật khẩu bằng mã dự phòng. */
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <div class="card card--glass auth-card">
            <header class="auth-card__head">
                <span class="auth-card__emoji" aria-hidden="true">🔑</span>
                <h1 class="auth-card__title">Đặt lại mật khẩu</h1>
                <p class="auth-card__sub">
                    Hệ thống không gửi email, nên bạn dùng <strong>mã dự phòng</strong> đã nhận
                    lúc tạo tài khoản để tự đặt lại mật khẩu.
                </p>
            </header>

            <form method="post" action="<?= e(url('/quen-mat-khau')) ?>" class="auth-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label class="field__label" for="login">Tên đăng nhập hoặc email</label>
                    <input class="input" type="text" id="login" name="login" value="<?= e(old('login')) ?>" required autofocus>
                </div>

                <div class="field">
                    <label class="field__label" for="recovery_code">Mã dự phòng</label>
                    <input class="input input--mono<?= field_error('recovery_code') !== null ? ' has-error' : '' ?>"
                           type="text" id="recovery_code" name="recovery_code" value="<?= e(old('recovery_code')) ?>"
                           placeholder="ABCD-EFGH-JKMN" required autocomplete="off" spellcheck="false">
                    <?php if (field_error('recovery_code') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('recovery_code')) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Gồm 12 ký tự, chia ba nhóm. Gõ có hay không có dấu gạch đều được.</p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Mật khẩu mới</label>
                    <div class="password-field">
                        <input class="input" type="password" id="password" name="password"
                               autocomplete="new-password" required minlength="8">
                        <button class="password-field__toggle" type="button" data-toggle-password
                                aria-label="Hiện / ẩn mật khẩu">👁️</button>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="password_confirm">Nhập lại mật khẩu mới</label>
                    <input class="input<?= field_error('password_confirm') !== null ? ' has-error' : '' ?>"
                           type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password" required minlength="8">
                    <?php if (field_error('password_confirm') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('password_confirm')) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit">Đặt lại mật khẩu</button>
            </form>

            <div class="callout callout--muted">
                <p>
                    <strong>Mất luôn mã dự phòng?</strong> Hãy liên hệ quản trị viên của hệ thống —
                    quản trị viên có thể đặt lại mật khẩu giúp bạn trong trang Quản lý người dùng.
                </p>
            </div>

            <p class="auth-card__foot">
                <a href="<?= e(url('/dang-nhap')) ?>">← Về trang đăng nhập</a>
            </p>
        </div>
    </div>
</section>
