<?php
/**
 * GapNext WP — Uninstall
 *
 * Fired when the plugin is deleted via WP Admin → Plugins → Delete.
 * Removes all custom database tables and plugin options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'gapnext_remediation_log',
    $wpdb->prefix . 'gapnext_client_access',
    $wpdb->prefix . 'gapnext_ai_report_generations',
    $wpdb->prefix . 'gapnext_submissions',
    $wpdb->prefix . 'gapnext_audits',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options
$options = [
    'gapnext_wp_db_version',
    'gapnext_default_language',
    'gapnext_default_access_mode',
    'gapnext_consultant_logo_id',
    'gapnext_checklist_page_id',
    'gapnext_results_page_id',
    'gapnext_notification_email',
    'gapnext_notify_consultant',
    'gapnext_pdf_footer_text',
    'gapnext_ai_api_url',
    'gapnext_ai_api_key',
    'gapnext_ai_branding_primary_color',
    'gapnext_ai_branding_secondary_color',
    'gapnext_ai_branding_header_title',
    'gapnext_ai_branding_footer_text',
    'gapnext_ai_branding_prepared_by',
    'gapnext_ai_branding_company_website',
    'gapnext_ai_branding_company_phone',
    'gapnext_draft_reminder_enabled',
    'gapnext_demo_question_limit',
    'gapnext_dashboard_page_id',
    'gapnext_custom_css',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove gapnext_client role
remove_role( 'gapnext_client' );

// Clean up transients (connection status per user)
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gapnext_%' OR option_name LIKE '_transient_timeout_gapnext_%'"
);
