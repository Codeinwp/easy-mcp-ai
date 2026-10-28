<?php
namespace Easy_MCP_AI\Tools\Site_Kit;

use Easy_MCP_AI\Compat\Shims\Sitekit_User_Binding;
use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Site_Kit_Rest {

    protected static $site_kit_ns = '/google-site-kit/v1';

    public function get_category() {
        return 'sitekit';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    protected function require_site_kit(): void {
        if ( ! defined( 'GOOGLESITEKIT_VERSION' ) ) {
            throw new \RuntimeException( 'Site Kit by Google is not active on this site. Install and activate Site Kit, then connect Google Analytics or Search Console in it.' );
        }
        $bound = Sitekit_User_Binding::bound_user_id();
        if ( null === $bound ) {
            throw new Detailed_Tool_Error(
                'Site Kit cannot be used from here: this version does not show which WordPress user it acts for.',
                sprintf( 'Site Kit %s no longer exposes its per-user storage the way Easy MCP AI finds it, so its Google connection cannot be used safely and nothing was sent to Google. Please report this to the site administrator.', GOOGLESITEKIT_VERSION ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( (int) get_current_user_id() !== $bound ) {
            throw new Detailed_Tool_Error(
                'Site Kit is still acting for another WordPress user in this request, so nothing was sent to Google.',
                'Site Kit records the user before the key is checked, and the compatibility fix that switches it (sitekit-user-binding) did not run: it may be switched off with the easy_mcp_ai_compat_shim_enabled filter. Please report this to the site administrator.'
            );
        }
    }

    protected function site_kit_get( string $path, array $params = array() ) {
        $request = new \WP_REST_Request( 'GET', self::$site_kit_ns . $path );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            throw self::site_kit_refusal( $response->as_error() );
        }
        return json_decode( (string) wp_json_encode( $response->get_data() ), true );
    }

    protected static function site_kit_refusal( \WP_Error $error ): Detailed_Tool_Error {
        $code    = (string) $error->get_error_code();
        $message = trim( wp_strip_all_tags( (string) $error->get_error_message() ) );
        $data    = $error->get_error_data();
        $data    = is_array( $data ) ? $data : array();
        $status  = isset( $data['status'] ) ? (int) $data['status'] : 0;
        $said    = '' === $message ? '' : ' Site Kit said: ' . rtrim( $message, '.' ) . '.';

        switch ( $code ) {
            case 'rest_forbidden':
                return new Detailed_Tool_Error(
                    'Site Kit does not let your account read this data.',
                    'Site Kit shows its Google data to users who connected their own Google account in Site Kit, and to users whose role the module\'s owner shared the dashboard with (Site Kit › Settings › Admin Settings › Dashboard sharing); a user it is shared with must also have opened Site Kit\'s dashboard in wp-admin once. wp_sitekit_get_status shows which applies to you.'
                );
            case 'module_not_active':
                return new Detailed_Tool_Error( 'This Site Kit module is not active on this site.', 'An administrator can connect it under Site Kit › Settings › Connect more services.' );
            case 'missing_required_setting':
                return new Detailed_Tool_Error( 'This Site Kit module is not connected to a Google property yet.', 'An administrator can finish its setup in Site Kit.' . $said );
            case 'missing_required_scopes':
                return new Detailed_Tool_Error( 'Site Kit\'s Google connection for this module lacks a permission it needs; nothing was sent to Google.', 'The module\'s owner can reconnect it from Site Kit\'s dashboard.' . $said );
            case 'invalid_analytics_4_report_metrics':
            case 'invalid_analytics_4_report_dimensions':
                return new Detailed_Tool_Error(
                    'Site Kit refused the report: it does not allow some of these metrics or dimensions.',
                    ltrim( $said . ' Users who see Site Kit\'s data through dashboard sharing can request only the metrics and dimensions Site Kit allows on its shared dashboard.' )
                );
        }
        if ( 429 === $status || isset( $data['cacheTTL'] ) ) {
            $wait = isset( $data['cacheTTL'] ) ? (int) $data['cacheTTL'] : 0;
            return new Detailed_Tool_Error(
                'Google\'s quota for this property is used up for now.',
                ( $wait > 0 ? sprintf( 'Site Kit waits %d minutes before asking Google again; do the same.', (int) ceil( $wait / 60 ) ) : 'Try again later.' ) . $said
            );
        }
        if ( isset( $data['reconnectURL'] ) ) {
            return new Detailed_Tool_Error( 'The Google connection Site Kit uses for this module must be reconnected by its owner.', 'The owner can reconnect it from Site Kit\'s dashboard.' . $said );
        }
        return new Detailed_Tool_Error( sprintf( 'Site Kit answered with an error (%s%s).', $code, $status > 0 ? ', HTTP ' . $status : '' ), ltrim( $said ) );
    }

    protected static function date_argument( array $arguments, string $key ): ?string {
        if ( ! isset( $arguments[ $key ] ) || '' === $arguments[ $key ] ) {
            return null;
        }
        $value = $arguments[ $key ];
        if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})\z/', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a date in YYYY-MM-DD format.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $value;
    }

    protected static function date_range( array $arguments, string $start_key, string $end_key ): ?array {
        $start = self::date_argument( $arguments, $start_key );
        $end   = self::date_argument( $arguments, $end_key );
        if ( null === $start && null === $end ) {
            return null;
        }
        if ( null === $start || null === $end ) {
            throw new \InvalidArgumentException( sprintf( 'Send both %s and %s, or neither.', $start_key, $end_key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( strcmp( $end, $start ) < 0 ) {
            throw new \InvalidArgumentException( sprintf( '%s (%s) must be on or after %s (%s).', $end_key, $end, $start_key, $start ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( $start, $end );
    }

    protected static function string_list( $value, string $key ): array {
        if ( null === $value ) {
            return array();
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an array of strings.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $items = array();
        foreach ( $value as $item ) {
            if ( ! is_string( $item ) || '' === trim( $item ) ) {
                throw new \InvalidArgumentException( sprintf( '%s must be an array of non-empty strings.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $items[] = trim( $item );
        }
        return array_values( array_unique( $items ) );
    }
}
