<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers for remediation actions (admin/consultant side).
 *
 * All handlers require manage_options capability.
 * Client-side AJAX handlers will be added in Phase 3.
 */
class GapNext_Remediation_Ajax {

    public function __construct() {
        // Admin-side (consultant) actions
        add_action( 'wp_ajax_gapnext_remediation_init',       [ $this, 'handle_init' ] );
        add_action( 'wp_ajax_gapnext_remediation_update',     [ $this, 'handle_update' ] );
        add_action( 'wp_ajax_gapnext_remediation_bulk',       [ $this, 'handle_bulk' ] );
        add_action( 'wp_ajax_gapnext_remediation_review',     [ $this, 'handle_review' ] );
        add_action( 'wp_ajax_gapnext_remediation_comment',    [ $this, 'handle_comment' ] );
        add_action( 'wp_ajax_gapnext_create_client',          [ $this, 'handle_create_client' ] );
    }

    /**
     * Initialize remediation plan for a submission.
     * Sets audit phase to 'remediation'.
     */
    public function handle_init() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id = (int) ( $_POST['submission_id'] ?? 0 );
        if ( ! $sub_id ) wp_send_json_error( __( 'Invalid submission.', 'gapnext-wp' ) );

        if ( GapNext_Remediation_Log::is_initialized( $sub_id ) ) {
            wp_send_json_error( __( 'Remediation already initialized.', 'gapnext-wp' ) );
        }

        $sub = GapNext_Audit_Manager::get_submission( $sub_id );
        if ( ! $sub ) wp_send_json_error( __( 'Submission not found.', 'gapnext-wp' ) );

        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        if ( ! $standard ) wp_send_json_error( __( 'Standard not found.', 'gapnext-wp' ) );

        $answers = json_decode( $sub->answers, true ) ?: [];
        $count = GapNext_Remediation_Log::initialize_remediation( $sub_id, $answers, $standard['clauses'] );

        // Update audit phase
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'gapnext_audits',
            [ 'phase' => 'remediation' ],
            [ 'uuid' => $sub->audit_uuid ],
            [ '%s' ],
            [ '%s' ]
        );

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of questions */
                __( 'Remediation initialized for %d questions.', 'gapnext-wp' ),
                $count
            ),
            'count' => $count,
        ] );
    }

    /**
     * Update a single remediation item (action, priority, deadline, responsible, status).
     */
    public function handle_update() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id = (int) ( $_POST['submission_id'] ?? 0 );
        $ref    = sanitize_text_field( $_POST['question_ref'] ?? '' );
        $field  = sanitize_key( $_POST['field'] ?? '' );
        $value  = wp_unslash( $_POST['value'] ?? '' );

        if ( ! $sub_id || ! $ref || ! $field ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        if ( ! GapNext_Audit_Manager::get_submission( $sub_id ) ) {
            wp_send_json_error( __( 'Submission not found.', 'gapnext-wp' ) );
        }

        switch ( $field ) {
            case 'action':
                $current = GapNext_Remediation_State::for_question( $sub_id, $ref );
                $type = $current['corrective_action'] === '' ? 'action_set' : 'action_updated';
                GapNext_Remediation_Log::insert( $sub_id, $ref, $type, [
                    'action' => sanitize_textarea_field( $value ),
                ] );
                break;

            case 'priority':
                $value = sanitize_key( $value );
                if ( ! in_array( $value, GapNext_Remediation_Log::PRIORITIES, true ) ) {
                    wp_send_json_error( __( 'Invalid priority.', 'gapnext-wp' ) );
                }
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'priority_set', [
                    'priority' => $value,
                ] );
                break;

            case 'deadline':
                $value = sanitize_text_field( $value );
                if ( $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                    wp_send_json_error( __( 'Invalid date format.', 'gapnext-wp' ) );
                }
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'deadline_set', [
                    'deadline' => $value ?: null,
                ] );
                break;

            case 'responsible':
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'responsible_set', [
                    'responsible' => sanitize_text_field( $value ),
                ] );
                break;

            case 'status':
                $value = sanitize_key( $value );
                if ( ! in_array( $value, GapNext_Remediation_Log::STATUSES, true ) ) {
                    wp_send_json_error( __( 'Invalid status.', 'gapnext-wp' ) );
                }
                $current = GapNext_Remediation_State::for_question( $sub_id, $ref );
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'status_changed', [
                    'old_status' => $current['status'],
                    'new_status' => $value,
                ] );
                break;

            default:
                wp_send_json_error( __( 'Unknown field.', 'gapnext-wp' ) );
        }

        // Return updated state
        $state = GapNext_Remediation_State::for_question( $sub_id, $ref );
        unset( $state['events'] ); // don't send full event list in response
        wp_send_json_success( $state );
    }

    /**
     * Bulk update: set priority or deadline for multiple questions at once.
     */
    public function handle_bulk() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id = (int) ( $_POST['submission_id'] ?? 0 );
        $field  = sanitize_key( $_POST['field'] ?? '' );
        $value  = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        $refs   = isset( $_POST['refs'] ) && is_array( $_POST['refs'] ) ? $_POST['refs'] : [];

        if ( ! $sub_id || ! $field || empty( $refs ) ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        if ( ! GapNext_Audit_Manager::get_submission( $sub_id ) ) {
            wp_send_json_error( __( 'Submission not found.', 'gapnext-wp' ) );
        }

        if ( $field === 'deadline' && $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
            wp_send_json_error( __( 'Invalid date format.', 'gapnext-wp' ) );
        }

        $updated = 0;
        foreach ( $refs as $ref ) {
            $ref = sanitize_text_field( $ref );
            if ( ! $ref ) continue;

            switch ( $field ) {
                case 'priority':
                    if ( in_array( $value, GapNext_Remediation_Log::PRIORITIES, true ) ) {
                        GapNext_Remediation_Log::insert( $sub_id, $ref, 'priority_set', [
                            'priority' => $value,
                        ] );
                        $updated++;
                    }
                    break;
                case 'deadline':
                    GapNext_Remediation_Log::insert( $sub_id, $ref, 'deadline_set', [
                        'deadline' => $value ?: null,
                    ] );
                    $updated++;
                    break;
            }
        }

        wp_send_json_success( [ 'updated' => $updated ] );
    }

    /**
     * Review: approve or reject a client's proposed answer change.
     */
    public function handle_review() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id   = (int) ( $_POST['submission_id'] ?? 0 );
        $ref      = sanitize_text_field( $_POST['question_ref'] ?? '' );
        $decision = sanitize_key( $_POST['decision'] ?? '' ); // 'approve' or 'reject'
        $feedback = sanitize_textarea_field( wp_unslash( $_POST['feedback'] ?? '' ) );

        if ( ! $sub_id || ! $ref || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        if ( ! GapNext_Audit_Manager::get_submission( $sub_id ) ) {
            wp_send_json_error( __( 'Submission not found.', 'gapnext-wp' ) );
        }

        $current = GapNext_Remediation_State::for_question( $sub_id, $ref );

        if ( ! $current['pending_approval'] ) {
            wp_send_json_error( __( 'No pending answer to review.', 'gapnext-wp' ) );
        }

        if ( $decision === 'approve' ) {
            GapNext_Remediation_Log::insert( $sub_id, $ref, 'answer_approved', [
                'approved_value' => $current['proposed_answer'],
                'feedback'       => $feedback,
                'new_status'     => 'verified',
            ] );
        } else {
            GapNext_Remediation_Log::insert( $sub_id, $ref, 'answer_rejected', [
                'rejected_value' => $current['proposed_answer'],
                'feedback'       => $feedback,
                'new_status'     => 'in_progress',
            ] );
        }

        $state = GapNext_Remediation_State::for_question( $sub_id, $ref );
        unset( $state['events'] );
        wp_send_json_success( $state );
    }

    /**
     * Add a comment to a remediation item.
     */
    public function handle_comment() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id  = (int) ( $_POST['submission_id'] ?? 0 );
        $ref     = sanitize_text_field( $_POST['question_ref'] ?? '' );
        $comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

        if ( ! $sub_id || ! $ref || ! $comment ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        if ( ! GapNext_Audit_Manager::get_submission( $sub_id ) ) {
            wp_send_json_error( __( 'Submission not found.', 'gapnext-wp' ) );
        }

        GapNext_Remediation_Log::insert( $sub_id, $ref, 'comment', [
            'comment' => $comment,
        ] );

        wp_send_json_success( [ 'saved' => true ] );
    }

    /**
     * Create a WP client user and grant access to an audit.
     */
    public function handle_create_client() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $email      = sanitize_email( $_POST['email'] ?? '' );
        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $audit_uuid = sanitize_text_field( $_POST['audit_uuid'] ?? '' );

        if ( ! $email || ! $name || ! $audit_uuid ) {
            wp_send_json_error( __( 'Email, name, and audit are required.', 'gapnext-wp' ) );
        }

        $result = GapNext_Client_Role::create_client_user( $email, $name, $audit_uuid );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $user = get_user_by( 'ID', $result );
        wp_send_json_success( [
            'user_id'      => $result,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
        ] );
    }
}
