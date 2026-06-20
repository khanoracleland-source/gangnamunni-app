<?php
if (!defined('G5_MYSQL_PASSWORD')) { require_once __DIR__ . '/dbconfig.php'; }
/**
 * 매뉴얼 피드백 → Claude CLI 프롬프트 생성 API
 * POST /api/manual_feedback_generate_prompt.php
 * 관리자 로그인 필요
 * @created 2026-04-08
 */
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'로그인 필요']);
    exit;
}

$id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'id 없음']); exit; }

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, 'hms_saas', 3306);
if (!$connect) { echo json_encode(['success'=>false,'error'=>'DB 오류']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$stmt = mysqli_prepare($connect, "SELECT * FROM manual_feedback_items WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$item = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$item) { echo json_encode(['success'=>false,'error'=>'항목 없음']); exit; }

// ─── 라벨 매핑 ───
$type_label = [
    'bug_fix'      => '오류 수정',
    'feature_add'  => '기능 추가',
    'content_edit' => '내용 수정',
    'update'       => '업데이트',
    'other'        => '기타',
][$item['request_type']] ?? $item['request_type'];

$priority_label = [
    'low'=>'낮음','normal'=>'보통','high'=>'높음','urgent'=>'긴급'
][$item['priority']] ?? $item['priority'];

$role_label = [
    'client'=>'클라이언트','developer'=>'개발자','manager'=>'매니저','other'=>'기타'
][$item['created_by_role']] ?? $item['created_by_role'];

$page_info = $item['page_url'] ?: '알 수 없음';
$section   = $item['section_title'] ?: '(지정 없음)';
$loc_desc  = $item['source_location_desc'] ?: '(없음)';

// ─── 프롬프트 생성 ───
$now = date('Y-m-d H:i:s');
$prompt = <<<PROMPT
# 매뉴얼 수정 작업 요청 — #{$item['id']}

## 1. 작업 목적
작성자 [{$role_label}] {$item['created_by_name']}님이 등록한 수정 요청 사항을 처리한다.
- 요청 유형: {$type_label}
- 중요도: {$priority_label}
- 등록일: {$item['created_at']}

## 2. 대상 페이지
- URL: {$page_info}
- 페이지 제목: {$item['page_title']}
- 경로: {$item['page_path']}

## 3. 위치 정보
- 섹션: {$section}
- 메뉴: {$item['menu_label']}
- 위치 설명: {$loc_desc}

## 4. 사용자 요청 원문

### 제목
{$item['request_title']}

### 내용
{$item['request_text']}

## 5. 추정 수정 포인트
위의 요청 내용을 분석하여 다음을 파악한다:
- 수정이 필요한 파일 위치
- 변경해야 할 코드 블록 또는 콘텐츠
- 영향받는 연관 파일

## 6. 구현 요구사항
- 기존 기능이 깨지지 않도록 최소 침습 방식으로 작업
- 수정 전 해당 파일 백업
- Bootstrap 5.3 + vanilla JS 스타일 유지
- PHP Prepared Statement 사용 (SQL 직접 삽입 금지)

## 7. 검수 체크리스트
- [ ] 요청한 수정/추가 사항이 정상 동작하는가?
- [ ] 기존 기능이 그대로 동작하는가?
- [ ] 모바일 화면에서도 정상 표시되는가?
- [ ] php -l 문법 검사 통과하는가?
- [ ] 서버 배포 및 실제 URL에서 확인했는가?

## 8. 주의사항
- 기존 admin_dev_feedback.php 와 충돌하지 않도록 할 것
- 이 프롬프트는 {$now} 에 생성되었음
- 작업 완료 후 manual_feedback_items 테이블의 status를 'done'으로 변경할 것
  예: UPDATE manual_feedback_items SET status='done', handled_at=NOW() WHERE id={$item['id']};
PROMPT;

// DB에 저장
$stmt2 = mysqli_prepare($connect,
    "UPDATE manual_feedback_items SET prompt_text=?, prompt_generated_at=NOW(), status='prompt_ready' WHERE id=?");
mysqli_stmt_bind_param($stmt2, 'si', $prompt, $id);
mysqli_stmt_execute($stmt2);
mysqli_stmt_close($stmt2);
mysqli_close($connect);

echo json_encode(['success'=>true, 'prompt'=>$prompt, 'id'=>$id]);
