<?php
/**
 * ========================================
 * 비밀번호 해시 생성 도구
 * ========================================
 *
 * 관리자 비밀번호를 password_hash()로 해시하여 생성합니다.
 *
 * @file    generate_password_hash.php
 * @created 2026-01-29
 * @version 1.0.0
 *
 * @security
 *   보안을 위해 비밀번호 해시를 생성한 후 이 파일을 삭제하세요!
 *
 * @usage
 *   1. 브라우저에서 이 파일 실행: http://domain.com/.../generate_password_hash.php
 *   2. 새 비밀번호 입력
 *   3. 생성된 해시를 복사하여 auth.php의 ADMIN_PASSWORD_HASH에 붙여넣기
 *   4. ⚠️ 이 파일을 서버에서 삭제
 */

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password)) {
        $error = '비밀번호를 입력해주세요.';
    } elseif ($new_password !== $confirm_password) {
        $error = '비밀번호가 일치하지 않습니다.';
    } elseif (strlen($new_password) < 6) {
        $error = '비밀번호는 최소 6자 이상이어야 합니다.';
    } else {
        // password_hash()로 해시 생성
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>비밀번호 해시 생성</title>
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
            padding: 20px;
        }
        .container {
            max-width: 700px;
        }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
        }
        .card-header {
            text-align: center;
            margin-bottom: 30px;
            border: none;
            background: none;
            padding: 0;
        }
        .card-header h1 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .card-header .icon {
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
        .card-header .icon i {
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
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 600;
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .hash-output {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin-top: 15px;
        }
        .instructions {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .instructions h5 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        .instructions ol {
            margin-bottom: 0;
            padding-left: 20px;
        }
        .instructions li {
            margin-bottom: 5px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .warning-box h5 {
            color: #ff6b00;
            margin-bottom: 10px;
        }
        .copy-btn {
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s;
        }
        .copy-btn:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>비밀번호 해시 생성</h1>
                <p class="text-muted">관리자 비밀번호를 안전하게 해시하여 저장하세요</p>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success) && $success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    비밀번호 해시가 성공적으로 생성되었습니다!
                </div>

                <div class="hash-output" id="hashOutput">
                    <?php echo htmlspecialchars($password_hash); ?>
                </div>

                <button class="btn btn-outline-primary btn-sm mt-2" onclick="copyToClipboard()">
                    <i class="fas fa-copy me-2"></i>해시 복사하기
                </button>

                <div class="instructions">
                    <h5><i class="fas fa-info-circle me-2"></i>다음 단계</h5>
                    <ol>
                        <li><strong>auth.php</strong> 파일을 엽니다</li>
                        <li><code>ADMIN_PASSWORD_HASH</code> 상수 값을 위의 해시로 교체합니다</li>
                        <li>파일을 저장하고 서버에 업로드합니다</li>
                        <li>로그인 페이지에서 새 비밀번호로 테스트합니다</li>
                        <li><strong>이 파일(generate_password_hash.php)을 서버에서 삭제합니다</strong></li>
                    </ol>
                </div>

                <div class="warning-box">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>보안 경고</h5>
                    <p class="mb-0">
                        비밀번호 해시를 생성한 후 <strong>반드시 이 파일을 삭제</strong>하세요!
                        이 파일이 서버에 남아있으면 보안 위험이 있습니다.
                    </p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">
                            <i class="fas fa-lock me-2"></i>새 비밀번호
                        </label>
                        <input
                            type="password"
                            class="form-control"
                            id="new_password"
                            name="new_password"
                            placeholder="새 비밀번호를 입력하세요 (최소 6자)"
                            required
                            minlength="6"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock me-2"></i>비밀번호 확인
                        </label>
                        <input
                            type="password"
                            class="form-control"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="비밀번호를 다시 입력하세요"
                            required
                            minlength="6"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>해시 생성
                    </button>
                </form>

                <div class="alert alert-info mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i>비밀번호 요구사항</h6>
                    <ul class="mb-0">
                        <li>최소 6자 이상</li>
                        <li>영문 대소문자, 숫자, 특수문자 조합 권장</li>
                        <li>예: <code>Admin@2026!</code>, <code>SecurePwd#99</code></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const hashText = document.getElementById('hashOutput').textContent;

            // 클립보드 API 사용
            if (navigator.clipboard) {
                navigator.clipboard.writeText(hashText).then(() => {
                    alert('해시가 클립보드에 복사되었습니다!');
                }).catch(err => {
                    console.error('복사 실패:', err);
                    fallbackCopy(hashText);
                });
            } else {
                fallbackCopy(hashText);
            }
        }

        function fallbackCopy(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                alert('해시가 클립보드에 복사되었습니다!');
            } catch (err) {
                alert('복사에 실패했습니다. 수동으로 복사해주세요.');
            }

            document.body.removeChild(textarea);
        }

        // 비밀번호 확인 검증
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (password !== confirm) {
                e.preventDefault();
                alert('비밀번호가 일치하지 않습니다.');
                return false;
            }
        });
    </script>
</body>
</html>
