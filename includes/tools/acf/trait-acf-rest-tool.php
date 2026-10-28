<?php

namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\Tools\Post_Type_Rest_Base;
use Easy_MCP_AI\Tools\Post_Type_Tool_Hint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Acf_Rest_Tool {

    protected function require_acf() {
        if ( ! class_exists( 'ACF' ) ) {
            throw new \RuntimeException( 'Advanced Custom Fields (ACF) is not active on this site. Note: Secure Custom Fields (SCF) uses the same ACF class name so this check covers both.' );
        }
    }

    protected function acf_post_base( $value ): string {
        return Post_Type_Rest_Base::resolve( $this->validate_rest_route_segment( $value, 'post_type' ) );
    }

    protected function acf_post_route( string $base, int $post_id, string $verb ): string {
        if ( null === Post_Type_Rest_Base::served_type( $base ) ) {
            throw new \InvalidArgumentException( self::not_a_post_type( $base, $post_id, $verb ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return self::post_type_route( $base, $post_id, 'get' === $verb ? 'GET' : 'POST' );
    }

    private static function not_a_post_type( string $base, int $id, string $verb ): string {
        if ( 0 === strcasecmp( 'users', $base ) ) {
            $tool = 'get' === $verb ? 'wp_acf_get_user_fields' : 'wp_acf_update_user_fields';
            if ( Post_Type_Tool_Hint::may_use( $tool ) ) {
                return sprintf( 'Use %1$s with user_id %2$d: this tool reaches ACF fields on posts only, and "users" is not a post type.', $tool, $id );
            }
            return 'This tool reaches ACF fields on posts only, and "users" is not a post type.';
        }
        if ( self::is_taxonomy_base( $base ) ) {
            if ( 'get' === $verb && Post_Type_Tool_Hint::may_use( 'wp_acf_get_term_fields' ) ) {
                return sprintf( 'Use wp_acf_get_term_fields with term_id %1$d: this tool reaches ACF fields on posts only, and this post_type is a taxonomy: %2$s', $id, $base );
            }
            return sprintf( 'This tool reaches ACF fields on posts only, and this post_type is a taxonomy: %s', $base );
        }
        return Post_Type_Tool_Hint::not_a_post_type( $base );
    }

    private static function is_taxonomy_base( string $base ): bool {
        if ( ! function_exists( 'get_taxonomies' ) ) {
            return false;
        }
        foreach ( (array) \get_taxonomies( array( 'show_in_rest' => true ), 'objects' ) as $name => $taxonomy ) {
            if ( ! is_object( $taxonomy ) || ( ! empty( $taxonomy->rest_namespace ) && 'wp/v2' !== $taxonomy->rest_namespace ) ) {
                continue;
            }
            $served = ! empty( $taxonomy->rest_base ) ? (string) $taxonomy->rest_base : (string) ( $taxonomy->name ?? $name );
            if ( 0 === strcasecmp( $served, $base ) ) {
                return true;
            }
        }
        return false;
    }

    protected function acf_read( $type, $id, $route ) {
        $call = Acf_Rest_Call::run(
            function () use ( $route ) {
                return $this->rest_request( 'GET', $route );
            },
            $type,
            $id,
            $route,
            false
        );
        return array( 'acf_fields' => $call['data']['acf'] ?? array() )
            + Acf_Rest_Call::read_report( $call['data'], $call['opened'], $type );
    }

    protected function acf_write( $type, $id, $route, array $fields ) {
        $call = Acf_Rest_Call::run(
            function () use ( $route, $fields ) {
                return $this->rest_request( 'POST', $route, array( 'acf' => $fields ) );
            },
            $type,
            $id,
            $route,
            true
        );
        return array( 'acf_fields' => $call['data']['acf'] ?? array() )
            + Acf_Rest_Call::write_report( $fields, $call['saved'], $call['data'], $call['opened'], $type );
    }
}
