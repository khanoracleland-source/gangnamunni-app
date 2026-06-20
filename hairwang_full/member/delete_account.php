<?php
/**
 * 계정 삭제 요청 페이지
 *
 * Google Play Store 데이터 안전 섹션 요구사항 충족을 위한 계정 삭제 페이지
 *
 * URL: https://hairwang.com/member/delete_account.php
 *
 * [기능]
 * - 로그인한 회원: 비밀번호 확인 후 즉시 계정 탈퇴 처리
 * - 비로그인 사용자: 계정 삭제 절차 안내
 *
 * [삭제되는 데이터]
 * - 회원 계정 정보 (아이디, 이메일, 전화번호 등)
 * - 푸시 알림 토큰
 * - 앱 사용 기록
 *
 * [보관되는 데이터]
 * - 법적 의무에 따른 거래 기록 (전자상거래법에 따라 5년 보관)
 *
 * @author  Developer
 * @version 1.0.0
 * @since   2025-12-09
 */

include_once('../common.php');

// 페이지 타이틀 설정
$g5['title'] = '계정 삭제';

// CSS 스타일
$delete_account_css = '
<style>
.delete-account-container {
    max-width: 600px;
    margin: 40px auto;
    padding: 30px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
.delete-account-container h1 {
    color: #333;
    font-size: 24px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e74c3c;
}
.delete-account-container h2 {
    color: #555;
    font-size: 18px;
    margin: 25px 0 15px 0;
}
.delete-account-container p {
    color: #666;
    line-height: 1.7;
    margin-bottom: 15px;
}
.delete-account-container ul {
    padding-left: 20px;
    margin-bottom: 20px;
}
.delete-account-container li {
    color: #666;
    line-height: 1.8;
    margin-bottom: 8px;
}
.warning-box {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 15px 20px;
    margin: 20px 0;
}
.warning-box strong {
    color: #856404;
}
.info-box {
    background: #e7f3ff;
    border: 1px solid #b6d4fe;
    border-radius: 8px;
    padding: 15px 20px;
    margin: 20px 0;
}
.info-box strong {
    color: #084298;
}
.delete-form {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 8px;
    margin-top: 30px;
}
.delete-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}
.delete-form input[type="password"] {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    margin-bottom: 15px;
    box-sizing: border-box;
}
.delete-form input[type="password"]:focus {
    outline: none;
    border-color: #e74c3c;
    box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
}
.delete-form .checkbox-wrap {
    margin: 15px 0;
}
.delete-form .checkbox-wrap label {
    display: inline;
    font-weight: normal;
    margin-left: 8px;
    cursor: pointer;
}
.btn-delete {
    width: 100%;
    padding: 14px;
    background: #e74c3c;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}
.btn-delete:hover {
    background: #c0392b;
}
.btn-delete:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.login-prompt {
    text-align: center;
    padding: 40px 20px;
}
.login-prompt .btn-login {
    display: inline-block;
    padding: 12px 30px;
    background: #3498db;
    color: #fff;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    margin-top: 20px;
}
.login-prompt .btn-login:hover {
    background: #2980b9;
}
.contact-info {
    background: #f1f1f1;
    padding: 20px;
    border-radius: 8px;
    margin-top: 30px;
}
.contact-info h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #333;
}
.contact-info p {
    margin: 5px 0;
    font-size: 14px;
}
.steps-list {
    counter-reset: step-counter;
    list-style: none;
    padding-left: 0;
}
.steps-list li {
    counter-increment: step-counter;
    position: relative;
    padding-left: 40px;
    margin-bottom: 15px;
}
.steps-list li::before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    width: 28px;
    height: 28px;
    background: #e74c3c;
    color: #fff;
    border-radius: 50%;
    text-align: center;
    line-height: 28px;
    font-weight: bold;
    font-size: 14px;
}
</style>
';

add_stylesheet($delete_account_css, 0);

include_once(G5_PATH.'/head.php');

// POST 요청 처리 (계정 삭제 실행)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {

    // 로그인 확인
    if (!$member['mb_id']) {
        alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php');
        exit;
    }

    // 최고관리자 체크
    if ($is_admin == 'super') {
        alert('최고 관리자는 탈퇴할 수 없습니다.');
        exit;
    }

    // admin 계정 삭제 방지
    if ($member['mb_id'] === 'admin') {
        alert('admin 계정은 삭제할 수 없습니다.');
        exit;
    }

    // 비밀번호 확인
    $post_mb_password = isset($_POST['mb_password']) ? trim($_POST['mb_password']) : '';

    if (!$post_mb_password) {
        alert('비밀번호를 입력해 주세요.');
        exit;
    }

    if (!check_password($post_mb_password, $member['mb_password'])) {
        alert('비밀번호가 일치하지 않습니다.');
        exit;
    }

    // 동의 체크 확인
    if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'yes') {
        alert('계정 삭제에 동의해 주세요.');
        exit;
    }

    // ---------------------------------------------------------
    // 계정 삭제 처리
    // ---------------------------------------------------------

    $mb_id = $member['mb_id'];
    $date = date("Ymd");

    // 1. 회원 탈퇴일 저장 및 개인정보 삭제
    $sql = " UPDATE {$g5['member_table']} SET
                mb_leave_date = '{$date}',
                mb_memo = '".date('Y-m-d H:i:s', G5_SERVER_TIME)." 앱에서 계정 삭제 요청\n".sql_real_escape_string($member['mb_memo'])."',
                mb_certify = '',
                mb_adult = 0,
                mb_dupinfo = '',
                mb_hp = '',
                mb_tel = '',
                mb_email = ''
             WHERE mb_id = '{$mb_id}' ";
    sql_query($sql);

    // 2. 푸시 토큰 삭제 (rb_push 테이블이 있는 경우)
    $push_table_exists = sql_fetch("SHOW TABLES LIKE 'rb_push'");
    if ($push_table_exists) {
        sql_query("DELETE FROM rb_push WHERE mb_id = '{$mb_id}'");
    }

    // 3. 앱 토큰 삭제 (rb_app_token 테이블이 있는 경우)
    $app_token_table_exists = sql_fetch("SHOW TABLES LIKE 'rb_app_token'");
    if ($app_token_table_exists) {
        sql_query("DELETE FROM rb_app_token WHERE mb_id = '{$mb_id}'");
    }

    // 4. 회원탈퇴 이벤트 실행
    run_event('member_leave', $member);

    // 5. 소셜로그인 연결 해제
    if (function_exists('social_member_link_delete')) {
        social_member_link_delete($mb_id);
    }

    // 6. 세션 삭제 (로그아웃)
    unset($_SESSION['ss_mb_id']);
    session_destroy();

    // 삭제 완료 메시지
    alert($member['mb_nick'].'님의 계정이 삭제되었습니다.\n\n삭제된 데이터:\n- 회원 정보 (이메일, 전화번호 등)\n- 푸시 알림 토큰\n- 앱 로그인 정보\n\n이용해 주셔서 감사합니다.', G5_URL);
    exit;
}
?>

<div class="delete-account-container">
    <h1>🗑️ 계정 삭제 (Account Deletion)</h1>

    <p>
        헤어왕 서비스의 계정 삭제를 원하시면 아래 절차를 따라주세요.
        <br>
        <em>If you wish to delete your HairWang account, please follow the steps below.</em>
    </p>

    <h2>📋 삭제되는 데이터 (Data to be Deleted)</h2>
    <ul>
        <li><strong>회원 정보</strong> - 아이디, 이메일, 전화번호, 주소 등 개인정보<br>
            <em>Member information - ID, email, phone number, address, etc.</em></li>
        <li><strong>푸시 알림 토큰</strong> - FCM 푸시 알림 수신 정보<br>
            <em>Push notification tokens - FCM push notification data</em></li>
        <li><strong>앱 사용 정보</strong> - 로그인 기록, 앱 설정 등<br>
            <em>App usage data - login history, app settings, etc.</em></li>
        <li><strong>소셜 로그인 연결</strong> - 카카오, 네이버 등 소셜 계정 연결<br>
            <em>Social login connections - Kakao, Naver, etc.</em></li>
    </ul>

    <div class="warning-box">
        <strong>⚠️ 주의사항 (Important Notice)</strong>
        <p style="margin-top: 10px; margin-bottom: 0;">
            계정 삭제 시 위 데이터는 즉시 삭제되며 복구할 수 없습니다.
            <br>
            <em>Once deleted, the above data cannot be recovered.</em>
        </p>
    </div>

    <h2>📁 보관되는 데이터 (Data Retained)</h2>
    <div class="info-box">
        <strong>ℹ️ 법적 보관 의무 데이터</strong>
        <p style="margin-top: 10px; margin-bottom: 0;">
            전자상거래 등에서의 소비자보호에 관한 법률에 따라 다음 데이터는 탈퇴 후에도 일정 기간 보관됩니다:
        </p>
        <ul style="margin-bottom: 0;">
            <li>계약 또는 청약철회 등에 관한 기록: 5년</li>
            <li>대금결제 및 재화 등의 공급에 관한 기록: 5년</li>
            <li>소비자의 불만 또는 분쟁처리에 관한 기록: 3년</li>
        </ul>
    </div>

    <?php if ($member['mb_id']) { ?>
    <!-- 로그인 상태: 계정 삭제 폼 표시 -->

    <?php if ($member['mb_id'] === 'admin' || $is_admin == 'super') { ?>
    <!-- admin 또는 최고관리자는 삭제 불가 안내 -->
    <div class="warning-box" style="background: #f8d7da; border-color: #f5c6cb;">
        <strong style="color: #721c24;">🚫 계정 삭제 불가 (Account Deletion Not Available)</strong>
        <p style="margin-top: 10px; margin-bottom: 0; color: #721c24;">
            <strong><?php echo $member['mb_nick']; ?></strong>님 (<?php echo $member['mb_id']; ?>)은 관리자 계정으로 삭제할 수 없습니다.
            <br>
            <em>Administrator accounts cannot be deleted.</em>
        </p>
    </div>
    <?php } else { ?>
    <!-- 일반 회원: 삭제 폼 표시 -->
    <h2>🔐 계정 삭제 진행</h2>

    <div class="delete-form">
        <p><strong><?php echo $member['mb_nick']; ?></strong>님 (<?php echo $member['mb_id']; ?>), 계정을 삭제하시려면 비밀번호를 입력해 주세요.</p>

        <form name="fDeleteAccount" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" onsubmit="return confirmDelete(this);">
            <input type="hidden" name="action" value="delete_account">

            <label for="mb_password">비밀번호 (Password)</label>
            <input type="password" name="mb_password" id="mb_password" required placeholder="비밀번호를 입력하세요">

            <div class="checkbox-wrap">
                <input type="checkbox" name="confirm_delete" id="confirm_delete" value="yes">
                <label for="confirm_delete">
                    위 내용을 확인하였으며, 계정 삭제에 동의합니다.
                    <br>
                    <em style="font-size: 13px; color: #888;">I have read the above and agree to delete my account.</em>
                </label>
            </div>

            <button type="submit" class="btn-delete" id="btn_delete">계정 삭제 (Delete Account)</button>
        </form>
    </div>
    <?php } ?>

    <script>
    function confirmDelete(f) {
        if (!f.mb_password.value) {
            alert('비밀번호를 입력해 주세요.\nPlease enter your password.');
            f.mb_password.focus();
            return false;
        }

        if (!f.confirm_delete.checked) {
            alert('계정 삭제에 동의해 주세요.\nPlease agree to delete your account.');
            return false;
        }

        if (!confirm('정말로 계정을 삭제하시겠습니까?\n삭제된 계정은 복구할 수 없습니다.\n\nAre you sure you want to delete your account?\nThis action cannot be undone.')) {
            return false;
        }

        document.getElementById('btn_delete').disabled = true;
        document.getElementById('btn_delete').innerText = '처리 중... (Processing...)';

        return true;
    }
    </script>

    <?php } else { ?>
    <!-- 비로그인 상태: 삭제 절차 안내 -->

    <h2>📝 계정 삭제 절차 (Deletion Steps)</h2>

    <ol class="steps-list">
        <li>
            <strong>앱 또는 웹사이트에서 로그인</strong><br>
            헤어왕 앱 또는 웹사이트(hairwang.com)에 로그인합니다.
            <br>
            <em>Log in to the HairWang app or website (hairwang.com).</em>
        </li>
        <li>
            <strong>이 페이지로 다시 접속</strong><br>
            로그인 후 이 페이지(계정 삭제)로 다시 접속합니다.
            <br>
            <em>After logging in, return to this page (Account Deletion).</em>
        </li>
        <li>
            <strong>비밀번호 입력 및 동의</strong><br>
            비밀번호를 입력하고 삭제에 동의한 후 계정 삭제 버튼을 클릭합니다.
            <br>
            <em>Enter your password, agree to deletion, and click the delete button.</em>
        </li>
        <li>
            <strong>삭제 완료</strong><br>
            계정이 즉시 삭제되며, 삭제된 데이터는 복구할 수 없습니다.
            <br>
            <em>Your account will be deleted immediately and cannot be recovered.</em>
        </li>
    </ol>

    <div class="login-prompt">
        <p>계정을 삭제하시려면 먼저 로그인해 주세요.</p>
        <p><em>Please log in first to delete your account.</em></p>
        <a href="<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-login">로그인 (Login)</a>
    </div>

    <?php } ?>

    <div class="contact-info">
        <h3>📞 문의 (Contact)</h3>
        <p>계정 삭제에 관한 문의사항이 있으시면 아래로 연락해 주세요.</p>
        <p><em>For inquiries about account deletion, please contact us:</em></p>
        <p><strong>이메일 (Email):</strong> support@hairwang.com</p>
        <p><strong>웹사이트 (Website):</strong> <a href="https://hairwang.com">https://hairwang.com</a></p>
    </div>
</div>

<?php
include_once(G5_PATH.'/tail.php');
?>
