<?php
// wp-plugin/gapnext-wp/includes/views/results.php
if ( ! defined( 'ABSPATH' ) ) exit;

$lang     = $sub->language;
$answers  = json_decode( $sub->answers, true ) ?: [];
$score    = round( $sub->score * 100, 1 );
$date_fmt = date_i18n( 'd/m/Y', strtotime( $sub->submitted_at ) );
$std_name = $standard ? $standard['name'] : $sub->standard_id;

// Compute stats
$total = $compliant = $partial = $non_comply = $na = 0;
if ( $standard ) {
    foreach ( $standard['clauses'] as $clause ) {
        if ( (int) $clause['level'] !== 2 ) continue;
        $total++;
        $val = ( $answers[ $clause['reference'] ] ?? [] )['value'] ?? null;
        if ( $val === 'na' )                    $na++;
        elseif ( $val === 1.0 || $val === 1 )   $compliant++;
        elseif ( $val === 0.5 )                 $partial++;
        elseif ( $val === 0.0 || $val === 0 )   $non_comply++;
    }
}
$answered   = $compliant + $partial + $non_comply + $na;
$unanswered = $total - $answered;
$applicable = $total - $na;
$denominator = $applicable > 0 ? $applicable : 1;

// Labels
$lbl = [
    'compliant'    => $lang === 'it' ? 'Conforme'      : 'Compliant',
    'partial'      => $lang === 'it' ? 'Parzialmente'  : 'Partial',
    'non_comply'   => $lang === 'it' ? 'Non-Conforme'  : 'Non-Compliant',
    'na'           => $lang === 'it' ? 'Non Applicabile' : 'Not Applicable',
    'unanswered'   => $lang === 'it' ? 'Non risposto'  : 'Unanswered',
    'total'        => $lang === 'it' ? 'Domande totali' : 'Total questions',
    'answered'     => $lang === 'it' ? 'Risposte'      : 'Answered',
    'applicable'   => $lang === 'it' ? 'Applicabili'   : 'Applicable',
    'score'        => $lang === 'it' ? 'Punteggio'     : 'Score',
    'company'      => $lang === 'it' ? 'Azienda'       : 'Company',
    'contact'      => $lang === 'it' ? 'Referente'     : 'Contact',
    'consultant'   => $lang === 'it' ? 'Consulente'    : 'Consultant',
    'notes'        => $lang === 'it' ? 'Note'          : 'Notes',
    'ref'          => $lang === 'it' ? 'Rif.'          : 'Ref.',
    'requirement'  => $lang === 'it' ? 'Requisito'     : 'Requirement',
    'status'       => $lang === 'it' ? 'Stato'         : 'Status',
    'date'         => $lang === 'it' ? 'Data'          : 'Date',
    'download_pdf' => $lang === 'it' ? 'Scarica PDF'   : 'Download PDF',
    'download_csv' => $lang === 'it' ? 'Scarica CSV'   : 'Download CSV',
    'download_md'  => $lang === 'it' ? 'Scarica Markdown' : 'Download Markdown',
    'evidence'     => $lang === 'it' ? 'Evidenze'      : 'Evidence',
    'vat'          => $lang === 'it' ? 'P.IVA'         : 'VAT',
    'address'      => $lang === 'it' ? 'Indirizzo'     : 'Address',
    'sector'       => $lang === 'it' ? 'Settore'       : 'Sector',
    'role'         => $lang === 'it' ? 'Ruolo'         : 'Role',
    'phone'        => $lang === 'it' ? 'Tel.'          : 'Phone',
];

// Build evidence URL map: [ ref => [ url1, url2, ... ] ]
$evidence_paths = json_decode( $sub->evidence_paths ?? '[]', true ) ?: [];
$upload_dir     = wp_upload_dir();
$evidence_urls  = [];
foreach ( $evidence_paths as $ref => $paths ) {
    if ( ! is_array( $paths ) ) continue;
    foreach ( $paths as $path ) {
        $url = str_replace(
            wp_normalize_path( $upload_dir['basedir'] ),
            $upload_dir['baseurl'],
            wp_normalize_path( $path )
        );
        $evidence_urls[ $ref ][] = $url;
    }
}

// Score color
$score_color = $score >= 80 ? '#16a34a' : ( $score >= 50 ? '#d97706' : '#dc2626' );

// Download base URL
$dl_base = admin_url( 'admin-post.php' ) . '?action=gapnext_download&sub=' . $sub->id . '&audit=' . rawurlencode( $sub->audit_uuid );
?>
<div class="gapnext-results-wrap">

    <?php if ( ! empty( $is_demo ) ) : ?>
    <div class="gapnext-demo-banner">
        <strong>DEMO</strong> —
        <?php
        $demo_answered = $answered;
        $full_q = ! empty( $full_question_count ) ? (int) $full_question_count : $total;
        echo esc_html( $lang === 'it'
            ? sprintf( 'Report demo — %d di %d domande valutate', $demo_answered, $full_q )
            : sprintf( 'Demo report — %d of %d questions evaluated', $demo_answered, $full_q )
        );
        ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="gnr-header">
        <h1 class="gnr-title"><?php echo esc_html( $std_name ); ?></h1>
        <div class="gnr-meta">
            <span><?php echo esc_html( $sub->company_name ); ?></span>
            <span class="gnr-sep">·</span>
            <span><?php echo esc_html( $lbl['date'] ); ?>: <?php echo esc_html( $date_fmt ); ?></span>
        </div>
    </div>

    <!-- Score badge + stats row -->
    <div class="gnr-summary-row">
        <div class="gnr-score-badge" style="border-color:<?php echo esc_attr( $score_color ); ?>;color:<?php echo esc_attr( $score_color ); ?>">
            <?php echo esc_html( $score ); ?>%
            <div class="gnr-score-label"><?php echo esc_html( $lbl['score'] ); ?></div>
        </div>
        <div class="gnr-stats-grid">
            <div class="gnr-stat-cell">
                <span class="gnr-stat-num"><?php echo esc_html( $total ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['total'] ); ?></span>
            </div>
            <div class="gnr-stat-cell">
                <span class="gnr-stat-num"><?php echo esc_html( $answered ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['answered'] ); ?></span>
            </div>
            <div class="gnr-stat-cell gnr-conforme">
                <span class="gnr-stat-num"><?php echo esc_html( $compliant ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['compliant'] ); ?></span>
            </div>
            <div class="gnr-stat-cell gnr-parzialmente">
                <span class="gnr-stat-num"><?php echo esc_html( $partial ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['partial'] ); ?></span>
            </div>
            <div class="gnr-stat-cell gnr-non-conforme">
                <span class="gnr-stat-num"><?php echo esc_html( $non_comply ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['non_comply'] ); ?></span>
            </div>
            <div class="gnr-stat-cell gnr-na">
                <span class="gnr-stat-num"><?php echo esc_html( $na ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['na'] ); ?></span>
            </div>
            <?php if ( $unanswered > 0 ) : ?>
            <div class="gnr-stat-cell gnr-unanswered">
                <span class="gnr-stat-num"><?php echo esc_html( $unanswered ); ?></span>
                <span class="gnr-stat-lbl"><?php echo esc_html( $lbl['unanswered'] ); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Distribution bar -->
    <?php if ( $total > 0 ) : ?>
    <div class="gnr-distrib-bar">
        <?php if ( $compliant  > 0 ) : ?><div class="gnr-bar-seg gnr-bar-compliant"  style="width:<?php echo esc_attr( round( $compliant  / $total * 100, 1 ) ); ?>%" title="<?php echo esc_attr( $lbl['compliant']  . ': ' . $compliant  ); ?>"></div><?php endif; ?>
        <?php if ( $partial    > 0 ) : ?><div class="gnr-bar-seg gnr-bar-partial"    style="width:<?php echo esc_attr( round( $partial    / $total * 100, 1 ) ); ?>%" title="<?php echo esc_attr( $lbl['partial']    . ': ' . $partial    ); ?>"></div><?php endif; ?>
        <?php if ( $non_comply > 0 ) : ?><div class="gnr-bar-seg gnr-bar-noncomply"  style="width:<?php echo esc_attr( round( $non_comply / $total * 100, 1 ) ); ?>%" title="<?php echo esc_attr( $lbl['non_comply'] . ': ' . $non_comply ); ?>"></div><?php endif; ?>
        <?php if ( $na         > 0 ) : ?><div class="gnr-bar-seg gnr-bar-na"         style="width:<?php echo esc_attr( round( $na         / $total * 100, 1 ) ); ?>%" title="<?php echo esc_attr( $lbl['na']         . ': ' . $na         ); ?>"></div><?php endif; ?>
        <?php if ( $unanswered > 0 ) : ?><div class="gnr-bar-seg gnr-bar-unanswered" style="width:<?php echo esc_attr( round( $unanswered / $total * 100, 1 ) ); ?>%" title="<?php echo esc_attr( $lbl['unanswered'] . ': ' . $unanswered ); ?>"></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $is_demo ) ) : ?>
    <div class="gapnext-demo-cta">
        <h3><?php echo esc_html( $lang === 'it'
            ? 'Vuoi la Gap Analysis completa?'
            : 'Want the full Gap Analysis?' ); ?></h3>
        <p><?php echo esc_html( $lang === 'it'
            ? sprintf( 'Questa demo ha valutato solo %d domande su %d. La versione completa include tutte le domande, report PDF dettagliato, analisi per sezione e piano di rimedio.', $demo_answered, $full_q )
            : sprintf( 'This demo evaluated only %d of %d questions. The full version includes all questions, detailed PDF report, section analysis, and remediation plan.', $demo_answered, $full_q ) ); ?></p>
        <?php
        $notification_email = get_option( 'gapnext_notification_email', get_option( 'admin_email' ) );
        if ( $notification_email ) : ?>
            <a href="mailto:<?php echo esc_attr( $notification_email ); ?>?subject=<?php echo esc_attr( $lang === 'it' ? 'Richiesta Gap Analysis completa' : 'Full Gap Analysis request' ); ?>" class="gapnext-btn gapnext-demo-cta-btn">
                <?php echo esc_html( $lang === 'it' ? 'Contattaci' : 'Contact Us' ); ?>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Contact info -->
    <div class="gnr-contacts-grid">
        <div class="gnr-contact-block">
            <h3><?php echo esc_html( $lbl['company'] ); ?></h3>
            <p><?php echo esc_html( $sub->company_name ); ?></p>
            <?php if ( $sub->company_address ) : ?><p><?php echo esc_html( $sub->company_address ); ?></p><?php endif; ?>
            <?php if ( $sub->company_vat )     : ?><p><?php echo esc_html( $lbl['vat'] ); ?>: <?php echo esc_html( $sub->company_vat ); ?></p><?php endif; ?>
            <?php if ( $sub->company_sector )  : ?><p><?php echo esc_html( $sub->company_sector ); ?></p><?php endif; ?>
        </div>
        <div class="gnr-contact-block">
            <h3><?php echo esc_html( $lbl['contact'] ); ?></h3>
            <p><?php echo esc_html( $sub->contact_name ); ?></p>
            <?php if ( $sub->contact_role )  : ?><p><?php echo esc_html( $sub->contact_role ); ?></p><?php endif; ?>
            <?php if ( $sub->contact_email ) : ?><p><?php echo esc_html( $sub->contact_email ); ?></p><?php endif; ?>
            <?php if ( $sub->contact_phone ) : ?><p><?php echo esc_html( $sub->contact_phone ); ?></p><?php endif; ?>
        </div>
        <div class="gnr-contact-block">
            <h3><?php echo esc_html( $lbl['consultant'] ); ?></h3>
            <p><?php echo esc_html( $sub->consultant_name ); ?></p>
            <?php if ( $sub->consultant_company ) : ?><p><?php echo esc_html( $sub->consultant_company ); ?></p><?php endif; ?>
            <?php if ( $sub->consultant_email )   : ?><p><?php echo esc_html( $sub->consultant_email ); ?></p><?php endif; ?>
            <?php if ( $sub->consultant_phone )   : ?><p><?php echo esc_html( $sub->consultant_phone ); ?></p><?php endif; ?>
        </div>
    </div>

    <!-- Downloads -->
    <div class="gnr-downloads">
        <a href="<?php echo esc_url( $dl_base . '&format=pdf' ); ?>" class="gapnext-btn gapnext-dl-btn" download><?php echo esc_html( $lbl['download_pdf'] ); ?></a>
        <a href="<?php echo esc_url( $dl_base . '&format=csv' ); ?>" class="gapnext-btn gapnext-dl-btn gapnext-btn-green" download><?php echo esc_html( $lbl['download_csv'] ); ?></a>
        <a href="<?php echo esc_url( $dl_base . '&format=md'  ); ?>" class="gapnext-btn gapnext-dl-btn gapnext-btn-purple" download><?php echo esc_html( $lbl['download_md'] ); ?></a>
    </div>

    <!-- Checklist table -->
    <?php if ( $standard ) : ?>
    <div class="gnr-checklist-wrap">
        <table class="gnr-checklist-table">
            <thead>
                <tr>
                    <th><?php echo esc_html( $lbl['ref'] ); ?></th>
                    <th><?php echo esc_html( $lbl['requirement'] ); ?></th>
                    <th><?php echo esc_html( $lbl['status'] ); ?></th>
                    <th><?php echo esc_html( $lbl['notes'] ); ?></th>
                    <th><?php echo esc_html( $lbl['evidence'] ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $current_section = '';
                foreach ( $standard['clauses'] as $clause ) :
                    if ( (int) $clause['level'] === 1 ) :
                        $current_section = $clause['reference'];
                        ?>
                        <tr class="gnr-section-row">
                            <td colspan="5"><strong><?php echo esc_html( $clause['reference'] . '. ' . $clause['title'] ); ?></strong></td>
                        </tr>
                        <?php
                    else :
                        $ref      = $clause['reference'];
                        $ans_data = $answers[ $ref ] ?? [];
                        $val      = $ans_data['value'] ?? null;
                        $note     = $ans_data['note']  ?? '';

                        if ( $val === 'na' ) {
                            $status_label = $lbl['na'];
                            $row_class    = 'gnr-row-na';
                        } elseif ( $val === 1.0 || $val === 1 ) {
                            $status_label = $lbl['compliant'];
                            $row_class    = 'gnr-row-compliant';
                        } elseif ( $val === 0.5 ) {
                            $status_label = $lbl['partial'];
                            $row_class    = 'gnr-row-partial';
                        } elseif ( $val === 0.0 || $val === 0 ) {
                            $status_label = $lbl['non_comply'];
                            $row_class    = 'gnr-row-noncomply';
                        } else {
                            $status_label = '—';
                            $row_class    = 'gnr-row-unanswered';
                        }
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td class="gnr-cell-ref"><?php echo esc_html( $clause['clause_ref'] ); ?></td>
                            <td><?php echo esc_html( $clause['title'] ); ?></td>
                            <td class="gnr-cell-status"><span class="gnr-status-badge"><?php echo esc_html( $status_label ); ?></span></td>
                            <td><?php echo esc_html( $note ); ?></td>
                            <td class="gnr-cell-evidence">
                                <?php if ( ! empty( $evidence_urls[ $ref ] ) ) : ?>
                                    <ul class="gnr-evidence-list">
                                    <?php foreach ( $evidence_urls[ $ref ] as $ev_url ) : ?>
                                        <li>
                                            <a href="<?php echo esc_url( $ev_url ); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html( basename( $ev_url ) ); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span class="gnr-no-evidence">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    endif;
                endforeach;
                ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
