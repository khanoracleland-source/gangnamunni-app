<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>

<link rel="stylesheet" href="<?php echo G5_URL ?>/rb/rb.widget/rb.new_roll/style.css">

<?php
    
//모듈정보 불러오기
$md_id = $row_mod['md_id']; //현재 모듈 ID
$rb_skin = sql_fetch (" select * from {$rb_module_table} where md_id = '{$md_id}' "); //환경설정 테이블 조회
$md_subject = $rb_skin['md_title']; //모듈 타이틀
      
$excluded_boards = []; // 제외할(포함할) 게시판 ID 
//설정예시 : $excluded_boards = ['free', 'notice', 'test'];
      
$limit_count = 10; // 출력갯수

$sql_commons = " FROM {$g5['board_new_table']} a, {$g5['board_table']} b WHERE a.bo_table = b.bo_table AND a.wr_id = a.wr_parent ";

/* 제외할 게시판 으로 처리 시 { */
if (!empty($excluded_boards)) {
    $excluded_boards_sql = "'" . implode("','", array_map('addslashes', $excluded_boards)) . "'";
    $sql_commons .= " AND b.bo_table NOT IN ($excluded_boards_sql)";
}
/* } */

/* 포함할 게시판으로 처리 시
if (!empty($excluded_boards)) {
    $excluded_boards_sql = "'" . implode("','", array_map('addslashes', $excluded_boards)) . "'";
    $sql_commons .= " AND b.bo_table IN ($excluded_boards_sql)";
}
*/

$sql_commons .= " ORDER BY a.bn_id DESC";
$sqls = "SELECT a.*, b.bo_subject, b.bo_mobile_subject {$sql_commons} LIMIT {$limit_count}";
      
$results = sql_query($sqls);
      
?>

<div class="bbs_main bbs_main_wrap_con_r_po_rel">
  

   
    <ul class="bbs_main_wrap_tit" style="display:<?php echo (isset($rb_skin['md_title_hide']) && $rb_skin['md_title_hide'] == '1') ? 'none' : 'block'; ?>">

        <li class="bbs_main_wrap_tit_l">
            <!-- 타이틀 { -->
            <a href="<?php echo $links_url; ?>">
                <h2 class="<?php echo isset($rb_skin['md_title_font']) ? $rb_skin['md_title_font'] : 'font-B'; ?>" style="color:<?php echo isset($rb_skin['md_title_color']) ? $rb_skin['md_title_color'] : '#25282b'; ?>; font-size:<?php echo isset($rb_skin['md_title_size']) ? $rb_skin['md_title_size'] : '20'; ?>px; "><?php echo $md_subject ?></h2>
            </a>
            <!-- } -->
        </li>

        <li class="bbs_main_wrap_tit_r">
            <!-- 좌우 페이징 { -->
            <button type="button" class="arr_prev_btn arr_sw_prev1 swiper-button-prev arr_sw_prev_roll">
                <img src="<?php echo G5_THEME_URL ?>/rb.img/icon/arr_prev.svg">
            </button>
            <button type="button" class="arr_next_btn arr_sw_next1 swiper-button-next arr_sw_next_roll">
                <img src="<?php echo G5_THEME_URL ?>/rb.img/icon/arr_next.svg">
            </button>
            <!-- } -->

            <button type="button" class="arr_plus_btn" onclick="location.href='<?php echo G5_URL ?>/rb/new.php';">
                <img src="<?php echo G5_THEME_URL ?>/rb.img/icon/arr_plus.svg" id="ovlay_tog_img_roll">
            </button>
        </li>
        <div class="cb"></div>
    </ul>
    
    
    
    
    <div class="ovlay_wrap_roll">
        <ul class="swiper-container swiper-container-new-roll">
            <div class="swiper-wrapper swiper-wrapper-new-roll">
                <?php 
                for ($i=0; $rows=sql_fetch_array($results); $i++) { 
                    $tmp_write_table = $g5['write_prefix'].$rows['bo_table'];
                    $row2 = sql_fetch(" select * from {$tmp_write_table} where wr_id = '{$rows['wr_id']}' ");
                    $hrefs = get_pretty_url($rows['bo_table'], $row2['wr_id']);
                    
                    //$wr_content = preg_replace("/<(.*?)\>/","",$row2['wr_content']);
                    //$wr_content = preg_replace("/&nbsp;/","",$wr_content);
                    //$wr_content = preg_replace("/&gt;/","",$wr_content);
                    $wr_content = strip_tags($row2['wr_content']);
                    
                    $sec_txt = '<span style="opacity:0.6">작성자 및 관리자 외 열람할 수 없습니다.<br>비밀글 기능으로 보호된 글입니다.</span>';
                ?>

                <dd class="swiper-slide swiper-slide-new-roll">
                    <div>
                        <ul class="bbs_main_wrap_con_ul2">
                            <li class="bbs_main_wrap_con_cont cut"><a href="<?php echo $hrefs ?>" class="font-r"><?php echo get_text($row2['wr_subject']); ?></a></li>
                            <?php if (strstr($row2['wr_option'], 'secret')) { ?>
                            <li class="bbs_main_wrap_con_info2 cut2"><?php echo $sec_txt; ?></li>
                            <?php } else { ?>
                            <li class="bbs_main_wrap_con_info2 cut2"><?php echo $wr_content; ?></li>
                            <?php } ?>
                            <li class="bbs_main_wrap_con_info1"><?php echo passing_time($row2['wr_datetime']); ?>　<span class="font-B"><?php echo get_text($row2['wr_name']); ?></span>　<?php echo get_text($rows['bo_subject']); ?></li>
                        </ul>
                        <div class="cb"></div>
                    </div>
                </dd>
            
                <?php } ?>

            </div>
        </ul> 
    </div>    
         
                
</div>
               
<script>
    
$(document).ready(function(){
        var swiper = new Swiper('.swiper-container-new-roll', {
            touchRatio: 1,
            spaceBetween: 20,
            
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            
            navigation: {
                nextEl: '.arr_sw_next_roll',
                prevEl: '.arr_sw_prev_roll',
            },
        });

}); 

</script>                  