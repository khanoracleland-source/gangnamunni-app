<?php
/**
 * 비밀번호 찾기 (공개 페이지 - 세션 불필요)
 * - 이메일 입력 -> gangnamunni_admins 조회
 * - 매치 시: 임시 비밀번호 생성 -> password_hash 저장 -> 표시
 * - 미매치: 에러 메시지
 *
 * QA #19 - 비밀번호 찾기
 * @created 2026-03-04
 */

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    http_response_code(500);
    echo '<p>서버 오류가 발생했습니다.</p>';
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// 사이트 이름
$site_name = '서울미녀';

// ===== POST 처리 =====
$result_msg = '';
$result_type = ''; // 'success' or 'error'
$temp_password = '';
$found_username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_password'])) {
    $input_email = trim($_POST['email'] ?? '');

    if (empty($input_email)) {
        $result_msg = '이메일을 입력해주세요.';
        $result_type = 'error';
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $result_msg = '유효한 이메일 형식을 입력해주세요.';
        $result_type = 'error';
    } else {
        // gangnamunni_admins 테이블에서 이메일 검색
        $stmt = mysqli_prepare($connect, "SELECT id, username, name, email FROM gangnamunni_admins WHERE email = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $input_email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($admin) {
                // 임시 비밀번호 생성 (영문+숫자 10자)
                $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $temp_password = '';
                for ($i = 0; $i < 10; $i++) {
                    $temp_password .= $chars[random_int(0, strlen($chars) - 1)];
                }

                // password_hash로 해시 후 DB 업데이트
                $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
                $update_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET password = ?, updated_at = NOW() WHERE id = ?");
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, 'si', $hashed, $admin['id']);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $found_username = $admin['username'];

                        // SMTP email sending
                        $email_sent = false;
                        $email_error = '';
                        try {
                            require_once __DIR__ . '/includes/mail_helper.php';
                            $email_body = '
                            <div style="font-family:sans-serif;max-width:500px;margin:0 auto;padding:20px;">
                                <h2 style="color:#AE1932;">서울미녀 관리자 임시 비밀번호</h2>
                                <p>안녕하세요, <strong>' . htmlspecialchars($admin['name']) . '</strong>님.</p>
                                <p>임시 비밀번호가 발급되었습니다.</p>
                                <div style="background:#f5f5f5;padding:15px;border-radius:8px;margin:15px 0;text-align:center;">
                                    <p style="margin:5px 0;color:#666;">아이디: <strong>' . htmlspecialchars($admin['username']) . '</strong></p>
                                    <p style="margin:5px 0;font-size:20px;letter-spacing:2px;"><strong>' . $temp_password . '</strong></p>
                                </div>
                                <p style="color:#e74c3c;">⚠ 로그인 후 반드시 비밀번호를 변경해주세요.</p>
                                <hr style="border:none;border-top:1px solid #eee;margin:20px 0;">
                                <p style="color:#999;font-size:12px;">서울미녀 관리자 시스템<br>https://hms.seoulbeauty.app/hairwang/gangnamunni/</p>
                            </div>';
                            $send_result = mail_send(0, $input_email, '[서울미녀] 임시 비밀번호 발급', $email_body);
                            if (isset($send_result['success']) && $send_result['success']) {
                                $email_sent = true;
                            } else {
                                $email_error = $send_result['error'] ?? 'unknown';
                            }
                        } catch (Exception $e) {
                            $email_error = $e->getMessage();
                        }

                        if ($email_sent) {
                            $result_msg = '임시 비밀번호가 이메일로 전송되었습니다. 로그인 후 반드시 비밀번호를 변경해주세요.';
                        } else {
                            $result_msg = '임시 비밀번호가 발급되었습니다. (이메일 발송 실패: ' . htmlspecialchars($email_error) . ')';
                        }
                        $result_type = 'success';
                    } else {
                        $result_msg = '비밀번호 업데이트 중 오류가 발생했습니다.';
                        $result_type = 'error';
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    $result_msg = '서버 오류가 발생했습니다.';
                    $result_type = 'error';
                }
            } else {
                $result_msg = '해당 이메일로 등록된 관리자 계정을 찾을 수 없습니다.';
                $result_type = 'error';
            }
        } else {
            $result_msg = '서버 오류가 발생했습니다.';
            $result_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>비밀번호 찾기 | <?php echo htmlspecialchars($site_name); ?></title>
    <!-- anti-flash -->
    <style>
    html{background:var(--bs-body-bg);color:#212529}
    html[data-bs-theme="dark"]{background:#212529;color:#dee2e6}
    body{background:transparent}
    </style>
    <script>
    (function(){
        var t=localStorage.getItem('admin_theme')||'light';
        document.documentElement.setAttribute('data-bs-theme',t);
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .login-logo { height:48px; width:48px; object-fit:contain; border-radius:10px; }
        [data-bs-theme="dark"] .login-logo { filter: invert(1) brightness(2); }
        .theme-toggle-float {
            position:fixed; top:16px; right:16px; z-index:1050;
            border-radius:50%; width:44px; height:44px;
            display:flex; align-items:center; justify-content:center;
            font-size:18px; cursor:pointer;
        }
        .theme-toggle-float:hover { transform:scale(1.1); }
        .login-card { border-radius:16px; border:none; }
        .login-brand { display:flex; flex-direction:column; align-items:center; gap:6px; margin-bottom:1.2rem; }
        .login-brand-name { font-size:1.4rem; font-weight:700; color:var(--bs-body-color); }
        .login-brand-sub { font-size:0.8rem; color:var(--bs-secondary-color); }
        .temp-pw-box {
            background: var(--bs-tertiary-bg);
            border: 2px dashed var(--bs-primary);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .temp-pw-box .pw-value {
            font-size: 24px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            color: var(--bs-primary);
            letter-spacing: 2px;
            user-select: all;
        }
        .temp-pw-box .pw-label {
            font-size: 12px;
            color: var(--bs-secondary-color);
            margin-top: 6px;
        }
    </style>
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <!-- 주간/야간 토글 -->
    <button class="btn btn-outline-secondary theme-toggle-float" id="loginThemeToggle" title="주간/야간 모드">
        <i class="bi bi-moon-stars" id="loginThemeIcon"></i>
    </button>

    <div class="card shadow login-card" style="width:100%;max-width:420px;">
        <div class="card-body p-4">
            <div class="login-brand">
                <img src="assets/images/seoul_beauty_logo.png" alt="<?php echo htmlspecialchars($site_name); ?>" class="login-logo">
                <div class="login-brand-name"><?php echo htmlspecialchars($site_name); ?></div>
                <div class="login-brand-sub">비밀번호 찾기</div>
            </div>

            <?php if ($result_type === 'success' && !empty($temp_password)): ?>
            <!-- 성공: 임시 비밀번호 표시 -->
            <div class="alert alert-success py-2 mb-3">
                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($result_msg); ?>
            </div>

            <div class="temp-pw-box mb-3">
                <div class="mb-2">
                    <span class="badge bg-secondary">아이디</span>
                    <strong class="ms-1"><?php echo htmlspecialchars($found_username); ?></strong>
                </div>
                <div class="pw-label">임시 비밀번호</div>
                <div class="pw-value" id="tempPwValue"><?php echo htmlspecialchars($temp_password); ?></div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="copyTempPw()">
                    <i class="bi bi-clipboard"></i> 복사
                </button>
            </div>

            <div class="p-2 rounded mb-3" style="background:var(--bs-tertiary-bg);font-size:12px;line-height:1.7;">
                <div class="text-danger fw-bold mb-1"><i class="bi bi-exclamation-triangle"></i> 보안 안내</div>
                <ul class="mb-0 ps-3">
                    <li>이 임시 비밀번호는 <strong>1회 표시</strong>됩니다. 반드시 메모해두세요.</li>
                    <li>로그인 후 즉시 <strong>비밀번호를 변경</strong>해주세요.</li>
                    <li>타인에게 공유하지 마세요.</li>
                </ul>
            </div>

            <a href="dashboard.php" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right"></i> 로그인 하기
            </a>

            <?php elseif ($result_type === 'error'): ?>
            <!-- 에러 -->
            <div class="alert alert-danger py-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($result_msg); ?>
            </div>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">등록된 이메일</label>
                    <input type="email" name="email" class="form-control" required autofocus
                           placeholder="admin@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="form-text">관리자 계정에 등록된 이메일 주소를 입력하세요.</div>
                </div>
                <button type="submit" name="find_password" class="btn btn-primary w-100">
                    <i class="bi bi-key"></i> 임시 비밀번호 발급
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="dashboard.php" class="text-body-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> 로그인으로 돌아가기
                </a>
            </div>

            <?php else: ?>
            <!-- 초기 상태: 이메일 입력 폼 -->
            <p class="text-center text-body-secondary small mb-3">
                관리자 계정에 등록된 이메일을 입력하시면<br>임시 비밀번호를 발급해드립니다.
            </p>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">등록된 이메일</label>
                    <input type="email" name="email" class="form-control" required autofocus
                           placeholder="admin@example.com">
                    <div class="form-text">관리자 계정에 등록된 이메일 주소를 입력하세요.</div>
                </div>
                <button type="submit" name="find_password" class="btn btn-primary w-100">
                    <i class="bi bi-key"></i> 임시 비밀번호 발급
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="dashboard.php" class="text-body-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> 로그인으로 돌아가기
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 임시 비밀번호 복사
    function copyTempPw() {
        var pwEl = document.getElementById('tempPwValue');
        if (!pwEl) return;
        var pw = pwEl.textContent;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(pw).then(function() {
                alert('임시 비밀번호가 클립보드에 복사되었습니다.');
            });
        } else {
            // 폴백
            var range = document.createRange();
            range.selectNodeContents(pwEl);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
            alert('임시 비밀번호가 클립보드에 복사되었습니다.');
        }
    }

    // 주간/야간 토글
    (function(){
        var html = document.documentElement;
        var btn = document.getElementById('loginThemeToggle');
        var icon = document.getElementById('loginThemeIcon');
        var saved = localStorage.getItem('admin_theme') || 'light';
        html.setAttribute('data-bs-theme', saved);
        if(icon) icon.className = saved === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        if(btn){
            btn.addEventListener('click', function(){
                var cur = html.getAttribute('data-bs-theme');
                var next = cur === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-bs-theme', next);
                localStorage.setItem('admin_theme', next);
                if(icon) icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
            });
        }
    })();
    </script>
</body>
</html>
<?php
mysqli_close($connect);
?>
