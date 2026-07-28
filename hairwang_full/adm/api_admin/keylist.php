<?php
/**
 * API 키 관리 페이지
 * API 키 발급, 수정, 삭제 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 액션 처리
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'create') {
    // API 키 생성
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $type = isset($_POST['type']) ? trim($_POST['type']) : 'all';
    $expired_at = isset($_POST['expired_at']) ? trim($_POST['expired_at']) : '';
    
    if (empty($name)) {
        alert('API 키 이름을 입력해주세요.');
    }
    
    if (!in_array($type, array('sns', 'pg', 'all'))) {
        alert('잘못된 API 타입입니다.');
    }
    
    // API 키 및 시크릿 생성
    $api_key = generate_api_key();
    $api_secret = generate_api_secret();
    $api_secret_hash = password_hash($api_secret, PASSWORD_DEFAULT);
    sql_query("ALTER TABLE {$g5['api_key_table']} MODIFY ak_secret varchar(255) NOT NULL", true);
    
    // 데이터베이스에 저장
    $sql = "
        INSERT INTO {$g5['api_key_table']}
        SET ak_name = '" . sql_real_escape_string($name) . "',
            ak_key = '" . sql_real_escape_string($api_key) . "',
            ak_secret = '" . sql_real_escape_string($api_secret_hash) . "',
            ak_type = '" . sql_real_escape_string($type) . "',
            ak_status = 1,
            ak_created_at = NOW(),
            ak_expired_at = " . ($expired_at ? "'" . sql_real_escape_string($expired_at) . "'" : "NULL") . "
    ";
    
    sql_query($sql);
    
    alert('API 키가 생성되었습니다. 시크릿은 지금 한 번만 표시됩니다: ' . $api_secret, './keylist.php');
    
} elseif ($action === 'update') {
    // API 키 수정
    $ak_id = isset($_POST['ak_id']) ? (int)$_POST['ak_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    $expired_at = isset($_POST['expired_at']) ? trim($_POST['expired_at']) : '';
    
    if ($ak_id === 0) {
        alert('API 키 ID가 유효하지 않습니다.');
    }
    
    if (empty($name)) {
        alert('API 키 이름을 입력해주세요.');
    }
    
    // 데이터베이스 업데이트
    $sql = "
        UPDATE {$g5['api_key_table']}
        SET ak_name = '" . sql_real_escape_string($name) . "',
            ak_status = '" . sql_real_escape_string($status) . "',
            ak_expired_at = " . ($expired_at ? "'" . sql_real_escape_string($expired_at) . "'" : "NULL") . "
        WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
    ";
    
    sql_query($sql);
    
    alert('API 키가 수정되었습니다.', './keylist.php');
    
} elseif ($action === 'delete') {
    // API 키 삭제
    $ak_id = isset($_POST['ak_id']) ? (int)$_POST['ak_id'] : 0;
    
    if ($ak_id === 0) {
        alert('API 키 ID가 유효하지 않습니다.');
    }
    
    // 연결된 IP 화이트리스트 삭제
    sql_query("DELETE FROM {$g5['api_ip_table']} WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");
    
    // API 키 삭제
    sql_query("DELETE FROM {$g5['api_key_table']} WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");
    
    alert('API 키가 삭제되었습니다.', './keylist.php');
    
} elseif ($action === 'regenerate') {
    // API 시크릿 재발급
    $ak_id = isset($_POST['ak_id']) ? (int)$_POST['ak_id'] : 0;
    
    if ($ak_id === 0) {
        alert('API 키 ID가 유효하지 않습니다.');
    }
    
    // 새로운 API 시크릿 생성
    $api_secret = generate_api_secret();
    $api_secret_hash = password_hash($api_secret, PASSWORD_DEFAULT);
    sql_query("ALTER TABLE {$g5['api_key_table']} MODIFY ak_secret varchar(255) NOT NULL", true);
    
    // 데이터베이스 업데이트
    $sql = "
        UPDATE {$g5['api_key_table']}
        SET ak_secret = '" . sql_real_escape_string($api_secret_hash) . "'
        WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
    ";
    
    sql_query($sql);
    
    alert('API 시크릿이 재발급되었습니다. 새로운 시크릿: ' . $api_secret, './keylist.php');
}

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['api_key_table']}";
$total_count = sql_fetch($sql)['cnt'];
$total_pages = ceil($total_count / $limit);

// API 키 목록 조회
$sql = "
    SELECT
        ak.*,
        (SELECT COUNT(*) FROM {$g5['api_ip_table']} WHERE ak_id = ak.ak_id) as ip_count,
        (SELECT COUNT(*) FROM {$g5['api_log_table']} WHERE ak_id = ak.ak_id) as log_count
    FROM {$g5['api_key_table']} ak
    ORDER BY ak_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./keylist.php" class="ov_list">API 키 목록</a>
    <span class="btn_ov01"><span class="ov_txt">등록된 API 키</span><span class="ov_num"> <?php echo number_format($total_count); ?>개</span></span>
</div>

<form name="fapi_key" id="fapi_key" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="action" value="create">
    
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>API 키 생성</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="name">API 키 이름</label></th>
                    <td>
                        <input type="text" name="name" id="name" class="frm_input required" size="50" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="type">API 타입</label></th>
                    <td>
                        <select name="type" id="type" class="frm_input">
                            <option value="all">전체 (SNS + PG)</option>
                            <option value="sns">SNS 로그인</option>
                            <option value="pg">PG 결제</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="expired_at">만료일시</label></th>
                    <td>
                        <input type="datetime-local" name="expired_at" id="expired_at" class="frm_input">
                        <span class="frm_info">만료일시를 지정하지 않으면 무기한 유효합니다.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <a href="./keylist.php" class="btn btn_02">목록</a>
        <input type="submit" value="API 키 생성" class="btn_submit btn">
    </div>
</form>

<div class="local_desc02 local_desc">
    <p>등록된 API 키를 관리할 수 있습니다.</p>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>API 키 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">이름</th>
                <th scope="col">API Key</th>
                <th scope="col">Type</th>
                <th scope="col">상태</th>
                <th scope="col">IP 화이트리스트</th>
                <th scope="col">로그</th>
                <th scope="col">마지막 사용</th>
                <th scope="col">만료일시</th>
                <th scope="col">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['ak_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['ak_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($row['ak_key']); ?></code></td>
                        <td><?php echo strtoupper(htmlspecialchars($row['ak_type'])); ?></td>
                        <td>
                            <?php if ($row['ak_status'] == 1) { ?>
                                <span class="btn_confirm btn">활성</span>
                            <?php } else { ?>
                                <span class="btn_confirm" style="background:#999;">비활성</span>
                            <?php } ?>
                        </td>
                        <td class="td_num"><?php echo number_format($row['ip_count']); ?>개</td>
                        <td class="td_num"><?php echo number_format($row['log_count']); ?>개</td>
                        <td><?php echo $row['ak_last_used'] ? $row['ak_last_used'] : '-'; ?></td>
                        <td><?php echo $row['ak_expired_at'] ? $row['ak_expired_at'] : '무기한'; ?></td>
                        <td class="td_mng">
                            <a href="./keyform.php?ak_id=<?php echo $row['ak_id']; ?>" class="btn btn_03">수정</a>
                            <button type="button" class="btn btn_02" onclick="confirm_delete('<?php echo $row['ak_id']; ?>');">삭제</button>
                            <button type="button" class="btn btn_03" onclick="regenerate_secret('<?php echo $row['ak_id']; ?>');">시크릿 재발급</button>
                            <a href="./iplist.php?ak_id=<?php echo $row['ak_id']; ?>" class="btn btn_03">IP 관리</a>
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
echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_pages, $_SERVER['PHP_SELF'] . '?page=');
?>

<script>
function confirm_delete(ak_id) {
    if (confirm('정말 삭제하시겠습니까?')) {
        document.getElementById('fapi_key').action.value = 'delete';
        var ak_id_input = document.createElement('input');
        ak_id_input.type = 'hidden';
        ak_id_input.name = 'ak_id';
        ak_id_input.value = ak_id;
        document.getElementById('fapi_key').appendChild(ak_id_input);
        document.getElementById('fapi_key').submit();
    }
}

function regenerate_secret(ak_id) {
    if (confirm('API 시크릿을 재발급하시겠습니까? 재발급 후 새로운 시크릿이 표시됩니다.')) {
        document.getElementById('fapi_key').action.value = 'regenerate';
        var ak_id_input = document.createElement('input');
        ak_id_input.type = 'hidden';
        ak_id_input.name = 'ak_id';
        ak_id_input.value = ak_id;
        document.getElementById('fapi_key').appendChild(ak_id_input);
        document.getElementById('fapi_key').submit();
    }
}
</script>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
