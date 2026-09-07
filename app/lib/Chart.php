<?php
declare(strict_types=1);

/**
 * Vẽ biểu đồ bằng SVG ngay trên máy chủ.
 *
 * Không dùng thư viện JavaScript nào: biểu đồ hiện ngay khi trang tải xong,
 * in ra giấy vẫn đúng, và tự đổi màu theo giao diện sáng/tối nhờ dùng biến
 * CSS (var(--...)) thay vì mã màu cứng.
 */
final class Chart
{
    /**
     * Biểu đồ đường có tô nền cho chuỗi số liệu theo ngày.
     *
     * @param array<int, array{label: string, clicks: int, unique: int, date?: string}> $series
     */
    public static function area(array $series, string $title = ''): string
    {
        if ($series === []) {
            return self::emptyState('Chưa có dữ liệu để vẽ biểu đồ.');
        }

        $width = 820.0;
        $height = 280.0;
        $padLeft = 46.0;
        $padRight = 14.0;
        $padTop = 18.0;
        $padBottom = 34.0;
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;

        $max = 0;
        foreach ($series as $point) {
            $max = max($max, (int) $point['clicks']);
        }
        $steps = 5;
        $niceMax = self::niceScale($max, $steps);
        $count = count($series);

        $x = static function (int $i) use ($padLeft, $plotWidth, $count): float {
            return $count === 1 ? $padLeft + $plotWidth / 2 : $padLeft + $plotWidth * $i / ($count - 1);
        };
        $y = static function (float $value) use ($padTop, $plotHeight, $niceMax): float {
            return $padTop + $plotHeight - ($niceMax > 0 ? $plotHeight * $value / $niceMax : 0);
        };

        $svg = '<svg class="chart chart--area" viewBox="0 0 ' . $width . ' ' . $height
            . '" preserveAspectRatio="none" role="img" aria-label="'
            . e($title !== '' ? $title : 'Biểu đồ lượt nhấp theo ngày') . '">';

        $svg .= '<defs>
            <linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="var(--chart-line)" stop-opacity="0.38"/>
                <stop offset="100%" stop-color="var(--chart-line)" stop-opacity="0.02"/>
            </linearGradient>
        </defs>';

        // Lưới ngang + nhãn trục dọc
        for ($i = 0; $i <= $steps; $i++) {
            $value = $niceMax * $i / $steps;
            $yPos = $y($value);
            $svg .= sprintf(
                '<line class="chart__grid" x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f"/>',
                $padLeft,
                $yPos,
                $width - $padRight,
                $yPos
            );
            $svg .= sprintf(
                '<text class="chart__axis" x="%.1f" y="%.1f" text-anchor="end">%s</text>',
                $padLeft - 8,
                $yPos + 4,
                e(self::compact((int) round($value)))
            );
        }

        // Vùng tô và đường nối
        $areaPath = '';
        $linePath = '';
        $uniquePath = '';
        foreach ($series as $i => $point) {
            $px = $x($i);
            $py = $y((float) $point['clicks']);
            $pu = $y((float) $point['unique']);
            $linePath .= ($i === 0 ? 'M' : 'L') . sprintf('%.1f %.1f', $px, $py);
            $uniquePath .= ($i === 0 ? 'M' : 'L') . sprintf('%.1f %.1f', $px, $pu);
            $areaPath .= ($i === 0 ? 'M' : 'L') . sprintf('%.1f %.1f', $px, $py);
        }
        $areaPath .= sprintf('L%.1f %.1fL%.1f %.1fZ', $x($count - 1), $y(0), $x(0), $y(0));

        $svg .= '<path class="chart__area" d="' . $areaPath . '" fill="url(#chartFill)"/>';
        $svg .= '<path class="chart__line" d="' . $linePath . '"/>';
        if ($max > 0) {
            $svg .= '<path class="chart__line chart__line--unique" d="' . $uniquePath . '"/>';
        }

        // Điểm dữ liệu + chú thích khi trỏ chuột
        $labelStep = (int) max(1, ceil($count / 12));
        foreach ($series as $i => $point) {
            $px = $x($i);
            $py = $y((float) $point['clicks']);
            $tooltip = sprintf(
                'Ngày %s: %s lượt nhấp (%s khách riêng)',
                $point['label'],
                n((int) $point['clicks']),
                n((int) $point['unique'])
            );
            $svg .= sprintf(
                '<g class="chart__point"><circle cx="%.1f" cy="%.1f" r="3.5"/>'
                . '<circle class="chart__hit" cx="%.1f" cy="%.1f" r="12"><title>%s</title></circle></g>',
                $px,
                $py,
                $px,
                $py,
                e($tooltip)
            );

            if ($i % $labelStep === 0 || $i === $count - 1) {
                $svg .= sprintf(
                    '<text class="chart__axis" x="%.1f" y="%.1f" text-anchor="middle">%s</text>',
                    $px,
                    $height - 12,
                    e($point['label'])
                );
            }
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Biểu đồ cột dọc (dùng cho phân bố theo giờ, theo thứ).
     *
     * @param array<int, array{label: string, value: int}> $data
     */
    public static function bars(array $data, string $unit = 'lượt'): string
    {
        if ($data === []) {
            return self::emptyState('Chưa có dữ liệu.');
        }

        $max = 0;
        foreach ($data as $item) {
            $max = max($max, (int) $item['value']);
        }

        $count = count($data);
        $width = 820.0;
        $height = 220.0;
        $padTop = 14.0;
        $padBottom = 28.0;
        $padSide = 10.0;
        $plotHeight = $height - $padTop - $padBottom;
        $slot = ($width - $padSide * 2) / $count;
        $barWidth = min(38.0, $slot * 0.62);

        $svg = '<svg class="chart chart--bars" viewBox="0 0 ' . $width . ' ' . $height
            . '" preserveAspectRatio="none" role="img">';

        foreach ($data as $i => $item) {
            $value = (int) $item['value'];
            $barHeight = $max > 0 ? $plotHeight * $value / $max : 0;
            $barHeight = $value > 0 ? max(2.0, $barHeight) : 0.0;
            $cx = $padSide + $slot * $i + $slot / 2;
            $bx = $cx - $barWidth / 2;
            $by = $padTop + $plotHeight - $barHeight;

            $svg .= sprintf(
                '<rect class="chart__bar%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="4">'
                . '<title>%s: %s %s</title></rect>',
                $value === $max && $max > 0 ? ' chart__bar--peak' : '',
                $bx,
                $by,
                $barWidth,
                $barHeight,
                e($item['label']),
                e(n($value)),
                e($unit)
            );
            // Nhãn trục ngang: bỏ bớt khi quá dày
            if ($count <= 24 || $i % 2 === 0) {
                $svg .= sprintf(
                    '<text class="chart__axis" x="%.1f" y="%.1f" text-anchor="middle">%s</text>',
                    $cx,
                    $height - 10,
                    e($item['label'])
                );
            }
        }

        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Biểu đồ vành khuyên.
     *
     * @param array<int, array{label: string, value: int, percent?: float}> $data
     */
    public static function donut(array $data, string $centerLabel = ''): string
    {
        $total = 0;
        foreach ($data as $item) {
            $total += (int) $item['value'];
        }
        if ($total === 0) {
            return self::emptyState('Chưa có dữ liệu.');
        }

        $radius = 60.0;
        $circumference = 2 * M_PI * $radius;
        $offset = 0.0;

        $svg = '<div class="donut">';
        $svg .= '<svg viewBox="0 0 160 160" class="donut__svg" role="img">';
        $svg .= '<circle class="donut__track" cx="80" cy="80" r="' . $radius . '" fill="none" stroke-width="20"/>';

        foreach (array_slice($data, 0, 6) as $i => $item) {
            $value = (int) $item['value'];
            $portion = $value / $total;
            $length = $circumference * $portion;
            $svg .= sprintf(
                '<circle class="donut__slice donut__slice--%d" cx="80" cy="80" r="%.2f" fill="none"'
                . ' stroke-width="20" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f"'
                . ' transform="rotate(-90 80 80)" stroke-linecap="butt"><title>%s: %s (%s%%)</title></circle>',
                $i + 1,
                $radius,
                $length,
                $circumference - $length,
                -$offset,
                e($item['label']),
                e(n($value)),
                e(number_format($portion * 100, 1, ',', '.'))
            );
            $offset += $length;
        }

        $svg .= sprintf(
            '<text class="donut__total" x="80" y="76" text-anchor="middle">%s</text>'
            . '<text class="donut__caption" x="80" y="95" text-anchor="middle">%s</text>',
            e(self::compact($total)),
            e($centerLabel)
        );
        $svg .= '</svg>';

        $svg .= '<ul class="donut__legend">';
        foreach (array_slice($data, 0, 6) as $i => $item) {
            $svg .= sprintf(
                '<li><span class="donut__dot donut__dot--%d"></span><span class="donut__name">%s</span>'
                . '<span class="donut__value">%s</span></li>',
                $i + 1,
                e($item['label']),
                e(number_format((int) $item['value'] * 100 / $total, 1, ',', '.') . '%')
            );
        }
        $svg .= '</ul></div>';

        return $svg;
    }

    /**
     * Danh sách xếp hạng dạng thanh ngang (HTML thuần, nhẹ và dễ đọc).
     *
     * @param array<int, array{label: string, value: int, percent: float}> $data
     */
    public static function ranking(array $data, string $emptyMessage = 'Chưa có dữ liệu.', ?callable $decorator = null): string
    {
        if ($data === []) {
            return self::emptyState($emptyMessage);
        }

        $max = 0;
        foreach ($data as $item) {
            $max = max($max, (int) $item['value']);
        }

        $html = '<ul class="ranking">';
        foreach ($data as $item) {
            $width = $max > 0 ? (int) round((int) $item['value'] * 100 / $max) : 0;
            $label = $decorator !== null ? (string) $decorator($item['label']) : e($item['label']);
            $html .= sprintf(
                '<li class="ranking__item"><div class="ranking__head"><span class="ranking__label">%s</span>'
                . '<span class="ranking__value">%s <small>%s%%</small></span></div>'
                . '<div class="ranking__track"><span class="ranking__fill" style="width:%d%%"></span></div></li>',
                $label,
                e(n((int) $item['value'])),
                e(number_format((float) $item['percent'], 1, ',', '.')),
                $width
            );
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Đường biểu diễn nhỏ gọn, nhúng trong thẻ liên kết.
     *
     * @param array<int, int> $values
     */
    public static function sparkline(array $values): string
    {
        if ($values === [] || max($values) === 0) {
            return '<svg class="sparkline" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true">'
                . '<line class="sparkline__flat" x1="0" y1="26" x2="100" y2="26"/></svg>';
        }

        $max = max($values);
        $count = count($values);
        $path = '';
        $area = '';
        foreach ($values as $i => $value) {
            $px = $count === 1 ? 50.0 : 100.0 * $i / ($count - 1);
            $py = 26.0 - 24.0 * ($value / $max);
            $path .= ($i === 0 ? 'M' : 'L') . sprintf('%.1f %.1f', $px, $py);
        }
        $area = $path . sprintf('L100 28L0 28Z');

        return '<svg class="sparkline" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true">'
            . '<path class="sparkline__area" d="' . $area . '"/>'
            . '<path class="sparkline__line" d="' . $path . '"/></svg>';
    }

    /** Vòng tròn thể hiện tỉ lệ (dùng cho hạn dùng, giới hạn lượt nhấp). */
    public static function gauge(float $percent, string $label = ''): string
    {
        $percent = max(0.0, min(100.0, $percent));
        $radius = 26.0;
        $circumference = 2 * M_PI * $radius;
        $filled = $circumference * $percent / 100;

        $tone = match (true) {
            $percent >= 90 => 'danger',
            $percent >= 70 => 'warning',
            default => 'success',
        };

        return sprintf(
            '<div class="gauge gauge--%s"><svg viewBox="0 0 64 64" aria-hidden="true">'
            . '<circle class="gauge__track" cx="32" cy="32" r="%.1f" fill="none" stroke-width="7"/>'
            . '<circle class="gauge__value" cx="32" cy="32" r="%.1f" fill="none" stroke-width="7"'
            . ' stroke-dasharray="%.2f %.2f" transform="rotate(-90 32 32)" stroke-linecap="round"/>'
            . '<text class="gauge__text" x="32" y="36" text-anchor="middle">%d%%</text></svg>'
            . '<span class="gauge__label">%s</span></div>',
            $tone,
            $radius,
            $radius,
            $filled,
            max(0.0, $circumference - $filled),
            (int) round($percent),
            e($label)
        );
    }

    // ------------------------------------------------------------------ nội bộ

    private static function emptyState(string $message): string
    {
        return '<p class="chart-empty">' . e($message) . '</p>';
    }

    /**
     * Chọn giá trị lớn nhất của trục dọc sao cho chia đều cho $steps mà mỗi
     * vạch vẫn là số nguyên "đẹp" (0, 5, 10, 15… thay vì 0, 6, 13, 19…).
     */
    private static function niceScale(int $max, int $steps): int
    {
        if ($max <= 0) {
            return $steps;
        }

        // Bước nhỏ nhất cần có để phủ hết dữ liệu
        $rawStep = (int) ceil($max / $steps);
        $magnitude = 10 ** (int) floor(log10(max(1, $rawStep)));

        // Các hệ số đều là số nguyên nên mọi vạch trục cũng là số nguyên;
        // danh sách đủ dày để trục không bị thừa khoảng trống quá nhiều.
        foreach ([1, 2, 3, 4, 5, 6, 8, 10] as $factor) {
            $step = (int) ($factor * $magnitude);
            if ($step >= $rawStep) {
                return $step * $steps;
            }
        }

        return (int) ($magnitude * 10) * $steps;
    }

    /** Số gọn cho nhãn trục: 1200 -> 1,2N */
    private static function compact(int $value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1, ',', '.') . 'Tr';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 1, ',', '.') . 'N';
        }
        return (string) $value;
    }
}
