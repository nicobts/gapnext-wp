<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Audit_Manager {

    public function __construct() {
        /* AI generate handler — disabled until AI pipeline is ready for production
        add_action( 'wp_ajax_gapnext_ai_generate', [ $this, 'handle_ai_generate' ] );
        */
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
                                <option value="demo" <?php selected( $default_access, 'demo' ); ?>><?php esc_html_e( 'Demo', 'gapnext-wp' ); ?></option>
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
                            <td>
                                <?php echo esc_html( ucfirst( $audit->access_mode ) ); ?>
                                <?php if ( $audit->access_mode === 'demo' ) : ?>
                                    <span style="display:inline-block;margin-left:4px;padding:1px 6px;background:#fef3c7;color:#92400e;border-radius:8px;font-size:10px;font-weight:600">Demo</span>
                                <?php endif; ?>
                            </td>
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

        /* AI button script — disabled until AI pipeline is ready for production
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
                'apiKey'           => get_option( 'gapnext_ai_api_key', '' ),
                'generateText'     => __( 'Generate AI Report', 'gapnext-wp' ),
                'generatingText'   => __( 'Generating\u2026', 'gapnext-wp' ),
                'downloadText'     => __( '\u2b07 Download AI Report', 'gapnext-wp' ),
                'errorText'        => __( 'Generation failed. Try again.', 'gapnext-wp' ),
                'networkErrorText' => __( 'Network error. Check connection.', 'gapnext-wp' ),
            ] );
        }
        */

        // ── Filter & sort params ──────────────────────────────────────────
        $f_standard = sanitize_text_field( wp_unslash( $_GET['std_filter']     ?? '' ) );
        $f_company  = sanitize_text_field( wp_unslash( $_GET['company_filter'] ?? '' ) );
        $f_status   = sanitize_key( $_GET['status_filter'] ?? '' );
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
            'status_filter'  => $f_status,
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
        $submissions = self::get_filtered_submissions( $f_standard, $f_company, $f_order, $f_status );
        $total_count    = count( $all_submissions );
        $filtered_count = count( $submissions );
        $is_filtered    = $f_standard !== '' || $f_company !== '' || $f_status !== '';
        $is_it          = ( strpos( get_locale(), 'it' ) === 0 );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $is_it ? 'Compilazioni' : 'Submissions' ); ?></h1>

            <?php if ( $deleted ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $is_it ? 'Compilazione eliminata.' : 'Submission deleted.' ); ?></p></div>
            <?php endif; ?>

            <!-- ── Filter bar ──────────────────────────────────────────── -->
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"
                  style="display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin:16px 0 12px;padding:14px 16px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;max-width:900px">
                <input type="hidden" name="page"  value="gapnext-submissions">
                <input type="hidden" name="order" value="<?php echo esc_attr( $f_order ); ?>">

                <div style="display:flex;flex-direction:column;gap:4px">
                    <label style="font-size:12px;font-weight:600;color:#1d2327"><?php echo esc_html( $is_it ? 'Standard' : 'Standard' ); ?></label>
                    <select name="std_filter" style="min-width:160px">
                        <option value=""><?php echo esc_html( $is_it ? '— Tutti gli standard —' : '— All standards —' ); ?></option>
                        <?php foreach ( $all_standards as $std ) : ?>
                            <option value="<?php echo esc_attr( $std ); ?>" <?php selected( $f_standard, $std ); ?>>
                                <?php echo esc_html( $std ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;flex-direction:column;gap:4px">
                    <label style="font-size:12px;font-weight:600;color:#1d2327"><?php echo esc_html( $is_it ? 'Azienda' : 'Company' ); ?></label>
                    <select name="company_filter" style="min-width:200px">
                        <option value=""><?php echo esc_html( $is_it ? '— Tutte le aziende —' : '— All companies —' ); ?></option>
                        <?php foreach ( $all_companies as $co ) : ?>
                            <option value="<?php echo esc_attr( $co ); ?>" <?php selected( $f_company, $co ); ?>>
                                <?php echo esc_html( $co ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;flex-direction:column;gap:4px">
                    <label style="font-size:12px;font-weight:600;color:#1d2327"><?php echo esc_html( $is_it ? 'Stato' : 'Status' ); ?></label>
                    <select name="status_filter" style="min-width:140px">
                        <option value=""><?php echo esc_html( $is_it ? '— Tutti gli stati —' : '— All statuses —' ); ?></option>
                        <option value="completed" <?php selected( $f_status, 'completed' ); ?>><?php echo esc_html( $is_it ? 'Completata' : 'Completed' ); ?></option>
                        <option value="draft" <?php selected( $f_status, 'draft' ); ?>><?php echo esc_html( $is_it ? 'Bozza' : 'Draft' ); ?></option>
                    </select>
                </div>

                <div style="display:flex;gap:8px;align-items:flex-end">
                    <?php submit_button( ( $is_it ? 'Applica filtri' : 'Apply filters' ), 'secondary', '', false, [ 'style' => 'margin:0' ] ); ?>
                    <?php if ( $is_filtered ) : ?>
                        <a href="<?php echo esc_url( $reset_url ); ?>" class="button" style="margin:0"><?php echo esc_html( $is_it ? 'Reimposta' : 'Reset' ); ?></a>
                    <?php endif; ?>
                </div>
            </form>
            <!-- ─────────────────────────────────────────────────────────── -->

            <!-- Result count -->
            <p style="margin:0 0 8px;font-size:13px;color:#646970">
                <?php if ( $is_filtered ) : ?>
                    <?php printf(
                        esc_html( $is_it ? '%1$d di %2$d compilazioni' : 'Showing %1$d of %2$d submissions' ),
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
                    <?php if ( $f_status ) : ?>
                        &nbsp;<span style="display:inline-flex;align-items:center;gap:4px;background:#fef3c7;color:#92400e;border-radius:12px;padding:2px 10px;font-size:12px">
                            <?php echo esc_html( $f_status === 'completed' ? ( $is_it ? 'Completata' : 'Completed' ) : ( $is_it ? 'Bozza' : 'Draft' ) ); ?>
                        </span>
                    <?php endif; ?>
                <?php else : ?>
                    <?php printf( esc_html( $is_it ? '%d compilazioni' : '%d submissions' ), $total_count ); ?>
                <?php endif; ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:200px"><?php echo esc_html( $is_it ? 'Azienda' : 'Company' ); ?></th>
                        <th style="width:160px"><?php echo esc_html( $is_it ? 'Standard' : 'Standard' ); ?></th>
                        <th style="width:50px"><?php echo esc_html( $is_it ? 'Lingua' : 'Lang' ); ?></th>
                        <th style="width:90px"><?php echo esc_html( $is_it ? 'Stato' : 'Status' ); ?></th>
                        <th style="width:80px"><?php echo esc_html( $is_it ? 'Punteggio' : 'Score' ); ?></th>
                        <th style="width:170px">
                            <a href="<?php echo esc_url( $date_sort_url ); ?>"
                               style="color:inherit;text-decoration:none;white-space:nowrap"
                               title="<?php echo esc_attr( $is_it ? 'Ordina per data' : 'Sort by date' ); ?>">
                                <?php echo esc_html( $is_it ? 'Data' : 'Date' ); ?><?php echo esc_html( $order_icon ); ?>
                            </a>
                        </th>
                        <th style="width:340px"><?php echo esc_html( $is_it ? 'Azioni' : 'Actions' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $submissions ) ) : ?>
                    <tr><td colspan="7" style="color:#646970;font-style:italic">
                        <?php echo esc_html( $is_it ? 'Nessuna compilazione corrisponde ai filtri selezionati.' : 'No submissions match the selected filters.' ); ?>
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
                        $is_draft  = $sub->status === 'draft';
                        $score_pct = round( $sub->score * 100, 1 );
                        if ( $is_draft )               $score_color = '#9ca3af';
                        elseif ( $sub->score >= 0.7 )  $score_color = '#166534';
                        elseif ( $sub->score >= 0.4 )  $score_color = '#92400e';
                        else                           $score_color = '#991b1b';

                        // Status badge
                        if ( $is_draft ) {
                            $status_label = $is_it ? 'Bozza' : 'Draft';
                            $status_bg    = '#fef3c7';
                            $status_color = '#92400e';
                        } elseif ( $sub->status === 'demo' ) {
                            $status_label = 'Demo';
                            $status_bg    = '#e0e7ff';
                            $status_color = '#3730a3';
                        } else {
                            $status_label = $is_it ? 'Completata' : 'Completed';
                            $status_bg    = '#dcfce7';
                            $status_color = '#166534';
                        }
                        ?>
                        <tr<?php echo $is_draft ? ' style="opacity:.85"' : ''; ?>>
                            <td>
                                <?php echo esc_html( $sub->company_name ?: '—' ); ?>
                            </td>
                            <td><?php echo esc_html( $sub->standard_id ); ?></td>
                            <td><?php echo esc_html( strtoupper( $sub->language ) ); ?></td>
                            <td>
                                <span style="display:inline-block;padding:2px 10px;background:<?php echo esc_attr( $status_bg ); ?>;color:<?php echo esc_attr( $status_color ); ?>;border-radius:10px;font-size:11px;font-weight:600"><?php echo esc_html( $status_label ); ?></span>
                            </td>
                            <td>
                                <?php if ( $is_draft ) : ?>
                                    <span style="color:#9ca3af">—</span>
                                <?php else : ?>
                                    <strong style="color:<?php echo esc_attr( $score_color ); ?>"><?php echo esc_html( $score_pct . '%' ); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $sub->submitted_at ) ) ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php echo esc_html( $is_it ? 'Dettagli' : 'View' ); ?></a>
                                <?php if ( ! $is_draft ) : ?>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'pdf',  $base_export ) ); ?>" class="button button-small">PDF</a>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'csv',  $base_export ) ); ?>" class="button button-small">CSV</a>
                                <a href="<?php echo esc_url( add_query_arg( 'format', 'md',   $base_export ) ); ?>" class="button button-small">MD</a>
                                <?php endif; ?>
                                <a href="<?php echo esc_url( $delete_url ); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php echo esc_attr( $is_it ? 'Eliminare questa compilazione? L\'azione non può essere annullata.' : 'Delete this submission? This cannot be undone.' ); ?>')">
                                    <?php echo esc_html( $is_it ? 'Elimina' : 'Delete' ); ?>
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

    private static function render_submission_view( $sub_id ) {
        $sub = self::get_submission( $sub_id );
        if ( ! $sub ) {
            echo '<div class="wrap"><p>' . esc_html__( 'Submission not found.', 'gapnext-wp' ) . '</p></div>';
            return;
        }

        /* AI script for the view page — disabled until AI pipeline is ready for production
        $ai_client_render = new GapNext_AI_Client();
        if ( $ai_client_render->is_configured() ) {
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
                'apiKey'           => get_option( 'gapnext_ai_api_key', '' ),
                'submissionId'     => isset( $_GET['view_sub'] ) ? (int) $_GET['view_sub'] : 0,
                'generateText'     => __( 'Generate AI Report', 'gapnext-wp' ),
                'generatingText'   => __( 'Generating\u2026', 'gapnext-wp' ),
                'downloadText'     => __( '\u2b07 Download AI Report', 'gapnext-wp' ),
                'errorText'        => __( 'Generation failed. Try again.', 'gapnext-wp' ),
                'networkErrorText' => __( 'Network error. Check connection.', 'gapnext-wp' ),
            ] );
        }
        */

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
        $is_it = ( strpos( get_locale(), 'it' ) === 0 );
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html( $is_it ? 'Compilazione' : 'Submission' ); ?>: <?php echo esc_html( $sub->company_name ); ?>
                <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">← <?php echo esc_html( $is_it ? 'Torna alla lista' : 'Back to list' ); ?></a>
                <a href="<?php echo esc_url( $delete_url ); ?>"
                   class="page-title-action"
                   style="color:#b91c1c;border-color:#b91c1c"
                   onclick="return confirm('<?php echo esc_attr( $is_it ? 'Eliminare questa compilazione? L\'azione non può essere annullata.' : 'Delete this submission? This cannot be undone.' ); ?>')">
                    🗑 <?php echo esc_html( $is_it ? 'Elimina compilazione' : 'Delete submission' ); ?>
                </a>
            </h1>

            <!-- Status + Export buttons -->
            <?php
            $is_draft = $sub->status === 'draft';
            if ( $is_draft ) {
                $detail_status_label = $is_it ? 'Bozza' : 'Draft';
                $detail_status_bg    = '#fef3c7';
                $detail_status_color = '#92400e';
            } elseif ( $sub->status === 'demo' ) {
                $detail_status_label = 'Demo';
                $detail_status_bg    = '#e0e7ff';
                $detail_status_color = '#3730a3';
            } else {
                $detail_status_label = $is_it ? 'Completata' : 'Completed';
                $detail_status_bg    = '#dcfce7';
                $detail_status_color = '#166534';
            }
            ?>
            <div style="display:flex;align-items:center;gap:12px;margin:12px 0 16px;flex-wrap:wrap">
                <span style="font-size:13px;font-weight:600;color:#64748b"><?php echo esc_html( $is_it ? 'Stato:' : 'Status:' ); ?></span>
                <span style="display:inline-block;padding:4px 14px;background:<?php echo esc_attr( $detail_status_bg ); ?>;color:<?php echo esc_attr( $detail_status_color ); ?>;border-radius:12px;font-size:12px;font-weight:700;letter-spacing:0.3px"><?php echo esc_html( $detail_status_label ); ?></span>
                <?php if ( ! $is_draft ) : ?>
                <span style="border-left:1px solid #e2e8f0;height:20px;display:inline-block"></span>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'pdf',  $base_export ) ); ?>" class="button button-primary">⬇ PDF</a>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'csv',  $base_export ) ); ?>" class="button">⬇ CSV</a>
                <a href="<?php echo esc_url( add_query_arg( 'format', 'md',   $base_export ) ); ?>" class="button">⬇ MD</a>
                <?php endif; ?>
            </div>

            <?php
            // Compute remediation data for tabs
            $states = GapNext_Remediation_State::for_submission( $sub->id );
            $agg    = GapNext_Remediation_State::aggregate( $sub->id, $states );
            $pending_count = $agg['pending_review'];
            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'gap_analysis';
            ?>

            <!-- Tabs -->
            <div class="gapnext-tabs">
                <button class="gapnext-tab <?php echo $active_tab === 'gap_analysis' ? 'active' : ''; ?>" data-tab="gap_analysis">
                    <?php echo esc_html( $is_it ? 'Gap Analysis' : 'Gap Analysis' ); ?>
                </button>
                <button class="gapnext-tab <?php echo $active_tab === 'remediation' ? 'active' : ''; ?>" data-tab="remediation">
                    <?php echo esc_html( $is_it ? 'Piano di Remediation' : 'Remediation Plan' ); ?>
                    <?php if ( $pending_count > 0 ) : ?>
                        <span class="gapnext-tab-badge"><?php echo esc_html( $pending_count ); ?></span>
                    <?php endif; ?>
                </button>
                <button class="gapnext-tab <?php echo $active_tab === 'client_access' ? 'active' : ''; ?>" data-tab="client_access">
                    <?php echo esc_html( $is_it ? 'Accesso Cliente' : 'Client Access' ); ?>
                </button>
            </div>

            <!-- Tab: Gap Analysis (existing content) -->
            <div id="gapnext-panel-gap_analysis" class="gapnext-tab-panel <?php echo $active_tab === 'gap_analysis' ? 'active' : ''; ?>">

            <?php
            // Share Results section — only for completed submissions with a results page configured
            $results_pid = (int) get_option( 'gapnext_results_page_id', 0 );
            if ( $results_pid && ! $is_draft ) :
                $results_url = add_query_arg( [ 'sub' => $sub->id, 'audit' => $sub->audit_uuid ], get_permalink( $results_pid ) );
            ?>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:4px;padding:16px 20px;margin:0 0 24px;max-width:800px">
                <h3 style="margin:0 0 10px;font-size:14px;color:#0c4a6e">
                    🔗 <?php echo esc_html( $is_it ? 'Condividi pagina risultati' : 'Share Results Page' ); ?>
                </h3>
                <p style="margin:0 0 10px;font-size:13px;color:#475569">
                    <?php echo esc_html( $is_it ? 'Chiunque abbia questo link può visualizzare i risultati completi online (nessun login richiesto):' : 'Anyone with this link can view the full results online (no login required):' ); ?>
                </p>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" id="gapnext-share-url"
                           value="<?php echo esc_url( $results_url ); ?>"
                           readonly
                           style="flex:1;min-width:300px;padding:7px 10px;font-size:13px;border:1px solid #cbd5e1;border-radius:4px;background:#fff;font-family:monospace">
                    <button type="button" class="button"
                            onclick="var el=document.getElementById('gapnext-share-url');el.select();document.execCommand('copy');this.textContent='<?php echo esc_js( $is_it ? 'Copiato!' : 'Copied!' ); ?>';var self=this;setTimeout(function(){self.textContent='<?php echo esc_js( $is_it ? 'Copia link' : 'Copy link' ); ?>';},2000);">
                        <?php echo esc_html( $is_it ? 'Copia link' : 'Copy link' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $results_url ); ?>" target="_blank" class="button">
                        <?php echo esc_html( $is_it ? 'Apri' : 'Open' ); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // ============================================================
            // DRAFT FORM LINK — share with customer to complete
            // ============================================================
            if ( $is_draft ) :
                $checklist_pid = (int) get_option( 'gapnext_checklist_page_id', 0 );
                $form_url      = $checklist_pid
                    ? add_query_arg( 'audit', $sub->audit_uuid, get_permalink( $checklist_pid ) )
                    : '';
            ?>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #16a34a;border-radius:4px;padding:16px 20px;margin:0 0 24px;max-width:860px">
                <h3 style="margin:0 0 10px;font-size:14px;color:#166534">
                    📋 <?php echo esc_html( $is_it ? 'Link compilazione' : 'Submission Form Link' ); ?>
                </h3>
                <p style="margin:0 0 10px;font-size:13px;color:#475569;line-height:1.6">
                    <?php echo esc_html( $is_it
                        ? 'Questa compilazione è ancora in bozza. Il link sottostante può essere inviato al cliente o all\'utente per riprendere e completare la compilazione. Quando l\'utente aprirà il link, la bozza verrà caricata automaticamente con i dati già inseriti.'
                        : 'This submission is still a draft. The link below can be sent to the customer or user to resume and complete the form. When the user opens the link, the draft will be loaded automatically with the data already entered.'
                    ); ?>
                </p>
                <?php if ( $form_url ) : ?>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input type="text" id="gapnext-form-link"
                           value="<?php echo esc_url( $form_url ); ?>"
                           readonly
                           style="flex:1;min-width:300px;padding:7px 10px;font-size:13px;border:1px solid #cbd5e1;border-radius:4px;background:#fff;font-family:monospace">
                    <button type="button" class="button"
                            onclick="var el=document.getElementById('gapnext-form-link');el.select();document.execCommand('copy');this.textContent='<?php echo esc_js( $is_it ? 'Copiato!' : 'Copied!' ); ?>';var self=this;setTimeout(function(){self.textContent='<?php echo esc_js( $is_it ? 'Copia link' : 'Copy link' ); ?>';},2000);">
                        <?php echo esc_html( $is_it ? 'Copia link' : 'Copy link' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $form_url ); ?>" target="_blank" class="button">
                        <?php echo esc_html( $is_it ? 'Apri' : 'Open' ); ?>
                    </a>
                </div>
                <p style="margin:10px 0 0;font-size:11px;color:#6b7280;line-height:1.5">
                    <?php echo esc_html( $is_it
                        ? 'Nota: questo è lo stesso link dell\'audit originale. La bozza viene associata all\'audit e ripristinata automaticamente all\'apertura del modulo. Se l\'audit richiede il login, l\'utente dovrà autenticarsi prima di accedere.'
                        : 'Note: this is the same link as the original audit. The draft is associated with the audit and restored automatically when the form is opened. If the audit requires login, the user will need to authenticate first.'
                    ); ?>
                </p>
                <?php else : ?>
                <p style="margin:0;font-size:12px;color:#991b1b">
                    <?php echo esc_html( $is_it
                        ? 'Pagina checklist non configurata nelle impostazioni. Configurare la pagina per generare il link.'
                        : 'Checklist page not configured in settings. Configure the page to generate the link.'
                    ); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php
            // ============================================================
            // DRAFT REMINDER SECTION — only for draft submissions
            // ============================================================
            if ( $is_draft ) :
                $reminder_log       = GapNext_Draft_Reminder::get_log( $sub->id );
                $next_scheduled     = GapNext_Draft_Reminder::get_next_scheduled( $sub->id );
                $reminders_enabled  = (bool) get_option( 'gapnext_draft_reminder_enabled', 1 );
                $is_it              = ( strpos( get_locale(), 'it' ) === 0 );
            ?>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #d97706;border-radius:4px;padding:16px 20px;margin:0 0 24px;max-width:860px">
                <h3 style="margin:0 0 12px;font-size:14px;color:#92400e">
                    <?php echo esc_html( $is_it ? 'Promemoria Bozza' : 'Draft Reminders' ); ?>
                </h3>

                <?php if ( $sub->contact_email ) : ?>
                <p style="margin:0 0 8px;font-size:13px;color:#1e293b">
                    <?php echo esc_html( $is_it ? 'Contatto:' : 'Contact:' ); ?>
                    <strong><?php echo esc_html( $sub->contact_email ); ?></strong>
                </p>
                <?php endif; ?>

                <!-- Manual send button -->
                <div style="margin:12px 0 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <button type="button" id="gapnext-send-reminder-btn" class="button button-primary"
                            data-sub-id="<?php echo esc_attr( $sub->id ); ?>"
                            <?php echo empty( $sub->contact_email ) ? 'disabled' : ''; ?>>
                        <?php echo esc_html( $is_it ? 'Invia promemoria ora' : 'Send Reminder Now' ); ?>
                    </button>
                    <span id="gapnext-reminder-status" style="font-size:13px;color:#475569"></span>
                </div>

                <!-- Auto-reminder info -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:12px 16px;margin:0 0 16px;font-size:12px;color:#475569;line-height:1.7">
                    <strong style="color:#1e293b"><?php echo esc_html( $is_it ? 'Come funzionano i promemoria automatici:' : 'How automatic reminders work:' ); ?></strong><br>
                    <?php echo esc_html( $is_it
                        ? 'Quando un utente salva una bozza, viene programmato un promemoria automatico via email dopo 24 ore tramite WordPress Cron. Se l\'utente salva nuovamente la bozza, il timer si azzera. Una volta completata la compilazione, i promemoria vengono annullati. I promemoria automatici possono essere attivati o disattivati nelle impostazioni del plugin.'
                        : 'When a user saves a draft, an automatic reminder email is scheduled to be sent 24 hours later via WordPress Cron. If the user saves the draft again, the timer resets. Once the submission is completed, reminders are cancelled. Automatic reminders can be enabled or disabled in the plugin settings.'
                    ); ?>
                    <br>
                    <strong><?php echo esc_html( $is_it ? 'Stato:' : 'Status:' ); ?></strong>
                    <?php if ( ! $reminders_enabled ) : ?>
                        <span style="color:#991b1b"><?php echo esc_html( $is_it ? 'I promemoria automatici sono disattivati nelle impostazioni.' : 'Automatic reminders are disabled in settings.' ); ?></span>
                    <?php elseif ( $next_scheduled ) : ?>
                        <span style="color:#166534"><?php printf(
                            esc_html( $is_it ? 'Prossimo promemoria automatico previsto per il %s' : 'Next automatic reminder scheduled for %s' ),
                            esc_html( date_i18n( 'd/m/Y H:i', $next_scheduled ) )
                        ); ?></span>
                    <?php else : ?>
                        <span style="color:#92400e"><?php echo esc_html( $is_it ? 'Nessun promemoria automatico attualmente programmato.' : 'No automatic reminder currently scheduled.' ); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Reminder log -->
                <div>
                    <h4 style="margin:0 0 8px;font-size:13px;color:#1e293b"><?php echo esc_html( $is_it ? 'Registro promemoria' : 'Reminder Log' ); ?></h4>
                    <?php if ( empty( $reminder_log ) ) : ?>
                        <p style="font-size:12px;color:#9ca3af;font-style:italic;margin:0">
                            <?php echo esc_html( $is_it ? 'Nessun promemoria inviato finora.' : 'No reminders have been sent yet.' ); ?>
                        </p>
                    <?php else : ?>
                        <table id="gapnext-reminder-log-table" class="wp-list-table widefat fixed striped" style="max-width:700px">
                            <thead>
                                <tr>
                                    <th style="width:150px"><?php echo esc_html( $is_it ? 'Data' : 'Date' ); ?></th>
                                    <th style="width:80px"><?php echo esc_html( $is_it ? 'Tipo' : 'Type' ); ?></th>
                                    <th><?php echo esc_html( $is_it ? 'Destinatario' : 'Recipient' ); ?></th>
                                    <th style="width:80px"><?php echo esc_html( $is_it ? 'Stato' : 'Status' ); ?></th>
                                    <th style="width:120px"><?php echo esc_html( $is_it ? 'Inviato da' : 'Sent by' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $reminder_log as $log_row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $log_row->sent_at ) ) ); ?></td>
                                    <td>
                                        <?php if ( $log_row->trigger_type === 'manual' ) : ?>
                                            <span style="display:inline-block;padding:2px 8px;background:#dbeafe;color:#1e40af;border-radius:10px;font-size:11px;font-weight:600"><?php echo esc_html( $is_it ? 'Manuale' : 'Manual' ); ?></span>
                                        <?php else : ?>
                                            <span style="display:inline-block;padding:2px 8px;background:#f3e8ff;color:#7c3aed;border-radius:10px;font-size:11px;font-weight:600"><?php echo esc_html( $is_it ? 'Autom.' : 'Auto' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px"><?php echo esc_html( $log_row->recipient_email ); ?></td>
                                    <td>
                                        <?php if ( $log_row->status === 'sent' ) : ?>
                                            <span style="color:#166534;font-weight:600;font-size:12px"><?php echo esc_html( $is_it ? 'Inviato' : 'Sent' ); ?></span>
                                        <?php else : ?>
                                            <span style="color:#991b1b;font-weight:600;font-size:12px"><?php echo esc_html( $is_it ? 'Fallito' : 'Failed' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px">
                                        <?php
                                        if ( $log_row->trigger_type === 'manual' && $log_row->sent_by ) {
                                            $user = get_userdata( $log_row->sent_by );
                                            echo esc_html( $user ? $user->display_name : '#' . $log_row->sent_by );
                                        } else {
                                            echo '<span style="color:#9ca3af">WP Cron</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            jQuery(function($){
                var $btn = $('#gapnext-send-reminder-btn');
                var $status = $('#gapnext-reminder-status');
                $btn.on('click', function(){
                    if (!confirm('<?php echo esc_js( $is_it ? 'Inviare un promemoria via email al contatto?' : 'Send a reminder email to the contact?' ); ?>')) return;
                    $btn.prop('disabled', true).text('<?php echo esc_js( $is_it ? 'Invio in corso...' : 'Sending...' ); ?>');
                    $status.text('');
                    $.post(ajaxurl, {
                        action: 'gapnext_send_reminder',
                        nonce: '<?php echo esc_js( wp_create_nonce( 'gapnext_send_reminder' ) ); ?>',
                        submission_id: $btn.data('sub-id')
                    }).done(function(res){
                        $btn.prop('disabled', false).text('<?php echo esc_js( $is_it ? 'Invia promemoria ora' : 'Send Reminder Now' ); ?>');
                        if (res.success) {
                            $status.css('color','#166534').text(res.data.message);
                            location.reload();
                        } else {
                            $status.css('color','#991b1b').text(res.data || '<?php echo esc_js( $is_it ? 'Invio promemoria fallito.' : 'Failed to send reminder.' ); ?>');
                        }
                    }).fail(function(){
                        $btn.prop('disabled', false).text('<?php echo esc_js( $is_it ? 'Invia promemoria ora' : 'Send Reminder Now' ); ?>');
                        $status.css('color','#991b1b').text('<?php echo esc_js( $is_it ? 'Errore di rete.' : 'Network error.' ); ?>');
                    });
                });
            });
            </script>
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
            <h2><?php echo esc_html( $is_it ? 'Dettagli' : 'Details' ); ?></h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;max-width:860px">

                <!-- Company -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php echo esc_html( $is_it ? 'Azienda' : 'Company' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php echo esc_html( $is_it ? 'Ragione Sociale' : 'Name' ); ?></th><td><?php echo esc_html( $sub->company_name ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Indirizzo' : 'Address' ); ?></th><td><?php echo esc_html( $sub->company_address ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'P.IVA' : 'VAT' ); ?></th><td><?php echo esc_html( $sub->company_vat ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Settore' : 'Sector' ); ?></th><td><?php echo esc_html( $sub->company_sector ); ?></td></tr>
                    </tbody>
                </table>

                <!-- Standard -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php echo esc_html( $is_it ? 'Audit' : 'Audit' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php echo esc_html( $is_it ? 'Standard' : 'Standard' ); ?></th><td><?php echo esc_html( $sub->standard_id ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Lingua' : 'Language' ); ?></th><td><?php echo esc_html( strtoupper( $lang ) ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Data' : 'Date' ); ?></th><td><?php echo esc_html( $sub->submitted_at ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Stato' : 'Status' ); ?></th><td><?php echo esc_html( ucfirst( $sub->status ) ); ?></td></tr>
                    </tbody>
                </table>

                <!-- Internal contact -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php echo esc_html( $is_it ? 'Contatto Interno' : 'Internal Contact' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php echo esc_html( $is_it ? 'Nome' : 'Name' ); ?></th><td><?php echo esc_html( $sub->contact_name ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Ruolo' : 'Role' ); ?></th><td><?php echo esc_html( $sub->contact_role ); ?></td></tr>
                        <tr><th>Email</th><td><?php echo esc_html( $sub->contact_email ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Telefono' : 'Phone' ); ?></th><td><?php echo esc_html( $sub->contact_phone ); ?></td></tr>
                    </tbody>
                </table>

                <!-- Consultant -->
                <table class="widefat" style="margin-bottom:16px">
                    <thead><tr><th colspan="2" style="background:#f8fafc"><?php echo esc_html( $is_it ? 'Consulente' : 'Consultant' ); ?></th></tr></thead>
                    <tbody>
                        <tr><th style="width:35%"><?php echo esc_html( $is_it ? 'Nome' : 'Name' ); ?></th><td><?php echo esc_html( $sub->consultant_name ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Azienda' : 'Company' ); ?></th><td><?php echo esc_html( $sub->consultant_company ); ?></td></tr>
                        <tr><th>Email</th><td><?php echo esc_html( $sub->consultant_email ); ?></td></tr>
                        <tr><th><?php echo esc_html( $is_it ? 'Telefono' : 'Phone' ); ?></th><td><?php echo esc_html( $sub->consultant_phone ); ?></td></tr>
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

            <?php
            // ============================================================
            // AI REPORT SECTION — disabled until AI pipeline is ready for production
            // ============================================================
            if ( false && $ai_client_render->is_configured() ) :
            ?>
            <div style="margin-top:40px;padding-top:32px;border-top:2px solid #e5e7eb">
                <h2 style="font-size:18px;font-weight:700;color:#1d2327;margin:0 0 4px;display:flex;align-items:center;gap:8px">
                    🤖 <?php esc_html_e( 'AI Report', 'gapnext-wp' ); ?>
                    <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                        <span style="font-size:12px;font-weight:600;background:#dcfce7;color:#166534;border-radius:20px;padding:2px 10px;letter-spacing:.3px">
                            ● <?php esc_html_e( 'Available', 'gapnext-wp' ); ?>
                        </span>
                    <?php endif; ?>
                </h2>

                <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                    <!-- Report already exists: show latest download + regenerate + history -->

                    <!-- Latest report download -->
                    <p style="color:#6b7280;font-size:13px;margin:6px 0 8px">
                        <?php esc_html_e( 'Latest report:', 'gapnext-wp' ); ?>
                    </p>
                    <a id="gapnext-ai-latest-download"
                       href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $sub->ai_report_url ) ); ?>"
                       target="_blank" class="button button-primary">
                        ⬇ <?php esc_html_e( 'Download AI Report', 'gapnext-wp' ); ?>
                    </a>

                    <!-- Regenerate section -->
                    <div style="margin-top:24px">
                        <p style="color:#374151;font-size:13px;margin:0 0 6px;font-weight:600">
                            <?php esc_html_e( 'Regenerate AI Report', 'gapnext-wp' ); ?>
                        </p>
                        <p style="color:#6b7280;font-size:12px;margin:0 0 8px">
                            <?php esc_html_e( 'Corrections or additional context for regeneration (optional):', 'gapnext-wp' ); ?>
                        </p>
                        <textarea id="gapnext-ai-corrections" rows="3"
                            style="width:100%;max-width:520px;font-size:13px;padding:8px;border:1px solid #d1d5db;border-radius:6px;resize:vertical"
                            placeholder="<?php esc_attr_e( 'e.g. Please focus more on ISO clause 6.1...', 'gapnext-wp' ); ?>"></textarea>
                        <br>
                        <button type="button" id="gapnext-ai-regenerate-view" class="button button-secondary" style="margin-top:8px">
                            <?php esc_html_e( 'Regenerate AI Report', 'gapnext-wp' ); ?>
                        </button>
                    </div>

                    <!-- Regenerate progress log panel (hidden until regeneration starts) -->
                    <div id="gapnext-ai-regen-log-panel" style="display:none;margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;max-width:520px">
                        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">
                            <?php esc_html_e( 'Progress', 'gapnext-wp' ); ?>
                        </div>
                        <div id="gapnext-ai-regen-log-steps"></div>
                    </div>

                    <!-- Generation history — table is always rendered so JS can prependTo tbody -->
                    <?php $history = self::get_ai_report_history( $sub->id ); ?>
                    <div style="margin-top:32px">
                        <h3 style="font-size:14px;font-weight:700;color:#374151;margin:0 0 12px">
                            <?php esc_html_e( 'Generation History', 'gapnext-wp' ); ?>
                        </h3>
                        <table id="gapnext-ai-history-table" class="wp-list-table widefat fixed striped" style="max-width:720px">
                            <thead>
                                <tr>
                                    <th style="width:160px"><?php esc_html_e( 'Date', 'gapnext-wp' ); ?></th>
                                    <th><?php esc_html_e( 'Comments', 'gapnext-wp' ); ?></th>
                                    <th style="width:110px"><?php esc_html_e( 'Download', 'gapnext-wp' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $history as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->generated_at ) ) ); ?></td>
                                    <td><?php echo $row->comments ? esc_html( $row->comments ) : '&mdash;'; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $row->download_url ) ); ?>"
                                           target="_blank">⬇ <?php esc_html_e( 'Report', 'gapnext-wp' ); ?></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else : ?>
                    <!-- No report yet: show generate button + log panel -->
                    <p style="color:#6b7280;font-size:13px;margin:6px 0 16px">
                        <?php esc_html_e( 'Generate a professional AI-powered compliance report for this submission.', 'gapnext-wp' ); ?>
                    </p>
                    <button type="button" id="gapnext-ai-generate-view" class="button button-primary">
                        <?php esc_html_e( 'Generate AI Report', 'gapnext-wp' ); ?>
                    </button>

                    <!-- Progress log panel (hidden until generation starts) -->
                    <div id="gapnext-ai-log-panel" style="display:none;margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;max-width:520px">
                        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">
                            <?php esc_html_e( 'Progress', 'gapnext-wp' ); ?>
                        </div>
                        <div id="gapnext-ai-log-steps"></div>
                    </div>
                    <style>
                    @keyframes gapnext-pulse-dots {
                        0%, 80%, 100% { opacity: 0; }
                        40%           { opacity: 1; }
                    }
                    .gapnext-ai-dot {
                        display: inline-block;
                        width: 4px; height: 4px;
                        border-radius: 50%;
                        background: #6366f1;
                        margin: 0 2px;
                        animation: gapnext-pulse-dots 1.4s infinite ease-in-out;
                    }
                    .gapnext-ai-dot:nth-child(2) { animation-delay: .2s; }
                    .gapnext-ai-dot:nth-child(3) { animation-delay: .4s; }
                    .gapnext-ai-step {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        font-size: 13px;
                        color: #374151;
                        padding: 5px 0;
                        opacity: .38;
                        transition: opacity .25s;
                    }
                    .gapnext-ai-step.active  { opacity: 1; }
                    .gapnext-ai-step.done    { opacity: 1; color: #374151; }
                    .gapnext-ai-step.failed  { opacity: 1; color: #991b1b; }
                    .gapnext-ai-step-icon    { font-size: 14px; width: 18px; text-align: center; flex-shrink: 0; }
                    .gapnext-ai-step-text    { flex: 1; }
                    </style>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            </div><!-- /gap_analysis panel -->

            <!-- Tab: Remediation Plan -->
            <div id="gapnext-panel-remediation" class="gapnext-tab-panel <?php echo $active_tab === 'remediation' ? 'active' : ''; ?>">
                <?php include GAPNEXT_WP_DIR . 'includes/views/admin-remediation.php'; ?>
            </div>

            <!-- Tab: Client Access -->
            <div id="gapnext-panel-client_access" class="gapnext-tab-panel <?php echo $active_tab === 'client_access' ? 'active' : ''; ?>">
                <?php include GAPNEXT_WP_DIR . 'includes/views/admin-client-access.php'; ?>
            </div>

        </div>
        <?php
    }

    private static function delete_submission( $sub_id ) {
        global $wpdb;
        $sub = self::get_submission( (int) $sub_id );

        if ( $sub ) {
            $evidence = json_decode( $sub->evidence_paths, true ) ?: [];
            if ( $evidence ) {
                $upload_dir = wp_upload_dir();
                $base       = trailingslashit( $upload_dir['basedir'] ) . 'gapnext-evidence/' . $sub->audit_uuid;
                foreach ( $evidence as $paths ) {
                    foreach ( (array) $paths as $path ) {
                        if ( file_exists( $path ) ) {
                            wp_delete_file( $path );
                        }
                    }
                }
                if ( is_dir( $base ) ) {
                    self::remove_directory( $base );
                }
            }
            $wpdb->delete( $wpdb->prefix . 'gapnext_ai_report_generations', [ 'submission_id' => (int) $sub_id ], [ '%d' ] );
            $wpdb->delete( $wpdb->prefix . 'gapnext_reminder_log', [ 'submission_id' => (int) $sub_id ], [ '%d' ] );
            GapNext_Draft_Reminder::cancel( (int) $sub_id );
        }

        $wpdb->delete( $wpdb->prefix . 'gapnext_submissions', [ 'id' => (int) $sub_id ], [ '%d' ] );
    }

    private static function remove_directory( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $items as $item ) {
            if ( $item->isDir() ) {
                rmdir( $item->getPathname() );
            } else {
                wp_delete_file( $item->getPathname() );
            }
        }
        rmdir( $dir );
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
                'access_mode' => in_array( $access, [ 'public', 'login_required', 'demo' ], true ) ? $access : 'public',
                'created_by'  => get_current_user_id(),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%d' ]
        );
        return (bool) $wpdb->insert_id;
    }

    private static function delete_audit( $uuid ) {
        global $wpdb;
        $sub_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gapnext_submissions WHERE audit_uuid = %s", $uuid
        ) );
        if ( $sub_ids ) {
            $placeholders = implode( ',', array_fill( 0, count( $sub_ids ), '%d' ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gapnext_ai_report_generations WHERE submission_id IN ($placeholders)", ...$sub_ids
            ) );
            $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}gapnext_reminder_log WHERE submission_id IN ($placeholders)", ...$sub_ids
            ) );
            foreach ( $sub_ids as $sid ) {
                GapNext_Draft_Reminder::cancel( (int) $sid );
            }
        }
        $wpdb->delete( $wpdb->prefix . 'gapnext_remediation_log', [ 'audit_uuid' => $uuid ], [ '%s' ] );
        $wpdb->delete( $wpdb->prefix . 'gapnext_client_access', [ 'audit_uuid' => $uuid ], [ '%s' ] );
        $wpdb->delete( $wpdb->prefix . 'gapnext_submissions', [ 'audit_uuid' => $uuid ], [ '%s' ] );
        $wpdb->delete( $wpdb->prefix . 'gapnext_audits', [ 'uuid' => $uuid ], [ '%s' ] );
    }

    public static function get_all_audits() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gapnext_audits ORDER BY created_at DESC" );
    }

    public static function get_all_submissions() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}gapnext_submissions WHERE status IN ('submitted','demo','draft') ORDER BY submitted_at DESC" );
    }

    public static function get_filtered_submissions( $standard = '', $company = '', $order = 'desc', $status = '' ) {
        global $wpdb;
        $order     = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';
        $where     = [ "status IN ('submitted','demo','draft')" ];
        $values    = [];

        if ( $standard !== '' ) {
            $where[]  = 'standard_id = %s';
            $values[] = $standard;
        }
        if ( $company !== '' ) {
            $where[]  = 'company_name = %s';
            $values[] = $company;
        }
        if ( $status !== '' ) {
            if ( $status === 'completed' ) {
                $where[] = "status IN ('submitted','demo')";
            } else {
                $where[]  = 'status = %s';
                $values[] = $status;
            }
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
     * Fetch all AI report generation rows for a submission, newest first.
     *
     * @param  int $submission_id
     * @return array Array of stdClass rows (uuid, download_url, comments, generated_at)
     */
    private static function get_ai_report_history( int $submission_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT uuid, download_url, comments, generated_at
             FROM {$wpdb->prefix}gapnext_ai_report_generations
             WHERE submission_id = %d
             ORDER BY generated_at DESC",
            $submission_id
        ) ) ?: [];
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

        $comments = sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) );
        $result = ( new GapNext_AI_Report() )->generate_for_submission( $submission_id, $comments );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( array_merge( $result, [ 'comments' => $comments ] ) );
    }

    /**
     * Human-readable description of a remediation event.
     */
    public static function describe_event( $event, $lang = 'en' ) {
        $d = $event->data;
        $is_it = $lang === 'it';

        switch ( $event->event_type ) {
            case 'status_changed':
                $from = $d['old_status'] ?? '?';
                $to = $d['new_status'] ?? '?';
                return $is_it
                    ? "Stato cambiato: {$from} → {$to}"
                    : "Status changed: {$from} → {$to}";
            case 'action_set':
                return $is_it ? 'Azione correttiva definita' : 'Corrective action set';
            case 'action_updated':
                return $is_it ? 'Azione correttiva aggiornata' : 'Corrective action updated';
            case 'priority_set':
                return ( $is_it ? 'Priorità: ' : 'Priority: ' ) . ( $d['priority'] ?? '' );
            case 'deadline_set':
                return ( $is_it ? 'Scadenza: ' : 'Deadline: ' ) . ( $d['deadline'] ?? '—' );
            case 'responsible_set':
                return ( $is_it ? 'Responsabile: ' : 'Responsible: ' ) . ( $d['responsible'] ?? '' );
            case 'answer_changed':
                return ( $is_it ? 'Risposta proposta: ' : 'Answer proposed: ' ) . ( $d['new_value'] ?? '' );
            case 'answer_approved':
                return $is_it ? 'Risposta approvata' : 'Answer approved';
            case 'answer_rejected':
                $fb = $d['feedback'] ?? '';
                return ( $is_it ? 'Risposta respinta' : 'Answer rejected' ) . ( $fb ? ": {$fb}" : '' );
            case 'evidence_added':
                return ( $is_it ? 'Evidenza caricata: ' : 'Evidence uploaded: ' ) . basename( $d['file_path'] ?? '' );
            case 'comment':
                return $d['comment'] ?? '';
            case 'verified':
                return $is_it ? 'Verificato ✓' : 'Verified ✓';
            default:
                return $event->event_type;
        }
    }
}
