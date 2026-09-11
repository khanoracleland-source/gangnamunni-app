<?php
/** Keep old comments as roots; place replies immediately after their parent. */
function community_comments_in_thread_order(array $comments): array {
    $ids = array_fill_keys(array_column($comments, 'id'), true);
    $roots = $children = [];
    foreach ($comments as $comment) {
        $parent = (int)($comment['parent_id'] ?? 0);
        if ($parent && $parent !== (int)$comment['id'] && isset($ids[$parent])) {
            $children[$parent][] = $comment;
        } else {
            $comment['parent_id'] = 0;
            $roots[] = $comment;
        }
    }
    $ordered = [];
    foreach ($roots as $root) {
        $ordered[] = $root;
        foreach ($children[$root['id']] ?? [] as $reply) $ordered[] = $reply;
    }
    return $ordered;
}

function community_comment_submit(mysqli $connect, int $post_id): void {
    if (!is_gangnamunni_logged_in()) {
        http_response_code(401);
        exit('로그인 후 댓글을 작성해주세요.');
    }
    $member = get_gangnamunni_member();
    $user = (string)($member['mb_id'] ?? '');
    $author = $member['mb_nick'] ?? $member['mb_name'] ?? $user;
    $content = trim((string)($_POST['content'] ?? ''));
    $parent = filter_var($_POST['parent_id'] ?? '0', FILTER_VALIDATE_INT);
    if ($user === '' || $content === '' || mb_strlen($content) > 500 || $parent === false || $parent < 0) {
        http_response_code(400);
        exit('댓글과 답글 대상을 확인해주세요. 댓글은 1~500자로 입력해주세요.');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_community_comments (
            id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL,
            mb_id VARCHAR(50) NOT NULL, author VARCHAR(100) DEFAULT NULL,
            content TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_post_id (post_id), INDEX idx_mb_id (mb_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // An additive relation preserves existing installations without altering their comments.
        mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_community_comment_threads (
            comment_id INT NOT NULL PRIMARY KEY, parent_id INT NOT NULL,
            INDEX idx_parent_id (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        mysqli_begin_transaction($connect);
        $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_community_posts WHERE id=? AND status='active' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        $post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$post) throw new DomainException('게시글을 찾을 수 없습니다.');
        if ($parent > 0) {
            $stmt = mysqli_prepare($connect, 'SELECT c.id FROM gangnamunni_community_comments c
                LEFT JOIN gangnamunni_community_comment_threads t ON t.comment_id=c.id
                WHERE c.id=? AND c.post_id=? AND t.comment_id IS NULL FOR UPDATE');
            mysqli_stmt_bind_param($stmt, 'ii', $parent, $post_id);
            mysqli_stmt_execute($stmt);
            $root = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!$root) throw new DomainException('답글을 작성할 원래 댓글을 찾을 수 없습니다.');
        }
        $stmt = mysqli_prepare($connect, 'INSERT INTO gangnamunni_community_comments (post_id, mb_id, author, content) VALUES (?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'isss', $post_id, $user, $author, $content);
        mysqli_stmt_execute($stmt);
        $comment_id = mysqli_insert_id($connect);
        mysqli_stmt_close($stmt);
        if ($parent > 0) {
            $stmt = mysqli_prepare($connect, 'INSERT INTO gangnamunni_community_comment_threads (comment_id, parent_id) VALUES (?, ?)');
            mysqli_stmt_bind_param($stmt, 'ii', $comment_id, $parent);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $stmt = mysqli_prepare($connect, 'UPDATE gangnamunni_community_posts SET comment_count = COALESCE(comment_count, 0) + 1 WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($connect);
        header('Location: detail_community.php?id=' . $post_id . '#comment-' . $comment_id, true, 303);
        exit;
    } catch (Throwable $error) {
        mysqli_rollback($connect);
        $expected = $error instanceof DomainException;
        if (!$expected) error_log('Community comment submit: ' . $error->getMessage());
        http_response_code($expected ? 400 : 500);
        echo htmlspecialchars($expected ? $error->getMessage() : '댓글을 저장하지 못했습니다. 뒤로 이동하여 다시 시도해주세요.', ENT_QUOTES, 'UTF-8');
        exit;
    }
}
