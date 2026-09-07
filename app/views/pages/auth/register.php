<?php
/**
 * Trang tạo tài khoản.
 *
 * @var bool $isFirstUser Người đầu tiên sẽ được cấp quyền quản trị
 */
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <?php if ($isFirstUser): ?>
            <div class="callout callout--info">
                <h2>👑 Bạn là người đầu tiên</h2>
                <p>
                    Hệ thống chưa có tài khoản nào, nên tài khoản bạn tạo ngay bây giờ sẽ được cấp
                    <strong>quyền quản trị</strong>: quản lý người dùng, xem toàn bộ liên kết và
                    thay đổi cài đặt chung.
                </p>
            </div>
        <?php endif; ?>

        <div class="card card--glass auth-card">
            <header class="auth-card__head">
                <span class="auth-card__emoji" aria-hidden="true">🎉</span>
                <h1 class="auth-card__title">Tạo tài khoản</h1>
                <p class="auth-card__sub">Miễn phí, chỉ cần tên đăng nhập và mật khẩu.</p>
            </header>

            <form method="post" action="<?= e(url('/dang-ky')) ?>" class="auth-form">
                <?= csrf_field() ?>

                <div class="field">
                    <label class="field__label" for="username">Tên đăng nhập <span class="field__req">*</span></label>
                    <input class="input<?= field_error('username') !== null ? ' has-error' : '' ?>" type="text"
                           id="username" name="username" value="<?= e(old('username')) ?>"
                           autocomplete="username" required autofocus
                           pattern="[A-Za-z0-9][A-Za-z0-9._\-]{2,31}" maxlength="32">
                    <?php if (field_error('username') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('username')) ?></p>
                    <?php else: ?>
                        <p class="field__hint">3–32 ký tự, chỉ dùng chữ không dấu, số và các dấu . _ -</p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="full_name">Họ và tên</label>
                    <input class="input" type="text" id="full_name" name="full_name"
                           value="<?= e(old('full_name')) ?>" autocomplete="name" maxlength="100"
                           placeholder="Nguyễn Văn A">
                </div>

                <div class="field">
                    <label class="field__label" for="unit">Đơn vị công tác</label>
                    <input class="input" type="text" id="unit" name="unit" value="<?= e(old('unit')) ?>"
                           maxlength="120" placeholder="Trường THPT …">
                </div>

                <div class="field">
                    <label class="field__label" for="email">Email</label>
                    <input class="input<?= field_error('email') !== null ? ' has-error' : '' ?>" type="email"
                           id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="email">
                    <?php if (field_error('email') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('email')) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Không bắt buộc — dùng để đăng nhập thay tên đăng nhập nếu bạn muốn.</p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="password">Mật khẩu <span class="field__req">*</span></label>
                    <div class="password-field">
                        <input class="input<?= field_error('password') !== null ? ' has-error' : '' ?>" type="password"
                               id="password" name="password" autocomplete="new-password" required
                               minlength="8" data-password-meter>
                        <button class="password-field__toggle" type="button" data-toggle-password
                                aria-label="Hiện / ẩn mật khẩu">👁️</button>
                    </div>
                    <div class="meter" data-meter hidden>
                        <span class="meter__bar"></span>
                        <span class="meter__label"></span>
                    </div>
                    <?php if (field_error('password') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('password')) ?></p>
                    <?php else: ?>
                        <p class="field__hint">Ít nhất 8 ký tự. Nên có cả chữ và số cho chắc.</p>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <label class="field__label" for="password_confirm">Nhập lại mật khẩu <span class="field__req">*</span></label>
                    <input class="input<?= field_error('password_confirm') !== null ? ' has-error' : '' ?>"
                           type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password" required minlength="8">
                    <?php if (field_error('password_confirm') !== null): ?>
                        <p class="field__error"><?= e((string) field_error('password_confirm')) ?></p>
                    <?php endif; ?>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit">Tạo tài khoản</button>
            </form>

            <p class="auth-card__foot">
                Đã có tài khoản? <a href="<?= e(url('/dang-nhap')) ?>">Đăng nhập</a>
            </p>
        </div>
    </div>
</section>
