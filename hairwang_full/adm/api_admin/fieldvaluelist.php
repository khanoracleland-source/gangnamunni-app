<?php
/**
 * 필드 값 조회 페이지
 * API 필드 값 정보를 조회하는 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 검색 조건
$af_id = isset($_GET['af_id']) ? (int)$_GET['af_id'] : 0;
$al_id = isset($_GET['al_id']) ? (int)$_GET['al_id'] : 0;
$sl_id = isset($_GET['sl_id']) ? (int)$_GET['sl_id'] : 0;
$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;
$fr_date = isset($_GET['fr_date']) ? trim($_GET['fr_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// WHERE 조건 구성
$where = array();

if ($af_id) {
    $where[] = "fv.af_id = '" . sql_real_escape_string($af_id) . "'";
}

if ($al_id) {
    $where[] = "fv.al_id = '" . sql_real_escape_string($al_id) . "'";
}

if ($sl_id) {
    $where[] = "fv.sl_id = '" . sql_real_escape_string($sl_id) . "'";
}

if ($po_id) {
    $where[] = "fv.po_id = '" . sql_real_escape_string($po_id) . "'";
}

if ($fr_date) {
    $where[] = "fv.fv_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
}

if ($to_date) {
    $where[] = "fv.fv_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['api_field_values_table']} fv $where_sql";
$total_count = sql_fetch($sql)['cnt'];
$total_pages = ceil($total_count / $limit);

// 필드 값 목록 조회
$sql = "
    SELECT
        fv.*,
        af.af_name,
        af.af_code,
        af.af_type,
        af.af_category,
        sl.sl_provider,
        sl.sl_action,
        po.po_external_order_id
    FROM {$g5['api_field_values_table']} fv
    LEFT JOIN {$g5['api_fields_table']} af ON fv.af_id = af.af_id
    LEFT JOIN {$g5['sns_api_log_table']} sl ON fv.sl_id = sl.sl_id
    LEFT JOIN {$g5['pg_api_orders_table']} po ON fv.po_id = po.po_id
    $where_sql
    ORDER BY fv.fv_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);

// 필드 목록 조회 (드롭다운용)
$field_list_sql = "SELECT af_id, af_name, af_category FROM {$g5['api_fields_table']} WHERE af_status = 1 ORDER BY af_category, af_order ASC";
$field_list_result = sql_query($field_list_sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./fieldlist.php" class="ov_list">필드 정의 목록</a>
    <span class="btn_ov01"><span class="ov_txt">총 필드 값</span><span class="ov_num"> <?php echo number_format($total_count); ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" method="get" class="local_sch01 local_sch">
    <label for="af_id" class="sound_only">필드</label>
    <select name="af_id" id="af_id">
        <option value="">전체</option>
        <?php
        $current_category = '';
        while ($field = sql_fetch_array($field_list_result)) {
            if ($current_category !== $field['af_category']) {
                $current_category = $field['af_category'];
                ?>
                <optgroup label="<?php echo strtoupper(htmlspecialchars($current_category)); ?>">
                <?php
            }
            ?>
            <option value="<?php echo $field['af_id']; ?>" <?php echo $af_id == $field['af_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($field['af_category']); ?> - <?php echo htmlspecialchars($field['af_name']); ?>
            </option>
            <?php
        }
        ?>
    </select>
    
    <label for="sl_id" class="sound_only">SNS 로그 ID</label>
    <input type="text" name="sl_id" id="sl_id" value="<?php echo htmlspecialchars($sl_id); ?>" class="frm_input" size="10" placeholder="SNS 로그 ID">
    
    <label for="po_id" class="sound_only">PG 주문 ID</label>
    <input type="text" name="po_id" id="po_id" value="<?php echo htmlspecialchars($po_id); ?>" class="frm_input" size="10" placeholder="PG 주문 ID">
    
    <label for="fr_date" class="sound_only">시작일</label>
    <input type="text" name="fr_date" id="fr_date" value="<?php echo htmlspecialchars($fr_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <label for="to_date" class="sound_only">종료일</label>
    <input type="text" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <input type="submit" value="검색" class="btn_submit">
</form>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>필드 값 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">필드 이름</th>
                <th scope="col">필드 코드</th>
                <th scope="col">필드 타입</th>
                <th scope="col">카테고리</th>
                <th scope="col">필드 값</th>
                <th scope="col">SNS 로그</th>
                <th scope="col">PG 주문</th>
                <th scope="col">생성일</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['fv_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['af_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($row['af_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['af_type']); ?></td>
                        <td><?php echo strtoupper(htmlspecialchars($row['af_category'])); ?></td>
                        <td>
                            <?php
                            $value = $row['fv_value'];
                            // JSON 형식인 경우 예쁘게 표시
                            if (json_decode($value)) {
                                $value = json_encode(json_decode($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                ?>
                                <pre style="background:#f5f5f5; padding:5px; border:1px solid #ddd; max-height:100px; overflow:auto;"><?php echo htmlspecialchars($value); ?></pre>
                                <?php
                            } else {
                                ?>
                                <?php echo htmlspecialchars($value); ?>
                                <?php
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($row['sl_id']) { ?>
                                <?php echo $row['sl_id']; ?>
                                <?php if ($row['sl_provider']) { ?>
                                    <br><small>(<?php echo strtoupper(htmlspecialchars($row['sl_provider'])); ?>)</small>
                                <?php } ?>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($row['po_id']) { ?>
                                <?php echo $row['po_id']; ?>
                                <?php if ($row['po_external_order_id']) { ?>
                                    <br><small>(<?php echo htmlspecialchars(substr($row['po_external_order_id'], 0, 20)); ?>...)</small>
                                <?php } ?>
                            <?php } else { ?>
                                -
                            <?php } ?>
                        </td>
                        <td><?php echo $row['fv_created_at']; ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="9" class="empty_table">데이터가 없습니다.</td>
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

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
