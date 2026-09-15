<?php
/**
 * ========================================
 * 로그인 페이지
 * ========================================
 *
 * 푸시 알림 대시보드에 접근하기 위한 로그인 페이지입니다.
 *
 * @file    login.php
 * @created 2025-12-18
 * @version 1.0.0
 *
 * @credentials
 *   아이디: admin
 *   비밀번호: ad12@!
 */

// auth.php 로드
require_once __DIR__ . '/auth.php';

// 이미 로그인된 경우 대시보드로 리다이렉트
if (is_logged_in()) {
    $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
    unset($_SESSION['redirect_url']);
    header('Location: ' . $redirect_url);
    exit;
}

// 로그인 처리
$error_message = '';
$warning_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 토큰 검증
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf_token)) {
        $error_message = '잘못된 요청입니다. 페이지를 새로고침하고 다시 시도해주세요.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // Brute Force 방어: 계정 잠금 확인
        if (is_account_locked($username)) {
            $remaining_time = get_lockout_remaining_time($username);
            $minutes = ceil($remaining_time / 60);

            $error_message = "로그인 시도 횟수를 초과했습니다. {$minutes}분 후에 다시 시도해주세요.";
            log_login_attempt($username, false);
        } else {
            // 로그인 시도
            if (login($username, $password)) {
                // 로그인 성공 - 로그인 시도 기록
                log_login_attempt($username, true);

                // 리다이렉트 URL 가져오기
                $redirect_url = $_SESSION['redirect_url'] ?? 'index.php';
                unset($_SESSION['redirect_url']);

                // 대시보드로 리다이렉트
                header('Location: ' . $redirect_url);
                exit;
            } else {
                // 로그인 실패 - 로그인 시도 기록
                log_login_attempt($username, false);

                // 로그인 실패 경고 메시지
                $attempt_info = get_login_attempt_info($username);

                if ($attempt_info) {
                    $remaining_attempts = $attempt_info['remaining_attempts'];

                    if ($remaining_attempts > 0) {
                        $warning_message = "남은 시도 횟수: {$remaining_attempts}회";
                    }
                }

                $error_message = '아이디 또는 비밀번호가 올바르지 않습니다.';
            }
        }
    }
}

// CSRF 토큰 생성
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 푸시 알림 시스템</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Noto Sans KR', sans-serif;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .login-header p {
            color: #6c757d;
            font-size: 14px;
        }
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .login-icon i {
            color: white;
            font-size: 36px;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: #667eea;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            color: white;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .alert {
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: none;
        }
        .alert-danger {
            background-color: #fee;
            color: #c33;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 12px;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        .password-toggle:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- 로그인 헤더 -->
            <div class="login-header">
                <div class="login-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <h1>푸시 알림 시스템</h1>
                <p>관리자 로그인</p>
            </div>

            <!-- 에러 메시지 표시 -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- 경고 메시지 표시 -->
            <?php if (!empty($warning_message)): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($warning_message); ?>
                </div>
            <?php endif; ?>

            <!-- 로그인 폼 -->
            <form method="post" action="login.php" id="loginForm">
                <!-- CSRF 토큰 -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <!-- 아이디 입력 -->
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>아이디
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="아이디를 입력하세요"
                            required
                            autocomplete="username"
                            autofocus
                        >
                    </div>
                </div>

                <!-- 비밀번호 입력 -->
                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>비밀번호
                    </label>
                    <div class="input-group position-relative">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="비밀번호를 입력하세요"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>

                <!-- 로그인 버튼 -->
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>로그인
                </button>
            </form>

            <!-- 푸터 -->
            <div class="login-footer">
                <p class="mb-0">푸시 알림 관리 시스템 v2.0</p>
                <p class="mb-0">&copy; 2025 EduMars. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 비밀번호 표시/숨기기 토글
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);

            // 아이콘 변경
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // 폼 제출 시 버튼 비활성화 (중복 제출 방지)
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>로그인 중...';
        });

        // Enter 키로 폼 제출
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>
