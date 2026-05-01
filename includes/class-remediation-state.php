<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Derives current remediation state by replaying events from GapNext_Remediation_Log.
 *
 * This is a read-only class — it never writes to the database.
 */
class GapNext_Remediation_State {

    /**
     * Get the current state of a single question.
     *
     * Replays all events for this question and returns the derived state.
     *
     * @param int    $submission_id
     * @param string $question_ref
     * @return array
     */
    public static function for_question( int $submission_id, string $question_ref ): array {
        $events = GapNext_Remediation_Log::get_events( $submission_id, $question_ref );
        return self::replay( $events );
    }

    /**
     * Get the current state of ALL questions in a submission.
     *
     * @param int $submission_id
     * @return array<string, array>  Keyed by question_ref
     */
    public static function for_submission( int $submission_id ): array {
        $events = GapNext_Remediation_Log::get_events( $submission_id );

        // Group events by question_ref
        $grouped = [];
        foreach ( $events as $event ) {
            $grouped[ $event->question_ref ][] = $event;
        }

        $states = [];
        foreach ( $grouped as $ref => $ref_events ) {
            $states[ $ref ] = self::replay( $ref_events );
        }

        return $states;
    }

    /**
     * Aggregate stats across all questions in a submission.
     *
     * @param int        $submission_id
     * @param array|null $states  Pre-computed states (optional, avoids double query)
     * @return array
     */
    public static function aggregate( int $submission_id, ?array $states = null ): array {
        if ( $states === null ) {
            $states = self::for_submission( $submission_id );
        }

        $agg = [
            'total'          => count( $states ),
            'open'           => 0,
            'in_progress'    => 0,
            'pending_review' => 0,
            'verified'       => 0,
            'rejected'       => 0,
            'unchanged'      => 0,
            'overdue'        => 0,
        ];

        $compliant_count = 0;
        $applicable_count = 0;
        $today = current_time( 'Y-m-d' );

        foreach ( $states as $state ) {
            $status = $state['status'];
            if ( isset( $agg[ $status ] ) ) {
                $agg[ $status ]++;
            }

            // Count as unchanged if no corrective action and original answer was compliant/na
            $orig = $state['original_answer'];
            if ( $state['corrective_action'] === '' && ( $orig === 1.0 || $orig === 1 || $orig === 'na' ) ) {
                $agg['unchanged']++;
            }

            // Overdue: has a deadline, deadline has passed, not verified
            if ( $state['deadline'] && $state['deadline'] < $today && $status !== 'verified' ) {
                $agg['overdue']++;
            }

            // Current score calculation
            $current = $state['current_answer'] ?? $state['original_answer'];
            if ( $current !== 'na' && $current !== null ) {
                $applicable_count++;
                if ( $current === 1.0 || $current === 1 ) {
                    $compliant_count++;
                }
            }
        }

        $agg['current_score'] = $applicable_count > 0
            ? round( $compliant_count / $applicable_count, 4 )
            : 0.0;

        return $agg;
    }

    /**
     * Replay a set of events (for one question) and derive current state.
     */
    private static function replay( array $events ): array {
        $state = [
            'original_answer'   => null,
            'current_answer'    => null,
            'proposed_answer'   => null,
            'status'            => 'open',
            'corrective_action' => '',
            'priority'          => 'medium',
            'deadline'          => null,
            'responsible'       => '',
            'evidence'          => [],
            'pending_approval'  => false,
            'events'            => $events,
        ];

        foreach ( $events as $event ) {
            $d = $event->data;

            switch ( $event->event_type ) {
                case 'status_changed':
                    $state['status'] = $d['new_status'] ?? $state['status'];
                    if ( isset( $d['original_answer'] ) && $state['original_answer'] === null ) {
                        $state['original_answer'] = $d['original_answer'];
                    }
                    $state['pending_approval'] = ( $state['status'] === 'pending_review' );
                    break;

                case 'action_set':
                case 'action_updated':
                    $state['corrective_action'] = $d['action'] ?? $state['corrective_action'];
                    break;

                case 'priority_set':
                    $state['priority'] = $d['priority'] ?? $state['priority'];
                    break;

                case 'deadline_set':
                    $state['deadline'] = $d['deadline'] ?? $state['deadline'];
                    break;

                case 'responsible_set':
                    $state['responsible'] = $d['responsible'] ?? $state['responsible'];
                    break;

                case 'answer_changed':
                    $state['proposed_answer'] = $d['new_value'] ?? null;
                    $state['pending_approval'] = true;
                    break;

                case 'answer_approved':
                    $state['current_answer'] = $d['approved_value'] ?? $state['proposed_answer'];
                    $state['proposed_answer'] = null;
                    $state['pending_approval'] = false;
                    if ( isset( $d['new_status'] ) ) {
                        $state['status'] = $d['new_status'];
                    }
                    break;

                case 'answer_rejected':
                    $state['proposed_answer'] = null;
                    $state['pending_approval'] = false;
                    if ( isset( $d['new_status'] ) ) {
                        $state['status'] = $d['new_status'];
                    }
                    break;

                case 'evidence_added':
                    if ( isset( $d['file_path'] ) ) {
                        $state['evidence'][] = $d['file_path'];
                    }
                    break;

                case 'verified':
                    $state['status'] = 'verified';
                    $state['pending_approval'] = false;
                    if ( isset( $d['verified_answer'] ) ) {
                        $state['current_answer'] = $d['verified_answer'];
                    }
                    break;

                case 'comment':
                    // Comments don't change state — they're in the event timeline
                    break;
            }
        }

        // If current_answer was never set, it equals original
        if ( $state['current_answer'] === null ) {
            $state['current_answer'] = $state['original_answer'];
        }

        return $state;
    }
}
