<?php
// /adm/rb/rb_pwa_logs.php
$sub_menu = '000763';
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

$g5['title'] = 'PWA 알림발송 이력';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 검색
$sfl = isset($_GET['sfl']) ? preg_replace('/[^a-z0-9_]/i','', $_GET['sfl']) : 'title';
$stx = isset($_GET['stx']) ? trim($_GET['stx']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$rows = 20;

// where
$where = array(); $where[] = "1";
if ($stx !== '') {
    $es = sql_real_escape_string($stx);
    if ($sfl==='title')        $where[] = "title LIKE '%{$es}%'";
    else if ($sfl==='reg_mb_id') $where[] = "reg_mb_id LIKE '%{$es}%'";
    else if ($sfl==='target')    $where[] = "target LIKE '%{$es}%'";
}
$sql_where = ' WHERE '.implode(' AND ', $where);

// count
$cnt_row = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_pwa_push_log {$sql_where}");
$total_count = (int)$cnt_row['cnt'];
$total_page  = ($total_count>0) ? ceil($total_count / $rows) : 1;
$from_record = ($page-1)*$rows;

// list
$sql = "SELECT id, kind, target, target_memo, title, url, image, total_cnt, succ_cnt, fail_cnt, reg_mb_id, reg_dt
        FROM rb_pwa_push_log
        {$sql_where}
        ORDER BY id DESC
        LIMIT {$from_record}, {$rows}";
$result = sql_query($sql);

$qstr = 'sfl='.$sfl.'&amp;stx='.urlencode($stx);
$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

// 종류(kind) 한글 라벨
function rb_pwa_kind_kor($kind){
    switch (strtolower((string)$kind)) {
        case 'test':   return '테스트';
        case 'manual': return '수동';
        case 'post':   return '자동';
        default:       return (string)$kind; // 알 수 없는 값은 원문 노출
    }
}

// 대상(target, target_memo) 한글 라벨 (레벨 = 같음)
function rb_pwa_target_kor($target, $memo){
    $t = strtolower((string)$target);
    $m = trim((string)$memo ?? '');

    if ($t === 'all') return '전체';

    if ($t === 'member') {
        // 특정 회원 아이디 추출 (ids=..., mb_ids=..., 혹은 콤마리스트)
        $list = '';
        if ($m !== '' && preg_match('/\b(?:ids?|mb_ids?)\s*=\s*([^\r\n#;]+)/i', $m, $mm)) {
            $list = trim($mm[1]);
        } else if ($m !== '' && (strpos($m, ',') !== false ||
                   preg_match('/^[A-Za-z0-9_.\-]+(?:\s*,\s*[A-Za-z0-9_.\-]+)+$/', $m))) {
            // 라벨 없이 콤마 나열된 경우
            $list = $m;
        }

        if ($list !== '') {
            $ids = array_values(array_unique(array_filter(array_map('trim', preg_split('/\s*,\s*/', $list)))));
            if (count($ids) === 1) return ''.$ids[0].'';
            return ''.count($ids).'명';
        }

        // 레벨 지정
        if (preg_match('/level\s*=\s*(\d+)/i', $m, $lm) || preg_match('/(\d+)\s*레벨/i', $m, $lm)) {
            return ''.((int)$lm[1]).'레벨';
        }

        return '회원대상';
    }

    return (string)$target;
}

?>

<div class="local_ov01 local_ov">
    <?php echo $listall; ?>
    <span class="btn_ov01"><span class="ov_txt">총</span><span class="ov_num">  <?php echo number_format($total_count); ?>개</span></span>
</div>

<form name="flog" class="local_sch01 local_sch" method="get">
    <label for="sfl" class="sound_only">검색대상</label>
    <select name="sfl" id="sfl">
        <option value="title" <?php echo $sfl==='title'?'selected':''; ?>>제목</option>
        <option value="reg_mb_id" <?php echo $sfl==='reg_mb_id'?'selected':''; ?>>등록자</option>
        <option value="target" <?php echo $sfl==='target'?'selected':''; ?>>대상종류</option>
    </select>
    <label for="stx" class="sound_only">검색어</label>
    <input type="text" name="stx" id="stx" value="<?php echo htmlspecialchars($stx,ENT_QUOTES); ?>" class="frm_input">
    <input type="submit" value="검색" class="btn_submit">
</form>

<form name="flogs" method="post" action="./rb_pwa_logs_update.php" onsubmit="return flogs_submit(this);" autocomplete="off">
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
            <th scope="col">발송구분</th>
            <th scope="col">수신대상</th>
            <th scope="col">알림제목</th>
            <th scope="col">URL</th>
            <th scope="col">이미지</th>
            <th scope="col">성공/실패/전체</th>
            <th scope="col">발송자</th>
            <th scope="col">발송일시</th>
        </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row = sql_fetch_array($result); $i++) {
        $img = '';
        if (!empty($row['image'])) {
            $src = htmlspecialchars($row['image'], ENT_QUOTES);
            $img = '<img src="'.$src.'" alt="" style="height:30px;border-radius:4px">';
        }
        $cnt = (int)$row['succ_cnt'].' / '.(int)$row['fail_cnt'].' / '.(int)$row['total_cnt'];
    ?>
        <tr>
            <td class="td_chk"><input type="checkbox" name="chk[]" value="<?php echo $row['id']; ?>"></td>
            <td nowrap><?php echo htmlspecialchars(rb_pwa_kind_kor($row['kind']), ENT_QUOTES); ?></td>
            <td nowrap title="<?php echo htmlspecialchars($row['target_memo'],ENT_QUOTES); ?>">
                <?php echo htmlspecialchars(rb_pwa_target_kor($row['target'], $row['target_memo']), ENT_QUOTES); ?>
            </td>
            <td class="td_left" style="min-width:150px;"><?php echo htmlspecialchars($row['title'],ENT_QUOTES); ?></td>
            <td class="td_left"><?php echo htmlspecialchars($row['url'],ENT_QUOTES); ?></td>
            <td><?php echo $img; ?></td>
            <td nowrap><?php echo $cnt; ?></td>
            <td nowrap><?php echo htmlspecialchars($row['reg_mb_id'],ENT_QUOTES); ?></td>
            <td class="td_datetime" nowrap><?php echo htmlspecialchars($row['reg_dt'],ENT_QUOTES); ?></td>
        </tr>
    <?php } if ($i===0) { ?>
        <tr><td colspan="9" class="empty_table"><span>자료가 없습니다.</span></td></tr>
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
function flogs_submit(f){
    // rb_confirm 경로로 재제출 시 무한루프 방지
    if (f.dataset.skipConfirm === '1') { f.dataset.skipConfirm = ''; return true; }

    var pressed = document.pressed || '';
    var hasRbConfirm = (typeof window.rb_confirm === 'function');

    // 버튼 값 hidden 주입 (rb_confirm → submit() 경로에서 필요)
    function ensureActHidden(val){
        var hid = f.querySelector('input[type="hidden"][name="act_button"]');
        if (!hid) {
            hid = document.createElement('input');
            hid.type = 'hidden';
            hid.name = 'act_button';
            f.appendChild(hid);
        }
        hid.value = val;
    }

    // 선택삭제
    if (pressed === '선택삭제') {
        var chks = f.querySelectorAll('input[name="chk[]"]:checked');
        if (chks.length === 0) { alert('삭제할 항목을 선택하세요.'); return false; }

        var msg = '선택한 발송 이력을 삭제하시겠습니까?\n관련 큐 데이터도 함께 삭제됩니다.';
        if (hasRbConfirm) {
            rb_confirm(msg).then(function(confirmed){
                if (confirmed) {
                    ensureActHidden(pressed);     // ← 여기 추가
                    f.dataset.skipConfirm = '1';
                    f.submit();
                }
            });
            return false;
        } else {
            return confirm(msg);
        }
    }

    // 전체삭제
    if (pressed === '전체삭제') {
        var msg2 = '발송 이력을 전체 삭제합니다.\n관련 큐 데이터도 함께 삭제됩니다.\n삭제된 데이터는 복구되지 않습니다.';
        if (hasRbConfirm) {
            rb_confirm(msg2).then(function(confirmed){
                if (confirmed) {
                    ensureActHidden(pressed);     // ← 여기 추가
                    f.dataset.skipConfirm = '1';
                    f.submit();
                }
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
