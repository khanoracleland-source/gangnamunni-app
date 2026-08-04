<?php
/**
 * 병원별 시술 상세페이지 이미지 헬퍼 (#시술이미지)
 *
 * 병원이 배정받은 시술 각각에 대해 상세페이지용 이미지를 여러 장 관리한다.
 *  - 관리 화면 : admin_hospital_treatments.php (업로드/삭제)
 *  - 노출 화면 : detail.php "상세 정보" 영역
 *
 * 스키마는 sql/hospital_treatment_images.sql 로 배포 시 1회 적용한다.
 * 프론트(공개) 경로에서는 DDL 을 실행하지 않고, 테이블이 없으면 조용히 빈 배열을 돌려준다.
 *
 * @created 2026-07-27
 */

if (!function_exists('hti_ensure_schema')) {
    /**
     * 테이블 보장 — 관리자 화면에서만 호출한다(마이그레이션 미적용 환경 대비).
     * 공개 페이지에서는 호출하지 말 것.
     */
    function hti_ensure_schema($connect)
    {
        static $done = false;
        if ($done) return true;
        $done = true;
        try {
            mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_hospital_treatment_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                hospital_treatment_id INT NOT NULL,
                hospital_id INT NOT NULL,
                treatment_id INT NOT NULL,
                image_url VARCHAR(500) NOT NULL,
                caption VARCHAR(200) DEFAULT NULL,
                sort_order INT DEFAULT 0,
                approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
                uploaded_by ENUM('admin','hospital') NOT NULL DEFAULT 'admin',
                reject_reason VARCHAR(1000) DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                reviewed_by VARCHAR(100) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ht (hospital_treatment_id, sort_order, id),
                KEY idx_approval (approval_status, created_at),
                KEY idx_hospital_treatment (hospital_id, treatment_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $columns = [
                'approval_status' => "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'",
                'uploaded_by' => "ENUM('admin','hospital') NOT NULL DEFAULT 'admin'",
                'reject_reason' => "VARCHAR(1000) DEFAULT NULL",
                'reviewed_at' => "DATETIME DEFAULT NULL",
                'reviewed_by' => "VARCHAR(100) DEFAULT NULL",
            ];
            foreach ($columns as $column => $definition) {
                $check = mysqli_query($connect, "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_hospital_treatment_images' AND COLUMN_NAME = '{$column}' LIMIT 1");
                if ($check && mysqli_num_rows($check) === 0) {
                    mysqli_query($connect, "ALTER TABLE gangnamunni_hospital_treatment_images ADD COLUMN `{$column}` {$definition}");
                }
            }
        } catch (\mysqli_sql_exception $e) {
            error_log('[hospital_treatment_images] 스키마 생성 실패: ' . $e->getMessage());
            return false;
        }
        return true;
    }
}

if (!function_exists('hti_images_by_ht_ids')) {
    /**
     * 배정(hospital_treatment) id 목록으로 이미지를 한 번에 로드한다.
     *
     * @param  array $ht_ids  gangnamunni_hospital_treatments.id 배열
     * @return array          [ht_id => [ ['id'=>..,'image_url'=>..,'caption'=>..], ... ], ...]
     */
    function hti_images_by_ht_ids($connect, array $ht_ids, $approved_only = false)
    {
        $out = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ht_ids))));
        if (empty($ids)) return $out;
        $in = implode(',', $ids); // 정수만 담기므로 안전
        try {
            $r = mysqli_query($connect,
                "SELECT id, hospital_treatment_id, image_url, caption, approval_status, uploaded_by, reject_reason, reviewed_at, reviewed_by
                 FROM gangnamunni_hospital_treatment_images
                 WHERE hospital_treatment_id IN ({$in})
                 " . ($approved_only ? "AND approval_status = 'approved'" : "") . "
                 ORDER BY sort_order ASC, id ASC");
            if ($r) while ($row = mysqli_fetch_assoc($r)) {
                $out[intval($row['hospital_treatment_id'])][] = [
                    'id'        => intval($row['id']),
                    'image_url' => $row['image_url'],
                    'caption'   => $row['caption'],
                    'approval_status' => $row['approval_status'] ?? 'approved',
                    'uploaded_by' => $row['uploaded_by'] ?? 'admin',
                    'reject_reason' => $row['reject_reason'] ?? null,
                    'reviewed_at' => $row['reviewed_at'] ?? null,
                    'reviewed_by' => $row['reviewed_by'] ?? null,
                ];
            }
        } catch (\mysqli_sql_exception $e) {
            // 마이그레이션 미적용 등 — 화면이 죽지 않도록 방어
            error_log('[hospital_treatment_images] 이미지 로드 실패(마이그레이션 확인): ' . $e->getMessage());
        }
        return $out;
    }
}

if (!function_exists('hti_images_for')) {
    /**
     * 특정 병원 + 특정 시술의 상세페이지 이미지 목록 (공개 페이지용).
     *
     * @return array [ ['id'=>..,'image_url'=>..,'caption'=>..], ... ]
     */
    function hti_images_for($connect, $hospital_id, $treatment_id)
    {
        $hospital_id  = intval($hospital_id);
        $treatment_id = intval($treatment_id);
        if ($hospital_id <= 0 || $treatment_id <= 0) return [];

        $out = [];
        try {
            $stmt = mysqli_prepare($connect,
                "SELECT id, image_url, caption
                 FROM gangnamunni_hospital_treatment_images
                 WHERE hospital_id = ? AND treatment_id = ? AND approval_status = 'approved'
                 ORDER BY sort_order ASC, id ASC");
            if (!$stmt) return [];
            mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $treatment_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($r)) {
                $out[] = [
                    'id'        => intval($row['id']),
                    'image_url' => $row['image_url'],
                    'caption'   => $row['caption'],
                ];
            }
            mysqli_stmt_close($stmt);
        } catch (\mysqli_sql_exception $e) {
            error_log('[hospital_treatment_images] 조회 실패(마이그레이션 확인): ' . $e->getMessage());
        }
        return $out;
    }
}

if (!function_exists('hti_handle_upload')) {
    /**
     * 업로드 처리 — uploads/treatments/ 아래에 저장한다.
     * admin_hospital_images.php 의 업로드 규칙(확장자/용량/MIME)을 그대로 따른다.
     *
     * @return array|null ['url'=>..] | ['error'=>..] | null(파일 미선택)
     */
    function hti_handle_upload($file_key)
    {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $errors = [
            UPLOAD_ERR_INI_SIZE   => '파일 크기가 서버 설정(upload_max_filesize) 한도를 초과합니다.',
            UPLOAD_ERR_FORM_SIZE  => '파일 크기가 폼 한도(MAX_FILE_SIZE)를 초과합니다.',
            UPLOAD_ERR_PARTIAL    => '파일이 부분만 업로드되었습니다. 다시 시도해주세요.',
            UPLOAD_ERR_NO_TMP_DIR => '서버 임시 디렉토리가 없습니다.',
            UPLOAD_ERR_CANT_WRITE => '서버 디스크 쓰기 실패.',
            UPLOAD_ERR_EXTENSION  => 'PHP 확장이 업로드를 차단했습니다.',
        ];
        $code = $_FILES[$file_key]['error'];
        if ($code !== UPLOAD_ERR_OK) {
            return ['error' => $errors[$code] ?? "업로드 에러 코드: {$code}"];
        }

        $file = $_FILES[$file_key];
        $allowed_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext, true)) {
            return ['error' => '이미지 파일만 업로드 가능합니다 (JPG, PNG, GIF, WebP)'];
        }
        // 확장자만 믿지 않고 실제 이미지인지 확인
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['error' => '이미지 파일이 아닙니다.'];
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            return ['error' => '파일 크기는 10MB 이하여야 합니다'];
        }

        $upload_dir = dirname(__DIR__) . '/uploads/treatments/';
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            return ['error' => '업로드 디렉토리를 만들 수 없습니다.'];
        }
        $fname = 'treat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $fname)) {
            return ['url' => 'uploads/treatments/' . $fname];
        }
        return ['error' => '파일 저장에 실패했습니다'];
    }
}

if (!function_exists('hti_application_treatment_ids')) {
    /**
     * 신청 폼에서 넘어온 시술 ID를 실제 활성 시술 목록으로 제한한다.
     */
    function hti_application_treatment_ids($connect, $raw_ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$raw_ids))));
        if (empty($ids)) return [];
        $in = implode(',', $ids);
        $valid = [];
        $r = mysqli_query($connect, "SELECT id FROM gangnamunni_treatment_master WHERE is_active = 1 AND id IN ({$in})");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $valid[] = intval($row['id']);
        return $valid;
    }
}

if (!function_exists('hti_application_files_for')) {
    /**
     * treatment_images[시술ID][] 형태의 다중 업로드를 일반 $_FILES 항목 배열로 변환한다.
     */
    function hti_application_files_for($treatment_id)
    {
        $all = $_FILES['treatment_images'] ?? null;
        if (!$all || !isset($all['name'][$treatment_id])) return [];
        $files = [];
        foreach ((array)$all['name'][$treatment_id] as $i => $name) {
            $files[] = [
                'name' => $name,
                'type' => $all['type'][$treatment_id][$i] ?? '',
                'tmp_name' => $all['tmp_name'][$treatment_id][$i] ?? '',
                'error' => $all['error'][$treatment_id][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $all['size'][$treatment_id][$i] ?? 0,
            ];
        }
        return $files;
    }
}

if (!function_exists('hti_save_application_treatments')) {
    /**
     * 병원 신청 시 선택한 시술은 즉시 활성 배정하고 이미지만 승인 대기로 저장한다.
     *
     * @return array 저장 중 발생한 사용자 표시용 경고 목록
     */
    function hti_save_application_treatments($connect, $hospital_id, $raw_ids, $registered_by = 'hospital')
    {
        hti_ensure_schema($connect);
        $warnings = [];
        $hospital_id = intval($hospital_id);
        $ids = hti_application_treatment_ids($connect, $raw_ids);
        if ($hospital_id <= 0 || empty($ids)) return $warnings;

        foreach ($ids as $treatment_id) {
            $ht_id = 0;
            $find = mysqli_prepare($connect, "SELECT id FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ? LIMIT 1");
            mysqli_stmt_bind_param($find, 'ii', $hospital_id, $treatment_id);
            mysqli_stmt_execute($find);
            mysqli_stmt_bind_result($find, $ht_id);
            mysqli_stmt_fetch($find);
            mysqli_stmt_close($find);

            if (!$ht_id) {
                $status = 'active';
                $price_min = 0;
                $price_max = 0;
                $ins_ht = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_hospital_treatments
                        (hospital_id, treatment_id, price_min, price_max, is_promoted, note,
                         status, registered_by, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 0, '', ?, ?, 1, NOW(), NOW())");
                if (!$ins_ht) {
                    $warnings[] = "시술 #{$treatment_id} 배정 저장에 실패했습니다.";
                    continue;
                }
                mysqli_stmt_bind_param($ins_ht, 'iiiiss',
                    $hospital_id, $treatment_id, $price_min, $price_max, $status, $registered_by);
                mysqli_stmt_execute($ins_ht);
                $ht_id = mysqli_insert_id($connect);
                mysqli_stmt_close($ins_ht);
            }

            $sort_order = 0;
            foreach (hti_application_files_for($treatment_id) as $file) {
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $_FILES['_hti_application_image'] = $file;
                $uploaded = hti_handle_upload('_hti_application_image');
                unset($_FILES['_hti_application_image']);
                if (!$uploaded || isset($uploaded['error'])) {
                    $warnings[] = "시술 #{$treatment_id} 이미지: " . ($uploaded['error'] ?? '업로드 실패');
                    continue;
                }
                $url = $uploaded['url'];
                $ins_img = mysqli_prepare($connect,
                    "INSERT INTO gangnamunni_hospital_treatment_images
                        (hospital_treatment_id, hospital_id, treatment_id, image_url, sort_order, approval_status, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, 'pending', 'hospital')");
                mysqli_stmt_bind_param($ins_img, 'iiisi', $ht_id, $hospital_id, $treatment_id, $url, $sort_order);
                mysqli_stmt_execute($ins_img);
                mysqli_stmt_close($ins_img);
                $sort_order++;
            }
        }
        return $warnings;
    }
}
