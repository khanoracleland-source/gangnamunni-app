<?php
/**
 * 병원 등록 프로세스 안내 — 전체 흐름 + 스크린샷
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$admin_page_title = '등록 프로세스 안내';
$admin_current = 'hospital_process';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

$img_base = SITE_FULL_URL . 'uploads/hospital_process/';

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
    .process-step { position: relative; margin-bottom: 48px; }
    .process-step::before { content: ''; position: absolute; left: 28px; top: 56px; bottom: -48px; width: 2px; background: linear-gradient(to bottom, var(--bs-primary), #dee2e6); }
    .process-step:last-child::before { display: none; }
    .step-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
    .step-number { width: 56px; height: 56px; border-radius: 50%; background: var(--bs-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; flex-shrink: 0; box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.3); }
    .step-title { font-size: 1.2rem; font-weight: 700; }
    .step-desc { color: #666; font-size: 0.9rem; }
    .step-screenshot { border-radius: 12px; border: 1px solid #e0e0e0; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 100%; cursor: pointer; transition: transform 0.2s; }
    .step-screenshot:hover { transform: scale(1.02); }
    .step-content { margin-left: 72px; }
    .flow-arrow { text-align: center; margin: -20px 0 20px; color: var(--bs-primary); font-size: 1.5rem; }
    .role-badge { font-size: 0.75rem; padding: 4px 10px; border-radius: 20px; }
    .lightbox { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 9999; display: none; justify-content: center; align-items: center; cursor: zoom-out; }
    .lightbox img { max-width: 95vw; max-height: 95vh; border-radius: 8px; }
    .lightbox.show { display: flex; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-diagram-3 me-2"></i>병원 등록 프로세스</h4>
    <div>
        <span class="badge bg-primary role-badge">공개</span>
        <span class="badge bg-success role-badge">신청자</span>
        <span class="badge bg-danger role-badge">관리자</span>
    </div>
</div>

<!-- 프로세스 흐름도 요약 -->
<div class="card border-0 shadow-sm mb-5">
    <div class="card-body py-4">
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 text-center">
            <div class="px-3 py-2 bg-primary bg-opacity-10 rounded-3"><i class="bi bi-eye d-block fs-4 text-primary"></i><small class="fw-semibold">입점 안내</small></div>
            <i class="bi bi-chevron-right text-muted"></i>
            <div class="px-3 py-2 bg-primary bg-opacity-10 rounded-3"><i class="bi bi-pencil-square d-block fs-4 text-primary"></i><small class="fw-semibold">신청서 작성</small></div>
            <i class="bi bi-chevron-right text-muted"></i>
            <div class="px-3 py-2 bg-warning bg-opacity-10 rounded-3"><i class="bi bi-hourglass-split d-block fs-4 text-warning"></i><small class="fw-semibold">심사 대기</small></div>
            <i class="bi bi-chevron-right text-muted"></i>
            <div class="px-3 py-2 bg-info bg-opacity-10 rounded-3"><i class="bi bi-pencil d-block fs-4 text-info"></i><small class="fw-semibold">수정 가능</small></div>
            <i class="bi bi-chevron-right text-muted"></i>
            <div class="px-3 py-2 bg-success bg-opacity-10 rounded-3"><i class="bi bi-check-circle d-block fs-4 text-success"></i><small class="fw-semibold">승인 + 계정발급</small></div>
            <i class="bi bi-chevron-right text-muted"></i>
            <div class="px-3 py-2 bg-success bg-opacity-10 rounded-3"><i class="bi bi-speedometer2 d-block fs-4 text-success"></i><small class="fw-semibold">운영 시작</small></div>
        </div>
    </div>
</div>

<!-- 상세 프로세스 -->
<?php
$steps = [
    ['num' => 1, 'title' => '병원 입점 안내 페이지', 'role' => '공개', 'role_color' => 'primary',
     'desc' => '비로그인으로 접근 가능한 마케팅 페이지. 입점 혜택, 등급 비교, FAQ를 확인합니다.',
     'images' => ['01_landing_info.png'], 'link' => 'hospital_landing.php?tab=info'],

    ['num' => 2, 'title' => '등록 신청 폼 작성', 'role' => '공개/신청자', 'role_color' => 'primary',
     'desc' => '병원 기본정보, 주소(카카오맵 GPS), 서류 업로드, 약관 동의 후 제출. 타 플랫폼(강남언니/바비톡/여신티켓) 등록 여부도 확인 가능.',
     'images' => ['02_landing_apply.png', '06_admin_apply.png'], 'link' => 'hospital_landing.php?tab=apply'],

    ['num' => 3, 'title' => '다크모드 지원', 'role' => '공개', 'role_color' => 'secondary',
     'desc' => '등록 대시보드는 주간/야간 모드를 지원합니다. 우측 상단 토글 버튼으로 전환.',
     'images' => ['04_landing_dark.png'], 'link' => ''],

    ['num' => 4, 'title' => '내 신청 현황 확인', 'role' => '신청자', 'role_color' => 'success',
     'desc' => '신청한 병원 목록을 카드형으로 확인. 상태(심사중/승인/반려), 수정 횟수, 경과일을 한눈에 파악.',
     'images' => ['07_my_status_list.png'], 'link' => 'hospital_apply_status.php'],

    ['num' => 5, 'title' => '신청 상세 + 타임라인', 'role' => '신청자', 'role_color' => 'success',
     'desc' => '신청 상세 정보와 진행 타임라인(접수→수정→심사→승인)을 확인. 수정 이력도 이전/변경 비교로 표시.',
     'images' => ['08_my_status_detail.png'], 'link' => 'hospital_apply_status.php?view=8123'],

    ['num' => 6, 'title' => '신청 정보 수정', 'role' => '신청자', 'role_color' => 'success',
     'desc' => '심사 중 또는 반려된 신청을 수정 가능. 변경된 필드만 자동 감지하여 수정 이력에 기록.',
     'images' => ['09_my_status_edit.png'], 'link' => 'hospital_apply_status.php?view=8123&edit=1'],

    ['num' => 7, 'title' => '관리자 심사 목록', 'role' => '관리자', 'role_color' => 'danger',
     'desc' => '관리자가 pending 상태 신청을 확인. "수정됨" 배지로 신청자가 정보를 변경한 건을 식별.',
     'images' => ['10_admin_hospitals_pending.png'], 'link' => 'admin_hospitals.php?status=pending'],

    ['num' => 8, 'title' => '관리자 상세 심사 + 승인/반려', 'role' => '관리자', 'role_color' => 'danger',
     'desc' => '신청 정보 전체 확인 + 수정 이력 비교. 승인 시 자동 계정 3곳 생성(g5_member + 관리자 + SSO). 반려 시 사유 입력 + 이메일 발송.',
     'images' => ['11_admin_hospital_detail.png'], 'link' => 'admin_hospitals.php?view=8123'],

    ['num' => 9, 'title' => '병원 등록 설정', 'role' => '관리자', 'role_color' => 'danger',
     'desc' => '병원 등록 신청 ON/OFF (iOS 심사 대응), 등록 통계 대시보드.',
     'images' => ['12_settings_hospital.png'], 'link' => 'admin_site_settings.php?tab=hospital'],

    ['num' => 10, 'title' => '프로모션 기간 설정', 'role' => '관리자', 'role_color' => 'danger',
     'desc' => '프로모션 기간, 수수료 면제, 배지 커스터마이징. 실시간 미리보기.',
     'images' => ['13_settings_promo.png'], 'link' => 'admin_site_settings.php?tab=promotion'],
];

foreach ($steps as $s): ?>
<div class="process-step">
    <div class="step-header">
        <div class="step-number"><?= $s['num'] ?></div>
        <div>
            <div class="step-title"><?= $s['title'] ?> <span class="badge bg-<?= $s['role_color'] ?> role-badge ms-2"><?= $s['role'] ?></span></div>
            <div class="step-desc"><?= $s['desc'] ?></div>
        </div>
    </div>
    <div class="step-content">
        <div class="row g-3">
            <?php foreach ($s['images'] as $img): ?>
            <div class="col-md-<?= count($s['images']) > 1 ? '6' : '12' ?>">
                <img src="<?= $img_base . $img ?>" alt="<?= $s['title'] ?>" class="step-screenshot w-100" onclick="openLightbox(this.src)">
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($s['link'])): ?>
        <a href="<?= $s['link'] ?>" class="btn btn-outline-primary btn-sm mt-2"><i class="bi bi-box-arrow-up-right me-1"></i>페이지 열기</a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- 라이트박스 -->
<div class="lightbox" id="lightbox" onclick="this.classList.remove('show');">
    <img id="lightboxImg" src="" alt="확대 보기">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('show');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('lightbox').classList.remove('show');
});
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
