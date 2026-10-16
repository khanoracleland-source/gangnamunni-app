<?php
/**
 * 관리자 에디터 설정
 * 후기 에디터 옵션 관리 (카메라전용, AI검수, 자동승인 등)
 */
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('editor_settings');

$db = Database::getInstance();

// AJAX 처리
if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $settings = [
            'camera_only' => !empty($_POST['camera_only']) ? '1' : '0',
            'require_rating' => !empty($_POST['require_rating']) ? '1' : '0',
            'min_content_length' => max(0, intval($_POST['min_content_length'] ?? 10)),
            'max_images' => max(1, min(20, intval($_POST['max_images'] ?? 10))),
            'max_ba_images' => max(1, min(10, intval($_POST['max_ba_images'] ?? 5))),
            'hide_ba_photos' => !empty($_POST['hide_ba_photos']) ? '1' : '0',
            'hide_procedure_info' => !empty($_POST['hide_procedure_info']) ? '1' : '0',
            'auto_approve' => !empty($_POST['auto_approve']) ? '1' : '0',
            'ai_check_enabled' => !empty($_POST['ai_check_enabled']) ? '1' : '0',
            'ai_min_score' => max(0, min(100, intval($_POST['ai_min_score'] ?? 70))),
            'allowed_image_types' => $_POST['allowed_image_types'] ?? 'jpeg,png,gif,webp'
        ];

        foreach ($settings as $key => $value) {
            $existing = $db->fetchOne("SELECT id FROM editor_settings WHERE setting_key = ?", [$key]);
            if ($existing) {
                $db->execute("UPDATE editor_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?", [$value, $key]);
            } else {
                $db->execute("INSERT INTO editor_settings (setting_key, setting_value, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())", [$key, $value]);
            }
        }
        echo json_encode(['success' => true, 'message' => '설정이 저장되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '저장 실패: ' . $e->getMessage()]);
    }
    exit;
}

// 현재 설정 로드
$current = [
    'camera_only' => '0',
    'require_rating' => '1',
    'min_content_length' => '10',
    'max_images' => '10',
    'max_ba_images' => '5',
    'hide_ba_photos' => '0',
    'hide_procedure_info' => '0',
    'auto_approve' => '0',
    'ai_check_enabled' => '1',
    'ai_min_score' => '70',
    'allowed_image_types' => 'jpeg,png,gif,webp'
];

try {
    $rows = $db->fetchAll("SELECT setting_key, setting_value FROM editor_settings WHERE is_active = 1");
    foreach ($rows as $row) {
        if (isset($current[$row['setting_key']])) {
            $current[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // 테이블 없으면 기본값
}

// 통계
$stats = ['total' => 0, 'pending' => 0, 'published' => 0, 'rejected' => 0, 'avg_ai' => 0];
try {
    $stats['total'] = intval($db->fetchValue("SELECT COUNT(*) FROM hospital_reviews") ?? 0);
    $stats['pending'] = intval($db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE review_status = 'pending'") ?? 0);
    $stats['published'] = intval($db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE review_status = 'published' AND is_active = 1") ?? 0);
    $stats['rejected'] = intval($db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE review_status = 'rejected'") ?? 0);
    $stats['avg_ai'] = floatval($db->fetchValue("SELECT ROUND(AVG(ai_score),1) FROM hospital_reviews WHERE ai_score IS NOT NULL") ?? 0);
} catch (Exception $e) {}

$admin_page_title = '에디터 설정';
$admin_current = 'editor_settings';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid p-4">
    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                <div class="small text-muted">전체 후기</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-warning"><?php echo number_format($stats['pending']); ?></div>
                <div class="small text-muted">검수 대기</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-success"><?php echo number_format($stats['published']); ?></div>
                <div class="small text-muted">게시됨</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <div class="fs-3 fw-bold text-info"><?php echo $stats['avg_ai']; ?></div>
                <div class="small text-muted">평균 AI 점수</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- 사진 설정 -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h5 class="mb-0">사진 업로드 설정</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="cameraOnly" <?php echo $current['camera_only'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cameraOnly">
                            <strong>카메라 직접 촬영만 허용</strong>
                            <br><small class="text-muted">갤러리에서 기존 사진 선택 불가 (직접 촬영만)</small>
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="hideBAPhotos" <?php echo $current['hide_ba_photos'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hideBAPhotos">
                            <strong>Before/After 사진 섹션 숨기기</strong>
                            <br><small class="text-muted">비포/애프터 구분 없이 일반 사진만 사용</small>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">최대 일반 사진 수</label>
                        <input type="number" class="form-control" id="maxImages" min="1" max="20" value="<?php echo $current['max_images']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">최대 Before/After 사진 수 (각)</label>
                        <input type="number" class="form-control" id="maxBAImages" min="1" max="10" value="<?php echo $current['max_ba_images']; ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">허용 이미지 형식</label>
                        <input type="text" class="form-control" id="allowedImageTypes" value="<?php echo htmlspecialchars($current['allowed_image_types']); ?>">
                        <small class="text-muted">콤마 구분 (예: jpeg,png,gif,webp)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 내용 설정 -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h5 class="mb-0">내용 및 평가 설정</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="requireRating" <?php echo $current['require_rating'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="requireRating">
                            <strong>별점 평가 필수</strong>
                            <br><small class="text-muted">최소 1개 축 평가 필수</small>
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="hideProcedureInfo" <?php echo $current['hide_procedure_info'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="hideProcedureInfo">
                            <strong>시술 정보 섹션 숨기기</strong>
                            <br><small class="text-muted">시술 날짜/비용 입력란 숨기기</small>
                        </label>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">최소 내용 길이 (자)</label>
                        <input type="number" class="form-control" id="minContentLength" min="0" max="500" value="<?php echo $current['min_content_length']; ?>">
                        <small class="text-muted">0 = 제한 없음</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI 검수 설정 -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h5 class="mb-0">AI 자동 검수</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="aiCheckEnabled" <?php echo $current['ai_check_enabled'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aiCheckEnabled">
                            <strong>AI 자동 검수 활성화</strong>
                            <br><small class="text-muted">후기 등록 시 스팸/비속어/품질 자동 검사</small>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">AI 통과 최소 점수</label>
                        <input type="range" class="form-range" id="aiMinScore" min="0" max="100" step="5" value="<?php echo $current['ai_min_score']; ?>" oninput="document.getElementById('aiMinScoreVal').textContent=this.value">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">0 (모두 통과)</small>
                            <strong id="aiMinScoreVal"><?php echo $current['ai_min_score']; ?></strong>
                            <small class="text-muted">100 (매우 엄격)</small>
                        </div>
                    </div>
                    <div class="alert alert-info small mb-0">
                        <strong>검수 항목:</strong> 스팸 키워드, 비속어, 반복 문자, 내용 길이, 중복 문장, 제목 유무, 사진 포함 여부
                    </div>
                </div>
            </div>
        </div>

        <!-- 게시 설정 -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header py-3">
                    <h5 class="mb-0">게시 설정</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="autoApprove" <?php echo $current['auto_approve'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="autoApprove">
                            <strong>AI 통과 시 자동 게시</strong>
                            <br><small class="text-muted">AI 검수 통과한 후기는 관리자 확인 없이 바로 게시</small>
                        </label>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <strong>주의:</strong> 자동 게시를 끄면 모든 후기가 관리자 승인 후 게시됩니다.<br>
                        AI 검수가 꺼져있으면 모든 후기가 수동 검수 대기 상태가 됩니다.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 저장 버튼 -->
    <div class="text-center mt-4">
        <button class="btn btn-primary btn-lg px-5" onclick="saveSettings()">
            <i class="bi bi-check-lg"></i> 설정 저장
        </button>
        <div id="saveResult" class="mt-2"></div>
    </div>
</div>

<script>
function saveSettings() {
    var formData = new FormData();
    formData.append('action', 'save_settings');
    formData.append('camera_only', document.getElementById('cameraOnly').checked ? '1' : '');
    formData.append('require_rating', document.getElementById('requireRating').checked ? '1' : '');
    formData.append('min_content_length', document.getElementById('minContentLength').value);
    formData.append('max_images', document.getElementById('maxImages').value);
    formData.append('max_ba_images', document.getElementById('maxBAImages').value);
    formData.append('hide_ba_photos', document.getElementById('hideBAPhotos').checked ? '1' : '');
    formData.append('hide_procedure_info', document.getElementById('hideProcedureInfo').checked ? '1' : '');
    formData.append('auto_approve', document.getElementById('autoApprove').checked ? '1' : '');
    formData.append('ai_check_enabled', document.getElementById('aiCheckEnabled').checked ? '1' : '');
    formData.append('ai_min_score', document.getElementById('aiMinScore').value);
    formData.append('allowed_image_types', document.getElementById('allowedImageTypes').value);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var el = document.getElementById('saveResult');
            if (data.success) {
                el.innerHTML = '<span class="text-success fw-bold">' + data.message + '</span>';
            } else {
                el.innerHTML = '<span class="text-danger fw-bold">' + data.message + '</span>';
            }
            setTimeout(function() { el.innerHTML = ''; }, 3000);
        })
        .catch(function(e) {
            document.getElementById('saveResult').innerHTML = '<span class="text-danger">저장 오류</span>';
        });
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
