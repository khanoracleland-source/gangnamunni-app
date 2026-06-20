<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

if (!defined('_SHOP_')) {
    $pop_division = 'comm';
} else {
    $pop_division = 'shop';
}

if(IS_MOBILE()) {
    $sql = " select * from rb_popup 
              where '".G5_TIME_YMDHIS."' between po_start and po_end
                and po_device IN ( 'both', 'mobile' ) and po_division IN ( 'both', '".$pop_division."' )
              order by po_id asc ";
    $result = sql_query($sql, false);
} else { 
    $sql = " select * from rb_popup 
              where '".G5_TIME_YMDHIS."' between po_start and po_end
                and po_device IN ( 'both', 'pc' ) and po_division IN ( 'both', '".$pop_division."' )
              order by po_id asc ";
    $result = sql_query($sql, false);
}
?>

<link rel="stylesheet" href="<?php echo G5_URL ?>/rb/rb.mod/popup/style.css?ver=<?php echo G5_TIME_YMDHIS ?>">


<div class="rb_popups" id="rb_pops">

    <div class="rb_popups_wrap">
       
        <?php
        for ($i=0; $po=sql_fetch_array($result); $i++)
        {
            // 이미 체크 되었다면 Continue
            if (isset($_COOKIE["rb_pops_{$po['po_id']}"]) && $_COOKIE["rb_pops_{$po['po_id']}"]) {
                continue;
            }
            
        ?>
       
        <div class="rb_popups_inner" id="rb_pops_<?php echo $po['po_id']; ?>">

            <div class="swiper-container swiper-container-rb-popups">
                <ul class="swiper-wrapper swiper-wrapper-rb-popups">
                       
                    <?php
                    for ($j = 1; $j <= 5; $j++) {
                        $content_key = "po_p{$j}_content";
                        if (isset($po[$content_key]) && $po[$content_key] != "") {
                    ?>
                            <li class="swiper-slide swiper-slide-rb-popups"><?php echo conv_content($po[$content_key], 1); ?></li>
                    <?php 
                        }
                    } 
                    ?>

                </ul>
 
                    <div class="swiper-pagination rb_popup_caption">
                        <?php for ($k = 1; $k <= 5; $k++): 
                            $content_key2 = "po_p{$k}_title";
                            if (isset($po[$content_key2]) && $po[$content_key2] != ""):
                        ?>
                        <div class="bullet-text" data-bullet="<?php echo htmlspecialchars($po[$content_key2]); ?>"></div>
                        <?php endif; endfor; ?>
                    </div>

               
            </div>
            
            <div class="hd_pops_footer">
                <button class="rb_pops_reject rb_pops_<?php echo $po['po_id']; ?> <?php echo $po['po_time']; ?>"><strong><?php echo $po['po_time']; ?></strong>시간 동안 다시 열람하지 않습니다.</button>
                <button class="rb_pops_close rb_pops_<?php echo $po['po_id']; ?>">닫기</button>
            </div>

        </div>
        
        <?php } ?>
    </div>
    
    
</div>





<script>
$(function() {
    // 모든 팝업을 초기 상태에서 숨김
    <?php if(IS_MOBILE()) { ?>
        $('.rb_popups_inner').css({ display: 'none', opacity: '0'});
    <?php } else { ?>
        $('.rb_popups_inner').css({ display: 'none', opacity: '0', top: '70%' });
    <?php } ?>
    
    // Swiper 초기화 함수
    function initializeSwiper(popup) {
        const swiper = new Swiper(`#${popup.attr('id')} .swiper-container`, {
            slidesPerView: 1,
            observer: true,
            observeParents: true,
            touchRatio: 1,
            autoHeight: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: `#${popup.attr('id')} .swiper-pagination`,
                clickable: true,
                renderBullet: function (index, className) {
                    var bulletText = popup.find(`.bullet-text:eq(${index})`).data('bullet');
                    return `<div class="${className}"><span>${bulletText}</span></div>`;
                }
            },
            on: {
                init: function () {
                    // Swiper 초기화 후 아래에서 위로 올라오면서 서서히 보이게 함
                    popup.css({ display: 'block' }).animate({ top: '50%', opacity: '1' }, 600, 'easeOutCubic');
                }
            }
        });
        return swiper;
    }

    // 첫 번째 팝업을 보여주고 Swiper를 초기화하는 함수
    function showPopup(popup) {
        // 팝업 배경 보이기
        $('#rb_pops').css('display', 'block');
        
        <?php if(IS_MOBILE()) { ?>
        popup.css({display: 'block', opacity: '0' })
             .animate({ opacity: '0' }, {
                duration: 0,
                complete: function() {
                    initializeSwiper(popup); // 팝업이 완전히 표시된 후 Swiper 초기화
                }
             });
        <?php } else { ?>
        popup.css({ top: '70%', display: 'block', opacity: '0' })
             .animate({ opacity: '0' }, {
                duration: 0,
                complete: function() {
                    initializeSwiper(popup); // 팝업이 완전히 표시된 후 Swiper 초기화
                }
             });
        <?php } ?>
    }

    // 첫 번째 팝업 표시
    var firstPopup = $('.rb_popups_inner').first();
    if (firstPopup.length > 0) {
        showPopup(firstPopup);
    }

    // "다시 열람하지 않기" 버튼 클릭 시 처리
    $(".rb_pops_reject").click(function() {
        var id = $(this).attr('class').split(' ')[1];
        var exp_time = parseInt($(this).attr('class').split(' ')[2]);

        // 현재 팝업 숨기기
        $("#" + id).hide();

        // 다음 팝업 표시
        var nextPopup = $("#" + id).next('.rb_popups_inner');
        if (nextPopup.length > 0) {
            showPopup(nextPopup);
        }

        // 쿠키 설정
        set_cookie(id, 1, exp_time, g5_cookie_domain);

        // 모든 팝업이 닫혔으면 배경도 숨김
        if ($('.rb_popups_inner:visible').length === 0) {
            $('#rb_pops').hide();
        }
    });

    // "닫기" 버튼 클릭 시 처리
    $('.rb_pops_close').click(function() {
        var idb = $(this).attr('class').split(' ')[1];

        // 현재 팝업 숨기기
        $('#' + idb).hide();

        // 다음 팝업 표시
        var nextPopup = $('#' + idb).next('.rb_popups_inner');
        if (nextPopup.length > 0) {
            showPopup(nextPopup);
        }

        // 모든 팝업이 닫혔으면 배경도 숨김
        if ($('.rb_popups_inner:visible').length === 0) {
            $('#rb_pops').hide();
        }
    });
});
</script>






<!-- } 팝업레이어 끝 -->