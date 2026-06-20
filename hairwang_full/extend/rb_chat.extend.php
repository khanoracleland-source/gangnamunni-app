<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_replace('admin_menu', 'add_admin_bbs_menu_chat', 1, 1); // 관리자 메뉴를 추가함
$chat_set = sql_fetch("SELECT * FROM rb_chat_set"); // 앱관리 테이블 조회

function add_admin_bbs_menu_chat($admin_menu){ // 메뉴추가

    $admin_menu['menu000'][] = array(
        '000620', '메세지 관리', G5_ADMIN_URL.'/rb/chat_form.php', 'rb_config'
    );
    return $admin_menu;
}

// 현재 로그인중인지 여부를 체크한다.
function user_online($mb_id) { 
    if ($mb_id) { 
        global $g5;
        $sql = "SELECT mb_id FROM {$g5['login_table']} WHERE mb_id = '".sql_real_escape_string($mb_id)."'";
        $result = sql_fetch($sql); 

        if ($result && isset($result['mb_id'])) { 
            $img_tag = "<span class='user_login_on'>ON</span>"; 
        } else { 
            $img_tag = "<span class='user_login_off'>OFF</span>"; 
        } 
        return $img_tag; 
    } 
    return "<span class=''user_login_off'>OFF</span>"; // mb_id가 없을 때 기본적으로 OFF를 반환합니다.
}

if (isset($chat_set['ch_use']) && $chat_set['ch_use'] == 1) {
    if ($is_member) { 
        if (isset($chat_set['ch_level']) && $member['mb_level'] >= $chat_set['ch_level']) {
            // 하단부 버튼을 HOOK으로 추가
            add_event('tail_sub', 'chat_btn');

            function chat_btn() {
                global $rb_hook_tail, $member, $chat_set; // 전역 변수 선언
                if ($rb_hook_tail) {
                    // 읽지 않은 메세지 갯수 확인
                    $recv_member_id = isset($member['mb_id']) ? $member['mb_id'] : '';
                    $chat_recv = sql_fetch("SELECT COUNT(*) as cnt FROM rb_chat WHERE me_recv_mb_id = '{$recv_member_id}' AND me_read_datetime = '0000-00-00 00:00:00'");

                    echo '<a href="'.G5_URL.'/rb/chat.php" class="chat_open_btn chat_btn_pos_'.$chat_set['ch_position'].'" onclick="win_chat(this.href); return false;" title="메세지" style="left:auto; right:auto; '.$chat_set['ch_position'].':'.$chat_set['ch_position_x'].'px; bottom:'.$chat_set['ch_position_y'].'px;"><div class="chat_open_btn_inner"><img src="'.G5_URL.'/rb/rb.mod/chat/image/chat_ci.svg">';
                    if (isset($chat_recv['cnt']) && $chat_recv['cnt'] > 0) {
                        echo '<span class="font-B">'.$chat_recv['cnt'].'</span>';
                    }
                    echo '</div></a>
                    <script>
                    var win_chat = function(href) {
                        var new_win = window.open(href, "win_chat", "left=100,top=100,width=500,height=700,scrollbars=1");
                        new_win.focus();
                    }
                    </script>';
                }

            }
        }
    }
    
    // 사이드뷰 추가 @Leegun 님께서 도움 주셨습니다.
    add_replace('member_sideview_items', function ($sideview, $data = []) {
        global $g5;

        // $data 배열에서 mb_id를 가져옵니다.
        if (isset($data['mb_id']) && $data['mb_id']) {
            
            echo '<script>
                var win_chat2 = function(href) {
                    var new_win = window.open(href, "win_chat", "left=100,top=100,width=500,height=700,scrollbars=1");
                    new_win.focus();
                }
            </script>';
            
            // 1:1 대화하기 메뉴 항목 생성
            $chat_menu = ['chat' => '<a href="' . G5_URL . '/rb/chat_form.php?me_recv_mb_id=' . $data['mb_id'] . '" rel="nofollow" onclick="win_chat2(this.href); return false;">1:1 대화하기</a>'];

            // 기존 메뉴 항목 앞에 새로운 메뉴 항목 추가
            $sideview['menus'] = $chat_menu + $sideview['menus'];
        }
        return $sideview;

    }, G5_HOOK_DEFAULT_PRIORITY, 2);
}