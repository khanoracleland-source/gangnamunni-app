<?php
// /adm/rb/rb_pwa_send.php
$sub_menu = '000762';
include_once('./_common.php');
auth_check_menu($auth, $sub_menu, "w");

function rb_pwa_admin_token_input_html() {
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

$g5['title'] = 'PWA 알림 발송';
include_once(G5_ADMIN_PATH.'/admin.head.php');

$token_html = rb_pwa_admin_token_input_html();
?>
<section class="cbox">
    <h2 class="h2_frm">푸시 작성</h2>

    <form id="sendForm" method="post" action="./rb_pwa_send_enqueue.php" onsubmit="return goSend(this)" enctype="multipart/form-data">
        <?php echo $token_html; ?>
        <div class="tbl_frm01 tbl_wrap">
            <table>
                <tbody>
                    <tr>
                        <th>대상</th>
                        <td>
                            <?php echo help('회원지정 선택 시, 수신할 회원 아이디를 콤마(,)로 구분하여 입력하세요.'); ?>
                            <label><input type="radio" name="target" value="all" checked> 전체</label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="target" value="level"> 레벨</label>&nbsp;&nbsp;
                            <style>.selects > select {height:30px;}</style>
                            <span class="selects">
                            <?php
                              // GNUBOARD 기본 헬퍼.
                              echo get_member_level_select('level_from', 1, $member['mb_level'], 1);
                            ?>
                            </span>
                            <span style="opacity:.8;"></span>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="target" value="member"> 회원지정</label>&nbsp;&nbsp;
                            <input type="text" name="mb_ids" class="frm_input" size="60" placeholder="tester1, tester2, .." disabled>
                            
                        </td>
                    </tr>

                    <tr>
                        <th>이미지 첨부</th>
                        <td>
                             <?php echo help('첨부 시 알림에 이미지를 표기할 수 있습니다. iOS 및 일부 기종에서는 지원이 되지 않을 수 있습니다.<br>업로드된 이미지는 /data/pwa/notice/ 에 알림용 JPG로 저장되어 전송됩니다.'); ?>
                            <input type="file" name="image" accept="image/*"> 
                           
                        </td>
                    </tr>

                    <tr>
                        <th>제목</th>
                        <td><input type="text" name="title" class="frm_input" size="80" required></td>
                    </tr>

                    <tr>
                        <th>내용</th>
                        <td>
                            <?php echo help('엔터로 줄바꿈 할 수 있습니다.'); ?>
                            <textarea name="body" cols="100" class="frm_input" style="width:100%; line-height:130%;" required></textarea>
                            
                        </td>
                    </tr>

                    <tr>
                        <th>URL</th>
                        <td>
                        <?php echo help('알림 클릭시 이동할 URL 을 입력할 수 있습니다.'); ?>
                        <input type="text" name="url" class="frm_input" size="100" value="/" required>
                        </td>
                    </tr>

                    <!-- 발송 진행(테이블 안 td로 이동) -->
                    <tr id="sendResultRow" style="display:none;">
                        <th>발송 진행</th>
                        <td>
                            <!--
                            <div>Log ID: <span id="logId"></span></div>
                            -->
                            <div>처리: <span id="done">0</span> / <span id="total">0</span>
                                (성공 <span id="succ">0</span> / 실패 <span id="fail">0</span>)</div>
                            <div id="progress" style="height:10px;background:#eee; border-radius:10px; width:300px;">
                                <div id="bar" style="height:10px;width:0;background:#25282B; border-radius:10px;"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="btn_fixed_top"><input type="submit" value="큐 등록" class="btn_submit btn"></div>
    </form>
</section>

<script>
(function(){
    // 대상 UI 토글
    function toggleTargetUI() {
        var tgt = (document.querySelector('input[name="target"]:checked')||{}).value;
        var mb = document.querySelector('input[name="mb_ids"]');
        var lv = document.querySelector('select[name="level_from"]');
        if (mb) mb.disabled = (tgt!=='member');
        if (lv) lv.disabled = (tgt!=='level');
    }
    document.addEventListener('change', function(e){
        if (e.target && e.target.name === 'target') toggleTargetUI();
    });
    // 초기 상태
    toggleTargetUI();
})();

function goSend(f) {
    var fd = new FormData(f);
    fetch(f.action, { method: 'POST', body: fd })
      .then(function(r){ return r.json(); })
      .then(function(js){
          if (!js.ok) { alert(js.msg || '에러'); return; }
          // 테이블 안에 있는 행을 노출
          document.getElementById('sendResultRow').style.display = '';
          //document.getElementById('logId').textContent = js.log_id;
          // 서버가 확정한 total 신뢰
          document.getElementById('total').textContent = js.total;
          tick(js.log_id);
      })
      .catch(function(){ alert('요청 중 오류가 발생 하였습니다.'); });
    return false;
}

function tick(logId) {
    (function loop(){
        fetch('./rb_pwa_send_batch.php?log_id='+encodeURIComponent(logId))
          .then(function(r){ return r.json(); })
          .then(function(j){
              if (!j.ok) { alert(j.msg || '배치 에러'); return; }
              if (typeof j.total === 'number') {
                  document.getElementById('total').textContent = j.total;
              }
              document.getElementById('done').textContent = j.done;
              document.getElementById('succ').textContent = j.succ;
              document.getElementById('fail').textContent = j.fail;

              var total = parseInt(document.getElementById('total').textContent, 10) || 0;
              var pct = total ? Math.min(100, Math.round(j.done / total * 100)) : 0;
              document.getElementById('bar').style.width = pct + '%';

              if (!j.finished) setTimeout(loop, 600);
              else document.getElementById('bar').style.width = '100%';
          })
          .catch(function(){ setTimeout(loop, 1200); });
    })();
}
</script>
<?php include_once(G5_ADMIN_PATH.'/admin.tail.php'); ?>
