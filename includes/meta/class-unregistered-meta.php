<?php

namespace Easy_MCP_AI\Meta;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Unregistered_Meta {

    const CORE_KEYS = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_encloseme', '_pingme' );

    const CORE_PREFIXES = array( '_wp_', '_menu_item_' );

    const INTEGRATION_POST_TYPES = array(
        'product'           => array( 'WooCommerce', 'wp_wc_update_product' ),
        'product_variation' => array( 'WooCommerce', 'wp_wc_update_product_variation' ),
        'tribe_events'      => array( 'The Events Calendar', 'wp_tec_update_event' ),
        'tribe_venue'       => array( 'The Events Calendar', '' ),
        'tribe_organizer'   => array( 'The Events Calendar', '' ),
    );

    const MSG_EMPTY = 'An empty key was not written: a meta key needs a name.';

    const MSG_BYTES = '"%s" was not written: it holds a control character, a non-ASCII byte, a backslash or a trailing space, and the meta table could match it to another key\'s rows. Send the key in plain ASCII.';

    const MSG_CASE = '"%1$s" was not written: the meta table compares keys without regard to case, accents or trailing spaces, so it would change the rows of "%2$s".';

    const MSG_SEND = ' Send "%s".';

    const MSG_SAME_CALL = '"%1$s" was not written: "%2$s" was sent in the same call, and the meta table would write both to the same rows. Send one of them.';

    const MSG_REGISTERED = '"%s" is registered by a plugin or the theme without REST API access, so Easy MCP AI leaves it to them.';

    const MSG_SANITIZED = '"%s" is sanitized by a plugin or the theme with a filter of its own, so Easy MCP AI leaves it to them.';

    const MSG_ALIAS = '"%1$s" is registered for the REST API as "%2$s". Send "%2$s".';

    const MSG_ACF = '"%s" is an ACF field, saved by ACF with its own rules: write it with wp_acf_update_fields.';

    const MSG_CORE = '"%s" is WordPress\'s own bookkeeping, which this tool does not write. Set the post\'s own fields instead (featured_media, template).';

    const MSG_INTEGRATION = '"%1$s" was not written: %2$s keeps more than post meta for this item%3$s.';

    const MSG_NO_CUSTOM_FIELDS = '"%1$s" was not written: post type %2$s does not support custom fields, so the REST API carries no meta for it.';

    const MSG_PROTECTED = '"%s" starts with an underscore, a protected key nothing registers: only administrators (manage_options) can write it.';

    const MSG_VALUE = '"%1$s" was not written: a key nothing registers takes a text value (or a number), and %2$s was sent.';

    const MSG_ROWS = '"%1$s" was not written: it has %2$d values on this post, and this tool would overwrite them all with one.';

    const MSG_STORED = '"%1$s" was not written: its value on this post is %2$s, not text, and this tool would replace it with text.';

    public static function plan( int $post_id, array $meta ): array {
        $plan = array(
            'open'    => array(),
            'skipped' => array(),
        );
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || empty( $post->post_type ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return $plan;
        }
        $type       = (string) $post->post_type;
        $rest_names = self::rest_names( $type );
        $registered = self::registered_keys( $type );
        $stored     = array_map( 'strval', array_keys( (array) get_post_meta( $post_id ) ) );
        $known      = array_merge( array_keys( $rest_names ), array_keys( $registered ), self::provider_field_names(), $stored );
        $context    = array(
            'post_id'    => $post_id,
            'type'       => $type,
            'registered' => $registered,
            'known'      => $known,
        );

        foreach ( $meta as $key => $value ) {
            $key = (string) $key;
            if ( isset( $rest_names[ $key ] ) ) {
                continue;
            }
            $why = self::why_not( $key, $value, $context );
            if ( null === $why ) {
                $plan['open'][ $key ] = is_string( $value ) ? $value : (string) $value;
            } elseif ( '' !== $why ) {
                $plan['skipped'][ $key ] = $why;
            }
        }
        return self::without_same_call_spellings( $plan );
    }

    public static function run( callable $call, int $post_id, array $keys ) {
        $post   = array() === $keys ? null : get_post( $post_id );
        $type   = is_object( $post ) && ! empty( $post->post_type ) ? (string) $post->post_type : '';
        $opened = array();
        try {
            if ( '' !== $type && function_exists( 'register_post_meta' ) ) {
                foreach ( $keys as $key ) {
                    self::open( $type, (string) $key, $post_id, $opened );
                }
            }
            return $call();
        } finally {
            foreach ( $opened as $key => $hooked ) {
                self::close( (string) $key, $hooked );
            }
        }
    }

    private static function open( string $type, string $key, int $post_id, array &$opened ): void {
        $seen  = null;
        $saved = false;
        $last  = static function ( $args, $defaults, $object_type, $meta_key ) use ( $key, &$seen ) {
            if ( 'post' !== $object_type || $key !== $meta_key ) {
                return $args;
            }
            unset( $defaults['default'] );
            $args = wp_parse_args( $args, (array) $defaults );
            if ( empty( $args['auth_callback'] ) ) {
                $args['auth_callback'] = is_protected_meta( $key, 'post' ) ? '__return_false' : '__return_true';
            }
            $suffix = empty( $args['object_subtype'] ) ? '' : '_for_' . $args['object_subtype'];
            foreach ( array( 'auth' => 'auth_callback', 'sanitize' => 'sanitize_callback' ) as $hook => $field ) {
                if ( is_callable( $args[ $field ] ?? null ) ) {
                    $args[ $field ] = self::own( $args[ $field ], self::hooked_at_10( "{$hook}_post_meta_{$key}{$suffix}", $args[ $field ] ) );
                }
            }
            $seen = $args;
            return $args;
        };
        add_filter( 'register_meta_args', $last, PHP_INT_MAX, 4 );
        try {
            $saved = register_post_meta( $type, $key, self::registration( $post_id ) );
        } finally {
            remove_filter( 'register_meta_args', $last, PHP_INT_MAX );
            if ( is_array( $seen ) ) {
                $subtype        = ! empty( $seen['object_subtype'] ) ? (string) $seen['object_subtype'] : '';
                $opened[ $key ] = array(
                    'subtype' => $subtype,
                    'args'    => $seen,
                    'entry'   => $saved ? self::registered_entry( $key, $subtype ) : null,
                );
            }
        }
    }

    private static function close( string $key, array $hooked ): void {
        $subtype = (string) $hooked['subtype'];
        $entry   = $hooked['entry'];
        if ( null !== $entry && self::registered_entry( $key, $subtype ) === $entry ) {
            unregister_meta_key( 'post', $key, $subtype );
            return;
        }
        $suffix = '' === $subtype ? '' : "_for_{$subtype}";
        foreach ( array( 'auth' => 'auth_callback', 'sanitize' => 'sanitize_callback' ) as $hook => $field ) {
            $ours = $hooked['args'][ $field ] ?? null;
            if ( $ours instanceof \Closure ) {
                remove_filter( "{$hook}_post_meta_{$key}{$suffix}", $ours );
            }
        }
    }

    private static function own( callable $callback, bool $theirs ): \Closure {
        if ( $theirs ) {
            return static function ( ...$params ) {
                return $params[0] ?? null;
            };
        }
        return static function ( ...$params ) use ( $callback ) {
            return call_user_func_array( $callback, $params );
        };
    }

    private static function hooked_at_10( string $hook, callable $callback ): bool {
        $at = has_filter( $hook, $callback, 10 );
        return true === $at || 10 === $at;
    }

    private static function registered_entry( string $key, string $subtype ): ?array {
        $all = get_registered_meta_keys( 'post', $subtype );
        return isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : null;
    }

    private static function why_not( string $key, $value, array $context, bool $hint = true ): ?string {
        $post_id    = $context['post_id'];
        $type       = $context['type'];
        $registered = $context['registered'];
        if ( '' === $key ) {
            return self::MSG_EMPTY;
        }
        if ( \Easy_MCP_AI\Cli_Router\Meta_Command::unsafe_key( $key ) || false !== strpos( $key, '\\' ) ) {
            return sprintf( self::MSG_BYTES, $key );
        }
        $variant = self::folded_onto( $key, $context['known'] );
        if ( null !== $variant ) {
            return self::case_reason( $key, $variant, $value, $context, $hint );
        }
        if ( isset( $registered[ $key ] ) ) {
            $rest = $registered[ $key ]['show_in_rest'] ?? false;
            return is_array( $rest ) && isset( $rest['name'] ) && is_string( $rest['name'] ) && '' !== $rest['name']
                ? sprintf( self::MSG_ALIAS, $key, $rest['name'] )
                : sprintf( self::MSG_REGISTERED, $key );
        }
        if ( self::has_auth_filter( $key, $type ) ) {
            return sprintf( self::MSG_REGISTERED, $key );
        }
        if ( self::has_sanitize_filter( $key, $type ) ) {
            return sprintf( self::MSG_SANITIZED, $key );
        }
        if ( null !== Meta_Exposure::provider_for_key( $key ) ) {
            return '';
        }
        $lower = strtolower( $key );
        if ( null !== Meta_Exposure::provider_for_key( $lower ) ) {
            return self::case_reason( $key, $lower, $value, $context, $hint );
        }
        if ( self::is_acf_field( $key, $post_id ) ) {
            return \Easy_MCP_AI\Tools\Post_Type_Tool_Hint::may_use( 'wp_acf_update_fields' ) ? sprintf( self::MSG_ACF, $key ) : '';
        }
        if ( self::is_core_key( $lower ) ) {
            return sprintf( self::MSG_CORE, $key );
        }
        if ( isset( self::INTEGRATION_POST_TYPES[ $type ] ) ) {
            list( $plugin, $tool ) = self::INTEGRATION_POST_TYPES[ $type ];
            $use = '' !== $tool && \Easy_MCP_AI\Tools\Post_Type_Tool_Hint::may_use( $tool ) ? '; write it with ' . $tool : '';
            return sprintf( self::MSG_INTEGRATION, $key, $plugin, $use );
        }
        if ( ! post_type_supports( $type, 'custom-fields' ) ) {
            return sprintf( self::MSG_NO_CUSTOM_FIELDS, $key, $type );
        }
        if ( is_protected_meta( $key, 'post' ) && ! current_user_can( 'manage_options' ) ) {
            return sprintf( self::MSG_PROTECTED, $key );
        }
        if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
            return sprintf( self::MSG_VALUE, $key, self::describe( $value ) );
        }
        $stored = self::stored_refusal( $post_id, $key );
        if ( null !== $stored ) {
            return $stored;
        }
        $row = self::row_folded_onto( $post_id, $key );
        if ( null !== $row ) {
            return self::case_reason( $key, $row, $value, $context, $hint );
        }
        return null;
    }

    private static function case_reason( string $key, string $target, $value, array $context, bool $hint ): string {
        $reason = sprintf( self::MSG_CASE, $key, $target );
        if ( ! $hint || null !== self::why_not( $target, $value, $context, false ) ) {
            return $reason;
        }
        return $reason . sprintf( self::MSG_SEND, $target );
    }

    private static function stored_refusal( int $post_id, string $key ): ?string {
        $values = (array) get_post_meta( $post_id, $key, false );
        if ( count( $values ) > 1 ) {
            return sprintf( self::MSG_ROWS, $key, count( $values ) );
        }
        if ( 1 === count( $values ) && ! is_string( reset( $values ) ) ) {
            return sprintf( self::MSG_STORED, $key, self::describe( reset( $values ) ) );
        }
        return null;
    }

    private static function without_same_call_spellings( array $plan ): array {
        $groups = array();
        foreach ( array_keys( $plan['open'] ) as $key ) {
            $groups[ self::fold( (string) $key ) ][] = (string) $key;
        }
        foreach ( $groups as $keys ) {
            if ( count( $keys ) < 2 ) {
                continue;
            }
            foreach ( $keys as $i => $key ) {
                unset( $plan['open'][ $key ] );
                $plan['skipped'][ $key ] = sprintf( self::MSG_SAME_CALL, $key, $keys[ 0 === $i ? 1 : 0 ] );
            }
        }
        return $plan;
    }

    private static function registration( int $post_id ): array {
        return array(
            'type'          => 'string',
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => static function ( $allowed, $meta_key, $object_id, $user_id ) use ( $post_id ): bool {
                return (int) $object_id === $post_id
                    && user_can( (int) $user_id, 'edit_post', (int) $object_id )
                    && ( ! is_protected_meta( (string) $meta_key, 'post' ) || user_can( (int) $user_id, 'manage_options' ) );
            },
        );
    }

    private static function rest_names( string $type ): array {
        if ( ! class_exists( '\\WP_REST_Post_Meta_Fields' ) ) {
            return array();
        }
        $schema = ( new \WP_REST_Post_Meta_Fields( $type ) )->get_field_schema();
        return isset( $schema['properties'] ) && is_array( $schema['properties'] ) ? $schema['properties'] : array();
    }

    private static function registered_keys( string $type ): array {
        if ( ! function_exists( 'get_registered_meta_keys' ) ) {
            return array();
        }
        return (array) get_registered_meta_keys( 'post', $type ) + (array) get_registered_meta_keys( 'post', '' );
    }

    private static function provider_field_names(): array {
        $names = array();
        foreach ( Meta_Exposure::all_providers() as $provider ) {
            foreach ( array_keys( $provider->get_fields() ) as $name ) {
                $names[] = (string) $name;
            }
        }
        return $names;
    }

    private static function folded_onto( string $key, array $known ): ?string {
        $folded = self::fold( $key );
        $others = array();
        foreach ( $known as $name ) {
            $name = (string) $name;
            if ( $name === $key ) {
                continue;
            }
            if ( 1 !== preg_match( '/^[\x20-\x7E]*$/', $name ) ) {
                $others[ $name ] = $name;
            } elseif ( self::fold( $name ) === $folded ) {
                return $name;
            }
        }
        if ( array() === $others ) {
            return null;
        }
        $others  = array_values( $others );
        $matched = self::table_matches( $key, $others );
        if ( null !== $matched ) {
            return $matched[0] ?? null;
        }
        foreach ( $others as $name ) {
            if ( self::rough_fold( $name ) === $folded ) {
                return $name;
            }
        }
        return null;
    }

    private static function fold( string $key ): string {
        return strtolower( rtrim( $key, ' ' ) );
    }

    private static function rough_fold( string $key ): string {
        if ( function_exists( 'remove_accents' ) ) {
            $key = remove_accents( $key );
        }
        return self::fold( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $key ) );
    }

    private static function table_matches( string $key, array $names ): ?array {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_row' ) || empty( $wpdb->postmeta ) ) {
            return null;
        }
        $quiet = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
        try {
            $column    = $wpdb->get_row( "SHOW FULL COLUMNS FROM {$wpdb->postmeta} WHERE Field = 'meta_key'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the column's collation as it is now.
            $collation = is_object( $column ) && isset( $column->Collation ) ? (string) $column->Collation : '';
            if ( 1 !== preg_match( '/^([a-z0-9]+)_[a-z0-9_]+$/i', $collation, $charset ) ) {
                return null;
            }
            $as      = "USING {$charset[1]} ) COLLATE {$collation}";
            $params  = array();
            $columns = array();
            foreach ( array_values( $names ) as $i => $name ) {
                array_push( $params, $name, $key );
                $columns[] = "CONVERT( %s {$as} = CONVERT( %s {$as} AS m{$i}";
            }
            $select = implode( ', ', $columns );
            $flags  = $wpdb->get_row( $wpdb->prepare( "SELECT {$select}", $params ), ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- two placeholders per name; the collation is the column's own, checked above.
        } finally {
            if ( null !== $quiet ) {
                $wpdb->suppress_errors( $quiet );
            }
        }
        if ( ! is_array( $flags ) || count( $flags ) !== count( $names ) ) {
            return null;
        }
        $matched = array();
        foreach ( array_values( $names ) as $i => $name ) {
            if ( '1' === (string) $flags[ $i ] ) {
                $matched[] = $name;
            }
        }
        return $matched;
    }

    private static function row_folded_onto( int $post_id, string $key ): ?string {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_col' ) || empty( $wpdb->postmeta ) ) {
            return null;
        }
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the table's own comparison is the point; a cached copy would not have it.
        foreach ( (array) $rows as $row ) {
            if ( (string) $row !== $key ) {
                return (string) $row;
            }
        }
        return null;
    }

    private static function has_auth_filter( string $key, string $type ): bool {
        return (bool) has_filter( "auth_post_meta_{$key}_for_{$type}" ) || (bool) has_filter( "auth_post_meta_{$key}" );
    }

    private static function has_sanitize_filter( string $key, string $type ): bool {
        return (bool) has_filter( "sanitize_post_meta_{$key}_for_{$type}" ) || (bool) has_filter( "sanitize_post_meta_{$key}" );
    }

    private static function is_acf_field( string $key, int $post_id ): bool {
        if ( ! function_exists( 'acf_get_field' ) ) {
            return false;
        }
        $names = array( $key );
        if ( '_' === substr( $key, 0, 1 ) && '' !== substr( $key, 1 ) ) {
            $names[] = substr( $key, 1 );
        }
        foreach ( $names as $name ) {
            if ( is_array( acf_get_field( $name ) ) ) {
                return true;
            }
            $reference = function_exists( 'acf_get_reference' ) ? acf_get_reference( $name, $post_id ) : null;
            if ( is_string( $reference ) && '' !== $reference && is_array( acf_get_field( $reference ) ) ) {
                return true;
            }
        }
        return false;
    }

    private static function is_core_key( string $lower ): bool {
        if ( in_array( $lower, self::CORE_KEYS, true ) ) {
            return true;
        }
        foreach ( self::CORE_PREFIXES as $prefix ) {
            if ( 0 === strpos( $lower, $prefix ) ) {
                return true;
            }
        }
        return false;
    }

    private static function describe( $value ): string {
        if ( null === $value ) {
            return 'null';
        }
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( is_array( $value ) || is_object( $value ) ) {
            return 'an array or object';
        }
        return is_int( $value ) || is_float( $value ) ? 'a number' : 'a ' . gettype( $value );
    }
}
