<?php
/**
 * 병원 등록 신청 — 공개용 (비로그인)
 * 이메일 OTP 확인, 전화번호 형식 확인, 파일 업로드 + DB INSERT
 * Bootstrap 5.3 독립 레이아웃 (관리자 헤더 없음)
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/notification_helper.php';
require_once __DIR__ . '/includes/mail_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

if (!function_exists('term')) {
    require_once __DIR__ . '/includes/industry_terms.php';
}

$page_title = term('business', '병원') . ' 등록 신청 — ' . term('site_name', '서울미녀');

// 앱 모드(?app=1): 모바일 앱 WebView 에서 회원가입 → '병원관계자' 선택 시 진입.
// 앱은 자체 헤더가 있으므로 상단 히어로 헤더를 숨기고 여백을 줄여 임베드 친화적으로 렌더한다.
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';

// register.php(회원가입 '병원관계자' 선택) 를 거쳐 진입했는지 판단 → 상단에 '이전으로' 버튼 노출.
// 앱/웹 모두 브라우저 히스토리로 회원가입 화면으로 되돌아갈 수 있게 한다.
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$from_register = (stripos($referer, 'register') !== false) || isset($_GET['from']);

// 신청 기능 활성화 체크
$apply_enabled = true;
$res_set = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='show_hospital_apply' LIMIT 1");
if ($res_set && $row_set = mysqli_fetch_assoc($res_set)) {
    $apply_enabled = ($row_set['setting_value'] === '1');
}

$message = '';
$error = '';
$success = false;

// 의료기관 개설허가증 제출 기능.
// 현재 임시 중단 상태. 다시 필요할 때 true로 바꾸면 폼과 서버 검증이 함께 활성화된다.
$require_medical_license = false;

// 간단 스팸 방지: 같은 IP에서 10분 내 3회 이상 제출 차단
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_check = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE applied_by LIKE ? AND applied_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$ip_pattern = 'public_' . $client_ip . '%';
mysqli_stmt_bind_param($rate_check, 's', $ip_pattern);
mysqli_stmt_execute($rate_check);
$rate_row = mysqli_stmt_get_result($rate_check)->fetch_assoc();
mysqli_stmt_close($rate_check);
$rate_limited = ($rate_row['cnt'] >= 3);

if (empty($_SESSION['hospital_apply_csrf'])) $_SESSION['hospital_apply_csrf'] = bin2hex(random_bytes(32));
$hospital_apply_csrf = $_SESSION['hospital_apply_csrf'];

// 신청 이메일 소유 확인용 OTP 발송
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'send_email_code') {
    header('Content-Type: application/json; charset=utf-8');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (empty($_POST['csrf_token']) || !hash_equals($hospital_apply_csrf, (string)$_POST['csrf_token'])) {
        echo json_encode(['ok'=>false, 'msg'=>'요청이 만료되었습니다.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false, 'msg'=>'올바른 이메일을 입력해 주세요.']); exit; }
    // 하나의 이메일 = 하나의 유형: 이미 일반 회원으로 가입된 이메일이면 병원관리자 신청 불가.
    if (mysqli_num_rows(mysqli_query($connect, "SELECT mb_id FROM g5_member WHERE LOWER(mb_email)=LOWER('" . mysqli_real_escape_string($connect, $email) . "') LIMIT 1"))) {
        echo json_encode(['ok'=>false, 'msg'=>'이미 일반 회원으로 가입된 이메일입니다. 다른 이메일로 신청해 주세요.']); exit;
    }
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $body = '<h2>병원 등록 신청 이메일 인증</h2><p>인증번호: <strong style="font-size:24px;letter-spacing:4px">'.$code.'</strong></p><p>3분 이내에 입력해 주세요.</p>';
    try { $sent = mail_send(0, $email, '[서울미녀] 병원 등록 이메일 인증번호', $body); $ok = is_array($sent) ? !empty($sent['success']) : (bool)$sent; } catch (Throwable $e) { $ok = false; error_log('[hospital_apply] OTP 예외: '.$e->getMessage()); }
    if (!$ok) { echo json_encode(['ok'=>false, 'msg'=>'인증메일 발송에 실패했습니다.']); exit; }
    $_SESSION['hospital_apply_email'] = $email; $_SESSION['hospital_apply_code'] = $code; $_SESSION['hospital_apply_code_expire'] = time()+180; $_SESSION['hospital_apply_code_attempts'] = 0;
    echo json_encode(['ok'=>true, 'msg'=>'인증번호를 발송했습니다.']); exit;
}

// ===== POST 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $apply_enabled && !$rate_limited) {
    // 허니팟 스팸 체크
    if (!empty($_POST['website_url_hp'])) {
        // 봇으로 판단, 조용히 무시
        $success = true;
        $message = '신청이 접수되었습니다.';
    } else {
        $name = trim($_POST['hospital_name'] ?? '');
        $owner_name = trim($_POST['owner_name'] ?? '');
        $business_number = trim($_POST['business_number'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $contact_name = trim($_POST['contact_name'] ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        // 공개 신청 폼에서는 진료과목을 받지 않는다. JSON 컬럼에는 빈 배열을 저장한다.
        $categories = '[]';
        $treatment_tags = trim($_POST['treatment_tags'] ?? '');
        $business_hours = trim($_POST['business_hours'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $website = trim($_POST['website'] ?? '');
        $sns_url = trim($_POST['sns_url'] ?? '');

        $agree_terms = isset($_POST['agree_terms']);
        $agree_privacy = isset($_POST['agree_privacy']);

        // 유효성 검사
        if (empty($name) || empty($owner_name) || empty($business_number) || empty($phone) || empty($email) || empty($contact_name) || empty($contact_phone) || empty($address)) {
            $error = '필수 항목을 모두 입력해 주세요.';
        } elseif (!$agree_terms || !$agree_privacy) {
            $error = '필수 약관에 동의해 주세요.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '올바른 이메일 주소를 입력해 주세요.';
        } elseif (!preg_match('/^[0-9+() .-]{7,30}$/', $phone) || !preg_match('/^[0-9+() .-]{7,30}$/', $contact_phone)) {
            $error = '전화번호 형식을 확인해 주세요.';
        } elseif (empty($_SESSION['hospital_apply_code']) || strtolower((string)($_SESSION['hospital_apply_email'] ?? '')) !== $email || time() > (int)($_SESSION['hospital_apply_code_expire'] ?? 0)) {
            $error = '이메일 인증을 먼저 완료해 주세요.';
        } elseif (!hash_equals((string)$_SESSION['hospital_apply_code'], trim((string)($_POST['email_code'] ?? '')))) {
            $_SESSION['hospital_apply_code_attempts'] = (int)($_SESSION['hospital_apply_code_attempts'] ?? 0) + 1;
            if ($_SESSION['hospital_apply_code_attempts'] >= 5) unset($_SESSION['hospital_apply_code'], $_SESSION['hospital_apply_email']);
            $error = '이메일 인증번호가 올바르지 않습니다.';
        } elseif (mysqli_num_rows(mysqli_query($connect, "SELECT mb_id FROM g5_member WHERE LOWER(mb_email)=LOWER('" . mysqli_real_escape_string($connect, $email) . "') LIMIT 1"))) {
            // 하나의 이메일 = 하나의 유형: 이미 일반 회원으로 가입된 이메일이면 병원관리자 신청 차단.
            $error = '이미 일반 회원으로 가입된 이메일입니다. 하나의 이메일은 일반회원 또는 병원관리자 중 하나로만 가입할 수 있습니다.';
        } elseif (mysqli_num_rows(mysqli_query($connect, "SELECT id FROM gangnamunni_hospitals WHERE (LOWER(email)=LOWER('" . mysqli_real_escape_string($connect, $email) . "') OR business_number='" . mysqli_real_escape_string($connect, $business_number) . "') AND status IN ('pending','approved') LIMIT 1"))) {
            $error = '이미 등록 신청된 이메일 또는 사업자등록번호입니다.';
        } else {
            // 파일 업로드
            $applied_by = 'public_' . $client_ip . '_' . time();
            $upload_dir = __DIR__ . '/uploads/hospital_apply/' . md5($applied_by) . '/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $cert_business_path = '';
            $cert_medical_path = '';
            $profile_image_path = '';

            // 사업자등록증 (필수)
            if (!empty($_FILES['cert_business']['name']) && $_FILES['cert_business']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cert_business']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','pdf']) && $_FILES['cert_business']['size'] <= 5*1024*1024) {
                    $fname = 'cert_business_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['cert_business']['tmp_name'], $upload_dir . $fname)) {
                        $cert_business_path = 'uploads/hospital_apply/' . md5($applied_by) . '/' . $fname;
                    }
                } else {
                    $error = '사업자등록증: JPG/PNG/PDF, 5MB 이하만 가능합니다.';
                }
            } else {
                $error = '사업자등록증은 필수 서류입니다.';
            }

            // 의료기관 허가증 (기능 활성화 시 필수)
            if ($require_medical_license && empty($error) && !empty($_FILES['cert_medical']['name']) && $_FILES['cert_medical']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['cert_medical']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','pdf']) && $_FILES['cert_medical']['size'] <= 5*1024*1024) {
                    $fname = 'cert_medical_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['cert_medical']['tmp_name'], $upload_dir . $fname)) {
                        $cert_medical_path = 'uploads/hospital_apply/' . md5($applied_by) . '/' . $fname;
                    }
                } else {
                    $error = '의료기관 허가증: JPG/PNG/PDF, 5MB 이하만 가능합니다.';
                }
            } elseif ($require_medical_license && empty($error)) {
                $error = '의료기관 개설허가증은 필수 서류입니다.';
            }

            // 대표 이미지 (선택)
            if (empty($error) && !empty($_FILES['profile_image']['name']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png']) && $_FILES['profile_image']['size'] <= 5*1024*1024) {
                    $fname = 'profile_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $fname)) {
                        $profile_image_path = 'uploads/hospital_apply/' . md5($applied_by) . '/' . $fname;
                    }
                }
            }

            // JSON 컬럼 정규화 — gangnamunni_hospitals 에 json_valid() CHECK 제약이 걸려 있어
            // (treatment_tags/business_hours) 반드시 유효한 JSON 이어야 함.
            // CSV 문자열/빈 값을 그대로 넣으면 CHECK 위반으로 INSERT 가 실패(500)한다.
            // admin_hospitals.php 의 승인 로직과 동일한 방식으로 정규화한다.
            $normalize_json_list = function($raw) {
                $raw = trim($raw);
                if ($raw === '') return '[]';
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $raw; // 이미 유효한 JSON
                $items = array_values(array_filter(array_map('trim', explode(',', $raw)), function($v){ return $v !== ''; }));
                return json_encode($items, JSON_UNESCAPED_UNICODE);
            };
            $treatment_tags = $normalize_json_list($treatment_tags);
            // 영업시간: 자유 텍스트이므로 JSON 객체로 저장(원문은 note 에 보존) — {} 로 버리지 않고 유지
            $business_hours = ($business_hours === '')
                ? '{}'
                : (is_array(json_decode($business_hours, true)) ? $business_hours
                   : json_encode(['note' => $business_hours], JSON_UNESCAPED_UNICODE));

            // DB INSERT
            if (empty($error)) {
                $stmt = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_hospitals (name, owner_name, business_number, phone, email, contact_name, contact_phone, address, categories, treatment_tags, business_hours, description, website, sns_url, profile_image_url, cert_business, cert_medical, applied_by, applied_at, status, is_active, ad_grade, is_demo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending', 0, 'none', ?, NOW())"
                );
                if ($stmt) {
                    $is_demo_val = isDevMode($connect) ? 1 : 0;
                    mysqli_stmt_bind_param($stmt, 'ssssssssssssssssssi',
                        $name, $owner_name, $business_number, $phone, $email,
                        $contact_name, $contact_phone, $address, $categories,
                        $treatment_tags, $business_hours, $description,
                        $website, $sns_url, $profile_image_path,
                        $cert_business_path, $cert_medical_path, $applied_by,
                        $is_demo_val
                    );
                    // mysqli 가 STRICT 모드(예외 발생)로 설정돼 있어 execute 실패 시 throw 되므로
                    // try/catch 로 감싸 500 대신 사용자 친화적 오류 메시지를 보여준다.
                    $insert_ok = false;
                    try {
                        $insert_ok = mysqli_stmt_execute($stmt);
                    } catch (\mysqli_sql_exception $e) {
                        $insert_ok = false;
                        error_log('[hospital_apply] INSERT 실패: ' . $e->getMessage());
                    }
                    if ($insert_ok) {
                        $new_id = mysqli_insert_id($connect);
                        $success = true;
                        unset($_SESSION['hospital_apply_code'], $_SESSION['hospital_apply_email'], $_SESSION['hospital_apply_code_expire'], $_SESSION['hospital_apply_code_attempts']);
                        $message = "병원 등록 신청이 접수되었습니다. (신청번호: #{$new_id})";

                        // 관리자 이메일
                        if (file_exists(__DIR__ . '/includes/mail_helper.php')) {
                            require_once __DIR__ . '/includes/mail_helper.php';
                            $admin_body = "<h2>새 병원 등록 신청 (공개 폼)</h2>
                                <p><strong>병원명:</strong> " . htmlspecialchars($name) . "</p>
                                <p><strong>대표자:</strong> " . htmlspecialchars($owner_name) . "</p>
                                <p><strong>사업자번호:</strong> " . htmlspecialchars($business_number) . "</p>
                                <p><strong>이메일:</strong> " . htmlspecialchars($email) . "</p>
                                <p><strong>담당자:</strong> " . htmlspecialchars($contact_name) . " / " . htmlspecialchars($contact_phone) . "</p>
                                <p><a href='" . SITE_FULL_URL . "admin_hospitals.php?tab=pending'>신청 목록 확인</a></p>";
                            try {
                                $ares = mail_send(0, 'admin@seoulbeauty.app', '[서울미녀] 새 병원 등록 신청 - ' . $name, $admin_body);
                                if (!(is_array($ares) ? !empty($ares['success']) : $ares)) error_log('[hospital_apply] 관리자 알림 메일 실패');
                            } catch (\Throwable $e) { error_log('[hospital_apply] 관리자 알림 메일 예외: ' . $e->getMessage()); }
                        }

                        // 신청자 확인 이메일
                        if (!empty($email) && file_exists(__DIR__ . '/includes/mail_helper.php')) {
                            $user_body = "<h2>병원 등록 신청이 접수되었습니다</h2>
                                <p>안녕하세요, <strong>" . htmlspecialchars($contact_name) . "</strong>님.</p>
                                <p><strong>" . htmlspecialchars($name) . "</strong>의 입점 신청이 정상적으로 접수되었습니다.</p>
                                <p>심사 결과는 1~3 영업일 내에 이메일로 안내드리겠습니다.</p>
                                <p>감사합니다.<br>" . term('site_name', '서울미녀') . " 팀</p>";
                            try {
                                $ures = mail_send(0, $email, '[서울미녀] 병원 등록 신청 접수 확인', $user_body);
                                if (!(is_array($ures) ? !empty($ures['success']) : $ures)) error_log('[hospital_apply] 신청자 확인 메일 실패 → ' . $email);
                            } catch (\Throwable $e) { error_log('[hospital_apply] 신청자 확인 메일 예외: ' . $e->getMessage()); }
                        }

                        // 관리자 FCM + 인앱 알림
                        notifyAdmins($connect, 'hospital_apply',
                            '새 병원 등록 신청 (공개)',
                            htmlspecialchars($name) . ' (신청번호 #' . $new_id . ')',
                            'admin_hospitals.php?tab=pending',
                            ['hospital_id' => $new_id]
                        );
                    } else {
                        $error = '신청 저장에 실패했습니다. 잠시 후 다시 시도해 주세요.';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = 'SQL 준비 실패: ' . mysqli_error($connect);
                }
            }
        }
    }
}

// mysqli_close는 페이지 끝에서 (dev_mode 체크 위해)
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root { --sb-primary: #6c5ce7; --sb-gradient: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 50%, #fd79a8 100%); }
        body { font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; background: #f8f9fa; }
        .form-header { background: var(--sb-gradient); color: #fff; padding: 40px 0; text-align: center; }
        .form-header h1 { font-size: 2rem; font-weight: 800; }
        .form-card { max-width: 800px; margin: -30px auto 40px; border-radius: 16px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .section-title { font-weight: 700; border-bottom: 2px solid var(--sb-primary); padding-bottom: 8px; margin-bottom: 16px; color: var(--sb-primary); }
        .btn-submit { background: var(--sb-primary); border: none; padding: 14px 50px; font-size: 1.1rem; font-weight: 700; border-radius: 50px; }
        .btn-submit:hover { background: #5a4bd1; }
        .hp-field { position: absolute; left: -9999px; }
        <?php if ($is_app_mode): ?>
        /* 앱 모드: 히어로 헤더 숨김 + 여백 축소 (앱 자체 헤더 존재) */
        body.app-mode { background: #fff; }
        body.app-mode .form-card { margin-top: 16px; box-shadow: none; border: 1px solid #eee; }
        body.app-mode .container { padding-left: 12px; padding-right: 12px; }
        <?php endif; ?>
    </style>
</head>
<body class="<?= $is_app_mode ? 'app-mode' : '' ?>">

<?php if (!$is_app_mode): ?>
<!-- 상단 (웹 전용 히어로 헤더) -->
<div class="form-header">
    <div class="container">
        <h1><i class="bi bi-hospital me-2"></i><?= term('business', '병원') ?> 등록 신청</h1>
        <p class="mb-0 opacity-75"><?= term('site_name', '서울미녀') ?> 플랫폼 입점 신청서</p>
    </div>
</div>
<?php endif; ?>

<div class="container">

<?php if ($from_register): ?>
<!-- 회원가입(register.php) 을 거쳐 진입 → 이전으로 돌아가기 -->
<div class="mx-auto pt-3" style="max-width: 800px;">
    <a href="javascript:history.back()" class="btn btn-link text-decoration-none ps-0 text-muted">
        <i class="bi bi-chevron-left me-1"></i>이전으로
    </a>
</div>
<?php endif; ?>

<?php if (!$apply_enabled): ?>
    <div class="card form-card">
        <div class="card-body text-center py-5">
            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
            <h4 class="mt-3">현재 신청을 받고 있지 않습니다</h4>
            <p class="text-muted">병원 등록 신청은 준비 중입니다. 나중에 다시 확인해 주세요.</p>
            <a href="hospital_landing.php" class="btn btn-outline-primary mt-2">입점 안내 보기</a>
        </div>
    </div>

<?php elseif ($rate_limited): ?>
    <div class="card form-card">
        <div class="card-body text-center py-5">
            <i class="bi bi-clock text-warning" style="font-size: 4rem;"></i>
            <h4 class="mt-3">잠시 후 다시 시도해 주세요</h4>
            <p class="text-muted">너무 많은 신청이 감지되었습니다. 10분 후 다시 시도해 주세요.</p>
        </div>
    </div>

<?php elseif ($success): ?>
    <div class="card form-card">
        <div class="card-body text-center py-5">
            <i class="bi bi-check-circle text-success" style="font-size: 5rem;"></i>
            <h2 class="fw-bold mt-3">신청이 접수되었습니다!</h2>
            <p class="text-muted fs-5"><?= htmlspecialchars($message) ?></p>
            <p class="text-muted">심사 결과는 1~3 영업일 내에 입력하신 이메일로 안내드립니다.</p>
            <div class="mt-4">
                <a href="hospital_landing.php" class="btn btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>입점 안내로 돌아가기</a>
            </div>
        </div>
    </div>

<?php else: ?>

    <?php if ($error): ?>
        <div class="alert alert-danger mx-auto mt-3" style="max-width: 800px;">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <div class="mx-auto mt-3" style="max-width: 800px;">
            <?php renderDevModeButton('hospital_apply'); ?>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <div class="card-body p-4 p-md-5">
            <form method="POST" enctype="multipart/form-data" id="publicApplyForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($hospital_apply_csrf, ENT_QUOTES) ?>">
                <!-- 허니팟 (봇 방지) -->
                <input type="text" name="website_url_hp" class="hp-field" tabindex="-1" autocomplete="off">

                <!-- 필수/선택 안내 -->
                <div class="alert alert-light border small mb-4 py-2">
                    <span class="text-danger fw-bold">*</span> 표시는 <strong>필수</strong> 입력 항목입니다. 표시가 없는 항목은 <span class="text-muted">선택</span> 입력입니다.
                </div>

                <!-- 기본 정보 -->
                <h5 class="section-title"><i class="bi bi-building me-2"></i>기본 정보</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원명 <span class="text-danger">*</span></label>
                        <input type="text" name="hospital_name" class="form-control" required maxlength="255" value="<?= htmlspecialchars($_POST['hospital_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">대표자명 <span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" class="form-control" required maxlength="100" value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">사업자등록번호 <span class="text-danger">*</span></label>
                        <input type="text" name="business_number" class="form-control" required maxlength="20" placeholder="000-00-00000" value="<?= htmlspecialchars($_POST['business_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 전화번호 <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" required maxlength="50" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 이메일 <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required maxlength="191" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <div class="form-text">심사 결과 및 관리자 계정이 이 이메일로 발송됩니다.</div>
                        <div class="input-group mt-2"><input type="text" name="email_code" class="form-control" inputmode="numeric" maxlength="6" placeholder="이메일 인증번호"><button type="button" class="btn btn-outline-primary" id="emailVerifyBtn">인증번호 발송</button></div>
                        <div id="emailVerifyMsg" class="form-text"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">담당자명 <span class="text-danger">*</span></label>
                        <input type="text" name="contact_name" class="form-control" required maxlength="100" value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">담당자 전화 <span class="text-danger">*</span></label>
                        <input type="tel" name="contact_phone" class="form-control" required maxlength="20" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>">
                    </div>
                </div>

                <!-- 병원 정보 -->
                <h5 class="section-title"><i class="bi bi-geo-alt me-2"></i>병원 정보</h5>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label fw-semibold">병원 주소 <span class="text-danger">*</span></label>
                        <input type="text" name="address" class="form-control" required maxlength="500" placeholder="도로명 주소" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">주요 시술 (콤마 구분) <span class="text-muted fw-normal">(선택)</span></label>
                        <input type="text" name="treatment_tags" class="form-control" placeholder="예: 코성형, 눈성형, 지방흡입" value="<?= htmlspecialchars($_POST['treatment_tags'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">영업시간 <span class="text-muted fw-normal">(선택)</span></label>
                        <input type="text" name="business_hours" class="form-control" placeholder="예: 평일 10:00~19:00" value="<?= htmlspecialchars($_POST['business_hours'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">홈페이지 <span class="text-muted fw-normal">(선택)</span></label>
                        <input type="url" name="website" class="form-control" placeholder="https://" value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">SNS (인스타그램 등) <span class="text-muted fw-normal">(선택)</span></label>
                        <input type="text" name="sns_url" class="form-control" placeholder="https://instagram.com/..." value="<?= htmlspecialchars($_POST['sns_url'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">병원 소개 <span class="text-muted fw-normal">(선택)</span></label>
                        <textarea name="description" class="form-control" rows="4" maxlength="2000"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- 서류 업로드 -->
                <h5 class="section-title"><i class="bi bi-cloud-upload me-2"></i>서류 업로드</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">사업자등록증 <span class="text-danger">*</span></label>
                        <input type="file" name="cert_business" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG/PNG/PDF, 5MB 이하</div>
                    </div>
                    <?php if ($require_medical_license): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">의료기관 개설허가증 <span class="text-danger">*</span></label>
                        <input type="file" name="cert_medical" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG/PNG/PDF, 5MB 이하</div>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">병원 대표 이미지 <span class="text-muted fw-normal">(선택)</span></label>
                        <input type="file" name="profile_image" class="form-control" accept=".jpg,.jpeg,.png">
                        <div class="form-text">JPG/PNG, 5MB 이하 (선택)</div>
                    </div>
                </div>

                <!-- 약관 -->
                <h5 class="section-title"><i class="bi bi-shield-check me-2"></i>약관 동의</h5>
                <div class="mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeTerms" required>
                        <label class="form-check-label" for="agreeTerms">
                            <span class="badge bg-danger me-1">필수</span> <?= term('site_name', '서울미녀') ?> 병원 입점 약관에 동의합니다.
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="agree_privacy" id="agreePrivacy" required>
                        <label class="form-check-label" for="agreePrivacy">
                            <span class="badge bg-danger me-1">필수</span> 개인정보 수집/이용에 동의합니다.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="agree_marketing" id="agreeMarketing">
                        <label class="form-check-label" for="agreeMarketing">
                            <span class="badge bg-secondary me-1">선택</span> 마케팅 수신에 동의합니다.
                        </label>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-submit">
                        <i class="bi bi-send me-2"></i>신청서 제출
                    </button>
                    <p class="text-muted small mt-3">제출 후 1~3 영업일 내에 심사 결과를 이메일로 안내드립니다.</p>
                </div>
</form>
        </div>
    </div>

    <!-- 이미 관리자 계정이 있다면 -->
    <div class="text-center mb-5">
        <p class="text-muted">이미 관리자 계정이 있으신가요?
            <a href="dashboard.php" class="fw-semibold">관리자 로그인</a>에서 신청하실 수도 있습니다.
        </p>
    </div>

<?php endif; ?>

</div>

<footer class="text-center py-4 text-muted small">
    &copy; <?= date('Y') ?> <?= term('site_name', '서울미녀') ?>. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelector('[name="business_number"]')?.addEventListener('input', function(e) {
    var v = e.target.value.replace(/[^0-9]/g, '');
    if (v.length > 3 && v.length <= 5) v = v.slice(0,3) + '-' + v.slice(3);
    else if (v.length > 5) v = v.slice(0,3) + '-' + v.slice(3,5) + '-' + v.slice(5,10);
    e.target.value = v;
});
document.getElementById('emailVerifyBtn')?.addEventListener('click', function() {
    var email = document.querySelector('[name="email"]').value.trim(), msg = document.getElementById('emailVerifyMsg'), btn = this;
    if (!email) { msg.textContent = '이메일을 먼저 입력해 주세요.'; return; }
    btn.disabled = true; msg.textContent = '발송 중...';
    fetch('hospital_apply_public.php?app=1', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({mode:'send_email_code', email:email, csrf_token:'<?= htmlspecialchars($hospital_apply_csrf, ENT_QUOTES) ?>'})})
      .then(function(r){return r.json();}).then(function(d){msg.textContent=d.msg||''; if(!d.ok) btn.disabled=false; else setTimeout(function(){btn.disabled=false;},180000);})
      .catch(function(){msg.textContent='네트워크 오류가 발생했습니다.';btn.disabled=false;});
});
</script>
<?php if (isDevMode($connect)): ?>
    <?= getDevModeJS_hospital_apply(); ?>
<?php endif; ?>
<?php mysqli_close($connect); ?>
</body>
</html>
