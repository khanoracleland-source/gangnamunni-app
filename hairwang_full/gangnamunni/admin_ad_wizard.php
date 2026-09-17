<?php
/**
 * admin_ad_wizard.php - 광고 신청 마법사
 * 5단계 원형 스텝 프로세서로 광고 신청 처리
 *
 * @since 2026-02-14
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
require_once __DIR__ . '/includes/hospital_filter.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

// TEMPORARY FEATURE GATE:
// 광고 신청 마법사 개발을 재개할 때 true로 변경한다.
// AJAX/POST 처리보다 먼저 실행되어 최고관리자를 포함한 모든 관리자를 차단한다.
$admin_ad_wizard_enabled = false;
if (!$admin_ad_wizard_enabled) {
    http_response_code(503);
    $admin_extra_css = '
    .ad-wizard-disabled-card { max-width:720px; margin:8vh auto; border:1px solid var(--bs-border-color); }
    .ad-wizard-disabled-icon { font-size:3rem; color:var(--bs-warning); }
    ';
    include __DIR__ . '/includes/admin_header.php';
    ?>
    <div class="card ad-wizard-disabled-card shadow-sm">
        <div class="card-body text-center p-5">
            <div class="ad-wizard-disabled-icon mb-3"><i class="bi bi-pause-circle"></i></div>
            <h4 class="mb-3">광고 신청 마법사 일시 중지</h4>
            <p class="text-body-secondary mb-4">해당 기능은 향후 개발을 위해 모든 관리자에게 일시적으로 비활성화되어 있습니다.</p>
            <a href="dashboard.php" class="btn btn-primary"><i class="bi bi-speedometer2 me-1"></i>대시보드로 이동</a>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/admin_footer.php';
    mysqli_close($connect);
    exit;
}

require_permission('ads');
if (empty($_SESSION['admin_ad_wizard_csrf'])) $_SESSION['admin_ad_wizard_csrf'] = bin2hex(random_bytes(32));
$ad_wizard_csrf = (string)$_SESSION['admin_ad_wizard_csrf'];
$ad_wizard_hospital_ids = get_assigned_admin_hospital_ids($connect);
$ad_wizard_hospital_in = empty($ad_wizard_hospital_ids) ? '0' : implode(',', array_map('intval', $ad_wizard_hospital_ids));

function ad_wizard_json_error($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function ad_wizard_valid_url($url) {
    $url = trim((string)$url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) return false;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals($ad_wizard_csrf, (string)($_POST['csrf_token'] ?? ''))) ad_wizard_json_error('요청이 만료되었습니다. 페이지를 새로고침해주세요.', 403);
    $action = $_POST['action'];

    if ($action === 'upload_banner') {
        $uploadDir = __DIR__ . '/uploads/ads/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) ad_wizard_json_error('업로드 폴더를 만들 수 없습니다.', 500);
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) ad_wizard_json_error('업로드에 실패했습니다.');
        if ((int)$file['size'] > 5 * 1024 * 1024) ad_wizard_json_error('이미지는 5MB 이하만 가능합니다.');
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) ad_wizard_json_error('실제 JPG, PNG, GIF, WebP 이미지만 가능합니다.');
        $fname = 'ad_' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $fname)) {
            $url = 'uploads/ads/' . $fname;
            $_SESSION['ad_wizard_uploads'][$url] = time();
            echo json_encode(['success' => true, 'url' => $url]);
        } else {
            echo json_encode(['success' => false, 'error' => '저장 실패']);
        }
        exit;
    }

    if ($action === 'submit') {
        $data = json_decode($_POST['data'] ?? '{}', true);
        if (!is_array($data)) ad_wizard_json_error('신청 데이터가 올바르지 않습니다.');
        mysqli_begin_transaction($connect);
        try {
            $hospitalId = (int)($data['hospital_id'] ?? 0);
            if ($hospitalId <= 0 || !in_array($hospitalId, $ad_wizard_hospital_ids, true)) throw new RuntimeException('본인에게 배정된 병원만 광고를 신청할 수 있습니다.');
            $hst = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id=? AND is_active=1 LIMIT 1");
            mysqli_stmt_bind_param($hst, 'i', $hospitalId); mysqli_stmt_execute($hst); $hospitalRow = mysqli_fetch_assoc(mysqli_stmt_get_result($hst)); mysqli_stmt_close($hst);
            if (!$hospitalRow) throw new RuntimeException('병원 정보를 확인할 수 없습니다.');
            $advName = (string)$hospitalRow['name'];
            $advContact = trim((string)($data['contact_name'] ?? ''));
            $advEmail = trim((string)($data['contact_email'] ?? ''));
            $advPhone = trim((string)($data['contact_phone'] ?? ''));
            $advBiz = trim((string)($data['business_number'] ?? ''));
            if ($advContact === '' || !filter_var($advEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('담당자와 올바른 이메일을 입력해주세요.');

            $ast = mysqli_prepare($connect, "SELECT id FROM gangnamunni_advertisers WHERE hospital_id=? LIMIT 1");
            mysqli_stmt_bind_param($ast, 'i', $hospitalId); mysqli_stmt_execute($ast); $advertiserRow = mysqli_fetch_assoc(mysqli_stmt_get_result($ast)); mysqli_stmt_close($ast);
            if ($advertiserRow) {
                $advertiserId = (int)$advertiserRow['id'];
                $ust = mysqli_prepare($connect, "UPDATE gangnamunni_advertisers SET hospital_name=?,contact_name=?,contact_email=?,contact_phone=?,business_number=? WHERE id=? AND hospital_id=?");
                mysqli_stmt_bind_param($ust, 'sssssii', $advName,$advContact,$advEmail,$advPhone,$advBiz,$advertiserId,$hospitalId); mysqli_stmt_execute($ust); mysqli_stmt_close($ust);
            } else {
                $ist = mysqli_prepare($connect, "INSERT INTO gangnamunni_advertisers(hospital_id,hospital_name,contact_name,contact_email,contact_phone,business_number,status,created_at) VALUES(?,?,?,?,?,?,'active',NOW())");
                mysqli_stmt_bind_param($ist, 'isssss', $hospitalId,$advName,$advContact,$advEmail,$advPhone,$advBiz); mysqli_stmt_execute($ist); $advertiserId=mysqli_insert_id($connect); mysqli_stmt_close($ist);
            }

            $pricingId = intval($data['pricing_id'] ?? 0);
            $pst = mysqli_prepare($connect, "SELECT ad_type,duration_days FROM gangnamunni_ad_pricing WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($pst, 'i', $pricingId); mysqli_stmt_execute($pst); $plan=mysqli_fetch_assoc(mysqli_stmt_get_result($pst)); mysqli_stmt_close($pst);
            if (!$plan || !in_array($plan['ad_type'], ['banner','premium','vip'], true)) throw new RuntimeException('올바른 광고 요금제를 선택해주세요.');
            $title = mb_substr(trim((string)($data['title'] ?? '')), 0, 200);
            $subtitle = mb_substr(trim((string)($data['subtitle'] ?? '')), 0, 500);
            $imageUrl = trim((string)($data['image_url'] ?? ''));
            $linkUrl = trim((string)($data['link_url'] ?? ''));
            $startDate = trim((string)($data['start_date'] ?? ''));
            $uploadTime = (int)($_SESSION['ad_wizard_uploads'][$imageUrl] ?? 0);
            if ($title === '' || $uploadTime <= 0 || time() - $uploadTime > 3600 || !preg_match('#^uploads/ads/ad_[a-f0-9]{32}\.(jpg|png|gif|webp)$#', $imageUrl) || !is_file(__DIR__ . '/' . $imageUrl)) throw new RuntimeException('광고 제목과 최근 1시간 내 업로드한 배너 이미지는 필수입니다.');
            if (!ad_wizard_valid_url($linkUrl)) throw new RuntimeException('올바른 HTTP(S) 랜딩 URL을 입력해주세요.');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || $startDate < date('Y-m-d')) throw new RuntimeException('시작일은 오늘 이후여야 합니다.');
            $duration = max(1, (int)$plan['duration_days']);
            $endDate = date('Y-m-d', strtotime($startDate . ' +' . ($duration - 1) . ' days'));
            $adType = $plan['ad_type'];

            $adst = mysqli_prepare($connect, "INSERT INTO gangnamunni_ads(hospital_id,advertiser_id,pricing_id,title,subtitle,image_url,link_url,ad_type,start_date,end_date,is_active,status,display_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,0,'pending',999,NOW(),NOW())");
            mysqli_stmt_bind_param($adst, 'iiisssssss', $hospitalId,$advertiserId,$pricingId,$title,$subtitle,$imageUrl,$linkUrl,$adType,$startDate,$endDate);
            if (!mysqli_stmt_execute($adst)) throw new RuntimeException('광고 신청 저장에 실패했습니다.');
            $adId = mysqli_insert_id($connect);
            mysqli_stmt_close($adst);
            unset($_SESSION['ad_wizard_uploads'][$imageUrl]);

            // Advertising uses the hospital ad wallet. Exposure starts only
            // after a slot assignment passes the three-month balance check.

            mysqli_commit($connect);
            echo json_encode(['success' => true, 'ad_id' => $adId, 'message' => '광고가 신청되었습니다. 결제 주문을 생성한 뒤 PG 승인을 완료해주세요.']);
        } catch (Exception $e) {
            mysqli_rollback($connect);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── 요금제 로드 ──
$pricing = [];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_ad_pricing ORDER BY total_price ASC");
while ($row = mysqli_fetch_assoc($r)) $pricing[] = $row;

// ── 병원 목록 (광고주 선택용, 상세 정보 포함) ──
$hospitals = [];
$r = mysqli_query($connect, "SELECT id, name, phone, email, address FROM gangnamunni_hospitals WHERE is_active=1 AND id IN ({$ad_wizard_hospital_in}) ORDER BY name LIMIT 200");
while ($row = mysqli_fetch_assoc($r)) $hospitals[] = $row;

// ── 기존 광고주 목록 (병원별 매칭) ──
$advertisers = [];
$r = mysqli_query($connect, "SELECT id, hospital_id, hospital_name, contact_name, contact_email, contact_phone, business_number FROM gangnamunni_advertisers WHERE hospital_id IN ({$ad_wizard_hospital_in}) ORDER BY hospital_name");
while ($row = mysqli_fetch_assoc($r)) $advertisers[] = $row;

$admin_page_title = '광고 신청 마법사';
$admin_current = 'ad_wizard';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 원형 스텝 프로세서 (동일) ── */
.wizard-header { display: flex; justify-content: center; align-items: flex-start; padding: 20px 0 30px; gap: 0; }
.wizard-step-item { display: flex; flex-direction: column; align-items: center; min-width: 90px; }
.wizard-step-row { display: flex; align-items: center; }
.wizard-circle {
    width: 56px; height: 56px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 22px; cursor: pointer;
    transition: all 0.3s ease; border: 3px solid transparent; flex-shrink: 0;
}
.wizard-circle.pending { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border-color: var(--bs-border-color); }
.wizard-circle.active { background: #ff6b35; color: #fff; border-color: #ff6b35; box-shadow: 0 0 0 5px rgba(255,107,53,0.25); transform: scale(1.1); }
.wizard-circle.completed { background: #28a745; color: #fff; border-color: #28a745; }
.wizard-circle.completed::after { content: '\2714'; position: absolute; font-size: 14px; }
.wizard-circle.completed span { visibility: hidden; }
.wizard-line { width: 60px; height: 3px; background: var(--bs-border-color); transition: background 0.3s; flex-shrink: 0; }
.wizard-line.completed { background: #28a745; }
.wizard-step-label { font-size: 12px; text-align: center; margin-top: 8px; color: var(--bs-secondary-color); white-space: nowrap; transition: all 0.3s; }
.wizard-step-label.active { color: #ff6b35; font-weight: 700; }
.wizard-step-label.completed { color: #28a745; }

.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ── 광고 유형 카드 ── */
.ad-type-card {
    border: 2px solid var(--bs-border-color); border-radius: 16px;
    padding: 28px 20px; text-align: center; cursor: pointer;
    transition: all 0.25s; position: relative; overflow: hidden;
    height: 100%; display: flex; flex-direction: column; justify-content: flex-start; align-items: center;
}
.ad-type-card:hover { border-color: #ff6b35; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.ad-type-card.selected { border-color: #ff6b35; background: rgba(255,107,53,0.06); box-shadow: 0 0 0 3px rgba(255,107,53,0.2); }
.ad-type-card .type-icon { font-size: 48px; margin-bottom: 12px; }
.ad-type-card .type-name { font-size: 18px; font-weight: 700; }
.ad-type-card .type-price { font-size: 14px; color: #ff6b35; font-weight: 600; margin-top: 4px; }
.ad-type-card .type-desc { font-size: 12px; color: var(--bs-secondary-color); margin-top: 8px; }

/* ── 요금제 선택 ── */
#pricingGrid { display: flex; flex-wrap: wrap; }
.pricing-item { transition: opacity 0.3s; }
.pricing-card {
    border: 2px solid var(--bs-border-color); border-radius: 12px;
    padding: 16px; cursor: pointer; transition: all 0.2s; height: 100%;
}
.pricing-card:hover { border-color: #ff6b35; }
.pricing-card.selected { border-color: #ff6b35; background: rgba(255,107,53,0.06); box-shadow: 0 0 0 3px rgba(255,107,53,0.2); }
.pricing-card .price-badge { font-size: 20px; font-weight: 700; color: #ff6b35; }

/* ── 병원 검색 드롭다운 ── */
.hospital-dropdown {
    display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 1050;
    background: var(--bs-body-bg); border: 1px solid var(--bs-border-color);
    border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    max-height: 280px; overflow-y: auto; margin-top: 4px;
}
.hospital-dropdown.show { display: block; }
.hospital-dropdown-item {
    padding: 10px 14px; cursor: pointer; display: flex; align-items: center; gap: 8px;
    border-bottom: 1px solid var(--bs-border-color-translucent); font-size: 14px;
}
.hospital-dropdown-item:last-child { border-bottom: none; }
.hospital-dropdown-item:hover { background: rgba(255,107,53,0.08); }
.hospital-dropdown-item.highlighted { background: rgba(255,107,53,0.12); }
.hospital-dropdown-item .hospital-name { font-weight: 600; }
.hospital-dropdown-item mark { background: #ffe0b2; padding: 0; border-radius: 2px; }
#ad_hospital_search.has-selection { border-color: #28a745; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2328a745'%3e%3cpath d='M13.485 1.431a1.473 1.473 0 0 1 2.104 2.062l-7.48 9.09a1.473 1.473 0 0 1-2.186.077l-3.9-3.9a1.473 1.473 0 0 1 2.084-2.083l2.773 2.773 6.605-8.019z'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; }

/* ── 배너 업로드 ── */
.banner-upload {
    border: 2px dashed var(--bs-border-color); border-radius: 12px;
    padding: 40px; text-align: center; cursor: pointer; transition: all 0.2s;
    min-height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.banner-upload:hover { border-color: #ff6b35; }
.banner-upload img { max-height: 180px; max-width: 100%; border-radius: 8px; }

/* ── 결제 요약 ── */
.payment-summary {
    background: var(--bs-tertiary-bg); border-radius: 12px;
    padding: 24px; border: 1px solid var(--bs-border-color);
}
.payment-summary .total { font-size: 28px; font-weight: 700; color: #ff6b35; }

/* ── 버튼 ── */
.wizard-footer { display: flex; justify-content: space-between; padding: 20px 0; border-top: 1px solid var(--bs-border-color); margin-top: 24px; }

/* ── 검토 ── */
.review-section { border: 1px solid var(--bs-border-color); border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.review-section h6 { font-weight: 700; margin-bottom: 10px; cursor: pointer; color: #ff6b35; }
.review-section h6:hover { text-decoration: underline; }

/* ── 미리보기 ── */
.banner-preview {
    border: 1px solid var(--bs-border-color); border-radius: 12px;
    padding: 16px; background: var(--bs-tertiary-bg);
    max-width: 400px; margin: 0 auto;
}
</style>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1"><i class="bi bi-megaphone-fill"></i> 광고 신청 마법사</h4>
        <small class="text-muted">단계별로 광고를 신청합니다 (5단계)</small>
    </div>
</div>
<?php if (empty($ad_wizard_hospital_ids)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>현재 관리자 계정에 배정된 병원이 없어 광고를 신청할 수 없습니다.</div>
<?php endif; ?>

<?php if (isDevMode($connect)): ?>
    <?php renderDevModeButton('ad_wizard'); ?>
<?php endif; ?>

<!-- 원형 스텝 인디케이터 -->
<div class="card mb-4">
    <div class="card-body p-2">
        <div class="wizard-header" id="wizardHeader"></div>
    </div>
</div>

<!-- 스텝 콘텐츠 -->
<div class="card">
    <div class="card-body">

        <!-- Step 1: 광고 유형 선택 -->
        <div class="wizard-content" data-step="1">
            <h5 class="mb-4"><i class="bi bi-badge-ad"></i> 광고 유형 선택</h5>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="ad-type-card" data-type="banner" onclick="selectAdType(this)">
                        <div class="type-icon">📢</div>
                        <div class="type-name">Banner</div>
                        <div class="type-price">10만 ~ 30만원</div>
                        <div class="type-desc">메인 배너 영역<br>7~30일 노출</div>
                        <ul class="list-unstyled text-start small mt-3 px-2">
                            <li><i class="bi bi-check text-success"></i> 메인 페이지 배너</li>
                            <li><i class="bi bi-check text-success"></i> 노출/클릭 통계</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ad-type-card" data-type="premium" onclick="selectAdType(this)">
                        <div class="type-icon">⭐</div>
                        <div class="type-name">Premium</div>
                        <div class="type-price">40만 ~ 100만원</div>
                        <div class="type-desc">추천 병원 + 카테고리 상단<br>30~90일 노출</div>
                        <ul class="list-unstyled text-start small mt-3 px-2">
                            <li><i class="bi bi-check text-success"></i> 배너 + 추천 목록</li>
                            <li><i class="bi bi-check text-success"></i> 카테고리 상단 노출</li>
                            <li><i class="bi bi-check text-success"></i> 상세 통계 리포트</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="ad-type-card" data-type="vip" onclick="selectAdType(this)">
                        <div class="type-icon">👑</div>
                        <div class="type-name">VIP</div>
                        <div class="type-price">150만 ~ 200만원</div>
                        <div class="type-desc">전 영역 우선 노출<br>120~180일</div>
                        <ul class="list-unstyled text-start small mt-3 px-2">
                            <li><i class="bi bi-check text-success"></i> 전 영역 최상단</li>
                            <li><i class="bi bi-check text-success"></i> 전담 매니저</li>
                            <li><i class="bi bi-check text-success"></i> 맞춤 통계</li>
                            <li><i class="bi bi-check text-success"></i> 후불 결제 가능</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 요금제 선택 -->
            <h6 class="mb-3">요금제 선택</h6>
            <div class="row g-2" id="pricingGrid">
                <?php foreach ($pricing as $p): ?>
                <div class="col-md-3 col-6 pricing-item" data-ad-type="<?= htmlspecialchars($p['ad_type'] ?? '') ?>">
                    <div class="pricing-card" data-pricing="<?= htmlspecialchars(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>" onclick="selectPricing(this)">
                        <span class="badge <?= ($p['ad_type']??'') === 'vip' ? 'bg-dark' : (($p['ad_type']??'') === 'premium' ? 'bg-warning text-dark' : 'bg-secondary') ?>" style="font-size:10px"><?= strtoupper($p['ad_type'] ?? '') ?></span>
                        <div class="price-badge mt-1">₩<?= number_format($p['total_price'] ?? 0) ?></div>
                        <div class="small fw-bold mt-1"><?= htmlspecialchars($p['display_name'] ?? $p['name'] ?? $p['code']) ?></div>
                        <div class="small text-muted"><?= ($p['duration_days'] ?? 0) ?>일</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: 광고 내용 -->
        <div class="wizard-content" data-step="2">
            <h5 class="mb-3"><i class="bi bi-card-image"></i> 광고 내용</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">광고주 (병원명) <span class="text-danger">*</span></label>
                    <div class="position-relative" id="hospitalSearchWrap">
                        <input type="text" class="form-control" id="ad_hospital_search" placeholder="병원명 검색 또는 직접 입력..." autocomplete="off"
                               oninput="filterHospitals(this.value)" onfocus="showHospitalDropdown()">
                        <input type="hidden" id="ad_hospital" value="">
                        <div class="hospital-dropdown" id="hospitalDropdown">
                            <?php foreach ($hospitals as $h): ?>
                            <div class="hospital-dropdown-item" data-id="<?= $h['id'] ?>"
                                 data-name="<?= htmlspecialchars($h['name']) ?>"
                                 data-phone="<?= htmlspecialchars($h['phone'] ?? '') ?>"
                                 data-email="<?= htmlspecialchars($h['email'] ?? '') ?>"
                                 data-address="<?= htmlspecialchars($h['address'] ?? '') ?>"
                                 onclick="selectHospital(this)">
                                <i class="bi bi-building text-primary"></i>
                                <span class="hospital-name"><?= htmlspecialchars($h['name']) ?></span>
                                <?php if ($h['address']): ?><small class="text-muted ms-2"><?= htmlspecialchars(mb_substr($h['address'], 0, 20)) ?></small><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <input type="text" class="form-control mt-2 d-none" id="ad_advertiser_name" readonly>
                    <div class="form-text text-muted" id="hospitalAutoFillMsg" style="display:none">
                        <i class="bi bi-info-circle"></i> 선택된 병원 정보가 아래에 자동 입력됩니다
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">담당자명 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ad_contact_name" placeholder="담당자 이름">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">담당자 이메일 <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="ad_contact_email" placeholder="email@example.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">담당자 전화</label>
                    <input type="tel" class="form-control" id="ad_contact_phone" placeholder="010-0000-0000">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">사업자등록번호</label>
                    <input type="text" class="form-control" id="ad_business_number" placeholder="000-00-00000">
                </div>
                <div class="col-12"><hr></div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">광고 제목 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ad_title" placeholder="눈에 띄는 광고 제목" maxlength="50" oninput="updatePreview()">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">부제목</label>
                    <input type="text" class="form-control" id="ad_subtitle" placeholder="추가 설명 (선택)" maxlength="80" oninput="updatePreview()">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">배너 이미지 <span class="text-danger">*</span></label>
                    <div class="banner-upload" id="bannerUploadZone" onclick="document.getElementById('bannerInput').click()">
                        <i class="bi bi-cloud-arrow-up" style="font-size:48px;color:var(--bs-secondary-color)"></i>
                        <span class="text-muted small mt-2">클릭하여 업로드 (권장: 1200x400)</span>
                    </div>
                    <input type="file" id="bannerInput" accept="image/*" class="d-none" onchange="uploadBanner(this)">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">미리보기</label>
                    <div class="banner-preview" id="bannerPreview">
                        <div class="text-center text-muted small p-4">
                            이미지와 제목을 입력하면<br>미리보기가 표시됩니다
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">랜딩 URL <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="ad_link_url" placeholder="https://example.com/landing">
                </div>
            </div>
        </div>

        <!-- Step 3: 기간/요금 확인 -->
        <div class="wizard-content" data-step="3">
            <h5 class="mb-3"><i class="bi bi-calendar-check"></i> 기간 / 요금 확인</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">시작일 <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="ad_start_date" onchange="calcEndDate()">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">종료일 (자동 계산)</label>
                    <input type="date" class="form-control" id="ad_end_date" readonly>
                </div>
                <div class="col-md-8">
                    <div class="payment-summary mt-3">
                        <h6 class="fw-bold mb-3">결제 상세</h6>
                        <table class="table table-sm mb-0">
                            <tr><td>상품</td><td class="text-end fw-bold" id="sum_product">-</td></tr>
                            <tr><td>유형</td><td class="text-end" id="sum_type">-</td></tr>
                            <tr><td>기간</td><td class="text-end" id="sum_duration">-</td></tr>
                            <tr><td class="border-top"><strong>공급가</strong></td><td class="text-end border-top" id="sum_supply">-</td></tr>
                            <tr><td>부가세 (10%)</td><td class="text-end" id="sum_vat">-</td></tr>
                            <tr class="table-warning"><td><strong>합계</strong></td><td class="text-end total" id="sum_total">-</td></tr>
                        </table>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="ad_tax_invoice">
                            <label class="form-check-label small" for="ad_tax_invoice">세금계산서 발행 요청</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: 결제 -->
        <div class="wizard-content" data-step="4">
            <h5 class="mb-3"><i class="bi bi-credit-card-2-back"></i> 결제 방법</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card p-3 mb-2" style="cursor:pointer" onclick="selectPayMethod('card')">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="adPayMethod" value="card" id="adPayCard" checked>
                            <label class="form-check-label fw-bold" for="adPayCard">
                                <i class="bi bi-credit-card-2-front me-2" style="font-size:20px"></i> 카드 결제
                            </label>
                            <div class="small text-muted mt-1">신용카드/체크카드 즉시 결제</div>
                        </div>
                    </div>
                    <div class="card p-3 mb-2" style="cursor:pointer" onclick="selectPayMethod('transfer')">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="adPayMethod" value="transfer" id="adPayTransfer">
                            <label class="form-check-label fw-bold" for="adPayTransfer">
                                <i class="bi bi-bank me-2" style="font-size:20px"></i> 계좌이체
                            </label>
                            <div class="small text-muted mt-1">무통장 입금 (입금 확인 후 광고 시작)</div>
                        </div>
                    </div>
                    <div class="card p-3 mb-2" style="cursor:pointer" onclick="selectPayMethod('cash')">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="adPayMethod" value="cash" id="adPayCash">
                            <label class="form-check-label fw-bold" for="adPayCash">
                                <i class="bi bi-cash-stack me-2" style="font-size:20px"></i> 현금 결제
                            </label>
                            <div class="small text-muted mt-1">방문 수납 또는 현금 영수증</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3">
                        <h6 class="fw-bold">사업자 정보 (선택)</h6>
                        <div class="mb-2">
                            <label class="form-label small">사업자등록번호</label>
                            <input type="text" class="form-control form-control-sm" id="ad_biz_number" placeholder="000-00-00000">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">상호명</label>
                            <input type="text" class="form-control form-control-sm" id="ad_biz_name" placeholder="상호명">
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 small">
                        <i class="bi bi-info-circle"></i> 광고 신청 후 관리자 승인이 필요합니다.<br>
                        승인 완료 후 선택한 시작일부터 광고가 노출됩니다.
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: 검토 및 제출 -->
        <div class="wizard-content" data-step="5">
            <h5 class="mb-3"><i class="bi bi-send-check"></i> 검토 및 제출</h5>
            <p class="text-muted small mb-3">모든 정보를 확인한 후 제출해주세요.</p>
            <div id="adReviewSummary"></div>
        </div>

        <!-- 하단 버튼 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none">
                <i class="bi bi-arrow-left"></i> 이전
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-warning text-white" id="btnNext" onclick="nextStep()">
                    다음 <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success btn-lg" id="btnSubmit" onclick="submitAd()" style="display:none">
                    <i class="bi bi-send-check"></i> 광고 신청
                </button>
            </div>
        </div>

    </div>
</div>

<!-- 결과 모달 -->
<div class="modal fade" id="resultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div id="resultIcon" style="font-size:64px"></div>
                <h4 id="resultTitle" class="mt-3"></h4>
                <p id="resultMsg" class="text-muted"></p>
                <a href="admin_ad_wizard.php" class="btn btn-warning text-white mt-3">새 광고 신청</a>
                <a href="admin_ads.php" class="btn btn-outline-secondary mt-3">광고 관리</a>
            </div>
        </div>
    </div>
</div>

<script>
const STEPS = [
    { num: 1, label: '광고 유형', icon: 'bi-badge-ad' },
    { num: 2, label: '광고 내용', icon: 'bi-card-image' },
    { num: 3, label: '기간/요금', icon: 'bi-calendar-check' },
    { num: 4, label: '결제', icon: 'bi-credit-card' },
    { num: 5, label: '검토 제출', icon: 'bi-send-check' },
];
let currentStep = 1;
let completedSteps = new Set();
let adData = { adType: '', pricing: null, bannerUrl: '' };

// ── 스텝 렌더링 ──
function renderStepHeader() {
    const header = document.getElementById('wizardHeader');
    header.innerHTML = STEPS.map((s, i) => {
        let state = 'pending';
        if (s.num === currentStep) state = 'active';
        else if (completedSteps.has(s.num)) state = 'completed';
        const line = i < STEPS.length - 1 ? `<div class="wizard-line ${completedSteps.has(s.num) ? 'completed' : ''}"></div>` : '';
        return `<div class="wizard-step-item"><div class="wizard-step-row"><div class="wizard-circle ${state}" onclick="goToStep(${s.num})"><span>${s.num}</span></div>${line}</div><div class="wizard-step-label ${state}">${s.label}</div></div>`;
    }).join('');
}

function showStep(step) {
    document.querySelectorAll('.wizard-content').forEach(el => el.classList.remove('active'));
    document.querySelector(`.wizard-content[data-step="${step}"]`)?.classList.add('active');
    currentStep = step;
    renderStepHeader();
    updateButtons();
    if (step === 3) updatePricingSummary();
    if (step === 5) buildAdReview();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function goToStep(step) {
    if (step <= currentStep || completedSteps.has(step) || step === currentStep + 1) {
        if (currentStep !== step && currentStep < step) { if (!validateStep(currentStep)) return; completedSteps.add(currentStep); }
        showStep(step);
    }
}
function nextStep() { if (!validateStep(currentStep)) return; completedSteps.add(currentStep); if (currentStep < STEPS.length) showStep(currentStep + 1); }
function prevStep() { if (currentStep > 1) showStep(currentStep - 1); }
function updateButtons() {
    document.getElementById('btnPrev').style.display = currentStep > 1 ? '' : 'none';
    document.getElementById('btnNext').style.display = currentStep < STEPS.length ? '' : 'none';
    document.getElementById('btnSubmit').style.display = currentStep === STEPS.length ? '' : 'none';
}

// ── 유효성 검사 ──
function validateStep(step) {
    if (step === 1) {
        if (!adData.adType) { alert('광고 유형을 선택해주세요.'); return false; }
        if (!adData.pricing) { alert('요금제를 선택해주세요.'); return false; }
    }
    if (step === 2) {
        if (!selectedHospitalId) { alert('본인에게 배정된 병원을 목록에서 선택해주세요.'); return false; }
        if (!document.getElementById('ad_title').value.trim()) { alert('광고 제목을 입력해주세요.'); return false; }
        if (!document.getElementById('ad_contact_email').value.trim()) { alert('담당자 이메일을 입력해주세요.'); return false; }
        if (!adData.bannerUrl) { alert('배너 이미지를 업로드해주세요.'); return false; }
        try { const u = new URL(document.getElementById('ad_link_url').value); if (!['http:','https:'].includes(u.protocol)) throw new Error(); } catch(e) { alert('올바른 랜딩 URL을 입력해주세요.'); return false; }
    }
    if (step === 3) {
        if (!document.getElementById('ad_start_date').value) { alert('시작일을 선택해주세요.'); return false; }
    }
    return true;
}

// ── 광고 유형 선택 ──
function selectAdType(el) {
    document.querySelectorAll('.ad-type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    adData.adType = el.dataset.type;
    // 해당 유형 요금제 강조 (전체 표시, 매칭은 강조)
    document.querySelectorAll('.pricing-item').forEach(item => {
        if (item.dataset.adType === adData.adType) {
            item.style.opacity = '1';
            item.style.order = '-1';
        } else {
            item.style.opacity = '0.5';
            item.style.order = '0';
        }
    });
    // 이전 선택 초기화
    document.querySelectorAll('.pricing-card').forEach(c => c.classList.remove('selected'));
    adData.pricing = null;
}

function selectPricing(el) {
    document.querySelectorAll('.pricing-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    adData.pricing = JSON.parse(el.dataset.pricing);
}

function selectPayMethod(method) {
    document.querySelector(`input[name="adPayMethod"][value="${method}"]`).checked = true;
}

// ── 병원 검색 드롭다운 ──
const hospitalItems = document.querySelectorAll('.hospital-dropdown-item');
const hospitalDropdown = document.getElementById('hospitalDropdown');
const hospitalSearch = document.getElementById('ad_hospital_search');
const advertisersData = <?= json_encode($advertisers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let selectedHospitalId = null;

function showHospitalDropdown() {
    hospitalDropdown.classList.add('show');
    filterHospitals(hospitalSearch.value);
}

function hideHospitalDropdown() {
    setTimeout(() => hospitalDropdown.classList.remove('show'), 200);
}

function filterHospitals(query) {
    const q = query.toLowerCase().trim();
    let visibleCount = 0;
    hospitalItems.forEach(item => {
        if (!item.dataset.id) { item.style.display = ''; return; } // "직접 입력" always visible
        const name = (item.dataset.name || '').toLowerCase();
        const addr = (item.dataset.address || '').toLowerCase();
        const match = !q || name.includes(q) || addr.includes(q);
        item.style.display = match ? '' : 'none';
        if (match) visibleCount++;
        // highlight matching text
        const nameSpan = item.querySelector('.hospital-name');
        if (nameSpan) {
            const origName = item.dataset.name || '';
            if (q && origName.toLowerCase().includes(q)) {
                const idx = origName.toLowerCase().indexOf(q);
                nameSpan.innerHTML = origName.substring(0, idx) + '<mark>' + origName.substring(idx, idx + q.length) + '</mark>' + origName.substring(idx + q.length);
            } else {
                nameSpan.textContent = origName;
            }
        }
    });
}

function selectHospital(el) {
    const searchInput = document.getElementById('ad_hospital_search');
    const hiddenInput = document.getElementById('ad_hospital');
    const nameInput = document.getElementById('ad_advertiser_name');
    const emailInput = document.getElementById('ad_contact_email');
    const phoneInput = document.getElementById('ad_contact_phone');
    const contactInput = document.getElementById('ad_contact_name');
    const bizInput = document.getElementById('ad_business_number');
    const linkInput = document.getElementById('ad_link_url');
    const msg = document.getElementById('hospitalAutoFillMsg');

    if (!el || !el.dataset.id) {
        // "직접 입력" selected
        hiddenInput.value = '';
        searchInput.value = '';
        searchInput.classList.remove('has-selection');
        nameInput.value = '';
        nameInput.readOnly = false;
        nameInput.style.display = '';
        msg.style.display = 'none';
        selectedHospitalId = null;
    } else {
        const id = el.dataset.id;
        const name = el.dataset.name;
        const phone = el.dataset.phone || '';
        const email = el.dataset.email || '';

        selectedHospitalId = id;
        hiddenInput.value = id;
        searchInput.value = name;
        searchInput.classList.add('has-selection');
        nameInput.value = name;
        nameInput.readOnly = true;
        nameInput.style.display = 'none';
        msg.style.display = '';

        // auto-fill from existing advertiser first, then hospital data
        const existingAdv = advertisersData.find(a => a.hospital_name === name);
        if (existingAdv) {
            contactInput.value = contactInput.value || existingAdv.contact_name || '';
            emailInput.value = emailInput.value || existingAdv.contact_email || '';
            phoneInput.value = phoneInput.value || existingAdv.contact_phone || '';
            if (bizInput) bizInput.value = bizInput.value || existingAdv.business_number || '';
        } else {
            if (!emailInput.value && email) emailInput.value = email;
            if (!phoneInput.value && phone) phoneInput.value = phone;
        }

        // auto-fill link URL with hospital page
        if (!linkInput.value) {
            linkInput.value = window.location.origin + '/hairwang/app/hospital_detail.php?id=' + id;
        }
    }
    hospitalDropdown.classList.remove('show');
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    if (!document.getElementById('hospitalSearchWrap')?.contains(e.target)) {
        hospitalDropdown.classList.remove('show');
    }
});

// Keyboard navigation
hospitalSearch.addEventListener('keydown', function(e) {
    if (!hospitalDropdown.classList.contains('show')) return;
    const visible = [...hospitalItems].filter(i => i.style.display !== 'none');
    const current = hospitalDropdown.querySelector('.highlighted');
    let idx = current ? visible.indexOf(current) : -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (current) current.classList.remove('highlighted');
        idx = (idx + 1) % visible.length;
        visible[idx]?.classList.add('highlighted');
        visible[idx]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (current) current.classList.remove('highlighted');
        idx = idx <= 0 ? visible.length - 1 : idx - 1;
        visible[idx]?.classList.add('highlighted');
        visible[idx]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (current) selectHospital(current);
    } else if (e.key === 'Escape') {
        hospitalDropdown.classList.remove('show');
    }
});

// ── 배너 업로드 ──
async function uploadBanner(input) {
    if (!input.files[0]) return;
    const zone = document.getElementById('bannerUploadZone');
    zone.innerHTML = '<div class="spinner-border text-warning"></div>';
    const fd = new FormData();
    fd.append('action', 'upload_banner');
    fd.append('csrf_token', <?=json_encode($ad_wizard_csrf)?>);
    fd.append('file', input.files[0]);
    const r = await fetch('', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
        zone.innerHTML = `<img src="${d.url}"><br><small class="text-success mt-1">업로드 완료</small>`;
        adData.bannerUrl = d.url;
        updatePreview();
    } else {
        zone.innerHTML = `<i class="bi bi-exclamation-triangle text-danger" style="font-size:36px"></i><br><small class="text-danger">${d.error}</small>`;
    }
}

function updatePreview() {
    const title = document.getElementById('ad_title')?.value || '';
    const subtitle = document.getElementById('ad_subtitle')?.value || '';
    const preview = document.getElementById('bannerPreview');
    if (adData.bannerUrl || title) {
        preview.innerHTML = `
            ${adData.bannerUrl ? `<img src="${adData.bannerUrl}" style="width:100%;border-radius:8px;margin-bottom:8px">` : ''}
            <div class="fw-bold">${esc(title)}</div>
            ${subtitle ? `<div class="small text-muted">${esc(subtitle)}</div>` : ''}
        `;
    }
}

// ── 기간/요금 계산 ──
function calcEndDate() {
    const start = document.getElementById('ad_start_date').value;
    if (!start || !adData.pricing) return;
    const days = adData.pricing.duration_days || 30;
    const d = new Date(start);
    d.setDate(d.getDate() + days);
    document.getElementById('ad_end_date').value = d.toISOString().split('T')[0];
    updatePricingSummary();
}

function updatePricingSummary() {
    if (!adData.pricing) return;
    const p = adData.pricing;
    document.getElementById('sum_product').textContent = p.name || p.code;
    document.getElementById('sum_type').textContent = p.ad_type || '-';
    document.getElementById('sum_duration').textContent = (p.duration_days || 0) + '일';
    document.getElementById('sum_supply').textContent = '₩' + Number(p.supply_price || 0).toLocaleString();
    document.getElementById('sum_vat').textContent = '₩' + Number(p.vat_amount || 0).toLocaleString();
    document.getElementById('sum_total').textContent = '₩' + Number(p.total_price || 0).toLocaleString();
    // 시작일 기본값
    if (!document.getElementById('ad_start_date').value) {
        const today = new Date();
        today.setDate(today.getDate() + 1);
        document.getElementById('ad_start_date').value = today.toISOString().split('T')[0];
        calcEndDate();
    }
}

// ── 검토 ──
function buildAdReview() {
    const p = adData.pricing || {};
    document.getElementById('adReviewSummary').innerHTML = `
        <div class="review-section">
            <h6 onclick="goToStep(1)"><i class="bi bi-badge-ad"></i> 1. 광고 유형 / 요금제</h6>
            <div class="small">
                <strong>유형:</strong> <span class="badge bg-warning text-dark">${esc(adData.adType)}</span>
                &nbsp;|&nbsp; <strong>상품:</strong> ${esc(p.name || '-')}
                &nbsp;|&nbsp; <strong>금액:</strong> <strong class="text-danger">₩${Number(p.total_price || 0).toLocaleString()}</strong>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(2)"><i class="bi bi-card-image"></i> 2. 광고 내용</h6>
            <div class="row small">
                <div class="col-6"><strong>광고주:</strong> ${esc(document.getElementById('ad_advertiser_name').value || document.getElementById('ad_hospital_search').value || '-')}</div>
                <div class="col-6"><strong>담당자:</strong> ${esc(document.getElementById('ad_contact_name').value)}</div>
                <div class="col-12"><strong>제목:</strong> ${esc(document.getElementById('ad_title').value)}</div>
                <div class="col-12">${adData.bannerUrl ? `<img src="${adData.bannerUrl}" style="height:60px;border-radius:4px;margin-top:4px">` : '<span class="text-danger">배너 미등록</span>'}</div>
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(3)"><i class="bi bi-calendar-check"></i> 3. 기간</h6>
            <div class="small">
                <strong>기간:</strong> ${document.getElementById('ad_start_date').value} ~ ${document.getElementById('ad_end_date').value}
                (${p.duration_days || 0}일)
            </div>
        </div>
        <div class="review-section">
            <h6 onclick="goToStep(4)"><i class="bi bi-credit-card"></i> 4. 결제</h6>
            <div class="small">
                <strong>결제 방법:</strong> ${document.querySelector('input[name="adPayMethod"]:checked')?.value || '-'}
                &nbsp;|&nbsp; <strong>합계:</strong> <strong class="text-danger">₩${Number(p.total_price || 0).toLocaleString()}</strong>
            </div>
        </div>
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }

// ── 최종 제출 ──
async function submitAd() {
    if (!confirm('광고를 신청하시겠습니까?')) return;
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 신청 중...';

    const p = adData.pricing || {};
    const advName = document.getElementById('ad_advertiser_name').value.trim() || document.getElementById('ad_hospital_search').value.trim();

    const data = {
        hospital_id: Number(selectedHospitalId || 0),
        advertiser_name: advName,
        contact_name: document.getElementById('ad_contact_name').value.trim(),
        contact_email: document.getElementById('ad_contact_email').value.trim(),
        contact_phone: document.getElementById('ad_contact_phone').value.trim(),
        business_number: (document.getElementById('ad_business_number')?.value || '').trim(),
        title: document.getElementById('ad_title').value.trim(),
        subtitle: document.getElementById('ad_subtitle').value.trim(),
        image_url: adData.bannerUrl,
        link_url: document.getElementById('ad_link_url').value.trim(),
        ad_type: adData.adType,
        pricing_id: p.id || 0,
        pricing_code: p.code || '',
        start_date: document.getElementById('ad_start_date').value,
        end_date: document.getElementById('ad_end_date').value,
        amount: p.total_price || 0,
        supply_price: p.supply_price || 0,
        vat_amount: p.vat_amount || 0,
        pay_method: document.querySelector('input[name="adPayMethod"]:checked')?.value || 'card',
    };

    const fd = new FormData();
    fd.append('action', 'submit');
    fd.append('csrf_token', <?=json_encode($ad_wizard_csrf)?>);
    fd.append('data', JSON.stringify(data));

    try {
        const r = await fetch('', {method:'POST', body:fd});
        const result = await r.json();
        const modal = new bootstrap.Modal(document.getElementById('resultModal'));
        if (result.success) {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-check-circle-fill text-success"></i>';
            document.getElementById('resultTitle').textContent = '광고 신청 완료!';
            document.getElementById('resultMsg').textContent = result.message;
        } else {
            document.getElementById('resultIcon').innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
            document.getElementById('resultTitle').textContent = '신청 실패';
            document.getElementById('resultMsg').textContent = result.error;
        }
        modal.show();
    } catch (e) {
        alert('오류: ' + e.message);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-check"></i> 광고 신청';
}

// ── 초기화 ──
renderStepHeader();
showStep(1);
// 오늘 날짜 기본값
document.getElementById('ad_start_date').min = new Date().toISOString().split('T')[0];
</script>

<?php if (isDevMode($connect)) { echo getDevModeJS_ad_wizard(); } ?>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
