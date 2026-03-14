<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AI settings sub-section for the GapNext Settings page.
 *
 * Responsible for:
 * - Rendering AI settings fields (API URL, API key, branding fields)
 * - Saving settings via admin_post_gapnext_save_ai_settings
 * - Test-connection AJAX via wp_ajax_gapnext_ai_test_connection
 */
class GapNext_AI_Settings {

    /**
     * Render the AI settings section HTML.
     * Called from GapNext_Admin::render_settings_page().
     */
    public function render_section(): void {
        $api_url         = get_option( 'gapnext_ai_api_url', '' );
        $api_key         = get_option( 'gapnext_ai_api_key', '' );
        $primary_color   = get_option( 'gapnext_ai_branding_primary_color', '' );
        $secondary_color = get_option( 'gapnext_ai_branding_secondary_color', '' );
        $header_title    = get_option( 'gapnext_ai_branding_header_title', '' );
        $footer_text     = get_option( 'gapnext_ai_branding_footer_text', '' );
        $prepared_by     = get_option( 'gapnext_ai_branding_prepared_by', '' );
        $company_website = get_option( 'gapnext_ai_branding_company_website', '' );
        $company_phone   = get_option( 'gapnext_ai_branding_company_phone', '' );
        $is_configured   = $api_url !== '' && $api_key !== '';

        // Per-user connection status badge
        $user_id    = get_current_user_id();
        $conn_status = get_transient( 'gapnext_ai_connection_status_' . $user_id );
        ?>

        <hr style="margin:32px 0">
        <h2 style="display:flex;align-items:center;gap:10px">
            <?php esc_html_e( 'AI Report Generation', 'gapnext-wp' ); ?>
            <?php if ( $conn_status === 'ok' ) : ?>
                <span style="font-size:13px;color:#166534;background:#dcfce7;padding:3px 10px;border-radius:12px">&#x2705; <?php esc_html_e( 'Connected', 'gapnext-wp' ); ?></span>
            <?php elseif ( $conn_status === 'fail' ) : ?>
                <span style="font-size:13px;color:#991b1b;background:#fee2e2;padding:3px 10px;border-radius:12px">&#x274C; <?php esc_html_e( 'Connection failed', 'gapnext-wp' ); ?></span>
            <?php endif; ?>
        </h2>
        <p class="description" style="margin-bottom:16px">
            <?php esc_html_e( 'Enter the GapNext AI Pipeline URL and API key to enable branded AI report generation from submissions. The API key is created and managed in SQLAdmin.', 'gapnext-wp' ); ?>
        </p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'gapnext_save_ai_settings' ); ?>
            <input type="hidden" name="action" value="gapnext_save_ai_settings">

            <table class="form-table">
                <tr>
                    <th><label for="gapnext_ai_api_url"><?php esc_html_e( 'API URL', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="url" name="gapnext_ai_api_url" id="gapnext_ai_api_url"
                               class="regular-text" value="<?php echo esc_attr( $api_url ); ?>"
                               placeholder="https://api.example.com">
                        <p class="description"><?php esc_html_e( 'Base URL of the GapNext AI Pipeline (no trailing slash).', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gapnext_ai_api_key"><?php esc_html_e( 'API Key', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="password" name="gapnext_ai_api_key" id="gapnext_ai_api_key"
                               class="regular-text" value="<?php echo esc_attr( $api_key ); ?>"
                               autocomplete="new-password"
                               placeholder="xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx">
                        <button type="button" id="gapnext-ai-test-btn" class="button"
                                style="margin-left:8px" <?php echo $is_configured ? '' : 'disabled'; ?>>
                            <?php esc_html_e( 'Test Connection', 'gapnext-wp' ); ?>
                        </button>
                        <span id="gapnext-ai-test-result" style="margin-left:8px;font-size:13px"></span>
                        <p class="description"><?php esc_html_e( 'UUID issued via SQLAdmin. Treat this like a password.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Primary Color', 'gapnext-wp' ); ?></th>
                    <td>
                        <input type="color" name="gapnext_ai_branding_primary_color"
                               value="<?php echo esc_attr( $primary_color ?: '#1A2B3C' ); ?>">
                        <p class="description"><?php esc_html_e( 'Used for headings, borders, and accents in the AI report.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Secondary Color', 'gapnext-wp' ); ?></th>
                    <td>
                        <input type="color" name="gapnext_ai_branding_secondary_color"
                               value="<?php echo esc_attr( $secondary_color ?: '#F4F4F4' ); ?>">
                        <p class="description"><?php esc_html_e( 'Used for backgrounds and section fills.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gapnext_ai_branding_header_title"><?php esc_html_e( 'Report Header Title', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="text" name="gapnext_ai_branding_header_title" id="gapnext_ai_branding_header_title"
                               class="regular-text" value="<?php echo esc_attr( $header_title ); ?>"
                               placeholder="<?php esc_attr_e( 'Audit Report', 'gapnext-wp' ); ?>">
                        <p class="description"><?php esc_html_e( 'Appears in the report header and cover page. Defaults to "Audit Report" if blank.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gapnext_ai_branding_footer_text"><?php esc_html_e( 'AI Report Footer Text', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="text" name="gapnext_ai_branding_footer_text" id="gapnext_ai_branding_footer_text"
                               class="regular-text" value="<?php echo esc_attr( $footer_text ); ?>"
                               placeholder="<?php esc_attr_e( 'Proprietary and Confidential', 'gapnext-wp' ); ?>">
                        <p class="description"><?php esc_html_e( 'Appears on every page footer of the AI-generated PDF. Falls back to the PDF Footer Text setting above if blank.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gapnext_ai_branding_prepared_by"><?php esc_html_e( 'Prepared By', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="text" name="gapnext_ai_branding_prepared_by" id="gapnext_ai_branding_prepared_by"
                               class="regular-text" value="<?php echo esc_attr( $prepared_by ); ?>"
                               placeholder="<?php esc_attr_e( 'e.g. Your Consulting Firm', 'gapnext-wp' ); ?>">
                        <p class="description"><?php esc_html_e( 'Consulting firm / auditor name shown on the cover page.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gapnext_ai_branding_company_website"><?php esc_html_e( 'Consulting Firm Website', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="url" name="gapnext_ai_branding_company_website" id="gapnext_ai_branding_company_website"
                               class="regular-text" value="<?php echo esc_attr( $company_website ); ?>"
                               placeholder="https://yourconsultingfirm.com">
                        <p class="description"><?php esc_html_e( 'Your firm\'s website — shown on the AI report cover page.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="gapnext_ai_branding_company_phone"><?php esc_html_e( 'Consulting Firm Phone', 'gapnext-wp' ); ?></label></th>
                    <td>
                        <input type="text" name="gapnext_ai_branding_company_phone" id="gapnext_ai_branding_company_phone"
                               class="regular-text" value="<?php echo esc_attr( $company_phone ); ?>"
                               placeholder="+39 02 1234567">
                        <p class="description"><?php esc_html_e( 'Your firm\'s phone number — shown on the AI report cover page.', 'gapnext-wp' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Save AI Settings', 'gapnext-wp' ) ); ?>
        </form>

        <script>
        jQuery(function($){
            // Enable Test Connection button when URL + key fields are non-empty
            function _updateTestBtn() {
                var url = $('#gapnext_ai_api_url').val().trim();
                var key = $('#gapnext_ai_api_key').val().trim();
                $('#gapnext-ai-test-btn').prop('disabled', !(url && key));
            }
            $('#gapnext_ai_api_url, #gapnext_ai_api_key').on('input', _updateTestBtn);

            $('#gapnext-ai-test-btn').on('click', function() {
                var $btn    = $(this);
                var $result = $('#gapnext-ai-test-result');
                $btn.prop('disabled', true).text(<?php echo wp_json_encode( __( 'Testing\u2026', 'gapnext-wp' ) ); ?>);
                $result.text('').css('color', '');
                $.post(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
                    action: 'gapnext_ai_test_connection',
                    nonce:  <?php echo wp_json_encode( wp_create_nonce( 'gapnext_ai_test_connection' ) ); ?>,
                    api_url: $('#gapnext_ai_api_url').val(),
                    api_key: $('#gapnext_ai_api_key').val(),
                }).done(function(resp) {
                    if (resp.success) {
                        $result.text('\u2705 ' + <?php echo wp_json_encode( __( 'Connected \u2014 ', 'gapnext-wp' ) ); ?> + resp.data.company).css('color', '#166534');
                    } else {
                        $result.text('\u274C ' + (resp.data && resp.data.message ? resp.data.message : <?php echo wp_json_encode( __( 'Connection failed', 'gapnext-wp' ) ); ?>)).css('color', '#991b1b');
                    }
                }).fail(function() {
                    $result.text('\u274C <?php echo esc_js( __( 'Request error. Check browser console.', 'gapnext-wp' ) ); ?>').css('color', '#991b1b');
                }).always(function() {
                    $btn.prop('disabled', false).text(<?php echo wp_json_encode( __( 'Test Connection', 'gapnext-wp' ) ); ?>);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Save AI settings (admin_post_gapnext_save_ai_settings).
     * Registered in GapNext_Admin::__construct().
     */
    public function save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Unauthorized.', 'gapnext-wp' ) );
        check_admin_referer( 'gapnext_save_ai_settings' );

        update_option( 'gapnext_ai_api_url',                    esc_url_raw( $_POST['gapnext_ai_api_url']                    ?? '' ) );
        update_option( 'gapnext_ai_api_key',                    sanitize_text_field( $_POST['gapnext_ai_api_key']            ?? '' ) );
        update_option( 'gapnext_ai_branding_primary_color',     sanitize_hex_color(  $_POST['gapnext_ai_branding_primary_color']   ?? '' ) ?: '' );
        update_option( 'gapnext_ai_branding_secondary_color',   sanitize_hex_color(  $_POST['gapnext_ai_branding_secondary_color'] ?? '' ) ?: '' );
        update_option( 'gapnext_ai_branding_header_title',      sanitize_text_field( $_POST['gapnext_ai_branding_header_title']    ?? '' ) );
        update_option( 'gapnext_ai_branding_footer_text',       sanitize_text_field( $_POST['gapnext_ai_branding_footer_text']     ?? '' ) );
        update_option( 'gapnext_ai_branding_prepared_by',       sanitize_text_field( $_POST['gapnext_ai_branding_prepared_by']     ?? '' ) );
        update_option( 'gapnext_ai_branding_company_website',   esc_url_raw(         $_POST['gapnext_ai_branding_company_website'] ?? '' ) );
        update_option( 'gapnext_ai_branding_company_phone',     sanitize_text_field( $_POST['gapnext_ai_branding_company_phone']   ?? '' ) );

        wp_safe_redirect( add_query_arg( [ 'page' => 'gapnext-settings', 'updated' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Test connection AJAX handler (wp_ajax_gapnext_ai_test_connection).
     * Registered in GapNext_Admin::__construct().
     * Uses api_url + api_key from the POST body (not yet saved to options).
     */
    public function handle_test_connection(): void {
        check_ajax_referer( 'gapnext_ai_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        // Use the values from the POST body so users can test before saving
        $api_url = esc_url_raw( $_POST['api_url'] ?? '' );
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

        if ( empty( $api_url ) || empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => __( 'API URL and key are required.', 'gapnext-wp' ) ] );
        }

        // Temporarily override options for this request only — do NOT call update_option
        // Create a one-shot client using the provided credentials
        $response = wp_remote_get(
            rtrim( $api_url, '/' ) . '/v1/auth/check',
            [
                'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json' ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => __( 'AI Report: Could not reach the API. Check the URL.', 'gapnext-wp' ) ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 ) {
            $user_id = get_current_user_id();
            set_transient( 'gapnext_ai_connection_status_' . $user_id, 'ok', HOUR_IN_SECONDS );
            wp_send_json_success( [ 'company' => $body['company'] ?? '' ] );
        } else {
            $user_id = get_current_user_id();
            set_transient( 'gapnext_ai_connection_status_' . $user_id, 'fail', HOUR_IN_SECONDS );
            $detail = is_array( $body ) && isset( $body['detail'] ) ? sanitize_text_field( $body['detail'] ) : "HTTP $code";
            wp_send_json_error( [ 'message' => $detail ] );
        }
    }
}
