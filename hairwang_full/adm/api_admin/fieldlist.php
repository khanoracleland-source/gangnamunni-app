<?php
/**
 * 필드 정의 관리 페이지
 * API 필드 정의 CRUD 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 액션 처리
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'create') {
    // 필드 정의 생성
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $default_value = isset($_POST['default_value']) ? trim($_POST['default_value']) : '';
    $options = isset($_POST['options']) ? trim($_POST['options']) : '';
    $required = isset($_POST['required']) ? (int)$_POST['required'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $order = isset($_POST['order']) ? (int)$_POST['order'] : 0;
    
    if (empty($name)) {
        alert('필드 이름을 입력해주세요.');
    }
    
    if (empty($code)) {
        alert('필드 코드를 입력해주세요.');
    }
    
    if (!in_array($type, array('text', 'number', 'select', 'checkbox', 'textarea'))) {
        alert('잘못된 필드 타입입니다.');
    }
    
    if (!in_array($category, array('sns', 'pg', 'common'))) {
        alert('잘못된 카테고리입니다.');
    }
    
    // 필드 코드 중복 확인
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['api_fields_table']} WHERE af_code = '" . sql_real_escape_string($code) . "'";
    $result = sql_fetch($sql);
    
    if ($result['cnt'] > 0) {
        alert('이미 존재하는 필드 코드입니다.');
    }
    
    // 데이터베이스에 저장
    $sql = "
        INSERT INTO {$g5['api_fields_table']}
        SET af_name = '" . sql_real_escape_string($name) . "',
            af_code = '" . sql_real_escape_string($code) . "',
            af_type = '" . sql_real_escape_string($type) . "',
            af_category = '" . sql_real_escape_string($category) . "',
            af_default_value = " . ($default_value ? "'" . sql_real_escape_string($default_value) . "'" : "NULL") . ",
            af_options = " . ($options ? "'" . sql_real_escape_string($options) . "'" : "NULL") . ",
            af_required = '" . sql_real_escape_string($required) . "',
            af_status = '" . sql_real_escape_string($status) . "',
            af_order = '" . sql_real_escape_string($order) . "',
            af_created_at = NOW()
    ";
    
    sql_query($sql);
    
    alert('필드 정의가 생성되었습니다.', './fieldlist.php');
    
} elseif ($action === 'update') {
    // 필드 정의 수정
    $af_id = isset($_POST['af_id']) ? (int)$_POST['af_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $default_value = isset($_POST['default_value']) ? trim($_POST['default_value']) : '';
    $options = isset($_POST['options']) ? trim($_POST['options']) : '';
    $required = isset($_POST['required']) ? (int)$_POST['required'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $order = isset($_POST['order']) ? (int)$_POST['order'] : 0;
    
    if ($af_id === 0) {
        alert('필드 ID가 유효하지 않습니다.');
    }
    
    if (empty($name)) {
        alert('필드 이름을 입력해주세요.');
    }
    
    if (empty($code)) {
        alert('필드 코드를 입력해주세요.');
    }
    
    if (!in_array($type, array('text', 'number', 'select', 'checkbox', 'textarea'))) {
        alert('잘못된 필드 타입입니다.');
    }
    
    if (!in_array($category, array('sns', 'pg', 'common'))) {
        alert('잘못된 카테고리입니다.');
    }
    
    // 데이터베이스 업데이트
    $sql = "
        UPDATE {$g5['api_fields_table']}
        SET af_name = '" . sql_real_escape_string($name) . "',
            af_code = '" . sql_real_escape_string($code) . "',
            af_type = '" . sql_real_escape_string($type) . "',
            af_category = '" . sql_real_escape_string($category) . "',
            af_default_value = " . ($default_value ? "'" . sql_real_escape_string($default_value) . "'" : "NULL") . ",
            af_options = " . ($options ? "'" . sql_real_escape_string($options) . "'" : "NULL") . ",
            af_required = '" . sql_real_escape_string($required) . "',
            af_status = '" . sql_real_escape_string($status) . "',
            af_order = '" . sql_real_escape_string($order) . "'
        WHERE af_id = '" . sql_real_escape_string($af_id) . "'
    ";
    
    sql_query($sql);
    
    alert('필드 정의가 수정되었습니다.', './fieldlist.php');
    
} elseif ($action === 'delete') {
    // 필드 정의 삭제
    $af_id = isset($_POST['af_id']) ? (int)$_POST['af_id'] : 0;
    
    if ($af_id === 0) {
        alert('필드 ID가 유효하지 않습니다.');
    }
    
    // 필드 값 삭제
    sql_query("DELETE FROM {$g5['api_field_values_table']} WHERE af_id = '" . sql_real_escape_string($af_id) . "'");
    
    // 필드 정의 삭제
    sql_query("DELETE FROM {$g5['api_fields_table']} WHERE af_id = '" . sql_real_escape_string($af_id) . "'");
    
    alert('필드 정의가 삭제되었습니다.', './fieldlist.php');
}

// 검색 조건
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';

// WHERE 조건 구성
$where = array();

if ($category) {
    $where[] = "af_category = '" . sql_real_escape_string($category) . "'";
}

if ($status !== '') {
    $where[] = "af_status = '" . sql_real_escape_string($status) . "'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['api_fields_table']} $where_sql";
$total_count = sql_fetch($sql)['cnt'];
$total_pages = ceil($total_count / $limit);

// 필드 목록 조회
$sql = "
    SELECT *
    FROM {$g5['api_fields_table']}
    $where_sql
    ORDER BY af_order ASC, af_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./keylist.php" class="ov_list">API 키 목록</a>
    <span class="btn_ov01"><span class="ov_txt">등록된 필드</span><span class="ov_num"> <?php echo number_format($total_count); ?>개</span></span>
</div>

<form name="ffield" id="ffield" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="action" value="create">
    
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>필드 정의 생성</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="name">필드 이름</label></th>
                    <td>
                        <input type="text" name="name" id="name" class="frm_input required" size="50" required>
                        <span class="frm_info">필드 표시 이름</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="code">필드 코드</label></th>
                    <td>
                        <input type="text" name="code" id="code" class="frm_input required" size="50" required>
                        <span class="frm_info">유일한 필드 코드 (영문, 숫자, 언더스코어만 허용)</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="type">필드 타입</label></th>
                    <td>
                        <select name="type" id="type" class="frm_input">
                            <option value="text">Text (텍스트)</option>
                            <option value="number">Number (숫자)</option>
                            <option value="select">Select (선택)</option>
                            <option value="checkbox">Checkbox (체크박스)</option>
                            <option value="textarea">Textarea (여러 줄 텍스트)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="category">카테고리</label></th>
                    <td>
                        <select name="category" id="category" class="frm_input">
                            <option value="sns">SNS 로그인</option>
                            <option value="pg">PG 결제</option>
                            <option value="common">공통</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_value">기본값</label></th>
                    <td>
                        <input type="text" name="default_value" id="default_value" class="frm_input" size="50">
                        <span class="frm_info">필드 기본값</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="options">옵션</label></th>
                    <td>
                        <input type="text" name="options" id="options" class="frm_input" size="50">
                        <span class="frm_info">Select, Checkbox 타입일 경우 JSON 형식의 옵션 (예: {"option1":"값1","option2":"값2"})</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="required">필수 여부</label></th>
                    <td>
                        <input type="checkbox" name="required" id="required" value="1">
                        <span class="frm_info">필수 필드인 경우 체크</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="order">정렬 순서</label></th>
                    <td>
                        <input type="number" name="order" id="order" class="frm_input" size="10" value="0">
                        <span class="frm_info">작을수록 먼저 표시</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <a href="./keylist.php" class="btn btn_02">목록</a>
        <input type="submit" value="필드 정의 생성" class="btn_submit btn">
    </div>
</form>

<form name="fsearch" id="fsearch" method="get" class="local_sch01 local_sch">
    <label for="category" class="sound_only">카테고리</label>
    <select name="category" id="category">
        <option value="">전체</option>
        <option value="sns" <?php echo $category === 'sns' ? 'selected' : ''; ?>>SNS 로그인</option>
        <option value="pg" <?php echo $category === 'pg' ? 'selected' : ''; ?>>PG 결제</option>
        <option value="common" <?php echo $category === 'common' ? 'selected' : ''; ?>>공통</option>
    </select>
    
    <label for="status" class="sound_only">상태</label>
    <select name="status" id="status">
        <option value="">전체</option>
        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>활성</option>
        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>비활성</option>
    </select>
    
    <input type="submit" value="검색" class="btn_submit">
</form>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>필드 정의 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">이름</th>
                <th scope="col">코드</th>
                <th scope="col">타입</th>
                <th scope="col">카테고리</th>
                <th scope="col">기본값</th>
                <th scope="col">필수</th>
                <th scope="col">상태</th>
                <th scope="col">정렬</th>
                <th scope="col">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['af_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['af_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($row['af_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['af_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['af_category']); ?></td>
                        <td><?php echo htmlspecialchars($row['af_default_value']); ?></td>
                        <td><?php echo $row['af_required'] ? 'O' : 'X'; ?></td>
                        <td>
                            <?php if ($row['af_status'] == 1) { ?>
                                <span class="btn_confirm btn">활성</span>
                            <?php } else { ?>
                                <span class="btn_confirm" style="background:#999;">비활성</span>
                            <?php } ?>
                        </td>
                        <td><?php echo $row['af_order']; ?></td>
                        <td class="td_mng">
                            <a href="./fieldform.php?af_id=<?php echo $row['af_id']; ?>" class="btn btn_03">수정</a>
                            <button type="button" class="btn btn_02" onclick="confirm_delete('<?php echo $row['af_id']; ?>');">삭제</button>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="10" class="empty_table">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<?php
// 페이징
echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_pages, $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
?>

<script>
function confirm_delete(af_id) {
    if (confirm('정말 삭제하시겠습니까?')) {
        document.getElementById('ffield').action.value = 'delete';
        var af_id_input = document.createElement('input');
        af_id_input.type = 'hidden';
        af_id_input.name = 'af_id';
        af_id_input.value = af_id;
        document.getElementById('ffield').appendChild(af_id_input);
        document.getElementById('ffield').submit();
    }
}
</script>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
