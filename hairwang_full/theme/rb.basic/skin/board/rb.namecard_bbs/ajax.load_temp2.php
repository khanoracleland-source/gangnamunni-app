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
$wr_22 = isset($write['wr_22']) ? $write['wr_22'] : '';
$tmp2 = !empty($wr_22) ? explode("|", $wr_22) : array();

// 저장된 항목을 변수로 분리
foreach($tmp2 as $value) {
    if (strpos($value, '=') !== false) {
        list($key, $value2) = explode('=', $value);
        $$key = $value2;
    }
}

// wr_22_cnt 초기화
$inpcnt2 = isset($write['wr_22_cnt']) && $write['wr_22_cnt'] ? $write['wr_22_cnt'] : 1;

// 파일 정보 가져오기
$file_new2 = get_file_new($wr_id, '2');

// board_skin_url이 정의되어 있지 않으면 설정
if (!isset($board_skin_url)) {
    $board_skin_url = G5_URL . '/theme/rb.basic/skin/board/rb.namecard_bbs';
}
?>

<input type="hidden" name="wr_22_cnt" value="<?php echo $inpcnt2 ?>">

<div>
    <div class="nc_left3">
        <span class="sub_inp_tit font-R">출력예시 미리보기</span>

        <div class="ex_lay1">
            <img src="<?php echo $board_skin_url; ?>/img/eximage.png">
            <li class="font-B lay_tit mt-20">경주 달고단 사과 1박스</li>
            <li class="lay_sub">경주 달고단 사과 1박스, 선물용 포장 가능합니다. 진짜 맛있는 사과! 꼭 맛보세요.</li>
        </div>
    </div>

    <div class="nc_left po_rel">
        <span class="sub_inp_tit font-R">상세정보 입력</span>

        <div class="" id="appends2">
            <div class="append append2 drag drag2 move_td">
                <?php 
                $j = -1;
                for($i = 1; $i <= $inpcnt2; $i++) { 
                    $j++;
                    
                    // 동적 변수 안전하게 처리
                    $ex22_1_val = isset(${'ex22_1_'.$i}) ? ${'ex22_1_'.$i} : '';
                    $ex22_2_val = isset(${'ex22_2_'.$i}) ? ${'ex22_2_'.$i} : '';
                    $ex22_3_val = isset(${'ex22_3_'.$i}) ? ${'ex22_3_'.$i} : '';
                ?>

                <div id="append2_<?php echo $i ?>" class="bg_d bg_d2">
                    <div class="rb_inp_wrap">
                        <ul class="file_input_wrap">
                            <input type="file" name="bf_file_new2[]" title="이미지" class="input full_input file_input" accept="image/*">
                            <?php if(isset($file_new2[$j]['file']) && $file_new2[$j]['file']) { ?>
                            <span class="file_input_chk">
                                <input type="checkbox" id="bf_file_new2_del<?php echo $j ?>" class="magic-checkbox" name="bf_file_new2_del[<?php echo $j ?>]" value="1"> 
                                <label for="bf_file_new2_del<?php echo $j ?>">삭제</label>
                            </span>
                            <?php } ?>
                        </ul>
                        <?php if(isset($file_new2[$j]['bf_source']) && $file_new2[$j]['bf_source']) { ?>
                        <div class="mt-10 color-888 font-12">등록파일 : <?php echo $file_new2[$j]['bf_source'] ?></div>
                        <?php } ?>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex22_1_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex22_1_val); ?>" id="ex22_1_<?php echo $i ?>" class="input full_input" placeholder="제목">
                        </ul>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex22_2_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex22_2_val); ?>" id="ex22_2_<?php echo $i ?>" class="input full_input" placeholder="링크 (https:// 포함)">
                        </ul>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex22_3_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex22_3_val); ?>" id="ex22_3_<?php echo $i ?>" class="input full_input" placeholder="워딩">
                        </ul>
                    </div>
                </div>

                <?php } ?>
            </div>
        </div>
    </div>

    <div class="nc_left2 sticty">
        <div class="pl-20">
            <button type="button" class="can_btn can_btn2">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.5" width="59" height="59" rx="9.5" fill="white" stroke="black" />
                    <path d="M23 29H37" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <button type="button" class="add_btn add_btn2">
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
    var maxField2 = 10;
    var wrapper2 = $('.append2');
    var extcnt2 = <?php echo $inpcnt2 ?>;

    $(".add_btn2").off("click").on("click", function(e) {
        if (extcnt2 < maxField2) {
            extcnt2++;

            var fieldHTML2 = '<div id="append2_' + extcnt2 + '" class="bg_d bg_d2">'
                + '<div class="rb_inp_wrap">'
                + '    <ul class="file_input_wrap">'
                + '        <input type="file" name="bf_file_new2[]" title="이미지" class="input full_input file_input" accept="image/*">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap">'
                + '    <ul>'
                + '        <input type="text" name="ex22_1_' + extcnt2 + '" value="" id="ex22_1_' + extcnt2 + '" class="input full_input" placeholder="제목">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap">'
                + '    <ul>'
                + '        <input type="text" name="ex22_2_' + extcnt2 + '" value="" id="ex22_2_' + extcnt2 + '" class="input full_input" placeholder="링크 (https:// 포함)">'
                + '    </ul>'
                + '</div>'
                + '<div class="rb_inp_wrap">'
                + '    <ul>'
                + '        <input type="text" name="ex22_3_' + extcnt2 + '" value="" id="ex22_3_' + extcnt2 + '" class="input full_input" placeholder="워딩">'
                + '    </ul>'
                + '</div>'
                + '</div>';

            $('.append2').append(fieldHTML2);
            
            if ($('.drag2').length && typeof $.fn.sortable !== 'undefined') {
                $('.drag2').sortable('refresh');
            }
            
            $('input[name="wr_22_cnt"]').val(extcnt2);
        } else {
            alert(maxField2 + '개 이상 추가할 수 없습니다.');
        }
    });

    function del_lines2(no) {
        if (extcnt2 > 0) {
            extcnt2--;
            $('#append2_' + no).remove();
            
            if ($('.drag2').length && typeof $.fn.sortable !== 'undefined') {
                $('.drag2').sortable('refresh');
            }
            
            $('input[name="wr_22_cnt"]').val(extcnt2);
            dels2();
        } else {
            alert('데이터는 한개이상 있어야 합니다.');
        }
    }

    $(".can_btn2").off("click").on("click", function(e) {
        if (extcnt2 > 1) {
            extcnt2--;
            var cnts2 = extcnt2 + 1;
            $('#append2_' + cnts2).remove();
            
            if ($('.drag2').length && typeof $.fn.sortable !== 'undefined') {
                $('.drag2').sortable('refresh');
            }
            
            $('input[name="wr_22_cnt"]').val(extcnt2);
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

    function dels2() {
        const divs2 = document.getElementsByClassName('bg_d2');

        for (let i = 0; i < divs2.length; i++) {
            $("#" + divs2[i].id + " input").each(function(index) {
                $(this).attr("name", "ex22_" + (index + 1) + "_" + (i + 1));
                $(this).attr("id", "ex22_" + (index + 1) + "_" + (i + 1));
            });

            $("#" + divs2[i].id + " select").each(function(index) {
                $(this).attr("name", "ex22_" + (index + 1) + "_" + (i + 1));
                $(this).attr("id", "ex22_" + (index + 1) + "_" + (i + 1));
            });
        }
    }

    $(function() {
        if (!$('.drag2').length || typeof $.fn.sortable === 'undefined') {
            return;
        }

        var originalWidth, originalHeight;

        $(".drag2").sortable({
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

                var parentOffset = $(".drag2").offset();
                if (parentOffset) {
                    var itemOffset = ui.helper ? ui.helper.offset() : null;
                    var itemWidth = ui.helper ? ui.helper.outerWidth() : 0;
                    var itemHeight = ui.helper ? ui.helper.outerHeight() : 0;

                    if (itemOffset && (itemOffset.left < parentOffset.left ||
                            itemOffset.top < parentOffset.top ||
                            itemOffset.left + itemWidth > parentOffset.left + $(".drag2").outerWidth() ||
                            itemOffset.top + itemHeight > parentOffset.top + $(".drag2").outerHeight())) {
                        $(".drag2").sortable("cancel");
                    }
                }
            },
            update: function(event, ui) {
                $('.drag2 .bg_d2').each(function(index) {
                    var newIndex = index + 1;

                    $(this).attr('id', 'append2_' + newIndex);
                    $(this).find('input').each(function() {
                        var inputName = $(this).attr('name');
                        var inputId = $(this).attr('id');

                        if (inputName && inputName.match(/ex22_\d+_\d+/)) {
                            var updatedName = inputName.replace(/\d+$/, newIndex);
                            $(this).attr('name', updatedName);
                        }

                        if (inputId && inputId.match(/ex22_\d+_\d+/)) {
                            var updatedId = inputId.replace(/\d+$/, newIndex);
                            $(this).attr('id', updatedId);
                        }
                    });
                });
            }
        }).disableSelection();

        $(".bg_d2").on("mousedown", function(event) {
            $(".bg_d2").removeClass("dragging");
            var $this = $(this);
            originalWidth = $this.outerWidth();
            originalHeight = $this.outerHeight();
            $this.css({
                width: originalWidth,
                height: originalHeight
            });
            $this.addClass("clicked");
        });

        $(".bg_d2").on("mouseup", function(event) {
            var $this = $(this);
            $this.css({
                width: originalWidth,
                height: originalHeight
            });
        });
    });
</script>