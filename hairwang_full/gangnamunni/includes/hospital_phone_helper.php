<?php

/**
 * 병원 전화번호를 비교 가능한 국내 번호 형식(숫자만)으로 정규화한다.
 */
function hospital_phone_normalize($raw)
{
    $digits = preg_replace('/\D+/', '', trim((string)$raw));
    if (strpos($digits, '0082') === 0) {
        $digits = '0' . substr($digits, 4);
    } elseif (strpos($digits, '82') === 0) {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

/**
 * 형식을 검증해 거부하지 않고, 인식 가능한 국내 번호만 하이픈 표기로 통일한다.
 */
function hospital_phone_format($raw)
{
    $phone = hospital_phone_normalize($raw);
    if ($phone === '') return trim((string)$raw);

    if (preg_match('/^(02)(\d{3,4})(\d{4})$/', $phone, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if (preg_match('/^(050[2-8])(\d{3,4})(\d{4})$/', $phone, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if (preg_match('/^(0\d{2})(\d{3,4})(\d{4})$/', $phone, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if (preg_match('/^(1[5-8]\d{2})(\d{4})$/', $phone, $m)) {
        return $m[1] . '-' . $m[2];
    }

    // 알려진 국내 번호 규칙이 아니어도 모든 숫자를 뒤에서부터 4자리씩 통일한다.
    $groups = [];
    while (strlen($phone) > 4) {
        array_unshift($groups, substr($phone, -4));
        $phone = substr($phone, 0, -4);
    }
    if ($phone !== '') array_unshift($groups, $phone);
    return implode('-', $groups);
}

/**
 * 형식 차이를 무시하고 같은 전화번호를 쓰는 다른 병원을 찾는다.
 */
function hospital_phone_find_duplicate($connect, $raw, $exclude_hospital_id = 0)
{
    $needle = hospital_phone_normalize($raw);
    if ($needle === '') return null;

    $stmt = mysqli_prepare($connect, "SELECT id, name, phone FROM gangnamunni_hospitals WHERE phone IS NOT NULL AND phone <> '' AND id <> ?");
    if (!$stmt) return null;
    $exclude_hospital_id = (int)$exclude_hospital_id;
    mysqli_stmt_bind_param($stmt, 'i', $exclude_hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $duplicate = null;
    while ($row = mysqli_fetch_assoc($result)) {
        if (hospital_phone_normalize($row['phone']) === $needle) {
            $duplicate = $row;
            break;
        }
    }
    mysqli_stmt_close($stmt);
    return $duplicate;
}
