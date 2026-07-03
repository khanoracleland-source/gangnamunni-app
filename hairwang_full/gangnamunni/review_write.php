<?php
/**
 * 후기 작성 페이지 (프론트엔드)
 * - 로그인 필수 (Gnuboard 회원)
 * - hospital_reviews 테이블에 INSERT (review_status = 'pending' → 관리자 승인 후 공개)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
// 앱 모드 감지 (?app=1 파라미터)
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';
// my_reviews.php "작성가능" 목록에서 넘어온 경우 병원 미리 선택 (?hospital_id=)
$prefill_hospital_id = intval($_GET['hospital_id'] ?? 0);
require_once __DIR__ . '/includes/settings_helper.php';
require_once __DIR__ . '/includes/photo_upload_helper.php';

// 로그인 확인 (Gnuboard 회원 또는 관리자 세션)
$is_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$is_admin_logged = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

if (!$is_logged_in && !$is_admin_logged) {
    $return_url = urlencode($_SERVER['REQUEST_URI'] ?? 'review_write.php');
    header("Location: ../bbs/login.php?url=" . $return_url);
    exit;
}

if ($is_logged_in) {
    $user_name = $member['mb_nick'] ?? $member['mb_id'] ?? '';
    $user_id = $member['mb_id'] ?? '';
} else {
    // 관리자 세션으로 로그인한 경우
    $user_name = $_SESSION['ad_admin_name'] ?? $_SESSION['ad_admin_user'] ?? 'admin';
    $user_id = $_SESSION['ad_admin_user'] ?? 'admin';
}

// 수정 모드 확인 (review_detail.php "수정하기"에서 ?id= 로 진입, 본인 후기만 허용)
$edit_id = intval($_GET['id'] ?? 0);
$edit_review = null;
if ($edit_id > 0) {
    $er_stmt = mysqli_prepare($connect, "SELECT * FROM hospital_reviews WHERE id = ? AND user_id = ? AND is_active = 1");
    if ($er_stmt) {
        mysqli_stmt_bind_param($er_stmt, 'is', $edit_id, $user_id);
        mysqli_stmt_execute($er_stmt);
        $edit_review = mysqli_fetch_assoc(mysqli_stmt_get_result($er_stmt));
        mysqli_stmt_close($er_stmt);
    }
    if (!$edit_review) {
        header('Location: reviews.php');
        exit;
    }
}

// 병원 목록 로드
$hospitals = [];
$h_r = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY name ASC");
if ($h_r) while ($h = mysqli_fetch_assoc($h_r)) $hospitals[] = $h;

// 시술 카테고리
$categories = [];
$cat_r = mysqli_query($connect, "SELECT DISTINCT name FROM gangnamunni_categories WHERE is_active = 1 ORDER BY display_order ASC");
if ($cat_r) while ($c = mysqli_fetch_assoc($cat_r)) $categories[] = $c['name'];
if (empty($categories)) $categories = ['눈성형', '코성형', '리프팅', '피부관리', '보톡스/필러'];

$msg = '';
$msg_type = '';

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_edit_id = intval($_POST['edit_id'] ?? 0);
    $hospital_id = intval($_POST['hospital_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $rating = max(1, min(5, intval($_POST['rating'] ?? 5)));
    $treatments = trim($_POST['treatments'] ?? '');

    if ($hospital_id <= 0 || empty($title) || empty($content)) {
        $msg = term('business', '병원') . ', 제목, 내용을 모두 입력해주세요.';
        $msg_type = 'danger';
    } elseif ($post_edit_id > 0) {
        // 수정 (본인 후기만, 재승인 대기 상태로 전환)
        $stmt = mysqli_prepare($connect,
            "UPDATE hospital_reviews SET hospital_id=?, title=?, content=?, rating=?, treatments=?, review_status='pending', updated_at=NOW()
             WHERE id=? AND user_id=?"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issssis', $hospital_id, $title, $content, $rating, $treatments, $post_edit_id, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $review_id = $post_edit_id;

                if (!empty($_POST['treatment_ids'])) {
                    require_once __DIR__ . '/includes/treatment_review_scorer.php';
                    scoreReviewTreatments($connect, $review_id, $hospital_id, $content, array_map('intval', $_POST['treatment_ids']));
                }

                handle_photo_upload($connect, [
                    'type'      => 'reviews',
                    'record_id' => $review_id,
                    'table'     => 'hospital_reviews',
                    'max_files' => 5,
                ]);

                $msg = term('review', '후기') . '가 수정되었습니다. 관리자 승인 후 다시 공개됩니다.';
                $msg_type = 'success';
            } else {
                $msg = '수정 중 오류가 발생했습니다.';
                $msg_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $stmt = mysqli_prepare($connect,
            "INSERT INTO hospital_reviews (hospital_id, user_id, user_name, title, content, rating, treatments, review_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issssss', $hospital_id, $user_id, $user_name, $title, $content, $rating, $treatments);
            if (mysqli_stmt_execute($stmt)) {
                $review_id = mysqli_insert_id($connect);

                // 시술 감성 점수 분석
                if (!empty($_POST['treatment_ids'])) {
                    require_once __DIR__ . '/includes/treatment_review_scorer.php';
                    scoreReviewTreatments($connect, $review_id, $hospital_id, $content, array_map('intval', $_POST['treatment_ids']));
                }

                // 사진 업로드 처리 (후기는 최대 5장)
                handle_photo_upload($connect, [
                    'type'      => 'reviews',
                    'record_id' => $review_id,
                    'table'     => 'hospital_reviews',
                    'max_files' => 5,
                ]);

                $msg = term('review', '후기') . '가 등록되었습니다. 관리자 승인 후 공개됩니다.';
                $msg_type = 'success';

                // 관리자 알림
                $hosp_name_r = '';
                $hr = mysqli_query($connect, "SELECT name FROM gangnamunni_hospitals WHERE id={$hospital_id} LIMIT 1");
                if ($hr && $hrow = mysqli_fetch_assoc($hr)) $hosp_name_r = $hrow['name'];
                notifyAdmins($connect, 'review',
                    '새 후기 등록',
                    htmlspecialchars($hosp_name_r) . ' — ' . htmlspecialchars($user_name) . ' (★' . $rating . ')',
                    'admin_reviews.php',
                    ['review_id' => $review_id, 'hospital_id' => $hospital_id]
                );
            } else {
                $msg = '등록 중 오류가 발생했습니다.';
                $msg_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// 헤더
include __DIR__ . '/includes/header.php';
?>

<style>
.rw-container { max-width: 640px; margin: 0 auto; padding: 20px 16px 80px; }
.rw-title { font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 20px; }
.rw-form-group { margin-bottom: 16px; }
.rw-label { display: block; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: var(--bs-body-color, #333); margin-bottom: 6px; }
.rw-input, .rw-select, .rw-textarea {
    width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); box-sizing: border-box; transition: border-color 0.2s;
}
.rw-input:focus, .rw-select:focus, .rw-textarea:focus { border-color: var(--primary-color, #AE1932); outline: none; }
.rw-textarea { min-height: 150px; resize: vertical; }
.rw-rating { display: flex; gap: 4px; flex-direction: row-reverse; justify-content: flex-end; }
.rw-rating input { display: none; }
.rw-rating label { font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); color: #ddd; cursor: pointer; transition: color 0.2s; }
.rw-rating label:hover, .rw-rating label:hover ~ label,
.rw-rating input:checked ~ label { color: #FFB800; }
.rw-submit {
    width: 100%; padding: 14px; background: var(--primary-color, #AE1932); color: #fff;
    border: none; border-radius: 10px; font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; cursor: pointer;
}
.rw-submit:hover { opacity: 0.9; }
.rw-alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); }
.rw-alert-success { background: #d4edda; color: #155724; }
.rw-alert-danger { background: #f8d7da; color: #721c24; }
.rw-back { display: inline-block; color: var(--bs-secondary-color, #666); font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); text-decoration: none; margin-bottom: 12px; }
.rw-back:hover { color: var(--primary-color, #AE1932); }
</style>

<div class="rw-container">
    <a href="reviews.php" class="rw-back">&larr; <?php echo term('review', '후기'); ?> 목록으로</a>
    <div class="rw-title"><?php echo term('review', '후기'); ?> <?php echo $edit_review ? '수정' : '작성'; ?></div>

    <?php if ($msg): ?>
    <div class="rw-alert rw-alert-<?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php if ($msg_type === 'success'): ?>
    <a href="reviews.php" class="rw-submit" style="display:block; text-align:center; text-decoration:none;"><?php echo term('review', '후기'); ?> 목록으로 돌아가기</a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (isDevMode($connect)): ?>
        <?php renderDevModeButton('review'); ?>
    <?php endif; ?>

    <?php if ($msg_type !== 'success'): ?>
    <form method="post" action="" enctype="multipart/form-data">
        <?php if ($edit_review): ?>
        <input type="hidden" name="edit_id" value="<?php echo (int)$edit_review['id']; ?>">
        <?php endif; ?>
        <div class="rw-form-group">
            <label class="rw-label"><?php echo term('business', '병원'); ?> 선택 *</label>
            <select name="hospital_id" class="rw-select" required>
                <option value=""><?php echo term('business', '병원'); ?>을 선택하세요</option>
                <?php foreach ($hospitals as $h): ?>
                <option value="<?php echo $h['id']; ?>" <?php echo (intval($_POST['hospital_id'] ?? ($edit_review['hospital_id'] ?? $prefill_hospital_id)) === intval($h['id'])) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($h['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="rw-form-group">
            <label class="rw-label">받은 <?php echo term('service', '시술'); ?> (선택)</label>
            <?php $hospital_id = intval($_POST['hospital_id'] ?? ($edit_review['hospital_id'] ?? $prefill_hospital_id)); include __DIR__ . '/includes/treatment_select_ui.php'; ?>
        </div>

        <div class="rw-form-group">
            <label class="rw-label">평점 *</label>
            <div class="rw-rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo ($i == ($_POST['rating'] ?? ($edit_review['rating'] ?? 5))) ? 'checked' : ''; ?>>
                <label for="star<?php echo $i; ?>">&#9733;</label>
                <?php endfor; ?>
            </div>
        </div>

        <div class="rw-form-group">
            <label class="rw-label">제목 *</label>
            <input type="text" name="title" class="rw-input" placeholder="<?php echo term('review', '후기'); ?> 제목을 입력하세요" value="<?php echo htmlspecialchars($_POST['title'] ?? ($edit_review['title'] ?? '')); ?>" required maxlength="200">
        </div>

        <div class="rw-form-group">
            <label class="rw-label">내용 *</label>
            <textarea name="content" class="rw-textarea" placeholder="<?php echo term('service', '시술'); ?> 경험을 자세히 공유해주세요" required><?php echo htmlspecialchars($_POST['content'] ?? ($edit_review['content'] ?? '')); ?></textarea>
        </div>

        <!-- 사진 첨부 (후기는 최대 5장) -->
        <div class="rw-form-group">
        <?php
        $photo_config = [
            'prefix'     => 'review',
            'max_photos' => 5,
            'label'      => '📷 ' . term('service', '시술') . ' 전후 사진 (선택, 최대 5장)',
            'consent_text' => term('service', '시술') . ' ' . term('review', '후기') . ' 사진을 첨부합니다. 등록된 사진은 ' . term('review', '후기') . '와 함께 공개됩니다.',
            'show_medical_notice' => false,
        ];
        include __DIR__ . '/includes/photo_upload_ui.php';
        ?>
        </div>

        <!-- 커뮤니티 가이드라인 동의 (Apple Guideline 1.2) -->
        <div class="rw-form-group" style="margin-top:16px;">
            <label style="display:flex; align-items:flex-start; gap:8px; font-size:13px; cursor:pointer;">
                <input type="checkbox" name="agree_guidelines" id="agreeGuidelines" required style="margin-top:3px;">
                <span><a href="community_guidelines.php" target="_blank" style="color:#AE1932; text-decoration:underline;">커뮤니티 가이드라인</a>을 읽었으며, 허위·광고·욕설 등 부적절한 콘텐츠 작성 시 삭제 및 계정 제재에 동의합니다.</span>
            </label>
        </div>

        <button type="submit" class="rw-submit" id="reviewSubmitBtn"><?php echo term('review', '후기'); ?> <?php echo $edit_review ? '수정하기' : '등록하기'; ?></button>
        <p style="text-align:center; font-size:12px; color:#999; margin-top:8px;"><?php echo $edit_review ? '수정된' : '등록된'; ?> <?php echo term('review', '후기'); ?>는 관리자 승인 후 공개됩니다.</p>
    </form>
    <?php endif; ?>
</div>

<script>
// 후기 폼 제출 시 사진 blob을 FormData에 추가
(function() {
    var form = document.querySelector('.rw-container form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        var data = window._puData && window._puData['review'];
        if (data && data.length > 0) {
            e.preventDefault();
            var formData = new FormData(form);
            // puAppendToFormData로 동의 체크 + blob 추가
            if (!puAppendToFormData('review', formData)) return;
            // 중복 제출 방지
            var btn = document.getElementById('reviewSubmitBtn');
            if (btn) { btn.disabled = true; btn.textContent = '등록 중...'; }
            fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData
            }).then(function(r) { return r.text(); })
            .then(function(html) {
                document.open();
                document.write(html);
                document.close();
            }).catch(function(err) {
                alert('오류가 발생했습니다: ' + err.message);
                if (btn) { btn.disabled = false; btn.textContent = '<?php echo term('review', '후기'); ?> <?php echo $edit_review ? '수정하기' : '등록하기'; ?>'; }
            });
        }
        // 사진이 없으면 기본 폼 제출
    });
})();
</script>
<?php if (isDevMode($connect)) { echo getDevModeJS_review(); } ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
