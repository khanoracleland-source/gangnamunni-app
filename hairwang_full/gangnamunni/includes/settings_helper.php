<?php
/**
 * 사이트 설정값 조회 헬퍼
 * gangnamunni_settings 테이블에서 설정값 로드
 */

function getSettingValue($connect, $key, $default = '') {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];

    $escaped = mysqli_real_escape_string($connect, $key);
    $result = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key = '{$escaped}' LIMIT 1");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $cache[$key] = $row['setting_value'];
        return $row['setting_value'];
    }
    return $default;
}

function getAllSettings($connect, $group = '') {
    $settings = [];
    $query = "SELECT * FROM gangnamunni_settings";
    if (!empty($group)) {
        $escaped = mysqli_real_escape_string($connect, $group);
        $query .= " WHERE setting_group = '{$escaped}'";
    }
    $query .= " ORDER BY setting_group, id";
    $result = mysqli_query($connect, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['setting_key']] = $row;
        }
    }
    return $settings;
}

function updateSetting($connect, $key, $value) {
    $escaped_key = mysqli_real_escape_string($connect, $key);
    $escaped_val = mysqli_real_escape_string($connect, $value);
    return mysqli_query($connect, "
        INSERT INTO gangnamunni_settings (setting_key, setting_value)
        VALUES ('{$escaped_key}', '{$escaped_val}')
        ON DUPLICATE KEY UPDATE setting_value = '{$escaped_val}'
    ");
}
?>