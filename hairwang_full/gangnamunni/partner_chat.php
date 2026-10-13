<?php
session_start();
$admin_page_title = '채팅 상담 관리';
$admin_current = 'partner_chat';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');
loadThemeSettings($connect);
require_admin_login();
require_once __DIR__ . '/includes/hospital_filter.php';

// 접근: 최고관리자 + 병원 계정만. (이전에는 로그인만 확인해 viewer·editor 등 모든 관리자가
// 소속 병원이 없다는 이유로 "전체" 범위가 되어 모든 환자 채팅을 읽고 병원 이름으로 답장할 수 있었다.)
$chat_role = $_SESSION['ad_admin_role'] ?? '';
if ($chat_role !== 'super_admin' && !is_hospital_role()) {
    http_response_code(403);
    exit('채팅 상담에 접근할 권한이 없습니다.');
}

// 병원 범위 (fail-closed): super_admin 만 전체, 병원 계정은 소속 병원만, 미배정이면 0건
$chat_hospital_ids = get_strict_admin_hospital_ids($connect);
$chat_scope_sql = $chat_hospital_ids === null
    ? ''
    : ' WHERE r.hospital_id IN (' . (empty($chat_hospital_ids) ? '0' : implode(',', array_map('intval', $chat_hospital_ids))) . ')';

if (empty($_SESSION['partner_chat_csrf'])) $_SESSION['partner_chat_csrf'] = bin2hex(random_bytes(32));
$chat_csrf = $_SESSION['partner_chat_csrf'];

// 채팅방 목록
$rooms = [];
$sql = "SELECT r.*, h.name as hospital_name,
        (SELECT message FROM gangnamunni_chat_messages WHERE room_id=r.id ORDER BY id DESC LIMIT 1) as last_msg,
        (SELECT COUNT(*) FROM gangnamunni_chat_messages WHERE room_id=r.id AND sender_type='patient' AND is_read=0) as unread
        FROM gangnamunni_chat_rooms r
        LEFT JOIN gangnamunni_hospitals h ON r.hospital_id=h.id
        {$chat_scope_sql}
        ORDER BY r.updated_at DESC";
$res = mysqli_query($connect, $sql);
if ($res) while ($row = mysqli_fetch_assoc($res)) $rooms[] = $row;

$room_id = intval($_GET['room_id'] ?? 0);
// 범위 밖 채팅방은 열람/전송 불가
if ($room_id > 0 && $chat_hospital_ids !== null) {
    $room_ok = false;
    foreach ($rooms as $r) { if ((int)$r['id'] === $room_id) { $room_ok = true; break; } }
    if (!$room_ok) $room_id = 0;
}
$messages = [];
if ($room_id > 0) {
    // 메시지 읽음 처리
    mysqli_query($connect, "UPDATE gangnamunni_chat_messages SET is_read=1 WHERE room_id=$room_id AND sender_type='patient'");
    $msg_res = mysqli_query($connect, "SELECT * FROM gangnamunni_chat_messages WHERE room_id=$room_id ORDER BY created_at ASC");
    if ($msg_res) while ($m = mysqli_fetch_assoc($msg_res)) $messages[] = $m;
}

// POST: 메시지 전송
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room_id > 0) {
    if (!hash_equals($chat_csrf, (string)($_POST['chat_csrf'] ?? ''))) {
        http_response_code(403);
        exit('요청이 만료되었습니다. 페이지를 새로고침한 후 다시 시도해주세요.');
    }
    $msg = trim($_POST['message'] ?? '');
    if ($msg) {
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_chat_messages (room_id, sender_type, sender_id, message) VALUES (?, 'hospital', ?, ?)");
        $admin_id = $_SESSION['ad_admin_id'] ?? 'admin';
        mysqli_stmt_bind_param($stmt, 'iss', $room_id, $admin_id, $msg);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_query($connect, "UPDATE gangnamunni_chat_rooms SET updated_at=NOW() WHERE id=$room_id");
        header("Location: partner_chat.php?room_id=$room_id"); exit;
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>
<style>
.chat-layout{display:flex;gap:0;height:calc(100vh - 160px);border:1px solid var(--bs-border-color);}
.chat-sidebar{width:280px;border-right:1px solid var(--bs-border-color);overflow-y:auto;}
.chat-room{padding:12px 16px;border-bottom:1px solid var(--bs-border-color);cursor:pointer;text-decoration:none;color:inherit;display:block;}
.chat-room:hover,.chat-room.active{background:var(--bs-tertiary-bg);}
.chat-main{flex:1;display:flex;flex-direction:column;}
.chat-msgs{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px;}
.chat-bubble{max-width:70%;padding:8px 14px;border-radius:14px;font-size:13px;line-height:1.5;}
.chat-bubble.hospital{align-self:flex-end;background:var(--bs-primary);color:#fff;border-bottom-right-radius:4px;}
.chat-bubble.patient{align-self:flex-start;background:var(--bs-tertiary-bg);border-bottom-left-radius:4px;}
.chat-bubble.system{align-self:center;background:var(--bs-light);color:var(--bs-secondary);font-size:11px;padding:4px 12px;}
.chat-input-area{padding:12px;border-top:1px solid var(--bs-border-color);display:flex;gap:8px;}
.unread-badge{background:#ef4444;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;}
</style>

<h4 class="mb-3"><i class="bi bi-chat-dots"></i> 채팅 상담 관리</h4>
<div class="chat-layout">
    <div class="chat-sidebar">
        <?php foreach ($rooms as $r): ?>
        <a href="partner_chat.php?room_id=<?php echo (int)$r['id']; ?>" class="chat-room <?php echo $room_id==$r['id']?'active':''; ?>">
            <div class="d-flex justify-content-between"><strong style="font-size:13px;"><?php echo htmlspecialchars($r['hospital_name'] ?? '병원 #'.$r['hospital_id']); ?></strong>
            <?php if ($r['unread'] > 0): ?><span class="unread-badge"><?php echo $r['unread']; ?></span><?php endif; ?></div>
            <div style="font-size:11px;color:var(--bs-secondary);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($r['last_msg'] ?? ''); ?></div>
            <div style="font-size:10px;color:var(--bs-secondary);margin-top:2px;">회원: <?php echo htmlspecialchars($r['member_id'] ?? ''); ?></div>
        </a>
        <?php endforeach; ?>
        <?php if (empty($rooms)): ?><div class="p-4 text-center text-muted">채팅방이 없습니다</div><?php endif; ?>
    </div>
    <div class="chat-main">
        <?php if ($room_id > 0): ?>
        <div class="chat-msgs" id="chatMsgs">
            <?php foreach ($messages as $m): ?>
            <div class="chat-bubble <?php echo htmlspecialchars(preg_replace('/[^a-z_]/', '', (string)$m['sender_type']), ENT_QUOTES); ?>"><?php echo nl2br(htmlspecialchars($m['message'])); ?>
                <div style="font-size:10px;opacity:0.7;margin-top:2px;"><?php echo $m['created_at']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <form method="post" class="chat-input-area">
            <input type="hidden" name="chat_csrf" value="<?php echo htmlspecialchars($chat_csrf, ENT_QUOTES); ?>">
            <input type="text" name="message" class="form-control" placeholder="답변을 입력하세요..." required autofocus>
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
        </form>
        <script>var c=document.getElementById('chatMsgs');if(c)c.scrollTop=c.scrollHeight;</script>
        <?php else: ?>
        <div class="d-flex align-items-center justify-content-center h-100 text-muted"><div class="text-center"><i class="bi bi-chat-dots" style="font-size:48px;"></i><p class="mt-2">채팅방을 선택하세요</p></div></div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
