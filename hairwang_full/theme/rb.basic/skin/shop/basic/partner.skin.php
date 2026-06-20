<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.css/style.shop.css?ver='.G5_TIME_YMDHIS.'">', 0);
add_javascript('<script src="'.G5_URL.'/rb/rb.mod/partner/partner.js"></script>', 0);

$thumb_width = 120;
$thumb_height = 120;

$main = isset($_GET['main']) ? $_GET['main'] : '';
$sub = isset($_GET['sub']) ? $_GET['sub'] : '';

//예치금 사용여부 판단
$table_rb_point_c_set = sql_query("DESCRIBE rb_point_c_set", false);
?>

<div class="rb_prof_tab rb_prof_partner">

    
    <div>
        
        <nav id="bo_cate" class="swiper-container swiper-container-category">
            <ul id="bo_cate_ul" class="swiper-wrapper swiper-wrapper-category">
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL ?>/rb/partner.php" <?php if($main == "") { ?>id="bo_cate_on"<?php } ?>>홈</a></li>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL ?>/rb/partner.php?main=item&sub=itemlist" <?php if($main == "item") { ?>id="bo_cate_on"<?php } ?>>상품관리</a></li>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL ?>/rb/partner.php?main=order&sub=orderlist" <?php if($main == "order") { ?>id="bo_cate_on"<?php } ?>>주문관리</a></li>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL ?>/rb/partner.php?main=qa&sub=qalist" <?php if($main == "qa") { ?>id="bo_cate_on"<?php } ?>>상품문의</a></li>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL ?>/rb/partner.php?main=review&sub=uselist" <?php if($main == "review") { ?>id="bo_cate_on"<?php } ?>>사용후기</a></li>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL ?>/rb/partner.php?main=amount" <?php if($main == "amount") { ?>id="bo_cate_on"<?php } ?>>정산내역</a></li>
                
                <?php if ($table_rb_point_c_set) { ?>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL; ?>/rb/point_c.php" target="_blank" class="win_point">예치금내역</a></li>
                <li class="swiper-slide swiper-slide-category"><a href="<?php echo G5_URL; ?>/rb/point_c.php?types=acc" target="_blank" class="win_point">출금신청</a></li>
                <?php } ?>
                
            </ul>
        </nav>

        <script>
            $(document).ready(function(){
                $("#bo_cate_ul li").addClass("swiper-slide swiper-slide-category");
            });
            
            // class="act"를 가진 요소를 찾기
            var activeElement = document.querySelector('.swiper-slide-category a#bo_cate_on');

            // 초기 슬라이드 인덱스를 담을 변수
            var initialSlideIndex = 0;

            if (activeElement) {
                // 부모 li 태그를 가져옴
                var parentLi = activeElement.closest('.swiper-slide-category');

                // 모든 슬라이드 요소들을 가져옴
                var allSlides = document.querySelectorAll('.swiper-slide-category');

                // 부모 li 태그의 인덱스를 계산
                initialSlideIndex = Array.prototype.indexOf.call(allSlides, parentLi);
            }

            var swiper = new Swiper('.swiper-container-category', {
                slidesPerView: 'auto', //가로갯수
                spaceBetween: 0, // 간격
                //slidesOffsetBefore: 40, //좌측여백
                //slidesOffsetAfter: 40, // 우측여백
                observer: true, //리셋
                observeParents: true, //리셋
                touchRatio: 1, // 드래그 가능여부
                initialSlide: initialSlideIndex, // 초기 슬라이드 인덱스 설정

            });

        </script>

        
    </div>
        
        <?php if($main == "") { ?>
        <div>
            <ul class="cont_info_wrap">
                <li class="cont_info_wrap_l">
                    <dd>닉네임</dd>
                    <dd><?php echo $mb['mb_nick'] ?> <!--<span>@<?php echo $mb['mb_id'] ?></span>--></dd>
                </li>
                <li class="cont_info_wrap_r">
                    <dd>회원레벨</dd>
                    <dd><?php echo $mb['mb_level'] ?>레벨</dd>
                </li>
                <div class="cb"></div>
            </ul>
            <ul class="cont_info_wrap">
                
                <?php if ($table_rb_point_c_set) { ?>
                <li class="cont_info_wrap_l">
                    <dd><?php echo $pnt_c_name ?></dd>
                    <dd>
                        <a href="<?php echo G5_URL; ?>/rb/point_c.php" target="_blank" class="win_point"><?php echo number_format($member['rb_point']); ?><?php echo $pnt_c_name_st ?></a>
                    </dd>
                </li>
                <?php } else { ?>
                <li class="cont_info_wrap_l">
                    <dd>포인트</dd>
                    <dd>
                        <a href="<?php echo G5_BBS_URL ?>/point.php" target="_blank" class="win_point"><?php echo number_format($member['mb_point']); ?>P</a>
                    </dd>
                </li>
                <?php } ?>
                
                
                <li class="cont_info_wrap_r">
                    <dd>정산계좌</dd>
                    <dd><?php echo ($member['mb_bank'] ? $member['mb_bank'] : '미등록'); ?></dd>
                </li>
                <div class="cb"></div>
            </ul>
            <ul class="cont_info_wrap">
                <li class="cont_info_wrap_l">
                    <dd>판매수수료</dd>
                    <dd>
                    <?php 
                    if(isset($pa['pa_ssr2']) && $pa['pa_ssr2'] > 0) { 
                        echo number_format($pa['pa_ssr2'])."원 (판매대금-".number_format($pa['pa_ssr2'])."원)";
                    } else { 
                        echo ($pa['pa_ssr'] ? $pa['pa_ssr'] : '0')."% (판매대금의 ".number_format($pa['pa_ssr'])."%)";
                    }
                    ?>
                    </dd>
                </li>
                <li class="cont_info_wrap_r">
                    <dd>출금가능일</dd>
                    <dd>
                        <?php 
                        if(isset($pa['pa_day']) && $pa['pa_day'] > 0) { 
                            echo "정산 완료일로부터 ".number_format($pa['pa_day'])."일";
                        } else { 
                            echo "즉시출금 가능";
                        }
                        ?>
                    </dd>
                </li>
                <div class="cb"></div>
            </ul>
        </div>
        <?php } ?>
        
        <?php 
        //상품관리
        if(isset($pa['pa_is']) && $pa['pa_is'] == 1) { 
            if($main == "item" && $sub == "itemlist") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/itemlist.php');
            }
            if($main == "item" && $sub == "itemform") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/itemform.php');
            }
            if($main == "order" && $sub == "orderlist") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/orderlist.php');
            }
            if($main == "order" && $sub == "orderform") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/orderform.php');
            }
            if($main == "qa" && $sub == "qalist") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/itemqalist.php');
            }
            if($main == "qa" && $sub == "qaform") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/itemqaform.php');
            }
            if($main == "review" && $sub == "uselist") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/itemuselist.php');
            }
            if($main == "review" && $sub == "useform") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/itemuseform.php');
            }
            if($main == "amount") { 
                include_once(G5_PATH.'/rb/rb.mod/partner/amount.php');
            }
        }
        ?>

    
</div>


<div class="cb"></div>