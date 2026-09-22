<?php
/** Shared dosage units. Readers do not perform schema changes. */
function treatment_units_defaults() {
    return treatment_units_sort(['Box', 'cc', 'IU', 'kj', 'ml', '기타', '라인', '병', '샷', '시린지', '회']);
}

/** 첫 글자가 한글(자모 · 음절)이면 1, 그 외(숫자 · 영문 등)는 0. */
function treatment_units_sort_group($name) {
    $first = mb_substr((string)$name, 0, 1, 'UTF-8');
    if ($first === '') return 0;
    $utf32 = mb_convert_encoding($first, 'UTF-32BE', 'UTF-8');
    $unpacked = unpack('N', $utf32);
    $code = $unpacked ? $unpacked[1] : 0;
    $isHangul = ($code >= 0x1100 && $code <= 0x11FF)   // 한글 자모
        || ($code >= 0x3130 && $code <= 0x318F)        // 호환용 자모 (ㄱ, ㄴ, ㄷ ...)
        || ($code >= 0xAC00 && $code <= 0xD7A3);       // 한글 음절 (가 ~ 힣)

    return $isHangul ? 1 : 0;
}

/**
 * 단위 정렬 기준: 영문 · 숫자(abc)가 먼저, 그다음 한글(ㄱㄴㄷ) 순.
 * 한글 음절의 유니코드 코드포인트는 초성 · 중성 · 종성 순서로 배열되어 있어
 * UTF-8 바이트 비교만으로 가나다 순이 보장된다.
 */
function treatment_units_compare($a, $b) {
    $groupA = treatment_units_sort_group($a);
    $groupB = treatment_units_sort_group($b);
    if ($groupA !== $groupB) return $groupA - $groupB;

    // 영문은 대소문자를 구분하지 않고 사전순으로 (Box, cc, IU, kj, ml ...)
    $keyA = mb_strtolower((string)$a, 'UTF-8');
    $keyB = mb_strtolower((string)$b, 'UTF-8');
    $result = strcmp($keyA, $keyB);

    return $result !== 0 ? $result : strcmp((string)$a, (string)$b);
}

/** 단위 이름 배열을 정렬한다. */
function treatment_units_sort(array $units) {
    usort($units, 'treatment_units_compare');

    return array_values($units);
}

/** DB 레코드 배열을 지정한 컬럼(기본 name) 기준으로 정렬한다. */
function treatment_units_sort_rows(array $rows, $column = 'name') {
    usort($rows, function ($a, $b) use ($column) {
        return treatment_units_compare(
            isset($a[$column]) ? $a[$column] : '',
            isset($b[$column]) ? $b[$column] : ''
        );
    });

    return array_values($rows);
}

function treatment_units_ensure_schema($connect) {
    $sql = file_get_contents(__DIR__ . '/../sql/treatment_units.sql');
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '' && !mysqli_query($connect, $statement)) {
            throw new RuntimeException('단위 목록을 준비하지 못했습니다.');
        }
    }
}

function treatment_units_list($connect) {
    try {
        $result = mysqli_query($connect, 'SELECT name FROM gangnamunni_treatment_units WHERE is_active = 1 ORDER BY id');
        if ($result) return treatment_units_sort(array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'name'));
    } catch (mysqli_sql_exception $e) {
        // Existing installations keep working before the migration is applied.
        if ((int)$e->getCode() !== 1146) throw $e;
    }
    return treatment_units_defaults();
}

function treatment_units_with_current(array $units, $current) {
    if ($current !== null && $current !== '' && !in_array($current, $units, true)) {
        $units[] = $current;
        // 목록에 없던 기존 단위도 정렬 순서에 맞춰 끼워 넣는다.
        $units = treatment_units_sort($units);
    }
    return $units;
}

function treatment_units_add($connect, $name) {
    $name = trim($name);
    if ($name === '' || mb_strlen($name, 'UTF-8') > 20 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
        throw new InvalidArgumentException('단위는 제어 문자 없이 1~20자로 입력해주세요.');
    }
    $stmt = mysqli_prepare($connect, 'INSERT INTO gangnamunni_treatment_units (name) VALUES (?) ON DUPLICATE KEY UPDATE is_active = 1');
    mysqli_stmt_bind_param($stmt, 's', $name);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('단위 저장 실패');
    mysqli_stmt_close($stmt);
}

function treatment_units_remove($connect, $id) {
    // Preserve historical price options; only remove the unit from new selections.
    $stmt = mysqli_prepare($connect, 'UPDATE gangnamunni_treatment_units SET is_active = 0 WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('단위 삭제 실패');
    mysqli_stmt_close($stmt);
}
