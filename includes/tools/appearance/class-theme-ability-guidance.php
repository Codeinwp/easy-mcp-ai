<?php
namespace Easy_MCP_AI\Tools\Appearance;

use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\Tools\Dynamic_Tool_Registrar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
































final class Theme_Ability_Guidance {

    
    const NONE = 'none';
    
    const CALLABLE = 'callable';
    
    const NOT_ALLOWED = 'not_allowed';
    
    const NOT_ENABLED = 'not_enabled';

    
    const LINE_LIMIT = 120;

    



    public static function assess(): array {
        $out = array(
            'case'  => self::NONE,
            'theme' => self::theme_name(),
            'tools' => array(),
        );

        $abilities = self::theme_abilities();
        if ( empty( $abilities ) || ! class_exists( '\\Easy_MCP_AI\\Tools\\Dynamic_Tool_Registrar' ) ) {
            return $out;
        }
        
        
        $server_loaded = class_exists( '\\Easy_MCP_AI\\MCP\\Server' );

        
        $enabled     = (array) get_option( 'easy_mcp_ai_enabled_abilities', array() );
        $any_enabled = false;
        foreach ( $abilities as $name => $ability ) {
            if ( ! in_array( $name, $enabled, true ) ) {
                continue;
            }
            $any_enabled = true;
            $tool        = Dynamic_Tool_Registrar::build_tool_name( $name );
            if ( $server_loaded && true === Server::current_call_may_use( $tool ) ) {
                $out['tools'][] = array(
                    $tool,
                    self::clean( method_exists( $ability, 'get_label' ) ? $ability->get_label() : '' ),
                    self::clean( method_exists( $ability, 'get_description' ) ? $ability->get_description() : '' ),
                );
            }
        }

        if ( ! empty( $out['tools'] ) ) {
            $out['case'] = self::CALLABLE;
        } elseif ( $any_enabled ) {
            $out['case'] = self::NOT_ALLOWED;
        } else {
            $out['case'] = self::NOT_ENABLED;
        }
        return $out;
    }

    




    public static function theme_abilities(): array {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return array();
        }
        $prefixes = self::theme_prefixes();
        $out      = array();
        foreach ( (array) wp_get_abilities() as $name => $ability ) {
            if ( ! is_object( $ability ) ) {
                continue;
            }
            $name     = method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : (string) $name;
            $category = method_exists( $ability, 'get_category' ) ? (string) $ability->get_category() : '';
            $slash    = strpos( $name, '/' );
            $prefix   = false === $slash ? '' : substr( $name, 0, $slash );
            if ( in_array( $category, $prefixes, true ) || ( '' !== $prefix && in_array( $prefix, $prefixes, true ) ) ) {
                $out[ $name ] = $ability;
            }
        }
        ksort( $out );
        return $out;
    }

    






    public static function theme_prefixes(): array {
        $prefixes = array();
        foreach ( array_unique( array( (string) get_template(), (string) get_stylesheet() ) ) as $slug ) {
            if ( '' === $slug ) {
                continue;
            }
            $prefixes[] = $slug;
            $key        = str_replace( '-', '_', strtolower( trim( $slug ) ) );
            $metadata   = apply_filters( $key . '_ai_connect_metadata', array() );
            if ( ! is_array( $metadata ) || ! isset( $metadata['ability_prefix'] ) ) {
                continue;
            }
            
            
            foreach ( (array) $metadata['ability_prefix'] as $declared ) {
                if ( is_string( $declared ) && preg_match( '/^[a-z0-9\-]+$/', $declared ) ) {
                    $prefixes[] = $declared;
                }
            }
        }
        return array_values( array_unique( $prefixes ) );
    }

    





    public static function tool_lines( array $tools ): string {
        $lines = array();
        foreach ( $tools as $row ) {
            $text = $row[1];
            if ( '' !== $row[2] ) {
                $text = '' === $text ? $row[2] : $text . ': ' . $row[2];
            }
            if ( mb_strlen( $text ) > self::LINE_LIMIT ) {
                $text = rtrim( mb_substr( $text, 0, self::LINE_LIMIT ) ) . '…';
            }
            $lines[] = '- ' . $row[0] . ( '' === $text ? '' : ' — ' . $text );
        }
        return implode( "\n", $lines );
    }

    
    private static function theme_name(): string {
        $name  = '';
        $theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
        if ( is_object( $theme ) && method_exists( $theme, 'get' ) ) {
            $name = self::clean( (string) $theme->get( 'Name' ) );
        }
        if ( '' === $name ) {
            $name = (string) get_stylesheet();
        }
        return mb_strlen( $name ) > 24 ? rtrim( mb_substr( $name, 0, 24 ) ) : $name;
    }

    





    private static function clean( $text ): string {
        $text = wp_strip_all_tags( (string) $text );
        return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
    }
}
