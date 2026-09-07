<?php
/**
 * Cài đặt hệ thống và bảo trì.
 *
 * @var array<string, string|null> $settings
 * @var array<string, mixed>       $summary
 */
$eccNames = [0 => 'L — thấp (~7%)', 1 => 'M — vừa (~15%)', 2 => 'Q — cao (~25%)', 3 => 'H — rất cao (~30%)'];
$currentEcc = (int) ($settings['default_ecc'] ?? 1);
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Quản trị</p>
                <h1 class="page-head__title">Cài đặt hệ thống</h1>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/quan-tri')) ?>">← Tổng quan</a>
            </div>
        </header>

        <form class="card form-card" method="post" action="<?= e(url('/quan-tri/cai-dat')) ?>">
            <?= csrf_field() ?>

            <fieldset class="fieldset">
                <legend class="fieldset__legend">Quyền sử dụng</legend>

                <label class="switch">
                    <input type="checkbox" name="allow_registration" value="1"
                        <?= ($settings['allow_registration'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"><span class="switch__dot"></span></span>
                    <span class="switch__label">
                        Cho phép tự đăng ký tài khoản
                        <small>Tắt đi thì chỉ quản trị viên cấp tài khoản được.</small>
                    </span>
                </label>

                <label class="switch">
                    <input type="checkbox" name="allow_guest_shorten" value="1"
                        <?= ($settings['allow_guest_shorten'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"><span class="switch__dot"></span></span>
                    <span class="switch__label">
                        Cho khách (chưa đăng nhập) rút gọn liên kết
                        <small>
                            Khách bị giới hạn <?= e(n((int) Config::get('guest_hourly_limit', 10))) ?> liên kết/giờ
                            theo địa chỉ IP.
                        </small>
                    </span>
                </label>

                <label class="switch">
                    <input type="checkbox" name="block_open_redirect" value="1"
                        <?= ($settings['block_open_redirect'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"><span class="switch__dot"></span></span>
                    <span class="switch__label">
                        Chặn rút gọn địa chỉ trỏ về chính hệ thống
                        <small>Ngăn tạo vòng lặp chuyển hướng. Nên để bật.</small>
                    </span>
                </label>

                <label class="switch">
                    <input type="checkbox" name="auto_title" value="1"
                        <?= ($settings['auto_title'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"><span class="switch__dot"></span></span>
                    <span class="switch__label">
                        Tự lấy tiêu đề của trang đích
                        <small>
                            Khi người dùng không tự nhập tiêu đề, máy chủ sẽ tải trang đích để đọc thẻ
                            &lt;title&gt;. Việc này làm chậm bước tạo liên kết và cần máy chủ có kết nối
                            ra Internet — mặc định tắt.
                        </small>
                    </span>
                </label>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset__legend">Mã QR</legend>
                <div class="field">
                    <label class="field__label" for="default_ecc">Mức sửa lỗi mặc định</label>
                    <select class="input" id="default_ecc" name="default_ecc">
                        <?php foreach ($eccNames as $value => $label): ?>
                            <option value="<?= (int) $value ?>" <?= $currentEcc === (int) $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint">
                        Mức M là lựa chọn cân bằng cho hầu hết trường hợp. Chọn Q hoặc H nếu mã QR
                        thường bị in mờ, dán ngoài trời hoặc bị che một phần.
                    </p>
                </div>
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset__legend">Thông báo trên đầu trang</legend>
                <div class="field">
                    <label class="field__label" for="announcement">Nội dung thông báo</label>
                    <textarea class="input" id="announcement" name="announcement" rows="2" maxlength="300"
                              placeholder="Ví dụ: Hệ thống bảo trì lúc 22h ngày 30/9."><?= e((string) ($settings['announcement'] ?? '')) ?></textarea>
                    <p class="field__hint">Để trống nếu không muốn hiện dải thông báo. Tối đa 300 ký tự.</p>
                </div>
            </fieldset>

            <div class="form-card__actions">
                <button class="btn btn--primary btn--lg" type="submit">Lưu cài đặt</button>
            </div>
        </form>

        <div class="card">
            <h2 class="card__title">Thông tin kỹ thuật</h2>
            <dl class="spec-list">
                <div><dt>Tệp cơ sở dữ liệu</dt><dd><code><?= e(Database::path()) ?></code></dd></div>
                <div><dt>Dung lượng</dt><dd><?= e(bytes_human((int) $summary['db_size'])) ?></dd></div>
                <div><dt>Số bản ghi lượt nhấp</dt><dd><?= e(n((int) $summary['clicks'])) ?></dd></div>
                <div><dt>Phiên bản PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
                <div><dt>Phiên bản SQLite</dt><dd><?= e((string) Database::pdo()->query('SELECT sqlite_version()')->fetchColumn()) ?></dd></div>
                <div><dt>Múi giờ</dt><dd><?= e((string) Config::get('timezone')) ?> — <?= e(Clock::tzLabel()) ?></dd></div>
                <div><dt>Giờ máy chủ hiện tại</dt><dd><?= e(Clock::nowDt()->format('H:i:s d/m/Y')) ?></dd></div>
                <div><dt>Địa chỉ gốc</dt><dd><code><?= e(base_url()) ?></code></dd></div>
            </dl>
            <p class="card__foot-note">
                Sao lưu hệ thống chỉ cần chép tệp cơ sở dữ liệu ở trên (kèm hai tệp
                <code>-wal</code>, <code>-shm</code> nếu có) sang nơi an toàn.
            </p>
        </div>

        <div class="card card--warning">
            <h2 class="card__title">Bảo trì</h2>
            <p class="card__sub">Các thao tác dưới đây không thể hoàn tác — hãy sao lưu trước khi dùng.</p>

            <div class="maintenance">
                <form class="maintenance__item" method="post" action="<?= e(url('/quan-tri/bao-tri')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="prune_clicks">
                    <div>
                        <h3>Xoá chi tiết lượt nhấp cũ</h3>
                        <p>Giữ lại số tổng hợp của từng liên kết, chỉ xoá bản ghi chi tiết cũ hơn số ngày bạn chọn.</p>
                    </div>
                    <div class="maintenance__control">
                        <input class="input input--xs" type="number" name="days" value="365" min="1" step="1">
                        <span>ngày</span>
                        <button class="btn btn--ghost btn--sm" type="submit">Xoá</button>
                    </div>
                </form>

                <form class="maintenance__item" method="post" action="<?= e(url('/quan-tri/bao-tri')) ?>"
                      data-confirm="Xoá tất cả liên kết đã hết hạn?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_expired">
                    <div>
                        <h3>Xoá liên kết đã hết hạn</h3>
                        <p>Xoá hẳn những liên kết có thời điểm hết hạn đã qua, kèm số liệu của chúng.</p>
                    </div>
                    <div class="maintenance__control">
                        <button class="btn btn--ghost btn--sm" type="submit">Xoá</button>
                    </div>
                </form>

                <form class="maintenance__item" method="post" action="<?= e(url('/quan-tri/bao-tri')) ?>"
                      data-confirm="Xoá toàn bộ nhật ký hoạt động?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_audit">
                    <div>
                        <h3>Xoá nhật ký hoạt động</h3>
                        <p>Dọn bảng nhật ký cho gọn. Không ảnh hưởng tới liên kết và thống kê.</p>
                    </div>
                    <div class="maintenance__control">
                        <button class="btn btn--ghost btn--sm" type="submit">Xoá</button>
                    </div>
                </form>

                <form class="maintenance__item" method="post" action="<?= e(url('/quan-tri/bao-tri')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_attempts">
                    <div>
                        <h3>Mở khoá đăng nhập</h3>
                        <p>Xoá lịch sử nhập sai mật khẩu, mở khoá các địa chỉ IP đang bị tạm chặn.</p>
                    </div>
                    <div class="maintenance__control">
                        <button class="btn btn--ghost btn--sm" type="submit">Mở khoá</button>
                    </div>
                </form>

                <form class="maintenance__item" method="post" action="<?= e(url('/quan-tri/bao-tri')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="vacuum">
                    <div>
                        <h3>Dồn nén cơ sở dữ liệu</h3>
                        <p>Chạy lệnh VACUUM để thu nhỏ tệp sau khi xoá nhiều dữ liệu.</p>
                    </div>
                    <div class="maintenance__control">
                        <button class="btn btn--ghost btn--sm" type="submit">Dồn nén</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
