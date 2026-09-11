<?php
// Every block hides mutual content and prevents incoming direct messages.
function community_blocked_ids(mysqli $db, string $viewer, string $scope = 'hide_content', bool $both = true): array {
    if ($viewer === '') return [];
    if (!in_array($scope, ['hide_content', 'block_messages'], true)) throw new InvalidArgumentException('Invalid block scope');
    if (!$db->query("SHOW TABLES LIKE 'gangnamunni_blocked_users'")->num_rows) return [];
    $condition = $both ? '(b.blocker_id=? OR b.blocked_id=?)' : 'b.blocker_id=?';
    $stmt = $db->prepare("SELECT b.blocker_id,b.blocked_id FROM gangnamunni_blocked_users b WHERE {$condition}");
    if ($both) $stmt->bind_param('ss', $viewer, $viewer); else $stmt->bind_param('s', $viewer);
    $stmt->execute();
    $ids = [];
    foreach ($stmt->get_result() as $row) $ids[] = $row['blocker_id'] === $viewer ? $row['blocked_id'] : $row['blocker_id'];
    $stmt->close();
    return array_values(array_unique($ids));
}

function community_content_condition(mysqli $db, string $column, ?string $viewer = null): string {
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) throw new InvalidArgumentException('Invalid column');
    $ids = community_blocked_ids($db, $viewer ?? (string)($_SESSION['ss_mb_id'] ?? ''));
    if (!$ids) return '1=1';
    $quoted = array_map(fn($id) => "'".$db->real_escape_string($id)."'", $ids);
    return "COALESCE({$column}, '') NOT IN (".implode(',', $quoted).')';
}

function community_message_blocked(mysqli $db, string $sender, string $recipient): bool {
    return in_array($sender, community_blocked_ids($db, $recipient, 'block_messages', false), true);
}
