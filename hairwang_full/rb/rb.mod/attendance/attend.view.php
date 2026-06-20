<?php
// guard
if (!defined('_GNUBOARD_')) exit;

// lib
include_once(G5_PATH.'/rb/rb.mod/attendance/attend.lib.php');
add_stylesheet('<link rel="stylesheet" href="'.G5_URL.'/rb/rb.mod/attendance/css/style.css?ver='.G5_SERVER_TIME.'">', 0);

// 설정 로드
$cf = rb_attend_get_config();
$rb_rank_enabled = array(
  1 => !empty($cf['bonus_rank1']),
  2 => !empty($cf['bonus_rank2']),
  3 => !empty($cf['bonus_rank3']),
  4 => !empty($cf['bonus_rank4']),
  5 => !empty($cf['bonus_rank5'])
);

// 파라미터
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($year < 1970) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

// 달력 범위
$first = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
$start_w = (int)$first->format('w');
$days = (int)$first->format('t');
$range_from = $first->format('Ymd');
$last = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $days));
$range_to = $last->format('Ymd');

// 내 출석 데이터(현재 월)
$mine = array();
if (isset($is_member) && $is_member) {
    $rs = sql_query("
        SELECT REPLACE(ymd,'-','') AS ymd8, at_rank
        FROM rb_attendance
        WHERE mb_id='".sql_real_escape_string($member['mb_id'])."'
          AND REPLACE(ymd,'-','') BETWEEN '{$range_from}' AND '{$range_to}'
    ");
    while ($r = sql_fetch_array($rs)) {
        $mine[$r['ymd8']] = (int)$r['at_rank'];
    }
}

// 날짜들
$today_ymd8 = rb_attend_norm_ymd8(defined('G5_TIME_YMD') ? G5_TIME_YMD : date('Ymd'));
$prev = (clone $first)->modify('-1 month');
$next = (clone $first)->modify('+1 month');

// 선택 날짜
$sel_ymd8 = isset($_GET['ymd']) ? preg_replace('/[^0-9]/','', $_GET['ymd']) : $today_ymd8;
if (strlen($sel_ymd8) !== 8) $sel_ymd8 = $today_ymd8;

// 오늘 완료 여부
$rb_today_done = isset($mine[$today_ymd8]);

// 헬퍼
function rb_fmt_dot($ymd8){ return substr($ymd8,0,4).'.'.substr($ymd8,4,2).'.'.substr($ymd8,6,2); }

// 서버사이드 목록 쿼리
$mt = $g5['member_table'];
$total_row = sql_fetch("SELECT COUNT(*) AS cnt FROM rb_attendance WHERE REPLACE(ymd,'-','')='{$sel_ymd8}'");
$total_count = (int)$total_row['cnt'];
$rows = 200;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$from_record = ($page - 1) * $rows;
$q = sql_query("
    SELECT a.*, REPLACE(a.ymd,'-','') AS ymd8, m.mb_nick
    FROM rb_attendance a
    LEFT JOIN {$mt} m ON m.mb_id = a.mb_id
    WHERE REPLACE(a.ymd,'-','')='{$sel_ymd8}'
    ORDER BY (a.at_rank IS NULL) ASC, a.at_rank ASC, a.at_datetime ASC, a.at_id ASC
    LIMIT {$from_record}, {$rows}
");
?>
    
    <script>
        function rb_attend_hd_height() {
            var sticky_header = $('#header').outerHeight() + 30;
            $('#rb-att-left').css('top', sticky_header + 'px');
        }

        $(document).ready(function() {
            // 처음 페이지 로드 시 호출
            rb_attend_hd_height();

            // 브라우저 리사이즈 시 호출
            $(window).resize(function() {
                rb_attend_hd_height();
            });
        });
    </script>

    <div class="rb-att-left" id="rb-att-left">
       
        <div class="rb-att-head">
            <a href="#" id="rbPrevBtn" data-year="<?php echo $prev->format('Y'); ?>" data-month="<?php echo $prev->format('n'); ?>">이전달</a>
            <div class="rb-att-title font-B" id="rbYMTitle"><?php echo sprintf('%04d.%02d', $year, $month); ?></div>
            <a href="#" id="rbNextBtn" data-year="<?php echo $next->format('Y'); ?>" data-month="<?php echo $next->format('n'); ?>">다음달</a>
        </div>

        <div id="rbAttMeta" data-year="<?php echo $year; ?>" data-month="<?php echo str_pad($month,2,'0',STR_PAD_LEFT); ?>"></div>

        <table class="rb-att-cal" id="rbAttCal">
            <thead>
                <tr>
                    <th>일</th>
                    <th>월</th>
                    <th>화</th>
                    <th>수</th>
                    <th>목</th>
                    <th>금</th>
                    <th>토</th>
                </tr>
            </thead>
            <tbody id="rbCalBody">
                <?php
        $d = 1;
        for ($r=0; $r<6; $r++) {
            echo '<tr>';
            for ($c=0; $c<7; $c++) {
                if ($r==0 && $c<$start_w) { echo '<td class="rb-att-cell"></td>'; continue; }
                if ($d > $days) { echo '<td class="rb-att-cell"></td>'; continue; }
                $ymd8 = sprintf('%04d%02d%02d', $year, $month, $d);
                $mine_mark = array_key_exists($ymd8, $mine);
                $is_today = ($ymd8 === $today_ymd8);
                $is_selected = ($ymd8 === $sel_ymd8);
                echo '<td class="rb-att-cell'.($is_today?' rb-att-today':'').($is_selected?' rb-att-selected':'').'" data-ymd="'.$ymd8.'">';
                echo '<div class="rb-att-day">'.$d.'</div>';
                if ($mine_mark) {
                    echo '<div class="rb-att-img"><img src="'.G5_URL.'/rb/rb.mod/attendance/image/attend.png" alt="출석완료"></div>';
                } else {
                    echo '<div class="rb-att-img"><img src="'.G5_URL.'/rb/rb.mod/attendance/image/attend_not.png" alt="미출석"></div>';
                }
                echo '</td>';
                $d++;
            }
            echo '</tr>';
            if ($d > $days) break;
        }
        ?>
            </tbody>
        </table>

    </div>

    <div class="rb-att-right">
        
        <div class="rb-att-top-wrap">
            <?php if(!empty($att['week_streak_point'])) { ?>
            <div class="rb-att-top-no"><?php echo $att['week_streak_len'] ?>일 연속<br><span class="font-B"><?php echo number_format($att['week_streak_point']) ?> 포인트</span></div>
            <?php } ?>
            <?php if(!empty($att['month_streak_point'])) { ?>
            <div class="rb-att-top-no"><?php echo $att['month_streak_len'] ?>일 연속<br><span class="font-B"><?php echo number_format($att['month_streak_point']) ?> 포인트</span></div>
            <?php } ?>
            <?php if(!empty($att['year_streak_point'])) { ?>
            <div class="rb-att-top-no"><?php echo $att['year_streak_len'] ?>일 연속<br><span class="font-B"><?php echo number_format($att['year_streak_point']) ?> 포인트</span></div>
            <?php } ?>
        </div>
        

        <div class="rb-att-form" id="rb-att-form">
            
            <form id="rbAttForm" onsubmit="return false;">
                <textarea name="content" placeholder="오늘의 출석 한마디"></textarea>
                <div class="rb-att-btn">
                    <button type="button" id="rbAttBtn">출석하기</button>
                    <span class="rb-att-msg" id="rbAttMsg"></span>
                </div>
            </form>

        </div>
        
        <div class="font-R font-12 color-999 mt-10">* 연속 출석은 매월 초기화 됩니다.</div>

        <div class="rb-att-list" id="rbAttList">
            <div class="rb-att-list-head">
                <div class="font-B"><span class="rb-att-list-date" id="rbAttListDate"><?php echo rb_fmt_dot($sel_ymd8); ?></span> 출석</div>
                <div id="rbAttListCount" class="font-14"><?php echo number_format($total_count); ?>명</div>
            </div>
            <div class="rb-att-list-box" id="rbAttListBox">
                <?php
        $printed = 0;
        while ($r = sql_fetch_array($q)) {
            $printed++;
            $mbs = get_member($r['mb_id']);
            $nick_plain = get_text(isset($mbs['mb_nick']) ? $mbs['mb_nick'] : $r['mb_id']);
            $sv = get_sideview(
                $r['mb_id'],
                $nick_plain,
                isset($mbs['mb_email']) ? $mbs['mb_email'] : '',
                isset($mbs['mb_homepage']) ? $mbs['mb_homepage'] : ''
            );
            $content = nl2br(get_text($r['at_content']));
            $rank = (int)$r['at_rank'];
            $badge = '';
            if ($rank >= 1 && $rank <= 5 && !empty($rb_rank_enabled[$rank])) {
                $badge = '<div class="rb-att-badge r'.$rank.'">'.$rank.'등</div>';
            }
            
            // 추가: 등수 포인트 표시용 HTML
            $rank_point_html = '';
            if ($rank >= 1 && $rank <= 5 && !empty($rb_rank_enabled[$rank])) {
                $k = 'bonus_rank' . (string)$rank;
                $p = (int)($att[$k] ?? 0);     // 설정이 없으면 0
                if ($p > 0) {
                    $rank_point_html = $badge.'<div class="rb-att-earned"><span class="font-B main_color">' . number_format($p) . 'P</span> 획득</div>';
                }
            }
                        ?>
                <div class="rb-att-item">
                    <?php echo $badge; ?>
                    <div class="rb-att-body">
                        <div class="rb-att-meta" title="미니홈" onclick="location.href='<?php echo G5_URL ?>/rb/home.php?mb_id=<?php echo $r['mb_id'] ?>';"><?php echo $sv; ?>　<?php echo $r['at_datetime']; ?></div>
                        <div class="rb-att-text"><?php echo $content; ?></div>
                        <?php if ($rank_point_html) echo $rank_point_html; ?>
                    </div>
                </div>
                <?php
        }
        if (!$printed) {
            echo '<div class="rb-att-empty">출석 한마디가 없습니다.</div>';
        }
        ?>
            </div>
        </div>
    </div>

    <div class="cb"></div>


<script>
    // 서버 설정
    var RB_RANK_ENABLED = {
        1: <?php echo $rb_rank_enabled[1] ? 'true' : 'false'; ?>,
        2: <?php echo $rb_rank_enabled[2] ? 'true' : 'false'; ?>,
        3: <?php echo $rb_rank_enabled[3] ? 'true' : 'false'; ?>,
        4: <?php echo $rb_rank_enabled[4] ? 'true' : 'false'; ?>,
        5: <?php echo $rb_rank_enabled[5] ? 'true' : 'false'; ?>
    };
    var RB_TODAY = '<?php echo $today_ymd8; ?>';
    var RB_TODAY_DONE = <?php echo $rb_today_done ? 'true' : 'false'; ?>;

    // 유틸
    function ymdDot(ymd8) {
        return ymd8.slice(0, 4) + '.' + ymd8.slice(4, 6) + '.' + ymd8.slice(6, 8);
    }

    // 리스트 HTML을 통째로 교체 (사이드뷰 서버사이드 유지)
    function loadListHTML(ymd8) {
        var url = '<?php echo G5_URL; ?>/rb/rb.mod/attendance/attend.list.php?format=html&ymd=' + ymd8;
        return fetch(url, {
                credentials: 'same-origin'
            })
            .then(function(r) {
                return r.text();
            })
            .then(function(html) {
                var box = document.getElementById('rbAttListBox');
                var cnt = document.getElementById('rbAttListCount');
                var hDate = document.getElementById('rbAttListDate');
                if (box) box.innerHTML = html;
                if (hDate) hDate.textContent = ymdDot(ymd8);
                // 카운트는 서버가 data-count attr로 내려주게 했으니 거기서 읽음
                var tmp = document.createElement('div');
                tmp.innerHTML = html;
                var wc = tmp.querySelector('[data-rb-list-count]');
                if (wc && cnt) cnt.textContent = (wc.getAttribute('data-rb-list-count') || '0') + '명';
            });
    }

    // 월 정보로 캘린더 재빌드
    function rebuildCalendar(j) {
        var tbody = document.getElementById('rbCalBody');
        var title = document.getElementById('rbYMTitle');
        var meta = document.getElementById('rbAttMeta');
        if (!tbody || !title || !meta) return;
        title.textContent = String(j.year).padStart(4, '0') + '.' + String(j.month).padStart(2, '0');
        meta.setAttribute('data-year', j.year);
        meta.setAttribute('data-month', String(j.month).padStart(2, '0'));
        tbody.innerHTML = '';
        var d = 1;
        for (var r = 0; r < 6; r++) {
            var tr = document.createElement('tr');
            for (var c = 0; c < 7; c++) {
                var td = document.createElement('td');
                td.className = 'rb-att-cell';
                if (r === 0 && c < j.start_w) {
                    tr.appendChild(td);
                    continue;
                }
                if (d > j.days) {
                    tr.appendChild(td);
                    continue;
                }
                var ymd8 = String(j.year).padStart(4, '0') + String(j.month).padStart(2, '0') + String(d).padStart(2, '0');
                td.setAttribute('data-ymd', ymd8);
                var day = document.createElement('div');
                day.className = 'rb-att-day';
                day.textContent = d;
                td.appendChild(day);
                var wrap = document.createElement('div');
                wrap.className = 'rb-att-img';
                var img = document.createElement('img');
                if (j.mine && j.mine[ymd8]) {
                    img.src = '<?php echo G5_URL; ?>/rb/rb.mod/attendance/image/attend.png';
                    img.alt = '출석완료';
                } else {
                    img.src = '<?php echo G5_URL; ?>/rb/rb.mod/attendance/image/attend_not.png';
                    img.alt = '미출석';
                }
                wrap.appendChild(img);
                td.appendChild(wrap);
                if (ymd8 === RB_TODAY) td.classList.add('rb-att-today');
                tr.appendChild(td);
                d++;
            }
            tbody.appendChild(tr);
            if (d > j.days) break;
        }
    }

    // 클릭 바인딩
    (function() {
        var cal = document.getElementById('rbAttCal');
        if (cal) {
            cal.addEventListener('click', function(ev) {
                var td = ev.target.closest('.rb-att-cell');
                if (!td) return;
                var ymd8 = td.getAttribute('data-ymd');
                if (!ymd8) return;
                document.querySelectorAll('.rb-att-cell.rb-att-selected').forEach(function(el) {
                    el.classList.remove('rb-att-selected');
                });
                td.classList.add('rb-att-selected');
                loadListHTML(ymd8);
            });
        }

        var prevBtn = document.getElementById('rbPrevBtn');
        var nextBtn = document.getElementById('rbNextBtn');

        function monthFetch(y, m) {
            var url = '<?php echo G5_URL; ?>/rb/rb.mod/attendance/attend.month.php?year=' + y + '&month=' + m;
            return fetch(url, {
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json();
                })
                .then(function(j) {
                    if (!j || !j.ok) return;
                    rebuildCalendar(j);
                    // 새 달에선 오늘이 그 달이면 오늘, 아니면 1일을 선택해 목록 로드
                    var ym = String(j.year).padStart(4, '0') + String(j.month).padStart(2, '0');
                    var sel = (j.today && j.today.slice(0, 6) === ym) ? j.today : ym + '01';
                    var td = document.querySelector('.rb-att-cell[data-ymd="' + sel + '"]');
                    if (td) td.classList.add('rb-att-selected');
                    loadListHTML(sel);
                    // 버튼 데이터 갱신
                    var p = new Date(j.year, j.month - 2, 1);
                    var n = new Date(j.year, j.month, 1);
                    if (prevBtn) {
                        prevBtn.dataset.year = String(p.getFullYear());
                        prevBtn.dataset.month = String(p.getMonth() + 1);
                    }
                    if (nextBtn) {
                        nextBtn.dataset.year = String(n.getFullYear());
                        nextBtn.dataset.month = String(n.getMonth() + 1);
                    }
                });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function(e) {
                e.preventDefault();
                monthFetch(prevBtn.dataset.year, prevBtn.dataset.month);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function(e) {
                e.preventDefault();
                monthFetch(nextBtn.dataset.year, nextBtn.dataset.month);
            });
        }

        // 버튼 전환
        var btn = document.getElementById('rbAttBtn');
        var msg = document.getElementById('rbAttMsg');

        function setCompleted() {
            if (!btn) return;
            btn.disabled = false;
            btn.textContent = '오늘 출석완료';
            btn.classList.add('is-completed');
            btn.onclick = function() {
                alert('오늘 출석을 완료했어요!');
            };
        }
        if (btn) {
            if (RB_TODAY_DONE) {
                setCompleted();
                if (msg) msg.textContent = '오늘 출석을 완료했어요!';
            } else {
                btn.addEventListener('click', function() {
                    var f = document.getElementById('rbAttForm');
                    if (!f) return;
                    if (btn.classList.contains('is-completed')) {
                        alert('오늘 출석을 완료했어요!');
                        return;
                    }
                    if (btn.disabled) return;
                    btn.disabled = true;
                    if (msg) msg.textContent = '요청중...';
                    var fd = new FormData(f);
                    fd.append('debug', '1');
                    fetch('<?php echo G5_URL; ?>/rb/rb.mod/attendance/attend.write.php', {
                            method: 'POST',
                            body: fd,
                            credentials: 'same-origin'
                        })
                        .then(function(r) {
                            return r.text();
                        })
                        .then(function(t) {
                            var res = null;
                            try {
                                res = JSON.parse(t);
                            } catch (e) {
                                if (msg) msg.textContent = 'JSON 파싱 오류';
                                btn.disabled = false;
                                return;
                            }
                            if (!res || !res.ok) {
                                if (msg) msg.textContent = (res && res.msg) ? res.msg : '오류';
                                btn.disabled = false;
                                return;
                            }
                            if (msg) msg.textContent = '출석 완료';
                            // 캘린더 이미지 교체
                            var cell = document.querySelector('.rb-att-cell[data-ymd="' + res.ymd + '"]');
                            if (cell) {
                                var old = cell.querySelector('.rb-att-img');
                                if (old) old.remove();
                                var box = document.createElement('div');
                                box.className = 'rb-att-img';
                                var img = document.createElement('img');
                                img.src = '<?php echo G5_URL; ?>/rb/rb.mod/attendance/image/attend.png';
                                img.alt = '출석완료';
                                box.appendChild(img);
                                cell.appendChild(box);
                            }
                            setCompleted();
                            alert('오늘 출석을 완료했어요!');
                            loadListHTML(res.ymd);
                        })
                        .catch(function() {
                            if (msg) msg.textContent = '통신 오류';
                            btn.disabled = false;
                        });
                });
            }
        }

        // 초기 리스트 로드: 선택 표시된 셀 or 오늘
        var firstSel = document.querySelector('.rb-att-cell.rb-att-selected');
        var initYmd = firstSel ? firstSel.getAttribute('data-ymd') : '<?php echo $sel_ymd8; ?>';
        loadListHTML(initYmd);
    })();
</script>