<?php
/** Shared dosage units. Readers do not perform schema changes. */
function treatment_units_defaults() {
    return ['샷', 'cc', 'ml', 'kj', '회', '병', 'IU', '라인', '시린지', 'Box', '기타'];
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
        if ($result) return array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'name');
    } catch (mysqli_sql_exception $e) {
        // Existing installations keep working before the migration is applied.
        if ((int)$e->getCode() !== 1146) throw $e;
    }
    return treatment_units_defaults();
}

function treatment_units_with_current(array $units, $current) {
    if ($current !== null && $current !== '' && !in_array($current, $units, true)) $units[] = $current;
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
