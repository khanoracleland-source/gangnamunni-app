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
        'admin_ai_settings.php',
        'partner_ai_settings.php',
        'ai_image_editor.php',
        'ai_ad_editor.php',
        'ai_ad_wizard.php',
        'ai_event_editor.php',
        'ai_event_wizard.php',
        'ai_raffle_editor.php',
        'ai_raffle_wizard.php',
        'admin_auto_test_v2.php',
        'admin_e2e_test.php',
        'admin_wizard_guide.php',
        'admin_auto_manual.php',
        'admin_screenshot_book.php',
        'admin_manual_feedback_list.php',
        'admin_demo_data.php',
        'admin_dev_feedback.php',
        'admin_usage_stats.php',
        'admin_code_check.php',
        'admin_server_info.php',
        'admin_backup.php',
        'admin_sync_wizard.php',
        'admin_client_spec.php',
        'admin_figma_review.php',
        'admin_screenshot_app.php',
        'admin_members_bulk_wizard.php',
        'admin_mail_compose_wizard.php',
        'admin_qa_dashboard.php',
        'admin_project_status.php',
        'admin_fix_log.php',
        'admin_progress.php',
    ];

    if (in_array(basename($page), $disabledPages, true)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        exit('404 Not Found');
    }
}
