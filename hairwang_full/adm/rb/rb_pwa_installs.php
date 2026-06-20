<?php
// /adm/rb/rb_pwa_installs.php
$sub_menu = '000764';
include_once('./_common.php');
auth_check_menu($auth, $sub_menu, "w");

if (!function_exists('help')) {
    function help($str){ return '<div class="tbl_frm01_help">'.$str.'</div>'; }
}
function rb_admin_token_input_html() {
    if (function_exists('get_admin_token')) {
        $t = get_admin_token();
        if (stripos($t, '<input') !== false) return $t;
        return '<input type="hidden" name="token" value="'.htmlspecialchars($t, ENT_QUOTES).'">';
    }
    if (function_exists('get_token')) {
        $t = get_token();
        return '<input type="hidden" name="token" value="'.htmlspecialchars($t, ENT_QUOTES).'">';
    }
    return '';
}

$g5['title'] = 'PWA 알림수신 현황';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 검색 파라미터
$sfl = isset($_GET['sfl']) ? preg_replace('/[^a-z0-9_]/i','', $_GET['sfl']) : 'mb_id';
$stx = isset($_GET['stx']) ? trim($_GET['stx']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$rows = 20;

// where
$where = array();
$where[] = "1";
if ($stx !== '') {
    $es = sql_real_escape_string($stx);
    if ($sfl === 'mb_id')        $where[] = "mb_id LIKE '%{$es}%'";
    else if ($sfl === 'endpoint')$where[] = "endpoint LIKE '%{$es}%'";
    else if ($sfl === 'ip')      $where[] = "ip LIKE '%{$es}%'";
}
$sql_where = ' WHERE '.implode(' AND ', $where);

// 카운트
$cnt_row = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_pwa_subscriptions {$sql_where}");
$total_count = (int)$cnt_row['cnt'];
$total_page  = ($total_count > 0) ? ceil($total_count / $rows) : 1;
$from_record = ($page - 1) * $rows;

// 목록
$sql = "SELECT id, mb_id, endpoint, ip, ua, installed, created_at, updated_at
        FROM rb_pwa_subscriptions
        {$sql_where}
        ORDER BY id DESC
        LIMIT {$from_record}, {$rows}";
$result = sql_query($sql);

// query string
$qstr = 'sfl='.$sfl.'&amp;stx='.urlencode($stx);

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';
?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">총</span><span class="ov_num">  <?php echo number_format($total_count); ?>개</span></span>
</div>

<form name="flist" class="local_sch01 local_sch" method="get">
    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="mb_id" <?php echo $sfl==='mb_id'?'selected':''; ?>>회원ID</option>
        <option value="endpoint" <?php echo $sfl==='endpoint'?'selected':''; ?>>엔드포인트</option>
        <option value="ip" <?php echo $sfl==='ip'?'selected':''; ?>>IP</option>
    </select>
    <label for="stx" class="sound_only">검색어</label>
    <input type="text" name="stx" id="stx" value="<?php echo htmlspecialchars($stx,ENT_QUOTES); ?>" class="frm_input">
    <input type="submit" value="검색" class="btn_submit">
</form>

<form name="finst" method="post" action="./rb_pwa_installs_update.php" onsubmit="return finst_submit(this);" autocomplete="off">
<?php echo rb_admin_token_input_html(); ?>
<input type="hidden" name="qstr" value="<?php echo $qstr; ?>">
<input type="hidden" name="page" value="<?php echo (int)$page; ?>">

<div class="tbl_head01 tbl_wrap">
<table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
        <tr>
            <th scope="col">
                <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
                <label for="chkall" class="sound_only">전체</label>
            </th>
            <th scope="col">닉네임</th>
            <th scope="col">ID</th>
            <th scope="col">엔드포인트</th>
            <th scope="col">IP</th>
            <th scope="col">수신여부</th>
            <th scope="col">등록일시</th>
            <th scope="col">수정일시</th>
        </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row = sql_fetch_array($result); $i++) {
        $endpoint_short = htmlspecialchars(mb_strimwidth($row['endpoint'], 0, 50, '...','UTF-8'), ENT_QUOTES);
        if(!empty($row['mb_id'])) { 
            $mbx = get_member($row['mb_id']);
            $mb_nick = get_sideview($row['mb_id'], get_text($mbx['mb_nick']), $mbx['mb_email'], $mbx['mb_homepage']);
        } else { 
            $mb_nick = '<span style="color:#999">비회원</span>';
        }
        
        $bg = 'bg'.($i%2);
    ?>
        <tr class="<?php echo $bg; ?>">
            <td class="td_chk">
                <input type="checkbox" name="chk[]" value="<?php echo $row['id']; ?>">
            </td>
            <td class="td_name sv_use" nowrap>
                <div><?php echo $mb_nick ?></div>
            </td>
            <td class="" nowrap>
                <?php echo htmlspecialchars($row['mb_id'],ENT_QUOTES); ?>
            </td>
            <td class="td_left" title="<?php echo htmlspecialchars($row['endpoint'],ENT_QUOTES); ?>">
            
            <?php echo $endpoint_short; ?>
            </td>
            <td class="" nowrap><?php echo htmlspecialchars($row['ip'],ENT_QUOTES); ?></td>
            <td nowrap><?php echo (int)$row['installed'] ? '수신':'<span style="color:#999">수신거부</span>'; ?></td>
            <td class="td_datetime" nowrap><?php echo htmlspecialchars($row['created_at'],ENT_QUOTES); ?></td>
            <td class="td_datetime" nowrap><?php echo htmlspecialchars($row['updated_at'],ENT_QUOTES); ?></td>
        </tr>
    <?php } if ($i===0) { ?>
        <tr><td colspan="8" class="empty_table"><span>자료가 없습니다.</span></td></tr>
    <?php } ?>
    </tbody>
</table>
</div>

<div class="btn_fixed_top">
    <input type="submit" name="act_button" value="선택삭제" class="btn btn_02" onclick="document.pressed=this.value">
    <input type="submit" name="act_button" value="전체삭제" class="btn btn_02" onclick="document.pressed=this.value">
</div>
</form>

<?php
echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?{$qstr}&amp;page=");
?>

<script>
// 클릭된 submit 버튼을 추적 (버튼 수정 없이)
(function(){
    var lastPressedEl = null;
    document.addEventListener('click', function(e){
        var btn = e.target && e.target.closest('form[name="finst"] input[type="submit"][name="act_button"]');
        if (btn) {
            lastPressedEl = btn;
            // 기존 코드에서 쓰는 값도 유지
            document.pressed = btn.value;
        }
    }, true);
    window.__getLastPressedEl = function(){ return lastPressedEl; };
})();

function finst_submit(f){
    // 비동기 확인 후 재제출할 때 무한루프 방지
    if (f.dataset.skipConfirm === '1') { f.dataset.skipConfirm = ''; return true; }

    var pressed = document.pressed || '';

    // 실제 눌린 버튼으로 제출하거나, 폴백으로 hidden 주입
    function doSubmitWithPressed(){
        f.dataset.skipConfirm = '1';
        var btn = (typeof window.__getLastPressedEl === 'function') ? window.__getLastPressedEl() : null;

        if (typeof f.requestSubmit === 'function' && btn) {
            // 클릭된 버튼의 name/value를 포함해서 제출됨
            f.requestSubmit(btn);
            return;
        }

        // requestSubmit을 못 쓰는 경우: hidden으로 act_button 값을 보장
        var hid = f.querySelector('input[type="hidden"][name="act_button"]');
        if (!hid) {
            hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = 'act_button';
            f.appendChild(hid);
        }
        hid.value = pressed || (btn && btn.value) || '';
        f.submit();
    }

    if (pressed === '선택삭제') {
        var chks = f.querySelectorAll('input[name="chk[]"]:checked');
        if (chks.length === 0) { alert('삭제할 항목을 선택하세요.'); return false; }

        var msg = '선택한 설치 정보를 삭제하시겠습니까?';
        if (typeof rb_confirm === 'function') {
            rb_confirm(msg).then(function(confirmed){
                if (confirmed) doSubmitWithPressed();
            });
            return false; // 비동기 확인이므로 기본 제출 막기
        } else {
            // 동기 confirm의 경우 브라우저가 자동으로 클릭버튼 값 포함해서 제출
            return confirm(msg);
        }
    }

    if (pressed === '전체삭제') {
        var msg2 = '설치 정보를 전체 삭제합니다.\n삭제된 데이터는 복구되지 않습니다.';
        if (typeof rb_confirm === 'function') {
            rb_confirm(msg2).then(function(confirmed){
                if (confirmed) doSubmitWithPressed();
            });
            return false;
        } else {
            return confirm(msg2);
        }
    }

    return true;
}
</script>


<?php include_once(G5_ADMIN_PATH.'/admin.tail.php'); ?>
