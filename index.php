<?php
declare(strict_types=1);

/**
 * Rút gọn link — Phòng GDPT-GDTX Sở GDĐT Đồng Nai
 * Thiết kế bởi Trương Anh Tuấn
 *
 * Tệp này là cửa vào duy nhất của hệ thống: nạp cấu hình, mở phiên làm việc,
 * rồi chuyển yêu cầu tới đúng bộ xử lý.
 */

require __DIR__ . '/app/bootstrap.php';

$router = new Router();

// ------------------------------------------------------------- trang công khai
$router->get('/', [HomeController::class, 'index']);
$router->post('/rut-gon', [HomeController::class, 'shorten']);
$router->get('/ket-qua/{code}', [HomeController::class, 'result']);
$router->get('/huong-dan', [PageController::class, 'guide']);
$router->get('/gioi-thieu', [PageController::class, 'about']);
$router->get('/cong-cu/utm', [ToolController::class, 'utm']);
$router->get('/robots.txt', [PageController::class, 'robots']);

// ------------------------------------------------------------------- tài khoản
$router->get('/dang-nhap', [AuthController::class, 'loginForm']);
$router->post('/dang-nhap', [AuthController::class, 'login']);
$router->get('/dang-ky', [AuthController::class, 'registerForm']);
$router->post('/dang-ky', [AuthController::class, 'register']);
$router->post('/dang-xuat', [AuthController::class, 'logout']);
$router->get('/quen-mat-khau', [AuthController::class, 'forgotForm']);
$router->post('/quen-mat-khau', [AuthController::class, 'forgot']);

$router->get('/tai-khoan', [AccountController::class, 'index']);
$router->post('/tai-khoan/thong-tin', [AccountController::class, 'updateProfile']);
$router->post('/tai-khoan/mat-khau', [AccountController::class, 'changePassword']);
$router->post('/tai-khoan/api-token', [AccountController::class, 'regenerateToken']);
$router->post('/tai-khoan/giao-dien', [AccountController::class, 'saveTheme']);

// -------------------------------------------------------------- quản lý liên kết
$router->get('/bang-dieu-khien', [DashboardController::class, 'index']);
$router->get('/lien-ket', [LinkController::class, 'index']);
$router->get('/lien-ket/tao', [LinkController::class, 'createForm']);
$router->post('/lien-ket/tao', [LinkController::class, 'store']);
$router->get('/lien-ket/{id:\d+}/sua', [LinkController::class, 'editForm']);
$router->post('/lien-ket/{id:\d+}/sua', [LinkController::class, 'update']);
$router->post('/lien-ket/{id:\d+}/xoa', [LinkController::class, 'destroy']);
$router->post('/lien-ket/{id:\d+}/trang-thai', [LinkController::class, 'toggle']);
$router->post('/lien-ket/{id:\d+}/danh-dau', [LinkController::class, 'star']);
$router->post('/lien-ket/{id:\d+}/xoa-thong-ke', [LinkController::class, 'resetStats']);
$router->get('/tao-hang-loat', [BulkController::class, 'form']);
$router->post('/tao-hang-loat', [BulkController::class, 'store']);
$router->get('/xuat-csv', [LinkController::class, 'exportCsv']);

// ------------------------------------------------------------------- thống kê
$router->get('/thong-ke', [StatsController::class, 'overview']);
$router->get('/thong-ke/{id:\d+}', [StatsController::class, 'link']);

// --------------------------------------------------------------------- mã QR
$router->get('/ma-qr/{code}.svg', [QrController::class, 'svg']);
$router->get('/ma-qr/{code}.png', [QrController::class, 'png']);
$router->get('/ma-qr/{code}', [QrController::class, 'page']);

// -------------------------------------------------------------------- quản trị
$router->get('/quan-tri', [AdminController::class, 'index']);
$router->get('/quan-tri/nguoi-dung', [AdminController::class, 'users']);
$router->post('/quan-tri/nguoi-dung/{id:\d+}', [AdminController::class, 'updateUser']);
$router->get('/quan-tri/lien-ket', [AdminController::class, 'links']);
$router->get('/quan-tri/cai-dat', [AdminController::class, 'settings']);
$router->post('/quan-tri/cai-dat', [AdminController::class, 'saveSettings']);
$router->get('/quan-tri/nhat-ky', [AdminController::class, 'auditLog']);
$router->post('/quan-tri/bao-tri', [AdminController::class, 'maintenance']);

// ------------------------------------------------------------------------- API
$router->get('/api', [ApiController::class, 'docs']);
$router->get('/api/kiem-tra-ten', [ApiController::class, 'checkCode']);
$router->post('/api/rut-gon', [ApiController::class, 'shorten']);
$router->get('/api/lien-ket', [ApiController::class, 'links']);
$router->get('/api/thong-ke/{code}', [ApiController::class, 'stats']);

// ------------------------------------------- xem trước và chuyển hướng (cuối cùng)
$router->get('/xem/{code}', [RedirectController::class, 'preview']);
$router->get('/{code}', [RedirectController::class, 'go']);
$router->post('/{code}', [RedirectController::class, 'unlock']);

$router->fallback(static function (): void {
    abort(404, 'Không tìm thấy trang', 'Đường dẫn bạn vừa truy cập không tồn tại hoặc đã bị xoá.');
});

$router->dispatch(request_method(), request_path());
