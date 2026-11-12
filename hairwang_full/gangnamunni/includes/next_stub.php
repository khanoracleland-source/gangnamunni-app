<?php
/**
 * What is left of a retired patient page (index.php, hospitals.php, detail.php … — the pages the app and ordinary visitors used
 * before the Next.js site): the file itself is only `require __DIR__ . '/includes/next_stub.php';`.
 *
 * Every GET goes to the Next.js page that replaced it, by the same table as includes/next_redirect.php. Unlike that file this
 * one never falls through to PHP (there is no PHP page any more): when the query has something the table does not know, the
 * visitor lands on the page's own start (detail.php?id=x → /next/hospitals) instead of an error. Any other method (the old
 * forms and AJAX calls of the removed pages) is answered with 410.
 *
 * data/next_redirect.on is no longer a switch here: it only chooses 301 (content "301") or 302 for an exact match.
 */
require_once __DIR__ . '/next_redirect.php'; // the table; with the flag file present a plain page load is already redirected in there

if (!function_exists('sb_next_stub')) {

    /** Where a link that the table cannot translate goes: the list the page belonged to. */
    function sb_next_stub_fallback($script)
    {
        $map = array(
            'detail.php' => '/hospitals', 'booking.php' => '/hospitals',
            'event_detail.php' => '/events',
            'detail_community.php' => '/community', 'community_write.php' => '/community',
            'booking_change.php' => '/mypage/bookings', 'booking_cancel_request.php' => '/mypage/bookings',
            'booking_cancel_complete.php' => '/mypage/bookings', 'booking_cancel_detail.php' => '/mypage/bookings',
            'booking_request_detail.php' => '/mypage/bookings', 'booking_payment.php' => '/mypage/bookings',
            'booking_payment_success.php' => '/mypage/bookings', 'my_bookings.php' => '/mypage/bookings',
            'my_consultation_detail.php' => '/mypage/consultations',
            'review_detail.php' => '/mypage/reviews', 'review_write.php' => '/mypage/reviews', 'my_reviews.php' => '/mypage/reviews',
            'progress_review.php' => '/mypage/progress',
            'my_points_policy_detail.php' => '/points-policy', 'my_points_policy.php' => '/points-policy',
            'my_notifications.php' => '/mypage/notifications',
            'hospitals.php' => '/hospitals', 'events.php' => '/events', 'community.php' => '/community', 'faq.php' => '/faq',
        );
        if (isset($map[$script])) return '/next' . $map[$script];
        $bare = sb_next_redirect_target($script, array()); // pages without parameters (mypage.php, qna.php …)
        return $bare !== null ? $bare : '/next';
    }

    function sb_next_stub()
    {
        if (PHP_SAPI === 'cli' || headers_sent()) return;
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            http_response_code(410);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(array('ok' => false, 'success' => false, 'message' => '이 페이지는 더 이상 사용되지 않습니다. 앱을 다시 실행해주세요.'), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $script = sb_next_redirect_script(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
        // php=1 used to mean "keep the PHP page"; app_logout=1 is the app's signed-out marker and is carried over to the Next.js page
        $get = $_GET;
        $logout = !empty($get['app_logout']);
        unset($get['php'], $get['app_logout']);

        $target = sb_next_redirect_target($script, $get);
        $exact = $target !== null;
        if (!$exact) {
            // an extra, unknown parameter must not lose the item: detail.php?id=5&x=1 still opens hospital 5
            $ids = array_intersect_key($get, array('id' => 1, 'booking_id' => 1));
            $target = $ids ? sb_next_redirect_target($script, $ids) : null;
            if ($target === null) $target = sb_next_stub_fallback($script);
        }
        if ($logout) $target .= (strpos($target, '?') === false ? '?' : '&') . 'app_logout=1';

        $permanent = $exact && !$logout && trim((string)@file_get_contents(__DIR__ . '/../../data/next_redirect.on')) === '301';
        header('Cache-Control: ' . ($permanent ? 'public, max-age=3600' : 'no-store'));
        header('Location: ' . $target, true, $permanent ? 301 : 302);
        exit;
    }
}
sb_next_stub();
