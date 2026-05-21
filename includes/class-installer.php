<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Installer {

    public static function activate() {
        GapNext_Client_Role::register_role();
        self::create_tables();
        self::set_defaults();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();

        $crons = _get_cron_array();
        if ( is_array( $crons ) ) {
            foreach ( $crons as $timestamp => $hooks ) {
                if ( isset( $hooks['gapnext_send_draft_reminder'] ) ) {
                    foreach ( $hooks['gapnext_send_draft_reminder'] as $key => $event ) {
                        wp_unschedule_event( $timestamp, 'gapnext_send_draft_reminder', $event['args'] );
                    }
                }
            }
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_audits = "CREATE TABLE {$wpdb->prefix}gapnext_audits (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            standard_id VARCHAR(100) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'it',
            access_mode VARCHAR(20) NOT NULL DEFAULT 'public',
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            phase VARCHAR(20) NOT NULL DEFAULT 'gap_analysis',
            client_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid)
        ) $charset;";

        $sql_submissions = "CREATE TABLE {$wpdb->prefix}gapnext_submissions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            audit_uuid VARCHAR(36) NOT NULL,
            standard_id VARCHAR(100) NOT NULL,
            language VARCHAR(5) NOT NULL DEFAULT 'it',
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            company_name VARCHAR(255) NOT NULL DEFAULT '',
            company_address VARCHAR(500) NOT NULL DEFAULT '',
            company_vat VARCHAR(100) NOT NULL DEFAULT '',
            company_sector VARCHAR(255) NOT NULL DEFAULT '',
            contact_name VARCHAR(255) NOT NULL DEFAULT '',
            contact_role VARCHAR(255) NOT NULL DEFAULT '',
            contact_email VARCHAR(255) NOT NULL DEFAULT '',
            contact_phone VARCHAR(100) NOT NULL DEFAULT '',
            consultant_name VARCHAR(255) NOT NULL DEFAULT '',
            consultant_company VARCHAR(255) NOT NULL DEFAULT '',
            consultant_email VARCHAR(255) NOT NULL DEFAULT '',
            consultant_phone VARCHAR(100) NOT NULL DEFAULT '',
            answers LONGTEXT NOT NULL DEFAULT '',
            evidence_paths LONGTEXT NOT NULL DEFAULT '',
            score FLOAT NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'submitted',
            ai_report_url  VARCHAR(500) NULL DEFAULT NULL,
            ai_report_uuid VARCHAR(36)  NULL DEFAULT NULL,
            remediation_score FLOAT NULL DEFAULT NULL,
            filling_mode VARCHAR(20) NOT NULL DEFAULT 'assisted',
            last_step INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY audit_uuid (audit_uuid),
            KEY status (status)
        ) $charset;";

        $sql_generations = "CREATE TABLE {$wpdb->prefix}gapnext_ai_report_generations (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT(20) UNSIGNED NOT NULL,
            uuid VARCHAR(36) NOT NULL DEFAULT '',
            download_url VARCHAR(500) NOT NULL DEFAULT '',
            comments TEXT NOT NULL,
            generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id)
        ) $charset;";

        $sql_remediation_log = "CREATE TABLE {$wpdb->prefix}gapnext_remediation_log (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT(20) UNSIGNED NOT NULL,
            question_ref VARCHAR(50) NOT NULL DEFAULT '',
            event_type VARCHAR(30) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            user_role VARCHAR(20) NOT NULL DEFAULT '',
            data LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_question (submission_id, question_ref, created_at),
            KEY submission_type (submission_id, event_type),
            KEY user_events (user_id, created_at)
        ) $charset;";

        $sql_client_access = "CREATE TABLE {$wpdb->prefix}gapnext_client_access (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            audit_uuid VARCHAR(36) NOT NULL,
            granted_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_audit (user_id, audit_uuid),
            KEY audit_uuid (audit_uuid)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_audits );
        dbDelta( $sql_submissions );
        dbDelta( $sql_generations );
        dbDelta( $sql_remediation_log );
        dbDelta( $sql_client_access );

        update_option( 'gapnext_wp_db_version', GAPNEXT_WP_VERSION );
    }

    private static function set_defaults() {
        add_option( 'gapnext_default_language', 'it' );
        add_option( 'gapnext_default_access_mode', 'public' );
        add_option( 'gapnext_consultant_logo_id', 0 );
        add_option( 'gapnext_checklist_page_id',   0 );
        add_option( 'gapnext_notification_email',  '' );
        add_option( 'gapnext_notify_consultant',   0 );
        add_option( 'gapnext_draft_reminder_enabled', 1 );
        add_option( 'gapnext_demo_question_limit', 15 );
        add_option( 'gapnext_pdf_footer_text',     'Proprietary and Confidential' );
    }
}
