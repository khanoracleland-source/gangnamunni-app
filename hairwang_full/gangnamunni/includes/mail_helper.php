<?php
/**
 * 메일 관리 헬퍼 - IMAP/SMTP 연동
 * Mailcow (mail.seoulbeauty.app) 메일 서버 연결
 *
 * @created 2026-02-11
 */

// 메일 계정 기본 설정
$_MAIL_ACCOUNTS_DEFAULT = [
    [
        'email' => 'admin@seoulbeauty.app',
        'password' => 'SeoulB3auty!2026',
        'name' => 'Seoul Beauty Admin',
        'label' => '관리자',
        'color' => '#3b82f6',
    ],
    [
        'email' => 'info@seoulbeauty.app',
        'password' => 'SeoulB3auty!2026',
        'name' => 'Seoul Beauty Info',
        'label' => '문의',
        'color' => '#10b981',
    ],
];

// JSON 오버라이드 적용 (이름, 라벨, 색상 변경 가능)
$_mail_overrides_file = __DIR__ . '/../data/mail_accounts.json';
$_mail_overrides = [];
if (file_exists($_mail_overrides_file)) {
    $_json = @file_get_contents($_mail_overrides_file);
    if ($_json !== false) {
        $_decoded = @json_decode($_json, true);
        if (is_array($_decoded)) $_mail_overrides = $_decoded;
    }
}
foreach ($_MAIL_ACCOUNTS_DEFAULT as $i => &$_acc) {
    $key = (string)$i;
    if (isset($_mail_overrides[$key])) {
        $ov = $_mail_overrides[$key];
        if (!empty($ov['name']))  $_acc['name']  = $ov['name'];
        if (!empty($ov['label'])) $_acc['label'] = $ov['label'];
        if (!empty($ov['color'])) $_acc['color'] = $ov['color'];
    }
}
unset($_acc);

define('MAIL_ACCOUNTS', $_MAIL_ACCOUNTS_DEFAULT);

define('MAIL_IMAP_HOST', '{mail.seoulbeauty.app:993/imap/ssl/novalidate-cert}');
define('MAIL_SMTP_HOST', 'mail.seoulbeauty.app');
define('MAIL_SMTP_PORT', 587);

/**
 * IMAP 연결
 */
function mail_connect($account_index = 0) {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return false;

    $acc = $accounts[$account_index];
    $mailbox = MAIL_IMAP_HOST . 'INBOX';

    $imap = @imap_open($mailbox, $acc['email'], $acc['password']);
    if (!$imap) {
        error_log('IMAP connect failed for ' . $acc['email'] . ': ' . imap_last_error());
        return false;
    }
    return $imap;
}

/**
 * 모든 계정의 안 읽은 메일 수 조회
 */
function mail_get_unread_counts() {
    $counts = [];
    $total = 0;
    foreach (MAIL_ACCOUNTS as $i => $acc) {
        $imap = mail_connect($i);
        if ($imap) {
            $check = imap_status($imap, MAIL_IMAP_HOST . 'INBOX', SA_UNSEEN | SA_MESSAGES);
            $counts[$i] = [
                'email' => $acc['email'],
                'label' => $acc['label'],
                'color' => $acc['color'],
                'unseen' => $check ? $check->unseen : 0,
                'total' => $check ? $check->messages : 0,
            ];
            $total += $counts[$i]['unseen'];
            imap_close($imap);
        } else {
            $counts[$i] = [
                'email' => $acc['email'],
                'label' => $acc['label'],
                'color' => $acc['color'],
                'unseen' => -1,
                'total' => -1,
            ];
        }
    }
    return ['accounts' => $counts, 'total_unseen' => $total];
}

/**
 * 메일 목록 조회
 * @param int $account_index 계정 인덱스
 * @param string $folder 폴더 (INBOX, Sent, Trash, Junk, Archive, Drafts)
 * @param string $search 검색어 (제목/발신자)
 * @param int $page 페이지
 * @param int $per_page 페이지당 건수
 * @param string $filter unseen|all|flagged
 * @param string $sort date_desc|date_asc|subject_asc|subject_desc|from_asc|from_desc
 * @return array
 */
function mail_get_list($account_index = 0, $folder = 'INBOX', $search = '', $page = 1, $per_page = 20, $filter = 'all', $sort = 'date_desc') {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return ['error' => '계정을 찾을 수 없습니다.'];

    $acc = $accounts[$account_index];
    $mailbox_path = MAIL_IMAP_HOST . $folder;
    $imap = @imap_open($mailbox_path, $acc['email'], $acc['password']);
    if (!$imap) {
        return ['error' => 'IMAP 연결 실패: ' . imap_last_error()];
    }

    // 검색 조건
    $criteria = 'ALL';
    if ($filter === 'unseen') $criteria = 'UNSEEN';
    elseif ($filter === 'flagged') $criteria = 'FLAGGED';

    if ($search) {
        // IMAP search by subject or from
        $criteria = 'OR SUBJECT "' . addslashes($search) . '" FROM "' . addslashes($search) . '"';
        if ($filter === 'unseen') {
            $criteria = 'UNSEEN ' . $criteria;
        }
    }

    $msg_ids = @imap_search($imap, $criteria, SE_UID);
    if ($msg_ids === false) $msg_ids = [];

    // 정렬: date_desc(기본), date_asc, subject_asc, subject_desc, from_asc, from_desc
    $need_header_sort = in_array($sort, ['subject_asc', 'subject_desc', 'from_asc', 'from_desc']);

    if ($need_header_sort && !empty($msg_ids)) {
        // 헤더 기반 정렬: 모든 메일 overview 가져와서 정렬
        $all_overviews = [];
        // UID 범위로 한번에 overview 가져오기
        $uid_str = implode(',', $msg_ids);
        $overviews = @imap_fetch_overview($imap, $uid_str, FT_UID);
        if ($overviews) {
            foreach ($overviews as $ov) {
                $subj = isset($ov->subject) ? mail_decode_mime($ov->subject) : '';
                $from = isset($ov->from) ? mail_decode_mime($ov->from) : '';
                $all_overviews[$ov->uid] = ['subject' => $subj, 'from' => $from];
            }
        }
        usort($msg_ids, function($a, $b) use ($sort, $all_overviews) {
            $da = $all_overviews[$a] ?? ['subject' => '', 'from' => ''];
            $db = $all_overviews[$b] ?? ['subject' => '', 'from' => ''];
            if ($sort === 'subject_asc') return strcasecmp($da['subject'], $db['subject']);
            if ($sort === 'subject_desc') return strcasecmp($db['subject'], $da['subject']);
            if ($sort === 'from_asc') return strcasecmp($da['from'], $db['from']);
            if ($sort === 'from_desc') return strcasecmp($db['from'], $da['from']);
            return 0;
        });
    } elseif ($sort === 'date_asc') {
        sort($msg_ids); // UID 오름차순 = 오래된 순
    } else {
        rsort($msg_ids); // UID 내림차순 = 최신순 (기본)
    }

    $total = count($msg_ids);
    $total_pages = max(1, ceil($total / $per_page));
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    $page_ids = array_slice($msg_ids, $offset, $per_page);

    $emails = [];
    foreach ($page_ids as $uid) {
        $header = @imap_fetchheader($imap, $uid, FT_UID);
        $header_info = @imap_headerinfo($imap, imap_msgno($imap, $uid));
        $overview = @imap_fetch_overview($imap, $uid, FT_UID);

        if (!$header_info || !$overview) continue;
        $ov = $overview[0];

        // Subject 디코딩
        $subject = isset($ov->subject) ? mail_decode_mime($ov->subject) : '(제목 없음)';
        // From 디코딩
        $from_name = '';
        $from_email = '';
        if (isset($header_info->from[0])) {
            $from_name = isset($header_info->from[0]->personal) ? mail_decode_mime($header_info->from[0]->personal) : '';
            $from_email = $header_info->from[0]->mailbox . '@' . ($header_info->from[0]->host ?? '');
        }
        // Reply-To (리스트에서도 필요)
        $reply_to = '';
        if (isset($header_info->reply_to[0])) {
            $rt = $header_info->reply_to[0]->mailbox . '@' . ($header_info->reply_to[0]->host ?? '');
            // imap_headerinfo는 Reply-To가 없으면 From을 기본값으로 반환 -> 다를 때만 저장
            if ($rt !== $from_email) {
                $reply_to = $rt;
            }
        }
        // To
        $to_email = '';
        if (isset($header_info->to[0])) {
            $to_email = $header_info->to[0]->mailbox . '@' . ($header_info->to[0]->host ?? '');
        }

        // 날짜: overview -> header_info->date -> header_info->udate 순서
        $date_str = '';
        $timestamp = 0;
        if (!empty($ov->date)) {
            $date_str = $ov->date;
            $timestamp = strtotime($date_str);
        } elseif (!empty($header_info->date)) {
            $date_str = $header_info->date;
            $timestamp = strtotime($date_str);
        }
        if (!$timestamp && isset($header_info->udate) && $header_info->udate > 0) {
            $timestamp = $header_info->udate;
            $date_str = date('r', $timestamp);
        }

        $emails[] = [
            'uid' => $uid,
            'subject' => $subject,
            'from_name' => $from_name,
            'from_email' => $from_email,
            'reply_to' => $reply_to,
            'to_email' => $to_email,
            'date' => $date_str,
            'timestamp' => $timestamp,
            'seen' => isset($ov->seen) && $ov->seen,
            'flagged' => isset($ov->flagged) && $ov->flagged,
            'answered' => isset($ov->answered) && $ov->answered,
            'size' => isset($ov->size) ? $ov->size : 0,
        ];
    }

    // 안 읽은 메일 수
    $check = imap_status($imap, $mailbox_path, SA_UNSEEN);
    $unseen = $check ? $check->unseen : 0;

    imap_close($imap);

    return [
        'emails' => $emails,
        'total' => $total,
        'unseen' => $unseen,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'account' => [
            'email' => $acc['email'],
            'name' => $acc['name'],
            'label' => $acc['label'],
            'color' => $acc['color'],
        ],
        'folder' => $folder,
    ];
}

/**
 * 메일 상세 읽기
 */
function mail_read($account_index, $uid, $folder = 'INBOX') {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return ['error' => '계정을 찾을 수 없습니다.'];

    $acc = $accounts[$account_index];
    $mailbox_path = MAIL_IMAP_HOST . $folder;
    $imap = @imap_open($mailbox_path, $acc['email'], $acc['password']);
    if (!$imap) return ['error' => 'IMAP 연결 실패'];

    $msgno = imap_msgno($imap, $uid);
    if (!$msgno) {
        imap_close($imap);
        return ['error' => '메일을 찾을 수 없습니다.'];
    }

    $header_info = imap_headerinfo($imap, $msgno);
    $structure = imap_fetchstructure($imap, $uid, FT_UID);

    // 읽음 표시
    imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);

    // Subject
    $subject = isset($header_info->subject) ? mail_decode_mime($header_info->subject) : '(제목 없음)';

    // From
    $from_name = '';
    $from_email = '';
    if (isset($header_info->from[0])) {
        $from_name = isset($header_info->from[0]->personal) ? mail_decode_mime($header_info->from[0]->personal) : '';
        $from_email = $header_info->from[0]->mailbox . '@' . ($header_info->from[0]->host ?? '');
    }

    // Reply-To
    $reply_to = '';
    $reply_to_name = '';
    if (isset($header_info->reply_to[0])) {
        $reply_to = $header_info->reply_to[0]->mailbox . '@' . ($header_info->reply_to[0]->host ?? '');
        $reply_to_name = isset($header_info->reply_to[0]->personal) ? mail_decode_mime($header_info->reply_to[0]->personal) : '';
    }

    // To
    $to_list = [];
    if (isset($header_info->to)) {
        foreach ($header_info->to as $to) {
            $to_list[] = $to->mailbox . '@' . ($to->host ?? '');
        }
    }

    // Date: header_info->date 또는 udate fallback
    $date = '';
    if (!empty($header_info->date)) {
        $date = $header_info->date;
    } elseif (isset($header_info->udate) && $header_info->udate > 0) {
        $date = date('r', $header_info->udate);
    }

    // Message-ID, References (답장 스레딩용)
    $message_id = isset($header_info->message_id) ? trim($header_info->message_id) : '';
    $references = '';
    $raw_headers = imap_fetchheader($imap, $uid, FT_UID);
    if (preg_match('/^References:\s*(.+?)(?=\r?\n\S|\r?\n\r?\n)/mis', $raw_headers, $ref_match)) {
        $references = trim(preg_replace('/\s+/', ' ', $ref_match[1]));
    }

    // CC
    $cc_list = [];
    if (isset($header_info->cc)) {
        foreach ($header_info->cc as $cc) {
            $cc_list[] = $cc->mailbox . '@' . ($cc->host ?? '');
        }
    }

    // Body
    $body = mail_get_body($imap, $uid, $structure);

    // Attachments
    $attachments = mail_get_attachments($imap, $uid, $structure);

    imap_close($imap);

    return [
        'uid' => $uid,
        'subject' => $subject,
        'from_name' => $from_name,
        'from_email' => $from_email,
        'reply_to' => $reply_to,
        'reply_to_name' => $reply_to_name,
        'to' => $to_list,
        'cc' => $cc_list,
        'date' => $date,
        'message_id' => $message_id,
        'references' => $references,
        'body_html' => $body['html'],
        'body_text' => $body['text'],
        'attachments' => $attachments,
    ];
}

/**
 * 메일 본문 추출 (재귀 + 인라인 이미지 CID→data URI 변환)
 */
function mail_get_body($imap, $uid, $structure) {
    $body = ['html' => '', 'text' => '', 'inline_images' => []];
    mail_extract_parts($imap, $uid, $structure, '', $body);

    // CID 참조를 data URI로 치환
    if ($body['html'] && !empty($body['inline_images'])) {
        foreach ($body['inline_images'] as $cid => $data_uri) {
            $body['html'] = str_replace('cid:' . $cid, $data_uri, $body['html']);
        }
    }

    return $body;
}

/**
 * 재귀적 MIME 파트 추출 (multipart/mixed, related, alternative 모두 지원)
 */
function mail_extract_parts($imap, $uid, $part, $section, &$body) {
    // MULTIPART (type=1) -> 하위 파트로 재귀
    if ($part->type === 1) {
        if (!empty($part->parts)) {
            foreach ($part->parts as $i => $subpart) {
                $sub_section = $section ? ($section . '.' . ($i + 1)) : (string)($i + 1);
                mail_extract_parts($imap, $uid, $subpart, $sub_section, $body);
            }
        }
        return;
    }

    // 리프 파트 - 섹션 번호 (단일 메시지는 '1')
    $fetch_section = $section ?: '1';

    // TEXT (type=0): text/plain 또는 text/html
    if ($part->type === 0) {
        $content = @imap_fetchbody($imap, $uid, $fetch_section, FT_UID);
        if ($content === false) return;
        $content = mail_decode_body($content, $part->encoding ?? 0);
        $charset = mail_get_charset($part);
        if ($charset && strtolower($charset) !== 'utf-8') {
            $content = @mb_convert_encoding($content, 'UTF-8', $charset);
        }

        if ($part->subtype === 'HTML' && !$body['html']) {
            $body['html'] = $content;
        } elseif ($part->subtype === 'PLAIN' && !$body['text']) {
            $body['text'] = $content;
        }
        return;
    }

    // IMAGE (type=5): 인라인 이미지 (Content-ID가 있는 경우)
    if ($part->type === 5 && !empty($part->id)) {
        $cid = trim($part->id, '<>');
        if ($cid) {
            $content = @imap_fetchbody($imap, $uid, $fetch_section, FT_UID);
            if ($content === false) return;
            $content = mail_decode_body($content, $part->encoding ?? 0);
            $mime = 'image/' . strtolower($part->subtype);
            $body['inline_images'][$cid] = 'data:' . $mime . ';base64,' . base64_encode($content);
        }
    }
}

/**
 * 첨부파일 목록
 */
function mail_get_attachments($imap, $uid, $structure) {
    $attachments = [];
    if (empty($structure->parts)) return $attachments;

    foreach ($structure->parts as $i => $part) {
        $filename = '';
        if ($part->ifdparameters) {
            foreach ($part->dparameters as $p) {
                if (strtolower($p->attribute) === 'filename') {
                    $filename = mail_decode_mime($p->value);
                }
            }
        }
        if (!$filename && $part->ifparameters) {
            foreach ($part->parameters as $p) {
                if (strtolower($p->attribute) === 'name') {
                    $filename = mail_decode_mime($p->value);
                }
            }
        }

        if ($filename) {
            $attachments[] = [
                'part' => $i + 1,
                'filename' => $filename,
                'size' => $part->bytes ?? 0,
                'type' => $part->subtype ?? 'unknown',
            ];
        }
    }

    return $attachments;
}

/**
 * 메일 삭제 (휴지통 이동 방식)
 * - Trash 폴더가 아닌 경우: Trash로 이동
 * - Trash 폴더인 경우: 영구 삭제
 */
function mail_delete($account_index, $uid, $folder = 'INBOX') {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return false;

    $acc = $accounts[$account_index];
    $imap = @imap_open(MAIL_IMAP_HOST . $folder, $acc['email'], $acc['password']);
    if (!$imap) return false;

    if (strtolower($folder) === 'trash') {
        // 휴지통에서는 영구 삭제
        imap_delete($imap, (string)$uid, FT_UID);
    } else {
        // 다른 폴더에서는 휴지통으로 이동
        imap_mail_move($imap, (string)$uid, 'Trash', CP_UID);
    }
    imap_expunge($imap);
    imap_close($imap);
    return true;
}

/**
 * 읽음/안읽음 토글
 */
function mail_toggle_seen($account_index, $uid, $seen = true, $folder = 'INBOX') {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return false;

    $acc = $accounts[$account_index];
    $imap = @imap_open(MAIL_IMAP_HOST . $folder, $acc['email'], $acc['password']);
    if (!$imap) return false;

    if ($seen) {
        imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
    } else {
        imap_clearflag_full($imap, (string)$uid, '\\Seen', ST_UID);
    }
    imap_close($imap);
    return true;
}

/**
 * MIME 헤더 디코딩
 */
function mail_decode_mime($string) {
    $elements = imap_mime_header_decode($string);
    $result = '';
    foreach ($elements as $el) {
        if ($el->charset === 'default') {
            $result .= $el->text;
        } else {
            $result .= mb_convert_encoding($el->text, 'UTF-8', $el->charset);
        }
    }
    return $result;
}

/**
 * 본문 인코딩 디코딩
 */
function mail_decode_body($content, $encoding) {
    switch ($encoding) {
        case 0: // 7BIT
        case 1: // 8BIT
            return $content;
        case 2: // BINARY
            return $content;
        case 3: // BASE64
            return base64_decode($content);
        case 4: // QUOTED-PRINTABLE
            return quoted_printable_decode($content);
        default:
            return $content;
    }
}

/**
 * 캐릭터셋 추출
 */
function mail_get_charset($structure) {
    if (isset($structure->parameters)) {
        foreach ($structure->parameters as $p) {
            if (strtolower($p->attribute) === 'charset') {
                return $p->value;
            }
        }
    }
    return 'UTF-8';
}

/**
 * 날짜 포맷
 */
function mail_format_date($date_string) {
    $ts = strtotime($date_string);
    if (!$ts) return $date_string;

    $today = strtotime('today');
    if ($ts >= $today) {
        return date('H:i', $ts);
    } elseif ($ts >= $today - 86400) {
        return '어제 ' . date('H:i', $ts);
    } elseif ($ts >= $today - 86400 * 7) {
        $days = ['일', '월', '화', '수', '목', '금', '토'];
        return $days[date('w', $ts)] . ' ' . date('H:i', $ts);
    } else {
        return date('m/d', $ts);
    }
}

/**
 * 파일 크기 포맷
 */
function mail_format_size($bytes) {
    if ($bytes < 1024) return $bytes . 'B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . 'KB';
    return round($bytes / 1048576, 1) . 'MB';
}

/**
 * SMTP 메일 발송 (fsockopen + STARTTLS, 외부 라이브러리 없음)
 * 발송 후 IMAP Sent 폴더에 저장
 *
 * @param int    $account_index  MAIL_ACCOUNTS 인덱스
 * @param string $to             수신자 이메일 (복수 시 콤마 구분)
 * @param string $subject        제목
 * @param string $body_html      HTML 본문
 * @param string $cc             CC (복수 시 콤마 구분)
 * @param string $in_reply_to    In-Reply-To 헤더 (답장 스레딩)
 * @param string $references     References 헤더 (답장 스레딩)
 * @return array ['success'=>true] 또는 ['error'=>'message']
 */
function mail_send($account_index, $to, $subject, $body_html, $cc = '', $in_reply_to = '', $references = '', $attachments = []) {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) {
        return ['error' => '유효하지 않은 계정 인덱스입니다.'];
    }

    $acc = $accounts[$account_index];
    $from_email = $acc['email'];
    $from_name = $acc['name'];
    $smtp_host = MAIL_SMTP_HOST;
    $smtp_port = MAIL_SMTP_PORT;
    $has_attachments = !empty($attachments) && is_array($attachments);

    // ---- MIME 메시지 구성 ----
    $alt_boundary = '----=_Alt_' . md5(uniqid(mt_rand(), true));
    $mixed_boundary = '----=_Mix_' . md5(uniqid(mt_rand(), true));
    $message_id = '<' . md5(uniqid(mt_rand(), true)) . '@' . substr($from_email, strpos($from_email, '@') + 1) . '>';
    $date = date('r');

    // text/plain 본문 (HTML 태그 제거)
    $body_text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $body_html));
    $body_text = html_entity_decode($body_text, ENT_QUOTES, 'UTF-8');
    $body_text = preg_replace('/\n{3,}/', "\n\n", trim($body_text));

    // 헤더 구성
    $headers = '';
    $headers .= 'Date: ' . $date . "\r\n";
    $headers .= 'From: =?UTF-8?B?' . base64_encode($from_name) . '?= <' . $from_email . '>' . "\r\n";
    $headers .= 'To: ' . $to . "\r\n";
    if ($cc) {
        $headers .= 'Cc: ' . $cc . "\r\n";
    }
    $headers .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=' . "\r\n";
    $headers .= 'Message-ID: ' . $message_id . "\r\n";
    if ($in_reply_to) {
        $headers .= 'In-Reply-To: ' . $in_reply_to . "\r\n";
    }
    if ($references) {
        $headers .= 'References: ' . $references . "\r\n";
    }
    $headers .= 'MIME-Version: 1.0' . "\r\n";

    if ($has_attachments) {
        // 첨부파일 있으면: multipart/mixed > multipart/alternative + attachments
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $mixed_boundary . '"' . "\r\n";
    } else {
        // 첨부파일 없으면: multipart/alternative (text + html)
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $alt_boundary . '"' . "\r\n";
    }
    $headers .= 'X-Mailer: SeoulBeauty-Mailer/1.0' . "\r\n";

    // 본문 구성 (multipart/alternative: text/plain + text/html)
    $alt_body = '';
    $alt_body .= '--' . $alt_boundary . "\r\n";
    $alt_body .= 'Content-Type: text/plain; charset="UTF-8"' . "\r\n";
    $alt_body .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $alt_body .= "\r\n";
    $alt_body .= chunk_split(base64_encode($body_text)) . "\r\n";

    $alt_body .= '--' . $alt_boundary . "\r\n";
    $alt_body .= 'Content-Type: text/html; charset="UTF-8"' . "\r\n";
    $alt_body .= 'Content-Transfer-Encoding: base64' . "\r\n";
    $alt_body .= "\r\n";
    $alt_body .= chunk_split(base64_encode($body_html)) . "\r\n";

    $alt_body .= '--' . $alt_boundary . '--' . "\r\n";

    if ($has_attachments) {
        // multipart/mixed 구조
        $body = '';
        $body .= '--' . $mixed_boundary . "\r\n";
        $body .= 'Content-Type: multipart/alternative; boundary="' . $alt_boundary . '"' . "\r\n";
        $body .= "\r\n";
        $body .= $alt_body;

        // 각 첨부파일 추가
        foreach ($attachments as $att) {
            if (!isset($att['path']) || !file_exists($att['path'])) continue;
            $att_name = $att['name'] ?? basename($att['path']);
            $att_type = $att['type'] ?? 'application/octet-stream';
            $att_data = @file_get_contents($att['path']);
            if ($att_data === false) continue;

            $body .= '--' . $mixed_boundary . "\r\n";
            $body .= 'Content-Type: ' . $att_type . '; name="=?UTF-8?B?' . base64_encode($att_name) . '?="' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="=?UTF-8?B?' . base64_encode($att_name) . '?="' . "\r\n";
            $body .= 'Content-Transfer-Encoding: base64' . "\r\n";
            $body .= "\r\n";
            $body .= chunk_split(base64_encode($att_data)) . "\r\n";
        }

        $body .= '--' . $mixed_boundary . '--' . "\r\n";
    } else {
        $body = $alt_body;
    }

    // 전체 메시지 (IMAP 저장용)
    $full_message = $headers . "\r\n" . $body;

    // 수신자 목록 (RCPT TO용)
    $recipients = array_map('trim', explode(',', $to));
    if ($cc) {
        $cc_list = array_map('trim', explode(',', $cc));
        $recipients = array_merge($recipients, $cc_list);
    }
    // 이메일만 추출 (Name <email> 형식 대응)
    $recipients = array_map(function($addr) {
        if (preg_match('/<([^>]+)>/', $addr, $m)) {
            return $m[1];
        }
        return trim($addr);
    }, $recipients);
    $recipients = array_filter($recipients, function($addr) {
        return filter_var($addr, FILTER_VALIDATE_EMAIL);
    });

    if (empty($recipients)) {
        return ['error' => '유효한 수신자가 없습니다.'];
    }

    // ---- SMTP 전송 ----
    $smtp_error = '';
    $smtp_log = [];

    // SMTP 연결 (30초 타임아웃, SSL 인증서 검증 비활성화 - 자체 메일서버)
    $errno = 0;
    $errstr = '';
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);
    $smtp = @stream_socket_client("tcp://{$smtp_host}:{$smtp_port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
    if (!$smtp) {
        return ['error' => 'SMTP 연결 실패: ' . $errstr . ' (' . $errno . ')'];
    }

    // 타임아웃 설정
    stream_set_timeout($smtp, 30);

    // SMTP 응답 읽기 헬퍼
    $smtp_read = function() use ($smtp, &$smtp_log) {
        $response = '';
        while ($line = @fgets($smtp, 512)) {
            $response .= $line;
            // 4번째 문자가 공백이면 마지막 줄 (멀티라인 응답 처리)
            if (isset($line[3]) && $line[3] === ' ') break;
            // 타임아웃 체크
            $meta = stream_get_meta_data($smtp);
            if ($meta['timed_out']) break;
        }
        $smtp_log[] = 'S: ' . trim($response);
        return $response;
    };

    // SMTP 명령 전송 헬퍼
    $smtp_cmd = function($cmd, $expected_code) use ($smtp, $smtp_read, &$smtp_log, &$smtp_error) {
        $smtp_log[] = 'C: ' . trim($cmd);
        @fwrite($smtp, $cmd . "\r\n");
        $response = $smtp_read();
        $code = (int)substr($response, 0, 3);
        if ($code !== $expected_code) {
            $smtp_error = 'SMTP 오류 [' . $cmd . ']: 예상=' . $expected_code . ', 수신=' . $code . ' ' . trim($response);
            return false;
        }
        return $response;
    };

    try {
        // 배너 읽기
        $banner = $smtp_read();
        if ((int)substr($banner, 0, 3) !== 220) {
            fclose($smtp);
            return ['error' => 'SMTP 배너 오류: ' . trim($banner)];
        }

        // EHLO
        if ($smtp_cmd('EHLO ' . gethostname(), 250) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // STARTTLS
        if ($smtp_cmd('STARTTLS', 220) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // TLS 활성화
        $crypto_ok = @stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        if (!$crypto_ok) {
            // TLS 1.2/1.3 실패 시 any TLS로 재시도
            $crypto_ok = @stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
        }
        if (!$crypto_ok) {
            fclose($smtp);
            return ['error' => 'STARTTLS 암호화 실패'];
        }

        // TLS 후 다시 EHLO
        if ($smtp_cmd('EHLO ' . gethostname(), 250) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // AUTH LOGIN
        if ($smtp_cmd('AUTH LOGIN', 334) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // 사용자명 (base64)
        if ($smtp_cmd(base64_encode($from_email), 334) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // 비밀번호 (base64)
        if ($smtp_cmd(base64_encode($acc['password']), 235) === false) {
            fclose($smtp);
            return ['error' => 'SMTP 인증 실패: ' . $smtp_error];
        }

        // MAIL FROM
        if ($smtp_cmd('MAIL FROM:<' . $from_email . '>', 250) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // RCPT TO (각 수신자별)
        foreach ($recipients as $rcpt) {
            if ($smtp_cmd('RCPT TO:<' . $rcpt . '>', 250) === false) {
                fclose($smtp);
                return ['error' => $smtp_error];
            }
        }

        // DATA
        if ($smtp_cmd('DATA', 354) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // 메일 데이터 전송 (투명성: 줄 시작 . -> ..)
        $data_lines = explode("\r\n", $full_message);
        foreach ($data_lines as $line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
            @fwrite($smtp, $line . "\r\n");
        }

        // 종료 마커
        if ($smtp_cmd('.', 250) === false) {
            fclose($smtp);
            return ['error' => $smtp_error];
        }

        // QUIT
        $smtp_cmd('QUIT', 221);

        fclose($smtp);

    } catch (\Exception $e) {
        if (is_resource($smtp)) fclose($smtp);
        return ['error' => 'SMTP 예외: ' . $e->getMessage()];
    }

    // ---- IMAP Sent 폴더에 저장 ----
    $sent_folder = MAIL_IMAP_HOST . 'Sent';
    $imap = @imap_open($sent_folder, $from_email, $acc['password']);
    if ($imap) {
        // \Seen 플래그와 함께 Sent 폴더에 저장
        $append_ok = @imap_append($imap, $sent_folder, $full_message, '\\Seen');
        if (!$append_ok) {
            error_log('IMAP Sent 저장 실패: ' . imap_last_error());
        }
        imap_close($imap);
    } else {
        error_log('IMAP Sent 폴더 연결 실패: ' . imap_last_error());
    }

    return ['success' => true, 'message_id' => $message_id];
}

/**
 * 다건 메일 삭제 (UID 배열)
 * IMAP 연결 1회로 여러 메일을 한꺼번에 삭제
 *
 * @param int    $account_index  MAIL_ACCOUNTS 인덱스
 * @param array  $uids           삭제할 UID 배열
 * @param string $folder         폴더 (기본: INBOX)
 * @return bool
 */
function mail_bulk_delete($account_index, $uids, $folder = 'INBOX') {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return false;
    if (empty($uids) || !is_array($uids)) return false;

    $acc = $accounts[$account_index];
    $imap = @imap_open(MAIL_IMAP_HOST . $folder, $acc['email'], $acc['password']);
    if (!$imap) return false;

    $is_trash = (strtolower($folder) === 'trash');
    foreach ($uids as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) {
            if ($is_trash) {
                imap_delete($imap, (string)$uid, FT_UID);
            } else {
                imap_mail_move($imap, (string)$uid, 'Trash', CP_UID);
            }
        }
    }

    imap_expunge($imap);
    imap_close($imap);
    return true;
}

/**
 * 다건 읽음/안읽음 설정 (UID 배열)
 * IMAP 연결 1회로 여러 메일의 \Seen 플래그를 일괄 설정/해제
 *
 * @param int    $account_index  MAIL_ACCOUNTS 인덱스
 * @param array  $uids           대상 UID 배열
 * @param bool   $seen           true=읽음 표시, false=안읽음 표시
 * @param string $folder         폴더 (기본: INBOX)
 * @return bool
 */
function mail_bulk_toggle_seen($account_index, $uids, $seen = true, $folder = 'INBOX') {
    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return false;
    if (empty($uids) || !is_array($uids)) return false;

    $acc = $accounts[$account_index];
    $imap = @imap_open(MAIL_IMAP_HOST . $folder, $acc['email'], $acc['password']);
    if (!$imap) return false;

    foreach ($uids as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) {
            if ($seen) {
                imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
            } else {
                imap_clearflag_full($imap, (string)$uid, '\\Seen', ST_UID);
            }
        }
    }

    imap_close($imap);
    return true;
}

/**
 * 메일 서명 조회
 * JSON 파일에서 계정별 서명 HTML 반환
 *
 * @param int $account_index  MAIL_ACCOUNTS 인덱스
 * @return string 서명 HTML (없으면 빈 문자열)
 */
function mail_get_signature($account_index) {
    $file = __DIR__ . '/../data/mail_signatures.json';
    if (!file_exists($file)) return '';

    $json = @file_get_contents($file);
    if ($json === false) return '';

    $data = @json_decode($json, true);
    if (!is_array($data)) return '';

    $key = (string)$account_index;
    return isset($data[$key]) ? (string)$data[$key] : '';
}

/**
 * 메일 서명 저장
 * JSON 파일에 계정별 서명 HTML 저장 (없으면 생성)
 *
 * @param int    $account_index  MAIL_ACCOUNTS 인덱스
 * @param string $html           서명 HTML
 * @return bool
 */
function mail_save_signature($account_index, $html) {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $file = $dir . '/mail_signatures.json';
    $data = [];

    // 기존 데이터 읽기
    if (file_exists($file)) {
        $json = @file_get_contents($file);
        if ($json !== false) {
            $decoded = @json_decode($json, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    $data[(string)$account_index] = $html;

    $result = @file_put_contents(
        $file,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    return $result !== false;
}

/**
 * 상용구(템플릿) 목록 조회
 * @return array [{id, title, content}, ...]
 */
function mail_get_templates() {
    $file = __DIR__ . '/../data/mail_templates.json';
    if (!file_exists($file)) return [];
    $json = @file_get_contents($file);
    if ($json === false) return [];
    $data = @json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * 상용구 저장 (신규 또는 수정)
 * @param int    $id      0이면 신규
 * @param string $title   제목
 * @param string $content 내용 HTML
 * @return bool
 */
function mail_save_template($id, $title, $content) {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $file = $dir . '/mail_templates.json';
    $templates = mail_get_templates();

    if ($id > 0) {
        $found = false;
        foreach ($templates as &$t) {
            if ($t['id'] == $id) {
                $t['title'] = $title;
                $t['content'] = $content;
                $found = true;
                break;
            }
        }
        unset($t);
        if (!$found) return false;
    } else {
        $max_id = 0;
        foreach ($templates as $t) {
            if ($t['id'] > $max_id) $max_id = $t['id'];
        }
        $templates[] = ['id' => $max_id + 1, 'title' => $title, 'content' => $content];
    }

    return @file_put_contents($file, json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

/**
 * 상용구 삭제
 * @param int $id
 * @return bool
 */
function mail_delete_template($id) {
    $templates = mail_get_templates();
    $templates = array_values(array_filter($templates, function($t) use ($id) {
        return $t['id'] != $id;
    }));
    $file = __DIR__ . '/../data/mail_templates.json';
    return @file_put_contents($file, json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

// =========================================================
// 휴지통 자동 삭제 설정
// =========================================================

/**
 * 휴지통 설정 조회
 */
function mail_get_trash_settings() {
    $file = __DIR__ . '/../data/mail_trash_settings.json';
    if (file_exists($file)) {
        $data = @json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return ['auto_purge_days' => 30, 'enabled' => true];
}

/**
 * 휴지통 설정 저장
 */
function mail_save_trash_settings($settings) {
    $file = __DIR__ . '/../data/mail_trash_settings.json';
    $dir = dirname($file);
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    return @file_put_contents($file, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}

/**
 * 휴지통 자동 정리 (설정 기일 이전 메일 영구 삭제)
 * 크론잡 또는 수동 호출
 */
function mail_auto_purge_trash($account_index = 0) {
    $settings = mail_get_trash_settings();
    if (!$settings['enabled'] || $settings['auto_purge_days'] < 1) return 0;

    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return 0;

    $acc = $accounts[$account_index];
    $imap = @imap_open(MAIL_IMAP_HOST . 'Trash', $acc['email'], $acc['password']);
    if (!$imap) return 0;

    $cutoff = date('j M Y', strtotime('-' . $settings['auto_purge_days'] . ' days'));
    $old_ids = @imap_search($imap, 'BEFORE "' . $cutoff . '"', SE_UID);
    if ($old_ids === false) $old_ids = [];

    $deleted = 0;
    foreach ($old_ids as $uid) {
        imap_delete($imap, (string)$uid, FT_UID);
        $deleted++;
    }
    if ($deleted > 0) imap_expunge($imap);

    imap_close($imap);
    return $deleted;
}

/**
 * 모든 계정 휴지통 자동 정리
 */
function mail_auto_purge_all_accounts() {
    $total = 0;
    foreach (MAIL_ACCOUNTS as $i => $acc) {
        $total += mail_auto_purge_trash($i);
    }
    return $total;
}

// =========================================================
// 발송 수신자 연락처 캐시
// =========================================================

/**
 * Sent 폴더에서 수신자 목록 추출 (캐시 5분)
 */
function mail_get_sent_contacts($account_index = 0) {
    $data_dir = __DIR__ . '/../data';
    if (!is_dir($data_dir)) mkdir($data_dir, 0775, true);
    $cache_file = $data_dir . '/mail_contacts_' . $account_index . '.json';
    $cache_ttl = 300; // 5분

    // 캐시 확인
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
        $cached = @json_decode(file_get_contents($cache_file), true);
        if (is_array($cached)) return $cached;
    }

    $accounts = MAIL_ACCOUNTS;
    if (!isset($accounts[$account_index])) return [];

    $acc = $accounts[$account_index];
    $imap = @imap_open(MAIL_IMAP_HOST . 'Sent', $acc['email'], $acc['password']);
    if (!$imap) return [];

    $contacts = [];
    $msg_ids = @imap_search($imap, 'ALL', SE_UID);
    if ($msg_ids === false) $msg_ids = [];
    rsort($msg_ids); // 최신순

    // 최근 200건만 스캔
    $scan_ids = array_slice($msg_ids, 0, 200);
    foreach ($scan_ids as $uid) {
        $msgno = @imap_msgno($imap, $uid);
        if (!$msgno) continue;
        $header_info = @imap_headerinfo($imap, $msgno);
        if (!$header_info) continue;

        if (isset($header_info->to)) {
            foreach ($header_info->to as $to) {
                $email = ($to->mailbox ?? '') . '@' . ($to->host ?? '');
                if (!$email || $email === '@') continue;
                $name = isset($to->personal) ? mail_decode_mime($to->personal) : '';
                $key = strtolower($email);
                if (!isset($contacts[$key])) {
                    $contacts[$key] = ['email' => $email, 'name' => $name, 'count' => 0];
                }
                $contacts[$key]['count']++;
                if (!$contacts[$key]['name'] && $name) $contacts[$key]['name'] = $name;
            }
        }
        if (isset($header_info->cc)) {
            foreach ($header_info->cc as $cc) {
                $email = ($cc->mailbox ?? '') . '@' . ($cc->host ?? '');
                if (!$email || $email === '@') continue;
                $name = isset($cc->personal) ? mail_decode_mime($cc->personal) : '';
                $key = strtolower($email);
                if (!isset($contacts[$key])) {
                    $contacts[$key] = ['email' => $email, 'name' => $name, 'count' => 0];
                }
                $contacts[$key]['count']++;
            }
        }
    }

    imap_close($imap);

    // 자기 자신 제외
    $own_email = strtolower($acc['email']);
    unset($contacts[$own_email]);

    // 빈도순 정렬
    $result = array_values($contacts);
    usort($result, function($a, $b) { return $b['count'] - $a['count']; });

    @file_put_contents($cache_file, json_encode($result, JSON_UNESCAPED_UNICODE));
    return $result;
}
