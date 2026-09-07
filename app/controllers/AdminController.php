<?php
declare(strict_types=1);

/** Khu vực quản trị: người dùng, liên kết, cài đặt, nhật ký, bảo trì. */
final class AdminController
{
    public static function index(): never
    {
        $admin = Auth::requireAdmin();

        view('admin/index', [
            'pageTitle' => 'Quản trị hệ thống',
            'user' => $admin,
            'summary' => Stats::systemSummary(),
            'overview' => Stats::overview(null),
            'series' => Stats::dailySeries(30, null, null),
            'topLinks' => Stats::topLinks(null, 8),
            'recentAudit' => self::auditRows(8),
            'topUsers' => self::topUsers(6),
        ]);
    }

    public static function users(): never
    {
        $admin = Auth::requireAdmin();

        $q = input('q');
        $sql = 'SELECT u.*,
                       (SELECT COUNT(*) FROM links l WHERE l.user_id = u.id) AS link_count,
                       (SELECT IFNULL(SUM(l.click_count), 0) FROM links l WHERE l.user_id = u.id) AS click_count
                FROM users u';
        $params = [];
        if ($q !== '') {
            // Mỗi cột một tên tham số riêng (MySQL không cho dùng lặp một tên).
            $sql .= " WHERE u.username LIKE :q_username
                         OR IFNULL(u.full_name, '') LIKE :q_full_name
                         OR IFNULL(u.email, '') LIKE :q_email";
            $like = '%' . $q . '%';
            foreach ([':q_username', ':q_full_name', ':q_email'] as $name) {
                $params[$name] = $like;
            }
        }
        $sql .= ' ORDER BY u.created_at DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        view('admin/users', [
            'pageTitle' => 'Quản lý người dùng',
            'user' => $admin,
            'users' => $stmt->fetchAll(),
            'q' => $q,
        ]);
    }

    public static function updateUser(array $params): never
    {
        csrf_verify();
        $admin = Auth::requireAdmin();

        $id = (int) $params['id'];
        $target = Auth::findById($id);
        if ($target === null) {
            abort(404, 'Không tìm thấy người dùng', 'Tài khoản này không còn tồn tại.');
        }

        $action = input('action');

        // Không cho tự hạ quyền / tự khoá để tránh mất lối vào khu quản trị.
        if ($id === (int) $admin['id'] && in_array($action, ['demote', 'suspend', 'delete'], true)) {
            flash('error', 'Bạn không thể tự thay đổi quyền hoặc tự khoá tài khoản của mình.');
            redirect('/quan-tri/nguoi-dung');
        }

        $pdo = Database::pdo();
        switch ($action) {
            case 'promote':
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = :id")->execute([':id' => $id]);
                $message = 'Đã cấp quyền quản trị cho ' . $target['username'] . '.';
                break;

            case 'demote':
                if (self::adminCount() <= 1) {
                    flash('error', 'Hệ thống phải còn ít nhất một quản trị viên.');
                    redirect('/quan-tri/nguoi-dung');
                }
                $pdo->prepare("UPDATE users SET role = 'user' WHERE id = :id")->execute([':id' => $id]);
                $message = 'Đã chuyển ' . $target['username'] . ' về quyền người dùng thường.';
                break;

            case 'suspend':
                $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = :id")->execute([':id' => $id]);
                Auth::revokeRememberTokens($id);
                $message = 'Đã tạm ngưng tài khoản ' . $target['username'] . '.';
                break;

            case 'activate':
                $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute([':id' => $id]);
                $message = 'Đã mở lại tài khoản ' . $target['username'] . '.';
                break;

            case 'reset_password':
                $newPassword = Auth::generateRecoveryCode();
                Auth::changePassword($id, $newPassword);
                $_SESSION['_admin_reset'] = ['username' => (string) $target['username'], 'password' => $newPassword];
                $message = 'Đã đặt lại mật khẩu cho ' . $target['username'] . '.';
                break;

            case 'quota':
                $quota = max(0, input_int('link_quota'));
                $pdo->prepare('UPDATE users SET link_quota = :q WHERE id = :id')
                    ->execute([':q' => $quota, ':id' => $id]);
                $message = $quota > 0
                    ? 'Đã đặt hạn mức ' . n($quota) . ' liên kết cho ' . $target['username'] . '.'
                    : 'Đã bỏ hạn mức liên kết của ' . $target['username'] . '.';
                break;

            case 'delete':
                if ($target['role'] === 'admin' && self::adminCount() <= 1) {
                    flash('error', 'Không thể xoá quản trị viên duy nhất của hệ thống.');
                    redirect('/quan-tri/nguoi-dung');
                }
                // Liên kết của người này được giữ lại (user_id thành NULL) để
                // các địa chỉ đã phát ra ngoài không bị hỏng.
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
                $message = 'Đã xoá tài khoản ' . $target['username'] . '. Các liên kết cũ vẫn hoạt động.';
                break;

            default:
                flash('error', 'Thao tác không hợp lệ.');
                redirect('/quan-tri/nguoi-dung');
        }

        Auth::log($admin, 'admin_user_' . $action, (string) $target['username']);
        flash('success', $message);
        redirect('/quan-tri/nguoi-dung');
    }

    public static function links(): never
    {
        $admin = Auth::requireAdmin();

        $filters = [
            'q' => input('q'),
            'tag' => input('tag'),
            'status' => input('status'),
            'sort' => input('sort', 'newest'),
            'page' => max(1, input_int('page', 1)),
            'per_page' => input_int('per_page', 24),
        ];
        $result = LinkService::paginate($filters);

        view('admin/links', [
            'pageTitle' => 'Toàn bộ liên kết',
            'user' => $admin,
            'result' => $result,
            'filters' => $filters,
            'tags' => LinkService::tagCounts(null),
        ]);
    }

    public static function settings(): never
    {
        $admin = Auth::requireAdmin();
        view('admin/settings', [
            'pageTitle' => 'Cài đặt hệ thống',
            'user' => $admin,
            'settings' => Settings::all(),
            'summary' => Stats::systemSummary(),
        ]);
    }

    public static function saveSettings(): never
    {
        csrf_verify();
        $admin = Auth::requireAdmin();

        foreach (['allow_registration', 'allow_guest_shorten', 'require_login_for_stats', 'auto_title', 'block_open_redirect'] as $key) {
            Settings::set($key, input_bool($key) ? '1' : '0');
        }

        $ecc = input_int('default_ecc', 1);
        Settings::set('default_ecc', (string) max(0, min(3, $ecc)));
        Settings::set('announcement', mb_substr(input('announcement'), 0, 300));

        Auth::log($admin, 'admin_settings', 'Cập nhật cài đặt hệ thống');
        flash('success', 'Đã lưu cài đặt hệ thống.');
        redirect('/quan-tri/cai-dat');
    }

    public static function auditLog(): never
    {
        $admin = Auth::requireAdmin();

        $page = max(1, input_int('page', 1));
        $perPage = 40;
        $total = (int) Database::pdo()->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        view('admin/audit', [
            'pageTitle' => 'Nhật ký hoạt động',
            'user' => $admin,
            'rows' => self::auditRows($perPage, ($page - 1) * $perPage),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public static function maintenance(): never
    {
        csrf_verify();
        $admin = Auth::requireAdmin();

        switch (input('action')) {
            case 'prune_clicks':
                $days = max(1, input_int('days', 365));
                $deleted = Stats::pruneClicks($days);
                flash('success', 'Đã xoá ' . n($deleted) . ' bản ghi lượt nhấp cũ hơn ' . n($days) . ' ngày.');
                break;

            case 'clear_audit':
                Database::pdo()->exec('DELETE FROM audit_log');
                flash('success', 'Đã xoá nhật ký hoạt động.');
                break;

            case 'clear_attempts':
                Database::pdo()->exec('DELETE FROM login_attempts');
                flash('success', 'Đã xoá lịch sử đăng nhập sai (mở khoá mọi địa chỉ IP đang bị chặn).');
                break;

            case 'vacuum':
                Database::vacuum();
                flash('success', 'Đã dồn nén tệp cơ sở dữ liệu. Dung lượng hiện tại: ' . bytes_human(Database::fileSize()) . '.');
                break;

            case 'delete_expired':
                $stmt = Database::pdo()->prepare(
                    'DELETE FROM links WHERE expires_at IS NOT NULL AND expires_at <= :now'
                );
                $stmt->execute([':now' => Clock::now()]);
                flash('success', 'Đã xoá ' . n($stmt->rowCount()) . ' liên kết đã hết hạn.');
                break;

            default:
                flash('error', 'Thao tác bảo trì không hợp lệ.');
        }

        Auth::log($admin, 'admin_maintenance', input('action'));
        redirect('/quan-tri/cai-dat');
    }

    // ------------------------------------------------------------------ nội bộ

    /** @return array<int, array<string, mixed>> */
    private static function auditRows(int $limit, int $offset = 0): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM audit_log ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(200, $limit))
            . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    private static function topUsers(int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.username, u.full_name, COUNT(l.id) AS link_count,
                    IFNULL(SUM(l.click_count), 0) AS click_count
             FROM users u LEFT JOIN links l ON l.user_id = u.id
             GROUP BY u.id ORDER BY click_count DESC, link_count DESC LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private static function adminCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    }
}
