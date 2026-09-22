<?php
/**
 * 내 알림 (My Notifications) - 사용자 알림 목록 페이지
 * 로그인 필수, 필터/읽음처리/더보기 AJAX
 * @created 2026-02-27
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/user_notifications.php';
require_login();
$me = get_gangnamunni_member();
$member_id = $me['mb_id'];

// --- AJAX 엔드포인트: 알림 읽음 처리 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'] ?? '';

    if ($action === 'read') {
        $nid = intval($_POST['id'] ?? 0);
        if ($nid > 0) {
            mark_user_notification_read($connect, $nid, $member_id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => '잘못된 요청']);
        }
        exit;
    }

    if ($action === 'read_all') {
        mark_user_all_read($connect, $member_id);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'load') {
        $page = max(1, intval($_POST['page'] ?? 1));
        $type = $_POST['type'] ?? 'all';
        $allowed_types = ['all', 'booking', 'event', 'system', 'promotion'];
        if (!in_array($type, $allowed_types)) $type = 'all';

        $limit = 20;
        $notifications = [];
        $total = 0;

        // 테이블 존재 확인
        $table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_user_notifications'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $notifications = get_user_notifications($connect, $member_id, $page, $limit, $type);
            $total = get_user_notification_count($connect, $member_id, $type);
        }

        $has_more = ($page * $limit) < $total;
        $html = '';

        foreach ($notifications as $n) {
            $html .= render_notification_item($n);
        }

        echo json_encode([
            'success' => true,
            'html' => $html,
            'has_more' => $has_more,
            'total' => $total,
            'page' => $page
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => '알 수 없는 요청']);
    exit;
}

// --- 초기 데이터 로딩 ---
$current_type = $_GET['type'] ?? 'all';
$allowed_types = ['all', 'booking', 'event', 'system', 'promotion'];
if (!in_array($current_type, $allowed_types)) $current_type = 'all';

$notifications = [];
$total_count = 0;
$unread_count = 0;
$table_exists = false;

// 테이블 존재 확인
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_user_notifications'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $table_exists = true;
    $notifications = get_user_notifications($connect, $member_id, 1, 20, $current_type);
    $total_count = get_user_notification_count($connect, $member_id, $current_type);
    $unread_count = get_user_unread_count($connect, $member_id);
}

$has_more = (1 * 20) < $total_count;

/**
 * 알림 항목 HTML 렌더링
 */
function render_notification_item($n) {
    global $USER_NOTIFICATION_TYPES;
    $type_info = $USER_NOTIFICATION_TYPES[$n['type']] ?? $USER_NOTIFICATION_TYPES['general'];
    $is_unread = intval($n['is_read'] ?? 0) === 0;
    $bg_class = $is_unread ? 'noti-unread' : 'noti-read';
    $icon = htmlspecialchars($n['icon'] ?? $type_info['icon']);
    $color = $type_info['color'];
    $title = htmlspecialchars($n['title'] ?? '');
    $message = htmlspecialchars($n['message'] ?? '');
    $time = user_time_ago($n['created_at']);
    $link = htmlspecialchars($n['link'] ?? '');
    $id = intval($n['id']);
    $type_label = $type_info['label'];

    $unread_dot = $is_unread ? '<span class="noti-dot"></span>' : '';

    return <<<HTML
<div class="noti-item {$bg_class}" data-id="{$id}" data-link="{$link}" role="button" tabindex="0">
    <div class="noti-icon-wrap" style="background:{$color}20">
        <i class="bi {$icon}" style="color:{$color}"></i>
    </div>
    <div class="noti-content">
        <div class="noti-title-row">
            <span class="noti-title">{$title}</span>
            {$unread_dot}
        </div>
        <p class="noti-message">{$message}</p>
        <div class="noti-meta">
            <span class="noti-type-badge" style="color:{$color};background:{$color}15">{$type_label}</span>
            <span class="noti-time">{$time}</span>
        </div>
    </div>
</div>
HTML;
}

// --- 페이지 레이아웃 변수 ---
$page_title = '내 알림';
$show_search = false;

$page_css = <<<'CSS'
/* 알림 페이지 전체 스타일 */
*{box-sizing:border-box;margin:0;padding:0}
body{
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','Noto Sans KR',sans-serif;
    background:#f5f5f7;
    min-height:100vh;
    -webkit-font-smoothing:antialiased;
}

.noti-wrapper{
    max-width:640px;
    margin:0 auto;
    background:var(--bs-body-bg, #fff);
    min-height:100vh;
    box-shadow:0 0 20px rgba(0,0,0,.04);
}

/* 페이지 헤더 */
.noti-header{
    position:sticky;
    top:0;
    z-index:100;
    background:var(--bs-body-bg, #fff);
    border-bottom:1px solid var(--bs-border-color, #f0f0f0);
    padding:12px 16px;
}
.noti-header-row{
    display:flex;
    align-items:center;
    gap:12px;
}
.noti-back{
    width:36px;height:36px;
    border-radius:50%;
    border:none;
    background:#f5f5f7;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;color:var(--bs-body-color, #333);
    cursor:pointer;
    transition:background .2s;
    flex-shrink:0;
}
.noti-back:hover{background:#eee}
.noti-page-title{
    font-size:18px;
    font-weight:700;
    color:#1a1a1a;
    flex:1;
}
.noti-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#AE1932;
    color:#fff;
    font-size:11px;
    font-weight:600;
    min-width:20px;
    height:20px;
    padding:0 6px;
    border-radius:10px;
    margin-left:6px;
}
.noti-read-all-btn{
    border:none;
    background:none;
    color:#AE1932;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    white-space:nowrap;
    padding:6px 10px;
    border-radius:8px;
    transition:background .2s;
}
.noti-read-all-btn:hover{background:#FFF0E8}
.noti-read-all-btn:disabled{color:#ccc;cursor:default}
.noti-read-all-btn:disabled:hover{background:none}

/* 필터 탭 */
.noti-tabs{
    display:flex;
    gap:0;
    padding:0 16px;
    background:var(--bs-body-bg, #fff);
    border-bottom:1px solid var(--bs-border-color, #f0f0f0);
    position:sticky;
    top:61px;
    z-index:99;
}
.noti-tab{
    flex:1;
    text-align:center;
    padding:10px 0;
    font-size:14px;
    font-weight:500;
    color:#999;
    cursor:pointer;
    border:none;
    background:none;
    position:relative;
    transition:color .2s;
}
.noti-tab:hover{color:var(--bs-secondary-color, #666)}
.noti-tab.active{
    color:#AE1932;
    font-weight:700;
}
.noti-tab.active::after{
    content:'';
    position:absolute;
    bottom:0;left:20%;right:20%;
    height:2.5px;
    background:#AE1932;
    border-radius:2px 2px 0 0;
}

/* 알림 리스트 */
.noti-list{
    padding:0;
}
.noti-item{
    display:flex;
    align-items:flex-start;
    gap:12px;
    padding:14px 16px;
    border-bottom:1px solid #f5f5f5;
    cursor:pointer;
    transition:background .15s;
}
.noti-item:hover{background:#fafafa}
.noti-item:active{background:var(--bs-tertiary-bg, #f5f5f5)}
.noti-item:last-child{border-bottom:none}

.noti-unread{background:#FFF8F0}
.noti-unread:hover{background:#FFF2E6}
.noti-read{background:var(--bs-body-bg, #fff)}

.noti-icon-wrap{
    width:40px;height:40px;
    border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;
    flex-shrink:0;
}
.noti-content{
    flex:1;
    min-width:0;
}
.noti-title-row{
    display:flex;
    align-items:center;
    gap:6px;
    margin-bottom:2px;
}
.noti-title{
    font-size:14px;
    font-weight:600;
    color:#1a1a1a;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    flex:1;
    min-width:0;
}
.noti-dot{
    width:8px;height:8px;
    border-radius:50%;
    background:#AE1932;
    flex-shrink:0;
}
.noti-message{
    font-size:13px;
    color:var(--bs-secondary-color, #666);
    line-height:1.4;
    margin:0 0 4px 0;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    overflow:hidden;
}
.noti-meta{
    display:flex;
    align-items:center;
    gap:8px;
}
.noti-type-badge{
    font-size:11px;
    font-weight:600;
    padding:1px 6px;
    border-radius:4px;
}
.noti-time{
    font-size:11px;
    color:#aaa;
}

/* 더 보기 버튼 */
.noti-load-more{
    display:block;
    width:calc(100% - 32px);
    margin:12px 16px 20px;
    padding:12px;
    border:1px solid var(--bs-border-color, #eee);
    border-radius:10px;
    background:#fafafa;
    color:var(--bs-secondary-color, #666);
    font-size:14px;
    font-weight:600;
    cursor:pointer;
    transition:all .2s;
    text-align:center;
}
.noti-load-more:hover{
    background:#f0f0f0;
    border-color:#ddd;
    color:var(--bs-body-color, #333);
}
.noti-load-more:disabled{
    cursor:default;
    color:#ccc;
}

/* 빈 상태 */
.noti-empty{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:60px 16px;
    text-align:center;
}
.noti-empty-icon{
    font-size:56px;
    color:#ddd;
    margin-bottom:16px;
}
.noti-empty-title{
    font-size:16px;
    font-weight:600;
    color:#999;
    margin-bottom:6px;
}
.noti-empty-desc{
    font-size:13px;
    color:#bbb;
}

/* 로딩 스피너 */
.noti-spinner{
    display:flex;
    justify-content:center;
    padding:24px;
}
.noti-spinner::after{
    content:'';
    width:28px;height:28px;
    border:3px solid #f0f0f0;
    border-top-color:#AE1932;
    border-radius:50%;
    animation:noti-spin .6s linear infinite;
}
@keyframes noti-spin{to{transform:rotate(360deg)}}

/* 토스트 메시지 */
.noti-toast{
    position:fixed;
    bottom:24px;
    left:50%;
    transform:translateX(-50%) translateY(80px);
    background:#333;
    color:#fff;
    font-size:13px;
    font-weight:500;
    padding:10px 20px;
    border-radius:8px;
    z-index:9999;
    opacity:0;
    transition:all .3s ease;
    pointer-events:none;
}
.noti-toast.show{
    opacity:1;
    transform:translateX(-50%) translateY(0);
}

/* 반응형 */
@media(max-width:640px){
    .noti-wrapper{box-shadow:none}
}
CSS;

$page_js = '';

include __DIR__ . '/includes/header.php';
?>

<div class="noti-wrapper">
    <!-- 페이지 헤더 -->
    <div class="noti-header">
        <div class="noti-header-row">
            <button class="noti-back" onclick="history.back()" aria-label="뒤로가기">
                <i class="bi bi-arrow-left"></i>
            </button>
            <span class="noti-page-title">
                내 알림
                <?php if ($unread_count > 0): ?>
                    <span class="noti-badge" id="unreadBadge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php endif; ?>
            </span>
            <button class="noti-read-all-btn" id="readAllBtn"
                <?= $unread_count === 0 ? 'disabled' : '' ?>
                onclick="markAllRead()">
                모두 읽음
            </button>
        </div>
    </div>

    <!-- 필터 탭 -->
    <div class="noti-tabs" id="notiTabs">
        <button class="noti-tab <?= $current_type === 'all' ? 'active' : '' ?>" data-type="all">전체</button>
        <button class="noti-tab <?= $current_type === 'booking' ? 'active' : '' ?>" data-type="booking">예약</button>
        <button class="noti-tab <?= $current_type === 'event' ? 'active' : '' ?>" data-type="event">이벤트</button>
        <button class="noti-tab <?= $current_type === 'system' ? 'active' : '' ?>" data-type="system">시스템</button>
        <button class="noti-tab <?= $current_type === 'promotion' ? 'active' : '' ?>" data-type="promotion">프로모션</button>
    </div>

    <!-- 알림 리스트 -->
    <div class="noti-list" id="notiList">
        <?php if (empty($notifications)): ?>
            <div class="noti-empty" id="emptyState">
                <div class="noti-empty-icon"><i class="bi bi-bell-slash"></i></div>
                <div class="noti-empty-title">알림이 없습니다</div>
                <div class="noti-empty-desc">새로운 알림이 오면 여기에 표시됩니다.</div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?= render_notification_item($n) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 더 보기 버튼 -->
    <?php if ($has_more): ?>
        <button class="noti-load-more" id="loadMoreBtn" onclick="loadMore()">
            더 보기
        </button>
    <?php endif; ?>
</div>

<!-- 토스트 -->
<div class="noti-toast" id="notiToast"></div>

<script>
(function() {
    'use strict';

    // 상태 변수
    var currentPage = 1;
    var currentType = '<?= $current_type ?>';
    var isLoading = false;
    var totalCount = <?= $total_count ?>;
    var unreadCount = <?= $unread_count ?>;
    var LIMIT = 20;
    var BASE_URL = location.pathname;

    // 요소 참조
    var notiList = document.getElementById('notiList');
    var loadMoreBtn = document.getElementById('loadMoreBtn');
    var readAllBtn = document.getElementById('readAllBtn');
    var unreadBadge = document.getElementById('unreadBadge');
    var notiToast = document.getElementById('notiToast');
    var tabs = document.querySelectorAll('.noti-tab');

    // --- 탭 전환 ---
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var type = this.getAttribute('data-type');
            if (type === currentType && !isLoading) return;
            switchTab(type);
        });
    });

    function switchTab(type) {
        currentType = type;
        currentPage = 1;

        // 탭 활성 상태 변경
        tabs.forEach(function(t) {
            t.classList.toggle('active', t.getAttribute('data-type') === type);
        });

        // 리스트 로딩 표시
        notiList.innerHTML = '<div class="noti-spinner"></div>';
        removeLoadMoreBtn();

        // URL 히스토리 변경 (새로고침 없이)
        var url = BASE_URL + (type !== 'all' ? '?type=' + type : '');
        history.replaceState(null, '', url);

        // AJAX로 데이터 로드
        loadNotifications(1, type, false);
    }

    // --- 알림 목록 로드 ---
    function loadNotifications(page, type, append) {
        if (isLoading) return;
        isLoading = true;

        var formData = new FormData();
        formData.append('page', page);
        formData.append('type', type);

        fetch(BASE_URL + '?ajax=load', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                showToast('데이터를 불러올 수 없습니다.');
                return;
            }

            totalCount = data.total;
            currentPage = data.page;

            if (append) {
                // 기존 리스트에 추가
                var temp = document.createElement('div');
                temp.innerHTML = data.html;
                while (temp.firstChild) {
                    notiList.appendChild(temp.firstChild);
                }
            } else {
                // 리스트 교체
                if (data.html) {
                    notiList.innerHTML = data.html;
                } else {
                    notiList.innerHTML =
                        '<div class="noti-empty" id="emptyState">' +
                        '<div class="noti-empty-icon"><i class="bi bi-bell-slash"></i></div>' +
                        '<div class="noti-empty-title">' + getEmptyTitle(type) + '</div>' +
                        '<div class="noti-empty-desc">새로운 알림이 오면 여기에 표시됩니다.</div>' +
                        '</div>';
                }
            }

            // 더 보기 버튼 표시/제거
            if (data.has_more) {
                showLoadMoreBtn();
            } else {
                removeLoadMoreBtn();
            }

            // 클릭 이벤트 다시 바인딩
            bindItemEvents();
        })
        .catch(function() {
            showToast('네트워크 오류가 발생했습니다.');
        })
        .finally(function() {
            isLoading = false;
        });
    }

    function getEmptyTitle(type) {
        var map = {
            'all': '알림이 없습니다',
            'booking': '예약 알림이 없습니다',
            'event': '이벤트 알림이 없습니다',
            'system': '시스템 알림이 없습니다',
            'promotion': '프로모션 알림이 없습니다'
        };
        return map[type] || '알림이 없습니다';
    }

    // --- 더 보기 ---
    window.loadMore = function() {
        if (isLoading) return;
        var nextPage = currentPage + 1;
        if (loadMoreBtn) {
            loadMoreBtn.textContent = '불러오는 중...';
            loadMoreBtn.disabled = true;
        }
        loadNotifications(nextPage, currentType, true);
    };

    function showLoadMoreBtn() {
        removeLoadMoreBtn();
        var btn = document.createElement('button');
        btn.className = 'noti-load-more';
        btn.id = 'loadMoreBtn';
        btn.textContent = '더 보기';
        btn.onclick = window.loadMore;
        notiList.parentNode.insertBefore(btn, notiList.nextSibling);
        loadMoreBtn = btn;
    }

    function removeLoadMoreBtn() {
        var existing = document.getElementById('loadMoreBtn');
        if (existing) existing.remove();
        loadMoreBtn = null;
    }

    // --- 알림 클릭 (읽음 처리 + 이동) ---
    function bindItemEvents() {
        var items = notiList.querySelectorAll('.noti-item');
        items.forEach(function(item) {
            // 이전 이벤트 리스너 방지
            if (item.dataset.bound) return;
            item.dataset.bound = '1';

            item.addEventListener('click', function() {
                handleItemClick(this);
            });
            item.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleItemClick(this);
                }
            });
        });
    }

    function handleItemClick(el) {
        var id = el.getAttribute('data-id');
        var link = el.getAttribute('data-link');
        var isUnread = el.classList.contains('noti-unread');

        if (isUnread) {
            // 즉시 시각적으로 읽음 처리
            el.classList.remove('noti-unread');
            el.classList.add('noti-read');
            var dot = el.querySelector('.noti-dot');
            if (dot) dot.remove();

            // 카운트 갱신
            unreadCount = Math.max(0, unreadCount - 1);
            updateUnreadBadge();

            // 서버에 읽음 처리 POST
            var formData = new FormData();
            formData.append('id', id);
            fetch(BASE_URL + '?ajax=read', {
                method: 'POST',
                body: formData
            }).catch(function() {});
        }

        // 링크가 있으면 이동
        if (link) {
            setTimeout(function() {
                location.href = link;
            }, 100);
        }
    }

    // --- 모두 읽음 ---
    window.markAllRead = function() {
        if (isLoading || unreadCount === 0) return;
        readAllBtn.disabled = true;
        readAllBtn.textContent = '처리 중...';

        fetch(BASE_URL + '?ajax=read_all', {
            method: 'POST'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                // 시각적으로 전부 읽음 처리
                var unreadItems = notiList.querySelectorAll('.noti-unread');
                unreadItems.forEach(function(item) {
                    item.classList.remove('noti-unread');
                    item.classList.add('noti-read');
                    var dot = item.querySelector('.noti-dot');
                    if (dot) dot.remove();
                });

                unreadCount = 0;
                updateUnreadBadge();
                showToast('모든 알림을 읽음 처리했습니다.');
            } else {
                showToast('처리 중 오류가 발생했습니다.');
            }
        })
        .catch(function() {
            showToast('네트워크 오류가 발생했습니다.');
        })
        .finally(function() {
            readAllBtn.textContent = '모두 읽음';
            readAllBtn.disabled = (unreadCount === 0);
        });
    };

    // --- 배지 업데이트 ---
    function updateUnreadBadge() {
        if (unreadCount > 0) {
            if (!unreadBadge) {
                var badge = document.createElement('span');
                badge.className = 'noti-badge';
                badge.id = 'unreadBadge';
                document.querySelector('.noti-page-title').appendChild(badge);
                unreadBadge = badge;
            }
            unreadBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            unreadBadge.style.display = '';
        } else {
            if (unreadBadge) unreadBadge.style.display = 'none';
            if (readAllBtn) readAllBtn.disabled = true;
        }
    }

    // --- 토스트 ---
    var toastTimer = null;
    function showToast(msg) {
        notiToast.textContent = msg;
        notiToast.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function() {
            notiToast.classList.remove('show');
        }, 2500);
    }

    // 초기 바인딩
    bindItemEvents();
})();
</script>

<?php
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
