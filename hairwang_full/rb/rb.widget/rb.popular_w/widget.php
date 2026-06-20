<?php
//모듈정보 불러오기
$md_id = $row_mod['md_id']; //현재 모듈 ID
$rb_skin = sql_fetch (" select * from {$rb_module_table} where md_id = '{$md_id}' "); //환경설정 테이블 조회
$md_subject = $rb_skin['md_title']; //모듈 타이틀
?>
<style>
    .bbs_maon_popular_con .point_list_name {width: 65%}
    .bbs_maon_popular_con .point_list_name a {
        float: left;
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
        max-width: 75%;
    }
    
    .bbs_maon_popular_con .popular_icons {
        float: left;
        margin-left: 10px;
        margin-top: 1px;
    }
    
    /* 순위 숫자 숨기기 */
    .bbs_maon_popular_con .point_list_num {
        display: none !important;
    }
    
    /* 숫자가 없을 때 이름 영역 너비 조정 */
    .bbs_maon_popular_con .point_list_name {
        width: 85% !important;
    }
    
    /* 레벨 아이콘 크기 조정 */
    .bbs_maon_popular_con .member_level_icon,
    .bbs_maon_popular_con img[class*="member_level"] {
        width: 16px !important;
        height: 16px !important;
        vertical-align: middle !important;
        margin: 0 3px;
        border: none !important;
    }
</style>
<div class="bbs_main">
    <ul class="bbs_main_wrap_tit">
        <li class="bbs_main_wrap_tit_l">
               <a href="#">
                <h2 class="font-B"><?php echo $md_subject ?></h2>
            </a>
        </li>

        <li class="bbs_main_wrap_tit_r">
            
        </li>

        <div class="cb"></div>
    </ul>
    
    
    <ul class="bbs_main_wrap_point_con bbs_maon_popular_con">
        <div class="rb_swiper" 
            id="rb_swiper_<?php echo $row_mod['md_id'] ?>" 
            data-pc-w="1" 
            data-pc-h="99" 
            data-mo-w="1" 
            data-mo-h="99" 
            data-pc-gap="12" 
            data-mo-gap="12" 
            data-autoplay="0" 
            data-autoplay-time="0" 
            data-pc-swap="0" 
            data-mo-swap="0"
        >
                    
            <div class="rb_swiper_inner">
                <div class="rb-swiper-wrapper swiper-wrapper">
                <!--
                rb_latest_popular(스킨, 갯수, 글자수, 일수, 캐시);
                wr_hit desc 조회수 조건입니다. (변경은 함수를 수정하셔야 합니다.)
                일수 : 30의 경우 오늘로 부터 30일전 입니다.
                -->
                <?php echo rb_latest_popular('basic', 7, 99, 7, 0); ?>

                </div>
            </div>
        </div>

    </ul>
    

</div>