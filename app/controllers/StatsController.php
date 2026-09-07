<?php
declare(strict_types=1);

/** Trang thống kê tổng hợp và thống kê chi tiết từng liên kết. */
final class StatsController
{
    public static function overview(): never
    {
        $user = Auth::requireLogin();
        $isAdmin = $user['role'] === 'admin';
        $scope = input('pham-vi') === 'tat-ca' && $isAdmin ? null : (int) $user['id'];

        $days = input_int('ngay', 30);
        $days = in_array($days, [7, 14, 30, 90, 365], true) ? $days : 30;

        view('stats/overview', [
            'pageTitle' => 'Thống kê tổng hợp',
            'user' => $user,
            'scope' => $scope,
            'days' => $days,
            'overview' => Stats::overview($scope),
            'series' => Stats::dailySeries($days, null, $scope),
            'hourly' => Stats::hourly(null, $scope),
            'weekday' => Stats::weekday(null, $scope),
            'devices' => Stats::breakdown('device', null, $scope, 4),
            'browsers' => Stats::breakdown('browser', null, $scope, 8),
            'systems' => Stats::breakdown('os', null, $scope, 8),
            'referers' => Stats::breakdown('referer_host', null, $scope, 8),
            'countries' => Stats::breakdown('country', null, $scope, 8),
            'sources' => Stats::breakdown('source', null, $scope, 6),
            'topLinks' => Stats::topLinks($scope, 10),
            'qrClicks' => Stats::qrClicks(null, $scope),
            'botClicks' => Stats::botClicks(null, $scope),
            'busiestDay' => Stats::busiestDay(null, $scope),
        ]);
    }

    public static function link(array $params): never
    {
        $user = Auth::requireLogin();
        $link = LinkService::find((int) $params['id']);

        if ($link === null) {
            abort(404, 'Không tìm thấy liên kết', 'Liên kết này không tồn tại hoặc đã bị xoá.');
        }
        if (!LinkService::canManage($link, $user)) {
            abort(403, 'Không có quyền', 'Bạn chỉ xem được thống kê của những liên kết do mình tạo.');
        }

        $linkId = (int) $link['id'];
        $days = input_int('ngay', 30);
        $days = in_array($days, [7, 14, 30, 90, 365], true) ? $days : 30;

        view('stats/link', [
            'pageTitle' => 'Thống kê /' . $link['code'],
            'user' => $user,
            'link' => $link,
            'state' => LinkService::state($link),
            'days' => $days,
            'series' => Stats::dailySeries($days, $linkId),
            'hourly' => Stats::hourly($linkId),
            'weekday' => Stats::weekday($linkId),
            'devices' => Stats::breakdown('device', $linkId, null, 4),
            'browsers' => Stats::breakdown('browser', $linkId, null, 8),
            'systems' => Stats::breakdown('os', $linkId, null, 8),
            'referers' => Stats::breakdown('referer_host', $linkId, null, 8),
            'countries' => Stats::breakdown('country', $linkId, null, 8),
            'sources' => Stats::breakdown('source', $linkId, null, 6),
            'recentClicks' => Stats::recentClicks($linkId, 25),
            'qrClicks' => Stats::qrClicks($linkId),
            'botClicks' => Stats::botClicks($linkId),
            'busiestDay' => Stats::busiestDay($linkId),
        ]);
    }
}
