<?php
/** Shared, transactional moderation actions. Caller must authorize manage_reports. */
function ugc_query(mysqli $db, string $sql, string $types = '', array $args = []): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$args);
    $stmt->execute();
    return $stmt;
}
function ugc_schema(mysqli $db): void {
    $db->query("CREATE TABLE IF NOT EXISTS gangnamunni_admin_restrictions (
        mb_id VARCHAR(100) PRIMARY KEY, previous_intercept VARCHAR(8) NOT NULL DEFAULT '',
        applied_intercept VARCHAR(8) NOT NULL, restriction_type VARCHAR(20) NOT NULL,
        admin_id INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS gangnamunni_blocked_users (
        id INT AUTO_INCREMENT PRIMARY KEY, blocker_id VARCHAR(100) NOT NULL,
        blocked_id VARCHAR(100) NOT NULL, blocked_name VARCHAR(200) DEFAULT NULL,
        reason VARCHAR(500) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_block_pair(blocker_id, blocked_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ugc_post_guard(): void {
    if (!in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin','admin'], true)) {
        http_response_code(403); exit('관리자만 변경할 수 있습니다.');
    }
    if (!hash_equals($_SESSION['ugc_admin_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403); exit('요청이 만료되었습니다. 새로고침 후 다시 시도해주세요.');
    }
}
function ugc_content(mysqli $db, string $type, int $id): array {
    $tables = ['community'=>'gangnamunni_community_posts','comment'=>'gangnamunni_community_comments',
        'review'=>'hospital_reviews','qna'=>'gangnamunni_qna','chat'=>'gangnamunni_chat_messages',
        'tip'=>'gangnamunni_tips','event'=>'gangnamunni_events'];
    if (!isset($tables[$type]) || $id < 1) throw new DomainException('잘못된 콘텐츠입니다.');
    $row = ugc_query($db, 'SELECT * FROM '.$tables[$type].' WHERE id=? FOR UPDATE', 'i', [$id])->get_result()->fetch_assoc();
    if (!$row) throw new DomainException('원본 콘텐츠가 없습니다. 기각하거나 확인 후 다시 처리해주세요.');
    return $row;
}
function ugc_author(mysqli $db, string $type, array $content): array {
    $column = ['community'=>'mb_id','comment'=>'mb_id','review'=>'user_id','qna'=>'member_id','chat'=>'sender_id'][$type] ?? null;
    if (!$column || empty($content[$column]) || ($type === 'chat' && ($content['sender_type'] ?? '') !== 'patient')) {
        throw new DomainException('실제 작성자 계정을 확인할 수 없습니다.');
    }
    // Community IDs are strings; other author columns are resolved against their stored member ID.
    $identityColumn = $type === 'chat' ? 'mb_no' : 'mb_id';
    $member = ugc_query($db, 'SELECT mb_no,mb_id,mb_nick,mb_intercept_date,mb_level FROM g5_member WHERE '.$identityColumn.'=? FOR UPDATE', 's', [(string)$content[$column]])->get_result()->fetch_assoc();
    if (!$member) throw new DomainException('작성자 회원을 찾을 수 없습니다.');
    return $member;
}
function ugc_restrict(mysqli $db, string $id, string $type, string $reason, int $admin): void {
    $m = ugc_query($db, 'SELECT mb_id,mb_email,mb_nick,mb_level,mb_intercept_date FROM g5_member WHERE mb_id=? FOR UPDATE', 's', [$id])->get_result()->fetch_assoc();
    if (!$m) throw new DomainException('존재하지 않는 회원입니다.');
    if ((int)$m['mb_level'] >= 10 || $id === ($_SESSION['ss_mb_id'] ?? '')) throw new DomainException('관리자 또는 본인 계정은 차단할 수 없습니다.');
    if ($db->query("SHOW TABLES LIKE 'gangnamunni_admins'")->num_rows) {
        $a = ugc_query($db, 'SELECT id FROM gangnamunni_admins WHERE is_active=1 AND (username=? OR email=?) LIMIT 1','ss',[$id,$m['mb_email']])->get_result()->fetch_assoc();
        if ($a) throw new DomainException('관리자 계정은 이 화면에서 차단할 수 없습니다.');
    }
    $existing = ugc_query($db, 'SELECT * FROM gangnamunni_admin_restrictions WHERE mb_id=? FOR UPDATE', 's', [$id])->get_result()->fetch_assoc();
    $date = date('Ymd');
    if (!$existing) {
        ugc_query($db, 'INSERT INTO gangnamunni_admin_restrictions (mb_id,previous_intercept,applied_intercept,restriction_type,admin_id) VALUES (?,?,?,?,?)', 'ssssi', [$id,(string)$m['mb_intercept_date'],$date,$type,$admin]);
        ugc_query($db, 'UPDATE g5_member SET mb_intercept_date=? WHERE mb_id=?', 'ss', [$date,$id]);
    } elseif ($type === 'user_banned') {
        ugc_query($db, 'UPDATE gangnamunni_admin_restrictions SET restriction_type=?,admin_id=? WHERE mb_id=?', 'sis', [$type,$admin,$id]);
    }
    $block = ugc_query($db, "SELECT id FROM gangnamunni_blocked_users WHERE BINARY blocker_id='ADMIN' AND blocked_id=? FOR UPDATE", 's', [$id])->get_result()->fetch_assoc();
    $reason = mb_substr($reason,0,500);
    if (!$block) ugc_query($db, "INSERT INTO gangnamunni_blocked_users (blocker_id,blocked_id,blocked_name,reason) VALUES ('ADMIN',?,?,?)", 'sss', [$id,$m['mb_nick'],$reason]);
    else ugc_query($db, 'UPDATE gangnamunni_blocked_users SET reason=? WHERE id=?', 'si', [$reason,$block['id']]);
}
function ugc_unblock(mysqli $db, int $id): void {
    $block = ugc_query($db, 'SELECT * FROM gangnamunni_blocked_users WHERE id=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
    if (!$block) throw new DomainException('이미 해제되었거나 존재하지 않는 차단입니다.');
    if ($block['blocker_id'] === 'ADMIN') {
        $member = ugc_query($db, 'SELECT mb_id FROM g5_member WHERE mb_id=? FOR UPDATE','s',[$block['blocked_id']])->get_result()->fetch_assoc();
        $r = ugc_query($db, 'SELECT * FROM gangnamunni_admin_restrictions WHERE mb_id=? FOR UPDATE','s',[$block['blocked_id']])->get_result()->fetch_assoc();
        if ($r) {
            ugc_query($db, 'UPDATE g5_member SET mb_intercept_date=? WHERE mb_id=? AND mb_intercept_date=?','sss',[$r['previous_intercept'],$r['mb_id'],$r['applied_intercept']]);
            ugc_query($db, 'DELETE FROM gangnamunni_admin_restrictions WHERE mb_id=?','s',[$r['mb_id']]);
        }
    }
    ugc_query($db, 'DELETE FROM gangnamunni_blocked_users WHERE id=?','i',[$id]);
}
function ugc_remove_content(mysqli $db, string $type, int $id): void {
    $queries = [
        'community'=>"UPDATE gangnamunni_community_posts SET status='deleted' WHERE id=?",
        // Preserve reply relationships while removing objectionable text.
        'comment'=>"UPDATE gangnamunni_community_comments SET content='[관리자에 의해 삭제된 댓글입니다.]' WHERE id=?",
        'review'=>'UPDATE hospital_reviews SET is_active=0 WHERE id=?',
        'qna'=>'UPDATE gangnamunni_qna SET is_public=0 WHERE id=?',
        'chat'=>"UPDATE gangnamunni_chat_messages SET message='[관리자에 의해 삭제된 메시지입니다.]' WHERE id=?",
        'tip'=>'UPDATE gangnamunni_tips SET is_active=0 WHERE id=?',
        'event'=>'UPDATE gangnamunni_events SET is_active=0 WHERE id=?'
    ];
    if (!isset($queries[$type])) throw new DomainException('지원하지 않는 콘텐츠 유형입니다.');
    ugc_query($db,$queries[$type],'i',[$id]);
}
function ugc_resolve(mysqli $db, int $id, string $action, string $note, int $admin): void {
    if (!in_array($action,['warning','content_removed','user_suspended','user_banned','dismiss'],true)) throw new DomainException('잘못된 조치입니다.');
    $report = ugc_query($db,'SELECT * FROM gangnamunni_content_reports WHERE id=? FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
    if (!$report || !in_array($report['status'],['pending','investigating'],true)) throw new DomainException('이미 처리되었거나 존재하지 않는 신고입니다: #'.$id);
    if ($action !== 'dismiss') {
        $content = ugc_content($db,$report['content_type'],(int)$report['content_id']);
        if ($action === 'content_removed') ugc_remove_content($db,$report['content_type'],(int)$report['content_id']);
        if (in_array($action,['warning','user_suspended','user_banned'],true) && !($action === 'warning' && in_array($report['content_type'], ['tip','event'], true))) {
            $member = ugc_author($db,$report['content_type'],$content);
            if ($action !== 'warning') ugc_restrict($db,$member['mb_id'],$action,$note,$admin);
            else ugc_query($db,"UPDATE g5_member SET mb_memo=CONCAT(COALESCE(mb_memo,''),?) WHERE mb_id=?",'ss',["\n[UGC warning #".$id.'] '.date('Y-m-d H:i').' '.$note,$member['mb_id']]);
            ugc_query($db,'UPDATE gangnamunni_content_reports SET reported_user_id=?,reported_user_name=? WHERE id=?','isi',[$member['mb_no'],$member['mb_nick'],$id]);
        }
    }
    $status = $action === 'dismiss' ? 'dismissed' : 'resolved';
    $stored = $action === 'dismiss' ? 'none' : $action;
    ugc_query($db,'UPDATE gangnamunni_content_reports SET status=?,action_taken=?,action_note=?,admin_id=?,resolved_at=NOW() WHERE id=?','sssii',[$status,$stored,$note,$admin,$id]);
}
