<?php
/**
 * Trang chủ: giới thiệu ngắn + biểu mẫu rút gọn.
 *
 * @var array<string, mixed>|null $user
 * @var array<string, mixed>      $summary
 * @var array<int, array>         $recentLinks
 */
$guestAllowed = Settings::bool('allow_guest_shorten', true);
$basePrefix = preg_replace('#^https?://#', '', base_url()) . '/';
?>

<section class="hero">
    <div class="wrap hero__inner">
        <div class="hero__text">
            <p class="hero__eyebrow">
                <span class="hero__dot" aria-hidden="true"></span>
                <?= e((string) Config::get('site_owner')) ?>
            </p>
            <h1 class="hero__title">
                Rút gọn liên kết<br>
                <span class="hero__title-accent">nhanh, gọn, có thống kê</span>
            </h1>
            <p class="hero__lead">
                Biến một địa chỉ dài dòng thành liên kết ngắn gọn, đọc qua điện thoại cũng nghe rõ,
                kèm <strong>mã QR</strong> để in lên văn bản và <strong>thống kê đầy đủ</strong> số lượt truy cập.
                Bạn còn có thể tự đặt <strong>tên tuỳ chọn</strong> cho liên kết của mình.
            </p>

            <dl class="hero__counters">
                <div class="hero__counter">
                    <dt>Liên kết đã tạo</dt>
                    <dd data-count-to="<?= (int) $summary['links'] ?>">0</dd>
                </div>
                <div class="hero__counter">
                    <dt>Lượt truy cập</dt>
                    <dd data-count-to="<?= (int) $summary['clicks'] ?>">0</dd>
                </div>
                <div class="hero__counter">
                    <dt>Người dùng</dt>
                    <dd data-count-to="<?= (int) $summary['users'] ?>">0</dd>
                </div>
            </dl>
        </div>

        <div class="hero__art" aria-hidden="true">
            <div class="float-card float-card--1">
                <span class="float-card__label">Trước</span>
                <code>sgddt.dongnai.gov.vn/pages/chi-tiet-tin.aspx?id=48219&amp;loai=thong-bao</code>
            </div>
            <div class="float-card float-card--arrow">
                <svg viewBox="0 0 40 40"><path d="M8 20h20m0 0-6-6m6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <div class="float-card float-card--2">
                <span class="float-card__label">Sau</span>
                <code><?= e($basePrefix) ?>thong-bao-2026</code>
                <div class="float-card__qr"><?= QrCode::encode(short_url('thong-bao-2026'), QrCode::ECC_LOW)->toSvg(3, 2, 'currentColor', 'none') ?></div>
            </div>
        </div>
    </div>
</section>

<section class="shortener" id="rut-gon">
    <div class="wrap">
        <div class="card card--glass shortener__card">
            <?php if (!$guestAllowed && $user === null): ?>
                <div class="shortener__locked">
                    <span class="shortener__locked-icon" aria-hidden="true">🔐</span>
                    <div>
                        <h2>Hệ thống đang yêu cầu đăng nhập</h2>
                        <p>Quản trị viên đã tắt chế độ rút gọn cho khách. Bạn vui lòng đăng nhập để tiếp tục.</p>
                    </div>
                    <div class="shortener__locked-actions">
                        <a class="btn btn--primary" href="<?= e(url('/dang-nhap')) ?>">Đăng nhập</a>
                        <?php if (Settings::bool('allow_registration', true)): ?>
                            <a class="btn btn--ghost" href="<?= e(url('/dang-ky')) ?>">Tạo tài khoản</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <form class="shortener__form" method="post" action="<?= e(url('/rut-gon')) ?>" data-shortener>
                    <?= csrf_field() ?>

                    <div class="field field--hero">
                        <label class="field__label" for="target_url">Dán địa chỉ cần rút gọn</label>
                        <div class="input-with-icon">
                            <svg class="input-with-icon__icon" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M10.6 13.4a3.4 3.4 0 0 1 0-4.8l1.9-1.9a3.4 3.4 0 0 1 4.8 4.8l-.7.7"/>
                                <path d="M13.4 10.6a3.4 3.4 0 0 1 0 4.8l-1.9 1.9a3.4 3.4 0 0 1-4.8-4.8l.7-.7"/>
                            </svg>
                            <input class="input input--lg<?= field_error('target_url') !== null ? ' has-error' : '' ?>"
                                   type="text" id="target_url" name="target_url" inputmode="url"
                                   value="<?= e(old('target_url')) ?>" autocomplete="off" required
                                   placeholder="https://sgddt.dongnai.gov.vn/…">
                        </div>
                        <?php if (field_error('target_url') !== null): ?>
                            <p class="field__error"><?= e((string) field_error('target_url')) ?></p>
                        <?php else: ?>
                            <p class="field__hint">Gõ thiếu <code>https://</code> cũng được, hệ thống tự thêm giúp bạn.</p>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label class="field__label" for="code">
                            Tên tuỳ chọn <span class="field__optional">(không bắt buộc)</span>
                        </label>
                        <div class="alias-input">
                            <span class="alias-input__prefix"><?= e($basePrefix) ?></span>
                            <input class="input alias-input__field<?= field_error('code') !== null ? ' has-error' : '' ?>"
                                   type="text" id="code" name="code" value="<?= e(old('code')) ?>"
                                   placeholder="ten-cua-toi" autocomplete="off" spellcheck="false"
                                   pattern="[A-Za-z0-9][A-Za-z0-9._\-]*"
                                   maxlength="<?= (int) Config::get('code_max_length', 64) ?>"
                                   data-alias-check
                                   data-check-url="<?= e(url('/api/kiem-tra-ten')) ?>">
                            <span class="alias-input__state" data-alias-state aria-live="polite"></span>
                        </div>
                        <?php if (field_error('code') !== null): ?>
                            <p class="field__error"><?= e((string) field_error('code')) ?></p>
                        <?php else: ?>
                            <p class="field__hint">
                                Để trống thì hệ thống tự sinh mã ngẫu nhiên. Ví dụ tên tuỳ chọn:
                                <button class="link-button" type="button" data-alias-suggest="hoi-thao-2026">hoi-thao-2026</button>,
                                <button class="link-button" type="button" data-alias-suggest="tuyen-sinh-10">tuyen-sinh-10</button>,
                                <button class="link-button" type="button" data-alias-suggest="lich-thi-hk1">lich-thi-hk1</button>.
                            </p>
                        <?php endif; ?>
                    </div>

                    <details class="advanced"<?= old('title') !== '' || old('tags') !== '' || old('expires_at') !== '' ? ' open' : '' ?>>
                        <summary class="advanced__summary">
                            <span>Tuỳ chọn nâng cao</span>
                            <small>tiêu đề, thẻ, hạn dùng, mật khẩu, giới hạn lượt nhấp</small>
                        </summary>

                        <div class="advanced__grid">
                            <div class="field">
                                <label class="field__label" for="title">Tiêu đề để dễ nhớ</label>
                                <input class="input" type="text" id="title" name="title" maxlength="200"
                                       value="<?= e(old('title')) ?>" placeholder="Thông báo tuyển sinh lớp 10">
                            </div>

                            <div class="field">
                                <label class="field__label" for="tags">Thẻ phân loại</label>
                                <input class="input" type="text" id="tags" name="tags" maxlength="200"
                                       value="<?= e(old('tags')) ?>" placeholder="tuyển sinh, 2026">
                                <p class="field__hint">Cách nhau bằng dấu phẩy, tối đa 8 thẻ.</p>
                            </div>

                            <div class="field">
                                <label class="field__label" for="expires_at">Hết hạn lúc <small>(giờ Việt Nam)</small></label>
                                <input class="input" type="datetime-local" id="expires_at" name="expires_at"
                                       value="<?= e(old('expires_at')) ?>">
                            </div>

                            <div class="field">
                                <label class="field__label" for="starts_at">Bắt đầu hoạt động từ <small>(giờ Việt Nam)</small></label>
                                <input class="input" type="datetime-local" id="starts_at" name="starts_at"
                                       value="<?= e(old('starts_at')) ?>">
                            </div>

                            <div class="field">
                                <label class="field__label" for="max_clicks">Giới hạn số lượt nhấp</label>
                                <input class="input" type="number" id="max_clicks" name="max_clicks" min="0" step="1"
                                       value="<?= e(old('max_clicks')) ?>" placeholder="0 = không giới hạn">
                            </div>

                            <div class="field">
                                <label class="field__label" for="password">Mật khẩu bảo vệ</label>
                                <input class="input" type="text" id="password" name="password" autocomplete="off"
                                       placeholder="Để trống nếu không cần">
                                <p class="field__hint">Người nhận phải nhập mật khẩu này mới mở được liên kết.</p>
                            </div>

                            <div class="field field--wide">
                                <label class="field__label" for="note">Ghi chú nội bộ</label>
                                <textarea class="input" id="note" name="note" rows="2" maxlength="500"
                                          placeholder="Chỉ bạn thấy ghi chú này."><?= e(old('note')) ?></textarea>
                            </div>
                        </div>
                    </details>

                    <div class="shortener__submit">
                        <button class="btn btn--primary btn--lg btn--shine" type="submit">
                            <span>Rút gọn ngay</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13m0 0-5-5m5 5-5 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <?php if ($user === null): ?>
                            <p class="shortener__note">
                                Đang dùng với tư cách khách — mỗi giờ tạo được
                                <?= e(n((int) Config::get('guest_hourly_limit', 10))) ?> liên kết.
                                <a href="<?= e(url('/dang-ky')) ?>">Tạo tài khoản</a> để lưu lại và xem thống kê đầy đủ.
                            </p>
                        <?php else: ?>
                            <p class="shortener__note">
                                Đang tạo với tài khoản <strong><?= e(Auth::displayName()) ?></strong>.
                                <a href="<?= e(url('/lien-ket/tao')) ?>">Dùng biểu mẫu đầy đủ</a> nếu cần tuỳ chỉnh mã QR.
                            </p>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($recentLinks !== []): ?>
    <section class="section">
        <div class="wrap">
            <header class="section__head">
                <h2 class="section__title">
                    <?= $user === null ? 'Liên kết bạn vừa tạo' : 'Liên kết gần đây của bạn' ?>
                </h2>
                <?php if ($user !== null): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/lien-ket')) ?>">Xem tất cả</a>
                <?php else: ?>
                    <p class="section__note">Danh sách này chỉ lưu trong phiên làm việc hiện tại.</p>
                <?php endif; ?>
            </header>

            <div class="link-grid">
                <?php foreach ($recentLinks as $link): ?>
                    <?= render('partials/link-card', ['link' => $link]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="section section--features">
    <div class="wrap">
        <header class="section__head section__head--center">
            <h2 class="section__title">Hệ thống có gì cho bạn?</h2>
            <p class="section__sub">Tất cả dữ liệu nằm trong một tệp duy nhất, sao lưu chỉ cần chép một tệp.</p>
        </header>

        <div class="feature-grid">
            <?php
            $features = [
                ['✏️', 'Tên tuỳ chọn', 'Tự đặt phần đuôi liên kết theo ý bạn, ví dụ <code>/tuyen-sinh-10</code>. Hệ thống kiểm tra ngay xem tên còn trống hay chưa.'],
                ['📱', 'Mã QR sẵn sàng in', 'Mỗi liên kết có mã QR riêng, tải về dạng PNG hoặc SVG (vector, in khổ lớn không rỗ), đổi được màu.'],
                ['📊', 'Thống kê đầy đủ', 'Lượt nhấp theo ngày, theo giờ, theo thứ; trình duyệt, hệ điều hành, thiết bị, nguồn giới thiệu, lượt quét QR.'],
                ['🔒', 'Mật khẩu bảo vệ', 'Liên kết nội bộ có thể đặt mật khẩu, chỉ người biết mật khẩu mới mở được.'],
                ['⏳', 'Hẹn giờ và giới hạn', 'Đặt thời điểm bắt đầu, thời điểm hết hạn hoặc số lượt nhấp tối đa cho từng liên kết.'],
                ['🏷️', 'Thẻ phân loại', 'Gắn thẻ cho từng liên kết rồi lọc nhanh theo đợt công tác, theo năm học.'],
                ['📦', 'Tạo hàng loạt', 'Dán một danh sách địa chỉ, hệ thống rút gọn tất cả trong một lần.'],
                ['📤', 'Xuất CSV & API', 'Tải danh sách ra Excel, hoặc gọi API để tích hợp với phần mềm khác của đơn vị.'],
            ];
            foreach ($features as [$icon, $title, $desc]): ?>
                <article class="feature">
                    <span class="feature__icon" aria-hidden="true"><?= $icon ?></span>
                    <h3 class="feature__title"><?= e($title) ?></h3>
                    <p class="feature__desc"><?= $desc ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section--steps">
    <div class="wrap">
        <header class="section__head section__head--center">
            <h2 class="section__title">Ba bước là xong</h2>
        </header>
        <ol class="steps">
            <li class="step">
                <span class="step__num">1</span>
                <h3>Dán địa chỉ</h3>
                <p>Sao chép địa chỉ dài từ trình duyệt rồi dán vào ô phía trên.</p>
            </li>
            <li class="step">
                <span class="step__num">2</span>
                <h3>Đặt tên tuỳ chọn</h3>
                <p>Gõ tên bạn muốn, ví dụ <code>lich-thi-hk1</code>. Bỏ trống thì hệ thống tự sinh mã.</p>
            </li>
            <li class="step">
                <span class="step__num">3</span>
                <h3>Chia sẻ và theo dõi</h3>
                <p>Sao chép liên kết, tải mã QR, rồi xem có bao nhiêu người đã truy cập.</p>
            </li>
        </ol>
    </div>
</section>
