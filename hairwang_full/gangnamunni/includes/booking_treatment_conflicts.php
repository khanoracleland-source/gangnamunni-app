<?php
/**
 * [05-18] 같은 날 함께 예약하기 어려운 시술 조합 (전체 병원 공통, 관리자가 시술 마스터 관리에서 설정).
 * 테이블은 sql/treatment_conflicts_20261023.sql 로 만든다. 없으면 제한을 적용하지 않는다.
 */
if (!function_exists('booking_treatment_conflicts_available')) {

    function booking_treatment_conflicts_available($connect) {
        static $ok = null;
        if ($ok === null) {
            $r = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_conflicts'");
            $ok = $r && mysqli_num_rows($r) > 0;
        }
        return $ok;
    }

    /**
     * 선택한 시술(이름) 중 서로 함께 예약할 수 없는 조합에 걸린 시술 이름들을 돌려준다(없으면 []).
     */
    function booking_treatment_conflict_names($connect, array $names) {
        if (count($names) < 2 || !booking_treatment_conflicts_available($connect)) return [];
        $ph = implode(',', array_fill(0, count($names), '?'));
        $stmt = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_treatment_master WHERE name IN ({$ph})");
        if (!$stmt) return [];
        mysqli_stmt_bind_param($stmt, str_repeat('s', count($names)), ...array_values($names));
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $ids = [];
        while ($res && ($r = $res->fetch_assoc())) { $ids[(string)$r['name']] = (int)$r['id']; }
        mysqli_stmt_close($stmt);
        if (count($ids) < 2) return [];
        $id_list = implode(',', array_map('intval', array_values($ids)));
        $rs = mysqli_query($connect, "SELECT tm_a_id, tm_b_id FROM gangnamunni_treatment_conflicts WHERE tm_a_id IN ({$id_list}) AND tm_b_id IN ({$id_list})");
        $hit = [];
        while ($rs && ($r = $rs->fetch_assoc())) { $hit[(int)$r['tm_a_id']] = true; $hit[(int)$r['tm_b_id']] = true; }
        $out = [];
        foreach ($ids as $name => $id) { if (isset($hit[$id])) $out[] = $name; }
        return $out;
    }
}
