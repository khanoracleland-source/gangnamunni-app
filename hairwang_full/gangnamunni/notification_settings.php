<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';
if (!is_gangnamunni_logged_in()) { header('Location: auth/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();

$csrf_token = generateCsrfToken();
$member_id_escaped = mysqli_real_escape_string($connect, (string)$member_id);
$social_login_result = mysqli_query(
    $connect,
    "SELECT provider, identifier FROM g5_member_social_profiles WHERE mb_id = '{$member_id_escaped}' LIMIT 1"
);
$social_login_profile = $social_login_result ? mysqli_fetch_assoc($social_login_result) : null;
$is_social_member = !empty($social_login_profile);
$editable_fields = ['noti_general','noti_message','noti_booking','noti_event','noti_promo','noti_push','mkt_push','mkt_sms','mkt_email','mkt_night_push'];

@mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id VARCHAR(100) NOT NULL UNIQUE,
  noti_booking TINYINT(1) NOT NULL DEFAULT 1,
  noti_chat TINYINT(1) NOT NULL DEFAULT 1,
  noti_event TINYINT(1) NOT NULL DEFAULT 1,
  noti_promo TINYINT(1) NOT NULL DEFAULT 1,
  noti_review TINYINT(1) NOT NULL DEFAULT 1,
  noti_push TINYINT(1) NOT NULL DEFAULT 1,
  noti_general TINYINT(1) NOT NULL DEFAULT 1,
  noti_message TINYINT(1) NOT NULL DEFAULT 1,
  mkt_push TINYINT(1) NOT NULL DEFAULT 0,
  mkt_sms TINYINT(1) NOT NULL DEFAULT 0,
  mkt_email TINYINT(1) NOT NULL DEFAULT 0,
  mkt_night_push TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
@mysqli_query($connect, "ALTER TABLE gangnamunni_notification_settings
  ADD COLUMN IF NOT EXISTS noti_booking TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS noti_event TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS noti_promo TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS noti_general TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS noti_message TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS noti_push TINYINT(1) NOT NULL DEFAULT 1");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = (string)($_POST['action'] ?? '');
    $field = (string)($_POST['field'] ?? '');
    $value = (string)($_POST['value'] ?? '') === '1' ? 1 : 0;
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => '요청이 만료되었습니다.']); exit;
    }

    if ($action === 'withdraw_social') {
        $member_stmt = mysqli_prepare($connect,
            "SELECT mb_no, mb_id, mb_nick, mb_email FROM g5_member WHERE mb_id = ? LIMIT 1");
        mysqli_stmt_bind_param($member_stmt, 's', $member_id);
        mysqli_stmt_execute($member_stmt);
        $member_row = mysqli_fetch_assoc(mysqli_stmt_get_result($member_stmt));
        mysqli_stmt_close($member_stmt);
        if (!$member_row) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => '회원 정보를 찾을 수 없습니다.']); exit;
        }

        // Kakao consent must be withdrawn before local PII is deleted. If
        // unlink fails, stop here so the UI never reports a false withdrawal.
        if (strtolower((string)($social_login_profile['provider'] ?? '')) === 'kakao') {
            $unlink = unlinkKakaoAccountForWithdrawal((string)($social_login_profile['identifier'] ?? ''));
            if (empty($unlink['ok'])) {
                error_log('[Withdraw settings] ' . ($unlink['message'] ?? 'Kakao unlink failed'));
                http_response_code(502);
                echo json_encode([
                    'ok' => false,
                    'message' => '카카오 계정 연결 해제에 실패했습니다. 다시 로그인한 후 재시도해주세요.'
                ]); exit;
            }
        }

        mysqli_begin_transaction($connect);
        try {
            // Some service tables use the numeric mb_no while others use the
            // Kakao-derived mb_id. Remove both forms so a later Kakao signup
            // cannot inherit the withdrawn account's data.
            $delete_by = function ($table, $column, $value, $type = 's') use ($connect) {
                $column_stmt = mysqli_prepare($connect,
                    "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
                mysqli_stmt_bind_param($column_stmt, 'ss', $table, $column);
                mysqli_stmt_execute($column_stmt);
                $exists = mysqli_stmt_get_result($column_stmt)->num_rows > 0;
                mysqli_stmt_close($column_stmt);
                if (!$exists) return;

                // Identifiers come exclusively from the hard-coded maps below.
                $stmt = mysqli_prepare($connect, "DELETE FROM `{$table}` WHERE `{$column}` = ?");
                mysqli_stmt_bind_param($stmt, $type, $value);
                if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
            };

            $sync_stmt = mysqli_prepare($connect,
                "SELECT hms_user_id FROM member_sync WHERE mb_no = ? OR mb_id = ? LIMIT 1");
            mysqli_stmt_bind_param($sync_stmt, 'is', $member_row['mb_no'], $member_row['mb_id']);
            mysqli_stmt_execute($sync_stmt);
            $sync_row = mysqli_fetch_assoc(mysqli_stmt_get_result($sync_stmt));
            mysqli_stmt_close($sync_stmt);
            if ($sync_row && !empty($sync_row['hms_user_id'])) {
                $hms_user_id = (int)$sync_row['hms_user_id'];
                $status_stmt = mysqli_prepare($connect, "UPDATE users SET status = 0 WHERE id = ?");
                mysqli_stmt_bind_param($status_stmt, 'i', $hms_user_id);
                if (!mysqli_stmt_execute($status_stmt)) throw new RuntimeException(mysqli_stmt_error($status_stmt));
                mysqli_stmt_close($status_stmt);
            }

            $mb_no = (int)$member_row['mb_no'];
            $mb_id = (string)$member_row['mb_id'];

            // Data keyed by the numeric Gnuboard member number.
            foreach ([
                ['gangnamunni_wishlist', 'member_id'],
                ['gangnamunni_points', 'member_id'],
                ['gangnamunni_user_actions', 'member_id'],
                ['gangnamunni_coupon_claims', 'member_id'],
                ['gangnamunni_chat_rooms', 'member_id'],
                ['gangnamunni_content_reports', 'reported_user_id'],
                ['gangnamunni_members', 'mb_no'],
            ] as $target) {
                $delete_by($target[0], $target[1], $mb_no, 'i');
            }

            // Data keyed by the stable social/Gnuboard login id.
            foreach ([
                ['gangnamunni_notification_settings', 'member_id'],
                ['gangnamunni_user_agreements', 'user_id'],
                ['gangnamunni_user_notifications', 'member_id'],
                ['gangnamunni_bookings', 'mb_id'],
                ['hospital_reviews', 'user_id'],
                ['gangnamunni_community_comments', 'mb_id'],
                ['gangnamunni_community_likes', 'user_id'],
                ['gangnamunni_community_posts', 'mb_id'],
                ['gangnamunni_members', 'mb_id'],
                ['gangnamunni_withdrawals', 'mb_id'],
                ['g5_point', 'mb_id'],
                ['g5_scrap', 'mb_id'],
                ['g5_memo', 'me_recv_mb_id'],
                ['g5_memo', 'me_send_mb_id'],
            ] as $target) {
                $delete_by($target[0], $target[1], $mb_id, 's');
            }

            $sync_delete_stmt = mysqli_prepare($connect,
                "DELETE FROM member_sync WHERE mb_no = ? OR mb_id = ?");
            mysqli_stmt_bind_param($sync_delete_stmt, 'is', $member_row['mb_no'], $member_row['mb_id']);
            if (!mysqli_stmt_execute($sync_delete_stmt)) throw new RuntimeException(mysqli_stmt_error($sync_delete_stmt));
            mysqli_stmt_close($sync_delete_stmt);

            $token_delete_stmt = mysqli_prepare($connect, "DELETE FROM sso_tokens WHERE g5_mb_id = ?");
            mysqli_stmt_bind_param($token_delete_stmt, 's', $member_row['mb_id']);
            if (!mysqli_stmt_execute($token_delete_stmt)) throw new RuntimeException(mysqli_stmt_error($token_delete_stmt));
            mysqli_stmt_close($token_delete_stmt);

            $social_delete_stmt = mysqli_prepare($connect,
                "DELETE FROM g5_member_social_profiles WHERE mb_id = ?");
            mysqli_stmt_bind_param($social_delete_stmt, 's', $member_row['mb_id']);
            if (!mysqli_stmt_execute($social_delete_stmt)) throw new RuntimeException(mysqli_stmt_error($social_delete_stmt));
            mysqli_stmt_close($social_delete_stmt);

            if (!empty($member_row['mb_email'])) {
                $admin_stmt = mysqli_prepare($connect,
                    "DELETE FROM gangnamunni_admins WHERE (username = ? OR email = ?) AND role IN ('hospital_super_admin','hospital_admin','hospital_sub_admin','hospital_staff')");
                mysqli_stmt_bind_param($admin_stmt, 'ss', $member_row['mb_email'], $member_row['mb_email']);
                if (!mysqli_stmt_execute($admin_stmt)) throw new RuntimeException(mysqli_stmt_error($admin_stmt));
                mysqli_stmt_close($admin_stmt);
            }

            $delete_stmt = mysqli_prepare($connect, "DELETE FROM g5_member WHERE mb_id = ?");
            mysqli_stmt_bind_param($delete_stmt, 's', $member_row['mb_id']);
            if (!mysqli_stmt_execute($delete_stmt) || mysqli_stmt_affected_rows($delete_stmt) !== 1) {
                throw new RuntimeException(mysqli_stmt_error($delete_stmt) ?: 'member_not_deleted');
            }
            mysqli_stmt_close($delete_stmt);

            $verify_stmt = mysqli_prepare($connect,
                "SELECT
                    (SELECT COUNT(*) FROM g5_member WHERE mb_id = ?) AS members,
                    (SELECT COUNT(*) FROM g5_member_social_profiles WHERE mb_id = ?) AS social_profiles,
                    (SELECT COUNT(*) FROM sso_tokens WHERE g5_mb_id = ?) AS tokens");
            mysqli_stmt_bind_param($verify_stmt, 'sss', $mb_id, $mb_id, $mb_id);
            mysqli_stmt_execute($verify_stmt);
            $remaining = mysqli_fetch_assoc(mysqli_stmt_get_result($verify_stmt));
            mysqli_stmt_close($verify_stmt);
            if (array_sum(array_map('intval', $remaining ?: [])) !== 0) {
                throw new RuntimeException('withdrawal_verification_failed');
            }
            mysqli_commit($connect);

            // Profile photos use the stable mb_id as the filename, so leaving
            // one behind would make a later Kakao signup look like the old account.
            $profile_file_id = preg_replace('/[^A-Za-z0-9_-]/', '', $mb_id);
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
                $profile_path = __DIR__ . '/uploads/profile/' . $profile_file_id . '.' . $extension;
                if ($profile_file_id !== '' && is_file($profile_path)) @unlink($profile_path);
            }

            unset($_SESSION['ss_mb_id']);
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            foreach (['sb_sso_token', md5('ck_mb_id'), md5('ck_auto')] as $cookie_name) {
                setcookie($cookie_name, '', time() - 3600, '/');
            }

            echo json_encode(['ok' => true, 'message' => '회원 탈퇴가 완료되었습니다.']); exit;
        } catch (Throwable $e) {
            mysqli_rollback($connect);
            error_log('[Withdraw settings] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => '탈퇴 처리 중 오류가 발생했습니다.']); exit;
        }
    }

    if (!in_array($field, $editable_fields, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'message' => '허용되지 않은 설정입니다.']); exit;
    }
    // 컬럼명은 위 화이트리스트를 통과한 값만 사용한다.
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_notification_settings (member_id, {$field}) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE {$field} = VALUES({$field})");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => '설정 저장 준비에 실패했습니다.']); exit;
    }
    mysqli_stmt_bind_param($stmt, 'si', $member_id, $value);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) http_response_code(500);
    echo json_encode(['ok' => (bool)$ok, 'field' => $field, 'value' => $value]); exit;
}

$settings = ['noti_general'=>1,'noti_message'=>1,'noti_booking'=>1,'noti_event'=>1,'noti_promo'=>1,'noti_push'=>1,'mkt_push'=>0,'mkt_sms'=>0,'mkt_email'=>0,'mkt_night_push'=>0];
$stmt_get = mysqli_prepare($connect, "SELECT noti_general,noti_message,noti_booking,noti_event,noti_promo,noti_push,mkt_push,mkt_sms,mkt_email,mkt_night_push FROM gangnamunni_notification_settings WHERE member_id=?");
if ($stmt_get) {
    mysqli_stmt_bind_param($stmt_get, 's', $member_id);
    mysqli_stmt_execute($stmt_get);
    $res = mysqli_stmt_get_result($stmt_get);
    if ($res && $row = mysqli_fetch_assoc($res)) $settings = array_merge($settings, $row);
    mysqli_stmt_close($stmt_get);
}
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>알림 설정 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{position:relative;padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{position:absolute;left:50%;transform:translateX(-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.setting-item{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);}
.setting-item label{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
.toggle{position:relative;width:44px;height:24px;}
.toggle input{opacity:0;width:0;height:0;}
.toggle .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s;}
.toggle input:checked+.slider{background:var(--pri);}
.toggle .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
.toggle input:checked+.slider:before{transform:translateX(20px);}
.section-label{padding:16px 20px 8px;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:var(--bs-secondary-color,#888);}
.service-item{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);text-decoration:none;color:var(--bs-body-color);}
.service-item span{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
.service-item.red span{color:var(--pri);}
.service-item i{color:var(--bs-secondary-color,#ccc);font-size:14px;}
.service-section{background:#fff;margin:10px 16px;overflow:hidden;}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>알림</h1></div>
<div class="service-section">
    <div class="section-label">서비스 안내</div>
    <a href="qna.php" class="service-item red"><span>제휴 및 광고문의</span></a>
    <a href="<?php echo htmlspecialchars(G5_BBS_URL . '/register.php?view=terms', ENT_QUOTES); ?>" class="service-item"><span>이용약관 및 개인정보처리 방침</span></a>
</div>

<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>">

<div class="service-section">
    <div class="section-label">알림 설정</div>
    <div class="setting-item">
        <div><label>일반 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_general" value="1" <?php echo ($settings['noti_general'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>예약 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_booking" value="1" <?php echo ($settings['noti_booking'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>이벤트/추첨 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_event" value="1" <?php echo ($settings['noti_event'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>프로모션 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_promo" value="1" <?php echo ($settings['noti_promo'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>푸시 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_push" value="1" <?php echo ($settings['noti_push'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item" style="border-bottom:none;">
        <div><label>쪽지 알림</label></div>
        <label class="toggle"><input type="checkbox" name="noti_message" value="1" <?php echo ($settings['noti_message'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
</div>

<div class="service-section">
    <div class="section-label">마케팅 정보 수신</div>
    <div class="setting-item">
        <div><label>푸시</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_push" value="1" <?php echo ($settings['mkt_push'] ?? 0) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>SMS</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_sms" value="1" <?php echo ($settings['mkt_sms'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item">
        <div><label>이메일</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_email" value="1" <?php echo ($settings['mkt_email'] ?? 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
    <div class="setting-item" style="border-bottom:none;">
        <div><label>야간 푸시 (오후 9시 - 오전 8시)</label></div>
        <label class="toggle"><input type="checkbox" name="mkt_night_push" value="1" <?php echo ($settings['mkt_night_push'] ?? 0) ? 'checked' : ''; ?>><span class="slider"></span></label>
    </div>
</div>

</form>

<div id="toast" style="display:none;position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:8px 18px;border-radius:20px;font-size:13px;z-index:9999;">저장되었습니다</div>

<script>
document.querySelectorAll('form input[type="checkbox"]').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var changed = this;
        var previous = !changed.checked;
        var data = new FormData();
        data.set('csrf_token', document.querySelector('[name="csrf_token"]').value);
        data.set('field', changed.name);
        data.set('value', changed.checked ? '1' : '0');
        changed.disabled = true;
        fetch(location.pathname + location.search, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function(result) {
                if (!result.ok) throw new Error(result.message || 'save_failed');
                var toast = document.getElementById('toast');
                toast.style.display = 'block';
                setTimeout(function() { toast.style.display = 'none'; }, 1500);
            })
            .catch(function() {
                changed.checked = previous;
                alert('설정 저장에 실패했습니다. 다시 시도해주세요.');
            })
            .finally(function() { changed.disabled = false; });
    });
});
</script>

<?php
// 마이페이지 하단에 있던 로그아웃 / 회원 탈퇴 를 이곳 "계정 관리" 로 옮겼다.
// logout.php 는 url 파라미터에 스킴/도메인이 있으면 "도메인 지정 불가" 로 거부하므로
// 도메인을 뺀 경로만 전달한다(베이스 경로 유지). 앱 WebView 는 app=1 을 유지해야 정상 복귀한다.
$is_app_mode   = isset($_GET['app']) && $_GET['app'] == '1';
$logout_return = rtrim(SITE_BASE_PATH, '/') . '/index.php' . ($is_app_mode ? '?app=1' : '');
$logout_url    = G5_BBS_URL . '/logout.php?url=' . urlencode($logout_return);
$withdraw_url  = rtrim(SITE_BASE_PATH, '/') . '/withdraw.php';
$withdraw_success_url = rtrim(SITE_BASE_PATH, '/') . '/index.php' . ($is_app_mode ? '?app=1' : '');
// 바텀시트 문구용 — 로그인한 본인의 이름. 닉네임 > 이름 > 아이디 순으로 사용한다.
$sheet_member  = function_exists('get_gangnamunni_member') ? get_gangnamunni_member() : ($member ?? []);
$sheet_nick    = '';
foreach (['mb_nick', 'mb_name', 'mb_id'] as $_k) {
    if (!empty($sheet_member[$_k])) { $sheet_nick = trim((string) $sheet_member[$_k]); break; }
}
if ($sheet_nick === '') $sheet_nick = '회원';
?>
<div class="service-section" style="margin-top:10px;margin-bottom:30px;">
    <div class="section-label">계정 관리</div>
    <?php // href 는 JS 미동작 시의 폴백 — 평소에는 바텀시트로 한 번 더 확인받는다. ?>
    <a href="<?php echo htmlspecialchars($logout_url, ENT_QUOTES); ?>" class="service-item" id="btnLogout">
        <span>로그아웃</span>
        <i class="bi bi-chevron-right"></i>
    </a>
    <a href="<?php echo htmlspecialchars($withdraw_url, ENT_QUOTES); ?>" class="service-item red" style="border-bottom:none;" id="btnWithdraw">
        <span>회원 탈퇴</span>
        <i class="bi bi-chevron-right"></i>
    </a>
</div>

<?php include __DIR__ . '/includes/bottom_sheet.php'; ?>
<script>
(function () {
    var LOGOUT_URL   = <?php echo json_encode($logout_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var WITHDRAW_URL = <?php echo json_encode($withdraw_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var WITHDRAW_SUCCESS_URL = <?php echo json_encode($withdraw_success_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    var WITHDRAW_CSRF = <?php echo json_encode($csrf_token); ?>;
    var IS_SOCIAL_MEMBER = <?php echo $is_social_member ? 'true' : 'false'; ?>;
    var NICK         = <?php echo json_encode($sheet_nick, JSON_UNESCAPED_UNICODE); ?>;

    function deleteSocialAccount() {
        var button = document.getElementById('btnWithdraw');
        button.style.pointerEvents = 'none';
        button.style.opacity = '0.55';

        var withdrawData = new FormData();
        withdrawData.set('action', 'withdraw_social');
        withdrawData.set('csrf_token', WITHDRAW_CSRF);

        fetch(location.pathname + location.search, {
            method: 'POST',
            credentials: 'same-origin',
            body: withdrawData
        })
        .then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || !data.ok) throw new Error(data.message || '탈퇴 처리에 실패했습니다.');
                return data;
            });
        })
        .then(function (data) {
            alert(data.message || '탈퇴가 완료되었습니다.');
            try { localStorage.clear(); } catch (e) {}
            try { sessionStorage.clear(); } catch (e) {}
            window.location.replace(WITHDRAW_SUCCESS_URL);
        })
        .catch(function (error) {
            alert(error.message || '탈퇴 처리에 실패했습니다.');
            button.style.pointerEvents = '';
            button.style.opacity = '';
        });
    }

    document.getElementById('btnLogout').addEventListener('click', function (e) {
        e.preventDefault();
        SMSheet.open({
            title:       '잠시 로그아웃하시겠어요?',
            confirmText: '로그아웃',
            cancelText:  '아니오',
            onConfirm:   function () { location.href = LOGOUT_URL; }
        });
    });

    document.getElementById('btnWithdraw').addEventListener('click', function (e) {
        e.preventDefault();
        SMSheet.open({
            title:       NICK + '님 정말 떠나시겠어요?\n서울미녀를 떠나시게 되어 아쉬워요 ㅠㅠ',
            desc:        '탈퇴 후에는 일부 정보가 복구되지 않을 수 있어요',
            confirmText: '탈퇴하기',
            cancelText:  '아니오',
            // The settings page already has an authenticated Gnuboard/SSO
            // session. Perform the verified hard-delete here for every account.
            onConfirm:   function () {
                deleteSocialAccount();
            }
        });
    });
})();
</script>
</body></html>
