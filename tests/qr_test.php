<?php
declare(strict_types=1);

/**
 * Kiểm định bộ tạo mã QR (app/lib/QrCode.php).
 *
 * Test này KHÔNG dùng lại code của QrCode để giải mã: nó tự dựng lại bản đồ
 * module chức năng, bảng vị trí hoa văn căn chỉnh (theo bảng trong chuẩn
 * ISO/IEC 18004) và tự giải mã ma trận ngược về chuỗi ban đầu. Nhờ vậy lỗi ở
 * một bên sẽ lộ ra chứ không triệt tiêu nhau.
 *
 * Chạy: php tests/qr_test.php
 */

require __DIR__ . '/../app/lib/QrCode.php';
require __DIR__ . '/qr_decoder.php';

$pass = 0;
$fail = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } else {
        $fail++;
        echo "  \033[31m✗\033[0m {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function section(string $name): void
{
    echo "\n\033[1m{$name}\033[0m\n";
}

// ---------------------------------------------------------------------------
// Bảng tham chiếu lấy từ chuẩn (dùng để đối chiếu với công thức trong QrCode)
// ---------------------------------------------------------------------------

/** Số từ mã dữ liệu (bảng 7 của chuẩn) cho vài phiên bản tiêu biểu: [L, M, Q, H]. */
const DATA_CODEWORDS = [
    1 => [19, 16, 13, 9],
    2 => [34, 28, 22, 16],
    3 => [55, 44, 34, 26],
    4 => [80, 64, 48, 36],
    5 => [108, 86, 62, 46],
    10 => [274, 216, 154, 122],
    20 => [861, 669, 485, 385],
    40 => [2956, 2334, 1666, 1276],
];

/**
 * Dung lượng tối đa ở chế độ byte (bảng 7 "Character capacity" của chuẩn).
 * Bảng này độc lập với DATA_CODEWORDS nên dùng để đối chiếu chéo.
 */
const BYTE_CAPACITY = [
    1 => [17, 14, 11, 7],
    5 => [106, 84, 60, 44],
    10 => [271, 213, 151, 119],
    20 => [858, 666, 482, 382],
    40 => [2953, 2331, 1663, 1273],
];


// ---------------------------------------------------------------------------
// 1. Đối chiếu bảng vị trí hoa văn căn chỉnh
// ---------------------------------------------------------------------------
section('1. Vị trí hoa văn căn chỉnh (công thức vs bảng E.1 của chuẩn)');
$reflection = new ReflectionClass(QrCode::class);
$mismatch = [];
for ($v = 1; $v <= 40; $v++) {
    $qr = QrCode::encode('x', QrCode::ECC_LOW, $v);
    $method = $reflection->getMethod('alignmentPatternPositions');
    $method->setAccessible(true);
    $computed = $method->invoke($qr);
    if ($computed !== ALIGN_TABLE[$v]) {
        $mismatch[] = "V{$v}: [" . implode(',', $computed) . '] ≠ [' . implode(',', ALIGN_TABLE[$v]) . ']';
    }
}
check('Cả 40 phiên bản khớp bảng chuẩn', $mismatch === [], implode('; ', array_slice($mismatch, 0, 5)));

// ---------------------------------------------------------------------------
// 2. Đối chiếu số từ mã dữ liệu
// ---------------------------------------------------------------------------
section('2. Dung lượng dữ liệu (công thức vs bảng 7 của chuẩn)');
$method = $reflection->getMethod('numDataCodewords');
$method->setAccessible(true);
$capacityErrors = [];
foreach (DATA_CODEWORDS as $v => $expected) {
    foreach ($expected as $ecl => $want) {
        $got = $method->invoke(null, $v, $ecl);
        if ($got !== $want) {
            $capacityErrors[] = "V{$v} ECC{$ecl}: {$got} ≠ {$want}";
        }
    }
}
check('Số từ mã dữ liệu khớp bảng chuẩn', $capacityErrors === [], implode('; ', $capacityErrors));

// Dung lượng byte mode tối đa: V1-L = 17 byte, V40-L = 2953 byte
$v1 = QrCode::encode(str_repeat('A', 17), QrCode::ECC_LOW);
check('V1-L chứa đúng 17 byte (phiên bản = 1)', $v1->version() === 1, 'nhận V' . $v1->version());
$maxL = QrCode::encode(str_repeat('A', 2953), QrCode::ECC_LOW);
check('V40-L chứa đúng 2953 byte (phiên bản = 40)', $maxL->version() === 40, 'nhận V' . $maxL->version());
$tooLong = false;
try {
    QrCode::encode(str_repeat('A', 2954), QrCode::ECC_LOW);
} catch (RuntimeException) {
    $tooLong = true;
}
check('2954 byte thì báo lỗi vượt dung lượng', $tooLong);

// Đối chiếu chéo với bảng "Character capacity": đúng ngưỡng thì vẫn là phiên
// bản đó, thêm 1 byte thì phải nhảy phiên bản (hoặc báo lỗi nếu đã là V40).
$capErrors = [];
foreach (BYTE_CAPACITY as $v => $levels) {
    foreach ($levels as $ecl => $maxBytes) {
        $atLimit = QrCode::encode(str_repeat('A', $maxBytes), $ecl, $v);
        if ($atLimit->version() !== $v) {
            $capErrors[] = sprintf('V%d ECC%d: %d byte lại thành V%d', $v, $ecl, $maxBytes, $atLimit->version());
            continue;
        }
        try {
            $over = QrCode::encode(str_repeat('A', $maxBytes + 1), $ecl, $v);
            if ($over->version() === $v) {
                $capErrors[] = sprintf('V%d ECC%d: %d byte vẫn nhét vừa (phải tràn)', $v, $ecl, $maxBytes + 1);
            }
        } catch (RuntimeException) {
            if ($v !== 40) {
                $capErrors[] = sprintf('V%d ECC%d: %d byte báo lỗi sớm', $v, $ecl, $maxBytes + 1);
            }
        }
    }
}
check('Ngưỡng dung lượng byte mode khớp bảng chuẩn (20 mốc)', $capErrors === [], implode('; ', $capErrors));

// ---------------------------------------------------------------------------
// 3. Cấu trúc ma trận
// ---------------------------------------------------------------------------
section('3. Cấu trúc ma trận');
$qr = QrCode::encode('https://link.dongnaiedu.vn/abc123', QrCode::ECC_MEDIUM);
$size = $qr->size();
check('Kích thước V' . $qr->version() . ' = ' . $size . ' module', $size === $qr->version() * 4 + 17);

// Hoa văn định vị: vòng 7x7 (tối–sáng–tối 3x3)
$finderOk = true;
foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
    for ($dy = -3; $dy <= 3; $dy++) {
        for ($dx = -3; $dx <= 3; $dx++) {
            $dist = max(abs($dx), abs($dy));
            $expected = $dist !== 2;
            if ($qr->isDark($cx + $dx, $cy + $dy) !== $expected) {
                $finderOk = false;
            }
        }
    }
}
check('3 hoa văn định vị đúng mẫu 1:1:3:1:1', $finderOk);

// Dải phân cách quanh finder phải sáng
$sepOk = true;
for ($i = 0; $i < 8; $i++) {
    if ($qr->isDark($i, 7) || $qr->isDark(7, $i)) {
        $sepOk = false;
    }
}
check('Dải phân cách quanh hoa văn định vị đều sáng', $sepOk);

// Hoa văn định thời xen kẽ
$timingOk = true;
for ($i = 8; $i < $size - 8; $i++) {
    if ($qr->isDark($i, 6) !== ($i % 2 === 0) || $qr->isDark(6, $i) !== ($i % 2 === 0)) {
        $timingOk = false;
    }
}
check('Hoa văn định thời xen kẽ đúng', $timingOk);
check('Module luôn tối tại (8, size-8)', $qr->isDark(8, $size - 8));

// ---------------------------------------------------------------------------
// 4. Giải mã ngược (round-trip)
// ---------------------------------------------------------------------------
section('4. Giải mã ngược ma trận về chuỗi gốc');
$samples = [
    'ngắn' => 'A',
    'URL rút gọn' => 'https://link.dongnaiedu.vn/hoi-thao',
    'URL dài' => 'https://sgddt.dongnai.gov.vn/pages/tin-tuc.aspx?id=12345&loai=thong-bao&nam=2026',
    'tiếng Việt có dấu' => 'Sở Giáo dục và Đào tạo Đồng Nai — Phòng GDPT-GDTX ✅',
    '1 byte biên' => str_repeat('Z', 17),
    '18 byte (đẩy sang V2)' => str_repeat('Z', 18),
    'vừa đủ V10' => str_repeat('k', 271),
    'dữ liệu nhị phân' => implode('', array_map('chr', range(1, 200))),
    'khối dài ~1080 byte' => str_repeat('Đồng Nai 2026! ', 60),
];
$eccNames = ['L', 'M', 'Q', 'H'];
foreach ($samples as $label => $text) {
    foreach ([QrCode::ECC_LOW, QrCode::ECC_MEDIUM, QrCode::ECC_QUARTILE, QrCode::ECC_HIGH] as $ecl) {
        try {
            $code = QrCode::encode($text, $ecl);
            $decoded = decodeMatrix($code->matrix(), $code->version());
            $ok = $decoded['text'] === $text
                && $decoded['ecl'] === $ecl
                && $decoded['mask'] === $code->mask()
                && $decoded['syndromesZero'];
            $detail = '';
            if (!$ok) {
                $detail = $decoded['text'] !== $text
                    ? 'chuỗi giải ra khác chuỗi gốc'
                    : (!$decoded['syndromesZero'] ? 'syndrome Reed–Solomon ≠ 0' : 'sai mức ECC/mask');
            }
            check(sprintf('%s [ECC %s, V%d]', $label, $eccNames[$ecl], $code->version()), $ok, $detail);
        } catch (Throwable $e) {
            check(sprintf('%s [ECC %s]', $label, $eccNames[$ecl]), false, $e->getMessage());
        }
    }
}

// Quét toàn bộ 40 phiên bản ở mức M
section('5. Quét toàn bộ 40 phiên bản (mức M)');
$verErrors = [];
for ($v = 1; $v <= 40; $v++) {
    $text = 'V' . $v . ':' . str_repeat('m', max(1, $v * 3));
    try {
        $code = QrCode::encode($text, QrCode::ECC_MEDIUM, $v);
        $decoded = decodeMatrix($code->matrix(), $code->version());
        if ($decoded['text'] !== $text || !$decoded['syndromesZero']) {
            $verErrors[] = 'V' . $code->version();
        }
    } catch (Throwable $e) {
        $verErrors[] = 'V' . $v . ' (' . $e->getMessage() . ')';
    }
}
check('40/40 phiên bản giải mã đúng', $verErrors === [], implode(', ', $verErrors));

// ---------------------------------------------------------------------------
// 6. Xuất tệp
// ---------------------------------------------------------------------------
section('6. Xuất SVG / PNG');
$svg = $qr->toSvg(8, 4, '#0b1220', '#ffffff', 'Mã QR');
check('SVG có thẻ mở/đóng hợp lệ', str_starts_with($svg, '<?xml') && str_ends_with($svg, '</svg>'));
check('SVG có viewBox đúng kích thước', str_contains($svg, 'viewBox="0 0 ' . ($size + 8) . ' ' . ($size + 8) . '"'));
$png = $qr->toPng(6, 4);
check('PNG có chữ ký tệp đúng', str_starts_with($png, "\x89PNG\r\n\x1a\n"));
$img = @imagecreatefromstring($png);
check('PNG đọc lại được bằng GD', $img !== false);
if ($img !== false) {
    $expected = ($size + 8) * 6;
    check("PNG kích thước {$expected}x{$expected}px", imagesx($img) === $expected && imagesy($img) === $expected);
    imagedestroy($img);
}
check('hexToRgb xử lý đúng dạng #abc và #aabbcc', QrCode::hexToRgb('#f0a') === [255, 0, 170] && QrCode::hexToRgb('1a2b3c') === [26, 43, 60]);

// ---------------------------------------------------------------------------
echo "\n" . str_repeat('─', 60) . "\n";
printf("Kết quả: \033[32m%d đạt\033[0m, %s%d lỗi\033[0m\n", $pass, $fail > 0 ? "\033[31m" : "\033[32m", $fail);
exit($fail > 0 ? 1 : 0);
