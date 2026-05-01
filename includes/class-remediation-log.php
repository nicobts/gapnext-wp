<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Writes immutable events to the gapnext_remediation_log table.
 *
 * Each event represents a single atomic change to a remediation item.
 * Events are never updated or deleted — current state is derived
 * by replaying events in chronological order.
 *
 * Event types:
 *   action_set       — corrective action text defined
 *   action_updated   — corrective action text modified
 *   priority_set     — priority assigned or changed
 *   deadline_set     — deadline assigned or changed
 *   responsible_set  — responsible person assigned or changed
 *   status_changed   — remediation status transition
 *   answer_changed   — client proposes a new answer value
 *   answer_approved  — consultant approves proposed answer
 *   answer_rejected  — consultant rejects proposed answer
 *   evidence_added   — file uploaded as remediation evidence
 *   comment          — free-text note/discussion
 *   verified         — consultant marks item as verified/closed
 */
class GapNext_Remediation_Log {

    /**
     * Valid event types.
     */
    private const EVENT_TYPES = [
        'action_set',
        'action_updated',
        'priority_set',
        'deadline_set',
        'responsible_set',
        'status_changed',
        'answer_changed',
        'answer_approved',
        'answer_rejected',
        'evidence_added',
        'comment',
        'verified',
    ];

    /**
     * Valid remediation statuses.
     */
    public const STATUSES = [
        'open',
        'in_progress',
        'pending_review',
        'verified',
        'rejected',
    ];

    /**
     * Valid priorities.
     */
    public const PRIORITIES = [ 'high', 'medium', 'low' ];

    /**
     * Insert a single event into the log.
     *
     * @param int    $submission_id
     * @param string $question_ref   Question reference (e.g. "4_1"), or '' for submission-level events
     * @param string $event_type     One of self::EVENT_TYPES
     * @param array  $data           Event-specific payload (will be JSON-encoded)
     * @param int    $user_id        WP user ID (0 = system)
     * @param string $user_role      'consultant' or 'client'
     * @return int|false             Inserted row ID or false on failure
     */
    public static function insert(
        int $submission_id,
        string $question_ref,
        string $event_type,
        array $data,
        int $user_id = 0,
        string $user_role = ''
    ) {
        if ( ! in_array( $event_type, self::EVENT_TYPES, true ) ) {
            return false;
        }

        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }
        if ( $user_role === '' ) {
            $user_role = current_user_can( 'manage_options' ) ? 'consultant' : 'client';
        }

        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'gapnext_remediation_log',
            [
                'submission_id' => $submission_id,
                'question_ref'  => $question_ref,
                'event_type'    => $event_type,
                'user_id'       => $user_id,
                'user_role'     => $user_role,
                'data'          => wp_json_encode( $data ),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get all events for a submission, ordered chronologically.
     *
     * @param int         $submission_id
     * @param string|null $question_ref  Filter to a specific question (null = all)
     * @return array      Array of event objects with decoded 'data' field
     */
    public static function get_events( int $submission_id, ?string $question_ref = null ): array {
        global $wpdb;

        if ( $question_ref !== null ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gapnext_remediation_log
                 WHERE submission_id = %d AND question_ref = %s
                 ORDER BY created_at ASC, id ASC",
                $submission_id,
                $question_ref
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gapnext_remediation_log
                 WHERE submission_id = %d
                 ORDER BY created_at ASC, id ASC",
                $submission_id
            ) );
        }

        // Decode JSON data field
        foreach ( $rows as $row ) {
            $row->data = json_decode( $row->data, true ) ?: [];
        }

        return $rows ?: [];
    }

    /**
     * Get recent events across all submissions (for admin dashboard).
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent( int $limit = 20 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, s.company_name, s.standard_id
             FROM {$wpdb->prefix}gapnext_remediation_log l
             JOIN {$wpdb->prefix}gapnext_submissions s ON s.id = l.submission_id
             ORDER BY l.created_at DESC
             LIMIT %d",
            $limit
        ) );

        foreach ( $rows as $row ) {
            $row->data = json_decode( $row->data, true ) ?: [];
        }

        return $rows ?: [];
    }

    /**
     * Initialize remediation for a submission — creates 'status_changed' events
     * for all level-2 questions, setting their status to 'open'.
     *
     * @param int   $submission_id
     * @param array $answers  Decoded JSON from submission (ref => {value, note})
     * @param array $clauses  Standard clause data array
     * @return int  Number of questions initialized
     */
    public static function initialize_remediation( int $submission_id, array $answers, array $clauses ): int {
        $count = 0;
        $user_id = get_current_user_id();

        foreach ( $clauses as $clause ) {
            if ( (int) ( $clause['level'] ?? 2 ) === 1 ) continue;

            $ref = $clause['reference'];
            $answer_row = $answers[ $ref ] ?? [];
            $value = is_array( $answer_row ) ? ( $answer_row['value'] ?? null ) : $answer_row;

            self::insert( $submission_id, $ref, 'status_changed', [
                'old_status' => '',
                'new_status' => 'open',
                'original_answer' => $value,
            ], $user_id, 'consultant' );

            $count++;
        }

        return $count;
    }

    /**
     * Check if remediation has been initialized for a submission.
     */
    public static function is_initialized( int $submission_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gapnext_remediation_log
             WHERE submission_id = %d AND event_type = 'status_changed'
             LIMIT 1",
            $submission_id
        ) );
    }
}
