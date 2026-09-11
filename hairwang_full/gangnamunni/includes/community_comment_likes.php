<?php
/** Persistent comment likes. A unique pair prevents duplicate votes. */
function community_comment_like_request(mysqli $connect, int $post_id): void {
    header('Content-Type: application/json; charset=utf-8');
    $respond = static function (array $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!is_gangnamunni_logged_in()) $respond(['success' => false, 'login_required' => true], 401);
    if (!validateCsrfToken((string)($_POST['csrf_token'] ?? ''))) $respond(['success' => false, 'message' => '새로고침 후 다시 시도해주세요.'], 403);
    $member = get_gangnamunni_member();
    $user = (string)($member['mb_id'] ?? '');
    if ($user === '') $respond(['success' => false, 'login_required' => true], 401);
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    $desired = $_POST['liked'] ?? '';
    if ($comment_id < 1 || !in_array($desired, ['0', '1'], true)) $respond(['success' => false, 'message' => '잘못된 요청입니다.'], 400);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        // Like the existing post-like feature, provision on first authenticated use.
        mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_community_comment_likes (
            comment_id INT NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (comment_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        mysqli_begin_transaction($connect);
        $stmt = mysqli_prepare($connect, "SELECT c.id FROM gangnamunni_community_comments c
            JOIN gangnamunni_community_posts p ON p.id=c.post_id
            WHERE c.id=? AND c.post_id=? AND p.status='active' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'ii', $comment_id, $post_id);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$exists) {
            mysqli_rollback($connect);
            $respond(['success' => false, 'message' => '댓글을 찾을 수 없습니다.'], 404);
        }
        $sql = $desired === '1'
            ? 'INSERT IGNORE INTO gangnamunni_community_comment_likes (comment_id, user_id) VALUES (?, ?)'
            : 'DELETE FROM gangnamunni_community_comment_likes WHERE comment_id=? AND user_id=?';
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, 'is', $comment_id, $user);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($connect, 'SELECT COUNT(*) AS count FROM gangnamunni_community_comment_likes WHERE comment_id=?');
        mysqli_stmt_bind_param($stmt, 'i', $comment_id);
        mysqli_stmt_execute($stmt);
        $count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'];
        mysqli_stmt_close($stmt);
        mysqli_commit($connect);
        $respond(['success' => true, 'liked' => $desired === '1', 'count' => $count]);
    } catch (Throwable $error) {
        mysqli_rollback($connect);
        error_log('Community comment like: ' . $error->getMessage());
        $respond(['success' => false, 'message' => '좋아요 처리에 실패했습니다. 다시 시도해주세요.'], 500);
    }
}

function community_comment_like_counts(mysqli $connect, int $post_id, string $user): array {
    $table = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_comment_likes'");
    if (!$table || !mysqli_num_rows($table)) return [];
    $stmt = mysqli_prepare($connect, 'SELECT l.comment_id, COUNT(*) AS count, MAX(l.user_id=?) AS liked
        FROM gangnamunni_community_comment_likes l
        JOIN gangnamunni_community_comments c ON c.id=l.comment_id
        WHERE c.post_id=? GROUP BY l.comment_id');
    mysqli_stmt_bind_param($stmt, 'si', $user, $post_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $counts = [];
    while ($row = mysqli_fetch_assoc($result)) $counts[(int)$row['comment_id']] = $row;
    mysqli_stmt_close($stmt);
    return $counts;
}
