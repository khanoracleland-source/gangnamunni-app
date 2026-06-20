<?php
/**
 * 사진 업로드 공통 헬퍼 (서버사이드)
 * - booking.php, consultation_submit.php, review_write.php 공용
 * - 업로드 검증, 저장, DB 업데이트 통합
 *
 * 사용법:
 *   require_once __DIR__ . '/photo_upload_helper.php';
 *   $result = handle_photo_upload($connect, [
 *       'type'       => 'bookings',          // bookings | consultations | reviews
 *       'record_id'  => $booking_id,
 *       'table'      => 'gangnamunni_bookings',
 *       'max_files'  => 3,                    // 기본 3, 후기는 5
 *   ]);
 */

/**
 * 사진 업로드 처리 (메인 함수)
 *
 * @param mysqli $connect DB 연결
 * @param array $options {
 *     type: string       - 업로드 폴더명 (bookings, consultations, reviews)
 *     record_id: int     - 레코드 ID (INSERT 후)
 *     table: string      - DB 테이블명
 *     max_files: int     - 최대 파일 수 (기본 3)
 *     field_name: string - $_FILES 키 (기본 'photos')
 *     col_urls: string   - DB 컬럼명 (기본 'photo_urls')
 *     col_consent: string - 동의 컬럼명 (기본 'photo_consent')
 * }
 * @return array ['success' => bool, 'photos' => array, 'count' => int, 'error' => string|null]
 */
function handle_photo_upload($connect, $options) {
    $type       = $options['type'] ?? 'uploads';
    $record_id  = intval($options['record_id'] ?? 0);
    $table      = $options['table'] ?? '';
    $max_files  = intval($options['max_files'] ?? 3);
    $field_name = $options['field_name'] ?? 'photos';
    $col_urls   = $options['col_urls'] ?? 'photo_urls';
    $col_consent = $options['col_consent'] ?? 'photo_consent';

    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    // 기본 응답
    $result = ['success' => true, 'photos' => [], 'count' => 0, 'error' => null];

    if ($record_id <= 0 || empty($table)) {
        $result['success'] = false;
        $result['error'] = '레코드 ID 또는 테이블 미지정';
        return $result;
    }

    // 파일이 없으면 바로 성공 반환
    if (empty($_FILES[$field_name]) || empty($_FILES[$field_name]['name'][0])) {
        return $result;
    }

    // 업로드 디렉토리 생성
    $upload_dir = __DIR__ . '/../uploads/' . $type . '/' . $record_id . '/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0775, true)) {
            $result['success'] = false;
            $result['error'] = '업로드 디렉토리 생성 실패';
            return $result;
        }
    }

    $uploaded = [];
    $count = min(count($_FILES[$field_name]['name']), $max_files);

    for ($i = 0; $i < $count; $i++) {
        $tmp   = $_FILES[$field_name]['tmp_name'][$i] ?? '';
        $size  = $_FILES[$field_name]['size'][$i] ?? 0;
        $ftype = $_FILES[$field_name]['type'][$i] ?? '';

        if (empty($tmp) || !is_uploaded_file($tmp)) continue;
        if ($size > $max_size) continue;
        if (!in_array($ftype, $allowed_types)) continue;

        // 확장자 결정
        $ext = 'jpg';
        if ($ftype === 'image/png') $ext = 'png';
        elseif ($ftype === 'image/gif') $ext = 'gif';
        elseif ($ftype === 'image/webp') $ext = 'webp';

        // 안전한 파일명 생성
        $filename = time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $dest = $upload_dir . $filename;

        if (move_uploaded_file($tmp, $dest)) {
            chmod($dest, 0644);
            $rel_path = 'uploads/' . $type . '/' . $record_id . '/' . $filename;
            $uploaded[] = $rel_path;
        }
    }

    // DB 업데이트 (사진 경로 + 동의)
    if (!empty($uploaded)) {
        $photo_json = json_encode($uploaded, JSON_UNESCAPED_SLASHES);
        $photo_consent = isset($_POST['photo_consent']) ? intval($_POST['photo_consent']) : 0;

        $sql = "UPDATE {$table} SET {$col_urls} = ?, {$col_consent} = ? WHERE id = ?";
        $stmt = mysqli_prepare($connect, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sii', $photo_json, $photo_consent, $record_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $result['photos'] = $uploaded;
    $result['count'] = count($uploaded);
    return $result;
}

/**
 * 기존 사진 URL JSON에서 배열 반환
 */
function get_photo_urls($json_str) {
    if (empty($json_str)) return [];
    $arr = json_decode($json_str, true);
    return is_array($arr) ? $arr : [];
}

/**
 * 사진 URL 배열을 HTML 썸네일로 렌더링
 * (관리자 페이지에서 사용)
 */
function render_photo_thumbnails($json_str, $size = 48) {
    $photos = get_photo_urls($json_str);
    if (empty($photos)) return '<span class="text-muted">-</span>';

    $html = '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
    foreach ($photos as $url) {
        $safe_url = htmlspecialchars($url);
        $html .= '<a href="' . $safe_url . '" target="_blank">'
               . '<img src="' . $safe_url . '" style="width:' . $size . 'px;height:' . $size . 'px;object-fit:cover;border-radius:4px;border:1px solid #ddd;">'
               . '</a>';
    }
    $html .= '</div>';
    return $html;
}
