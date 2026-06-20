<?php
/**
 * 사이트 설정 — 통합 세팅 페이지
 * 5탭: 일반 / 병원 등록 / 프로모션 / 지도 / 개발자
 * Bootstrap 5.3 + AJAX 저장
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '사이트 설정';
$admin_current = 'site_settings';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('site_settings');

// 모든 세팅 로드
$all_settings = [];
$res = mysqli_query($connect, "SELECT setting_key, setting_value, setting_type, setting_group, description FROM gangnamunni_settings ORDER BY setting_group, setting_key");
while ($row = mysqli_fetch_assoc($res)) {
    $all_settings[$row['setting_key']] = $row;
}

function sv($key, $default = '') {
    global $all_settings;
    return $all_settings[$key]['setting_value'] ?? $default;
}

$active_tab = $_GET['tab'] ?? 'general';

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>사이트 설정</h4>
    <span class="badge bg-secondary"><?= count($all_settings) ?>개 설정</span>
</div>

<!-- 탭 -->
<ul class="nav nav-tabs mb-3" id="settingsTabs">
    <li class="nav-item"><a class="nav-link <?= $active_tab === 'general' ? 'active' : '' ?>" href="?tab=general"><i class="bi bi-sliders me-1"></i>일반</a></li>
    <li class="nav-item"><a class="nav-link <?= $active_tab === 'hospital' ? 'active' : '' ?>" href="?tab=hospital"><i class="bi bi-hospital me-1"></i>병원 등록</a></li>
    <li class="nav-item"><a class="nav-link <?= $active_tab === 'promotion' ? 'active' : '' ?>" href="?tab=promotion"><i class="bi bi-gift me-1"></i>프로모션</a></li>
    <li class="nav-item"><a class="nav-link <?= $active_tab === 'map' ? 'active' : '' ?>" href="?tab=map"><i class="bi bi-geo-alt me-1"></i>지도</a></li>
    <li class="nav-item"><a class="nav-link <?= $active_tab === 'dev' ? 'active' : '' ?>" href="?tab=dev"><i class="bi bi-code-slash me-1"></i>개발자</a></li>
</ul>

<div id="saveAlert" class="alert alert-success d-none mb-3"><i class="bi bi-check-circle me-1"></i>저장되었습니다.</div>

<?php if ($active_tab === 'general'): ?>
<!-- ===== 일반 설정 ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>일반 설정</h6></div>
    <div class="card-body">
        <?php
        $general_items = [
            ['hms_standalone_mode', 'HMS 독립 모드', 'toggle', 'HMS 독립 실행 모드 (Laravel 없이)'],
            ['rolling_banner_enabled', '롤링 배너 활성화', 'toggle', '메인 롤링 배너 ON/OFF'],
            ['rolling_banner_count', '롤링 배너 수', 'number', '표시할 배너 수'],
            ['rolling_banner_speed', '롤링 속도 (ms)', 'number', '자동 전환 속도'],
            ['booking_require_login', '예약 로그인 필수', 'toggle', '예약/예약조회 시 로그인 필수 (피그마 설계 기본 ON)'],
            ['booking_phone_auth', '전화번호 인증 허용', 'toggle', 'ON 시 비로그인도 전화번호로 예약/조회 가능 (기본 OFF)'],
        ];
        foreach ($general_items as $item):
            $key = $item[0]; $label = $item[1]; $type = $item[2]; $desc = $item[3];
            $val = sv($key, '');
        ?>
        <div class="row align-items-center mb-3 border-bottom pb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-0"><?= $label ?></label>
                <div class="text-muted small"><?= $desc ?></div>
            </div>
            <div class="col-md-8">
                <?php if ($type === 'toggle'): ?>
                <div class="form-check form-switch">
                    <input class="form-check-input setting-toggle" type="checkbox" data-key="<?= $key ?>" <?= $val === '1' ? 'checked' : '' ?>>
                </div>
                <?php elseif ($type === 'number'): ?>
                <input type="number" class="form-control form-control-sm setting-input" data-key="<?= $key ?>" value="<?= htmlspecialchars($val) ?>" style="max-width:150px;">
                <?php else: ?>
                <input type="text" class="form-control form-control-sm setting-input" data-key="<?= $key ?>" value="<?= htmlspecialchars($val) ?>">
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($active_tab === 'hospital'): ?>
<!-- ===== 병원 등록 설정 ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-hospital me-2"></i>병원 등록 설정</h6></div>
    <div class="card-body">
        <div class="row align-items-center mb-3 border-bottom pb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-0">병원 등록 신청 공개</label>
                <div class="text-muted small">OFF 시 마이페이지/공개폼에서 병원 등록 메뉴 비노출 (iOS 심사 대응)</div>
            </div>
            <div class="col-md-8">
                <div class="form-check form-switch">
                    <input class="form-check-input setting-toggle" type="checkbox" data-key="show_hospital_apply" <?= sv('show_hospital_apply', '0') === '1' ? 'checked' : '' ?>>
                </div>
            </div>
        </div>
        <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>현재 상태:</strong> <?= sv('show_hospital_apply', '0') === '1' ? '공개 (ON)' : '비공개 (OFF)' ?>
            <br>관리자 사이드바의 "병원 등록" 메뉴는 이 설정과 무관하게 항상 표시됩니다.
            <br>랜딩 페이지(hospital_landing.php)도 항상 접근 가능합니다.
        </div>

        <h6 class="fw-bold mt-4 mb-3">등록 통계</h6>
        <?php
        $stat_pending = 0; $stat_approved = 0; $stat_rejected = 0;
        $r = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_hospitals WHERE applied_by IS NOT NULL GROUP BY status");
        while ($row = mysqli_fetch_assoc($r)) {
            if ($row['status'] === 'pending') $stat_pending = $row['cnt'];
            elseif ($row['status'] === 'approved') $stat_approved = $row['cnt'];
            elseif ($row['status'] === 'rejected') $stat_rejected = $row['cnt'];
        }
        ?>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card bg-warning bg-opacity-10 border-warning">
                    <div class="card-body text-center py-3">
                        <div class="fs-2 fw-bold text-warning"><?= $stat_pending ?></div>
                        <div class="small">심사 대기</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success bg-opacity-10 border-success">
                    <div class="card-body text-center py-3">
                        <div class="fs-2 fw-bold text-success"><?= $stat_approved ?></div>
                        <div class="small">승인 완료</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-danger bg-opacity-10 border-danger">
                    <div class="card-body text-center py-3">
                        <div class="fs-2 fw-bold text-danger"><?= $stat_rejected ?></div>
                        <div class="small">반려</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'promotion'): ?>
<!-- ===== 프로모션 설정 ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-gift me-2"></i>프로모션 설정</h6></div>
    <div class="card-body">
        <?php
        $promo_items = [
            ['promo_enabled', '프로모션 활성화', 'toggle', '전역 프로모션 ON/OFF'],
            ['promo_start_date', '시작일', 'date', '프로모션 시작 날짜'],
            ['promo_end_date', '종료일', 'date', '프로모션 종료 날짜'],
            ['promo_free_commission', '수수료 면제', 'toggle', '프로모션 기간 중 수수료 0%'],
            ['promo_default_grade', '기본 등급', 'select', '프로모션 기간 신규 병원 기본 등급'],
            ['promo_badge_text', '배지 텍스트', 'text', '프론트에 표시되는 프로모션 배지'],
            ['promo_badge_color', '배지 색상', 'color', '프로모션 배지 배경색'],
        ];
        foreach ($promo_items as $item):
            $key = $item[0]; $label = $item[1]; $type = $item[2]; $desc = $item[3];
            $val = sv($key, '');
        ?>
        <div class="row align-items-center mb-3 border-bottom pb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-0"><?= $label ?></label>
                <div class="text-muted small"><?= $desc ?></div>
            </div>
            <div class="col-md-8">
                <?php if ($type === 'toggle'): ?>
                <div class="form-check form-switch">
                    <input class="form-check-input setting-toggle" type="checkbox" data-key="<?= $key ?>" <?= $val === '1' ? 'checked' : '' ?>>
                </div>
                <?php elseif ($type === 'date'): ?>
                <input type="date" class="form-control form-control-sm setting-input" data-key="<?= $key ?>" value="<?= htmlspecialchars($val) ?>" style="max-width:200px;">
                <?php elseif ($type === 'color'): ?>
                <div class="d-flex align-items-center gap-2">
                    <input type="color" class="form-control form-control-color setting-input" data-key="<?= $key ?>" value="<?= htmlspecialchars($val ?: '#ff6b6b') ?>">
                    <span class="badge" style="background:<?= htmlspecialchars($val ?: '#ff6b6b') ?>"><?= htmlspecialchars(sv('promo_badge_text', '프로모션')) ?></span>
                </div>
                <?php elseif ($type === 'select'): ?>
                <select class="form-select form-select-sm setting-input" data-key="<?= $key ?>" style="max-width:150px;">
                    <?php foreach (['none'=>'Free','E'=>'E','D'=>'D','C'=>'C','B'=>'B','A'=>'A','S'=>'S'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= $val === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" class="form-control form-control-sm setting-input" data-key="<?= $key ?>" value="<?= htmlspecialchars($val) ?>" style="max-width:200px;">
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- 프로모션 미리보기 -->
        <div class="mt-3 p-3 bg-light rounded">
            <h6 class="fw-bold mb-2">미리보기</h6>
            <?php
            $pe = sv('promo_enabled', '0') === '1';
            $ps = sv('promo_start_date', '');
            $pend = sv('promo_end_date', '');
            $now = date('Y-m-d');
            $active = $pe && (!$ps || $ps <= $now) && (!$pend || $pend >= $now);
            ?>
            <span class="badge fs-6" style="background:<?= sv('promo_badge_color', '#ff6b6b') ?>">
                <?= htmlspecialchars(sv('promo_badge_text', '프로모션')) ?>
            </span>
            <span class="ms-2 <?= $active ? 'text-success' : 'text-muted' ?>">
                <?= $active ? '현재 활성 중' : '비활성' ?>
            </span>
            <?php if ($ps && $pend): ?>
            <span class="text-muted ms-2 small">(<?= $ps ?> ~ <?= $pend ?>)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'map'): ?>
<!-- ===== 지도 설정 ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2"></i>지도 설정</h6></div>
    <div class="card-body">
        <div class="row align-items-center mb-3 border-bottom pb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-0">카카오맵 API 키</label>
                <div class="text-muted small">카카오 개발자 콘솔에서 발급받은 JavaScript 키</div>
            </div>
            <div class="col-md-8">
                <input type="text" class="form-control form-control-sm setting-input" data-key="kakao_map_api_key" value="<?= htmlspecialchars(sv('kakao_map_api_key', '')) ?>" placeholder="JavaScript 키 입력">
            </div>
        </div>
        <div class="row align-items-center mb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-0">지도 API 키 목록 (JSON)</label>
                <div class="text-muted small">여러 키 로테이션 사용 시</div>
            </div>
            <div class="col-md-8">
                <input type="text" class="form-control form-control-sm setting-input" data-key="kakao_map_api_keys" value="<?= htmlspecialchars(sv('kakao_map_api_keys', '[]')) ?>">
            </div>
        </div>
    </div>
</div>

<?php elseif ($active_tab === 'dev'): ?>
<!-- ===== 개발자 설정 ===== -->
<div class="card border-0 shadow-sm">
    <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-code-slash me-2"></i>개발자 설정</h6></div>
    <div class="card-body">
        <?php
        $dev_items = [
            ['client_spec_dev_mode', '클라이언트 스펙 개발 모드', 'toggle', '개발용 디버그 정보 표시'],
            ['client_spec_google_sheets_url', 'Google Sheets URL', 'text', '클라이언트 기획서 스프레드시트 URL'],
        ];
        foreach ($dev_items as $item):
            $key = $item[0]; $label = $item[1]; $type = $item[2]; $desc = $item[3];
            $val = sv($key, '');
        ?>
        <div class="row align-items-center mb-3 border-bottom pb-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-0"><?= $label ?></label>
                <div class="text-muted small"><?= $desc ?></div>
            </div>
            <div class="col-md-8">
                <?php if ($type === 'toggle'): ?>
                <div class="form-check form-switch">
                    <input class="form-check-input setting-toggle" type="checkbox" data-key="<?= $key ?>" <?= $val === '1' ? 'checked' : '' ?>>
                </div>
                <?php else: ?>
                <input type="text" class="form-control form-control-sm setting-input" data-key="<?= $key ?>" value="<?= htmlspecialchars($val) ?>">
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- 전체 세팅 목록 -->
        <h6 class="fw-bold mt-4 mb-3">전체 세팅 목록 (<?= count($all_settings) ?>개)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-hover small">
                <thead class="table-light">
                    <tr><th>그룹</th><th>키</th><th>값</th><th>설명</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($all_settings as $s): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($s['setting_group'] ?? '') ?></span></td>
                        <td class="text-nowrap"><code><?= htmlspecialchars($s['setting_key']) ?></code></td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(mb_substr($s['setting_value'] ?? '', 0, 50)) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($s['description'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// AJAX 설정 저장
function saveSetting(key, value) {
    console.log('[SiteSettings] 저장:', key, '=', value);
    fetch('api_save_setting.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'key=' + encodeURIComponent(key) + '&value=' + encodeURIComponent(value)
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            var alert = document.getElementById('saveAlert');
            alert.classList.remove('d-none');
            setTimeout(function() { alert.classList.add('d-none'); }, 2000);
        } else {
            console.warn('[SiteSettings] 저장 실패:', d.error);
        }
    });
}

// 토글
document.querySelectorAll('.setting-toggle').forEach(function(el) {
    el.addEventListener('change', function() {
        saveSetting(this.dataset.key, this.checked ? '1' : '0');
    });
});

// 입력 필드 (change 이벤트)
document.querySelectorAll('.setting-input').forEach(function(el) {
    el.addEventListener('change', function() {
        saveSetting(this.dataset.key, this.value);
    });
});
</script>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
