<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Export_Pdf {

    /**
     * Return expected saved file path for this submission.
     */
    public static function saved_path( $sub ) {
        $upload_dir = wp_upload_dir();
        $dir  = $upload_dir['basedir'] . '/gapnext-evidence/' . $sub->audit_uuid . '/exports';
        $base = sanitize_file_name( $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) );
        return $dir . '/' . $base . '.pdf';
    }

    /**
     * Save PDF to a file on disk.
     */
    public static function save( $sub ) {
        $path = self::saved_path( $sub );
        wp_mkdir_p( dirname( $path ) );
        try {
            $pdf = self::build( $sub );
            $pdf->Output( $path, 'F' );
        } catch ( \Throwable $e ) {
            // Silently fail — will be generated on-demand
        }
    }

    /**
     * Stream PDF as a download to the browser (no auth check — caller must verify).
     */
    public static function stream( $sub ) {
        @ini_set( 'memory_limit', '512M' );
        @set_time_limit( 300 );

        // Catch uncaught PHP fatals (E_ERROR) via shutdown handler.
        // These cannot be caught by try/catch but ARE available in shutdown functions.
        register_shutdown_function( function() {
            $err = error_get_last();
            if ( $err && in_array( $err['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
                while ( ob_get_level() ) ob_end_clean();
                http_response_code( 500 );
                header( 'Content-Type: text/plain; charset=utf-8' );
                echo 'GapNext PDF fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
                exit;
            }
        } );

        // Clear any buffered output BEFORE building so no partial content corrupts headers
        while ( ob_get_level() ) ob_end_clean();

        try {
            $pdf = self::build( $sub );
        } catch ( \Throwable $e ) {
            // Output plain text — NOT wp_die() which loads WordPress admin HTML/JS
            // and causes unrelated core.js errors in the browser.
            while ( ob_get_level() ) ob_end_clean();
            http_response_code( 500 );
            header( 'Content-Type: text/plain; charset=utf-8' );
            echo 'GapNext PDF error: ' . $e->getMessage();
            exit;
        }

        $filename = sanitize_file_name( 'gapnext-report-' . $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) . '.pdf' );
        $pdf->Output( $filename, 'D' );
        exit;
    }

    /**
     * Stream PDF as a download. Called from admin-post.php handler.
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
        if ( file_exists( $saved ) ) {
            $filename = sanitize_file_name( 'gapnext-report-' . $sub->standard_id . '-' . date( 'Ymd', strtotime( $sub->submitted_at ) ) . '.pdf' );
            header( 'Content-Type: application/pdf' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . filesize( $saved ) );
            header( 'Pragma: no-cache' );
            while ( ob_get_level() ) ob_end_clean();
            readfile( $saved );
            exit;
        }

        self::stream( $sub );
    }

    // =========================================================
    // MAIN BUILD — uses writeHTML() for low-memory rendering
    // =========================================================

    private static function build( $sub ) {
        $answers  = json_decode( $sub->answers,        true ) ?: [];
        $evidence = json_decode( $sub->evidence_paths, true ) ?: [];
        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        $lang     = $sub->language;

        if ( ! defined( 'K_PATH_CACHE' ) ) {
            define( 'K_PATH_CACHE', rtrim( sys_get_temp_dir(), '/\\' ) . '/' );
        }

        require_once GAPNEXT_WP_DIR . 'vendor/tcpdf/tcpdf.php';
        require_once GAPNEXT_WP_DIR . 'includes/class-gapnext-tcpdf.php';

        // Resolve logo
        $logo_id   = (int) get_option( 'gapnext_consultant_logo_id', 0 );
        $logo_path = $logo_id ? get_attached_file( $logo_id ) : '';
        if ( ! $logo_path || ! file_exists( $logo_path ) ) {
            $icon_url  = get_site_icon_url( 256 );
            $logo_path = $icon_url ? self::url_to_path( $icon_url ) : '';
        }
        if ( ! file_exists( (string) $logo_path ) ) {
            $logo_path = '';
        }

        $std_name         = $standard ? $standard['name'] : $sub->standard_id;
        $report_date      = date_i18n( 'd/m/Y', strtotime( $sub->submitted_at ) );
        $footer_text      = get_option( 'gapnext_pdf_footer_text', 'Proprietary and Confidential' );
        $consultant_label = trim( $sub->consultant_name . ( $sub->consultant_company ? ' — ' . $sub->consultant_company : '' ) );
        $stats            = self::calc_stats( $answers, $standard );
        $score_pct        = round( $sub->score * 100, 1 );

        // Instantiate TCPDF
        $pdf = new GapNext_TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', true );
        $pdf->logo_path        = (string) $logo_path;
        $pdf->standard_name    = $std_name;
        $pdf->company_name     = $sub->company_name;
        $pdf->report_date      = $report_date;
        $pdf->consultant_label = $consultant_label;
        $pdf->footer_text      = (string) $footer_text;
        $pdf->is_demo = $sub->status === 'demo';
        $pdf->SetCreator( 'GapNext WP' );
        $pdf->SetAuthor( $sub->consultant_name );
        $pdf->SetTitle( 'Gap Analysis Report — ' . $std_name );

        // =====================================================
        // COVER PAGE (no header/footer)
        // =====================================================
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );
        $pdf->SetMargins( 20, 15, 20 );
        $pdf->SetAutoPageBreak( false );
        $pdf->AddPage();

        // Top accent bar (lightweight single rect — fine in manual mode)
        $pdf->SetFillColor( 30, 64, 175 );
        $pdf->Rect( 0, 0, 210, 8, 'F' );

        // Bottom accent bar
        $pdf->SetFillColor( 30, 64, 175 );
        $pdf->Rect( 0, 287, 210, 10, 'F' );

        // Cover content via writeHTML
        $pdf->writeHTML(
            self::build_cover_html( $sub, $std_name, $score_pct, $report_date, $lang, $consultant_label, $logo_path, $sub->status === 'demo' ),
            true, false, true, false, ''
        );

        // =====================================================
        // CONTENT PAGES — Executive Summary + Methodology
        // =====================================================
        $pdf->setPrintHeader( true );
        // Footer stays false until AFTER AddPage() closes the cover page
        $pdf->SetMargins( 15, 22, 15 );
        $pdf->SetAutoPageBreak( true, 16 );
        $pdf->AddPage();
        $pdf->setPrintFooter( true );
        $pdf->writeHTML(
            self::build_summary_html( $sub, $stats, $lang, $std_name ),
            true, false, true, false, ''
        );

        // =====================================================
        // Results Overview
        // =====================================================
        $pdf->AddPage();
        $pdf->writeHTML(
            self::build_overview_html( $stats, $lang, $standard, $score_pct, $sub->score ),
            true, false, true, false, ''
        );

        // =====================================================
        // Section Breakdown (dedicated page)
        // =====================================================
        if ( ! empty( $stats['sections'] ) ) {
            $pdf->AddPage();
            $pdf->writeHTML(
                self::build_section_breakdown_html( $stats, $lang ),
                true, false, true, false, ''
            );
        }

        // =====================================================
        // Detailed Checklist
        // =====================================================
        if ( $standard ) {
            $pdf->AddPage();
            $pdf->writeHTML(
                self::build_checklist_html( $standard, $answers, $evidence, $lang, $sub->status === 'demo' ),
                true, false, true, false, ''
            );
        }

        return $pdf;
    }

    // =========================================================
    // HTML BUILDERS
    // =========================================================
    // TCPDF HTML rules:
    //   - Content area = 180mm (A4 210mm - 15mm margins each side)
    //   - All <td> width values are in mm — total must be < 180
    //   - Use percentages (width="33%") when possible
    //   - NEVER nest tables — TCPDF renders them unreliably
    //   - Use border="1" on <table> for uniform grid borders
    //   - Use cellpadding on <table>, NOT padding on <td>

    private static function h( $str ) {
        return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
    }

    private static function section_h2( $title ) {
        return '<br /><p style="font-size:12pt; font-weight:bold; color:#1e40af;">'
            . self::h( $title ) . '</p>'
            . '<table cellspacing="0" cellpadding="0" width="100%"><tr>'
            . '<td style="border-bottom:0.5px solid #cbd5e1;">&nbsp;</td>'
            . '</tr></table><br />';
    }

    private static function section_h3( $title ) {
        return '<br /><p style="font-size:10pt; font-weight:bold; color:#1e293b;">'
            . self::h( $title ) . '</p><br />';
    }

    private static function build_cover_html( $sub, $std_name, $score_pct, $report_date, $lang, $consultant_label, $logo_path, $is_demo = false ) {
        $date_label  = $lang === 'it' ? 'Data'       : 'Date';
        $prep_label  = $lang === 'it' ? 'Preparato da:' : 'Prepared by:';

        $h = '<br /><br />';

        if ( $logo_path && file_exists( $logo_path ) ) {
            $h .= '<p style="text-align:center;"><img src="' . self::h( $logo_path ) . '" width="120" /></p>';
            $h .= '<br /><br />';
        } else {
            $h .= '<br /><br /><br />';
        }

        $h .= '<p style="font-size:26pt; font-weight:bold; color:#1e40af; text-align:center;">Gap Analysis Report</p>';
        if ( $is_demo ) {
            $h .= '<p style="font-size:18pt; font-weight:bold; color:#d97706; text-align:center; letter-spacing:3px;">DEMO</p>';
        }
        $h .= '<p style="font-size:15pt; color:#475569; text-align:center; font-weight:normal;">' . self::h( $std_name ) . '</p>';
        $h .= '<br /><br />';

        $h .= '<p style="font-size:13pt; font-weight:bold; color:#1e293b; text-align:center;">' . self::h( $sub->company_name ) . '</p>';
        if ( $sub->company_sector ) {
            $h .= '<p style="font-size:10pt; color:#64748b; text-align:center;">' . self::h( $sub->company_sector ) . '</p>';
        }
        $h .= '<br />';

        $h .= '<p style="text-align:center; color:#64748b; font-size:10pt;">'
            . self::h( $date_label . ': ' . $report_date ) . '</p>';

        $h .= '<br /><br /><br /><br /><br /><br /><br />';

        $h .= '<p style="text-align:center; font-size:9pt; font-weight:bold; color:#475569;">' . self::h( $prep_label ) . '</p>';
        $h .= '<p style="text-align:center; font-size:9pt; color:#1e293b;">' . self::h( $consultant_label ) . '</p>';
        if ( $sub->consultant_email ) {
            $h .= '<p style="text-align:center; font-size:8pt; color:#64748b;">' . self::h( $sub->consultant_email ) . '</p>';
        }

        return $h;
    }

    private static function build_summary_html( $sub, $stats, $lang, $std_name ) {
        $h = self::section_h2( $lang === 'it' ? 'Sommario Esecutivo' : 'Executive Summary' );
        $h .= '<p style="font-size:10pt; color:#1e293b; text-align:justify;">'
            . nl2br( self::h( self::build_executive_summary( $sub, $stats, $lang, $std_name ) ) )
            . '</p><br />';

        $h .= self::section_h2( $lang === 'it' ? 'Metodologia' : 'Methodology' );

        $bullets = $lang === 'it' ? [
            'La Gap Analysis è stata condotta sulla base dello standard ' . $std_name . ' attraverso una checklist strutturata per sezioni.',
            'Per ogni requisito, al referente interno è stata assegnata una valutazione: Conforme, Parzialmente Conforme, Non Conforme o Non Applicabile.',
            'Il punteggio finale è calcolato come: numero di requisiti Conformi ÷ (totale requisiti − requisiti Non Applicabili). I requisiti Non Applicabili sono esclusi sia dal numeratore sia dal denominatore.',
            'Il risultato fornisce una misura oggettiva del livello di conformità corrente e identifica le aree prioritarie di intervento.',
        ] : [
            'The Gap Analysis was conducted based on the ' . $std_name . ' standard through a structured, section-by-section checklist.',
            'For each requirement, the internal contact was assigned a rating: Compliant, Partially Compliant, Non-Compliant, or Not Applicable.',
            'The final score is calculated as: number of Compliant requirements ÷ (total requirements − Not Applicable requirements). Not Applicable items are excluded from both numerator and denominator.',
            'The result provides an objective measure of the current compliance level and identifies priority areas for improvement.',
        ];

        $h .= '<ul style="font-size:10pt; color:#1e293b;">';
        foreach ( $bullets as $bullet ) {
            $h .= '<li>' . self::h( $bullet ) . '</li>';
        }
        $h .= '</ul><br />';

        // Scoring scale — flat table, no nesting (widths: 5 + 55 + auto = ~180)
        $h .= self::section_h3( $lang === 'it' ? 'Scala di Valutazione' : 'Scoring Scale' );

        $scale = [
            [ '#16a34a', $lang === 'it' ? 'Conforme (1.0)'     : 'Compliant (1.0)',   $lang === 'it' ? 'Il requisito è pienamente soddisfatto.'                    : 'The requirement is fully met.' ],
            [ '#d97706', $lang === 'it' ? 'Parzialmente (0.5)' : 'Partial (0.5)',     $lang === 'it' ? 'Il requisito è soddisfatto solo in parte.'              : 'The requirement is partially met.' ],
            [ '#dc2626', $lang === 'it' ? 'Non Conforme (0)'   : 'Non-Compliant (0)', $lang === 'it' ? 'Il requisito non è soddisfatto.'                         : 'The requirement is not met.' ],
            [ '#6b7280', $lang === 'it' ? 'Non Applicabile'    : 'Not Applicable',    $lang === 'it' ? 'Il requisito non è applicabile a questa organizzazione.' : 'The requirement does not apply to this organization.' ],
        ];

        $h .= '<table cellspacing="0" cellpadding="4" width="100%">';
        foreach ( $scale as $row ) {
            list( $color, $label, $desc ) = $row;
            $h .= '<tr>'
                . '<td width="2%" style="background-color:' . $color . ';">&nbsp;</td>'
                . '<td width="28%" style="font-size:9pt; font-weight:bold; color:#1e293b; background-color:#f8fafc;">' . self::h( $label ) . '</td>'
                . '<td width="70%" style="font-size:9pt; color:#475569; background-color:#f8fafc;">' . self::h( $desc ) . '</td>'
                . '</tr>';
        }
        $h .= '</table>';

        return $h;
    }

    private static function build_overview_html( $stats, $lang, $standard, $score_pct = 0, $score_raw = 0 ) {
        $h = self::section_h2( $lang === 'it' ? 'Panoramica dei Risultati' : 'Results Overview' );

        // Score panel and stats grid both use identical table settings so they align perfectly.
        // cellspacing=0 + cellpadding=10 on every table; gap between stat cells via 2% spacer cols.
        // Stat col layout: [32%][2%][32%][2%][32%] = 100%
        $score_color = $score_raw >= 0.7 ? '#16a34a' : ( $score_raw >= 0.4 ? '#d97706' : '#dc2626' );
        $score_bg    = $score_raw >= 0.7 ? '#f0fdf4' : ( $score_raw >= 0.4 ? '#fffbeb' : '#fef2f2' );
        $score_label = $lang === 'it' ? 'Punteggio di Conformità' : 'Compliance Score';

        // Score panel — single content cell, border-left accent, same width as stats grid
        $h .= '<table cellspacing="0" cellpadding="10" width="100%">';
        $h .= '<tr>';
        $h .= '<td width="100%" style="background-color:' . $score_bg . '; border-left:4px solid ' . $score_color . ';">'
            . '<span style="font-size:26pt; font-weight:bold; color:' . $score_color . ';">' . self::h( $score_pct . '%' ) . '</span>'
            . '<br /><span style="font-size:7pt; color:#64748b;">' . self::h( $score_label ) . '</span>'
            . '</td>';
        $h .= '</tr>';
        $h .= '</table>';

        // 6px breathing room between score panel and stats grid
        $h .= '<p style="font-size:6pt;">&nbsp;</p>';

        // Stats grid — same cellspacing/cellpadding, spacer cols for visual gaps
        $answered   = $stats['compliant'] + $stats['partial'] + $stats['non_comply'] + $stats['na'];
        $unanswered = $stats['total'] - $answered;

        $rows = [
            [
                [ $lang === 'it' ? 'Domande Totali' : 'Total Questions', $stats['total'],      '#1e293b' ],
                [ $lang === 'it' ? 'Risposte'       : 'Answered',        $answered,            '#1e40af' ],
                [ $lang === 'it' ? 'Non risposto'   : 'Unanswered',      $unanswered,          '#9ca3af' ],
            ],
            [
                [ $lang === 'it' ? 'Conformi'     : 'Compliant',     $stats['compliant'],  '#16a34a' ],
                [ $lang === 'it' ? 'Parziali'     : 'Partial',       $stats['partial'],    '#d97706' ],
                [ $lang === 'it' ? 'Non Conformi' : 'Non-Compliant', $stats['non_comply'], '#dc2626' ],
            ],
        ];

        $h .= '<table cellspacing="0" cellpadding="10" width="100%">';
        foreach ( $rows as $row ) {
            $h .= '<tr>';
            foreach ( $row as $idx => $item ) {
                list( $label, $value, $color ) = $item;
                $h .= '<td width="32%" style="background-color:#f8fafc; border-left:3px solid ' . $color . ';">'
                    . '<span style="font-size:15pt; font-weight:bold; color:' . $color . ';">' . self::h( $value ) . '</span>'
                    . '<br /><span style="font-size:7pt; color:#64748b;">' . self::h( $label ) . '</span>'
                    . '</td>';
                if ( $idx < 2 ) {
                    $h .= '<td width="2%">&nbsp;</td>'; // gap between cells
                }
            }
            $h .= '</tr>';
            // Row spacer
            $h .= '<tr><td colspan="5" style="font-size:4pt;">&nbsp;</td></tr>';
        }
        // Last row: Not Applicable in first slot, rest empty
        $na_label = $lang === 'it' ? 'Non Applicabili' : 'Not Applicable';
        $h .= '<tr>';
        $h .= '<td width="32%" style="background-color:#f8fafc; border-left:3px solid #6b7280;">'
            . '<span style="font-size:15pt; font-weight:bold; color:#6b7280;">' . self::h( $stats['na'] ) . '</span>'
            . '<br /><span style="font-size:7pt; color:#64748b;">' . self::h( $na_label ) . '</span>'
            . '</td>';
        $h .= '<td width="2%">&nbsp;</td>';
        $h .= '<td width="32%">&nbsp;</td>';
        $h .= '<td width="2%">&nbsp;</td>';
        $h .= '<td width="32%">&nbsp;</td>';
        $h .= '</tr>';
        $h .= '</table><br />';

        // Answer distribution — flat table, no nesting
        // Uses colored cell as visual bar indicator
        $h .= self::section_h3( $lang === 'it' ? 'Distribuzione delle Risposte' : 'Answer Distribution' );

        $total_for_chart = max( 1, $stats['applicable'] );
        $bar_items = [
            [ $lang === 'it' ? 'Conforme'        : 'Compliant',     $stats['compliant'],  '#16a34a' ],
            [ $lang === 'it' ? 'Parzialmente'    : 'Partial',       $stats['partial'],    '#d97706' ],
            [ $lang === 'it' ? 'Non Conforme'    : 'Non-Compliant', $stats['non_comply'], '#dc2626' ],
            [ $lang === 'it' ? 'Non Applicabile' : 'Not Applicable',$stats['na'],         '#94a3b8' ],
        ];

        $h .= '<table cellspacing="1" cellpadding="5" width="100%">';
        foreach ( $bar_items as $item ) {
            list( $label, $value, $color ) = $item;
            $pct = round( $value / $total_for_chart * 100 );
            // Bar width as percentage of the bar column (max 60%)
            $bar_pct = max( 1, round( $pct * 0.6 ) );
            $empty_pct = 60 - $bar_pct;

            $h .= '<tr>'
                . '<td width="22%" style="font-size:8pt; color:#1e293b;">' . self::h( $label ) . '</td>'
                . '<td width="8%" style="font-size:9pt; font-weight:bold; color:' . $color . '; text-align:right;">' . self::h( $value ) . '</td>'
                . '<td width="' . $bar_pct . '%" style="background-color:' . $color . '; font-size:3pt;">&nbsp;</td>'
                . '<td width="' . $empty_pct . '%" style="background-color:#f1f5f9; font-size:3pt;">&nbsp;</td>'
                . '<td width="10%" style="font-size:7pt; color:#64748b; text-align:right;">' . self::h( $pct . '%' ) . '</td>'
                . '</tr>';
        }
        $h .= '</table><br />';

        return $h;
    }

    private static function build_section_breakdown_html( $stats, $lang ) {
        $h = self::section_h2( $lang === 'it' ? 'Riepilogo per Sezione' : 'Section Breakdown' );

        if ( empty( $stats['sections'] ) ) {
            return $h;
        }

        $h .= '<table border="1" cellspacing="0" cellpadding="4" width="100%" style="border-color:#e2e8f0; font-size:9pt;">';
        $h .= '<tr style="background-color:#1e40af; font-weight:bold;">'
            . '<th width="8%"  style="text-align:center; color:#ffffff;">' . ( $lang === 'it' ? 'Sez.' : 'Sec.' )   . '</th>'
            . '<th width="44%" style="text-align:left;   color:#ffffff;">' . ( $lang === 'it' ? 'Titolo' : 'Title' ) . '</th>'
            . '<th width="10%" style="text-align:center; color:#ffffff;">' . ( $lang === 'it' ? 'Conf.'  : 'Compl.' ) . '</th>'
            . '<th width="10%" style="text-align:center; color:#ffffff;">' . ( $lang === 'it' ? 'Parz.'  : 'Partial' ) . '</th>'
            . '<th width="10%" style="text-align:center; color:#ffffff;">Non-C.</th>'
            . '<th width="8%"  style="text-align:center; color:#ffffff;">N/A</th>'
            . '<th width="10%" style="text-align:center; color:#ffffff;">%</th>'
            . '</tr>';

        $fill = false;
        foreach ( $stats['sections'] as $sec ) {
            $applicable_sec = max( 1, $sec['total'] - $sec['na'] );
            $pct       = round( ( $sec['compliant'] + $sec['partial'] * 0.5 ) / $applicable_sec * 100, 1 );
            $pct_color = $pct >= 70 ? '#16a34a' : ( $pct >= 40 ? '#d97706' : '#dc2626' );
            $row_bg    = $fill ? '#f9fafb' : '#ffffff';

            $h .= '<tr style="background-color:' . $row_bg . ';">'
                . '<td width="8%"  style="text-align:center; color:#1e293b;">' . self::h( $sec['ref'] )        . '</td>'
                . '<td width="44%" style="color:#1e293b;">'                    . self::h( $sec['title'] )      . '</td>'
                . '<td width="10%" style="text-align:center; color:#1e293b;">' . self::h( $sec['compliant'] )  . '</td>'
                . '<td width="10%" style="text-align:center; color:#1e293b;">' . self::h( $sec['partial'] )    . '</td>'
                . '<td width="10%" style="text-align:center; color:#1e293b;">' . self::h( $sec['non_comply'] ) . '</td>'
                . '<td width="8%"  style="text-align:center; color:#1e293b;">' . self::h( $sec['na'] )         . '</td>'
                . '<td width="10%" style="text-align:center; font-weight:bold; background-color:' . $pct_color . '; color:#ffffff;">' . self::h( $pct . '%' ) . '</td>'
                . '</tr>';
            $fill = ! $fill;
        }
        $h .= '</table>';

        return $h;
    }

    private static function build_checklist_html( $standard, $answers, $evidence, $lang, $is_demo = false ) {
        $ref_col   = $lang === 'it' ? 'Rif.'            : 'Ref.';
        $title_col = $lang === 'it' ? 'Requisito'       : 'Requirement';
        $ans_col   = $lang === 'it' ? 'Risposta'        : 'Answer';
        $note_col  = $lang === 'it' ? 'Note / Evidenze' : 'Notes / Evidence';

        $h = self::section_h2( $lang === 'it' ? 'Checklist Dettagliata' : 'Detailed Checklist' );

        // Widths as %: 8 + 42 + 15 + 35 = 100
        $h .= '<table border="1" cellspacing="0" cellpadding="3" width="100%" style="border-color:#e2e8f0; font-size:7pt;">';
        $h .= '<thead><tr style="background-color:#1e40af; font-weight:bold;">'
            . '<th width="8%" style="color:#ffffff; text-align:center;">'  . self::h( $ref_col ) . '</th>'
            . '<th width="42%" style="color:#ffffff; text-align:left;">'   . self::h( $title_col ) . '</th>'
            . '<th width="15%" style="color:#ffffff; text-align:center;">' . self::h( $ans_col ) . '</th>'
            . '<th width="35%" style="color:#ffffff; text-align:left;">'   . self::h( $note_col ) . '</th>'
            . '</tr></thead><tbody>';

        $fill = false;
        $pending_section = null;
        foreach ( $standard['clauses'] as $clause ) {
            if ( (int) $clause['level'] === 1 ) {
                $pending_section = $clause;
                $fill = false;
                continue;
            }

            $ref = $clause['reference'];

            // Demo: skip questions without answers
            if ( $is_demo && ! isset( $answers[ $ref ] ) ) {
                continue;
            }

            // Emit section header if pending
            if ( $pending_section ) {
                $h .= '<tr style="background-color:#eef2ff;">'
                    . '<td colspan="4" style="font-weight:bold; font-size:8pt; color:#1e293b;">'
                    . self::h( $pending_section['reference'] . '. ' . $pending_section['title'] )
                    . '</td></tr>';
                $pending_section = null;
            }
            $val      = ( $answers[ $ref ] ?? [] )['value'] ?? null;
            $note     = ( $answers[ $ref ] ?? [] )['note'] ?? '';
            $files    = $evidence[ $ref ] ?? [];
            $file_str = implode( ', ', array_map( 'basename', (array) $files ) );
            $note_out = trim( $note . ( $file_str ? "\n" . $file_str : '' ) );

            if ( $val === 1.0 || $val === 1 ) {
                $ans_label = $lang === 'it' ? 'Conforme'     : 'Compliant';
                $ans_bg    = '#16a34a';
            } elseif ( $val === 0.5 ) {
                $ans_label = $lang === 'it' ? 'Parzialmente' : 'Partial';
                $ans_bg    = '#d97706';
            } elseif ( $val === 0.0 || $val === 0 ) {
                $ans_label = $lang === 'it' ? 'Non-Conforme' : 'Non-Compliant';
                $ans_bg    = '#dc2626';
            } elseif ( $val === 'na' ) {
                $ans_label = $lang === 'it' ? 'Non Appl.'    : 'N/A';
                $ans_bg    = '#6b7280';
            } else {
                $ans_label = '—';
                $ans_bg    = '#9ca3af';
            }

            $row_bg = $fill ? '#f9fafb' : '#ffffff';

            $h .= '<tr style="background-color:' . $row_bg . ';">'
                . '<td width="8%" style="text-align:center; color:#1e293b;">'  . self::h( $clause['clause_ref'] ) . '</td>'
                . '<td width="42%" style="color:#1e293b;">'                    . self::h( $clause['title'] ) . '</td>'
                . '<td width="15%" style="text-align:center; font-weight:bold; background-color:' . $ans_bg . '; color:#ffffff;">' . self::h( $ans_label ) . '</td>'
                . '<td width="35%" style="color:#475569;">'                    . nl2br( self::h( $note_out ) ) . '</td>'
                . '</tr>';

            $fill = ! $fill;
        }

        $h .= '</tbody></table>';
        return $h;
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private static function calc_stats( $answers, $standard ) {
        $total      = 0;
        $compliant  = 0;
        $partial    = 0;
        $non_comply = 0;
        $na         = 0;
        $unanswered = 0;
        $sections   = [];
        $cur_sec    = null;

        if ( $standard ) {
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] === 1 ) {
                    $cur_sec = $clause['reference'];
                    $sections[ $cur_sec ] = [
                        'title'      => $clause['title'],
                        'ref'        => $clause['clause_ref'],
                        'total'      => 0,
                        'compliant'  => 0,
                        'partial'    => 0,
                        'non_comply' => 0,
                        'na'         => 0,
                        'unanswered' => 0,
                    ];
                    continue;
                }

                $total++;
                if ( $cur_sec ) $sections[ $cur_sec ]['total']++;

                $ref = $clause['reference'];
                $val = ( $answers[ $ref ] ?? [] )['value'] ?? null;

                if ( $val === 'na' ) {
                    $na++;
                    if ( $cur_sec ) $sections[ $cur_sec ]['na']++;
                } elseif ( $val === 1.0 || $val === 1 ) {
                    $compliant++;
                    if ( $cur_sec ) $sections[ $cur_sec ]['compliant']++;
                } elseif ( $val === 0.5 ) {
                    $partial++;
                    if ( $cur_sec ) $sections[ $cur_sec ]['partial']++;
                } elseif ( $val === 0.0 || $val === 0 ) {
                    $non_comply++;
                    if ( $cur_sec ) $sections[ $cur_sec ]['non_comply']++;
                } else {
                    $unanswered++;
                    if ( $cur_sec ) $sections[ $cur_sec ]['unanswered']++;
                }
            }
        }

        return [
            'total'      => $total,
            'applicable' => $total - $na,
            'compliant'  => $compliant,
            'partial'    => $partial,
            'non_comply' => $non_comply,
            'na'         => $na,
            'unanswered' => $unanswered,
            'sections'   => $sections,
        ];
    }

    private static function build_executive_summary( $sub, $stats, $lang, $std_name ) {
        $score_pct  = round( $sub->score * 100, 1 );
        $date_fmt   = date_i18n( 'd/m/Y', strtotime( $sub->submitted_at ) );
        $cons_extra = $sub->consultant_company ? ' (' . $sub->consultant_company . ')' : '';

        if ( $lang === 'it' ) {
            $level = $sub->score >= 0.7 ? 'elevato' : ( $sub->score >= 0.4 ? 'parziale' : 'insufficiente' );
            return sprintf(
                'In data %s, %s ha richiesto una Gap Analysis rispetto allo standard %s, condotta dal consulente %s%s. ' .
                'L\'analisi ha esaminato complessivamente %d requisiti, di cui %d applicabili all\'organizzazione ' .
                '(%d classificati come Non Applicabili). ' .
                'Il punteggio complessivo indica un livello di conformità %s pari al %s%%: ' .
                '%d requisiti risultano Conformi, %d Parzialmente Conformi, %d Non Conformi. ' .
                'I requisiti di non conformità identificati rappresentano le aree prioritarie su cui concentrare ' .
                'il piano di azione e miglioramento continuo.',
                $date_fmt, $sub->company_name, $std_name, $sub->consultant_name, $cons_extra,
                $stats['total'], $stats['applicable'], $stats['na'],
                $level, $score_pct,
                $stats['compliant'], $stats['partial'], $stats['non_comply']
            );
        } else {
            $level = $sub->score >= 0.7 ? 'high' : ( $sub->score >= 0.4 ? 'partial' : 'insufficient' );
            return sprintf(
                'On %s, %s requested a Gap Analysis against the %s standard, conducted by consultant %s%s. ' .
                'The analysis examined a total of %d requirements, of which %d are applicable to the organisation ' .
                '(%d classified as Not Applicable). ' .
                'The overall score indicates a %s level of compliance at %s%%: ' .
                '%d requirements are Compliant, %d Partially Compliant, and %d Non-Compliant. ' .
                'The identified non-compliant requirements represent the priority areas on which to focus ' .
                'the action and continuous improvement plan.',
                $date_fmt, $sub->company_name, $std_name, $sub->consultant_name, $cons_extra,
                $stats['total'], $stats['applicable'], $stats['na'],
                $level, $score_pct,
                $stats['compliant'], $stats['partial'], $stats['non_comply']
            );
        }
    }

    private static function score_color( $score ) {
        if ( $score >= 0.7 ) return [ 22, 163, 74 ];
        if ( $score >= 0.4 ) return [ 217, 119, 6 ];
        return [ 220, 38, 38 ];
    }

    private static function url_to_path( $url ) {
        $upload_dir = wp_upload_dir();
        return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
    }
}
