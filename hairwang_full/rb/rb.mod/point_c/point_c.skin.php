<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);
?>

<div id="point" class="new_win">
    <h1 id="win_title"><?php echo $g5['title'] ?></h1>

    <div class="new_win_con2">
       <ul class="win_ul">
           <li class="selected"><a href="<?php echo G5_URL ?>/rb/point_c.php">보유<?php echo $pnt_c_name ?></a></li>
           
           <?php if(isset($pnt_c['pnt_add_use']) && $pnt_c['pnt_add_use'] == 1) { ?>
           <li class=""><a href="<?php echo G5_URL ?>/rb/point_c.php?types=add"><?php echo $pnt_c_name ?>충전</a></li>
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
        <ul class="point_list">
            <?php
            $sum_point1 = $sum_point2 = $sum_point3 = 0;

            $i = 0;
            foreach((array) $list as $row){
                $point1 = $point2 = 0;
                $point_use_class = '';
                if ($row['po_point'] > 0) {
                    $point1 = '+' .number_format($row['po_point']);
                    $sum_point1 += $row['po_point'];
                } else {
                    $point2 = number_format($row['po_point']);
                    $sum_point2 += $row['po_point'];
                    $point_use_class = 'point_use';
                }

                $po_content = $row['po_content'];

                $expr = '';
                if($row['po_expired'] == 1)
                    $expr = ' txt_expired';
            ?>
            <li class="<?php echo $point_use_class; ?>">
                <div class="point_top">
                    <span class="point_tit"><?php echo $po_content; ?></span>
                    <span class="point_num <?php if ($point2) { ?>reds<?php } ?>"><?php if ($point1) echo $point1; else echo $point2; ?></span>
                </div>
                <span class="point_date1"><?php echo $row['po_datetime']; ?></span>
                <span class="point_date<?php echo $expr; ?>">
                    <?php if ($row['po_expired'] == 1) { ?>
                    만료 <?php echo substr(str_replace('-', '', $row['po_expire_date']), 2); ?>
                    <?php } else echo $row['po_expire_date'] == '9999-12-31' ? '&nbsp;' : $row['po_expire_date']; ?>
                </span>
            </li>
            <?php
                $i++;
            }   // end foreach

            if ($i == 0)
                echo '<li class="empty_li">자료가 없습니다.</li>';
            else {
                if ($sum_point1 > 0)
                    $sum_point1 = "+" . number_format($sum_point1);
                $sum_point2 = number_format($sum_point2);
            }
            ?>

            <li class="point_status">
                소계
                <span><?php echo $sum_point1; ?></span>
                <span><?php echo $sum_point2; ?></span>
            </li>
        </ul>
    </div>

    <?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$qstr.'&amp;page='); ?>
    <div class="win_btn mt-20">
    <button type="button" onclick="javascript:window.close();" class="btn_close">창닫기</button>
    </div>
</div>