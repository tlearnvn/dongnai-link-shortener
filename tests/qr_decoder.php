<?php
declare(strict_types=1);

/**
 * Bộ GIẢI MÃ QR độc lập, chỉ dùng cho kiểm định.
 *
 * Cố ý KHÔNG dùng lại code của app/lib/QrCode.php: bản đồ module chức năng,
 * bảng vị trí hoa văn căn chỉnh và các bảng cấu hình khối ở đây được dựng lại
 * từ mô tả trong chuẩn ISO/IEC 18004. Nhờ vậy nếu bộ mã hoá sai thì test sẽ
 * phát hiện, chứ không phải hai bên cùng sai theo một kiểu rồi triệt tiêu nhau.
 */

/** Vị trí tâm hoa văn căn chỉnh theo bảng E.1 của chuẩn. */
const ALIGN_TABLE = [
    1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
    10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62],
    14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74],
    17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86],
    20 => [6, 34, 62, 90], 21 => [6, 28, 50, 72, 94], 22 => [6, 26, 50, 74, 98],
    23 => [6, 30, 54, 78, 102], 24 => [6, 28, 54, 80, 106], 25 => [6, 32, 58, 84, 110],
    26 => [6, 30, 58, 86, 114], 27 => [6, 34, 62, 90, 118], 28 => [6, 26, 50, 74, 98, 122],
    29 => [6, 30, 54, 78, 102, 126], 30 => [6, 26, 52, 78, 104, 130],
    31 => [6, 30, 56, 82, 108, 134], 32 => [6, 34, 60, 86, 112, 138],
    33 => [6, 30, 58, 86, 114, 142], 34 => [6, 34, 62, 90, 118, 146],
    35 => [6, 30, 54, 78, 102, 126, 150], 36 => [6, 24, 50, 76, 102, 128, 154],
    37 => [6, 28, 54, 80, 106, 132, 158], 38 => [6, 32, 58, 84, 110, 136, 162],
    39 => [6, 26, 54, 82, 110, 138, 166], 40 => [6, 30, 58, 86, 114, 142, 170],
];

/** @return array<int, int> */
function alignPositionsFromTable(int $version): array
{
    return ALIGN_TABLE[$version];
}

/**
 * Bản đồ module chức năng, dựng lại từ mô tả của chuẩn.
 *
 * @return array<int, array<int, bool>>
 */
function functionMap(int $version): array
{
    $size = $version * 4 + 17;
    $map = array_fill(0, $size, array_fill(0, $size, false));

    // Ba hoa văn định vị + dải phân cách: khối 8x8 ở ba góc
    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $map[$y][$x] = true;
            $map[$y][$size - 1 - $x] = true;
            $map[$size - 1 - $y][$x] = true;
        }
    }
    // Hoa văn định thời
    for ($i = 0; $i < $size; $i++) {
        $map[6][$i] = true;
        $map[$i][6] = true;
    }
    // Vùng thông tin định dạng (2 bản sao) + module luôn tối
    for ($i = 0; $i < 9; $i++) {
        $map[8][$i] = true;
        $map[$i][8] = true;
    }
    for ($i = 0; $i < 8; $i++) {
        $map[8][$size - 1 - $i] = true;
        $map[$size - 1 - $i][8] = true;
    }
    // Hoa văn căn chỉnh
    $positions = alignPositionsFromTable($version);
    foreach ($positions as $cy) {
        foreach ($positions as $cx) {
            $isCorner = ($cx === 6 && $cy === 6)
                || ($cx === 6 && $cy === $size - 7)
                || ($cx === $size - 7 && $cy === 6);
            if ($isCorner) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $map[$cy + $dy][$cx + $dx] = true;
                }
            }
        }
    }
    // Vùng thông tin phiên bản
    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $a = $size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $map[$b][$a] = true;
            $map[$a][$b] = true;
        }
    }

    return $map;
}

function maskBit(int $mask, int $x, int $y): bool
{
    return match ($mask) {
        0 => ($x + $y) % 2 === 0,
        1 => $y % 2 === 0,
        2 => $x % 3 === 0,
        3 => ($x + $y) % 3 === 0,
        4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
        5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
        6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
        7 => ((($x + $y) % 2) + ($x * $y) % 3) % 2 === 0,
        default => throw new RuntimeException('mask?'),
    };
}

/** Giải 15 bit thông tin định dạng: trả về [mức ECC, mask] hoặc null nếu sai. */
function decodeFormatBits(int $bits): ?array
{
    $bits ^= 0x5412;
    $best = null;
    $bestDist = 4;
    for ($ecl = 0; $ecl < 4; $ecl++) {
        for ($mask = 0; $mask < 8; $mask++) {
            $data = [1, 0, 3, 2][$ecl] << 3 | $mask;
            $rem = $data;
            for ($i = 0; $i < 10; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
            }
            $candidate = ($data << 10) | $rem;
            $dist = substr_count(decbin($candidate ^ $bits), '1');
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = [$ecl, $mask];
            }
        }
    }
    return $bestDist === 0 ? $best : null;
}

const GF_POLY = 0x11D;

function gfMul(int $x, int $y): int
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * GF_POLY);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z & 0xFF;
}

function gfPow(int $base, int $exp): int
{
    $result = 1;
    for ($i = 0; $i < $exp; $i++) {
        $result = gfMul($result, $base);
    }
    return $result;
}

/**
 * Giải mã ma trận QR về chuỗi gốc.
 * Trả về ['text' => ..., 'ecl' => ..., 'mask' => ..., 'syndromesZero' => bool].
 */
function decodeMatrix(array $modules, int $version): array
{
    $size = $version * 4 + 17;
    $map = functionMap($version);

    // 1. Đọc thông tin định dạng (bản sao 1: cột x=8 và hàng y=8 quanh góc trên–trái)
    $read = static fn(int $x, int $y): int => $modules[$y][$x] ? 1 : 0;
    $bitsList = [];
    for ($i = 0; $i <= 5; $i++) {
        $bitsList[$i] = $read(8, $i);
    }
    $bitsList[6] = $read(8, 7);
    $bitsList[7] = $read(8, 8);
    $bitsList[8] = $read(7, 8);
    for ($i = 9; $i < 15; $i++) {
        $bitsList[$i] = $read(14 - $i, 8);
    }
    $formatBits = 0;
    foreach ($bitsList as $i => $bit) {
        $formatBits |= $bit << $i;
    }
    $format = decodeFormatBits($formatBits);
    if ($format === null) {
        throw new RuntimeException('Thông tin định dạng sai (BCH không khớp).');
    }
    [$ecl, $mask] = $format;

    // Bản sao 2 phải khớp bản sao 1
    $bits2 = [];
    for ($i = 0; $i < 8; $i++) {
        $bits2[$i] = $read($size - 1 - $i, 8);
    }
    for ($i = 8; $i < 15; $i++) {
        $bits2[$i] = $read(8, $size - 15 + $i);
    }
    $formatBits2 = 0;
    foreach ($bits2 as $i => $bit) {
        $formatBits2 |= $bit << $i;
    }
    if ($formatBits2 !== $formatBits) {
        throw new RuntimeException('Hai bản sao thông tin định dạng không khớp.');
    }

    // 2. Bỏ mặt nạ
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (!$map[$y][$x] && maskBit($mask, $x, $y)) {
                $modules[$y][$x] = !$modules[$y][$x];
            }
        }
    }

    // 3. Đọc bit theo đường zigzag (phải -> trái, hai cột một lượt)
    $bitStream = [];
    $col = $size - 1;
    $goingUp = true;
    while ($col > 0) {
        if ($col === 6) {
            $col = 5;
        }
        $rows = $goingUp ? range($size - 1, 0) : range(0, $size - 1);
        foreach ($rows as $y) {
            foreach ([$col, $col - 1] as $x) {
                if (!$map[$y][$x]) {
                    $bitStream[] = $modules[$y][$x] ? 1 : 0;
                }
            }
        }
        $col -= 2;
        $goingUp = !$goingUp;
    }

    $codewords = [];
    $chunks = array_chunk($bitStream, 8);
    foreach ($chunks as $chunk) {
        if (count($chunk) === 8) {
            $byte = 0;
            foreach ($chunk as $bit) {
                $byte = ($byte << 1) | $bit;
            }
            $codewords[] = $byte;
        }
    }

    // 4. Tách đan xen theo cấu hình khối của (phiên bản, mức ECC)
    $eccPerBlock = [
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];
    $numBlocksTable = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];
    $blockEccLen = $eccPerBlock[$ecl][$version];
    $numBlocks = $numBlocksTable[$ecl][$version];
    $rawCodewords = count($codewords);
    $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
    $shortBlockLen = intdiv($rawCodewords, $numBlocks);

    // Khởi tạo khối rỗng rồi rải ngược các từ mã đã đan xen
    $blocks = [];
    for ($b = 0; $b < $numBlocks; $b++) {
        $len = $shortBlockLen + ($b < $numShortBlocks ? 0 : 1);
        $blocks[$b] = array_fill(0, $len, null);
    }
    $idx = 0;
    $maxLen = $shortBlockLen + 1;
    for ($i = 0; $i < $maxLen; $i++) {
        for ($b = 0; $b < $numBlocks; $b++) {
            // Khối ngắn không có ô ở vị trí cuối phần dữ liệu
            if ($i === $shortBlockLen - $blockEccLen && $b < $numShortBlocks) {
                continue;
            }
            // Với khối ngắn, các ô ECC dịch lên một vị trí (không có ô giữ chỗ)
            $target = $i;
            if ($b < $numShortBlocks && $i > $shortBlockLen - $blockEccLen) {
                $target = $i - 1;
            }
            if ($target >= count($blocks[$b])) {
                continue;
            }
            $blocks[$b][$target] = $codewords[$idx++];
        }
    }

    // 5. Kiểm tra syndrome Reed–Solomon của từng khối (phải bằng 0)
    $syndromesZero = true;
    foreach ($blocks as $block) {
        for ($s = 0; $s < $blockEccLen; $s++) {
            $alpha = gfPow(2, $s);
            $sum = 0;
            foreach ($block as $coef) {
                $sum = gfMul($sum, $alpha) ^ (int) $coef;
            }
            if ($sum !== 0) {
                $syndromesZero = false;
            }
        }
    }

    // 6. Ghép phần dữ liệu của các khối rồi giải chuỗi bit
    $data = [];
    foreach ($blocks as $b => $block) {
        $dataLen = count($block) - $blockEccLen;
        for ($i = 0; $i < $dataLen; $i++) {
            $data[] = (int) $block[$i];
        }
    }
    $bitString = '';
    foreach ($data as $byte) {
        $bitString .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
    }
    $mode = substr($bitString, 0, 4);
    if ($mode !== '0100') {
        throw new RuntimeException("Chế độ mã hoá không phải byte mode (nhận: {$mode}).");
    }
    $ccBits = $version <= 9 ? 8 : 16;
    $count = (int) bindec(substr($bitString, 4, $ccBits));
    $payload = substr($bitString, 4 + $ccBits, $count * 8);
    $text = '';
    foreach (str_split($payload, 8) as $chunk) {
        $text .= chr((int) bindec($chunk));
    }

    return ['text' => $text, 'ecl' => $ecl, 'mask' => $mask, 'syndromesZero' => $syndromesZero];
}
