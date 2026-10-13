<?php
// Load the same Gnuboard session name/cookie and SSO fallback used by the
// account-settings page before the JSON API checks ss_mb_id.
require_once dirname(__DIR__) . '/includes/init.php';
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
$member = $db->fetchOne("SELECT mb_no, mb_id, mb_password, mb_nick, mb_email FROM g5_member WHERE mb_id = ?", [$user_id]);
if (!$member) jsonError('회원 정보를 찾을 수 없습니다.', 404);

$social_profile = $db->fetchOne(
    "SELECT mp_no, provider, identifier FROM g5_member_social_profiles WHERE mb_id = ? LIMIT 1",
    [$user_id]
);

// 비밀번호 확인 — g5_member 비밀번호는 그누보드 방식(PBKDF2 create_hash)이므로
// password_verify(bcrypt)로는 항상 실패한다. app_login.php 와 동일하게 validate_password 사용.
// Kakao/Google 회원은 서비스 비밀번호가 없으므로 인증된 세션 + CSRF로 확인한다.
if (!$social_profile && !validate_password($password, $member['mb_password'])) {
    jsonError('비밀번호가 일치하지 않습니다.');
}

if (strtolower((string)($social_profile['provider'] ?? '')) === 'kakao') {
    $unlink = unlinkKakaoAccountForWithdrawal((string)($social_profile['identifier'] ?? ''));
    if (empty($unlink['ok'])) {
        error_log('[Withdraw API] ' . ($unlink['message'] ?? 'Kakao unlink failed'));
        jsonError('카카오 계정 연결 해제에 실패했습니다. 다시 로그인한 후 재시도해주세요.', 502);
    }
}

try {
    $pdo = $db->getPdo();
    $pdo->beginTransaction();

    // 1. 탈퇴 사유 기록
    $db->execute(
        "INSERT INTO gangnamunni_withdrawals (mb_id, mb_nick, mb_email, reason, withdrawn_at) VALUES (?, ?, ?, ?, NOW())",
        [$user_id, $member['mb_nick'], $member['mb_email'], $reason]
    );

    // 2. 이 회원에 명시적으로 연결(g5_mb_id)된 병원 관계자 계정만 함께 삭제한다(플랫폼 관리자 제외).
    //    이메일/아이디 일치만으로 지우면 병원 이메일로 가입한 회원이 탈퇴해 병원 관리자 계정을 지울 수 있다.
    if ($db->fetchOne("SHOW COLUMNS FROM gangnamunni_admins LIKE 'g5_mb_id'")) {
        $db->execute(
            "DELETE FROM gangnamunni_admins WHERE g5_mb_id = ? AND role IN ('hospital_super_admin','hospital_admin','hospital_sub_admin','hospital_staff')",
            [$user_id]
        );
    }

    // 3. member_sync에서 HMS user도 비활성화 표시
    $sync = $db->fetchOne("SELECT hms_user_id FROM member_sync WHERE mb_id = ?", [$user_id]);
    if ($sync && !empty($sync['hms_user_id'])) {
        $db->execute("UPDATE users SET status = 0 WHERE id = ?", [$sync['hms_user_id']]);
    }

    // 매핑을 남겨두면 동기화 시 회원이 다시 생성될 수 있다.
    $db->execute("DELETE FROM member_sync WHERE mb_no = ? OR mb_id = ?", [$member['mb_no'], $user_id]);

    // 4. SSO 토큰 삭제
    $db->execute("DELETE FROM sso_tokens WHERE g5_mb_id = ?", [$user_id]);

    // 5. SNS 연결 정보와 Gnuboard 회원 본체를 완전 삭제한다.
    $db->execute("DELETE FROM g5_member_social_profiles WHERE mb_id = ?", [$user_id]);
    $deleted = $db->execute("DELETE FROM g5_member WHERE mb_id = ?", [$user_id]);
    if ($deleted->rowCount() < 1) {
        throw new RuntimeException('Member row was not deleted.');
    }

    $pdo->commit();

    // 세션 파괴
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    foreach (['sb_sso_token', md5('ck_mb_id'), md5('ck_auto')] as $cookie_name) {
        setcookie($cookie_name, '', [
            'expires' => time() - 3600, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true, 'samesite' => 'Lax'
        ]);
    }

    jsonSuccess(['message' => '회원 탈퇴가 완료되었습니다. 이용해주셔서 감사합니다.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("[Withdraw] Error: " . $e->getMessage());
    jsonError('탈퇴 처리 중 오류가 발생했습니다.');
}
