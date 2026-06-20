<?php
/**
 * 게시판 개별 수정
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

$board_key = isset($_GET['board_key']) ? $_GET['board_key'] : '';

if (!$board_key) {
    alert('게시판을 선택해주세요.', './boards.php');
}

// 게시판 정보 가져오기
$sql = " SELECT * FROM {$addon_app_admin_table_boards} WHERE board_key = '{$board_key}' ";
$board = sql_fetch($sql);

if (!$board) {
    alert('게시판을 찾을 수 없습니다.', './boards.php');
}

// 게시판 정보 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    check_admin_token();

    $board_name = isset($_POST['board_name']) ? trim($_POST['board_name']) : '';
    $board_description = isset($_POST['board_description']) ? trim($_POST['board_description']) : '';
    $icon_class = isset($_POST['icon_class']) ? trim($_POST['icon_class']) : 'fa-th-large';
    $is_visible_pc = isset($_POST['is_visible_pc']) ? 1 : 0;
    $is_visible_mobile = isset($_POST['is_visible_mobile']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    if (empty($board_name)) {
        alert('게시판 이름을 입력해주세요.');
    }

    $sql = " UPDATE {$addon_app_admin_table_boards}
             SET board_name = '" . sql_real_escape_string($board_name) . "',
                 board_description = '" . sql_real_escape_string($board_description) . "',
                 icon_class = '{$icon_class}',
                 is_visible_pc = {$is_visible_pc},
                 is_visible_mobile = {$is_visible_mobile},
                 sort_order = {$sort_order},
                 updated_at = NOW()
             WHERE board_key = '{$board_key}' ";

    if (sql_query($sql)) {
        addon_app_admin_log('board_edit', $board_key, '게시판 수정: ' . $board_name);
        alert('게시판이 수정되었습니다.', './boards.php');
    } else {
        alert('저장 중 오류가 발생했습니다.');
    }
}

// 레이아웃 헤더
$g5['title'] = '게시판 수정 - ' . $board['board_name'];
include_once('./layout_header.php');

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '게시판 수정',
    'board_key' => $board_key,
    'board' => array(
        'name' => $board['board_name'],
        'description' => $board['board_description'],
        'icon_class' => $board['icon_class'],
        'is_visible_pc' => $board['is_visible_pc'],
        'is_visible_mobile' => $board['is_visible_mobile'],
        'sort_order' => $board['sort_order']
    )
);

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">

<div class="addon-app-admin-wrap">

    <div class="addon-header">
        <div class="addon-container">
            <div class="addon-header-inner">
                <h1 class="addon-logo">
                    <i class="fa fa-edit"></i> 게시판 수정
                </h1>
                <div class="addon-header-right">
                    <a href="./boards.php" class="addon-btn addon-btn-sm addon-btn-secondary">
                        <i class="fa fa-arrow-left"></i> 목록으로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="addon-container">

        <!-- 현재 정보 카드 -->
        <div class="addon-section addon-mt-30">
            <div class="current-board-card">
                <div class="current-board-icon">
                    <i class="fa <?php echo htmlspecialchars($board['icon_class']); ?>"></i>
                </div>
                <div class="current-board-info">
                    <h2><?php echo htmlspecialchars($board['board_name']); ?></h2>
                    <p><?php echo htmlspecialchars($board['board_description']); ?></p>
                    <div class="current-board-meta">
                        <span class="badge <?php echo $board['is_visible_pc'] ? 'badge-success' : 'badge-secondary'; ?>">
                            PC <?php echo $board['is_visible_pc'] ? '표시' : '숨김'; ?>
                        </span>
                        <span class="badge <?php echo $board['is_visible_mobile'] ? 'badge-success' : 'badge-secondary'; ?>">
                            모바일 <?php echo $board['is_visible_mobile'] ? '표시' : '숨김'; ?>
                        </span>
                        <span class="badge badge-info">순서: <?php echo $board['sort_order']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 수정 폼 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-pencil"></i> 게시판 정보 수정
            </h2>

            <form method="post" action="">
                <?php echo get_admin_token_field(); ?>
                <input type="hidden" name="action" value="save">

                <div class="addon-form-group">
                    <label class="addon-form-label">게시판 키 (변경 불가)</label>
                    <input type="text" class="addon-form-input" value="<?php echo htmlspecialchars($board_key); ?>" readonly disabled>
                    <small class="addon-form-help">게시판 고유 식별자 (수정 불가)</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">게시판 이름 *</label>
                    <input type="text" name="board_name" class="addon-form-input" value="<?php echo htmlspecialchars($board['board_name']); ?>" required placeholder="공지사항">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">게시판 설명</label>
                    <textarea name="board_description" class="addon-form-input" rows="3" placeholder="중요한 공지사항을 확인하세요"><?php echo htmlspecialchars($board['board_description']); ?></textarea>
                    <small class="addon-form-help">게시판에 대한 간단한 설명</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">아이콘</label>
                    <div class="icon-selector">
                        <input type="text" name="icon_class" id="iconInput" class="addon-form-input" value="<?php echo htmlspecialchars($board['icon_class']); ?>" placeholder="fa-th-large">
                        <div class="icon-preview">
                            <i class="fa <?php echo htmlspecialchars($board['icon_class']); ?>" id="iconPreview"></i>
                        </div>
                    </div>
                    <small class="addon-form-help">Font Awesome 아이콘 클래스 (예: fa-home, fa-user)</small>

                    <!-- 아이콘 선택 그리드 -->
                    <div class="icon-grid">
                        <?php
                        $icons = array(
                            'fa-th-large', 'fa-home', 'fa-user', 'fa-bell', 'fa-calendar',
                            'fa-heart', 'fa-star', 'fa-comment', 'fa-shopping-cart', 'fa-envelope',
                            'fa-camera', 'fa-music', 'fa-video-camera', 'fa-file-text', 'fa-newspaper-o',
                            'fa-bullhorn', 'fa-flag', 'fa-bookmark', 'fa-gift', 'fa-trophy'
                        );

                        foreach ($icons as $icon):
                        ?>
                            <div class="icon-option" data-icon="<?php echo $icon; ?>" title="<?php echo $icon; ?>">
                                <i class="fa <?php echo $icon; ?>"></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">정렬 순서</label>
                    <input type="number" name="sort_order" class="addon-form-input" value="<?php echo htmlspecialchars($board['sort_order']); ?>" min="0">
                    <small class="addon-form-help">숫자가 작을수록 먼저 표시됩니다</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">표시 설정</label>
                    <div class="visibility-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_visible_pc" value="1" <?php echo $board['is_visible_pc'] ? 'checked' : ''; ?>>
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text">
                                <i class="fa fa-desktop"></i> PC에서 표시
                            </span>
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_visible_mobile" value="1" <?php echo $board['is_visible_mobile'] ? 'checked' : ''; ?>>
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text">
                                <i class="fa fa-mobile"></i> 모바일에서 표시
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="./boards.php" class="addon-btn addon-btn-secondary addon-btn-lg">
                        <i class="fa fa-times"></i> 취소
                    </a>
                    <button type="submit" class="addon-btn addon-btn-primary addon-btn-lg">
                        <i class="fa fa-save"></i> 저장
                    </button>
                </div>
            </form>
        </div>

    </div>

    <div class="addon-footer">
        <div class="addon-container">
            <div class="addon-footer-content">
                <p>&copy; 2026 Hair Wang App Admin. All rights reserved.</p>
            </div>
        </div>
    </div>

</div>

<style>
.current-board-card {
    display: flex;
    gap: 25px;
    padding: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
}

.current-board-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.current-board-icon i {
    font-size: 40px;
}

.current-board-info h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
}

.current-board-info p {
    margin: 0 0 15px 0;
    opacity: 0.9;
}

.current-board-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-success {
    background: #28a745;
    color: white;
}

.badge-secondary {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.badge-info {
    background: rgba(255, 255, 255, 0.3);
    color: white;
}

.icon-selector {
    display: flex;
    gap: 15px;
    align-items: center;
}

.icon-selector input {
    flex: 1;
}

.icon-preview {
    width: 60px;
    height: 60px;
    background: #f5f5f5;
    border: 2px solid #ddd;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.icon-preview i {
    font-size: 28px;
    color: var(--primary-color);
}

.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
    gap: 10px;
    margin-top: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 8px;
}

.icon-option {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.icon-option:hover {
    border-color: var(--primary-color);
    background: #f5f0ff;
    transform: scale(1.1);
}

.icon-option.selected {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: white;
}

.icon-option i {
    font-size: 20px;
}

.visibility-options {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 20px;
    background: #f9f9f9;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    flex: 1;
    min-width: 200px;
}

.checkbox-label:hover {
    border-color: var(--primary-color);
    background: #f5f0ff;
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkbox-custom {
    width: 24px;
    height: 24px;
    border: 2px solid #ddd;
    border-radius: 6px;
    position: relative;
    transition: all 0.3s;
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom {
    background: var(--primary-color);
    border-color: var(--primary-color);
}

.checkbox-label input[type="checkbox"]:checked + .checkbox-custom::after {
    content: '\f00c';
    font-family: FontAwesome;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 14px;
}

.checkbox-text {
    font-size: 15px;
    font-weight: 500;
    color: #333;
}

.checkbox-text i {
    margin-right: 6px;
    color: var(--primary-color);
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    padding-top: 30px;
    border-top: 1px solid #e0e0e0;
    margin-top: 30px;
}

.addon-btn-lg {
    padding: 15px 40px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .current-board-card {
        flex-direction: column;
        text-align: center;
    }

    .current-board-icon {
        margin: 0 auto;
    }

    .icon-grid {
        grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
    }

    .visibility-options {
        flex-direction: column;
    }

    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
// 아이콘 입력 변경 시 미리보기 업데이트
document.getElementById('iconInput').addEventListener('input', function() {
    const iconClass = this.value;
    const preview = document.getElementById('iconPreview');
    preview.className = 'fa ' + iconClass;
});

// 아이콘 그리드 클릭
document.querySelectorAll('.icon-option').forEach(function(option) {
    option.addEventListener('click', function() {
        const icon = this.getAttribute('data-icon');

        // 선택 상태 업데이트
        document.querySelectorAll('.icon-option').forEach(function(opt) {
            opt.classList.remove('selected');
        });
        this.classList.add('selected');

        // 입력 필드와 미리보기 업데이트
        document.getElementById('iconInput').value = icon;
        document.getElementById('iconPreview').className = 'fa ' + icon;
    });
});

// 현재 아이콘 선택 표시
document.addEventListener('DOMContentLoaded', function() {
    const currentIcon = document.getElementById('iconInput').value;
    document.querySelectorAll('.icon-option').forEach(function(option) {
        if (option.getAttribute('data-icon') === currentIcon) {
            option.classList.add('selected');
        }
    });
});
</script>

<?php
include_once('./layout_footer.php');
?>
