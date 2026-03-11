<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Export_Md {

    /**
     * Generate Markdown report as a string.
     */
    public static function generate_string( $sub ) {
        $answers  = json_decode( $sub->answers,        true ) ?: [];
        $evidence = json_decode( $sub->evidence_paths, true ) ?: [];
        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        $lang     = $sub->language;

        $score_pct = round( $sub->score * 100, 1 );
        $std_name  = $standard ? $standard['name'] : $sub->standard_id;
        $date_fmt  = date_i18n( 'd/m/Y', strtotime( $sub->submitted_at ) );

        // Compute stats for executive summary
        $total = $compliant = $partial = $non_comply = $na = 0;
        if ( $standard ) {
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] !== 2 ) continue;
                $total++;
                $val = ( $answers[ $clause['reference'] ] ?? [] )['value'] ?? null;
                if ( $val === 'na' )                     $na++;
                elseif ( $val === 1.0 || $val === 1 )   $compliant++;
                elseif ( $val === 0.5 )                  $partial++;
                elseif ( $val === 0.0 || $val === 0 )   $non_comply++;
            }
        }
        $answered   = $compliant + $partial + $non_comply + $na;
        $unanswered = $total - $answered;
        $applicable = $total - $na;
        $cons_extra = $sub->consultant_company ? ' (' . $sub->consultant_company . ')' : '';

        $md  = '# Gap Analysis Report — ' . $std_name . "\n\n";
        $md .= '**' . ( $lang === 'it' ? 'Punteggio' : 'Score' ) . ':** ' . $score_pct . '%';
        $md .= ' | **' . ( $lang === 'it' ? 'Data' : 'Date' ) . ':** ' . $date_fmt;
        $md .= ' | **' . ( $lang === 'it' ? 'Lingua' : 'Language' ) . ':** ' . strtoupper( $lang ) . "\n\n";

        // Stats table (mirrors results page)
        if ( $lang === 'it' ) {
            $md .= "| Domande totali | Risposte | Conforme | Parzialmente | Non-Conforme | Non Applicabile | Non risposto |\n";
        } else {
            $md .= "| Total Questions | Answered | Compliant | Partial | Non-Compliant | Not Applicable | Unanswered |\n";
        }
        $md .= "|---|---|---|---|---|---|---|\n";
        $md .= "| $total | $answered | $compliant | $partial | $non_comply | $na | $unanswered |\n\n";

        $md .= "---\n\n";

        // Executive Summary
        $md .= '## ' . ( $lang === 'it' ? 'Sommario Esecutivo' : 'Executive Summary' ) . "\n\n";
        if ( $lang === 'it' ) {
            $level = $sub->score >= 0.7 ? 'elevato' : ( $sub->score >= 0.4 ? 'parziale' : 'insufficiente' );
            $md .= sprintf(
                'In data %s, %s ha richiesto una Gap Analysis rispetto allo standard %s, condotta dal consulente %s%s. ' .
                'L\'analisi ha esaminato %d requisiti, di cui %d applicabili (%d Non Applicabili). ' .
                'Il punteggio complessivo indica un livello di conformità **%s** pari al **%s%%**: ' .
                '%d Conformi, %d Parziali, %d Non Conformi.',
                $date_fmt, $sub->company_name, $std_name, $sub->consultant_name, $cons_extra,
                $total, $applicable, $na, $level, $score_pct,
                $compliant, $partial, $non_comply
            );
        } else {
            $level = $sub->score >= 0.7 ? 'high' : ( $sub->score >= 0.4 ? 'partial' : 'insufficient' );
            $md .= sprintf(
                'On %s, %s requested a Gap Analysis against the %s standard, conducted by consultant %s%s. ' .
                'The analysis examined %d requirements, of which %d are applicable (%d Not Applicable). ' .
                'The overall score indicates a **%s** level of compliance at **%s%%**: ' .
                '%d Compliant, %d Partial, %d Non-Compliant.',
                $date_fmt, $sub->company_name, $std_name, $sub->consultant_name, $cons_extra,
                $total, $applicable, $na, $level, $score_pct,
                $compliant, $partial, $non_comply
            );
        }
        $md .= "\n\n";

        // Methodology
        $md .= '## ' . ( $lang === 'it' ? 'Metodologia' : 'Methodology' ) . "\n\n";
        if ( $lang === 'it' ) {
            $md .= "- La Gap Analysis è stata condotta sulla base dello standard **$std_name** tramite checklist strutturata per sezioni.\n";
            $md .= "- Per ogni requisito è stata assegnata una valutazione: Conforme (1.0), Parzialmente Conforme (0.5), Non Conforme (0) o Non Applicabile.\n";
            $md .= "- Il punteggio è calcolato come media delle risposte applicabili, escludendo le voci Non Applicabili dal denominatore.\n";
        } else {
            $md .= "- The Gap Analysis was conducted based on the **$std_name** standard through a structured, section-by-section checklist.\n";
            $md .= "- Each requirement was rated: Compliant (1.0), Partially Compliant (0.5), Non-Compliant (0), or Not Applicable.\n";
            $md .= "- The score is calculated as the average of applicable responses, excluding Not Applicable items from the denominator.\n";
        }
        $md .= "\n---\n\n";

        // Company
        $md .= '## ' . ( $lang === 'it' ? 'Azienda' : 'Company' ) . "\n\n";
        $md .= '| ' . ( $lang === 'it' ? 'Campo' : 'Field' ) . ' | ' . ( $lang === 'it' ? 'Valore' : 'Value' ) . " |\n";
        $md .= "|---|---|\n";
        $md .= '| ' . ( $lang === 'it' ? 'Ragione Sociale' : 'Company Name' ) . ' | ' . self::md_escape( $sub->company_name ) . " |\n";
        $md .= '| ' . ( $lang === 'it' ? 'Indirizzo' : 'Address' ) . ' | ' . self::md_escape( $sub->company_address ) . " |\n";
        $md .= '| P.IVA / VAT | ' . self::md_escape( $sub->company_vat ) . " |\n";
        $md .= '| ' . ( $lang === 'it' ? 'Settore' : 'Sector' ) . ' | ' . self::md_escape( $sub->company_sector ) . " |\n\n";

        // Internal contact
        $md .= '## ' . ( $lang === 'it' ? 'Referente Interno' : 'Internal Contact' ) . "\n\n";
        $md .= '| ' . ( $lang === 'it' ? 'Campo' : 'Field' ) . ' | ' . ( $lang === 'it' ? 'Valore' : 'Value' ) . " |\n";
        $md .= "|---|---|\n";
        $md .= '| ' . ( $lang === 'it' ? 'Nome' : 'Name' ) . ' | ' . self::md_escape( $sub->contact_name ) . " |\n";
        $md .= '| ' . ( $lang === 'it' ? 'Ruolo' : 'Role' ) . ' | ' . self::md_escape( $sub->contact_role ) . " |\n";
        $md .= '| Email | ' . self::md_escape( $sub->contact_email ) . " |\n";
        $md .= '| ' . ( $lang === 'it' ? 'Telefono' : 'Phone' ) . ' | ' . self::md_escape( $sub->contact_phone ) . " |\n\n";

        // Consultant
        $md .= '## ' . ( $lang === 'it' ? 'Consulente' : 'Consultant' ) . "\n\n";
        $md .= '| ' . ( $lang === 'it' ? 'Campo' : 'Field' ) . ' | ' . ( $lang === 'it' ? 'Valore' : 'Value' ) . " |\n";
        $md .= "|---|---|\n";
        $md .= '| ' . ( $lang === 'it' ? 'Nome' : 'Name' ) . ' | ' . self::md_escape( $sub->consultant_name ) . " |\n";
        $md .= '| ' . ( $lang === 'it' ? 'Azienda' : 'Company' ) . ' | ' . self::md_escape( $sub->consultant_company ) . " |\n";
        $md .= '| Email | ' . self::md_escape( $sub->consultant_email ) . " |\n\n";

        $md .= "---\n\n";
        $md .= '## ' . ( $lang === 'it' ? 'Risultati Checklist' : 'Checklist Results' ) . "\n\n";

        if ( $standard ) {
            $current_section = '';
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] === 1 ) {
                    $md .= '### ' . $clause['clause_ref'] . ' — ' . $clause['title'] . "\n\n";
                    $md .= '| ' . ( $lang === 'it' ? 'Rif.' : 'Ref.' );
                    $md .= ' | ' . ( $lang === 'it' ? 'Requisito' : 'Requirement' );
                    $md .= ' | ' . ( $lang === 'it' ? 'Risposta' : 'Answer' );
                    $md .= ' | ' . ( $lang === 'it' ? 'Note' : 'Notes' ) . " |\n";
                    $md .= "|---|---|---|---|\n";
                    continue;
                }

                $ref  = $clause['reference'];
                $ans  = $answers[ $ref ] ?? null;
                $val  = $ans['value'] ?? null;
                $note = $ans['note'] ?? '';

                if ( $val === 1.0 || $val === 1 ) {
                    $label = ( $lang === 'it' ? '✅ Conforme'     : '✅ Compliant' );
                } elseif ( $val === 0.5 ) {
                    $label = ( $lang === 'it' ? '🟡 Parzialmente' : '🟡 Partial' );
                } elseif ( $val === 0.0 || $val === 0 ) {
                    $label = ( $lang === 'it' ? '❌ Non-Conforme' : '❌ Non-Compliant' );
                } elseif ( $val === 'na' ) {
                    $label = ( $lang === 'it' ? '— Non Appl.'    : '— N/A' );
                } else {
                    $label = '—';
                }

                $files = $evidence[ $ref ] ?? [];
                $files_str = implode( ', ', array_map( 'basename', $files ) );
                $note_cell = $note . ( $files_str ? ' [' . $files_str . ']' : '' );

                $md .= '| ' . self::md_escape( $clause['clause_ref'] );
                $md .= ' | ' . self::md_escape( $clause['title'] );
                $md .= ' | ' . $label;
                $md .= ' | ' . self::md_escape( $note_cell ) . " |\n";
            }
        }

        return $md;
    }

    /**
     * Save Markdown to a file on disk.
     */
    public static function save( $sub, $path ) {
        file_put_contents( $path, self::generate_string( $sub ) );
    }

    /**
     * Stream Markdown as a download. Called from admin-post.php handler.
     */
    public static function export( $submission_id ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'gapnext-wp' ) );
        }

        $sub = GapNext_Audit_Manager::get_submission( (int) $submission_id );
        if ( ! $sub ) {
            wp_die( esc_html__( 'Submission not found', 'gapnext-wp' ) );
        }

        $saved = self::saved_path( $sub );
        if ( $saved && file_exists( $saved ) ) {
            $content = file_get_contents( $saved );
        } else {
            $content = self::generate_string( $sub );
        }

        $filename = sanitize_file_name( 'gapnext-' . $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) . '.md' );
        header( 'Content-Type: text/markdown; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $content ) );
        header( 'Pragma: no-cache' );
        echo $content;
        exit;
    }

    /**
     * Return expected saved file path for this submission.
     */
    public static function saved_path( $sub ) {
        $upload_dir = wp_upload_dir();
        $dir = $upload_dir['basedir'] . '/gapnext-evidence/' . $sub->audit_uuid . '/exports';
        $base = sanitize_file_name( $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) );
        return $dir . '/' . $base . '.md';
    }

    /**
     * Escape a string for safe inclusion in a Markdown table cell.
     */
    private static function md_escape( $str ) {
        return str_replace( [ '|', "\n", "\r" ], [ '\\|', ' ', '' ], (string) $str );
    }
}
