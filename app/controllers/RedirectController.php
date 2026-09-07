<?php
declare(strict_types=1);

/** Chuyển hướng từ mã rút gọn sang địa chỉ gốc, kèm ghi nhận thống kê. */
final class RedirectController
{
    public static function go(array $params): never
    {
        $code = (string) ($params['code'] ?? '');
        $link = LinkService::findByCode($code);

        if ($link === null) {
            http_response_code(404);
            view('not-found', [
                'pageTitle' => 'Không tìm thấy liên kết',
                'code' => $code,
            ]);
        }

        $state = LinkService::state($link);
        if ($state['key'] !== 'active') {
            http_response_code(410);
            view('unavailable', [
                'pageTitle' => 'Liên kết không dùng được',
                'link' => $link,
                'state' => $state,
            ]);
        }

        // Liên kết có mật khẩu: hỏi mật khẩu trước khi chuyển.
        if ($link['password_hash'] !== null && !self::isUnlocked((int) $link['id'])) {
            view('unlock', [
                'pageTitle' => 'Liên kết được bảo vệ',
                'link' => $link,
                'error' => null,
            ]);
        }

        self::sendTo($link);
    }

    /** Nhận mật khẩu của liên kết được bảo vệ. */
    public static function unlock(array $params): never
    {
        csrf_verify();

        $code = (string) ($params['code'] ?? '');
        $link = LinkService::findByCode($code);
        if ($link === null || $link['password_hash'] === null) {
            redirect('/' . rawurlencode($code));
        }

        $state = LinkService::state($link);
        if ($state['key'] !== 'active') {
            http_response_code(410);
            view('unavailable', ['pageTitle' => 'Liên kết không dùng được', 'link' => $link, 'state' => $state]);
        }

        $password = (string) ($_POST['link_password'] ?? '');
        if (!password_verify($password, (string) $link['password_hash'])) {
            // Chờ nhẹ để giảm tốc độ thử mật khẩu tự động.
            usleep(400000);
            view('unlock', [
                'pageTitle' => 'Liên kết được bảo vệ',
                'link' => $link,
                'error' => 'Mật khẩu chưa đúng. Bạn kiểm tra lại nhé!',
            ]);
        }

        $_SESSION['unlocked_links'][(int) $link['id']] = true;
        self::sendTo($link);
    }

    /** Trang xem trước: cho biết liên kết dẫn tới đâu trước khi mở. */
    public static function preview(array $params): never
    {
        $code = (string) ($params['code'] ?? '');
        $link = LinkService::findByCode($code);
        if ($link === null) {
            http_response_code(404);
            view('not-found', ['pageTitle' => 'Không tìm thấy liên kết', 'code' => $code]);
        }

        view('preview', [
            'pageTitle' => 'Xem trước liên kết /' . $link['code'],
            'link' => $link,
            'state' => LinkService::state($link),
            'canManage' => LinkService::canManage($link, Auth::user()),
        ]);
    }

    // ------------------------------------------------------------------ nội bộ

    private static function isUnlocked(int $linkId): bool
    {
        return !empty($_SESSION['unlocked_links'][$linkId]);
    }

    /** Ghi nhận lượt nhấp rồi chuyển hướng. */
    private static function sendTo(array $link): never
    {
        // Tham số ?s=qr do mã QR mang theo, giúp phân biệt lượt quét QR.
        $source = input('s');
        $source = in_array($source, ['qr', 'zalo', 'fb', 'email', 'sms'], true) ? $source : '';

        try {
            LinkService::recordClick($link, $source);
        } catch (Throwable $e) {
            // Không để lỗi thống kê cản người dùng tới đích.
            error_log('[rutgon] Không ghi được lượt nhấp: ' . $e->getMessage());
        }

        $target = (string) $link['target_url'];

        // Chuyển hướng tạm (302) để lần sau trình duyệt vẫn hỏi lại máy chủ,
        // nhờ vậy số liệu thống kê không bị thiếu do bộ đệm trình duyệt.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Referrer-Policy: unsafe-url'); // để trang đích biết nguồn giới thiệu
        header('Location: ' . $target, true, 302);

        // Dự phòng cho trình duyệt cũ không theo header Location.
        echo '<!doctype html><meta charset="utf-8"><title>Đang chuyển…</title>'
            . '<meta http-equiv="refresh" content="0;url=' . e($target) . '">'
            . '<p>Đang chuyển tới <a href="' . e($target) . '">' . e(truncate_str($target, 80)) . '</a>…</p>';
        exit;
    }
}
