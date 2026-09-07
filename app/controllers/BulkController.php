<?php
declare(strict_types=1);

/** Tạo nhiều liên kết một lượt (dán danh sách địa chỉ). */
final class BulkController
{
    public static function form(): never
    {
        $user = Auth::requireLogin();

        // Kết quả chỉ hiện một lần, tải lại trang là hết.
        $results = $_SESSION['_bulk_results'] ?? null;
        unset($_SESSION['_bulk_results']);

        view('links/bulk', [
            'pageTitle' => 'Tạo hàng loạt',
            'user' => $user,
            'results' => $results,
        ]);
    }

    public static function store(): never
    {
        csrf_verify();
        $user = Auth::requireLogin();

        $raw = (string) ($_POST['links'] ?? '');
        $tags = input('tags');
        $expiresAt = input('expires_at');

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $l): bool => $l !== ''));

        $maxLines = (int) Config::get('bulk_max_lines', 200);
        if ($lines === []) {
            flash('error', 'Bạn chưa dán địa chỉ nào cả.');
            redirect('/tao-hang-loat');
        }
        if (count($lines) > $maxLines) {
            flash('error', "Mỗi lần chỉ xử lý tối đa {$maxLines} dòng. Bạn chia nhỏ ra giúp nhé.");
            redirect('/tao-hang-loat');
        }

        $created = [];
        $failed = [];

        foreach ($lines as $line) {
            // Cú pháp mỗi dòng: "địa-chỉ" hoặc "địa-chỉ | tên-tuỳ-chọn | tiêu đề"
            $parts = array_map('trim', explode('|', $line));
            $url = $parts[0] ?? '';
            $code = $parts[1] ?? '';
            $title = $parts[2] ?? '';

            $result = LinkService::create([
                'target_url' => $url,
                'code' => $code,
                'title' => $title,
                'tags' => $tags,
                'expires_at' => $expiresAt,
            ], $user);

            if ($result['ok']) {
                $created[] = $result['link'];
            } else {
                $failed[] = [
                    'line' => $line,
                    'error' => (string) (reset($result['errors']) ?: 'Không rõ nguyên nhân.'),
                ];
            }
        }

        $_SESSION['_bulk_results'] = ['created' => $created, 'failed' => $failed];

        if ($created !== []) {
            flash('success', 'Đã tạo ' . n(count($created)) . ' liên kết. 🎉');
        }
        if ($failed !== []) {
            flash('error', n(count($failed)) . ' dòng chưa xử lý được, xem chi tiết bên dưới.');
        }

        redirect('/tao-hang-loat');
    }
}
