<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.css/point_c.css?ver='.G5_TIME_YMDHIS.'" />', 0);

if(isset($pnt_c['pnt_add_use']) && $pnt_c['pnt_add_use'] == 1) {
    
} else { 
    alert_close('올바른 방법으로 이용해주세요.');
}
?>

<div id="point" class="new_win">
    <form name="fmemoform" action="./point_c_add_update.php" onsubmit="return fpointform_submit(this);" method="post" autocomplete="off">
    <h1 id="win_title"><?php echo $g5['title'] ?></h1>

    <div class="new_win_con2">
       <ul class="win_ul">
           <li class=""><a href="<?php echo G5_URL ?>/rb/point_c.php">보유<?php echo $pnt_c_name ?></a></li>
           <?php if(isset($pnt_c['pnt_add_use']) && $pnt_c['pnt_add_use'] == 1) { ?>
           <li class="selected"><a href="<?php echo G5_URL ?>/rb/point_c.php?types=add"><?php echo $pnt_c_name ?>충전</a></li>
           <?php } ?>
           <?php if(isset($pnt_c['pnt_acc_use']) && $pnt_c['pnt_acc_use'] == 1) { ?>
           <li class=""><a href="<?php echo G5_URL ?>/rb/point_c.php?types=acc"><?php echo $pnt_c_name ?>출금</a></li>
           <?php } ?>
           <div class="cb"></div>
       </ul>
        
        <ul class="point_all">
        	<li class="full_li">
        		보유<?php echo $pnt_c_name ?>
        		<span><?php echo number_format($member['rb_point']); ?> <?php echo $pnt_c_name_st ?></span>
        	</li>
		</ul>
      
        <?php
        $row = sql_fetch(" select COUNT(*) as cnt from rb_point_c_add where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' ");
        
        if($row['cnt'] > 0) {
            $data = sql_fetch(" select * from rb_point_c_add where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' limit 1 ");
        ?>
        
        <input type="hidden" name="cn" value="취소">
        <div class="point_add">
            <div class="rb_inp_wrap new_bbs_border_wrap" style="text-align:center">
                <h6 class="bbs_sub_titles font-B mb-5">충전 신청건이 있습니다.</h6>
                <label class="helps">충전 신청내용을 관리자가 검토하고 있습니다.</label>
                <div class="mt-20 pay_bkis">
                    <input type="hidden" name="p_price" id="p_price">
                    <ul>충전<?php echo $pnt_c_name ?> : <span class="main_color font-B"><?php echo number_format($data['p_point']) ?><?php echo $pnt_c_name_st ?></span></ul>
                    <ul class="font-14 color-777 mt-10">결제금액 : <?php echo number_format($data['p_price']) ?>원</ul>
                </div>
            </div>
        </div>
        <?php } else { ?>
        
        <div class="point_add">
        <div class="rb_inp_wrap new_bbs_border_wrap">
        <ul>
            <li>
               
                <h6 class="bbs_sub_titles font-B mb-5">충전 <?php echo $pnt_c_name ?></h6>
                
                <?php if(isset($pnt_c['pnt_point_add_min']) && $pnt_c['pnt_point_add_min'] > 0 || isset($pnt_c['pnt_point_add_max']) && $pnt_c['pnt_point_add_max'] > 0) { ?>
                <label class="helps">충전하실 <?php echo $pnt_c_name ?>을(를) 입력하세요. <span class="color-000">(최소 <?php echo isset($pnt_c['pnt_point_add_min']) ? number_format($pnt_c['pnt_point_add_min']) : '0'; ?><?php echo $pnt_c_name_st ?><?php if(isset($pnt_c['pnt_point_add_max']) && $pnt_c['pnt_point_add_max'] > 0) { ?> ~ 최대 <?php echo number_format($pnt_c['pnt_point_add_max']) ?><?php echo $pnt_c_name_st ?><?php } ?>)</span></label>
                <?php } else { ?>
                <label class="helps">충전하실 <?php echo $pnt_c_name ?>을(를) 입력하세요.</label>
                <?php } ?>
                
                <div class="radio_gap">
                    <input type="text" name="p_point" value="" id="p_point" class="input required w40 main_rb_bordercolor main_color font-B" placeholder="충전<?php echo $pnt_c_name ?>" required> <span class="font-B"><?php echo $pnt_c_name_st ?></span>
                </div>
            </li>
            
            
                        <script>
                        $(document).ready(function() {

                            // 숫자를 화폐 단위로 변환하는 함수
                            function formatPrice(price) {
                                return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                            }

                            // 1. Select box에서 선택한 옵션의 가격을 표시하는 함수 (할인 적용 및 콤마 추가)
                            $('input[name="p_point"]').on('input', function() {
                                var prices = $('input[name="p_point"]').val();
                                var selPrice = parseFloat(prices) || 0; // 입력된 값이 숫자가 아니면 0으로 처리

                                
                                <?php if(isset($pnt_c['pnt_vat']) && $pnt_c['pnt_vat'] > 0) { ?>
                                    // 부가세 10% 추가 계산
                                    var vatRate = 0.1; // 부가세율 10%
                                    var vatAmount = selPrice * vatRate; // 부가세 계산
                                    var totalPrice = selPrice + vatAmount; // 총 가격 (부가세 포함)
                                <?php } else { ?>
                                    var totalPrice = selPrice;
                                <?php } ?>

                                // 가격이 0보다 작아지지 않도록 설정
                                if (totalPrice < 0) {
                                    totalPrice = 0;
                                }

                                // 총 가격을 반올림하여 정수로 변환
                                totalPrice = Math.round(totalPrice);

                                // 가격을 화폐 단위로 변환해서 표시
                                $('#p_price_txt').text(formatPrice(totalPrice) + '원');

                                // hidden input에 숫자 값만 저장
                                $('#p_price').val(totalPrice);
                            });
                        });
                        </script>
            
            
            
            
            
            
            <li class="mt-20">
                <h6 class="bbs_sub_titles font-B mb-5">결제방법</h6>
                <div class="radio_gap">
                <dd><input type="radio" id="p_pay1" name="p_pay" class="radio_plat" value="무통장" checked><label for="p_pay1">무통장 입금</label></dd>
                </div>
            </li>
            
            <li class="mt-20">
                <h6 class="bbs_sub_titles font-B mb-5">입금자명</h6>
                <label class="helps">실제 입금자명과 일치해야 합니다.</label>

                <input type="text" name="p_bk_name" value="<?php echo $member['mb_name'] ?>" id="p_bk_name" class="input required w30 font-B" placeholder="입금자명" required="">
            </li>
            
            <div class="mt-20">
                <textarea readonly style="min-height:150px; border-color:#ddd !important;"><?php echo $pnt_c['pnt_agree'] ?></textarea>
            </div>
            
            <li class="mt-10"><input type="checkbox" value="동의" name="p_agree" id="p_agree"><label for="p_agree"><?php echo $pnt_c_name ?> 충전 이용약관에 동의 합니다.</label></li>

            
            <div class="mt-20 pay_bkis">
                <input type="hidden" name="p_price" id="p_price">
                <ul>결제하실 금액 : <span class="main_color font-B" id="p_price_txt">0원</span><?php if(isset($pnt_c['pnt_vat']) && $pnt_c['pnt_vat'] > 0) { ?> (VAT포함)<?php } ?></ul>
                <ul>입금계좌 : <?php echo $pnt_c['pnt_bk'] ?></ul>
            </div>
            
            

        </ul>
    </div>
    </div>
       
    <?php } ?>
        
    </div>
    
    <div class="win_btn">
            <?php if($row['cnt'] > 0) { ?>
            <button type="submit" id="btn_submit" class="btn btn_b02 reply_btn">신청취소</button>
            <?php } else { ?>
        	<button type="submit" id="btn_submit" class="btn btn_b02 reply_btn">충전하기</button>
        	<?php } ?>
        	<button type="button" onclick="javascript:window.close();" class="btn_close">창닫기</button>
    </div>
    
    </form>

</div>


<?php if($row['cnt'] > 0) { ?>
<script>
function fpointform_submit(f)
{
    var selectedCn = document.querySelector('input[name="cn"]').value;

    if (selectedCn === "취소") {
        // confirm 대화 상자 띄우기
        if (!confirm("충전 신청을 취소 하시겠습니까?")) {
            return false;
        }
    }
    
    return true;  // 폼 전송 진행
}
</script>
<?php } else { ?>

<script>
function fpointform_submit(f)
{
    
    // 숫자를 화폐 단위로 변환하는 함수
    function formatPrices(price) {
        return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    var selectedPoint = $('input[name="p_point"]').val();
    var selectedPay = $('input[name="p_pay"]:checked').val();
    var selectedAgree = $('input[name="p_agree"]:checked').val();
    
    <?php if (isset($pnt_c['pnt_point_add_min']) && $pnt_c['pnt_point_add_min'] > 0) { ?>
        var minPoint = parseInt("<?php echo $pnt_c['pnt_point_add_min']; ?>", 10); // 숫자로 변환
        selectedPoint = parseInt(selectedPoint, 10); // selectedPoint도 숫자로 변환

        if (minPoint > selectedPoint) {
            alert('최소 ' + formatPrices(minPoint) + '<?php echo $pnt_c_name_st ?> 이상 충전하셔야 합니다.');
            return false;  // 폼 전송 중지
        }
    <?php } ?>
    
    <?php if (isset($pnt_c['pnt_point_add_max']) && $pnt_c['pnt_point_add_max'] > 0) { ?>
        var maxPoint = parseInt("<?php echo $pnt_c['pnt_point_add_max']; ?>", 10); // 숫자로 변환
        selectedPoint = parseInt(selectedPoint, 10); // selectedPoint도 숫자로 변환

        if (maxPoint < selectedPoint) {
            alert('1회 충전시 최대 ' + formatPrices(maxPoint) + '<?php echo $pnt_c_name_st ?> 까지만 충전할 수 있습니다.');
            return false;  // 폼 전송 중지
        }
    <?php } ?>
    
    if (!selectedPoint) {
        alert('충전하실 금액을 선택해 주세요.');
        return false;  // 폼 전송 중지
    }
    
    if (!selectedPay) {
        alert('결제방법을 선택해주세요.');
        return false;  // 폼 전송 중지
    }
    
    if (!selectedAgree) {
        alert('<?php echo $pnt_c_name ?> 충전 이용약관에 동의해주세요.');
        return false;  // 폼 전송 중지
    }

    return true;  // 폼 전송 진행
}
</script>


<?php } ?>