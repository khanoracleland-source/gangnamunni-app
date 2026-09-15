<?php
/**
 * 비밀번호 찾기 (공개 페이지 - CSRF 보호용 세션만 사용)
 * - 관리자 아이디 + 이메일 입력 -> gangnamunni_admins 정확히 일치하는 계정 조회
 * - 매치 시: 임시 비밀번호를 이메일로만 발송
 * - 계정 존재 여부는 브라우저에 노출하지 않음
 *
 * QA #19 - 비밀번호 찾기
 * @created 2026-03-04
 */

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Strict',
    ]);
}
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
$csrf_token = generateCsrfToken();
$generic_success_msg = '가입된 관리자 계정이 확인되면 임시 비밀번호를 이메일로 발송합니다.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_password'])) {
    $input_username = trim($_POST['username'] ?? '');
    $input_email = trim($_POST['email'] ?? '');
    $normalized_email = strtolower($input_email);
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $result_msg = '요청이 만료되었습니다. 페이지를 새로고침한 후 다시 시도해주세요.';
        $result_type = 'error';
    } elseif ($input_username === '' || $input_email === '') {
        $result_msg = '관리자 아이디와 이메일을 모두 입력해주세요.';
        $result_type = 'error';
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $result_msg = '유효한 이메일 형식을 입력해주세요.';
        $result_type = 'error';
    } elseif (!checkRateLimit('admin_pw_reset_ip_' . $client_ip, 5, 900)
        || !checkRateLimit('admin_pw_reset_email_' . $normalized_email, 3, 3600)) {
        $result_msg = '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.';
        $result_type = 'error';
    } else {
        // 이메일만으로 LIMIT 1 조회하면 중복된 기존 데이터에서 다른 계정의
        // 비밀번호를 변경할 수 있다. 아이디와 이메일이 같은 행에 속할 때만 재설정한다.
        $stmt = mysqli_prepare($connect, "SELECT id, username, name, email FROM gangnamunni_admins WHERE username = ? AND LOWER(email) = LOWER(?) AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $input_username, $input_email);
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

                // 메일 발송 실패 시 기존 비밀번호가 유지되도록 트랜잭션 사용
                $hashed = password_hash($temp_password, PASSWORD_DEFAULT);
                mysqli_begin_transaction($connect);
                $update_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admins SET password = ?, updated_at = NOW() WHERE id = ?");
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, 'si', $hashed, $admin['id']);
                    if (mysqli_stmt_execute($update_stmt)) {
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
                            $send_result = mail_send(0, $admin['email'], '[서울미녀] 임시 비밀번호 발급', $email_body);
                            if (isset($send_result['success']) && $send_result['success']) {
                                $email_sent = true;
                            } else {
                                $email_error = $send_result['error'] ?? 'unknown';
                            }
                        } catch (Exception $e) {
                            $email_error = $e->getMessage();
                        }

                        if ($email_sent && mysqli_commit($connect)) {
                            $result_msg = $generic_success_msg;
                            $result_type = 'success';
                        } else {
                            mysqli_rollback($connect);
                            error_log('Admin password reset mail failed: ' . $email_error);
                            $result_msg = '일시적인 서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
                            $result_type = 'error';
                        }
                    } else {
                        mysqli_rollback($connect);
                        $result_msg = '일시적인 서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
                        $result_type = 'error';
                    }
                    mysqli_stmt_close($update_stmt);
                } else {
                    mysqli_rollback($connect);
                    $result_msg = '서버 오류가 발생했습니다.';
                    $result_type = 'error';
                }
            } else {
                // 계정 유무를 추측할 수 없도록 동일한 응답 반환
                $result_msg = $generic_success_msg;
                $result_type = 'success';
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
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

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

            <?php if ($result_type === 'success'): ?>
            <div class="alert alert-success py-2 mb-3">
                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($result_msg); ?>
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                    <label class="form-label">관리자 아이디</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           autocomplete="username" placeholder="admin"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">등록된 이메일</label>
                    <input type="email" name="email" class="form-control" required
                           autocomplete="email"
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
                관리자 아이디와 등록된 이메일을 입력하시면<br>해당 계정에 임시 비밀번호를 발급해드립니다.
            </p>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div class="mb-3">
                    <label class="form-label">관리자 아이디</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           autocomplete="username" placeholder="admin">
                </div>
                <div class="mb-3">
                    <label class="form-label">등록된 이메일</label>
                    <input type="email" name="email" class="form-control" required
                           autocomplete="email"
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
