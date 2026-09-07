<?php
declare(strict_types=1);

/** Trang chủ và luồng rút gọn nhanh. */
final class HomeController
{
    public static function index(): never
    {
        $user = Auth::user();

        // Với khách, hệ thống ghi nhớ các liên kết vừa tạo trong phiên làm việc
        // để họ không bị mất khi rời trang.
        $recent = [];
        if ($user !== null) {
            $result = LinkService::paginate(['user_id' => (int) $user['id'], 'per_page' => 12, 'page' => 1]);
            $recent = array_slice($result['rows'], 0, 4);
        } else {
            foreach (array_reverse((array) ($_SESSION['guest_links'] ?? [])) as $id) {
                $link = LinkService::find((int) $id);
                if ($link !== null) {
                    $recent[] = $link;
                }
                if (count($recent) >= 4) {
                    break;
                }
            }
        }

        view('home', [
            'pageTitle' => null, // trang chủ dùng tiêu đề mặc định
            'user' => $user,
            'summary' => Stats::systemSummary(),
            'recentLinks' => $recent,
        ]);
    }

    public static function shorten(): never
    {
        csrf_verify();
        $user = Auth::user();

        $data = [
            'target_url' => input('target_url'),
            'code' => input('code'),
            'title' => input('title'),
            'tags' => input('tags'),
            'note' => input('note'),
            'password' => input('password'),
            'expires_at' => input('expires_at'),
            'starts_at' => input('starts_at'),
            'max_clicks' => input_int('max_clicks'),
        ];

        $result = LinkService::create($data, $user);

        if (!$result['ok']) {
            keep_input($_POST, $result['errors'] ?? []);
            $first = reset($result['errors']) ?: 'Chưa rút gọn được, vui lòng kiểm tra lại.';
            flash('error', (string) $first);
            redirect('/#rut-gon');
        }

        $link = $result['link'] ?? [];
        $code = (string) $link['code'];

        if ($user === null) {
            $_SESSION['guest_links'][] = (int) $link['id'];
            $_SESSION['guest_links'] = array_slice((array) $_SESSION['guest_links'], -20);
        }
        $_SESSION['_just_created'] = $code;

        redirect('/ket-qua/' . rawurlencode($code));
    }

    /** Trang hiện kết quả: liên kết ngắn, mã QR và các nút chia sẻ. */
    public static function result(array $params): never
    {
        $code = (string) ($params['code'] ?? '');
        $link = LinkService::findByCode($code);
        if ($link === null) {
            abort(404, 'Không tìm thấy liên kết', 'Liên kết này không tồn tại hoặc đã bị xoá.');
        }

        $justCreated = ($_SESSION['_just_created'] ?? null) === (string) $link['code'];
        unset($_SESSION['_just_created']);

        view('result', [
            'pageTitle' => 'Rút gọn thành công',
            'link' => $link,
            'justCreated' => $justCreated,
            'canManage' => LinkService::canManage($link, Auth::user()),
        ]);
    }
}
