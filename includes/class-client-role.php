<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Client_Role {

    public function __construct() {
        add_filter( 'login_redirect', [ $this, 'redirect_client_after_login' ], 10, 3 );
        add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar_for_clients' ] );
        add_action( 'admin_init', [ $this, 'block_admin_for_clients' ] );
    }

    /**
     * Register the gapnext_client role. Safe to call multiple times —
     * WordPress ignores add_role() if the role already exists.
     */
    public static function register_role() {
        add_role( 'gapnext_client', __( 'GapNext Client', 'gapnext-wp' ), [
            'read' => true,
        ] );
    }

    /**
     * After login, redirect gapnext_client users to the dashboard page
     * instead of wp-admin.
     */
    public function redirect_client_after_login( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! is_wp_error( $user ) && in_array( 'gapnext_client', (array) $user->roles, true ) ) {
            $dashboard_pid = (int) get_option( 'gapnext_dashboard_page_id', 0 );
            if ( $dashboard_pid ) {
                return get_permalink( $dashboard_pid );
            }
            return home_url();
        }
        return $redirect_to;
    }

    /**
     * Hide the WP admin bar for gapnext_client users.
     */
    public function hide_admin_bar_for_clients( $show ) {
        if ( is_user_logged_in() && current_user_can( 'gapnext_client' ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return $show;
    }

    /**
     * Block wp-admin access for gapnext_client users (redirect to home).
     * AJAX requests are still allowed.
     */
    public function block_admin_for_clients() {
        if ( wp_doing_ajax() ) return;
        if ( current_user_can( 'gapnext_client' ) && ! current_user_can( 'manage_options' ) ) {
            $dashboard_pid = (int) get_option( 'gapnext_dashboard_page_id', 0 );
            wp_safe_redirect( $dashboard_pid ? get_permalink( $dashboard_pid ) : home_url() );
            exit;
        }
    }

    /**
     * Create a new WP user with gapnext_client role and grant access to an audit.
     *
     * @param string $email    Client email
     * @param string $name     Display name
     * @param string $audit_uuid  Audit to grant access to
     * @return int|WP_Error    User ID on success, WP_Error on failure
     */
    public static function create_client_user( string $email, string $name, string $audit_uuid ) {
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'gapnext-wp' ) );
        }

        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            // User exists — just grant access
            self::grant_access( $existing->ID, $audit_uuid );
            return $existing->ID;
        }

        $password = wp_generate_password( 16, true, true );
        $username = sanitize_user( strtok( $email, '@' ), true );

        // Ensure unique username
        $base = $username;
        $i = 1;
        while ( username_exists( $username ) ) {
            $username = $base . $i;
            $i++;
        }

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'role'         => 'gapnext_client',
        ] );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        self::grant_access( $user_id, $audit_uuid );

        // Send the new user a password reset / welcome email
        wp_new_user_notification( $user_id, null, 'user' );

        return $user_id;
    }

    /**
     * Grant a user access to a specific audit.
     */
    public static function grant_access( int $user_id, string $audit_uuid ) {
        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'gapnext_client_access',
            [
                'user_id'    => $user_id,
                'audit_uuid' => $audit_uuid,
                'granted_by' => get_current_user_id(),
                'granted_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%d', '%s' ]
        );
    }

    /**
     * Revoke a user's access to a specific audit.
     */
    public static function revoke_access( int $user_id, string $audit_uuid ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'gapnext_client_access',
            [ 'user_id' => $user_id, 'audit_uuid' => $audit_uuid ],
            [ '%d', '%s' ]
        );
    }

    /**
     * Get all audit UUIDs a user has access to.
     *
     * @return string[]
     */
    public static function get_user_audits( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT audit_uuid FROM {$wpdb->prefix}gapnext_client_access WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get all users who have access to a specific audit.
     *
     * @return array[] Each element: ['user_id' => int, 'email' => string, 'display_name' => string]
     */
    public static function get_audit_clients( string $audit_uuid ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ca.user_id, u.user_email, u.display_name
             FROM {$wpdb->prefix}gapnext_client_access ca
             JOIN {$wpdb->users} u ON u.ID = ca.user_id
             WHERE ca.audit_uuid = %s
             ORDER BY u.display_name",
            $audit_uuid
        ), ARRAY_A );
        return $rows ?: [];
    }
}
