<?php
/**
 * 휴지통 자동 정리 크론잡
 * 매일 실행: 설정 기간 지난 메일 영구 삭제
 */
require_once __DIR__ . '/includes/mail_helper.php';
$deleted = mail_auto_purge_all_accounts();
if ($deleted > 0) {
    error_log("[mail_purge] $deleted emails permanently deleted from Trash");
}
echo "Purged: $deleted\n";
