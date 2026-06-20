<!--
경로 : /rb/rb.widget/rb.point_rank/
출처 : https://sir.kr/g5_skin/59210
제작자 : 미니님a

사용자코드를 입력하세요.
배너관리 > 출력형태 : 개별출력 ID=5 배너가 연동되어 있습니다.
배너를 변경하고자 하시는 경우 마지막 라인의 rb_banners() 함수의 ID를 변경해주세요.
-->

<?php

//모듈정보 불러오기
$md_id = $row_mod['md_id']; //현재 모듈 ID
$rb_skin = sql_fetch (" select * from {$rb_module_table} where md_id = '{$md_id}' "); //환경설정 테이블 조회
$md_subject = $rb_skin['md_title']; //모듈 타이틀

// 이번 주의 시작과 지난 주의 시작 계산
$this_week_start = date('Y-m-d', strtotime('last Monday'));
$last_week_start = date('Y-m-d', strtotime('last Monday -1 week'));
$last_week_end = date('Y-m-d', strtotime('last Sunday'));

// 출력 인원수
$limit = 5;

// 제외할 아이디
$exclude_ids_array = ['admin', 'webmaster', 'test3'];
$exclude_ids = implode("','", $exclude_ids_array);

// 현재 주의 총 포인트를 가져오는 쿼리
$sql_current_week = "
    SELECT
        m.mb_id,
        m.mb_nick,
        SUM(IFNULL(p.po_point, 0)) AS total_points
    FROM
        {$g5['member_table']} m
    LEFT JOIN
        {$g5['point_table']} p ON m.mb_id = p.mb_id
    WHERE
        m.mb_id NOT IN ('{$exclude_ids}')
        AND (m.mb_leave_date IS NULL OR m.mb_leave_date = '' OR m.mb_leave_date > DATE_FORMAT(NOW(), '%Y%m%d'))
        AND (m.mb_intercept_date IS NULL OR m.mb_intercept_date = '' OR m.mb_intercept_date > DATE_FORMAT(NOW(), '%Y%m%d'))
    GROUP BY
        m.mb_id
    ORDER BY
        total_points DESC
    LIMIT {$limit}
";
$current_week_result = sql_query($sql_current_week);

// 지난 주의 총 포인트를 가져오는 쿼리
$sql_last_week = "
    SELECT
        m.mb_id,
        SUM(IFNULL(p.po_point, 0)) AS total_points
    FROM
        {$g5['member_table']} m
    LEFT JOIN
        {$g5['point_table']} p ON m.mb_id = p.mb_id
    WHERE
        m.mb_id NOT IN ('{$exclude_ids}')
        AND (m.mb_leave_date IS NULL OR m.mb_leave_date = '' OR m.mb_leave_date > DATE_FORMAT(NOW(), '%Y%m%d'))
        AND (m.mb_intercept_date IS NULL OR m.mb_intercept_date = '' OR m.mb_intercept_date > DATE_FORMAT(NOW(), '%Y%m%d'))
        AND p.po_datetime BETWEEN '{$last_week_start}' AND '{$last_week_end}'
    GROUP BY
        m.mb_id
    ORDER BY
        total_points DESC
";
$last_week_result = sql_query($sql_last_week);


// 지난 주의 랭킹을 계산
$last_week_ranking = [];
$rank = 1;
while ($rows = sql_fetch_array($last_week_result)) {
    $last_week_ranking[$rows['mb_id']] = $rank++;
}

// 순위 변동 계산 함수
function get_rank_change($mb_id, $current_rank, $last_week_ranking) {
    if (isset($last_week_ranking[$mb_id])) {
        $last_rank = $last_week_ranking[$mb_id];
        $change = $last_rank - $current_rank;
        if ($change > 0) {
            return "<span style='color:blue'>▲ {$change}</span>";
        } elseif ($change < 0) {
            return "<span style='color:red'>▼ " . abs($change) . "</span>";
        } else {
            return "";
        }
    } else {
        return "<span style='color:#7a4efe'>New</span>";
    }
}
?>
<link rel="stylesheet" href="<?php echo G5_URL ?>/rb/rb.widget/user.point_profile_rank/style.css">

<div class="bbs_main">

    <ul class="bbs_main_wrap_tit">
        <li class="bbs_main_wrap_tit_l"><a href="#">
                <h2 class="font-B"><?php echo $md_subject ?></h2>
            </a>
        </li>
        <!--
        <li class="bbs_main_wrap_tit_r">
            <button type="button" class="tiny_tab_btn active">보유</button>
            <button type="button" class="tiny_tab_btn">누적</button>
        </li>
        -->
        <div class="cb"></div>
    </ul>
    <ul class="bbs_main_wrap_point_con">
        <div class="swiper-container swiper-container-point_rank">
            <ul class="swiper-wrapper swiper-wrapper-point_rank">

                <?php
                    $rank = 1;
                    while ($rows = sql_fetch_array($current_week_result)) {
                        $mb_id = $rows['mb_id'];
                        $total_points = number_format($rows['total_points']);
                        $rank_change = get_rank_change($mb_id, $rank, $last_week_ranking);
                        $profile_img = '<span class="profile_img">' . get_member_profile_img($mb_id) . '</span>';
                        $mb_nick = $profile_img . '<a href="' . G5_URL . '/rb/home.php?mb_id=' . $mb_id . '"><span style="margin-left: 5px;">' . get_text_with_member_level($rows['mb_nick'], $mb_id) . '</span></a>';

                        echo "<dd class='swiper-slide swiper-slide-point_rank'>";
                        if($rank == 1) {
                            echo "<span class='point_list_num top1 point_rank_num'>{$rank}</span>";
                        } else if($rank == 2) {
                            echo "<span class='point_list_num top2 point_rank_num'>{$rank}</span>";
                        } else if($rank == 3) {
                            echo "<span class='point_list_num top3 point_rank_num'>{$rank}</span>";
                        } else { 
                            echo "<span class='point_list_num point_rank_num'>{$rank}</span>";
                        }
                        echo "<span class='point_list_name'><span class='cut_img'>{$mb_nick}</span></span>";
                        echo "<span class='point_list_point font-H'>{$total_points} <img src='/img/point.png' alt='포인트' style='width: 16px; height: 16px; vertical-align: middle; margin-left: 2px;'></span>";
                        echo "<span class='point_list_ch'>{$rank_change}</span>";
                        echo "</dd>";
                        
                        $rank++;
                    }
                ?>
                <!-- } -->

            </ul>
        </div>

        <script>
            var swiper = new Swiper('.swiper-container-point_rank', {
                slidesPerColumnFill: 'row', //세로형
                slidesPerView: 1, //가로갯수
                slidesPerColumn: 10, // 세로갯수
                spaceBetween: 12, // 간격
                observer: true, //리셋
                observeParents: true, //리셋
                touchRatio: 0, // 드래그 가능여부

                breakpoints: { // 반응형
                    1024: {
                        slidesPerView: 1, //가로갯수
                        slidesPerColumn: 10, // 세로갯수

                        spaceBetween: 12, // 간격
                    },
                    10: {
                        slidesPerView: 1, //가로갯수
                        slidesPerColumn: 10, // 세로갯수

                        spaceBetween: 12, // 간격
                    }
                }

            });
        </script>

    </ul>


</div>