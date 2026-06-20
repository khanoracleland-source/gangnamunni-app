<?php
$sub_menu = '000771';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "w");

$g5['title'] = '출석부 관리';
include_once (G5_ADMIN_PATH.'/admin.head.php');

include_once(G5_PATH.'/rb/rb.mod/attendance/attend.install.php'); // 테이블 생성 헬퍼
rb_attend_install_if_needed();

include_once(G5_PATH.'/rb/rb.mod/attendance/attend.lib.php'); // 라이브러리

// 스키마 보강: 보너스 4,5등과 기본 참여 포인트 컬럼 추가
// 기존 설치 환경에서도 안전하게 동작
function rb_attend_alter_config_table(){
    $cols = array(
        'bonus_rank4' => "ALTER TABLE rb_attend_config ADD COLUMN bonus_rank4 INT NOT NULL DEFAULT 0",
        'bonus_rank5' => "ALTER TABLE rb_attend_config ADD COLUMN bonus_rank5 INT NOT NULL DEFAULT 0",
        'base_attend_point' => "ALTER TABLE rb_attend_config ADD COLUMN base_attend_point INT NOT NULL DEFAULT 0"
    );
    foreach ($cols as $col => $ddl) {
        $row = sql_fetch("SHOW COLUMNS FROM rb_attend_config LIKE '{$col}'");
        if (!$row) { @sql_query($ddl, false); }
    }
}
rb_attend_alter_config_table();

// 설정 저장 처리
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__mode']) && $_POST['__mode']==='save_config') {
    check_admin_token();
    $a = array(
        // 세 구간 연속 포인트(라벨은 요구안에 맞춰 한글로 표시, 저장은 기존 필드 사용)
        'week_streak_len'   => (int)($_POST['streak_len_1'] ?? 7),
        'week_streak_point' => (int)($_POST['streak_point_1'] ?? 0),

        'month_streak_len'  => (int)($_POST['streak_len_2'] ?? 30),
        'month_streak_point'=> (int)($_POST['streak_point_2'] ?? 0),

        'year_streak_len'   => (int)($_POST['streak_len_3'] ?? 365),
        'year_streak_point' => (int)($_POST['streak_point_3'] ?? 0),

        // 1~5등 보너스
        'bonus_rank1'       => (int)($_POST['bonus_rank1'] ?? 0),
        'bonus_rank2'       => (int)($_POST['bonus_rank2'] ?? 0),
        'bonus_rank3'       => (int)($_POST['bonus_rank3'] ?? 0),
        'bonus_rank4'       => (int)($_POST['bonus_rank4'] ?? 0),
        'bonus_rank5'       => (int)($_POST['bonus_rank5'] ?? 0),

        // 참여 포인트
        'base_attend_point' => (int)($_POST['base_attend_point'] ?? 0)
    );

    // 저장
    sql_query("UPDATE rb_attend_config SET
      week_streak_point='{$a['week_streak_point']}',
      month_streak_point='{$a['month_streak_point']}',
      year_streak_point='{$a['year_streak_point']}',
      bonus_rank1='{$a['bonus_rank1']}',
      bonus_rank2='{$a['bonus_rank2']}',
      bonus_rank3='{$a['bonus_rank3']}',
      bonus_rank4='{$a['bonus_rank4']}',
      bonus_rank5='{$a['bonus_rank5']}',
      week_streak_len='{$a['week_streak_len']}',
      month_streak_len='{$a['month_streak_len']}',
      year_streak_len='{$a['year_streak_len']}',
      base_attend_point='{$a['base_attend_point']}'
      WHERE cf_id=1
    ");

    alert('설정이 저장되었습니다.', $_SERVER['SCRIPT_NAME']);
    exit;
}

// 날짜별 선택삭제 처리
// 선택삭제 시, rb_attendance 레코드를 삭제하고 streak 스냅샷을 간단히 재계산
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__mode']) && $_POST['__mode']==='delete_selected') {
    check_admin_token();

    $ids = isset($_POST['at_id']) && is_array($_POST['at_id']) ? array_map('intval', $_POST['at_id']) : array();
    if (!$ids) {
        alert('삭제할 항목을 선택하세요.', $_SERVER['SCRIPT_NAME'].'?'.http_build_query($_GET));
        exit;
    }

    // 삭제 전, 영향 받는 회원 목록 수집
    $idlist = implode(',', $ids);
    $aff = array();
    $res = sql_query("SELECT DISTINCT mb_id FROM rb_attendance WHERE at_id IN ({$idlist})");
    while ($r = sql_fetch_array($res)) { $aff[] = $r['mb_id']; }

    // 삭제
    sql_query("DELETE FROM rb_attendance WHERE at_id IN ({$idlist})");

    // 간단 재계산: 각 회원의 마지막 연속 정보를 재구성
    foreach ($aff as $mbid) {
        $mbid_esc = sql_real_escape_string($mbid);

        // 마지막 출석일
        $rmax = sql_fetch("SELECT MAX(REPLACE(ymd,'-','')) AS last_ymd FROM rb_attendance WHERE mb_id='{$mbid_esc}'");
        $last_ymd = isset($rmax['last_ymd']) && $rmax['last_ymd'] ? $rmax['last_ymd'] : null;

        if (!$last_ymd) {
            // 출석 기록이 없으면 스냅샷 삭제
            sql_query("DELETE FROM rb_attend_streak WHERE mb_id='{$mbid_esc}'");
            continue;
        }

        // 마지막 일자부터 역으로 연속 일수 카운트
        $cont = 0;
        $cur = DateTime::createFromFormat('Ymd', $last_ymd);
        for (;;) {
            $cur_ymd = $cur->format('Ymd');
            $has = sql_fetch("SELECT at_id FROM rb_attendance WHERE mb_id='{$mbid_esc}' AND REPLACE(ymd,'-','')='{$cur_ymd}' LIMIT 1");
            if ($has && $has['at_id']) {
                $cont++;
                $cur->modify('-1 day');
            } else {
                break;
            }
        }

        // 스냅샷 업데이트
        $exists = sql_fetch("SELECT mb_id FROM rb_attend_streak WHERE mb_id='{$mbid_esc}'");
        if ($exists && $exists['mb_id']) {
            sql_query("UPDATE rb_attend_streak SET last_ymd='{$last_ymd}', cont_days='{$cont}' WHERE mb_id='{$mbid_esc}'");
        } else {
            sql_query("INSERT INTO rb_attend_streak (mb_id,last_ymd,cont_days) VALUES ('{$mbid_esc}','{$last_ymd}','{$cont}')");
        }
    }

    alert('선택 항목을 삭제했습니다.', $_SERVER['SCRIPT_NAME'].'?'.http_build_query($_GET));
    exit;
}

// 조회용 파라미터
$ymd = isset($_GET['ymd']) ? $_GET['ymd'] : (defined('G5_TIME_YMD') ? G5_TIME_YMD : date('Ymd'));
$ymd8 = preg_replace('/[^0-9]/','', $ymd);
if (strlen($ymd8) !== 8) $ymd8 = date('Ymd');

// 페이징 기본
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$rows = 10;
$from_record = ($page - 1) * $rows;

// 목록 쿼리
$mt = $g5['member_table'];
$total_row = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_attendance WHERE REPLACE(ymd,'-','')='{$ymd8}'");
$total_count = (int)$total_row['cnt'];
$total_page = max(1, ceil($total_count / $rows));

$q = sql_query("
    SELECT a.*, REPLACE(a.ymd,'-','') AS ymd8, m.mb_nick
    FROM rb_attendance a
    LEFT JOIN {$mt} m ON m.mb_id = a.mb_id
    WHERE REPLACE(a.ymd,'-','')='{$ymd8}'
    ORDER BY (a.at_rank IS NULL) ASC, a.at_rank ASC, a.at_datetime ASC, a.at_id ASC
    LIMIT {$from_record}, {$rows}
");

// 설정 불러오기
$cf = rb_attend_get_config();
$token = get_admin_token();

// 날짜 포맷 헬퍼
function rb_att_fmt_date($ymd8) { return substr($ymd8,0,4).'-'.substr($ymd8,4,2).'-'.substr($ymd8,6,2); }

?>

<style>
.rb-badge{display:inline-block;min-width:32px;text-align:center;padding:2px 6px;border-radius:6px;border:1px solid #ddd;background:#f7f7f7;font-size:12px}
.rb-badge.r1{background:#000;color:#fff;border-color:#000}
.rb-badge.r2{background:#777;color:#fff;border-color:#777}
.rb-badge.r3{background:#333;color:#fff;border-color:#ccc}
.rb-badge.r4{background:#999;color:#fff}
.rb-badge.r5{background:#bbb;color:#fff}
</style>


<form method="get" class="local_sch01 local_sch" style="margin-bottom:10px">
    <input type="date" id="ymd" name="ymd" value="<?php echo rb_att_fmt_date($ymd8); ?>" class="frm_input">
    <input type="submit" value="조회" class="btn_submit">
    <a href="/rb/attend.php" target="_blank" class="ov_listall">출석부 바로가기</a>
  </form>

  <form method="post" onsubmit="return rb_att_del_submit(this, event);">
    <input type="hidden" name="__mode" value="delete_selected">
    <input type="hidden" name="token" value="<?php echo $token; ?>">

    <div class="tbl_head01 tbl_wrap">
      <table>
        <thead>
          <tr>
            <th scope="col"><label for="chkall" class="sound_only">전체</label><input type="checkbox" id="chkall" onclick="rb_check_all(this.form)"></th>
            <th scope="col">회원</th>
            <th scope="col">한마디</th>
            <th scope="col">순위</th>
            <th scope="col">작성시간</th>
            <th scope="col">IP</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 0;
          while ($row = sql_fetch_array($q)) {
              $nick = $row['mb_nick'] ? get_text($row['mb_nick']) : $row['mb_id'];
              $badge = '<span class="rb-badge">참여</span>';
              if ($row['at_rank'] == 1) $badge = '<span class="rb-badge r1">1등</span>';
              else if ($row['at_rank'] == 2) $badge = '<span class="rb-badge r2">2등</span>';
              else if ($row['at_rank'] == 3) $badge = '<span class="rb-badge r3">3등</span>';
              else if ($row['at_rank'] == 4) $badge = '<span class="rb-badge r4">4등</span>';
              else if ($row['at_rank'] == 5) $badge = '<span class="rb-badge r5">5등</span>';
              $content = nl2br(get_text($row['at_content']));
              echo '<tr>';
              echo '<td class="td_chk"><input type="checkbox" name="at_id[]" value="'.(int)$row['at_id'].'"></td>';
              echo '<td><a href="../member_form.php?w=u&mb_id='.$row['mb_id'].'">'.$nick.'</a></td>';
              echo '<td class="td_left">'.$content.'</td>';
              echo '<td>'.$badge.'</td>';
              echo '<td>'.$row['at_datetime'].'</td>';
              echo '<td>'.($row['at_ip'] ?: '-').'</td>';
              echo '</tr>';
              $i++;
          }
          if ($i == 0) {
              echo '<tr><td colspan="6" class="empty_table"><span>출석이 없습니다.</span></td></tr>';
          }
          ?>
        </tbody>
      </table>
    </div>

    <div class="btn_fixed_top">
      <input type="submit" value="선택삭제" class="btn btn_02">
    </div>
  </form>


<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?ymd=".$ymd8."&amp;page="); ?>


<script>
    
// rb_confirm 폴리필
if (typeof window.rb_confirm !== 'function') {
  window.rb_confirm = function(msg) {
    return new Promise(function(resolve) {
      resolve(window.confirm(msg));
    });
  };
}
    
// 체크박스 전체선택
function rb_check_all(f){
  var chk = f.querySelectorAll('input[name="at_id[]"]');
  var on = f.querySelector('#chkall').checked;
  chk.forEach(function(c){ c.checked = on; });
}

// 삭제 확인
function rb_att_del_submit(f, ev) {
  // 선택 확인
  var any = false;
  f.querySelectorAll('input[name="at_id[]"]').forEach(function(c){ if (c.checked) any = true; });
  if (!any) { alert('삭제할 항목을 선택하세요.'); return false; }

  // 기본 제출 막기
  if (ev && typeof ev.preventDefault === 'function') ev.preventDefault();

  var msg = '삭제 시 지급된 포인트가 회수되지 않습니다.\n선택한 출석글을 삭제하시겠습니까?';

  // 여기 핵심: rb_confirm이 있든 없든, Promise로 정규화
  var confirmResult = (typeof window.rb_confirm === 'function')
    ? window.rb_confirm(msg)                          // Promise 또는 boolean일 수 있음
    : window.confirm(msg);                            // boolean

  Promise.resolve(confirmResult).then(function(confirmed){
    if (!confirmed) return;
    var submitBtn = f.querySelector('[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    // form.submit()가 input[name="submit"]에 가로채이지 않도록 보호
    if (typeof HTMLFormElement !== 'undefined' && HTMLFormElement.prototype.submit) {
      HTMLFormElement.prototype.submit.call(f);
    } else {
      f.submit();
    }
  });

  return false;
}
</script>





<form method="post">
    <input type="hidden" name="__mode" value="save_config">
    <input type="hidden" name="token" value="<?php echo $token; ?>">
    
    <section>
    <h2 class="h2_frm">출석부 환경설정</h2>
    
    <div class="local_desc01 local_desc">
        <p>
            연속 출석은 여러개의 조건 설정이 가능합니다.<br>
            예) 3일간 연속 출석시 3000포인트 지급, 7일간 연속 출석시 7000포인트 지급<br>
            포인트가 설정된 등수의 경우 글 등록시 등수 뱃지가 출력 됩니다.<br>
            <b>주의! 연속출석은 매월 초기화 되므로 30일을 넘지 않도록 설정해주세요.</b>
        </p>
    </div>
   
    <div class="tbl_frm01 tbl_wrap">
        <table>

        <colgroup>
            <col class="grid_4">
            <col>
        </colgroup>
        <tbody>
        <tr>
            <th scope="row">연속출석 설정 1</th>
            <td>
                <input type="number" name="streak_len_1" class="frm_input" style="width:70px;" value="<?php echo (int)$cf['week_streak_len']; ?>"> 일간 연속 출석시　
                <input type="number" name="streak_point_1" class="frm_input" style="width:80px;" value="<?php echo (int)$cf['week_streak_point']; ?>"> 포인트 지급
            </td>
            
        </tr>
        
        <tr>
            <th scope="row">연속출석 설정 2</th>
            <td>
                <input type="number" name="streak_len_2" class="frm_input" style="width:70px;" value="<?php echo (int)$cf['month_streak_len']; ?>"> 일간 연속 출석시　
                  <input type="number" name="streak_point_2" class="frm_input" style="width:80px;" value="<?php echo (int)$cf['month_streak_point']; ?>"> 포인트 지급
            </td>
            
        </tr>
        
        <tr>
            <th scope="row">연속출석 설정 3</th>
            <td>
                <input type="number" name="streak_len_3" class="frm_input" style="width:70px;" value="<?php echo (int)$cf['year_streak_len']; ?>"> 일간 연속 출석시　
                  <input type="number" name="streak_point_3" class="frm_input" style="width:80px;" value="<?php echo (int)$cf['year_streak_point']; ?>"> 포인트 지급
            </td>
            
        </tr>
        
        <tr>
            <th scope="row">등수 보너스</th>
            <td>
                <div class="rb-row"><label>1등 보너스</label><input type="number" name="bonus_rank1" class="frm_input" style="width:80px; height:30px;" value="<?php echo (int)$cf['bonus_rank1']; ?>"> 포인트 지급</div>
                <div class="rb-row"><label>2등 보너스</label><input type="number" name="bonus_rank2" class="frm_input" style="width:80px; height:30px; margin-top:5px;" value="<?php echo (int)$cf['bonus_rank2']; ?>"> 포인트 지급</div>
                <div class="rb-row"><label>3등 보너스</label><input type="number" name="bonus_rank3" class="frm_input" style="width:80px; height:30px; margin-top:5px;" value="<?php echo (int)$cf['bonus_rank3']; ?>"> 포인트 지급</div>
                <div class="rb-row"><label>4등 보너스</label><input type="number" name="bonus_rank4" class="frm_input" style="width:80px; height:30px; margin-top:5px;" value="<?php echo (int)$cf['bonus_rank4']; ?>"> 포인트 지급</div>
                <div class="rb-row"><label>5등 보너스</label><input type="number" name="bonus_rank5" class="frm_input" style="width:80px; height:30px; margin-top:5px;" value="<?php echo (int)$cf['bonus_rank5']; ?>"> 포인트 지급</div>
            </td>
            
        </tr>
        
        <tr>
            <th scope="row">참여 기본포인트</th>
            <td>
                <input type="number" name="base_attend_point" class="frm_input" style="width:80px;" value="<?php echo (int)$cf['base_attend_point']; ?>"> 포인트
            </td>
            
        </tr>

        </tbody>
        </table>
    </div>
    </section>
    

    <div class="btn_confirm01 btn_confirm">
        <input type="submit" value="확인" class="btn_submit btn">
    </div>

</form>

<?php
include_once(G5_ADMIN_PATH.'/admin.tail.php');