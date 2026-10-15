<?php
/**
 * 메일 관리 페이지 v3
 * Gmail/Naver 스타일 UI + 답장/전달/새메일 + 체크박스 일괄처리
 * 첨부파일 + 공통서명 + 페이지당 표시 수
 *
 * @created 2026-02-11
 * @updated 2026-02-11
 */
session_start();
$admin_page_title = '메일 관리';
$admin_current = 'admin_mail';

// DB 상수를 관리자 검사보다 먼저 올린다 — check_admin_login() 이 cred_epoch
// (비밀번호 변경 시 세션 폐기)를 조회할 수 있어야 한다. 아래쪽의
// require_once 는 그대로 두되 두 번 로드되지 않는다(require_once).
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';

// AJAX 요청: 미로그인 시 JSON 에러 반환
if (isset($_GET['ajax']) && !check_admin_login()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'not_logged_in', 'total_unseen' => 0]);
    exit;
}

require_admin_login();
require_permission('admin_mail');
require_once __DIR__ . '/includes/mail_helper.php';

// 페이지당 표시 수 (세션 저장)
if (isset($_GET['set_per_page'])) {
    $_SESSION['mail_per_page'] = max(10, min(100, intval($_GET['set_per_page'])));
    echo json_encode(['success' => true]);
    exit;
}
$per_page = $_SESSION['mail_per_page'] ?? 20;

// === AJAX 요청 처리 ===
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_GET['ajax'];

    // 안 읽은 메일 수 (상단바용)
    if ($action === 'unread_counts') {
        echo json_encode(mail_get_unread_counts());
        exit;
    }

    // 메일 목록
    if ($action === 'list') {
        $account = intval($_GET['account'] ?? 0);
        $folder = $_GET['folder'] ?? 'INBOX';
        $search = trim($_GET['search'] ?? '');
        $page = intval($_GET['page'] ?? 1);
        $filter = $_GET['filter'] ?? 'all';
        $pp = intval($_GET['per_page'] ?? $per_page);
        $sort = $_GET['sort'] ?? 'date_desc';
        echo json_encode(mail_get_list($account, $folder, $search, $page, $pp, $filter, $sort));
        exit;
    }

    // 메일 읽기
    if ($action === 'read') {
        $account = intval($_GET['account'] ?? 0);
        $uid = intval($_GET['uid'] ?? 0);
        $folder = $_GET['folder'] ?? 'INBOX';
        echo json_encode(mail_read($account, $uid, $folder));
        exit;
    }

    // 메일 삭제
    if ($action === 'delete') {
        $account = intval($_POST['account'] ?? 0);
        $uid = intval($_POST['uid'] ?? 0);
        $folder = $_POST['folder'] ?? 'INBOX';
        echo json_encode(['success' => mail_delete($account, $uid, $folder)]);
        exit;
    }

    // 일괄 삭제
    if ($action === 'bulk_delete') {
        $account = intval($_POST['account'] ?? 0);
        $folder = $_POST['folder'] ?? 'INBOX';
        $uids = array_map('intval', explode(',', $_POST['uids'] ?? ''));
        $uids = array_filter($uids);
        if (empty($uids)) {
            echo json_encode(['error' => '선택된 메일이 없습니다.']);
            exit;
        }
        if (function_exists('mail_bulk_delete')) {
            echo json_encode(['success' => mail_bulk_delete($account, $uids, $folder)]);
        } else {
            // fallback: 하나씩 삭제
            foreach ($uids as $u) mail_delete($account, $u, $folder);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // 일괄 읽음/안읽음
    if ($action === 'bulk_seen') {
        $account = intval($_POST['account'] ?? 0);
        $folder = $_POST['folder'] ?? 'INBOX';
        $uids = array_map('intval', explode(',', $_POST['uids'] ?? ''));
        $uids = array_filter($uids);
        $seen = ($_POST['seen'] ?? '1') === '1';
        if (function_exists('mail_bulk_toggle_seen')) {
            echo json_encode(['success' => mail_bulk_toggle_seen($account, $uids, $seen, $folder)]);
        } else {
            foreach ($uids as $u) mail_toggle_seen($account, $u, $seen, $folder);
            echo json_encode(['success' => true]);
        }
        exit;
    }

    // 읽음/안읽음 토글
    if ($action === 'toggle_seen') {
        $account = intval($_POST['account'] ?? 0);
        $uid = intval($_POST['uid'] ?? 0);
        $folder = $_POST['folder'] ?? 'INBOX';
        $seen = ($_POST['seen'] ?? '1') === '1';
        echo json_encode(['success' => mail_toggle_seen($account, $uid, $seen, $folder)]);
        exit;
    }

    // 메일 보내기 (첨부파일 지원)
    if ($action === 'send') {
        $account = intval($_POST['account'] ?? 0);
        $to = trim($_POST['to'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = $_POST['body'] ?? '';
        $cc = trim($_POST['cc'] ?? '');
        $in_reply_to = trim($_POST['in_reply_to'] ?? '');
        $references = trim($_POST['references'] ?? '');

        // 서명 추가 (체크박스로 선택한 경우만)
        $include_sig = ($_POST['include_sig'] ?? '0') === '1';
        if ($include_sig && function_exists('mail_get_signature')) {
            $sig = mail_get_signature($account);
            if ($sig) {
                $body .= '<br><br>' . $sig;
            }
        }

        if (!$to || !$subject) {
            echo json_encode(['error' => '받는사람과 제목을 입력하세요.']);
            exit;
        }

        // 첨부파일 처리
        $attachments = [];
        if (!empty($_FILES['attachments'])) {
            $upload_dir = __DIR__ . '/data/mail_uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $files = $_FILES['attachments'];
            $count = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $count; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($error !== UPLOAD_ERR_OK || $size < 1) continue;
                if ($size > 10 * 1024 * 1024) continue; // 10MB 제한

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $safe_name = uniqid('mail_') . '.' . $ext;
                $dest = $upload_dir . $safe_name;

                if (move_uploaded_file($tmp, $dest)) {
                    $attachments[] = [
                        'path' => $dest,
                        'name' => $name,
                        'type' => mime_content_type($dest),
                    ];
                }
            }
        }

        if (function_exists('mail_send')) {
            $result = mail_send($account, $to, $subject, $body, $cc, $in_reply_to, $references, $attachments);
        } else {
            $result = ['error' => 'mail_send 함수를 사용할 수 없습니다.'];
        }

        // 첨부파일 정리
        foreach ($attachments as $att) {
            if (file_exists($att['path'])) @unlink($att['path']);
        }

        echo json_encode($result);
        exit;
    }

    // 서명 조회
    if ($action === 'get_signature') {
        $account = intval($_GET['account'] ?? 0);
        if (function_exists('mail_get_signature')) {
            echo json_encode(['signature' => mail_get_signature($account)]);
        } else {
            echo json_encode(['signature' => '']);
        }
        exit;
    }

    // 서명 저장
    if ($action === 'save_signature') {
        $account = intval($_POST['account'] ?? 0);
        $html = $_POST['signature'] ?? '';
        if (function_exists('mail_save_signature')) {
            mail_save_signature($account, $html);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => '서명 저장 함수를 사용할 수 없습니다.']);
        }
        exit;
    }

    // 상용구 목록
    if ($action === 'get_templates') {
        echo json_encode(['templates' => function_exists('mail_get_templates') ? mail_get_templates() : []]);
        exit;
    }

    // 상용구 저장
    if ($action === 'save_template') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        if (!$title) {
            echo json_encode(['error' => '제목을 입력하세요.']);
            exit;
        }
        if (function_exists('mail_save_template')) {
            echo json_encode(['success' => mail_save_template($id, $title, $content)]);
        } else {
            echo json_encode(['error' => '상용구 함수를 사용할 수 없습니다.']);
        }
        exit;
    }

    // 상용구 삭제
    if ($action === 'delete_template') {
        $id = intval($_POST['id'] ?? 0);
        if (function_exists('mail_delete_template')) {
            echo json_encode(['success' => mail_delete_template($id)]);
        } else {
            echo json_encode(['error' => '상용구 함수를 사용할 수 없습니다.']);
        }
        exit;
    }

    // 연락처 (수신자 자동완성)
    if ($action === 'get_contacts') {
        $account = intval($_GET['account'] ?? 0);
        echo json_encode(['contacts' => function_exists('mail_get_sent_contacts') ? mail_get_sent_contacts($account) : []]);
        exit;
    }

    // 휴지통 설정 조회
    if ($action === 'get_trash_settings') {
        echo json_encode(function_exists('mail_get_trash_settings') ? mail_get_trash_settings() : ['auto_purge_days' => 30, 'enabled' => true]);
        exit;
    }

    // 휴지통 설정 저장
    if ($action === 'save_trash_settings') {
        $days = max(1, min(365, intval($_POST['auto_purge_days'] ?? 30)));
        $enabled = ($_POST['enabled'] ?? '1') === '1';
        if (function_exists('mail_save_trash_settings')) {
            mail_save_trash_settings(['auto_purge_days' => $days, 'enabled' => $enabled]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 휴지통 수동 정리
    if ($action === 'purge_trash') {
        $total = 0;
        if (function_exists('mail_auto_purge_all_accounts')) {
            $total = mail_auto_purge_all_accounts();
        }
        echo json_encode(['success' => true, 'deleted' => $total]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// === HTML 렌더링 ===
$admin_extra_css = <<<'CSSEOF'
/* Gmail/Naver style mail layout v3 */
.mail-layout { display: flex; gap: 0; height: calc(100vh - 110px); min-height: 500px; }

/* 사이드바 */
.mail-sidebar { width: 200px; background: var(--bs-body-bg); border-right: 1px solid var(--bs-border-color); overflow-y: auto; flex-shrink: 0; padding: 8px; }
.mail-compose-btn { width: 100%; border-radius: 20px; padding: 10px 16px; font-weight: 600; font-size: 14px; margin-bottom: 12px; }
.mail-sidebar .folder-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-radius: 8px; margin-bottom: 2px; color: var(--bs-body-color); display: flex; justify-content: space-between; align-items: center; }
.mail-sidebar .folder-item:hover { background: var(--bs-tertiary-bg); }
.mail-sidebar .folder-item.active { background: var(--bs-primary-bg-subtle); color: var(--bs-primary); font-weight: 600; }
.mail-sidebar .folder-badge { font-size: 11px; font-weight: 600; }
.mail-sidebar .account-switcher { padding: 8px; border-top: 1px solid var(--bs-border-color); margin-top: 8px; }
.mail-sidebar .account-switcher select { font-size: 12px; }

/* 메일 목록 (넓게) */
.mail-list { width: 480px; border-right: 1px solid var(--bs-border-color); display: flex; flex-direction: column; flex-shrink: 0; }
.mail-toolbar { padding: 6px 8px; border-bottom: 1px solid var(--bs-border-color); background: var(--bs-body-bg); display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.mail-tb-btn { font-size: 11px !important; padding: 2px 8px !important; white-space: nowrap; }
.mail-search-box { flex: 1; position: relative; min-width: 120px; }
.mail-search-box input { padding: 5px 10px 5px 30px; font-size: 12px; border-radius: 20px; background: var(--bs-tertiary-bg); border: 1px solid transparent; width: 100%; }
.mail-search-box input:focus { background: var(--bs-body-bg); border-color: var(--bs-primary); }
.mail-search-box .bi-search { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--bs-tertiary-color); font-size: 12px; }
.mail-list-body { flex: 1; overflow-y: auto; }

/* 체크박스 메일 아이템 */
.mail-item { padding: 8px 8px; border-bottom: 1px solid var(--bs-border-color); cursor: pointer; transition: background 0.1s; display: flex; gap: 6px; align-items: flex-start; }
.mail-item:hover { background: var(--bs-tertiary-bg); }
.mail-item.active { background: var(--bs-primary-bg-subtle); }
.mail-item.unread { background: rgba(13,110,253,0.10); border-left: 4px solid #0d6efd; }
.mail-item.unread .mail-from { font-weight: 700; }
.mail-item.unread .mail-subject { font-weight: 600; }
.mail-item .mail-check { flex-shrink: 0; margin-top: 6px; cursor: pointer; width: 16px; height: 16px; }
.mail-item .mail-avatar { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: 600; flex-shrink: 0; }
.mail-item .mail-info { flex: 1; min-width: 0; }
.mail-item .mail-subject { font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--bs-secondary-color); }
.mail-item .mail-from { font-size: 12px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mail-item .mail-seen-badge { font-size: 14px; vertical-align: middle; }
.mail-item.unread .mail-seen-badge { color: #0d6efd; }
.mail-item:not(.unread) .mail-seen-badge { color: #6c757d; }
.mail-item .mail-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.mail-item .mail-date { font-size: 10px; color: var(--bs-tertiary-color); white-space: nowrap; }
.mail-item .mail-star { cursor: pointer; color: var(--bs-tertiary-color); font-size: 13px; }
.mail-item .mail-star.flagged { color: #f59e0b; }
.mail-item .mail-delete-btn { color: var(--bs-tertiary-color); cursor: pointer; font-size: 13px; opacity: 0; transition: opacity 0.15s; }
.mail-item:hover .mail-delete-btn { opacity: 1; }
.mail-item .mail-delete-btn:hover { color: var(--bs-danger); }

/* 정렬 드롭다운 */
.sort-select { font-size: 11px; padding: 2px 6px; border-radius: 6px; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); cursor: pointer; }

/* 상용구 검색 */
.template-search { padding: 6px 10px; border-bottom: 1px solid var(--bs-border-color); }
.template-search input { font-size: 12px; padding: 4px 8px; border-radius: 6px; border: 1px solid var(--bs-border-color); width: 100%; }

/* 일괄 처리 바 */
.bulk-bar { padding: 6px 10px; background: var(--bs-primary-bg-subtle); border-bottom: 1px solid var(--bs-border-color); display: none; gap: 6px; align-items: center; font-size: 12px; }
.bulk-bar.show { display: flex; }
.bulk-bar .bulk-count { font-weight: 600; color: var(--bs-primary); }
.bulk-bar .btn { font-size: 11px; padding: 2px 8px; }

/* 상태 바 */
.mail-status-bar { padding: 4px 10px; border-top: 1px solid var(--bs-border-color); font-size: 11px; color: var(--bs-secondary-color); display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.per-page-select { font-size: 11px; padding: 1px 4px; border-radius: 4px; border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); }

/* 메일 본문 */
.mail-content { flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.mail-view-header { padding: 16px 20px; border-bottom: 1px solid var(--bs-border-color); background: var(--bs-body-bg); }
.mail-view-subject { font-size: 20px; font-weight: 700; margin-bottom: 12px; }
.mail-view-meta { display: flex; gap: 12px; align-items: flex-start; }
.mail-view-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 16px; flex-shrink: 0; }
.mail-view-sender { flex: 1; }
.mail-view-actions { display: flex; gap: 4px; flex-shrink: 0; }
.mail-view-body { flex: 1; padding: 20px; overflow-y: auto; font-size: 14px; line-height: 1.7; }
.mail-view-body pre { white-space: pre-wrap; font-family: inherit; margin: 0; }
.mail-view-body img { max-width: 100%; height: auto; }
.mail-html-frame { display: block; width: 100%; min-height: 120px; border: 0; background: #fff; border-radius: 4px; }
.mail-view-attachments { padding: 12px 20px; border-top: 1px solid var(--bs-border-color); background: var(--bs-tertiary-bg); }

/* 답장/작성 모달 - 1.5배 크게 */
.compose-overlay {
    position: fixed; bottom: 0; right: 24px;
    width: 1050px; max-width: calc(100vw - 48px);
    background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-bottom: none;
    border-radius: 12px 12px 0 0; box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
    z-index: 1050; display: none; flex-direction: column; max-height: 88vh;
}
.compose-overlay.fullscreen {
    width: calc(100vw - var(--admin-sidebar-width) - 80px);
    right: 24px; left: auto;
    max-height: 92vh; height: 92vh;
}
.compose-header { padding: 10px 16px; background: var(--bs-dark-bg-subtle); border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.compose-header h6 { margin: 0; font-size: 14px; font-weight: 600; }
.compose-body { padding: 12px 16px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; }
.compose-body .form-control, .compose-body .form-select { font-size: 13px; margin-bottom: 6px; }
.compose-body textarea { flex: 1; min-height: 350px; resize: vertical; font-size: 14px; line-height: 1.6; }
.compose-footer { padding: 8px 16px; border-top: 1px solid var(--bs-border-color); display: flex; justify-content: space-between; align-items: center; }

/* 첨부파일 영역 */
.attach-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.attach-item { display: inline-flex; align-items: center; gap: 4px; background: var(--bs-tertiary-bg); border-radius: 6px; padding: 3px 8px; font-size: 11px; }
.attach-item .remove-attach { cursor: pointer; color: var(--bs-danger); font-size: 12px; }
.attach-item img { width: 24px; height: 24px; object-fit: cover; border-radius: 3px; }

/* 서명 미리보기 */
.signature-preview { border-top: 1px dashed var(--bs-border-color); padding-top: 8px; margin-top: 8px; font-size: 12px; color: var(--bs-secondary-color); }

/* 상용구 드롭다운 */
.template-dropdown { position: relative; display: inline-block; }
.template-menu { position: absolute; bottom: 100%; left: 0; min-width: 280px; max-height: 300px; overflow-y: auto; background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 8px; box-shadow: 0 -4px 16px rgba(0,0,0,0.15); display: none; z-index: 1060; margin-bottom: 4px; }
.template-menu.show { display: block; }
.template-menu-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid var(--bs-border-color); }
.template-menu-item:hover { background: var(--bs-tertiary-bg); }
.template-menu-item:last-child { border-bottom: none; }
.template-menu-item .tmpl-title { font-weight: 600; }
.template-menu-item .tmpl-preview { font-size: 11px; color: var(--bs-secondary-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; }
.template-menu-footer { padding: 6px 12px; border-top: 1px solid var(--bs-border-color); text-align: center; }

.mail-empty { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--bs-tertiary-color); flex-direction: column; gap: 10px; }

/* 환영 화면 (빈 오른쪽 패널) */
.mail-empty-welcome {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; padding: 40px 20px; text-align: center; color: var(--bs-body-color);
}
.mail-empty-welcome .welcome-icon {
    width: 100px; height: 100px; border-radius: 50%;
    background: linear-gradient(135deg, var(--bs-primary-bg-subtle), rgba(var(--bs-primary-rgb), 0.15));
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 20px;
}
.mail-empty-welcome .welcome-icon i { font-size: 42px; color: var(--bs-primary); }
.mail-empty-welcome h4 { font-weight: 700; margin-bottom: 8px; }
.mail-empty-welcome p { font-size: 14px; max-width: 320px; line-height: 1.6; }
.mail-empty-welcome .welcome-shortcuts {
    display: flex; gap: 16px; margin-top: 28px;
}
.mail-empty-welcome .shortcut-item {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 16px 20px; border-radius: 12px; cursor: pointer;
    background: var(--bs-tertiary-bg); transition: all 0.2s;
    min-width: 90px;
}
.mail-empty-welcome .shortcut-item:hover {
    background: var(--bs-primary-bg-subtle); transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.mail-empty-welcome .shortcut-item i { font-size: 22px; color: var(--bs-primary); }
.mail-empty-welcome .shortcut-item span { font-size: 12px; font-weight: 500; }
.mail-empty-welcome .welcome-stats {
    margin-top: 28px; display: flex; gap: 24px; font-size: 13px; color: var(--bs-secondary-color);
}
.mail-empty-welcome .stat-item { display: flex; align-items: center; gap: 6px; }
.mail-empty-welcome .stat-num { font-weight: 700; font-size: 18px; color: var(--bs-primary); }

/* 필터 버튼 */
.filter-btn { font-size: 11px; padding: 1px 8px; border-radius: 12px; }
.filter-btn.active { background: var(--bs-primary); color: #fff; border-color: var(--bs-primary); }

/* 수신자 자동완성 */
.contact-autocomplete { position: relative; }
.contact-dropdown {
    position: absolute; top: 100%; left: 0; right: 0; max-height: 220px; overflow-y: auto;
    background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-top: none;
    border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1060; display: none;
}
.contact-dropdown.show { display: block; }
.contact-dropdown-item {
    padding: 6px 10px; cursor: pointer; font-size: 12px; border-bottom: 1px solid var(--bs-border-color);
    display: flex; align-items: center; gap: 8px;
}
.contact-dropdown-item:last-child { border-bottom: none; }
.contact-dropdown-item:hover, .contact-dropdown-item.active { background: var(--bs-primary-bg-subtle); }
.contact-dropdown-item .contact-avatar {
    width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 11px; font-weight: 600; flex-shrink: 0;
}
.contact-dropdown-item .contact-info { flex: 1; min-width: 0; }
.contact-dropdown-item .contact-name { font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contact-dropdown-item .contact-email { font-size: 11px; color: var(--bs-secondary-color); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contact-dropdown-item .contact-count { font-size: 10px; color: var(--bs-tertiary-color); flex-shrink: 0; }

/* 휴지통 안내 바 */
.trash-info-bar { padding: 8px 12px; background: rgba(var(--bs-warning-rgb),0.1); border-bottom: 1px solid var(--bs-border-color); font-size: 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.trash-info-bar .text-muted { color: var(--bs-secondary-color) !important; }

/* 1200px 이하 */
@media (max-width: 1200px) { .mail-list { width: 380px; } }

/* 태블릿 */
@media (max-width: 992px) {
    .mail-list { width: 320px; }
    .mail-empty-welcome .welcome-shortcuts { gap: 10px; }
    .mail-empty-welcome .shortcut-item { padding: 12px 14px; min-width: 70px; }
}

/* 모바일 */
@media (max-width: 768px) {
    .mail-layout { flex-direction: column; height: auto; min-height: auto; }
    .mail-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--bs-border-color); padding: 4px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; }
    .mail-sidebar .mail-compose-btn { width: auto; margin-bottom: 0; padding: 6px 14px; font-size: 13px; border-radius: 16px; }
    .mail-sidebar .folder-item { display: inline-flex; margin-right: 2px; padding: 4px 10px; font-size: 12px; }
    .mail-sidebar .account-switcher { border-top: none; margin-top: 0; padding: 4px; }
    .mail-list { width: 100%; border-right: none; max-height: 50vh; }
    .mail-content { min-height: 400px; }
    .mail-content:has(.mail-empty-welcome) { min-height: 300px; }
    .mail-empty-welcome { padding: 20px 16px; }
    .mail-empty-welcome .welcome-icon { width: 70px; height: 70px; margin-bottom: 14px; }
    .mail-empty-welcome .welcome-icon i { font-size: 28px; }
    .mail-empty-welcome h4 { font-size: 18px; }
    .mail-empty-welcome p { font-size: 13px; }
    .mail-empty-welcome .welcome-shortcuts { flex-wrap: wrap; justify-content: center; }
    .mail-empty-welcome .shortcut-item { padding: 10px 16px; }
    .compose-overlay { width: 100%; right: 0; max-width: 100%; }
    .compose-overlay.fullscreen { width: 100%; left: 0; }
    .bulk-bar { flex-wrap: wrap; }
    .mail-toolbar { flex-wrap: wrap; }
    .mail-view-meta { flex-direction: column; gap: 8px; }
    .mail-view-actions { margin-top: 8px; }
}
CSSEOF;

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
include __DIR__ . '/includes/admin_header.php';
?>

<div class="mail-layout">
    <!-- 사이드바 -->
    <div class="mail-sidebar">
        <button class="btn btn-primary mail-compose-btn" onclick="openCompose()">
            <i class="bi bi-pencil-square me-1"></i> 편지쓰기
        </button>

        <div id="folderList"></div>

        <div class="account-switcher">
            <label class="form-label small text-muted mb-1">계정</label>
            <select class="form-select form-select-sm" id="accountSelect" onchange="selectAccount(this.value)"></select>
        </div>
    </div>

    <!-- 메일 목록 -->
    <div class="mail-list">
        <div class="mail-toolbar" style="flex-direction:column;align-items:stretch;gap:4px;">
            <div class="d-flex align-items-center gap-1 flex-wrap">
                <button class="btn btn-sm btn-primary mail-tb-btn" onclick="selectAllMails()" title="전체 선택"><i class="bi bi-check-all"></i> 전체선택</button>
                <button class="btn btn-sm btn-outline-secondary mail-tb-btn" onclick="clearSelection()" title="전체 해제"><i class="bi bi-x-circle"></i> 전체해제</button>
                <button class="btn btn-sm btn-outline-danger mail-tb-btn" id="bulkDeleteBtn" onclick="bulkDelete()" title="선택 삭제" disabled><i class="bi bi-trash"></i> 삭제 <span class="badge bg-danger" id="bulkCount" style="display:none;font-size:9px;padding:1px 4px;">0</span></button>
                <button class="btn btn-sm btn-outline-info mail-tb-btn" id="bulkReadBtn" onclick="bulkSeen(true)" title="읽음" style="display:none;"><i class="bi bi-envelope-open"></i></button>
                <button class="btn btn-sm btn-outline-warning mail-tb-btn" id="bulkUnreadBtn" onclick="bulkSeen(false)" title="안읽음" style="display:none;"><i class="bi bi-envelope"></i></button>
            </div>
            <div class="d-flex align-items-center gap-1">
                <div class="mail-search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" id="mailSearch" placeholder="검색..." onkeydown="if(event.key==='Enter')loadMailList()">
                </div>
                <select class="sort-select" id="sortSelect" onchange="changeSort(this.value)" title="정렬">
                    <option value="date_desc">최신순</option>
                    <option value="date_asc">오래된순</option>
                    <option value="from_asc">보낸사람 ↑</option>
                    <option value="from_desc">보낸사람 ↓</option>
                    <option value="subject_asc">제목 ↑</option>
                    <option value="subject_desc">제목 ↓</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="loadMailList()" title="새로고침" style="padding:2px 6px;font-size:12px;"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>

        <div class="px-2 py-1 d-flex gap-1 border-bottom">
            <button class="btn btn-sm filter-btn active" data-filter="all" onclick="setFilter('all')">전체</button>
            <button class="btn btn-sm filter-btn" data-filter="unseen" onclick="setFilter('unseen')">안읽음</button>
            <button class="btn btn-sm filter-btn" data-filter="flagged" onclick="setFilter('flagged')">중요</button>
        </div>
        <div class="mail-list-body" id="mailListBody">
            <div class="mail-empty"><i class="bi bi-envelope" style="font-size:32px;"></i><span>메일을 불러오는 중...</span></div>
        </div>
        <div class="mail-status-bar" id="mailStatusBar">
            <span id="mailCount">-</span>
            <div class="d-flex align-items-center gap-2">
                <select class="per-page-select" id="perPageSelect" onchange="changePerPage(this.value)" title="페이지당 표시 수">
                    <option value="10"<?php if($per_page==10) echo ' selected'; ?>>10건</option>
                    <option value="20"<?php if($per_page==20) echo ' selected'; ?>>20건</option>
                    <option value="50"<?php if($per_page==50) echo ' selected'; ?>>50건</option>
                    <option value="100"<?php if($per_page==100) echo ' selected'; ?>>100건</option>
                </select>
                <nav id="mailPagination"></nav>
            </div>
        </div>
    </div>

    <!-- 메일 본문 -->
    <div class="mail-content" id="mailContent">
        <div class="mail-empty-welcome">
            <div class="welcome-icon">
                <i class="bi bi-envelope-paper"></i>
            </div>
            <h4>Seoul Beauty Mail</h4>
            <p class="text-muted">왼쪽 목록에서 메일을 선택하여 읽거나<br>편지쓰기 버튼으로 새 메일을 작성하세요</p>
            <div class="welcome-shortcuts">
                <div class="shortcut-item" onclick="openCompose()">
                    <i class="bi bi-pencil-square"></i>
                    <span>편지쓰기</span>
                </div>
                <div class="shortcut-item" onclick="loadMailList()">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span>새로고침</span>
                </div>
                <div class="shortcut-item" onclick="setFilter('unseen')">
                    <i class="bi bi-envelope-exclamation"></i>
                    <span>안읽음</span>
                </div>
            </div>
            <div class="welcome-stats" id="welcomeStats"></div>
        </div>
    </div>
</div>

<!-- 새메일 작성 (하단 팝업 - 크게) -->
<div class="compose-overlay" id="composeOverlay">
    <div class="compose-header" onclick="toggleCompose()">
        <h6><i class="bi bi-pencil-square me-1"></i> <span id="composeTitle">새 메일</span></h6>
        <div>
            <button class="btn btn-sm btn-link text-muted p-0 me-2" onclick="event.stopPropagation();toggleFullscreen()" title="전체화면"><i class="bi bi-arrows-fullscreen" id="fullscreenIcon"></i></button>
            <button class="btn btn-sm btn-link text-muted p-0 me-2" onclick="event.stopPropagation();minimizeCompose()" title="최소화"><i class="bi bi-dash-lg"></i></button>
            <button class="btn btn-sm btn-link text-muted p-0" onclick="event.stopPropagation();closeCompose()" title="닫기"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <div class="compose-body" id="composeBody">
        <select class="form-select form-select-sm" id="composeAccount"></select>
        <div class="contact-autocomplete">
            <input type="text" class="form-control form-control-sm" id="composeTo" placeholder="받는사람 (이메일)" autocomplete="off">
            <div class="contact-dropdown" id="contactDropdown"></div>
        </div>
        <input type="text" class="form-control form-control-sm" id="composeCc" placeholder="참조 (CC)" style="display:none;">
        <input type="text" class="form-control form-control-sm" id="composeSubject" placeholder="제목">
        <textarea class="form-control" id="composeBodyText" placeholder="내용을 입력하세요..."></textarea>

        <!-- 첨부파일 목록 -->
        <div class="attach-list" id="attachList"></div>

        <!-- 원본 메일 인용 (답장/전달 시 이미지 포함 HTML) -->
        <div id="quotedOriginal" style="display:none;">
            <div class="d-flex align-items-center gap-2 mt-2 mb-1" style="cursor:pointer;" onclick="toggleQuotedOriginal()">
                <hr style="flex:1;margin:0;"><span class="text-muted small" id="quotedToggleLabel"><i class="bi bi-three-dots"></i> 원본 메일 보기</span><hr style="flex:1;margin:0;">
            </div>
            <div id="quotedContent" style="display:none;border-left:3px solid var(--bs-border-color);padding:8px 12px;margin-top:4px;font-size:13px;max-height:300px;overflow-y:auto;background:var(--bs-tertiary-bg);border-radius:4px;"></div>
        </div>

        <!-- 서명 미리보기 -->
        <div class="signature-preview" id="signaturePreview" style="display:none;"></div>

        <input type="hidden" id="composeInReplyTo" value="">
        <input type="hidden" id="composeReferences" value="">
        <input type="hidden" id="composeQuotedHtml" value="">
        <input type="file" id="attachInput" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" style="display:none;" onchange="handleAttachFiles(this)">
    </div>
    <div class="compose-footer">
        <div class="d-flex gap-1 align-items-center flex-wrap">
            <button class="btn btn-primary btn-sm" onclick="sendMail()">
                <i class="bi bi-send me-1"></i> 보내기
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('attachInput').click()" title="파일 첨부">
                <i class="bi bi-paperclip"></i>
            </button>
            <div class="template-dropdown">
                <button class="btn btn-outline-secondary btn-sm" onclick="toggleTemplateMenu()" title="상용구 삽입">
                    <i class="bi bi-card-text"></i>
                </button>
                <div class="template-menu" id="templateMenu">
                    <div class="template-search">
                        <input type="text" id="templateSearchInput" placeholder="상용구 검색..." oninput="filterTemplateMenu(this.value)">
                    </div>
                    <div id="templateMenuList"></div>
                    <div class="template-menu-footer">
                        <button class="btn btn-sm btn-link text-primary p-0" onclick="openTemplateManager()">
                            <i class="bi bi-gear me-1"></i>상용구 관리
                        </button>
                    </div>
                </div>
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="toggleCc()" title="CC 추가">
                <i class="bi bi-people"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="openSignatureSettings()" title="서명 설정">
                <i class="bi bi-pen"></i>
            </button>
            <div class="form-check form-check-inline ms-2 mb-0" style="font-size:12px;">
                <input class="form-check-input" type="checkbox" id="includeSigCheck" checked>
                <label class="form-check-label" for="includeSigCheck">서명 포함</label>
            </div>
        </div>
        <button class="btn btn-outline-danger btn-sm" onclick="closeCompose()">
            <i class="bi bi-trash"></i> 취소
        </button>
    </div>
</div>

<!-- 서명 설정 모달 -->
<div class="modal fade" id="signatureModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-pen me-1"></i> 메일 서명 설정</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label small">계정 선택</label>
                    <select class="form-select form-select-sm" id="sigAccount" onchange="loadSignature()"></select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">서명 HTML (모든 발송 메일 하단에 추가됨)</label>
                    <textarea class="form-control" id="sigContent" rows="10" placeholder="예: <p>서울미녀 관리팀<br>admin@seoulbeauty.app</p><img src='https://...' width='200'>"></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small">미리보기</label>
                    <div class="border rounded p-3 bg-body-secondary" id="sigPreview" style="min-height:60px;font-size:13px;">-</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="saveSignature()"><i class="bi bi-check-lg me-1"></i> 저장</button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- 상용구 관리 모달 -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-card-text me-1"></i> 상용구 관리</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3" id="templateList">
                    <div class="text-muted small text-center py-3">등록된 상용구가 없습니다.</div>
                </div>
                <hr>
                <h6 class="small fw-semibold"><i class="bi bi-plus-circle me-1"></i> 상용구 추가/수정</h6>
                <input type="hidden" id="tmplEditId" value="0">
                <div class="mb-2">
                    <input type="text" class="form-control form-control-sm" id="tmplTitle" placeholder="상용구 제목 (예: 인사말, 안내문)">
                </div>
                <div class="mb-2">
                    <textarea class="form-control" id="tmplContent" rows="6" placeholder="상용구 내용 (HTML 가능)&#10;예: <p>안녕하세요, 서울미녀입니다.</p>"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="saveTemplate()"><i class="bi bi-check-lg me-1"></i> 저장</button>
                    <button class="btn btn-secondary btn-sm" onclick="resetTemplateForm()">초기화</button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- 휴지통 설정 모달 -->
<div class="modal fade" id="trashSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-trash3 me-1"></i> 휴지통 설정</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">자동 삭제 기간</label>
                    <div class="input-group input-group-sm">
                        <input type="number" class="form-control" id="trashPurgeDays" min="1" max="365" value="30">
                        <span class="input-group-text">일</span>
                    </div>
                    <div class="form-text">휴지통 메일이 이 기간 이후 자동 삭제됩니다.</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="trashAutoEnabled" checked>
                    <label class="form-check-label small" for="trashAutoEnabled">자동 삭제 활성화</label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" onclick="saveTrashSettings()"><i class="bi bi-check-lg me-1"></i> 저장</button>
                <button class="btn btn-warning btn-sm" onclick="purgeTrashNow()"><i class="bi bi-trash me-1"></i> 지금 정리</button>
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<?php
$admin_extra_js = <<<'JSEOF'
var currentAccount = 0;
var currentFolder = 'INBOX';
var currentFilter = 'all';
var currentSort = 'date_desc';
var currentPage = 1;
var currentPerPage = parseInt(document.getElementById('perPageSelect').value) || 20;
var currentUid = null;
var accountsData = [];
var currentMailData = null;
var attachedFiles = [];
var cachedContacts = [];

// 아바타 색상
var avatarColors = ['#667eea','#f093fb','#fa709a','#4facfe','#43e97b','#38f9d7','#a18cd1','#fbc2eb','#ff9a9e','#fad0c4'];
function getAvatarColor(str) {
    var hash = 0;
    for (var i = 0; i < (str||'').length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
    return avatarColors[Math.abs(hash) % avatarColors.length];
}
function getInitial(name, email) {
    if (name) return name.charAt(0).toUpperCase();
    if (email) return email.charAt(0).toUpperCase();
    return '?';
}

// 초기화
document.addEventListener('DOMContentLoaded', function() {
    loadAccounts();
});

// 계정 + 폴더 로드
function loadAccounts() {
    fetch('admin_mail.php?ajax=unread_counts')
        .then(r => r.json())
        .then(data => {
            if (data.error) return;
            accountsData = data.accounts;

            var sel = document.getElementById('accountSelect');
            sel.innerHTML = '';
            data.accounts.forEach(function(acc, i) {
                var opt = document.createElement('option');
                opt.value = i;
                opt.textContent = acc.label + ' (' + acc.email + ')';
                opt.selected = (i === currentAccount);
                sel.appendChild(opt);
            });

            // 작성 폼 계정
            var cSel = document.getElementById('composeAccount');
            cSel.innerHTML = '';
            data.accounts.forEach(function(acc, i) {
                var opt = document.createElement('option');
                opt.value = i;
                opt.textContent = acc.email;
                opt.selected = (i === currentAccount);
                cSel.appendChild(opt);
            });

            // 서명 설정 계정
            var sSel = document.getElementById('sigAccount');
            sSel.innerHTML = '';
            data.accounts.forEach(function(acc, i) {
                var opt = document.createElement('option');
                opt.value = i;
                opt.textContent = acc.email;
                sSel.appendChild(opt);
            });

            // 폴더 목록
            var folders = [
                {name: 'INBOX', icon: 'bi-inbox-fill', label: '받은편지함'},
                {name: 'Sent', icon: 'bi-send-fill', label: '보낸편지함'},
                {name: 'Drafts', icon: 'bi-file-earmark-text', label: '임시보관함'},
                {name: 'Junk', icon: 'bi-shield-exclamation', label: '스팸'},
                {name: 'Trash', icon: 'bi-trash3', label: '휴지통'},
                {name: 'Archive', icon: 'bi-archive', label: '보관함'},
            ];
            var acc = data.accounts[currentAccount];
            var fHtml = '';
            folders.forEach(function(f) {
                var isActive = f.name === currentFolder;
                var badge = '';
                if (f.name === 'INBOX' && acc && acc.unseen > 0) {
                    badge = '<span class="folder-badge text-primary">' + acc.unseen + '</span>';
                }
                fHtml += '<div class="folder-item' + (isActive ? ' active' : '') + '" onclick="selectFolder(\'' + f.name + '\')">';
                fHtml += '<span><i class="bi ' + f.icon + ' me-2"></i>' + f.label + '</span>';
                fHtml += badge + '</div>';
            });
            document.getElementById('folderList').innerHTML = fHtml;

            var topBadge = document.getElementById('mailUnreadBadge');
            if (topBadge) {
                topBadge.textContent = data.total_unseen;
                topBadge.style.display = data.total_unseen > 0 ? 'inline-block' : 'none';
            }

            if (!currentUid) loadMailList();
        });
}

function selectAccount(index) {
    currentAccount = parseInt(index);
    currentFolder = 'INBOX';
    currentPage = 1;
    currentUid = null;
    loadAccounts();
    loadMailList();
    showEmptyContent();
}

function selectFolder(folder) {
    currentFolder = folder;
    currentPage = 1;
    currentUid = null;
    loadAccounts();
    loadMailList();
    showEmptyContent();
}

function setFilter(filter) {
    currentFilter = filter;
    currentPage = 1;
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    loadMailList();
}

function showEmptyContent() {
    var isTrash = (currentFolder.toLowerCase() === 'trash');
    if (isTrash) {
        document.getElementById('mailContent').innerHTML =
            '<div class="mail-empty-welcome">' +
            '<div class="welcome-icon" style="background:linear-gradient(135deg,rgba(var(--bs-warning-rgb),0.15),rgba(var(--bs-danger-rgb),0.15));"><i class="bi bi-trash3" style="color:var(--bs-danger);"></i></div>' +
            '<h4>휴지통</h4>' +
            '<p class="text-muted">삭제된 메일이 보관됩니다.<br>설정한 기간이 지나면 자동으로 영구 삭제됩니다.</p>' +
            '<div class="welcome-shortcuts">' +
            '<div class="shortcut-item" onclick="openTrashSettings()"><i class="bi bi-gear"></i><span>설정</span></div>' +
            '<div class="shortcut-item" onclick="purgeTrashNow()"><i class="bi bi-trash" style="color:var(--bs-danger);"></i><span>지금 정리</span></div>' +
            '<div class="shortcut-item" onclick="selectFolder(\'INBOX\')"><i class="bi bi-inbox-fill"></i><span>받은편지함</span></div>' +
            '</div>' +
            '<div class="welcome-stats" id="welcomeStats"></div>' +
            '</div>';
    } else {
        document.getElementById('mailContent').innerHTML =
            '<div class="mail-empty-welcome">' +
            '<div class="welcome-icon"><i class="bi bi-envelope-paper"></i></div>' +
            '<h4>Seoul Beauty Mail</h4>' +
            '<p class="text-muted">왼쪽 목록에서 메일을 선택하여 읽거나<br>편지쓰기 버튼으로 새 메일을 작성하세요</p>' +
            '<div class="welcome-shortcuts">' +
            '<div class="shortcut-item" onclick="openCompose()"><i class="bi bi-pencil-square"></i><span>편지쓰기</span></div>' +
            '<div class="shortcut-item" onclick="loadMailList()"><i class="bi bi-arrow-clockwise"></i><span>새로고침</span></div>' +
            '<div class="shortcut-item" onclick="setFilter(\'unseen\')"><i class="bi bi-envelope-exclamation"></i><span>안읽음</span></div>' +
            '</div>' +
            '<div class="welcome-stats" id="welcomeStats"></div>' +
            '</div>';
    }
    updateWelcomeStats();
}

var lastMailStats = { total: 0, unseen: 0 };
function updateWelcomeStats() {
    var el = document.getElementById('welcomeStats');
    if (!el) return;
    if (lastMailStats.total > 0) {
        el.innerHTML =
            '<div class="stat-item"><span class="stat-num">' + lastMailStats.total + '</span>전체</div>' +
            '<div class="stat-item"><span class="stat-num">' + lastMailStats.unseen + '</span>안읽음</div>';
    }
}

// ===== 메일 목록 =====
function loadMailList() {
    var search = document.getElementById('mailSearch').value;
    var params = new URLSearchParams({
        ajax: 'list', account: currentAccount, folder: currentFolder,
        search: search, page: currentPage, filter: currentFilter, per_page: currentPerPage, sort: currentSort
    });

    document.getElementById('mailListBody').innerHTML =
        '<div class="p-4 text-center"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    clearSelection();

    fetch('admin_mail.php?' + params)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                document.getElementById('mailListBody').innerHTML =
                    '<div class="p-3 text-danger small">' + data.error + '</div>';
                return;
            }

            if (data.emails.length === 0) {
                document.getElementById('mailListBody').innerHTML =
                    '<div class="mail-empty py-5"><i class="bi bi-inbox" style="font-size:40px;"></i><span>메일이 없습니다</span></div>';
                document.getElementById('mailCount').textContent = '0건';
                document.getElementById('mailPagination').innerHTML = '';
                return;
            }

            var html = '';
            // 휴지통 안내 바
            if (isTrashFolder()) {
                html += '<div class="trash-info-bar">';
                html += '<span><i class="bi bi-info-circle me-1"></i>삭제된 메일은 설정 기간 후 자동 삭제됩니다.</span>';
                html += '<div class="d-flex gap-1">';
                html += '<button class="btn btn-outline-warning btn-sm" style="font-size:11px;padding:1px 6px;" onclick="openTrashSettings()"><i class="bi bi-gear"></i> 설정</button>';
                html += '<button class="btn btn-outline-danger btn-sm" style="font-size:11px;padding:1px 6px;" onclick="emptyTrash()"><i class="bi bi-trash"></i> 비우기</button>';
                html += '</div></div>';
            }
            data.emails.forEach(function(mail) {
                var isActive = mail.uid === currentUid;
                var cls = 'mail-item' + (mail.seen ? '' : ' unread') + (isActive ? ' active' : '');
                var senderName = mail.from_name || mail.from_email.split('@')[0];
                var color = getAvatarColor(mail.from_email);
                var initial = getInitial(mail.from_name, mail.from_email);
                var starCls = mail.flagged ? 'mail-star flagged' : 'mail-star';

                html += '<div class="' + cls + '" data-uid="' + mail.uid + '">';
                html += '<input type="checkbox" class="form-check-input mail-check" value="' + mail.uid + '" onchange="updateBulkBar()" onclick="event.stopPropagation()">';
                html += '<div class="mail-avatar" style="background:' + color + '" onclick="readMail(' + mail.uid + ')">' + initial + '</div>';
                html += '<div class="mail-info" onclick="readMail(' + mail.uid + ')">';
                html += '<div class="mail-from">';
                html += mail.seen ? '<i class="bi bi-envelope-open mail-seen-badge" title="읽음"></i> ' : '<i class="bi bi-envelope-fill mail-seen-badge" title="안읽음"></i> ';
                html += escHtml(senderName);
                if (mail.reply_to) html += ' <i class="bi bi-reply-fill" style="color:#0dcaf0;font-size:11px;" title="답장: ' + escHtml(mail.reply_to) + '"></i>';
                html += '</div>';
                html += '<div class="mail-subject">' + escHtml(mail.subject || '(제목 없음)') + '</div>';
                html += '</div>';
                html += '<div class="mail-meta">';
                html += '<span class="mail-date">' + formatDate(mail.date) + '</span>';
                html += '<div class="d-flex gap-1 align-items-center">';
                html += '<i class="bi bi-star' + (mail.flagged ? '-fill' : '') + ' ' + starCls + '"></i>';
                html += '<i class="bi bi-trash mail-delete-btn" onclick="event.stopPropagation();quickDelete(' + mail.uid + ')" title="삭제"></i>';
                html += '</div></div>';
                html += '</div>';
            });
            document.getElementById('mailListBody').innerHTML = html;

            var countText = data.total + '건';
            if (data.unseen > 0) countText += ' (안읽음 ' + data.unseen + ')';
            if (data.total_pages > 1) countText += ' | ' + data.page + '/' + data.total_pages + '페이지';
            document.getElementById('mailCount').textContent = countText;
            lastMailStats = { total: data.total, unseen: data.unseen || 0 };
            updateWelcomeStats();

            // 페이지네이션 - 숫자 버튼 (1 2 3...)
            if (data.total_pages > 1) {
                var pgHtml = '<div class="d-flex gap-1 align-items-center flex-wrap">';
                // 이전
                if (data.page > 1) pgHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="goPage(' + (data.page-1) + ')" style="font-size:11px;padding:2px 6px;">&lsaquo;</button>';
                // 페이지 번호
                var startPage = Math.max(1, data.page - 3);
                var endPage = Math.min(data.total_pages, data.page + 3);
                if (startPage > 1) pgHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="goPage(1)" style="font-size:11px;padding:2px 8px;">1</button>';
                if (startPage > 2) pgHtml += '<span style="font-size:11px;color:var(--bs-secondary-color);">...</span>';
                for (var p = startPage; p <= endPage; p++) {
                    if (p === data.page) {
                        pgHtml += '<button class="btn btn-sm btn-primary" style="font-size:11px;padding:2px 8px;font-weight:700;" disabled>' + p + '</button>';
                    } else {
                        pgHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="goPage(' + p + ')" style="font-size:11px;padding:2px 8px;">' + p + '</button>';
                    }
                }
                if (endPage < data.total_pages - 1) pgHtml += '<span style="font-size:11px;color:var(--bs-secondary-color);">...</span>';
                if (endPage < data.total_pages) pgHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="goPage(' + data.total_pages + ')" style="font-size:11px;padding:2px 8px;">' + data.total_pages + '</button>';
                // 다음
                if (data.page < data.total_pages) pgHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="goPage(' + (data.page+1) + ')" style="font-size:11px;padding:2px 6px;">&rsaquo;</button>';
                pgHtml += '</div>';
                document.getElementById('mailPagination').innerHTML = pgHtml;
            } else {
                document.getElementById('mailPagination').innerHTML = '';
            }
        })
        .catch(function(err) {
            document.getElementById('mailListBody').innerHTML =
                '<div class="p-3 text-danger small">오류: ' + err.message + '</div>';
        });
}

// ===== 체크박스 / 일괄 처리 =====
function getSelectedUids() {
    var uids = [];
    document.querySelectorAll('.mail-check:checked').forEach(function(cb) { uids.push(cb.value); });
    return uids;
}

function updateBulkBar() {
    var uids = getSelectedUids();
    var cnt = uids.length;
    var delBtn = document.getElementById('bulkDeleteBtn');
    var badge = document.getElementById('bulkCount');
    var readBtn = document.getElementById('bulkReadBtn');
    var unreadBtn = document.getElementById('bulkUnreadBtn');
    if (cnt > 0) {
        delBtn.disabled = false;
        badge.textContent = cnt;
        badge.style.display = 'inline';
        readBtn.style.display = '';
        unreadBtn.style.display = '';
    } else {
        delBtn.disabled = true;
        badge.style.display = 'none';
        readBtn.style.display = 'none';
        unreadBtn.style.display = 'none';
    }
}

function selectAllMails() {
    document.querySelectorAll('.mail-check').forEach(function(cb) { cb.checked = true; });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.mail-check').forEach(function(cb) { cb.checked = false; });
    updateBulkBar();
}

function isTrashFolder() { return currentFolder.toLowerCase() === 'trash'; }

function bulkDelete() {
    var uids = getSelectedUids();
    if (uids.length === 0) return;
    var msg = isTrashFolder()
        ? uids.length + '건을 영구 삭제하시겠습니까?\n(복구할 수 없습니다)'
        : uids.length + '건을 휴지통으로 이동하시겠습니까?';
    if (!confirm(msg)) return;

    fetch('admin_mail.php?ajax=bulk_delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentAccount + '&folder=' + encodeURIComponent(currentFolder) + '&uids=' + uids.join(',')
    })
    .then(r => r.json())
    .then(function(data) {
        if (data.success) {
            showToast(isTrashFolder() ? uids.length + '건 영구 삭제 완료' : uids.length + '건 휴지통 이동 완료');
            currentUid = null;
            showEmptyContent();
            loadMailList();
            loadAccounts();
        } else {
            alert('삭제 실패: ' + (data.error || ''));
        }
    });
}

function bulkSeen(seen) {
    var uids = getSelectedUids();
    if (uids.length === 0) return;

    fetch('admin_mail.php?ajax=bulk_seen', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentAccount + '&folder=' + encodeURIComponent(currentFolder) + '&uids=' + uids.join(',') + '&seen=' + (seen ? '1' : '0')
    })
    .then(r => r.json())
    .then(function() {
        showToast(seen ? '읽음 표시 완료' : '안읽음 표시 완료');
        clearSelection();
        loadMailList();
        loadAccounts();
    });
}

// 페이지당 표시 수 변경
function changePerPage(val) {
    currentPerPage = parseInt(val);
    currentPage = 1;
    fetch('admin_mail.php?set_per_page=' + val);
    loadMailList();
}

// ===== 메일 읽기 =====
function readMail(uid) {
    currentUid = uid;
    document.querySelectorAll('.mail-item').forEach(function(el) {
        el.classList.toggle('active', parseInt(el.dataset.uid) === uid);
        if (parseInt(el.dataset.uid) === uid) el.classList.remove('unread');
    });

    document.getElementById('mailContent').innerHTML =
        '<div class="mail-empty"><div class="spinner-border text-primary"></div></div>';

    fetch('admin_mail.php?' + new URLSearchParams({
        ajax: 'read', account: currentAccount, uid: uid, folder: currentFolder
    }))
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            document.getElementById('mailContent').innerHTML =
                '<div class="p-3 text-danger">' + data.error + '</div>';
            return;
        }
        currentMailData = data;
        renderMailView(data);
        loadAccounts();
    })
    .catch(function(err) {
        document.getElementById('mailContent').innerHTML =
            '<div class="p-3 text-danger">오류: ' + err.message + '</div>';
    });
}

function renderMailView(data) {
    var senderName = data.from_name || data.from_email.split('@')[0];
    var color = getAvatarColor(data.from_email);
    var initial = getInitial(data.from_name, data.from_email);

    var html = '';

    // 헤더
    html += '<div class="mail-view-header">';
    html += '<div class="mail-view-subject">' + escHtml(data.subject) + '</div>';
    html += '<div class="mail-view-meta">';
    html += '<div class="mail-view-avatar" style="background:' + color + '">' + initial + '</div>';
    html += '<div class="mail-view-sender">';
    html += '<div><strong>' + escHtml(senderName) + '</strong>';
    if (data.from_name) html += ' <span class="text-muted small">&lt;' + escHtml(data.from_email) + '&gt;</span>';
    html += '</div>';
    html += '<div class="small text-muted">받는사람: ' + escHtml((data.to || []).join(', ')) + '</div>';
    if (data.reply_to && data.reply_to !== data.from_email) {
        html += '<div class="small" style="color:#0dcaf0;font-weight:bold;"><i class="bi bi-reply-fill me-1"></i>답장 주소: ' + escHtml(data.reply_to_name ? data.reply_to_name + ' <' + data.reply_to + '>' : data.reply_to) + '</div>';
    }
    if (data.cc && data.cc.length > 0) {
        html += '<div class="small text-muted">참조: ' + escHtml(data.cc.join(', ')) + '</div>';
    }
    html += '<div class="small text-muted">' + formatDateFull(data.date) + '</div>';
    html += '</div>';

    // 액션 버튼
    html += '<div class="mail-view-actions">';
    html += '<button class="btn btn-sm btn-outline-primary" onclick="replyMail()" title="답장"><i class="bi bi-reply"></i> 답장</button>';
    html += '<button class="btn btn-sm btn-outline-secondary" onclick="forwardMail()" title="전달"><i class="bi bi-forward"></i></button>';
    html += '<button class="btn btn-sm btn-outline-secondary" onclick="toggleSeen(' + data.uid + ', false)" title="안읽음"><i class="bi bi-envelope"></i></button>';
    html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteMail(' + data.uid + ')" title="삭제"><i class="bi bi-trash"></i></button>';
    html += '</div>';
    html += '</div></div>';

    // 첨부파일
    if (data.attachments && data.attachments.length > 0) {
        html += '<div class="mail-view-attachments">';
        html += '<div class="small fw-semibold mb-1"><i class="bi bi-paperclip me-1"></i>첨부파일 (' + data.attachments.length + ')</div>';
        data.attachments.forEach(function(a) {
            html += '<span class="badge bg-body-secondary text-body me-1 p-2">';
            html += '<i class="bi bi-file-earmark me-1"></i>' + escHtml(a.filename);
            html += ' <span class="text-muted">(' + formatSize(a.size) + ')</span>';
            html += '</span>';
        });
        html += '</div>';
    }

    // 본문
    html += '<div class="mail-view-body">';
    if (data.body_html) {
        // 외부 발신자 HTML 은 스크립트가 실행되지 않는 sandbox iframe 안에서만 렌더링한다.
        html += '<iframe class="mail-html-frame" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="메일 본문"></iframe>';
    } else if (data.body_text) {
        html += '<pre>' + escHtml(data.body_text) + '</pre>';
    } else {
        html += '<div class="text-muted">(본문 없음)</div>';
    }
    html += '</div>';

    // 하단 답장 버튼
    html += '<div style="border-top:1px solid var(--bs-border-color);padding:16px 20px;background:var(--bs-body-bg);">';
    html += '<div class="d-flex gap-2">';
    html += '<button class="btn btn-outline-primary btn-sm" onclick="replyMail()"><i class="bi bi-reply me-1"></i>답장</button>';
    html += '<button class="btn btn-outline-secondary btn-sm" onclick="forwardMail()"><i class="bi bi-forward me-1"></i>전달</button>';
    html += '</div></div>';

    document.getElementById('mailContent').innerHTML = html;
    if (data.body_html) {
        mountMailHtmlFrame(document.querySelector('#mailContent .mail-html-frame'), data.body_html);
    }
}

// ===== 답장/전달 =====
function getReplyToAddress(d) {
    // Reply-To가 From과 다르면 Reply-To 사용 (실제 문의자 이메일)
    // imap_headerinfo는 Reply-To 헤더가 없으면 From을 기본값으로 반환
    if (d.reply_to && d.reply_to !== d.from_email) {
        return d.reply_to;
    }
    // Reply-To가 없거나 From과 같으면 From 사용
    return d.from_email;
}

function replyMail() {
    if (!currentMailData) return;
    var d = currentMailData;
    resetCompose();
    openCompose();

    var replyAddr = getReplyToAddress(d);
    var replyName = (d.reply_to && d.reply_to !== d.from_email) ? (d.reply_to_name || '') : (d.from_name || '');

    // 답장 대상 표시 (Gmail 스타일)
    var titleEl = document.getElementById('composeTitle');
    if (replyName && replyName !== replyAddr) {
        titleEl.innerHTML = '답장 <span style="font-weight:normal;font-size:13px;color:#0dcaf0;margin-left:8px;">' + escHtml(replyName) + ' &lt;' + escHtml(replyAddr) + '&gt;</span>';
    } else {
        titleEl.innerHTML = '답장 <span style="font-weight:normal;font-size:13px;color:#0dcaf0;margin-left:8px;">' + escHtml(replyAddr) + '</span>';
    }

    document.getElementById('composeTo').value = replyAddr;
    document.getElementById('composeSubject').value =
        d.subject.match(/^Re:/i) ? d.subject : 'Re: ' + d.subject;
    document.getElementById('composeInReplyTo').value = d.message_id || '';
    document.getElementById('composeReferences').value = (d.references || '') + ' ' + (d.message_id || '');

    // 새 텍스트 영역은 비워두고 커서 포커스
    document.getElementById('composeBodyText').value = '';
    document.getElementById('composeBodyText').focus();

    // 원본 메일 HTML 인용 (이미지 포함)
    var quotedHtml = buildQuotedHtml(d, '답장');
    setQuotedOriginal(quotedHtml);
}

function forwardMail() {
    if (!currentMailData) return;
    var d = currentMailData;
    resetCompose();
    openCompose();
    document.getElementById('composeTitle').textContent = '전달';
    document.getElementById('composeTo').value = '';
    document.getElementById('composeSubject').value =
        d.subject.match(/^Fwd:/i) ? d.subject : 'Fwd: ' + d.subject;

    // 원본 메일 HTML 인용 (이미지 포함)
    var quotedHtml = buildQuotedHtml(d, '전달');
    setQuotedOriginal(quotedHtml);
    document.getElementById('composeTo').focus();
}

// 원본 메일 HTML 인용문 생성
function buildQuotedHtml(d, type) {
    var html = '<div style="color:#888;font-size:12px;margin-bottom:8px;">';
    if (type === '답장') {
        html += '--- 원본 메일 ---<br>';
    } else {
        html += '---------- 전달된 메일 ----------<br>';
    }
    html += '<b>보낸사람:</b> ' + escHtml(d.from_name ? d.from_name + ' <' + d.from_email + '>' : d.from_email) + '<br>';
    html += '<b>날짜:</b> ' + formatDateFull(d.date) + '<br>';
    html += '<b>제목:</b> ' + escHtml(d.subject) + '<br>';
    if (type === '전달') {
        html += '<b>받는사람:</b> ' + escHtml((d.to || []).join(', ')) + '<br>';
    }
    html += '</div>';

    // 원본 본문 (HTML이 있으면 HTML, 없으면 텍스트)
    if (d.body_html) {
        html += d.body_html;
    } else if (d.body_text) {
        html += '<pre style="white-space:pre-wrap;font-family:inherit;">' + escHtml(d.body_text) + '</pre>';
    }
    return html;
}

// 인용 원본 표시
function setQuotedOriginal(html) {
    if (!html) {
        document.getElementById('quotedOriginal').style.display = 'none';
        document.getElementById('composeQuotedHtml').value = '';
        return;
    }
    document.getElementById('composeQuotedHtml').value = html;
    // 인용 원본(외부 HTML 포함)도 sandbox iframe 으로만 표시한다. 전송용 원본은 composeQuotedHtml 에 그대로 둔다.
    var quotedBox = document.getElementById('quotedContent');
    quotedBox.textContent = '';
    var quotedFrame = document.createElement('iframe');
    quotedFrame.className = 'mail-html-frame';
    quotedFrame.setAttribute('sandbox', 'allow-same-origin allow-popups allow-popups-to-escape-sandbox');
    quotedFrame.setAttribute('referrerpolicy', 'no-referrer');
    quotedFrame.title = '원본 메일';
    quotedBox.appendChild(quotedFrame);
    mountMailHtmlFrame(quotedFrame, html);
    document.getElementById('quotedOriginal').style.display = 'block';
    // 기본으로 펼침
    document.getElementById('quotedContent').style.display = 'block';
    document.getElementById('quotedToggleLabel').innerHTML = '<i class="bi bi-chevron-up"></i> 원본 메일 접기';
}

function toggleQuotedOriginal() {
    var content = document.getElementById('quotedContent');
    var label = document.getElementById('quotedToggleLabel');
    if (content.style.display === 'none') {
        content.style.display = 'block';
        label.innerHTML = '<i class="bi bi-chevron-up"></i> 원본 메일 접기';
    } else {
        content.style.display = 'none';
        label.innerHTML = '<i class="bi bi-three-dots"></i> 원본 메일 보기';
    }
}

// ===== 메일 보내기 (FormData - 첨부파일 지원) =====
function sendMail() {
    var to = document.getElementById('composeTo').value.trim();
    var subject = document.getElementById('composeSubject').value.trim();
    if (!to) { alert('받는사람을 입력하세요.'); return; }
    if (!subject) { alert('제목을 입력하세요.'); return; }

    var sendBtn = document.querySelector('.compose-footer .btn-primary');
    sendBtn.disabled = true;
    sendBtn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> 전송중...';

    var formData = new FormData();
    formData.append('account', document.getElementById('composeAccount').value);
    formData.append('to', to);
    formData.append('subject', subject);
    // 새 텍스트 + 인용 원본 HTML 결합
    var newText = document.getElementById('composeBodyText').value.replace(/\n/g, '<br>');
    var quotedHtml = document.getElementById('composeQuotedHtml').value;
    var fullBody = newText;
    if (quotedHtml) {
        fullBody += '<br><br><blockquote style="border-left:3px solid #ccc;padding:8px 12px;margin:8px 0;color:#666;">' + quotedHtml + '</blockquote>';
    }
    formData.append('body', fullBody);
    formData.append('cc', document.getElementById('composeCc').value.trim());
    formData.append('in_reply_to', document.getElementById('composeInReplyTo').value);
    formData.append('references', document.getElementById('composeReferences').value);
    formData.append('include_sig', document.getElementById('includeSigCheck').checked ? '1' : '0');

    // 첨부파일 추가
    attachedFiles.forEach(function(f) {
        formData.append('attachments[]', f);
    });

    fetch('admin_mail.php?ajax=send', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send me-1"></i> 보내기';

        if (data.success) {
            closeCompose();
            showToast('메일이 전송되었습니다.');
            if (currentFolder === 'Sent') loadMailList();
            loadAccounts();
        } else {
            alert('전송 실패: ' + (data.error || '알 수 없는 오류'));
        }
    })
    .catch(function(err) {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send me-1"></i> 보내기';
        alert('전송 오류: ' + err.message);
    });
}

// ===== 첨부파일 =====
function handleAttachFiles(input) {
    var files = input.files;
    for (var i = 0; i < files.length; i++) {
        if (files[i].size > 10 * 1024 * 1024) {
            alert(files[i].name + ': 10MB 초과');
            continue;
        }
        attachedFiles.push(files[i]);
    }
    input.value = '';
    renderAttachList();
}

function renderAttachList() {
    var html = '';
    attachedFiles.forEach(function(f, i) {
        var isImg = f.type.startsWith('image/');
        html += '<span class="attach-item">';
        if (isImg) {
            var url = URL.createObjectURL(f);
            html += '<img src="' + url + '" alt="">';
        } else {
            html += '<i class="bi bi-file-earmark"></i>';
        }
        html += escHtml(f.name) + ' <span class="text-muted">(' + formatSize(f.size) + ')</span>';
        html += ' <i class="bi bi-x-circle-fill remove-attach" onclick="removeAttach(' + i + ')"></i>';
        html += '</span>';
    });
    document.getElementById('attachList').innerHTML = html;
}

function removeAttach(index) {
    attachedFiles.splice(index, 1);
    renderAttachList();
}

// ===== 편지쓰기 UI =====
function resetCompose() {
    document.getElementById('composeTo').value = '';
    document.getElementById('composeSubject').value = '';
    document.getElementById('composeBodyText').value = '';
    document.getElementById('composeInReplyTo').value = '';
    document.getElementById('composeReferences').value = '';
    document.getElementById('composeQuotedHtml').value = '';
    document.getElementById('composeCc').value = '';
    document.getElementById('composeCc').style.display = 'none';
    document.getElementById('quotedOriginal').style.display = 'none';
    document.getElementById('quotedContent').innerHTML = '';
    attachedFiles = [];
    document.getElementById('attachList').innerHTML = '';
}

function openCompose() {
    document.getElementById('composeOverlay').style.display = 'flex';
    document.getElementById('composeBody').style.display = 'flex';
    document.getElementById('composeTitle').textContent = '새 메일';
    document.getElementById('composeAccount').value = currentAccount;
    loadComposeSignature();
    if (cachedContacts.length === 0) loadContacts();
}
function closeCompose() {
    document.getElementById('composeOverlay').style.display = 'none';
    document.getElementById('composeOverlay').classList.remove('fullscreen');
    resetCompose();
}
function toggleCompose() {
    var body = document.getElementById('composeBody');
    body.style.display = body.style.display === 'none' ? 'flex' : 'none';
}
function minimizeCompose() {
    document.getElementById('composeBody').style.display = 'none';
}
function toggleFullscreen() {
    var overlay = document.getElementById('composeOverlay');
    overlay.classList.toggle('fullscreen');
    var icon = document.getElementById('fullscreenIcon');
    icon.className = overlay.classList.contains('fullscreen') ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
}
function toggleCc() {
    var cc = document.getElementById('composeCc');
    cc.style.display = cc.style.display === 'none' ? 'block' : 'none';
    if (cc.style.display === 'block') cc.focus();
}

// ===== 서명 =====
function loadComposeSignature() {
    var sigCheck = document.getElementById('includeSigCheck');
    var preview = document.getElementById('signaturePreview');
    if (!sigCheck || !sigCheck.checked) {
        if (preview) preview.style.display = 'none';
        return;
    }
    var accIdx = document.getElementById('composeAccount').value;
    fetch('admin_mail.php?ajax=get_signature&account=' + accIdx)
        .then(r => r.json())
        .then(data => {
            if (data.signature) {
                preview.innerHTML = data.signature;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
        })
        .catch(function() {});
}

function openSignatureSettings() {
    loadSignature();
    new bootstrap.Modal(document.getElementById('signatureModal')).show();
}

function loadSignature() {
    var accIdx = document.getElementById('sigAccount').value;
    fetch('admin_mail.php?ajax=get_signature&account=' + accIdx)
        .then(r => r.json())
        .then(data => {
            document.getElementById('sigContent').value = data.signature || '';
            document.getElementById('sigPreview').innerHTML = data.signature || '<span class="text-muted">서명이 설정되지 않았습니다.</span>';
        });
}

// 서명 편집 시 미리보기
document.addEventListener('DOMContentLoaded', function() {
    var sigEl = document.getElementById('sigContent');
    if (sigEl) {
        sigEl.addEventListener('input', function() {
            document.getElementById('sigPreview').innerHTML = this.value || '<span class="text-muted">-</span>';
        });
    }
});

function saveSignature() {
    var accIdx = document.getElementById('sigAccount').value;
    var html = document.getElementById('sigContent').value;

    fetch('admin_mail.php?ajax=save_signature', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + accIdx + '&signature=' + encodeURIComponent(html)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('서명이 저장되었습니다.');
            bootstrap.Modal.getInstance(document.getElementById('signatureModal')).hide();
            loadComposeSignature();
        }
    });
}

// ===== 삭제/읽음 =====
function deleteMail(uid) {
    var msg = isTrashFolder() ? '이 메일을 영구 삭제하시겠습니까?\n(복구할 수 없습니다)' : '이 메일을 휴지통으로 이동하시겠습니까?';
    if (!confirm(msg)) return;
    fetch('admin_mail.php?ajax=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentAccount + '&uid=' + uid + '&folder=' + encodeURIComponent(currentFolder)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            currentUid = null;
            showToast(isTrashFolder() ? '영구 삭제 완료' : '휴지통으로 이동');
            showEmptyContent();
            loadMailList();
            loadAccounts();
        }
    });
}

function toggleSeen(uid, seen) {
    fetch('admin_mail.php?ajax=toggle_seen', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentAccount + '&uid=' + uid + '&folder=' + encodeURIComponent(currentFolder) + '&seen=' + (seen ? '1' : '0')
    })
    .then(r => r.json())
    .then(function() {
        loadMailList();
        loadAccounts();
        if (!seen) showToast('안읽음 표시');
    });
}

function goPage(page) { currentPage = page; loadMailList(); }

// 정렬 변경
function changeSort(val) {
    currentSort = val;
    currentPage = 1;
    loadMailList();
}

// 목록에서 바로 삭제
function quickDelete(uid) {
    var msg = isTrashFolder() ? '영구 삭제하시겠습니까?' : '휴지통으로 이동하시겠습니까?';
    if (!confirm(msg)) return;
    fetch('admin_mail.php?ajax=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentAccount + '&uid=' + uid + '&folder=' + encodeURIComponent(currentFolder)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (currentUid === uid) { currentUid = null; showEmptyContent(); }
            showToast(isTrashFolder() ? '영구 삭제 완료' : '휴지통 이동');
            loadMailList();
            loadAccounts();
        }
    });
}

// ===== 유틸 =====
function escHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
function stripHtml(html) {
    // DOMParser 문서는 스크립트·이벤트 핸들러를 실행하지 않는다 (detached div 의 innerHTML 은 img onerror 등이 실행됨).
    if (!html) return '';
    var doc = new DOMParser().parseFromString(String(html), 'text/html');
    return (doc.body && doc.body.textContent) || '';
}
// 신뢰할 수 없는 메일 HTML 을 sandbox iframe(allow-scripts 없음)에 넣고 높이를 내용에 맞춘다.
function mountMailHtmlFrame(frame, bodyHtml) {
    if (!frame) return;
    var head = '<meta charset="utf-8">'
        + '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: https:; style-src \'unsafe-inline\'; font-src data: https:">'
        + '<meta name="referrer" content="no-referrer">'
        + '<base target="_blank">'
        + '<style>html,body{margin:0;padding:0;background:#fff;color:#212529;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-size:14px;line-height:1.7;word-break:break-word;}img{max-width:100%;height:auto;}</style>';
    var fit = function () {
        try {
            var d = frame.contentDocument;
            if (d && d.documentElement) frame.style.height = (d.documentElement.scrollHeight + 4) + 'px';
        } catch (e) {}
    };
    frame.addEventListener('load', fit);
    window.addEventListener('resize', fit);
    frame.srcdoc = '<!doctype html><html><head>' + head + '</head><body>' + String(bodyHtml) + '</body></html>';
}
function formatDate(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    var now = new Date();
    if (d.toDateString() === now.toDateString()) {
        return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
    }
    if (d.getFullYear() === now.getFullYear()) {
        return (d.getMonth()+1) + '월 ' + d.getDate() + '일';
    }
    return d.getFullYear() + '.' + (d.getMonth()+1) + '.' + d.getDate();
}
function formatDateFull(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    var weekdays = ['일','월','화','수','목','금','토'];
    return d.getFullYear() + '년 ' + (d.getMonth()+1) + '월 ' + d.getDate() + '일 (' + weekdays[d.getDay()] + ') '
        + d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}
function formatSize(bytes) {
    if (bytes < 1024) return bytes + 'B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + 'KB';
    return (bytes/1048576).toFixed(1) + 'MB';
}
function showToast(msg) {
    var toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-3 px-4 py-2 bg-dark text-white rounded-pill shadow';
    toast.style.zIndex = '9999';
    toast.style.fontSize = '13px';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}

// ===== 상용구 (템플릿) =====
var cachedTemplates = [];
function toggleTemplateMenu() {
    var menu = document.getElementById('templateMenu');
    if (menu.classList.contains('show')) {
        menu.classList.remove('show');
        return;
    }
    document.getElementById('templateSearchInput').value = '';
    // 목록 로드
    fetch('admin_mail.php?ajax=get_templates')
        .then(r => r.json())
        .then(data => {
            cachedTemplates = data.templates || [];
            renderTemplateMenuItems(cachedTemplates);
            menu.classList.add('show');
            document.getElementById('templateSearchInput').focus();
        });
}

function renderTemplateMenuItems(templates) {
    var list = document.getElementById('templateMenuList');
    if (templates.length === 0) {
        list.innerHTML = '<div class="p-3 text-muted small text-center">등록된 상용구가 없습니다.<br>관리에서 추가하세요.</div>';
    } else {
        var html = '';
        templates.forEach(function(t) {
            var preview = stripHtml(t.content).substring(0, 50);
            html += '<div class="template-menu-item" onclick="insertTemplate(' + t.id + ')">';
            html += '<div class="tmpl-title">' + escHtml(t.title) + '</div>';
            html += '<div class="tmpl-preview">' + escHtml(preview) + '</div>';
            html += '</div>';
        });
        list.innerHTML = html;
    }
}

function filterTemplateMenu(query) {
    query = query.toLowerCase().trim();
    if (!query) {
        renderTemplateMenuItems(cachedTemplates);
        return;
    }
    var filtered = cachedTemplates.filter(function(t) {
        return t.title.toLowerCase().indexOf(query) !== -1 || stripHtml(t.content).toLowerCase().indexOf(query) !== -1;
    });
    renderTemplateMenuItems(filtered);
}

function insertTemplate(id) {
    document.getElementById('templateMenu').classList.remove('show');
    fetch('admin_mail.php?ajax=get_templates')
        .then(r => r.json())
        .then(data => {
            var t = (data.templates || []).find(function(x) { return x.id === id; });
            if (!t) return;
            var textarea = document.getElementById('composeBodyText');
            var pos = textarea.selectionStart || 0;
            var before = textarea.value.substring(0, pos);
            var after = textarea.value.substring(pos);
            // HTML -> 개행 변환 후 삽입
            var text = stripHtml(t.content.replace(/<br\s*\/?>/gi, '\n').replace(/<\/p>/gi, '\n'));
            textarea.value = before + text + after;
            textarea.focus();
            textarea.setSelectionRange(pos + text.length, pos + text.length);
            showToast('"' + t.title + '" 삽입 완료');
        });
}

// 상용구 메뉴 외부 클릭 닫기
document.addEventListener('click', function(e) {
    var menu = document.getElementById('templateMenu');
    if (menu && menu.classList.contains('show')) {
        if (!e.target.closest('.template-dropdown')) {
            menu.classList.remove('show');
        }
    }
});

function openTemplateManager() {
    document.getElementById('templateMenu').classList.remove('show');
    loadTemplateList();
    new bootstrap.Modal(document.getElementById('templateModal')).show();
}

function loadTemplateList() {
    fetch('admin_mail.php?ajax=get_templates')
        .then(r => r.json())
        .then(data => {
            var templates = data.templates || [];
            var el = document.getElementById('templateList');
            if (templates.length === 0) {
                el.innerHTML = '<div class="text-muted small text-center py-3">등록된 상용구가 없습니다.</div>';
                return;
            }
            var html = '<div class="list-group">';
            templates.forEach(function(t) {
                html += '<div class="list-group-item d-flex justify-content-between align-items-start">';
                html += '<div class="me-2" style="flex:1;min-width:0;">';
                html += '<div class="fw-semibold small">' + escHtml(t.title) + '</div>';
                html += '<div class="text-muted small text-truncate">' + escHtml(stripHtml(t.content).substring(0, 80)) + '</div>';
                html += '</div>';
                html += '<div class="d-flex gap-1 flex-shrink-0">';
                html += '<button class="btn btn-sm btn-outline-primary" onclick="editTemplate(' + t.id + ')" title="수정"><i class="bi bi-pencil"></i></button>';
                html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(' + t.id + ')" title="삭제"><i class="bi bi-trash"></i></button>';
                html += '</div></div>';
            });
            html += '</div>';
            el.innerHTML = html;
        });
}

function editTemplate(id) {
    fetch('admin_mail.php?ajax=get_templates')
        .then(r => r.json())
        .then(data => {
            var t = (data.templates || []).find(function(x) { return x.id === id; });
            if (!t) return;
            document.getElementById('tmplEditId').value = t.id;
            document.getElementById('tmplTitle').value = t.title;
            document.getElementById('tmplContent').value = t.content;
            document.getElementById('tmplTitle').focus();
        });
}

function resetTemplateForm() {
    document.getElementById('tmplEditId').value = '0';
    document.getElementById('tmplTitle').value = '';
    document.getElementById('tmplContent').value = '';
}

function saveTemplate() {
    var id = parseInt(document.getElementById('tmplEditId').value) || 0;
    var title = document.getElementById('tmplTitle').value.trim();
    var content = document.getElementById('tmplContent').value;
    if (!title) { alert('제목을 입력하세요.'); return; }

    fetch('admin_mail.php?ajax=save_template', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id + '&title=' + encodeURIComponent(title) + '&content=' + encodeURIComponent(content)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(id ? '상용구 수정 완료' : '상용구 추가 완료');
            resetTemplateForm();
            loadTemplateList();
        } else {
            alert('저장 실패: ' + (data.error || ''));
        }
    });
}

function deleteTemplate(id) {
    if (!confirm('이 상용구를 삭제하시겠습니까?')) return;
    fetch('admin_mail.php?ajax=delete_template', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('상용구 삭제 완료');
            loadTemplateList();
        }
    });
}

// ===== 서명 체크박스 상태 유지 =====
document.addEventListener('DOMContentLoaded', function() {
    var sigCheck = document.getElementById('includeSigCheck');
    if (sigCheck) {
        var saved = localStorage.getItem('mail_include_sig');
        if (saved !== null) sigCheck.checked = saved === '1';
        sigCheck.addEventListener('change', function() {
            localStorage.setItem('mail_include_sig', this.checked ? '1' : '0');
            loadComposeSignature();
        });
    }
});

// ===== 수신자 자동완성 =====
var contactDropdownIdx = -1;

function loadContacts() {
    fetch('admin_mail.php?ajax=get_contacts&account=' + currentAccount)
        .then(r => r.json())
        .then(data => { cachedContacts = data.contacts || []; });
}

document.addEventListener('DOMContentLoaded', function() {
    var toInput = document.getElementById('composeTo');
    var dropdown = document.getElementById('contactDropdown');
    if (!toInput || !dropdown) return;

    toInput.addEventListener('input', function() {
        var query = this.value.trim().toLowerCase();
        if (query.length < 1) { dropdown.classList.remove('show'); return; }
        var filtered = cachedContacts.filter(function(c) {
            return c.email.toLowerCase().indexOf(query) !== -1 || (c.name && c.name.toLowerCase().indexOf(query) !== -1);
        }).slice(0, 8);
        if (filtered.length === 0) { dropdown.classList.remove('show'); return; }
        contactDropdownIdx = -1;
        var html = '';
        filtered.forEach(function(c, i) {
            var color = getAvatarColor(c.email);
            var initial = getInitial(c.name, c.email);
            html += '<div class="contact-dropdown-item" data-idx="' + i + '" data-email="' + escHtml(c.email) + '" onclick="selectContact(this)">';
            html += '<div class="contact-avatar" style="background:' + color + '">' + initial + '</div>';
            html += '<div class="contact-info"><div class="contact-name">' + escHtml(c.name || c.email) + '</div>';
            html += '<div class="contact-email">' + escHtml(c.email) + '</div></div>';
            html += '<span class="contact-count">' + c.count + '회</span>';
            html += '</div>';
        });
        dropdown.innerHTML = html;
        dropdown.classList.add('show');
    });

    toInput.addEventListener('keydown', function(e) {
        var items = dropdown.querySelectorAll('.contact-dropdown-item');
        if (!dropdown.classList.contains('show') || items.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            contactDropdownIdx = Math.min(contactDropdownIdx + 1, items.length - 1);
            items.forEach(function(el, i) { el.classList.toggle('active', i === contactDropdownIdx); });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            contactDropdownIdx = Math.max(contactDropdownIdx - 1, 0);
            items.forEach(function(el, i) { el.classList.toggle('active', i === contactDropdownIdx); });
        } else if (e.key === 'Enter' && contactDropdownIdx >= 0) {
            e.preventDefault();
            items[contactDropdownIdx].click();
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('show');
        }
    });

    toInput.addEventListener('blur', function() {
        setTimeout(function() { dropdown.classList.remove('show'); }, 200);
    });

    toInput.addEventListener('focus', function() {
        if (cachedContacts.length === 0) loadContacts();
        if (this.value.trim().length >= 1) this.dispatchEvent(new Event('input'));
    });
});

function selectContact(el) {
    document.getElementById('composeTo').value = el.dataset.email;
    document.getElementById('contactDropdown').classList.remove('show');
    document.getElementById('composeSubject').focus();
}

// ===== 휴지통 설정 =====
function openTrashSettings() {
    fetch('admin_mail.php?ajax=get_trash_settings')
        .then(r => r.json())
        .then(data => {
            document.getElementById('trashPurgeDays').value = data.auto_purge_days || 30;
            document.getElementById('trashAutoEnabled').checked = data.enabled !== false;
            new bootstrap.Modal(document.getElementById('trashSettingsModal')).show();
        });
}

function saveTrashSettings() {
    var days = parseInt(document.getElementById('trashPurgeDays').value) || 30;
    var enabled = document.getElementById('trashAutoEnabled').checked;
    fetch('admin_mail.php?ajax=save_trash_settings', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'auto_purge_days=' + days + '&enabled=' + (enabled ? '1' : '0')
    })
    .then(r => r.json())
    .then(function(data) {
        if (data.success) {
            showToast('휴지통 설정 저장 완료 (' + days + '일)');
            var modal = bootstrap.Modal.getInstance(document.getElementById('trashSettingsModal'));
            if (modal) modal.hide();
        }
    });
}

function purgeTrashNow() {
    if (!confirm('설정 기간이 지난 휴지통 메일을 영구 삭제하시겠습니까?')) return;
    fetch('admin_mail.php?ajax=purge_trash', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: ''
    })
    .then(r => r.json())
    .then(function(data) {
        if (data.success) {
            showToast(data.deleted > 0 ? data.deleted + '건 영구 삭제 완료' : '삭제할 메일이 없습니다.');
            if (isTrashFolder()) loadMailList();
            loadAccounts();
        }
    });
}

function emptyTrash() {
    if (!confirm('휴지통의 모든 메일을 영구 삭제하시겠습니까?\n(복구할 수 없습니다)')) return;
    // 현재 보이는 모든 메일 UID 수집
    var allUids = [];
    document.querySelectorAll('.mail-check').forEach(function(cb) { allUids.push(cb.value); });
    if (allUids.length === 0) { showToast('삭제할 메일이 없습니다.'); return; }
    fetch('admin_mail.php?ajax=bulk_delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'account=' + currentAccount + '&folder=Trash&uids=' + allUids.join(',')
    })
    .then(r => r.json())
    .then(function(data) {
        if (data.success) {
            showToast(allUids.length + '건 영구 삭제 완료');
            currentUid = null;
            showEmptyContent();
            loadMailList();
            loadAccounts();
        }
    });
}

// 페이지 로드 시 연락처 미리 로드
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(loadContacts, 2000);
});
JSEOF;

include __DIR__ . '/includes/admin_footer.php';
?>
