<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Draft_Reminder {

    const HOOK = 'gapnext_send_draft_reminder';

    public function __construct() {
        add_action( self::HOOK, [ $this, 'send_reminder' ] );
    }

    public static function schedule( int $draft_id, string $contact_email ): void {
        if ( ! get_option( 'gapnext_draft_reminder_enabled', 1 ) ) {
            return;
        }
        if ( empty( $contact_email ) ) {
            return;
        }

        self::cancel( $draft_id );
        wp_schedule_single_event( time() + 86400, self::HOOK, [ $draft_id ] );
    }

    public static function cancel( int $draft_id ): void {
        $timestamp = wp_next_scheduled( self::HOOK, [ $draft_id ] );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK, [ $draft_id ] );
        }
    }

    public function send_reminder( int $draft_id ): void {
        self::do_send( $draft_id, 'automatic' );
    }

    public static function send_manual( int $draft_id ): array {
        return self::do_send( $draft_id, 'manual', get_current_user_id() );
    }

    private static function do_send( int $draft_id, string $trigger_type = 'automatic', int $sent_by = 0 ): array {
        global $wpdb;

        $submission = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gapnext_submissions WHERE id = %d LIMIT 1",
            $draft_id
        ) );

        if ( ! $submission || $submission->status !== 'draft' ) {
            return [ 'success' => false, 'error' => 'not_draft' ];
        }

        $contact_email = $submission->contact_email;
        if ( empty( $contact_email ) ) {
            return [ 'success' => false, 'error' => 'no_email' ];
        }

        $audit = GapNext_Audit_Manager::get_audit_by_uuid( $submission->audit_uuid );
        if ( ! $audit ) {
            return [ 'success' => false, 'error' => 'no_audit' ];
        }

        $lang  = $submission->language ?: 'en';
        $is_it = $lang === 'it';

        $checklist_pid = (int) get_option( 'gapnext_checklist_page_id', 0 );
        if ( ! $checklist_pid ) {
            return [ 'success' => false, 'error' => 'no_checklist_page' ];
        }
        $resume_url = add_query_arg( 'audit', $submission->audit_uuid, get_permalink( $checklist_pid ) );

        $standard_name = $submission->standard_id;
        $standard_file = GAPNEXT_WP_DIR . 'includes/data/' . $submission->standard_id . '_' . $lang . '.php';
        if ( file_exists( $standard_file ) ) {
            $standard = include $standard_file;
            if ( is_array( $standard ) && ! empty( $standard['name'] ) ) {
                $standard_name = $standard['name'];
            }
        }

        $company_name = $submission->company_name ?: ( $is_it ? 'la tua azienda' : 'your company' );

        $subject = '[GapNext] ' . ( $is_it
            ? 'Completa la tua Gap Analysis'
            : 'Complete your Gap Analysis' );

        $body = self::build_email_html( $is_it, $company_name, $standard_name, $resume_url );

        $sent = wp_mail( $contact_email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

        self::log( $draft_id, $trigger_type, $contact_email, $sent ? 'sent' : 'failed', $sent_by );

        return [ 'success' => $sent, 'email' => $contact_email ];
    }

    private static function log( int $submission_id, string $trigger_type, string $email, string $status, int $sent_by = 0 ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'gapnext_reminder_log',
            [
                'submission_id'  => $submission_id,
                'trigger_type'   => $trigger_type,
                'recipient_email'=> $email,
                'status'         => $status,
                'sent_by'        => $sent_by,
                'sent_at'        => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%d', '%s' ]
        );
    }

    public static function get_log( int $submission_id ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gapnext_reminder_log WHERE submission_id = %d ORDER BY sent_at DESC",
            $submission_id
        ) );
        return $rows ?: [];
    }

    public static function get_next_scheduled( int $draft_id ): int {
        return (int) wp_next_scheduled( self::HOOK, [ $draft_id ] );
    }

    private static function build_email_html( bool $is_it, string $company_name, string $standard_name, string $resume_url ): string {
        $b  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
        $b .= '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">';
        $b .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;"><tr><td align="center">';
        $b .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">';

        $b .= '<tr><td style="background:#1e40af;padding:24px 32px;">';
        $b .= '<p style="margin:0;font-size:20px;font-weight:bold;color:#ffffff;">Gap Analysis</p>';
        $b .= '<p style="margin:4px 0 0;font-size:13px;color:#bfdbfe;">' . esc_html( $is_it
            ? 'Promemoria: completa la tua Gap Analysis'
            : 'Reminder: complete your Gap Analysis' ) . '</p>';
        $b .= '</td></tr>';

        $b .= '<tr><td style="padding:32px;">';
        if ( $is_it ) {
            $b .= '<p style="margin:0 0 16px;font-size:15px;color:#1e293b;line-height:1.6;">';
            $b .= esc_html( 'Hai iniziato una Gap Analysis per ' . $company_name . ' relativa allo standard ' . $standard_name . '.' );
            $b .= '</p>';
            $b .= '<p style="margin:0 0 24px;font-size:15px;color:#1e293b;line-height:1.6;">';
            $b .= esc_html( 'I tuoi progressi sono stati salvati. Clicca il pulsante qui sotto per continuare la compilazione.' );
            $b .= '</p>';
        } else {
            $b .= '<p style="margin:0 0 16px;font-size:15px;color:#1e293b;line-height:1.6;">';
            $b .= esc_html( 'You started a Gap Analysis for ' . $company_name . ' on standard ' . $standard_name . '.' );
            $b .= '</p>';
            $b .= '<p style="margin:0 0 24px;font-size:15px;color:#1e293b;line-height:1.6;">';
            $b .= esc_html( 'Your progress has been saved. Click the button below to continue where you left off.' );
            $b .= '</p>';
        }

        $b .= '<table cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr>';
        $b .= '<td style="background:#1e40af;border-radius:6px;">';
        $b .= '<a href="' . esc_url( $resume_url ) . '" style="display:inline-block;padding:14px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;">';
        $b .= esc_html( $is_it ? 'Continua la Gap Analysis' : 'Continue Gap Analysis' );
        $b .= '</a></td></tr></table>';

        $b .= '</td></tr>';

        $b .= '<tr><td style="padding:24px 32px;border-top:1px solid #e2e8f0;">';
        $b .= '<p style="margin:0;font-size:12px;color:#94a3b8;">' . esc_html( $is_it
            ? 'Messaggio generato automaticamente da GapNext WP.'
            : 'Automatically generated by GapNext WP.' ) . '</p>';
        $b .= '</td></tr>';

        $b .= '</table></td></tr></table></body></html>';

        return $b;
    }
}
