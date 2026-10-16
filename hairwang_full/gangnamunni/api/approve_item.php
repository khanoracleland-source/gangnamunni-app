<?php
/**
 * 통합 승인/거부 API
 * POST: { type: hospital|ad|review, id: int, action: approve|reject|pending, reason: string }
 *
 * 대시보드 승인 대기 큐 + 관리 페이지에서 AJAX 호출
 */
session_start();
require_once dirname(__DIR__) . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인 필요']);
    exit;
}

require_once dirname(__DIR__) . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['ad_admin_role'] ?? 'viewer';
if ($role === 'viewer') {
    echo json_encode(['success' => false, 'error' => '권한 없음']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$type = $input['type'] ?? '';
$id = intval($input['id'] ?? 0);
$action = $input['action'] ?? '';
$reason = trim($input['reason'] ?? '');

if (!$type || !$id || !$action) {
    echo json_encode(['success' => false, 'error' => '필수 파라미터 누락']);
    exit;
}

if (!in_array($action, ['approve', 'reject', 'pending'])) {
    echo json_encode(['success' => false, 'error' => '잘못된 action']);
    exit;
}

$admin_id = intval($_SESSION['ad_admin_id'] ?? 0);
$status_map = [
    'approve' => 'approved',
    'reject' => 'rejected',
    'pending' => 'pending',
];
$new_status = $status_map[$action];

try {
    switch ($type) {
        case 'hospital':
            $is_active = ($action === 'approve') ? 1 : 0;
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET status = ?, is_active = ?, reviewed_by = ?, reviewed_at = NOW(), reject_reason = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'siisi', $new_status, $is_active, $admin_id, $reason, $id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected > 0) {
                echo json_encode(['success' => true, 'message' => '병원 상태가 변경되었습니다.', 'status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'error' => '병원을 찾을 수 없습니다.']);
            }
            break;

        case 'ad':
            $is_active = ($action === 'approve') ? 1 : 0;
            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_ads SET status = ?, is_active = ?, reviewed_by = ?, reviewed_at = NOW(), reject_reason = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'siisi', $new_status, $is_active, $admin_id, $reason, $id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected > 0) {
                echo json_encode(['success' => true, 'message' => '광고 상태가 변경되었습니다.', 'status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'error' => '광고를 찾을 수 없습니다.']);
            }
            break;

        case 'review':
            $stmt = mysqli_prepare($connect,
                "UPDATE hospital_reviews SET review_status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected > 0) {
                echo json_encode(['success' => true, 'message' => '후기 상태가 변경되었습니다.', 'status' => $new_status]);
            } else {
                echo json_encode(['success' => false, 'error' => '후기를 찾을 수 없습니다.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => '지원하지 않는 타입: ' . $type]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '처리 중 오류 발생']);
}

mysqli_close($connect);
