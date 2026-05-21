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

// Section count for step numbering: step 0 = mode, step 1 = company, steps 2..N+1 = sections, step N+2 = review
$section_keys  = array_keys( $sections );
$section_count = count( $section_keys );
$review_step   = $section_count + 2;

// Build section metadata for JS
$sections_meta = [];
foreach ( $sections as $sec_ref => $section ) {
    $sections_meta[] = [
        'ref'     => $sec_ref,
        'heading' => $sec_ref . '. ' . $section['heading'],
        'count'   => count( $section['questions'] ),
    ];
}

// Strip standard name prefix
$standard_short = preg_replace( '/^(Integrated\s+)?Gap\s+Analysis\s+Checklist\s+|^Checklist\s+Gap\s+Analysis\s+/i', '', $standard['name'] );

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
    'next'         => $lang === 'it' ? 'Avanti'          : 'Next',
    'back'         => $lang === 'it' ? 'Indietro'        : 'Back',
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
    'sect_contact_self' => $lang === 'it' ? 'I tuoi dati'  : 'Your Details',
    'sect_consult' => $lang === 'it' ? 'Consulente'        : 'Consultant',
    'consult_co'   => $lang === 'it' ? 'Azienda'           : 'Company',
    'step1_title'  => $lang === 'it' ? 'Dati Azienda e Contatti' : 'Company & Contacts',
    'step_review'  => $lang === 'it' ? 'Riepilogo e Invio'         : 'Review & Submit',
    'dl_label'       => $lang === 'it' ? 'Scarica i tuoi risultati:' : 'Download your results:',
    'dl_pdf'         => $lang === 'it' ? 'Scarica PDF'               : 'Download PDF',
    'dl_pdf_sub'     => $lang === 'it' ? 'Report completo'           : 'Full report',
    'dl_csv'         => $lang === 'it' ? 'Scarica CSV'               : 'Download CSV',
    'dl_csv_sub'     => $lang === 'it' ? 'Excel / Foglio di calcolo' : 'Excel / Spreadsheet',
    'dl_md'          => $lang === 'it' ? 'Scarica Markdown'          : 'Download Markdown',
    'dl_md_sub'      => $lang === 'it' ? 'Testo semplice'            : 'Plain text',
    'results_online' => $lang === 'it' ? 'Visualizza i risultati online' : 'View results online',
    // Mode selector
    'mode_title'     => $lang === 'it' ? 'Come vuoi compilare la checklist?' : 'How will you fill out the checklist?',
    'mode_self'      => $lang === 'it' ? 'Autovalutazione'      : 'Self-Assessment',
    'mode_self_desc' => $lang === 'it' ? 'L\'azienda compila autonomamente la checklist.' : 'The company fills the checklist independently.',
    'mode_assisted'      => $lang === 'it' ? 'Con Consulente'       : 'Consultant-Assisted',
    'mode_assisted_desc' => $lang === 'it' ? 'L\'azienda compila con l\'assistenza di un consulente.' : 'The company fills with a consultant present.',
    // Save status
    'saving'         => $lang === 'it' ? 'Salvataggio...'     : 'Saving...',
    'saved_at'       => $lang === 'it' ? 'Salvato alle'       : 'Saved at',
    'save_failed'    => $lang === 'it' ? 'Salvataggio fallito' : 'Save failed',
    'draft_restored' => $lang === 'it' ? 'Bozza ripristinata' : 'Draft restored',
    'draft_dismiss'  => $lang === 'it' ? 'Chiudi'             : 'Dismiss',
];
?>

<div class="gapnext-form-wrap" id="gapnext-form-wrap">

    <?php if ( ! empty( $is_demo ) ) : ?>
    <div class="gapnext-demo-banner">
        <strong>DEMO</strong> —
        <?php
        $demo_q_count = $total_questions;
        $full_q_count = ! empty( $full_question_count ) ? (int) $full_question_count : $total_questions;
        echo esc_html( $lang === 'it'
            ? sprintf( 'Questa è una versione demo con %d di %d domande', $demo_q_count, $full_q_count )
            : sprintf( 'This is a demo version with %d of %d questions', $demo_q_count, $full_q_count )
        );
        ?>
    </div>
    <?php endif; ?>

    <!-- Audit scope header -->
    <header class="gapnext-scope-header">
        <p class="gapnext-scope-eyebrow"><?php esc_html_e( 'Gap Analysis', 'gapnext-wp' ); ?></p>
        <h1 class="gapnext-scope-standard"><?php echo esc_html( 'Checklist - ' . $standard_short ); ?></h1>
    </header>

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
        <span class="gapnext-save-status" id="gapnext-save-status"></span>
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
            <?php echo esc_html( $labels['proj_score'] ); ?>: <strong id="gn-projected-score">&mdash;</strong>
        </span>
    </div>

    <!-- Step indicators (dynamic) -->
    <div class="gapnext-steps" id="gapnext-steps">
        <?php if ( empty( $is_demo ) ) : ?>
        <span class="gapnext-step active" data-step="0">
            <span class="gapnext-step-num">0</span>
            <span class="gapnext-step-label"><?php echo esc_html( $lang === 'it' ? 'Modalita' : 'Mode' ); ?></span>
        </span>
        <?php endif; ?>
        <span class="gapnext-step<?php echo ! empty( $is_demo ) ? ' active' : ''; ?>" data-step="1">
            <span class="gapnext-step-num">1</span>
            <span class="gapnext-step-label"><?php echo esc_html( $lang === 'it' ? 'Dati' : 'Info' ); ?></span>
        </span>
        <?php $step_idx = 2; foreach ( $sections as $sec_ref => $section ) : ?>
            <span class="gapnext-step" data-step="<?php echo esc_attr( $step_idx ); ?>" data-section="<?php echo esc_attr( $sec_ref ); ?>" data-total="<?php echo esc_attr( count( $section['questions'] ) ); ?>">
                <span class="gapnext-step-num"><?php echo esc_html( $step_idx ); ?></span>
                <span class="gapnext-step-label"><?php echo esc_html( $sec_ref ); ?></span>
                <span class="gapnext-step-badge">0/<?php echo esc_html( count( $section['questions'] ) ); ?></span>
            </span>
        <?php $step_idx++; endforeach; ?>
        <span class="gapnext-step" data-step="<?php echo esc_attr( $review_step ); ?>">
            <span class="gapnext-step-num"><?php echo esc_attr( $review_step ); ?></span>
            <span class="gapnext-step-label"><?php echo esc_html( $lang === 'it' ? 'Invio' : 'Submit' ); ?></span>
        </span>
    </div>

    <!-- Auto-save info -->
    <div class="gapnext-autosave-info" id="gapnext-autosave-info">
        <button type="button" class="gapnext-autosave-toggle" id="gapnext-autosave-toggle">
            <svg class="gapnext-autosave-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 10.5a.75.75 0 110-1.5.75.75 0 010 1.5zM8.75 7.5a.75.75 0 01-1.5 0v-3a.75.75 0 011.5 0v3z" fill="currentColor"/></svg>
            <span><?php echo esc_html( $lang === 'it' ? 'I progressi vengono salvati automaticamente' : 'Progress is automatically saved' ); ?></span>
            <svg class="gapnext-autosave-chevron" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="gapnext-autosave-details" id="gapnext-autosave-details">
            <?php if ( $lang === 'it' ) : ?>
            <ul>
                <li>I tuoi progressi vengono salvati automaticamente dopo ogni passaggio.</li>
                <li>Se non completi la compilazione, riceverai un'email con il link per riprendere da dove avevi lasciato.</li>
                <li>Puoi riaprire lo stesso link in qualsiasi momento: i tuoi dati saranno ancora disponibili.</li>
                <li>Ti consigliamo di salvare o aggiungere ai preferiti il link di questa pagina per accedervi facilmente in futuro.</li>
            </ul>
            <?php else : ?>
            <ul>
                <li>Your progress is saved automatically after each step.</li>
                <li>If you don't finish, a reminder email will be sent with a link to continue where you left off.</li>
                <li>You can reopen the same link at any time — your data will still be there.</li>
                <li>We recommend bookmarking this page link for easy future access.</li>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Draft restored banner -->
    <div id="gapnext-draft-banner" class="gapnext-draft-banner" style="display:none">
        <span id="gapnext-draft-banner-text"></span>
        <button type="button" class="gapnext-draft-dismiss" id="gapnext-draft-dismiss"><?php echo esc_html( $labels['draft_dismiss'] ); ?></button>
    </div>

    <div id="gapnext-draft-status"></div>

    <form id="gapnext-audit-form" enctype="multipart/form-data">
        <input type="hidden" name="audit_uuid" value="<?php echo esc_attr( $audit->uuid ); ?>">
        <input type="hidden" name="action" value="gapnext_submit">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'gapnext_submit' ) ); ?>">
        <input type="hidden" name="total_questions" value="<?php echo esc_attr( $total_questions ); ?>">
        <input type="hidden" name="draft_id" id="gapnext-draft-id" value="0">
        <input type="hidden" name="filling_mode" id="gapnext-filling-mode" value="<?php echo ! empty( $is_demo ) ? 'self' : ''; ?>">
        <input type="hidden" name="last_step" id="gapnext-last-step" value="0">

        <!-- STEP 0: Mode Selector -->
        <div class="gapnext-step-content" id="gapnext-step-0"<?php if ( ! empty( $is_demo ) ) echo ' style="display:none"'; ?>
            <h2><?php echo esc_html( $labels['mode_title'] ); ?></h2>
            <div class="gapnext-mode-selector">
                <button type="button" class="gapnext-mode-card" data-mode="self">
                    <span class="gapnext-mode-icon">&#128100;</span>
                    <span class="gapnext-mode-name"><?php echo esc_html( $labels['mode_self'] ); ?></span>
                    <span class="gapnext-mode-desc"><?php echo esc_html( $labels['mode_self_desc'] ); ?></span>
                </button>
                <button type="button" class="gapnext-mode-card" data-mode="assisted">
                    <span class="gapnext-mode-icon">&#128101;</span>
                    <span class="gapnext-mode-name"><?php echo esc_html( $labels['mode_assisted'] ); ?></span>
                    <span class="gapnext-mode-desc"><?php echo esc_html( $labels['mode_assisted_desc'] ); ?></span>
                </button>
            </div>
        </div>

        <!-- STEP 1: Company & Contacts -->
        <div class="gapnext-step-content" id="gapnext-step-1"<?php if ( empty( $is_demo ) ) echo ' style="display:none"'; ?>>
            <h2><?php echo esc_html( $labels['step1_title'] ); ?></h2>
            <div id="gapnext-toast-container"></div>

            <fieldset>
                <legend><?php echo esc_html( $labels['sect_company'] ); ?></legend>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_name'] ); ?></label><input type="text" name="company_name" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_addr'] ); ?></label><input type="text" name="company_address"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_vat'] ); ?></label><input type="text" name="company_vat"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['company_sec'] ); ?></label><input type="text" name="company_sector"></div>
            </fieldset>

            <fieldset>
                <legend id="gapnext-contact-legend"><?php echo esc_html( ! empty( $is_demo ) ? $labels['sect_contact_self'] : $labels['sect_contact'] ); ?></legend>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_name'] ); ?></label><input type="text" name="contact_name" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_role'] ); ?></label><input type="text" name="contact_role"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_email'] ); ?></label><input type="email" name="contact_email" required></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_phone'] ); ?></label><input type="tel" name="contact_phone"></div>
            </fieldset>

            <fieldset id="gapnext-consultant-fieldset"<?php if ( ! empty( $is_demo ) ) echo ' style="display:none"'; ?>>
                <legend><?php echo esc_html( $labels['sect_consult'] ); ?></legend>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_name'] ); ?></label><input type="text" name="consultant_name" class="gapnext-consultant-field"<?php if ( empty( $is_demo ) ) echo ' required'; ?>></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['consult_co'] ); ?></label><input type="text" name="consultant_company"></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_email'] ); ?></label><input type="email" name="consultant_email" class="gapnext-consultant-field"<?php if ( empty( $is_demo ) ) echo ' required'; ?>></div>
                <div class="gapnext-field"><label><?php echo esc_html( $labels['contact_phone'] ); ?></label><input type="tel" name="consultant_phone"></div>
            </fieldset>

            <div class="gapnext-nav">
                <?php if ( empty( $is_demo ) ) : ?>
                <button type="button" class="gapnext-btn gapnext-prev" data-prev="0"><?php echo esc_html( $labels['back'] ); ?></button>
                <?php endif; ?>
                <button type="button" class="gapnext-btn gapnext-next" data-next="2"><?php echo esc_html( $labels['next'] ); ?></button>
            </div>
        </div>

        <!-- SECTION STEPS: one per level-1 clause group -->
        <?php $step_idx = 2; foreach ( $sections as $sec_ref => $section ) :
            $prev_step = $step_idx - 1;
            $next_step = $step_idx + 1;
        ?>
            <div class="gapnext-step-content gapnext-section-step" id="gapnext-step-<?php echo esc_attr( $step_idx ); ?>" data-section="<?php echo esc_attr( $sec_ref ); ?>" style="display:none">
                <h2><?php echo esc_html( $sec_ref . '. ' . $section['heading'] ); ?></h2>

                <?php foreach ( $section['questions'] as $q ) : ?>
                    <?php $ref = esc_attr( $q['reference'] ); ?>
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

                <div class="gapnext-nav">
                    <button type="button" class="gapnext-btn gapnext-prev" data-prev="<?php echo esc_attr( $prev_step ); ?>"><?php echo esc_html( $labels['back'] ); ?></button>
                    <button type="button" class="gapnext-btn gapnext-next" data-next="<?php echo esc_attr( $next_step ); ?>"><?php echo esc_html( $labels['next'] ); ?></button>
                </div>
            </div>
        <?php $step_idx++; endforeach; ?>

        <!-- REVIEW & SUBMIT STEP -->
        <div class="gapnext-step-content" id="gapnext-step-<?php echo esc_attr( $review_step ); ?>" style="display:none">
            <h2><?php echo esc_html( $labels['step_review'] ); ?></h2>
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
                    <strong id="gn-rev-score">&mdash;</strong>
                </div>
            </div>
            <p id="gapnext-summary-text"></p>
            <div class="gapnext-nav">
                <button type="button" class="gapnext-btn gapnext-prev" data-prev="<?php echo esc_attr( $review_step - 1 ); ?>"><?php echo esc_html( $labels['back'] ); ?></button>
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
                <span class="gn-review-count" id="gn-final-compliant">&mdash;</span>
            </div>
            <div class="gn-review-row gn-parzialmente">
                <span class="gn-review-label"><?php echo esc_html( $labels['parzialmente'] ); ?></span>
                <span class="gn-review-count" id="gn-final-partial">&mdash;</span>
            </div>
            <div class="gn-review-row gn-non-conforme">
                <span class="gn-review-label"><?php echo esc_html( $labels['non_conforme'] ); ?></span>
                <span class="gn-review-count" id="gn-final-noncompliant">&mdash;</span>
            </div>
            <div class="gn-review-row gn-na">
                <span class="gn-review-label"><?php echo esc_html( $labels['na'] ); ?></span>
                <span class="gn-review-count" id="gn-final-na">&mdash;</span>
            </div>
            <div class="gn-review-row gn-unanswered">
                <span class="gn-review-label"><?php echo esc_html( $labels['unanswered'] ); ?></span>
                <span class="gn-review-count" id="gn-final-unanswered">&mdash;</span>
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
