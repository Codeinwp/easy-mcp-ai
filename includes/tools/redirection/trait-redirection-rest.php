<?php
namespace Easy_MCP_AI\Tools\Redirection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Redirection_Rest {

    protected static $redirection_ns = '/redirection/v1';

    protected static $redirection_min_per_page = 5;

    protected static $redirection_max_per_page = 200;

    protected static $redirection_max_pages = 50;

    public function get_category() {
        return 'redirection';
    }

    public function get_required_capability() {
        if ( class_exists( '\\Redirection_Capabilities' ) && is_callable( array( '\\Redirection_Capabilities', 'get_plugin_access' ) ) ) {
            $cap = \Redirection_Capabilities::get_plugin_access();
            if ( is_string( $cap ) && '' !== trim( $cap ) ) {
                return trim( $cap );
            }
        }
        return 'manage_options';
    }

    protected function require_redirection(): void {
        if ( ! class_exists( '\\Red_Item' ) ) {
            throw new \RuntimeException( 'Redirection is not active on this site. Install and activate the Redirection plugin.' );
        }
    }

    protected function find_redirect( int $id ): ?array {
        $data = $this->rest_request(
            'GET',
            self::$redirection_ns . '/redirect',
            array(
                'filterBy' => array( 'id' => (string) $id ),
                'per_page' => self::$redirection_min_per_page,
                'page'     => 0,
            )
        );
        foreach ( self::rows( $data ) as $item ) {
            if ( self::int_of( $item, 'id' ) === $id ) {
                return $item;
            }
        }
        return null;
    }

    protected function require_redirect( int $id ): array {
        $item = $this->find_redirect( $id );
        if ( null === $item ) {
            throw new \InvalidArgumentException( sprintf( 'No redirect with id %d. List redirects with wp_redirection_list_redirects.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $item;
    }

    protected function fetch_groups(): array {
        $groups = array();
        $page   = 0;
        do {
            $data   = $this->rest_request( 'GET', self::$redirection_ns . '/group', array( 'per_page' => self::$redirection_max_per_page, 'page' => $page ) );
            $rows   = self::rows( $data );
            $groups = array_merge( $groups, $rows );
            $total  = self::int_of( is_array( $data ) ? $data : array(), 'total' );
            $page++;
        } while ( ! empty( $rows ) && count( $groups ) < $total && $page < self::$redirection_max_pages );
        return $groups;
    }

    protected static function no_groups_message(): string {
        return 'Redirection has no groups yet, so it cannot store a redirect. Finish its setup under Tools › Redirection in wp-admin; the setup creates the default groups.';
    }

    protected static function int_of( array $row, string $key ): int {
        return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (int) $row[ $key ] : 0;
    }

    protected static function str_of( array $row, string $key ): string {
        return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
    }

    protected static function array_of( array $row, string $key ): array {
        return isset( $row[ $key ] ) && is_array( $row[ $key ] ) ? $row[ $key ] : array();
    }

    protected static function normalize_redirect( array $item ): array {
        $action_data = self::array_of( $item, 'action_data' );
        $match_type  = '' !== self::str_of( $item, 'match_type' ) ? self::str_of( $item, 'match_type' ) : 'url';
        $source      = self::array_of( self::array_of( $item, 'match_data' ), 'source' );

        $out = array(
            'id'                    => self::int_of( $item, 'id' ),
            'source'                => self::str_of( $item, 'url' ),
            'match_type'            => $match_type,
            'action_type'           => self::str_of( $item, 'action_type' ),
            'code'                  => self::int_of( $item, 'action_code' ),
            'target'                => 'url' === $match_type && isset( $action_data['url'] ) ? self::str_of( $action_data, 'url' ) : null,
            'title'                 => self::str_of( $item, 'title' ),
            'group_id'              => self::int_of( $item, 'group_id' ),
            'position'              => self::int_of( $item, 'position' ),
            'enabled'               => ! empty( $item['enabled'] ),
            'regex'                 => ! empty( $item['regex'] ),
            'ignore_case'           => isset( $source['flag_case'] ) ? (bool) $source['flag_case'] : null,
            'ignore_trailing_slash' => isset( $source['flag_trailing'] ) ? (bool) $source['flag_trailing'] : null,
            'query_handling'        => isset( $source['flag_query'] ) ? self::str_of( $source, 'flag_query' ) : null,
            'hits'                  => self::int_of( $item, 'hits' ),
            'last_access'           => self::str_of( $item, 'last_access' ),
        );
        if ( 'url' !== $match_type ) {
            $out['action_data'] = $action_data;
        }
        return $out;
    }

    protected static function rows( $data ): array {
        $items = is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
        $rows  = array();
        foreach ( $items as $item ) {
            if ( is_object( $item ) ) {
                $item = get_object_vars( $item );
            }
            if ( is_array( $item ) ) {
                $rows[] = $item;
            }
        }
        return $rows;
    }

    protected static function paging( array $arguments ): array {
        $per_page = isset( $arguments['per_page'] ) ? (int) $arguments['per_page'] : 25;
        $per_page = min( self::$redirection_max_per_page, max( self::$redirection_min_per_page, $per_page ) );
        $page     = isset( $arguments['page'] ) ? max( 1, (int) $arguments['page'] ) : 1;
        return array( $per_page, $page, $page - 1 );
    }

    protected static function paging_result( int $total, int $page, int $per_page ): array {
        return array(
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
        );
    }

    protected static function action_types(): array {
        return array( 'url', 'error', 'nothing', 'random', 'pass' );
    }

    protected static function target_actions(): array {
        return array( 'url', 'pass' );
    }

    protected static function resolve_code( string $action_type, $sent, ?int $keep = null ): int {
        $default = self::default_code( $action_type );
        if ( null === $sent || '' === $sent ) {
            return null !== $keep && self::code_fits( $action_type, $keep ) ? $keep : $default;
        }
        if ( 0 === $default ) {
            throw new \InvalidArgumentException( sprintf( 'code does not apply to action_type "%s"; leave it out.', $action_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code = is_numeric( $sent ) ? (int) $sent : 0;
        if ( ! self::code_fits( $action_type, $code ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid code %1$s for action_type "%2$s": Redirection would store %3$d instead. Use a %4$s code.', is_scalar( $sent ) ? (string) $sent : gettype( $sent ), $action_type, $default, 'error' === $action_type ? 'HTTP error (400, 401, 403, 404, 410, 418, 451, 500-504)' : 'redirect (301, 302, 303, 304, 307, 308)' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $code;
    }

    protected static function default_code( string $action_type ): int {
        if ( 'url' === $action_type || 'random' === $action_type ) {
            return 301;
        }
        return 'error' === $action_type ? 404 : 0;
    }

    protected static function code_fits( string $action_type, int $code ): bool {
        $sanitizer = new \Red_Item_Sanitize();
        if ( 'url' === $action_type || 'random' === $action_type ) {
            return (bool) $sanitizer->is_valid_redirect_code( $code );
        }
        return 'error' === $action_type && (bool) $sanitizer->is_valid_error_code( $code );
    }

    protected function source_flags( array $arguments, array $source ): ?array {
        $sent = false;
        foreach ( array( 'ignore_case' => 'flag_case', 'ignore_trailing_slash' => 'flag_trailing', 'regex' => 'flag_regex' ) as $arg => $flag ) {
            if ( isset( $arguments[ $arg ] ) ) {
                $source[ $flag ] = rest_sanitize_boolean( $arguments[ $arg ] );
                $sent            = true;
            }
        }
        if ( isset( $arguments['query_handling'] ) && '' !== $arguments['query_handling'] ) {
            $this->refuse_unless_one_of( 'query_handling', $arguments['query_handling'], array( 'exact', 'ignore', 'pass' ), $arguments['query_handling'], 'exact, ignore or pass' );
            $source['flag_query'] = $arguments['query_handling'];
            $sent                 = true;
        }
        return $sent ? $source : null;
    }

    protected static function default_source_flags(): array {
        $options = class_exists( '\\Red_Options' ) && is_callable( array( '\\Red_Options', 'get' ) ) ? \Red_Options::get() : array();
        $options = is_array( $options ) ? $options : array();
        return array(
            'flag_query'    => isset( $options['flag_query'] ) && is_string( $options['flag_query'] ) ? $options['flag_query'] : 'exact',
            'flag_case'     => isset( $options['flag_case'] ) ? (bool) $options['flag_case'] : true,
            'flag_trailing' => isset( $options['flag_trailing'] ) ? (bool) $options['flag_trailing'] : true,
        );
    }

    protected function resolve_group( $sent ): int {
        $groups = $this->fetch_groups();
        if ( empty( $groups ) ) {
            throw new \InvalidArgumentException( self::no_groups_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $ids = array_map(
            function ( array $g ): int {
                return self::int_of( $g, 'id' );
            },
            $groups
        );
        if ( null !== $sent && '' !== $sent ) {
            $id = $this->parse_required_id( $sent, 'group_id' );
            if ( ! in_array( $id, $ids, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'No Redirection group with id %d. List groups with wp_redirection_list_groups.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return $id;
        }
        foreach ( $groups as $g ) {
            if ( ! empty( $g['default'] ) ) {
                return self::int_of( $g, 'id' );
            }
        }
        return $ids[0];
    }

    protected static function stored_source( string $source, bool $regex ): string {
        return (string) ( new \Red_Item_Sanitize() )->sanitize_url( $source, $regex );
    }

    protected static function parse_source( $value ): string {
        $source = is_string( $value ) ? trim( $value ) : '';
        if ( '' === $source ) {
            throw new \InvalidArgumentException( 'source is required: the path to redirect from, e.g. /old-page.' );
        }
        return $source;
    }

    protected function ordering( array $arguments, array $orders, array &$params ): void {
        if ( isset( $arguments['orderby'] ) && '' !== $arguments['orderby'] ) {
            $this->refuse_unless_one_of( 'orderby', $arguments['orderby'], $orders, $arguments['orderby'], implode( ', ', $orders ) );
            $params['orderby'] = $arguments['orderby'];
        }
        if ( isset( $arguments['direction'] ) && '' !== $arguments['direction'] ) {
            $this->refuse_unless_one_of( 'direction', $arguments['direction'], array( 'asc', 'desc' ), $arguments['direction'], 'asc or desc' );
            $params['direction'] = $arguments['direction'];
        }
    }
}
