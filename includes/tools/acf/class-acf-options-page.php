<?php

namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Post_Type_Tool_Hint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Acf_Options_Page {

    const LAYOUT_KEYS = array( 'acf_fc_layout', 'acf_fc_layout_disabled', 'acf_fc_layout_custom_label' );

    public static function listing(): array {
        if ( ! function_exists( 'acf_get_options_pages' ) ) {
            return array();
        }
        $pages = acf_get_options_pages();
        $list  = array();
        foreach ( is_array( $pages ) ? $pages : array() as $slug => $page ) {
            if ( ! is_array( $page ) || ! current_user_can( self::capability( $page ) ) || 'option' !== self::storage( $page )['type'] ) {
                continue;
            }
            $slug   = (string) ( $page['menu_slug'] ?? $slug );
            $list[] = array(
                'slug'         => $slug,
                'title'        => (string) ( $page['page_title'] ?? '' ),
                'field_groups' => array_values( array_map( 'strval', array_column( self::groups( $slug ), 'key' ) ) ),
            );
        }
        return $list;
    }

    public static function read( string $slug ): array {
        $post_id = self::resolve( $slug )['post_id'];
        $fields  = self::fields( $slug );
        $result  = array(
            'options_page' => $slug,
            'acf_fields'   => self::values( $post_id, $fields ),
        );
        if ( empty( $fields ) ) {
            $result['notice'] = 'No ACF field group is assigned to this options page, so it has no values.';
        }
        return $result;
    }

    public static function write( string $slug, array $sent ): array {
        $target = self::resolve( $slug );
        $fields = self::fields( $slug );
        if ( empty( $fields ) ) {
            throw new \InvalidArgumentException( 'Nothing was saved: no ACF field group is assigned to this options page.' );
        }
        $input = self::to_input( $sent, $fields );
        if ( empty( $input['values'] ) ) {
            throw new Detailed_Tool_Error(
                'Nothing was saved: no field sent belongs to this options page.' . ( Post_Type_Tool_Hint::may_use( 'wp_acf_get_fields' ) ? ' wp_acf_get_fields with this options_page lists its field names.' : '' ),
                empty( $input['ignored'] ) ? 'fields was empty.' : 'Not fields of this page: ' . implode( ', ', $input['ignored'] ) . '.'
            );
        }

        self::save( $target['page'], $target['post_id'], $input['values'], self::as_screen_posts( $input['values'], $fields, $target['post_id'] ), self::names( $fields ) );

        $result = array(
            'options_page' => $slug,
            'acf_fields'   => self::values( $target['post_id'], $fields ),
        );
        if ( ! empty( $input['ignored'] ) ) {
            $result['fields_ignored'] = $input['ignored'];
            $result['notice']         = sprintf( 'These were sent but not saved, because no field or sub field of this options page has that name or key: %s.', implode( ', ', $input['ignored'] ) );
        }
        return $result;
    }

    public static function resolve( string $slug ): array {
        if ( ! function_exists( 'acf_get_options_page' ) ) {
            throw new \RuntimeException( 'This site\'s ACF has no options pages: ACF PRO and Secure Custom Fields add them, ACF\'s free build does not.' );
        }
        $page = acf_get_options_page( $slug );
        if ( ! is_array( $page ) ) {
            throw self::not_found( $slug );
        }
        $capability = self::capability( $page );
        if ( ! current_user_can( $capability ) ) {
            if ( '' === $capability ) {
                throw new \RuntimeException( 'This options page names no capability, so WordPress opens it to no one but a multisite super admin.' );
            }
            throw new \RuntimeException( sprintf( 'You cannot open this options page: it requires the "%s" capability.', $capability ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $storage = self::storage( $page );
        if ( 'option' !== $storage['type'] ) {
            throw new \InvalidArgumentException( sprintf( 'This options page keeps its values on %s %s, not in the site options, so they are that object\'s ACF fields: the ACF tools for it reach them.', $storage['type'], $storage['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'page'    => $page,
            'post_id' => $storage['post_id'],
        );
    }

    private static function not_found( string $slug ): Detailed_Tool_Error {
        $open    = array_column( self::listing(), 'slug' );
        $details = empty( $open ) ? 'You can open no options page on this site.' : 'Options pages you can open: ' . implode( ', ', $open ) . '.';
        if ( ! empty( self::groups( $slug ) ) ) {
            return new Detailed_Tool_Error( sprintf( 'Field groups target this options page, but the site registers it on admin screens only, so it is not reachable here: %s', $slug ), $details );
        }
        $where = Post_Type_Tool_Hint::may_use( 'wp_acf_list_field_groups' ) ? ' (wp_acf_list_field_groups lists them under options_pages)' : '';
        return new Detailed_Tool_Error( sprintf( 'No ACF options page has this slug%s: %s', $where, $slug ), $details );
    }

    private static function capability( array $page ): string {
        if ( ! array_key_exists( 'capability', $page ) ) {
            return 'edit_posts';
        }
        return is_string( $page['capability'] ) ? $page['capability'] : '';
    }

    private static function storage( array $page ): array {
        $post_id = acf_get_valid_post_id( $page['post_id'] ?? 'options' );
        $decoded = acf_decode_post_id( $post_id );
        return array(
            'post_id' => (string) $post_id,
            'type'    => (string) ( $decoded['type'] ?? '' ),
            'id'      => (string) ( $decoded['id'] ?? '' ),
        );
    }

    private static function groups( string $slug ): array {
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            return array();
        }
        $groups = acf_get_field_groups( array( 'options_page' => $slug ) );
        return is_array( $groups ) ? array_values( array_filter( $groups, 'is_array' ) ) : array();
    }

    private static function fields( string $slug ): array {
        $fields = array();
        foreach ( self::groups( $slug ) as $group ) {
            foreach ( (array) acf_get_fields( (string) ( $group['key'] ?? '' ) ) as $field ) {
                $type = is_array( $field ) ? acf_get_field_type( $field['type'] ?? '' ) : null;
                if ( is_object( $type ) && ! empty( $type->show_in_rest ) ) {
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    private static function values( string $post_id, array $fields ): array {
        $format = acf_get_setting( 'rest_api_format' );
        $format = is_string( $format ) && '' !== $format ? $format : 'light';
        $values = array();
        foreach ( $fields as $field ) {
            $values[ (string) $field['name'] ] = acf_format_value_for_rest( acf_get_value( $post_id, $field ), $post_id, $field, $format );
        }
        return $values;
    }

    private static function names( array $fields ): array {
        $names = array();
        foreach ( $fields as $field ) {
            if ( ! is_array( $field ) || ! isset( $field['key'], $field['name'] ) ) {
                continue;
            }
            $names[ (string) $field['key'] ] = (string) $field['name'];
            $clone                           = self::clone_key( $field );
            if ( null !== $clone ) {
                $names[ $clone ] = '';
            }
            $names += self::names( self::nested_fields( $field ) );
        }
        return $names;
    }

    private static function to_input( array $sent, array $fields ): array {
        $values  = array();
        $ignored = array();
        foreach ( $sent as $name => $value ) {
            $name  = (string) $name;
            $field = acf_search_fields( $name, $fields );
            if ( ! is_array( $field ) ) {
                $ignored[] = $name;
                continue;
            }
            $key   = (string) $field['key'];
            $value = self::keyed( $value, $field, $name, $ignored );
            $clone = self::clone_key( $field );
            if ( null !== $clone ) {
                $values[ $clone ][ $key ] = $value;
            } else {
                $values[ $key ] = $value;
            }
        }
        return array(
            'values'  => $values,
            'ignored' => $ignored,
        );
    }

    private static function keyed( $value, array $field, string $path, array &$ignored ) {
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $sub_fields = self::sub_fields( $field );
        switch ( $field['type'] ?? '' ) {
            case 'group':
                return self::keyed_row( $value, $sub_fields, $path, $ignored );
            case 'repeater':
            case 'flexible_content':
                $rows = array();
                foreach ( $value as $i => $row ) {
                    $layout = 'repeater' === $field['type'] ? $sub_fields : ( is_array( $row ) ? self::layout( $field, $row['acf_fc_layout'] ?? null ) : null );
                    if ( ! is_array( $row ) || null === $layout ) {
                        $ignored[] = $path . '.' . $i;
                        continue;
                    }
                    $rows[] = self::keyed_row( $row, $layout, $path . '.' . $i, $ignored, 'flexible_content' === $field['type'] );
                }
                return $rows;
        }
        return $value;
    }

    private static function keyed_row( array $row, array $sub_fields, string $path, array &$ignored, bool $layout_row = false ): array {
        $keyed = array();
        foreach ( $row as $name => $value ) {
            $name = (string) $name;
            if ( $layout_row && in_array( $name, self::LAYOUT_KEYS, true ) ) {
                $keyed[ $name ] = $value;
                continue;
            }
            $sub = acf_search_fields( $name, $sub_fields );
            if ( ! is_array( $sub ) ) {
                $ignored[] = $path . '.' . $name;
                continue;
            }
            $keyed[ (string) $sub['key'] ] = null === $value ? '' : self::keyed( $value, $sub, $path . '.' . $name, $ignored );
        }
        return $keyed;
    }

    private static function layout( array $field, $name ): ?array {
        foreach ( self::layouts( $field ) as $layout ) {
            if ( is_array( $layout ) && is_string( $name ) && ( $layout['name'] ?? null ) === $name ) {
                return self::sub_fields( $layout );
            }
        }
        return null;
    }

    private static function clone_key( array $field ): ?string {
        return isset( $field['prefix'] ) && is_string( $field['prefix'] ) && preg_match( '/^acf\[([^\]]+)\]$/', $field['prefix'], $clone ) ? $clone[1] : null;
    }

    private static function sub_fields( array $field ): array {
        return isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array();
    }

    private static function layouts( array $field ): array {
        return isset( $field['layouts'] ) && is_array( $field['layouts'] ) ? $field['layouts'] : array();
    }

    private static function nested_fields( array $field ): array {
        $nested = self::sub_fields( $field );
        foreach ( self::layouts( $field ) as $layout ) {
            $nested = array_merge( $nested, is_array( $layout ) ? self::sub_fields( $layout ) : array() );
        }
        return $nested;
    }

    private static function has_paginated_repeater( array $field ): bool {
        if ( 'repeater' === ( $field['type'] ?? '' ) && ! empty( $field['pagination'] ) ) {
            return true;
        }
        foreach ( self::nested_fields( $field ) as $nested ) {
            if ( is_array( $nested ) && self::has_paginated_repeater( $nested ) ) {
                return true;
            }
        }
        return false;
    }

    private static function as_screen_posts( array $values, array $fields, string $post_id ): array {
        foreach ( $fields as $field ) {
            $key   = (string) $field['key'];
            $clone = self::clone_key( $field );
            $slot  = null === $clone ? $values : ( $values[ $clone ] ?? null );
            if ( ! is_array( $slot ) || ! array_key_exists( $key, $slot ) || ! self::has_paginated_repeater( $field ) ) {
                continue;
            }
            $posted = self::screen_value( $slot[ $key ], $field, $post_id, false );
            if ( null === $clone ) {
                $values[ $key ] = $posted;
            } else {
                $values[ $clone ][ $key ] = $posted;
            }
        }
        return $values;
    }

    private static function screen_value( $value, array $field, string $post_id, bool $fresh ) {
        $type = $field['type'] ?? '';
        $name = (string) $field['name'];
        if ( 'group' === $type ) {
            return is_array( $value ) ? self::screen_row( $value, self::sub_fields( $field ), $name . '_', $post_id, $fresh ) : $value;
        }
        if ( 'repeater' !== $type && 'flexible_content' !== $type ) {
            return $value;
        }
        $rows    = is_array( $value ) ? $value : array();
        $layouts = 'flexible_content' === $type && ! $fresh ? acf_get_metadata( $post_id, $name ) : null;
        foreach ( $rows as $i => $row ) {
            $sub_fields = 'repeater' === $type ? self::sub_fields( $field ) : (array) self::layout( $field, $row['acf_fc_layout'] ?? null );
            $replaced   = is_array( $layouts ) && isset( $layouts[ $i ] ) && $layouts[ $i ] !== ( $row['acf_fc_layout'] ?? null );
            $rows[ $i ] = self::screen_row( $row, $sub_fields, $name . '_' . $i . '_', $post_id, $fresh || $replaced );
        }
        if ( 'flexible_content' === $type || empty( $field['pagination'] ) ) {
            return is_array( $value ) ? $rows : $value;
        }
        $stored = $fresh ? array() : acf_get_value( $post_id, $field );
        $count  = is_array( $stored ) ? count( $stored ) : 0;
        $paged  = array();
        foreach ( $rows as $i => $row ) {
            $paged[ $i < $count ? 'row-' . $i : $i ] = $row;
        }
        for ( $i = count( $rows ); $i < $count; $i++ ) {
            $paged[ 'row-' . $i ] = array( 'acf_deleted' => '1' );
        }
        return $paged;
    }

    private static function screen_row( array $row, array $sub_fields, string $prefix, string $post_id, bool $fresh ): array {
        foreach ( $sub_fields as $sub ) {
            $key = (string) ( $sub['key'] ?? '' );
            if ( array_key_exists( $key, $row ) && self::has_paginated_repeater( $sub ) ) {
                $row[ $key ] = self::screen_value( $row[ $key ], array( 'name' => $prefix . ( $sub['name'] ?? '' ) ) + $sub, $post_id, $fresh );
            }
        }
        return $row;
    }

    private static function save( array $page, string $post_id, array $values, array $posted, array $names ): void {
        global $wp_actions;
        $post     = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- saved to be restored, not read.
        $autoload = acf_get_setting( 'autoload' );
        $runs     = $wp_actions['acf/save_post'] ?? null;
        $_POST['acf'] = wp_slash( $values );
        try {
            acf_reset_validation_errors();
            acf_validate_save_post( false );
            $errors = acf_get_validation_errors();
            acf_reset_validation_errors();
            if ( ! empty( $errors ) ) {
                throw self::invalid( (array) $errors, $names );
            }
            $_POST['acf'] = wp_slash( $posted );
            acf_update_setting( 'autoload', $page['autoload'] ?? false );
            acf_save_post( $post_id );
            do_action( 'acf/options_page/save', $post_id, $page['menu_slug'] ?? '' );
        } finally {
            $_POST = $post;
            acf_update_setting( 'autoload', $autoload );
            if ( null === $runs ) {
                unset( $wp_actions['acf/save_post'] );
            } else {
                $wp_actions['acf/save_post'] = $runs; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- puts back the count acf_save_post() raised.
            }
        }
    }

    private static function invalid( array $errors, array $names ): Detailed_Tool_Error {
        $lines = array();
        foreach ( $errors as $error ) {
            $input = is_array( $error ) && isset( $error['input'] ) ? (string) $error['input'] : '';
            preg_match_all( '/\[([^\]]*)\]/', $input, $parts );
            $path = array();
            foreach ( $parts[1] as $part ) {
                $name = $names[ $part ] ?? $part;
                if ( '' !== $name ) {
                    $path[] = $name;
                }
            }
            $path    = implode( '.', $path );
            $message = is_array( $error ) && isset( $error['message'] ) ? wp_strip_all_tags( (string) $error['message'] ) : '';
            $lines[] = ( '' !== $path ? $path . ': ' : '' ) . $message;
        }
        return new Detailed_Tool_Error(
            sprintf( 'Nothing was saved: ACF refused %d value(s) on this options page. The details name each field and the reason.', count( $lines ) ),
            implode( "\n", $lines )
        );
    }
}
