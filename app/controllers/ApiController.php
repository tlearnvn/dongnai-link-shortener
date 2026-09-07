<?php
declare(strict_types=1);

/**
 * API đơn giản cho phần mềm khác gọi vào.
 *
 * Xác thực bằng khoá API của người dùng, truyền qua header
 * `Authorization: Bearer dnl_...` hoặc tham số `token`.
 */
final class ApiController
{
    /** Kiểm tra tên tuỳ chọn còn trống hay không (dùng cho ô nhập ở trang chủ). */
    public static function checkCode(): never
    {
        $code = input('code');

        if ($code === '') {
            json_out(['ok' => false, 'available' => false, 'message' => 'Chưa nhập tên tuỳ chọn.']);
        }

        $error = LinkService::validateCode($code);
        if ($error === null) {
            json_out([
                'ok' => true,
                'available' => true,
                'code' => $code,
                'short_url' => short_url($code),
                'message' => 'Tên này còn trống, dùng được!',
            ]);
        }

        json_out([
            'ok' => true,
            'available' => false,
            'code' => $code,
            'message' => $error,
            'suggestion' => LinkService::suggestCode($code),
        ]);
    }

    public static function shorten(): never
    {
        $user = self::authenticate();

        // Nhận cả JSON và form thường.
        $payload = self::jsonBody();
        $data = [
            'target_url' => (string) ($payload['url'] ?? $payload['target_url'] ?? input('url')),
            'code' => (string) ($payload['code'] ?? $payload['alias'] ?? input('code')),
            'title' => (string) ($payload['title'] ?? input('title')),
            'tags' => (string) ($payload['tags'] ?? input('tags')),
            'note' => (string) ($payload['note'] ?? input('note')),
            'expires_at' => (string) ($payload['expires_at'] ?? input('expires_at')),
            'max_clicks' => (int) ($payload['max_clicks'] ?? input_int('max_clicks')),
            'password' => (string) ($payload['password'] ?? ''),
        ];

        $result = LinkService::create($data, $user);
        if (!$result['ok']) {
            json_out(['ok' => false, 'errors' => $result['errors'] ?? []], 422);
        }

        json_out(['ok' => true, 'link' => self::present($result['link'] ?? [])], 201);
    }

    public static function links(): never
    {
        $user = self::authenticate();

        $result = LinkService::paginate([
            'user_id' => (int) $user['id'],
            'q' => input('q'),
            'status' => input('status'),
            'sort' => input('sort', 'newest'),
            'page' => max(1, input_int('page', 1)),
            'per_page' => input_int('per_page', 24),
        ]);

        json_out([
            'ok' => true,
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'links' => array_map([self::class, 'present'], $result['rows']),
        ]);
    }

    public static function stats(array $params): never
    {
        $user = self::authenticate();

        $link = LinkService::findByCode((string) ($params['code'] ?? ''));
        if ($link === null) {
            json_out(['ok' => false, 'error' => 'Không tìm thấy liên kết.'], 404);
        }
        if (!LinkService::canManage($link, $user)) {
            json_out(['ok' => false, 'error' => 'Bạn không có quyền xem liên kết này.'], 403);
        }

        $linkId = (int) $link['id'];
        $days = input_int('ngay', 30);

        json_out([
            'ok' => true,
            'link' => self::present($link),
            'timezone' => 'UTC+7',
            'daily' => Stats::dailySeries($days, $linkId),
            'hourly' => Stats::hourly($linkId),
            'weekday' => Stats::weekday($linkId),
            'devices' => Stats::breakdown('device', $linkId),
            'browsers' => Stats::breakdown('browser', $linkId),
            'systems' => Stats::breakdown('os', $linkId),
            'referers' => Stats::breakdown('referer_host', $linkId),
            'countries' => Stats::breakdown('country', $linkId),
            'qr_clicks' => Stats::qrClicks($linkId),
            'bot_clicks' => Stats::botClicks($linkId),
        ]);
    }

    public static function docs(): never
    {
        view('api-docs', [
            'pageTitle' => 'Tài liệu API',
            'user' => Auth::user(),
        ]);
    }

    // ------------------------------------------------------------------ nội bộ

    /** Lấy người dùng từ khoá API; không hợp lệ thì trả 401. */
    private static function authenticate(): array
    {
        $token = '';
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            $token = $m[1];
        }
        if ($token === '') {
            $token = input('token');
        }
        if ($token === '') {
            $payload = self::jsonBody();
            $token = (string) ($payload['token'] ?? '');
        }

        // Đang đăng nhập bằng trình duyệt thì cho dùng luôn (tiện thử nghiệm).
        if ($token === '' && Auth::check()) {
            return (array) Auth::user();
        }

        $user = $token === '' ? null : Auth::findByApiToken($token);
        if ($user === null) {
            json_out([
                'ok' => false,
                'error' => 'Thiếu hoặc sai khoá API. Gửi kèm header: Authorization: Bearer <khoá>.',
            ], 401);
        }

        return $user;
    }

    /** @return array<string, mixed> */
    private static function jsonBody(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (!str_contains(strtolower($contentType), 'application/json')) {
            return $cached = [];
        }
        $raw = (string) file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return $cached = is_array($decoded) ? $decoded : [];
    }

    /** Chuẩn hoá dữ liệu liên kết cho API. */
    private static function present(array $link): array
    {
        if ($link === []) {
            return [];
        }
        return [
            'code' => (string) $link['code'],
            'short_url' => short_url((string) $link['code']),
            'target_url' => (string) $link['target_url'],
            'title' => $link['title'] ?? null,
            'tags' => LinkService::tagList($link['tags'] ?? null),
            'clicks' => (int) $link['click_count'],
            'unique_visitors' => (int) $link['unique_count'],
            'state' => LinkService::state($link)['key'],
            'has_password' => $link['password_hash'] !== null,
            'qr_svg' => url('ma-qr/' . rawurlencode((string) $link['code']) . '.svg'),
            'qr_png' => url('ma-qr/' . rawurlencode((string) $link['code']) . '.png'),
            'created_at' => Clock::iso((string) $link['created_at']),
            'expires_at' => Clock::iso($link['expires_at'] ?? null),
            'last_click_at' => Clock::iso($link['last_click_at'] ?? null),
        ];
    }
}
