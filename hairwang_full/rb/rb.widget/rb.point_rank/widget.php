<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 출력 인원수
$limit = 10; // 롤링에 표시할 총 인원수

// 제외할 아이디
$exclude_ids_array = ['admin'];
$exclude_ids = implode("','", $exclude_ids_array);

// 현재 보유 포인트를 기준으로 순위를 매기는 쿼리
$sql_current_points = "
    SELECT
        mb_id,
        mb_nick,
        mb_point AS total_points
    FROM
        {$g5['member_table']}
    WHERE
        mb_id NOT IN ('{$exclude_ids}')
        AND mb_point > 0
    ORDER BY
        mb_point DESC
    LIMIT {$limit}
";
$current_points_result = sql_query($sql_current_points);
?>

<!-- 헤더 포인트 랭킹 위젯 시작 -->
<div id="pointWidget" class="hd_point_rank_widget">
    <div class="hd_point_rank_container">
        <span class="hd_point_rank_label">💰</span>
        <div class="hd_point_rank_viewport">
            <ul class="hd_point_rank_list">
                <?php
                $rank = 1;
                $items = array(); // 실제 데이터를 저장할 배열
                $first_item = null; // 첫 번째 아이템 저장
                
                while ($rows = sql_fetch_array($current_points_result)) {
                    $mb_id = $rows['mb_id'];
                    $mb_nick = cut_str($rows['mb_nick'], 10);
                    $total_points = number_format($rows['total_points']);

                    // 첫 번째 아이템 저장 (복제용)
                    if ($rank == 1) {
                        $first_item = array(
                            'mb_id' => $mb_id,
                            'mb_nick' => $mb_nick,
                            'total_points' => $total_points
                        );
                    }

                    // 1~3등 클래스 적용
                    $rank_class = '';
                    if ($rank == 1) $rank_class = 'rank_1st';
                    elseif ($rank == 2) $rank_class = 'rank_2nd';
                    elseif ($rank == 3) $rank_class = 'rank_3rd';

                    echo '<li class="hd_point_rank_item">'
                       . '<span class="hd_point_rank_num '.$rank_class.'">'.$rank.'위</span>'
                       . '<span class="hd_point_rank_name" onclick="location.href=\''.G5_URL.'/rb/home.php?mb_id='.$mb_id.'\';">'.$mb_nick.'</span>'
                       . '<span class="hd_point_rank_point">'.$total_points.'<span style="color:#0000ff;font-weight:600;"> P</span></span>'
                       . '</li>';
                    
                    $items[] = $rows; // 실제 데이터 저장
                    $rank++;
                }
                
                // 무한 롤링을 위해 첫 번째 아이템 복제 (null 체크 추가)
                if (!empty($items) && $first_item !== null) {
                    echo '<li class="hd_point_rank_item">'
                       . '<span class="hd_point_rank_num rank_1st">1위</span>'
                       . '<span class="hd_point_rank_name" onclick="location.href=\''.G5_URL.'/rb/home.php?mb_id='.urlencode($first_item['mb_id']).'\';">'.$first_item['mb_nick'].'</span>'
                       . '<span class="hd_point_rank_point">'.$first_item['total_points'].'<span style="color:#0000ff;font-weight:600;"> P</span></span>'
                       . '</li>';
                }
                
                // 데이터가 없는 경우 처리
                if (empty($items)) {
                    echo '<li class="hd_point_rank_item">'
                       . '<span class="hd_point_rank_num">1위</span>'
                       . '<span class="hd_point_rank_name">데이터 없음</span>'
                       . '<span class="hd_point_rank_point">0<span style="color:#0000ff; font-weight:600;"> P</span></span>'
                       . '</li>';
                }
                ?>
            </ul>
        </div>
    </div>
</div>
<!-- 헤더 포인트 랭킹 위젯 끝 -->

<style>
  /* 네임스페이스를 pointWidget으로 제한 */
  #pointWidget.hd_point_rank_widget { display: inline-block; vertical-align: middle; margin-right: -110px; position: relative; }
  #pointWidget .hd_point_rank_container { background: #f8f9fa; border-radius: 20px; padding: 0 15px; height: 35px; display: flex; align-items: center; overflow: hidden; min-width: 220px; }
  #pointWidget .hd_point_rank_label { font-size: 12px; font-weight: 600; color: #666; margin-right: 8px; }
  #pointWidget .hd_point_rank_viewport { height: 35px; overflow: hidden; position: relative; flex: 1; }
  #pointWidget .hd_point_rank_list { position: absolute; top: 0; left: 0; margin: 0; padding: 0; list-style: none; width: 100%; }
  #pointWidget .hd_point_rank_item { height: 35px; display: flex; align-items: center; font-size: 13px; white-space: nowrap; }
  #pointWidget .hd_point_rank_num { font-weight: 700; color: #667eea; margin-right: 6px; }
  #pointWidget .hd_point_rank_num.rank_1st { color: #ffd700; }
  #pointWidget .hd_point_rank_num.rank_2nd { color: #c0c0c0; }
  #pointWidget .hd_point_rank_num.rank_3rd { color: #cd7f32; }
  #pointWidget .hd_point_rank_name { color: #333; font-weight: 600; margin-right: 6px; cursor: pointer; transition: color 0.2s; }
  #pointWidget .hd_point_rank_name:hover { color: #667eea; }
  #pointWidget .hd_point_rank_point { font-size: 12px; color: #666; font-weight: 600; }
  @media (max-width: 1024px) { #pointWidget.hd_point_rank_widget { display: none !important; } }
</style>

<script>
(function($){
  $(function(){
    var $list = $('#pointWidget .hd_point_rank_list');
    var $items = $list.find('.hd_point_rank_item');
    var itemH = 35, count = $items.length - 1, idx = 0;
    if(count > 1) {
      setInterval(function(){
        idx++;
        $list.animate({ top: -(idx * itemH) }, 400, function(){
          if(idx >= count) { idx = 0; $list.css('top', 0); }
        });
      }, 3000);
    }
  });
})(jQuery);
</script>