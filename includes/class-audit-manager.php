<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Audit_Manager {

    public function __construct() {
        add_action( 'wp_ajax_gapnext_ai_generate', [ $this, 'handle_ai_generate' ] );
    }

    public static function render_audit_links_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $created = false;

        if ( isset( $_POST['gapnext_create_audit'] ) ) {
            check_admin_referer( 'gapnext_create_audit', 'gapnext_audit_nonce' );
            $created = self::create_audit();
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['uuid'] ) ) {
            check_admin_referer( 'gapnext_delete_' . sanitize_text_field( $_GET['uuid'] ) );
            self::delete_audit( sanitize_text_field( $_GET['uuid'] ) );
        }

        $standards      = GapNext_Standard_Registry::get_available_standards();
        $audits         = self::get_all_audits();
        $default_lang   = get_option( 'gapnext_default_language', 'it' );
        $default_access = get_option( 'gapnext_default_access_mode', 'public' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Audit Links', 'gapnext-wp' ); ?></h1>

            <?php if ( $created ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Audit link created successfully.', 'gapnext-wp' ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Create New Audit Link', 'gapnext-wp' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'gapnext_create_audit', 'gapnext_audit_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="audit_title"><?php esc_html_e( 'Title', 'gapnext-wp' ); ?></label></th>
                        <td><input type="text" name="audit_title" id="audit_title" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="audit_standard"><?php esc_html_e( 'Standard', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <select name="audit_standard" id="audit_standard">
                                <?php foreach ( $standards as $id => $names ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>">
                                        <?php echo esc_html( $names['name_it'] . ' / ' . $names['name_en'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="audit_language"><?php esc_html_e( 'Language', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <select name="audit_language" id="audit_language">
                                <option value="it" <?php selected( $default_lang, 'it' ); ?>>Italiano</option>
                                <option value="en" <?php selected( $default_lang, 'en' ); ?>>English</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="audit_access"><?php esc_html_e( 'Access Mode', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <select name="audit_access" id="audit_access">
                                <option value="public" <?php selected( $default_access, 'public' ); ?>><?php esc_html_e( 'Public', 'gapnext-wp' ); ?></option>
                                <option value="login_required" <?php selected( $default_access, 'login_required' ); ?>><?php esc_html_e( 'Login Required', 'gapnext-wp' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <input type="hidden" name="gapnext_create_audit" value="1">
                <?php submit_button( __( 'Create Audit Link', 'gapnext-wp' ) ); ?>
            </form>

            <h2><?php esc_html_e( 'Existing Audit Links', 'gapnext-wp' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Title', 'gapnext-wp' ); ?></th>
                        <th><?php esc_html_e( 'Standard', 'gapnext-wp' ); ?></th>
                        <th><?php esc_html_e( 'Language', 'gapnext-wp' ); ?></th>
                        <th><?php esc_html_e( 'Access', 'gapnext-wp' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'gapnext-wp' ); ?></th>
                        <th><?php esc_html_e( 'Link', 'gapnext-wp' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'gapnext-wp' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $audits ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No audit links yet.', 'gapnext-wp' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $audits as $audit ) : ?>
                        <?php
                        $checklist_pid = (int) get_option( 'gapnext_checklist_page_id', 0 );
                        $base_url      = $checklist_pid ? get_permalink( $checklist_pid ) : get_home_url();
                        $link          = add_query_arg( 'audit', $audit->uuid, $base_url );
                        $delete_url = wp_nonce_url(
                            add_query_arg( [ 'page' => 'gapnext-wp', 'action' => 'delete', 'uuid' => $audit->uuid ], admin_url( 'admin.php' ) ),
                            'gapnext_delete_' . $audit->uuid
                        );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $audit->title ); ?></td>
                            <td><?php echo esc_html( $audit->standard_id ); ?></td>
                            <td><?php echo esc_html( strtoupper( $audit->language ) ); ?></td>
                            <td><?php echo esc_html( $audit->access_mode ); ?></td>
                            <td><?php echo esc_html( $audit->created_at ); ?></td>
                            <td>
                                <input type="text" value="<?php echo esc_url( $link ); ?>" readonly style="width:100%"
                                    onclick="this.select();document.execCommand('copy');" title="<?php esc_attr_e( 'Click to copy', 'gapnext-wp' ); ?>">
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $delete_url ); ?>"
                                   onclick="return confirm('<?php esc_attr_e( 'Delete this audit link?', 'gapnext-wp' ); ?>')"
                                   class="button button-small button-link-delete">
                                    <?php esc_html_e( 'Delete', 'gapnext-wp' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function render_submissions_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Handle delete submission
        $deleted = false;
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_submission' && isset( $_GET['sub_id'] ) ) {
            $sub_id = (int) $_GET['sub_id'];
            check_admin_referer( 'gapnext_delete_sub_' . $sub_id );
            self::delete_submission( $sub_id );
            $deleted = true;
        }

        // Inline view
        if ( isset( $_GET['view_sub'] ) ) {
            self::render_submission_view( (int) $_GET['view_sub'] );
            return;
        }

        // Enqueue AI button script if AI is configured (single instance — reused in row loop)
        $ai_client = new GapNext_AI_Client();
        if ( $ai_client->is_configured() ) {
            wp_enqueue_script(
                'gapnext-admin-ai',
                GAPNEXT_WP_URL . 'assets/gapnext-admin-ai.js',
                [ 'jquery' ],
                GAPNEXT_WP_VERSION,
                true
            );
            wp_localize_script( 'gapnext-admin-ai', 'gapnextAI', [
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'gapnext_ai_generate' ),
                'generateText'     => __( 'Generate AI Report', 'gapnext-wp' ),
                'generatingText'   => __( 'Generating\u2026', 'gapnext-wp' ),
                'downloadText'     => __( '\u2b07 Download AI Report', 'gapnext-wp' ),
                'errorText'        => __( 'Generation failed. Try again.', 'gapnext-wp' ),
                'networkErrorText' => __( 'Network error. Check connection.', 'gapnext-wp' ),
            ] );
        }

        // ── Filter & sort params ──────────────────────────────────────────
        $f_standard = sanitize_text_field( wp_unslash( $_GET['std_filter']     ?? '' ) );
        $f_company  = sanitize_text_field( wp_unslash( $_GET['company_filter'] ?? '' ) );
        $f_order    = ( isset( $_GET['order'] ) && $_GET['order'] === 'asc' ) ? 'asc' : 'desc';

        // Toggle direction for next click on the Date header
        $next_order = $f_order === 'desc' ? 'asc' : 'desc';
        $order_icon = $f_order === 'desc' ? ' ▼' : ' ▲';

        // Base URL for sorting/filtering (preserves page)
        $base_list_url = add_query_arg( 'page', 'gapnext-submissions', admin_url( 'admin.php' ) );

        // Date sort URL — preserves current filters, toggles order
        $date_sort_url = add_query_arg( [
            'std_filter'     => $f_standard,
            'company_filter' => $f_company,
            'order'          => $next_order,
        ], $base_list_url );

        // Reset URL
        $reset_url = $base_list_url;

        // Options for dropdowns (distinct values from all submitted rows)
        $all_submissions  = self::get_all_submissions();
        $all_standards    = array_unique( array_column( $all_submissions, 'standard_id' ) );
        $all_companies    = array_unique( array_column( $all_submissions, 'company_name' ) );
        sort( $all_standards );
        sort( $all_companies );

        // Filtered + sorted result set
        $submissions = self::get_filtered_submissions( $f_standard, $f_company, $f_order );
        $total_count    = count( $all_submissions );
        $filtered_count = count( $submissions );
        $is_filtered    = $f_standard !== '' || $f_company !== '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Submissions', 'gapnext-wp' ); ?></h1>

            <?php if ( $deleted ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Submission deleted.', 'gapnext-wp' ); ?></p></div>
            <?php endif; ?>

            <!-- ── Filter bar ──────────────────────────────────────────── -->
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
                  style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin:16px 0 12px;padding:14px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-width:900px">
                <input type="hidden" name="page"  value="gapnext-submissions">
                <input type="hidden" name="order" value="<?php echo esc_attr( $f_order ); ?>">

                <div style="display:flex;flex-direction:column;gap:4px">
                    <label style="font-size:12px;font-weight:600;color:#1d2327"><?php esc_html_e( 'Standard', 'gapnext-wp' ); ?></label>
                    <select name="std_filter" style="min-width:160px">
                        <option value=""><?php esc_html_e( '— All standards —', 'gapnext-wp' ); ?></option>
                        <?php foreach ( $all_standards as $std ) : ?>
                            <option value="<?php echo esc_attr( $std ); ?>" <?php selected( $f_standard, $std ); ?>>
                                <?php echo esc_html( $std ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;flex-direction:column;gap:4px">
                    <label style="font-size:12px;font-weight:600;color:#1d2327"><?php esc_html_e( 'Company', 'gapnext-wp' ); ?></label>
                    <select name="company_filter" style="min-width:200px">
                        <option value=""><?php esc_html_e( '— All companies —', 'gapnext-wp' ); ?></option>
                        <?php foreach ( $all_companies as $co ) : ?>
                            <option value="<?php echo esc_attr( $co ); ?>" <?php selected( $f_company, $co ); ?>>
                                <?php echo esc_html( $co ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;gap:8px;align-items:flex-end">
                    <?php submit_button( __( 'Apply filters', 'gapnext-wp' ), 'secondary', '', false, [ 'style' => 'margin:0' ] ); ?>
                    <?php if ( $is_filtered ) : ?>
                        <a href="<?php echo esc_url( $reset_url ); ?>" class="button" style="margin:0"><?php esc_html_e( 'Reset', 'gapnext-wp' ); ?></a>
                    <?php endif; ?>
                </div>
            </form>
            <!-- ─────────────────────────────────────────────────────────── -->

            <!-- Result count -->
            <p style="margin:0 0 8px;font-size:13px;color:#646970">
                <?php if ( $is_filtered ) : ?>
                    <?php printf(
                        /* translators: 1: filtered count, 2: total count */
                        esc_html__( 'Showing %1$d of %2$d submissions', 'gapnext-wp' ),
                        $filtered_count,
                        $total_count
                    ); ?>
                    <?php if ( $f_standard ) : ?>
                        &nbsp;<span style="display:inline-flex;align-items:center;gap:4px;background:#dbeafe;color:#1e40af;border-radius:12px;padding:2px 10px;font-size:12px">
                            <?php echo esc_html( $f_standard ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $f_company ) : ?>
                        &nbsp;<span style="display:inline-flex;align-items:center;gap:4px;background:#d1fae5;color:#065f46;border-radius:12px;padding:2px 10px;font-size:12px">
                            <?php echo esc_html( $f_company ); ?>
                        </span>
                    <?php endif; ?>
                <?php else : ?>
                    <?php printf( esc_html__( '%d submissions', 'gapnext-wp' ), $total_count ); ?>
                <?php endif; ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:300px"><?php esc_html_e( 'Company', 'gapnext-wp' ); ?></th>
                        <th style="width:300px"><?php esc_html_e( 'Standard', 'gapnext-wp' ); ?></th>
                        <th style="width:50px"><?php esc_html_e( 'Lang', 'gapnext-wp' ); ?></th>
                        <th style="width:80px"><?php esc_html_e( 'Score', 'gapnext-wp' ); ?></th>
                        <th style="width:170px">
                            <a href="<?php echo esc_url( $date_sort_url ); ?>"
                               style="color:inherit;text-decoration:none;white-space:nowrap"
                               title="<?php esc_attr_e( 'Sort by date', 'gapnext-wp' ); ?>">
                                <?php esc_html_e( 'Date', 'gapnext-wp' ); ?><?php echo esc_html( $order_icon ); ?>
                            </a>
                        </th>
                        <th style="width:340px"><?php esc_html_e( 'Actions', 'gapnext-wp' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $submissions ) ) : ?>
                    <tr><td colspan="6" style="color:#646970;font-style:italic">
                        <?php esc_html_e( 'No submissions match the selected filters.', 'gapnext-wp' ); ?>
                    </td></tr>
                <?php else : ?>
                    <?php foreach ( $submissions as $sub ) : ?>
                        <?php
                        $nonce       = wp_create_nonce( 'gapnext_export_' . $sub->id );
                        $base_export = add_query_arg(
                            [ 'action' => 'gapnext_export', 'sub_id' => $sub->id, '_wpnonce' => $nonce ],
                            admin_url( 'admin-post.php' )
                        );
                        $view_url   = add_query_arg( [ 'page' => 'gapnext-submissions', 'view_sub' => $sub->id ], admin_url( 'admin.php' ) );
                        $delete_url = wp_nonce_url(
                            add_query_arg( [ 'page' => 'gapnext-submissions', 'action' => 'delete_submission', 'sub_id' => $sub->id ], admin_url( 'admin.php' ) ),
                            'gapnext_delete_sub_' . $sub->id
                        );
                        $score_pct = round( $sub->score * 100, 1 );
                        if ( $sub->score >= 0.7 )      $score_color = '#166534';
                        elseif ( $sub->score >= 0.4 )  $score_color = '#92400e';
                        else                           $score_color = '#991b1b';
                        ?>
                        <tr>
                            <td><?php echo esc_html( $sub->company_name ); ?></td>
                            <td><?php echo esc_html( $sub->standard_id ); ?></td>
                            <td><?php echo esc_html( strtoupper( $sub->language ) ); ?></td>
                            <td><strong style="color:<?php echo esc_attr( $score_color ); ?>"><?php echo esc_html( $score_pct . '%' ); ?></strong></td>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $sub->submitted_at ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php esc_html_e( 'View', 'gapnext-wp' ); ?></a>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'pdf',  $base_export ) ); ?>" class="button button-small">PDF</a>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'csv',  $base_export ) ); ?>" class="button button-small">CSV</a>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'md',   $base_export ) ); ?>" class="button button-small">MD</a>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'json', $base_export ) ); ?>" class="button button-small">JSON</a>
                                <a href="<?php echo esc_url( $delete_url ); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php esc_attr_e( 'Delete this submission? This cannot be undone.', 'gapnext-wp' ); ?>')">
                                    <?php esc_html_e( 'Delete', 'gapnext-wp' ); ?>
                                </a>
                                <?php if ( $ai_client->is_configured() ) : ?>
                                    <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                                        <a href="<?php echo esc_url( $sub->ai_report_url ); ?>"
                                           target="_blank" class="button button-small button-primary">
                                            <?php esc_html_e( '&#x2B07; Download AI Report', 'gapnext-wp' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <button type="button" class="button button-small gapnext-ai-generate"
                                                data-id="<?php echo esc_attr( $sub->id ); ?>">
                                            <?php esc_html_e( 'Generate AI Report', 'gapnext-wp' ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private static function render_submission_view( $sub_id ) {
        $sub = self::get_submission( $sub_id );
        if ( ! $sub ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Submission not found.', 'gapnext-wp' ) . '</p></div>';
            return;
        }

        $answers    = json_decode( $sub->answers,        true ) ?: [];
        $evidence   = json_decode( $sub->evidence_paths, true ) ?: [];
        $standard   = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        $upload_dir = wp_upload_dir();
        $lang     = $sub->language;
        $score_pct = round( $sub->score * 100, 1 );

        // Compute stats
        $stats = self::calc_submission_stats( $answers, $standard );

        $nonce       = wp_create_nonce( 'gapnext_export_' . $sub->id );
        $base_export = add_query_arg(
            [ 'action' => 'gapnext_export', 'sub_id' => $sub->id, '_wpnonce' => $nonce ],
            admin_url( 'admin-post.php' )
        );
        $back_url   = add_query_arg( 'page', 'gapnext-submissions', admin_url( 'admin.php' ) );
        $delete_url = wp_nonce_url(
            add_query_arg( [ 'page' => 'gapnext-submissions', 'action' => 'delete_submission', 'sub_id' => $sub->id ], admin_url( 'admin.php' ) ),
            'gapnext_delete_sub_' . $sub->id
        );

        // Score color
        if ( $sub->score >= 0.7 )     { $score_bg = '#166534'; }
        elseif ( $sub->score >= 0.4 ) { $score_bg = '#92400e'; }
        else                          { $score_bg = '#991b1b'; }

        // Labels (bilingual)
        $l_conforme    = $lang === 'it' ? 'Conforme'       : 'Compliant';
        $l_parziale    = $lang === 'it' ? 'Parzialmente'   : 'Partial';
        $l_non_conf    = $lang === 'it' ? 'Non-Conforme'   : 'Non-Compliant';
        $l_na          = $lang === 'it' ? 'Non Appl.'      : 'N/A';
        $l_unanswered  = $lang === 'it' ? 'Non risposto'   : 'Unanswered';
        $l_answered    = $lang === 'it' ? 'Risposte fornite' : 'Answers provided';
        $l_applicable  = $lang === 'it' ? 'Applicabili'    : 'Applicable';
        $l_total       = $lang === 'it' ? 'Totale requisiti' : 'Total requirements';

        // Projected score = Conformities / (Total questions − N/A)
        $denominator = $stats['total'] - $stats['na'];
        $proj_score  = $denominator > 0
            ? round( $stats['compliant'] / $denominator * 100, 1 )
            : 0;
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Submission', 'gapnext-wp' ); ?>: <?php echo esc_html( $sub->company_name ); ?>
                <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">← <?php esc_html_e( 'Back to list', 'gapnext-wp' ); ?></a>
                <a href="<?php echo esc_url( $delete_url ); ?>"
                   class="page-title-action"
                   style="color:#b91c1c;border-color:#b91c1c"
                   onclick="return confirm('<?php esc_attr_e( 'Delete this submission? This cannot be undone.', 'gapnext-wp' ); ?>')">
                    🗑 <?php esc_html_e( 'Delete submission', 'gapnext-wp' ); ?>
                </a>
            </h1>

            <!-- Export buttons -->
            <p>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'pdf',  $base_export ) ); ?>" class="button button-primary">⬇ PDF</a>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'csv',  $base_export ) ); ?>" class="button">⬇ CSV</a>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'md',   $base_export ) ); ?>" class="button">⬇ MD</a>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'json', $base_export ) ); ?>" class="button">⬇ JSON</a>
            </p>

            <?php
            // Share Results section — only shown when a results page is configured
            $results_pid = (int) get_option( 'gapnext_results_page_id', 0 );
            if ( $results_pid ) :
                $results_url = add_query_arg( [ 'sub' => $sub->id, 'audit' => $sub->audit_uuid ], get_permalink( $results_pid ) );
            ?>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:4px;padding:16px 20px;margin:0 0 24px;max-width:800px">
                <h3 style="margin:0 0 10px;font-size:14px;color:#0c4a6e">
                    🔗 <?php esc_html_e( 'Share Results Page', 'gapnext-wp' ); ?>
                </h3>
                <p style="margin:0 0 10px;font-size:13px;color:#475569">
                    <?php esc_html_e( 'Anyone with this link can view the full results online (no login required):', 'gapnext-wp' ); ?>
                </p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" id="gapnext-share-url"
                           value="<?php echo esc_url( $results_url ); ?>"
                           readonly
                           style="flex:1;min-width:300px;padding:7px 10px;font-size:13px;border:1px solid #cbd5e1;border-radius:4px;background:#fff;font-family:monospace">
                    <button type="button" class="button"
                            onclick="var el=document.getElementById('gapnext-share-url');el.select();document.execCommand('copy');this.textContent='<?php echo esc_js( __( 'Copied!', 'gapnext-wp' ) ); ?>';var self=this;setTimeout(function(){self.textContent='<?php echo esc_js( __( 'Copy link', 'gapnext-wp' ) ); ?>';},2000);">
                        <?php esc_html_e( 'Copy link', 'gapnext-wp' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $results_url ); ?>" target="_blank" class="button">
                        <?php esc_html_e( 'Open', 'gapnext-wp' ); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- ============================================================
                 SCORE CARD + STATS GRID
                 ============================================================ -->
            <div style="display:flex;gap:20px;flex-wrap:wrap;margin:20px 0 28px">

                <!-- Big score badge -->
                <div style="background:<?php echo esc_attr( $score_bg ); ?>;color:#fff;border-radius:12px;padding:24px 32px;text-align:center;min-width:160px">
                    <div style="font-size:42px;font-weight:700;line-height:1"><?php echo esc_html( $score_pct ); ?>%</div>
                    <div style="font-size:13px;margin-top:6px;opacity:.85"><?php echo $lang === 'it' ? 'Conformità' : 'Compliance'; ?></div>
                    <div style="font-size:11px;margin-top:4px;opacity:.7"><?php echo esc_html( $sub->standard_id ); ?></div>
                </div>

                <!-- Stats grid -->
                <div style="flex:1;min-width:340px">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">

                        <?php
                        $boxes = [
                            [ $l_total,      $stats['total'],      '#1e3a8a', '#eff6ff' ],
                            [ $l_answered,   $stats['answered'],   '#0f766e', '#f0fdfa' ],
                            [ $l_applicable, $stats['applicable'], '#475569', '#f8fafc' ],
                            [ $l_conforme,   $stats['compliant'],  '#166534', '#f0fdf4' ],
                            [ $l_parziale,   $stats['partial'],    '#92400e', '#fffbeb' ],
                            [ $l_non_conf,   $stats['non_comply'], '#991b1b', '#fef2f2' ],
                        ];
                        foreach ( $boxes as $box ) :
                            list( $box_label, $box_val, $box_color, $box_bg ) = $box;
                        ?>
                        <div style="background:<?php echo esc_attr( $box_bg ); ?>;border-left:4px solid <?php echo esc_attr( $box_color ); ?>;border-radius:8px;padding:12px 14px">
                            <div style="font-size:26px;font-weight:700;color:<?php echo esc_attr( $box_color ); ?>;line-height:1"><?php echo esc_html( $box_val ); ?></div>
                            <div style="font-size:11px;color:#64748b;margin-top:4px"><?php echo esc_html( $box_label ); ?></div>
                        </div>
                        <?php endforeach; ?>

                    </div>

                    <!-- N/A + Unanswered row -->
                    <div style="display:flex;gap:10px;margin-top:10px">
                        <div style="flex:1;background:#f9fafb;border-left:4px solid #9ca3af;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center">
                            <span style="font-size:12px;color:#374151"><?php echo esc_html( $l_na ); ?></span>
                            <strong style="color:#6b7280"><?php echo esc_html( $stats['na'] ); ?></strong>
                        </div>
                        <div style="flex:1;background:#f9fafb;border-left:4px solid #d1d5db;border-radius:8px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center">
                            <span style="font-size:12px;color:#374151"><?php echo esc_html( $l_unanswered ); ?></span>
                            <strong style="color:#9ca3af"><?php echo esc_html( $stats['unanswered'] ); ?></strong>
                        </div>
                    </div>

                    <!-- Distribution bar -->
                    <?php if ( $stats['total'] > 0 ) :
                        $bar_total = $stats['total'];
                        $bars = [
                            [ $stats['compliant'],  '#16a34a' ],
                            [ $stats['partial'],    '#d97706' ],
                            [ $stats['non_comply'], '#dc2626' ],
                            [ $stats['na'],         '#9ca3af' ],
                            [ $stats['unanswered'], '#e5e7eb' ],
                        ];
                    ?>
                    <div style="margin-top:12px">
                        <div style="height:10px;border-radius:6px;overflow:hidden;display:flex;background:#e5e7eb">
                            <?php foreach ( $bars as $b ) :
                                $w = $bar_total > 0 ? round( $b[0] / $bar_total * 100, 1 ) : 0;
                                if ( $w > 0 ) :
                            ?>
                            <div style="width:<?php echo esc_attr( $w ); ?>%;background:<?php echo esc_attr( $b[1] ); ?>;transition:width .3s"></div>
                            <?php endif; endforeach; ?>
                        </div>
                        <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:6px;font-size:11px;color:#6b7280">
                            <span><span style="color:#16a34a">●</span> <?php echo esc_html( $l_conforme ); ?> <?php echo esc_html( $stats['compliant'] ); ?></span>
                            <span><span style="color:#d97706">●</span> <?php echo esc_html( $l_parziale ); ?> <?php echo esc_html( $stats['partial'] ); ?></span>
                            <span><span style="color:#dc2626">●</span> <?php echo esc_html( $l_non_conf ); ?> <?php echo esc_html( $stats['non_comply'] ); ?></span>
                            <span><span style="color:#9ca3af">●</span> <?php echo esc_html( $l_na ); ?> <?php echo esc_html( $stats['na'] ); ?></span>
                            <?php if ( $stats['unanswered'] > 0 ) : ?>
                            <span><span style="color:#d1d5db">●</span> <?php echo esc_html( $l_unanswered ); ?> <?php echo esc_html( $stats['unanswered'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ============================================================
                 COMPANY / CONTACT DETAILS
                 ============================================================ -->
            <h2><?php esc_html_e( 'Details', 'gapnext-wp' ); ?></h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;max-width:860px">

                <!-- Company -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php esc_html_e( 'Company', 'gapnext-wp' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->company_name ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Address', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->company_address ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'VAT', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->company_vat ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Sector', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->company_sector ); ?></td></tr>
                    </tbody>
                </table>

                <!-- Standard -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php esc_html_e( 'Audit', 'gapnext-wp' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php esc_html_e( 'Standard', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->standard_id ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Language', 'gapnext-wp' ); ?></th><td><?php echo esc_html( strtoupper( $lang ) ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Date', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->submitted_at ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Status', 'gapnext-wp' ); ?></th><td><?php echo esc_html( ucfirst( $sub->status ) ); ?></td></tr>
                    </tbody>
                </table>

                <!-- Internal contact -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php esc_html_e( 'Internal Contact', 'gapnext-wp' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->contact_name ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Role', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->contact_role ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Email', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->contact_email ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Phone', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->contact_phone ); ?></td></tr>
                    </tbody>
                </table>

                <!-- Consultant -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php esc_html_e( 'Consultant', 'gapnext-wp' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->consultant_name ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Company', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->consultant_company ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Email', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->consultant_email ); ?></td></tr>
                        <tr><th><?php esc_html_e( 'Phone', 'gapnext-wp' ); ?></th><td><?php echo esc_html( $sub->consultant_phone ); ?></td></tr>
                    </tbody>
                </table>

            </div>

            <!-- ============================================================
                 CHECKLIST RESULTS
                 ============================================================ -->
            <?php if ( $standard ) : ?>
                <h2 style="margin-top:8px"><?php esc_html_e( 'Checklist Results', 'gapnext-wp' ); ?></h2>
                <table class="wp-list-table widefat fixed striped" style="table-layout:auto">
                    <thead>
                        <tr>
                            <th style="width:70px"><?php esc_html_e( 'Ref.', 'gapnext-wp' ); ?></th>
                            <th><?php esc_html_e( 'Requirement', 'gapnext-wp' ); ?></th>
                            <th style="width:130px"><?php esc_html_e( 'Answer', 'gapnext-wp' ); ?></th>
                            <th style="width:25%"><?php esc_html_e( 'Notes', 'gapnext-wp' ); ?></th>
                            <th style="width:130px"><?php esc_html_e( 'Evidence', 'gapnext-wp' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $standard['clauses'] as $clause ) : ?>
                        <?php if ( (int) $clause['level'] === 1 ) : ?>
                            <tr style="background:#1e3a8a">
                                <td colspan="5" style="color:#fff;font-weight:600;padding:8px 10px">
                                    <?php echo esc_html( $clause['clause_ref'] . ' — ' . $clause['title'] ); ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php
                            $ref  = $clause['reference'];
                            $ans  = $answers[ $ref ] ?? null;
                            $val  = $ans['value'] ?? null;
                            $note = $ans['note'] ?? '';

                            if ( $val === 1.0 || $val === 1 ) {
                                $label    = $lang === 'it' ? '✅ Conforme'     : '✅ Compliant';
                                $ans_bg   = '#f0fdf4';
                                $ans_color= '#166534';
                            } elseif ( $val === 0.5 ) {
                                $label    = $lang === 'it' ? '⚠ Parzialmente' : '⚠ Partial';
                                $ans_bg   = '#fffbeb';
                                $ans_color= '#92400e';
                            } elseif ( $val === 0.0 || $val === 0 ) {
                                $label    = $lang === 'it' ? '❌ Non-Conforme' : '❌ Non-Compliant';
                                $ans_bg   = '#fef2f2';
                                $ans_color= '#991b1b';
                            } elseif ( $val === 'na' ) {
                                $label    = $lang === 'it' ? '— Non Appl.'    : '— N/A';
                                $ans_bg   = '#f9fafb';
                                $ans_color= '#6b7280';
                            } else {
                                $label    = '—';
                                $ans_bg   = '';
                                $ans_color= '#9ca3af';
                            }

                            $files = $evidence[ $ref ] ?? [];
                            ?>
                            <tr>
                                <td style="font-weight:600;color:#374151"><?php echo esc_html( $clause['clause_ref'] ); ?></td>
                                <td><?php echo esc_html( $clause['title'] ); ?></td>
                                <td style="background:<?php echo esc_attr( $ans_bg ); ?>;color:<?php echo esc_attr( $ans_color ); ?>;font-weight:600;font-size:12px;text-align:center">
                                    <?php echo esc_html( $label ); ?>
                                </td>
                                <td style="font-size:12px"><?php echo nl2br( esc_html( $note ) ); ?></td>
                                <td style="font-size:11px">
                                    <?php if ( empty( $files ) ) : ?>
                                        <span style="color:#9ca3af">—</span>
                                    <?php else : ?>
                                        <?php foreach ( $files as $f ) :
                                            $ev_url = str_replace(
                                                wp_normalize_path( $upload_dir['basedir'] ),
                                                $upload_dir['baseurl'],
                                                wp_normalize_path( $f )
                                            );
                                        ?>
                                            <div>
                                                <a href="<?php echo esc_url( $ev_url ); ?>" target="_blank" rel="noopener noreferrer"
                                                   style="color:#2563eb;text-decoration:none;word-break:break-all"
                                                   onmouseover="this.style.textDecoration='underline'"
                                                   onmouseout="this.style.textDecoration='none'">
                                                    <?php echo esc_html( basename( $f ) ); ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function delete_submission( $sub_id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'gapnext_submissions', [ 'id' => (int) $sub_id ], [ '%d' ] );
    }

    /**
     * Compute per-status counts from stored answers + standard clause list.
     * Mirrors the logic in GapNext_Export_Pdf::calc_stats().
     */
    private static function calc_submission_stats( $answers, $standard ) {
        $total = $compliant = $partial = $non_comply = $na = $unanswered = 0;

        if ( $standard ) {
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] === 1 ) continue;
                $total++;
                $ref = $clause['reference'];
                $val = ( $answers[ $ref ] ?? [] )['value'] ?? null;

                if ( $val === 'na' )                    $na++;
                elseif ( $val === 1.0 || $val === 1 )   $compliant++;
                elseif ( $val === 0.5 )                 $partial++;
                elseif ( $val === 0.0 || $val === 0 )   $non_comply++;
                else                                    $unanswered++;
            }
        }

        return [
            'total'      => $total,
            'answered'   => $total - $unanswered,
            'applicable' => $total - $na - $unanswered,
            'compliant'  => $compliant,
            'partial'    => $partial,
            'non_comply' => $non_comply,
            'na'         => $na,
            'unanswered' => $unanswered,
        ];
    }

    private static function create_audit() {
        global $wpdb;
        $lang   = sanitize_text_field( $_POST['audit_language'] ?? 'it' );
        $access = sanitize_text_field( $_POST['audit_access'] ?? 'public' );
        $wpdb->insert(
            $wpdb->prefix . 'gapnext_audits',
            [
                'uuid'        => wp_generate_uuid4(),
                'title'       => sanitize_text_field( $_POST['audit_title'] ?? '' ),
                'standard_id' => sanitize_text_field( $_POST['audit_standard'] ?? '' ),
                'language'    => in_array( $lang, [ 'it', 'en' ], true ) ? $lang : 'it',
                'access_mode' => in_array( $access, [ 'public', 'login_required' ], true ) ? $access : 'public',
                'created_by'  => get_current_user_id(),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d' ]
        );
        return (bool) $wpdb->insert_id;
    }

    private static function delete_audit( $uuid ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'gapnext_audits', [ 'uuid' => $uuid ], [ '%s' ] );
    }

    public static function get_all_audits() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gapnext_audits ORDER BY created_at DESC" );
    }

    public static function get_all_submissions() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gapnext_submissions WHERE status = 'submitted' ORDER BY submitted_at DESC" );
    }

    public static function get_filtered_submissions( $standard = '', $company = '', $order = 'desc' ) {
        global $wpdb;
        $order     = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
        $where     = [ "status = 'submitted'" ];
        $values    = [];

        if ( $standard !== '' ) {
            $where[]  = 'standard_id = %s';
            $values[] = $standard;
        }
        if ( $company !== '' ) {
            $where[]  = 'company_name = %s';
            $values[] = $company;
        }

        $sql = "SELECT * FROM {$wpdb->prefix}gapnext_submissions WHERE " . implode( ' AND ', $where ) . " ORDER BY submitted_at {$order}";

        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results( $sql );
    }

    public static function get_submission( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gapnext_submissions WHERE id = %d", $id ) );
    }

    public static function get_audit_by_uuid( $uuid ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}gapnext_audits WHERE uuid = %s", $uuid ) );
    }

    /**
     * AJAX handler for "Generate AI Report" button.
     * Action: wp_ajax_gapnext_ai_generate
     */
    public function handle_ai_generate(): void {
        check_ajax_referer( 'gapnext_ai_generate', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $submission_id = intval( $_POST['submission_id'] ?? 0 );
        if ( $submission_id <= 0 ) {
            wp_send_json_error( [ 'message' => __( 'Invalid submission.', 'gapnext-wp' ) ] );
        }

        $result = ( new GapNext_AI_Report() )->generate_for_submission( $submission_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( $result ); // {download_url, uuid, file_size_kb}
    }
}
