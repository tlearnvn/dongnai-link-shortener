<?php
declare(strict_types=1);

/**
 * Kiểm định ảnh QR do máy chủ trả về có quét được hay không.
 *
 * Khác với tests/qr_test.php (kiểm tra ma trận trong bộ nhớ), test này đi qua
 * đúng đường mà máy quét thật đi: gọi HTTP tới /ma-qr/{ten}.png, đọc từng
 * điểm ảnh của tệp PNG, dựng lại ma trận rồi giải mã ngược về địa chỉ.
 *
 * Chạy: php tests/qr_image_test.php [http://127.0.0.1:8765] [ten-tuy-chon]
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

/**
 * Đọc ảnh PNG rồi dựng lại ma trận module.
 *
 * @return array{matrix: array<int, array<int, bool>>, version: int}
 */
function matrixFromPng(string $png): array
{
    $image = imagecreatefromstring($png);
    if ($image === false) {
        throw new RuntimeException('Không mở được ảnh PNG.');
    }
    $width = imagesx($image);

    // Tìm ô module đầu tiên: quét đường chéo để đo bề dày lề trắng và cỡ ô.
    $isDark = static function (int $x, int $y) use ($image): bool {
        $rgb = imagecolorat($image, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        // Độ sáng theo cảm nhận của mắt
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 128;
    };

    // Lề trắng: số điểm ảnh sáng liên tiếp trên đường chéo.
    // Lề bằng 0 là hợp lệ (tham số le=0) — lúc đó điểm (0,0) đã là ô tối.
    $border = 0;
    while ($border < $width && !$isDark($border, $border)) {
        $border++;
    }
    if ($border >= $width) {
        throw new RuntimeException('Ảnh không có ô tối nào — không phải mã QR.');
    }

    // Cỡ một ô: bề dày của vạch tối đầu tiên trong hoa văn định vị,
    // vốn dày đúng 7 ô, nên chia 7 là ra cỡ ô.
    $x = $border;
    while ($x < $width && $isDark($x, $border)) {
        $x++;
    }
    $finderWidth = $x - $border;
    if ($finderWidth % 7 !== 0) {
        throw new RuntimeException("Bề dày hoa văn định vị ({$finderWidth}px) không chia hết cho 7.");
    }
    $scale = intdiv($finderWidth, 7);

    $size = intdiv($width - 2 * $border, $scale);
    if (($size - 17) % 4 !== 0) {
        throw new RuntimeException("Số ô suy ra ({$size}) không hợp lệ với chuẩn QR.");
    }

    $matrix = [];
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            // Lấy màu ở tâm mỗi ô cho chắc
            $px = $border + $col * $scale + intdiv($scale, 2);
            $py = $border + $row * $scale + intdiv($scale, 2);
            $matrix[$row][$col] = $isDark($px, $py);
        }
    }
    imagedestroy($image);

    return ['matrix' => $matrix, 'version' => intdiv($size - 17, 4)];
}

// ---------------------------------------------------------------------------

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8765', '/');
$code = $argv[2] ?? null;

echo "\033[1mKiểm định ảnh mã QR do máy chủ trả về\033[0m — {$base}\n\n";

// Nếu không truyền tên, tự tạo một liên kết mới bằng cách gọi trang chủ.
if ($code === null) {
    $ch = curl_init($base . '/');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => '/tmp/qrimg-jar.txt',
        CURLOPT_COOKIEFILE => '/tmp/qrimg-jar.txt', CURLOPT_TIMEOUT => 15]);
    $home = (string) curl_exec($ch);
    curl_close($ch);

    if (!preg_match('/name="_token" value="([a-f0-9]+)"/', $home, $m)) {
        exit("Không lấy được mã CSRF từ trang chủ — máy chủ đã chạy chưa?\n");
    }
    $code = 'kiem-dinh-qr-' . bin2hex(random_bytes(3));

    $ch = curl_init($base . '/rut-gon');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            '_token' => $m[1],
            'target_url' => 'https://sgddt.dongnai.gov.vn/kiem-dinh-ma-qr',
            'code' => $code,
        ]),
        CURLOPT_COOKIEJAR => '/tmp/qrimg-jar.txt',
        CURLOPT_COOKIEFILE => '/tmp/qrimg-jar.txt',
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    curl_close($ch);
    @unlink('/tmp/qrimg-jar.txt');
}

$variants = [
    'mặc định' => '',
    'ô nhỏ nhất (4px)' => '?co=4',
    'ô lớn (20px)' => '?co=20',
    'không lề' => '?le=0',
    'lề dày (8 ô)' => '?le=8',
    'màu đỏ trên nền kem' => '?mau=%23b91c1c&nen=%23fff7ed',
    'mức sửa lỗi H' => '?sua-loi=3',
    'mức sửa lỗi L' => '?sua-loi=0',
    'không gắn dấu nguồn' => '?nguon=0',
];

$expectedBase = $base . '/' . $code;

foreach ($variants as $label => $query) {
    $url = $base . '/ma-qr/' . rawurlencode($code) . '.png' . $query;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $png = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 200) {
        check("PNG {$label}", false, "máy chủ trả về {$status}");
        continue;
    }

    try {
        $parsed = matrixFromPng($png);
        $decoded = decodeMatrix($parsed['matrix'], $parsed['version']);

        $expected = str_contains($query, 'nguon=0') ? $expectedBase : $expectedBase . '?s=qr';
        $ok = $decoded['text'] === $expected && $decoded['syndromesZero'];

        check(
            sprintf('PNG %s → giải ra đúng địa chỉ (V%d)', $label, $parsed['version']),
            $ok,
            $ok ? '' : 'giải ra: ' . $decoded['text']
        );
    } catch (Throwable $e) {
        check("PNG {$label}", false, $e->getMessage());
    }
}

// SVG: kiểm tra đúng số ô tối bằng cách đếm lệnh vẽ trong path
$ch = curl_init($base . '/ma-qr/' . rawurlencode($code) . '.svg');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
$svg = (string) curl_exec($ch);
curl_close($ch);

$svgDark = substr_count($svg, 'h1v1h-1z');
$reference = QrCode::encode($expectedBase . '?s=qr', QrCode::ECC_MEDIUM);
$referenceDark = 0;
foreach ($reference->matrix() as $row) {
    foreach ($row as $cell) {
        if ($cell) {
            $referenceDark++;
        }
    }
}
check(
    'SVG có đúng số ô tối như ma trận gốc',
    $svgDark === $referenceDark,
    "SVG {$svgDark} ô, ma trận {$referenceDark} ô"
);

echo "\n" . str_repeat('─', 60) . "\n";
printf("Kết quả: \033[32m%d đạt\033[0m, %s%d lỗi\033[0m\n", $pass, $fail > 0 ? "\033[31m" : "\033[32m", $fail);
exit($fail > 0 ? 1 : 0);
