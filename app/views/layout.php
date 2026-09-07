<?php
/**
 * Khung giao diện dùng chung cho mọi trang.
 *
 * @var string      $content   Nội dung trang đã kết xuất
 * @var string|null $pageTitle Tiêu đề riêng của trang
 */

$siteName = (string) Config::get('site_name');
$siteOwner = (string) Config::get('site_owner');
$fullSiteName = $siteName . ' - ' . $siteOwner;
$title = isset($pageTitle) && $pageTitle !== null && $pageTitle !== ''
    ? $pageTitle . ' · ' . $fullSiteName
    : $fullSiteName;

$currentUser = Auth::user();
$isAdmin = Auth::isAdmin();
$announcement = trim((string) Settings::get('announcement', ''));

// Giao diện: ưu tiên lựa chọn lưu trong tài khoản, sau đó tới cookie.
$theme = $currentUser['theme'] ?? ($_COOKIE['rutgon_theme'] ?? 'auto');
if (!in_array($theme, ['auto', 'light', 'dark'], true)) {
    $theme = 'auto';
}
?>
<!doctype html>
<html lang="vi" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e((string) Config::get('site_tagline')) ?> — <?= e($siteOwner) ?>.">
<meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b1220" media="(prefers-color-scheme: dark)">
<meta name="robots" content="<?= str_starts_with(request_path(), '/gioi-thieu') || request_path() === '/' || str_starts_with(request_path(), '/huong-dan') ? 'index, follow' : 'noindex, nofollow' ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e((string) Config::get('site_tagline')) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="vi_VN">
<link rel="icon" href="<?= e(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>?v=1">
<script>
/* Áp dụng giao diện sáng/tối ngay trước khi vẽ trang để không bị nháy màu. */
(function () {
    try {
        var stored = localStorage.getItem('rutgon-theme');
        if (stored === 'light' || stored === 'dark' || stored === 'auto') {
            document.documentElement.dataset.theme = stored;
        }
    } catch (e) { /* chế độ riêng tư có thể chặn localStorage */ }
})();
</script>
</head>
<body>

<a class="skip-link" href="#noi-dung">Bỏ qua, tới nội dung chính</a>

<!-- Nền động: các khối màu trôi nhẹ phía sau nội dung -->
<div class="backdrop" aria-hidden="true">
    <span class="blob blob--1"></span>
    <span class="blob blob--2"></span>
    <span class="blob blob--3"></span>
    <span class="grid-overlay"></span>
</div>

<header class="site-header">
    <div class="wrap site-header__inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand__mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none">
                    <defs>
                        <linearGradient id="brandGrad" x1="0" y1="0" x2="32" y2="32">
                            <stop offset="0%" stop-color="#6366f1"/>
                            <stop offset="55%" stop-color="#a855f7"/>
                            <stop offset="100%" stop-color="#14b8a6"/>
                        </linearGradient>
                    </defs>
                    <rect width="32" height="32" rx="9" fill="url(#brandGrad)"/>
                    <path d="M12.6 19.4a3.6 3.6 0 0 1 0-5.1l2.1-2.1a3.6 3.6 0 0 1 5.1 5.1l-.8.8"
                          stroke="#fff" stroke-width="2.1" stroke-linecap="round"/>
                    <path d="M19.4 12.6a3.6 3.6 0 0 1 0 5.1l-2.1 2.1a3.6 3.6 0 0 1-5.1-5.1l.8-.8"
                          stroke="#fff" stroke-width="2.1" stroke-linecap="round" opacity=".75"/>
                </svg>
            </span>
            <span class="brand__text">
                <strong><?= e($siteName) ?></strong>
                <small><?= e($siteOwner) ?></small>
            </span>
        </a>

        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="dieu-huong"
                aria-label="Mở/đóng menu">
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" id="dieu-huong" aria-label="Điều hướng chính">
            <a class="site-nav__link<?= nav_active('/') ?>" href="<?= e(url('/')) ?>">Trang chủ</a>
            <?php if ($currentUser !== null): ?>
                <a class="site-nav__link<?= nav_active('/bang-dieu-khien') ?>" href="<?= e(url('/bang-dieu-khien')) ?>">Bảng điều khiển</a>
                <a class="site-nav__link<?= nav_active('/lien-ket') ?>" href="<?= e(url('/lien-ket')) ?>">Liên kết</a>
                <a class="site-nav__link<?= nav_active('/thong-ke') ?>" href="<?= e(url('/thong-ke')) ?>">Thống kê</a>
            <?php endif; ?>
            <a class="site-nav__link<?= nav_active('/huong-dan') ?>" href="<?= e(url('/huong-dan')) ?>">Hướng dẫn</a>
            <?php if ($isAdmin): ?>
                <a class="site-nav__link site-nav__link--admin<?= nav_active('/quan-tri') ?>" href="<?= e(url('/quan-tri')) ?>">Quản trị</a>
            <?php endif; ?>

            <div class="site-nav__actions">
                <button class="theme-toggle" type="button" data-theme-toggle
                        title="Đổi giao diện sáng / tối" aria-label="Đổi giao diện sáng / tối">
                    <svg class="theme-toggle__sun" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="4.4"/>
                        <path d="M12 2.6v2.2M12 19.2v2.2M2.6 12h2.2M19.2 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6"/>
                    </svg>
                    <svg class="theme-toggle__moon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20.2 14.6A8.6 8.6 0 0 1 9.4 3.8a8.6 8.6 0 1 0 10.8 10.8z"/>
                    </svg>
                </button>

                <?php if ($currentUser !== null): ?>
                    <div class="user-menu" data-user-menu>
                        <button class="user-menu__button" type="button" aria-expanded="false">
                            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(Auth::displayName(), 0, 1))) ?></span>
                            <span class="user-menu__name"><?= e(truncate_str(Auth::displayName(), 18)) ?></span>
                            <svg class="user-menu__caret" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 8l5 5 5-5"/></svg>
                        </button>
                        <div class="user-menu__panel" hidden>
                            <div class="user-menu__head">
                                <strong><?= e(Auth::displayName()) ?></strong>
                                <span>@<?= e((string) $currentUser['username']) ?><?= $isAdmin ? ' · quản trị viên' : '' ?></span>
                            </div>
                            <a href="<?= e(url('/tai-khoan')) ?>">Tài khoản của tôi</a>
                            <a href="<?= e(url('/lien-ket/tao')) ?>">Tạo liên kết mới</a>
                            <a href="<?= e(url('/tao-hang-loat')) ?>">Tạo hàng loạt</a>
                            <a href="<?= e(url('/cong-cu/utm')) ?>">Công cụ gắn thẻ UTM</a>
                            <a href="<?= e(url('/api')) ?>">Tài liệu API</a>
                            <form method="post" action="<?= e(url('/dang-xuat')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="user-menu__logout">Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="btn btn--ghost btn--sm" href="<?= e(url('/dang-nhap')) ?>">Đăng nhập</a>
                    <?php if (Settings::bool('allow_registration', true) || Auth::userCount() === 0): ?>
                        <a class="btn btn--primary btn--sm" href="<?= e(url('/dang-ky')) ?>">Tạo tài khoản</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<?php if ($announcement !== ''): ?>
    <div class="announcement" role="status">
        <div class="wrap announcement__inner">
            <span class="announcement__icon" aria-hidden="true">📢</span>
            <p><?= e($announcement) ?></p>
        </div>
    </div>
<?php endif; ?>

<main id="noi-dung" class="site-main">
    <div class="wrap">
        <?= render('partials/flash') ?>
    </div>
    <?= $content ?>
</main>

<footer class="site-footer">
    <div class="wrap site-footer__inner">
        <div class="site-footer__brand">
            <strong><?= e($fullSiteName) ?></strong>
            <p><?= e((string) Config::get('site_tagline')) ?>.</p>
        </div>

        <nav class="site-footer__links" aria-label="Liên kết chân trang">
            <a href="<?= e(url('/')) ?>">Rút gọn liên kết</a>
            <a href="<?= e(url('/huong-dan')) ?>">Hướng dẫn</a>
            <a href="<?= e(url('/cong-cu/utm')) ?>">Công cụ UTM</a>
            <a href="<?= e(url('/api')) ?>">API</a>
            <a href="<?= e(url('/gioi-thieu')) ?>">Giới thiệu</a>
        </nav>

        <div class="site-footer__meta">
            <p class="site-footer__credit">© <?= e(Clock::nowDt()->format('Y')) ?> — <?= e((string) Config::get('footer_credit')) ?></p>
            <p class="site-footer__clock">
                <span aria-hidden="true">🕒</span>
                Múi giờ hệ thống: <?= e(Clock::tzLabel()) ?> ·
                <time datetime="<?= e(Clock::nowDt()->format('c')) ?>"><?= e(Clock::nowDt()->format('H:i d/m/Y')) ?></time>
            </p>
        </div>
    </div>
</footer>

<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>

<script src="<?= e(url('assets/js/app.js')) ?>?v=1" defer></script>
</body>
</html>
