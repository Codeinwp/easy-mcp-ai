<?php

namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Validator {

    public static function destructive_tools() {
        return array(
            'wp_delete_post',
            'wp_delete_page',
            'wp_delete_media',
            'wp_delete_comment',
            'wp_delete_category',
            'wp_delete_tag',
            'wp_delete_block',
            'wp_delete_cpt_item',
            'wp_delete_menu',
            'wp_delete_menu_item',
            'wp_delete_revision',
            'wp_delete_user_meta',
            'wp_create_user',
            'wp_update_user',
            'wp_delete_user',
            'wp_update_user_meta',
            'wp_update_site_settings',
            'wp_update_template',
            'wp_update_global_styles',
            'wp_run_cron_event',
            'wp_activate_plugin',
            'wp_deactivate_plugin',
            'wp_update_plugin',
            'wp_switch_theme',
            'wp_update_theme',
            'wp_update_theme_mod',
            'wp_delete_theme_mod',
            'wp_update_custom_css',
            'wp_rest_delete',
        );
    }

    public static function parse_patterns( $raw ) {
        $parts = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
        $clean = array();
        foreach ( $parts as $part ) {
            $part = is_string( $part ) ? trim( $part ) : '';
            if ( '' !== $part ) {
                $clean[] = $part;
            }
        }
        return $clean;
    }

    public static function is_valid_pattern( $pattern ) {
        return is_string( $pattern ) && '' !== $pattern && 1 === preg_match( '/^[A-Za-z0-9_*?\[\]!\-]+$/', $pattern );
    }

    public static function is_valid_approval_entry( $entry ): bool {
        if ( self::is_valid_pattern( $entry ) ) {
            return true;
        }
        if ( ! is_string( $entry ) || 1 !== preg_match( '/^(\S+) +(\S+)$/', $entry, $parts ) ) {
            return false;
        }
        return self::is_valid_pattern( $parts[1] )
            && 1 === preg_match( '#^/[A-Za-z0-9_.~%@{}*?!\[\]/\-]*$#', $parts[2] )
            && 1 !== preg_match( '/\[!?\]?[^\]]*\[/', $parts[2] );
    }

    public static function sanitize_ip_whitelist( $raw ) {
        $raw = is_string( $raw ) ? $raw : '';
        if ( '' === trim( $raw ) ) {
            return array( 'value' => '', 'invalid' => array() );
        }
        $lines   = preg_split( '/\r\n|\r|\n/', $raw );
        $valid   = array();
        $invalid = array();
        foreach ( $lines as $line ) {
            list( $ip_part ) = explode( '#', $line, 2 );
            $ip_part         = trim( $ip_part );

            if ( '' === $ip_part ) {
                continue;
            }

            if ( false !== strpos( $ip_part, '/' ) ) {
                if ( self::is_valid_cidr( $ip_part ) ) {
                    $valid[] = $ip_part;
                } else {
                    $invalid[] = $line;
                }
            } elseif ( filter_var( $ip_part, FILTER_VALIDATE_IP ) ) {
                $valid[] = $ip_part;
            } else {
                $invalid[] = $line;
            }
        }
        return array(
            'value'   => implode( "\n", $valid ),
            'invalid' => $invalid,
        );
    }

    public static function is_valid_cidr( $cidr ) {
        if ( substr_count( $cidr, '/' ) !== 1 ) {
            return false;
        }
        list( $subnet, $prefix ) = explode( '/', $cidr, 2 );
        if ( ! ctype_digit( $prefix ) ) {
            return false;
        }
        $prefix_int = (int) $prefix;
        if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return $prefix_int <= 32;
        }
        if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return $prefix_int <= 128;
        }
        return false;
    }

    public static function integer_in_range( $raw, $min, $max ) {
        if ( is_bool( $raw ) ) {
            return null;
        }
        if ( is_string( $raw ) ) {
            $raw = trim( $raw );
            if ( ! preg_match( '/^\d+$/D', $raw ) ) {
                return null;
            }
            $raw = (int) $raw;
        }
        if ( ! is_int( $raw ) || $raw < $min || $raw > $max ) {
            return null;
        }
        return $raw;
    }
}
