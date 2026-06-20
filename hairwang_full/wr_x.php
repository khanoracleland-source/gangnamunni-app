<?php
include_once('./_common.php');
$g5[title] = "게시판 여분필드 추가"; 
include_once(G5_PATH.'/head.php');

//여분필드추가 wr_x 
if ($is_admin =='super') { 
	if($wr_start && $wr_end){
		//시작할번호 $wr_start
		//마지막번호 $wr_end 
		
		if($board_id){
			$board_qry = " where bo_table = '{$board_id}' ";
		}
		$sql = "select * from {$g5['board_table']}".$board_qry;
		$result = sql_query($sql);
		for ($i=0; $row=sql_fetch_array($result); $i++) {
			$write_table = $g5['write_prefix'] . $row['bo_table']; // 게시판 테이블명
			for ($k=$wr_start; $k<=$wr_end; $k++) { 
				$cols = sql_fetch(" SHOW COLUMNS FROM {$write_table} LIKE 'wr_{$k}' ");
				if(!isset($cols)) {
					sql_query(" ALTER TABLE `{$write_table}` ADD `wr_{$k}` varchar(255) NOT NULL DEFAULT '' "); 
				}
			}
		}
		alert('여분필드를 추가했습니다.','./wr_x.php');
	}
} else { 
	alert_close("최고관리자만 접속 가능합니다."); 
}

?>
<style>
.wr_form_btn{display:inline-block; padding:0 10px; line-height:38px; background:#4158D1; color:#fff; border:0;}
</style>
<div class="wr_form_box">
	<form name="colwrite" id="colwrite" method="post" enctype="multipart/form-data" autocomplete="off" style="width:100%;">
		<div class="tbl_frm01 tbl_wrap">
        <table>
        <tr>
            <th scope="row" style="width:20%;" ><label for="wr_start">wr_시작번호<strong class="sound_only">필수</strong></label></th>
            <td>wr_<input type="text" name="wr_start" value="" id="wr_start" required class="frm_input required" size="10" maxlength="20"></td>
        </tr>
        <tr>
            <th scope="row"><label for="wr_end">wr_끝번호<strong class="sound_only">필수</strong></label></th>
            <td>wr_<input type="text" name="wr_end" value="" id="wr_end" required class="frm_input required" size="10" maxlength="20"></td>
        </tr>
		<tr>
            <th scope="row"><label for="board_id">게시판 ID<strong class="sound_only">필수</strong></label></th>
            <td>
			<input type="text" name="board_id" value="" id="board_id" class="frm_input" size="10" maxlength="20">
			<span>게시판아이디를 입력하지 않을경우 전체게시판에 적용됩니다.</span>
			</td>
        </tr>
		</table>
		</div>
		<div class=""style="text-align:center; padding:15px 0;">
		<input type="submit" value="여분필드 추가" id="btn_submit" accesskey="s" class="wr_form_btn">
		</div>
			
	</form>
</div>
<?php 
include_once(G5_PATH.'/tail.php');
?>