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

$categories = ['일상', '시술/수술고민', '의사Q&A', '정보공유', '시술 추천', '병원 추천', '기타'];
$max_photos = 4;
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

// Preserve historical categories when editing an older post.
if ($is_edit && !in_array($edit_post['category'], $categories, true)) {
    $categories[] = $edit_post['category'];
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
    if (mb_strlen($post_content) < 10) $errors[] = '내용은 최소 10자 이상 입력해주세요.';
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

    // 새 사진 업로드 (최대 4장)
    if (!empty($_FILES['photos']['name'][0])) {
        $upload_dir = __DIR__ . '/uploads/community/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_count = count($_FILES['photos']['name']);
        $remaining_slots = max(0, $max_photos - count($photo_urls));
        if ($file_count > $remaining_slots) $errors[] = '사진은 최대 4장까지 첨부할 수 있습니다.';

        for ($i = 0; empty($errors) && $i < min($file_count, $remaining_slots); $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = '사진 업로드에 실패했습니다. 다시 선택해주세요.';
                continue;
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['photos']['tmp_name'][$i]);
            if (!in_array($mime, $allowed_types, true)) {
                $errors[] = '허용되지 않는 이미지 형식입니다. (JPG/PNG/GIF/WebP만 가능)';
                continue;
            }
            if ($_FILES['photos']['size'][$i] > $max_size) {
                $errors[] = '이미지 크기는 5MB 이하여야 합니다.';
                continue;
            }

            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
            $new_name = 'community_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target_path = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['photos']['tmp_name'][$i], $target_path)) {
                $photo_urls[] = '/hairwang/gangnamunni/uploads/community/' . $new_name;
            } else {
                $errors[] = '사진 저장에 실패했습니다. 다시 시도해주세요.';
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
$hide_global_nav = true;
$hide_bottom_nav = true;

$page_css = '
body { padding-bottom: 0; }
.main-content { padding: 0; }
.write-container { box-sizing: border-box; width: 100%; max-width: 640px; margin: 0 auto; padding: 0 20px 40px; color: var(--bs-body-color, #000); }
.write-header { position: relative; display: flex; align-items: center; justify-content: center; min-height: 62px; margin-bottom: 9px; }
.write-header .back-btn { position: absolute; left: -10px; width: 40px; height: 44px; display: flex; align-items: center; justify-content: center; text-decoration: none; color: inherit; }
.back-btn svg { width: 24px; height: 24px; }
.write-header h1 { font-size: 18px; font-weight: 600; margin: 0; padding: 0 30px; text-align: center; }
.write-categories { border: 0; border-bottom: 1px solid #d9d9d9; padding: 0 0 24px; margin: 0 0 29px; min-width: 0; }
.write-categories legend { font-size: 18px; font-weight: 600; margin-bottom: 17px; }
.category-options { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); column-gap: 8px; row-gap: 18px; }
.category-option { display: flex; align-items: center; gap: 10px; font-size: 18px; cursor: pointer; white-space: nowrap; }
.category-option input { appearance: none; width: 13px; height: 13px; flex: 0 0 13px; border: 1px solid #c9c9c9; border-radius: 0; margin: 0; background: var(--bs-body-bg, #fff); }
.category-option input:checked { background: #df0011; border-color: #df0011; }
.category-option input:checked::after { content: ""; display: block; width: 4px; height: 8px; border: solid white; border-width: 0 1px 1px 0; transform: translate(4px, 0) rotate(45deg); }
.write-container .form-group { margin-bottom: 28px; }
.write-container .form-group > label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 10px; }
.required-mark { color: #b81739; }
.write-container input[type="text"], .write-container textarea { box-sizing: border-box; width: 100%; padding: 13px 15px; border: 1px solid #d9d9d9; border-radius: 0; font: inherit; font-size: 14px; background: var(--bs-body-bg, #fff); color: inherit; }
.write-container input[type="text"] { height: 47px; }
.write-container textarea { display: block; min-height: 204px; resize: vertical; line-height: 1.6; }
.write-container input::placeholder, .write-container textarea::placeholder { color: #aaa; }
.write-container :focus-visible { outline: 2px solid #b81739; outline-offset: 3px; }
.char-count { text-align: right; font-size: 12px; color: #aaa; margin-top: 6px; }
.write-container .photo-group { margin-top: 35px; margin-bottom: 23px; }
.photo-upload { display: flex; flex-wrap: wrap; gap: 15px; }
.photo-preview, .photo-add-btn { box-sizing: border-box; width: 120px; height: 120px; }
.photo-preview { position: relative; overflow: hidden; }
.photo-preview img { width: 100%; height: 100%; object-fit: cover; }
.photo-remove { position: absolute; top: 5px; right: 5px; width: 24px; height: 24px; background: #0009; color: #fff; border: 0; border-radius: 50%; cursor: pointer; }
.photo-add-btn { border: 1px dashed #bbb; border-radius: 0; background: transparent; display: flex; flex-direction: column; gap: 8px; align-items: center; justify-content: center; cursor: pointer; color: #aaa; font: inherit; font-size: 12px; }
.photo-add-btn[hidden] { display: none; }
.photo-add-btn svg { width: 27px; height: 27px; color: #d4d4d4; }
.photo-add-btn:hover { border-color: #b81739; }
.write-policy { font-size: 12px; color: #999; margin: 0 0 32px; }
.write-container .btn-submit { width: 100%; min-height: 50px; padding: 12px; background: #b81739; color: #fff; border: 0; border-radius: 0; font-size: 18px; font-weight: 500; cursor: pointer; }
.write-container .btn-submit:hover { background: #a21432; }
.error-box { background: #fff0f0; border: 1px solid #ffd0d0; padding: 12px 16px; margin-bottom: 16px; color: #c00; font-size: 14px; }
@media (max-width: 480px) {
    .write-container { padding-left: 20px; padding-right: 20px; }
    .write-header h1 { font-size: 15px; }
    .write-categories legend { font-size: 16px; }
    .category-options { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px 12px; }
    .category-option { font-size: 16px; }
    .write-container textarea { min-height: 204px; }
    .write-container input[type="text"], .write-container textarea { font-size: 16px; }
}
';

$page_js = '';
include __DIR__ . '/includes/header.php';
?>

    <div class="write-container">
        <div class="write-header">
            <a href="<?php echo $is_edit ? 'detail_community.php?id='.$edit_id : 'community.php'; ?>" class="back-btn" aria-label="뒤로 가기"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 2 5 12l10 10"/></svg></a>
            <h1><?php echo $is_edit ? '글 수정' : '나만의 뷰티 경험을 공유해보세요'; ?></h1>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="error-box">
            <?php foreach ($errors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <fieldset class="write-categories">
                <legend>관심 있는 카테고리를 선택해주세요</legend>
                <div class="category-options">
                <?php $selected_category = $_POST['category'] ?? ($edit_post['category'] ?? '일상'); ?>
                <?php foreach ($categories as $cat): ?>
                    <label class="category-option">
                        <input type="radio" name="category" value="<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>" <?php echo $selected_category === $cat ? 'checked' : ''; ?> required>
                        <span><?php echo htmlspecialchars($cat); ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="form-group">
                <label for="postTitle">제목을 입력해주세요<span class="required-mark">*</span></label>
                <input id="postTitle" type="text" name="title" maxlength="200" required placeholder="어떤 이야기를 나누고 싶으신가요?"
                       value="<?php echo htmlspecialchars($_POST['title'] ?? ($edit_post['title'] ?? ''), ENT_QUOTES); ?>">
            </div>

            <div class="form-group">
                <label for="postContent">내용<span class="required-mark">*</span></label>
                <textarea id="postContent" name="content" required minlength="10" aria-describedby="contentCount" placeholder="시술 후기, 질문, 꿀팁 등 자유롭게 작성해보세요"><?php echo htmlspecialchars($_POST['content'] ?? ($edit_post['content'] ?? '')); ?></textarea>
                <div class="char-count" id="contentCount">0자 / 최소 10자</div>
            </div>

            <div class="form-group photo-group">
                <label for="photoInput">사진 (최대 4장, 각 5MB 이하)</label>
                <div class="photo-upload" id="photoUpload">
                    <?php
                    // 기존 사진 표시 (수정 모드)
                    if ($is_edit && !empty($edit_post['photos'])) {
                        $existing_photos = json_decode($edit_post['photos'], true);
                        if (is_array($existing_photos)) {
                            foreach ($existing_photos as $ep) {
                                echo '<div class="photo-preview" data-url="' . htmlspecialchars($ep) . '">';
                                echo '<img src="' . htmlspecialchars($ep) . '" alt="">';
                                echo '<button type="button" class="photo-remove" aria-label="사진 삭제" onclick="removeExistingPhoto(this)">&times;</button>';
                                echo '<input type="hidden" name="keep_photos[]" value="' . htmlspecialchars($ep) . '">';
                                echo '</div>';
                            }
                        }
                    }
                    ?>
                    <?php for ($slot = 0; $slot < 2; $slot++): ?>
                    <button type="button" class="photo-add-btn" aria-label="사진 추가" onclick="document.getElementById('photoInput').click()">
                        <svg viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="14" cy="14" r="12"/><path d="M14 6v16M6 14h16"/></svg>
                        <span><?php echo $slot === 0 ? '사진 추가' : '0 / 4'; ?></span>
                    </button>
                    <?php endfor; ?>
                    <input id="photoInput" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" hidden onchange="previewPhotos(this)">
                </div>
            </div>

            <p class="write-policy">운영정책을 위반한 게시물은 삭제될 수 있습니다</p>
            <button type="submit" class="btn-submit"><?php echo $is_edit ? '수정 완료' : '작성했어요'; ?></button>
        </form>
    </div>

<script>
const photoInput = document.getElementById('photoInput');
const photoContainer = document.getElementById('photoUpload');
let selectedPhotos = [];
function syncPhotoFiles() {
    const transfer = new DataTransfer();
    selectedPhotos.forEach(file => transfer.items.add(file));
    photoInput.files = transfer.files;
}
function previewPhotos(input) {
    const existing = photoContainer.querySelectorAll('.photo-preview[data-url]').length;
    const incoming = Array.from(input.files);
    if (incoming.length + selectedPhotos.length + existing > 4) {
        alert('사진은 최대 4장까지 첨부할 수 있습니다.');
        syncPhotoFiles();
        return;
    }
    if (incoming.some(file => file.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type))) {
        alert('JPG/PNG/GIF/WebP 사진을 각 5MB 이하로 선택해주세요.');
        syncPhotoFiles();
        return;
    }
    selectedPhotos.push(...incoming);
    renderNewPhotos();
}
function renderNewPhotos() {
    photoContainer.querySelectorAll('.photo-preview[data-new]').forEach(preview => {
        URL.revokeObjectURL(preview.querySelector('img').src);
        preview.remove();
    });
    const addButton = photoContainer.querySelector('.photo-add-btn');
    selectedPhotos.forEach((file, index) => {
        const preview = document.createElement('div');
        preview.className = 'photo-preview';
        preview.dataset.new = 'true';
        const image = document.createElement('img');
        image.src = URL.createObjectURL(file);
        image.alt = file.name;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'photo-remove';
        remove.setAttribute('aria-label', '사진 삭제');
        remove.textContent = '×';
        remove.onclick = () => { selectedPhotos.splice(index, 1); renderNewPhotos(); };
        preview.append(image, remove);
        photoContainer.insertBefore(preview, addButton);
    });
    syncPhotoFiles();
    updateAddBtn();
}
function removeExistingPhoto(btn) {
    const preview = btn.closest('.photo-preview');
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'delete_photos[]';
    hidden.value = preview.dataset.url;
    document.querySelector('.write-container form').appendChild(hidden);
    preview.remove();
    updateAddBtn();
}
function updateAddBtn() {
    const count = photoContainer.querySelectorAll('.photo-preview').length;
    photoContainer.querySelectorAll('.photo-add-btn').forEach((button, index) => {
        button.hidden = count >= 4 || (index === 1 && count > 0);
        button.querySelector('span').textContent = index === 0 && count === 0 ? '사진 추가' : count + ' / 4';
    });
}
const contentInput = document.getElementById('postContent');
function updateContentCount() {
    const length = Array.from(contentInput.value.trim()).length;
    document.getElementById('contentCount').textContent = length + '자 / 최소 10자';
    contentInput.setCustomValidity(length < 10 ? '내용은 최소 10자 이상 입력해주세요.' : '');
}
contentInput.addEventListener('input', updateContentCount);
updateContentCount();
updateAddBtn();
</script>

<?php
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
