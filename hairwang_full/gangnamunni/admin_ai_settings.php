<?php
/**
 * AI 자동화 설정 페이지 (슈퍼 어드민 전용)
 * - AI 기능별 ON/OFF 토글
 * - 임계값 설정
 * - 자동화 규칙 관리
 * - API 키 관리 (Phase D)
 */
session_start();

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 접속 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


// 인증
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('ai_settings');

// AI 설정 키 정의
$ai_setting_keys = [
    // 후기 AI
    ['key' => 'ai_check_enabled', 'label' => '후기 AI 검수', 'desc' => '후기 등록 시 AI가 자동으로 내용을 분석하여 스팸/비속어/품질 체크', 'group' => 'review', 'type' => 'toggle'],
    ['key' => 'ai_min_score', 'label' => 'AI 최소 점수', 'desc' => '이 점수 미만이면 자동 보류 (0~100)', 'group' => 'review', 'type' => 'number', 'min' => 0, 'max' => 100],
    ['key' => 'auto_approve', 'label' => '후기 자동 승인', 'desc' => 'AI 검수 통과 시 자동으로 게시 (OFF: 수동 승인 필요)', 'group' => 'review', 'type' => 'toggle'],

    // 광고 AI
    ['key' => 'ai_ad_check', 'label' => '광고 AI 검수', 'desc' => '광고 신청 시 과대광고/허위 키워드 자동 탐지', 'group' => 'ad', 'type' => 'toggle'],
    ['key' => 'ai_ad_min_score', 'label' => '광고 AI 최소 점수', 'desc' => '이 점수 미만 광고는 자동 보류', 'group' => 'ad', 'type' => 'number', 'min' => 0, 'max' => 100],
    ['key' => 'ai_ad_auto_approve', 'label' => '광고 자동 승인', 'desc' => 'AI 통과 광고 자동 활성화', 'group' => 'ad', 'type' => 'toggle'],

    // 병원 AI
    ['key' => 'ai_hospital_check', 'label' => '병원 등록 AI 검증', 'desc' => '병원 등록 시 필수정보 누락/중복 자동 체크', 'group' => 'hospital', 'type' => 'toggle'],
    ['key' => 'ai_hospital_auto_approve', 'label' => '병원 자동 승인', 'desc' => 'AI 검증 통과 병원 자동 활성화', 'group' => 'hospital', 'type' => 'toggle'],

    // 스팸/이상탐지
    ['key' => 'ai_spam_detect', 'label' => '스팸 자동 감지', 'desc' => '중복 IP/내용 패턴으로 스팸 자동 차단', 'group' => 'system', 'type' => 'toggle'],
    ['key' => 'ai_anomaly_alert', 'label' => '이상 징후 알림', 'desc' => '비정상 트래픽/대량 등록 감지 시 관리자 알림', 'group' => 'system', 'type' => 'toggle'],
    ['key' => 'ai_content_suggest', 'label' => '내용 개선 제안', 'desc' => '후기/광고 작성 시 AI가 개선점 실시간 제안', 'group' => 'system', 'type' => 'toggle'],
];

// AJAX 처리
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if ($_POST['ajax_action'] === 'save_settings') {
        $settings = json_decode($_POST['settings'] ?? '{}', true);
        if (!$settings) {
            echo json_encode(['success' => false, 'error' => '설정 데이터 오류']);
            exit;
        }

        $saved = 0;
        foreach ($settings as $key => $value) {
            // 허용된 키만 저장
            $allowed = array_column($ai_setting_keys, 'key');
            if (!in_array($key, $allowed)) continue;

            $value = strval($value);
            $result = mysqli_query($connect,
                "SELECT id FROM editor_settings WHERE setting_key = '" . mysqli_real_escape_string($connect, $key) . "'");
            if (mysqli_num_rows($result) > 0) {
                mysqli_query($connect,
                    "UPDATE editor_settings SET setting_value = '" . mysqli_real_escape_string($connect, $value) . "' WHERE setting_key = '" . mysqli_real_escape_string($connect, $key) . "'");
            } else {
                mysqli_query($connect,
                    "INSERT INTO editor_settings (setting_key, setting_value) VALUES ('" . mysqli_real_escape_string($connect, $key) . "', '" . mysqli_real_escape_string($connect, $value) . "')");
            }
            $saved++;
        }

        echo json_encode(['success' => true, 'saved' => $saved]);
        exit;
    }

    if ($_POST['ajax_action'] === 'get_ai_stats') {
        $stats = [];

        // 후기 AI 통계
        $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM review_ai_logs"));
        $stats['review_checks'] = (int)($r['c'] ?? 0);

        $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM review_ai_logs WHERE passed = 1"));
        $stats['review_passed'] = (int)($r['c'] ?? 0);

        $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT AVG(score) as avg FROM review_ai_logs"));
        $stats['review_avg_score'] = round((float)($r['avg'] ?? 0), 1);

        // 승인 대기 현황
        $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM hospital_reviews WHERE review_status = 'pending'"));
        $stats['pending_reviews'] = (int)($r['c'] ?? 0);

        $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_ads WHERE status = 'pending'"));
        $stats['pending_ads'] = (int)($r['c'] ?? 0);

        $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_hospitals WHERE status = 'pending'"));
        $stats['pending_hospitals'] = (int)($r['c'] ?? 0);

        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    // [추가 2026-06-19] AI 사용량 통계 (gangnamunni_ai_usage_log 집계)
    if ($_POST['ajax_action'] === 'get_usage_stats') {
        $usage = ['total' => 0, 'in_tokens' => 0, 'out_tokens' => 0, 'today' => 0, 'by_provider' => [], 'by_action' => []];
        $tbl = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_ai_usage_log'");
        if ($tbl && mysqli_num_rows($tbl) > 0) {
            $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c, IFNULL(SUM(input_tokens),0) it, IFNULL(SUM(output_tokens),0) ot FROM gangnamunni_ai_usage_log"));
            $usage['total'] = (int)$r['c']; $usage['in_tokens'] = (int)$r['it']; $usage['out_tokens'] = (int)$r['ot'];
            $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM gangnamunni_ai_usage_log WHERE DATE(created_at) = CURDATE()"));
            $usage['today'] = (int)$r['c'];
            $res = mysqli_query($connect, "SELECT provider, COUNT(*) c, IFNULL(SUM(input_tokens),0) it, IFNULL(SUM(output_tokens),0) ot FROM gangnamunni_ai_usage_log GROUP BY provider ORDER BY c DESC");
            while ($res && ($row = mysqli_fetch_assoc($res))) $usage['by_provider'][] = $row;
            $res = mysqli_query($connect, "SELECT action_type, COUNT(*) c FROM gangnamunni_ai_usage_log GROUP BY action_type ORDER BY c DESC LIMIT 10");
            while ($res && ($row = mysqli_fetch_assoc($res))) $usage['by_action'][] = $row;
        }
        echo json_encode(['success' => true, 'usage' => $usage]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// 현재 설정값 로드
$current_settings = [];
$result = mysqli_query($connect, "SELECT setting_key, setting_value FROM editor_settings");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
}

// 기본값 설정
$defaults = [
    'ai_check_enabled' => '1', 'ai_min_score' => '70', 'auto_approve' => '0',
    'ai_ad_check' => '0', 'ai_ad_min_score' => '60', 'ai_ad_auto_approve' => '0',
    'ai_hospital_check' => '0', 'ai_hospital_auto_approve' => '0',
    'ai_spam_detect' => '1', 'ai_anomaly_alert' => '0', 'ai_content_suggest' => '0',
];
foreach ($defaults as $k => $v) {
    if (!isset($current_settings[$k])) {
        $current_settings[$k] = $v;
    }
}

$groups = [
    'review' => ['label' => '후기 AI 검수', 'icon' => 'bi-chat-dots', 'color' => '#667eea'],
    'ad' => ['label' => '광고 AI 검수', 'icon' => 'bi-megaphone', 'color' => '#4facfe'],
    'hospital' => ['label' => '병원 등록 AI', 'icon' => 'bi-hospital', 'color' => '#43e97b'],
    'system' => ['label' => '시스템 AI', 'icon' => 'bi-cpu', 'color' => '#fa709a'],
];

// HTML 페이지 렌더링
$admin_page_title = 'AI 자동화 설정';
$admin_current = 'ai_settings';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.ai-group-card { border: none; border-radius: 12px; overflow: hidden; }
.ai-group-header { padding: 16px 20px; color: #fff; font-weight: 600; }
.ai-setting-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-bottom: 1px solid #f0f0f0; }
.ai-setting-row:last-child { border-bottom: none; }
.ai-setting-info { flex: 1; padding-right: 16px; }
.ai-setting-label { font-weight: 600; font-size: 14px; }
.ai-setting-desc { font-size: 12px; color: #888; margin-top: 2px; }
.ai-toggle { position: relative; width: 52px; height: 28px; flex-shrink: 0; }
.ai-toggle input { display: none; }
.ai-toggle-slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #ddd; border-radius: 28px; cursor: pointer; transition: 0.3s; }
.ai-toggle-slider::before { content: ''; position: absolute; width: 22px; height: 22px; left: 3px; top: 3px; background: var(--bs-body-bg); border-radius: 50%; transition: 0.3s; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.ai-toggle input:checked + .ai-toggle-slider { background: #667eea; }
.ai-toggle input:checked + .ai-toggle-slider::before { transform: translateX(24px); }
.ai-number-input { width: 80px; padding: 4px 8px; border: 1px solid var(--bs-border-color); border-radius: 8px; text-align: center; font-weight: 600; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px; }
.stat-box { padding: 16px; border-radius: 12px; background: var(--bs-tertiary-bg, #f8f9fa); text-align: center; }
.stat-box .value { font-size: 28px; font-weight: 700; }
.stat-box .label { font-size: 12px; color: #888; margin-top: 4px; }
.save-bar { position: sticky; bottom: 0; padding: 12px 20px; background: var(--bs-body-bg); border-top: 1px solid var(--bs-border-color); z-index: 50; }
.ai-status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.ai-status-on { background: #d4edda; color: #155724; }
.ai-status-off { background: #f8d7da; color: #721c24; }

/* API 키 관리 - 그룹 아코디언 */
.key-provider-group { border: none; border-radius: 12px; overflow: hidden; margin-bottom: 12px; }
.key-provider-header { padding: 14px 20px; color: #fff; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: space-between; transition: opacity 0.2s; border: none; width: 100%; }
.key-provider-header:hover { opacity: 0.9; }
.key-provider-header .provider-info { display: flex; align-items: center; gap: 10px; }
.key-provider-header .provider-count { background: rgba(255,255,255,0.25); padding: 2px 10px; border-radius: 12px; font-size: 12px; }
.key-provider-header .collapse-icon { transition: transform 0.3s; font-size: 18px; }
.key-provider-header.collapsed .collapse-icon { transform: rotate(-90deg); }
.key-provider-body { max-height: 2000px; overflow: hidden; transition: max-height 0.4s ease; }
.key-provider-body.collapsed { max-height: 0; }
.key-card { display: flex; align-items: center; padding: 14px 20px; border-bottom: 1px solid #f0f0f0; gap: 16px; flex-wrap: wrap; }
.key-card:last-child { border-bottom: none; }
.key-card-main { flex: 1; min-width: 200px; }
.key-card-model { font-weight: 600; font-size: 14px; }
.key-card-masked { font-family: monospace; font-size: 12px; color: #888; margin-top: 2px; }
.key-card-meta { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.key-meta-item { display: flex; flex-direction: column; align-items: center; min-width: 60px; }
.key-meta-item .meta-value { font-weight: 700; font-size: 14px; }
.key-meta-item .meta-label { font-size: 10px; color: #999; text-transform: uppercase; }
.key-card-date { font-size: 12px; color: #aaa; white-space: nowrap; }
.key-card-actions { display: flex; gap: 6px; }
.provider-bg-anthropic { background: linear-gradient(135deg, #d97706, #b45309); }
.provider-bg-openai { background: linear-gradient(135deg, #10a37f, #0d8c6d); }
.provider-bg-google { background: linear-gradient(135deg, #4285f4, #3367d6); }
.provider-bg-default { background: linear-gradient(135deg, #6b7280, #4b5563); }
/* 다크모드 오버라이드 */
[data-bs-theme="dark"] .ai-setting-row { border-bottom-color: var(--bs-border-color); }
[data-bs-theme="dark"] .key-card { border-bottom-color: var(--bs-border-color); }
[data-bs-theme="dark"] .ai-toggle-slider { background: #495057; }
[data-bs-theme="dark"] .ai-status-on { background: #1a3a2a; color: #75b798; }
[data-bs-theme="dark"] .ai-status-off { background: #3a1a1a; color: #ea868f; }
[data-bs-theme="dark"] .ai-number-input { background: #2b3035; border-color: var(--bs-border-color); color: var(--bs-body-color); }
[data-bs-theme="dark"] .ai-group-card { background: var(--bs-body-bg); }
</style>

<!-- AI 키 발급 안내 배너 -->
<div class="card mb-4" style="border: 2px solid #3B82F6; border-radius: 12px; overflow: hidden;">
    <div class="card-body" style="background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(139,92,246,0.08));">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <h6 class="fw-bold mb-1"><i class="bi bi-lightbulb-fill text-warning me-2"></i>AI 기능을 사용하려면 API 키가 필요합니다</h6>
                <p class="small mb-0" style="color: #666;">
                    Zhipu GLM <strong>(무료)</strong>, OpenAI ($5~), Anthropic ($5~), DeepSeek ($5~) 등 6개 프로바이더 지원.
                    <strong>GLM-4.7-Flash는 완전 무료</strong>로 신용카드 없이 가입만 하면 바로 사용 가능합니다.
                </p>
            </div>
            <a href="manual/ai_key_guide.html" target="_blank" class="btn btn-primary" style="border-radius: 10px; white-space: nowrap; background: linear-gradient(135deg, #3B82F6, #8B5CF6); border: none; font-weight: 600;">
                <i class="bi bi-book me-1"></i> API 키 발급 가이드
            </a>
        </div>
    </div>
</div>

<!-- 통계 대시보드 -->
<div class="card mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-graph-up-arrow"></i> AI 운영 현황</h6>
        <div class="stats-grid" id="aiStats">
            <div class="stat-box">
                <div class="value" id="statReviewChecks">-</div>
                <div class="label">후기 AI 검수</div>
            </div>
            <div class="stat-box">
                <div class="value" id="statReviewPassed">-</div>
                <div class="label">검수 통과</div>
            </div>
            <div class="stat-box">
                <div class="value" id="statAvgScore">-</div>
                <div class="label">평균 AI 점수</div>
            </div>
            <div class="stat-box">
                <div class="value text-warning" id="statPendingReviews">-</div>
                <div class="label">후기 승인 대기</div>
            </div>
            <div class="stat-box">
                <div class="value text-info" id="statPendingAds">-</div>
                <div class="label">광고 승인 대기</div>
            </div>
            <div class="stat-box">
                <div class="value text-success" id="statPendingHospitals">-</div>
                <div class="label">병원 승인 대기</div>
            </div>
        </div>
    </div>
</div>

<!-- AI 설정 그룹 -->
<?php foreach ($groups as $group_key => $group): ?>
<div class="card ai-group-card mb-3">
    <div class="ai-group-header" style="background: <?= $group['color'] ?>;">
        <i class="bi <?= $group['icon'] ?> me-2"></i> <?= $group['label'] ?>
    </div>
    <div class="card-body p-0">
        <?php foreach ($ai_setting_keys as $setting):
            if ($setting['group'] !== $group_key) continue;
            $val = $current_settings[$setting['key']] ?? '0';
        ?>
        <div class="ai-setting-row">
            <div class="ai-setting-info">
                <div class="ai-setting-label">
                    <?= htmlspecialchars($setting['label']) ?>
                    <span class="ai-status-badge <?= $val && $val !== '0' ? 'ai-status-on' : 'ai-status-off' ?>" id="badge_<?= $setting['key'] ?>">
                        <?= $val && $val !== '0' ? 'ON' : 'OFF' ?>
                    </span>
                </div>
                <div class="ai-setting-desc"><?= htmlspecialchars($setting['desc']) ?></div>
            </div>
            <?php if ($setting['type'] === 'toggle'): ?>
            <label class="ai-toggle">
                <input type="checkbox" data-key="<?= $setting['key'] ?>" <?= $val === '1' ? 'checked' : '' ?> onchange="markDirty()">
                <span class="ai-toggle-slider"></span>
            </label>
            <?php elseif ($setting['type'] === 'number'): ?>
            <input type="number" class="ai-number-input" data-key="<?= $setting['key'] ?>"
                   value="<?= htmlspecialchars($val) ?>"
                   min="<?= $setting['min'] ?? 0 ?>" max="<?= $setting['max'] ?? 100 ?>"
                   onchange="markDirty()">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- HMS 연동 정보 -->
<div class="card mb-3">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-link-45deg"></i> HMS 연동</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>기능</th><th>HMS URL</th><th>상태</th></tr></thead>
                <tbody>
                    <tr>
                        <td>병원 관리</td>
                        <td><a href="https://hms.seoulbeauty.app/super-admin/hospitals" target="_blank">/super-admin/hospitals</a></td>
                        <td><span class="badge bg-success">연동됨</span></td>
                    </tr>
                    <tr>
                        <td>의사 관리</td>
                        <td><a href="https://hms.seoulbeauty.app/super-admin/doctors" target="_blank">/super-admin/doctors</a></td>
                        <td><span class="badge bg-success">연동됨</span></td>
                    </tr>
                    <tr>
                        <td>후기 관리</td>
                        <td><a href="https://hms.seoulbeauty.app/hospital-reviews" target="_blank">/hospital-reviews</a></td>
                        <td><span class="badge bg-success">연동됨</span></td>
                    </tr>
                    <tr>
                        <td>시술 분류</td>
                        <td><a href="https://hms.seoulbeauty.app/super-admin/hospital-type" target="_blank">/super-admin/hospital-type</a></td>
                        <td><span class="badge bg-success">연동됨</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- API 키 관리 섹션 (프로바이더별 그룹, 확장/축소) -->
<!-- ======================================================== -->
<div id="ai-key-management" class="mt-4">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-key"></i> API 키 관리</h5>
            <div>
                <button class="btn btn-outline-light btn-sm me-1" onclick="toggleAllGroups()" title="모두 펼치기/접기">
                    <i class="bi bi-arrows-expand" id="toggleAllIcon"></i>
                </button>
                <button class="btn btn-success btn-sm" onclick="showAddKeyModal()">
                    <i class="bi bi-plus-lg"></i> 키 등록
                </button>
            </div>
        </div>
        <div class="card-body p-3" id="keyGroupContainer">
            <div class="text-center py-4 text-muted">로딩 중...</div>
        </div>
    </div>

    <!-- 사용량 통계 -->
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> AI 사용량 통계</h5>
        </div>
        <div class="card-body">
            <div class="row" id="aiUsageStats">
                <div class="col-12 text-center text-muted py-3">로딩 중...</div>
            </div>
        </div>
    </div>
</div>

<!-- API 키 등록 모달 -->
<div class="modal fade" id="addKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI API 키 등록</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">프로바이더</label>
                    <select id="keyProvider" class="form-select">
                        <option value="anthropic">Anthropic (Claude)</option>
                        <option value="openai">OpenAI (GPT/DALL·E)</option>
                        <option value="google">Google (Gemini) — 저렴</option>
                        <option value="deepseek">DeepSeek — 최저가</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">API 키</label>
                    <div class="input-group">
                        <input type="password" id="keyValue" class="form-control" placeholder="sk-...">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleKeyVisibility()">
                            <i class="bi bi-eye" id="keyEyeIcon"></i>
                        </button>
                    </div>
                    <small class="text-muted">AES-256-CBC 암호화 저장됩니다</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">모델명 (선택)</label>
                    <input type="text" id="keyModel" class="form-control" placeholder="기본 모델 사용 시 비워두세요">
                    <small class="text-muted">
                        Anthropic: claude-haiku-4-5-20251001 | OpenAI: gpt-4o-mini | Google: gemini-2.0-flash
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="registerKey()">
                    <i class="bi bi-shield-lock"></i> 암호화 저장
                </button>
            </div>
        </div>
    </div>
</div>

<!-- API 키 수정 모달 (모델/우선순위/키교체) -->
<div class="modal fade" id="editKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI API 키 수정 — <span id="editKeyProviderLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editKeyId">
                <div class="mb-3">
                    <label class="form-label">모델명</label>
                    <input type="text" id="editKeyModel" class="form-control" placeholder="기본 모델 사용 시 비워두세요">
                </div>
                <div class="mb-3">
                    <label class="form-label">우선순위 (낮을수록 먼저 사용)</label>
                    <input type="number" id="editKeyPriority" class="form-control" min="1" value="1">
                </div>
                <div class="mb-3">
                    <label class="form-label">API 키 교체 (선택)</label>
                    <input type="password" id="editKeyValue" class="form-control" placeholder="비워두면 기존 키 유지, 입력 시 교체">
                    <small class="text-muted">내 키로 교체하려면 여기에 입력하세요. AES-256 암호화 저장.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="saveEditKey()">
                    <i class="bi bi-check-lg"></i> 업데이트
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 저장 바 -->
<div class="save-bar text-end" id="saveBar" style="display:none;">
    <span class="text-warning me-3" id="saveStatus"><i class="bi bi-exclamation-circle"></i> 변경사항이 있습니다</span>
    <button class="btn btn-primary" onclick="saveSettings()"><i class="bi bi-check-lg"></i> 설정 저장</button>
</div>

<script>
let isDirty = false;

function markDirty() {
    isDirty = true;
    document.getElementById('saveBar').style.display = '';
}

function saveSettings() {
    const settings = {};
    document.querySelectorAll('[data-key]').forEach(el => {
        if (el.type === 'checkbox') {
            settings[el.dataset.key] = el.checked ? '1' : '0';
        } else {
            settings[el.dataset.key] = el.value;
        }
    });

    const fd = new FormData();
    fd.append('ajax_action', 'save_settings');
    fd.append('settings', JSON.stringify(settings));

    fetch(location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                isDirty = false;
                document.getElementById('saveBar').style.display = 'none';
                // 배지 업데이트
                document.querySelectorAll('[data-key]').forEach(el => {
                    const badge = document.getElementById('badge_' + el.dataset.key);
                    if (!badge) return;
                    let val;
                    if (el.type === 'checkbox') val = el.checked;
                    else val = el.value && el.value !== '0';
                    badge.className = 'ai-status-badge ' + (val ? 'ai-status-on' : 'ai-status-off');
                    badge.textContent = val ? 'ON' : 'OFF';
                });
                alert('설정이 저장되었습니다.');
            } else {
                alert('저장 실패: ' + (d.error || ''));
            }
        })
        .catch(e => alert('오류: ' + e.message));
}

function loadStats() {
    const fd = new FormData();
    fd.append('ajax_action', 'get_ai_stats');
    fetch(location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const s = d.stats;
            document.getElementById('statReviewChecks').textContent = s.review_checks;
            document.getElementById('statReviewPassed').textContent = s.review_passed;
            document.getElementById('statAvgScore').textContent = s.review_avg_score;
            document.getElementById('statPendingReviews').textContent = s.pending_reviews;
            document.getElementById('statPendingAds').textContent = s.pending_ads;
            document.getElementById('statPendingHospitals').textContent = s.pending_hospitals;
        });
}

loadStats();

// ===== AI 키 관리 (api/ai_key_manage.php 연동) =====
// 기존에 onclick으로 호출되나 미정의였던 함수들 구현 (백엔드 API는 완성되어 있음)
const AI_KEY_API = 'api/ai_key_manage.php';
const AI_PROVIDER_LABEL = {
    anthropic: 'Anthropic (Claude)', openai: 'OpenAI (GPT/DALL·E)', google: 'Google (Gemini)',
    zhipu: 'Zhipu GLM', deepseek: 'DeepSeek', openrouter: 'OpenRouter', ollama: 'Ollama', custom: 'Custom'
};

// 키 목록 로드 → #keyGroupContainer 렌더 (provider 그룹별)
function loadKeys() {
    const box = document.getElementById('keyGroupContainer');
    if (!box) return;
    box.innerHTML = '<div class="text-muted small py-2">불러오는 중...</div>';
    fetch(AI_KEY_API + '?action=list')
        .then(r => r.json())
        .then(d => {
            if (!d.success) { box.innerHTML = '<div class="text-danger small py-2">목록 로드 실패: ' + (d.error || '') + '</div>'; return; }
            if (!d.keys || d.keys.length === 0) {
                box.innerHTML = '<div class="text-muted small py-3 text-center"><i class="bi bi-key"></i> 등록된 키가 없습니다. 우측 상단 "키 등록"으로 추가하세요.</div>';
                return;
            }
            // provider별 그룹핑
            const groups = {};
            d.keys.forEach(k => { (groups[k.provider] = groups[k.provider] || []).push(k); });
            let html = '';
            Object.keys(groups).forEach(prov => {
                html += '<div class="mb-2"><div class="fw-bold small mb-1"><i class="bi bi-cpu"></i> ' + (AI_PROVIDER_LABEL[prov] || prov) + '</div>';
                html += '<div class="table-responsive"><table class="table table-sm mb-1"><thead><tr>'
                      + '<th>모델</th><th>키</th><th>우선순위</th><th>사용</th><th>활성</th><th></th></tr></thead><tbody>';
                groups[prov].forEach(k => {
                    const active = (k.is_active == 1);
                    const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                    html += '<tr>'
                        + '<td>' + (k.model_name ? esc(k.model_name) : '<span class="text-muted">기본</span>') + '</td>'
                        + '<td><code>' + esc(k.masked_key || '****') + '</code></td>'
                        + '<td>' + parseInt(k.priority||0) + '</td>'
                        + '<td>' + parseInt(k.usage_count||0) + '회</td>'
                        + '<td><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" '
                          + (active ? 'checked' : '') + ' onchange="toggleKeyActive(' + parseInt(k.id) + ', this.checked)"></div></td>'
                        + '<td class="text-nowrap">'
                          + '<button class="btn btn-outline-secondary btn-sm py-0 px-1" onclick="editKey(' + parseInt(k.id) + ',\'' + esc(k.provider||'') + '\',\'' + esc(k.model_name||'') + '\',' + parseInt(k.priority||1) + ')" title="모델/우선순위 수정"><i class="bi bi-pencil"></i></button> '
                          + '<button class="btn btn-outline-info btn-sm py-0 px-1" onclick="testKey(' + k.id + ', this)" title="연결 테스트"><i class="bi bi-plug"></i></button> '
                          + '<button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="deleteKey(' + k.id + ')" title="삭제(비우기)"><i class="bi bi-trash"></i></button>'
                        + '</td></tr>';
                });
                html += '</tbody></table></div></div>';
            });
            box.innerHTML = html;
        })
        .catch(e => { box.innerHTML = '<div class="text-danger small py-2">오류: ' + e.message + '</div>'; });
}

// 키 등록 모달 열기
function showAddKeyModal() {
    const m = document.getElementById('addKeyModal');
    if (m) {
        document.getElementById('keyValue').value = '';
        document.getElementById('keyModel').value = '';
        new bootstrap.Modal(m).show();
    }
}

// 키 표시/숨김 토글
function toggleKeyVisibility() {
    const inp = document.getElementById('keyValue');
    const icon = document.getElementById('keyEyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; icon.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; icon.className = 'bi bi-eye'; }
}

// 키 등록 (action=store)
function registerKey() {
    const provider = document.getElementById('keyProvider').value;
    const apiKey = document.getElementById('keyValue').value.trim();
    const model = document.getElementById('keyModel').value.trim();
    if (!apiKey) { alert('API 키를 입력하세요.'); return; }
    const fd = new FormData();
    fd.append('action', 'store');
    fd.append('provider', provider);
    fd.append('api_key', apiKey);
    fd.append('model_name', model);
    fetch(AI_KEY_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                bootstrap.Modal.getInstance(document.getElementById('addKeyModal')).hide();
                loadKeys();
            } else { alert('등록 실패: ' + (d.error || '')); }
        })
        .catch(e => alert('오류: ' + e.message));
}

// 키 수정 (모델/우선순위/키교체) — editKeyModal 사용
function editKey(id, provider, model, priority) {
    document.getElementById('editKeyId').value = id;
    document.getElementById('editKeyProviderLabel').textContent = AI_PROVIDER_LABEL[provider] || provider;
    document.getElementById('editKeyModel').value = model || '';
    document.getElementById('editKeyPriority').value = priority || 1;
    document.getElementById('editKeyValue').value = '';
    new bootstrap.Modal(document.getElementById('editKeyModal')).show();
}

// 키 수정 저장 (action=update)
function saveEditKey() {
    const id = document.getElementById('editKeyId').value;
    const model = document.getElementById('editKeyModel').value.trim();
    const priority = document.getElementById('editKeyPriority').value;
    const newKey = document.getElementById('editKeyValue').value.trim();
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('model_name', model);
    fd.append('priority', priority);
    if (newKey) fd.append('api_key', newKey); // 입력 시에만 키 교체
    fetch(AI_KEY_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) { bootstrap.Modal.getInstance(document.getElementById('editKeyModal')).hide(); loadKeys(); }
            else alert('수정 실패: ' + (d.error || ''));
        })
        .catch(e => alert('오류: ' + e.message));
}

// 키 활성/비활성 토글 (action=update)
function toggleKeyActive(id, active) {
    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('id', id);
    fd.append('is_active', active ? 1 : 0);
    fetch(AI_KEY_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (!d.success) { alert('변경 실패: ' + (d.error || '')); loadKeys(); } });
}

// 키 삭제 (action=delete)
function deleteKey(id) {
    if (!confirm('이 키를 삭제하시겠습니까?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch(AI_KEY_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.success) loadKeys(); else alert('삭제 실패: ' + (d.error || '')); });
}

// 키 연결 테스트 (action=test)
function testKey(id, btn) {
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const fd = new FormData();
    fd.append('action', 'test');
    fd.append('id', id);
    fetch(AI_KEY_API, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { alert(d.success ? '✅ 연결 정상' + (d.message ? ': ' + d.message : '') : '❌ 실패: ' + (d.error || '')); })
        .catch(e => alert('오류: ' + e.message))
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

// 그룹 전체 펼치기/접기 (현재 단일 컨테이너이므로 새로고침으로 동작)
function toggleAllGroups() { loadKeys(); }

// ===== AI 사용량 통계 (#aiUsageStats) =====
function loadUsageStats() {
    const box = document.getElementById('aiUsageStats');
    if (!box) return;
    const fd = new FormData(); fd.append('ajax_action', 'get_usage_stats');
    fetch(location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { box.innerHTML = '<div class="col-12 text-danger small">통계 로드 실패</div>'; return; }
            const u = d.usage;
            if (u.total === 0) {
                box.innerHTML = '<div class="col-12 text-center text-muted py-3"><i class="bi bi-graph-up"></i> 아직 AI 호출 기록이 없습니다. 후기검수/광고생성 등을 사용하면 집계됩니다.</div>';
                return;
            }
            const card = (label, val, color) => '<div class="col-6 col-md-3 mb-2"><div class="border rounded p-2 text-center">'
                + '<div class="fs-4 fw-bold text-' + color + '">' + val + '</div><div class="small text-muted">' + label + '</div></div></div>';
            let html = card('총 호출', u.total.toLocaleString() + '회', 'primary')
                     + card('오늘 호출', u.today.toLocaleString() + '회', 'success')
                     + card('입력 토큰', u.in_tokens.toLocaleString(), 'info')
                     + card('출력 토큰', u.out_tokens.toLocaleString(), 'warning');
            // provider별
            if (u.by_provider && u.by_provider.length) {
                html += '<div class="col-12 mt-2"><div class="small fw-bold mb-1">프로바이더별</div><table class="table table-sm mb-0"><thead><tr><th>프로바이더</th><th>호출</th><th>입력토큰</th><th>출력토큰</th></tr></thead><tbody>';
                u.by_provider.forEach(p => { html += '<tr><td>' + p.provider + '</td><td>' + Number(p.c).toLocaleString() + '회</td><td>' + Number(p.it).toLocaleString() + '</td><td>' + Number(p.ot).toLocaleString() + '</td></tr>'; });
                html += '</tbody></table></div>';
            }
            // action별
            if (u.by_action && u.by_action.length) {
                html += '<div class="col-12 mt-2"><div class="small fw-bold mb-1">기능별 호출</div><div class="d-flex flex-wrap gap-1">';
                u.by_action.forEach(a => { html += '<span class="badge bg-secondary">' + (a.action_type || '-') + ': ' + a.c + '회</span>'; });
                html += '</div></div>';
            }
            box.innerHTML = html;
        })
        .catch(e => { box.innerHTML = '<div class="col-12 text-danger small">오류: ' + e.message + '</div>'; });
}

// 페이지 로드 시 키 목록 + 사용량 자동 로드
loadKeys();
loadUsageStats();

window.addEventListener('beforeunload', function(e) {
    if (isDirty) { e.preventDefault(); e.returnValue = ''; }
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
