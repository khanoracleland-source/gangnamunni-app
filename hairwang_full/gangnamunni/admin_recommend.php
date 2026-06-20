<?php
/**
 * ============================================================================
 * [관리자 #16 - 2026-05-28] 메인 화면 추천 병원 카테고리 관리자 지정 기능
 * ============================================================================
 * 목적 : 관리자가 메인 화면 '추천 병원' 섹션에 노출할 병원을 카테고리별로 직접 지정
 * 신규 : 본 파일 (admin_recommend.php) — 기존 admin_hospitals.php 그대로 보존
 * DB    : gangnamunni_hospitals.is_recommended (TINYINT) + recommended_category (VARCHAR)
 *         두 컬럼은 ALTER TABLE IF NOT EXISTS 로 사전 추가 완료 (2026-05-28)
 * 영향 범위 : 신규 페이지만 추가. 기존 병원 데이터 영향 없음 (기본값 0/NULL)
 * 사이드바 : admin_header.php 메뉴 그룹 추가 필요 (별도 PR)
 * ============================================================================
 */

session_start();
$admin_page_title = '추천 병원 관리';
$admin_current = 'recommend';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('hospitals');

$message = '';
$error = '';

// 추천 토글 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_recommend') {
    $hid = intval($_POST['hospital_id'] ?? 0);
    $cat = trim($_POST['category'] ?? '');
    $is_rec = isset($_POST['is_recommended']) ? 1 : 0;
    if ($hid > 0) {
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET is_recommended = ?, recommended_category = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'isi', $is_rec, $cat, $hid);
        if (mysqli_stmt_execute($stmt)) {
            $message = "병원 #{$hid} 추천 설정이 변경되었습니다.";
        } else {
            $error = '실패: ' . mysqli_error($connect);
        }
        mysqli_stmt_close($stmt);
    }
}

// 카테고리 목록 (시술 카테고리)
$categories = ['전체', '눈성형', '코성형', '안면윤곽', '리프팅', '가슴성형', '지방흡입', '피부/레이저', '치아교정', '기타'];

// 현재 추천 병원 조회
$rec_query = "SELECT id, name, address, recommended_category, is_recommended, ad_grade FROM gangnamunni_hospitals WHERE is_active = 1 AND (status = 'approved' OR status IS NULL) ORDER BY is_recommended DESC, name ASC LIMIT 200";
$rec_result = mysqli_query($connect, $rec_query);
$hospitals = [];
if ($rec_result) while ($r = mysqli_fetch_assoc($rec_result)) $hospitals[] = $r;

// 카테고리별 추천 카운트
$cat_counts = [];
$cc_q = mysqli_query($connect, "SELECT recommended_category, COUNT(*) as c FROM gangnamunni_hospitals WHERE is_recommended = 1 GROUP BY recommended_category");
if ($cc_q) while ($cc = mysqli_fetch_assoc($cc_q)) $cat_counts[$cc['recommended_category'] ?? '미지정'] = $cc['c'];

include __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-star-fill text-warning"></i> 추천 병원 관리</h4>
        <a href="admin_hospitals.php" class="btn btn-sm btn-outline-secondary">병원 관리로 이동</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle"></i>
        메인 화면 '추천 병원' 섹션에 노출할 병원을 선택하고 카테고리를 지정하세요.
        선택된 병원만 메인 화면 추천 섹션에 표시됩니다.
    </div>

    <!-- 카테고리별 카운트 -->
    <div class="card mb-3">
        <div class="card-header py-2"><strong>카테고리별 추천 현황</strong></div>
        <div class="card-body py-2">
            <?php foreach ($cat_counts as $cat => $cnt): ?>
            <span class="badge bg-primary me-2"><?= htmlspecialchars($cat) ?> : <?= $cnt ?>개</span>
            <?php endforeach; ?>
            <?php if (empty($cat_counts)): ?>
            <span class="text-muted small">아직 추천된 병원이 없습니다.</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 병원 목록 -->
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>병원명</th>
                    <th>주소</th>
                    <th style="width:80px;">등급</th>
                    <th style="width:150px;">카테고리</th>
                    <th style="width:100px;" class="text-center">추천 여부</th>
                    <th style="width:80px;" class="text-center">저장</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hospitals as $h): ?>
                <tr <?= $h['is_recommended'] ? 'class="table-warning"' : '' ?>>
                    <form method="POST" class="d-contents">
                        <input type="hidden" name="action" value="toggle_recommend">
                        <input type="hidden" name="hospital_id" value="<?= $h['id'] ?>">
                        <td><?= $h['id'] ?></td>
                        <td><?= htmlspecialchars($h['name']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars(mb_substr($h['address'] ?? '', 0, 40)) ?></td>
                        <td><?= htmlspecialchars($h['ad_grade'] ?? 'none') ?></td>
                        <td>
                            <select name="category" class="form-select form-select-sm">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= ($h['recommended_category'] === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input type="checkbox" name="is_recommended" value="1" class="form-check-input" <?= $h['is_recommended'] ? 'checked' : '' ?>>
                            </div>
                        </td>
                        <td class="text-center">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i></button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>
