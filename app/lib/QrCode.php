<?php
declare(strict_types=1);

/**
 * Bộ tạo mã QR thuần PHP (không cần thư viện ngoài).
 *
 * Hỗ trợ chế độ byte (UTF-8), phiên bản 1–40, 4 mức sửa lỗi L/M/Q/H.
 * Thuật toán theo chuẩn ISO/IEC 18004: mã hoá dữ liệu -> chia khối ->
 * sinh mã sửa lỗi Reed–Solomon trên GF(256) -> đan xen -> vẽ ma trận ->
 * chọn mặt nạ (mask) có điểm phạt thấp nhất.
 *
 * Xuất ra: ma trận boolean, SVG, hoặc PNG (qua GD).
 */
final class QrCode
{
    public const ECC_LOW = 0;      // ~7%
    public const ECC_MEDIUM = 1;   // ~15%
    public const ECC_QUARTILE = 2; // ~25%
    public const ECC_HIGH = 3;     // ~30%

    /** Số bit định dạng của từng mức sửa lỗi (dùng khi vẽ format info). */
    private const ECC_FORMAT_BITS = [1, 0, 3, 2];

    /** Số từ mã sửa lỗi cho mỗi khối, theo [mức ECC][phiên bản]. */
    private const ECC_CODEWORDS_PER_BLOCK = [
        // Chỉ số 0 là ô đệm (không dùng), phiên bản tính từ 1
        [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Số khối sửa lỗi, theo [mức ECC][phiên bản]. */
    private const NUM_ECC_BLOCKS = [
        [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private int $version;
    private int $ecc;
    private int $size;
    private int $mask;
    /** @var array<int, array<int, bool>> */
    private array $modules = [];
    /** @var array<int, array<int, bool>> */
    private array $isFunction = [];

    private function __construct(int $version, int $ecc, int $size, int $mask)
    {
        $this->version = $version;
        $this->ecc = $ecc;
        $this->size = $size;
        $this->mask = $mask;
    }

    /** Tạo mã QR từ một chuỗi văn bản (mã hoá byte/UTF-8). */
    public static function encode(string $text, int $ecc = self::ECC_MEDIUM, int $minVersion = 1): self
    {
        if ($ecc < 0 || $ecc > 3) {
            throw new InvalidArgumentException('Mức sửa lỗi không hợp lệ.');
        }
        $minVersion = max(1, min(40, $minVersion));
        $bytes = $text === '' ? [] : array_values(unpack('C*', $text) ?: []);
        $length = count($bytes);

        // Chọn phiên bản nhỏ nhất chứa đủ dữ liệu.
        $version = 0;
        for ($v = $minVersion; $v <= 40; $v++) {
            $capacityBits = self::numDataCodewords($v, $ecc) * 8;
            $neededBits = 4 + self::charCountBits($v) + 8 * $length;
            if ($neededBits <= $capacityBits) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new RuntimeException('Nội dung quá dài để tạo mã QR.');
        }

        $qr = new self($version, $ecc, $version * 4 + 17, -1);
        $codewords = $qr->buildCodewords($bytes, $length);
        $qr->initMatrix();
        $qr->drawFunctionPatterns();
        $qr->drawCodewords($codewords);
        $qr->selectBestMask();

        return $qr;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function mask(): int
    {
        return $this->mask;
    }

    public function eccLevel(): int
    {
        return $this->ecc;
    }

    public function isDark(int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            return false;
        }
        return $this->modules[$y][$x];
    }

    /** @return array<int, array<int, bool>> */
    public function matrix(): array
    {
        return $this->modules;
    }

    // ---------------------------------------------------------------- mã hoá

    /** Số bit của trường "số lượng ký tự" ở chế độ byte. */
    private static function charCountBits(int $version): int
    {
        return $version <= 9 ? 8 : 16;
    }

    /** Tổng số module dữ liệu (chưa trừ ECC) của một phiên bản. */
    private static function numRawDataModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    /** Số từ mã dữ liệu khả dụng của (phiên bản, mức ECC). */
    private static function numDataCodewords(int $version, int $ecc): int
    {
        return intdiv(self::numRawDataModules($version), 8)
            - self::ECC_CODEWORDS_PER_BLOCK[$ecc][$version] * self::NUM_ECC_BLOCKS[$ecc][$version];
    }

    /**
     * Dựng chuỗi bit dữ liệu, chèn đệm rồi thêm mã sửa lỗi và đan xen khối.
     *
     * @param array<int, int> $bytes
     * @return array<int, int>
     */
    private function buildCodewords(array $bytes, int $length): array
    {
        $bits = '0100'; // chỉ thị chế độ byte
        $bits .= str_pad(decbin($length), self::charCountBits($this->version), '0', STR_PAD_LEFT);
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = self::numDataCodewords($this->version, $this->ecc) * 8;
        // Ký hiệu kết thúc (tối đa 4 bit 0)
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        // Đệm cho tròn byte
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);

        $data = [];
        foreach (str_split($bits, 8) as $chunk) {
            $data[] = (int) bindec($chunk);
        }
        // Byte đệm luân phiên 0xEC / 0x11
        $padBytes = [0xEC, 0x11];
        $i = 0;
        $target = intdiv($capacityBits, 8);
        while (count($data) < $target) {
            $data[] = $padBytes[$i % 2];
            $i++;
        }

        return $this->addEccAndInterleave($data);
    }

    /**
     * Chia dữ liệu thành khối, tính ECC cho từng khối rồi đan xen theo chuẩn.
     *
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private function addEccAndInterleave(array $data): array
    {
        $version = $this->version;
        $ecc = $this->ecc;
        $numBlocks = self::NUM_ECC_BLOCKS[$ecc][$version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$ecc][$version];
        $rawCodewords = intdiv(self::numRawDataModules($version), 8);
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $divisor = self::rsComputeDivisor($blockEccLen);
        $blocks = [];
        $offset = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $dat = array_slice($data, $offset, $datLen);
            $offset += $datLen;
            $block = $dat;
            if ($i < $numShortBlocks) {
                $block[] = 0; // ô giữ chỗ để mọi khối cùng độ dài khi đan xen
            }
            foreach (self::rsComputeRemainder($dat, $divisor) as $eccByte) {
                $block[] = $eccByte;
            }
            $blocks[] = $block;
        }

        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                // Bỏ qua ô giữ chỗ của các khối ngắn
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }

    /** @return array<int, int> Đa thức sinh Reed–Solomon bậc $degree. */
    private static function rsComputeDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }
        return $result;
    }

    /**
     * @param array<int, int> $data
     * @param array<int, int> $divisor
     * @return array<int, int>
     */
    private static function rsComputeRemainder(array $data, array $divisor): array
    {
        $degree = count($divisor);
        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ (int) array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMultiply($coef, $factor);
            }
        }
        return $result;
    }

    /** Phép nhân trên trường Galois GF(2^8), đa thức tối giản 0x11D. */
    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    // ------------------------------------------------------------------- vẽ

    private function initMatrix(): void
    {
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);
    }

    private function setFunctionModule(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        // Hoa văn định thời (timing pattern): hàng 6 và cột 6
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        // 3 hoa văn định vị (finder pattern) ở 3 góc
        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        // Hoa văn căn chỉnh (alignment pattern)
        $positions = $this->alignmentPatternPositions();
        $n = count($positions);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $skipCorner = ($i === 0 && $j === 0)
                    || ($i === 0 && $j === $n - 1)
                    || ($i === $n - 1 && $j === 0);
                if (!$skipCorner) {
                    $this->drawAlignmentPattern($positions[$i], $positions[$j]);
                }
            }
        }

        $this->drawFormatBits(0); // tạm, sẽ vẽ lại sau khi chọn mask
        $this->drawVersionBits();
    }

    private function drawFinderPattern(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy)); // khoảng cách Chebyshev
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunctionModule($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignmentPattern(int $x, int $y): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return array<int, int> */
    private function alignmentPatternPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $numAlign = intdiv($this->version, 7) + 2;
        $step = $this->version === 32
            ? 26
            : (int) (ceil(($this->version * 4 + 4) / ($numAlign * 2 - 2)) * 2);

        $result = [];
        for ($pos = $this->size - 7; count($result) < $numAlign - 1; $pos -= $step) {
            array_unshift($result, $pos);
        }
        array_unshift($result, 6);
        return $result;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = self::ECC_FORMAT_BITS[$this->ecc] << 3 | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412; // 15 bit mã BCH

        // Bản sao thứ nhất (quanh finder trên–trái)
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, self::getBit($bits, $i));
        }
        $this->setFunctionModule(8, 7, self::getBit($bits, 6));
        $this->setFunctionModule(8, 8, self::getBit($bits, 7));
        $this->setFunctionModule(7, 8, self::getBit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, self::getBit($bits, $i));
        }

        // Bản sao thứ hai
        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, self::getBit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, self::getBit($bits, $i));
        }
        $this->setFunctionModule(8, $this->size - 8, true); // module luôn tối
    }

    private function drawVersionBits(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = $this->version << 12 | $rem; // 18 bit

        for ($i = 0; $i < 18; $i++) {
            $dark = self::getBit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $dark);
            $this->setFunctionModule($b, $a, $dark);
        }
    }

    /**
     * Rải từ mã lên ma trận theo đường zigzag hai cột, từ phải sang trái.
     *
     * @param array<int, int> $codewords
     */
    private function drawCodewords(array $codewords): void
    {
        $i = 0; // vị trí bit
        $total = count($codewords) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // nhảy qua cột định thời
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = self::getBit($codewords[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                    default => throw new InvalidArgumentException('Mask không hợp lệ.'),
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Thử cả 8 mặt nạ, giữ lại mặt nạ có điểm phạt nhỏ nhất. */
    private function selectBestMask(): void
    {
        $bestMask = 0;
        $minPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->drawFormatBits($mask);
            $this->applyMask($mask);
            $penalty = $this->penaltyScore();
            if ($penalty < $minPenalty) {
                $minPenalty = $penalty;
                $bestMask = $mask;
            }
            $this->applyMask($mask); // hoàn tác (XOR hai lần)
        }
        $this->mask = $bestMask;
        $this->drawFormatBits($bestMask);
        $this->applyMask($bestMask);
    }

    /** Điểm phạt theo 4 quy tắc của chuẩn (càng nhỏ càng dễ quét). */
    private function penaltyScore(): int
    {
        $result = 0;
        $size = $this->size;

        // Quy tắc 1 + 3: dãy ≥5 module cùng màu, và hoa văn giống finder — theo hàng
        for ($y = 0; $y < $size; $y++) {
            $runColor = false;
            $runLength = 0;
            $runHistory = array_fill(0, 7, 0);
            for ($x = 0; $x < $size; $x++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runLength++;
                    if ($runLength === 5) {
                        $result += 3;
                    } elseif ($runLength > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runLength, $runHistory);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($runHistory) * 40;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runLength = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runLength, $runHistory) * 40;
        }
        // ... và theo cột
        for ($x = 0; $x < $size; $x++) {
            $runColor = false;
            $runLength = 0;
            $runHistory = array_fill(0, 7, 0);
            for ($y = 0; $y < $size; $y++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runLength++;
                    if ($runLength === 5) {
                        $result += 3;
                    } elseif ($runLength > 5) {
                        $result++;
                    }
                } else {
                    $this->finderPenaltyAddHistory($runLength, $runHistory);
                    if (!$runColor) {
                        $result += $this->finderPenaltyCountPatterns($runHistory) * 40;
                    }
                    $runColor = $this->modules[$y][$x];
                    $runLength = 1;
                }
            }
            $result += $this->finderPenaltyTerminateAndCount($runColor, $runLength, $runHistory) * 40;
        }

        // Quy tắc 2: khối 2x2 cùng màu
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $color = $this->modules[$y][$x];
                if (
                    $color === $this->modules[$y][$x + 1]
                    && $color === $this->modules[$y + 1][$x]
                    && $color === $this->modules[$y + 1][$x + 1]
                ) {
                    $result += 3;
                }
            }
        }

        // Quy tắc 4: tỉ lệ module tối lệch khỏi 50%
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $cell) {
                if ($cell) {
                    $dark++;
                }
            }
        }
        $total = $size * $size;
        $k = (int) (ceil(abs($dark * 20 - $total * 10) / $total) - 1);
        $result += $k * 10;

        return $result;
    }

    /** @param array<int, int> $runHistory */
    private function finderPenaltyAddHistory(int $currentRunLength, array &$runHistory): void
    {
        if ($runHistory[0] === 0) {
            $currentRunLength += $this->size; // lề trắng ảo bên ngoài mã
        }
        array_pop($runHistory);
        array_unshift($runHistory, $currentRunLength);
    }

    /**
     * Đếm hoa văn giống finder (tỉ lệ 1:1:3:1:1) — quy tắc phạt số 3.
     *
     * @param array<int, int> $runHistory
     */
    private function finderPenaltyCountPatterns(array $runHistory): int
    {
        $n = $runHistory[1];
        $core = $n > 0
            && $runHistory[2] === $n
            && $runHistory[3] === $n * 3
            && $runHistory[4] === $n
            && $runHistory[5] === $n;

        $count = 0;
        if ($core && $runHistory[0] >= $n * 4 && $runHistory[6] >= $n) {
            $count++;
        }
        if ($core && $runHistory[6] >= $n * 4 && $runHistory[0] >= $n) {
            $count++;
        }
        return $count;
    }

    /** @param array<int, int> $runHistory */
    private function finderPenaltyTerminateAndCount(bool $currentRunColor, int $currentRunLength, array &$runHistory): int
    {
        if ($currentRunColor) { // dãy tối kết thúc ở biên
            $this->finderPenaltyAddHistory($currentRunLength, $runHistory);
            $currentRunLength = 0;
        }
        $currentRunLength += $this->size; // lề trắng ảo
        $this->finderPenaltyAddHistory($currentRunLength, $runHistory);
        return $this->finderPenaltyCountPatterns($runHistory);
    }

    private static function getBit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) !== 0;
    }

    // --------------------------------------------------------------- xuất ra

    /** Xuất SVG (nét sắc ở mọi kích thước, gộp các module vào một path). */
    public function toSvg(int $scale = 8, int $border = 4, string $dark = '#0b1220', string $light = '#ffffff', string $title = ''): string
    {
        $scale = max(1, $scale);
        $border = max(0, $border);
        $dimension = ($this->size + $border * 2) * $scale;

        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $path .= sprintf('M%d %dh1v1h-1z', $x + $border, $y + $border);
                }
            }
        }

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %2$d %2$d" shape-rendering="crispEdges" role="img">',
            $dimension,
            $this->size + $border * 2
        );
        if ($title !== '') {
            $svg .= '<title>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</title>';
        }
        if ($light !== '' && $light !== 'none') {
            $svg .= sprintf('<rect width="100%%" height="100%%" fill="%s"/>', htmlspecialchars($light, ENT_QUOTES, 'UTF-8'));
        }
        $svg .= sprintf('<path d="%s" fill="%s"/>', $path, htmlspecialchars($dark, ENT_QUOTES, 'UTF-8'));
        $svg .= '</svg>';

        return $svg;
    }

    /** Xuất PNG (chuỗi nhị phân) qua thư viện GD. */
    public function toPng(int $scale = 10, int $border = 4, string $dark = '#0b1220', string $light = '#ffffff'): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Máy chủ chưa bật thư viện GD nên không xuất được PNG.');
        }
        $scale = max(1, min(40, $scale));
        $border = max(0, $border);
        $dimension = ($this->size + $border * 2) * $scale;

        $image = imagecreatetruecolor($dimension, $dimension);
        [$dr, $dg, $db] = self::hexToRgb($dark);
        [$lr, $lg, $lb] = self::hexToRgb($light);
        $lightColor = (int) imagecolorallocate($image, $lr, $lg, $lb);
        $darkColor = (int) imagecolorallocate($image, $dr, $dg, $db);
        imagefilledrectangle($image, 0, 0, $dimension - 1, $dimension - 1, $lightColor);

        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $x0 = ($x + $border) * $scale;
                    $y0 = ($y + $border) * $scale;
                    imagefilledrectangle($image, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $darkColor);
                }
            }
        }

        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-fA-F]{3}$/', $hex)) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
