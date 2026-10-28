<?php

namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Text_Redactor {

    const MASK = '[REDACTED]';

    const SENSITIVE_KEY_PATTERN = '/pass(word|wd)?|pwd|secret|token|api[_\-]?key|apikey|salt|auth[_\-]?key|private[_\-]?key|credential/i';

    public static function redact( $text, bool $heuristic = true ) {
        if ( ! is_string( $text ) || '' === $text ) {
            return is_string( $text ) ? $text : '';
        }

        $text = preg_replace( '#\b([a-z][a-z0-9+.\-]*://)[^/\s:@]+:[^/\s@]*@#i', '$1' . self::MASK . '@', $text );

        $text = preg_replace( '/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=\-]{8,}/i', '$1 ' . self::MASK, $text );

        $text = preg_replace( '/\bwpmcp_[A-Za-z0-9_]{8,}/', 'wpmcp_' . self::MASK, $text );

        $text = preg_replace( '/\beyJ[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}/', self::MASK, $text );

        $text = preg_replace(
            '/(define\s*\(\s*[\'"][A-Z0-9_]*(?:KEY|SALT|PASSWORD|SECRET|TOKEN)[A-Z0-9_]*[\'"]\s*,\s*)([\'"])(?:(?!\2).)*\2/',
            '$1$2' . self::MASK . '$2',
            $text
        );

        $text = preg_replace(
            '/(?<![A-Za-z0-9])((?:password|passwd|pass|pwd|secret|client_secret|token|access_token|refresh_token|api[_\-]?key|apikey|auth_key|private_key)["\'\]]?\s*(?:=>|[=:])\s*["\']?)([^\s"\'&,;<>]+)/i',
            '$1' . self::MASK,
            $text
        );

        if ( ! $heuristic ) {
            return $text;
        }

        $text = preg_replace_callback(
            '/\S{32,}/',
            static function ( $m ) {
                $s = $m[0];
                if ( false !== strpos( $s, self::MASK ) ) {
                    return $s;
                }
                if ( preg_match( '/[a-z]/', $s ) && preg_match( '/[A-Z]/', $s ) && preg_match( '/[0-9]/', $s )
                    && preg_match( '/[!@#$^*()\[\]{}<>~+|,;`]/', $s ) ) {
                    return self::MASK;
                }
                return $s;
            },
            $text
        );

        return $text;
    }

    public static function redact_value( $value, $key = '', $depth = 0 ) {
        if ( '' !== (string) $key && ! is_int( $key ) && preg_match( self::SENSITIVE_KEY_PATTERN, (string) $key ) ) {
            return self::MASK;
        }
        if ( is_array( $value ) ) {
            if ( $depth >= 8 ) {
                return self::MASK;
            }
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ $k ] = self::redact_value( $v, $k, $depth + 1 );
            }
            return $out;
        }
        if ( is_object( $value ) ) {
            return '[object ' . get_class( $value ) . ']';
        }
        if ( is_string( $value ) ) {
            return self::redact( $value );
        }
        return $value;
    }
}
