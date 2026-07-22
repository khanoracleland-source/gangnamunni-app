<?php
/**
 * 후기 작성 마법사 - 4단계 (병원+시술 선택 → 별점 평가 → 내용+사진 → 미리보기+제출)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// 로그인 확인
$member = $_SESSION['member'] ?? null;
if (!$member) { header('Location: auth/login.php?url=' . urlencode($_SERVER['REQUEST_URI'])); exit; }

$hospital_id = intval($_GET['hospital_id'] ?? 0);

// 병원 목록 (드롭다운용)
$hospitals = [];
$r = mysqli_query($connect, "SELECT id, name, address FROM gangnamunni_hospitals WHERE status='active' ORDER BY name");
if ($r) while ($row = mysqli_fetch_assoc($r)) $hospitals[] = $row;

// 카테고리 목록 (시술 태그)
$categories = [];
$r = mysqli_query($connect, "SELECT id, name, icon FROM gangnamunni_categories WHERE is_active=1 ORDER BY display_order");
if ($r) while ($row = mysqli_fetch_assoc($r)) $categories[] = $row;

// 이미지 업로드 AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_image') {
    header('Content-Type: application/json');
    $uploadDir = __DIR__ . '/uploads/reviews/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
    if (empty($_FILES['image'])) { echo json_encode(['success'=>false,'error'=>'파일 없음']); exit; }
    $file = $_FILES['image'];
    if ($file['size'] > 10*1024*1024) { echo json_encode(['success'=>false,'error'=>'10MB 초과']); exit; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) { echo json_encode(['success'=>false,'error'=>'허용되지 않는 형식']); exit; }
    $newName = 'rv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        echo json_encode(['success'=>true,'url'=>'uploads/reviews/'.$newName,'filename'=>$newName]);
    } else {
        echo json_encode(['success'=>false,'error'=>'업로드 실패']);
    }
    exit;
}

// 후기 제출 AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_review') {
    header('Content-Type: application/json');
    try {
        $hId = intval($_POST['hospital_id'] ?? 0);
        $title = $esc(trim($_POST['title'] ?? ''));
        $content = $esc(trim($_POST['content'] ?? ''));
        $procedure = $esc(trim($_POST['procedure_name'] ?? ''));
        $ratingResult = intval($_POST['rating_result'] ?? 0);
        $ratingConsult = intval($_POST['rating_consult'] ?? 0);
        $ratingFacility = intval($_POST['rating_facility'] ?? 0);
        $ratingPrice = intval($_POST['rating_price'] ?? 0);
        $ratingKindness = intval($_POST['rating_kindness'] ?? 0);
        $ratingAvg = round(($ratingResult + $ratingConsult + $ratingFacility + $ratingPrice + $ratingKindness) / 5, 1);
        $images = json_decode($_POST['images'] ?? '[]', true) ?: [];

        if (!$hId || !$title || !$content) throw new Exception('필수 정보를 입력해주세요.');

        // 테이블 생성
        mysqli_query($connect, "CREATE TABLE IF NOT EXISTS hospital_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hospital_id INT NOT NULL,
            user_id VARCHAR(50),
            user_name VARCHAR(100),
            title VARCHAR(255) DEFAULT '',
            content TEXT,
            editor_content TEXT,
            editor_type VARCHAR(20) DEFAULT 'text',
            procedure_name VARCHAR(200) DEFAULT '',
            rating DECIMAL(2,1) DEFAULT 0,
            rating_result TINYINT DEFAULT 0,
            rating_consult TINYINT DEFAULT 0,
            rating_facility TINYINT DEFAULT 0,
            rating_price TINYINT DEFAULT 0,
            rating_kindness TINYINT DEFAULT 0,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            views INT DEFAULT 0,
            likes INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX(hospital_id), INDEX(status), INDEX(user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        mysqli_query($connect, "CREATE TABLE IF NOT EXISTS review_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            image_url VARCHAR(500),
            image_type ENUM('before','after','general') DEFAULT 'general',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX(review_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $userId = $esc($member['mb_id'] ?? '');
        $userName = $esc($member['mb_name'] ?? $member['mb_nick'] ?? '');

        $sql = "INSERT INTO hospital_reviews (hospital_id, user_id, user_name, title, content, procedure_name, rating, rating_result, rating_consult, rating_facility, rating_price, rating_kindness, status, created_at)
                VALUES ({$hId}, '{$userId}', '{$userName}', '{$title}', '{$content}', '{$procedure}', {$ratingAvg}, {$ratingResult}, {$ratingConsult}, {$ratingFacility}, {$ratingPrice}, {$ratingKindness}, 'pending', NOW())";
        if (!mysqli_query($connect, $sql)) throw new Exception(mysqli_error($connect));
        $reviewId = mysqli_insert_id($connect);

        // 이미지 저장
        foreach ($images as $i => $img) {
            $imgUrl = $esc($img['url'] ?? '');
            $imgType = $esc($img['type'] ?? 'general');
            if ($imgUrl) {
                mysqli_query($connect, "INSERT INTO review_images (review_id, image_url, image_type, sort_order) VALUES ({$reviewId}, '{$imgUrl}', '{$imgType}', {$i})");
            }
        }

        echo json_encode(['success' => true, 'review_id' => $reviewId, 'message' => '후기가 등록되었습니다. 관리자 승인 후 공개됩니다.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

$page_title = '후기 작성';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> | 서울미녀</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="includes/wizard_base.css" rel="stylesheet">
<style>
body { background: var(--bs-body-bg); padding-bottom: 80px; }
.rating-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--bs-border-color); }
.rating-row:last-child { border-bottom: none; }
.rating-label { font-weight: 600; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); min-width: 80px; }
.rating-value { font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; color: #ffc107; min-width: 40px; text-align: right; }
.photo-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.photo-item { position: relative; border-radius: 10px; overflow: hidden; aspect-ratio: 1; background: var(--bs-secondary-bg); }
.photo-item img { width: 100%; height: 100%; object-fit: cover; }
.photo-item .photo-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: rgba(0,0,0,.6); color: #fff; border: none; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; display: flex; align-items: center; justify-content: center; }
.photo-item .photo-type { position: absolute; bottom: 0; left: 0; right: 0; padding: 2px 6px; background: rgba(0,0,0,.5); color: #fff; font-size: calc(10px * var(--fs-scale,1) * var(--fs-page,1)); text-align: center; }
.add-photo { display: flex; flex-direction: column; align-items: center; justify-content: center; border: 2px dashed var(--bs-border-color); border-radius: 10px; cursor: pointer; aspect-ratio: 1; transition: all .2s; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--bs-secondary-color); }
.add-photo:hover { border-color: #ff6b35; color: #ff6b35; }
.add-photo i { font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 4px; }
.preview-card { border-radius: 14px; overflow: hidden; border: 1px solid var(--bs-border-color); }
.preview-header { padding: 14px 16px; border-bottom: 1px solid var(--bs-border-color); }
.preview-body { padding: 16px; }
.preview-images { display: flex; gap: 6px; overflow-x: auto; padding: 8px 0; }
.preview-images img { width: 100px; height: 100px; object-fit: cover; border-radius: 8px; flex-shrink: 0; }
</style>
</head>
<body>

<div class="container-fluid" style="max-width:640px">
    <div class="py-3 px-2">
        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> 후기 작성</h5>
        <p class="text-muted small mb-0">시술 경험을 공유해주세요</p>
    </div>

    <!-- 스텝 -->
    <div class="wz-steps">
        <div class="wz-step active" data-step="1"><div class="wz-circle">1</div><span class="wz-label">병원/시술</span></div>
        <div class="wz-line"></div>
        <div class="wz-step" data-step="2"><div class="wz-circle">2</div><span class="wz-label">별점</span></div>
        <div class="wz-line"></div>
        <div class="wz-step" data-step="3"><div class="wz-circle">3</div><span class="wz-label">내용</span></div>
        <div class="wz-line"></div>
        <div class="wz-step" data-step="4"><div class="wz-circle">4</div><span class="wz-label">확인</span></div>
    </div>

    <div class="wz-body">

        <!-- Step 1: 병원 + 시술 선택 -->
        <div class="wz-panel active" id="wzPanel1">
            <h6 class="fw-bold mb-3"><i class="bi bi-building"></i> 병원 선택</h6>
            <?php if ($hospital_id): ?>
                <?php
                $h = null;
                foreach ($hospitals as $hp) if ($hp['id'] == $hospital_id) { $h = $hp; break; }
                ?>
                <?php if ($h): ?>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body py-2">
                        <div class="fw-bold"><?= htmlspecialchars($h['name']) ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($h['address'] ?? '') ?></div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="wz-search-wrap mb-3">
                    <input type="text" class="form-control" id="hospitalSearch" placeholder="병원 이름 검색..." autocomplete="off">
                    <div class="wz-dropdown" id="hospitalDropdown">
                        <?php foreach ($hospitals as $h): ?>
                        <div class="wz-dropdown-item" data-value="<?= $h['id'] ?>" data-name="<?= htmlspecialchars($h['name']) ?>">
                            <i class="bi bi-building"></i>
                            <div>
                                <div class="fw-bold" style="font-size:13px"><?= htmlspecialchars($h['name']) ?></div>
                                <div style="font-size:11px;color:var(--bs-secondary-color)"><?= htmlspecialchars(mb_substr($h['address'] ?? '', 0, 30)) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <h6 class="fw-bold mb-2"><i class="bi bi-tag"></i> 시술 카테고리 <span class="text-muted fw-normal small">(최대 3개)</span></h6>
            <div id="catTags" class="mb-3">
                <?php foreach ($categories as $cat): ?>
                <span class="wz-tag" data-value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>"><?= $cat['icon'] ?: '🏥' ?> <?= htmlspecialchars($cat['name']) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold">시술명 <span class="text-muted fw-normal">(직접 입력)</span></label>
                <input type="text" class="form-control" id="procedureName" placeholder="예: 눈매교정, 코필러, 레이저토닝...">
            </div>

            <div class="wz-actions">
                <a href="javascript:history.back()" class="btn-wz-prev"><i class="bi bi-arrow-left"></i> 돌아가기</a>
                <button class="btn-wz-next" onclick="wz.next()">별점 평가 <i class="bi bi-arrow-right"></i></button>
            </div>
        </div>

        <!-- Step 2: 5개 카테고리 별점 -->
        <div class="wz-panel" id="wzPanel2">
            <h6 class="fw-bold mb-3"><i class="bi bi-star"></i> 별점 평가</h6>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="rating-row">
                        <div class="rating-label">시술 결과</div>
                        <div class="wz-stars" id="starsResult"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
                        <div class="rating-value" id="valResult">0</div>
                    </div>
                    <div class="rating-row">
                        <div class="rating-label">상담</div>
                        <div class="wz-stars" id="starsConsult"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
                        <div class="rating-value" id="valConsult">0</div>
                    </div>
                    <div class="rating-row">
                        <div class="rating-label">시설</div>
                        <div class="wz-stars" id="starsFacility"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
                        <div class="rating-value" id="valFacility">0</div>
                    </div>
                    <div class="rating-row">
                        <div class="rating-label">가격</div>
                        <div class="wz-stars" id="starsPrice"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
                        <div class="rating-value" id="valPrice">0</div>
                    </div>
                    <div class="rating-row">
                        <div class="rating-label">친절도</div>
                        <div class="wz-stars" id="starsKindness"><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span><span class="star">&#9733;</span></div>
                        <div class="rating-value" id="valKindness">0</div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-3">
                <span class="text-muted small">평균</span>
                <span class="fw-bold fs-4" style="color:#ffc107" id="ratingAvg">0.0</span>
            </div>

            <div class="wz-actions">
                <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
                <button class="btn-wz-next" onclick="wz.next()">내용 작성 <i class="bi bi-arrow-right"></i></button>
            </div>
        </div>

        <!-- Step 3: 후기 내용 + 사진 -->
        <div class="wz-panel" id="wzPanel3">
            <h6 class="fw-bold mb-3"><i class="bi bi-chat-left-text"></i> 후기 내용</h6>
            <div class="mb-3">
                <label class="form-label small fw-bold">제목 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="rvTitle" placeholder="후기 제목을 입력해주세요" maxlength="100">
                <div class="form-text"><span id="titleCount">0</span>/100</div>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-bold">내용 <span class="text-danger">*</span></label>
                <textarea class="form-control" id="rvContent" rows="6" placeholder="시술 경험을 자세히 공유해주세요.&#10;- 시술 전 상담은 어땠나요?&#10;- 시술 과정은 어땠나요?&#10;- 시술 후 회복 과정은?" maxlength="2000"></textarea>
                <div class="form-text"><span id="contentCount">0</span>/2000</div>
            </div>

            <h6 class="fw-bold mb-2"><i class="bi bi-images"></i> 사진 첨부 <span class="text-muted fw-normal small">(최대 6장, 10MB)</span></h6>
            <div class="photo-grid mb-3" id="photoGrid">
                <div class="add-photo" onclick="document.getElementById('photoInput').click()">
                    <i class="bi bi-plus-circle"></i>
                    <span>사진 추가</span>
                </div>
            </div>
            <input type="file" id="photoInput" accept="image/*" multiple style="display:none">

            <div class="wz-actions">
                <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 이전</button>
                <button class="btn-wz-next" onclick="wz.next()">미리보기 <i class="bi bi-arrow-right"></i></button>
            </div>
        </div>

        <!-- Step 4: 미리보기 + 제출 -->
        <div class="wz-panel" id="wzPanel4">
            <h6 class="fw-bold mb-3"><i class="bi bi-eye"></i> 미리보기</h6>
            <div id="reviewPreview"></div>
            <div class="alert alert-info small mt-3">
                <i class="bi bi-info-circle"></i> 후기는 관리자 승인 후 공개됩니다.
            </div>
            <div class="wz-actions">
                <button class="btn-wz-prev" onclick="wz.prev()"><i class="bi bi-arrow-left"></i> 수정</button>
                <button class="btn-wz-submit" id="btnReviewSubmit" onclick="submitReview()"><i class="bi bi-send"></i> 후기 등록</button>
            </div>
        </div>

        <!-- 완료 -->
        <div class="wz-panel" id="wzPanelDone" style="display:none;text-align:center;padding:40px 0">
            <div style="font-size:64px;color:#28a745">&#10004;&#65039;</div>
            <h4 class="mt-3">후기가 등록되었습니다!</h4>
            <p class="text-muted">관리자 승인 후 공개됩니다.</p>
            <div class="mt-4">
                <a href="index.php" class="btn btn-outline-secondary me-2">홈으로</a>
                <a href="my_reviews.php" class="btn btn-primary">내 후기 보기</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/bottom_navigation.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="includes/wizard_base.js"></script>
<script>
const wz = new WizardBase(4, { storageKey: 'review_wz' });
const reviewData = { hospitalId: <?= $hospital_id ?: 0 ?>, hospitalName: '', categories: [], procedure: '', ratings: {result:0,consult:0,facility:0,price:0,kindness:0}, photos: [] };

// Step 1: 병원 검색
<?php if (!$hospital_id): ?>
initSearchDropdown(
    document.getElementById('hospitalSearch'),
    document.getElementById('hospitalDropdown'),
    { onSelect: (item) => {
        reviewData.hospitalId = parseInt(item.dataset.value);
        reviewData.hospitalName = item.dataset.name;
        document.getElementById('hospitalSearch').value = item.dataset.name;
    }}
);
<?php else: ?>
reviewData.hospitalName = <?= json_encode($h['name'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
<?php endif; ?>

// 카테고리 태그
const catSel = initTagSelect(document.getElementById('catTags'), {
    max: 3,
    onChange: (vals) => { reviewData.categories = vals; }
});

// Step 2: 별점
const ratings = {};
['Result','Consult','Facility','Price','Kindness'].forEach(key => {
    const k = key.toLowerCase();
    ratings[k] = initStarRating(document.getElementById('stars'+key), (val) => {
        reviewData.ratings[k] = val;
        document.getElementById('val'+key).textContent = val;
        updateAvg();
    });
});

function updateAvg() {
    const r = reviewData.ratings;
    const vals = [r.result, r.consult, r.facility, r.price, r.kindness];
    const filled = vals.filter(v => v > 0);
    const avg = filled.length ? (vals.reduce((a,b)=>a+b,0) / 5).toFixed(1) : '0.0';
    document.getElementById('ratingAvg').textContent = avg;
}

// Step 3: 글자수
document.getElementById('rvTitle').addEventListener('input', function() {
    document.getElementById('titleCount').textContent = this.value.length;
});
document.getElementById('rvContent').addEventListener('input', function() {
    document.getElementById('contentCount').textContent = this.value.length;
});

// 사진 업로드
document.getElementById('photoInput').addEventListener('change', async function() {
    const files = [...this.files];
    for (const file of files) {
        if (reviewData.photos.length >= 6) { alert('최대 6장까지 가능합니다.'); break; }
        if (file.size > 10*1024*1024) { alert(file.name + ': 10MB 초과'); continue; }

        const fd = new FormData();
        fd.append('action', 'upload_image');
        fd.append('image', file);
        try {
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                reviewData.photos.push({ url: data.url, type: 'general', filename: data.filename });
                renderPhotos();
            } else {
                alert(data.error || '업로드 실패');
            }
        } catch(e) { alert('네트워크 오류'); }
    }
    this.value = '';
});

function renderPhotos() {
    const grid = document.getElementById('photoGrid');
    grid.innerHTML = '';
    reviewData.photos.forEach((p, i) => {
        const types = ['general','before','after'];
        const typeLabel = { general:'일반', before:'전', after:'후' };
        grid.innerHTML += `<div class="photo-item">
            <img src="${wzEsc(p.url)}" alt="">
            <button class="photo-remove" onclick="removePhoto(${i})"><i class="bi bi-x"></i></button>
            <div class="photo-type" onclick="cycleType(${i})" style="cursor:pointer">${typeLabel[p.type]}</div>
        </div>`;
    });
    if (reviewData.photos.length < 6) {
        grid.innerHTML += `<div class="add-photo" onclick="document.getElementById('photoInput').click()"><i class="bi bi-plus-circle"></i><span>사진 추가</span></div>`;
    }
}

function removePhoto(i) { reviewData.photos.splice(i, 1); renderPhotos(); }
function cycleType(i) {
    const types = ['general','before','after'];
    const cur = types.indexOf(reviewData.photos[i].type);
    reviewData.photos[i].type = types[(cur+1)%3];
    renderPhotos();
}

// 유효성
wz.setValidator(1, () => {
    if (!reviewData.hospitalId) { alert('병원을 선택해주세요.'); return false; }
    reviewData.procedure = document.getElementById('procedureName').value.trim();
    return true;
});
wz.setValidator(2, () => {
    const r = reviewData.ratings;
    if (!r.result || !r.consult || !r.facility || !r.price || !r.kindness) {
        alert('모든 항목의 별점을 선택해주세요.'); return false;
    }
    return true;
});
wz.setValidator(3, () => {
    if (!document.getElementById('rvTitle').value.trim()) { alert('제목을 입력해주세요.'); return false; }
    if (!document.getElementById('rvContent').value.trim()) { alert('내용을 입력해주세요.'); return false; }
    return true;
});

// Step 4: 미리보기
wz.onStepChange = (step) => { if (step === 4) renderPreview(); };

function renderPreview() {
    const title = document.getElementById('rvTitle').value;
    const content = document.getElementById('rvContent').value;
    const r = reviewData.ratings;
    const avg = ((r.result+r.consult+r.facility+r.price+r.kindness)/5).toFixed(1);
    const starsHtml = (val) => '&#9733;'.repeat(val) + '&#9734;'.repeat(5-val);

    let imagesHtml = '';
    if (reviewData.photos.length > 0) {
        imagesHtml = '<div class="preview-images">' + reviewData.photos.map(p => `<img src="${wzEsc(p.url)}" alt="">`).join('') + '</div>';
    }

    document.getElementById('reviewPreview').innerHTML = `
        <div class="preview-card">
            <div class="preview-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">${wzEsc(reviewData.hospitalName)}</div>
                        <div class="small text-muted">${wzEsc(reviewData.procedure || '시술명 미입력')}</div>
                    </div>
                    <div class="text-end">
                        <div style="color:#ffc107;font-size:20px;font-weight:700">${avg}</div>
                        <div class="small text-muted">${wzEsc(<?= json_encode($member['mb_nick'] ?? $member['mb_name'] ?? '사용자', JSON_UNESCAPED_UNICODE) ?>)}</div>
                    </div>
                </div>
            </div>
            <div class="preview-body">
                <h6 class="fw-bold mb-2">${wzEsc(title)}</h6>
                <p class="small mb-2" style="white-space:pre-wrap">${wzEsc(content)}</p>
                ${imagesHtml}
                <div class="mt-3 pt-2 border-top small">
                    <div class="row g-1">
                        <div class="col-6">시술결과 <span style="color:#ffc107">${starsHtml(r.result)}</span></div>
                        <div class="col-6">상담 <span style="color:#ffc107">${starsHtml(r.consult)}</span></div>
                        <div class="col-6">시설 <span style="color:#ffc107">${starsHtml(r.facility)}</span></div>
                        <div class="col-6">가격 <span style="color:#ffc107">${starsHtml(r.price)}</span></div>
                        <div class="col-6">친절도 <span style="color:#ffc107">${starsHtml(r.kindness)}</span></div>
                    </div>
                </div>
            </div>
        </div>`;
}

// 제출
async function submitReview() {
    const btn = document.getElementById('btnReviewSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 등록 중...';

    const fd = new FormData();
    fd.append('action', 'submit_review');
    fd.append('hospital_id', reviewData.hospitalId);
    fd.append('title', document.getElementById('rvTitle').value.trim());
    fd.append('content', document.getElementById('rvContent').value.trim());
    fd.append('procedure_name', reviewData.procedure);
    fd.append('rating_result', reviewData.ratings.result);
    fd.append('rating_consult', reviewData.ratings.consult);
    fd.append('rating_facility', reviewData.ratings.facility);
    fd.append('rating_price', reviewData.ratings.price);
    fd.append('rating_kindness', reviewData.ratings.kindness);
    fd.append('images', JSON.stringify(reviewData.photos));

    try {
        const res = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            wz.clearStorage();
            document.querySelectorAll('.wz-panel').forEach(p => p.classList.remove('active'));
            document.getElementById('wzPanelDone').style.display = 'block';
            document.querySelector('.wz-steps').style.display = 'none';
        } else {
            alert(data.error || '오류가 발생했습니다.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send"></i> 후기 등록';
        }
    } catch (e) {
        alert('네트워크 오류: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> 후기 등록';
    }
}
</script>
</body>
</html>
