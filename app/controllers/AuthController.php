<?php
declare(strict_types=1);

/** Đăng ký, đăng nhập, đăng xuất và đặt lại mật khẩu bằng mã dự phòng. */
final class AuthController
{
    public static function loginForm(): never
    {
        if (Auth::check()) {
            redirect('/bang-dieu-khien');
        }
        view('auth/login', ['pageTitle' => 'Đăng nhập']);
    }

    public static function login(): never
    {
        csrf_verify();

        $login = input('login');
        $password = (string) ($_POST['password'] ?? '');
        $remember = input_bool('remember');

        if ($login === '' || $password === '') {
            keep_input($_POST, ['login' => 'Vui lòng nhập đủ tên đăng nhập và mật khẩu.']);
            flash('error', 'Vui lòng nhập đủ tên đăng nhập và mật khẩu.');
            redirect('/dang-nhap');
        }

        $error = Auth::attempt($login, $password, $remember);
        if ($error !== null) {
            keep_input($_POST, ['login' => $error]);
            flash('error', $error);
            redirect('/dang-nhap');
        }

        $intended = (string) ($_SESSION['_intended'] ?? '/bang-dieu-khien');
        unset($_SESSION['_intended']);

        flash('success', 'Chào mừng ' . Auth::displayName() . ' đã trở lại! 👋');
        redirect($intended);
    }

    public static function registerForm(): never
    {
        if (Auth::check()) {
            redirect('/bang-dieu-khien');
        }
        if (!Settings::bool('allow_registration', true) && Auth::userCount() > 0) {
            abort(403, 'Tạm ngưng đăng ký', 'Hệ thống đang tạm ngưng nhận tài khoản mới. Vui lòng liên hệ quản trị viên để được cấp tài khoản.');
        }
        view('auth/register', [
            'pageTitle' => 'Tạo tài khoản',
            'isFirstUser' => Auth::userCount() === 0,
        ]);
    }

    public static function register(): never
    {
        csrf_verify();

        // Đang đăng nhập thì không tạo thêm tài khoản (tránh nhầm lẫn phiên).
        if (Auth::check()) {
            redirect('/bang-dieu-khien');
        }

        if (!Settings::bool('allow_registration', true) && Auth::userCount() > 0) {
            abort(403, 'Tạm ngưng đăng ký', 'Hệ thống đang tạm ngưng nhận tài khoản mới.');
        }

        $username = input('username');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $email = input('email');
        $fullName = input('full_name');
        $unit = input('unit');

        $errors = Auth::validateRegistration($username, $password, $confirm, $email);
        if ($errors !== []) {
            keep_input($_POST, $errors);
            flash('error', (string) reset($errors));
            redirect('/dang-ky');
        }

        $created = Auth::register($username, $password, $email, $fullName, $unit);
        Auth::completeLogin($created['user'], true);

        // Mã dự phòng chỉ hiện đúng một lần, ngay sau khi tạo tài khoản.
        $_SESSION['_show_recovery_code'] = $created['recovery_code'];

        flash('success', 'Tạo tài khoản thành công. Chúc bạn dùng vui! 🎉');
        redirect('/tai-khoan');
    }

    public static function logout(): never
    {
        csrf_verify();
        Auth::logout();
        session_start_safe();
        flash('info', 'Bạn đã đăng xuất. Hẹn gặp lại!');
        redirect('/');
    }

    public static function forgotForm(): never
    {
        view('auth/forgot', ['pageTitle' => 'Quên mật khẩu']);
    }

    public static function forgot(): never
    {
        csrf_verify();

        $login = input('login');
        $code = input('recovery_code');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $confirm) {
            keep_input($_POST, ['password_confirm' => 'Hai lần nhập mật khẩu chưa giống nhau.']);
            flash('error', 'Hai lần nhập mật khẩu chưa giống nhau.');
            redirect('/quen-mat-khau');
        }

        $error = Auth::resetWithRecoveryCode($login, $code, $password);
        if ($error !== null) {
            keep_input($_POST, ['recovery_code' => $error]);
            flash('error', $error);
            redirect('/quen-mat-khau');
        }

        flash('success', 'Đã đặt lại mật khẩu. Bạn đăng nhập lại nhé!');
        redirect('/dang-nhap');
    }
}
