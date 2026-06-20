<?php
include_once('./_common.php');

$bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : '';
$wr_id = isset($_POST['wr_id']) ? (int)$_POST['wr_id'] : 0;
$sort_type = isset($_POST['sort_type']) ? $_POST['sort_type'] : 'newest';

if (!$bo_table || !$wr_id) {
    die('올바른 접근이 아닙니다.');
}

// 게시판 정보 가져오기
$board = sql_fetch(" select * from {$g5['board_table']} where bo_table = '$bo_table' ");
if (!$board['bo_table']) {
    die('존재하지 않는 게시판입니다.');
}

$write_table = $g5['write_prefix'] . $bo_table;

// 정렬 조건 설정
switch($sort_type) {
    case 'best':
        $order_by = " order by wr_good desc, wr_datetime desc ";
        break;
    case 'oldest':
        $order_by = " order by wr_datetime asc ";
        break;
    case 'newest':
    default:
        $order_by = " order by wr_datetime desc ";
        break;
}

// 댓글 목록 가져오기
$sql = " select * from $write_table 
         where wr_parent = '$wr_id' 
         and wr_is_comment = 1 
         $order_by ";
$result = sql_query($sql);

for ($i=0; $row=sql_fetch_array($result); $i++) {
    $comment_id = $row['wr_id'];
    $cmt_depth = strlen($row['wr_comment_reply']) * 20;
    $cmt_depth_bg = $cmt_depth - 20;
    
    $name = get_sideview($row['mb_id'], $row['wr_name'], $row['wr_email'], $row['wr_homepage']);
    $is_secret = strstr($row['wr_option'], "secret") ? true : false;
    
    $content = $row['wr_content'];
    $content = preg_replace("/\[\<a\s.*href\=\"(http|https|ftp|mms)\:\/\/([^[:space:]]+)\.(mp3|wma|wmv|asf|asx|mpg|mpeg)\".*\<\/a\>\]/i", "<script>doc_write(obj_movie('$1://$2.$3'));</script>", $content);
    
    // 댓글 출력
    ?>
    <article id="c_<?php echo $comment_id ?>" class="comment_item">
        <div class="pf_img"><?php echo get_member_profile_img($row['mb_id']) ?></div>
        
        <div class="cm_wrap" <?php if ($cmt_depth) { ?>style="padding-left:<?php echo $cmt_depth ?>px; background-image:url('<?php echo $board_skin_url ?>/img/ico_rep_tiny.svg'); background-position:top 22px left <?php echo $cmt_depth_bg ?>px"<?php } ?>>
            <header>
                <?php echo $name ?>
                <?php if ($board['bo_use_ip_view']) { ?>
                <span>(<?php echo $row['wr_ip']; ?>)</span>
                <?php } ?>
                <span class="bo_vc_hdinfo"><?php echo passing_time3($row['wr_datetime']) ?></span>
            </header>

            <div class="cmt_contents">
                <p>
                    <?php if ($is_secret) { ?><img src="<?php echo $board_skin_url; ?>/img/ico_sec.svg" alt="비밀글"><?php } ?>
                    <?php echo $content ?>
                </p>
                <p class="p_times"><span><?php echo date('Y-m-d H:i', strtotime($row['wr_datetime'])) ?></span></p>
            </div>
        </div>
        
        <?php if($is_admin || ($member['mb_id'] && $member['mb_id'] === $row['mb_id'])) { ?>
        <div class="bo_vl_opt">
            <button type="button" class="btn_cm_opt btn_b01 btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-horizontal">
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="19" cy="12" r="1"></circle>
                    <circle cx="5" cy="12" r="1"></circle>
                </svg>
            </button>
            <ul class="bo_vc_act">
                <li><a href="javascript:void(0);" onclick="comment_box('<?php echo $comment_id ?>', 'c');">답변</a></li>
                <li><a href="javascript:void(0);" onclick="comment_box('<?php echo $comment_id ?>', 'cu');">수정</a></li>
                <li><a href="javascript:void(0);" onclick="comment_delete('<?php echo $comment_id ?>');">삭제</a></li>
            </ul>
        </div>
        <?php } ?>
    </article>
    <?php
}

if ($i == 0) {
    echo '<p id="bo_vc_empty">등록된 댓글이 없습니다.</p>';
}
?>

<script>
    var sv_hide = false;
    $(document).on("click", ".sv_member, .sv_guest", function(e) {
        $(".sv").removeClass("sv_on");
        $(this).closest(".sv_wrap").find(".sv").addClass("sv_on");
    });

    // 사이드뷰 호버 이벤트
    $(document).on("mouseenter", ".sv, .sv_wrap", function() {
        sv_hide = false;
    });
    
    $(document).on("mouseleave", ".sv, .sv_wrap", function() {
        sv_hide = true;
        // 마우스가 떠난 후 약간의 지연 시간을 두고 닫기
        setTimeout(function() {
            if(sv_hide) {
                $(".sv").removeClass("sv_on");
            }
        }, 200);
    });
    
    // 사이드뷰 포커스 이벤트
    $(document).on("focusin", ".sv_member, .sv_guest", function() {
        sv_hide = false;
        $(".sv").removeClass("sv_on");
        $(this).closest(".sv_wrap").find(".sv").addClass("sv_on");
    });
    
    $(document).on("focusin", ".sv a", function() {
        sv_hide = false;
    });
    
    $(document).on("focusout", ".sv a", function() {
        sv_hide = true;
    });
    
    // 문서 클릭 시 사이드뷰 닫기
    $(document).on("click", function(e) {
        // 사이드뷰 영역 외 클릭 시에만 닫기
        if(!$(e.target).closest('.sv_wrap').length && !$(e.target).closest('.sv').length) {
            $(".sv").removeClass("sv_on");
        }
    });
    
    // 문서 포커스인 시 사이드뷰 닫기
    $(document).on("focusin", function(e) {
        if(!$(e.target).closest('.sv_wrap').length && !$(e.target).closest('.sv').length) {
            $(".sv").removeClass("sv_on");
        }
    });
    
</script>