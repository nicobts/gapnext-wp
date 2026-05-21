<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Admin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_post_gapnext_save_settings', [ $this, 'save_settings' ] );
        add_action( 'admin_post_gapnext_export',        [ $this, 'handle_export' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        /* AI settings — disabled until AI pipeline is ready for production
        $ai_settings = new GapNext_AI_Settings();
        add_action( 'admin_post_gapnext_save_ai_settings',  [ $ai_settings, 'save_settings' ] );
        add_action( 'wp_ajax_gapnext_ai_test_connection',   [ $ai_settings, 'handle_test_connection' ] );
        */
    }

    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized', 'gapnext-wp' ) );
        }

        $sub_id = (int) ( $_GET['sub_id'] ?? 0 );
        check_admin_referer( 'gapnext_export_' . $sub_id );
        $format = sanitize_key( $_GET['format'] ?? '' );

        switch ( $format ) {
            case 'pdf':  GapNext_Export_Pdf::export( $sub_id );  break;
            case 'csv':  GapNext_Export_Csv::export( $sub_id );  break;
            case 'md':   GapNext_Export_Md::export( $sub_id );   break;
            case 'json': GapNext_Export_Json::export( $sub_id ); break;
            default:     wp_die( esc_html__( 'Invalid format.', 'gapnext-wp' ) );
        }
    }

    public function register_menus() {
        add_menu_page(
            __( 'GapNext WP', 'gapnext-wp' ),
            __( 'GapNext WP', 'gapnext-wp' ),
            'manage_options',
            'gapnext-wp',
            [ 'GapNext_Audit_Manager', 'render_audit_links_page' ],
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'gapnext-wp',
            __( 'Audit Links', 'gapnext-wp' ),
            __( 'Audit Links', 'gapnext-wp' ),
            'manage_options',
            'gapnext-wp',
            [ 'GapNext_Audit_Manager', 'render_audit_links_page' ]
        );

        add_submenu_page(
            'gapnext-wp',
            __( 'Submissions', 'gapnext-wp' ),
            __( 'Submissions', 'gapnext-wp' ),
            'manage_options',
            'gapnext-submissions',
            [ 'GapNext_Audit_Manager', 'render_submissions_page' ]
        );

        add_submenu_page(
            'gapnext-wp',
            __( 'Settings', 'gapnext-wp' ),
            __( 'Settings', 'gapnext-wp' ),
            'manage_options',
            'gapnext-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'gapnext' ) === false ) return;
        wp_enqueue_media();

        // Remediation assets on submission detail page
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'gapnext-submissions' && isset( $_GET['view_sub'] ) ) {
            wp_enqueue_style(
                'gapnext-admin-remediation',
                GAPNEXT_WP_URL . 'assets/gapnext-admin-remediation.css',
                [],
                GAPNEXT_WP_VERSION
            );
            wp_enqueue_script(
                'gapnext-admin-remediation',
                GAPNEXT_WP_URL . 'assets/gapnext-admin-remediation.js',
                [ 'jquery' ],
                GAPNEXT_WP_VERSION,
                true
            );

            $sub_id = (int) $_GET['view_sub'];
            $sub = GapNext_Audit_Manager::get_submission( $sub_id );
            wp_localize_script( 'gapnext-admin-remediation', 'gapnextRemediation', [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'gapnext_remediation' ),
                'submissionId' => $sub_id,
                'auditUuid'    => $sub ? $sub->audit_uuid : '',
                'i18n'         => [
                    'confirmInit'   => __( 'Initialize remediation plan for all questions? This cannot be undone.', 'gapnext-wp' ),
                    'initializing'  => __( 'Initializing...', 'gapnext-wp' ),
                    'initBtn'       => __( 'Initialize Remediation Plan', 'gapnext-wp' ),
                    'rejectReason'  => __( 'Reason for rejection:', 'gapnext-wp' ),
                    'clientRequired'=> __( 'Email and name are required.', 'gapnext-wp' ),
                ],
            ] );
        }
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $default_lang   = get_option( 'gapnext_default_language', 'it' );
        $default_access = get_option( 'gapnext_default_access_mode', 'public' );
        $logo_id        = (int) get_option( 'gapnext_consultant_logo_id', 0 );
        $logo_url       = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
        $checklist_page  = (int) get_option( 'gapnext_checklist_page_id', 0 );
        $results_page    = (int) get_option( 'gapnext_results_page_id',   0 );
        $pdf_footer_text    = get_option( 'gapnext_pdf_footer_text', '' );
        $notification_email = get_option( 'gapnext_notification_email', get_option( 'admin_email' ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'GapNext WP — Settings', 'gapnext-wp' ); ?></h1>
            <?php if ( isset( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'gapnext-wp' ); ?></p></div>
            <?php endif; ?>

            <!-- ── Quick-start instructions ─────────────────────────────── -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;border-radius:4px;padding:18px 22px;margin:16px 0 24px;max-width:800px">
                <h2 style="margin:0 0 12px;font-size:14px;color:#1d2327">
                    📋 <?php esc_html_e( 'Quick-start — required setup', 'gapnext-wp' ); ?>
                </h2>
                <ol style="margin:0;padding-left:20px;line-height:1.9;color:#3c434a;font-size:13px">
                    <li>
                        <?php esc_html_e( 'Create a new WordPress page (e.g. "Gap Analysis") and paste this shortcode into the page content:', 'gapnext-wp' ); ?>
                        <br>
                        <code style="display:inline-block;margin:4px 0;padding:4px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:13px;user-select:all">[gapnext_checklist]</code>
                    </li>
                    <li><?php esc_html_e( 'Publish the page.', 'gapnext-wp' ); ?></li>
                    <li><?php esc_html_e( 'In the "Checklist Page" field below, select that page — this is where all audit links will send respondents.', 'gapnext-wp' ); ?></li>
                    <li><?php esc_html_e( 'Go to Audit Links → Create a new audit link, share the generated URL with your client.', 'gapnext-wp' ); ?></li>
                    <li>
                        <?php esc_html_e( '(Optional) Create a second page with this shortcode for a shareable web results page:', 'gapnext-wp' ); ?>
                        <br>
                        <code style="display:inline-block;margin:4px 0;padding:4px 10px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:13px;user-select:all">[gapnext_results]</code>
                        <br>
                        <?php esc_html_e( 'Then select it in the "Results Page" field below.', 'gapnext-wp' ); ?>
                    </li>
                </ol>
                <p style="margin:10px 0 0;font-size:12px;color:#646970">
                    <?php esc_html_e( 'The plugin will not work until a checklist page is created and selected below.', 'gapnext-wp' ); ?>
                </p>
            </div>
            <!-- ────────────────────────────────────────────────────────── -->

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'gapnext_save_settings', 'gapnext_settings_nonce' ); ?>
                <input type="hidden" name="action" value="gapnext_save_settings">
                <table class="form-table">
                    <tr>
                        <th><label for="gapnext_checklist_page_id"><?php esc_html_e( 'Checklist Page', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages( [
                                'name'              => 'gapnext_checklist_page_id',
                                'id'                => 'gapnext_checklist_page_id',
                                'selected'          => $checklist_page,
                                'show_option_none'  => __( '— Select a page —', 'gapnext-wp' ),
                                'option_none_value' => '0',
                            ] );
                            ?>
                            <p class="description"><?php esc_html_e( 'The page that contains the [gapnext_checklist] shortcode. Audit links will point here.', 'gapnext-wp' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_results_page_id"><?php esc_html_e( 'Results Page', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages( [
                                'name'              => 'gapnext_results_page_id',
                                'id'                => 'gapnext_results_page_id',
                                'selected'          => $results_page,
                                'show_option_none'  => __( '— None (disable online results) —', 'gapnext-wp' ),
                                'option_none_value' => '0',
                            ] );
                            ?>
                            <p class="description"><?php esc_html_e( 'The page with [gapnext_results] shortcode. A "View results online" link will appear after submission.', 'gapnext-wp' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_default_language"><?php esc_html_e( 'Default Language', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <select name="gapnext_default_language" id="gapnext_default_language">
                                <option value="it" <?php selected( $default_lang, 'it' ); ?>>Italiano</option>
                                <option value="en" <?php selected( $default_lang, 'en' ); ?>>English</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_default_access_mode"><?php esc_html_e( 'Default Access Mode', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <select name="gapnext_default_access_mode" id="gapnext_default_access_mode">
                                <option value="public" <?php selected( $default_access, 'public' ); ?>><?php esc_html_e( 'Public', 'gapnext-wp' ); ?></option>
                                <option value="login_required" <?php selected( $default_access, 'login_required' ); ?>><?php esc_html_e( 'Login Required', 'gapnext-wp' ); ?></option>
                                <option value="demo" <?php selected( $default_access, 'demo' ); ?>><?php esc_html_e( 'Demo', 'gapnext-wp' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e( 'Consultant Logo', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <?php if ( $logo_url ) : ?>
                                <img id="gapnext_logo_preview" src="<?php echo esc_url( $logo_url ); ?>" style="max-height:80px;display:block;margin-bottom:8px;">
                            <?php else : ?>
                                <img id="gapnext_logo_preview" src="" style="max-height:80px;display:none;margin-bottom:8px;">
                            <?php endif; ?>
                            <input type="hidden" name="gapnext_consultant_logo_id" id="gapnext_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
                            <button type="button" class="button" id="gapnext_upload_logo_btn"><?php esc_html_e( 'Upload / Change Logo', 'gapnext-wp' ); ?></button>
                            <p class="description"><?php esc_html_e( 'Appears on the PDF report cover page. Falls back to the WordPress site logo if not set.', 'gapnext-wp' ); ?></p>
                            <script>
                            jQuery(function($){
                                var frame;
                                $('#gapnext_upload_logo_btn').on('click', function(e){
                                    e.preventDefault();
                                    if(frame){ frame.open(); return; }
                                    frame = wp.media({ title: '<?php echo esc_js( __( 'Select Logo', 'gapnext-wp' ) ); ?>', button:{ text:'<?php echo esc_js( __( 'Use this logo', 'gapnext-wp' ) ); ?>' }, multiple:false });
                                    frame.on('select', function(){
                                        var att = frame.state().get('selection').first().toJSON();
                                        $('#gapnext_logo_id').val(att.id);
                                        $('#gapnext_logo_preview').attr('src', att.url).show();
                                    });
                                    frame.open();
                                });
                            });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_pdf_footer_text"><?php esc_html_e( 'PDF Footer Text', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <input type="text" name="gapnext_pdf_footer_text" id="gapnext_pdf_footer_text"
                                   class="regular-text" value="<?php echo esc_attr( $pdf_footer_text ); ?>"
                                   placeholder="e.g. CD Consultancy - Proprietary and Confidential">
                            <p class="description"><?php esc_html_e( 'Appears centred in the footer of every PDF report page.', 'gapnext-wp' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_notification_email"><?php esc_html_e( 'Notification Email', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <input type="email" name="gapnext_notification_email" id="gapnext_notification_email"
                                   class="regular-text" value="<?php echo esc_attr( $notification_email ); ?>">
                            <p class="description"><?php esc_html_e( 'Receives a copy of the submission notification email (in addition to the consultant). Leave blank to disable.', 'gapnext-wp' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_draft_reminder_enabled"><?php esc_html_e( 'Draft Reminder Email', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="gapnext_draft_reminder_enabled" id="gapnext_draft_reminder_enabled" value="1"
                                    <?php checked( get_option( 'gapnext_draft_reminder_enabled', 1 ) ); ?>>
                                <?php esc_html_e( 'Send a reminder email 24h after the last draft save if the form has not been submitted', 'gapnext-wp' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="gapnext_demo_question_limit"><?php esc_html_e( 'Demo Question Limit', 'gapnext-wp' ); ?></label></th>
                        <td>
                            <input type="number" name="gapnext_demo_question_limit" id="gapnext_demo_question_limit"
                                   class="small-text" min="5" max="50"
                                   value="<?php echo esc_attr( get_option( 'gapnext_demo_question_limit', 15 ) ); ?>">
                            <p class="description"><?php esc_html_e( 'Number of questions shown in demo audit links (default: 15).', 'gapnext-wp' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Settings', 'gapnext-wp' ) ); ?>
            </form>

            <!-- ── Evidence file storage note ───────────────────────────── -->
            <div style="background:#fff;border:1px solid #c3c4c7;border-left:4px solid #d97706;border-radius:4px;padding:18px 22px;margin:24px 0;max-width:800px">
                <h2 style="margin:0 0 10px;font-size:14px;color:#1d2327">
                    📎 <?php esc_html_e( 'Evidence File Storage', 'gapnext-wp' ); ?>
                </h2>
                <p style="margin:0 0 10px;font-size:13px;color:#3c434a;line-height:1.7">
                    <?php esc_html_e( 'When respondents upload evidence files during an audit, the files are saved inside the WordPress uploads directory using the following folder structure:', 'gapnext-wp' ); ?>
                </p>
                <code style="display:block;padding:10px 14px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;font-size:12px;line-height:1.8;color:#1d2327">
                    wp-content/uploads/gapnext-evidence/{audit-uuid}/q-{question-ref}/{filename}
                </code>
                <ul style="margin:12px 0 0;padding-left:20px;font-size:13px;color:#3c434a;line-height:1.9">
                    <li><strong>{audit-uuid}</strong> — <?php esc_html_e( 'the unique identifier of the audit link (e.g. a1b2c3d4-…). Each audit gets its own subfolder, preventing file collisions between audits.', 'gapnext-wp' ); ?></li>
                    <li><strong>q-{question-ref}</strong> — <?php esc_html_e( 'the question reference key (e.g. q-4_1, q-6_2). Each question gets its own subfolder so evidence stays organised per checklist item.', 'gapnext-wp' ); ?></li>
                    <li><strong>{filename}</strong> — <?php esc_html_e( 'the original uploaded file name, sanitised by WordPress to prevent path-traversal issues. Multiple files per question are supported.', 'gapnext-wp' ); ?></li>
                </ul>
                <p style="margin:12px 0 0;font-size:13px;color:#3c434a;line-height:1.7">
                    <?php esc_html_e( 'The absolute server path of each uploaded file is stored in the database (wp_gapnext_submissions.evidence_paths) as a JSON object keyed by question reference. On the results page these paths are automatically converted to public URLs so that linked files can be opened or downloaded directly in the browser.', 'gapnext-wp' ); ?>
                </p>
                <p style="margin:10px 0 0;font-size:12px;color:#646970">
                    <?php
                    $upload_dir = wp_upload_dir();
                    /* translators: %s: example filesystem path */
                    printf(
                        esc_html__( 'Example path on this server: %s', 'gapnext-wp' ),
                        '<code style="font-size:11px">' . esc_html( trailingslashit( $upload_dir['basedir'] ) . 'gapnext-evidence/&lt;audit-uuid&gt;/q-&lt;ref&gt;/document.pdf' ) . '</code>'
                    );
                    ?>
                </p>
                <p style="margin:8px 0 0;font-size:12px;color:#646970">
                    ⚠️ <?php esc_html_e( 'Note: evidence files are not managed by the WordPress Media Library. If you delete an audit or submission from the database, the files in the uploads folder must be removed manually.', 'gapnext-wp' ); ?>
                </p>
            </div>
            <!-- ────────────────────────────────────────────────────────── -->

        <?php /* AI settings section — disabled until AI pipeline is ready for production
        ( new GapNext_AI_Settings() )->render_section();
        */ ?>

        </div>
        <?php
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized.', 'gapnext-wp' ) );
        check_admin_referer( 'gapnext_save_settings', 'gapnext_settings_nonce' );

        $lang               = sanitize_text_field( $_POST['gapnext_default_language'] ?? 'it' );
        $access             = sanitize_text_field( $_POST['gapnext_default_access_mode'] ?? 'public' );
        $logo               = (int) ( $_POST['gapnext_consultant_logo_id'] ?? 0 );
        $checklist_pid      = (int) ( $_POST['gapnext_checklist_page_id'] ?? 0 );
        $results_pid        = (int) ( $_POST['gapnext_results_page_id']   ?? 0 );
        update_option( 'gapnext_default_language',    in_array( $lang, [ 'it', 'en' ], true ) ? $lang : 'it' );
        update_option( 'gapnext_default_access_mode', in_array( $access, [ 'public', 'login_required', 'demo' ], true ) ? $access : 'public' );
        update_option( 'gapnext_consultant_logo_id',  $logo );
        update_option( 'gapnext_checklist_page_id',   $checklist_pid );
        update_option( 'gapnext_results_page_id',     $results_pid );
        update_option( 'gapnext_pdf_footer_text',     sanitize_text_field( $_POST['gapnext_pdf_footer_text'] ?? '' ) );
        update_option( 'gapnext_notification_email',  sanitize_email( $_POST['gapnext_notification_email'] ?? '' ) );
        update_option( 'gapnext_draft_reminder_enabled', isset( $_POST['gapnext_draft_reminder_enabled'] ) ? 1 : 0 );
        update_option( 'gapnext_demo_question_limit', max( 5, min( 50, (int) ( $_POST['gapnext_demo_question_limit'] ?? 15 ) ) ) );

        wp_redirect( add_query_arg( [ 'page' => 'gapnext-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }
}
