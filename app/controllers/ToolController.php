<?php
declare(strict_types=1);

/** Bộ công cụ phụ trợ. */
final class ToolController
{
    /**
     * Trình tạo liên kết có tham số UTM — hữu ích khi cần biết người xem đến
     * từ chiến dịch nào (bài đăng Zalo, email, trang thông báo...).
     */
    public static function utm(): never
    {
        view('tools/utm', [
            'pageTitle' => 'Tạo liên kết có gắn thẻ UTM',
            'user' => Auth::user(),
        ]);
    }
}
