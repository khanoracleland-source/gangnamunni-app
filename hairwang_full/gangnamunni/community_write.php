<?php
/**
 * ========================================
 * 커뮤니티 글쓰기 / 수정 페이지
 * ========================================
 * ?id=X : 수정 모드 (작성자 본인만 가능)
 * 없음  : 새 글 작성
 *
 * @file    gangnamunni/community_write.php
 * @created 2026-03-23
 */

require_once __DIR__ . '/includes/init.php';

// 로그인 필수
require_login($_SERVER['REQUEST_URI']);

$member_info = get_gangnamunni_member();
$mb_id = $member_info['mb_id'] ?? '';
$mb_name = $member_info['mb_nick'] ?? $member_info['mb_name'] ?? '익명';

$categories = ['시술후기', '일상', '질문', '정보공유', '기타'];
$edit_id = intval($_GET['id'] ?? 0);
$is_edit = ($edit_id > 0);
$edit_post = null;

// 수정 모드: 기존 글 로드 + 작성자 확인
if ($is_edit) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_community_posts WHERE id=? AND status='active'");
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_post = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$edit_post) {
        echo '<script>alert("게시글을 찾을 수 없습니다.");location.href="community.php";</script>';
        exit;
    }
    if ($edit_post['mb_id'] !== $mb_id) {
        echo '<script>alert("본인의 글만 수정할 수 있습니다.");history.back();</script>';
        exit;
    }
}

// POST 처리
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_category = trim($_POST['category'] ?? '');
    $post_title = trim($_POST['title'] ?? '');
    $post_content = trim($_POST['content'] ?? '');

    // 유효성 검사
    if (empty($post_title)) $errors[] = '제목을 입력해주세요.';
    if (mb_strlen($post_title) > 200) $errors[] = '제목은 200자 이내로 입력해주세요.';
    if (empty($post_content)) $errors[] = '내용을 입력해주세요.';
    if (!in_array($post_category, $categories)) $errors[] = '카테고리를 선택해주세요.';

    // 사진 업로드 처리
    $photo_urls = [];
    if ($is_edit && !empty($edit_post['photos'])) {
        $existing = json_decode($edit_post['photos'], true);
        if (is_array($existing)) $photo_urls = $existing;
    }

    // 삭제 요청된 기존 사진 제거
    if (!empty($_POST['delete_photos'])) {
        $delete_list = (array)$_POST['delete_photos'];
        $photo_urls = array_values(array_diff($photo_urls, $delete_list));
    }

    // 새 사진 업로드 (최대 5장)
    if (!empty($_FILES['photos']['name'][0])) {
        $upload_dir = __DIR__ . '/uploads/community/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_count = count($_FILES['photos']['name']);
        $remaining_slots = 5 - count($photo_urls);

        for ($i = 0; $i < min($file_count, $remaining_slots); $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['photos']['type'][$i], $allowed_types)) {
                $errors[] = '허용되지 않는 이미지 형식입니다. (JPG/PNG/GIF/WebP만 가능)';
                continue;
            }
            if ($_FILES['photos']['size'][$i] > $max_size) {
                $errors[] = '이미지 크기는 5MB 이하여야 합니다.';
                continue;
            }

            $ext = pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION);
            $new_name = 'community_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $target_path)) {
                $photo_urls[] = '/hairwang/gangnamunni/uploads/community/' . $new_name;
            }
        }
    }

    $photos_json = !empty($photo_urls) ? json_encode($photo_urls, JSON_UNESCAPED_UNICODE) : null;

    if (empty($errors)) {
        if ($is_edit) {
            // UPDATE
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_community_posts SET category=?, title=?, content=?, photos=? WHERE id=? AND mb_id=?");
            mysqli_stmt_bind_param($stmt, 'ssssis', $post_category, $post_title, $post_content, $photos_json, $edit_id, $mb_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = true;
                $redirect_id = $edit_id;
            } else {
                $errors[] = '수정에 실패했습니다.';
            }
            mysqli_stmt_close($stmt);
        } else {
            // INSERT
            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_community_posts (mb_id, author_name, category, title, content, photos) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssss', $mb_id, $mb_name, $post_category, $post_title, $post_content, $photos_json);
            if (mysqli_stmt_execute($stmt)) {
                $success = true;
                $redirect_id = mysqli_insert_id($connect);
            } else {
                $errors[] = '게시글 작성에 실패했습니다.';
            }
            mysqli_stmt_close($stmt);
        }

        if ($success) {
            echo '<script>location.href="detail_community.php?id=' . intval($redirect_id) . '";</script>';
            exit;
        }
    }
}

$page_title = $is_edit ? '글 수정' : '글쓰기';
$show_search = false;

$page_css = '
.write-container { max-width: 640px; margin: 0 auto; padding: 16px; }
.write-header { display: flex; align-items: center; gap: 12px; padding: 12px 0 16px; border-bottom: 1px solid var(--bs-border-color, #f0f0f0); margin-bottom: 20px; }
.write-header .back-btn { font-size: 24px; text-decoration: none; color: var(--bs-body-color, #000); }
.write-header h1 { font-size: 20px; font-weight: 700; margin: 0; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-size: 14px; font-weight: 600; color: var(--bs-body-color, #333); margin-bottom: 8px; }
.form-group select, .form-group input[type="text"], .form-group textarea {
    width: 100%; padding: 12px 16px; border: 1px solid var(--bs-border-color, #e0e0e0);
    border-radius: 10px; font-size: 15px; background: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #333); outline: none; transition: border-color 0.2s;
}
.form-group select:focus, .form-group input:focus, .form-group textarea:focus { border-color: #5B5FC7; }
.form-group textarea { min-height: 200px; resize: vertical; line-height: 1.6; }
.photo-upload { display: flex; flex-wrap: wrap; gap: 10px; }
.photo-upload .photo-preview { position: relative; width: 80px; height: 80px; border-radius: 10px; overflow: hidden; }
.photo-upload .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
.photo-upload .photo-preview .photo-remove { position: absolute; top: 2px; right: 2px; width: 22px; height: 22px; background: rgba(0,0,0,0.6); color: #fff; border: none; border-radius: 50%; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1; }
.photo-add-btn { width: 80px; height: 80px; border: 2px dashed var(--bs-border-color, #ddd); border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: var(--bs-secondary-color, #aaa); font-size: 12px; transition: border-color 0.2s; }
.photo-add-btn:hover { border-color: #5B5FC7; color: #5B5FC7; }
.photo-add-btn i { font-size: 24px; margin-bottom: 4px; }
.btn-submit { width: 100%; padding: 14px; background: #5B5FC7; color: #fff; border: none; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
.btn-submit:hover { background: #4A4EB5; }
.error-box { background: #FFF0F0; border: 1px solid #FFD0D0; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; color: #CC0000; font-size: 14px; }
.char-count { text-align: right; font-size: 12px; color: var(--bs-secondary-color, #aaa); margin-top: 4px; }
';

$page_js = '';
include __DIR__ . '/includes/header.php';
?>

    <div class="write-container">
        <div class="write-header">
            <a href="<?php echo $is_edit ? 'detail_community.php?id='.$edit_id : 'community.php'; ?>" class="back-btn">&larr;</a>
            <h1><?php echo $is_edit ? '글 수정' : '글쓰기'; ?></h1>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>카테고리</label>
                <select name="category" required>
                    <option value="">선택해주세요</option>
                    <?php foreach ($categories as $cat):
                        $sel = '';
                        if ($is_edit && ($edit_post['category'] ?? '') === $cat) $sel = ' selected';
                        elseif (isset($_POST['category']) && $_POST['category'] === $cat) $sel = ' selected';
                    ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>제목</label>
                <input type="text" name="title" maxlength="200" required placeholder="제목을 입력해주세요"
                       value="<?php echo htmlspecialchars($is_edit ? ($edit_post['title'] ?? '') : ($_POST['title'] ?? '')); ?>"
                       oninput="document.getElementById('titleCount').textContent=this.value.length+'/200'">
                <div class="char-count" id="titleCount"><?php echo mb_strlen($is_edit ? ($edit_post['title'] ?? '') : ($_POST['title'] ?? '')); ?>/200</div>
            </div>

            <div class="form-group">
                <label>내용</label>
                <textarea name="content" required placeholder="내용을 입력해주세요"><?php echo htmlspecialchars($is_edit ? ($edit_post['content'] ?? '') : ($_POST['content'] ?? '')); ?></textarea>
            </div>

            <div class="form-group">
                <label>사진 (최대 5장, 각 5MB 이하)</label>
                <div class="photo-upload" id="photoUpload">
                    <?php
                    // 기존 사진 표시 (수정 모드)
                    if ($is_edit && !empty($edit_post['photos'])) {
                        $existing_photos = json_decode($edit_post['photos'], true);
                        if (is_array($existing_photos)) {
                            foreach ($existing_photos as $ep) {
                                echo '<div class="photo-preview" data-url="' . htmlspecialchars($ep) . '">';
                                echo '<img src="' . htmlspecialchars($ep) . '" alt="">';
                                echo '<button type="button" class="photo-remove" onclick="removeExistingPhoto(this)">&times;</button>';
                                echo '<input type="hidden" name="keep_photos[]" value="' . htmlspecialchars($ep) . '">';
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                    <label class="photo-add-btn" id="photoAddBtn">
                        <i class="bi bi-camera"></i>
                        <span>추가</span>
                        <input type="file" name="photos[]" multiple accept="image/*" style="display:none" onchange="previewPhotos(this)">
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-check-lg"></i> <?php echo $is_edit ? '수정 완료' : '게시글 작성'; ?>
            </button>
        </form>
    </div>

<script>
function previewPhotos(input) {
    var container = document.getElementById('photoUpload');
    var addBtn = document.getElementById('photoAddBtn');
    var existing = container.querySelectorAll('.photo-preview').length;
    var maxPhotos = 5;

    if (input.files) {
        var remaining = maxPhotos - existing;
        for (var i = 0; i < Math.min(input.files.length, remaining); i++) {
            var file = input.files[i];
            if (file.size > 5 * 1024 * 1024) {
                alert('이미지 크기는 5MB 이하여야 합니다.');
                continue;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                var div = document.createElement('div');
                div.className = 'photo-preview';
                div.innerHTML = '<img src="' + e.target.result + '" alt="">' +
                    '<button type="button" class="photo-remove" onclick="removeNewPhoto(this)">&times;</button>';
                container.insertBefore(div, addBtn);
                updateAddBtn();
            };
            reader.readAsDataURL(file);
        }
    }
}

function removeExistingPhoto(btn) {
    var preview = btn.closest('.photo-preview');
    var url = preview.getAttribute('data-url');
    // 삭제할 사진을 hidden input으로 추가
    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'delete_photos[]';
    hidden.value = url;
    document.querySelector('form').appendChild(hidden);
    preview.remove();
    updateAddBtn();
}

function removeNewPhoto(btn) {
    btn.closest('.photo-preview').remove();
    updateAddBtn();
}

function updateAddBtn() {
    var container = document.getElementById('photoUpload');
    var addBtn = document.getElementById('photoAddBtn');
    var count = container.querySelectorAll('.photo-preview').length;
    addBtn.style.display = (count >= 5) ? 'none' : '';
}
</script>

<?php
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
