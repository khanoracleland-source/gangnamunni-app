<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * AI 이미지 에디터
 * 기존 광고/이벤트 이미지 불러와서 편집 + AI 생성
 * Fabric.js 기반 캔버스 에디터
 */

session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

if (file_exists(__DIR__ . '/ai/openai_config.php')) {
    require_once __DIR__ . '/ai/openai_config.php';
}

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
    loadThemeSettings($connect);
}

// AJAX 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($_POST['action']) {
        case 'load_images':
            $source = $_POST['source'] ?? 'all';
            $search = trim($_POST['search'] ?? '');
            $sort = $_POST['sort'] ?? 'date_desc';
            $page = max(1, intval($_POST['page'] ?? 1));
            $per_page = max(10, min(200, intval($_POST['per_page'] ?? 20)));
            $images = [];

            if ($source === 'all' || $source === 'ads') {
                $sql = "SELECT id, title, subtitle, image_url, ad_type, created_at FROM gangnamunni_ads WHERE image_url IS NOT NULL AND image_url != ''";
                if ($search) { $esc = mysqli_real_escape_string($connect, $search); $sql .= " AND (title LIKE '%{$esc}%' OR subtitle LIKE '%{$esc}%')"; }
                $r = mysqli_query($connect, $sql);
                if ($r) while ($row = mysqli_fetch_assoc($r)) {
                    $images[] = ['id'=>'ad_'.$row['id'], 'source'=>'ads', 'source_label'=>'광고', 'title'=>$row['title'], 'subtitle'=>$row['subtitle']??'', 'url'=>$row['image_url'], 'type'=>$row['ad_type']??'', 'date'=>$row['created_at']];
                }
            }
            if ($source === 'all' || $source === 'events') {
                $sql = "SELECT id, title, subtitle, image_url, category, created_at FROM gangnamunni_events WHERE image_url IS NOT NULL AND image_url != ''";
                if ($search) { $esc = mysqli_real_escape_string($connect, $search); $sql .= " AND (title LIKE '%{$esc}%' OR subtitle LIKE '%{$esc}%')"; }
                $r = mysqli_query($connect, $sql);
                if ($r) while ($row = mysqli_fetch_assoc($r)) {
                    $images[] = ['id'=>'event_'.$row['id'], 'source'=>'events', 'source_label'=>'이벤트', 'title'=>$row['title'], 'subtitle'=>$row['subtitle']??'', 'url'=>$row['image_url'], 'type'=>$row['category']??'', 'date'=>$row['created_at']];
                }
            }
            if ($source === 'all' || $source === 'event_images') {
                $sql = "SELECT ei.id, ei.image_url, ei.caption, e.title, ei.created_at FROM gangnamunni_event_images ei LEFT JOIN gangnamunni_events e ON ei.event_id = e.id WHERE ei.image_url IS NOT NULL AND ei.image_url != ''";
                if ($search) { $esc = mysqli_real_escape_string($connect, $search); $sql .= " AND (ei.caption LIKE '%{$esc}%' OR e.title LIKE '%{$esc}%')"; }
                $r = mysqli_query($connect, $sql);
                if ($r) while ($row = mysqli_fetch_assoc($r)) {
                    $images[] = ['id'=>'ei_'.$row['id'], 'source'=>'event_images', 'source_label'=>'이벤트갤러리', 'title'=>$row['title']??$row['caption']??'', 'subtitle'=>$row['caption']??'', 'url'=>$row['image_url'], 'type'=>'', 'date'=>$row['created_at']];
                }
            }
            if ($source === 'all' || $source === 'hospitals') {
                $sql = "SELECT id, name, profile_image_url, address, created_at FROM gangnamunni_hospitals WHERE profile_image_url IS NOT NULL AND profile_image_url != ''";
                if ($search) { $esc = mysqli_real_escape_string($connect, $search); $sql .= " AND (name LIKE '%{$esc}%' OR address LIKE '%{$esc}%')"; }
                $r = mysqli_query($connect, $sql);
                if ($r) while ($row = mysqli_fetch_assoc($r)) {
                    $images[] = ['id'=>'hosp_'.$row['id'], 'source'=>'hospitals', 'source_label'=>'병원', 'title'=>$row['name'], 'subtitle'=>$row['address']??'', 'url'=>$row['profile_image_url'], 'type'=>'', 'date'=>$row['created_at']??''];
                }
            }
            if ($source === 'all' || $source === 'uploads') {
                $upload_dir = __DIR__ . '/uploads/';
                if (is_dir($upload_dir)) {
                    $files = glob($upload_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                    foreach ($files as $file) {
                        $fname = basename($file);
                        if ($search && stripos($fname, $search) === false) continue;
                        $images[] = ['id'=>'upload_'.md5($fname), 'source'=>'uploads', 'source_label'=>'업로드', 'title'=>$fname, 'subtitle'=>'', 'url'=>'uploads/'.$fname, 'type'=>'', 'date'=>date('Y-m-d H:i:s', filemtime($file))];
                    }
                }
            }

            // 정렬
            usort($images, function($a, $b) use ($sort) {
                switch ($sort) {
                    case 'name_asc': return strcmp(mb_strtolower($a['title']), mb_strtolower($b['title']));
                    case 'name_desc': return strcmp(mb_strtolower($b['title']), mb_strtolower($a['title']));
                    case 'date_asc': return strcmp($a['date'] ?? '', $b['date'] ?? '');
                    case 'date_desc': default: return strcmp($b['date'] ?? '', $a['date'] ?? '');
                }
            });

            // 페이지네이션
            $total = count($images);
            $total_pages = max(1, ceil($total / $per_page));
            $page = min($page, $total_pages);
            $offset = ($page - 1) * $per_page;
            $images = array_slice($images, $offset, $per_page);

            echo json_encode(['success'=>true, 'images'=>$images, 'total'=>$total, 'page'=>$page, 'total_pages'=>$total_pages, 'per_page'=>$per_page], JSON_UNESCAPED_UNICODE);
            exit;

        case 'save_edited':
            // 편집된 이미지 저장
            $image_data = $_POST['image_data'] ?? '';
            $filename = $_POST['filename'] ?? 'edited_' . time();
            $format = $_POST['format'] ?? 'png';
            $target = $_POST['target'] ?? 'new'; // new, ad_{id}, event_{id}

            if (empty($image_data)) {
                echo json_encode(['success' => false, 'message' => '이미지 데이터가 없습니다.']);
                exit;
            }

            // base64 디코드
            $data = explode(',', $image_data);
            $binary = base64_decode(end($data));

            // 파일 저장
            $upload_dir = __DIR__ . '/uploads/edited/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $filename);
            if (empty($safe_name)) $safe_name = 'edited_' . time();
            $ext = $format === 'jpg' ? 'jpg' : 'png';
            $file_path = $upload_dir . $safe_name . '.' . $ext;
            $rel_path = 'uploads/edited/' . $safe_name . '.' . $ext;

            file_put_contents($file_path, $binary);

            // 대상 업데이트
            if (preg_match('/^ad_(\d+)$/', $target, $m)) {
                $id = intval($m[1]);
                $esc_path = mysqli_real_escape_string($connect, $rel_path);
                mysqli_query($connect, "UPDATE gangnamunni_ads SET image_url = '{$esc_path}' WHERE id = {$id}");
            } elseif (preg_match('/^event_(\d+)$/', $target, $m)) {
                $id = intval($m[1]);
                $esc_path = mysqli_real_escape_string($connect, $rel_path);
                mysqli_query($connect, "UPDATE gangnamunni_events SET image_url = '{$esc_path}' WHERE id = {$id}");
            }

            echo json_encode([
                'success' => true,
                'message' => '이미지 저장 완료',
                'url' => $rel_path,
                'size' => filesize($file_path)
            ], JSON_UNESCAPED_UNICODE);
            exit;

        case 'upload_image':
            // 새 이미지 업로드
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => '파일 업로드 오류']);
                exit;
            }
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                echo json_encode(['success' => false, 'message' => '허용되지 않는 파일 형식']);
                exit;
            }
            $fname = 'upload_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $dest = $upload_dir . $fname;
            move_uploaded_file($_FILES['image']['tmp_name'], $dest);
            echo json_encode(['success' => true, 'url' => 'uploads/' . $fname], JSON_UNESCAPED_UNICODE);
            exit;

        case 'generate_ai_image':
            // AI 이미지 생성 (DALL-E)
            $prompt = trim($_POST['prompt'] ?? '');
            $size = $_POST['size'] ?? '1024x1024';

            if (empty($prompt)) {
                echo json_encode(['success' => false, 'message' => '프롬프트를 입력해주세요.']);
                exit;
            }

            if (function_exists('generateImage')) {
                $result = generateImage($prompt, ['size' => $size]);
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'AI 모듈 미설정']);
            }
            exit;

        case 'delete_images':
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            if (!is_array($ids) || empty($ids)) {
                echo json_encode(['success' => false, 'message' => '삭제할 이미지가 없습니다.']);
                exit;
            }
            $deleted = 0;
            foreach ($ids as $id) {
                if (preg_match('/^ad_(\d+)$/', $id, $m)) {
                    mysqli_query($connect, "UPDATE gangnamunni_ads SET image_url = NULL WHERE id = " . intval($m[1]));
                    $deleted++;
                } elseif (preg_match('/^event_(\d+)$/', $id, $m)) {
                    mysqli_query($connect, "UPDATE gangnamunni_events SET image_url = NULL WHERE id = " . intval($m[1]));
                    $deleted++;
                } elseif (preg_match('/^ei_(\d+)$/', $id, $m)) {
                    $r = mysqli_query($connect, "SELECT image_url FROM gangnamunni_event_images WHERE id = " . intval($m[1]));
                    if ($r && $row = mysqli_fetch_assoc($r)) {
                        $fp = __DIR__ . '/' . $row['image_url'];
                        if (file_exists($fp)) @unlink($fp);
                    }
                    mysqli_query($connect, "DELETE FROM gangnamunni_event_images WHERE id = " . intval($m[1]));
                    $deleted++;
                } elseif (preg_match('/^hosp_(\d+)$/', $id, $m)) {
                    mysqli_query($connect, "UPDATE gangnamunni_hospitals SET profile_image_url = NULL WHERE id = " . intval($m[1]));
                    $deleted++;
                } elseif (strpos($id, 'upload_') === 0) {
                    $upload_dir = __DIR__ . '/uploads/';
                    if (is_dir($upload_dir)) {
                        $files = glob($upload_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                        foreach ($files as $file) {
                            if (md5(basename($file)) === substr($id, 7)) {
                                @unlink($file);
                                $deleted++;
                                break;
                            }
                        }
                    }
                }
            }
            echo json_encode(['success' => true, 'deleted' => $deleted], JSON_UNESCAPED_UNICODE);
            exit;

        case 'ai_enhance_prompt':
            $prompt = trim($_POST['prompt'] ?? '');
            if (empty($prompt)) { echo json_encode(['success'=>false,'message'=>'프롬프트를 입력하세요.']); exit; }
            if (file_exists(__DIR__ . '/includes/ai_proxy.php')) {
                require_once __DIR__ . '/includes/ai_proxy.php';
                $result = callAI('prompt_enhance', "다음 한국어 설명을 DALL-E 3 이미지 생성에 최적화된 영문 프롬프트로 변환해주세요. 구체적이고 시각적으로 풍부한 묘사를 포함하세요. 프롬프트만 출력하세요:\n\n{$prompt}", ['max_tokens' => 500]);
                if ($result['success']) {
                    echo json_encode(['success'=>true, 'enhanced_prompt'=>trim($result['content'])], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success'=>false, 'message'=>$result['error'] ?? 'AI 호출 실패']);
                }
            } else {
                echo json_encode(['success'=>false, 'message'=>'AI 모듈 미설정']);
            }
            exit;

        case 'ai_generate_copy':
            $keyword = trim($_POST['keyword'] ?? '');
            $tone = trim($_POST['tone'] ?? '전문적');
            if (empty($keyword)) { echo json_encode(['success'=>false,'message'=>'키워드를 입력하세요.']); exit; }
            if (file_exists(__DIR__ . '/includes/ai_proxy.php')) {
                require_once __DIR__ . '/includes/ai_proxy.php';
                $sys = "당신은 한국 미용/의료 마케팅 카피라이터입니다. 짧고 임팩트 있는 광고 문구를 생성합니다.";
                $msg = "키워드: {$keyword}\n톤: {$tone}\n\n위 키워드로 배너/광고에 사용할 짧은 문구 5개를 생성하세요. 각 문구는 15자 이내로 작성하고, JSON 배열로만 출력하세요. 예: [\"문구1\",\"문구2\",...]";
                $result = callAI('ad_copy', $msg, ['system' => $sys, 'max_tokens' => 300]);
                if ($result['success']) {
                    $content = trim($result['content']);
                    $copies = json_decode($content, true);
                    if (!$copies) {
                        preg_match_all('/"([^"]+)"/', $content, $mm);
                        $copies = $mm[1] ?? [$content];
                    }
                    echo json_encode(['success'=>true, 'copies'=>array_slice($copies, 0, 5)], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success'=>false, 'message'=>$result['error'] ?? 'AI 호출 실패']);
                }
            } else {
                echo json_encode(['success'=>false, 'message'=>'AI 모듈 미설정']);
            }
            exit;

        case 'ai_describe_image':
            $image_data = $_POST['image_data'] ?? '';
            if (empty($image_data)) { echo json_encode(['success'=>false,'message'=>'이미지 데이터가 없습니다.']); exit; }
            if (file_exists(__DIR__ . '/includes/ai_proxy.php')) {
                require_once __DIR__ . '/includes/ai_proxy.php';
                $result = callAI('image_describe', "이 이미지를 분석하여 다음을 한국어로 생성해주세요:\n1. 이미지 설명 (1줄)\n2. SEO 태그 5개 (쉼표 구분)\n3. 추천 광고 문구 2개\n\nJSON으로 출력: {\"description\":\"...\",\"tags\":\"...\",\"copies\":[\"...\",\"...\"]}", ['image_data' => $image_data, 'max_tokens' => 500]);
                if ($result['success']) {
                    $parsed = json_decode(trim($result['content']), true);
                    if (!$parsed) $parsed = ['description' => trim($result['content']), 'tags' => '', 'copies' => []];
                    echo json_encode(['success'=>true, 'data'=>$parsed], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success'=>false, 'message'=>$result['error'] ?? 'AI 호출 실패']);
                }
            } else {
                echo json_encode(['success'=>false, 'message'=>'AI 모듈 미설정']);
            }
            exit;

        case 'proxy_image':
            // 외부 이미지 CORS 프록시
            $url = $_POST['url'] ?? '';
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'message' => '유효하지 않은 URL']);
                exit;
            }
            $allowed = ['http', 'https'];
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array($scheme, $allowed)) {
                echo json_encode(['success' => false, 'message' => '허용되지 않는 프로토콜']);
                exit;
            }
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if ($httpCode === 200 && $data) {
                $b64 = base64_encode($data);
                $mime = preg_match('/^image\//', $contentType) ? $contentType : 'image/png';
                echo json_encode(['success' => true, 'dataUrl' => "data:{$mime};base64,{$b64}"]);
            } else {
                echo json_encode(['success' => false, 'message' => '이미지 다운로드 실패 (HTTP ' . $httpCode . ')']);
            }
            exit;
    }
    exit;
}

// 관리자 레이아웃
$admin_page_title = 'AI 이미지 에디터';
$admin_current = 'ai_image_editor';

$admin_extra_css = '
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js"></script>
<style>
:root {
    --primary: #AC1830;
    --purple: #AC1830;
    --dark: #1a1a1a;
    --gray: #6b7280;
    --light: #f3f4f6;
    --border: #e5e7eb;
}

/* 이미지 에디터 전용: admin-main 제약 해제 */
.admin-main {
    padding: 0 !important;
    overflow: hidden;
}
.admin-main > .editor-layout {
    max-width: none;
}

.editor-layout {
    display: grid;
    grid-template-columns: 380px 1fr 220px;
    gap: 0;
    height: calc(100vh - 50px);
    overflow: hidden;
}

/* 좌측: 이미지 브라우저 */
.image-browser {
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: white;
    overflow: hidden;
}
.browser-header {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.browser-search {
    padding: 10px 16px;
    border-bottom: 1px solid var(--border);
}
.browser-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}
.browser-search input:focus {
    border-color: var(--primary);
}
.browser-filters {
    padding: 8px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.browser-filter {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border);
    background: white;
    color: var(--gray);
    transition: all 0.15s;
}
/* 브라우저 툴바 (전체선택/삭제/정렬/페이지) */
.browser-toolbar {
    padding: 6px 10px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 4px;
    flex-wrap: wrap;
}
.browser-toolbar .tb-left {
    display: flex;
    align-items: center;
    gap: 4px;
}
.browser-toolbar .tb-right {
    display: flex;
    align-items: center;
    gap: 4px;
}
.browser-toolbar button, .browser-toolbar select {
    padding: 3px 8px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 11px;
    background: white;
    cursor: pointer;
    white-space: nowrap;
}
.browser-toolbar button:hover {
    background: var(--light);
}
.browser-toolbar button.danger {
    color: #EF4444;
    border-color: #FCA5A5;
}
.browser-toolbar button.danger:hover {
    background: #FEF2F2;
}
.browser-toolbar select {
    font-size: 11px;
    outline: none;
}
/* 체크박스 */
.browser-item .item-check {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: var(--primary);
    margin-top: 20px;
}
/* 페이지네이션 */
.browser-pagination {
    padding: 8px 16px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 12px;
    color: var(--gray);
}
.browser-pagination button {
    padding: 4px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: white;
    cursor: pointer;
    font-size: 12px;
}
.browser-pagination button:hover {
    background: var(--light);
}
.browser-pagination button:disabled {
    opacity: 0.4;
    cursor: default;
}
/* AI 카피 생성 섹션 */
.ai-copy-section {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
}
.ai-copy-section h4 {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.ai-copy-input {
    display: flex;
    gap: 4px;
    margin-bottom: 6px;
}
.ai-copy-input input {
    flex: 1;
    padding: 6px 8px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 12px;
    outline: none;
    box-sizing: border-box;
}
.ai-copy-input input:focus {
    border-color: var(--primary);
}
.ai-copy-input button {
    padding: 6px 10px;
    border: none;
    background: var(--primary);
    color: white;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.ai-copy-results {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.ai-copy-item {
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.15s;
}
.ai-copy-item:hover {
    background: #FFF7ED;
    border-color: var(--primary);
}

.browser-filter.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.browser-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
}
.browser-item {
    display: flex;
    gap: 10px;
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
    margin-bottom: 4px;
    transition: background 0.15s;
}
.browser-item:hover {
    background: #f9fafb;
}
.browser-item.selected {
    background: #FFF7ED;
    outline: 2px solid var(--primary);
}
.browser-item .thumb {
    width: 56px;
    height: 56px;
    border-radius: 8px;
    background: var(--light);
    overflow: hidden;
    flex-shrink: 0;
}
.browser-item .thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.browser-item .info {
    flex: 1;
    min-width: 0;
}
.browser-item .info .title {
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.browser-item .info .meta {
    font-size: 11px;
    color: var(--gray);
    margin-top: 2px;
}
.browser-item .info .source-tag {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    margin-top: 4px;
}
.source-tag.ads { background: #FEF3C7; color: #92400E; }
.source-tag.events { background: #DBEAFE; color: #1E40AF; }
.source-tag.event_images { background: #E0E7FF; color: #3730A3; }
.source-tag.hospitals { background: #D1FAE5; color: #065F46; }
.source-tag.uploads { background: #F3E8FF; color: #6B21A8; }

.browser-actions {
    padding: 12px 16px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
}
.browser-actions button {
    flex: 1;
    padding: 8px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border);
    background: white;
    transition: all 0.15s;
}
.browser-actions button:hover {
    background: var(--light);
}
.browser-actions button.primary {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.browser-actions button.primary:hover {
    background: #E64D0D;
}

/* 중앙: 캔버스 영역 */
.canvas-area {
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
    overflow: hidden;
}

/* 툴바 */
.editor-toolbar {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    background: white;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}
.toolbar-group {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 0 6px;
    border-right: 1px solid var(--border);
}
.toolbar-group:last-child {
    border-right: none;
}
.tool-btn {
    padding: 8px 10px;
    border: none;
    background: transparent;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    color: var(--gray);
    transition: all 0.15s;
    position: relative;
}
.tool-btn:hover {
    background: var(--light);
    color: var(--dark);
}
.tool-btn.active {
    background: #FFF7ED;
    color: var(--primary);
}
.tool-btn .label {
    font-size: 10px;
    display: block;
    margin-top: 2px;
}

/* 캔버스 래퍼 */
.canvas-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: auto;
    padding: 20px;
}
.canvas-container {
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    background: white;
}

/* 상태바 */
.status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 16px;
    background: white;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--gray);
}
.zoom-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}
.zoom-controls button {
    padding: 2px 8px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 12px;
}

/* 우측: 속성 패널 */
.props-panel {
    border-left: 1px solid var(--border);
    background: white;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 50px);
}
.props-header {
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    font-weight: 700;
    font-size: 14px;
}
.props-section {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
}
.props-section h4 {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.prop-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}
.prop-row label {
    font-size: 12px;
    color: var(--gray);
    width: 45px;
    flex-shrink: 0;
}
.prop-row input[type="text"],
.prop-row input[type="number"],
.prop-row select {
    flex: 1;
    padding: 6px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 12px;
    outline: none;
    box-sizing: border-box;
}
.prop-row input:focus,
.prop-row select:focus {
    border-color: var(--primary);
}
.prop-row input[type="color"] {
    width: 32px;
    height: 32px;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 2px;
    cursor: pointer;
}
.prop-row input[type="range"] {
    flex: 1;
    accent-color: var(--primary);
}

/* 프리셋 뱃지 */
.badge-presets {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.badge-preset {
    padding: 6px 8px;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid var(--border);
    transition: all 0.15s;
}
.badge-preset:hover {
    transform: scale(1.05);
}

/* 필터 프리셋 */
.filter-presets {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px;
}
.filter-preset {
    padding: 6px 8px;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid var(--border);
    background: white;
    transition: all 0.15s;
}
.filter-preset:hover {
    background: var(--light);
}
.filter-preset.active {
    background: #FFF7ED;
    border-color: var(--primary);
    color: var(--primary);
}

/* 저장 패널 */
.save-panel {
    padding: 10px 14px;
    border-top: 1px solid var(--border);
}
.save-panel .save-btn {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    border: none;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    margin-bottom: 6px;
    transition: all 0.15s;
}
.save-btn.primary {
    background: var(--primary);
    color: white;
}
.save-btn.primary:hover {
    background: #E64D0D;
}
.save-btn.secondary {
    background: white;
    color: var(--dark);
    border: 1px solid var(--border);
}
.save-btn.secondary:hover {
    background: var(--light);
}

/* 모달 */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
}
.modal-overlay.show {
    display: flex;
}
.modal-box {
    background: white;
    border-radius: 16px;
    padding: 24px;
    max-width: 480px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}
.modal-box h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
}
.modal-box .form-group {
    margin-bottom: 14px;
}
.modal-box label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    color: var(--gray);
}
.modal-box input, .modal-box textarea, .modal-box select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
}
.modal-box textarea { height: 80px; resize: vertical; }
.modal-box input:focus, .modal-box textarea:focus, .modal-box select:focus {
    border-color: var(--primary);
}
.modal-actions {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}
.modal-actions button {
    flex: 1;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border);
}
.modal-actions .btn-primary {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* 로딩 */
.loading-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 100;
}
.loading-overlay.show {
    display: flex;
}
.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--border);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

/* 반응형 */
@media (max-width: 1200px) {
    .editor-layout {
        grid-template-columns: 340px 1fr 180px;
    }
}
@media (max-width: 900px) {
    .editor-layout {
        grid-template-columns: 1fr;
        height: auto;
    }
    .image-browser, .props-panel {
        max-height: 300px;
    }
}

/* ========== 다크모드 지원 ========== */
[data-bs-theme="dark"] {
    --dark: #e5e7eb;
    --gray: #9ca3af;
    --light: #2d3748;
    --border: #4a5568;
}

/* 주요 패널 배경 */
[data-bs-theme="dark"] .image-browser,
[data-bs-theme="dark"] .editor-toolbar,
[data-bs-theme="dark"] .status-bar,
[data-bs-theme="dark"] .props-panel {
    background: var(--bs-body-bg, #212529);
    color: var(--bs-body-color, #dee2e6);
}

/* 캔버스 영역 (작업 공간) */
[data-bs-theme="dark"] .canvas-area {
    background: var(--bs-tertiary-bg, #2b3035);
}

/* 버튼/컨트롤 배경 */
[data-bs-theme="dark"] .browser-filter,
[data-bs-theme="dark"] .browser-actions button,
[data-bs-theme="dark"] .zoom-controls button,
[data-bs-theme="dark"] .filter-preset,
[data-bs-theme="dark"] .save-btn.secondary,
[data-bs-theme="dark"] .modal-actions button {
    background: var(--bs-body-bg, #212529);
    color: var(--bs-body-color, #dee2e6);
}

/* 호버/선택/활성 상태 */
[data-bs-theme="dark"] .browser-item:hover {
    background: var(--bs-tertiary-bg, #2b3035);
}
[data-bs-theme="dark"] .browser-item.selected,
[data-bs-theme="dark"] .tool-btn.active,
[data-bs-theme="dark"] .filter-preset.active {
    background: rgba(255, 84, 15, 0.15);
}

/* 입력 필드 */
[data-bs-theme="dark"] .browser-search input,
[data-bs-theme="dark"] .prop-row input[type="text"],
[data-bs-theme="dark"] .prop-row input[type="number"],
[data-bs-theme="dark"] .prop-row select,
[data-bs-theme="dark"] .modal-box input,
[data-bs-theme="dark"] .modal-box textarea,
[data-bs-theme="dark"] .modal-box select {
    background: var(--bs-tertiary-bg, #2b3035);
    color: var(--bs-body-color, #dee2e6);
    border-color: var(--border);
}
[data-bs-theme="dark"] input::placeholder,
[data-bs-theme="dark"] textarea::placeholder {
    color: var(--gray);
}

/* 모달 */
[data-bs-theme="dark"] .modal-box {
    background: var(--bs-body-bg, #212529);
    color: var(--bs-body-color, #dee2e6);
}

/* 로딩 오버레이 */
[data-bs-theme="dark"] .loading-overlay {
    background: rgba(33, 37, 41, 0.85);
}

/* 캔버스 그림자 강화 */
[data-bs-theme="dark"] .canvas-container {
    box-shadow: 0 4px 24px rgba(0,0,0,0.4);
}

/* 소스 태그 (다크모드용 반투명) */
[data-bs-theme="dark"] .source-tag.ads { background: rgba(254,243,199,0.2); color: #FCD34D; }
[data-bs-theme="dark"] .source-tag.events { background: rgba(219,234,254,0.2); color: #93C5FD; }
[data-bs-theme="dark"] .source-tag.event_images { background: rgba(224,231,255,0.2); color: #A5B4FC; }
[data-bs-theme="dark"] .source-tag.hospitals { background: rgba(209,250,229,0.2); color: #6EE7B7; }
[data-bs-theme="dark"] .source-tag.uploads { background: rgba(243,232,255,0.2); color: #C4B5FD; }

/* 스크롤바 */
[data-bs-theme="dark"] .browser-list::-webkit-scrollbar { width: 6px; }
[data-bs-theme="dark"] .browser-list::-webkit-scrollbar-track { background: var(--bs-tertiary-bg, #2b3035); }
[data-bs-theme="dark"] .browser-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }

/* 다크모드: 추가 요소 */
[data-bs-theme="dark"] .browser-toolbar button,
[data-bs-theme="dark"] .browser-toolbar select,
[data-bs-theme="dark"] .browser-pagination button {
    background: var(--bs-body-bg, #212529);
    color: var(--bs-body-color, #dee2e6);
    border-color: var(--border);
}
[data-bs-theme="dark"] .ai-copy-input input {
    background: var(--bs-tertiary-bg, #2b3035);
    color: var(--bs-body-color, #dee2e6);
    border-color: var(--border);
}
[data-bs-theme="dark"] .ai-copy-item {
    background: var(--bs-body-bg, #212529);
    color: var(--bs-body-color, #dee2e6);
    border-color: var(--border);
}
[data-bs-theme="dark"] .ai-copy-item:hover {
    background: rgba(255, 84, 15, 0.15);
}
</style>
';

$admin_extra_js = '';

include __DIR__ . '/includes/admin_header.php';
?>

<div class="editor-layout">
    <!-- 좌측: 이미지 브라우저 -->
    <div class="image-browser">
        <div class="browser-header">
            <i class="fas fa-images"></i> 이미지 라이브러리
        </div>
        <div class="browser-search">
            <input type="text" id="imageSearch" placeholder="이미지 검색..." oninput="debounceSearch()">
        </div>
        <div class="browser-toolbar">
            <div class="tb-left">
                <button onclick="checkAll()" title="전체선택"><i class="fas fa-check-double"></i></button>
                <button onclick="uncheckAll()" title="전체해제"><i class="fas fa-square"></i></button>
                <button class="danger" onclick="deleteCheckedImages()" title="선택삭제"><i class="fas fa-trash"></i> 삭제</button>
            </div>
            <div class="tb-right">
                <select id="sortOrder" onchange="currentPage=1;loadImages()">
                    <option value="date_desc">최신순</option>
                    <option value="date_asc">오래된순</option>
                    <option value="name_asc">이름↑</option>
                    <option value="name_desc">이름↓</option>
                </select>
                <select id="perPage" onchange="currentPage=1;loadImages()">
                    <option value="20">20개</option>
                    <option value="50">50개</option>
                    <option value="100">100개</option>
                </select>
            </div>
        </div>
        <div class="browser-filters">
            <button class="browser-filter active" data-source="all" onclick="filterSource(this)">전체</button>
            <button class="browser-filter" data-source="ads" onclick="filterSource(this)">광고</button>
            <button class="browser-filter" data-source="events" onclick="filterSource(this)">이벤트</button>
            <button class="browser-filter" data-source="hospitals" onclick="filterSource(this)">병원</button>
            <button class="browser-filter" data-source="uploads" onclick="filterSource(this)">업로드</button>
        </div>
        <div class="browser-list" id="browserList">
            <div style="text-align: center; padding: 40px 20px; color: var(--gray);">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                <p style="margin-top: 10px; font-size: 13px;">이미지 로딩 중...</p>
            </div>
        </div>
        <div class="browser-pagination">
            <button onclick="goPage(-1)" id="btnPrev" disabled>◀</button>
            <span id="pageInfo">1 / 1</span>
            <button onclick="goPage(1)" id="btnNext" disabled>▶</button>
            <span id="totalCount" style="margin-left:8px;font-size:11px;">0건</span>
        </div>
        <div class="browser-actions">
            <button onclick="document.getElementById('fileUpload').click()">
                <i class="fas fa-upload"></i> 업로드
            </button>
            <button onclick="loadFromUrl()">
                <i class="fas fa-link"></i> URL
            </button>
            <button class="primary" onclick="showAiGenerateModal()">
                <i class="fas fa-wand-magic-sparkles"></i> AI 생성
            </button>
            <input type="file" id="fileUpload" accept="image/*" style="display:none" onchange="handleFileUpload(this)">
        </div>
    </div>

    <!-- 중앙: 캔버스 영역 -->
    <div class="canvas-area">
        <!-- 툴바 -->
        <div class="editor-toolbar">
            <div class="toolbar-group">
                <button class="tool-btn" onclick="editorAction('select')" title="선택" id="tool-select">
                    <i class="fas fa-arrow-pointer"></i>
                </button>
                <button class="tool-btn" onclick="editorAction('pan')" title="이동">
                    <i class="fas fa-hand"></i>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="tool-btn" onclick="addText()" title="텍스트">
                    <i class="fas fa-font"></i>
                </button>
                <button class="tool-btn" onclick="addRect()" title="사각형">
                    <i class="far fa-square"></i>
                </button>
                <button class="tool-btn" onclick="addCircle()" title="원형">
                    <i class="far fa-circle"></i>
                </button>
                <button class="tool-btn" onclick="addLine()" title="선">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="tool-btn" onclick="toggleDrawing()" title="자유 그리기" id="tool-draw">
                    <i class="fas fa-pen"></i>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="tool-btn" onclick="addBadge('할인')" title="할인 배지">
                    <i class="fas fa-tag"></i>
                </button>
                <button class="tool-btn" onclick="addBadge('NEW')" title="NEW 배지">
                    <i class="fas fa-certificate"></i>
                </button>
                <button class="tool-btn" onclick="addBadge('HOT')" title="HOT 배지">
                    <i class="fas fa-fire"></i>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="tool-btn" onclick="cropImage()" title="자르기">
                    <i class="fas fa-crop-simple"></i>
                </button>
                <button class="tool-btn" onclick="flipH()" title="좌우 반전">
                    <i class="fas fa-arrows-left-right"></i>
                </button>
                <button class="tool-btn" onclick="flipV()" title="상하 반전">
                    <i class="fas fa-arrows-up-down"></i>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="tool-btn" onclick="editorAction('undo')" title="실행 취소">
                    <i class="fas fa-undo"></i>
                </button>
                <button class="tool-btn" onclick="editorAction('redo')" title="다시 실행">
                    <i class="fas fa-redo"></i>
                </button>
            </div>
            <div class="toolbar-group">
                <button class="tool-btn" onclick="deleteSelected()" title="선택 삭제">
                    <i class="fas fa-trash"></i>
                </button>
                <button class="tool-btn" onclick="clearCanvas()" title="전체 지우기">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>
        </div>

        <!-- 캔버스 -->
        <div class="canvas-wrapper" id="canvasWrapper">
            <div style="text-align: center; color: var(--gray);">
                <i class="fas fa-image" style="font-size: 64px; margin-bottom: 16px; color: var(--border);"></i>
                <p style="font-size: 15px;">좌측에서 이미지를 선택하거나 업로드하세요</p>
                <p style="font-size: 13px; margin-top: 8px;">또는 AI 생성 버튼으로 새 이미지를 만드세요</p>
            </div>
        </div>

        <!-- 상태바 -->
        <div class="status-bar">
            <span id="statusText">이미지를 선택하세요</span>
            <div class="zoom-controls">
                <button onclick="zoomCanvas(-0.1)">-</button>
                <span id="zoomLevel">100%</span>
                <button onclick="zoomCanvas(0.1)">+</button>
                <button onclick="zoomCanvas(0, true)">맞춤</button>
            </div>
        </div>
    </div>

    <!-- 우측: 속성 패널 -->
    <div class="props-panel">
        <div class="props-header">
            <i class="fas fa-sliders"></i> 속성
        </div>

        <!-- 객체 속성 -->
        <div class="props-section" id="objectProps" style="display: none;">
            <h4>선택 객체</h4>
            <div class="prop-row">
                <label>색상</label>
                <input type="color" id="propFill" value="#AC1830" onchange="updateProp('fill', this.value)">
            </div>
            <div class="prop-row">
                <label>테두리</label>
                <input type="color" id="propStroke" value="#000000" onchange="updateProp('stroke', this.value)">
                <input type="number" id="propStrokeWidth" value="0" min="0" max="20" style="width:50px" onchange="updateProp('strokeWidth', parseInt(this.value))">
            </div>
            <div class="prop-row">
                <label>투명도</label>
                <input type="range" id="propOpacity" min="0" max="100" value="100" oninput="updateProp('opacity', this.value/100)">
            </div>
            <div class="prop-row">
                <label>회전</label>
                <input type="range" id="propAngle" min="0" max="360" value="0" oninput="updateProp('angle', parseInt(this.value))">
            </div>
        </div>

        <!-- 텍스트 속성 -->
        <div class="props-section" id="textProps" style="display: none;">
            <h4>텍스트</h4>
            <div class="prop-row">
                <label>폰트</label>
                <select id="propFont" onchange="updateProp('fontFamily', this.value)">
                    <option value="Arial">Arial</option>
                    <option value="Noto Sans KR">Noto Sans KR</option>
                    <option value="Nanum Gothic">나눔고딕</option>
                    <option value="Georgia">Georgia</option>
                    <option value="Impact">Impact</option>
                    <option value="Courier New">Courier</option>
                </select>
            </div>
            <div class="prop-row">
                <label>크기</label>
                <input type="number" id="propFontSize" value="24" min="8" max="200" onchange="updateProp('fontSize', parseInt(this.value))">
            </div>
            <div class="prop-row">
                <button class="tool-btn" onclick="updateProp('fontWeight', 'bold')" title="굵게"><i class="fas fa-bold"></i></button>
                <button class="tool-btn" onclick="updateProp('fontStyle', 'italic')" title="기울임"><i class="fas fa-italic"></i></button>
                <button class="tool-btn" onclick="updateProp('underline', true)" title="밑줄"><i class="fas fa-underline"></i></button>
                <button class="tool-btn" onclick="updateProp('textAlign', 'center')" title="가운데"><i class="fas fa-align-center"></i></button>
            </div>
            <div class="prop-row">
                <label>배경</label>
                <input type="color" id="propTextBg" value="#ffffff" onchange="updateProp('textBackgroundColor', this.value)">
                <button class="tool-btn" onclick="updateProp('textBackgroundColor', '')" title="배경 제거" style="font-size:11px;">없음</button>
            </div>
        </div>

        <!-- 뱃지 프리셋 -->
        <div class="props-section">
            <h4>빠른 배지</h4>
            <div class="badge-presets">
                <div class="badge-preset" style="background:#AC1830;color:white;" onclick="addBadge('할인')">할인</div>
                <div class="badge-preset" style="background:#EF4444;color:white;" onclick="addBadge('HOT')">HOT</div>
                <div class="badge-preset" style="background:#10B981;color:white;" onclick="addBadge('NEW')">NEW</div>
                <div class="badge-preset" style="background:#AC1830;color:white;" onclick="addBadge('응모')">응모</div>
                <div class="badge-preset" style="background:#F59E0B;color:white;" onclick="addBadge('추천')">추천</div>
                <div class="badge-preset" style="background:#3B82F6;color:white;" onclick="addBadge('무료')">무료</div>
                <div class="badge-preset" style="background:#1a1a1a;color:white;" onclick="addBadge('BEST')">BEST</div>
                <div class="badge-preset" style="background:#EC4899;color:white;" onclick="addBadge('한정')">한정</div>
                <div class="badge-preset" style="background:#059669;color:white;" onclick="addBadge('이벤트')">이벤트</div>
            </div>
        </div>

        <!-- 필터 -->
        <div class="props-section">
            <h4>이미지 필터</h4>
            <div class="filter-presets">
                <div class="filter-preset" onclick="applyFilter('none')">원본</div>
                <div class="filter-preset" onclick="applyFilter('grayscale')">흑백</div>
                <div class="filter-preset" onclick="applyFilter('sepia')">세피아</div>
                <div class="filter-preset" onclick="applyFilter('brightness')">밝게</div>
                <div class="filter-preset" onclick="applyFilter('contrast')">대비</div>
                <div class="filter-preset" onclick="applyFilter('blur')">블러</div>
                <div class="filter-preset" onclick="applyFilter('sharpen')">선명</div>
                <div class="filter-preset" onclick="applyFilter('invert')">반전</div>
            </div>
            <div class="prop-row" style="margin-top: 10px;">
                <label>밝기</label>
                <input type="range" id="filterBrightness" min="-100" max="100" value="0" oninput="adjustFilter('brightness', this.value)">
            </div>
            <div class="prop-row">
                <label>대비</label>
                <input type="range" id="filterContrast" min="-100" max="100" value="0" oninput="adjustFilter('contrast', this.value)">
            </div>
        </div>

        <!-- 캔버스 크기 -->
        <div class="props-section">
            <h4>캔버스 크기</h4>
            <div class="prop-row">
                <label>가로</label>
                <input type="number" id="canvasWidth" value="1024" min="100" max="4096">
                <label>세로</label>
                <input type="number" id="canvasHeight" value="1024" min="100" max="4096">
            </div>
            <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                <button class="tool-btn" onclick="setCanvasSize(1024,1024)" style="font-size:11px;">1:1</button>
                <button class="tool-btn" onclick="setCanvasSize(1792,1024)" style="font-size:11px;">16:9</button>
                <button class="tool-btn" onclick="setCanvasSize(1024,1792)" style="font-size:11px;">9:16</button>
                <button class="tool-btn" onclick="setCanvasSize(1200,628)" style="font-size:11px;">FB 광고</button>
                <button class="tool-btn" onclick="setCanvasSize(1080,1080)" style="font-size:11px;">인스타</button>
            </div>
        </div>

        <!-- AI 카피 생성 -->
        <div class="ai-copy-section">
            <h4><i class="fas fa-robot"></i> AI 카피 생성</h4>
            <div class="ai-copy-input">
                <input type="text" id="aiCopyKeyword" placeholder="키워드 입력...">
                <button onclick="aiGenerateCopy()"><i class="fas fa-wand-magic-sparkles"></i></button>
            </div>
            <div class="ai-copy-results" id="aiCopyResults"></div>
        </div>

        <!-- 저장 -->
        <div class="save-panel">
            <button class="save-btn primary" onclick="saveImage('new')">
                <i class="fas fa-save"></i> 새 이미지로 저장
            </button>
            <button class="save-btn secondary" id="saveUpdateBtn" style="display:none" onclick="saveImage('update')">
                <i class="fas fa-sync"></i> 원본 이미지 업데이트
            </button>
            <button class="save-btn secondary" onclick="downloadImage()">
                <i class="fas fa-download"></i> 다운로드
            </button>
        </div>
    </div>
</div>

<!-- AI 생성 모달 -->
<div class="modal-overlay" id="aiModal">
    <div class="modal-box">
        <h3><i class="fas fa-wand-magic-sparkles"></i> AI 이미지 생성</h3>
        <div class="form-group">
            <label>프롬프트 (영문 권장)</label>
            <textarea id="aiPrompt" placeholder="Beautiful Korean beauty clinic promotion banner, modern minimalist design, pink and white color scheme, professional medical aesthetics..."></textarea>
        </div>
        <div class="form-group">
            <label>크기</label>
            <select id="aiSize">
                <option value="1024x1024">1024x1024 (정사각형)</option>
                <option value="1792x1024">1792x1024 (가로형)</option>
                <option value="1024x1792">1024x1792 (세로형)</option>
            </select>
        </div>
        <div class="modal-actions">
            <button onclick="closeModal('aiModal')">취소</button>
            <button onclick="aiEnhancePrompt()" style="background:#AC1830;color:white;border-color:#AC1830;">
                <i class="fas fa-language"></i> 프롬프트 보정
            </button>
            <button class="btn-primary" onclick="generateAiImage()">
                <i class="fas fa-wand-magic-sparkles"></i> 생성
            </button>
        </div>
    </div>
</div>

<!-- 저장 모달 -->
<div class="modal-overlay" id="saveModal">
    <div class="modal-box">
        <h3><i class="fas fa-save"></i> 이미지 저장</h3>
        <div class="form-group">
            <label>파일명</label>
            <input type="text" id="saveFilename" placeholder="edited_image_001">
        </div>
        <div class="form-group">
            <label>형식</label>
            <select id="saveFormat">
                <option value="png">PNG (투명 배경 지원)</option>
                <option value="jpg">JPG (작은 파일 크기)</option>
            </select>
        </div>
        <div class="form-group" id="saveTargetGroup" style="display:none">
            <label>적용 대상</label>
            <select id="saveTarget">
                <option value="new">새 이미지로 저장</option>
            </select>
        </div>
        <div class="modal-actions">
            <button onclick="closeModal('saveModal')">취소</button>
            <button class="btn-primary" onclick="confirmSave()">
                <i class="fas fa-save"></i> 저장
            </button>
        </div>
    </div>
</div>

<script>
// == 전역 변수 ==
var canvas = null;
var currentZoom = 1;
var undoStack = [];
var redoStack = [];
var currentSource = 'all';
var loadedImageId = null;
var searchTimeout = null;
var currentPage = 1;
var totalPages = 1;

// == 초기화 ==
document.addEventListener('DOMContentLoaded', function() {
    loadImages();
});

function initCanvas(width, height) {
    var wrapper = document.getElementById('canvasWrapper');
    wrapper.innerHTML = '<canvas id="fabricCanvas"></canvas>';

    canvas = new fabric.Canvas('fabricCanvas', {
        width: width || 1024,
        height: height || 1024,
        backgroundColor: '#ffffff',
        preserveObjectStacking: true
    });

    canvas.on('selection:created', onObjectSelected);
    canvas.on('selection:updated', onObjectSelected);
    canvas.on('selection:cleared', onObjectDeselected);
    canvas.on('object:modified', saveUndoState);

    document.getElementById('canvasWidth').value = width || 1024;
    document.getElementById('canvasHeight').value = height || 1024;

    saveUndoState();
    updateStatus(width + ' x ' + height);
    zoomCanvas(0, true);
}

// == 이미지 브라우저 ==
function loadImages() {
    var search = document.getElementById('imageSearch').value.trim();
    var sort = document.getElementById('sortOrder').value;
    var perPage = document.getElementById('perPage').value;
    var fd = new FormData();
    fd.append('action', 'load_images');
    fd.append('source', currentSource);
    fd.append('search', search);
    fd.append('sort', sort);
    fd.append('page', currentPage);
    fd.append('per_page', perPage);

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                renderBrowserList(data.images);
                totalPages = data.total_pages || 1;
                currentPage = data.page || 1;
                document.getElementById('pageInfo').textContent = currentPage + ' / ' + totalPages;
                document.getElementById('totalCount').textContent = (data.total || 0) + '건';
                document.getElementById('btnPrev').disabled = (currentPage <= 1);
                document.getElementById('btnNext').disabled = (currentPage >= totalPages);
            }
        });
}

function renderBrowserList(images) {
    var list = document.getElementById('browserList');
    if (!images || images.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--gray);"><i class="fas fa-image" style="font-size:32px;margin-bottom:10px;color:var(--border);"></i><p style="font-size:13px;">이미지가 없습니다</p></div>';
        return;
    }

    var html = '';
    images.forEach(function(img) {
        var thumbUrl = img.url;
        html += '<div class="browser-item" data-id="' + img.id + '" data-url="' + escapeHtml(img.url) + '" onclick="selectBrowserItem(this)">';
        html += '<input type="checkbox" class="item-check" data-id="' + img.id + '" onclick="event.stopPropagation()">';
        html += '<div class="thumb"><img src="' + escapeHtml(thumbUrl) + '" onerror="this.parentNode.innerHTML=\'<div style=padding:14px;text-align:center;font-size:20px>🖼️</div>\'"></div>';
        html += '<div class="info">';
        html += '<div class="title">' + escapeHtml(img.title || '(제목 없음)') + '</div>';
        html += '<div class="meta">' + escapeHtml(img.type || '') + '</div>';
        html += '<span class="source-tag ' + img.source + '">' + escapeHtml(img.source_label) + '</span>';
        html += '</div>';
        html += '</div>';
    });
    list.innerHTML = html;
}

function selectBrowserItem(el) {
    document.querySelectorAll('.browser-item').forEach(function(item) {
        item.classList.remove('selected');
    });
    el.classList.add('selected');

    var url = el.getAttribute('data-url');
    var id = el.getAttribute('data-id');
    loadedImageId = id;
    loadImageToCanvas(url);

    // 업데이트 버튼 표시
    var updateBtn = document.getElementById('saveUpdateBtn');
    updateBtn.style.display = 'block';
    updateBtn.textContent = '원본 이미지 업데이트 (' + id.split('_')[0] + ')';
}

function loadImageToCanvas(url) {
    updateStatus('이미지 로딩 중...');

    // 외부 URL이면 서버 프록시를 통해 로드 (CORS 우회)
    if (url.startsWith('http') && !url.startsWith(location.origin)) {
        var fd = new FormData();
        fd.append('action', 'proxy_image');
        fd.append('url', url);
        fetch(location.href, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    renderImageOnCanvas(data.dataUrl);
                } else {
                    updateStatus('외부 이미지 로드 실패: ' + (data.message || ''));
                }
            })
            .catch(function() { updateStatus('프록시 요청 실패'); });
        return;
    }

    // data: URL 또는 로컬 상대경로
    renderImageOnCanvas(url);
}

function renderImageOnCanvas(url) {
    fabric.Image.fromURL(url, function(img) {
        if (!img) {
            updateStatus('이미지 로드 실패');
            return;
        }

        var w = img.width || 1024;
        var h = img.height || 1024;

        // 최대 크기 제한
        if (w > 2048) { h = Math.round(h * 2048 / w); w = 2048; }
        if (h > 2048) { w = Math.round(w * 2048 / h); h = 2048; }

        initCanvas(w, h);

        img.set({
            left: 0,
            top: 0,
            selectable: false,
            evented: false
        });
        img.scaleToWidth(w);
        canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
        saveUndoState();
        updateStatus(w + ' x ' + h + ' 로드됨');
    }, { crossOrigin: 'anonymous' });
}

function filterSource(btn) {
    document.querySelectorAll('.browser-filter').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    currentSource = btn.getAttribute('data-source');
    loadImages();
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadImages, 300);
}

// == 편집 도구 ==
function addText() {
    if (!canvas) { initCanvas(1024, 1024); }
    var text = new fabric.IText('텍스트 입력', {
        left: canvas.width / 2 - 80,
        top: canvas.height / 2 - 20,
        fontSize: 36,
        fontFamily: 'Arial',
        fill: '#AC1830',
        fontWeight: 'bold',
        shadow: 'rgba(0,0,0,0.3) 2px 2px 4px',
        padding: 8
    });
    canvas.add(text);
    canvas.setActiveObject(text);
    canvas.renderAll();
    saveUndoState();
}

function addRect() {
    if (!canvas) { initCanvas(1024, 1024); }
    var rect = new fabric.Rect({
        left: canvas.width / 2 - 75,
        top: canvas.height / 2 - 50,
        width: 150,
        height: 100,
        fill: 'transparent',
        stroke: '#AC1830',
        strokeWidth: 3,
        rx: 10,
        ry: 10
    });
    canvas.add(rect);
    canvas.setActiveObject(rect);
    canvas.renderAll();
    saveUndoState();
}

function addCircle() {
    if (!canvas) { initCanvas(1024, 1024); }
    var circle = new fabric.Circle({
        left: canvas.width / 2 - 50,
        top: canvas.height / 2 - 50,
        radius: 50,
        fill: 'transparent',
        stroke: '#AC1830',
        strokeWidth: 3
    });
    canvas.add(circle);
    canvas.setActiveObject(circle);
    canvas.renderAll();
    saveUndoState();
}

function addLine() {
    if (!canvas) { initCanvas(1024, 1024); }
    var line = new fabric.Line([100, canvas.height / 2, canvas.width - 100, canvas.height / 2], {
        stroke: '#1a1a1a',
        strokeWidth: 3
    });
    canvas.add(line);
    canvas.setActiveObject(line);
    canvas.renderAll();
    saveUndoState();
}

function addBadge(text) {
    if (!canvas) { initCanvas(1024, 1024); }

    var colors = {
        '할인': { bg: '#AC1830', fg: '#fff' },
        'HOT': { bg: '#EF4444', fg: '#fff' },
        'NEW': { bg: '#10B981', fg: '#fff' },
        '응모': { bg: '#AC1830', fg: '#fff' },
        '추천': { bg: '#F59E0B', fg: '#fff' },
        '무료': { bg: '#3B82F6', fg: '#fff' },
        'BEST': { bg: '#1a1a1a', fg: '#fff' },
        '한정': { bg: '#EC4899', fg: '#fff' },
        '이벤트': { bg: '#059669', fg: '#fff' }
    };

    var c = colors[text] || { bg: '#AC1830', fg: '#fff' };
    var displayText = text;
    if (text === '할인') displayText = '20% 할인';

    var badge = new fabric.Group([
        new fabric.Rect({
            width: displayText.length * 18 + 30,
            height: 36,
            rx: 18,
            ry: 18,
            fill: c.bg,
            originX: 'center',
            originY: 'center'
        }),
        new fabric.Text(displayText, {
            fontSize: 16,
            fontWeight: 'bold',
            fill: c.fg,
            fontFamily: 'Arial',
            originX: 'center',
            originY: 'center'
        })
    ], {
        left: 20,
        top: 20
    });

    canvas.add(badge);
    canvas.setActiveObject(badge);
    canvas.renderAll();
    saveUndoState();
}

function toggleDrawing() {
    if (!canvas) return;
    canvas.isDrawingMode = !canvas.isDrawingMode;
    var btn = document.getElementById('tool-draw');
    btn.classList.toggle('active', canvas.isDrawingMode);
    if (canvas.isDrawingMode) {
        canvas.freeDrawingBrush.width = 3;
        canvas.freeDrawingBrush.color = '#AC1830';
    }
}

function deleteSelected() {
    if (!canvas) return;
    var active = canvas.getActiveObjects();
    if (active.length > 0) {
        active.forEach(function(obj) { canvas.remove(obj); });
        canvas.discardActiveObject();
        canvas.renderAll();
        saveUndoState();
    }
}

function clearCanvas() {
    if (!canvas) return;
    if (!confirm('모든 오브젝트를 지우시겠습니까?')) return;
    canvas.getObjects().forEach(function(obj) { canvas.remove(obj); });
    canvas.renderAll();
    saveUndoState();
}

function flipH() {
    if (!canvas) return;
    var obj = canvas.getActiveObject();
    if (obj) {
        obj.set('flipX', !obj.flipX);
        canvas.renderAll();
        saveUndoState();
    }
}

function flipV() {
    if (!canvas) return;
    var obj = canvas.getActiveObject();
    if (obj) {
        obj.set('flipY', !obj.flipY);
        canvas.renderAll();
        saveUndoState();
    }
}

function cropImage() {
    if (!canvas) return;
    alert('자르기 모드: 사각형을 그린 후 자르기 영역을 지정하세요.\n(사각형 도구 -> 영역 선택 -> Ctrl+Shift+C 로 크롭)');
}

// == 속성 패널 ==
function onObjectSelected(e) {
    var obj = e.selected ? e.selected[0] : canvas.getActiveObject();
    if (!obj) return;

    document.getElementById('objectProps').style.display = 'block';

    if (obj.type === 'i-text' || obj.type === 'text') {
        document.getElementById('textProps').style.display = 'block';
        document.getElementById('propFont').value = obj.fontFamily || 'Arial';
        document.getElementById('propFontSize').value = obj.fontSize || 24;
    } else {
        document.getElementById('textProps').style.display = 'none';
    }

    document.getElementById('propFill').value = rgbToHex(obj.fill) || '#AC1830';
    document.getElementById('propStroke').value = rgbToHex(obj.stroke) || '#000000';
    document.getElementById('propStrokeWidth').value = obj.strokeWidth || 0;
    document.getElementById('propOpacity').value = Math.round((obj.opacity || 1) * 100);
    document.getElementById('propAngle').value = Math.round(obj.angle || 0);
}

function onObjectDeselected() {
    document.getElementById('objectProps').style.display = 'none';
    document.getElementById('textProps').style.display = 'none';
}

function updateProp(prop, value) {
    if (!canvas) return;
    var obj = canvas.getActiveObject();
    if (!obj) return;
    obj.set(prop, value);
    canvas.renderAll();
    saveUndoState();
}

// == 필터 ==
function applyFilter(filterName) {
    if (!canvas) return;
    var bgImg = canvas.backgroundImage;
    if (!bgImg) return;

    bgImg.filters = [];

    switch(filterName) {
        case 'grayscale':
            bgImg.filters.push(new fabric.Image.filters.Grayscale());
            break;
        case 'sepia':
            bgImg.filters.push(new fabric.Image.filters.Sepia());
            break;
        case 'brightness':
            bgImg.filters.push(new fabric.Image.filters.Brightness({ brightness: 0.15 }));
            break;
        case 'contrast':
            bgImg.filters.push(new fabric.Image.filters.Contrast({ contrast: 0.2 }));
            break;
        case 'blur':
            bgImg.filters.push(new fabric.Image.filters.Blur({ blur: 0.2 }));
            break;
        case 'sharpen':
            bgImg.filters.push(new fabric.Image.filters.Convolute({
                matrix: [0, -1, 0, -1, 5, -1, 0, -1, 0]
            }));
            break;
        case 'invert':
            bgImg.filters.push(new fabric.Image.filters.Invert());
            break;
    }

    bgImg.applyFilters();
    canvas.renderAll();

    document.querySelectorAll('.filter-preset').forEach(function(p) { p.classList.remove('active'); });
    event.target.classList.add('active');
}

function adjustFilter(type, value) {
    if (!canvas) return;
    var bgImg = canvas.backgroundImage;
    if (!bgImg) return;

    // 기존 필터 유지하면서 밝기/대비 조절
    bgImg.filters = bgImg.filters.filter(function(f) {
        return !(f instanceof fabric.Image.filters.Brightness) && !(f instanceof fabric.Image.filters.Contrast);
    });

    var brightness = parseInt(document.getElementById('filterBrightness').value) / 100;
    var contrast = parseInt(document.getElementById('filterContrast').value) / 100;

    if (brightness !== 0) bgImg.filters.push(new fabric.Image.filters.Brightness({ brightness: brightness }));
    if (contrast !== 0) bgImg.filters.push(new fabric.Image.filters.Contrast({ contrast: contrast }));

    bgImg.applyFilters();
    canvas.renderAll();
}

// == Undo/Redo ==
function saveUndoState() {
    if (!canvas) return;
    undoStack.push(JSON.stringify(canvas));
    if (undoStack.length > 30) undoStack.shift();
    redoStack = [];
}

function editorAction(action) {
    if (action === 'undo' && undoStack.length > 1) {
        redoStack.push(undoStack.pop());
        canvas.loadFromJSON(undoStack[undoStack.length - 1], function() { canvas.renderAll(); });
    } else if (action === 'redo' && redoStack.length > 0) {
        var state = redoStack.pop();
        undoStack.push(state);
        canvas.loadFromJSON(state, function() { canvas.renderAll(); });
    } else if (action === 'select') {
        if (canvas) canvas.isDrawingMode = false;
        document.getElementById('tool-draw').classList.remove('active');
    }
}

// == 줌 ==
function zoomCanvas(delta, fit) {
    if (fit) {
        var wrapper = document.getElementById('canvasWrapper');
        var w = wrapper.clientWidth - 40;
        var h = wrapper.clientHeight - 40;
        if (canvas) {
            currentZoom = Math.min(w / canvas.width, h / canvas.height, 1);
        }
    } else {
        currentZoom = Math.max(0.1, Math.min(3, currentZoom + delta));
    }
    if (canvas) {
        canvas.setZoom(currentZoom);
        canvas.setDimensions({
            width: canvas.getWidth() * currentZoom / canvas.getZoom(),
            height: canvas.getHeight() * currentZoom / canvas.getZoom()
        });
    }
    document.getElementById('zoomLevel').textContent = Math.round(currentZoom * 100) + '%';
}

function setCanvasSize(w, h) {
    if (!canvas) { initCanvas(w, h); return; }
    canvas.setWidth(w);
    canvas.setHeight(h);
    canvas.renderAll();
    document.getElementById('canvasWidth').value = w;
    document.getElementById('canvasHeight').value = h;
    updateStatus(w + ' x ' + h);
    zoomCanvas(0, true);
}

// == 파일 ==
function handleFileUpload(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var reader = new FileReader();
    reader.onload = function(e) {
        loadImageToCanvas(e.target.result);
    };
    reader.readAsDataURL(file);

    // 서버 업로드도 동시에
    var fd = new FormData();
    fd.append('action', 'upload_image');
    fd.append('image', file);
    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                loadImages(); // 목록 새로고침
            }
        });
    input.value = '';
}

function loadFromUrl() {
    var url = prompt('이미지 URL을 입력하세요:');
    if (url) loadImageToCanvas(url);
}

// == 저장 ==
function saveImage(mode) {
    if (!canvas) return;
    document.getElementById('saveFilename').value = 'edited_' + Date.now();

    if (mode === 'update' && loadedImageId) {
        var sel = document.getElementById('saveTarget');
        sel.innerHTML = '<option value="new">새 이미지로 저장</option>';
        sel.innerHTML += '<option value="' + loadedImageId + '" selected>원본 업데이트 (' + loadedImageId + ')</option>';
        document.getElementById('saveTargetGroup').style.display = 'block';
    } else {
        document.getElementById('saveTargetGroup').style.display = 'none';
    }

    document.getElementById('saveModal').classList.add('show');
}

function confirmSave() {
    if (!canvas) return;
    var filename = document.getElementById('saveFilename').value.trim() || 'edited_' + Date.now();
    var format = document.getElementById('saveFormat').value;
    var target = document.getElementById('saveTarget')?.value || 'new';

    var mimeType = format === 'jpg' ? 'image/jpeg' : 'image/png';
    var dataUrl = canvas.toDataURL({ format: format === 'jpg' ? 'jpeg' : 'png', quality: 0.92 });

    var fd = new FormData();
    fd.append('action', 'save_edited');
    fd.append('image_data', dataUrl);
    fd.append('filename', filename);
    fd.append('format', format);
    fd.append('target', target);

    updateStatus('저장 중...');

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                updateStatus('저장 완료: ' + data.url);
                closeModal('saveModal');
                loadImages(); // 목록 새로고침
                alert('이미지 저장 완료!\n경로: ' + data.url);
            } else {
                updateStatus('저장 실패');
                alert(data.message || '저장 실패');
            }
        });
}

function downloadImage() {
    if (!canvas) return;
    var format = 'png';
    var dataUrl = canvas.toDataURL({ format: 'png', quality: 0.92 });
    var link = document.createElement('a');
    link.href = dataUrl;
    link.download = 'edited_' + Date.now() + '.png';
    link.click();
}

// == 페이지네이션 ==
function goPage(delta) {
    currentPage = Math.max(1, Math.min(totalPages, currentPage + delta));
    loadImages();
}

// == 체크박스 관리 ==
function checkAll() {
    document.querySelectorAll('.item-check').forEach(function(cb) { cb.checked = true; });
}
function uncheckAll() {
    document.querySelectorAll('.item-check').forEach(function(cb) { cb.checked = false; });
}
function deleteCheckedImages() {
    var checked = document.querySelectorAll('.item-check:checked');
    if (checked.length === 0) { alert('삭제할 이미지를 선택하세요.'); return; }
    if (!confirm(checked.length + '개 이미지를 삭제하시겠습니까?\n(광고/이벤트/병원은 이미지 링크만 제거됩니다)')) return;

    var ids = [];
    checked.forEach(function(cb) { ids.push(cb.getAttribute('data-id')); });

    var fd = new FormData();
    fd.append('action', 'delete_images');
    fd.append('ids', JSON.stringify(ids));

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                updateStatus(data.deleted + '개 삭제 완료');
                loadImages();
            } else {
                alert(data.message || '삭제 실패');
            }
        });
}

// == AI 카피 생성 ==
function aiGenerateCopy() {
    var keyword = document.getElementById('aiCopyKeyword').value.trim();
    if (!keyword) { alert('키워드를 입력하세요.'); return; }

    var results = document.getElementById('aiCopyResults');
    results.innerHTML = '<div style="text-align:center;padding:8px;"><i class="fas fa-spinner fa-spin"></i> 생성 중...</div>';

    var fd = new FormData();
    fd.append('action', 'ai_generate_copy');
    fd.append('keyword', keyword);
    fd.append('tone', '전문적');

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.copies) {
                var html = '';
                data.copies.forEach(function(copy) {
                    html += '<div class="ai-copy-item" onclick="addCopyToCanvas(this)" title="클릭하면 캔버스에 추가">' + escapeHtml(copy) + '</div>';
                });
                results.innerHTML = html;
            } else {
                results.innerHTML = '<div style="color:#EF4444;font-size:11px;padding:4px;">' + (data.message || '생성 실패') + '</div>';
            }
        })
        .catch(function() {
            results.innerHTML = '<div style="color:#EF4444;font-size:11px;padding:4px;">요청 오류</div>';
        });
}

function addCopyToCanvas(el) {
    if (!canvas) { initCanvas(1024, 1024); }
    var text = el.textContent.trim();
    var textObj = new fabric.IText(text, {
        left: canvas.width / 2 - 80,
        top: canvas.height / 2 - 20,
        fontSize: 32,
        fontFamily: 'Noto Sans KR, Arial',
        fill: '#AC1830',
        fontWeight: 'bold',
        shadow: 'rgba(0,0,0,0.3) 2px 2px 4px',
        padding: 8
    });
    canvas.add(textObj);
    canvas.setActiveObject(textObj);
    canvas.renderAll();
    saveUndoState();
    updateStatus('텍스트 추가됨: ' + text);
}

// == AI 프롬프트 보정 ==
function aiEnhancePrompt() {
    var prompt = document.getElementById('aiPrompt').value.trim();
    if (!prompt) { alert('프롬프트를 먼저 입력하세요.'); return; }

    var textarea = document.getElementById('aiPrompt');
    textarea.disabled = true;
    textarea.value = '프롬프트 보정 중...';

    var fd = new FormData();
    fd.append('action', 'ai_enhance_prompt');
    fd.append('prompt', prompt);

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            textarea.disabled = false;
            if (data.success && data.enhanced_prompt) {
                textarea.value = data.enhanced_prompt;
            } else {
                textarea.value = prompt;
                alert(data.message || '프롬프트 보정 실패');
            }
        })
        .catch(function() {
            textarea.disabled = false;
            textarea.value = prompt;
            alert('요청 오류');
        });
}

// == AI 생성 ==
function showAiGenerateModal() {
    document.getElementById('aiModal').classList.add('show');
}

function generateAiImage() {
    var prompt = document.getElementById('aiPrompt').value.trim();
    var size = document.getElementById('aiSize').value;

    if (!prompt) { alert('프롬프트를 입력해주세요.'); return; }

    updateStatus('AI 이미지 생성 중...');
    closeModal('aiModal');

    var fd = new FormData();
    fd.append('action', 'generate_ai_image');
    fd.append('prompt', prompt);
    fd.append('size', size);

    fetch(location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.data && data.data.url) {
                loadImageToCanvas(data.data.url);
                updateStatus('AI 이미지 생성 완료');
            } else {
                updateStatus('AI 생성 실패');
                alert(data.message || 'AI 이미지 생성 실패');
            }
        })
        .catch(function() {
            updateStatus('AI 생성 오류');
            alert('AI 이미지 생성 중 오류 발생');
        });
}

// == 유틸리티 ==
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function updateStatus(text) {
    document.getElementById('statusText').textContent = text;
}

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function rgbToHex(color) {
    if (!color) return '#000000';
    if (color.startsWith('#')) return color.substring(0, 7);
    if (color === 'transparent') return '#ffffff';
    var match = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (match) {
        return '#' + [match[1], match[2], match[3]].map(function(x) {
            return parseInt(x).toString(16).padStart(2, '0');
        }).join('');
    }
    return '#000000';
}

// 키보드 단축키
document.addEventListener('keydown', function(e) {
    if (!canvas) return;
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); editorAction('undo'); }
    if (e.ctrlKey && e.key === 'y') { e.preventDefault(); editorAction('redo'); }
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            deleteSelected();
        }
    }
});

// ESC 모달 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(function(m) {
            m.classList.remove('show');
        });
    }
});
</script>

<?php
include __DIR__ . '/includes/admin_footer.php';
?>
