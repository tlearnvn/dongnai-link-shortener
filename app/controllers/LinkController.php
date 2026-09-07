<?php
declare(strict_types=1);

/** Quản lý danh sách liên kết của người dùng. */
final class LinkController
{
    /** Bộ lọc lấy từ tham số truy vấn của trang danh sách. */
    private static function filtersFromRequest(array $user, bool $allUsers = false): array
    {
        $filters = [
            'q' => input('q'),
            'tag' => input('tag'),
            'status' => input('status'),
            'sort' => input('sort', 'newest'),
            'starred' => input('starred') === '1',
            'page' => max(1, input_int('page', 1)),
            'per_page' => input_int('per_page', (int) Config::get('per_page', 12)),
        ];
        if (!$allUsers) {
            $filters['user_id'] = (int) $user['id'];
        }
        return $filters;
    }

    public static function index(): never
    {
        $user = Auth::requireLogin();
        $filters = self::filtersFromRequest($user);
        $result = LinkService::paginate($filters);

        view('links/index', [
            'pageTitle' => 'Liên kết của tôi',
            'user' => $user,
            'result' => $result,
            'filters' => $filters,
            'tags' => LinkService::tagCounts((int) $user['id']),
        ]);
    }

    public static function createForm(): never
    {
        $user = Auth::requireLogin();
        view('links/form', [
            'pageTitle' => 'Tạo liên kết mới',
            'user' => $user,
            'link' => null,
            'tags' => LinkService::tagCounts((int) $user['id']),
        ]);
    }

    public static function store(): never
    {
        csrf_verify();
        $user = Auth::requireLogin();

        $result = LinkService::create(self::formData(), $user);
        if (!$result['ok']) {
            keep_input($_POST, $result['errors'] ?? []);
            flash('error', (string) (reset($result['errors']) ?: 'Không tạo được liên kết.'));
            redirect('/lien-ket/tao');
        }

        $link = $result['link'] ?? [];
        $_SESSION['_just_created'] = (string) $link['code'];
        flash('success', 'Đã tạo liên kết ' . short_url_display((string) $link['code']) . ' 🎉');
        redirect('/ket-qua/' . rawurlencode((string) $link['code']));
    }

    public static function editForm(array $params): never
    {
        $user = Auth::requireLogin();
        $link = self::findOwned((int) $params['id'], $user);

        view('links/form', [
            'pageTitle' => 'Sửa liên kết /' . $link['code'],
            'user' => $user,
            'link' => $link,
            'tags' => LinkService::tagCounts($user['role'] === 'admin' ? null : (int) $user['id']),
        ]);
    }

    public static function update(array $params): never
    {
        csrf_verify();
        $user = Auth::requireLogin();
        $link = self::findOwned((int) $params['id'], $user);

        $data = self::formData();
        $data['is_active'] = input_bool('is_active') ? '1' : '0';
        $data['remove_password'] = input_bool('remove_password') ? '1' : '0';

        $result = LinkService::update($link, $data, $user);
        if (!$result['ok']) {
            keep_input($_POST, $result['errors'] ?? []);
            flash('error', (string) (reset($result['errors']) ?: 'Không lưu được thay đổi.'));
            redirect('/lien-ket/' . (int) $link['id'] . '/sua');
        }

        flash('success', 'Đã lưu thay đổi cho liên kết.');
        redirect('/thong-ke/' . (int) $link['id']);
    }

    public static function destroy(array $params): never
    {
        csrf_verify();
        $user = Auth::requireLogin();
        $link = self::findOwned((int) $params['id'], $user);

        LinkService::delete((int) $link['id'], $user);
        flash('success', 'Đã xoá liên kết /' . $link['code'] . ' cùng toàn bộ số liệu của nó.');
        redirect('/lien-ket');
    }

    public static function toggle(array $params): never
    {
        csrf_verify();
        $user = Auth::requireLogin();
        $link = self::findOwned((int) $params['id'], $user);

        $active = LinkService::toggleActive((int) $link['id'], $user);
        flash('success', $active
            ? 'Đã bật lại liên kết /' . $link['code']
            : 'Đã tạm dừng liên kết /' . $link['code']);
        redirect_back('/lien-ket');
    }

    public static function star(array $params): never
    {
        csrf_verify();
        $user = Auth::requireLogin();
        $link = self::findOwned((int) $params['id'], $user);

        $starred = LinkService::toggleStar((int) $link['id']);
        flash('success', $starred ? 'Đã ghim liên kết vào mục quan trọng ⭐' : 'Đã bỏ ghim liên kết.');
        redirect_back('/lien-ket');
    }

    public static function resetStats(array $params): never
    {
        csrf_verify();
        $user = Auth::requireLogin();
        $link = self::findOwned((int) $params['id'], $user);

        LinkService::resetStats((int) $link['id'], $user);
        flash('success', 'Đã xoá toàn bộ số liệu thống kê của liên kết này.');
        redirect('/thong-ke/' . (int) $link['id']);
    }

    /** Xuất danh sách đang lọc ra tệp CSV. */
    public static function exportCsv(): never
    {
        $user = Auth::requireLogin();
        $isAdminScope = $user['role'] === 'admin' && input('pham-vi') === 'tat-ca';

        $filters = self::filtersFromRequest($user, $isAdminScope);
        $filters['per_page'] = 96;
        $filters['page'] = 1;

        // Lấy toàn bộ kết quả khớp bộ lọc, không giới hạn theo trang.
        $rows = [];
        do {
            $result = LinkService::paginate($filters);
            $rows = array_merge($rows, $result['rows']);
            $filters['page']++;
        } while ($filters['page'] <= $result['pages'] && count($rows) < 20000);

        $csv = LinkService::toCsv($rows);
        $fileName = 'lien-ket-rut-gon-' . Clock::nowDt()->format('Ymd-Hi') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    // ------------------------------------------------------------------ nội bộ

    /** @return array<string, mixed> */
    private static function formData(): array
    {
        return [
            'target_url' => input('target_url'),
            'code' => input('code'),
            'title' => input('title'),
            'note' => input('note'),
            'tags' => input('tags'),
            'password' => (string) ($_POST['password'] ?? ''),
            'starts_at' => input('starts_at'),
            'expires_at' => input('expires_at'),
            'max_clicks' => input_int('max_clicks'),
            'qr_dark' => input('qr_dark'),
            'qr_light' => input('qr_light'),
        ];
    }

    /** Lấy liên kết và kiểm tra quyền, không có quyền thì dừng. */
    private static function findOwned(int $id, array $user): array
    {
        $link = LinkService::find($id);
        if ($link === null) {
            abort(404, 'Không tìm thấy liên kết', 'Liên kết này không tồn tại hoặc đã bị xoá.');
        }
        if (!LinkService::canManage($link, $user)) {
            abort(403, 'Không có quyền', 'Bạn chỉ có thể quản lý những liên kết do mình tạo.');
        }
        return $link;
    }
}
