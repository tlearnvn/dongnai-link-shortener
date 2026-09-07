<?php
/**
 * Tạo nhiều liên kết một lượt.
 *
 * @var array<string, mixed> $user
 * @var array{created: array, failed: array}|null $results
 */
$maxLines = (int) Config::get('bulk_max_lines', 200);
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Công cụ</p>
                <h1 class="page-head__title">Tạo hàng loạt</h1>
                <p class="page-head__sub">
                    Dán danh sách địa chỉ, mỗi dòng một địa chỉ — hệ thống rút gọn tất cả trong một lần.
                </p>
            </div>
        </header>

        <?php if ($results !== null): ?>
            <?php if (($results['created'] ?? []) !== []): ?>
                <div class="card">
                    <header class="card__head">
                        <h2 class="card__title">✅ Đã tạo <?= e(n(count($results['created']))) ?> liên kết</h2>
                        <button class="btn btn--ghost btn--sm" type="button" data-copy-list="#ket-qua-hang-loat">
                            Sao chép tất cả
                        </button>
                    </header>

                    <div class="table-scroll">
                        <table class="table" id="ket-qua-hang-loat">
                            <thead>
                                <tr>
                                    <th>Liên kết rút gọn</th>
                                    <th>Địa chỉ gốc</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results['created'] as $row): ?>
                                    <tr>
                                        <td>
                                            <a class="mono-link" data-short-url
                                               href="<?= e(short_url((string) $row['code'])) ?>"
                                               target="_blank" rel="noopener">
                                                <?= e(short_url_display((string) $row['code'])) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="table__url" title="<?= e((string) $row['target_url']) ?>">
                                                <?= e(truncate_str((string) $row['target_url'], 52)) ?>
                                            </span>
                                        </td>
                                        <td class="table__actions">
                                            <button class="btn btn--ghost btn--xs" type="button"
                                                    data-copy="<?= e(short_url((string) $row['code'])) ?>">Sao chép</button>
                                            <a class="btn btn--ghost btn--xs"
                                               href="<?= e(url('/ma-qr/' . rawurlencode((string) $row['code']))) ?>">QR</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (($results['failed'] ?? []) !== []): ?>
                <div class="card card--warning">
                    <h2 class="card__title">⚠️ <?= e(n(count($results['failed']))) ?> dòng chưa xử lý được</h2>
                    <ul class="issue-list">
                        <?php foreach ($results['failed'] as $item): ?>
                            <li>
                                <code><?= e(truncate_str((string) $item['line'], 70)) ?></code>
                                <span><?= e((string) $item['error']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form class="card card--glass form-card" method="post" action="<?= e(url('/tao-hang-loat')) ?>">
            <?= csrf_field() ?>

            <div class="field">
                <label class="field__label" for="links">
                    Danh sách địa chỉ <span class="field__req">*</span>
                </label>
                <textarea class="input input--mono" id="links" name="links" rows="10" required
                          placeholder="https://vidu.vn/trang-mot&#10;https://vidu.vn/trang-hai | ten-tuy-chon&#10;https://vidu.vn/trang-ba | lich-thi | Lịch thi học kỳ 1"></textarea>
                <p class="field__hint">
                    Mỗi dòng một địa chỉ, tối đa <?= e(n($maxLines)) ?> dòng.
                    Muốn tự đặt tên thì thêm dấu <code>|</code> rồi ghi tên tuỳ chọn,
                    thêm dấu <code>|</code> nữa để ghi tiêu đề.
                </p>
            </div>

            <div class="field-row">
                <div class="field">
                    <label class="field__label" for="tags">Gắn thẻ cho cả lô</label>
                    <input class="input" type="text" id="tags" name="tags" maxlength="200"
                           placeholder="đợt 1, 2026">
                </div>
                <div class="field">
                    <label class="field__label" for="expires_at">Hết hạn chung <small>(GMT+7)</small></label>
                    <input class="input" type="datetime-local" id="expires_at" name="expires_at">
                </div>
            </div>

            <div class="form-card__actions">
                <button class="btn btn--primary btn--lg" type="submit">Rút gọn tất cả</button>
                <a class="btn btn--ghost" href="<?= e(url('/lien-ket')) ?>">Về danh sách</a>
            </div>
        </form>

        <div class="callout callout--muted">
            <h2>Ví dụ dán vào</h2>
            <pre class="code-block">https://sgddt.dongnai.gov.vn/thong-bao/2026/tuyen-sinh.pdf | tuyen-sinh-2026 | Thông báo tuyển sinh
https://sgddt.dongnai.gov.vn/lich-cong-tac.aspx | lich-cong-tac
https://drive.google.com/file/d/1a2b3c/view</pre>
            <p>Ba dòng trên sẽ cho ra ba liên kết, hai dòng đầu dùng tên bạn tự đặt.</p>
        </div>
    </div>
</section>
