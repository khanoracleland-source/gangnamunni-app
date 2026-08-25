<?php
/**
 * 회원 비밀번호 찾기
 * - 이메일 입력 -> g5_member 조회 -> 임시 비밀번호 발급 + 이메일 발송
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB error");
mysqli_set_charset($connect, 'utf8mb4');

$result_msg = '';
$result_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $result_msg = '이메일을 입력해주세요.';
        $result_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result_msg = '유효한 이메일을 입력해주세요.';
        $result_type = 'error';
    } else {
        $stmt = mysqli_prepare($connect, "SELECT mb_no, mb_id, mb_name, mb_email FROM g5_member WHERE mb_email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $member = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);

            if ($member) {
                $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
                $new_pw = '';
                for ($i = 0; $i < 8; $i++) $new_pw .= $chars[random_int(0, strlen($chars) - 1)];

                // Gnuboard PBKDF2 hash
                if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
                require_once __DIR__ . '/../lib/pbkdf2.compat.php';
                $hashed = create_hash($new_pw);

                $upd = mysqli_prepare($connect, "UPDATE g5_member SET mb_password = ? WHERE mb_no = ?");
                mysqli_stmt_bind_param($upd, 'si', $hashed, $member['mb_no']);

                if (mysqli_stmt_execute($upd)) {
                    // Send email
                    $email_sent = false;
                    try {
                        require_once __DIR__ . '/includes/mail_helper.php';
                        $body = '
                        <div style="font-family:sans-serif;max-width:500px;margin:0 auto;padding:20px;">
                            <h2 style="color:#AE1932;">서울미녀 임시 비밀번호</h2>
                            <p>안녕하세요, <strong>' . htmlspecialchars($member['mb_name'] ?: $member['mb_id']) . '</strong>님.</p>
                            <div style="background:#f5f5f5;padding:15px;border-radius:8px;margin:15px 0;text-align:center;">
                                <p style="margin:5px 0;color:#666;">아이디: <strong>' . htmlspecialchars($member['mb_id']) . '</strong></p>
                                <p style="margin:5px 0;font-size:20px;letter-spacing:2px;"><strong>' . $new_pw . '</strong></p>
                            </div>
                            <p style="color:#e74c3c;">⚠ 로그인 후 반드시 비밀번호를 변경해주세요.</p>
                        </div>';
                        $r = mail_send(0, $email, '[서울미녀] 임시 비밀번호', $body);
                        $email_sent = isset($r['success']) && $r['success'];
                    } catch (Exception $e) {}

                    if ($email_sent) {
                        $result_msg = '임시 비밀번호가 이메일로 전송되었습니다.';
                    } else {
                        $result_msg = '임시 비밀번호: <strong>' . $new_pw . '</strong><br><small class="text-muted">(이메일 발송에 실패하여 화면에 표시합니다)</small>';
                    }
                    $result_type = 'success';
                } else {
                    $result_msg = '오류가 발생했습니다.';
                    $result_type = 'error';
                }
                mysqli_stmt_close($upd);
            } else {
                $result_msg = '해당 이메일로 등록된 계정이 없습니다.';
                $result_type = 'error';
            }
        }
    }
}
mysqli_close($connect);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>비밀번호 찾기 - 서울미녀</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea, #764ba2); min-height: 100vh; display: flex; align-items: center; }
        .card { border: none; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <i class="bi bi-key-fill" style="font-size:40px;color:#667eea;"></i>
                        <h5 class="mt-2">비밀번호 찾기</h5>
                        <p class="text-muted small">가입 시 등록한 이메일을 입력해주세요</p>
                    </div>
                    <?php if ($result_msg): ?>
                    <div class="alert alert-<?php echo $result_type === 'success' ? 'success' : 'danger'; ?> py-2 small">
                        <?php echo $result_msg; ?>
                    </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small">이메일</label>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">임시 비밀번호 발급</button>
                    </form>
                    <div class="text-center mt-3">
                        <div class="text-center mb-2">
                        <button class="btn btn-warning btn-sm w-100 mb-2" disabled>
                            <i class="bi bi-chat-fill me-1"></i>카카오 로그인
                            <span class="badge bg-secondary ms-1">준비 중</span>
                        </button>
                    </div>
                    <a href="index.php" class="text-decoration-none small">메인으로 돌아가기</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
