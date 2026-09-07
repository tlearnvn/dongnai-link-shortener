<?php
/** Công cụ gắn thẻ UTM rồi rút gọn. */
$sources = ['zalo' => 'Zalo', 'facebook' => 'Facebook', 'email' => 'Email', 'sms' => 'Tin nhắn SMS', 'website' => 'Trang thông tin đơn vị', 'qr' => 'Mã QR in trên văn bản', 'zalo-oa' => 'Zalo OA của đơn vị'];
?>
<section class="section">
    <div class="wrap wrap--narrow">
        <header class="page-head page-head--stack">
            <p class="page-head__eyebrow">Công cụ</p>
            <h1 class="page-head__title">Gắn thẻ UTM cho liên kết</h1>
            <p class="page-head__sub">
                Thẻ UTM giúp bạn biết người xem đến từ đâu — từ tin Zalo, từ email hay từ mã QR trên văn bản —
                khi trang đích có dùng Google Analytics hoặc công cụ phân tích tương tự.
            </p>
        </header>

        <div class="card card--glass form-card">
            <form data-utm-form>
                <div class="field">
                    <label class="field__label" for="utm_url">Địa chỉ trang đích <span class="field__req">*</span></label>
                    <input class="input input--lg" type="text" id="utm_url" name="utm_url"
                           placeholder="https://sgddt.dongnai.gov.vn/thong-bao" required>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="utm_source">Nguồn (utm_source)</label>
                        <input class="input" type="text" id="utm_source" name="utm_source"
                               list="goi-y-nguon" placeholder="zalo">
                        <datalist id="goi-y-nguon">
                            <?php foreach ($sources as $value => $label): ?>
                                <option value="<?= e($value) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                        <p class="field__hint">Kênh phát hành: zalo, facebook, email, qr…</p>
                    </div>

                    <div class="field">
                        <label class="field__label" for="utm_medium">Hình thức (utm_medium)</label>
                        <input class="input" type="text" id="utm_medium" name="utm_medium" placeholder="social">
                        <p class="field__hint">Cách truyền tải: social, email, print, banner…</p>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label class="field__label" for="utm_campaign">Chiến dịch (utm_campaign)</label>
                        <input class="input" type="text" id="utm_campaign" name="utm_campaign"
                               placeholder="tuyen-sinh-2026">
                        <p class="field__hint">Tên đợt công tác, ví dụ: tuyen-sinh-2026.</p>
                    </div>

                    <div class="field">
                        <label class="field__label" for="utm_content">Nội dung (utm_content)</label>
                        <input class="input" type="text" id="utm_content" name="utm_content" placeholder="bai-dang-1">
                        <p class="field__hint">Phân biệt hai bản khác nhau của cùng chiến dịch.</p>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="utm_result">Liên kết đã gắn thẻ</label>
                    <textarea class="input input--mono" id="utm_result" rows="3" readonly
                              placeholder="Kết quả hiện ở đây khi bạn nhập địa chỉ phía trên."></textarea>
                </div>

                <div class="form-card__actions">
                    <button class="btn btn--primary" type="button" data-utm-copy>Sao chép liên kết</button>
                    <button class="btn btn--ghost" type="button" data-utm-shorten>Rút gọn liên kết này</button>
                    <button class="btn btn--ghost" type="reset">Xoá hết</button>
                </div>
                <p class="field__hint">
                    Nút “Rút gọn liên kết này” sẽ mở trang chủ với địa chỉ đã điền sẵn, bạn chỉ cần
                    đặt tên tuỳ chọn rồi bấm rút gọn.
                </p>
            </form>
        </div>

        <div class="card prose">
            <h2>Gợi ý cách đặt thẻ cho đơn vị giáo dục</h2>
            <table class="prose__table">
                <thead>
                    <tr><th>Trường hợp</th><th>utm_source</th><th>utm_medium</th><th>utm_campaign</th></tr>
                </thead>
                <tbody>
                    <tr><td>Đăng tin lên nhóm Zalo</td><td>zalo</td><td>social</td><td>ten-dot-cong-tac</td></tr>
                    <tr><td>Gửi email cho các trường</td><td>email</td><td>email</td><td>ten-dot-cong-tac</td></tr>
                    <tr><td>Mã QR in trên công văn</td><td>qr</td><td>print</td><td>so-cong-van</td></tr>
                    <tr><td>Đăng trên trang thông tin</td><td>website</td><td>referral</td><td>ten-dot-cong-tac</td></tr>
                </tbody>
            </table>
            <p class="prose__note">
                💡 Thẻ UTM chỉ có ích khi <strong>trang đích</strong> có công cụ phân tích. Nếu bạn chỉ cần
                biết bao nhiêu người bấm vào, dùng luôn phần
                <a href="<?= e(url('/thong-ke')) ?>">Thống kê</a> của hệ thống này là đủ — nó đếm sẵn cho bạn,
                kể cả tách riêng lượt quét mã QR.
            </p>
        </div>
    </div>
</section>
