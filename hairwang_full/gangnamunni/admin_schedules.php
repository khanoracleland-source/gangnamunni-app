<?php
session_start();
$admin_page_title = '일정 관리';
$admin_current = 'schedules';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('schedules');

$days_kr = ['Monday'=>'월요일','Tuesday'=>'화요일','Wednesday'=>'수요일','Thursday'=>'목요일','Friday'=>'금요일','Saturday'=>'토요일','Sunday'=>'일요일'];

$schedules = [];
$r = mysqli_query($connect, "SELECT * FROM hospital_schedules ORDER BY FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
if ($r) { while ($row = mysqli_fetch_assoc($r)) $schedules[] = $row; }

$holidays = [];
$r2 = mysqli_query($connect, "SELECT dh.*, d_u.first_name, d_u.last_name
    FROM doctor_holidays dh
    LEFT JOIN doctors d ON dh.doctor_id = d.id
    LEFT JOIN users d_u ON d.user_id = d_u.id
    WHERE dh.date >= CURDATE()
    ORDER BY dh.date LIMIT 20");
if ($r2) { while ($row = mysqli_fetch_assoc($r2)) $holidays[] = $row; }

include __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 조회 전용 안내 (인수인계) -->
<div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small"><strong>조회 전용 페이지:</strong> 이 화면은 HMS(병원 본 시스템) 데이터를 <u>조회만</u> 합니다. 추가·수정·삭제는 HMS 본체에서 처리되며, 강남언니 관리자에서는 변경할 수 없습니다. (설계상 의도된 동작)</div>
</div>
<?php
?>
<div class="row g-3">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-clock me-2"></i>병원 운영 시간</h5></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>요일</th><th>시작</th><th>종료</th></tr></thead>
                    <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">운영 시간이 설정되지 않았습니다.</td></tr>
                    <?php else: foreach ($schedules as $s): ?>
                    <tr>
                        <td class="fw-semibold"><?= $days_kr[$s['day_of_week']] ?? $s['day_of_week'] ?></td>
                        <td><?= htmlspecialchars($s['start_time']) ?></td>
                        <td><?= htmlspecialchars($s['end_time']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="bi bi-calendar-x me-1"></i>의사 휴무 (예정)</h6></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>날짜</th><th>의사</th></tr></thead>
                    <tbody>
                    <?php if (empty($holidays)): ?>
                        <tr><td colspan="2" class="text-center text-muted py-3">예정된 휴무가 없습니다.</td></tr>
                    <?php else: foreach ($holidays as $h): ?>
                    <tr>
                        <td><?= date('m/d (D)', strtotime($h['date'])) ?></td>
                        <td><?= htmlspecialchars(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; mysqli_close($connect); ?>