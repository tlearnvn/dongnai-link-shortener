<?php
declare(strict_types=1);

/**
 * Mọi mốc thời gian trong hệ thống đều theo giờ Việt Nam (UTC+7).
 *
 * Lưu ý: hàm CURRENT_TIMESTAMP của SQLite trả về giờ UTC, nên toàn bộ code
 * phải lấy thời gian qua lớp này thay vì để SQLite tự sinh.
 */
final class Clock
{
    private static ?DateTimeZone $tz = null;

    public static function tz(): DateTimeZone
    {
        if (!self::$tz instanceof DateTimeZone) {
            self::$tz = new DateTimeZone((string) Config::get('timezone', 'Asia/Ho_Chi_Minh'));
        }
        return self::$tz;
    }

    public static function nowDt(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::tz());
    }

    /** Thời điểm hiện tại, dạng 'Y-m-d H:i:s' theo UTC+7. */
    public static function now(): string
    {
        return self::nowDt()->format('Y-m-d H:i:s');
    }

    /** Ngày hôm nay, dạng 'Y-m-d' theo UTC+7. */
    public static function today(): string
    {
        return self::nowDt()->format('Y-m-d');
    }

    /** Chuyển chuỗi thời gian đã lưu thành đối tượng ngày (múi giờ UTC+7). */
    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value, self::tz());
        } catch (Exception) {
            return null;
        }
    }

    /** Định dạng để hiển thị, ví dụ: 14:05 07/09/2026. */
    public static function format(?string $value, string $pattern = 'H:i \n\g\à\y d/m/Y'): string
    {
        $dt = self::parse($value);
        return $dt === null ? '—' : $dt->format($pattern);
    }

    public static function formatDate(?string $value): string
    {
        return self::format($value, 'd/m/Y');
    }

    public static function formatShort(?string $value): string
    {
        return self::format($value, 'H:i d/m/Y');
    }

    /** Dạng dùng cho thẻ <input type="datetime-local">. */
    public static function forInput(?string $value): string
    {
        $dt = self::parse($value);
        return $dt === null ? '' : $dt->format('Y-m-d\TH:i');
    }

    /** Chuẩn hoá giá trị từ <input type="datetime-local"> về 'Y-m-d H:i:s'. */
    public static function fromInput(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $dt = self::parse(str_replace('T', ' ', trim($value)));
        return $dt?->format('Y-m-d H:i:s');
    }

    /** Khoảng thời gian tương đối bằng tiếng Việt: "5 phút trước". */
    public static function human(?string $value): string
    {
        $dt = self::parse($value);
        if ($dt === null) {
            return '—';
        }
        $diff = self::nowDt()->getTimestamp() - $dt->getTimestamp();
        $future = $diff < 0;
        $diff = abs($diff);

        $label = match (true) {
            $diff < 10 => 'vài giây',
            $diff < 60 => $diff . ' giây',
            $diff < 3600 => intdiv($diff, 60) . ' phút',
            $diff < 86400 => intdiv($diff, 3600) . ' giờ',
            $diff < 2592000 => intdiv($diff, 86400) . ' ngày',
            $diff < 31536000 => intdiv($diff, 2592000) . ' tháng',
            default => intdiv($diff, 31536000) . ' năm',
        };

        if ($diff < 10 && !$future) {
            return 'vừa xong';
        }
        return $future ? 'sau ' . $label . ' nữa' : $label . ' trước';
    }

    /** Chuỗi ISO 8601 kèm độ lệch +07:00 (dùng cho API và thẻ <time>). */
    public static function iso(?string $value): ?string
    {
        return self::parse($value)?->format('c');
    }

    /** Tên thứ trong tuần bằng tiếng Việt (0 = Chủ nhật). */
    public static function weekdayName(int $weekday): string
    {
        return ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'][$weekday] ?? '?';
    }

    /** Nhãn múi giờ để hiển thị ở chân trang. */
    public static function tzLabel(): string
    {
        return 'GMT+7 (giờ Việt Nam)';
    }
}
