<?php
declare(strict_types=1);

/**
 * Lấy tiêu đề trang đích (tuỳ chọn, quản trị viên bật/tắt trong Cài đặt).
 *
 * Có kiểm tra an toàn để hệ thống không bị lợi dụng làm bàn đạp truy cập
 * vào máy chủ nội bộ (SSRF): chỉ cho phép http/https, chặn địa chỉ IP nội
 * bộ, giới hạn thời gian chờ và dung lượng tải về.
 */
final class Meta
{
    private const TIMEOUT_SECONDS = 4;
    private const MAX_BYTES = 65536; // chỉ cần phần đầu trang là đủ lấy <title>

    public static function fetchTitle(string $url): ?string
    {
        if (!self::isSafeUrl($url)) {
            return null;
        }

        $html = self::fetchHead($url);
        if ($html === null) {
            return null;
        }

        if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return null;
        }

        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        // Nếu trang không khai báo UTF-8, chuỗi có thể không hợp lệ — bỏ qua.
        if ($title === '' || !mb_check_encoding($title, 'UTF-8')) {
            return null;
        }

        return mb_substr($title, 0, 200);
    }

    /** Chỉ nhận địa chỉ công khai; chặn localhost và dải IP nội bộ. */
    private static function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        // Phân giải tên miền rồi kiểm tra từng địa chỉ IP thu được.
        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $addresses[] = (string) $record['ip'];
                }
                if (isset($record['ipv6'])) {
                    $addresses[] = (string) $record['ipv6'];
                }
            }
            if ($addresses === []) {
                $resolved = gethostbyname($host);
                if ($resolved !== $host) {
                    $addresses[] = $resolved;
                }
            }
        }
        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $ip) {
            $public = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($public === false) {
                return false;
            }
        }

        return true;
    }

    /** Tải phần đầu của trang; ưu tiên cURL, không có thì dùng stream. */
    private static function fetchHead(string $url): ?string
    {
        $userAgent = 'Mozilla/5.0 (compatible; RutGonLinkDongNai/1.0; +' . base_url() . ')';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_USERAGENT => $userAgent,
                CURLOPT_RANGE => '0-' . (self::MAX_BYTES - 1),
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            return is_string($body) && $body !== '' ? $body : null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header' => "User-Agent: {$userAgent}\r\nRange: bytes=0-" . (self::MAX_BYTES - 1) . "\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $handle = @fopen($url, 'r', false, $context);
        if ($handle === false) {
            return null;
        }
        $body = (string) stream_get_contents($handle, self::MAX_BYTES);
        fclose($handle);

        return $body !== '' ? $body : null;
    }
}
