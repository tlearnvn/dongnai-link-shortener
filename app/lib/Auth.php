<?php
declare(strict_types=1);

/** Đăng ký, đăng nhập, ghi nhớ đăng nhập và phân quyền. */
final class Auth
{
    private const REMEMBER_COOKIE = 'rutgon_remember';
    private const REMEMBER_DAYS = 30;

    /** @var array<string, mixed>|null */
    private static ?array $user = null;
    private static bool $resolved = false;

    /** Người dùng đang đăng nhập, hoặc null. */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id > 0) {
            self::$user = self::findById($id);
            if (self::$user === null || self::$user['status'] !== 'active') {
                self::$user = null;
                unset($_SESSION['user_id']);
            }
            return self::$user;
        }

        self::$user = self::loginFromRememberCookie();
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
    }

    /** Tên hiển thị ưu tiên họ tên, không có thì lấy tên đăng nhập. */
    public static function displayName(): string
    {
        $user = self::user();
        if ($user === null) {
            return 'Khách';
        }
        $name = trim((string) ($user['full_name'] ?? ''));
        return $name !== '' ? $name : (string) $user['username'];
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user === null) {
            $_SESSION['_intended'] = request_path();
            flash('info', 'Vui lòng đăng nhập để tiếp tục.');
            redirect('/dang-nhap');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            abort(403, 'Không có quyền truy cập', 'Chức năng này chỉ dành cho quản trị viên của hệ thống.');
        }
        return $user;
    }

    // ------------------------------------------------------------- tra cứu

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function findByLogin(string $login): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM users WHERE username = :login OR (email IS NOT NULL AND email <> "" AND email = :login) LIMIT 1'
        );
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function findByApiToken(string $token): ?array
    {
        if (strlen($token) < 20) {
            return null;
        }
        $stmt = Database::pdo()->prepare("SELECT * FROM users WHERE api_token = :t AND status = 'active'");
        $stmt->execute([':t' => $token]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function userCount(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    // ------------------------------------------------------------- đăng ký

    /**
     * Kiểm tra dữ liệu đăng ký.
     *
     * @return array<string, string> Danh sách lỗi theo từng trường (rỗng = hợp lệ)
     */
    public static function validateRegistration(string $username, string $password, string $confirm, string $email): array
    {
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,31}$/', $username)) {
            $errors['username'] = 'Tên đăng nhập gồm 3–32 ký tự, chỉ dùng chữ không dấu, số và . _ -';
        } elseif (self::findByLogin($username) !== null) {
            $errors['username'] = 'Tên đăng nhập này đã có người dùng.';
        }

        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Mật khẩu cần ít nhất 8 ký tự.';
        } elseif ($password !== $confirm) {
            $errors['password_confirm'] = 'Hai lần nhập mật khẩu chưa giống nhau.';
        }

        if ($email !== '') {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['email'] = 'Địa chỉ email không đúng định dạng.';
            } else {
                $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :e');
                $stmt->execute([':e' => $email]);
                if ((int) $stmt->fetchColumn() > 0) {
                    $errors['email'] = 'Email này đã được dùng cho một tài khoản khác.';
                }
            }
        }

        return $errors;
    }

    /**
     * Tạo tài khoản mới. Người dùng đầu tiên của hệ thống thành quản trị viên.
     *
     * @return array{user: array<string, mixed>, recovery_code: string}
     */
    public static function register(string $username, string $password, string $email = '', string $fullName = '', string $unit = ''): array
    {
        $isFirstUser = self::userCount() === 0;
        $recoveryCode = self::generateRecoveryCode();

        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (username, email, full_name, unit, password_hash, role, status,
                                api_token, recovery_code_hash, created_at)
             VALUES (:username, :email, :full_name, :unit, :hash, :role, :status, :token, :recovery, :created)'
        );
        $stmt->execute([
            ':username' => $username,
            ':email' => $email !== '' ? $email : null,
            ':full_name' => $fullName !== '' ? $fullName : null,
            ':unit' => $unit !== '' ? $unit : null,
            ':hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $isFirstUser ? 'admin' : 'user',
            ':status' => 'active',
            ':token' => self::generateApiToken(),
            ':recovery' => password_hash($recoveryCode, PASSWORD_DEFAULT),
            ':created' => Clock::now(),
        ]);

        $user = self::findById((int) Database::pdo()->lastInsertId());
        if ($user === null) {
            throw new RuntimeException('Không tạo được tài khoản.');
        }

        self::log($user, 'register', $isFirstUser ? 'Tài khoản đầu tiên — cấp quyền quản trị' : 'Tài khoản mới');

        return ['user' => $user, 'recovery_code' => $recoveryCode];
    }

    // ----------------------------------------------------------- đăng nhập

    /** Đăng nhập; trả về null nếu thành công, hoặc chuỗi mô tả lỗi. */
    public static function attempt(string $login, string $password, bool $remember = false): ?string
    {
        $ip = client_ip();
        if (self::isLockedOut($ip)) {
            $minutes = (int) Config::get('login_lockout_minutes', 15);
            return "Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau {$minutes} phút.";
        }

        $user = self::findByLogin($login);
        $ok = $user !== null && password_verify($password, (string) $user['password_hash']);
        self::recordAttempt($ip, $login, $ok);

        if (!$ok || $user === null) {
            return 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
        if ($user['status'] !== 'active') {
            return 'Tài khoản đang bị tạm ngưng. Vui lòng liên hệ quản trị viên.';
        }

        // Nâng cấp thuật toán băm nếu cấu hình PHP đã đổi.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $stmt->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
        }

        self::completeLogin($user, $remember);
        return null;
    }

    public static function completeLogin(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = $user;
        self::$resolved = true;

        $stmt = Database::pdo()->prepare(
            'UPDATE users SET last_login_at = :at, last_login_ip = :ip, login_count = login_count + 1 WHERE id = :id'
        );
        $stmt->execute([':at' => Clock::now(), ':ip' => client_ip(), ':id' => $user['id']]);

        if ($remember) {
            self::issueRememberCookie((int) $user['id']);
        }
        self::log($user, 'login', 'Đăng nhập thành công');
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user !== null) {
            self::log($user, 'logout', 'Đăng xuất');
        }
        self::clearRememberCookie();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: 'rutgon_session', '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        self::$user = null;
        self::$resolved = true;
    }

    // -------------------------------------------------- ghi nhớ đăng nhập

    private static function issueRememberCookie(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $expires = Clock::nowDt()->modify('+' . self::REMEMBER_DAYS . ' days');

        $stmt = Database::pdo()->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at)
             VALUES (:u, :s, :h, :e, :c)'
        );
        $stmt->execute([
            ':u' => $userId,
            ':s' => $selector,
            ':h' => hash('sha256', $validator),
            ':e' => $expires->format('Y-m-d H:i:s'),
            ':c' => Clock::now(),
        ]);

        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires' => $expires->getTimestamp(),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => str_starts_with(base_url(), 'https://'),
        ]);
    }

    private static function loginFromRememberCookie(): ?array
    {
        $cookie = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');
        if (!str_contains($cookie, ':')) {
            return null;
        }
        [$selector, $validator] = explode(':', $cookie, 2);

        $stmt = Database::pdo()->prepare('SELECT * FROM remember_tokens WHERE selector = :s');
        $stmt->execute([':s' => $selector]);
        $row = $stmt->fetch();
        if ($row === false) {
            self::clearRememberCookie();
            return null;
        }
        if ((string) $row['expires_at'] < Clock::now()) {
            self::deleteRememberToken((int) $row['id']);
            self::clearRememberCookie();
            return null;
        }
        if (!hash_equals((string) $row['token_hash'], hash('sha256', $validator))) {
            // Có dấu hiệu token bị đánh cắp: xoá toàn bộ token của người này.
            self::revokeRememberTokens((int) $row['user_id']);
            self::clearRememberCookie();
            return null;
        }

        $user = self::findById((int) $row['user_id']);
        if ($user === null || $user['status'] !== 'active') {
            self::clearRememberCookie();
            return null;
        }

        // Dùng một lần rồi cấp token mới (chống phát lại).
        self::deleteRememberToken((int) $row['id']);
        $_SESSION['user_id'] = (int) $user['id'];
        self::issueRememberCookie((int) $user['id']);

        return $user;
    }

    private static function deleteRememberToken(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM remember_tokens WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public static function revokeRememberTokens(int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM remember_tokens WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
    }

    private static function clearRememberCookie(): void
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            setcookie(self::REMEMBER_COOKIE, '', [
                'expires' => time() - 42000,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }

    // ------------------------------------------------- chống dò mật khẩu

    private static function recordAttempt(string $ip, string $username, bool $ok): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO login_attempts (ip, username, successful, created_at) VALUES (:ip, :u, :ok, :at)'
        );
        $stmt->execute([
            ':ip' => $ip,
            ':u' => mb_substr($username, 0, 64),
            ':ok' => $ok ? 1 : 0,
            ':at' => Clock::now(),
        ]);

        if ($ok) {
            $clear = Database::pdo()->prepare('DELETE FROM login_attempts WHERE ip = :ip AND successful = 0');
            $clear->execute([':ip' => $ip]);
        }
    }

    private static function isLockedOut(string $ip): bool
    {
        $max = (int) Config::get('login_max_attempts', 8);
        $window = (int) Config::get('login_lockout_minutes', 15);
        $since = Clock::nowDt()->modify("-{$window} minutes")->format('Y-m-d H:i:s');

        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND successful = 0 AND created_at >= :since'
        );
        $stmt->execute([':ip' => $ip, ':since' => $since]);

        return (int) $stmt->fetchColumn() >= $max;
    }

    // ------------------------------------------------------- mật khẩu, token

    public static function changePassword(int $userId, string $newPassword): void
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $stmt->execute([':h' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $userId]);
        self::revokeRememberTokens($userId);
    }

    /** Đặt lại mật khẩu bằng mã dự phòng (thay cho gửi email). */
    public static function resetWithRecoveryCode(string $login, string $code, string $newPassword): ?string
    {
        $user = self::findByLogin($login);
        $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        $formatted = trim(chunk_split($code, 4, '-'), '-');

        if ($user === null || $user['recovery_code_hash'] === null) {
            return 'Không tìm thấy tài khoản, hoặc tài khoản chưa có mã dự phòng.';
        }
        if (!password_verify($formatted, (string) $user['recovery_code_hash'])) {
            return 'Mã dự phòng không đúng.';
        }
        if (mb_strlen($newPassword) < 8) {
            return 'Mật khẩu mới cần ít nhất 8 ký tự.';
        }

        self::changePassword((int) $user['id'], $newPassword);
        // Mã dự phòng chỉ dùng được một lần — cấp mã mới cho lần sau.
        $newCode = self::generateRecoveryCode();
        $stmt = Database::pdo()->prepare('UPDATE users SET recovery_code_hash = :h WHERE id = :id');
        $stmt->execute([':h' => password_hash($newCode, PASSWORD_DEFAULT), ':id' => $user['id']]);

        self::log($user, 'password_reset', 'Đặt lại mật khẩu bằng mã dự phòng');
        $_SESSION['_new_recovery_code'] = $newCode;

        return null;
    }

    public static function generateRecoveryCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // bỏ ký tự dễ nhìn lẫn
        $groups = [];
        for ($g = 0; $g < 3; $g++) {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $chunk;
        }
        return implode('-', $groups);
    }

    public static function generateApiToken(): string
    {
        return 'dnl_' . bin2hex(random_bytes(24));
    }

    public static function regenerateApiToken(int $userId): string
    {
        $token = self::generateApiToken();
        $stmt = Database::pdo()->prepare('UPDATE users SET api_token = :t WHERE id = :id');
        $stmt->execute([':t' => $token, ':id' => $userId]);
        return $token;
    }

    // -------------------------------------------------------------- nhật ký

    /** Ghi nhật ký hoạt động. */
    public static function log(?array $user, string $action, string $detail = ''): void
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO audit_log (user_id, actor, action, detail, ip, created_at)
                 VALUES (:u, :actor, :action, :detail, :ip, :at)'
            );
            $stmt->execute([
                ':u' => $user === null ? null : (int) $user['id'],
                ':actor' => $user === null ? 'khách' : (string) $user['username'],
                ':action' => $action,
                ':detail' => $detail !== '' ? $detail : null,
                ':ip' => client_ip(),
                ':at' => Clock::now(),
            ]);
        } catch (PDOException) {
            // Nhật ký không được phép làm gián đoạn nghiệp vụ chính.
        }
    }
}
