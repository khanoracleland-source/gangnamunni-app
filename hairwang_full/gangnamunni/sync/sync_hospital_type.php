<?php
// CLI 전용 스크립트 — 웹 요청은 404
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * hospital_type <-> gangnamunni_categories bidirectional sync
 * - HMS hospital_type add/update -> gangnamunni_categories reflect
 * - gangnamunni_categories add/update -> HMS hospital_type reflect
 * cron: called from cron_sync.sh (every minute)
 */

// DB config (hms_saas unified DB)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '<REMOVED_DB_PASSWORD>';
$db_name = 'hms_saas';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("sync_hospital_type: DB connection failed - " . $conn->connect_error);
    exit(1);
}
$conn->set_charset('utf8mb4');

$synced = 0;

// === Step 1: HMS hospital_type -> gangnamunni_categories ===
$result = $conn->query("
    SELECT ht.id, ht.name
    FROM hospital_type ht
    WHERE NOT EXISTS (
        SELECT 1 FROM gangnamunni_categories gc
        WHERE gc.hms_hospital_type_id = ht.id
    )
    AND NOT EXISTS (
        SELECT 1 FROM gangnamunni_categories gc
        WHERE gc.name = ht.name
    )
");

if ($result && $result->num_rows > 0) {
    $max_order = $conn->query("SELECT COALESCE(MAX(display_order), 0) + 1 as next_order FROM gangnamunni_categories");
    $next_order = $max_order->fetch_assoc()['next_order'];

    while ($row = $result->fetch_assoc()) {
        $name = $conn->real_escape_string($row['name']);
        $slug = $conn->real_escape_string(strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $row['name'])));

        $conn->query("
            INSERT INTO gangnamunni_categories (name, slug, display_order, is_active, hms_hospital_type_id, created_at, updated_at)
            VALUES ('{$name}', '{$slug}', {$next_order}, 1, {$row['id']}, NOW(), NOW())
        ");

        if ($conn->affected_rows > 0) {
            $synced++;
            $next_order++;
            error_log("sync_hospital_type: HMS->gangnamunni new '{$name}' (ht_id={$row['id']})");
        }
    }
}

// Name change detection (hospital_type.name changed)
$result = $conn->query("
    SELECT ht.id, ht.name as new_name, gc.id as gc_id, gc.name as old_name
    FROM hospital_type ht
    JOIN gangnamunni_categories gc ON gc.hms_hospital_type_id = ht.id
    WHERE ht.name != gc.name
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $new_name = $conn->real_escape_string($row['new_name']);
        $conn->query("
            UPDATE gangnamunni_categories
            SET name = '{$new_name}', updated_at = NOW()
            WHERE id = {$row['gc_id']}
        ");
        if ($conn->affected_rows > 0) {
            $synced++;
            error_log("sync_hospital_type: name change '{$row['old_name']}' -> '{$new_name}'");
        }

        $old_name_escaped = $conn->real_escape_string($row['old_name']);
        $conn->query("
            UPDATE categories
            SET name = '{$new_name}', updated_at = NOW()
            WHERE name = '{$old_name_escaped}' AND tenant_id IS NULL
        ");
    }
}

// === Step 2: gangnamunni_categories -> HMS hospital_type ===
$result = $conn->query("
    SELECT gc.id, gc.name
    FROM gangnamunni_categories gc
    WHERE gc.hms_hospital_type_id IS NULL
    AND NOT EXISTS (
        SELECT 1 FROM hospital_type ht WHERE ht.name = gc.name
    )
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $name = $conn->real_escape_string($row['name']);

        $conn->query("
            INSERT INTO hospital_type (name, created_at, updated_at)
            VALUES ('{$name}', NOW(), NOW())
        ");

        if ($conn->affected_rows > 0) {
            $new_ht_id = $conn->insert_id;
            $conn->query("
                UPDATE gangnamunni_categories
                SET hms_hospital_type_id = {$new_ht_id}
                WHERE id = {$row['id']}
            ");
            $synced++;
            error_log("sync_hospital_type: gangnamunni->HMS new '{$name}' (ht_id={$new_ht_id})");
        }
    }
}

// Map by name where hms_hospital_type_id is NULL
$conn->query("
    UPDATE gangnamunni_categories gc
    JOIN hospital_type ht ON ht.name = gc.name
    SET gc.hms_hospital_type_id = ht.id
    WHERE gc.hms_hospital_type_id IS NULL
");
if ($conn->affected_rows > 0) {
    $synced += $conn->affected_rows;
}

// Reverse name change: gangnamunni -> hospital_type
$result = $conn->query("
    SELECT gc.id, gc.name as new_name, ht.id as ht_id, ht.name as old_name
    FROM gangnamunni_categories gc
    JOIN hospital_type ht ON gc.hms_hospital_type_id = ht.id
    WHERE gc.name != ht.name
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $new_name = $conn->real_escape_string($row['new_name']);
        $check = $conn->query("SELECT id FROM hospital_type WHERE name = '{$new_name}' AND id != {$row['ht_id']}");
        if ($check && $check->num_rows == 0) {
            $conn->query("
                UPDATE hospital_type
                SET name = '{$new_name}', updated_at = NOW()
                WHERE id = {$row['ht_id']}
            ");
            if ($conn->affected_rows > 0) {
                $synced++;
                error_log("sync_hospital_type: name change '{$row['old_name']}' -> '{$new_name}' (ht)");
            }
        }
    }
}

$conn->close();

if ($synced > 0) {
    error_log("sync_hospital_type: sync complete ({$synced} records)");
}
