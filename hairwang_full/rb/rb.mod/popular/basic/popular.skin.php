<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
?>

<?php
    $rank = 1;
    for ($i = 0; $i < count($list); $i++) {
        echo "<dd class='rb_swiper_list'>";
        if ($rank == 1) {
            echo "<span class='point_list_num top1_bg'>{$rank}</span>";
        } else if ($rank == 2) {
            echo "<span class='point_list_num top2_bg'>{$rank}</span>";
        } else { 
            echo "<span class='point_list_num'>{$rank}</span>";
        }
        echo "<span class='point_list_name'>";
        echo "<a href='{$list[$i]['href']}'>{$list[$i]['subject']}</a>";
        if($list[$i]['wr_comment'] > 0) {
            echo "<span class='popular_icons font-B main_color'>{$list[$i]['wr_comment']}</span>";
        }
        echo "<span class='cb'></span>";
        echo "</span>";
        echo "<span class='point_list_point font-R'>".passing_time3($list[$i]['wr_datetime'])."</span>";
        echo "</dd>";
        
        $rank++;
    }

    if (count($list) == 0) {
        echo "게시물이 없습니다.";
    }
?>