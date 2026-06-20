<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

global $row_mod, $rb_module_table;
$rb_skin = sql_fetch (" select * from {$rb_module_table} where md_id = '{$row_mod['md_id']}' "); //환경설정 테이블 조회 (삭제금지)
$md_banner_bg = isset($rb_skin['md_banner_bg']) ? $rb_skin['md_banner_bg'] : '';

?>

<style>
    .swiper-wrapper-slide_wide_bn {text-align: center;}
    .swiper-button-next-wide {right:10%;}
    .swiper-button-prev-wide {left:10%;}
    @media all and (max-width:1024px) {
        .swiper-button-next-wide {right:20px;}
        .swiper-button-prev-wide {left:20px;}
        .swiper-button-next-wide svg {width: 10px;}
        .swiper-button-prev-wide svg {width: 10px;}
        .rb_wide_bn_wrap {padding: 0px;}
    }
</style>

<?php
$i = 0; // $i 변수를 초기화

while ($row = sql_fetch_array($result)) {
    $bn_border  = isset($row['bn_border']) && $row['bn_border'] ? ' bn_border' : '';
    $bn_radius  = isset($row['bn_radius']) && $row['bn_radius'] ? ' bn_radius' : '';
    
    // 새창 옵션
    $bn_new_win = isset($row['bn_new_win']) && $row['bn_new_win'] ? ' target="_blank"' : '';
    
    if ($i == 0) echo '<div class="mod_bn_wrap rb_wide_bn_wrap rb_wide_bn_'.$row_mod['md_id'].'" style="background-color:'.$md_banner_bg.'"><div class="swiper-container swiper-container-slide_wide_bn_'.$row_mod['md_id'].'"><ul class="swiper-wrapper swiper-wrapper-slide_wide_bn swiper-wrapper-slide_wide_bn_'.$row_mod['md_id'].'">'.PHP_EOL;
    
    $bimg = G5_DATA_PATH.'/banners/'.$row['bn_id'];
    if (file_exists($bimg)) {
        $banner = '';
        $size = getimagesize($bimg);
        $img_width = $size[0];
        echo '<div class="swiper-slide swiper-slide-slide_wide_bn_'.$row_mod['md_id'].' slide_item top_ad">'.PHP_EOL;
        if ($row['bn_url'][0] == '#')
            $banner .= '<a href="'.$row['bn_url'].'">';
        else if ($row['bn_url'] && $row['bn_url'] != 'http://') {
            $banner .= '<a href="'.G5_URL.'/rb/rb.mod/banner/bannerhit.php?bn_id='.$row['bn_id'].'"'.$bn_new_win.'>';
        }
        echo $banner.'<img src="'.G5_DATA_URL.'/banners/'.$row['bn_id'].'?ver='.G5_SERVER_TIME.'" title="'.get_text($row['bn_alt']).'" width="100%" class="'.$bn_radius.'" style="max-width:'.$img_width.'px;">';
        if ($banner) {
            echo '</a>'.PHP_EOL;
        }
        
        if (isset($row['bn_ad_ico']) && $row['bn_ad_ico']) {
            echo '<span class="ico_ad">AD</span>'.PHP_EOL;
        }
        echo '</div>'.PHP_EOL;
    }
    $i++;
}

if ($i > 0) echo '</ul>';

if ($i > 1) echo '
<div class="swiper-button-next swiper-button-next-wide swiper-button-next-wide_'.$row_mod['md_id'].'">
<svg width="24" height="46" viewBox="0 0 24 46" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M1 45L22.3333 23L1 1" stroke="#09244B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
</div>
<div class="swiper-button-prev swiper-button-prev-wide swiper-button-prev-wide_'.$row_mod['md_id'].'">
<svg width="24" height="46" viewBox="0 0 24 46" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M23 0.999999L1.66667 23L23 45" stroke="#09244B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
</div>'.PHP_EOL;

if ($i > 0) echo '</div></div>';

?>
                    


                    <script>
                                                
                        var swiper = new Swiper('.swiper-container-slide_wide_bn_<?php echo $row_mod['md_id'] ?>', {
                            slidesPerView: 1, // 가로갯수
                            spaceBetween: 0, // 간격
                            observer: true, // 리셋
                            observeParents: true, // 리셋
                            autoHeight:true,
                            <?php if ($i > 1) { // 이미지가 1개 이상인 경우 터치와 루프 활성 ?>
                                touchRatio: 1,
                                loop: true,
                            <?php } else { ?>
                                touchRatio: 0,
                                loop: false,
                            <?php } ?>
                            
                            navigation: { //네비
                                nextEl: '.swiper-button-next-wide_<?php echo $row_mod['md_id'] ?>',
                                prevEl: '.swiper-button-prev-wide_<?php echo $row_mod['md_id'] ?>',
                            },


                        });
                        

                    </script>


<script>
        
        //부모 width를 무시하고 div 를 100%로 만들고, 모듈설정 버튼의 100% 처리를 위해 스크립트를 사용 합니다.
        //복제 사용을 위해 $row_mod['md_id'](모듈ID) 를 활용 합니다.
    
        function adjustDivWidth_<?php echo $row_mod['md_id'] ?>() {
            const content_w = $('.rb_wide_bn_<?php echo $row_mod['md_id'] ?>');
            const firstAdminOv_w = content_w.nextUntil('.admin_ov').next('.admin_ov');
            
            if ($(window).width() > <?php echo $rb_core['main_width'] ?>) {
                content_w.css({
                    'width': '100vw',
                    'position': 'relative',
                    'left': '50%',
                    'transform': 'translateX(-50%)'
                });
                firstAdminOv_w.css({
                    'width': '100vw',
                    'left': '50%',
                    'transform': 'translateX(-50%)'
                });
            } else {
                content_w.css({
                    'width': '100%',
                    'position': 'static',
                    'left': '0',
                    'transform': 'none'
                });
                firstAdminOv_w.css({
                    'width': '100%',
                    'left': '0',
                    'transform': 'none'
                });
            }
        }

        $(document).ready(adjustDivWidth_<?php echo $row_mod['md_id'] ?>);
        $(window).resize(adjustDivWidth_<?php echo $row_mod['md_id'] ?>);

</script>