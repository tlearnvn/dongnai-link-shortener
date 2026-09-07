<?php
declare(strict_types=1);

/** Các trang nội dung tĩnh. */
final class PageController
{
    public static function guide(): never
    {
        view('guide', ['pageTitle' => 'Hướng dẫn sử dụng', 'user' => Auth::user()]);
    }

    public static function about(): never
    {
        view('about', [
            'pageTitle' => 'Giới thiệu',
            'user' => Auth::user(),
            'summary' => Stats::systemSummary(),
        ]);
    }

    public static function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        // Không cho robot lập chỉ mục các trang quản lý và không đi theo
        // liên kết rút gọn (tránh làm sai lệch số liệu thống kê).
        echo "User-agent: *\n";
        echo "Allow: /$\n";
        echo "Allow: /gioi-thieu\n";
        echo "Allow: /huong-dan\n";
        echo "Disallow: /bang-dieu-khien\n";
        echo "Disallow: /lien-ket\n";
        echo "Disallow: /thong-ke\n";
        echo "Disallow: /quan-tri\n";
        echo "Disallow: /tai-khoan\n";
        echo "Disallow: /api\n";
        echo "Disallow: /ma-qr\n";
        echo "Disallow: /ket-qua\n";
        echo "Disallow: /xem\n";
        echo "\nSitemap: " . url('/') . "\n";
        exit;
    }
}
