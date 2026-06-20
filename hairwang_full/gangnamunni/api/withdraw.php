<?php
require_once __DIR__ . '/bootstrap.php';
requireMethod('POST');
$authUser = getAuthUser(true);
requireCsrf();

$body = getJsonBody();
$reason = sanitizeInput($body['reason'] ?? '');
$password = $body['password'] ?? '';

$user_id = $authUser['user_id'];
if (empty($user_id)) jsonError('로그인이 필요합니다.', 401);

$db = Database::getInstance();

// g5_member에서 회원 확인
$member = $db->fetchOne("SELECT mb_id, mb_password, mb_nick, mb_email FROM g5_member WHERE mb_id = ?", [$user_id]);
if (!$member) jsonError('회원 정보를 찾을 수 없습니다.', 404);

// 비밀번호 확인
if (!password_verify($password, $member['mb_password'])) {
    jsonError('비밀번호가 일치하지 않습니다.');
}

try {
    $pdo = $db->getPdo();
    $pdo->beginTransaction();

    // 1. 탈퇴 사유 기록
    $db->execute(
        "INSERT INTO gangnamunni_withdrawals (mb_id, mb_nick, mb_email, reason, withdrawn_at) VALUES (?, ?, ?, ?, NOW())",
        [$user_id, $member['mb_nick'], $member['mb_email'], $reason]
    );

    // 2. g5_member 탈퇴 처리 (mb_leave_dt 설정)
    $db->execute(
        "UPDATE g5_member SET mb_leave_dt = DATE_FORMAT(NOW(), '%Y%m%d'), mb_memo = CONCAT(IFNULL(mb_memo,''), '\n[탈퇴] ', ?) WHERE mb_id = ?",
        [$reason, $user_id]
    );

    // 3. member_sync에서 HMS user도 비활성화 표시
    $sync = $db->fetchOne("SELECT hms_user_id FROM member_sync WHERE mb_id = ?", [$user_id]);
    if ($sync && !empty($sync['hms_user_id'])) {
        $db->execute("UPDATE users SET status = 0 WHERE id = ?", [$sync['hms_user_id']]);
    }

    // 4. SSO 토큰 삭제
    $db->execute("DELETE FROM sso_tokens WHERE g5_mb_id = ?", [$user_id]);

    $pdo->commit();

    // 세션 파괴
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // SSO 쿠키 삭제
    setcookie('sb_sso_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    jsonSuccess(['message' => '회원 탈퇴가 완료되었습니다. 이용해주셔서 감사합니다.']);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("[Withdraw] Error: " . $e->getMessage());
    jsonError('탈퇴 처리 중 오류가 발생했습니다.');
}
