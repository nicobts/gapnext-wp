<?php
// wp-plugin/gapnext-wp/includes/views/form.php
if ( ! defined( 'ABSPATH' ) ) exit;

$lang = $audit->language;

// Separate level-1 sections from level-2 questions, grouped by section
$sections = [];
$current_section = null;

foreach ( $standard['clauses'] as $clause ) {
    if ( (int) $clause['level'] === 1 ) {
        $current_section = $clause['reference'];
        $sections[ $current_section ] = [
            'heading'   => $clause['title'],
            'questions' => [],
        ];
    } elseif ( $current_section ) {
        $sections[ $current_section ]['questions'][] = $clause;
    }
}

// Count only level-2 questions for progress bar
$total_questions = array_sum( array_map( fn($s) => count( $s['questions'] ), $sections ) );

// Language labels
$labels = [
    'conforme'     => $lang === 'it' ? 'Conforme'      : 'Compliant',
    'parzialmente' => $lang === 'it' ? 'Parzialmente'  : 'Partial',
    'non_conforme' => $lang === 'it' ? 'Non-Conforme'  : 'Non-Compliant',
    'na'           => $lang === 'it' ? 'Non Appl.'     : 'N/A',
    'unanswered'   => $lang === 'it' ? 'Non risposto'  : 'Unanswered',
    'answered_of'  => $lang === 'it' ? 'Risposte'      : 'Answered',
    'proj_score'   => $lang === 'it' ? 'Punteggio stimato' : 'Projected score',
    'notes'        => $lang === 'it' ? 'Note'         : 'Notes',
    'evidence'     => $lang === 'it' ? 'Carica evidenze' : 'Upload Evidence',
    'step1'        => $lang === 'it' ? '1. Dati Azienda' : '1. Company Info',
    'step2'        => $lang === 'it' ? '2. Checklist'    : '2. Checklist',
    'step3'        => $lang === 'it' ? '3. Invio'        : '3. Submit',
    'next'         => $lang === 'it' ? 'Avanti →'        : 'Next →',
    'back'         => $lang === 'it' ? '← Indietro'      : '← Back',
    'submit_btn'   => $lang === 'it' ? 'Invia Checklist' : 'Submit Checklist',
    'success_title'=> $lang === 'it' ? 'Invio completato!'  : 'Submission complete!',
    'success_score'=> $lang === 'it' ? 'Punteggio finale:' : 'Final score:',
    'success_msg'  => $lang === 'it' ? 'Grazie per aver completato la gap analysis.' : 'Thank you for completing the gap analysis.',
    'company_name' => $lang === 'it' ? 'Ragione Sociale *' : 'Company Name *',
    'company_addr' => $lang === 'it' ? 'Indirizzo'         : 'Address',
    'company_vat'  => $lang === 'it' ? 'P.IVA / Reg.'      : 'VAT / Reg.',
    'company_sec'  => $lang === 'it' ? 'Settore'           : 'Industry Sector',
    'contact_name' => $lang === 'it' ? 'Nome *'            : 'Name *',
    'contact_role' => $lang === 'it' ? 'Ruolo'             : 'Role',
    'contact_email'=> $lang === 'it' ? 'Email *'           : 'Email *',
    'contact_phone'=> $lang === 'it' ? 'Telefono'          : 'Phone',
    'sect_company' => $lang === 'it' ? 'Dati Azienda'      : 'Company Details',
    'sect_contact' => $lang === 'it' ? 'Referente Interno' : 'Internal Contact',
    'sect_consult' => $lang === 'it' ? 'Consulente'        : 'Consultant',
    'consult_co'   => $lang === 'it' ? 'Azienda'           : 'Company',
    'step1_title'  => $lang === 'it' ? 'Dati Azienda e Contatti' : 'Company & Contacts',
    'step3_title'    => $lang === 'it' ? 'Riepilogo e Invio'         : 'Review & Submit',
    'dl_label'       => $lang === 'it' ? 'Scarica i tuoi risultati:' : 'Download your results:',
    'dl_pdf'         => $lang === 'it' ? 'Scarica PDF'               : 'Download PDF',
    'dl_pdf_sub'     => $lang === 'it' ? 'Report completo'           : 'Full report',
    'dl_csv'         => $lang === 'it' ? 'Scarica CSV'               : 'Download CSV',
    'dl_csv_sub'     => $lang === 'it' ? 'Excel / Foglio di calcolo' : 'Excel / Spreadsheet',
    'dl_md'          => $lang === 'it' ? 'Scarica Markdown'          : 'Download Markdown',
    'dl_md_sub'      => $lang === 'it' ? 'Testo semplice'            : 'Plain text',
    'results_online' => $lang === 'it' ? 'Visualizza i risultati online' : 'View results online',
];
?>

<div class="gapnext-form-wrap" id="gapnext-form-wrap">

    <!-- Progress bar -->
    <div class="gapnext-progress-bar-wrap">
        <div class="gapnext-progress-bar" id="gapnext-progress-bar" style="width:0%"></div>
    </div>
    <div class="gapnext-progress-meta">
        <span class="gapnext-progress-label" id="gapnext-progress-label">0%</span>
        <span class="gapnext-progress-answered">
            <strong id="gn-count-answered">0</strong> / <strong><?php echo esc_html( $total_questions ); ?></strong>
            <?php echo esc_html( $labels['answered_of'] ); ?>
        </span>
    </div>
    <div class="gapnext-score-breakdown" id="gapnext-score-breakdown">
        <span class="gn-stat gn-conforme">
            <span class="gn-dot"></span>
            <span id="gn-count-compliant">0</span> <?php echo esc_html( $labels['conforme'] ); ?>
        </span>
        <span class="gn-stat gn-parzialmente">
            <span class="gn-dot"></span>
            <span id="gn-count-partial">0</span> <?php echo esc_html( $labels['parzialmente'] ); ?>
        </span>
        <span class="gn-stat gn-non-conforme">
            <span class="gn-dot"></span>
            <span id="gn-count-noncompliant">0</span> <?php echo esc_html( $labels['non_conforme'] ); ?>
        </span>
        <span class="gn-stat gn-na">
            <span class="gn-dot"></span>
            <span id="gn-count-na">0</span> <?php echo esc_html( $labels['na'] ); ?>
        </span>
        <span class="gn-stat gn-projected">
            <?php echo esc_html( $labels['proj_score'] ); ?>: <strong id="gn-projected-score">—</strong>
        </span>
    </div>

    <!-- Step indicators -->
    <div class="gapnext-steps">
        <span class="gapnext-step active" data-step="1"><?php echo esc_html( $labels['step1'] ); ?></span>
        <span class="gapnext-step" data-step="2"><?php echo esc_html( $labels['step2'] ); ?></span>
        <span class="gapnext-step" data-step="3"><?php echo esc_html( $labels['step3'] ); ?></span>
    </div>

    <div id="gapnext-draft-status"></div>

    <form id="gapnext-audit-form" enctype="multipart/form-data">
        <input type="hidden" name="audit_uuid" value="<?php echo esc_attr( $audit->uuid ); ?>">
        <input type="hidden" name="action" value="gapnext_submit">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gapnext_submit' ) ); ?>">
        <input type="hidden" name="total_questions" value="<?php echo esc_attr( $total_questions ); ?>">
        <input type="hidden" name="draft_id" id="gapnext-draft-id" value="0">

        <!-- STEP 1: Company & Contacts -->
        <div class="gapnext-step-content" id="gapnext-step-1">
            <h2><?php echo esc_html( $labels['step1_title'] ); ?></h2>

            <fieldset>
                <legend><?php echo esc_html( $labels['sect_company'] ); ?></legend>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_name'] ); ?></label><input type="text" name="company_name" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_addr'] ); ?></label><input type="text" name="company_address"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_vat'] ); ?></label><input type="text" name="company_vat"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_sec'] ); ?></label><input type="text" name="company_sector"></div>
            </fieldset>

            <fieldset>
                <legend><?php echo esc_html( $labels['sect_contact'] ); ?></legend>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_name'] ); ?></label><input type="text" name="contact_name" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_role'] ); ?></label><input type="text" name="contact_role"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_email'] ); ?></label><input type="email" name="contact_email" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_phone'] ); ?></label><input type="tel" name="contact_phone"></div>
            </fieldset>

            <fieldset>
                <legend><?php echo esc_html( $labels['sect_consult'] ); ?></legend>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_name'] ); ?></label><input type="text" name="consultant_name" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['consult_co'] ); ?></label><input type="text" name="consultant_company"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_email'] ); ?></label><input type="email" name="consultant_email" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_phone'] ); ?></label><input type="tel" name="consultant_phone"></div>
            </fieldset>

            <button type="button" class="gapnext-btn gapnext-next" data-next="2"><?php echo esc_html( $labels['next'] ); ?></button>
        </div>

        <!-- STEP 2: Checklist -->
        <div class="gapnext-step-content" id="gapnext-step-2" style="display:none">
            <h2><?php echo esc_html( $standard['name'] ); ?></h2>

            <?php foreach ( $sections as $sec_ref => $section ) : ?>
                <div class="gapnext-accordion">
                    <button type="button" class="gapnext-accordion-header">
                        <span><?php echo esc_html( $sec_ref . '. ' . $section['heading'] ); ?></span>
                        <span class="gapnext-accordion-icon">▼</span>
                    </button>
                    <div class="gapnext-accordion-body">
                        <?php foreach ( $section['questions'] as $q ) : ?>
                            <?php
                            $ref = esc_attr( $q['reference'] );
                            ?>
                            <div class="gapnext-question" data-ref="<?php echo $ref; ?>">
                                <div class="gapnext-question-header">
                                    <strong><?php echo esc_html( $q['clause_ref'] ); ?></strong>
                                    <?php echo esc_html( $q['title'] ); ?>
                                </div>
                                <?php if ( ! empty( $q['description'] ) ) : ?>
                                    <p class="gapnext-question-desc"><?php echo esc_html( $q['description'] ); ?></p>
                                <?php endif; ?>
                                <?php if ( ! empty( $q['help_text'] ) ) : ?>
                                    <p class="gapnext-question-help"><em><?php echo esc_html( $q['help_text'] ); ?></em></p>
                                <?php endif; ?>

                                <div class="gapnext-answer-toggle">
                                    <label class="gapnext-answer conforme">
                                        <input type="radio" name="answer[<?php echo $ref; ?>]" value="1">
                                        <?php echo esc_html( $labels['conforme'] ); ?>
                                    </label>
                                    <label class="gapnext-answer parzialmente">
                                        <input type="radio" name="answer[<?php echo $ref; ?>]" value="0.5">
                                        <?php echo esc_html( $labels['parzialmente'] ); ?>
                                    </label>
                                    <label class="gapnext-answer non-conforme">
                                        <input type="radio" name="answer[<?php echo $ref; ?>]" value="0">
                                        <?php echo esc_html( $labels['non_conforme'] ); ?>
                                    </label>
                                    <label class="gapnext-answer na">
                                        <input type="radio" name="answer[<?php echo $ref; ?>]" value="na">
                                        <?php echo esc_html( $labels['na'] ); ?>
                                    </label>
                                </div>

                                <div class="gapnext-notes-wrap">
                                    <label><?php echo esc_html( $labels['notes'] ); ?></label>
                                    <textarea name="notes[<?php echo $ref; ?>]" rows="2" placeholder="..."></textarea>
                                </div>

                                <div class="gapnext-evidence-wrap">
                                    <label><?php echo esc_html( $labels['evidence'] ); ?></label>
                                    <input type="file" name="evidence[<?php echo $ref; ?>][]"
                                           multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="gapnext-nav">
                <button type="button" class="gapnext-btn gapnext-prev" data-prev="1"><?php echo esc_html( $labels['back'] ); ?></button>
                <button type="button" class="gapnext-btn gapnext-next" data-next="3"><?php echo esc_html( $labels['next'] ); ?></button>
            </div>
        </div>

        <!-- STEP 3: Submit -->
        <div class="gapnext-step-content" id="gapnext-step-3" style="display:none">
            <h2><?php echo esc_html( $labels['step3_title'] ); ?></h2>
            <div class="gapnext-review-stats" id="gapnext-review-stats">
                <div class="gn-review-row gn-conforme">
                    <span class="gn-review-label"><?php echo esc_html( $labels['conforme'] ); ?></span>
                    <span class="gn-review-count" id="gn-rev-compliant">0</span>
                </div>
                <div class="gn-review-row gn-parzialmente">
                    <span class="gn-review-label"><?php echo esc_html( $labels['parzialmente'] ); ?></span>
                    <span class="gn-review-count" id="gn-rev-partial">0</span>
                </div>
                <div class="gn-review-row gn-non-conforme">
                    <span class="gn-review-label"><?php echo esc_html( $labels['non_conforme'] ); ?></span>
                    <span class="gn-review-count" id="gn-rev-noncompliant">0</span>
                </div>
                <div class="gn-review-row gn-na">
                    <span class="gn-review-label"><?php echo esc_html( $labels['na'] ); ?></span>
                    <span class="gn-review-count" id="gn-rev-na">0</span>
                </div>
                <div class="gn-review-row gn-unanswered">
                    <span class="gn-review-label"><?php echo esc_html( $labels['unanswered'] ); ?></span>
                    <span class="gn-review-count" id="gn-rev-unanswered"><?php echo esc_html( $total_questions ); ?></span>
                </div>
                <div class="gn-review-score">
                    <span><?php echo esc_html( $labels['proj_score'] ); ?></span>
                    <strong id="gn-rev-score">—</strong>
                </div>
            </div>
            <p id="gapnext-summary-text"></p>
            <div class="gapnext-nav">
                <button type="button" class="gapnext-btn gapnext-prev" data-prev="2"><?php echo esc_html( $labels['back'] ); ?></button>
                <button type="submit" class="gapnext-btn gapnext-submit" id="gapnext-submit-btn">
                    <?php echo esc_html( $labels['submit_btn'] ); ?>
                </button>
            </div>
        </div>

    </form>

    <!-- Success screen -->
    <div id="gapnext-success" style="display:none">
        <h2><?php echo esc_html( $labels['success_title'] ); ?></h2>
        <p><?php echo esc_html( $labels['success_score'] ); ?> <strong id="gapnext-final-score"></strong></p>
        <div class="gapnext-success-stats" id="gapnext-success-stats">
            <div class="gn-review-row gn-conforme">
                <span class="gn-review-label"><?php echo esc_html( $labels['conforme'] ); ?></span>
                <span class="gn-review-count" id="gn-final-compliant">—</span>
            </div>
            <div class="gn-review-row gn-parzialmente">
                <span class="gn-review-label"><?php echo esc_html( $labels['parzialmente'] ); ?></span>
                <span class="gn-review-count" id="gn-final-partial">—</span>
            </div>
            <div class="gn-review-row gn-non-conforme">
                <span class="gn-review-label"><?php echo esc_html( $labels['non_conforme'] ); ?></span>
                <span class="gn-review-count" id="gn-final-noncompliant">—</span>
            </div>
            <div class="gn-review-row gn-na">
                <span class="gn-review-label"><?php echo esc_html( $labels['na'] ); ?></span>
                <span class="gn-review-count" id="gn-final-na">—</span>
            </div>
            <div class="gn-review-row gn-unanswered">
                <span class="gn-review-label"><?php echo esc_html( $labels['unanswered'] ); ?></span>
                <span class="gn-review-count" id="gn-final-unanswered">—</span>
            </div>
        </div>
        <p><?php echo esc_html( $labels['success_msg'] ); ?></p>
        <div id="gapnext-downloads" style="display:none">
            <p class="gapnext-downloads-label"><?php echo esc_html( $labels['dl_label'] ); ?></p>
            <div class="gapnext-downloads-btns">
                <a href="#" id="gapnext-dl-pdf" class="gapnext-dl-btn gapnext-dl-pdf" target="_blank">
                    <span class="gapnext-dl-icon">&#11123;</span>
                    <span class="gapnext-dl-label"><?php echo esc_html( $labels['dl_pdf'] ); ?></span>
                    <span class="gapnext-dl-sub"><?php echo esc_html( $labels['dl_pdf_sub'] ); ?></span>
                </a>
                <a href="#" id="gapnext-dl-csv" class="gapnext-dl-btn gapnext-dl-csv" target="_blank">
                    <span class="gapnext-dl-icon">&#11123;</span>
                    <span class="gapnext-dl-label"><?php echo esc_html( $labels['dl_csv'] ); ?></span>
                    <span class="gapnext-dl-sub"><?php echo esc_html( $labels['dl_csv_sub'] ); ?></span>
                </a>
                <a href="#" id="gapnext-dl-md" class="gapnext-dl-btn gapnext-dl-md" target="_blank">
                    <span class="gapnext-dl-icon">&#11123;</span>
                    <span class="gapnext-dl-label"><?php echo esc_html( $labels['dl_md'] ); ?></span>
                    <span class="gapnext-dl-sub"><?php echo esc_html( $labels['dl_md_sub'] ); ?></span>
                </a>
            </div>
        </div>
        <div id="gapnext-results-wrap" style="display:none;margin-top:20px">
            <a href="#" id="gapnext-results-link" class="gapnext-results-online-btn" target="_blank">
                <span>&#128279;</span> <?php echo esc_html( $labels['results_online'] ); ?>
            </a>
        </div>
    </div>

</div>
