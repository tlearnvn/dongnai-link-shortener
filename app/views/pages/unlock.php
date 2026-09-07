<?php
/**
 * Nhập mật khẩu để mở liên kết được bảo vệ.
 *
 * @var array<string, mixed> $link
 * @var string|null          $error
 */
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <div class="card card--glass state-page state-page--form">
            <span class="state-page__emoji" aria-hidden="true">🔐</span>
            <h1 class="state-page__title">Liên kết được bảo vệ</h1>
            <p class="state-page__message">
                Liên kết <code>/<?= e((string) $link['code']) ?></code> yêu cầu mật khẩu.
                Vui lòng nhập mật khẩu mà người gửi đã cung cấp.
            </p>

            <form class="unlock-form" method="post"
                  action="<?= e(url('/' . rawurlencode((string) $link['code']))) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field__label" for="link_password">Mật khẩu</label>
                    <input class="input input--lg<?= $error !== null ? ' has-error' : '' ?>" type="password"
                           id="link_password" name="link_password" autocomplete="off" required autofocus>
                    <?php if ($error !== null): ?>
                        <p class="field__error"><?= e($error) ?></p>
                    <?php endif; ?>
                </div>
                <button class="btn btn--primary btn--lg" type="submit">Mở liên kết</button>
            </form>

            <p class="state-page__hint">
                Không biết mật khẩu? Hãy hỏi lại người đã gửi liên kết này cho bạn.
            </p>
        </div>
    </div>
</section>
