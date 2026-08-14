<?php
/**
 * Admin pages intentionally kept in the codebase but unavailable at runtime.
 *
 * To restore a page later, remove its basename from the list below and restore
 * its navigation/link entries marked "TEMPORARILY DISABLED".
 */
function enforce_temporarily_disabled_admin_page(string $page): void
{
    $disabledPages = [
        'admin_event_entries.php',
        'admin_nurse_wizard.php',
        'admin_patient_wizard.php',
        'admin_pharmacist_wizard.php',
        'admin_medicine_wizard.php',
        'admin_medicines.php',
    ];

    if (in_array(basename($page), $disabledPages, true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        exit('404 Not Found');
    }
}

