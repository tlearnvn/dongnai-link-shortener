<?php
declare(strict_types=1);

/**
 * Nhận diện trình duyệt / hệ điều hành / loại thiết bị từ chuỗi User-Agent.
 *
 * Dùng luật đơn giản, không cần cơ sở dữ liệu ngoài. Thứ tự kiểm tra rất
 * quan trọng vì nhiều trình duyệt "khai" thêm tên của trình duyệt khác
 * (ví dụ Edge chứa cả "Chrome" và "Safari").
 */
final class Ua
{
    /** @return array{browser: string, os: string, device: string, is_bot: bool} */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'browser' => self::browser($ua),
            'os' => self::os($ua),
            'device' => self::device($ua),
            'is_bot' => self::isBot($ua),
        ];
    }

    public static function browser(string $ua): string
    {
        $rules = [
            'Zalo' => '/Zalo/i',
            'Facebook' => '/FBAN|FBAV|FB_IAB/i',
            'Messenger' => '/Messenger/i',
            'Edge' => '/Edg[A-Z]?\//i',
            'Opera' => '/OPR\/|Opera/i',
            'Samsung Internet' => '/SamsungBrowser/i',
            'Cốc Cốc' => '/coc_coc_browser/i',
            'Brave' => '/Brave/i',
            'Vivaldi' => '/Vivaldi/i',
            'Yandex' => '/YaBrowser/i',
            'Chrome' => '/Chrome|CriOS/i',
            'Firefox' => '/Firefox|FxiOS/i',
            'Safari' => '/Safari/i',
            'Internet Explorer' => '/MSIE|Trident/i',
        ];
        foreach ($rules as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }
        return $ua === '' ? 'Không rõ' : 'Khác';
    }

    public static function os(string $ua): string
    {
        $rules = [
            'Android' => '/Android/i',
            'iPadOS' => '/iPad/i',
            'iOS' => '/iPhone|iPod/i',
            'Windows' => '/Windows NT|Windows Phone/i',
            'macOS' => '/Macintosh|Mac OS X/i',
            'Chrome OS' => '/CrOS/i',
            'Ubuntu' => '/Ubuntu/i',
            'Linux' => '/Linux|X11/i',
        ];
        foreach ($rules as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }
        return $ua === '' ? 'Không rõ' : 'Khác';
    }

    /** Điện thoại / Máy tính bảng / Máy tính. */
    public static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $ua)) {
            return 'Máy tính bảng';
        }
        if (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini/i', $ua)) {
            return 'Điện thoại';
        }
        if ($ua === '') {
            return 'Không rõ';
        }
        return 'Máy tính';
    }

    public static function isBot(string $ua): bool
    {
        return (bool) preg_match(
            '/bot|crawler|spider|crawl|slurp|facebookexternalhit|preview|scanner|monitor|curl|wget|python-requests|okhttp|headless|lighthouse|pingdom|uptime/i',
            $ua
        );
    }

    /**
     * Quốc gia của khách, lấy từ header do CDN/proxy cung cấp (nếu có).
     * Không có thì suy đoán từ ngôn ngữ trình duyệt.
     */
    public static function country(): string
    {
        foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE'] as $key) {
            $value = strtoupper(trim((string) ($_SERVER[$key] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $value) && $value !== 'XX') {
                return $value;
            }
        }

        $language = self::language();
        if ($language !== '') {
            // vi-VN -> VN, en-US -> US
            if (preg_match('/-([A-Za-z]{2})/', $language, $m)) {
                return strtoupper($m[1]);
            }
            if (str_starts_with(strtolower($language), 'vi')) {
                return 'VN';
            }
        }

        return '';
    }

    /** Ngôn ngữ ưu tiên của trình duyệt, ví dụ "vi-VN". */
    public static function language(): string
    {
        $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($header === '') {
            return '';
        }
        $first = trim(explode(',', $header)[0]);
        $first = explode(';', $first)[0];
        return preg_match('/^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$/', $first) ? $first : '';
    }

    /** Tên quốc gia tiếng Việt cho các mã hay gặp. */
    public static function countryName(string $code): string
    {
        $names = [
            'VN' => 'Việt Nam', 'US' => 'Hoa Kỳ', 'JP' => 'Nhật Bản', 'KR' => 'Hàn Quốc',
            'CN' => 'Trung Quốc', 'TW' => 'Đài Loan', 'SG' => 'Singapore', 'TH' => 'Thái Lan',
            'MY' => 'Malaysia', 'ID' => 'Indonesia', 'PH' => 'Philippines', 'KH' => 'Campuchia',
            'LA' => 'Lào', 'AU' => 'Úc', 'CA' => 'Canada', 'GB' => 'Anh', 'FR' => 'Pháp',
            'DE' => 'Đức', 'RU' => 'Nga', 'IN' => 'Ấn Độ', 'HK' => 'Hồng Kông',
        ];
        if ($code === '') {
            return 'Không xác định';
        }
        return $names[strtoupper($code)] ?? strtoupper($code);
    }

    /** Cờ emoji từ mã quốc gia 2 chữ (để hiển thị cho vui mắt). */
    public static function countryFlag(string $code): string
    {
        if (!preg_match('/^[A-Za-z]{2}$/', $code)) {
            return '🌐';
        }
        $code = strtoupper($code);
        return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
    }
}
