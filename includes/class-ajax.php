<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_gapnext_submit',            [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_gapnext_submit',     [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_gapnext_autosave',          [ $this, 'handle_autosave' ] );
        add_action( 'wp_ajax_nopriv_gapnext_autosave',   [ $this, 'handle_autosave' ] );
        add_action( 'admin_post_gapnext_download',        [ $this, 'handle_download' ] );
        add_action( 'admin_post_nopriv_gapnext_download', [ $this, 'handle_download' ] );
    }

    public function handle_autosave() {
        if ( ! check_ajax_referer( 'gapnext_autosave', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'gapnext-wp' ), 403 );
        }

        $uuid = sanitize_text_field( wp_unslash( $_POST['audit_uuid'] ?? '' ) );
        if ( empty( $uuid ) ) {
            wp_send_json_error( __( 'Invalid audit.', 'gapnext-wp' ), 400 );
        }

        $audit = GapNext_Audit_Manager::get_audit_by_uuid( $uuid );
        if ( ! $audit ) {
            wp_send_json_error( __( 'Audit not found.', 'gapnext-wp' ), 404 );
        }

        $data = [
            'audit_uuid'         => $uuid,
            'standard_id'        => $audit->standard_id,
            'language'           => $audit->language,
            'company_name'       => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
            'company_address'    => sanitize_text_field( wp_unslash( $_POST['company_address'] ?? '' ) ),
            'company_vat'        => sanitize_text_field( wp_unslash( $_POST['company_vat'] ?? '' ) ),
            'company_sector'     => sanitize_text_field( wp_unslash( $_POST['company_sector'] ?? '' ) ),
            'contact_name'       => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
            'contact_role'       => sanitize_text_field( wp_unslash( $_POST['contact_role'] ?? '' ) ),
            'contact_email'      => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
            'contact_phone'      => sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) ),
            'consultant_name'    => sanitize_text_field( wp_unslash( $_POST['consultant_name'] ?? '' ) ),
            'consultant_company' => sanitize_text_field( wp_unslash( $_POST['consultant_company'] ?? '' ) ),
            'consultant_email'   => sanitize_email( wp_unslash( $_POST['consultant_email'] ?? '' ) ),
            'consultant_phone'   => sanitize_text_field( wp_unslash( $_POST['consultant_phone'] ?? '' ) ),
            'status'             => 'draft',
            'evidence_paths'     => '[]',
            'score'              => 0,
            'submitted_at'       => current_time( 'mysql' ),
            'filling_mode'       => in_array( $_POST['filling_mode'] ?? '', [ 'self', 'assisted' ], true )
                                        ? $_POST['filling_mode'] : 'assisted',
            'last_step'          => max( 0, (int) ( $_POST['last_step'] ?? 0 ) ),
        ];

        // Sanitize answers
        $raw_answers = isset( $_POST['answer'] ) && is_array( $_POST['answer'] ) ? $_POST['answer'] : [];
        $answers = [];
        foreach ( $raw_answers as $ref => $raw_val ) {
            $ref = sanitize_key( $ref );
            if ( ! $ref ) continue;
            if ( $raw_val === 'na' ) {
                $answers[ $ref ] = [ 'value' => 'na', 'note' => '' ];
            } else {
                $fval = (float) $raw_val;
                if ( in_array( $fval, [ 0.0, 0.5, 1.0 ], true ) ) {
                    $answers[ $ref ] = [ 'value' => $fval, 'note' => '' ];
                }
            }
        }

        // Merge notes
        $raw_notes = isset( $_POST['notes'] ) && is_array( $_POST['notes'] ) ? $_POST['notes'] : [];
        foreach ( $raw_notes as $ref => $note ) {
            $ref = sanitize_key( $ref );
            if ( $ref && isset( $answers[ $ref ] ) ) {
                $answers[ $ref ]['note'] = sanitize_textarea_field( wp_unslash( $note ) );
            }
        }

        $data['answers'] = wp_json_encode( $answers );

        global $wpdb;
        $draft_id = (int) ( $_POST['draft_id'] ?? 0 );

        if ( $draft_id > 0 ) {
            $rows = $wpdb->update(
                $wpdb->prefix . 'gapnext_submissions',
                $data,
                [ 'id' => $draft_id, 'audit_uuid' => $uuid, 'status' => 'draft' ]
            );
            if ( $rows !== false ) {
                // rows=0 means data was identical — still a successful save
                GapNext_Draft_Reminder::schedule( $draft_id, $data['contact_email'] );
                wp_send_json_success( [ 'draft_id' => $draft_id, 'saved_at' => $data['submitted_at'] ] );
            }
        }

        // Insert new draft
        $wpdb->insert( $wpdb->prefix . 'gapnext_submissions', $data );
        if ( $wpdb->insert_id ) {
            GapNext_Draft_Reminder::schedule( (int) $wpdb->insert_id, $data['contact_email'] );
            wp_send_json_success( [ 'draft_id' => $wpdb->insert_id, 'saved_at' => $data['submitted_at'] ] );
        }

        wp_send_json_error( __( 'Failed to save draft.', 'gapnext-wp' ), 500 );
    }

    public function handle_submit() {
        // Nonce verification
        if ( ! check_ajax_referer( 'gapnext_submit', 'nonce', false ) ) {
            wp_send_json_error( __( 'Security check failed.', 'gapnext-wp' ), 403 );
        }

        $uuid = sanitize_text_field( wp_unslash( $_POST['audit_uuid'] ?? '' ) );
        if ( empty( $uuid ) ) {
            wp_send_json_error( __( 'Invalid audit.', 'gapnext-wp' ), 400 );
        }

        $audit = GapNext_Audit_Manager::get_audit_by_uuid( $uuid );
        if ( ! $audit ) {
            wp_send_json_error( __( 'Audit not found.', 'gapnext-wp' ), 404 );
        }

        // Filling mode
        $filling_mode = in_array( $_POST['filling_mode'] ?? '', [ 'self', 'assisted' ], true )
                            ? $_POST['filling_mode'] : 'assisted';

        // Sanitize company / contact fields
        $data = [
            'audit_uuid'         => $uuid,
            'standard_id'        => $audit->standard_id,
            'language'           => $audit->language,
            'filling_mode'       => $filling_mode,
            'company_name'       => sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ),
            'company_address'    => sanitize_text_field( wp_unslash( $_POST['company_address'] ?? '' ) ),
            'company_vat'        => sanitize_text_field( wp_unslash( $_POST['company_vat'] ?? '' ) ),
            'company_sector'     => sanitize_text_field( wp_unslash( $_POST['company_sector'] ?? '' ) ),
            'contact_name'       => sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ),
            'contact_role'       => sanitize_text_field( wp_unslash( $_POST['contact_role'] ?? '' ) ),
            'contact_email'      => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
            'contact_phone'      => sanitize_text_field( wp_unslash( $_POST['contact_phone'] ?? '' ) ),
            'consultant_name'    => sanitize_text_field( wp_unslash( $_POST['consultant_name'] ?? '' ) ),
            'consultant_company' => sanitize_text_field( wp_unslash( $_POST['consultant_company'] ?? '' ) ),
            'consultant_email'   => sanitize_email( wp_unslash( $_POST['consultant_email'] ?? '' ) ),
            'consultant_phone'   => sanitize_text_field( wp_unslash( $_POST['consultant_phone'] ?? '' ) ),
        ];

        // Required field validation — consultant fields only required in assisted mode
        if ( empty( $data['company_name'] ) || empty( $data['contact_name'] ) || empty( $data['contact_email'] ) ) {
            wp_send_json_error( __( 'Required fields missing.', 'gapnext-wp' ), 400 );
        }
        if ( $filling_mode === 'assisted' && ( empty( $data['consultant_name'] ) || empty( $data['consultant_email'] ) ) ) {
            wp_send_json_error( __( 'Required fields missing.', 'gapnext-wp' ), 400 );
        }

        // Sanitize answers: key = sequential ref (e.g. "1"), value = float (0, 0.5, 1)
        $raw_answers = isset( $_POST['answer'] ) && is_array( $_POST['answer'] ) ? $_POST['answer'] : [];
        $answers = [];
        foreach ( $raw_answers as $ref => $raw_val ) {
            $ref = sanitize_key( $ref );
            if ( ! $ref ) continue;
            if ( $raw_val === 'na' ) {
                $answers[ $ref ] = 'na';
            } else {
                $fval = (float) $raw_val;
                if ( in_array( $fval, [ 0.0, 0.5, 1.0 ], true ) ) {
                    $answers[ $ref ] = $fval;
                }
            }
        }

        // Sanitize notes
        $raw_notes = isset( $_POST['notes'] ) && is_array( $_POST['notes'] ) ? $_POST['notes'] : [];
        $notes = [];
        foreach ( $raw_notes as $ref => $note ) {
            $ref = sanitize_key( $ref );
            if ( $ref ) {
                $notes[ $ref ] = sanitize_textarea_field( wp_unslash( $note ) );
            }
        }

        // Handle file uploads
        $evidence_paths = [];
        if ( ! empty( $_FILES['evidence'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';

            foreach ( $_FILES['evidence']['name'] as $ref => $file_list ) {
                $ref = sanitize_key( $ref );
                if ( ! $ref ) continue;
                $evidence_paths[ $ref ] = [];

                foreach ( $file_list as $i => $filename ) {
                    if ( empty( $filename ) || $_FILES['evidence']['error'][ $ref ][ $i ] !== UPLOAD_ERR_OK ) {
                        continue;
                    }

                    $upload_subdir = 'gapnext-evidence/' . $uuid . '/q-' . $ref;
                    $filter = function( $dirs ) use ( $upload_subdir ) {
                        $dirs['subdir'] = '/' . $upload_subdir;
                        $dirs['path']   = $dirs['basedir'] . '/' . $upload_subdir;
                        $dirs['url']    = $dirs['baseurl'] . '/' . $upload_subdir;
                        return $dirs;
                    };
                    add_filter( 'upload_dir', $filter );

                    $file = [
                        'name'     => $filename,
                        'type'     => $_FILES['evidence']['type'][ $ref ][ $i ],
                        'tmp_name' => $_FILES['evidence']['tmp_name'][ $ref ][ $i ],
                        'error'    => $_FILES['evidence']['error'][ $ref ][ $i ],
                        'size'     => $_FILES['evidence']['size'][ $ref ][ $i ],
                    ];
                    $result = wp_handle_upload( $file, [ 'test_form' => false ] );

                    remove_filter( 'upload_dir', $filter );

                    if ( isset( $result['file'] ) ) {
                        $evidence_paths[ $ref ][] = $result['file'];
                    }
                }
            }
        }

        // Merge answers with notes into structured format
        $answers_with_notes = [];
        foreach ( $answers as $ref => $val ) {
            $answers_with_notes[ $ref ] = [
                'value' => $val,
                'note'  => $notes[ $ref ] ?? '',
            ];
        }

        // Total questions is sent by the form as a hidden field — avoids loading the full
        // standard data file inside the AJAX handler (which can exhaust memory on large checklists).
        $total_questions  = max( 0, (int) ( $_POST['total_questions'] ?? 0 ) );

        // Per-status counts
        $count_compliant  = 0;
        $count_partial    = 0;
        $count_non_comply = 0;
        $count_na         = 0;
        foreach ( $answers as $val ) {
            if ( $val === 1.0 || $val === 1 )       $count_compliant++;
            elseif ( $val === 0.5 )                 $count_partial++;
            elseif ( $val === 0.0 || $val === 0 )   $count_non_comply++;
            elseif ( $val === 'na' )                $count_na++;
        }

        // Score = Conformities / (Total questions − N/A)
        $denominator = $total_questions - $count_na;
        $score       = $denominator > 0 ? $count_compliant / $denominator : 0.0;

        // Insert or promote draft → submitted (or demo)
        global $wpdb;
        $draft_id      = (int) ( $_POST['draft_id'] ?? 0 );
        $submit_status = 'submitted';
        if ( $audit->access_mode === 'demo' ) {
            $submit_status = 'demo';
            $filling_mode  = 'self';
            $data['filling_mode'] = 'self';
        }
        $submit_data = array_merge( $data, [
            'answers'        => wp_json_encode( $answers_with_notes ),
            'evidence_paths' => wp_json_encode( $evidence_paths ),
            'score'          => $score,
            'status'         => $submit_status,
            'submitted_at'   => current_time( 'mysql' ),
        ] );

        $submission_id = 0;
        if ( $draft_id > 0 ) {
            $rows = $wpdb->update(
                $wpdb->prefix . 'gapnext_submissions',
                $submit_data,
                [ 'id' => $draft_id, 'audit_uuid' => $uuid, 'status' => 'draft' ]
            );
            if ( $rows !== false ) {
                // rows=0 means data was identical — draft still exists and was promoted
                $submission_id = $draft_id;
                GapNext_Draft_Reminder::cancel( $draft_id );
            }
        }

        if ( ! $submission_id ) {
            $wpdb->insert( $wpdb->prefix . 'gapnext_submissions', $submit_data );
            $submission_id = $wpdb->insert_id ? (int) $wpdb->insert_id : 0;
        }

        if ( ! $submission_id ) {
            wp_send_json_error( __( 'Failed to save submission.', 'gapnext-wp' ), 500 );
        }

        // Send submission notification email
        self::send_notification( $data, $submission_id, $uuid, $score, [
            'total'       => $total_questions,
            'answered'    => count( $answers ),
            'compliant'   => $count_compliant,
            'partial'     => $count_partial,
            'non_comply'  => $count_non_comply,
            'na'          => $count_na,
        ] );

        wp_send_json_success( [
            'score'         => $score,
            'submission_id' => $submission_id,
            'audit_uuid'    => $uuid,
            'downloads'     => [],
            'stats'         => [
                'total'       => $total_questions,
                'answered'    => count( $answers ),
                'compliant'   => $count_compliant,
                'partial'     => $count_partial,
                'non_comply'  => $count_non_comply,
                'na'          => $count_na,
            ],
        ] );
    }

    public function handle_download() {
        $sub_id = (int) ( $_GET['sub'] ?? 0 );
        $uuid   = sanitize_text_field( wp_unslash( $_GET['audit'] ?? '' ) );
        $format = sanitize_key( $_GET['format'] ?? '' );

        if ( ! $sub_id || ! $uuid || ! in_array( $format, [ 'pdf', 'csv', 'md' ], true ) ) {
            wp_die( esc_html__( 'Invalid request.', 'gapnext-wp' ), '', [ 'response' => 400 ] );
        }

        $sub = GapNext_Audit_Manager::get_submission( $sub_id );
        if ( ! $sub || $sub->audit_uuid !== $uuid ) {
            wp_die( esc_html__( 'Not found.', 'gapnext-wp' ), '', [ 'response' => 404 ] );
        }

        // Enforce access control for login_required audits
        $audit = GapNext_Audit_Manager::get_audit_by_uuid( $uuid );
        if ( $audit && $audit->access_mode === 'login_required' ) {
            if ( ! is_user_logged_in() ) {
                wp_die( esc_html__( 'Login required.', 'gapnext-wp' ), '', [ 'response' => 403 ] );
            }
            // Allow admins and the assigned client user
            if ( ! current_user_can( 'manage_options' ) ) {
                $user_audits = GapNext_Client_Role::get_user_audits( get_current_user_id() );
                if ( ! in_array( $uuid, $user_audits, true ) ) {
                    wp_die( esc_html__( 'Access denied.', 'gapnext-wp' ), '', [ 'response' => 403 ] );
                }
            }
        }

        $base = 'gapnext-report-' . $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) );

        switch ( $format ) {
            case 'pdf':
                GapNext_Export_Pdf::stream( $sub );
                break;

            case 'csv':
                $content  = GapNext_Export_Csv::generate_string( $sub );
                $filename = sanitize_file_name( $base . '.csv' );
                while ( ob_get_level() ) ob_end_clean();
                header( 'Content-Type: text/csv; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
                exit;

            case 'md':
                $content  = GapNext_Export_Md::generate_string( $sub );
                $filename = sanitize_file_name( $base . '.md' );
                while ( ob_get_level() ) ob_end_clean();
                header( 'Content-Type: text/plain; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput
                exit;
        }
    }

    // =========================================================
    // NOTIFICATION EMAIL
    // =========================================================

    private static function send_notification( $data, $submission_id, $uuid, $score, $stats ) {
        $lang        = $data['language'] ?? 'en';
        $score_pct   = round( $score * 100, 1 );
        $standard_id = $data['standard_id'] ?? '';
        $date_fmt    = date_i18n( 'd/m/Y' );

        // Download URLs (public — nopriv handler)
        $dl_base = admin_url( 'admin-post.php' ) . '?action=gapnext_download&sub=' . $submission_id . '&audit=' . rawurlencode( $uuid );
        $url_pdf = $dl_base . '&format=pdf';
        $url_csv = $dl_base . '&format=csv';
        $url_md  = $dl_base . '&format=md';

        // Results page URL
        $results_pid = (int) get_option( 'gapnext_results_page_id', 0 );
        $results_url = '';
        if ( $results_pid ) {
            $results_url = add_query_arg(
                [ 'sub' => $submission_id, 'audit' => $uuid ],
                get_permalink( $results_pid )
            );
        }

        // Recipients
        $consultant_email   = sanitize_email( $data['consultant_email'] ?? '' );
        $notification_email = sanitize_email( get_option( 'gapnext_notification_email', '' ) );
        $recipients         = array_unique( array_filter( [ $consultant_email, $notification_email ] ) );
        if ( empty( $recipients ) ) return;

        $score_color = $score >= 0.7 ? '#16a34a' : ( $score >= 0.4 ? '#d97706' : '#dc2626' );
        $is_it       = $lang === 'it';

        $subject = '[GapNext] ' . ( $is_it ? 'Gap Analysis completata' : 'Gap Analysis Completed' ) . ' — ' . $data['company_name'];

        $details = [
            ( $is_it ? 'Azienda'    : 'Company' )    => $data['company_name'] . ( ! empty( $data['company_sector'] )    ? ' — ' . $data['company_sector']    : '' ),
            ( $is_it ? 'Referente'  : 'Contact' )    => $data['contact_name']  . ( ! empty( $data['contact_email'] )     ? ' <' . $data['contact_email'] . '>' : '' ),
            ( $is_it ? 'Consulente' : 'Consultant' ) => $data['consultant_name'] . ( ! empty( $data['consultant_company'] ) ? ' — ' . $data['consultant_company'] : '' ),
            'Standard'                               => $standard_id,
            ( $is_it ? 'Data'       : 'Date' )       => $date_fmt,
            ( $is_it ? 'Conformi'   : 'Compliant' )  => $stats['compliant'] . ' / ' . $stats['total'],
            ( $is_it ? 'Parziali'   : 'Partial' )    => $stats['partial'],
            ( $is_it ? 'Non Conformi' : 'Non-Compliant' ) => $stats['non_comply'],
            ( $is_it ? 'Non Applicabili' : 'Not Applicable' ) => $stats['na'],
        ];

        // Build HTML body
        $b  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
        $b .= '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;">';
        $b .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;"><tr><td align="center">';
        $b .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">';

        // Header
        $b .= '<tr><td style="background:#1e40af;padding:24px 32px;">';
        $b .= '<p style="margin:0;font-size:20px;font-weight:bold;color:#ffffff;">Gap Analysis Report</p>';
        $b .= '<p style="margin:4px 0 0;font-size:13px;color:#bfdbfe;">' . esc_html( $is_it ? 'Una nuova Gap Analysis è stata completata.' : 'A new Gap Analysis has been completed.' ) . '</p>';
        $b .= '</td></tr>';

        // Score badge
        $b .= '<tr><td style="padding:24px 32px 0;"><table width="100%" cellpadding="0" cellspacing="0"><tr>';
        $b .= '<td style="background:' . $score_color . ';border-radius:6px;padding:16px;text-align:center;">';
        $b .= '<p style="margin:0;font-size:32px;font-weight:bold;color:#ffffff;">' . esc_html( $score_pct ) . '%</p>';
        $b .= '<p style="margin:4px 0 0;font-size:13px;color:#ffffff;">' . esc_html( $is_it ? 'Punteggio di conformità' : 'Compliance score' ) . '</p>';
        $b .= '</td></tr></table></td></tr>';

        // Details
        $b .= '<tr><td style="padding:24px 32px 0;">';
        $b .= '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;">';
        $alt = false;
        foreach ( $details as $key => $val ) {
            $bg  = $alt ? '#f8fafc' : '#ffffff';
            $b  .= '<tr style="background:' . $bg . ';">';
            $b  .= '<td style="width:40%;color:#64748b;border-bottom:1px solid #e2e8f0;">' . esc_html( $key ) . '</td>';
            $b  .= '<td style="color:#1e293b;font-weight:bold;border-bottom:1px solid #e2e8f0;">' . esc_html( $val ) . '</td>';
            $b  .= '</tr>';
            $alt = ! $alt;
        }
        $b .= '</table></td></tr>';

        // Download buttons
        $b .= '<tr><td style="padding:24px 32px 0;">';
        $b .= '<p style="margin:0 0 12px;font-size:14px;font-weight:bold;color:#1e293b;">' . esc_html( $is_it ? 'Scarica il report' : 'Download the report' ) . '</p>';
        $b .= '<table cellpadding="0" cellspacing="8"><tr>';
        $b .= '<td><a href="' . esc_url( $url_pdf ) . '" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:5px;font-size:13px;font-weight:bold;">&#8659; PDF</a></td>';
        $b .= '<td><a href="' . esc_url( $url_csv ) . '" style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:5px;font-size:13px;font-weight:bold;">&#8659; CSV</a></td>';
        $b .= '<td><a href="' . esc_url( $url_md )  . '" style="display:inline-block;background:#7c3aed;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:5px;font-size:13px;font-weight:bold;">&#8659; Markdown</a></td>';
        $b .= '</tr></table></td></tr>';

        // Results page link
        if ( $results_url ) {
            $b .= '<tr><td style="padding:16px 32px 0;">';
            $b .= '<a href="' . esc_url( $results_url ) . '" style="display:inline-block;background:#f8fafc;border:1px solid #1e40af;color:#1e40af;text-decoration:none;padding:10px 18px;border-radius:5px;font-size:13px;">';
            $b .= esc_html( $is_it ? '&#128279; Visualizza risultati online' : '&#128279; View results online' );
            $b .= '</a></td></tr>';
        }

        // Footer
        $b .= '<tr><td style="padding:24px 32px;border-top:1px solid #e2e8f0;margin-top:24px;">';
        $b .= '<p style="margin:0;font-size:12px;color:#94a3b8;">' . esc_html( $is_it ? 'Messaggio generato automaticamente da GapNext WP.' : 'Automatically generated by GapNext WP.' ) . '</p>';
        $b .= '</td></tr>';

        $b .= '</table></td></tr></table></body></html>';

        wp_mail( $recipients, $subject, $b, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

}
