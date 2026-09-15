<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * 병원 파트너 AI 설정 페이지 (standalone, 관리자 헤더 없음)
 * 병원이 직접 AI API 키를 등록/관리하는 페이지
 * 모바일 친화적 레이아웃
 */
session_start();

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB connection failed');
mysqli_set_charset($connect, 'utf8mb4');

require_once __DIR__ . '/includes/ai_proxy.php';
require_once __DIR__ . '/includes/cpv_helper.php';

// 병원 ID
$hospital_id = intval($_GET['hospital_id'] ?? $_SESSION['partner_hospital_id'] ?? 0);

// [개선 2026-06-19] hospital_id 없을 때:
//  - 관리자(ad_admin_logged_in) 세션이면 → 병원 선택 목록 표시 (사이드바에서 눌러도 깨지지 않음)
//  - 그 외 → 기존 접근 오류 안내
if ($hospital_id <= 0) {
    $is_admin = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>병원 자체 AI 키 설정</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"></head><body class="bg-body-tertiary p-4">';
    echo '<div class="container" style="max-width:760px;">';
    if ($is_admin) {
        echo '<h4 class="mb-2"><i class="bi bi-key"></i> 병원 자체 AI 키 설정</h4>';
        echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> 병원별 AI 키 설정 페이지입니다. 설정할 병원을 선택하세요. <a href="dashboard.php">← 대시보드</a></div>';
        $hs = mysqli_query($connect, "SELECT id, name, ai_enabled FROM gangnamunni_hospitals ORDER BY id DESC LIMIT 300");
        echo '<div class="list-group">';
        if ($hs && mysqli_num_rows($hs) > 0) {
            while ($h = mysqli_fetch_assoc($hs)) {
                $badge = $h['ai_enabled'] ? '<span class="badge bg-success">AI ON</span>' : '<span class="badge bg-secondary">AI OFF</span>';
                echo '<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="partner_ai_settings.php?hospital_id=' . (int)$h['id'] . '">';
                echo '<span><i class="bi bi-hospital"></i> ' . htmlspecialchars($h['name']) . ' <small class="text-muted">#' . (int)$h['id'] . '</small></span>' . $badge . '</a>';
            }
        } else {
            echo '<div class="text-muted p-3">등록된 병원이 없습니다.</div>';
        }
        echo '</div>';
    } else {
        echo '<div style="text-align:center;padding:60px 20px;"><h3>접근 오류</h3><p class="text-muted">병원 정보를 확인할 수 없습니다. 병원 관리자 계정으로 로그인하거나 병원을 지정해 접근하세요.</p></div>';
    }
    echo '</div></body></html>';
    mysqli_close($connect);
    exit;
}
$_SESSION['partner_hospital_id'] = $hospital_id;

// 병원 정보 조회
$stmt = mysqli_prepare($connect, "SELECT id, name, ai_enabled, ai_use_own_key, ai_own_api_key, ai_own_provider, ai_cost_from_wallet FROM gangnamunni_hospitals WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
mysqli_stmt_execute($stmt);
$hospital = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$hospital) {
    die('<div style="text-align:center;padding:60px 20px;font-family:sans-serif;"><h3>병원을 찾을 수 없습니다.</h3></div>');
}

$message = '';
$error = '';

// POST 처리: 자체 키 등록/해제
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';

    if ($post_action === 'save_own_key') {
        $use_own = isset($_POST['ai_use_own_key']) ? 1 : 0;
        $provider = in_array($_POST['provider'] ?? '', ['openai','anthropic','google']) ? $_POST['provider'] : 'openai';
        $raw_key = trim($_POST['api_key'] ?? '');

        $key_sql = '';
        if (!empty($raw_key) && strpos($raw_key, '****') === false) {
            $encrypted = aiEncryptKey($raw_key);
            $key_sql = ", ai_own_api_key = '" . mysqli_real_escape_string($connect, $encrypted) . "'";
        }

        $sql = "UPDATE gangnamunni_hospitals SET ai_use_own_key={$use_own}, ai_own_provider='{$provider}'{$key_sql}, updated_at=NOW() WHERE id={$hospital_id}";
        if (mysqli_query($connect, $sql)) {
            $message = 'AI 설정이 저장되었습니다.';
            // 리로드
            $stmt = mysqli_prepare($connect, "SELECT id, name, ai_enabled, ai_use_own_key, ai_own_api_key, ai_own_provider, ai_cost_from_wallet FROM gangnamunni_hospitals WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            mysqli_stmt_execute($stmt);
            $hospital = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
        } else {
            $error = '저장 실패: ' . mysqli_error($connect);
        }
    }

    if ($post_action === 'test_key') {
        $provider = $_POST['test_provider'] ?? 'openai';
        $raw_key = trim($_POST['test_api_key'] ?? '');
        if (!empty($raw_key)) {
            $testResult = aiTestKey($provider, $raw_key);
            if ($testResult['success']) {
                $message = '키 테스트 성공: ' . $testResult['message'];
            } else {
                $error = '키 테스트 실패: ' . $testResult['message'];
            }
        } else {
            $error = 'API 키를 입력해주세요.';
        }
    }

    if ($post_action === 'remove_key') {
        $sql = "UPDATE gangnamunni_hospitals SET ai_use_own_key=0, ai_own_api_key=NULL, updated_at=NOW() WHERE id={$hospital_id}";
        if (mysqli_query($connect, $sql)) {
            $message = '자체 키가 제거되었습니다. 플랫폼 키를 사용합니다.';
            $hospital['ai_use_own_key'] = '0';
            $hospital['ai_own_api_key'] = null;
        }
    }
}

// 지갑 잔액
$wallet_balance = 0;
$stmt = mysqli_prepare($connect, "SELECT balance FROM gangnamunni_cpv_wallets WHERE hospital_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $wr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $wallet_balance = $wr ? (int)$wr['balance'] : 0;
    mysqli_stmt_close($stmt);
}

// AI 비용 단가
$ai_costs = [
    'ai_base'    => ['label' => '기본 AI 호출', 'cost' => (int)getCpvSetting('ai_base_cost', 50)],
    'ai_review'  => ['label' => '후기 AI 검수', 'cost' => (int)getCpvSetting('ai_review_cost', 30)],
    'ai_ad'      => ['label' => '광고 AI 검수', 'cost' => (int)getCpvSetting('ai_ad_cost', 50)],
    'ai_suggest' => ['label' => 'AI 제안', 'cost' => (int)getCpvSetting('ai_suggest_cost', 100)],
    'ai_image'   => ['label' => 'AI 이미지 처리', 'cost' => (int)getCpvSetting('ai_image_cost', 200)],
];

$ai_enabled = ($hospital['ai_enabled'] ?? 0) == 1;
$has_own_key = !empty($hospital['ai_own_api_key']);
$use_own = ($hospital['ai_use_own_key'] ?? 0) == 1;
$cost_from_wallet = ($hospital['ai_cost_from_wallet'] ?? 1) == 1;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI 설정 — <?php echo htmlspecialchars($hospital['name']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body { background: #f8fafc; font-family: 'Pretendard','Apple SD Gothic Neo',sans-serif; }
.top-bar { background: linear-gradient(135deg,#0891b2,#06b6d4); padding: 20px; color: #fff; text-align: center; }
.top-bar h1 { font-size: 20px; font-weight: 800; margin: 0; }
.top-bar .sub { font-size: 12px; opacity: 0.8; margin-top: 4px; }
.container { max-width: 640px; padding: 16px; }
.status-card { border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.cost-table td { padding: 8px 12px; font-size: 13px; }
.cost-table .cost { font-weight: 700; color: #0891b2; }
</style>
</head>
<body>

<div class="top-bar">
    <h1><i class="bi bi-robot"></i> AI 설정</h1>
    <div class="sub"><?php echo htmlspecialchars($hospital['name']); ?></div>
</div>

<div class="container">

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-1"></i><?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-x-circle me-1"></i><?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- AI 활성 상태 -->
<div class="status-card <?php echo $ai_enabled ? 'bg-success bg-opacity-10 border border-success' : 'bg-secondary bg-opacity-10 border'; ?>">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi <?php echo $ai_enabled ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-secondary'; ?> fs-4"></i>
        <div>
            <strong>AI 기능: <?php echo $ai_enabled ? 'ON' : 'OFF'; ?></strong>
            <div class="text-muted small"><?php echo $ai_enabled ? 'AI 기능을 사용할 수 있습니다' : '관리자가 AI 기능을 비활성화했습니다'; ?></div>
        </div>
    </div>
    <?php if ($ai_enabled): ?>
    <div class="row g-2 text-center mt-2">
        <div class="col-4">
            <div class="bg-white rounded p-2">
                <div class="fw-bold text-primary"><?php echo $use_own ? '자체' : '플랫폼'; ?></div>
                <div class="text-muted" style="font-size:11px;">키 유형</div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white rounded p-2">
                <div class="fw-bold text-success"><?php echo number_format($wallet_balance); ?>원</div>
                <div class="text-muted" style="font-size:11px;">지갑 잔액</div>
            </div>
        </div>
        <div class="col-4">
            <div class="bg-white rounded p-2">
                <div class="fw-bold <?php echo $cost_from_wallet ? 'text-warning' : 'text-secondary'; ?>"><?php echo $cost_from_wallet ? 'ON' : 'OFF'; ?></div>
                <div class="text-muted" style="font-size:11px;">비용 차감</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($ai_enabled): ?>

<!-- 자체 키 설정 -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-key me-1"></i> API 키 설정</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="save_own_key">

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="ai_use_own_key" id="useOwnKey" <?php echo $use_own ? 'checked' : ''; ?> onchange="document.getElementById('keyFields').style.display=this.checked?'block':'none'">
                <label class="form-check-label" for="useOwnKey"><strong>자체 API 키 사용</strong></label>
                <div class="form-text">직접 등록한 API 키를 사용하면 플랫폼 비용이 차감되지 않습니다</div>
            </div>

            <div id="keyFields" style="display:<?php echo $use_own ? 'block' : 'none'; ?>;">
                <div class="mb-3">
                    <label class="form-label small fw-bold">AI 프로바이더</label>
                    <select name="provider" class="form-select form-select-sm">
                        <option value="openai" <?php echo (($hospital['ai_own_provider'] ?? 'openai') === 'openai') ? 'selected' : ''; ?>>OpenAI (GPT-4o)</option>
                        <option value="anthropic" <?php echo (($hospital['ai_own_provider'] ?? '') === 'anthropic') ? 'selected' : ''; ?>>Anthropic (Claude)</option>
                        <option value="google" <?php echo (($hospital['ai_own_provider'] ?? '') === 'google') ? 'selected' : ''; ?>>Google (Gemini)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">API 키</label>
                    <input type="password" name="api_key" class="form-control form-control-sm" placeholder="<?php echo $has_own_key ? '(등록됨 — 변경 시 새 키 입력)' : 'API 키 입력'; ?>">
                    <div class="form-text">AES-256-CBC 암호화 저장됩니다</div>
                </div>
                <?php if ($has_own_key): ?>
                <div class="alert alert-info py-2 small">
                    <i class="bi bi-shield-check"></i> 키 등록됨 (프로바이더: <?php echo htmlspecialchars($hospital['ai_own_provider'] ?? 'openai'); ?>)
                </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>저장</button>
                <?php if ($has_own_key): ?>
                <button type="submit" name="action" value="remove_key" class="btn btn-outline-danger btn-sm" onclick="return confirm('자체 키를 제거하시겠습니까?')"><i class="bi bi-trash me-1"></i>키 제거</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- AI 비용 단가표 -->
<?php if (!$use_own && $cost_from_wallet): ?>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-currency-dollar me-1"></i> AI 비용 단가 (플랫폼 키 사용 시)</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 cost-table">
            <thead class="table-light">
                <tr><th>기능</th><th class="text-end">단가</th></tr>
            </thead>
            <tbody>
            <?php foreach ($ai_costs as $type => $info): ?>
                <tr>
                    <td><?php echo $info['label']; ?></td>
                    <td class="text-end cost"><?php echo number_format($info['cost']); ?>원/건</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-center small text-muted">
        지갑 잔액: <strong class="text-success"><?php echo number_format($wallet_balance); ?>원</strong>
        | <a href="partner_wallet.php?hospital_id=<?php echo $hospital_id; ?>">충전하기</a>
    </div>
</div>
<?php endif; ?>

<!-- 키 테스트 -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-lightning me-1"></i> API 키 테스트</div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="test_key">
            <div class="row g-2 mb-2">
                <div class="col-4">
                    <select name="test_provider" class="form-select form-select-sm">
                        <option value="openai">OpenAI</option>
                        <option value="anthropic">Anthropic</option>
                        <option value="google">Google</option>
                    </select>
                </div>
                <div class="col-8">
                    <input type="text" name="test_api_key" class="form-control form-control-sm" placeholder="테스트할 API 키 입력">
                </div>
            </div>
            <button type="submit" class="btn btn-outline-info btn-sm w-100"><i class="bi bi-play me-1"></i>키 테스트 실행</button>
        </form>
    </div>
</div>

<?php else: ?>
<div class="alert alert-secondary text-center">
    <i class="bi bi-info-circle me-1"></i> 관리자가 AI 기능을 활성화하면 설정할 수 있습니다.
</div>
<?php endif; ?>

<div class="text-center mt-4">
    <a href="partner_settings.php?hospital_id=<?php echo $hospital_id; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>예산 설정으로</a>
    <a href="partner_wallet.php?hospital_id=<?php echo $hospital_id; ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-wallet2 me-1"></i>지갑 관리</a>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
