<?php
declare(strict_types=1);

/** Bảng điều khiển: nhìn nhanh toàn cảnh liên kết của người dùng. */
final class DashboardController
{
    public static function index(): never
    {
        $user = Auth::requireLogin();
        $userId = (int) $user['id'];
        $isAdmin = $user['role'] === 'admin';

        // Quản trị viên xem số liệu toàn hệ thống, người dùng xem số liệu của mình.
        $scope = input('pham-vi') === 'tat-ca' && $isAdmin ? null : $userId;

        $days = input_int('ngay', 30);
        $days = in_array($days, [7, 14, 30, 90], true) ? $days : 30;

        $listFilters = ['per_page' => 12, 'page' => 1, 'sort' => 'newest'];
        if ($scope !== null) {
            $listFilters['user_id'] = $scope;
        }
        $recent = LinkService::paginate($listFilters);

        view('dashboard', [
            'pageTitle' => 'Bảng điều khiển',
            'user' => $user,
            'scope' => $scope,
            'days' => $days,
            'overview' => Stats::overview($scope),
            'series' => Stats::dailySeries($days, null, $scope),
            'topLinks' => Stats::topLinks($scope, 5),
            'recentLinks' => array_slice($recent['rows'], 0, 6),
            'devices' => Stats::breakdown('device', null, $scope, 4),
            'browsers' => Stats::breakdown('browser', null, $scope, 6),
            'referers' => Stats::breakdown('referer_host', null, $scope, 6),
            'qrClicks' => Stats::qrClicks(null, $scope),
            'botClicks' => Stats::botClicks(null, $scope),
            'busiestDay' => Stats::busiestDay(null, $scope),
            'tags' => LinkService::tagCounts($scope),
        ]);
    }
}
