<?php
/**
 * 회원가입 마법사 - 3단계 (기본정보 → 관심분야 → 약관+완료)
 */
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 카테고리 로드
$categories = [];
$r = mysqli_query($connect, "SELECT id, name, icon FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order");
if ($r) while ($row = mysqli_fetch_assoc($r)) $categories[] = $row;

// AJAX: 가입 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    header('Content-Type: application/json');
    try {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $interests = $esc($_POST['interests'] ?? '[]');
        $region = $esc($_POST['region'] ?? '');

        if (!$name || !$email || !$password) throw new Exception('필수 정보를 입력해주세요.');
        if (strlen($password) < 6) throw new Exception('비밀번호는 6자 이상이어야 합니다.');

        // 이메일 중복 체크
        $r = mysqli_query($connect, "SELECT mb_no FROM g5_member WHERE mb_email = '".$esc($email)."' LIMIT 1");
        if (mysqli_num_rows($r) > 0) throw new Exception('이미 등록된 이메일입니다.');

        // Gnuboard g5_member 에 가입
        $mb_id = 'sb_' . substr(md5($email . time()), 0, 10);
        $mb_password = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_email, mb_hp, mb_level, mb_datetime, mb_ip, mb_login_ip, mb_email_certify, mb_memo)
                VALUES ('".$esc($mb_id)."', '".$esc($mb_password)."', '".$esc($name)."', '".$esc($email)."',
                '".$esc($phone)."', 2, '{$now}', '".$esc($_SERVER['REMOTE_ADDR'] ?? '')."', '', '{$now}',
                '관심: {$interests} / 지역: {$region}')";
        if (!mysqli_query($connect, $sql)) throw new Exception('가입 처리 중 오류가 발생했습니다.');

        $mbNo = mysqli_insert_id($connect);

        // 자동 로그인 세션
        session_start();
        $_SESSION['ss_mb_id'] = $mb_id;
        $_SESSION['ss_mb_no'] = $mbNo;

        echo json_encode(['success' => true, 'message' => '가입이 완료되었습니다!', 'mb_id' => $mb_id]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>회원가입 | 서울미녀</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
/* ── wizard_base.css (인라인 — includes/ 403 우회) ── */
.wz-steps{display:flex;justify-content:center;align-items:center;padding:20px 0 28px;gap:0;flex-wrap:wrap}
.wz-step{display:flex;flex-direction:column;align-items:center;position:relative;z-index:1;cursor:pointer}
.wz-step .wz-circle{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));border:3px solid var(--bs-border-color);background:var(--bs-body-bg);color:var(--bs-secondary-color);transition:all .3s}
.wz-step.active .wz-circle{border-color:#ff6b35;background:#ff6b35;color:#fff}
.wz-step.done .wz-circle{border-color:#28a745;background:#28a745;color:#fff}
.wz-step .wz-label{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));margin-top:5px;color:var(--bs-secondary-color);font-weight:500;white-space:nowrap}
.wz-step.active .wz-label{color:#ff6b35;font-weight:700}
.wz-step.done .wz-label{color:#28a745}
.wz-line{width:60px;height:3px;background:var(--bs-border-color);margin:0 6px;margin-bottom:18px;transition:background .3s}
.wz-line.done{background:#28a745}
.wz-body{max-width:800px;margin:0 auto;padding:0 12px}
.wz-panel{display:none;animation:wzFade .3s ease}
.wz-panel.active{display:block}
@keyframes wzFade{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.wz-card{border:2px solid var(--bs-border-color);border-radius:14px;padding:20px;cursor:pointer;transition:all .25s;height:100%;position:relative;text-align:center}
.wz-card:hover{border-color:#ff6b35;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.08)}
.wz-card.selected{border-color:#ff6b35;background:rgba(255,107,53,.05);box-shadow:0 0 0 3px rgba(255,107,53,.15)}
.wz-card .wz-card-icon{font-size:calc(36px * var(--fs-scale,1) * var(--fs-page,1));margin-bottom:8px}
.wz-card .wz-card-name{font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700}
.wz-card .wz-card-desc{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color);margin-top:4px}
.wz-card .wz-check{position:absolute;top:8px;right:8px;width:22px;height:22px;border-radius:50%;border:2px solid var(--bs-border-color);display:flex;align-items:center;justify-content:center;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:transparent;transition:all .2s}
.wz-card.selected .wz-check{border-color:#ff6b35;background:#ff6b35;color:#fff}
.wz-search-wrap{position:relative}
.wz-dropdown{display:none;position:absolute;top:100%;left:0;right:0;z-index:1050;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.15);max-height:260px;overflow-y:auto;margin-top:4px}
.wz-dropdown.show{display:block}
.wz-dropdown-item{padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;border-bottom:1px solid var(--bs-border-color-translucent);font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1))}
.wz-dropdown-item:last-child{border-bottom:none}
.wz-dropdown-item:hover,.wz-dropdown-item.highlighted{background:rgba(255,107,53,.08)}
.wz-dropdown-item mark{background:#ffe0b2;padding:0;border-radius:2px}
.wz-upload{border:2px dashed var(--bs-border-color);border-radius:12px;padding:30px;text-align:center;cursor:pointer;transition:all .2s;min-height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center}
.wz-upload:hover,.wz-upload.dragover{border-color:#ff6b35;background:rgba(255,107,53,.03)}
.wz-upload img{max-height:150px;max-width:100%;border-radius:8px;margin-top:8px}
.wz-stars{display:inline-flex;gap:4px;font-size:calc(28px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer}
.wz-stars .star{color:var(--bs-border-color);transition:color .15s}
.wz-stars .star.active{color:#ffc107}
.wz-stars .star:hover{color:#ffb300}
.wz-summary{background:var(--bs-tertiary-bg);border-radius:12px;padding:20px;margin-bottom:16px}
.wz-summary h6{cursor:pointer;margin-bottom:10px}
.wz-summary h6:hover{color:#ff6b35}
.wz-summary .row{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1))}
.wz-actions{display:flex;justify-content:space-between;align-items:center;margin-top:24px;padding-top:16px;border-top:1px solid var(--bs-border-color)}
.wz-actions .btn-wz-next{background:#ff6b35;color:#fff;border:none;padding:10px 28px;border-radius:10px;font-weight:600}
.wz-actions .btn-wz-next:hover{background:#e55a2b}
.wz-actions .btn-wz-prev{background:transparent;border:1px solid var(--bs-border-color);padding:10px 20px;border-radius:10px}
.wz-actions .btn-wz-submit{background:#28a745;color:#fff;border:none;padding:12px 32px;border-radius:10px;font-weight:700;font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1))}
.wz-actions .btn-wz-submit:hover{background:#218838}
.wz-actions .btn-wz-home{display:inline-flex;align-items:center;gap:6px;padding:10px 20px;border-radius:10px;font-weight:600;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));color:#fff;background:linear-gradient(135deg,#667eea,#764ba2);border:none;text-decoration:none;transition:all .2s;box-shadow:0 2px 8px rgba(102,126,234,.35)}
.wz-actions .btn-wz-home:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(102,126,234,.5);color:#fff;text-decoration:none}
.wz-slot{display:inline-block;padding:8px 16px;border:1px solid var(--bs-border-color);border-radius:8px;cursor:pointer;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));transition:all .15s;margin:3px}
.wz-slot:hover{border-color:#ff6b35;color:#ff6b35}
.wz-slot.selected{background:#ff6b35;color:#fff;border-color:#ff6b35}
.wz-slot.disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
.wz-tag{display:inline-block;padding:6px 14px;border:1px solid var(--bs-border-color);border-radius:20px;cursor:pointer;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));transition:all .15s;margin:3px}
.wz-tag:hover{border-color:#ff6b35}
.wz-tag.selected{background:#ff6b35;color:#fff;border-color:#ff6b35}
.wz-progress-log{background:var(--bs-tertiary-bg);border-radius:10px;padding:14px;font-family:'Consolas',monospace;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));max-height:300px;overflow-y:auto;line-height:1.6}
@media(max-width:576px){
  .wz-steps{gap:0;padding:12px 0 20px}
  .wz-line{width:30px}
  .wz-step .wz-circle{width:34px;height:34px;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1))}
  .wz-step .wz-label{font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1))}
  .wz-body{padding:0 8px}
  .wz-card{padding:14px}
  .wz-card .wz-card-icon{font-size:calc(28px * var(--fs-scale,1) * var(--fs-page,1))}
  .wz-actions{flex-wrap:wrap;gap:8px}
}
</style>
<style>
body{background:var(--bs-body-bg);padding-bottom:80px}
.reg-header{text-align:center;padding:24px 0 8px}
.reg-header h3{font-weight:800}
.reg-header .subtitle{color:var(--bs-secondary-color);font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1))}
.pw-strength{height:4px;border-radius:2px;margin-top:4px;transition:all .3s}
.social-btn{width:100%;padding:12px;border-radius:10px;font-weight:600;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;border:1px solid var(--bs-border-color);background:var(--bs-body-bg);cursor:pointer;transition:all .15s}
.social-btn:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,.08)}
.social-kakao{background:#FEE500;border-color:#FEE500;color:#3C1E1E}
.social-naver{background:#03C75A;border-color:#03C75A;color:#fff}
.region-grid{display:flex;flex-wrap:wrap;gap:6px;justify-content:center}
.terms-item{border:1px solid var(--bs-border-color);border-radius:10px;padding:12px;margin-bottom:8px}
.terms-item .form-check-label{font-weight:600;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1))}
.terms-content{max-height:120px;overflow-y:auto;font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color);margin-top:8px;padding:8px;background:var(--bs-tertiary-bg);border-radius:6px;display:none}
</style>
</head>
<body>
<div class="reg-header">
    <h3>서울미녀 회원가입</h3>
    <div class="subtitle">아름다움을 찾는 여정의 시작</div>
</div>

<div class="wz-steps">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">기본정보</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">관심분야</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">약관동의</span></div>
</div>

<div class="wz-body">
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-person-plus"></i> 기본 정보</h5>
        <div class="mb-3">
            <button class="social-btn social-kakao" onclick="alert('카카오 로그인은 준비 중입니다. (관리자: 카카오 REST API 키 발급 후 활성화)')">
                <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#3C1E1E" d="M12 3C6.5 3 2 6.58 2 11c0 2.84 1.87 5.33 4.68 6.74l-1.2 4.46c-.08.3.26.54.52.36l5.28-3.52c.24.02.48.03.72.03 5.5 0 10-3.58 10-8s-4.5-8-10-8z"/></svg>
                카카오로 시작하기
            </button>
            <button class="social-btn social-naver" onclick="alert('네이버 로그인은 준비 중입니다. (관리자: 네이버 Client ID/Secret 발급 후 활성화)')">
                <b style="font-size:16px">N</b> 네이버로 시작하기
            </button>
        </div>

        <div class="text-center my-3">
            <span class="text-muted small px-3" style="background:var(--bs-body-bg);position:relative;z-index:1">또는 이메일로 가입</span>
            <hr style="margin-top:-10px">
        </div>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-bold">이름 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="reg_name" placeholder="이름">
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">이메일 <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="reg_email" placeholder="email@example.com">
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">비밀번호 <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="reg_password" placeholder="6자 이상" oninput="checkPwStrength(this.value)">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePw()"><i class="bi bi-eye" id="pwEyeIcon"></i></button>
                </div>
                <div class="pw-strength" id="pwStrength"></div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-bold">전화번호</label>
                <input type="tel" class="form-control" id="reg_phone" placeholder="010-0000-0000">
            </div>
        </div>

        <div class="wz-actions">
            <a href="/hairwang/gangnamunni/" class="btn-wz-home"><i class="bi bi-house-fill"></i> 홈 페이지로</a>
            <button class="btn-wz-next" onclick="wz.next()">관심분야 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 관심 분야 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-heart"></i> 관심 분야 선택</h5>
        <p class="small text-muted mb-3">관심 있는 시술을 선택하면 맞춤 정보를 제공해드립니다 (선택사항)</p>
        <div class="cat-grid mb-4" id="interestGrid">
            <?php foreach ($categories as $cat): ?>
            <div class="wz-tag" data-value="<?= $cat['id'] ?>"><?= $cat['icon'] ?: '🏷️' ?> <?= htmlspecialchars($cat['name']) ?></div>
            <?php endforeach; ?>
        </div>

        <h6 class="small fw-bold text-muted mb-2">관심 지역</h6>
        <div class="region-grid" id="regionGrid">
            <?php foreach (['서울 강남', '서울 강북', '서울 기타', '경기/인천', '부산', '대구', '기타 지역'] as $r): ?>
            <div class="wz-tag" data-value="<?= $r ?>"><?= $r ?></div>
            <?php endforeach; ?>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">약관 동의 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 약관 동의 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-shield-check"></i> 약관 동의</h5>

        <div class="terms-item" style="background:rgba(255,107,53,.05)">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="termsAll" onchange="toggleAllTerms(this.checked)">
                <label class="form-check-label" for="termsAll" style="font-size:15px">전체 동의</label>
            </div>
        </div>
        <div class="terms-item">
            <div class="form-check">
                <input class="form-check-input terms-cb" type="checkbox" id="terms1" onchange="updateAllCheck()">
                <label class="form-check-label" for="terms1">[필수] 이용약관 동의</label>
            </div>
            <div class="terms-content" id="terms1Content">서울미녀 서비스 이용약관입니다. 본 약관은 서울미녀(이하 '회사')가 제공하는 서비스의 이용조건 및 절차에 관한 사항을 규정합니다...</div>
            <button class="btn btn-sm btn-link p-0 mt-1" onclick="this.previousElementSibling.style.display=this.previousElementSibling.style.display==='none'?'block':'none'">내용 보기</button>
        </div>
        <div class="terms-item">
            <div class="form-check">
                <input class="form-check-input terms-cb" type="checkbox" id="terms2" onchange="updateAllCheck()">
                <label class="form-check-label" for="terms2">[필수] 개인정보 수집·이용 동의</label>
            </div>
            <div class="terms-content">수집항목: 이름, 이메일, 전화번호, 관심분야. 수집목적: 서비스 제공, 맞춤 정보 안내. 보유기간: 회원탈퇴 시까지...</div>
            <button class="btn btn-sm btn-link p-0 mt-1" onclick="this.previousElementSibling.style.display=this.previousElementSibling.style.display==='none'?'block':'none'">내용 보기</button>
        </div>
        <div class="terms-item">
            <div class="form-check">
                <input class="form-check-input terms-cb" type="checkbox" id="terms3" onchange="updateAllCheck()">
                <label class="form-check-label" for="terms3">[선택] 마케팅 정보 수신 동의</label>
            </div>
            <div class="terms-content">이벤트, 할인 정보 등 마케팅 목적의 알림을 받으실 수 있습니다. 수신 거부는 언제든 가능합니다.</div>
            <button class="btn btn-sm btn-link p-0 mt-1" onclick="this.previousElementSibling.style.display=this.previousElementSibling.style.display==='none'?'block':'none'">내용 보기</button>
        </div>

        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-submit" id="btnRegSubmit" onclick="submitRegister()"><i class="bi bi-check-lg"></i> 가입하기</button>
        </div>
    </div>

    <!-- 완료 -->
    <div class="wz-panel" id="wzPanelDone" style="display:none;text-align:center;padding:40px 0">
        <div style="font-size:64px">🎉</div>
        <h4 class="mt-3">가입을 환영합니다!</h4>
        <p class="text-muted">서울미녀에서 아름다움을 찾아보세요</p>
        <div class="card border-0 shadow-sm mx-auto mt-3" style="max-width:280px">
            <div class="card-body text-center">
                <div style="font-size:32px">🎁</div>
                <div class="fw-bold">첫 상담 10% 할인</div>
                <div class="small text-muted">가입 축하 쿠폰이 발급되었습니다</div>
            </div>
        </div>
        <div class="mt-4">
            <a href="/" class="btn btn-primary">시작하기</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/bottom_navigation.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* wizard_base.js (인라인 — includes/ 403 우회) */
class WizardBase {
    constructor(totalSteps, opts = {}) {
        this.totalSteps = totalSteps;
        this.currentStep = 1;
        this.validators = {};
        this.onStepChange = opts.onStepChange || null;
        this.onSubmit = opts.onSubmit || null;
        this.storageKey = opts.storageKey || null;
        this._initEvents();
    }
    _initEvents() {
        document.querySelectorAll('.wz-step').forEach(s => {
            s.addEventListener('click', () => {
                const n = parseInt(s.dataset.step);
                if (n <= this.currentStep + 1 && n >= 1) this.goTo(n);
            });
        });
    }
    goTo(step) {
        if (step < 1 || step > this.totalSteps) return;
        if (step > this.currentStep && this.validators[this.currentStep]) {
            if (!this.validators[this.currentStep]()) return;
        }
        this.currentStep = step;
        document.querySelectorAll('.wz-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('wzPanel' + step);
        if (panel) panel.classList.add('active');
        document.querySelectorAll('.wz-step').forEach(s => {
            const sn = parseInt(s.dataset.step);
            s.classList.remove('active', 'done');
            if (sn === step) s.classList.add('active');
            else if (sn < step) s.classList.add('done');
        });
        document.querySelectorAll('.wz-line').forEach((l, i) => {
            l.classList.toggle('done', i + 1 < step);
        });
        if (this.onStepChange) this.onStepChange(step);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        this._autoSave();
    }
    next() { if (this.currentStep < this.totalSteps) this.goTo(this.currentStep + 1); }
    prev() { if (this.currentStep > 1) this.goTo(this.currentStep - 1); }
    setValidator(step, fn) { this.validators[step] = fn; }
    _autoSave() {
        if (!this.storageKey) return;
        try { localStorage.setItem(this.storageKey, JSON.stringify({ step: this.currentStep, ts: Date.now() })); } catch (e) {}
    }
    restore() {
        if (!this.storageKey) return null;
        try {
            const raw = localStorage.getItem(this.storageKey);
            if (!raw) return null;
            const data = JSON.parse(raw);
            if (Date.now() - data.ts > 86400000) { localStorage.removeItem(this.storageKey); return null; }
            return data;
        } catch (e) { return null; }
    }
    clearStorage() { if (this.storageKey) localStorage.removeItem(this.storageKey); }
}
function initStarRating(container, callback) {
    const stars = container.querySelectorAll('.star');
    let value = 0;
    stars.forEach((star, i) => {
        star.addEventListener('click', () => { value = i + 1; stars.forEach((s, j) => s.classList.toggle('active', j < value)); if (callback) callback(value); });
        star.addEventListener('mouseenter', () => { stars.forEach((s, j) => s.classList.toggle('active', j <= i)); });
    });
    container.addEventListener('mouseleave', () => { stars.forEach((s, j) => s.classList.toggle('active', j < value)); });
    return { getValue: () => value, setValue: (v) => { value = v; stars.forEach((s, j) => s.classList.toggle('active', j < v)); } };
}
function initCardSelect(container, opts = {}) {
    const cards = container.querySelectorAll('.wz-card');
    const multi = opts.multi || false;
    const selected = new Set();
    cards.forEach(card => {
        card.addEventListener('click', () => {
            const val = card.dataset.value;
            if (multi) { if (selected.has(val)) { selected.delete(val); card.classList.remove('selected'); } else { selected.add(val); card.classList.add('selected'); } }
            else { cards.forEach(c => c.classList.remove('selected')); selected.clear(); selected.add(val); card.classList.add('selected'); }
            if (opts.onChange) opts.onChange([...selected]);
        });
    });
    return { getSelected: () => [...selected], setSelected: (vals) => { selected.clear(); cards.forEach(c => { const v = c.dataset.value; if (vals.includes(v)) { selected.add(v); c.classList.add('selected'); } else c.classList.remove('selected'); }); } };
}
function initTagSelect(container, opts = {}) {
    const tags = container.querySelectorAll('.wz-tag');
    const max = opts.max || Infinity;
    const selected = new Set();
    tags.forEach(tag => {
        tag.addEventListener('click', () => {
            const val = tag.dataset.value;
            if (selected.has(val)) { selected.delete(val); tag.classList.remove('selected'); }
            else if (selected.size < max) { selected.add(val); tag.classList.add('selected'); }
            if (opts.onChange) opts.onChange([...selected]);
        });
    });
    return { getSelected: () => [...selected] };
}
function wzEsc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function wzAlert(msg) { alert(msg); }
</script>
<script>
const wz = new WizardBase(3);

// Step 1 입력값 보존 — 패널 숨길 때 브라우저가 값 초기화하는 문제 방지
const _step1Fields = ['reg_name', 'reg_email', 'reg_password', 'reg_phone'];
const _step1Saved = {};
const _origGoTo = wz.goTo.bind(wz);
wz.goTo = function(step) {
    // Step 1에서 떠날 때 값 저장
    if (wz.currentStep === 1) {
        _step1Fields.forEach(id => {
            const el = document.getElementById(id);
            if (el) _step1Saved[id] = el.value;
        });
    }
    _origGoTo(step);
    // Step 1으로 돌아올 때 값 복원
    if (step === 1) {
        _step1Fields.forEach(id => {
            const el = document.getElementById(id);
            if (el && _step1Saved[id] !== undefined) el.value = _step1Saved[id];
        });
    }
};

// 비밀번호 강도
function checkPwStrength(pw) {
    const bar = document.getElementById('pwStrength');
    let score = 0;
    if (pw.length >= 6) score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    const colors = ['#dc3545','#dc3545','#ffc107','#28a745','#28a745'];
    const widths = ['20%','40%','60%','80%','100%'];
    bar.style.width = widths[Math.min(score, 4)];
    bar.style.background = colors[Math.min(score, 4)];
}

function togglePw() {
    const input = document.getElementById('reg_password');
    const icon = document.getElementById('pwEyeIcon');
    if (input.type === 'password') { input.type = 'text'; icon.className = 'bi bi-eye-slash'; }
    else { input.type = 'password'; icon.className = 'bi bi-eye'; }
}

// 관심분야
const interestSelect = initTagSelect(document.getElementById('interestGrid'), { max: 5 });
const regionSelect = initTagSelect(document.getElementById('regionGrid'), {});

// 약관
function toggleAllTerms(checked) {
    document.querySelectorAll('.terms-cb').forEach(cb => cb.checked = checked);
}
function updateAllCheck() {
    const all = document.querySelectorAll('.terms-cb');
    document.getElementById('termsAll').checked = [...all].every(cb => cb.checked);
}

// 유효성
wz.setValidator(1, () => {
    if (!document.getElementById('reg_name').value.trim()) { alert('이름을 입력해주세요.'); return false; }
    if (!document.getElementById('reg_email').value.trim()) { alert('이메일을 입력해주세요.'); return false; }
    if (document.getElementById('reg_password').value.length < 6) { alert('비밀번호는 6자 이상이어야 합니다.'); return false; }
    return true;
});
wz.setValidator(3, () => {
    if (!document.getElementById('terms1').checked || !document.getElementById('terms2').checked) {
        alert('필수 약관에 동의해주세요.'); return false;
    }
    return true;
});

async function submitRegister() {
    if (!wz.validators[3]()) return;
    const btn = document.getElementById('btnRegSubmit');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 가입 중...';

    const fd = new FormData();
    fd.append('action', 'register');
    fd.append('name', document.getElementById('reg_name').value.trim());
    fd.append('email', document.getElementById('reg_email').value.trim());
    fd.append('password', document.getElementById('reg_password').value);
    fd.append('phone', document.getElementById('reg_phone').value.trim());
    fd.append('interests', JSON.stringify(interestSelect.getSelected()));
    fd.append('region', regionSelect.getSelected().join(',') || '');

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            document.querySelectorAll('.wz-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('wzPanelDone').style.display = 'block';
            document.querySelector('.wz-steps').style.display = 'none';
        } else { alert(data.error); btn.disabled = false; btn.textContent = '가입하기'; }
    } catch (e) { alert('오류: ' + e.message); btn.disabled = false; btn.textContent = '가입하기'; }
}
</script>
</body>
</html>
