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

        // Pass form-specific data to JS (uuid needed for localStorage key)
        wp_localize_script( 'gapnext-wp', 'GapNextWPForm', [
            'audit_uuid' => $uuid,
        ] );

        ob_start();
        $this->render_form( $audit, $standard );
        return ob_get_clean();
    }

    private function render_form( $audit, $standard ) {
        $lang = $audit->language;
        include GAPNEXT_WP_DIR . 'includes/views/form.php';
    }
}
