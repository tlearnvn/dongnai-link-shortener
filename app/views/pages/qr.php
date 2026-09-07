<?php
/**
 * Trang mã QR: xem, tuỳ chỉnh màu/kích thước và tải về.
 *
 * @var array<string, mixed> $link
 * @var bool                 $canManage
 */
$code = (string) $link['code'];
$shortUrl = short_url($code);

// Giá trị hiện tại (đọc từ tham số truy vấn để bản không có JavaScript vẫn dùng được)
$scale = max(2, min(30, input_int('co', 10)));
$border = max(0, min(8, input_int('le', 4)));
$dark = input('mau') !== '' ? input('mau') : (string) $link['qr_dark'];
$light = input('nen') !== '' ? input('nen') : (string) $link['qr_light'];
$ecc = max(0, min(3, input_int('sua-loi', Settings::int('default_ecc', 1))));
$withSource = input('nguon') !== '0';

$query = http_build_query([
    'co' => $scale,
    'le' => $border,
    'mau' => $dark,
    'nen' => $light,
    'sua-loi' => $ecc,
    'nguon' => $withSource ? '1' : '0',
]);

$svgUrl = url('ma-qr/' . rawurlencode($code) . '.svg') . '?' . $query;
$pngUrl = url('ma-qr/' . rawurlencode($code) . '.png') . '?' . $query;

// Thông tin kỹ thuật của mã đang xem
$payload = $withSource ? $shortUrl . '?s=qr' : $shortUrl;
$qr = QrCode::encode($payload, $ecc);
$eccNames = [0 => 'L — thấp (~7%)', 1 => 'M — vừa (~15%)', 2 => 'Q — cao (~25%)', 3 => 'H — rất cao (~30%)'];

$presets = [
    ['Mực đen', '#0b1220', '#ffffff'],
    ['Tím Đồng Nai', '#4f46e5', '#ffffff'],
    ['Xanh ngọc', '#0f766e', '#f0fdfa'],
    ['Đỏ trang trọng', '#b91c1c', '#fff7ed'],
    ['Xanh dương', '#1d4ed8', '#eff6ff'],
    ['Nâu đất', '#78350f', '#fffbeb'],
];
?>
<section class="section">
    <div class="wrap">
        <header class="page-head">
            <div>
                <p class="page-head__eyebrow">Mã QR</p>
                <h1 class="page-head__title">/<?= e($code) ?></h1>
                <p class="page-head__sub">
                    Quét mã sẽ mở <a href="<?= e($shortUrl) ?>" target="_blank" rel="noopener"><?= e(short_url_display($code)) ?></a>
                    → <?= e(truncate_str((string) $link['target_url'], 70)) ?>
                </p>
            </div>
            <div class="page-head__actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/xem/' . rawurlencode($code))) ?>">Xem trước</a>
                <?php if ($canManage): ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/thong-ke/' . (int) $link['id'])) ?>">Thống kê</a>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/lien-ket/' . (int) $link['id'] . '/sua')) ?>">Sửa</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="qr-studio">
            <div class="card qr-studio__preview">
                <div class="qr-frame qr-frame--lg" style="background: <?= e($light) ?>">
                    <img id="qr-preview" src="<?= e($svgUrl) ?>" alt="Mã QR của <?= e($code) ?>"
                         width="320" height="320">
                </div>

                <p class="qr-studio__caption">
                    Phiên bản <?= (int) $qr->version() ?> · <?= (int) $qr->size() ?>×<?= (int) $qr->size() ?> module ·
                    mức sửa lỗi <?= e(['L', 'M', 'Q', 'H'][$ecc]) ?>
                </p>

                <div class="qr-studio__downloads">
                    <a class="btn btn--primary" href="<?= e($pngUrl . '&tai=1') ?>" download>Tải ảnh PNG</a>
                    <a class="btn btn--ghost" href="<?= e($svgUrl . '&tai=1') ?>" download>Tải vector SVG</a>
                    <button class="btn btn--ghost" type="button" data-copy="<?= e($shortUrl) ?>">Sao chép liên kết</button>
                    <button class="btn btn--ghost" type="button" data-print="#qr-preview">In mã QR</button>
                </div>

                <p class="qr-studio__tip">
                    💡 Cần in lên băng-rôn hay pa-nô khổ lớn? Chọn <strong>SVG</strong> — ảnh vector nên phóng to
                    bao nhiêu cũng nét. In trên giấy A4 thì PNG là đủ.
                </p>
            </div>

            <form class="card qr-studio__controls" method="get" action="<?= e(url('/ma-qr/' . rawurlencode($code))) ?>"
                  data-qr-studio data-svg-base="<?= e(url('ma-qr/' . rawurlencode($code) . '.svg')) ?>"
                  data-modules="<?= (int) $qr->size() ?>">
                <h2 class="card__title">Tuỳ chỉnh</h2>

                <div class="field">
                    <label class="field__label" for="co">
                        Kích thước ô <output class="field__output" data-output-for="co"><?= (int) $scale ?></output> px
                    </label>
                    <input class="range" type="range" id="co" name="co" min="4" max="24" step="1" value="<?= (int) $scale ?>">
                    <p class="field__hint">
                        Ảnh PNG xuất ra:
                        <span data-px-size><?= (int) (($qr->size() + $border * 2) * $scale) ?></span> px mỗi chiều.
                    </p>
                </div>

                <div class="field">
                    <label class="field__label" for="le">
                        Lề trắng <output class="field__output" data-output-for="le"><?= (int) $border ?></output> ô
                    </label>
                    <input class="range" type="range" id="le" name="le" min="0" max="8" step="1" value="<?= (int) $border ?>">
                    <p class="field__hint">Chuẩn khuyến nghị là 4 ô để máy quét nhận nhanh.</p>
                </div>

                <div class="field field--row">
                    <label class="color-field">
                        <span>Màu mã</span>
                        <input type="color" id="mau" name="mau" value="<?= e($dark) ?>">
                    </label>
                    <label class="color-field">
                        <span>Màu nền</span>
                        <input type="color" id="nen" name="nen" value="<?= e($light) ?>">
                    </label>
                </div>

                <div class="field">
                    <span class="field__label">Bảng màu có sẵn</span>
                    <div class="swatches">
                        <?php foreach ($presets as [$name, $fg, $bg]): ?>
                            <button class="swatch" type="button" title="<?= e($name) ?>"
                                    data-swatch-dark="<?= e($fg) ?>" data-swatch-light="<?= e($bg) ?>"
                                    style="--sw-fg: <?= e($fg) ?>; --sw-bg: <?= e($bg) ?>">
                                <span aria-hidden="true"></span>
                                <em><?= e($name) ?></em>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="sua-loi">Mức sửa lỗi</label>
                    <select class="input" id="sua-loi" name="sua-loi">
                        <?php foreach ($eccNames as $value => $label): ?>
                            <option value="<?= (int) $value ?>" <?= $ecc === (int) $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field__hint">
                        Mức cao hơn giúp mã vẫn quét được khi bị bẩn, nhoè hoặc dán che một phần,
                        nhưng ô sẽ dày hơn.
                    </p>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="nguon" value="1" <?= $withSource ? 'checked' : '' ?>>
                    <span>Đánh dấu lượt quét QR trong thống kê</span>
                </label>
                <p class="field__hint">
                    Khi bật, mã QR mang thêm dấu <code>?s=qr</code> để bạn phân biệt được
                    người quét mã và người bấm liên kết.
                </p>

                <div class="qr-studio__submit">
                    <button class="btn btn--primary" type="submit">Áp dụng</button>
                    <a class="btn btn--ghost" href="<?= e(url('/ma-qr/' . rawurlencode($code))) ?>">Về mặc định</a>
                </div>
                <p class="field__hint field__hint--muted">
                    (Nút “Áp dụng” dành cho trường hợp trình duyệt tắt JavaScript — bình thường
                    mã QR đổi ngay khi bạn kéo thanh trượt.)
                </p>
            </form>
        </div>
    </div>
</section>
