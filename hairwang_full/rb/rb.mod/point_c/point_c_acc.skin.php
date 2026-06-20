<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.css/point_c.css?ver='.G5_TIME_YMDHIS.'" />', 0);

if(isset($pnt_c['pnt_acc_use']) && $pnt_c['pnt_acc_use'] == 1) {
    
} else { 
    alert_close('올바른 방법으로 이용해주세요.');
}
?>

<div id="point" class="new_win">
    <form name="fmemoform" action="./point_c_acc_update.php" onsubmit="return fpointform_submit(this);" method="post" autocomplete="off">
    <h1 id="win_title"><?php echo $g5['title'] ?></h1>

    <div class="new_win_con2">
       <ul class="win_ul">
           <li class=""><a href="<?php echo G5_URL ?>/rb/point_c.php">보유<?php echo $pnt_c_name ?></a></li>
           <?php if(isset($pnt_c['pnt_add_use']) && $pnt_c['pnt_add_use'] == 1) { ?>
           <li class=""><a href="<?php echo G5_URL ?>/rb/point_c.php?types=add"><?php echo $pnt_c_name ?>충전</a></li>
           <?php } ?>
           <?php if(isset($pnt_c['pnt_acc_use']) && $pnt_c['pnt_acc_use'] == 1) { ?>
           <li class="selected"><a href="<?php echo G5_URL ?>/rb/point_c.php?types=acc"><?php echo $pnt_c_name ?>출금</a></li>
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
        $row = sql_fetch(" select COUNT(*) as cnt from rb_point_c_acc where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' ");
        
        if($row['cnt'] > 0) {
            $data = sql_fetch(" select * from rb_point_c_acc where p_mb_id = '{$member['mb_id']}' and p_status = '접수' and p_use = '0' limit 1 ");
        ?>
        
        <input type="hidden" name="cn" value="취소">
        <div class="point_add">
            <div class="rb_inp_wrap new_bbs_border_wrap" style="text-align:center">
                <h6 class="bbs_sub_titles font-B mb-5">출금 대기건이 있습니다.</h6>
                <label class="helps">출금 신청내용을 관리자가 검토하고 있습니다.</label>
                <div class="mt-20 pay_bkis">
                    <input type="hidden" name="p_price" id="p_price">
                    <ul>입금예정 금액 : <span class="main_color font-B"><?php echo number_format($data['p_price']) ?>원</span></ul>
                    <ul class="font-14 color-777 mt-10"><?php echo $data['p_bank'] ?></ul>
                </div>
            </div>
        </div>
        <?php } else { ?>
        
        <div class="point_add">
            <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>
                <li>
                    <h6 class="bbs_sub_titles font-B mb-5">출금 <?php echo $pnt_c_name ?></h6>
                    <label class="helps">출금신청 하실 <?php echo $pnt_c_name ?>을(를) 입력하세요. <span class="color-000">(최소 <?php echo isset($pnt_c['pnt_point_acc_min']) ? number_format($pnt_c['pnt_point_acc_min']) : '0'; ?><?php echo $pnt_c_name_st ?><?php if(isset($pnt_c['pnt_point_acc_max']) && $pnt_c['pnt_point_acc_max'] > 0) { ?> ~ 최대 <?php echo number_format($pnt_c['pnt_point_acc_max']) ?><?php echo $pnt_c_name_st ?><?php } ?>)</span></label>
                    <div class="radio_gap">
                        <input type="number" name="p_point" value="" id="p_point" class="input required w40 main_rb_bordercolor main_color font-B" placeholder="출금신청 <?php echo $pnt_c_name ?>" required=""> <span class="font-B"><?php echo $pnt_c_name_st ?></span>
                    </div>
                </li>
                
                <li>
                    <h6 class="bbs_sub_titles font-B mt-20">출금 계좌 (계좌번호, 은행, 예금주명)</h6>
                    <label class="helps mt-5">본인명의 계좌만 신청 가능하며, 신청하신 포인트는 우선 차감 됩니다.<br>출금 불가 사유시 선 차감된 <?php echo $pnt_c_name ?>은(는) 재지급 됩니다.</label>
                    <div class="radio_gap">
                        <input type="text" name="p_bank" value="<?php echo isset($member['mb_bank']) ? $member['mb_bank'] : ''; ?>" id="p_bank" class="input required w80 main_color font-B" placeholder="출금계좌" required="">
                    </div>
                </li>

                <div class="mt-20">
                    <textarea readonly style="min-height:150px; border-color:#ddd !important;"><?php echo $pnt_c['pnt_agree2'] ?></textarea>
                </div>

                <li class="mt-10"><input type="checkbox" value="동의" name="p_agree" id="p_agree"><label for="p_agree"><?php echo $pnt_c_name ?> 출금 이용약관에 동의 합니다.</label></li>
                
                <?php
                if (isset($pnt_c['pnt_point_acc_ssr_w']) && $pnt_c['pnt_point_acc_ssr_w'] > 0) {
                    $ssr = "(수수료 : ".number_format($pnt_c['pnt_point_acc_ssr_w'])."".$pnt_c_name_st.")";
                } else { 
                    if (isset($pnt_c['pnt_point_acc_ssr_p']) && $pnt_c['pnt_point_acc_ssr_p'] > 0) {
                        $ssr = "(수수료 : ".number_format($pnt_c['pnt_point_acc_ssr_p'])."%)";
                    } else { 
                        $ssr = "";
                    }
                }
                ?>

                <div class="mt-20 pay_bkis">
                    <ul class="">출금신청 <?php echo $pnt_c_name ?> : <span class="main_color font-B" id="p_price_txt">0<?php echo $pnt_c_name_st ?></span></ul>
                    <ul class="mt-5">출금 후 잔여 <?php echo $pnt_c_name ?> : <span class="main_color font-B" id="p_price_txt2">0<?php echo $pnt_c_name_st ?></span></ul>
                    <ul class="mt-5">입금예정 금액 : <span class="main_color font-B" id="p_price_txt3">0원</span> <span class="font-R"><?php echo $ssr ?></span></ul>
                </div>

                <input type="hidden" id="p_ssr" name="p_ssr">
                <input type="hidden" id="p_price" name="p_price">

            </ul>
        </div>
        </div>
        
        <?php } ?>
        
    </div>
    
    <div class="win_btn">
            <?php if($row['cnt'] > 0) { ?>
            <button type="submit" id="btn_submit" class="btn btn_b02 reply_btn">신청취소</button>
            <?php } else { ?>
        	<button type="submit" id="btn_submit" class="btn btn_b02 reply_btn">출금신청하기</button>
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
        if (!confirm("출금 신청건을 취소 하시겠습니까?\n접수된 내용은 삭제되며 차감된 <?php echo $pnt_c_name ?>은(는) 재지급 됩니다.")) {
            return false;
        }
    }
    
    return true;  // 폼 전송 진행
}
</script>
<?php } else { ?>

<script>
    
    
    document.getElementById('p_point').addEventListener('input', function() {
        var selectedPoint = parseInt(this.value) || 0;  // 입력된 값, 값이 없으면 0으로 처리
        var p_price_txt = document.getElementById('p_price_txt');
        var p_price_txt2 = document.getElementById('p_price_txt2');
        var p_price_txt3 = document.getElementById('p_price_txt3');
        var mb_points = <?php echo $member['rb_point'] ?>;  // 보유 포인트
        
        
        <?php
        // feeRate 설정
        if (isset($pnt_c['pnt_point_acc_ssr_w']) && $pnt_c['pnt_point_acc_ssr_w'] > 0) {
            // 백분율이 아닌 경우 (정액)
            echo "var feeRate = {$pnt_c['pnt_point_acc_ssr_w']};";
        } elseif (isset($pnt_c['pnt_point_acc_ssr_p']) && $pnt_c['pnt_point_acc_ssr_p'] > 0) {
            // 백분율인 경우
            echo "var feeRate = {$pnt_c['pnt_point_acc_ssr_p']} / 100;";
        } else {
            // 수수료가 없는 경우
            echo "var feeRate = 0;";
        }
        ?>

        var remainingPoint = mb_points - selectedPoint;

        // 수수료 계산
        var fee;
        if (feeRate > 1) { 
            // 정액 수수료
            fee = feeRate;
        } else {
            // 백분율 수수료
            fee = selectedPoint * feeRate;
        }
        
        var amountAfterFee = selectedPoint - fee;
        amountAfterFee = Math.floor(amountAfterFee);
        
        // 10원 단위로 내림 처리 (1원 단위 삭제)
        amountAfterFee = Math.floor(amountAfterFee / 10) * 10;
        
        if (amountAfterFee < 0) {
            amountAfterFee = 0;
        }

        // 출금신청 금액 출력
        if (remainingPoint >= 0) {
            p_price_txt.textContent = selectedPoint.toLocaleString() + '<?php echo $pnt_c_name_st ?>';
            p_price_txt2.textContent = remainingPoint.toLocaleString() + '<?php echo $pnt_c_name_st ?>';
            p_price_txt3.textContent = amountAfterFee.toLocaleString() + '원';
            document.getElementById('p_price').value = amountAfterFee;
            document.getElementById('p_ssr').value = fee;
        } else {
            p_price_txt.textContent = '신청불가';  // 보유 포인트보다 크면 출금 불가
            p_price_txt2.textContent = '0P';
            p_price_txt3.textContent = '0원';
        }
    });
    
function fpointform_submit(f)
{

    var selectedPoint = document.querySelector('input[name="p_point"]');
    var selectedBank = document.querySelector('input[name="p_bank"]');
    var selectedAgree = document.querySelector('input[name="p_agree"]:checked');
    
    var mb_point = parseInt("<?php echo $member['rb_point'] ?>", 10);  // 보유 포인트
    
    <?php if (isset($pnt_c['pnt_point_acc_min']) && $pnt_c['pnt_point_acc_min'] > 0) { ?>
    var acc_point_min = parseInt("<?php echo $pnt_c['pnt_point_acc_min'] ?>", 10); // 최소치를 숫자로 변환
    if (parseInt(selectedPoint.value, 10) < acc_point_min) {
        alert('출금신청 금액은 최소 <?php echo number_format($pnt_c['pnt_point_acc_min']); ?><?php echo $pnt_c_name_st ?> 이상이어야 합니다.');
        return false;  // 폼 전송 중지
    }
    <?php } ?>
    
    <?php if (isset($pnt_c['pnt_point_acc_max']) && $pnt_c['pnt_point_acc_max'] > 0) { ?>
    var acc_point_max = parseInt("<?php echo $pnt_c['pnt_point_acc_max'] ?>", 10); // 최대치를 숫자로 변환
    if (parseInt(selectedPoint.value, 10) > acc_point_max) {
        alert('출금신청 금액은 1회 최대 <?php echo number_format($pnt_c['pnt_point_acc_max']); ?><?php echo $pnt_c_name_st ?> 까지 가능 합니다.');
        return false;  // 폼 전송 중지
    }
    <?php } ?>
    
    
    if (!selectedPoint.value) {
        alert('출금 신청하실 <?php echo $pnt_c_name ?>을(를) 입력하세요.');
        return false;  // 폼 전송 중지
    }
    
    if (parseInt(selectedPoint.value) > mb_point) {
        alert('출금신청 하신 <?php echo $pnt_c_name ?>이(가) 보유 <?php $pnt_c_name ?> 보다 많습니다.');
        return false;  // 폼 전송 중지
    }
    
    if (!selectedBank.value) {
        alert('출금계좌가 없어 출금신청이 불가능 합니다.\n본인명의 출금계좌를 입력해주세요.');
        return false;  // 폼 전송 중지
    }
    
    if (!selectedAgree) {
        alert('<?php echo $pnt_c_name ?> 출금 이용약관에 동의해주세요.');
        return false;  // 폼 전송 중지
    }

    return true;  // 폼 전송 진행
}
</script>


<?php } ?>