<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Assembles the FastAPI payload from a submission and triggers AI report generation.
 *
 * Answer mapping (score → FastAPI status):
 *   'na' or null/missing → "not_applicable"
 *   >= 0.8              → "compliant"
 *   >= 0.4 and < 0.8    → "partial"
 *   < 0.4               → "non_compliant"
 */
class GapNext_AI_Report {

    /**
     * Generate an AI report for the given submission.
     *
     * On success, stores ai_report_url + ai_report_uuid on the submission row.
     *
     * @param  int $submission_id
     * @return array{uuid: string, download_url: string, file_size_kb: int}|WP_Error
     */
    public function generate_for_submission( int $submission_id, string $comments = '' ): array|WP_Error {
        global $wpdb;

        // 1. Load submission
        $sub = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gapnext_submissions WHERE id = %d",
            $submission_id
        ) );
        if ( ! $sub ) {
            return new WP_Error( 'gapnext_ai_not_found', __( 'Submission not found.', 'gapnext-wp' ) );
        }

        // 2. Load standard clause data
        $standard_data = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        if ( empty( $standard_data ) || empty( $standard_data['clauses'] ) ) {
            return new WP_Error(
                'gapnext_ai_no_standard',
                sprintf(
                    /* translators: %s: standard ID */
                    __( 'Standard data not found for %s.', 'gapnext-wp' ),
                    $sub->standard_id
                )
            );
        }

        // 3. Map answers to checklist
        $answers   = json_decode( $sub->answers, true ) ?? [];
        $checklist = $this->map_answers_to_checklist( $answers, $standard_data['clauses'] );

        if ( empty( $checklist ) ) {
            return new WP_Error( 'gapnext_ai_empty_checklist', __( 'No checklist items found in submission.', 'gapnext-wp' ) );
        }

        // 4. Build payload
        $payload = [
            'meta'      => [
                'standard'     => $sub->standard_id,
                'language'     => $sub->language,
                'submitted_at' => $sub->submitted_at,
                'score'        => (float) $sub->score,
                'corrections'  => $comments,
            ],
            'company'   => [
                'name'    => $sub->company_name,
                'address' => $sub->company_address,
                'vat'     => $sub->company_vat,
                'sector'  => $sub->company_sector,
            ],
            'contact'   => [
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
            'branding'  => $this->build_branding( $sub ),
        ];

        // 5. Call FastAPI
        $client = new GapNext_AI_Client();
        $result = $client->generate_report( $payload );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // 6. Persist result on submission row
        $wpdb->update(
            $wpdb->prefix . 'gapnext_submissions',
            [
                'ai_report_url'  => $result['download_url'] ?? '',
                'ai_report_uuid' => $result['uuid']         ?? '',
            ],
            [ 'id' => $submission_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        // Log this generation to history
        $wpdb->insert(
            $wpdb->prefix . 'gapnext_ai_report_generations',
            [
                'submission_id' => $submission_id,
                'uuid'          => $result['uuid']         ?? '',
                'download_url'  => $result['download_url'] ?? '',
                'comments'      => $comments,
                'generated_at'  => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        return $result;
    }

    /**
     * Map submission answers to the FastAPI WpChecklistItem array.
     *
     * $answers is keyed by question reference (e.g. "4_1"),
     * $clauses is the loaded standard clause array from get_standard_data()['clauses'].
     *
     * @param  array $answers  Decoded JSON from submissions.answers
     * @param  array $clauses  Standard clause data from GapNext_Standard_Registry
     * @return array           Array of WpChecklistItem-shaped arrays
     */
    private function map_answers_to_checklist( array $answers, array $clauses ): array {
        $checklist = [];

        foreach ( $clauses as $clause ) {
            $ref        = $clause['reference'] ?? '';
            $answer_row = $answers[ $ref ] ?? null;

            // Answers are stored as {"value": float|"na", "note": "..."} sub-objects
            $raw_value = is_array( $answer_row ) ? ( $answer_row['value'] ?? null ) : $answer_row;
            $note      = is_array( $answer_row ) ? ( $answer_row['note']  ?? '' )   : '';

            // Map WP score → WpChecklistItem.answer string (consumed by FastAPI wp_adapter.py)
            // wp_adapter maps: compliant→Conformity, partial→Observation,
            //                  non_compliant→Non-Conformity, not_applicable→Not Applicable
            if ( $raw_value === null || $raw_value === '' || $raw_value === 'na' ) {
                $wp_answer = 'not_applicable';
            } elseif ( (float) $raw_value >= 0.8 ) {
                $wp_answer = 'compliant';
            } elseif ( (float) $raw_value >= 0.4 ) {
                $wp_answer = 'partial';
            } else {
                $wp_answer = 'non_compliant';
            }

            $checklist[] = [
                'reference'  => $ref,
                'clause_ref' => $clause['clause_ref'] ?? $ref,
                'title'      => $clause['title'] ?? '',
                'answer'     => $wp_answer,
                'value'      => is_numeric( $raw_value ) ? (float) $raw_value : null,
                'notes'      => $note,
            ];
        }

        return $checklist;
    }

    /**
     * Build the branding context array from wp_options + submission data.
     *
     * company_address comes from the submission row (per-client).
     * company_website + company_phone come from wp_options (consulting firm, constant).
     *
     * array_filter() removes empty-string options — intentional.
     * It does NOT remove valid hex colors like "#000000" (truthy strings).
     *
     * @param  object $submission  Submission row from DB
     * @return array               Branding context (empty keys omitted)
     */
    private function build_branding( object $submission ): array {
        $logo_id  = (int) get_option( 'gapnext_consultant_logo_id', 0 );
        $logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : null;

        return array_filter( [
            'logo_url'        => $logo_url,
            'primary_color'   => get_option( 'gapnext_ai_branding_primary_color',   '' ),
            'secondary_color' => get_option( 'gapnext_ai_branding_secondary_color', '' ),
            'header_title'    => get_option( 'gapnext_ai_branding_header_title',    '' ),
            'footer_text'     => get_option( 'gapnext_ai_branding_footer_text',     '' )
                                 ?: get_option( 'gapnext_pdf_footer_text', '' ),
            'prepared_by'     => get_option( 'gapnext_ai_branding_prepared_by',     '' ),
            'company_address' => $submission->company_address,
            'company_website' => get_option( 'gapnext_ai_branding_company_website', '' ),
            'company_phone'   => get_option( 'gapnext_ai_branding_company_phone',   '' ),
        ] );
    }
}
