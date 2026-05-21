<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Checklist {

    public function __construct() {
        add_shortcode( 'gapnext_checklist', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
    }

    public function maybe_enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'gapnext_checklist' ) ) {
            wp_enqueue_style(
                'gapnext-wp',
                GAPNEXT_WP_URL . 'assets/gapnext-wp.css',
                [],
                GAPNEXT_WP_VERSION
            );
            wp_enqueue_script(
                'gapnext-wp',
                GAPNEXT_WP_URL . 'assets/gapnext-wp.js',
                [ 'jquery' ],
                GAPNEXT_WP_VERSION,
                true
            );
            $results_pid = (int) get_option( 'gapnext_results_page_id', 0 );
            wp_localize_script( 'gapnext-wp', 'GapNextWP', [
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'gapnext_submit' ),
                'autosave_nonce'   => wp_create_nonce( 'gapnext_autosave' ),
                'results_page_url' => $results_pid ? get_permalink( $results_pid ) : '',
                'download_url'     => admin_url( 'admin-post.php' ),
                'i18n'             => [
                    'submitting'          => __( 'Submitting...', 'gapnext-wp' ),
                    'error'               => __( 'An error occurred. Please try again.', 'gapnext-wp' ),
                    'required_fields'     => __( 'Please fill in all required fields.', 'gapnext-wp' ),
                    'field_required'      => __( 'This field is required', 'gapnext-wp' ),
                    'field_required_named'=> __( '{field} is required', 'gapnext-wp' ),
                    'questions_answered'  => __( 'questions answered', 'gapnext-wp' ),
                    'draft_saved'         => __( 'Draft saved', 'gapnext-wp' ),
                    'draft_restored'      => __( 'Draft restored', 'gapnext-wp' ),
                    'download_results'    => __( 'Download your results:', 'gapnext-wp' ),
                ],
            ] );
        }
    }

    public function render( $atts ) {
        $uuid = isset( $_GET['audit'] ) ? sanitize_text_field( wp_unslash( $_GET['audit'] ) ) : '';

        if ( empty( $uuid ) ) {
            return '<p class="gapnext-error">' . esc_html__( 'Invalid or missing audit link.', 'gapnext-wp' ) . '</p>';
        }

        $audit = GapNext_Audit_Manager::get_audit_by_uuid( $uuid );
        if ( ! $audit ) {
            return '<p class="gapnext-error">' . esc_html__( 'Audit not found.', 'gapnext-wp' ) . '</p>';
        }

        // Access control
        if ( $audit->access_mode === 'login_required' && ! is_user_logged_in() ) {
            wp_redirect( wp_login_url( get_permalink() . '?audit=' . rawurlencode( $uuid ) ) );
            exit;
        }

        $standard = GapNext_Standard_Registry::get_standard_data( $audit->standard_id, $audit->language );
        if ( ! $standard ) {
            return '<p class="gapnext-error">' . esc_html__( 'Standard data not found.', 'gapnext-wp' ) . '</p>';
        }

        $is_demo = $audit->access_mode === 'demo';
        $full_question_count = 0;
        foreach ( $standard['clauses'] as $c ) {
            if ( (int) ( $c['level'] ?? 2 ) === 2 ) $full_question_count++;
        }

        // Slice questions for demo audits
        if ( $is_demo ) {
            $demo_limit = (int) get_option( 'gapnext_demo_question_limit', 15 );
            $sliced     = [];
            $q_count    = 0;
            $last_l1    = null;
            foreach ( $standard['clauses'] as $clause ) {
                if ( (int) $clause['level'] === 1 ) {
                    $last_l1 = $clause;
                    continue;
                }
                if ( $q_count >= $demo_limit ) break;
                // Add parent header if not yet added
                if ( $last_l1 ) {
                    $sliced[] = $last_l1;
                    $last_l1  = null;
                }
                $sliced[] = $clause;
                $q_count++;
            }
            $standard['clauses'] = $sliced;
        }

        // Check for existing server-side draft
        global $wpdb;
        $draft = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, answers, filling_mode, last_step, company_name, company_address, company_vat, company_sector,
                    contact_name, contact_role, contact_email, contact_phone,
                    consultant_name, consultant_company, consultant_email, consultant_phone, submitted_at
             FROM {$wpdb->prefix}gapnext_submissions
             WHERE audit_uuid = %s AND status = 'draft'
             ORDER BY submitted_at DESC LIMIT 1",
            $uuid
        ) );

        $draft_data = null;
        if ( $draft ) {
            $draft_data = [
                'draft_id'           => (int) $draft->id,
                'filling_mode'       => $draft->filling_mode ?: 'assisted',
                'last_step'          => (int) $draft->last_step,
                'saved_at'           => $draft->submitted_at,
                'company_name'       => $draft->company_name,
                'company_address'    => $draft->company_address,
                'company_vat'        => $draft->company_vat,
                'company_sector'     => $draft->company_sector,
                'contact_name'       => $draft->contact_name,
                'contact_role'       => $draft->contact_role,
                'contact_email'      => $draft->contact_email,
                'contact_phone'      => $draft->contact_phone,
                'consultant_name'    => $draft->consultant_name,
                'consultant_company' => $draft->consultant_company,
                'consultant_email'   => $draft->consultant_email,
                'consultant_phone'   => $draft->consultant_phone,
                'answers'            => json_decode( $draft->answers, true ) ?: [],
            ];
        }

        // Build section metadata for JS
        $sections_meta = [];
        foreach ( $standard['clauses'] as $clause ) {
            if ( (int) ( $clause['level'] ?? 2 ) === 1 ) {
                $sections_meta[] = [
                    'ref'     => $clause['reference'],
                    'heading' => $clause['reference'] . '. ' . $clause['title'],
                ];
            }
        }

        // Pass form-specific data to JS
        wp_localize_script( 'gapnext-wp', 'GapNextWPForm', [
            'audit_uuid'          => $uuid,
            'sections_meta'       => $sections_meta,
            'server_draft'        => $draft_data,
            'is_demo'             => $is_demo,
            'full_question_count' => $full_question_count,
        ] );

        ob_start();
        $this->render_form( $audit, $standard, $is_demo, $full_question_count );
        return ob_get_clean();
    }

    private function render_form( $audit, $standard, $is_demo = false, $full_question_count = 0 ) {
        $lang = $audit->language;
        include GAPNEXT_WP_DIR . 'includes/views/form.php';
    }
}
