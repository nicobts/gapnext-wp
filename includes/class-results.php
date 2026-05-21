<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Results {

    public function __construct() {
        add_shortcode( 'gapnext_results', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
    }

    public function maybe_enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'gapnext_results' ) ) {
            wp_enqueue_style(
                'gapnext-wp',
                GAPNEXT_WP_URL . 'assets/gapnext-wp.css',
                [],
                GAPNEXT_WP_VERSION
            );
            $custom_css = get_option( 'gapnext_custom_css', '' );
            if ( $custom_css ) {
                wp_add_inline_style( 'gapnext-wp', $custom_css );
            }
        }
    }

    public function render( $atts ) {
        $sub_id = (int) ( $_GET['sub'] ?? 0 );
        $uuid   = isset( $_GET['audit'] ) ? sanitize_text_field( wp_unslash( $_GET['audit'] ) ) : '';

        if ( ! $sub_id || ! $uuid ) {
            return '<p class="gapnext-error">' . esc_html__( 'Invalid results link.', 'gapnext-wp' ) . '</p>';
        }

        $sub = GapNext_Audit_Manager::get_submission( $sub_id );
        if ( ! $sub || $sub->audit_uuid !== $uuid ) {
            return '<p class="gapnext-error">' . esc_html__( 'Results not found.', 'gapnext-wp' ) . '</p>';
        }

        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        if ( ! $standard ) {
            return '<p class="gapnext-error">' . esc_html__( 'Standard data not found.', 'gapnext-wp' ) . '</p>';
        }

        $is_demo = $sub->status === 'demo';
        $full_question_count = 0;
        if ( $is_demo && $standard ) {
            foreach ( $standard['clauses'] as $c ) {
                if ( (int) ( $c['level'] ?? 2 ) === 2 ) $full_question_count++;
            }
        }

        ob_start();
        include GAPNEXT_WP_DIR . 'includes/views/results.php';
        return ob_get_clean();
    }
}
