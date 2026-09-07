<?php
declare(strict_types=1);

/** Nghiệp vụ chính: tạo, sửa, tra cứu liên kết rút gọn và ghi nhận lượt nhấp. */
final class LinkService
{
    /**
     * Những mã không được dùng làm tên tuỳ chọn vì trùng đường dẫn hệ thống.
     * (So sánh không phân biệt chữ hoa/thường.)
     */
    public const RESERVED = [
        'admin', 'api', 'app', 'assets', 'bang-dieu-khien', 'cai-dat', 'chinh-sach',
        'cong-cu', 'dang-ky', 'dang-nhap', 'dang-xuat', 'data', 'doi-mat-khau',
        'favicon.ico', 'gioi-thieu', 'huong-dan', 'index', 'index.php', 'lien-ket',
        'ma-qr', 'quan-tri', 'quen-mat-khau', 'robots.txt', 'sitemap.xml', 'static',
        'tai-khoan', 'tai-lieu', 'tao-hang-loat', 'thong-ke', 'tim-kiem', 'trang-chu',
        'xem', 'xuat-csv', 'login', 'logout', 'register', 'dashboard', 'stats', 'qr',
        'links', 'settings', 'account', 'about', 'help', 'null', 'undefined',
    ];

    // ------------------------------------------------------- kiểm tra mã rút gọn

    /** Kiểm tra tên tuỳ chọn; trả về null nếu hợp lệ, hoặc lời nhắc lỗi. */
    public static function validateCode(string $code): ?string
    {
        $min = (int) Config::get('code_min_length', 3);
        $max = (int) Config::get('code_max_length', 64);
        $length = strlen($code);

        if ($length < $min || $length > $max) {
            return "Tên tuỳ chọn cần dài từ {$min} đến {$max} ký tự.";
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*[A-Za-z0-9]$/', $code) && !preg_match('/^[A-Za-z0-9]$/', $code)) {
            return 'Tên tuỳ chọn chỉ gồm chữ không dấu, số, dấu gạch ngang, gạch dưới và dấu chấm; phải bắt đầu và kết thúc bằng chữ hoặc số.';
        }
        if (preg_match('/[._-]{2,}/', $code)) {
            return 'Tên tuỳ chọn không nên có hai dấu đặc biệt liền nhau.';
        }
        if (in_array(strtolower($code), self::RESERVED, true)) {
            return 'Tên này đang được hệ thống sử dụng, bạn chọn tên khác nhé.';
        }
        if (!self::isCodeAvailable($code)) {
            return 'Tên tuỳ chọn này đã có người dùng rồi. Bạn thử tên khác xem sao!';
        }
        return null;
    }

    public static function isCodeAvailable(string $code, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM links WHERE code = :code';
        $params = [':code' => $code];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $exceptId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() === 0
            && !in_array(strtolower($code), self::RESERVED, true);
    }

    /** Sinh mã tự động chưa bị trùng. */
    public static function generateUniqueCode(): string
    {
        $length = (int) Config::get('code_length', 6);
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = random_code($length);
            if (self::isCodeAvailable($code)) {
                return $code;
            }
            // Trùng nhiều lần thì nới dần độ dài cho chắc.
            if ($attempt > 0 && $attempt % 4 === 0) {
                $length++;
            }
        }
        return random_code($length + 4);
    }

    /** Gợi ý tên tuỳ chọn còn trống dựa trên một chuỗi mong muốn. */
    public static function suggestCode(string $desired): string
    {
        $base = slugify($desired);
        if ($base === '') {
            return self::generateUniqueCode();
        }
        $base = substr($base, 0, 40);
        if (self::validateCode($base) === null) {
            return $base;
        }
        for ($i = 2; $i <= 40; $i++) {
            $candidate = $base . '-' . $i;
            if (self::validateCode($candidate) === null) {
                return $candidate;
            }
        }
        return $base . '-' . random_code(4);
    }

    // ----------------------------------------------------------- kiểm tra địa chỉ

    /** Chuẩn hoá địa chỉ: thêm https:// nếu người dùng gõ thiếu. */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/\s+/', '', $url) ?? $url;
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $url)) {
            // Bỏ qua trường hợp người dùng gõ "//example.com"
            $url = 'https://' . ltrim($url, '/');
        }
        return $url;
    }

    /** Kiểm tra địa chỉ đích; trả về null nếu hợp lệ. */
    public static function validateUrl(string $url): ?string
    {
        $maxLength = (int) Config::get('max_url_length', 2000);

        if ($url === '') {
            return 'Bạn chưa nhập địa chỉ cần rút gọn.';
        }
        if (strlen($url) > $maxLength) {
            return "Địa chỉ quá dài (tối đa {$maxLength} ký tự).";
        }

        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?: ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'Chỉ hỗ trợ địa chỉ bắt đầu bằng http:// hoặc https://';
        }
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === '' || !str_contains($host, '.')) {
            return 'Địa chỉ chưa có tên miền hợp lệ.';
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'Địa chỉ không đúng định dạng.';
        }

        // Chặn tự trỏ vào chính hệ thống để khỏi tạo vòng lặp chuyển hướng.
        if (Settings::bool('block_open_redirect', true)) {
            $ownBase = rtrim(base_url(), '/');
            $ownHost = host_of($ownBase);
            if ($ownHost !== '' && strcasecmp(host_of($url), $ownHost) === 0) {
                $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
                $basePath = (string) (parse_url($ownBase, PHP_URL_PATH) ?: '');
                if ($basePath === '' || str_starts_with($path, $basePath)) {
                    return 'Không thể rút gọn một địa chỉ nằm trong chính hệ thống này (sẽ tạo vòng lặp).';
                }
            }
        }

        return null;
    }

    // -------------------------------------------------------------------- tạo mới

    /**
     * Tạo liên kết mới.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, link?: array<string, mixed>, errors?: array<string, string>}
     */
    public static function create(array $data, ?array $user = null): array
    {
        $errors = [];

        $url = self::normalizeUrl((string) ($data['target_url'] ?? ''));
        $urlError = self::validateUrl($url);
        if ($urlError !== null) {
            $errors['target_url'] = $urlError;
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '') {
            $codeError = self::validateCode($code);
            if ($codeError !== null) {
                $errors['code'] = $codeError;
            }
        }

        $expiresAt = Clock::fromInput((string) ($data['expires_at'] ?? ''));
        $startsAt = Clock::fromInput((string) ($data['starts_at'] ?? ''));
        if ($expiresAt !== null && $expiresAt <= Clock::now()) {
            $errors['expires_at'] = 'Thời điểm hết hạn phải nằm ở tương lai.';
        }
        if ($expiresAt !== null && $startsAt !== null && $startsAt >= $expiresAt) {
            $errors['starts_at'] = 'Thời điểm bắt đầu phải trước thời điểm hết hạn.';
        }

        $maxClicks = (int) ($data['max_clicks'] ?? 0);
        if ($maxClicks < 0) {
            $errors['max_clicks'] = 'Giới hạn lượt nhấp không thể là số âm.';
        }

        $password = (string) ($data['password'] ?? '');
        if ($password !== '' && mb_strlen($password) < 4) {
            $errors['password'] = 'Mật khẩu bảo vệ cần ít nhất 4 ký tự.';
        }

        if ($user === null && !Settings::bool('allow_guest_shorten', true)) {
            $errors['target_url'] = 'Hệ thống đang yêu cầu đăng nhập trước khi rút gọn liên kết.';
        }
        if ($user === null && self::guestLimitReached()) {
            $limit = (int) Config::get('guest_hourly_limit', 10);
            $errors['target_url'] = "Khách chỉ tạo được {$limit} liên kết mỗi giờ. Vui lòng đăng nhập để dùng không giới hạn.";
        }
        if ($user !== null && (int) $user['link_quota'] > 0) {
            $used = self::countForUser((int) $user['id']);
            if ($used >= (int) $user['link_quota']) {
                $errors['target_url'] = 'Bạn đã dùng hết số liên kết được cấp (' . n((int) $user['link_quota']) . ').';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        if ($code === '') {
            $code = self::generateUniqueCode();
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' && Settings::bool('auto_title', false)) {
            $title = (string) Meta::fetchTitle($url);
        }

        $now = Clock::now();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO links (code, target_url, title, note, tags, user_id, creator_ip, password_hash,
                                starts_at, expires_at, max_clicks, is_active, qr_dark, qr_light, created_at)
             VALUES (:code, :url, :title, :note, :tags, :user, :ip, :pass,
                     :starts, :expires, :max, 1, :dark, :light, :created)'
        );
        $stmt->execute([
            ':code' => $code,
            ':url' => $url,
            ':title' => $title !== '' ? mb_substr($title, 0, 200) : null,
            ':note' => self::cleanText($data['note'] ?? null, 500),
            ':tags' => self::normalizeTags((string) ($data['tags'] ?? '')),
            ':user' => $user === null ? null : (int) $user['id'],
            ':ip' => client_ip(),
            ':pass' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            ':starts' => $startsAt,
            ':expires' => $expiresAt,
            ':max' => $maxClicks > 0 ? $maxClicks : null,
            ':dark' => self::cleanColor($data['qr_dark'] ?? null, '#0b1220'),
            ':light' => self::cleanColor($data['qr_light'] ?? null, '#ffffff'),
            ':created' => $now,
        ]);

        $link = self::find((int) Database::pdo()->lastInsertId());
        if ($link === null) {
            return ['ok' => false, 'errors' => ['target_url' => 'Không lưu được liên kết, vui lòng thử lại.']];
        }

        Auth::log($user, 'link_create', $code . ' → ' . truncate_str($url, 120));

        return ['ok' => true, 'link' => $link];
    }

    /**
     * Cập nhật liên kết đã có.
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, errors?: array<string, string>}
     */
    public static function update(array $link, array $data, ?array $user = null): array
    {
        $errors = [];
        $id = (int) $link['id'];

        $url = self::normalizeUrl((string) ($data['target_url'] ?? ''));
        $urlError = self::validateUrl($url);
        if ($urlError !== null) {
            $errors['target_url'] = $urlError;
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = (string) $link['code'];
        }
        if (strcasecmp($code, (string) $link['code']) !== 0) {
            $min = (int) Config::get('code_min_length', 3);
            $max = (int) Config::get('code_max_length', 64);
            if (strlen($code) < $min || strlen($code) > $max) {
                $errors['code'] = "Tên tuỳ chọn cần dài từ {$min} đến {$max} ký tự.";
            } elseif (!self::isCodeAvailable($code, $id)) {
                $errors['code'] = 'Tên tuỳ chọn này đã có người dùng rồi.';
            } else {
                $codeError = self::validateCode($code);
                // validateCode kiểm tra cả tính khả dụng nên bỏ qua lỗi trùng của chính nó.
                if ($codeError !== null && !str_contains($codeError, 'đã có người dùng')) {
                    $errors['code'] = $codeError;
                }
            }
        }

        $expiresAt = Clock::fromInput((string) ($data['expires_at'] ?? ''));
        $startsAt = Clock::fromInput((string) ($data['starts_at'] ?? ''));
        if ($expiresAt !== null && $startsAt !== null && $startsAt >= $expiresAt) {
            $errors['starts_at'] = 'Thời điểm bắt đầu phải trước thời điểm hết hạn.';
        }

        $maxClicks = (int) ($data['max_clicks'] ?? 0);
        if ($maxClicks < 0) {
            $errors['max_clicks'] = 'Giới hạn lượt nhấp không thể là số âm.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Mật khẩu: để trống = giữ nguyên, "xoá" = bỏ bảo vệ
        $passwordHash = $link['password_hash'];
        $password = (string) ($data['password'] ?? '');
        if (($data['remove_password'] ?? '') === '1') {
            $passwordHash = null;
        } elseif ($password !== '') {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE links SET code = :code, target_url = :url, title = :title, note = :note, tags = :tags,
                              password_hash = :pass, starts_at = :starts, expires_at = :expires,
                              max_clicks = :max, is_active = :active, qr_dark = :dark, qr_light = :light,
                              updated_at = :updated
             WHERE id = :id'
        );
        $stmt->execute([
            ':code' => $code,
            ':url' => $url,
            ':title' => self::cleanText($data['title'] ?? null, 200),
            ':note' => self::cleanText($data['note'] ?? null, 500),
            ':tags' => self::normalizeTags((string) ($data['tags'] ?? '')),
            ':pass' => $passwordHash,
            ':starts' => $startsAt,
            ':expires' => $expiresAt,
            ':max' => $maxClicks > 0 ? $maxClicks : null,
            ':active' => ($data['is_active'] ?? '1') === '1' ? 1 : 0,
            ':dark' => self::cleanColor($data['qr_dark'] ?? null, '#0b1220'),
            ':light' => self::cleanColor($data['qr_light'] ?? null, '#ffffff'),
            ':updated' => Clock::now(),
            ':id' => $id,
        ]);

        Auth::log($user, 'link_update', (string) $link['code'] . ' → ' . $code);

        return ['ok' => true];
    }

    public static function delete(int $id, ?array $user = null): void
    {
        $link = self::find($id);
        $stmt = Database::pdo()->prepare('DELETE FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($link !== null) {
            Auth::log($user, 'link_delete', (string) $link['code']);
        }
    }

    public static function toggleActive(int $id, ?array $user = null): bool
    {
        $link = self::find($id);
        if ($link === null) {
            return false;
        }
        $new = (int) $link['is_active'] === 1 ? 0 : 1;
        $stmt = Database::pdo()->prepare('UPDATE links SET is_active = :a, updated_at = :u WHERE id = :id');
        $stmt->execute([':a' => $new, ':u' => Clock::now(), ':id' => $id]);
        Auth::log($user, 'link_toggle', (string) $link['code'] . ($new === 1 ? ' → bật' : ' → tạm dừng'));
        return $new === 1;
    }

    public static function toggleStar(int $id): bool
    {
        $link = self::find($id);
        if ($link === null) {
            return false;
        }
        $new = (int) $link['is_starred'] === 1 ? 0 : 1;
        $stmt = Database::pdo()->prepare('UPDATE links SET is_starred = :s WHERE id = :id');
        $stmt->execute([':s' => $new, ':id' => $id]);
        return $new === 1;
    }

    /** Xoá toàn bộ số liệu thống kê của một liên kết. */
    public static function resetStats(int $id, ?array $user = null): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM clicks WHERE link_id = :id');
        $stmt->execute([':id' => $id]);
        $stmt = $pdo->prepare('UPDATE links SET click_count = 0, unique_count = 0, last_click_at = NULL WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $link = self::find($id);
        Auth::log($user, 'link_reset_stats', (string) ($link['code'] ?? $id));
    }

    // ------------------------------------------------------------------ tra cứu

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM links WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM links WHERE code = :code');
        $stmt->execute([':code' => $code]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Người dùng hiện tại có quyền xem/sửa liên kết này không. */
    public static function canManage(array $link, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        return $link['user_id'] !== null && (int) $link['user_id'] === (int) $user['id'];
    }

    public static function countForUser(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM links WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Danh sách liên kết có tìm kiếm, lọc, sắp xếp và phân trang.
     *
     * @param array<string, mixed> $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int}
     */
    public static function paginate(array $filters): array
    {
        $where = [];
        $params = [];

        if (isset($filters['user_id'])) {
            $where[] = 'l.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // Mỗi cột một tên tham số riêng: MySQL với native prepares không
            // cho dùng lặp một tên tham số trong cùng câu lệnh. Chuỗi rỗng viết
            // bằng '' (dấu nháy đơn) vì "" là tên cột trong SQL chuẩn.
            $where[] = "(l.code LIKE :q_code
                        OR l.target_url LIKE :q_url
                        OR IFNULL(l.title, '') LIKE :q_title
                        OR IFNULL(l.note, '') LIKE :q_note
                        OR IFNULL(l.tags, '') LIKE :q_tags)";
            $like = '%' . $q . '%';
            foreach ([':q_code', ':q_url', ':q_title', ':q_note', ':q_tags'] as $name) {
                $params[$name] = $like;
            }
        }

        $tag = trim((string) ($filters['tag'] ?? ''));
        if ($tag !== '') {
            // Thẻ lưu dạng "thẻ1,thẻ2,thẻ3". Không dùng phép nối chuỗi ở đây:
            // trong SQLite "||" là nối chuỗi, còn trong MySQL "||" là phép HOẶC
            // luận lý — cùng một câu lệnh sẽ cho kết quả khác nhau mà không báo
            // lỗi. Thay bằng bốn dạng khớp tường minh, chạy đúng trên cả hai.
            $where[] = "(IFNULL(l.tags, '') = :tag_only
                        OR IFNULL(l.tags, '') LIKE :tag_first
                        OR IFNULL(l.tags, '') LIKE :tag_last
                        OR IFNULL(l.tags, '') LIKE :tag_middle)";
            $params[':tag_only'] = $tag;               // thẻ duy nhất
            $params[':tag_first'] = $tag . ',%';       // thẻ đầu tiên
            $params[':tag_last'] = '%,' . $tag;        // thẻ cuối cùng
            $params[':tag_middle'] = '%,' . $tag . ',%'; // thẻ ở giữa
        }

        if (!empty($filters['starred'])) {
            $where[] = 'l.is_starred = 1';
        }

        $now = Clock::now();
        switch ((string) ($filters['status'] ?? '')) {
            case 'active':
                $where[] = 'l.is_active = 1
                            AND (l.expires_at IS NULL OR l.expires_at > :now_expires)
                            AND (l.starts_at IS NULL OR l.starts_at <= :now_starts)
                            AND (l.max_clicks IS NULL OR l.click_count < l.max_clicks)';
                $params[':now_expires'] = $now;
                $params[':now_starts'] = $now;
                break;
            case 'paused':
                $where[] = 'l.is_active = 0';
                break;
            case 'expired':
                $where[] = 'l.expires_at IS NOT NULL AND l.expires_at <= :now';
                $params[':now'] = $now;
                break;
            case 'scheduled':
                $where[] = 'l.starts_at IS NOT NULL AND l.starts_at > :now';
                $params[':now'] = $now;
                break;
            case 'exhausted':
                $where[] = 'l.max_clicks IS NOT NULL AND l.click_count >= l.max_clicks';
                break;
            case 'protected':
                $where[] = 'l.password_hash IS NOT NULL';
                break;
        }

        $orderBy = match ((string) ($filters['sort'] ?? 'newest')) {
            'oldest' => 'l.created_at ASC',
            'clicks' => 'l.click_count DESC, l.created_at DESC',
            'least' => 'l.click_count ASC, l.created_at DESC',
            // LOWER() thay cho COLLATE NOCASE để chạy được cả SQLite và MySQL.
            'code' => 'LOWER(l.code) ASC',
            // Viết tường minh thay cho "NULLS LAST" để chạy được với SQLite cũ.
            'recent_click' => 'l.last_click_at IS NULL, l.last_click_at DESC, l.created_at DESC',
            default => 'l.created_at DESC',
        };

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countStmt = Database::pdo()->prepare('SELECT COUNT(*) FROM links l' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $perPage = (int) ($filters['per_page'] ?? Config::get('per_page', 12));
        $allowed = (array) Config::get('per_page_options', [12]);
        if (!in_array($perPage, $allowed, true)) {
            $perPage = (int) Config::get('per_page', 12);
        }
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($filters['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT l.*, u.username AS owner_username, u.full_name AS owner_name
                FROM links l LEFT JOIN users u ON u.id = l.user_id'
            . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** Tất cả thẻ đang dùng, kèm số lượng liên kết. @return array<string, int> */
    public static function tagCounts(?int $userId = null): array
    {
        $sql = "SELECT tags FROM links WHERE tags IS NOT NULL AND tags <> ''";
        $params = [];
        if ($userId !== null) {
            $sql .= ' AND user_id = :u';
            $params[':u'] = $userId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (explode(',', (string) $row['tags']) as $tag) {
                $tag = trim($tag);
                if ($tag !== '') {
                    $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                }
            }
        }
        arsort($counts);
        return $counts;
    }

    // ------------------------------------------------------- trạng thái liên kết

    /**
     * Trạng thái hiện thời của liên kết.
     *
     * @return array{key: string, label: string, tone: string, reason: string}
     */
    public static function state(array $link): array
    {
        $now = Clock::now();

        if ((int) $link['is_active'] !== 1) {
            return ['key' => 'paused', 'label' => 'Tạm dừng', 'tone' => 'muted',
                'reason' => 'Liên kết này đang được tạm dừng.'];
        }
        if ($link['starts_at'] !== null && (string) $link['starts_at'] > $now) {
            return ['key' => 'scheduled', 'label' => 'Chờ tới hạn', 'tone' => 'info',
                'reason' => 'Liên kết sẽ bắt đầu hoạt động từ ' . Clock::formatShort((string) $link['starts_at']) . '.'];
        }
        if ($link['expires_at'] !== null && (string) $link['expires_at'] <= $now) {
            return ['key' => 'expired', 'label' => 'Hết hạn', 'tone' => 'danger',
                'reason' => 'Liên kết đã hết hạn lúc ' . Clock::formatShort((string) $link['expires_at']) . '.'];
        }
        if ($link['max_clicks'] !== null && (int) $link['click_count'] >= (int) $link['max_clicks']) {
            return ['key' => 'exhausted', 'label' => 'Đủ lượt', 'tone' => 'warning',
                'reason' => 'Liên kết đã dùng hết ' . n((int) $link['max_clicks']) . ' lượt cho phép.'];
        }
        return ['key' => 'active', 'label' => 'Đang chạy', 'tone' => 'success', 'reason' => ''];
    }

    public static function isUsable(array $link): bool
    {
        return self::state($link)['key'] === 'active';
    }

    // -------------------------------------------------------------- ghi nhận nhấp

    /** Ghi nhận một lượt nhấp và cập nhật số tổng hợp. */
    public static function recordClick(array $link, string $source = ''): void
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $info = Ua::parse($ua);
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $now = Clock::nowDt();
        $visitorHash = self::visitorHash($ua);

        $pdo = Database::pdo();

        // Lượt "duy nhất" = dấu vết khách chưa từng xuất hiện ở liên kết này.
        $check = $pdo->prepare('SELECT 1 FROM clicks WHERE link_id = :l AND visitor_hash = :v LIMIT 1');
        $check->execute([':l' => (int) $link['id'], ':v' => $visitorHash]);
        $isUnique = $check->fetchColumn() === false;

        $stmt = $pdo->prepare(
            'INSERT INTO clicks (link_id, clicked_at, click_date, click_hour, weekday, visitor_hash, is_unique,
                                 referer, referer_host, browser, os, device, country, language, source, is_bot)
             VALUES (:l, :at, :date, :hour, :wd, :v, :uniq, :ref, :refhost, :browser, :os, :device,
                     :country, :lang, :source, :bot)'
        );
        $stmt->execute([
            ':l' => (int) $link['id'],
            ':at' => $now->format('Y-m-d H:i:s'),
            ':date' => $now->format('Y-m-d'),
            ':hour' => (int) $now->format('G'),
            ':wd' => (int) $now->format('w'),
            ':v' => $visitorHash,
            ':uniq' => $isUnique ? 1 : 0,
            ':ref' => $referer !== '' ? mb_substr($referer, 0, 500) : null,
            ':refhost' => $referer !== '' ? host_of($referer) : null,
            ':browser' => $info['browser'],
            ':os' => $info['os'],
            ':device' => $info['device'],
            ':country' => Ua::country() ?: null,
            ':lang' => Ua::language() ?: null,
            ':source' => $source !== '' ? $source : null,
            ':bot' => $info['is_bot'] ? 1 : 0,
        ]);

        $update = $pdo->prepare(
            'UPDATE links SET click_count = click_count + 1,
                              unique_count = unique_count + :uniq,
                              last_click_at = :at
             WHERE id = :id'
        );
        $update->execute([
            ':uniq' => $isUnique ? 1 : 0,
            ':at' => $now->format('Y-m-d H:i:s'),
            ':id' => (int) $link['id'],
        ]);
    }

    /**
     * Dấu vết ẩn danh của khách (IP + trình duyệt + muối riêng của hệ thống).
     * Không lưu IP thật để tôn trọng quyền riêng tư của người truy cập.
     */
    private static function visitorHash(string $ua): string
    {
        $salt = Settings::get('hash_salt');
        if ($salt === null || $salt === '') {
            $salt = bin2hex(random_bytes(16));
            Settings::set('hash_salt', $salt);
        }
        return substr(hash('sha256', client_ip() . '|' . $ua . '|' . $salt), 0, 32);
    }

    // ------------------------------------------------------------------ tiện ích

    private static function guestLimitReached(): bool
    {
        $limit = (int) Config::get('guest_hourly_limit', 10);
        if ($limit <= 0) {
            return false;
        }
        $since = Clock::nowDt()->modify('-1 hour')->format('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM links WHERE user_id IS NULL AND creator_ip = :ip AND created_at >= :since'
        );
        $stmt->execute([':ip' => client_ip(), ':since' => $since]);
        return (int) $stmt->fetchColumn() >= $limit;
    }

    /** Chuẩn hoá danh sách thẻ: bỏ trùng, giới hạn số lượng và độ dài. */
    public static function normalizeTags(string $raw): ?string
    {
        $parts = preg_split('/[,;\n]+/', $raw) ?: [];
        $tags = [];
        foreach ($parts as $part) {
            $tag = trim(preg_replace('/\s+/', ' ', $part) ?? '');
            if ($tag === '') {
                continue;
            }
            $tag = mb_substr($tag, 0, 24);
            if (!in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
            if (count($tags) >= 8) {
                break;
            }
        }
        return $tags === [] ? null : implode(',', $tags);
    }

    /** @return array<int, string> */
    public static function tagList(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn($t) => $t !== ''));
    }

    private static function cleanText(mixed $value, int $limit): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        return mb_substr($text, 0, $limit);
    }

    private static function cleanColor(mixed $value, string $default): string
    {
        $color = trim((string) ($value ?? ''));
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $default;
    }

    /** Xuất danh sách liên kết ra CSV (mở được bằng Excel, có BOM UTF-8). */
    public static function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Không mở được bộ đệm để xuất tệp.');
        }
        fwrite($handle, "\xEF\xBB\xBF"); // BOM để Excel hiểu UTF-8

        // PHP 8.4 yêu cầu truyền tường minh tham số $escape; dùng chuỗi rỗng
        // để theo đúng chuẩn CSV (không dùng dấu gạch chéo ngược để escape).
        $writeRow = static function (array $fields) use ($handle): void {
            fputcsv($handle, $fields, ',', '"', '');
        };

        $writeRow([
            'Mã rút gọn', 'Địa chỉ rút gọn', 'Địa chỉ gốc', 'Tiêu đề', 'Thẻ',
            'Tổng lượt nhấp', 'Lượt khách riêng', 'Trạng thái',
            'Ngày tạo (UTC+7)', 'Hết hạn (UTC+7)', 'Lần nhấp cuối (UTC+7)', 'Người tạo',
        ]);
        foreach ($rows as $row) {
            $writeRow([
                (string) $row['code'],
                short_url((string) $row['code']),
                (string) $row['target_url'],
                (string) ($row['title'] ?? ''),
                (string) ($row['tags'] ?? ''),
                (int) $row['click_count'],
                (int) $row['unique_count'],
                self::state($row)['label'],
                Clock::formatShort((string) $row['created_at']),
                $row['expires_at'] !== null ? Clock::formatShort((string) $row['expires_at']) : '',
                $row['last_click_at'] !== null ? Clock::formatShort((string) $row['last_click_at']) : '',
                (string) ($row['owner_username'] ?? 'khách'),
            ]);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);
        return $csv;
    }
}
