<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_URL.'/rb/rb.mod/chat/style.chat.css?ver='.G5_TIME_YMDHIS.'">', 0);

//회원정보 불러오기
$sql = " select * from {$g5['member_table']} group by mb_id order by mb_id asc"; 
$result = sql_query($sql); 

?>

<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script type="text/javascript" src="<?php echo G5_URL ?>/rb/rb.mod/chat/chat_search.js"></script>

<div id="memo">
    <div id="memo-body">
        <div id="memo-right">
            <!-- 검색 -->
            <div id="memo-search">
                <form onsubmit="return false;">
                    <input type="text" name="mb_nick" id="sch_stx" class="sec_inp" placeholder="대화 추가하기(닉네임입력)">
                    <button type="submit" id="search-submit" onclick="chat_invite();">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus w-5 h-5 sm:w-6 sm:h-6"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    </button>
                    <a href="javascript:void(0);" id="btn_close2" onclick="javascript:self.close();">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 -5 10 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus w-5 h-5 sm:w-6 sm:h-6"><line x1="13" y1="12" x2="3" y2="2"></line><line x1="13" y1="2" x2="3" y2="12"></line></svg>
                    </a>
                </form>
            </div>
            <div id="memo-chatlist">
                <ul class="memo-chatlist">
                <?php
                $sql = "select *, 
                        if(me_recv_mb_id='".$member['mb_id']."',me_send_mb_id,me_recv_mb_id) as me_chat_id 
                        from (select * from rb_chat order by me_id desc limit 0,99999) as tempmemo WHERE (me_recv_mb_id ='".$member['mb_id']."' or me_send_mb_id ='".$member['mb_id']."') 
                        group by me_chat_id
                        order by me_id desc limit 0,99999";
                $res = sql_query($sql);

                $chat_exists = false; // 대화가 있는지 여부를 저장하는 변수

                for($i=0;$row=sql_fetch_array($res);$i++) {
                    $mb = get_member($row['me_chat_id']);

                    // 내가 보낸 대화가 있는지 확인
                    $check_sql = "select count(me_id) as cnt from rb_chat where me_send_mb_id='".$member['mb_id']."' and me_recv_mb_id='".$row['me_chat_id']."'";
                    $check_res = sql_fetch($check_sql);

                    // 내가 보낸 대화가 없는지와 내가 읽지 않은 메시지가 있는지 확인
                    $unread_sql = "select count(me_id) as cnt from rb_chat where me_send_mb_id='".$row['me_chat_id']."' and me_recv_mb_id='".$member['mb_id']."' and me_read_datetime = '0000-00-00 00:00:00'";
                    $unread_res = sql_fetch($unread_sql);

                    if ($check_res['cnt'] == 0 && $unread_res['cnt'] == 0) {
                        continue; // 내가 보낸 대화도 없고 내가 읽지 않은 메시지도 없으면 목록에 표시하지 않음
                    }

                    $chat_exists = true; // 대화가 있음을 표시

                    $readed = (substr($row['me_read_datetime'],0,1) == 0) ? '' : 'read';
                    $bg = 'bg'.($i%2);
                    
                    //읽지 않은 메세지
                    $q = "select count(me_id) as cnt from rb_chat where me_send_mb_id='".$row['me_chat_id']."' and me_recv_mb_id='".$member['mb_id']."' and me_read_datetime = '0000-00-00 00:00:00'";
                    $r = sql_fetch($q);
                    $p_icon = get_member_profile_img($mb['mb_id']);

                    echo '<li class="memo-chatroom">';
                    echo '<div class="chat-link" data-mb_id="'.$row['me_chat_id'].'">';
                    echo '<span class="chatroom-icon">'.$p_icon.'</span>';

                    if($r['cnt'] > 0) { echo '<span class="no_read"></span>'; } else { echo '<span class="no_read2"></span>'; }

                    echo '<span class="chatroom-view">';
                    echo '<span class="chatroom-name">'.user_online($mb['mb_id']).' <span>'.$mb['mb_nick'].'</span></span>';
                    echo '<span class="chatroom-title font-R">'.cut_str(strip_tags($row['me_memo']), 15).'</span>';
                    echo '<span class="chatroom-date">'.$row['me_send_datetime'].'</span>';
                    echo '</span>';

                    if($r['cnt'] > 0) {
                        echo '<dd class="chatroom-cnt badge2"><span class="cnttxt font-R">신규 </span><span class="cntnum font-B">'.$r['cnt'].'</span></dd>';
                    }
                    echo '</div>';
                    echo '<dd class="chatroom-cnt badge3 chatroom-cnt-out"><span class="cnttxt font-R">삭제</span></dd>';
                    echo '</li>';
                }

                // 대화가 없을 경우 메시지 출력
                if (!$chat_exists) {
                    echo '<li class="no_data font-14">대화목록이 없습니다.</li>';
                }
                ?>
                </ul>
            </div>
        </div>
    </div>

    <div id="memo-footer">
        <strong><?php echo $chat_set['ch_days_old'] ?></strong>일이 지난 대화는 삭제됩니다.
        <a href="javascript:void(0);" id="btn_close3" onclick="javascript:window.location.reload();">
            <i class="fa fa-refresh" aria-hidden="true"></i>
        </a>
    </div>
</div>



<script>

$(function() {
    $(".chat-link").on('click', function() {
        var $this = $(this),
            $what = $this.closest('.memo-chatroom').find('.chat-link');
            value = $what.data('mb_id');
        var href = "<?php echo G5_URL ?>/rb/chat_form.php?me_recv_mb_id="+value;
        //var new_win = window.open(href, 'win_'+value, 'left=400,top=50,width=450,height=600,scrollbars=1');
        var new_win = location.href = href;
        new_win.focus();
    });
    
    // .chatroom-cnt-out 클릭 이벤트 추가
    $(document).on('click', '.chatroom-cnt-out', function(e) {
        e.preventDefault(); // 기본 링크 동작 방지

        if (confirm('채팅방에서 내가 보낸 모든 대화를 삭제하시겠습니까?\n목록 및 파일이 모두 삭제됩니다.')) {
            var del_id = $(this).closest('.memo-chatroom').find('.chat-link').data('mb_id');
            $.ajax({
                type: "POST",
                url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat_del.php',
                data: {
                    del_id: del_id, // 채팅방 ID
                    delete_all: 'true'
                },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        alert('내가보낸 모든 대화 및 파일이 삭제되었습니다.');
                        // 목록에서 대화 삭제
                        //$(this).closest('.memo-chatroom').remove();
                        location.reload();
                    } else {
                        alert('대화를 삭제하는 데 실패했습니다.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('삭제 중 오류 발생:', status, error);
                    alert('삭제 중 오류가 발생했습니다.');
                }
            });
        }
    });
});

function chat_invite() {
    var mb_nick = $("#sch_stx").val();
    if (mb_nick === "") {
        alert("추가하실 회원의 닉네임을 입력하세요.");
        $("#sch_stx").focus();
        return false;
    }
    
    if (mb_nick === "<?php echo $member['mb_nick']; ?>") {
        alert("자신과는 대화할 수 없습니다.");
        $("#sch_stx").focus();
        return false;
    }

    $.ajax({
        type: "POST",
        data: { act: 'search_member', mb_nick: mb_nick },
        url: '<?php echo G5_URL ?>/rb/rb.mod/chat/core/ajax.chat.php',
        dataType: 'json', // 응답을 JSON 형식으로 처리하도록 지정
        success: function(response) {
            if (!response || !response.success || !response.data || response.data.length === 0) {
                alert('대화상대를 추가하지 못하였습니다.\n닉네임을 정확히 입력하세요.');
                return false;
            } else {
                var member = response.data[0];
                var href = "<?php echo G5_URL ?>/rb/chat_form.php?me_recv_mb_id=" + member.mb_id;
                //var new_win = window.open(href, 'win_' + member.mb_id, 'left=400,top=50,width=450,height=600,scrollbars=1');
                var new_win = location.href = href;
                new_win.focus();
                return false;
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            alert('대화상대를 추가하는 중 오류가 발생했습니다.');
        }
    });
    return false;
}


    $(function() {    //화면 다 뜨면 시작
        var searchSource = [
            <?php 
            for ($i=0; $row=sql_fetch_array($result); $i++) { 
                if($row['mb_level'] >= $chat_set['ch_level']) {
                    echo json_encode($row['mb_nick']).",";
                }
            }
            ?>
        ]; // 배열 형태로 

        $("#sch_stx").autocomplete({  //오토 컴플릿트 시작
            source : searchSource,    // source 는 자동 완성 대상
            select : function(event, ui) {    //아이템 선택시
                console.log(ui.item);
            },
            focus : function(event, ui) {    //포커스 가면
                return false;//한글 에러 잡기용도로 사용됨
            },
            open: function(){
                $('.ui-autocomplete').css('width', '100%');
                $('.ui-autocomplete').css('top', '60px');
                $('.ui-autocomplete').css('left', '0px');
                $('.ui-autocomplete').css('font-size', '12px');
                $('.ui-autocomplete').css('border', '0px');
                $('.ui-autocomplete').css('background-color', '#fff');
                $('.ui-autocomplete').css('max-height', '190px');
                $('.ui-autocomplete').css('overflow-y', 'scroll');
                $('.ui-autocomplete').css('overflow-x', 'hidden');
                $('.ui-autocomplete').css('border-bottom', '1px solid #eee');
                $('.ui-autocomplete').css('box-shadow', '10px 0px 10px rgba(0,0,0,0.1)');
                $('.ui-autocomplete').css('box-sizing', 'border-box');
                $('.ui-menu-item-wrapper').css('padding', '10px 10px 10px 10px');
            },
            minLength: 1,// 최소 글자수
            autoFocus: true, //첫번째 항목 자동 포커스 기본값 false
            classes: {    //잘 모르겠음
                "ui-autocomplete": "highlight"
            },
            delay: 500,    //검색창에 글자 써지고 나서 autocomplete 창 뜰 때 까지 딜레이 시간(ms)
//            disabled: true, //자동완성 기능 끄기
            position: { my : "right top", at: "right bottom" },    //잘 모르겠음
            close : function(event){    //자동완성창 닫아질때 호출
                console.log(event);
            }
            
            
        });
        
    });
    

/*
setInterval( function() {
    location.reload();	
}, 20000 ); //20초에 갱신
*/

</script>