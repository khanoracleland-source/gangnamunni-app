<?php
/**
 * 실시간 채팅 상담 API (Polling 기반)
 * GET ?action=rooms — 내 채팅방 목록
 * GET ?action=messages&room_id=X&after=Y — 메시지 조회
 * POST {action: 'create', hospital_id} — 채팅방 생성
 * POST {action: 'send', room_id, message} — 메시지 전송
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    hospital_id INT NOT NULL,
    status ENUM('active','closed') DEFAULT 'active',
    last_message_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_member (member_id),
    KEY idx_hospital (hospital_id),
    UNIQUE KEY idx_unique_room (member_id, hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_type ENUM('patient','hospital','system') NOT NULL,
    sender_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_room (room_id, created_at),
    KEY idx_unread (room_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 로그인 체크
$g5_common = dirname(__DIR__) . '/common.php';
if (file_exists($g5_common) && !defined('G5_PATH')) {
    include_once $g5_common;
}
$is_logged = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$member_id = $is_logged ? (int)($member['mb_no'] ?? 0) : 0;

if (!$is_logged) {
    jsonSuccess(['login_required' => true]);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'rooms';

    if ($action === 'rooms') {
        $stmt = mysqli_prepare($connect,
            "SELECT r.*, h.name as hospital_name, h.main_image_url as hospital_image,
             (SELECT COUNT(*) FROM gangnamunni_chat_messages m WHERE m.room_id = r.id AND m.is_read = 0 AND m.sender_type != 'patient') as unread_count,
             (SELECT m2.message FROM gangnamunni_chat_messages m2 WHERE m2.room_id = r.id ORDER BY m2.id DESC LIMIT 1) as last_message
             FROM gangnamunni_chat_rooms r
             JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
             WHERE r.member_id = ?
             ORDER BY COALESCE(r.last_message_at, r.created_at) DESC");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $member_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $rooms = [];
            while ($row = mysqli_fetch_assoc($res)) $rooms[] = $row;
            mysqli_stmt_close($stmt);
            jsonSuccess(['rooms' => $rooms]);
        }

    } elseif ($action === 'messages') {
        $room_id = (int)($_GET['room_id'] ?? 0);
        $after_id = (int)($_GET['after'] ?? 0);

        // 권한 체크
        $check = mysqli_prepare($connect, "SELECT id FROM gangnamunni_chat_rooms WHERE id=? AND member_id=?");
        mysqli_stmt_bind_param($check, 'ii', $room_id, $member_id);
        mysqli_stmt_execute($check);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
            mysqli_stmt_close($check);
            jsonError('접근 권한 없음', 403);
        }
        mysqli_stmt_close($check);

        // 메시지 조회
        $sql = "SELECT * FROM gangnamunni_chat_messages WHERE room_id = ?";
        $params = [$room_id];
        $types = 'i';
        if ($after_id > 0) {
            $sql .= " AND id > ?";
            $params[] = $after_id;
            $types .= 'i';
        }
        $sql .= " ORDER BY id ASC LIMIT 100";

        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $messages = [];
        while ($row = mysqli_fetch_assoc($res)) $messages[] = $row;
        mysqli_stmt_close($stmt);

        // 읽음 처리
        $mark = mysqli_prepare($connect, "UPDATE gangnamunni_chat_messages SET is_read=1 WHERE room_id=? AND sender_type != 'patient' AND is_read=0");
        mysqli_stmt_bind_param($mark, 'i', $room_id);
        mysqli_stmt_execute($mark);
        mysqli_stmt_close($mark);

        jsonSuccess(['messages' => $messages]);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create') {
        $hospital_id = (int)($input['hospital_id'] ?? 0);
        if ($hospital_id <= 0) jsonError('hospital_id 필수');

        // 기존 방 확인
        $exist = mysqli_prepare($connect, "SELECT id FROM gangnamunni_chat_rooms WHERE member_id=? AND hospital_id=?");
        mysqli_stmt_bind_param($exist, 'ii', $member_id, $hospital_id);
        mysqli_stmt_execute($exist);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($exist));
        mysqli_stmt_close($exist);

        if ($existing) {
            jsonSuccess(['room_id' => (int)$existing['id'], 'existing' => true]);
        }

        $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_chat_rooms (member_id, hospital_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins, 'ii', $member_id, $hospital_id);
        mysqli_stmt_execute($ins);
        $room_id = mysqli_insert_id($connect);
        mysqli_stmt_close($ins);

        // 시스템 메시지
        $sys = mysqli_prepare($connect, "INSERT INTO gangnamunni_chat_messages (room_id, sender_type, message) VALUES (?, 'system', '상담이 시작되었습니다. 궁금한 점을 자유롭게 문의해주세요.')");
        mysqli_stmt_bind_param($sys, 'i', $room_id);
        mysqli_stmt_execute($sys);
        mysqli_stmt_close($sys);

        jsonSuccess(['room_id' => $room_id, 'existing' => false]);

    } elseif ($action === 'send') {
        $room_id = (int)($input['room_id'] ?? 0);
        $message = trim($input['message'] ?? '');

        if (!$room_id || !$message) jsonError('room_id, message 필수');
        if (mb_strlen($message) > 2000) jsonError('메시지가 너무 깁니다');

        // 권한 체크
        $check = mysqli_prepare($connect, "SELECT id FROM gangnamunni_chat_rooms WHERE id=? AND member_id=?");
        mysqli_stmt_bind_param($check, 'ii', $room_id, $member_id);
        mysqli_stmt_execute($check);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
            mysqli_stmt_close($check);
            jsonError('접근 권한 없음', 403);
        }
        mysqli_stmt_close($check);

        $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_chat_messages (room_id, sender_type, sender_id, message) VALUES (?, 'patient', ?, ?)");
        mysqli_stmt_bind_param($ins, 'iis', $room_id, $member_id, $message);
        mysqli_stmt_execute($ins);
        $msg_id = mysqli_insert_id($connect);
        mysqli_stmt_close($ins);

        // last_message_at 업데이트
        mysqli_query($connect, "UPDATE gangnamunni_chat_rooms SET last_message_at = NOW() WHERE id = {$room_id}");

        jsonSuccess(['message_id' => $msg_id]);
    }
}

mysqli_close($connect);
jsonError('잘못된 요청');
