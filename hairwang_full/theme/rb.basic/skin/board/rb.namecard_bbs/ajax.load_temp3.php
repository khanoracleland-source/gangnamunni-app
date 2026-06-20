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
$wr_23 = isset($write['wr_23']) ? $write['wr_23'] : '';
$tmp3 = !empty($wr_23) ? explode("|", $wr_23) : array();

// 저장된 항목을 변수로 분리
foreach($tmp3 as $value) {
    if (strpos($value, '=') !== false) {
        list($key, $value2) = explode('=', $value);
        $$key = $value2;
    }
}

// wr_23_cnt 초기화
$inpcnt3 = isset($write['wr_23_cnt']) && $write['wr_23_cnt'] ? $write['wr_23_cnt'] : 1;

// 파일 정보 가져오기
$file_new3 = get_file_new($wr_id, '3');

// board_skin_url이 정의되어 있지 않으면 설정
if (!isset($board_skin_url)) {
    $board_skin_url = G5_URL . '/theme/rb.basic/skin/board/rb.namecard_bbs';
}
?>

<input type="hidden" name="wr_23_cnt" value="<?php echo $inpcnt3 ?>">

<div>
    <div class="nc_left3">
        <span class="sub_inp_tit font-R">출력예시 미리보기</span>

        <div class="ex_lay1">
            <img src="<?php echo $board_skin_url; ?>/img/rev.png">
            <li class="font-B lay_tit mt-20">달고단 사과주스 만들기 체험단</li>
            <li class="font-B lay_pri main_color">50,000원</li>
            <li class="lay_sub">달고단 사과주스 만들기 체험 입니다. 진짜 맛있는 사과! 꼭 오셔서 만들어보시고, 무료로 받아가세요!</li>
            <li class="lay_sub rb_inp_wrap_confirm">
                <a href="javascript:void(0);" class="btn_cancel btn font-B">전화예약</a>
                <button type="button" id="" class="btn_submit btn font-B" onclick="">온라인예약</button>
            </li>
        </div>
    </div>

    <div class="nc_left po_rel">
        <span class="sub_inp_tit font-R">상세정보 입력</span>

        <div class="" id="appends3">
            <div class="append append3 drag drag3 move_td">
                <?php 
                $j = -1;
                for($i = 1; $i <= $inpcnt3; $i++) { 
                    $j++;
                    
                    // 동적 변수 안전하게 처리
                    $ex23_1_val = isset(${'ex23_1_'.$i}) ? ${'ex23_1_'.$i} : '';
                    $ex23_2_val = isset(${'ex23_2_'.$i}) ? ${'ex23_2_'.$i} : '';
                    $ex23_3_val = isset(${'ex23_3_'.$i}) ? ${'ex23_3_'.$i} : '';
                    $ex23_4_val = isset(${'ex23_4_'.$i}) ? ${'ex23_4_'.$i} : '';
                    $ex23_5_val = isset(${'ex23_5_'.$i}) ? ${'ex23_5_'.$i} : '';
                    $ex23_6_val = isset(${'ex23_6_'.$i}) ? ${'ex23_6_'.$i} : '';
                    $ex23_7_val = isset(${'ex23_7_'.$i}) ? ${'ex23_7_'.$i} : '';
                    $ex23_8_val = isset(${'ex23_8_'.$i}) ? ${'ex23_8_'.$i} : '';
                    $ex23_9_val = isset(${'ex23_9_'.$i}) ? ${'ex23_9_'.$i} : '';
                ?>

                <div id="append3_<?php echo $i ?>" class="bg_d bg_d3">
                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex23_1_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex23_1_val); ?>" id="ex23_1_<?php echo $i ?>" class="input full_input" placeholder="제목">
                        </ul>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex23_2_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex23_2_val); ?>" id="ex23_2_<?php echo $i ?>" class="input full_input" placeholder="링크 (https:// 포함)">
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex23_3_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex23_3_val); ?>" id="ex23_3_<?php echo $i ?>" class="input full_input" placeholder="강조워딩">
                        </ul>
                    </div>

                    <div class="rb_inp_wrap">
                        <ul>
                            <input type="text" name="ex23_4_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex23_4_val); ?>" id="ex23_4_<?php echo $i ?>" class="input full_input" placeholder="서브워딩">
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <span class="sub_inp_tit font-R">예약금 설정</span>
                        <ul>
                            <input type="text" name="ex23_5_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex23_5_val); ?>" id="ex23_5_<?php echo $i ?>" class="input full_input" placeholder="예약금">
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <span class="sub_inp_tit font-R">예약금 입금계좌 (예약금이 없는 경우 노출되지 않습니다.)</span>
                        <ul>
                            <input type="text" name="ex23_6_<?php echo $i ?>" value="<?php echo htmlspecialchars($ex23_6_val); ?>" id="ex23_6_<?php echo $i ?>" class="input full_input" placeholder="입금계좌">
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <span class="sub_inp_tit font-R">기타 안내사항을 입력하세요.</span>
                        <ul>
                            <textarea name="ex23_7_<?php echo $i ?>" id="ex23_7_<?php echo $i ?>" class="input full_input" style="min-height:100px;" placeholder="기타 안내사항"><?php echo htmlspecialchars($ex23_7_val); ?></textarea>
                        </ul>
                    </div>
                    
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <span class="sub_inp_tit font-R">예약방법</span>
                        <ul>
                            <input type="checkbox" id="ex23_8_<?php echo $i ?>" class="magic-checkbox" name="ex23_8_<?php echo $i ?>" value="1" <?php if($ex23_8_val == 1) { ?>checked<?php } ?>> 
                            <label for="ex23_8_<?php echo $i ?>">전화예약</label>　
                            <input type="checkbox" id="ex23_9_<?php echo $i ?>" class="magic-checkbox" name="ex23_9_<?php echo $i ?>" value="1" <?php if($ex23_9_val == 1) { ?>checked<?php } ?>> 
                            <label for="ex23_9_<?php echo $i ?>">온라인예약</label>
                        </ul>
                    </div>
                </div>

                <?php } ?>
            </div>
        </div>
    </div>

    <div class="nc_left2 sticty">
        <div class="pl-20">
            <button type="button" class="can_btn3">
                <svg width="60" height="60" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="0.5" y="0.5" width="59" height="59" rx="9.5" fill="white" stroke="black" />
                    <path d="M23 29H37" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
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
    $(".can_btn3").off("click").on("click", function(e) {
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
    });
</script>