<?php
declare(strict_types=1);

/** Trang mã QR và xuất tệp ảnh QR (SVG/PNG). */
final class QrController
{
    /** Trang xem, tuỳ chỉnh và tải mã QR. */
    public static function page(array $params): never
    {
        $link = self::resolve((string) ($params['code'] ?? ''));

        view('qr', [
            'pageTitle' => 'Mã QR cho /' . $link['code'],
            'link' => $link,
            'canManage' => LinkService::canManage($link, Auth::user()),
        ]);
    }

    public static function svg(array $params): never
    {
        $link = self::resolve((string) ($params['code'] ?? ''));
        [$scale, $border, $dark, $light, $ecc, $withSource] = self::options($link);

        $qr = QrCode::encode(self::payload($link, $withSource), $ecc);
        $svg = $qr->toSvg($scale, $border, $dark, $light, 'Mã QR ' . short_url((string) $link['code']));

        self::sendFile(
            $svg,
            'image/svg+xml; charset=utf-8',
            'ma-qr-' . self::safeFileName((string) $link['code']) . '.svg',
            input('tai') === '1'
        );
    }

    public static function png(array $params): never
    {
        $link = self::resolve((string) ($params['code'] ?? ''));
        [$scale, $border, $dark, $light, $ecc, $withSource] = self::options($link);

        $qr = QrCode::encode(self::payload($link, $withSource), $ecc);
        $png = $qr->toPng($scale, $border, $dark, $light);

        self::sendFile(
            $png,
            'image/png',
            'ma-qr-' . self::safeFileName((string) $link['code']) . '.png',
            input('tai') === '1'
        );
    }

    // ------------------------------------------------------------------ nội bộ

    private static function resolve(string $code): array
    {
        $link = LinkService::findByCode($code);
        if ($link === null) {
            abort(404, 'Không tìm thấy liên kết', 'Không có liên kết nào ứng với mã này.');
        }
        return $link;
    }

    /**
     * Nội dung nhúng trong mã QR. Thêm ?s=qr để thống kê tách riêng lượt quét.
     */
    private static function payload(array $link, bool $withSource): string
    {
        $url = short_url((string) $link['code']);
        return $withSource ? $url . '?s=qr' : $url;
    }

    /**
     * Đọc tuỳ chọn hiển thị từ tham số truy vấn.
     *
     * @return array{0:int,1:int,2:string,3:string,4:int,5:bool}
     */
    private static function options(array $link): array
    {
        $scale = max(2, min(30, input_int('co', 10)));
        $border = max(0, min(8, input_int('le', 4)));

        $dark = self::color(input('mau'), (string) $link['qr_dark']);
        $light = input('nen') === 'trong' ? 'none' : self::color(input('nen'), (string) $link['qr_light']);

        $ecc = input_int('sua-loi', Settings::int('default_ecc', 1));
        $ecc = max(0, min(3, $ecc));

        $withSource = input('nguon') !== '0';

        return [$scale, $border, $dark, $light, $ecc, $withSource];
    }

    /** Chỉ nhận mã màu 6 chữ số hex để tránh chèn nội dung lạ vào SVG. */
    private static function color(string $value, string $default): string
    {
        $value = trim($value);
        if ($value !== '' && !str_starts_with($value, '#')) {
            $value = '#' . $value;
        }
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $default;
    }

    private static function safeFileName(string $code): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $code) ?: 'qr';
    }

    private static function sendFile(string $body, string $contentType, string $fileName, bool $download): never
    {
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: public, max-age=86400');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $fileName . '"');
        echo $body;
        exit;
    }
}
