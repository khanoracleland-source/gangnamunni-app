<?php
/**
 * 시술 hajm (dosage) filtri — hospitals.php
 * Faqat 시술 aniqlanganda (treatment= yoki q= 시술ga mos) ishlaydi.
 * URL: hospitals.php?treatment=리쥬란힐러&dosage=2cc
 */

if (!function_exists('hospital_dosage_label')) {
    function hospital_dosage_label($value, $unit) {
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') . $unit;
    }
}

if (!function_exists('hospital_dosage_resolve_treatment_id')) {
    function hospital_dosage_resolve_treatment_id($connect, $treatment_name) {
        $treatment_name = trim((string)$treatment_name);
        if ($treatment_name === '' || !$connect) return 0;
        $id = 0;
        $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_treatment_master WHERE name = ? AND is_active = 1 LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $treatment_name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $id);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
        }
        return (int)$id;
    }
}

if (!function_exists('hospital_dosage_options')) {
    /** 시술 bo'yicha faol shifoxonalarda mavjud hajm variantlari: [['value'=>1.0,'unit'=>'cc','label'=>'1cc'], ...] */
    function hospital_dosage_options($connect, $treatment_id) {
        $treatment_id = (int)$treatment_id;
        if ($treatment_id <= 0 || !$connect) return [];
        $sql = "SELECT DISTINCT p.dosage_value, p.dosage_unit
                FROM gangnamunni_hospital_treatment_prices p
                INNER JOIN gangnamunni_hospital_treatments ht ON ht.id = p.hospital_treatment_id
                INNER JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
                WHERE ht.treatment_id = {$treatment_id}
                  AND ht.status = 'active' AND ht.is_active = 1
                  AND p.price > 0
                  AND p.dosage_value IS NOT NULL AND p.dosage_value > 0
                  AND p.dosage_unit IS NOT NULL AND TRIM(p.dosage_unit) <> ''
                  AND h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0
                  AND (h.status = 'approved' OR h.status IS NULL)";
        $res = @mysqli_query($connect, $sql);
        if (!$res) return [];
        $opts = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $unit = trim((string)$r['dosage_unit']);
            $value = (float)$r['dosage_value'];
            $label = hospital_dosage_label($value, $unit);
            $opts[$label] = ['value' => $value, 'unit' => $unit, 'label' => $label];
        }
        $opts = array_values($opts);
        usort($opts, function ($a, $b) {
            return [$a['unit'], $a['value']] <=> [$b['unit'], $b['value']];
        });
        return $opts;
    }
}

if (!function_exists('hospital_dosage_selected')) {
    /** ?dosage= qiymatini faqat mavjud variantlar ichidan qabul qiladi (whitelist). */
    function hospital_dosage_selected($options, $raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return null;
        foreach ($options as $o) {
            if ($o['label'] === $raw) return $o;
        }
        return null;
    }
}

if (!function_exists('hospital_dosage_sql_condition')) {
    /** Narx jadvali alias'i uchun SQL sharti: " AND ABS(p.dosage_value - 2) < 0.0001 AND p.dosage_unit = 'cc'" */
    function hospital_dosage_sql_condition($connect, $selected, $alias = 'p') {
        if (!$selected) return '';
        $value = (float)$selected['value'];
        $unit = mysqli_real_escape_string($connect, $selected['unit']);
        return " AND ABS({$alias}.dosage_value - {$value}) < 0.0001 AND {$alias}.dosage_unit = '{$unit}'";
    }
}
