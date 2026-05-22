<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Export_Json {

    /**
     * Generate JSON content as a string.
     */
    public static function generate_string( $sub ) {
        $answers  = json_decode( $sub->answers,        true ) ?: [];
        $evidence = json_decode( $sub->evidence_paths, true ) ?: [];
        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );

        if ( $sub->status === 'demo' && $standard ) {
            $demo_limit = (int) get_option( 'gapnext_demo_question_limit', 15 );
            $sliced = [];
            $q_count = 0;
            $last_l1 = null;
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) ( $clause['level'] ?? 2 ) === 1 ) { $last_l1 = $clause; continue; }
                if ( $q_count >= $demo_limit ) break;
                if ( $last_l1 ) { $sliced[] = $last_l1; $last_l1 = null; }
                $sliced[] = $clause;
                $q_count++;
            }
            $standard['clauses'] = $sliced;
        }

        // Compute stats
        $total = $compliant = $partial = $non_comply = $na = 0;
        if ( $standard ) {
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] !== 2 ) continue;
                $total++;
                $val = ( $answers[ $clause['reference'] ] ?? [] )['value'] ?? null;
                if ( $val === 'na' )                   $na++;
                elseif ( $val === 1.0 || $val === 1 )  $compliant++;
                elseif ( $val === 0.5 )                $partial++;
                elseif ( $val === 0.0 || $val === 0 )  $non_comply++;
            }
        }
        $answered   = $compliant + $partial + $non_comply + $na;
        $unanswered = $total - $answered;

        // Build checklist rows
        $checklist = [];
        if ( $standard ) {
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] !== 2 ) continue;
                $ref  = $clause['reference'];
                $ans  = $answers[ $ref ] ?? null;
                $val  = $ans['value'] ?? null;
                $files = $evidence[ $ref ] ?? [];

                if ( $val === 1.0 || $val === 1 )    $label = 'compliant';
                elseif ( $val === 0.5 )              $label = 'partial';
                elseif ( $val === 0.0 || $val === 0 ) $label = 'non_compliant';
                elseif ( $val === 'na' )             $label = 'not_applicable';
                else                                 $label = 'unanswered';

                $checklist[] = [
                    'reference'  => $ref,
                    'clause_ref' => $clause['clause_ref'],
                    'title'      => $clause['title'],
                    'answer'     => $label,
                    'value'      => $val,
                    'notes'      => $ans['note'] ?? '',
                    'evidence'   => array_map( 'basename', (array) $files ),
                ];
            }
        }

        $data = [
            'meta' => [
                'standard'    => $sub->standard_id,
                'language'    => strtoupper( $sub->language ),
                'submitted_at' => $sub->submitted_at,
                'score'       => round( $sub->score * 100, 1 ),
            ],
            'stats' => [
                'total'         => $total,
                'answered'      => $answered,
                'unanswered'    => $unanswered,
                'compliant'     => $compliant,
                'partial'       => $partial,
                'non_compliant' => $non_comply,
                'not_applicable' => $na,
                'overall_score' => round( $sub->score * 100, 1 ) . '%',
            ],
            'company' => [
                'name'    => $sub->company_name,
                'address' => $sub->company_address,
                'vat'     => $sub->company_vat,
                'sector'  => $sub->company_sector,
            ],
            'contact' => [
                'name'  => $sub->contact_name,
                'role'  => $sub->contact_role,
                'email' => $sub->contact_email,
                'phone' => $sub->contact_phone,
            ],
            'consultant' => [
                'name'    => $sub->consultant_name,
                'company' => $sub->consultant_company,
                'email'   => $sub->consultant_email,
                'phone'   => $sub->consultant_phone,
            ],
            'checklist' => $checklist,
        ];

        return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }

    /**
     * Stream JSON as a download. Called from admin-post.php handler.
     */
    public static function export( $submission_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'gapnext-wp' ) );
        }

        $sub = GapNext_Audit_Manager::get_submission( (int) $submission_id );
        if ( ! $sub ) {
            wp_die( esc_html__( 'Submission not found.', 'gapnext-wp' ) );
        }

        $content  = self::generate_string( $sub );
        $filename = sanitize_file_name( 'gapnext-' . $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) . '.json' );
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $content ) );
        header( 'Pragma: no-cache' );
        echo $content;
        exit;
    }
}
