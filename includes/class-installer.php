<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Installer {

    public static function activate() {
        self::create_tables();
        self::set_defaults();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
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
            PRIMARY KEY (id),
            KEY audit_uuid (audit_uuid),
            KEY status (status)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_audits );
        dbDelta( $sql_submissions );

        update_option( 'gapnext_wp_db_version', GAPNEXT_WP_VERSION );
    }

    private static function set_defaults() {
        add_option( 'gapnext_default_language', 'it' );
        add_option( 'gapnext_default_access_mode', 'public' );
        add_option( 'gapnext_consultant_logo_id', 0 );
        add_option( 'gapnext_checklist_page_id',   0 );
        add_option( 'gapnext_notification_email',  '' );
        add_option( 'gapnext_notify_consultant',   0 );
        add_option( 'gapnext_pdf_footer_text',     'Proprietary and Confidential' );
    }
}
