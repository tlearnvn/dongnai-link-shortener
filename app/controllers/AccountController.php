<?php
declare(strict_types=1);

/** Trang tài khoản cá nhân. */
final class AccountController
{
    public static function index(): never
    {
        $user = Auth::requireLogin();

        // Mã dự phòng chỉ hiện một lần duy nhất rồi biến mất khỏi phiên.
        $recoveryCode = $_SESSION['_show_recovery_code'] ?? $_SESSION['_new_recovery_code'] ?? null;
        unset($_SESSION['_show_recovery_code'], $_SESSION['_new_recovery_code']);

        $newToken = $_SESSION['_new_api_token'] ?? null;
        unset($_SESSION['_new_api_token']);

        view('account', [
            'pageTitle' => 'Tài khoản của tôi',
            'user' => $user,
            'recoveryCode' => $recoveryCode,
            'newToken' => $newToken,
            'overview' => Stats::overview((int) $user['id']),
        ]);
    }

    public static function updateProfile(): never
    {
        csrf_verify();
        $user = Auth::requireLogin();

        $fullName = input('full_name');
        $unit = input('unit');
        $email = input('email');

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', 'Địa chỉ email không đúng định dạng.');
            redirect('/tai-khoan');
        }
        if ($email !== '') {
            $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id');
            $stmt->execute([':e' => $email, ':id' => (int) $user['id']]);
            if ((int) $stmt->fetchColumn() > 0) {
                flash('error', 'Email này đã được dùng cho một tài khoản khác.');
                redirect('/tai-khoan');
            }
        }

        $stmt = Database::pdo()->prepare(
            'UPDATE users SET full_name = :name, unit = :unit, email = :email WHERE id = :id'
        );
        $stmt->execute([
            ':name' => $fullName !== '' ? mb_substr($fullName, 0, 100) : null,
            ':unit' => $unit !== '' ? mb_substr($unit, 0, 120) : null,
            ':email' => $email !== '' ? $email : null,
            ':id' => (int) $user['id'],
        ]);

        flash('success', 'Đã cập nhật thông tin tài khoản.');
        redirect('/tai-khoan');
    }

    public static function changePassword(): never
    {
        csrf_verify();
        $user = Auth::requireLogin();

        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        if (!password_verify($current, (string) $user['password_hash'])) {
            flash('error', 'Mật khẩu hiện tại không đúng.');
            redirect('/tai-khoan');
        }
        if (mb_strlen($new) < 8) {
            flash('error', 'Mật khẩu mới cần ít nhất 8 ký tự.');
            redirect('/tai-khoan');
        }
        if ($new !== $confirm) {
            flash('error', 'Hai lần nhập mật khẩu mới chưa giống nhau.');
            redirect('/tai-khoan');
        }

        Auth::changePassword((int) $user['id'], $new);
        Auth::log($user, 'password_change', 'Người dùng tự đổi mật khẩu');

        flash('success', 'Đã đổi mật khẩu. Các thiết bị đang ghi nhớ đăng nhập sẽ cần đăng nhập lại.');
        redirect('/tai-khoan');
    }

    public static function regenerateToken(): never
    {
        csrf_verify();
        $user = Auth::requireLogin();

        $_SESSION['_new_api_token'] = Auth::regenerateApiToken((int) $user['id']);
        Auth::log($user, 'api_token_reset', 'Cấp lại khoá API');

        flash('success', 'Đã cấp khoá API mới. Khoá cũ không còn dùng được nữa.');
        redirect('/tai-khoan');
    }

    /** Lưu lựa chọn giao diện sáng/tối vào tài khoản. */
    public static function saveTheme(): never
    {
        csrf_verify();
        $user = Auth::requireLogin();

        $theme = input('theme');
        if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
            $theme = 'auto';
        }

        $stmt = Database::pdo()->prepare('UPDATE users SET theme = :t WHERE id = :id');
        $stmt->execute([':t' => $theme, ':id' => (int) $user['id']]);

        if (is_ajax()) {
            json_out(['ok' => true, 'theme' => $theme]);
        }
        redirect_back('/tai-khoan');
    }
}
