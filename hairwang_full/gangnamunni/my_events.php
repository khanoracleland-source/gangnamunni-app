<?php
/**
 * 내 이벤트 - 공개 사용자 페이지
 * 로그인 사용자의 이벤트 응모 내역 및 활성 이벤트 목록
 *
 * @requires includes/init.php (session, DB, require_login, get_gangnamunni_member)
 */

require_once __DIR__ . '/includes/init.php';
require_login();

$me = get_gangnamunni_member();

// ──────────────────────────────────────────────
// DB 연결 ($connect 는 init.php 에서 제공)
// ──────────────────────────────────────────────

// ── Tab 1: 내 응모 내역 조회 ──
$entries = [];
$entries_error = false;

// member_id 컬럼 존재 여부 확인 후 폴백
$has_member_id = false;
$col_check = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_event_entries LIKE 'member_id'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $has_member_id = true;
}

if ($has_member_id) {
    // member_id 기반 조회
    $stmt = mysqli_prepare($connect,
        "SELECT ee.*, e.title AS event_title, e.image_url AS event_image,
                e.raffle_prize, e.raffle_end_date, e.raffle_winner_count
         FROM gangnamunni_event_entries ee
         LEFT JOIN gangnamunni_events e ON ee.event_id = e.id
         WHERE ee.member_id = ?
         ORDER BY ee.created_at DESC"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $me['mb_id']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $entries[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $entries_error = true;
    }
} else {
    // 폴백: user_email 기반 조회
    $stmt = mysqli_prepare($connect,
        "SELECT ee.*, e.title AS event_title, e.image_url AS event_image,
                e.raffle_prize, e.raffle_end_date, e.raffle_winner_count
         FROM gangnamunni_event_entries ee
         LEFT JOIN gangnamunni_events e ON ee.event_id = e.id
         WHERE ee.user_email = ?
         ORDER BY ee.created_at DESC"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $me['mb_email']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $entries[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $entries_error = true;
    }
}

// ── Tab 2: 활성 이벤트 목록 조회 ──
$events = [];
$ev_result = mysqli_query($connect,
    "SELECT * FROM gangnamunni_events
     WHERE is_active = 1
     ORDER BY created_at DESC
     LIMIT 20"
);
if ($ev_result) {
    while ($row = mysqli_fetch_assoc($ev_result)) {
        $events[] = $row;
    }
}

// ── 페이지 메타 ──
$page_title = '내 이벤트';
$show_search = false;

// ── 인라인 CSS ──
$page_css = <<<'CSS'
<style>
/* ── 기본 레이아웃 ── */
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,'Noto Sans KR',sans-serif;background:var(--bs-tertiary-bg, #f5f5f5);color:#222;-webkit-font-smoothing:antialiased}
.page-wrap{max-width:640px;margin:0 auto;padding:0 16px 80px}

/* ── 탭 바 ── */
.tab-bar{display:flex;border-bottom:2px solid #e5e5e5;margin-bottom:16px;position:sticky;top:0;background:var(--bs-tertiary-bg, #f5f5f5);z-index:10;padding-top:8px}
.tab-btn{flex:1;text-align:center;padding:12px 0 10px;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:#888;background:none;border:none;cursor:pointer;position:relative;transition:color .2s}
.tab-btn.active{color:#AC1830}
.tab-btn::after{content:'';position:absolute;bottom:-2px;left:20%;right:20%;height:3px;border-radius:3px 3px 0 0;background:transparent;transition:background .2s,left .2s,right .2s}
.tab-btn.active::after{background:#AC1830;left:10%;right:10%}

/* ── 탭 콘텐츠 ── */
.tab-content{display:none;animation:fadeUp .25s ease}
.tab-content.active{display:block}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}

/* ── 카드 공통 ── */
.ev-card{display:flex;background:var(--bs-body-bg, #fff);border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden;margin-bottom:12px;text-decoration:none;color:inherit;transition:box-shadow .2s,transform .15s}
.ev-card:hover{box-shadow:0 4px 14px rgba(0,0,0,.1);transform:translateY(-1px)}
.ev-card-img{width:110px;min-height:110px;flex-shrink:0;background:#eee;object-fit:cover}
.ev-card-img.placeholder{display:flex;align-items:center;justify-content:center;font-size:calc(32px * var(--fs-scale,1) * var(--fs-page,1));color:#ccc}
.ev-card-body{flex:1;padding:12px 14px;display:flex;flex-direction:column;justify-content:center;min-width:0}
.ev-card-title{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;line-height:1.4;margin:0 0 6px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.ev-card-meta{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:#888;margin:0 0 4px}
.ev-card-meta span+span::before{content:'|';margin:0 6px;color:#ddd}

/* ── 상태 뱃지 ── */
.badge-status{display:inline-block;font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;padding:3px 10px;border-radius:20px;line-height:1.4}
.badge-pending{background:#f0f0f0;color:var(--bs-secondary-color, #666)}
.badge-won{background:#e6f9ee;color:#1a8a4a}
.badge-lost{background:#fce8e8;color:#c0392b}

/* ── 가격 ── */
.ev-price{display:flex;align-items:center;gap:6px;margin-top:4px}
.ev-price .original{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:#aaa;text-decoration:line-through}
.ev-price .discount{font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:800;color:#AC1830}
.ev-price .rate{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:#AC1830;background:rgba(172, 24, 48,.08);padding:2px 6px;border-radius:4px}

/* ── 카테고리 태그 ── */
.ev-category{display:inline-block;font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;color:#AC1830;background:rgba(172, 24, 48,.07);padding:2px 8px;border-radius:4px;margin-bottom:4px}

/* ── 래플 정보 ── */
.ev-raffle{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:#AC1830;margin-top:4px;display:flex;align-items:center;gap:4px}
.ev-raffle svg{flex-shrink:0}

/* ── 기간 ── */
.ev-period{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:#999;margin-top:2px}

/* ── 빈 상태 ── */
.empty-state{text-align:center;padding:60px 20px;color:#aaa}
.empty-state .empty-icon{font-size:calc(56px * var(--fs-scale,1) * var(--fs-page,1));margin-bottom:16px;display:block}
.empty-state .empty-msg{font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;margin-bottom:8px;color:#888}
.empty-state .empty-sub{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1))}

/* ── 페이지 헤더 ── */
.page-header{display:flex;align-items:center;justify-content:space-between;padding:16px 0 4px}
.page-header h1{font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));font-weight:800;margin:0}
.page-header .user-name{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));color:#888;font-weight:500}

/* ── 반응형 ── */
@media(max-width:400px){
    .ev-card-img{width:90px;min-height:90px}
    .ev-card-body{padding:10px 12px}
    .ev-card-title{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1))}
}
</style>
CSS;

// ── 인라인 JS ──
$page_js = <<<'JS'
(function(){
    'use strict';

    var tabs = document.querySelectorAll('.tab-btn');
    var panels = document.querySelectorAll('.tab-content');

    function activateTab(tabId) {
        tabs.forEach(function(t) {
            t.classList.toggle('active', t.getAttribute('data-tab') === tabId);
        });
        panels.forEach(function(p) {
            p.classList.toggle('active', p.id === tabId);
        });
        // URL hash 업데이트 (히스토리 안 쌓음)
        if (history.replaceState) {
            history.replaceState(null, '', '#' + tabId);
        }
    }

    tabs.forEach(function(btn) {
        btn.addEventListener('click', function() {
            activateTab(this.getAttribute('data-tab'));
        });
    });

    // 초기 탭: URL hash 또는 첫 번째
    var hash = window.location.hash.replace('#', '');
    var validTabs = ['tab-entries', 'tab-events'];
    if (validTabs.indexOf(hash) === -1) {
        hash = validTabs[0];
    }
    activateTab(hash);
})();
JS;

include __DIR__ . '/includes/header.php';
?>

<div class="page-wrap">

    <!-- 페이지 헤더 -->
    <div class="page-header">
        <h1>내 이벤트</h1>
        <span class="user-name"><?php echo htmlspecialchars($me['mb_name'] ?? ''); ?>님</span>
    </div>

    <!-- 탭 바 -->
    <div class="tab-bar">
        <button type="button" class="tab-btn active" data-tab="tab-entries">내 응모 내역</button>
        <button type="button" class="tab-btn" data-tab="tab-events">이벤트 목록</button>
    </div>

    <!-- ═══════════════════════════════════════ -->
    <!-- Tab 1: 내 응모 내역                      -->
    <!-- ═══════════════════════════════════════ -->
    <div class="tab-content active" id="tab-entries">
<?php if ($entries_error): ?>
        <div class="empty-state">
            <span class="empty-icon">&#9888;</span>
            <p class="empty-msg">데이터를 불러올 수 없습니다</p>
            <p class="empty-sub">잠시 후 다시 시도해주세요.</p>
        </div>
<?php elseif (empty($entries)): ?>
        <div class="empty-state">
            <span class="empty-icon">&#127881;</span>
            <p class="empty-msg">아직 응모한 이벤트가 없습니다</p>
            <p class="empty-sub">진행 중인 이벤트에 참여해보세요!</p>
        </div>
<?php else: ?>
    <?php foreach ($entries as $entry):
        // 상태 뱃지 결정: is_winner NULL=응모중, 1=당첨, 0=미당첨
        $is_winner = $entry['is_winner'];
        if ($is_winner === null || $is_winner === '') {
            $badge_class = 'badge-pending';
            $badge_text  = '응모중';
        } elseif ((int)$is_winner === 1) {
            $badge_class = 'badge-won';
            $badge_text  = '당첨';
        } else {
            $badge_class = 'badge-lost';
            $badge_text  = '미당첨';
        }

        $event_id    = (int)($entry['event_id'] ?? 0);
        $title       = htmlspecialchars($entry['event_title'] ?? '이벤트');
        $image       = $entry['event_image'] ?? '';
        $prize       = htmlspecialchars($entry['raffle_prize'] ?? '');
        $created     = $entry['created_at'] ?? '';
        $raffle_end  = $entry['raffle_end_date'] ?? '';
    ?>
        <a href="event_detail.php?id=<?php echo $event_id; ?>" class="ev-card">
            <?php if ($image): ?>
                <img src="<?php echo htmlspecialchars($image); ?>" alt="" class="ev-card-img" loading="lazy">
            <?php else: ?>
                <div class="ev-card-img placeholder">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                </div>
            <?php endif; ?>
            <div class="ev-card-body">
                <p class="ev-card-title"><?php echo $title; ?></p>
                <p class="ev-card-meta">
                    <span><?php echo $created ? date('Y.m.d', strtotime($created)) : ''; ?> 응모</span>
                    <span class="badge-status <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                </p>
                <?php if ($prize): ?>
                <div class="ev-raffle">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H4v4"/><path d="M2 8h20"/><rect x="6" y="12" width="12" height="8" rx="1"/><path d="M12 8v12"/><path d="M12 8c-2-4-6-4-6 0"/><path d="M12 8c2-4 6-4 6 0"/></svg>
                    <span>경품: <?php echo $prize; ?></span>
                </div>
                <?php endif; ?>
                <?php if ($raffle_end): ?>
                <p class="ev-period">추첨일: <?php echo date('Y.m.d', strtotime($raffle_end)); ?></p>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════ -->
    <!-- Tab 2: 이벤트 목록                       -->
    <!-- ═══════════════════════════════════════ -->
    <div class="tab-content" id="tab-events">
<?php if (empty($events)): ?>
        <div class="empty-state">
            <span class="empty-icon">&#128197;</span>
            <p class="empty-msg">현재 진행 중인 이벤트가 없습니다</p>
            <p class="empty-sub">새로운 이벤트가 곧 시작됩니다!</p>
        </div>
<?php else: ?>
    <?php foreach ($events as $ev):
        $ev_id        = (int)($ev['id'] ?? 0);
        $ev_title     = htmlspecialchars($ev['title'] ?? '');
        $ev_image     = $ev['image_url'] ?? '';
        $ev_category  = htmlspecialchars($ev['category'] ?? '');
        $ev_discount  = $ev['discount_price'] ?? null;
        $ev_original  = $ev['original_price'] ?? null;
        $ev_rate      = $ev['discount_rate'] ?? null;
        $ev_start     = $ev['start_date'] ?? '';
        $ev_end       = $ev['end_date'] ?? '';
        $ev_has_raffle = (int)($ev['has_raffle'] ?? 0);
        $ev_raffle_prize = htmlspecialchars($ev['raffle_prize'] ?? '');

        // 이벤트 기간 텍스트
        $period_text = '';
        if ($ev_start && $ev_end) {
            $period_text = date('m.d', strtotime($ev_start)) . ' ~ ' . date('m.d', strtotime($ev_end));
        } elseif ($ev_start) {
            $period_text = date('m.d', strtotime($ev_start)) . ' ~';
        }

        // D-day 계산
        $dday_text = '';
        if ($ev_end) {
            $diff = (int)((strtotime(date('Y-m-d', strtotime($ev_end))) - strtotime(date('Y-m-d'))) / 86400);
            if ($diff > 0) {
                $dday_text = 'D-' . $diff;
            } elseif ($diff === 0) {
                $dday_text = 'D-Day';
            }
        }
    ?>
        <a href="event_detail.php?id=<?php echo $ev_id; ?>" class="ev-card">
            <?php if ($ev_image): ?>
                <img src="<?php echo htmlspecialchars($ev_image); ?>" alt="" class="ev-card-img" loading="lazy">
            <?php else: ?>
                <div class="ev-card-img placeholder">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                </div>
            <?php endif; ?>
            <div class="ev-card-body">
                <?php if ($ev_category): ?>
                    <span class="ev-category"><?php echo $ev_category; ?></span>
                <?php endif; ?>
                <p class="ev-card-title"><?php echo $ev_title; ?></p>
                <?php if ($ev_discount !== null || $ev_original !== null): ?>
                <div class="ev-price">
                    <?php if ($ev_rate): ?>
                        <span class="rate"><?php echo (int)$ev_rate; ?>%</span>
                    <?php endif; ?>
                    <?php if ($ev_discount !== null): ?>
                        <span class="discount"><?php echo number_format((int)$ev_discount); ?>원</span>
                    <?php endif; ?>
                    <?php if ($ev_original !== null && $ev_discount !== null && (int)$ev_original !== (int)$ev_discount): ?>
                        <span class="original"><?php echo number_format((int)$ev_original); ?>원</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <p class="ev-card-meta">
                    <?php if ($period_text): ?>
                        <span><?php echo $period_text; ?></span>
                    <?php endif; ?>
                    <?php if ($dday_text): ?>
                        <span style="color:#AC1830;font-weight:700"><?php echo $dday_text; ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($ev_has_raffle && $ev_raffle_prize): ?>
                <div class="ev-raffle">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12V8H4v4"/><path d="M2 8h20"/><rect x="6" y="12" width="12" height="8" rx="1"/><path d="M12 8v12"/><path d="M12 8c-2-4-6-4-6 0"/><path d="M12 8c2-4 6-4 6 0"/></svg>
                    <span>경품: <?php echo $ev_raffle_prize; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>
    </div>

</div>

<?php
echo $page_css;
// $page_js 는 footer.php 가 <script>로 감싸 출력 (중복 echo 제거)

include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
