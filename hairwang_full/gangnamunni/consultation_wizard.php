<?php
/**
 * 상담 신청 마법사 - 4단계 (상담유형 → 관심시술 → 증상+사진 → 연락처+확인)
 */
require_once __DIR__ . '/includes/init.php';

$hospital_id = intval($_GET['hospital_id'] ?? $_GET['id'] ?? 0);

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 병원 정보 (선택적)
$hospital = null;
if ($hospital_id) {
    $r = mysqli_query($connect, "SELECT id, name, phone, address FROM gangnamunni_hospitals WHERE id = {$hospital_id}");
    if ($r) $hospital = mysqli_fetch_assoc($r);
}

// 카테고리 (시술 분류)
$categories = [];
$r = mysqli_query($connect, "SELECT id, name, icon, emoji FROM gangnamunni_categories WHERE is_active = 1 ORDER BY sort_order, name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $categories[] = $row;

// 병원 목록 (병원 미선택 시)
$hospitals = [];
if (!$hospital_id) {
    $r = mysqli_query($connect, "SELECT id, name, address FROM gangnamunni_hospitals ORDER BY name LIMIT 100");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $hospitals[] = $row;
}

// AJAX: 상담 제출
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_consultation') {
    header('Content-Type: application/json');
    try {
        // 테이블 생성 (없으면)
        mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_consultations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hospital_id INT DEFAULT 0,
            consult_type ENUM('online','phone','visit') DEFAULT 'online',
            categories JSON,
            symptoms TEXT,
            patient_name VARCHAR(100) NOT NULL,
            patient_phone VARCHAR(20) NOT NULL,
            patient_email VARCHAR(200) DEFAULT '',
            photo_urls JSON,
            status ENUM('pending','replied','completed','cancelled') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $hospId = intval($_POST['hospital_id'] ?? 0);
        $type = $esc($_POST['consult_type'] ?? 'online');
        $cats = $esc($_POST['categories'] ?? '[]');
        $symptoms = $esc($_POST['symptoms'] ?? '');
        $name = $esc($_POST['patient_name'] ?? '');
        $phone = $esc($_POST['patient_phone'] ?? '');
        $email = $esc($_POST['patient_email'] ?? '');
        $photos = $esc($_POST['photo_urls'] ?? '[]');

        if (!$name || !$phone) throw new Exception('이름과 전화번호를 입력해주세요.');

        $sql = "INSERT INTO gangnamunni_consultations (hospital_id, consult_type, categories, symptoms, patient_name, patient_phone, patient_email, photo_urls, created_at)
                VALUES ({$hospId}, '{$type}', '{$cats}', '{$symptoms}', '{$name}', '{$phone}', '{$email}', '{$photos}', NOW())";
        if (!mysqli_query($connect, $sql)) throw new Exception(mysqli_error($connect));

        echo json_encode(['success' => true, 'message' => '상담 신청이 접수되었습니다.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$page_title = '상담 신청';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> | 서울미녀</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="includes/wizard_base.css" rel="stylesheet">
<style>
body{background:var(--bs-body-bg);padding-bottom:80px}
.type-card .wz-card-icon{font-size:calc(42px * var(--fs-scale,1) * var(--fs-page,1))}
.cat-grid{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}
.photo-preview{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.photo-preview .photo-thumb{width:80px;height:80px;border-radius:8px;object-fit:cover;border:1px solid var(--bs-border-color);position:relative}
.photo-preview .photo-remove{position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:#dc3545;color:#fff;font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));display:flex;align-items:center;justify-content:center;cursor:pointer;border:none}
</style>
</head>
<body>
<div class="container-fluid px-3 pt-2">
    <a href="<?= $hospital_id ? "hospital_detail.php?id={$hospital_id}" : 'hospitals.php' ?>" class="text-decoration-none small text-muted"><i class="bi bi-arrow-left"></i> 돌아가기</a>
    <h5 class="mt-2 fw-bold"><?= $hospital ? htmlspecialchars($hospital['name']) . ' ' : '' ?>상담 신청</h5>
</div>

<div class="wz-steps">
    <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">상담유형</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">관심시술</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">증상/사진</span></div>
    <div class="wz-line"></div>
    <div class="wz-step" data-step="4"><div class="wz-circle">4</div><span class="wz-label">확인</span></div>
</div>

<div class="wz-body">

    <!-- Step 1: 상담 유형 -->
    <div class="wz-panel active" id="wzPanel1">
        <h5 class="mb-3"><i class="bi bi-chat-left-heart"></i> 상담 유형 선택</h5>
        <div class="row g-3" id="typeGrid">
            <div class="col-4">
                <div class="wz-card type-card" data-value="online">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="wz-card-icon">💬</div>
                    <div class="wz-card-name">온라인 상담</div>
                    <div class="wz-card-desc">채팅으로 빠른 상담</div>
                </div>
            </div>
            <div class="col-4">
                <div class="wz-card type-card" data-value="phone">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="wz-card-icon">📞</div>
                    <div class="wz-card-name">전화 상담</div>
                    <div class="wz-card-desc">전문 상담사 연결</div>
                </div>
            </div>
            <div class="col-4">
                <div class="wz-card type-card" data-value="visit">
                    <div class="wz-check"><i class="bi bi-check"></i></div>
                    <div class="wz-card-icon">🏥</div>
                    <div class="wz-card-name">방문 상담</div>
                    <div class="wz-card-desc">직접 방문 예약</div>
                </div>
            </div>
        </div>

        <?php if (!$hospital_id && count($hospitals) > 0): ?>
        <div class="mt-4">
            <label class="form-label small fw-bold">병원 선택 (선택사항)</label>
            <div class="wz-search-wrap">
                <input type="text" class="form-control" id="hospSearch" placeholder="병원명 검색..." autocomplete="off">
                <div class="wz-dropdown" id="hospDropdown">
                    <div class="wz-dropdown-item" data-id="0" data-name="선택 안함">
                        <i class="bi bi-x-circle text-muted"></i> 특정 병원 없음 (일반 상담)
                    </div>
                    <?php foreach ($hospitals as $h): ?>
                    <div class="wz-dropdown-item" data-id="<?= $h['id'] ?>" data-name="<?= htmlspecialchars($h['name']) ?>">
                        <i class="bi bi-building text-primary"></i> <?= htmlspecialchars($h['name']) ?>
                        <small class="text-muted ms-1"><?= htmlspecialchars(mb_substr($h['address'] ?? '', 0, 15)) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="wz-actions">
            <div></div>
            <button class="btn-wz-next" onclick="wz.next()">관심 시술 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 2: 관심 시술 -->
    <div class="wz-panel" id="wzPanel2">
        <h5 class="mb-3"><i class="bi bi-tags"></i> 관심 시술 선택</h5>
        <p class="small text-muted mb-3">관심 있는 시술을 선택해주세요 (복수 선택 가능)</p>
        <div class="cat-grid" id="catGrid">
            <?php foreach ($categories as $cat): ?>
            <div class="wz-tag" data-value="<?= $cat['id'] ?>">
                <?= $cat['emoji'] ?: '🏷️' ?> <?= htmlspecialchars($cat['name']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">증상 입력 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 3: 증상 & 사진 -->
    <div class="wz-panel" id="wzPanel3">
        <h5 class="mb-3"><i class="bi bi-camera"></i> 증상 & 사진</h5>
        <div class="mb-3">
            <label class="form-label small fw-bold">어떤 고민이 있으신가요?</label>
            <textarea class="form-control" id="cs_symptoms" rows="4" placeholder="현재 고민이나 원하는 시술에 대해 자유롭게 적어주세요"></textarea>
        </div>
        <div>
            <label class="form-label small fw-bold">사진 첨부 <span class="text-secondary">(선택)</span></label>
            <div class="wz-upload" onclick="document.getElementById('cs_photos').click()" id="photoUpload">
                <i class="bi bi-camera" style="font-size:28px;color:var(--bs-secondary-color)"></i>
                <div class="small text-muted mt-1">사진을 올려주시면 더 정확한 상담이 가능합니다</div>
                <div class="small text-muted">(최대 5장, 각 10MB 이하)</div>
            </div>
            <input type="file" id="cs_photos" accept="image/*" multiple style="display:none" onchange="handlePhotos(this)">
            <div class="photo-preview" id="photoPreview"></div>
        </div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
            <button class="btn-wz-next" onclick="wz.next()">연락처 입력 <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>

    <!-- Step 4: 연락처 & 확인 -->
    <div class="wz-panel" id="wzPanel4">
        <h5 class="mb-3"><i class="bi bi-person-check"></i> 연락처 & 확인</h5>
        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label small fw-bold">이름 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="cs_name" placeholder="이름" value="<?= htmlspecialchars($member['mb_name'] ?? '') ?>">
            </div>
            <div class="col-6">
                <label class="form-label small fw-bold">전화번호 <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" id="cs_phone" placeholder="010-0000-0000" value="<?= htmlspecialchars($member['mb_hp'] ?? '') ?>">
            </div>
            <div class="col-6">
                <label class="form-label small fw-bold">이메일</label>
                <input type="email" class="form-control" id="cs_email" placeholder="email@example.com" value="<?= htmlspecialchars($member['mb_email'] ?? '') ?>">
            </div>
        </div>
        <h6 class="fw-bold small text-muted">상담 요약</h6>
        <div id="consultSummary"></div>
        <div class="wz-actions">
            <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
            <button class="btn-wz-submit" id="btnConsultSubmit" onclick="submitConsult()"><i class="bi bi-send"></i> 상담 신청</button>
        </div>
    </div>

    <!-- 완료 -->
    <div class="wz-panel" id="wzPanelDone" style="display:none;text-align:center;padding:40px 0">
        <div style="font-size:64px;color:#28a745">💬</div>
        <h4 class="mt-3">상담 신청 완료!</h4>
        <p class="text-muted">빠른 시간 내에 연락드리겠습니다.</p>
        <a href="<?= $hospital_id ? "hospital_detail.php?id={$hospital_id}" : '/' ?>" class="btn btn-primary mt-3">홈으로</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/bottom_navigation.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(4, { storageKey: 'consult_<?= $hospital_id ?>' });
const consultData = { type: '', hospitalId: <?= $hospital_id ?>, hospitalName: '<?= $hospital ? addslashes($hospital['name']) : '' ?>', categories: [], photos: [] };

// Step 1
const typeSelect = initCardSelect(document.getElementById('typeGrid'), {
    onChange: (vals) => { consultData.type = vals[0] || ''; }
});

<?php if (!$hospital_id && count($hospitals) > 0): ?>
initSearchDropdown(document.getElementById('hospSearch'), document.getElementById('hospDropdown'), {
    onSelect: (item) => {
        consultData.hospitalId = parseInt(item.dataset.id || 0);
        consultData.hospitalName = item.dataset.name || '';
        document.getElementById('hospSearch').value = consultData.hospitalName;
    }
});
<?php endif; ?>

// Step 2
const tagSelect = initTagSelect(document.getElementById('catGrid'), {
    max: 5,
    onChange: (vals) => { consultData.categories = vals; }
});

// Step 3: 사진
const uploadedPhotos = [];
function handlePhotos(input) {
    const files = [...input.files].slice(0, 5 - uploadedPhotos.length);
    files.forEach(file => {
        if (file.size > 10 * 1024 * 1024) { alert(file.name + ': 10MB 초과'); return; }
        const reader = new FileReader();
        reader.onload = (e) => {
            uploadedPhotos.push({ name: file.name, data: e.target.result });
            renderPhotoPreviews();
        };
        reader.readAsDataURL(file);
    });
}
function renderPhotoPreviews() {
    const container = document.getElementById('photoPreview');
    container.innerHTML = '';
    uploadedPhotos.forEach((p, i) => {
        container.innerHTML += `<div style="position:relative;display:inline-block">
            <img src="${p.data}" class="photo-thumb">
            <button class="photo-remove" onclick="uploadedPhotos.splice(${i},1);renderPhotoPreviews()">&times;</button>
        </div>`;
    });
}

// 검증
wz.setValidator(1, () => {
    if (!consultData.type) { alert('상담 유형을 선택해주세요.'); return false; }
    return true;
});

// Step 4: 요약
wz.onStepChange = (step) => {
    if (step === 4) {
        const typeLabel = {online:'온라인 상담', phone:'전화 상담', visit:'방문 상담'}[consultData.type] || '-';
        const catNames = consultData.categories.map(id => {
            const el = document.querySelector(`.wz-tag[data-value="${id}"]`);
            return el ? el.textContent.trim() : id;
        });
        document.getElementById('consultSummary').innerHTML = `
            <div class="wz-summary">
                <div class="row">
                    <div class="col-6"><strong>상담유형:</strong> ${wzEsc(typeLabel)}</div>
                    <div class="col-6"><strong>병원:</strong> ${wzEsc(consultData.hospitalName || '미선택')}</div>
                    <div class="col-12 mt-1"><strong>관심시술:</strong> ${catNames.length ? catNames.map(n => `<span class="badge bg-light text-dark me-1">${wzEsc(n)}</span>`).join('') : '없음'}</div>
                    <div class="col-12 mt-1"><strong>증상:</strong> ${wzEsc(document.getElementById('cs_symptoms').value || '미입력')}</div>
                    <div class="col-12 mt-1"><strong>사진:</strong> ${uploadedPhotos.length}장</div>
                </div>
            </div>`;
    }
};

async function submitConsult() {
    const btn = document.getElementById('btnConsultSubmit');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 접수 중...';

    const fd = new FormData();
    fd.append('action', 'submit_consultation');
    fd.append('hospital_id', consultData.hospitalId);
    fd.append('consult_type', consultData.type);
    fd.append('categories', JSON.stringify(consultData.categories));
    fd.append('symptoms', document.getElementById('cs_symptoms').value.trim());
    fd.append('patient_name', document.getElementById('cs_name').value.trim());
    fd.append('patient_phone', document.getElementById('cs_phone').value.trim());
    fd.append('patient_email', document.getElementById('cs_email').value.trim());
    fd.append('photo_urls', JSON.stringify(uploadedPhotos.map(p => p.data.substring(0, 100) + '...')));

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            wz.clearStorage();
            document.querySelectorAll('.wz-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('wzPanelDone').style.display = 'block';
            document.querySelector('.wz-steps').style.display = 'none';
        } else { alert(data.error); btn.disabled = false; btn.textContent = '상담 신청'; }
    } catch (e) { alert('오류: ' + e.message); btn.disabled = false; btn.textContent = '상담 신청'; }
}
</script>
</body>
</html>
