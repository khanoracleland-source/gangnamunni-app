<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$withdraw_member_id = get_gangnamunni_member_id();
$withdraw_member_id_escaped = mysqli_real_escape_string($connect, (string)$withdraw_member_id);
$withdraw_social_result = mysqli_query(
    $connect,
    "SELECT 1 FROM g5_member_social_profiles WHERE mb_id = '{$withdraw_member_id_escaped}' LIMIT 1"
);
$is_social_member = $withdraw_social_result && mysqli_num_rows($withdraw_social_result) > 0;

$page_title = term('member') . ' 탈퇴';
$body_class = 'page-withdraw';
include __DIR__ . '/includes/header.php';
$withdraw_success_url = G5_URL . '/app/index.php';
?>

<style>
.withdraw-container {
    max-width: 540px;
    margin: 0 auto;
    padding: 24px 16px 40px;
}
.withdraw-header {
    text-align: center;
    margin-bottom: 32px;
}
.withdraw-header h2 {
    font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 8px;
}
.withdraw-header p {
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    color: #888;
    line-height: 1.6;
}
.withdraw-warning {
    background: #fff5f5;
    border: 1px solid #fed7d7;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}
.withdraw-warning h4 {
    color: #e53e3e;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 600;
    margin-bottom: 8px;
}
.withdraw-warning ul {
    margin: 0; padding-left: 18px;
    font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: #666;
    line-height: 1.8;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}
.form-group select,
.form-group textarea,
.form-group input[type="password"] {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    color: #333;
    background: #fff;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.form-group select:focus,
.form-group textarea:focus,
.form-group input:focus {
    outline: none;
    border-color: #e53e3e;
}
.form-group textarea {
    resize: vertical;
    min-height: 80px;
}
.btn-withdraw {
    width: 100%;
    padding: 14px;
    background: #e53e3e;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-withdraw:hover {
    background: #c53030;
}
.btn-withdraw:disabled {
    background: #ccc;
    cursor: not-allowed;
}
.btn-cancel {
    display: block;
    width: 100%;
    padding: 14px;
    background: #f5f5f5;
    color: #666;
    border: none;
    border-radius: 10px;
    font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 500;
    cursor: pointer;
    margin-top: 10px;
    text-align: center;
    text-decoration: none;
}
.btn-cancel:hover {
    background: #eee;
}
.confirm-check {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    color: #333;
}
.confirm-check input[type="checkbox"] {
    width: 18px; height: 18px;
    accent-color: #e53e3e;
}
</style>

<meta name="csrf-token" content="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

<div class="withdraw-container">
    <div class="withdraw-header">
        <h2>회원 탈퇴</h2>
        <p>탈퇴 전 아래 내용을 꼭 확인해주세요.</p>
    </div>

    <div class="withdraw-warning">
        <h4>탈퇴 시 유의사항</h4>
        <ul>
            <li>탈퇴 후 계정 복구가 불가능합니다.</li>
            <li>작성한 후기, 상담 내역은 삭제되지 않습니다.</li>
            <li>진행 중인 예약이 있는 경우 먼저 취소해주세요.</li>
            <li>동일 아이디로 재가입이 제한될 수 있습니다.</li>
        </ul>
    </div>

    <form id="withdrawForm" onsubmit="return false;">
        <div class="form-group">
            <label for="reason">탈퇴 사유</label>
            <select id="reason" name="reason">
                <option value="">사유를 선택해주세요</option>
                <option value="서비스 불만">서비스 불만</option>
                <option value="사용 빈도 낮음">사용 빈도 낮음</option>
                <option value="다른 서비스 이용">다른 서비스 이용</option>
                <option value="개인정보 보호">개인정보 보호</option>
                <option value="기타">기타</option>
            </select>
        </div>

        <div class="form-group">
            <label for="reason_detail">추가 의견 (선택)</label>
            <textarea id="reason_detail" name="reason_detail" placeholder="서비스 개선을 위해 의견을 남겨주세요."></textarea>
        </div>

        <?php if (!$is_social_member) { ?>
        <div class="form-group">
            <label for="password">비밀번호 확인</label>
            <input type="password" id="password" name="password" placeholder="현재 비밀번호를 입력해주세요" required>
        </div>
        <?php } else { ?>
        <input type="hidden" id="password" name="password" value="">
        <?php } ?>

        <label class="confirm-check">
            <input type="checkbox" id="confirmCheck">
            <span>위 유의사항을 모두 확인했으며, 탈퇴에 동의합니다.</span>
        </label>

        <button type="button" class="btn-withdraw" id="btnWithdraw" disabled onclick="doWithdraw()">회원 탈퇴</button>
        <a href="mypage.php" class="btn-cancel">돌아가기</a>
    </form>
</div>

<script>
document.getElementById('confirmCheck').addEventListener('change', function() {
    document.getElementById('btnWithdraw').disabled = !this.checked;
});

function doWithdraw() {
    var reason = document.getElementById('reason').value;
    var detail = document.getElementById('reason_detail').value;
    var password = document.getElementById('password').value;

    if (!<?php echo $is_social_member ? 'true' : 'false'; ?> && !password) {
        alert('비밀번호를 입력해주세요.');
        return;
    }
    if (!document.getElementById('confirmCheck').checked) {
        alert('유의사항에 동의해주세요.');
        return;
    }
    if (!confirm('정말 탈퇴하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
        return;
    }

    var fullReason = reason;
    if (detail) fullReason += ' - ' + detail;

    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    var btn = document.getElementById('btnWithdraw');
    btn.disabled = true;
    btn.textContent = '처리 중...';

    fetch('api/withdraw.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            reason: fullReason,
            password: password
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message || '탈퇴가 완료되었습니다.');
            window.location.replace(<?php echo json_encode($withdraw_success_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);
        } else {
            alert(data.message || '탈퇴 처리에 실패했습니다.');
            btn.disabled = false;
            btn.textContent = '회원 탈퇴';
        }
    })
    .catch(function(err) {
        alert('네트워크 오류가 발생했습니다.');
        btn.disabled = false;
        btn.textContent = '회원 탈퇴';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
