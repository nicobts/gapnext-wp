<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * HTTP client for the GapNext FastAPI AI pipeline.
 *
 * Reads api_url and api_key from wp_options on construction.
 * All methods return an associative array on success or WP_Error on failure.
 */
class GapNext_AI_Client {

    private string $api_url;
    private string $api_key;

    public function __construct() {
        $this->api_url = rtrim( (string) get_option( 'gapnext_ai_api_url', '' ), '/' );
        $this->api_key = (string) get_option( 'gapnext_ai_api_key', '' );
    }

    /**
     * Returns true when both api_url and api_key are non-empty.
     * Does NOT verify the key against FastAPI — use check_connection() for that.
     */
    public function is_configured(): bool {
        return $this->api_url !== '' && $this->api_key !== '';
    }

    /**
     * GET /v1/auth/check — validates the bearer token, returns company name.
     *
     * @return array{status: string, company: string}|WP_Error
     */
    public function check_connection(): array|WP_Error {
        $response = wp_remote_get(
            $this->api_url . '/v1/auth/check',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Accept'        => 'application/json',
                ],
                'timeout' => 15,
            ]
        );

        return $this->_parse_response( $response, 200 );
    }

    /**
     * POST /v1/reports/generate-from-wp — triggers AI report generation.
     *
     * @param  array $payload  Full WpExport payload (PHP array, will be JSON-encoded).
     * @return array{uuid: string, download_url: string, file_size_kb: int}|WP_Error
     */
    public function generate_report( array $payload ): array|WP_Error {
        $response = wp_remote_post(
            $this->api_url . '/v1/reports/generate-from-wp',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'body'    => wp_json_encode( $payload ),
                'timeout' => 120,  // AI generation can take 30-60 s
            ]
        );

        return $this->_parse_response( $response, 200 );
    }

    /**
     * Parse a WP HTTP response.
     *
     * Returns decoded JSON body on expected status code, WP_Error otherwise.
     * Reads FastAPI's {"detail": "..."} format for error messages.
     */
    private function _parse_response( $response, int $expected_status ): array|WP_Error {
        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'gapnext_ai_http_error',
                __( 'AI Report: Could not reach the API. Check the URL in settings.', 'gapnext-wp' ),
                [ 'original' => $response->get_error_message() ]
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $body        = json_decode( $body_raw, true );

        if ( $status_code === $expected_status ) {
            return is_array( $body ) ? $body : [];
        }

        // Extract FastAPI's {"detail": "..."} message if present
        $detail = is_array( $body ) && isset( $body['detail'] )
            ? $body['detail']
            : sprintf( 'HTTP %d', $status_code );

        $message = match ( $status_code ) {
            401     => __( 'AI Report: Invalid API key. Please check your settings.', 'gapnext-wp' ),
            429     => __( 'AI Report: Monthly report quota reached.', 'gapnext-wp' ),
            408     => __( 'AI Report: Request timed out. Try again.', 'gapnext-wp' ),
            default => sprintf(
                /* translators: %s: error detail */
                __( 'AI Report: Generation failed (%s).', 'gapnext-wp' ),
                $detail
            ),
        };

        return new WP_Error( 'gapnext_ai_api_error', $message, [ 'status' => $status_code, 'detail' => $detail ] );
    }
}
