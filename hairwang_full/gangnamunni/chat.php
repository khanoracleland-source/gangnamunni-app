<?php
/**
 * 채팅 상담 페이지
 * URL: chat.php (채팅방 목록) / chat.php?hospital_id=X (채팅방 열기)
 */
require_once __DIR__ . '/includes/init.php';

$hospital_id = intval($_GET['hospital_id'] ?? 0);
$room_id = intval($_GET['room_id'] ?? 0);

// 로그인 체크
if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>상담 채팅 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root { --primary: #AE1932; --bg: #f8f9fa; --card: #fff; --text: #1a1a1a; --muted: #888; --border: #eee; }
[data-bs-theme="dark"] { --bg: #1a1a1a; --card: #2d2d2d; --text: #e5e5e5; --muted: #999; --border: #444; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); max-width:640px; margin:0 auto; height:100vh; display:flex; flex-direction:column; }

.chat-header { padding:14px 16px; display:flex; align-items:center; gap:12px; border-bottom:1px solid var(--border); background:var(--card); flex-shrink:0; }
.chat-header .back-btn { width:36px; height:36px; border:none; background:none; font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); cursor:pointer; color:var(--text); }
.chat-header h1 { font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; flex:1; }

/* 채팅방 목록 */
.room-item { display:flex; gap:12px; padding:14px 16px; border-bottom:1px solid var(--border); background:var(--card); cursor:pointer; text-decoration:none; color:inherit; }
.room-item:hover { background:var(--bg); }
.room-item img { width:48px; height:48px; border-radius:12px; object-fit:cover; flex-shrink:0; }
.room-item .no-img { width:48px; height:48px; border-radius:12px; background:var(--border); display:flex; align-items:center; justify-content:center; font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); flex-shrink:0; }
.room-info { flex:1; min-width:0; }
.room-info .room-name { font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; margin-bottom:2px; }
.room-info .room-last { font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color:var(--muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.room-badge { background:var(--primary); color:#fff; font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1)); padding:2px 8px; border-radius:10px; align-self:center; }

/* 채팅 메시지 */
.messages-area { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:8px; }
.msg { max-width:75%; padding:10px 14px; border-radius:16px; font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); line-height:1.5; word-break:break-word; }
.msg.mine { align-self:flex-end; background:var(--primary); color:#fff; border-bottom-right-radius:4px; }
.msg.theirs { align-self:flex-start; background:var(--card); border:1px solid var(--border); border-bottom-left-radius:4px; }
.msg.system { align-self:center; background:var(--bg); color:var(--muted); font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1)); border-radius:12px; padding:6px 14px; }
.msg-time { font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1)); opacity:0.7; margin-top:2px; }

/* 입력 영역 */
.input-area { padding:10px 16px; border-top:1px solid var(--border); background:var(--card); display:flex; gap:8px; flex-shrink:0; }
.input-area input { flex:1; padding:10px 14px; border:1px solid var(--border); border-radius:20px; font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); background:var(--bg); color:var(--text); }
.input-area input:focus { outline:none; border-color:var(--primary); }
.input-area .send-btn { width:40px; height:40px; border:none; background:var(--primary); color:#fff; border-radius:50%; font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1)); cursor:pointer; display:flex; align-items:center; justify-content:center; }

.empty-state { text-align:center; padding:60px 20px; color:var(--muted); flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.empty-state i { font-size:calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom:12px; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<div class="chat-header">
    <button class="back-btn" onclick="<?php echo ($room_id || $hospital_id) ? "location.href='chat.php'" : "history.back()"; ?>"><i class="bi bi-chevron-left"></i></button>
    <h1 id="chat-title"><i class="bi bi-chat-dots"></i> 상담 채팅</h1>
</div>

<div id="rooms-view" style="flex:1;overflow-y:auto;<?php echo ($room_id || $hospital_id) ? 'display:none' : ''; ?>"></div>

<div id="chat-view" style="flex:1;display:<?php echo ($room_id || $hospital_id) ? 'flex' : 'none'; ?>;flex-direction:column;">
    <div class="messages-area" id="messages"></div>
    <div class="input-area">
        <input type="text" id="msg-input" placeholder="메시지를 입력하세요..." maxlength="2000">
        <button class="send-btn" onclick="sendMessage()"><i class="bi bi-send-fill"></i></button>
    </div>
</div>

<script>
var API = window.location.pathname.replace(/\/[^\/]*$/, '') + '/api_chat.php';
var currentRoomId = <?php echo $room_id ?: 0; ?>;
var hospitalId = <?php echo $hospital_id ?: 0; ?>;
var lastMsgId = 0;
var pollTimer = null;

function loadRooms() {
    fetch(API + '?action=rooms')
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.login_required) { alert('로그인이 필요합니다'); return; }
        var el = document.getElementById('rooms-view');
        if (res.login_required) { el.innerHTML = '<div class="empty-state"><p>로그인이 필요합니다</p></div>'; return; }
        if (!res.success || !res.rooms || !res.rooms.length) {
            el.innerHTML = '<div class="empty-state"><i class="bi bi-chat-dots"></i><p>채팅 내역이 없습니다</p><p style="font-size:13px;margin-top:8px;">병원 상세 페이지에서 상담을 시작해보세요</p></div>';
            return;
        }
        el.innerHTML = '';
        (res.rooms || []).forEach(function(r) {
            var a = document.createElement('a');
            a.className = 'room-item';
            a.href = 'chat.php?room_id=' + r.id;
            var img = r.hospital_image ?
                '<img src="' + esc(r.hospital_image) + '" alt="">' :
                '<div class="no-img"><i class="bi bi-building"></i></div>';
            var badge = r.unread_count > 0 ? '<span class="room-badge">' + r.unread_count + '</span>' : '';
            a.innerHTML = img + '<div class="room-info"><div class="room-name">' + esc(r.hospital_name) + '</div><div class="room-last">' + esc(r.last_message || '') + '</div></div>' + badge;
            el.appendChild(a);
        });
    });
}

function openChat(roomId) {
    currentRoomId = roomId;
    document.getElementById('rooms-view').style.display = 'none';
    document.getElementById('chat-view').style.display = 'flex';
    loadMessages();
    startPolling();
}

function createRoom() {
    fetch(API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'create', hospital_id: hospitalId})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            currentRoomId = res.room_id;
            openChat(currentRoomId);
        }
    });
}

function loadMessages() {
    fetch(API + '?action=messages&room_id=' + currentRoomId + '&after=' + lastMsgId)
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success) return;
        var el = document.getElementById('messages');
        (res.messages || []).forEach(function(m) {
            var div = document.createElement('div');
            div.className = 'msg ' + (m.sender_type === 'patient' ? 'mine' : (m.sender_type === 'system' ? 'system' : 'theirs'));
            div.innerHTML = esc(m.message) + '<div class="msg-time">' + formatTime(m.created_at) + '</div>';
            el.appendChild(div);
            lastMsgId = Math.max(lastMsgId, m.id);
        });
        if (res.messages.length > 0) {
            el.scrollTop = el.scrollHeight;
        }
    });
}

function sendMessage() {
    var input = document.getElementById('msg-input');
    var msg = input.value.trim();
    if (!msg) return;
    input.value = '';

    fetch(API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'send', room_id: currentRoomId, message: msg})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) loadMessages();
    });
}

function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(loadMessages, 3000);
}

function formatTime(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ', 'T'));
    return d.getHours() + ':' + String(d.getMinutes()).padStart(2, '0');
}

function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// Enter 키로 전송
document.getElementById('msg-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendMessage();
});

// 초기화
if (hospitalId > 0 && !currentRoomId) {
    createRoom();
} else if (currentRoomId > 0) {
    openChat(currentRoomId);
} else {
    loadRooms();
}
</script>

<?php if (!$room_id && !$hospital_id): ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php endif; ?>
</body>
</html>
