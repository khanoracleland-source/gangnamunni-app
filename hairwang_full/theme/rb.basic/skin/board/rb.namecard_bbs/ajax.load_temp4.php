<?php
include_once('../../../../../common.php');

// POST 데이터 받기
$bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : '';
$wr_id = isset($_POST['wr_id']) ? $_POST['wr_id'] : 0;

// write 데이터 가져오기
$write = array();
if ($wr_id) {
    // 실제 환경에서는 여기서 DB 쿼리를 수행
    // $write = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id = '{$wr_id}'");
}

// get_file_new 함수가 없는 경우 정의
if (!function_exists('get_file_new')) {
    function get_file_new($wr_id, $idx) {
        $files = array();
        for ($i = 0; $i < 10; $i++) {
            $files[$i] = array(
                'file' => '',
                'bf_source' => ''
            );
        }
        return $files;
    }
}

// 변수 초기화 및 안전한 처리
$wr_24 = isset($write['wr_24']) ? $write['wr_24'] : '';
$tmp4 = !empty($wr_24) ? explode("|", $wr_24) : array();

// 저장된 항목을 변수로 분리
foreach($tmp4 as $value) {
    if (strpos($value, '=') !== false) {
        list($key, $value2) = explode('=', $value);
        $$key = $value2;
    }
}

// wr_24_cnt 초기화
$inpcnt4 = isset($write['wr_24_cnt']) && $write['wr_24_cnt'] ? $write['wr_24_cnt'] : 1;

// 파일 정보 가져오기
$file_new4 = get_file_new($wr_id, '4');

// board_skin_url이 정의되어 있지 않으면 설정
if (!isset($board_skin_url)) {
    $board_skin_url = G5_URL . '/theme/rb.basic/skin/board/rb.namecard_bbs';
}
?>

<input type="hidden" name="wr_24_cnt" value="<?php echo $inpcnt4 ?>">

<div>
    <div class="nc_left3">
        <span class="sub_inp_tit font-R">출력예시 미리보기</span>
        
        <div>
            <div class="ex_lay2">
                <li class="font-B lay_tit">아이스 아메리카노 한잔 무료</li>
                <li class="lay_cp font-B main_color">아이스 아메리카노 S</li>
            </div>

            <div class="ex_lay2_btm">
                <li class="fl lay_pri">스타벅스</li>
                <li class="fr lay_date">2024-12-31 만료</li>
                <div class="cb"></div>
            </div>

            <li class="lay_sub">매장직원에게 발급받으신 쿠폰을 제시해주세요.<br><br></li>
        </div>
        
        <div>
            <div class="ex_lay2">
                <li class="font-B lay_tit">교촌치킨 정률 할인</li>
                <li class="lay_cp font-B main_color">30% 할인</li>
            </div>

            <div class="ex_lay2_btm">
                <li class="fl lay_pri">교촌치킨</li>
                <li class="fr lay_date">2024-12-31 만료</li>
                <div class="cb"></div>
            </div>

            <li class="lay_sub">매장직원에게 발급받으신 쿠폰을 제시해주세요.<br><br></li>
        </div>
    </div>

    <div class="nc_left po_rel">
        <span class="sub_inp_tit font-R">상세정보 입력</span>

        <div class="" id="appends4">
            <div class="append append4 drag drag4 move_td">
                <?php 
                $j = -1;
                for($i = 1; $i <= $inpcnt4; $i++) { 
                    $j++;
                    
                    // 동적 변수 안전하게 처리
                    $ex24_1_val = isset(${'ex24_1_'.$i}) ? ${'ex24_1_'.$i} : '';
                    $ex24_2_val = isset(${'ex24_2_'.$i}) ? ${'ex24_2_'.$i} : '';
                    $ex24_3_val = isset(${'ex24_3_'.$i}) ? ${'ex24_3_'.$i} : '';
                    $ex24_4_val = isset(${'ex24_4_'.$i}) ? ${'ex24_4_'.$i} : '';
                    $ex24_5_val = isset(${'ex24_5_'.$i}) ? ${'ex24_5_'.$i} : '';
                    $ex24_6_val = isset(${'ex24_6_'.$i}) ? ${'ex24_6_'.$i} : '';
                ?>

                <div id="append4_<?php echo $i ?>" class="bg_d bg_d4">
                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex24_1_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex24_1_val); ?>" id="ex24_1_<?php echo $i ?>" class="input full_input" placeholder="제목">
                        </ul>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex24_3_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex24_3_val); ?>" id="ex24_3_<?php echo $i ?>" class="input full_input" placeholder="사용처">
                        </ul>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex24_4_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex24_4_val); ?>" id="ex24_4_<?php echo $i ?>" class="input full_input" placeholder="설명">
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <span class="sub_inp_tit font-R">할인금액(할인율) 또는 품목</span>
                        <ul>
                            <input type="text" name="ex24_5_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex24_5_val); ?>" id="ex24_5_<?php echo $i ?>" class="input full_input" placeholder="할인금액 또는 품목">
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <span class="sub_inp_tit font-R">쿠폰 만료일</span>
                        <ul>
                            <input type="date" name="ex24_6_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex24_6_val); ?>" id="ex24_6_<?php echo $i ?>" class="input full_input" placeholder="쿠폰 만료일">
                        </ul>
                    </div>
                </div>

                <?php } ?>
            </div>
        </div>
    </div>

    <div class="nc_left2 sticty">
        <div class="pl-20">
            <button type="button" class="can_btn can_btn4">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.5" width="59" height="59" rx="9.5" fill="white" stroke="black" />
                    <path d="M23 29H37" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <button type="button" class="add_btn add_btn4">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.5" width="59" height="59" rx="9.5" fill="white" stroke="black" />
                    <path d="M30 22V36M23 29H37" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <button type="button" class="move_up">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.5" width="59" height="59" rx="9.5" fill="white" stroke="black" />
                    <path d="M30 40V20" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M25 25L30 20L35 25" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <button type="button" class="move_down">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.5" width="59" height="59" rx="9.5" fill="white" stroke="black" />
                    <path d="M30 20V40" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M25 35L30 40L35 35" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="cb"></div>
</div>

<script>
    var maxField4 = 10;
    var wrapper4 = $('.append4');
    var extcnt4 = <?php echo $inpcnt4 ?>;

    $(".add_btn4").off("click").on("click", function(e) {
        if (extcnt4 < maxField4) {
            extcnt4++;

            var fieldHTML4 = '<div id="append4_' + extcnt4 + '" class="bg_d bg_d4">'
                + '<div class="rb_inp_wrap">'
                + '    <ul>'
                + '        <input type="text" name="ex24_1_' + extcnt4 + '" value="" id="ex24_1_' + extcnt4 + '" class="input full_input" placeholder="제목">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap">'
                + '    <ul>'
                + '        <input type="text" name="ex24_3_' + extcnt4 + '" value="" id="ex24_3_' + extcnt4 + '" class="input full_input" placeholder="사용처">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap">'
                + '    <ul>'
                + '        <input type="text" name="ex24_4_' + extcnt4 + '" value="" id="ex24_4_' + extcnt4 + '" class="input full_input" placeholder="설명">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap rb_inp_wrap_gap">'
                + '    <span class="sub_inp_tit font-R">할인금액(할인율) 또는 품목</span>'
                + '    <ul>'
                + '        <input type="text" name="ex24_5_' + extcnt4 + '" value="" id="ex24_5_' + extcnt4 + '" class="input full_input" placeholder="할인금액 또는 품목">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap rb_inp_wrap_gap">'
                + '    <span class="sub_inp_tit font-R">쿠폰 만료일</span>'
                + '    <ul>'
                + '        <input type="date" name="ex24_6_' + extcnt4 + '" value="" id="ex24_6_' + extcnt4 + '" class="input full_input" placeholder="쿠폰 만료일">'
                + '    </ul>'
                + '</div>'
                + '</div>';

            $('.append4').append(fieldHTML4);
            
            if ($('.drag4').length && typeof $.fn.sortable !== 'undefined') {
                $('.drag4').sortable('refresh');
            }
            
            $('input[name="wr_24_cnt"]').val(extcnt4);
        } else {
            alert(maxField4 + '개 이상 추가할 수 없습니다.');
        }
    });

    function del_lines4(no) {
        if (extcnt4 > 0) {
            extcnt4--;
            $('#append4_' + no).remove();
            
            if ($('.drag4').length && typeof $.fn.sortable !== 'undefined') {
                $('.drag4').sortable('refresh');
            }
            
            $('input[name="wr_24_cnt"]').val(extcnt4);
            dels4();
        } else {
            alert('데이터는 한개이상 있어야 합니다.');
        }
    }

    $(".can_btn4").off("click").on("click", function(e) {
        if (extcnt4 > 1) {
            extcnt4--;
            var cnts4 = extcnt4 + 1;
            $('#append4_' + cnts4).remove();
            
            if ($('.drag4').length && typeof $.fn.sortable !== 'undefined') {
                $('.drag4').sortable('refresh');
            }
            
            $('input[name="wr_24_cnt"]').val(extcnt4);
        } else {
            var parentDivId = $(this).closest('[id^="temp_div"]').attr('id');
            
            if (parentDivId) {
                var lastNumber = parseInt(parentDivId.replace('temp_div', ''), 10);
                var targetNumber = lastNumber - 1;
                
                console.log("Current ID:", parentDivId, "Target to remove:", targetNumber);
                
                $(this).parents().eq(2).html('');
                
                if (typeof clickedOrder !== 'undefined' && targetNumber >= 0 && targetNumber < clickedOrder.length) {
                    clickedOrder.splice(targetNumber, 1);
                }
                
                console.log("Updated clickedOrder:", typeof clickedOrder !== 'undefined' ? clickedOrder : []);
            }
        }
    });

    function dels4() {
        const divs4 = document.getElementsByClassName('bg_d4');

        for (let i = 0; i < divs4.length; i++) {
            $("#" + divs4[i].id + " input").each(function(index) {
                $(this).attr("name", "ex24_" + (index + 1) + "_" + (i + 1));
                $(this).attr("id", "ex24_" + (index + 1) + "_" + (i + 1));
            });

            $("#" + divs4[i].id + " select").each(function(index) {
                $(this).attr("name", "ex24_" + (index + 1) + "_" + (i + 1));
                $(this).attr("id", "ex24_" + (index + 1) + "_" + (i + 1));
            });
        }
    }

    $(function() {
        if (!$('.drag4').length || typeof $.fn.sortable === 'undefined') {
            return;
        }

        var originalWidth, originalHeight;

        $(".drag4").sortable({
            placeholder: "placeholders_box",
            tolerance: "pointer",
            helper: function(event, ui) {
                var helper = ui.clone();
                return helper.css({
                    position: 'absolute',
                    width: ui.outerWidth(),
                    height: ui.outerHeight()
                });
            },
            start: function(event, ui) {
                originalWidth = ui.item.outerWidth();
                originalHeight = ui.item.outerHeight();
                ui.helper.addClass("dragging");
                ui.helper.css({
                    width: originalWidth,
                    height: originalHeight,
                    position: 'absolute'
                });

                var parentOffset = ui.item.parent().offset();
                var currentOffset = ui.item.offset();

                ui.helper.css({
                    top: currentOffset.top - parentOffset.top,
                    left: currentOffset.left - parentOffset.left
                });

                var paddingLeft = parseInt(ui.helper.css('padding-left'), 0) || 0;
                var paddingRight = parseInt(ui.helper.css('padding-right'), 0) || 0;
                var paddingTop = parseInt(ui.helper.css('padding-top'), 0) || 0;
                var paddingBottom = parseInt(ui.helper.css('padding-bottom'), 0) || 0;

                $(".placeholders_box").css({
                    width: originalWidth - paddingLeft - paddingRight,
                    height: originalHeight - paddingTop - paddingBottom,
                    margin: '0px'
                });
            },
            stop: function(event, ui) {
                ui.item.removeClass("dragging");
                ui.item.css({
                    width: originalWidth,
                    height: originalHeight
                });

                var parentOffset = $(".drag4").offset();
                if (parentOffset) {
                    var itemOffset = ui.helper ? ui.helper.offset() : null;
                    var itemWidth = ui.helper ? ui.helper.outerWidth() : 0;
                    var itemHeight = ui.helper ? ui.helper.outerHeight() : 0;

                    if (itemOffset && (itemOffset.left < parentOffset.left ||
                            itemOffset.top < parentOffset.top ||
                            itemOffset.left + itemWidth > parentOffset.left + $(".drag4").outerWidth() ||
                            itemOffset.top + itemHeight > parentOffset.top + $(".drag4").outerHeight())) {
                        $(".drag4").sortable("cancel");
                    }
                }
            },
            update: function(event, ui) {
                $('.drag4 .bg_d4').each(function(index) {
                    var newIndex = index + 1;

                    $(this).attr('id', 'append4_' + newIndex);
                    $(this).find('input').each(function() {
                        var inputName = $(this).attr('name');
                        var inputId = $(this).attr('id');

                        if (inputName && inputName.match(/ex24_\d+_\d+/)) {
                            var updatedName = inputName.replace(/\d+$/, newIndex);
                            $(this).attr('name', updatedName);
                        }

                        if (inputId && inputId.match(/ex24_\d+_\d+/)) {
                            var updatedId = inputId.replace(/\d+$/, newIndex);
                            $(this).attr('id', updatedId);
                        }
                    });
                });
            }
        }).disableSelection();

        $(".bg_d4").on("mousedown", function(event) {
            $(".bg_d4").removeClass("dragging");
            var $this = $(this);
            originalWidth = $this.outerWidth();
            originalHeight = $this.outerHeight();
            $this.css({
                width: originalWidth,
                height: originalHeight
            });
            $this.addClass("clicked");
        });

        $(".bg_d4").on("mouseup", function(event) {
            var $this = $(this);
            $this.css({
                width: originalWidth,
                height: originalHeight
            });
        });
    });
</script>