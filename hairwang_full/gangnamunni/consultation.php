<?php
/**
 * 상담 신청 페이지 (공개 프론트엔드)
 * - hospital_id가 GET으로 전달되면 해당 병원 자동 선택
 * - hospital_id가 없으면 병원 선택 드롭다운 표시
 * - gangnamunni_consultations 테이블에 저장
 *
 * QA #17 - 상담신청 hospital_id 없이도 동작
 * @created 2026-03-04
 */

// 공통 초기화 (세션, DB, 테마 설정)
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

// 사진 업로드 공용 헬퍼
require_once __DIR__ . '/includes/photo_upload_helper.php';

$site_name = function_exists('site_name') ? site_name() : '서울미녀';

// ===== 병원 목록 조회 (드롭다운용) =====
$hospital_list = [];
$hl_result = mysqli_query($connect,
    "SELECT id, name, address FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC"
);
if ($hl_result) {
    while ($row = mysqli_fetch_assoc($hl_result)) {
        $hospital_list[] = $row;
    }
}

// ===== GET 파라미터에서 hospital_id =====
$preselected_hospital_id = intval($_GET['hospital_id'] ?? 0);
$preselected_hospital_name = '';
if ($preselected_hospital_id > 0) {
    foreach ($hospital_list as $hl) {
        if (intval($hl['id']) === $preselected_hospital_id) {
            $preselected_hospital_name = $hl['name'];
            break;
        }
    }
}

// ===== POST 처리: 상담 신청 =====
$success_msg = '';
$error_msg = '';
$form_data = [
    'hospital_id' => $preselected_hospital_id,
    'name' => '',
    'phone' => '',
    'email' => '',
    'treatment_type' => '',
    'message' => '',
    'preferred_date' => '',
    'preferred_time' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_consultation'])) {
    // CSRF 간단 체크
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error_msg = '잘못된 요청입니다. 페이지를 새로고침 후 다시 시도해주세요.';
    } else {
        $form_data['hospital_id'] = intval($_POST['hospital_id'] ?? 0);
        $form_data['name'] = trim($_POST['name'] ?? '');
        $form_data['phone'] = trim($_POST['phone'] ?? '');
        $form_data['email'] = trim($_POST['email'] ?? '');
        $form_data['treatment_type'] = trim($_POST['treatment_type'] ?? '');
        $form_data['message'] = trim($_POST['message'] ?? '');
        $form_data['preferred_date'] = trim($_POST['preferred_date'] ?? '');
        $form_data['preferred_time'] = trim($_POST['preferred_time'] ?? '');

        // 유효성 검사
        if ($form_data['hospital_id'] <= 0) {
            $error_msg = term('business', '병원') . '을 선택해주세요.';
        } elseif (empty($form_data['name'])) {
            $error_msg = '이름을 입력해주세요.';
        } elseif (empty($form_data['phone'])) {
            $error_msg = '연락처를 입력해주세요.';
        } elseif (!preg_match('/^[0-9\-]{9,15}$/', $form_data['phone'])) {
            $error_msg = '유효한 연락처를 입력해주세요.';
        } else {
            // 병원 이름 조회
            $hospital_name = '';
            $hn_stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ? AND is_active = 1");
            if ($hn_stmt) {
                mysqli_stmt_bind_param($hn_stmt, 'i', $form_data['hospital_id']);
                mysqli_stmt_execute($hn_stmt);
                $hn_result = mysqli_stmt_get_result($hn_stmt);
                $hn_row = mysqli_fetch_assoc($hn_result);
                $hospital_name = $hn_row['name'] ?? '';
                mysqli_stmt_close($hn_stmt);
            }

            if (empty($hospital_name)) {
                $error_msg = '유효하지 않은 ' . term('business', '병원') . '입니다.';
            } else {
                // DB 저장
                $ins_stmt = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_consultations (hospital_id, hospital_name, name, phone, email, treatment_type, message, preferred_date, preferred_time, status, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
                );

                if ($ins_stmt) {
                    mysqli_stmt_bind_param($ins_stmt, 'issssssss',
                        $form_data['hospital_id'],
                        $hospital_name,
                        $form_data['name'],
                        $form_data['phone'],
                        $form_data['email'],
                        $form_data['treatment_type'],
                        $form_data['message'],
                        $form_data['preferred_date'],
                        $form_data['preferred_time']
                    );

                    if (mysqli_stmt_execute($ins_stmt)) {
                        $consultation_id = mysqli_insert_id($connect);

                        // 사진 업로드 처리
                        handle_photo_upload($connect, [
                            'type'      => 'consultations',
                            'record_id' => $consultation_id,
                            'table'     => 'gangnamunni_consultations',
                            'max_files' => 3,
                        ]);

                        $success_msg = term('consultation', '상담') . ' 신청이 완료되었습니다. 담당자가 빠른 시일 내에 연락드리겠습니다.';
                        // 폼 초기화
                        $form_data = [
                            'hospital_id' => $preselected_hospital_id,
                            'name' => '',
                            'phone' => '',
                            'email' => '',
                            'treatment_type' => '',
                            'message' => '',
                            'preferred_date' => '',
                            'preferred_time' => '',
                        ];
                    } else {
                        $error_msg = '저장 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
                    }
                    mysqli_stmt_close($ins_stmt);
                } else {
                    $error_msg = '서버 오류가 발생했습니다. (' . mysqli_error($connect) . ')';
                }
            }
        }
    }
}

// CSRF 토큰 생성
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 시술 종류 옵션
$treatment_types = [
    '' => '-- 선택해주세요 --',
    '피부/레이저' => '피부/레이저',
    '보톡스/필러' => '보톡스/필러',
    '리프팅' => '리프팅',
    '눈성형' => '눈성형',
    '코성형' => '코성형',
    '가슴성형' => '가슴성형',
    '지방흡입' => '지방흡입',
    '치아교정/미백' => '치아교정/미백',
    '탈모치료' => '탈모치료',
    '제모' => '제모',
    '기타' => '기타',
];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo term('consultation', '상담'); ?> 신청 | <?php echo htmlspecialchars($site_name); ?></title>
    <!-- anti-flash -->
    <style>
    html{background:var(--bs-body-bg, #fff);color:#212529}
    body{background:transparent}
    </style>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans KR', sans-serif;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
        }

        /* 폼 컨테이너 */
        .consultation-container {
            max-width: 640px;
            margin: 0 auto;
            padding: 24px 16px 60px;
        }

        .form-card {
            border-radius: 16px;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-body-bg);
            overflow: hidden;
        }

        .form-card .card-header-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            padding: 24px;
            text-align: center;
        }
        .form-card .card-header-custom h2 {
            font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 800;
            margin: 0 0 6px;
        }
        .form-card .card-header-custom p {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            opacity: 0.85;
            margin: 0;
        }

        .form-card .card-body {
            padding: 24px;
        }

        /* 병원 선택된 상태 카드 */
        .selected-hospital-card {
            border: 2px solid var(--bs-primary);
            border-radius: 10px;
            padding: 12px 16px;
            background: var(--bs-tertiary-bg);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .selected-hospital-card .hospital-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--bs-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            flex-shrink: 0;
        }
        .selected-hospital-card .hospital-info {
            flex: 1;
        }
        .selected-hospital-card .hospital-info .name {
            font-weight: 700;
            font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
        }
        .selected-hospital-card .hospital-info .change-link {
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
        }

        /* 푸터 */
        .site-footer {
            border-top: 1px solid var(--bs-border-color);
            padding: 24px 0;
            text-align: center;
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            color: var(--bs-secondary-color);
        }
    </style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<?php
// 글로벌 네비게이션 (홈/후기/병원/이벤트/커뮤니티/칼럼 + 로그인)
$has_global_nav = file_exists(__DIR__ . '/includes/global_nav.php');
if ($has_global_nav) { include __DIR__ . '/includes/global_nav.php'; }
?>

<!-- 메인 콘텐츠 -->
<div class="consultation-container">

<!-- 광고 배너 -->
<?php
require_once __DIR__ . '/includes/settings_helper.php';
$ad_page_key = 'consultation'; include __DIR__ . '/includes/ad_banner.php';
?>

    <!-- 성공 메시지 -->
    <?php if (!empty($success_msg)): ?>
    <div class="alert alert-success d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-check-circle-fill fs-5 mt-1"></i>
        <div>
            <strong>신청 완료!</strong><br>
            <?php echo htmlspecialchars($success_msg); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 에러 메시지 -->
    <?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
        <div><?php echo htmlspecialchars($error_msg); ?></div>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="card-header-custom">
            <h2><i class="bi bi-chat-dots"></i> <?php echo term('consultation', '상담'); ?> 신청</h2>
            <p>원하시는 <?php echo term('service', '시술'); ?>에 대해 전문 <?php echo term('consultation', '상담'); ?>을 받아보세요</p>
        </div>
        <div class="card-body">
            <form method="POST" action="consultation.php<?php echo $preselected_hospital_id > 0 ? '?hospital_id=' . $preselected_hospital_id : ''; ?>" id="consultationForm" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <!-- 병원 선택 -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-building"></i> <?php echo term('business', '병원'); ?> 선택 <span class="text-danger">*</span>
                    </label>

                    <?php if ($preselected_hospital_id > 0 && !empty($preselected_hospital_name)): ?>
                    <!-- 병원이 미리 선택된 경우 -->
                    <div class="selected-hospital-card" id="selectedHospitalCard">
                        <div class="hospital-icon"><i class="bi bi-building"></i></div>
                        <div class="hospital-info">
                            <div class="name"><?php echo htmlspecialchars($preselected_hospital_name); ?></div>
                            <a href="#" class="change-link text-primary" id="changeHospitalLink">다른 <?php echo term('business', '병원'); ?> 선택</a>
                        </div>
                    </div>
                    <input type="hidden" name="hospital_id" id="hospitalIdHidden" value="<?php echo $preselected_hospital_id; ?>">

                    <!-- 숨겨진 드롭다운 (다른 병원 선택 시 표시) -->
                    <div id="hospitalDropdownWrap" style="display:none;">
                        <select class="form-select" name="hospital_id_select" id="hospitalSelect">
                            <option value="">-- <?php echo term('business', '병원'); ?>을 선택해주세요 --</option>
                            <?php foreach ($hospital_list as $hl): ?>
                            <option value="<?php echo intval($hl['id']); ?>"
                                    <?php echo (intval($hl['id']) === $preselected_hospital_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($hl['name']); ?>
                                <?php if (!empty($hl['address'])): ?>
                                (<?php echo htmlspecialchars(mb_substr($hl['address'], 0, 20)); ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="#" class="small text-secondary mt-1 d-inline-block" id="cancelChangeLink">원래 <?php echo term('business', '병원'); ?>으로 돌아가기</a>
                    </div>

                    <?php else: ?>
                    <!-- 병원이 미선택된 경우: 드롭다운 직접 표시 -->
                    <select class="form-select" name="hospital_id" id="hospitalSelectDirect" required>
                        <option value="">-- <?php echo term('business', '병원'); ?>을 선택해주세요 --</option>
                        <?php foreach ($hospital_list as $hl): ?>
                        <option value="<?php echo intval($hl['id']); ?>"
                                <?php echo (intval($hl['id']) === intval($form_data['hospital_id'])) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hl['name']); ?>
                            <?php if (!empty($hl['address'])): ?>
                            (<?php echo htmlspecialchars(mb_substr($hl['address'], 0, 20)); ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($hospital_list)): ?>
                    <div class="form-text text-warning">
                        <i class="bi bi-exclamation-triangle"></i> 현재 등록된 <?php echo term('business', '병원'); ?>이 없습니다.
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- 이름 -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-person"></i> 이름 <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="name" class="form-control" placeholder="홍길동"
                           value="<?php echo htmlspecialchars($form_data['name']); ?>" required maxlength="50">
                </div>

                <!-- 연락처 -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-telephone"></i> 연락처 <span class="text-danger">*</span>
                    </label>
                    <input type="tel" name="phone" class="form-control" placeholder="010-1234-5678"
                           value="<?php echo htmlspecialchars($form_data['phone']); ?>" required maxlength="15"
                           pattern="[0-9\-]{9,15}">
                    <div class="form-text">하이픈(-) 포함 또는 숫자만 입력해주세요.</div>
                </div>

                <!-- 이메일 -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-envelope"></i> 이메일
                    </label>
                    <input type="email" name="email" class="form-control" placeholder="example@email.com"
                           value="<?php echo htmlspecialchars($form_data['email']); ?>" maxlength="100">
                </div>

                <!-- 시술 종류 -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-clipboard2-pulse"></i> 관심 <?php echo term('service', '시술'); ?>
                    </label>
                    <select name="treatment_type" class="form-select">
                        <?php foreach ($treatment_types as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"
                                <?php echo ($form_data['treatment_type'] === $val) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 희망 날짜/시간 -->
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">
                            <i class="bi bi-calendar3"></i> 희망 날짜
                        </label>
                        <input type="date" name="preferred_date" class="form-control"
                               value="<?php echo htmlspecialchars($form_data['preferred_date']); ?>"
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">
                            <i class="bi bi-clock"></i> 희망 시간
                        </label>
                        <select name="preferred_time" class="form-select">
                            <option value="">선택</option>
                            <?php
                            $time_slots = ['09:00','09:30','10:00','10:30','11:00','11:30',
                                           '12:00','12:30','13:00','13:30','14:00','14:30',
                                           '15:00','15:30','16:00','16:30','17:00','17:30','18:00'];
                            foreach ($time_slots as $ts): ?>
                            <option value="<?php echo $ts; ?>"
                                    <?php echo ($form_data['preferred_time'] === $ts) ? 'selected' : ''; ?>>
                                <?php echo $ts; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- 상담 내용 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-chat-left-text"></i> <?php echo term('consultation', '상담'); ?> 내용
                    </label>
                    <textarea name="message" class="form-control" rows="4"
                              placeholder="궁금한 점이나 원하시는 <?php echo term('service', '시술'); ?>에 대해 자유롭게 작성해주세요."
                              maxlength="1000"><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                    <div class="form-text">최대 1,000자</div>
                </div>

                <!-- 사진 첨부 -->
                <?php
                $photo_config = [
                    'prefix'     => 'consult',
                    'max_photos' => 3,
                    'label'      => '📷 사진 첨부 (선택, 최대 3장)',
                    'consent_text' => '상담 참고용 사진을 첨부합니다. 제공된 사진은 상담 목적으로만 사용되며, 상담 완료 후 30일 이내에 삭제됩니다.',
                    'show_medical_notice' => true,
                ];
                include __DIR__ . '/includes/photo_upload_ui.php';
                ?>

                <!-- 제출 버튼 -->
                <button type="submit" name="submit_consultation" class="btn btn-primary w-100 py-2 fw-bold" id="submitBtn">
                    <i class="bi bi-send"></i> <?php echo term('consultation', '상담'); ?> 신청하기
                </button>

                <p class="text-center text-body-secondary small mt-3 mb-0">
                    <i class="bi bi-shield-check"></i> 입력하신 개인정보는 <?php echo term('consultation', '상담'); ?> 목적으로만 사용됩니다.
                </p>
            </form>
        </div>
    </div>
</div>

<!-- 푸터 -->
<footer class="site-footer">
    <div class="container">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    // 병원 변경 토글 (미리 선택된 경우)
    var changeLink = document.getElementById('changeHospitalLink');
    var cancelLink = document.getElementById('cancelChangeLink');
    var selectedCard = document.getElementById('selectedHospitalCard');
    var dropdownWrap = document.getElementById('hospitalDropdownWrap');
    var hiddenInput = document.getElementById('hospitalIdHidden');
    var selectEl = document.getElementById('hospitalSelect');

    if (changeLink) {
        changeLink.addEventListener('click', function(e) {
            e.preventDefault();
            selectedCard.style.display = 'none';
            dropdownWrap.style.display = 'block';
            // 드롭다운 값을 hidden에 반영
            if (selectEl) {
                selectEl.name = 'hospital_id';
                if (hiddenInput) hiddenInput.name = '';
            }
        });
    }

    if (cancelLink) {
        cancelLink.addEventListener('click', function(e) {
            e.preventDefault();
            selectedCard.style.display = 'flex';
            dropdownWrap.style.display = 'none';
            // hidden 복원
            if (hiddenInput) hiddenInput.name = 'hospital_id';
            if (selectEl) selectEl.name = 'hospital_id_select';
        });
    }

    // 폼 제출 시 중복 방지
    var form = document.getElementById('consultationForm');
    var submitBtn = document.getElementById('submitBtn');
    if (form && submitBtn) {
        form.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 전송 중...';
            // 3초 후 복원 (에러 등 대비)
            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send"></i> <?php echo term('consultation', '상담'); ?> 신청하기';
            }, 3000);
        });
    }
})();
</script>
</body>
</html>
<?php
mysqli_close($connect);
?>
