<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Export_Csv {

    /**
     * Generate CSV content as a string.
     */
    public static function generate_string( $sub ) {
        $answers  = json_decode( $sub->answers,        true ) ?: [];
        $evidence = json_decode( $sub->evidence_paths, true ) ?: [];
        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        $lang     = $sub->language;

        // Compute stats (same logic as results page)
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
        $score_pct  = round( $sub->score * 100, 1 );

        ob_start();
        $out = fopen( 'php://output', 'w' );
        fputs( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel

        fputcsv( $out, [ 'Standard',         $sub->standard_id ] );
        fputcsv( $out, [ 'Language',         strtoupper( $lang ) ] );
        fputcsv( $out, [ 'Score',            $score_pct . '%' ] );
        fputcsv( $out, [ 'Date',             $sub->submitted_at ] );
        fputcsv( $out, [] );

        // Stats summary (mirrors results page)
        fputcsv( $out, [ $lang === 'it' ? 'Riepilogo' : 'Summary', '' ] );
        fputcsv( $out, [ $lang === 'it' ? 'Domande totali'  : 'Total Questions',  $total ] );
        fputcsv( $out, [ $lang === 'it' ? 'Risposte'        : 'Answered',         $answered ] );
        fputcsv( $out, [ $lang === 'it' ? 'Conforme'        : 'Compliant',        $compliant ] );
        fputcsv( $out, [ $lang === 'it' ? 'Parzialmente'    : 'Partial',          $partial ] );
        fputcsv( $out, [ $lang === 'it' ? 'Non-Conforme'    : 'Non-Compliant',    $non_comply ] );
        fputcsv( $out, [ $lang === 'it' ? 'Non Applicabile' : 'Not Applicable',   $na ] );
        fputcsv( $out, [ $lang === 'it' ? 'Non risposto'    : 'Unanswered',       $unanswered ] );
        fputcsv( $out, [ $lang === 'it' ? 'Punteggio'       : 'Overall Score',    $score_pct . '%' ] );
        fputcsv( $out, [] );

        fputcsv( $out, [ 'Company',          $sub->company_name ] );
        fputcsv( $out, [ 'Address',          $sub->company_address ] );
        fputcsv( $out, [ 'VAT',              $sub->company_vat ] );
        fputcsv( $out, [ 'Sector',           $sub->company_sector ] );
        fputcsv( $out, [] );
        fputcsv( $out, [ 'Contact Name',     $sub->contact_name ] );
        fputcsv( $out, [ 'Contact Role',     $sub->contact_role ] );
        fputcsv( $out, [ 'Contact Email',    $sub->contact_email ] );
        fputcsv( $out, [ 'Contact Phone',    $sub->contact_phone ] );
        fputcsv( $out, [] );
        fputcsv( $out, [ 'Consultant Name',  $sub->consultant_name ] );
        fputcsv( $out, [ 'Consultant Co.',   $sub->consultant_company ] );
        fputcsv( $out, [ 'Consultant Email', $sub->consultant_email ] );
        fputcsv( $out, [ 'Consultant Phone', $sub->consultant_phone ] );
        fputcsv( $out, [] );
        fputcsv( $out, [ 'Reference', 'Clause Ref', 'Question', 'Answer', 'Notes', 'Evidence Files' ] );

        if ( $standard ) {
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] !== 2 ) continue;

                $ref  = $clause['reference'];
                $ans  = $answers[ $ref ] ?? null;
                $val  = $ans['value'] ?? '';
                $note = $ans['note'] ?? '';

                if ( $val === 1.0 || $val === 1 ) {
                    $label = $sub->language === 'it' ? 'Conforme'     : 'Compliant';
                } elseif ( $val === 0.5 ) {
                    $label = $sub->language === 'it' ? 'Parzialmente' : 'Partial';
                } elseif ( $val === 0.0 || $val === 0 ) {
                    $label = $sub->language === 'it' ? 'Non-Conforme' : 'Non-Compliant';
                } elseif ( $val === 'na' ) {
                    $label = $sub->language === 'it' ? 'Non Applicabile' : 'Not Applicable';
                } else {
                    $label = '';
                }

                $files = $evidence[ $ref ] ?? [];
                fputcsv( $out, [
                    $ref,
                    $clause['clause_ref'],
                    $clause['title'],
                    $label,
                    $note,
                    implode( ', ', array_map( 'basename', $files ) ),
                ] );
            }
        }

        fclose( $out );
        return ob_get_clean();
    }

    /**
     * Save CSV to a file on disk.
     */
    public static function save( $sub, $path ) {
        file_put_contents( $path, self::generate_string( $sub ) );
    }

    /**
     * Stream CSV as a download. Called from admin-post.php handler.
     */
    public static function export( $submission_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'gapnext-wp' ) );
        }

        $sub = GapNext_Audit_Manager::get_submission( (int) $submission_id );
        if ( ! $sub ) {
            wp_die( esc_html__( 'Submission not found', 'gapnext-wp' ) );
        }

        // Serve pre-saved file if available
        $saved = self::saved_path( $sub );
        if ( $saved && file_exists( $saved ) ) {
            $content = file_get_contents( $saved );
        } else {
            $content = self::generate_string( $sub );
        }

        $filename = sanitize_file_name( 'gapnext-' . $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) . '.csv' );
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $content ) );
        header( 'Pragma: no-cache' );
        echo $content;
        exit;
    }

    /**
     * Return expected saved file path for this submission, or '' if not found.
     */
    public static function saved_path( $sub ) {
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/gapnext-evidence/' . $sub->audit_uuid . '/exports';
        $base = sanitize_file_name( $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) );
        return $dir . '/' . $base . '.csv';
    }
}
