<?php
/**
 * 리뷰어 통계 API
 *
 * - action=get         : 특정 리뷰어 통계 조회
 * - action=top         : 신뢰도 상위 리뷰어 목록
 * - action=update      : 특정 리뷰어 통계 갱신
 * - action=update_all  : 전체 리뷰어 통계 일괄 갱신
 * - action=card_html   : 리뷰어 프로필 카드 HTML
 */
session_start();

require_once __DIR__ . '/../includes/reviewer_stats.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'get':
        $userName = $_GET['user_name'] ?? '';
        if (empty($userName)) {
            echo json_encode(['success' => false, 'error' => 'user_name required']);
            break;
        }
        $stats = getReviewerStats($connect, $userName);
        if ($stats) {
            $stats['badge_html'] = renderBadge($stats['badge'] ?? 'newbie');
            echo json_encode(['success' => true, 'stats' => $stats]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Reviewer not found']);
        }
        break;

    case 'top':
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $reviewers = getTopReviewers($connect, $limit);
        foreach ($reviewers as &$r) {
            $r['badge_html'] = renderBadge($r['badge'] ?? 'newbie');
        }
        unset($r);
        echo json_encode(['success' => true, 'reviewers' => $reviewers]);
        break;

    case 'update':
        // 관리자만
        if (empty($_SESSION['ad_admin_logged_in'])) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            break;
        }
        $userName = $_POST['user_name'] ?? '';
        if (empty($userName)) {
            echo json_encode(['success' => false, 'error' => 'user_name required']);
            break;
        }
        $result = updateReviewerStats($connect, $userName);
        echo json_encode($result);
        break;

    case 'update_all':
        // super_admin만
        if (empty($_SESSION['ad_admin_logged_in']) || ($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'error' => 'Super admin only']);
            break;
        }
        $result = updateAllReviewerStats($connect);
        echo json_encode(['success' => true, 'result' => $result]);
        break;

    case 'card_html':
        $userName = $_GET['user_name'] ?? '';
        if (empty($userName)) {
            echo json_encode(['success' => false, 'error' => 'user_name required']);
            break;
        }
        $stats = getReviewerStats($connect, $userName);
        if ($stats) {
            echo json_encode(['success' => true, 'html' => renderReviewerCard($stats)]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Reviewer not found']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
