<?php
/**
 * 병원 등급별 기능 제한 관리
 * ad_grade(S~E) 별로 기능 ON/OFF + 수량 제한 설정
 */
session_start();
$admin_page_title = '등급별 기능 관리';
$admin_current = 'grade_permissions';
$admin_page_description = '병원 광고등급(S~E)에 따라 기능 사용 가능 여부와 최대 수량을 관리합니다.';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('grade_permissions');

$msg = '';

// AJAX 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_POST['action'] === 'save') {
        $grade = $_POST['grade'] ?? '';
        $feature = $_POST['feature'] ?? '';
        $allowed = intval($_POST['allowed'] ?? 1);
        $max_count = $_POST['max_count'] !== '' ? intval($_POST['max_count']) : null;

        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_grade_permissions SET is_allowed=?, max_count=? WHERE ad_grade=? AND feature_key=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iiss', $allowed, $max_count, $grade, $feature);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    exit;
}

// 데이터 로드
$grades = ['S', 'A', 'B', 'C', 'D', 'E', 'none'];
$features = [];
$perms = [];
$q = mysqli_query($connect, "SELECT * FROM gangnamunni_grade_permissions ORDER BY FIELD(ad_grade,'S','A','B','C','D','E','none'), feature_key");
if ($q) {
    while ($r = mysqli_fetch_assoc($q)) {
        $perms[$r['ad_grade']][$r['feature_key']] = $r;
        if (!in_array($r['feature_key'], $features)) $features[] = $r['feature_key'];
    }
}

$feature_labels = [
    'banner_ads' => '배너 광고',
    'infeed_ads' => '인피드 광고',
    'rolling_banner' => '롤링 배너',
    'treatments' => '시술 등록',
    'doctors' => '의사 등록',
    'events' => '이벤트',
    'ai_features' => 'AI 기능',
    'priority_listing' => '우선 노출',
];

// 병원별 등급 현황
$grade_counts = [];
$gc = mysqli_query($connect, "SELECT ad_grade, COUNT(*) as cnt FROM gangnamunni_hospitals WHERE is_active=1 GROUP BY ad_grade ORDER BY FIELD(ad_grade,'S','A','B','C','D','E','none')");
if ($gc) { while ($r = mysqli_fetch_assoc($gc)) $grade_counts[$r['ad_grade']] = $r['cnt']; }

include __DIR__ . '/includes/admin_header.php';
?>

<style>
.grade-badge { padding:4px 12px; border-radius:6px; font-weight:700; font-size:13px; color:#fff; }
.grade-S { background:linear-gradient(135deg,#FFD700,#FFA500); color:#000; }
.grade-A { background:linear-gradient(135deg,#C0C0C0,#A0A0A0); }
.grade-B { background:#6366f1; }
.grade-C { background:#22c55e; }
.grade-D { background:#f59e0b; color:#000; }
.grade-E { background:#94a3b8; }
.grade-none { background:#e2e8f0; color:#64748b; }
.perm-toggle { cursor:pointer; }
.perm-on { color:#22c55e; font-weight:700; }
.perm-off { color:#ef4444; font-weight:700; }
.count-input { width:70px; text-align:center; font-size:12px; }
</style>

<div class="container-fluid py-3">
<h4 class="mb-3"><i class="bi bi-shield-check me-2"></i>등급별 기능 관리</h4>

<!-- 등급 현황 -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($grades as $g):
        $cnt = $grade_counts[$g] ?? 0;
    ?>
    <div class="d-flex align-items-center gap-2 border rounded px-3 py-2">
        <span class="grade-badge grade-<?php echo $g; ?>"><?php echo $g === 'none' ? 'Free' : $g; ?></span>
        <span class="fw-bold"><?php echo $cnt; ?></span>
        <span class="text-muted small">병원</span>
    </div>
    <?php endforeach; ?>
</div>

<!-- 권한 매트릭스 -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="min-width:140px;">기능</th>
                    <?php foreach ($grades as $g): ?>
                    <th class="text-center" style="min-width:100px;">
                        <span class="grade-badge grade-<?php echo $g; ?>"><?php echo $g === 'none' ? 'Free' : $g; ?></span>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($features as $f):
                    $label = $feature_labels[$f] ?? $f;
                ?>
                <tr>
                    <td class="fw-bold"><?php echo htmlspecialchars($label); ?></td>
                    <?php foreach ($grades as $g):
                        $p = $perms[$g][$f] ?? ['is_allowed' => 0, 'max_count' => null];
                        $allowed = $p['is_allowed'];
                        $max = $p['max_count'];
                    ?>
                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input perm-toggle" type="checkbox"
                                       data-grade="<?php echo $g; ?>" data-feature="<?php echo $f; ?>"
                                       <?php echo $allowed ? 'checked' : ''; ?>
                                       onchange="savePerm(this)">
                            </div>
                            <?php if ($allowed): ?>
                            <input type="number" class="form-control form-control-sm count-input"
                                   data-grade="<?php echo $g; ?>" data-feature="<?php echo $f; ?>"
                                   value="<?php echo $max !== null ? $max : ''; ?>"
                                   placeholder="∞" onchange="saveCount(this)">
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="mt-3 text-muted small">
    <i class="bi bi-info-circle me-1"></i>토글: ON/OFF, 숫자: 최대 수량 (비워두면 무제한). 변경 시 자동 저장됩니다.
</div>
</div>

<script>
function savePerm(el) {
    var grade = el.dataset.grade;
    var feature = el.dataset.feature;
    var allowed = el.checked ? 1 : 0;
    fetch('admin_grade_permissions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=save&grade=' + grade + '&feature=' + feature + '&allowed=' + allowed + '&max_count='
    }).then(function() { location.reload(); });
}
function saveCount(el) {
    var grade = el.dataset.grade;
    var feature = el.dataset.feature;
    fetch('admin_grade_permissions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=save&grade=' + grade + '&feature=' + feature + '&allowed=1&max_count=' + encodeURIComponent(el.value)
    });
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
