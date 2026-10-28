<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Protected_Sources {

    const OVERRIDE = 'allow_protected_source';

    const MCP_ROUTE = 'easy-mcp-ai/v1/mcp';

    const SIGN_IN_ACTIONS = array( 'confirm_admin_email', 'postpass', 'logout', 'lostpassword', 'retrievepassword', 'resetpass', 'rp', 'register', 'checkemail', 'confirmaction', 'login', 'entered_recovery_mode' );

    const MATCHING = array(
        'Red_Item'         => array( 'get_url', 'get_match_url' ),
        'Red_Url_Request'  => array( 'get_decoded_url', 'get_original_url' ),
        'Red_Url_Match'    => array( 'get_url', 'get_url_with_params' ),
        'Red_Url'          => array( 'get_url', 'is_match' ),
        'Red_Url_Query'    => array( 'add_to_target' ),
        'Red_Source_Flags' => array( 'is_regex' ),
        'Red_Match'        => array( 'get_type', 'get_data', 'is_match', 'get_target_url' ),
        'Red_Action'       => array( 'get_type', 'needs_target' ),
    );

    public static function overridden( array $arguments ): bool {
        return isset( $arguments[ self::OVERRIDE ] ) && rest_sanitize_boolean( $arguments[ self::OVERRIDE ] );
    }

    public static function as_stored( array $body ): ?array {
        if ( ! class_exists( '\\Red_Item_Sanitize' ) || ! method_exists( '\\Red_Item_Sanitize', 'get' ) ) {
            throw new \InvalidArgumentException( self::unchecked( 'Red_Item_Sanitize::get()' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $row = ( new \Red_Item_Sanitize() )->get( $body );
        return is_array( $row ) ? $row : null;
    }

    public static function from_item( array $item ): array {
        $row = array();
        foreach ( array( 'url', 'match_url', 'match_type', 'action_type' ) as $key ) {
            $row[ $key ] = isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ? (string) $item[ $key ] : '';
        }
        $row['action_code'] = isset( $item['action_code'] ) && is_scalar( $item['action_code'] ) ? (int) $item['action_code'] : 0;
        $row['action_data'] = $item['action_data'] ?? null;
        $row['match_data']  = isset( $item['match_data'] ) && is_array( $item['match_data'] ) ? $item['match_data'] : null;
        $row['regex']       = empty( $item['regex'] ) ? 0 : 1;
        return $row;
    }

    public static function refuse( string $subject, ?array $row ): void {
        $taken = null !== $row ? self::taken_over( $row ) : null;
        if ( null !== $taken ) {
            throw new \InvalidArgumentException( self::refusal( $subject, $taken ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    public static function taken_over( array $row ): ?array {
        $missing = self::missing();
        if ( null !== $missing ) {
            throw new \InvalidArgumentException( self::unchecked( $missing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $item = self::item( $row );
        if ( null === $item->match || null === $item->action || null === $item->source_flags ) {
            return null;
        }
        if ( 'nothing' === $item->action->get_type() ) {
            return null;
        }
        foreach ( self::samples() as $sample ) {
            if ( self::fires( $item, $sample['address'] ) ) {
                return $sample;
            }
        }
        if ( $item->source_flags->is_regex() ) {
            return null;
        }
        $own = self::own_page( $item->get_url() );
        return null !== $own && self::fires( $item, $item->get_url() ) ? $own : null;
    }

    public static function refusal( string $subject, array $taken ): string {
        $why     = array(
            'the home page'    => 'every visitor would be sent away',
            'the sign-in page' => 'nobody could sign in',
            'the REST API'     => 'the editor and this connection could break',
        );
        $tail    = sprintf( ': %1$s. If the user asked for exactly this, resend with %2$s: true.', $why[ $taken['page'] ], self::OVERRIDE );
        $address = '/' === $taken['address'] ? '/' : ltrim( $taken['address'], '/' );
        $message = sprintf( '%1$s would redirect %2$s (%3$s)', $subject, $taken['page'], $address ) . $tail;
        return strlen( $message ) <= Detailed_Tool_Error::MAX_MESSAGE ? $message : sprintf( '%1$s would redirect %2$s', $subject, $taken['page'] ) . $tail;
    }

    public static function is_own_host( string $host ): bool {
        $own = array( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( site_url(), PHP_URL_HOST ) );
        if ( class_exists( '\\Redirection_Request' ) && is_callable( array( '\\Redirection_Request', 'get_server_name' ) ) ) {
            $own[] = \Redirection_Request::get_server_name();
        }
        foreach ( $own as $candidate ) {
            if ( is_string( $candidate ) && '' !== $candidate && strtolower( $candidate ) === strtolower( $host ) ) {
                return true;
            }
        }
        return false;
    }

    private static function fires( \Red_Item $item, string $address ): bool {
        $request = new \Red_Url_Request( $address );
        $decoded = $request->get_decoded_url();
        $key     = new \Red_Url_Match( $decoded );
        $keys    = array_map( array( self::class, 'lower' ), array( $key->get_url(), $key->get_url_with_params(), 'regex' ) );
        if ( ! in_array( self::lower( $item->get_match_url() ), $keys, true ) ) {
            return false;
        }
        $url = new \Red_Url( $item->get_url() );
        if ( ! $url->is_match( $decoded, $item->source_flags ) ) {
            return false;
        }
        foreach ( self::states( $item->match, $decoded ) as $matched ) {
            $fires = $item->action->needs_target() ? (bool) self::target( $item, $request, $url, $matched ) : $matched;
            if ( $fires ) {
                return true;
            }
        }
        return false;
    }

    private static function target( \Red_Item $item, \Red_Url_Request $request, \Red_Url $url, bool $matched ) {
        $target = $item->match->get_target_url( $request->get_original_url(), $url->get_url(), $item->source_flags, $matched );
        return false === $target ? false : \Red_Url_Query::add_to_target( $target, $request->get_original_url(), $item->source_flags );
    }

    private static function states( $match, string $decoded ): array {
        if ( 'server' !== $match->get_type() ) {
            return array( true, false );
        }
        $data   = $match->get_data();
        $server = is_array( $data ) && isset( $data['server'] ) && is_string( $data['server'] ) ? $data['server'] : '';
        $host   = wp_parse_url( $server, PHP_URL_HOST );
        return array_values( array_unique( array( (bool) $match->is_match( $decoded ), is_string( $host ) && self::is_own_host( $host ) ) ) );
    }

    private static function item( array $row ): \Red_Item {
        $row['status'] = 'enabled';
        if ( isset( $row['match_data'] ) && is_array( $row['match_data'] ) ) {
            $json              = array() === $row['match_data'] ? false : wp_json_encode( $row['match_data'], JSON_UNESCAPED_SLASHES );
            $row['match_data'] = is_string( $json ) ? $json : null;
        }
        return new \Red_Item( $row );
    }

    private static function own_page( string $url ): ?array {
        $pages = self::pages();
        $key   = ( new \Red_Url_Match( $url ) )->get_url();
        foreach ( $pages['sign_in'] as $address ) {
            if ( ( new \Red_Url_Match( $address ) )->get_url() === $key ) {
                return array( 'page' => 'the sign-in page', 'address' => $address );
            }
        }
        $rest = ( new \Red_Url_Match( $pages['rest'] ) )->get_url();
        if ( $key === $rest || 0 === strpos( $key, $rest . '/' ) ) {
            return array( 'page' => 'the REST API', 'address' => $pages['rest'] );
        }
        $at     = strpos( $url, '?' );
        $params = array();
        if ( false !== $at ) {
            parse_str( substr( $url, $at + 1 ), $params );
        }
        return array_key_exists( 'rest_route', $params ) ? array( 'page' => 'the REST API', 'address' => $pages['home'] . '?rest_route=' ) : null;
    }

    private static function samples(): array {
        $pages   = self::pages();
        $samples = array( array( 'page' => 'the home page', 'address' => $pages['home'] ) );
        $queries = array( '' );
        foreach ( self::SIGN_IN_ACTIONS as $action ) {
            $queries[] = '?action=' . $action;
        }
        $reauth  = wp_parse_url( wp_login_url( admin_url(), true ), PHP_URL_QUERY );
        $queries = array_merge( $queries, array( '?loggedout=true', '?checkemail=confirm', '?interim-login=1' ), is_string( $reauth ) && '' !== $reauth ? array( '?' . $reauth ) : array() );
        foreach ( $pages['sign_in'] as $path ) {
            foreach ( $queries as $query ) {
                $samples[] = array( 'page' => 'the sign-in page', 'address' => $path . $query );
            }
        }
        foreach ( array( $pages['rest'], $pages['home'] . '?rest_route=/', $pages['home'] . 'index.php?rest_route=/' ) as $base ) {
            foreach ( array( '', self::MCP_ROUTE ) as $route ) {
                $samples[] = array( 'page' => 'the REST API', 'address' => $base . $route );
            }
        }
        return $samples;
    }

    private static function pages(): array {
        return array(
            'home'    => trailingslashit( self::path_of( home_url( '/' ) ) ),
            'rest'    => trailingslashit( self::path_of( home_url( '/' . rest_get_url_prefix() . '/' ) ) ),
            'sign_in' => array_values( array_unique( array( self::path_of( site_url( '/wp-login.php' ) ), self::path_of( wp_login_url() ) ) ) ),
        );
    }

    private static function path_of( string $url ): string {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        return is_string( $path ) && '' !== $path ? $path : '/';
    }

    private static function lower( string $text ): string {
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
    }

    private static function missing(): ?string {
        foreach ( self::MATCHING as $class => $methods ) {
            if ( ! class_exists( '\\' . $class ) ) {
                return $class;
            }
            foreach ( $methods as $method ) {
                if ( ! method_exists( '\\' . $class, $method ) ) {
                    return $class . '::' . $method . '()';
                }
            }
        }
        foreach ( array( 'source_flags', 'match', 'action' ) as $property ) {
            if ( ! property_exists( '\\Red_Item', $property ) ) {
                return 'Red_Item::$' . $property;
            }
        }
        return null;
    }

    private static function unchecked( string $missing ): string {
        return sprintf( 'The source could not be checked against the home page, the sign-in page and the REST API: this Redirection version has no %1$s, which the check asks. Add the redirect under Tools › Redirection, or resend with %2$s: true if the user confirmed it may redirect one of those pages.', $missing, self::OVERRIDE );
    }
}
