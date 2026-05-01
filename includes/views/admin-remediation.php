<?php
// includes/views/admin-remediation.php
if ( ! defined( 'ABSPATH' ) ) exit;

$is_init = GapNext_Remediation_Log::is_initialized( $sub->id );

if ( ! $is_init ) : ?>
    <div class="gnr-init-panel">
        <h3><?php esc_html_e( 'Remediation Plan Not Started', 'gapnext-wp' ); ?></h3>
        <p><?php esc_html_e( 'Initialize the remediation plan to start tracking corrective actions and progress for each checklist item. This will create tracking entries for all questions.', 'gapnext-wp' ); ?></p>
        <button type="button" id="gnr-init-btn" class="button button-primary">
            <?php esc_html_e( 'Initialize Remediation Plan', 'gapnext-wp' ); ?>
        </button>
    </div>
<?php return; endif;

// Labels
$status_labels = [
    'open'           => $lang === 'it' ? 'Aperto' : 'Open',
    'in_progress'    => $lang === 'it' ? 'In Corso' : 'In Progress',
    'pending_review' => $lang === 'it' ? 'In Revisione' : 'Pending Review',
    'verified'       => $lang === 'it' ? 'Verificato' : 'Verified',
    'rejected'       => $lang === 'it' ? 'Respinto' : 'Rejected',
];

$answer_labels = [
    1   => $lang === 'it' ? 'Conforme' : 'Compliant',
    1.0 => $lang === 'it' ? 'Conforme' : 'Compliant',
    0.5 => $lang === 'it' ? 'Parziale' : 'Partial',
    0   => $lang === 'it' ? 'Non-Conforme' : 'Non-Compliant',
    0.0 => $lang === 'it' ? 'Non-Conforme' : 'Non-Compliant',
    'na'=> $lang === 'it' ? 'Non Appl.' : 'N/A',
];

$answer_class = function( $val ) {
    if ( $val === 1.0 || $val === 1 ) return 'conforme';
    if ( $val === 0.5 ) return 'parziale';
    if ( $val === 0.0 || $val === 0 ) return 'non-conforme';
    if ( $val === 'na' ) return 'na';
    return 'unanswered';
};

$pending_count = $agg['pending_review'] ?? 0;
$current_pct   = round( ( $agg['current_score'] ?? 0 ) * 100, 1 );
$original_pct  = round( $sub->score * 100, 1 );
?>

<!-- Aggregate stats -->
<div class="gnr-agg-bar">
    <div class="gnr-agg-stat" style="border-left-color:#2271b1">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['total'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Total', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#16a34a">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['verified'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Verified', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#d97706">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['pending_review'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Pending', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#2563eb">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['in_progress'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'In Progress', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#9ca3af">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['open'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Open', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#dc2626">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['overdue'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Overdue', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#7c3aed">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $original_pct ); ?>% → <?php echo esc_html( $current_pct ); ?>%</span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Score Progress', 'gapnext-wp' ); ?></span>
    </div>
</div>

<!-- Filters -->
<div class="gnr-filters">
    <button class="gnr-filter-btn active" data-filter="all"><?php esc_html_e( 'All', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="needs_action"><?php esc_html_e( 'Needs Action', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="pending_review"><?php esc_html_e( 'Pending Review', 'gapnext-wp' ); ?> <?php if ( $pending_count ) : ?><span class="gapnext-tab-badge"><?php echo esc_html( $pending_count ); ?></span><?php endif; ?></button>
    <button class="gnr-filter-btn" data-filter="in_progress"><?php esc_html_e( 'In Progress', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="open"><?php esc_html_e( 'Open', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="verified"><?php esc_html_e( 'Verified', 'gapnext-wp' ); ?></button>
</div>

<!-- Item list -->
<?php
if ( $standard ) :
    foreach ( $standard['clauses'] as $clause ) :
        if ( (int) $clause['level'] === 1 ) :
            ?>
            <h3 style="margin:20px 0 8px;font-size:14px;color:#1e3a8a">
                <?php echo esc_html( $clause['clause_ref'] . ' — ' . $clause['title'] ); ?>
            </h3>
            <?php
            continue;
        endif;

        $ref = $clause['reference'];
        $state = $states[ $ref ] ?? [
            'original_answer' => null, 'current_answer' => null, 'proposed_answer' => null,
            'status' => 'open', 'corrective_action' => '', 'priority' => 'medium',
            'deadline' => null, 'responsible' => '', 'evidence' => [],
            'pending_approval' => false, 'events' => [],
        ];

        $orig_val   = $state['original_answer'];
        $orig_label = $answer_labels[ $orig_val ] ?? '—';
        $orig_class = $answer_class( $orig_val );
        $events     = $state['events'] ?? [];
        ?>
        <div class="gnr-item" data-ref="<?php echo esc_attr( $ref ); ?>" data-status="<?php echo esc_attr( $state['status'] ); ?>">

            <div class="gnr-item-header">
                <span class="gnr-item-ref"><?php echo esc_html( $clause['clause_ref'] ); ?></span>
                <span class="gnr-item-title"><?php echo esc_html( $clause['title'] ); ?></span>
                <span class="gnr-item-original <?php echo esc_attr( $orig_class ); ?>"><?php echo esc_html( $orig_label ); ?></span>
                <span class="gnr-status gnr-status-<?php echo esc_attr( $state['status'] ); ?>">
                    <?php echo esc_html( $status_labels[ $state['status'] ] ?? $state['status'] ); ?>
                </span>
            </div>

            <?php if ( $state['pending_approval'] && $state['proposed_answer'] !== null ) : ?>
            <div class="gnr-pending-banner">
                <span>
                    <?php
                    $proposed_label = $answer_labels[ $state['proposed_answer'] ] ?? $state['proposed_answer'];
                    printf(
                        /* translators: %s: proposed answer label */
                        esc_html__( 'Client proposes: %s', 'gapnext-wp' ),
                        '<strong>' . esc_html( $proposed_label ) . '</strong>'
                    );
                    ?>
                </span>
                <button class="gnr-btn-approve"><?php esc_html_e( 'Approve', 'gapnext-wp' ); ?></button>
                <button class="gnr-btn-reject"><?php esc_html_e( 'Reject', 'gapnext-wp' ); ?></button>
            </div>
            <?php endif; ?>

            <div class="gnr-item-fields">
                <div class="gnr-item-field gnr-item-action-field">
                    <label><?php esc_html_e( 'Corrective Action', 'gapnext-wp' ); ?></label>
                    <textarea class="gnr-field-save" data-field="action"
                              placeholder="<?php esc_attr_e( 'Describe the corrective action needed...', 'gapnext-wp' ); ?>"
                    ><?php echo esc_textarea( $state['corrective_action'] ); ?></textarea>
                </div>
                <div class="gnr-item-field">
                    <label><?php esc_html_e( 'Priority', 'gapnext-wp' ); ?></label>
                    <select class="gnr-field-save" data-field="priority">
                        <option value="high"   <?php selected( $state['priority'], 'high' ); ?>><?php esc_html_e( 'High', 'gapnext-wp' ); ?></option>
                        <option value="medium" <?php selected( $state['priority'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'gapnext-wp' ); ?></option>
                        <option value="low"    <?php selected( $state['priority'], 'low' ); ?>><?php esc_html_e( 'Low', 'gapnext-wp' ); ?></option>
                    </select>
                </div>
                <div class="gnr-item-field">
                    <label><?php esc_html_e( 'Deadline', 'gapnext-wp' ); ?></label>
                    <input type="date" class="gnr-field-save" data-field="deadline"
                           value="<?php echo esc_attr( $state['deadline'] ?? '' ); ?>">
                </div>
                <div class="gnr-item-field">
                    <label><?php esc_html_e( 'Responsible', 'gapnext-wp' ); ?></label>
                    <input type="text" class="gnr-field-save" data-field="responsible"
                           value="<?php echo esc_attr( $state['responsible'] ); ?>"
                           placeholder="<?php esc_attr_e( 'Person name', 'gapnext-wp' ); ?>">
                </div>
            </div>

            <!-- Status selector (consultant can override) -->
            <div style="margin-top:10px">
                <label style="font-size:11px;color:#646970;font-weight:600"><?php esc_html_e( 'Status', 'gapnext-wp' ); ?></label>
                <select class="gnr-field-save" data-field="status" style="width:auto;margin-left:6px">
                    <?php foreach ( GapNext_Remediation_Log::STATUSES as $s ) : ?>
                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $state['status'], $s ); ?>>
                        <?php echo esc_html( $status_labels[ $s ] ?? $s ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Comment input -->
            <div style="margin-top:10px;display:flex;gap:6px">
                <input type="text" class="gnr-comment-input" style="flex:1;font-size:12px"
                       placeholder="<?php esc_attr_e( 'Add a comment...', 'gapnext-wp' ); ?>">
                <button class="button button-small gnr-add-comment-btn"><?php esc_html_e( 'Post', 'gapnext-wp' ); ?></button>
            </div>

            <!-- Event timeline -->
            <?php if ( ! empty( $events ) ) : ?>
            <div class="gnr-timeline">
                <button class="gnr-timeline-toggle"><?php esc_html_e( 'Show activity', 'gapnext-wp' ); ?> (<?php echo count( $events ); ?>)</button>
                <ul class="gnr-timeline-list" style="display:none">
                    <?php foreach ( array_reverse( $events ) as $evt ) :
                        $evt_date = date_i18n( 'd/m/Y H:i', strtotime( $evt->created_at ) );
                        $actor = $evt->user_role === 'consultant' ? __( 'Consultant', 'gapnext-wp' ) : __( 'Client', 'gapnext-wp' );
                        $desc = GapNext_Audit_Manager::describe_event( $evt, $lang );
                    ?>
                    <li>
                        <span class="gnr-timeline-date"><?php echo esc_html( $evt_date ); ?></span>
                        <span class="gnr-timeline-actor"><?php echo esc_html( $actor ); ?>:</span>
                        <?php echo esc_html( $desc ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>
    <?php
    endforeach;
endif;
