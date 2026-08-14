<?php
session_start();
$admin_page_title = '마법사 사용법 가이드';
$admin_current = 'wizard_guide';
$admin_extra_css = [];
$admin_extra_js = [];
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
include __DIR__ . '/includes/admin_header.php';

$guideUrl = 'uploads/guide/';
$guideDir = __DIR__ . '/' . $guideUrl;
$pngCount = is_dir($guideDir) ? count(glob($guideDir . '*.png')) : 0;

// AJAX: 스크린샷 캡처
if (isset($_GET['action']) && $_GET['action'] === 'capture') {
    header('Content-Type: application/json');
    $engineDir = __DIR__ . '/auto_manual_engine';
    $script = $guideDir . 'capture_wizards.js';
    if (!file_exists($script)) { echo json_encode(['ok'=>false,'msg'=>'스크립트 없음']); exit; }
    $cmd = "cd {$engineDir} && NODE_PATH={$engineDir}/node_modules timeout 180 node {$script} 2>&1";
    $output = shell_exec($cmd);
    $count = count(glob($guideDir . '*.png'));
    echo json_encode(['ok'=>$count>0,'count'=>$count,'output'=>$output]);
    exit;
}

// ═══ 마법사 데이터 정의 ═══
$sections = [
    // ── 0. 로그인 ──
    [
        'id'=>'login', 'icon'=>'🔐', 'color'=>'#6c757d', 'title'=>'로그인 / 접속 방법',
        'desc'=>'관리자 대시보드에 로그인하는 방법입니다.',
        'screenshots'=>[
            ['file'=>'login_form.png', 'label'=>'로그인 화면', 'desc'=>'관리자 아이디(admin)와 비밀번호(admin123)를 입력한 상태입니다.'],
            ['file'=>'dashboard_after_login.png', 'label'=>'대시보드', 'desc'=>'로그인 성공 후 대시보드 화면입니다. 통계카드와 관리 메뉴가 표시됩니다.'],
        ],
        'fields'=>[
            ['name'=>'아이디', 'required'=>true, 'desc'=>'관리자 계정 아이디', 'example'=>'admin'],
            ['name'=>'비밀번호', 'required'=>true, 'desc'=>'관리자 계정 비밀번호', 'example'=>'admin123'],
        ],
        'tips'=>['기본 계정: admin / admin123', '로그아웃: URL에 ?logout=1 추가'],
    ],

    // ── 2. 간호사 ──
    [
        'id'=>'nurse', 'icon'=>'👩‍⚕️', 'color'=>'#17a2b8', 'title'=>'간호사 등록 마법사',
        'file'=>'admin_nurse_wizard.php',
        'desc'=>'새로운 간호사를 등록합니다.',
        'tables'=>['users','nurses','model_has_roles'],
        'screenshots'=>[
            ['file'=>'nurse_step1.png', 'label'=>'Step 1: 기본 정보', 'desc'=>'이영희 간호사의 이름, 이메일, 전화번호, 성별(여성), 생년월일을 입력한 예시입니다.'],
            ['file'=>'nurse_step2.png', 'label'=>'Step 2: 소속/자격', 'desc'=>'병원을 검색 선택하고, 자격증(정간호사 면허)과 소개를 입력한 예시입니다.'],
            ['file'=>'nurse_step3.png', 'label'=>'Step 3: 주소/계정', 'desc'=>'서울 서초구 주소와 비밀번호를 입력한 예시입니다.'],
            ['file'=>'nurse_step4.png', 'label'=>'Step 4: 검토 완료', 'desc'=>'입력한 모든 정보를 확인하고 등록 버튼을 클릭합니다.'],
        ],
        'fields'=>[
            ['name'=>'이름/성', 'required'=>true, 'desc'=>'간호사 이름', 'example'=>'영희 / 김'],
            ['name'=>'이메일', 'required'=>true, 'desc'=>'로그인 계정 (중복 불가)', 'example'=>'nurse@example.com'],
            ['name'=>'전화번호', 'required'=>true, 'desc'=>'연락처', 'example'=>'010-2345-6789'],
            ['name'=>'병원', 'required'=>true, 'desc'=>'소속 병원 선택', 'example'=>'Ahmed Lang'],
            ['name'=>'자격증', 'required'=>false, 'desc'=>'간호사 자격증 정보', 'example'=>'정간호사'],
        ],
        'tips'=>['department_id=4 자동 배정','role_id=4 (Nurse)','owner_type: App\\Models\\Nurse'],
    ],

    // ── 3. 약사 ──
    [
        'id'=>'pharmacist', 'icon'=>'💊', 'color'=>'#6f42c1', 'title'=>'약사 등록 마법사',
        'file'=>'admin_pharmacist_wizard.php',
        'desc'=>'새로운 약사를 등록합니다. 면허번호를 포함합니다.',
        'tables'=>['users','pharmacists','model_has_roles'],
        'screenshots'=>[
            ['file'=>'pharmacist_step1.png', 'label'=>'Step 1: 기본 정보', 'desc'=>'박준서 약사의 이름, 이메일, 전화번호, 성별, 생년월일을 입력한 예시입니다.'],
            ['file'=>'pharmacist_step2.png', 'label'=>'Step 2: 소속/자격', 'desc'=>'병원 선택, 면허번호(PH-2026-00142), 자격증을 입력한 예시입니다.'],
            ['file'=>'pharmacist_step3.png', 'label'=>'Step 3: 주소/계정', 'desc'=>'서울 송파구 주소와 비밀번호를 입력한 예시입니다.'],
            ['file'=>'pharmacist_step4.png', 'label'=>'Step 4: 검토 완료', 'desc'=>'입력한 모든 정보를 확인하고 등록 버튼을 클릭합니다.'],
        ],
        'fields'=>[
            ['name'=>'이름/성', 'required'=>true, 'desc'=>'약사 이름', 'example'=>'철수 / 박'],
            ['name'=>'이메일', 'required'=>true, 'desc'=>'로그인 계정', 'example'=>'pharm@example.com'],
            ['name'=>'면허번호', 'required'=>false, 'desc'=>'약사 면허번호', 'example'=>'PH-2024-001'],
        ],
        'tips'=>['department_id=6 자동 배정','role_id=6 (Pharmacist)','owner_type: App\\Models\\Pharmacist'],
    ],

    // ── 4. 환자 ──
    [
        'id'=>'patient', 'icon'=>'🧑', 'color'=>'#dc3545', 'title'=>'환자 등록 마법사',
        'file'=>'admin_patient_wizard.php',
        'desc'=>'새로운 환자를 등록합니다. 고유번호(PAT-XXXXXX)가 자동 발급됩니다.',
        'tables'=>['users','patients','model_has_roles'],
        'screenshots'=>[
            ['file'=>'patient_step1.png', 'label'=>'Step 1: 기본 정보', 'desc'=>'최수진 환자의 이름, 이메일, 전화, 성별(여성), 생년월일(필수), 혈액형(AB+)을 입력한 예시입니다.'],
            ['file'=>'patient_step2.png', 'label'=>'Step 2: 의료 정보', 'desc'=>'병원 선택, 보험(국민건강보험), 알레르기(페니실린), 응급연락처를 입력한 예시입니다.'],
            ['file'=>'patient_step3.png', 'label'=>'Step 3: 주소/계정', 'desc'=>'서울 마포구 주소와 비밀번호를 입력한 예시입니다.'],
            ['file'=>'patient_step4.png', 'label'=>'Step 4: 검토 완료', 'desc'=>'입력한 정보를 확인합니다. 환자번호(PAT-XXXXXX)가 자동으로 생성됩니다.'],
        ],
        'fields'=>[
            ['name'=>'이름/성', 'required'=>true, 'desc'=>'환자 이름', 'example'=>'민수 / 이'],
            ['name'=>'이메일', 'required'=>true, 'desc'=>'로그인 계정', 'example'=>'patient@example.com'],
            ['name'=>'생년월일', 'required'=>true, 'desc'=>'환자는 생년월일 필수', 'example'=>'1990-01-15'],
            ['name'=>'보험사', 'required'=>false, 'desc'=>'건강보험 정보', 'example'=>'국민건강보험'],
            ['name'=>'알레르기', 'required'=>false, 'desc'=>'알레르기 정보', 'example'=>'페니실린'],
            ['name'=>'응급연락처', 'required'=>false, 'desc'=>'비상시 연락처', 'example'=>'010-9999-0000'],
        ],
        'tips'=>['patient_unique_id (PAT-XXXXXX) 자동 생성','생년월일이 필수 입력','department_id=3, role_id=3 (Patient)'],
    ],

    // ── 5. 직원 ──
    [
        'id'=>'staff', 'icon'=>'👔', 'color'=>'#795548', 'title'=>'직원 등록 마법사',
        'file'=>'admin_staff_wizard.php',
        'desc'=>'접수원/행정직원을 등록합니다. 4가지 직종 카드에서 선택합니다.',
        'tables'=>['users','receptionists','model_has_roles'],
        'screenshots'=>[
            ['file'=>'staff_step1.png', 'label'=>'Step 1: 기본 정보', 'desc'=>'강지현 직원의 이름, 이메일, 전화번호, 성별(여성)을 입력한 예시입니다.'],
            ['file'=>'staff_step2.png', 'label'=>'Step 2: 직종/소속', 'desc'=>'접수원 카드를 선택하고, 병원과 자격증을 입력한 예시입니다.'],
            ['file'=>'staff_step3.png', 'label'=>'Step 3: 주소/계정', 'desc'=>'서울 종로구 주소와 비밀번호를 입력한 예시입니다.'],
            ['file'=>'staff_step4.png', 'label'=>'Step 4: 검토 완료', 'desc'=>'입력한 모든 정보를 확인하고 등록 버튼을 클릭합니다.'],
        ],
        'fields'=>[
            ['name'=>'이름/성', 'required'=>true, 'desc'=>'직원 이름', 'example'=>'지영 / 최'],
            ['name'=>'이메일', 'required'=>true, 'desc'=>'로그인 계정', 'example'=>'staff@example.com'],
            ['name'=>'직종', 'required'=>true, 'desc'=>'4종 카드에서 선택', 'example'=>'접수원'],
        ],
        'tips'=>['Receptionist 모델로 저장','department_id=5, role_id=5','4가지 직종 카드 UI로 선택'],
    ],

    // ── 6. 의약품 ──
    [
        'id'=>'medicine', 'icon'=>'💉', 'color'=>'#ffc107', 'title'=>'의약품 등록 마법사',
        'file'=>'admin_medicine_wizard.php',
        'desc'=>'새로운 의약품을 등록합니다. 가격, 수량, 성분, 부작용 정보를 포함합니다.',
        'tables'=>['medicines','categories','brands'],
        'screenshots'=>[
            ['file'=>'medicine_step1.png', 'label'=>'Step 1: 기본 정보', 'desc'=>'타이레놀 500mg의 약품명, 카테고리, 브랜드, 주성분(아세트아미노펜)을 입력한 예시입니다.'],
            ['file'=>'medicine_step2.png', 'label'=>'Step 2: 가격/수량', 'desc'=>'판매가 15,000원, 매입가 8,000원, 수량 200개를 설정한 예시입니다. 마진율 87.5% 자동 계산.'],
            ['file'=>'medicine_step3.png', 'label'=>'Step 3: 추가 정보', 'desc'=>'보관방법(실온 15~30°C)과 유효기간을 입력한 예시입니다.'],
            ['file'=>'medicine_step4.png', 'label'=>'Step 4: 검토 완료', 'desc'=>'입력한 모든 정보를 확인하고 등록 버튼을 클릭합니다.'],
        ],
        'fields'=>[
            ['name'=>'약품명', 'required'=>true, 'desc'=>'의약품 이름', 'example'=>'타이레놀 500mg'],
            ['name'=>'카테고리', 'required'=>false, 'desc'=>'categories 테이블에서 선택 (직접 입력 가능)', 'example'=>'피부관리'],
            ['name'=>'판매가', 'required'=>true, 'desc'=>'판매 가격', 'example'=>'15000'],
            ['name'=>'매입가', 'required'=>false, 'desc'=>'매입 가격', 'example'=>'8000'],
            ['name'=>'수량', 'required'=>true, 'desc'=>'초기 재고 수량', 'example'=>'100'],
        ],
        'tips'=>['카테고리/브랜드는 "직접 입력" 토글 가능','마진율 자동 계산 (판매가-매입가)/매입가','quantity와 available_quantity 동일 설정'],
    ],

    // ── 7. 예약 ──
    [
        'id'=>'appointment', 'icon'=>'📅', 'color'=>'#fd7e14', 'title'=>'예약 등록 마법사',
        'file'=>'admin_appointment_wizard.php',
        'desc'=>'새로운 진료 예약을 등록합니다. 환자/의사를 선택하고 일시를 지정합니다.',
        'tables'=>['appointments'],
        'screenshots'=>[
            ['file'=>'appointment_step1.png', 'label'=>'Step 1: 환자 선택', 'desc'=>'병원 선택 후 "김"으로 환자를 검색하여 선택한 예시입니다. 선택된 환자 카드가 표시됩니다.'],
            ['file'=>'appointment_step2.png', 'label'=>'Step 2: 의사/진료과', 'desc'=>'진료과를 선택하면 의사 카드 그리드가 표시됩니다. 의사를 클릭하면 진료비가 자동 설정됩니다.'],
            ['file'=>'appointment_step3.png', 'label'=>'Step 3: 일시/증상', 'desc'=>'내일 날짜를 선택하고, 가용 타임슬롯 중 하나를 선택, 결제방식(카드)과 증상을 입력한 예시입니다.'],
            ['file'=>'appointment_step4.png', 'label'=>'Step 4: 검토 완료', 'desc'=>'예약 정보를 확인하고 등록 버튼을 클릭합니다.'],
        ],
        'fields'=>[
            ['name'=>'환자', 'required'=>true, 'desc'=>'AJAX 검색으로 기존 환자 선택', 'example'=>'홍길동'],
            ['name'=>'진료과', 'required'=>true, 'desc'=>'department 선택', 'example'=>'내과'],
            ['name'=>'의사', 'required'=>true, 'desc'=>'카드 그리드에서 선택', 'example'=>'김의사'],
            ['name'=>'날짜/시간', 'required'=>true, 'desc'=>'datetime 형식', 'example'=>'2026-02-25 10:00'],
            ['name'=>'증상', 'required'=>false, 'desc'=>'예약 사유/증상 메모', 'example'=>'정기검진'],
        ],
        'tips'=>['opd_date는 datetime 형식','시간대는 의사 스케줄에서 자동 생성','department_id 필수','is_completed=0으로 등록'],
    ],

    // ── 8. 엑셀 Import ──
    [
        'id'=>'import', 'icon'=>'📤', 'color'=>'#198754', 'title'=>'엑셀 대량 등록 (Import)',
        'file'=>'admin_excel_import_wizard.php',
        'desc'=>'엑셀/CSV 파일로 대량 데이터를 한 번에 등록합니다. 9가지 엔티티를 지원합니다.',
        'screenshots'=>[
            ['file'=>'import_step1.png', 'label'=>'Step 1: 대상 선택', 'desc'=>'등록할 데이터 유형(의사/간호사/약사/환자/직원/의약품/예약/광고 등)을 선택하고, 엑셀 템플릿을 다운로드합니다.'],
            ['file'=>'import_step2.png', 'label'=>'Step 2: 파일 업로드', 'desc'=>'xlsx 또는 csv 파일을 드래그앤드롭으로 업로드합니다. 10행 미리보기가 표시됩니다.'],
        ],
        'fields'=>[
            ['name'=>'엔티티 유형', 'required'=>true, 'desc'=>'9종 중 선택', 'example'=>'의사'],
            ['name'=>'파일', 'required'=>true, 'desc'=>'xlsx 또는 csv (UTF-8)', 'example'=>'doctors.xlsx'],
        ],
        'tips'=>['템플릿을 먼저 다운로드하여 형식 확인','CSV는 UTF-8 인코딩 필수','최대 1000행 권장','6단계: 대상→업로드→매핑→충돌검사→등록→결과'],
    ],

    // ── 9. 엑셀 Export ──
    [
        'id'=>'export', 'icon'=>'📥', 'color'=>'#607D8B', 'title'=>'데이터 검색/내보내기 (Export)',
        'file'=>'admin_excel_export.php',
        'desc'=>'시스템 데이터를 검색하고 엑셀 파일로 내보냅니다. 11가지 엔티티를 지원합니다.',
        'screenshots'=>[
            ['file'=>'export_step1.png', 'label'=>'탭 + 검색 UI', 'desc'=>'11개 엔티티 탭(병원/의사/간호사/약사/환자/직원/의약품/예약/광고/후기/회원) 중 선택하고 검색합니다.'],
            ['file'=>'export_step2.png', 'label'=>'검색 결과 예시', 'desc'=>'필터에 맞는 데이터가 테이블에 표시됩니다. "엑셀 다운로드" 버튼으로 xlsx 파일을 다운로드합니다.'],
        ],
        'fields'=>[
            ['name'=>'엔티티 탭', 'required'=>true, 'desc'=>'11종 탭 중 선택', 'example'=>'병원'],
            ['name'=>'검색어', 'required'=>false, 'desc'=>'이름, 이메일 등 키워드', 'example'=>'홍길동'],
        ],
        'tips'=>['병원 = HMS tenants 테이블','후기 = hospital_reviews','회원 = g5_member (Gnuboard)','필터 없이 검색 시 전체 데이터'],
    ],
];

// TEMPORARILY DISABLED: hide documentation links for unavailable pages.
$disabledGuideSections = ['nurse', 'pharmacist', 'patient', 'medicine'];
$sections = array_values(array_filter($sections, static function (array $section) use ($disabledGuideSections): bool {
    return !in_array($section['id'] ?? '', $disabledGuideSections, true);
}));
?>

<style>
/* ═══ 히어로 ═══ */
.guide-hero{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border-radius:16px;padding:2rem;margin-bottom:1.5rem}
.guide-hero h2{font-weight:700;margin-bottom:.5rem}
.guide-hero p{opacity:.9}
.stat-card{background:rgba(255,255,255,.15);border-radius:12px;padding:.8rem 1.2rem;text-align:center;backdrop-filter:blur(10px)}
.stat-card .stat-num{font-size:1.8rem;font-weight:700}
.stat-card .stat-label{font-size:.8rem;opacity:.9}

/* ═══ 목차 ═══ */
.toc{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.5rem}
.toc a{padding:.35rem .7rem;border-radius:8px;background:#f0f0f0;color:var(--bs-body-color);text-decoration:none;font-size:.82rem;transition:.2s}
.toc a:hover{background:#667eea;color:#fff}

/* ═══ 섹션 ═══ */
.wizard-section{border:none;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);margin-bottom:1.2rem;overflow:hidden}
.wizard-section .section-head{padding:1rem 1.25rem;cursor:pointer;display:flex;align-items:center;gap:.75rem;transition:background .2s}
.wizard-section .section-head:hover{background:rgba(0,0,0,.02)}
.wizard-section .section-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}
.wizard-section .section-info{flex:1}
.wizard-section .section-info h5{margin:0;font-weight:600;font-size:1rem}
.wizard-section .section-info p{margin:0;font-size:.78rem;opacity:.6}
.section-badge{font-size:.7rem;padding:.2rem .5rem;border-radius:6px}

/* ═══ 갤러리 ═══ */
.screenshot-gallery{display:flex;gap:.75rem;overflow-x:auto;padding:.5rem 0 1rem;scroll-snap-type:x mandatory}
.screenshot-gallery::-webkit-scrollbar{height:6px}
.screenshot-gallery::-webkit-scrollbar-thumb{background:#ccc;border-radius:3px}
.ss-card{flex:0 0 320px;scroll-snap-align:start;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;cursor:pointer;transition:transform .2s,box-shadow .2s}
.ss-card:hover{transform:translateY(-3px);box-shadow:0 6px 20px rgba(0,0,0,.12)}
.ss-card img{width:100%;height:200px;object-fit:cover;object-position:top;display:block}
.ss-card .ss-caption{padding:.5rem .75rem;font-size:.8rem}
.ss-card .ss-caption strong{display:block;font-size:.85rem;margin-bottom:.2rem}
.ss-card .ss-caption span{color:#666;font-size:.75rem}
.ss-placeholder{flex:0 0 320px;border:2px dashed #ddd;border-radius:10px;display:flex;align-items:center;justify-content:center;height:260px;color:#aaa;font-size:.85rem}

/* ═══ 필드 테이블 ═══ */
.field-table{font-size:.82rem;margin-bottom:1rem}
.field-table th{background:var(--bs-tertiary-bg);font-weight:600;white-space:nowrap}
.field-table .req{color:#dc3545;font-weight:700}
.field-table .opt{color:#6c757d}

/* ═══ 팁/DB배지 ═══ */
.tip-badge{display:inline-block;background:var(--bs-body-bg)3cd;border:1px solid #ffc107;color:#856404;border-radius:6px;padding:.2rem .5rem;font-size:.75rem;margin:.15rem}
.db-badge{display:inline-block;background:#d1ecf1;border:1px solid #17a2b8;color:#0c5460;border-radius:6px;padding:.15rem .45rem;font-size:.72rem;margin:.1rem}

/* ═══ 다크모드 ═══ */
[data-bs-theme="dark"] .wizard-section{background:#2d2d2d}
[data-bs-theme="dark"] .ss-card{border-color:#555}
[data-bs-theme="dark"] .ss-card .ss-caption span{color:#aaa}
[data-bs-theme="dark"] .ss-placeholder{border-color:#555;color:#666}
[data-bs-theme="dark"] .toc a{background:#333;color:#ccc}
[data-bs-theme="dark"] .toc a:hover{background:#667eea;color:#fff}
[data-bs-theme="dark"] .tip-badge{background:#332b00;border-color:#665500;color:#ffc107}
[data-bs-theme="dark"] .db-badge{background:#0c3640;border-color:#17a2b8;color:#7dd3e1}
[data-bs-theme="dark"] .field-table th{background:#333}
[data-bs-theme="dark"] .section-head.bg-white,
[data-bs-theme="dark"] .section-head{background:var(--bs-body-bg) !important;color:var(--bs-body-color) !important}
[data-bs-theme="dark"] .section-head:hover{background:rgba(255,255,255,.05) !important}
[data-bs-theme="dark"] .screenshot-gallery::-webkit-scrollbar-thumb{background:#555}
[data-bs-theme="dark"] .ss-card .ss-caption{background:#2d2d2d}
</style>

<!-- 히어로 -->
<div class="guide-hero">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <h2>마법사 사용법 가이드</h2>
            <p>로그인부터 각 마법사의 단계별 화면 캡처와 상세 사용법을 안내합니다.</p>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <button class="btn btn-light btn-sm" onclick="captureAll()" id="btnCapture">
                    <i class="bi bi-camera"></i> 전체 캡처 (<?= $pngCount ?>장)
                </button>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="d-flex gap-3 justify-content-center mt-3 mt-lg-0">
                <div class="stat-card"><div class="stat-num"><?= $pngCount ?></div><div class="stat-label">스크린샷</div></div>
                <div class="stat-card"><div class="stat-num">7</div><div class="stat-label">등록 마법사</div></div>
                <div class="stat-card"><div class="stat-num">2</div><div class="stat-label">엑셀 기능</div></div>
            </div>
        </div>
    </div>
</div>

<!-- 목차 -->
<div class="toc">
<?php foreach ($sections as $s): ?>
    <a href="#sec-<?= $s['id'] ?>"><?= $s['icon'] ?> <?= $s['title'] ?></a>
<?php endforeach; ?>
</div>

<!-- 각 섹션 -->
<?php foreach ($sections as $idx => $s): ?>
<div class="wizard-section card" id="sec-<?= $s['id'] ?>">
    <div class="section-head bg-white" data-bs-toggle="collapse" data-bs-target="#body-<?= $s['id'] ?>">
        <div class="section-icon" style="background:<?= $s['color'] ?>18;color:<?= $s['color'] ?>;"><?= $s['icon'] ?></div>
        <div class="section-info">
            <h5><?= $s['title'] ?></h5>
            <p><?= $s['desc'] ?></p>
        </div>
        <span class="section-badge bg-primary text-white"><?= count($s['screenshots']) ?>장</span>
        <?php if (!empty($s['file'])): ?>
        <a href="<?= $s['file'] ?>" class="btn btn-sm btn-outline-primary ms-2" onclick="event.stopPropagation();">사용하기</a>
        <?php endif; ?>
        <i class="bi bi-chevron-down ms-1"></i>
    </div>
    <div class="collapse <?= $idx === 0 ? 'show' : '' ?>" id="body-<?= $s['id'] ?>">
        <div class="card-body">

            <!-- 스크린샷 갤러리 -->
            <h6 class="mb-2"><i class="bi bi-images"></i> 화면 캡처</h6>
            <div class="screenshot-gallery">
                <?php foreach ($s['screenshots'] as $ss): ?>
                    <?php if (file_exists($guideDir . $ss['file'])): ?>
                    <div class="ss-card" onclick="showImg('<?= $guideUrl . $ss['file'] ?>','<?= htmlspecialchars($ss['label']) ?>')">
                        <img src="<?= $guideUrl . $ss['file'] ?>" alt="<?= $ss['label'] ?>" loading="lazy">
                        <div class="ss-caption">
                            <strong><?= $ss['label'] ?></strong>
                            <span><?= $ss['desc'] ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="ss-placeholder"><?= $ss['label'] ?><br><small>(캡처 필요)</small></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="row mt-3">
                <!-- 입력 필드 설명 -->
                <div class="<?= !empty($s['tables']) ? 'col-lg-8' : 'col-12' ?>">
                    <?php if (!empty($s['fields'])): ?>
                    <h6 class="mb-2"><i class="bi bi-input-cursor-text"></i> 입력 항목</h6>
                    <table class="table table-sm table-bordered field-table">
                        <thead><tr><th>필드명</th><th>필수</th><th>설명</th><th>예시</th></tr></thead>
                        <tbody>
                        <?php foreach ($s['fields'] as $f): ?>
                        <tr>
                            <td><strong><?= $f['name'] ?></strong></td>
                            <td class="text-center"><?= $f['required'] ? '<span class="req">필수</span>' : '<span class="opt">선택</span>' ?></td>
                            <td><?= $f['desc'] ?></td>
                            <td><code><?= htmlspecialchars($f['example']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <?php if (!empty($s['tips'])): ?>
                    <h6 class="mb-1"><i class="bi bi-lightbulb"></i> 참고사항</h6>
                    <?php foreach ($s['tips'] as $tip): ?>
                    <span class="tip-badge"><?= htmlspecialchars($tip) ?></span>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- DB 테이블 -->
                <?php if (!empty($s['tables'])): ?>
                <div class="col-lg-4 mt-3 mt-lg-0">
                    <h6 class="mb-1"><i class="bi bi-database"></i> 연관 DB 테이블</h6>
                    <?php foreach ($s['tables'] as $t): ?>
                    <span class="db-badge"><?= $t ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- FAQ -->
<div class="card mt-4 mb-4" style="border:none;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
    <div class="card-body">
        <h5><i class="bi bi-question-circle"></i> 자주 묻는 질문</h5>
        <div class="accordion mt-3" id="faq">
            <?php
            $faqs = [
                ['q'=>'이메일이 이미 등록되어 있다면?', 'a'=>'users 테이블 이메일은 unique입니다. 실시간 중복 검사가 동작하며, 중복 시 빨간 경고가 표시됩니다. 엑셀 Import의 경우 충돌 검사 단계에서 건너뛰기/덮어쓰기를 선택합니다.'],
                ['q'=>'마법사로 등록한 데이터가 HMS에도 보이나요?', 'a'=>'네. 동일한 DB 테이블(users, doctors, patients 등)에 INSERT하므로 HMS 관리자 화면에서도 즉시 확인됩니다.'],
                ['q'=>'엑셀 Import에서 오류가 발생하면?', 'a'=>'오류 행은 건너뛰고 나머지를 계속 처리합니다. 완료 후 "에러 엑셀 다운로드"로 오류 내역을 확인하고 수정 후 다시 업로드합니다.'],
                ['q'=>'Export에서 어떤 데이터를 내보낼 수 있나요?', 'a'=>'11가지: 병원, 의사, 간호사, 약사, 환자, 직원, 의약품, 예약, 광고, 후기, 회원. 필터링 후 xlsx 다운로드 가능합니다.'],
            ];
            foreach ($faqs as $i => $faq): ?>
            <div class="accordion-item" style="border:none;border-radius:8px;margin-bottom:.4rem;box-shadow:0 1px 3px rgba(0,0,0,.05);">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" style="font-size:.9rem;"><?= $faq['q'] ?></button>
                </h2>
                <div id="faq<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#faq">
                    <div class="accordion-body py-2" style="font-size:.85rem;"><?= $faq['a'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- 이미지 모달 -->
<div class="modal fade" id="imgModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">
            <div class="modal-header border-0 py-2">
                <h6 class="modal-title" id="imgModalLabel"></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <img id="imgModalSrc" src="" alt="" style="width:100%;display:block;">
            </div>
        </div>
    </div>
</div>

<script>
function showImg(url, title) {
    document.getElementById('imgModalLabel').textContent = title;
    document.getElementById('imgModalSrc').src = url;
    new bootstrap.Modal(document.getElementById('imgModal')).show();
}
function captureAll() {
    const btn = document.getElementById('btnCapture');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 캡처 중... (최대 3분)';
    fetch('?action=capture')
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-camera"></i> 전체 캡처 (' + d.count + '장)';
            if (d.ok) setTimeout(() => location.reload(), 1000);
            else alert('캡처 실패: ' + (d.msg || ''));
        })
        .catch(e => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-camera"></i> 전체 캡처';
            alert('오류: ' + e.message);
        });
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
