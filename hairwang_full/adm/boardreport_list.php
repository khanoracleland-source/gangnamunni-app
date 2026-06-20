<?php
$sub_menu = "000900";
require_once './_common.php';
include_once(G5_ADMIN_PATH.'/boardreport.lib.php');

auth_check_menu($auth, $sub_menu, 'w');

// 페이징
$rows = isset($config['cf_page_rows']) ? (int)$config['cf_page_rows'] : 20; 

// ----------------------
// 공통 (테이블 보장: boardreport.lib.php에서 수행됨)
// ----------------------
//$report_table = $g5['board_table'].'_report';

// ----------------------
// 액션 처리 (AJAX)
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act'])) {
    if (function_exists('ob_get_length') && ob_get_length()) { @ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $act        = trim($_POST['act']);
    $rp_ids     = array_map('intval', (array)($_POST['rp_ids'] ?? []));
    $bo_table   = trim($_POST['bo_table'] ?? '');
    $target_id  = (int)($_POST['target_id'] ?? 0);
    $comment_id = (int)($_POST['comment_id'] ?? 0);

    if (!function_exists('check_admin_token') || !check_admin_token()) {
        echo json_encode(['ok'=>false, 'message'=>'잘못된 접근입니다.(token)']); exit;
    }

    if (!$rp_ids && !in_array($act, ['lock','unlock'], true)) {
        echo json_encode(['ok'=>false, 'message'=>'대상 선택 필요']); exit;
    }

    try {
        if ($act === 'mark_done' || $act === 'mark_undone') {
            $flag = ($act === 'mark_done') ? 1 : 0;
            $in   = implode(',', $rp_ids);
            if ($in === '') { echo json_encode(['ok'=>false, 'message'=>'대상 선택 필요']); exit; }
            sql_query("UPDATE `{$report_table}` SET rp_status = '{$flag}' WHERE rp_id IN ({$in})");
            echo json_encode(['ok'=>true, 'message'=>'상태가 변경되었습니다.']); exit;
        }

        if ($act === 'delete_reports') {
            $in = implode(',', $rp_ids);
            if ($in === '') { echo json_encode(['ok'=>false, 'message'=>'대상 선택 필요']); exit; }
            sql_query("DELETE FROM `{$report_table}` WHERE rp_id IN ({$in})");
            echo json_encode(['ok'=>true, 'message'=>'신고 내역이 삭제되었습니다.']); exit;
        }

        if ($act === 'lock' || $act === 'unlock') {
            if ($bo_table === '' || $target_id <= 0) {
                echo json_encode(['ok'=>false, 'message'=>'대상이 올바르지 않습니다.']); exit;
            }
            if (!preg_match('/^[A-Za-z0-9_]+$/', $bo_table)) {
                echo json_encode(['ok'=>false, 'message'=>'잘못된 게시판 식별자']); exit;
            }

            $write_table = $g5['write_prefix'].$bo_table;
            if (!sql_query("DESCRIBE `{$write_table}`", false)) {
                echo json_encode(['ok'=>false, 'message'=>'대상 테이블을 찾을 수 없습니다.']); exit;
            }

            $to_id = ($comment_id > 0) ? $comment_id : $target_id;
            $val   = ($act === 'lock') ? '잠금' : '';
            sql_query("UPDATE `{$write_table}` SET wr_report = '{$val}' WHERE wr_id = '{$to_id}'");
            echo json_encode(['ok'=>true, 'message'=> ($act==='lock' ? '잠금' : '해제').' 처리되었습니다.']); exit;
        }

        echo json_encode(['ok'=>false, 'message'=>'알 수 없는 요청']); exit;

    } catch(Exception $e) {
        echo json_encode(['ok'=>false, 'message'=>'오류: '.$e->getMessage()]); exit;
    }
}

// ----------------------
// 목록 화면
// ----------------------
$sch_bo_table = trim($_GET['bo_table'] ?? '');
$sch_status   = $_GET['status'] ?? '';
$sch_kw       = trim($_GET['kw'] ?? '');
$sch_sdate    = trim($_GET['sdate'] ?? '');
$sch_edate    = trim($_GET['edate'] ?? '');

$where = [];
if ($sch_bo_table !== '') $where[] = "r.bo_table = '".g5_sql_esc($sch_bo_table)."'";
if ($sch_status !== '' && ($sch_status==='0' || $sch_status==='1')) $where[] = "r.rp_status = '".g5_sql_esc($sch_status)."'";
if ($sch_kw !== '') {
    $kw = trim($sch_kw);
    if (ctype_digit($kw)) {
        // 숫자만 입력된 경우 wr_id, comment_id에 대해 정확 검색
        $where[] = "(r.wr_id = '{$kw}' OR r.comment_id = '{$kw}')";
    } else {
        // 문자열 검색 (사유/메모/아이디)
        $kw = strtr($kw, ['\\' => '\\\\', '%' => '\%', '_' => '\_']);
        $kw = g5_sql_esc($kw);
        $where[] = "(r.rp_reason LIKE '%{$kw}%' ESCAPE '\\' 
                  OR r.rp_memo   LIKE '%{$kw}%' ESCAPE '\\' 
                  OR r.mb_id     LIKE '%{$kw}%' ESCAPE '\\')";
    }
}
if ($sch_sdate !== '' && $sch_edate !== '') {
    $s = g5_sql_esc($sch_sdate.' 00:00:00');
    $e = g5_sql_esc($sch_edate.' 23:59:59');
    $where[] = "(r.rp_datetime BETWEEN '{$s}' AND '{$e}')";
}
$where_sql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$from = ($page - 1) * $rows;

$row  = sql_fetch("SELECT COUNT(*) AS cnt FROM `{$report_table}` r {$where_sql}");
$total_count = (int)$row['cnt'];

$sql = "
SELECT r.*, 
       b.bo_subject,
       CASE WHEN r.comment_id > 0 THEN 1 ELSE 0 END AS is_comment
FROM `{$report_table}` r
LEFT JOIN `{$g5['board_table']}` b ON b.bo_table = r.bo_table
{$where_sql}
ORDER BY r.rp_id DESC
LIMIT {$from}, {$rows}
";
$qry = sql_query($sql);

$total_page = (int)ceil($total_count / $rows);
$qstr = http_build_query([
  'bo_table'=>$sch_bo_table, 'status'=>$sch_status, 'kw'=>$sch_kw,
  'sdate'=>$sch_sdate, 'edate'=>$sch_edate
]);

$boards = [];
$q_bo   = sql_query("SELECT bo_table, bo_subject FROM {$g5['board_table']} ORDER BY bo_subject ASC");
while ($bo = sql_fetch_array($q_bo)) $boards[] = $bo;

$g5['title'] = '신고 내역 관리';
include_once(G5_ADMIN_PATH.'/admin.head.php');
require_once G5_PLUGIN_PATH . '/jquery-ui/datepicker.php';

add_stylesheet('<link rel="stylesheet" href="'.G5_CSS_URL.'/report.css">', 0);
?>


<script>
$(function(){
  var opt = {
    changeMonth:true, changeYear:true,
    dateFormat:"yy-mm-dd",
    showButtonPanel:true, yearRange:"c-99:c+99"
  };
  $("#sdate").datepicker($.extend({}, opt, {
    onClose:function(sel){ if(sel) $("#edate").datepicker("option","minDate", sel); }
  }));
  $("#edate").datepicker($.extend({}, opt, {
    onClose:function(sel){ if(sel) $("#sdate").datepicker("option","maxDate", sel); }
  }));
});
</script>

<div class="report-admin">

	<form class="sch" method="get">
		<input type="hidden" name="dir" value="<?php echo get_text($_GET['dir'] ?? '');?>">
		<input type="hidden" name="pid" value="<?php echo get_text($_GET['pid'] ?? '');?>">
		<label class="sch-item">게시판
			<select name="bo_table" class="frm_input">
				<option value="">전체</option>
				<?php foreach($boards as $bo): ?>
				<option value="<?php echo $bo['bo_table'];?>"
				<?php echo ($sch_bo_table === $bo['bo_table']) ? 'selected' : ''; ?>>
				<?php echo $bo['bo_subject'];?> (<?php echo $bo['bo_table'];?>)
				</option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="sch-item">상태
			<select name="status">
				<option value="" <?php echo $sch_status===''?'selected':''; ?>>전체</option>
				<option value="0" <?php echo $sch_status==='0'?'selected':''; ?>>미처리</option>
				<option value="1" <?php echo $sch_status==='1'?'selected':''; ?>>처리</option>
			</select>
		</label>
		<label class="sch-item">기간
  			<div class="period-range">
				<input type="text" name="sdate" value="<?php echo get_text($sch_sdate);?>" id="sdate" class="frm_input" size="11" maxlength="10">
				<span class="dash">~</span>
				<input type="text" name="edate" value="<?php echo get_text($sch_edate);?>" id="edate" class="frm_input" size="11" maxlength="10">
			</div>
		</label>
		<label class="sch-item grow">키워드
			<input type="text" name="kw" value="<?php echo get_text($sch_kw);?>" class="frm_input" placeholder="사유/메모/아이디">
		</label>
		<button type="submit" class="btn btn-search">검색</button>
	</form>

	<form id="reportForm" method="post" onsubmit="return false;">
		<?php echo get_admin_token_field_safe(); ?>
		<div class="report-admin-actions">
			<div class="report-admin-total">
				총 <?php echo number_format($total_count);?>건
			</div>

			<div class="report-admin-actions-btns">
				<button type="button" class="btn" onclick="bulkAction('mark_done')">선택 처리완료</button>
				<button type="button" class="btn" onclick="bulkAction('mark_undone')">선택 미처리</button>
				<button type="button" class="btn" onclick="bulkAction('delete_reports')" style="color:#ef4444">선택 신고삭제</button>
			</div>
		</div>

		<table>
			<colgroup>
				<col style="width:30px;">
				<col style="width:45px;">
				<col style="width:110px;">
				<col style="width:135px;">
				<col style="width:135px;">
				<col style="width:100px;">
				<col style="">
				<col style="width:100px;">
				<col style="width:100px;">
				<col style="width:70px;">
				<col style="width:100px;">
			</colgroup>
			<thead>
				<tr>
					<th><input type="checkbox" id="chkall" onclick="toggleAll(this)"></th>
					<th>번호</th>
					<th>게시판</th>
					<th>대상</th>
					<th>신고자</th>
					<th>사유</th>
					<th>메모</th>
					<th>일시</th>
					<th>IP</th>
					<th>상태</th>
					<th>잠금</th>
				</tr>
			</thead>
			<tbody>
			<?php 
			for($i=0; $row=sql_fetch_array($qry); $i++):
				$is_cmt = (int)$row['is_comment'] === 1;
				$bo  = get_text($row['bo_table']);
				$bid = (int)$row['wr_id'];
				$cid = (int)$row['comment_id'];
				$view_url = G5_BBS_URL."/board.php?bo_table={$bo}&wr_id={$bid}".($is_cmt ? "#c_{$cid}" : "");
				// 잠금 여부
				$write_table = $g5['write_prefix'].$bo;
				$target_id   = $is_cmt ? $cid : $bid;
				$wr = sql_fetch("SELECT wr_report FROM `{$write_table}` WHERE wr_id = '{$target_id}'");
				$locked = ($wr && $wr['wr_report'] === '잠금');

				// 가상번호 계산
				$virtual_num = $total_count - ($from + $i);
				?>
				<tr>
					<td style="text-align:center;"><input type="checkbox" name="rp_ids[]" value="<?php echo $row['rp_id'];?>"></td>
					<td style="text-align:center;"><?php echo $virtual_num;?></td>
					<td title="<?php echo get_text($row['bo_subject']);?>"><?php echo $bo;?></td>
					<td>
						<a href="<?php echo $view_url;?>" target="_blank"><?php echo $is_cmt ? '댓글' : '글'; ?> 보기</a>
						<div style="font-size:11px;color:#6b7280">wr_id: <?php echo $bid;?><?php if($is_cmt) echo " / cmt: {$cid}";?></div>
					</td>
					<td><?php echo get_text($row['mb_id'] ?: '비회원');?></td>
					<td><?php echo nl2br(get_text($row['rp_reason']));?></td>
					<td><?php echo nl2br(get_text($row['rp_memo']));?></td>
					<td style="font-size:11.5px;"><?php echo $row['rp_datetime'];?></td>
					<td style="font-size:11.5px;"><?php echo get_text($row['rp_ip']);?></td>
					<td style="text-align:center;">
						<?php if ($row['rp_status']) { ?>
						<span class="badge green">처리</span>
						<?php } else { ?>
						<span class="badge red">미처리</span>
						<?php } ?>
					</td>
					<td class="actions">
						<span class="lock-wrap">
							<?php if ($locked) { ?>
								<span class="badge gray">잠금</span>
								<button type="button" onclick="lockAction('unlock','<?php echo $bo;?>','<?php echo $bid;?>','<?php echo $cid;?>')">해제</button>
							<?php } else { ?>
								<span class="badge green">정상</span>
								<button type="button" onclick="lockAction('lock','<?php echo $bo;?>','<?php echo $bid;?>','<?php echo $cid;?>')">잠금</button>
							<?php } ?>
						</span>
					</td>
				</tr>
				<?php endfor; if($i===0): ?>
					<tr><td colspan="11" style="text-align:center;color:#6b7280">신고 내역이 없습니다.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</form>

	<div class="report-admin-footer">
		<div class="report-admin-center">
			<?php
			$write_pages = isset($config['cf_write_pages']) ? (int)$config['cf_write_pages'] : 10;
			$list_page   = $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&page=';
			echo get_paging($write_pages, $page, $total_page, $list_page);
			?>
		</div>
	</div>
</div>

<script>
function toggleAll(cb){
  document.querySelectorAll('input[name="rp_ids[]"]').forEach(ch=> ch.checked = cb.checked);
}

async function bulkAction(act){
  const ids = Array.from(document.querySelectorAll('input[name="rp_ids[]"]:checked')).map(x=>x.value);
  if (!ids.length) { alert('대상을 선택하세요.'); return; }
  if (act==='delete_reports' && !confirm('선택 신고내역을 삭제하시겠습니까?')) return;

  const fd = new FormData();
  fd.append('act', act);
  ids.forEach(id => fd.append('rp_ids[]', id));

  const tokenInput = document.querySelector('input[name="token"]');
  if (tokenInput) fd.append('token', tokenInput.value);

  const res = await fetch(location.href, {
    method: 'POST',
    body: fd,
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  const data = await res.json();
  alert(data.message || (data.ok?'완료':'실패'));
  if (data.ok) location.reload();
}

async function lockAction(act, bo_table, wr_id, comment_id){
  if (!confirm((act==='lock'?'잠금':'해제')+' 처리하시겠습니까?')) return;

  const fd = new FormData();
  fd.append('act', act);
  fd.append('bo_table', bo_table);
  fd.append('target_id', wr_id);
  fd.append('comment_id', comment_id);

  const tokenInput = document.querySelector('input[name="token"]');
  if (tokenInput) fd.append('token', tokenInput.value);

  const res = await fetch(location.href, {
    method: 'POST',
    body: fd,
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  const data = await res.json();
  alert(data.message || (data.ok?'완료':'실패'));
  if (data.ok) location.reload();
}
</script>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');