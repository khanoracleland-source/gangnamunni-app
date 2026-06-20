<?php
// [인수인계 정비 2026-06-15] 비활성화
//  사유: 미사용(어디서도 링크 안됨) + gangnamunni_members 테이블에 mb_email/mb_password 컬럼이 없어
//        쿼리가 ERROR 1054 로 실패하는 미완성/죽은 코드. 그누보드 표준 비번찾기는 /bbs/password_lost.php 사용.
//  조치: 관리자 외 접근 차단. (삭제 아님 — 필요 시 아래 블록 제거로 원복 가능)
@session_start();
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(403);
    die('이 페이지는 비활성화되었습니다. 비밀번호 찾기는 로그인 화면을 이용해 주세요.');
}
?>
<?php
/**
 * 회원 비밀번호 재설정 (프론트엔드 / 앱 WebView)
 * - GET ?email=xxx&app=1 으로 진입 가능
 * - app=1: standalone 모드 (상단/하단 네비 숨김)
 * - gangnamunni_members 테이블 대상
 *
 * @created 2026-03-18
 */
session_start();

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    http_response_code(500);
    echo '<p>서버 오류가 발생했습니다.</p>';
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// 파라미터
$is_app = isset($_GET['app']) && $_GET['app'] == '1';
$prefill_email = trim($_GET['email'] ?? '');

// 사이트 이름
$site_name = '서울미녀';

// ===== POST 처리 =====
$result_msg = '';
$result_type = ''; // 'success' or 'error'
$temp_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $input_email = trim($_POST['email'] ?? '');

    if (empty($input_email)) {
        $result_msg = '이메일을 입력해주세요.';
        $result_type = 'error';
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $result_msg = '유효한 이메일 형식을 입력해주세요.';
        $result_type = 'error';
    } else {
        // gangnamunni_members 테이블에서 이메일 검색
        $stmt = mysqli_prepare($connect, "SELECT mb_id, mb_nick, mb_email FROM gangnamunni_members WHERE mb_email = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $input_email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $member = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($member) {
                // 임시 비밀번호 생성 (영문+숫자 8자)
                $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $temp_password = '';
                for ($i = 0; $i < 8; $i++) {
                    $temp_password .= $chars[random_int(0, strlen($chars) - 1)];
                }

                // md5 해시로 비밀번호 업데이트
                $hashed = md5($temp_password);
                $update_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_members SET mb_password = ? WHERE mb_id = ?");
                if ($update_stmt) {
                    mysqli_stmt_bind_param($update_stmt, 'ss', $hashed, $member['mb_id']);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $result_msg = '임시 비밀번호가 발급되었습니다. 로그인 후 변경해주세요.';
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
                $result_msg = '등록되지 않은 이메일입니다.';
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
    <title>비밀번호 재설정 | <?php echo htmlspecialchars($site_name); ?></title>
    <!-- anti-flash -->
    <style>
    html{background:#fff;color:#212529}
    html[data-bs-theme="dark"]{background:#212529;color:#dee2e6}
    body{background:transparent}
    </style>
    <script>
    (function(){
        var t=localStorage.getItem('sb_theme')||'light';
        document.documentElement.setAttribute('data-bs-theme',t);
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .reset-logo { height:48px; width:48px; object-fit:contain; border-radius:10px; }
        [data-bs-theme="dark"] .reset-logo { filter: brightness(1.2); }
        .theme-toggle-float {
            position:fixed; top:16px; right:16px; z-index:1050;
            border-radius:50%; width:44px; height:44px;
            display:flex; align-items:center; justify-content:center;
            font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1)); cursor:pointer;
        }
        .theme-toggle-float:hover { transform:scale(1.1); }
        .reset-card { border-radius:16px; border:none; }
        .reset-brand { display:flex; flex-direction:column; align-items:center; gap:6px; margin-bottom:1.2rem; }
        .reset-brand-name { font-size:1.4rem; font-weight:700; color:var(--bs-body-color); }
        .reset-brand-sub { font-size:0.8rem; color:var(--bs-secondary-color); }
        .temp-pw-box {
            background: var(--bs-tertiary-bg);
            border: 2px dashed var(--bs-primary);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .temp-pw-box .pw-value {
            font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 800;
            font-family: 'Courier New', monospace;
            color: var(--bs-primary);
            letter-spacing: 3px;
            user-select: all;
        }
        .temp-pw-box .pw-label {
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--bs-secondary-color);
            margin-top: 6px;
        }
        <?php if ($is_app): ?>
        /* 앱 모드: 네비 숨김, 풀스크린 */
        .global-nav, .bottom-nav, .top-nav, header, footer, nav { display: none !important; }
        body { padding-top: 0 !important; padding-bottom: 0 !important; }
        <?php endif; ?>
    </style>
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <!-- 주간/야간 토글 -->
    <button class="btn btn-outline-secondary theme-toggle-float" id="themeToggle" title="주간/야간 모드">
        <i class="bi bi-moon-stars" id="themeIcon"></i>
    </button>

    <div class="card shadow reset-card" style="width:100%;max-width:420px;">
        <div class="card-body p-4">
            <div class="reset-brand">
                <img src="assets/images/seoul_beauty_logo.png" alt="<?php echo htmlspecialchars($site_name); ?>" class="reset-logo">
                <div class="reset-brand-name"><?php echo htmlspecialchars($site_name); ?></div>
                <div class="reset-brand-sub">비밀번호 재설정</div>
            </div>

            <?php if ($result_type === 'success' && !empty($temp_password)): ?>
            <!-- 성공: 임시 비밀번호 표시 -->
            <div class="alert alert-success py-2 mb-3">
                <i class="bi bi-check-circle-fill"></i> <?php echo htmlspecialchars($result_msg); ?>
            </div>

            <div class="temp-pw-box mb-3">
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

            <?php if (!$is_app): ?>
            <a href="index.php" class="btn btn-primary w-100">
                <i class="bi bi-house"></i> 홈으로 돌아가기
            </a>
            <?php else: ?>
            <p class="text-center text-body-secondary small">앱으로 돌아가서 새 비밀번호로 로그인해주세요.</p>
            <?php endif; ?>

            <?php elseif ($result_type === 'error'): ?>
            <!-- 에러 -->
            <div class="alert alert-danger py-2 mb-3">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo htmlspecialchars($result_msg); ?>
            </div>

            <form method="POST" action="password_reset.php<?php echo $is_app ? '?app=1' : ''; ?>">
                <div class="mb-3">
                    <label class="form-label">가입 이메일</label>
                    <input type="email" name="email" class="form-control" required autofocus
                           placeholder="email@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $prefill_email); ?>">
                    <div class="form-text">회원가입 시 사용한 이메일 주소를 입력하세요.</div>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary w-100">
                    <i class="bi bi-key"></i> 임시 비밀번호 발급
                </button>
            </form>

            <?php if (!$is_app): ?>
            <div class="text-center mt-3">
                <a href="index.php" class="text-body-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> 홈으로 돌아가기
                </a>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- 초기 상태: 이메일 입력 폼 -->
            <p class="text-center text-body-secondary small mb-3">
                가입 시 사용한 이메일을 입력하시면<br>임시 비밀번호를 발급해드립니다.
            </p>

            <form method="POST" action="password_reset.php<?php echo $is_app ? '?app=1' : ''; ?>">
                <div class="mb-3">
                    <label class="form-label">가입 이메일</label>
                    <input type="email" name="email" class="form-control" required autofocus
                           placeholder="email@example.com"
                           value="<?php echo htmlspecialchars($prefill_email); ?>">
                    <div class="form-text">회원가입 시 사용한 이메일 주소를 입력하세요.</div>
                </div>
                <button type="submit" name="reset_password" class="btn btn-primary w-100">
                    <i class="bi bi-key"></i> 임시 비밀번호 발급
                </button>
            </form>

            <?php if (!$is_app): ?>
            <div class="text-center mt-3">
                <a href="index.php" class="text-body-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> 홈으로 돌아가기
                </a>
            </div>
            <?php endif; ?>
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
        var btn = document.getElementById('themeToggle');
        var icon = document.getElementById('themeIcon');
        var saved = localStorage.getItem('sb_theme') || 'light';
        html.setAttribute('data-bs-theme', saved);
        if(icon) icon.className = saved === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        if(btn){
            btn.addEventListener('click', function(){
                var cur = html.getAttribute('data-bs-theme');
                var next = cur === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-bs-theme', next);
                localStorage.setItem('sb_theme', next);
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