<?php
/**
 * 게시판 관리 페이지 - g5_board 연동
 * @package AddonAppAdmin
 * @version 1.0.0
 * @date 2026-01-19
 */

include_once('./_common.php');

// 레이아웃 헤더
$g5['title'] = '게시판 관리';
include_once('./layout_header.php');

// 정렬 순서 일괄 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sort_order') {
    header('Content-Type: application/json');

    try {
        $sort_data = json_decode($_POST['sort_data'], true);

        if (!is_array($sort_data)) {
            echo json_encode(array('success' => false, 'message' => '잘못된 데이터 형식'));
            exit;
        }

        $success_count = 0;
        foreach ($sort_data as $item) {
            $bo_table = isset($item['bo_table']) ? $item['bo_table'] : '';
            $sort_order = isset($item['sort_order']) ? intval($item['sort_order']) : 0;

            if ($bo_table) {
                $update_sql = " UPDATE {$addon_app_admin_table_boards}
                               SET sort_order = {$sort_order}
                               WHERE bo_table = '{$bo_table}' ";

                if (sql_query($update_sql)) {
                    $success_count++;
                }
            }
        }

        echo json_encode(array('success' => true, 'count' => $success_count));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
    }
    exit;
}

// 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    check_admin_token();

    $bo_table = isset($_POST['bo_table']) ? trim($_POST['bo_table']) : '';

    if ($bo_table) {
        $icon_class = isset($_POST['icon_class']) ? trim($_POST['icon_class']) : 'fa-th-large';
        $is_visible_pc = isset($_POST['is_visible_pc']) ? 1 : 0;
        $is_visible_mobile = isset($_POST['is_visible_mobile']) ? 1 : 0;
        $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

        // 기존 설정이 있는지 확인
        $check_sql = " SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_boards} WHERE bo_table = '{$bo_table}' ";
        $check_result = sql_fetch($check_sql);

        if ($check_result && $check_result['cnt'] > 0) {
            // 업데이트
            $sql = " UPDATE {$addon_app_admin_table_boards} SET
                        icon_class = '{$icon_class}',
                        is_visible_pc = {$is_visible_pc},
                        is_visible_mobile = {$is_visible_mobile},
                        sort_order = {$sort_order}
                     WHERE bo_table = '{$bo_table}' ";
        } else {
            // 삽입
            $sql = " INSERT INTO {$addon_app_admin_table_boards}
                     (bo_table, icon_class, is_visible_pc, is_visible_mobile, sort_order)
                     VALUES ('{$bo_table}', '{$icon_class}', {$is_visible_pc}, {$is_visible_mobile}, {$sort_order}) ";
        }

        if (sql_query($sql)) {
            addon_app_admin_log('board_update', $bo_table, '게시판 설정 업데이트');
            echo '<script>alert("게시판 설정이 저장되었습니다."); location.href="./boards.php";</script>';
            exit;
        } else {
            echo '<script>alert("저장에 실패했습니다.");</script>';
        }
    }
}

// g5_board 테이블에서 모든 게시판 가져오기
$debug_info = array();
$debug_info['step'] = '게시판 조회 시작';
$debug_info['g5_board_table'] = isset($g5['board_table']) ? $g5['board_table'] : 'NOT SET';
$debug_info['addon_boards_table'] = isset($addon_app_admin_table_boards) ? $addon_app_admin_table_boards : 'NOT SET';
$debug_info['errors'] = array();
$boards = array();

try {
    $sql = " SELECT b.bo_table, b.bo_subject, b.bo_mobile_subject, b.bo_device, b.bo_use_list_view,
                    s.icon_class, s.is_visible_pc, s.is_visible_mobile, s.sort_order
             FROM " . $g5['board_table'] . " b
             LEFT JOIN {$addon_app_admin_table_boards} s ON b.bo_table = s.bo_table
             ORDER BY COALESCE(s.sort_order, 999) ASC, b.bo_table ASC ";

    $debug_info['sql_query'] = $sql;

    $result = @sql_query($sql);

    if (!$result) {
        $debug_info['query_success'] = false;
        $error_msg = 'SQL 쿼리 실행 실패';

        // sql_error() 함수가 있으면 사용
        if (function_exists('sql_error')) {
            $error_msg .= ': ' . sql_error();
        } else if (isset($connect_db)) {
            $error_msg .= ': ' . mysqli_error($connect_db);
        }

        $debug_info['sql_error'] = $error_msg;
        $debug_info['errors'][] = $error_msg;
    } else {
        $debug_info['query_success'] = true;

        $row_count = sql_num_rows($result);
        $debug_info['row_count'] = $row_count;
        $debug_info['boards'] = array();

        while ($row = sql_fetch_array($result)) {
            // 원본 데이터 저장 (디버깅용)
            $original_icon = $row['icon_class'];

            // icon_class가 null이면 기본값 설정
            if (empty($row['icon_class'])) {
                $row['icon_class'] = 'fa-th-large';
            }

            // is_visible 값이 null이면 기본값 1 설정
            if ($row['is_visible_pc'] === null) {
                $row['is_visible_pc'] = 1;
            }
            if ($row['is_visible_mobile'] === null) {
                $row['is_visible_mobile'] = 1;
            }
            if ($row['sort_order'] === null) {
                $row['sort_order'] = 0;
            }

            $boards[] = $row;
            $debug_info['boards'][] = array(
                'bo_table' => $row['bo_table'],
                'bo_subject' => $row['bo_subject'],
                'icon_class' => $row['icon_class'],
                'icon_class_original' => $original_icon,  // 원본 값 표시
                'is_visible_pc' => $row['is_visible_pc'],
                'is_visible_mobile' => $row['is_visible_mobile']
            );
        }
    }
} catch (Exception $e) {
    $error_msg = 'Exception 발생: ' . $e->getMessage();
    $debug_info['errors'][] = $error_msg;
    $debug_info['exception'] = array(
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    );
}

// 아이콘 할당 함수 (게시판 이름 기반)
function assign_smart_icon($bo_subject, $bo_table) {
    $bo_subject_lower = strtolower($bo_subject);
    $bo_table_lower = strtolower($bo_table);

    // 게시판 이름과 테이블명 모두 검사
    $search_text = $bo_subject_lower . ' ' . $bo_table_lower;

    if (strpos($search_text, '공지') !== false || strpos($search_text, 'notice') !== false) {
        return 'fa-bullhorn';
    } elseif (strpos($search_text, '갤러리') !== false || strpos($search_text, 'gallery') !== false) {
        return 'fa-image';
    } elseif (strpos($search_text, '자료') !== false || strpos($search_text, '다운로드') !== false || strpos($search_text, 'download') !== false) {
        return 'fa-download';
    } elseif (strpos($search_text, '질문') !== false || strpos($search_text, 'qna') !== false || strpos($search_text, 'q&a') !== false) {
        return 'fa-question-circle';
    } elseif (strpos($search_text, '자유') !== false || strpos($search_text, 'free') !== false) {
        return 'fa-comments';
    } elseif (strpos($search_text, '포토') !== false || strpos($search_text, 'photo') !== false) {
        return 'fa-camera';
    } elseif (strpos($search_text, '영상') !== false || strpos($search_text, '동영상') !== false || strpos($search_text, 'video') !== false) {
        return 'fa-film';
    } elseif (strpos($search_text, '이벤트') !== false || strpos($search_text, 'event') !== false) {
        return 'fa-gift';
    } elseif (strpos($search_text, '시술') !== false || strpos($search_text, 'salon') !== false) {
        return 'fa-scissors';
    } elseif (strpos($search_text, '모의') !== false || strpos($search_text, 'mock') !== false) {
        return 'fa-flask';
    } elseif (strpos($search_text, '제품') !== false || strpos($search_text, 'product') !== false) {
        return 'fa-shopping-cart';
    } elseif (strpos($search_text, '리뷰') !== false || strpos($search_text, 'review') !== false) {
        return 'fa-star';
    } elseif (strpos($search_text, '공지') !== false || strpos($search_text, 'notice') !== false) {
        return 'fa-bell';
    } else {
        // 기본 아이콘 (테이블명 기반으로 다양하게)
        $icons = array('fa-th-large', 'fa-list-alt', 'fa-folder-open', 'fa-file-text', 'fa-inbox');
        return $icons[crc32($bo_table) % count($icons)];
    }
}

// addon_app_admin_boards 테이블에 없는 게시판 자동 추가 및 기존 아이콘 업데이트
$auto_insert_count = 0;
$auto_update_count = 0;
$auto_insert_details = array();

foreach ($boards as $idx => $board) {
    // LEFT JOIN 결과에서 addon 테이블 데이터가 없으면 자동 추가
    $check_sql = " SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_boards} WHERE bo_table = '{$board['bo_table']}' ";
    $check_result = sql_fetch($check_sql);

    if ($check_result && $check_result['cnt'] == 0) {
        // 스마트 아이콘 할당
        $default_icon = assign_smart_icon($board['bo_subject'], $board['bo_table']);

        // 기본 아이콘 설정
        $insert_sql = " INSERT INTO {$addon_app_admin_table_boards}
                        (bo_table, icon_class, is_visible_pc, is_visible_mobile, sort_order)
                        VALUES ('{$board['bo_table']}', '{$default_icon}', 1, 1, 0) ";

        if (sql_query($insert_sql)) {
            $auto_insert_count++;
            // boards 배열 업데이트
            $boards[$idx]['icon_class'] = $default_icon;
            $boards[$idx]['is_visible_pc'] = 1;
            $boards[$idx]['is_visible_mobile'] = 1;
            $boards[$idx]['sort_order'] = 0;

            $auto_insert_details[] = array(
                'bo_table' => $board['bo_table'],
                'bo_subject' => $board['bo_subject'],
                'icon_class' => $default_icon,
                'action' => 'inserted'
            );

            error_log('[BOARDS] 게시판 자동 추가: ' . $board['bo_table'] . ' - ' . $default_icon);
        }
    } else {
        // 이미 존재하는 게시판의 아이콘이 null이거나 fa-th-large인 경우 스마트 아이콘으로 업데이트
        if (empty($board['icon_class']) || $board['icon_class'] == 'fa-th-large') {
            $smart_icon = assign_smart_icon($board['bo_subject'], $board['bo_table']);

            $update_sql = " UPDATE {$addon_app_admin_table_boards}
                           SET icon_class = '{$smart_icon}'
                           WHERE bo_table = '{$board['bo_table']}' ";

            if (sql_query($update_sql)) {
                $auto_update_count++;
                $boards[$idx]['icon_class'] = $smart_icon;

                $auto_insert_details[] = array(
                    'bo_table' => $board['bo_table'],
                    'bo_subject' => $board['bo_subject'],
                    'icon_class' => $smart_icon,
                    'action' => 'updated'
                );

                error_log('[BOARDS] 게시판 아이콘 업데이트: ' . $board['bo_table'] . ' - ' . $smart_icon);
            }
        }
    }
}

$debug_info['auto_insert_count'] = $auto_insert_count;
$debug_info['auto_update_count'] = $auto_update_count;
$debug_info['auto_insert_details'] = $auto_insert_details;
$debug_info['final_board_count'] = count($boards);

?>

<style>
.board-icon-preview {
    font-size: 24px;
    color: #aa20ff;
    margin-right: 10px;
}
.board-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border-bottom: 1px solid #eee;
    background: white;
    margin-bottom: 10px;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.board-row:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.board-info {
    flex: 1;
}
.board-actions {
    display: flex;
    gap: 10px;
}
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}
.badge-success {
    background-color: #28a745;
    color: white;
}
.badge-secondary {
    background-color: #6c757d;
    color: white;
}
.edit-form {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}
.form-row {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 15px;
    margin-bottom: 15px;
    align-items: center;
}
.form-label {
    font-weight: 600;
    color: #333;
}
.form-input {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}
.form-input:focus {
    outline: none;
    border-color: #aa20ff;
    box-shadow: 0 0 0 3px rgba(170, 32, 255, 0.1);
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: #aa20ff;
    color: white;
}
.btn-primary:hover {
    background: #8919d9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(170, 32, 255, 0.3);
}
.btn-secondary {
    background: #6c757d;
    color: white;
}
.btn-secondary:hover {
    background: #5a6268;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
.icon-picker-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
}
.icon-preview {
    font-size: 32px;
    color: #aa20ff;
    width: 50px;
    text-align: center;
}

/* 탭 스타일 */
.tabs-container {
    margin-bottom: 30px;
}
.tabs {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid #e0e0e0;
}
.tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: #666;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.tab-button:hover {
    color: #aa20ff;
    background: #f5f0ff;
}
.tab-button.active {
    color: #aa20ff;
    border-bottom-color: #aa20ff;
    background: #f5f0ff;
}
.tab-button i {
    margin-right: 6px;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* 정렬 가능한 리스트 스타일 */
.sortable-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.sortable-item {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: move;
    transition: all 0.3s;
}
.sortable-item:hover {
    border-color: #aa20ff;
    box-shadow: 0 4px 12px rgba(170, 32, 255, 0.15);
    transform: translateX(5px);
}
.sortable-item.dragging {
    opacity: 0.5;
    transform: scale(1.02);
}
.sortable-item.drag-over {
    border-top: 3px solid #aa20ff;
}
.sortable-handle {
    font-size: 20px;
    color: #999;
    cursor: grab;
    padding: 5px;
}
.sortable-handle:active {
    cursor: grabbing;
}
.sortable-icon {
    font-size: 28px;
    color: #aa20ff;
    width: 40px;
    text-align: center;
}
.sortable-content {
    flex: 1;
}
.sortable-title {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}
.sortable-info {
    font-size: 13px;
    color: #999;
}
.sortable-info code {
    background: #f5f5f5;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}
.sortable-badges {
    display: flex;
    gap: 8px;
}
.current-order {
    font-weight: bold;
    color: #aa20ff;
}
</style>

<div class="admin-content-header">
    <h1 class="admin-content-title">게시판 관리</h1>
    <p class="admin-content-desc">
        <i class="fa fa-info-circle"></i>
        그누보드5 게시판의 아이콘, 표시 순서, 표시 여부를 관리합니다.
    </p>
</div>

<script>
// ============================================
// 탭 전환 기능 (버튼보다 먼저 정의)
// ============================================
function switchTab(tabName) {
    // 모든 탭 버튼 비활성화
    document.querySelectorAll('.tab-button').forEach(function(btn) {
        btn.classList.remove('active');
    });

    // 모든 탭 콘텐츠 숨기기
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
        content.style.display = 'none';
    });

    // 선택한 탭 활성화
    var selectedTab = document.querySelector('.tab-button[onclick*="' + tabName + '"]');
    if (selectedTab) {
        selectedTab.classList.add('active');
    }

    var selectedContent = document.getElementById('tab-' + tabName);
    if (selectedContent) {
        selectedContent.classList.add('active');
        selectedContent.style.display = 'block';
    }

    console.log('%c📑 탭 전환:', 'color: #2196f3; font-weight: bold;', tabName);
}
</script>

<!-- 탭 메뉴 -->
<div class="tabs-container">
    <div class="tabs">
        <button class="tab-button active" onclick="switchTab('list')">
            <i class="fa fa-list"></i> 게시판 목록
        </button>
        <button class="tab-button" onclick="switchTab('sort')">
            <i class="fa fa-sort"></i> 정렬 순서 관리
        </button>
    </div>
</div>

<!-- 디버그 정보 -->
<?php if (count($boards) === 0): ?>
<div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
    <h3 style="color: #856404; margin-bottom: 10px;">디버그 정보</h3>
    <p><strong>g5_board 테이블:</strong> <?php echo isset($g5['board_table']) ? $g5['board_table'] : 'NOT SET'; ?></p>
    <p><strong>addon_boards 테이블:</strong> <?php echo isset($addon_app_admin_table_boards) ? $addon_app_admin_table_boards : 'NOT SET'; ?></p>
    <p><strong>SQL 쿼리:</strong><br><code style="background: #f8f9fa; padding: 10px; display: block; margin-top: 5px; word-break: break-all;"><?php echo htmlspecialchars($sql); ?></code></p>

    <?php
    // g5_board 테이블 직접 조회 테스트
    $test_sql = "SELECT COUNT(*) as cnt FROM " . $g5['board_table'];
    $test_result = sql_fetch($test_sql);
    ?>
    <p><strong>g5_board 전체 게시판 수:</strong> <?php echo $test_result ? $test_result['cnt'] : '조회 실패'; ?></p>

    <?php
    // 테이블 존재 여부 확인
    $check_sql = "SHOW TABLES LIKE '" . $g5['board_table'] . "'";
    $check_result = sql_query($check_sql);
    $table_exists = sql_num_rows($check_result) > 0;
    ?>
    <p><strong>g5_board 테이블 존재:</strong> <?php echo $table_exists ? 'YES' : 'NO'; ?></p>
</div>
<?php endif; ?>

<!-- 탭 콘텐츠: 게시판 목록 -->
<div id="tab-list" class="tab-content active">
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <h2 style="font-size: 20px; color: #333; margin-bottom: 20px;">
            <i class="fa fa-list"></i> 게시판 목록 (<?php echo count($boards); ?>개)
        </h2>

    <?php if (count($boards) > 0): ?>
        <div id="board-list">
            <?php foreach ($boards as $board): ?>
                <div class="board-row" id="board-<?php echo $board['bo_table']; ?>">
                    <div class="board-icon-preview">
                        <i class="fa <?php echo htmlspecialchars($board['icon_class'] ? $board['icon_class'] : 'fa-th-large'); ?>"></i>
                    </div>
                    <div class="board-info">
                        <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($board['bo_subject']); ?>
                            <?php if ($board['is_visible_pc'] == 1 || $board['is_visible_mobile'] == 1): ?>
                                <span class="badge badge-success">표시</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">숨김</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 13px; color: #999;">
                            테이블: <code><?php echo htmlspecialchars($board['bo_table']); ?></code>
                            | 순서: <?php echo $board['sort_order'] ? $board['sort_order'] : '미설정'; ?>
                            | PC: <?php echo $board['is_visible_pc'] ? 'O' : 'X'; ?>
                            | 모바일: <?php echo $board['is_visible_mobile'] ? 'O' : 'X'; ?>
                        </div>
                    </div>
                    <div class="board-actions">
                        <button type="button" class="btn btn-primary btn-sm" onclick="showEditForm('<?php echo $board['bo_table']; ?>')">
                            <i class="fa fa-edit"></i> 설정
                        </button>
                    </div>
                </div>

                <!-- 인라인 편집 폼 (숨김 상태) -->
                <div id="edit-form-<?php echo $board['bo_table']; ?>" class="edit-form" style="display: none;">
                    <form method="post" action="">
                        <?php echo get_admin_token_field(); ?>
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="bo_table" value="<?php echo $board['bo_table']; ?>">

                        <div style="font-size: 18px; font-weight: bold; margin-bottom: 20px; color: #333;">
                            <i class="fa fa-cog"></i> <?php echo htmlspecialchars($board['bo_subject']); ?> 설정
                        </div>

                        <div class="form-row">
                            <label class="form-label">아이콘 클래스</label>
                            <div class="icon-picker-wrapper">
                                <div class="icon-preview">
                                    <i class="fa <?php echo htmlspecialchars($board['icon_class'] ? $board['icon_class'] : 'fa-th-large'); ?>" id="icon-preview-<?php echo $board['bo_table']; ?>"></i>
                                </div>
                                <input type="text" name="icon_class" class="form-input" id="icon-input-<?php echo $board['bo_table']; ?>"
                                       value="<?php echo htmlspecialchars($board['icon_class'] ? $board['icon_class'] : 'fa-th-large'); ?>"
                                       placeholder="예: fa-bullhorn"
                                       onkeyup="updateIconPreview(this.value, '<?php echo $board['bo_table']; ?>')"
                                       style="flex: 1;">
                                <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="openIconPicker(document.getElementById('icon-input-<?php echo $board['bo_table']; ?>'), document.getElementById('icon-preview-<?php echo $board['bo_table']; ?>'))">
                                    <i class="fa fa-search"></i> 아이콘 검색
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <label class="form-label">정렬 순서</label>
                            <input type="number" name="sort_order" class="form-input"
                                   value="<?php echo $board['sort_order'] ? $board['sort_order'] : 0; ?>"
                                   min="0" style="width: 150px;">
                        </div>

                        <div class="form-row">
                            <label class="form-label">표시 설정</label>
                            <div>
                                <label style="display: inline-flex; align-items: center; margin-right: 20px;">
                                    <input type="checkbox" name="is_visible_pc" value="1"
                                           <?php echo $board['is_visible_pc'] ? 'checked' : ''; ?>
                                           style="margin-right: 5px;">
                                    PC 표시
                                </label>
                                <label style="display: inline-flex; align-items: center;">
                                    <input type="checkbox" name="is_visible_mobile" value="1"
                                           <?php echo $board['is_visible_mobile'] ? 'checked' : ''; ?>
                                           style="margin-right: 5px;">
                                    모바일 표시
                                </label>
                            </div>
                        </div>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save"></i> 저장
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="hideEditForm('<?php echo $board['bo_table']; ?>')">
                                <i class="fa fa-times"></i> 취소
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 40px 0;">
            등록된 게시판이 없습니다.
        </p>
    <?php endif; ?>
    </div>
</div>

<!-- 탭 콘텐츠: 정렬 순서 관리 -->
<div id="tab-sort" class="tab-content" style="display: none;">
    <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
        <h2 style="font-size: 20px; color: #333; margin-bottom: 20px;">
            <i class="fa fa-sort"></i> 게시판 정렬 순서 관리
        </h2>

        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
            <i class="fa fa-info-circle" style="color: #1976d2;"></i>
            <strong>사용 방법:</strong> 드래그하여 순서를 변경하고 "정렬 순서 저장" 버튼을 클릭하세요.
        </div>

        <?php if (count($boards) > 0): ?>
            <div id="sortable-boards" class="sortable-list">
                <?php foreach ($boards as $board): ?>
                    <div class="sortable-item" data-bo-table="<?php echo htmlspecialchars($board['bo_table']); ?>">
                        <div class="sortable-handle">
                            <i class="fa fa-bars"></i>
                        </div>
                        <div class="sortable-icon">
                            <i class="fa <?php echo htmlspecialchars($board['icon_class'] ? $board['icon_class'] : 'fa-th-large'); ?>"></i>
                        </div>
                        <div class="sortable-content">
                            <div class="sortable-title"><?php echo htmlspecialchars($board['bo_subject']); ?></div>
                            <div class="sortable-info">
                                테이블: <code><?php echo htmlspecialchars($board['bo_table']); ?></code>
                                | 현재 순서: <span class="current-order"><?php echo $board['sort_order'] ? $board['sort_order'] : 0; ?></span>
                            </div>
                        </div>
                        <div class="sortable-badges">
                            <?php if ($board['is_visible_pc'] == 1): ?>
                                <span class="badge badge-success">PC</span>
                            <?php endif; ?>
                            <?php if ($board['is_visible_mobile'] == 1): ?>
                                <span class="badge badge-success">모바일</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 30px; text-align: center;">
                <button type="button" class="btn btn-primary" onclick="saveSortOrder()" style="padding: 15px 40px; font-size: 16px;">
                    <i class="fa fa-save"></i> 정렬 순서 저장
                </button>
            </div>
        <?php else: ?>
            <p style="text-align: center; color: #999; padding: 40px 0;">
                정렬할 게시판이 없습니다.
            </p>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/js/icon-picker.js"></script>
<script>
// ============================================
// 디버그 정보 콘솔 출력
// ============================================
console.log('%c========================================', 'color: #aa20ff; font-weight: bold; font-size: 16px;');
console.log('%c📱 게시판 관리 페이지 디버그 정보', 'color: #aa20ff; font-weight: bold; font-size: 14px;');
console.log('%c========================================', 'color: #aa20ff; font-weight: bold; font-size: 16px;');

var debugInfo = <?php echo json_encode($debug_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>;
console.log('%c1️⃣ 전체 디버그 정보:', 'color: #2196f3; font-weight: bold;');
console.table(debugInfo);

console.log('%c2️⃣ g5_board 테이블명:', 'color: #2196f3; font-weight: bold;', debugInfo.g5_board_table);
console.log('%c3️⃣ addon_boards 테이블명:', 'color: #2196f3; font-weight: bold;', debugInfo.addon_boards_table);
console.log('%c4️⃣ SQL 쿼리:', 'color: #2196f3; font-weight: bold;');
console.log(debugInfo.sql_query);
console.log('%c5️⃣ 쿼리 성공 여부:', 'color: #2196f3; font-weight: bold;', debugInfo.query_success ? '✅ 성공' : '❌ 실패');

if (!debugInfo.query_success && debugInfo.sql_error) {
    console.error('%c❌ SQL 오류:', 'color: #f44336; font-weight: bold;', debugInfo.sql_error);
}

console.log('%c6️⃣ 조회된 행 수:', 'color: #2196f3; font-weight: bold;', debugInfo.row_count);
console.log('%c7️⃣ 자동 추가된 게시판:', 'color: #4caf50; font-weight: bold;', debugInfo.auto_insert_count + '개');
console.log('%c8️⃣ 아이콘 업데이트된 게시판:', 'color: #ff9800; font-weight: bold;', debugInfo.auto_update_count + '개');

if (debugInfo.auto_insert_details && debugInfo.auto_insert_details.length > 0) {
    console.log('%c   자동 처리 상세:', 'color: #4caf50; font-weight: bold;');
    console.table(debugInfo.auto_insert_details);
}

console.log('%c9️⃣ 최종 게시판 배열 크기:', 'color: #2196f3; font-weight: bold;', debugInfo.final_board_count);

// 에러 정보 출력
if (debugInfo.errors && debugInfo.errors.length > 0) {
    console.error('%c❌ 오류 발생:', 'color: #f44336; font-weight: bold;');
    debugInfo.errors.forEach(function(err, idx) {
        console.error((idx + 1) + '. ' + err);
    });
}

// Exception 정보 출력
if (debugInfo.exception) {
    console.error('%c❌ Exception 상세:', 'color: #f44336; font-weight: bold;');
    console.table(debugInfo.exception);
}

if (debugInfo.boards && debugInfo.boards.length > 0) {
    console.log('%c🔟 게시판 목록:', 'color: #2196f3; font-weight: bold;');
    console.table(debugInfo.boards);
} else {
    console.warn('%c⚠️ 조회된 게시판이 없습니다!', 'color: #ff9800; font-weight: bold;');
}

console.log('%c========================================', 'color: #aa20ff; font-weight: bold; font-size: 16px;');

// 탭 전환 기능은 페이지 상단에 정의됨

// ============================================
// 드래그 앤 드롭 정렬 기능
// ============================================
var draggedItem = null;
var sortableItems = document.querySelectorAll('.sortable-item');

sortableItems.forEach(function(item) {
    // 드래그 시작
    item.addEventListener('dragstart', function(e) {
        draggedItem = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
        console.log('%c🎯 드래그 시작:', 'color: #4caf50; font-weight: bold;', this.dataset.boTable);
    });

    // 드래그 종료
    item.addEventListener('dragend', function(e) {
        this.classList.remove('dragging');
        sortableItems.forEach(function(item) {
            item.classList.remove('drag-over');
        });
        console.log('%c✅ 드래그 종료:', 'color: #4caf50; font-weight: bold;', this.dataset.boTable);
    });

    // 드래그 오버
    item.addEventListener('dragover', function(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';

        if (this !== draggedItem) {
            this.classList.add('drag-over');
        }
        return false;
    });

    // 드래그 진입
    item.addEventListener('dragenter', function(e) {
        if (this !== draggedItem) {
            this.classList.add('drag-over');
        }
    });

    // 드래그 벗어남
    item.addEventListener('dragleave', function(e) {
        this.classList.remove('drag-over');
    });

    // 드롭
    item.addEventListener('drop', function(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        if (draggedItem !== this) {
            var allItems = Array.from(sortableItems);
            var draggedIndex = allItems.indexOf(draggedItem);
            var targetIndex = allItems.indexOf(this);

            if (draggedIndex < targetIndex) {
                this.parentNode.insertBefore(draggedItem, this.nextSibling);
            } else {
                this.parentNode.insertBefore(draggedItem, this);
            }

            // 순서 번호 업데이트
            updateSortNumbers();
        }

        this.classList.remove('drag-over');
        return false;
    });

    // 드래그 가능하게 설정
    item.setAttribute('draggable', 'true');
});

// 정렬 번호 업데이트
function updateSortNumbers() {
    var items = document.querySelectorAll('.sortable-item');
    items.forEach(function(item, index) {
        var orderSpan = item.querySelector('.current-order');
        if (orderSpan) {
            orderSpan.textContent = index + 1;
        }
    });
    console.log('%c🔄 정렬 번호 업데이트 완료', 'color: #ff9800; font-weight: bold;');
}

// 정렬 순서 저장
function saveSortOrder() {
    var items = document.querySelectorAll('.sortable-item');
    var sortData = [];

    items.forEach(function(item, index) {
        sortData.push({
            bo_table: item.dataset.boTable,
            sort_order: index + 1
        });
    });

    console.log('%c💾 정렬 순서 저장:', 'color: #4caf50; font-weight: bold;');
    console.table(sortData);

    // AJAX로 서버에 저장
    var formData = new FormData();
    formData.append('action', 'save_sort_order');
    formData.append('sort_data', JSON.stringify(sortData));
    formData.append('token', '<?php echo get_admin_token(); ?>');

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('정렬 순서가 저장되었습니다.');
            console.log('%c✅ 저장 성공', 'color: #4caf50; font-weight: bold;');
            location.reload();
        } else {
            alert('저장 중 오류가 발생했습니다: ' + (data.message || '알 수 없는 오류'));
            console.error('%c❌ 저장 실패:', 'color: #f44336; font-weight: bold;', data.message);
        }
    })
    .catch(error => {
        alert('저장 중 오류가 발생했습니다.');
        console.error('%c❌ 네트워크 오류:', 'color: #f44336; font-weight: bold;', error);
    });
}

function showEditForm(boTable) {
    console.log('%c🔧 편집 폼 열기:', 'color: #4caf50; font-weight: bold;', boTable);

    // 모든 편집 폼 숨기기
    document.querySelectorAll('[id^="edit-form-"]').forEach(function(form) {
        form.style.display = 'none';
    });

    // 선택한 폼 표시
    var editForm = document.getElementById('edit-form-' + boTable);
    if (editForm) {
        editForm.style.display = 'block';
        editForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function hideEditForm(boTable) {
    var editForm = document.getElementById('edit-form-' + boTable);
    if (editForm) {
        editForm.style.display = 'none';
    }
}

function updateIconPreview(iconClass, boTable) {
    var preview = document.getElementById('icon-preview-' + boTable);
    if (preview) {
        preview.className = 'fa ' + iconClass.trim();
    }
}
</script>

<?php
include_once('./layout_footer.php');
?>
