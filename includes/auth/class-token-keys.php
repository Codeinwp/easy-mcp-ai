<?php
namespace Easy_MCP_AI\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















class Token_Keys {

    







    const ID_LENGTH   = 6;
    const ID_FRAGMENT = '[a-f0-9]{6}';

    
    const REASON_SHORT     = 'short';
    const REASON_DUPLICATE = 'duplicate';
    const REASON_MALFORMED = 'malformed';

    const MIN_KEY_BYTES = 32;

    
    const FILTER   = 'easy_mcp_ai_token_keys';
    const CONSTANT = 'EASY_MCP_AI_TOKEN_KEYS';

    
    private static $resolved = null;

    
    public static function reset() {
        self::$resolved = null;
    }

    
    private static function resolve() {
        if ( null !== self::$resolved ) {
            return self::$resolved;
        }

        $candidates = array(
            'filter'      => \apply_filters( self::FILTER, null ),
            'constant'    => defined( self::CONSTANT ) ? constant( self::CONSTANT ) : null,
            'environment' => getenv( self::CONSTANT ),
        );

        $parsed = array( 'keys' => array(), 'current' => '', 'errors' => array() );
        $source = '';
        foreach ( $candidates as $candidate_source => $value ) {
            if ( self::has_value( $value ) ) {
                $source = $candidate_source;
                $parsed = self::parse( $value );
                break;
            }
        }

        self::$resolved = array(
            'keys'    => $parsed['keys'],
            'current' => $parsed['current'],
            'errors'  => $parsed['errors'],
            'source'  => $source,
        );
        return self::$resolved;
    }

    private static function has_value( $value ) {
        return ( is_string( $value ) && '' !== trim( $value ) ) || ( is_array( $value ) && array() !== $value );
    }

    










    public static function parse( $raw ) {
        $entries = array();
        if ( is_array( $raw ) ) {
            $entries = array_values( $raw );
        } elseif ( is_string( $raw ) ) {
            foreach ( explode( ',', $raw ) as $entry ) {
                if ( '' !== trim( $entry ) ) {
                    $entries[] = trim( $entry );
                }
            }
        }

        $keys    = array();
        $errors  = array();
        $current = '';
        foreach ( $entries as $position => $secret ) {
            $reason = '';
            if ( ! is_string( $secret ) ) {
                $reason = self::REASON_MALFORMED;
            } elseif ( strlen( $secret ) < self::MIN_KEY_BYTES ) {
                $reason = self::REASON_SHORT;
            } elseif ( isset( $keys[ self::id_for( $secret ) ] ) ) {
                $reason = self::REASON_DUPLICATE;
            }
            if ( '' !== $reason ) {
                $errors[] = array( 'id' => '#' . ( $position + 1 ), 'reason' => $reason, 'current' => 0 === $position );
                continue;
            }
            $id          = self::id_for( $secret );
            $keys[ $id ] = $secret;
            if ( 0 === $position ) {
                $current = $id;
            }
        }

        return array( 'keys' => $keys, 'current' => $current, 'errors' => $errors );
    }

    



    public static function id_for( $secret ) {
        return substr( hash_hmac( 'sha256', 'easy-mcp-ai-key-id', (string) $secret ), 0, self::ID_LENGTH );
    }

    
    public static function current_id() {
        return self::resolve()['current'];
    }

    public static function ids() {
        return array_keys( self::resolve()['keys'] );
    }

    public static function has_key( $id ) {
        return isset( self::resolve()['keys'][ (string) $id ] );
    }

    
    public static function source() {
        return self::resolve()['source'];
    }

    
    public static function errors() {
        return self::resolve()['errors'];
    }

    




    public static function hash( $raw, $id = '' ) {
        if ( '' === (string) $id ) {
            return hash( 'sha256', (string) $raw );
        }
        $keys = self::resolve()['keys'];
        if ( ! isset( $keys[ $id ] ) ) {
            return null;
        }
        return hash_hmac( 'sha256', (string) $raw, $keys[ $id ] );
    }

    
    public static function hash_current( $raw ) {
        return self::hash( $raw, self::current_id() );
    }

    




    public static function parse_id( $raw, $prefix ) {
        if ( ! is_string( $raw ) ) {
            return '';
        }
        if ( 1 !== preg_match( '/^' . preg_quote( (string) $prefix, '/' ) . '(' . self::ID_FRAGMENT . ')_[a-f0-9]{64}$/', $raw, $m ) ) {
            return '';
        }
        return $m[1];
    }

    






    public static function signing_key() {
        $resolved = self::resolve();
        return '' !== $resolved['current'] ? $resolved['keys'][ $resolved['current'] ] : \wp_salt( 'auth' );
    }

    
    public static function mint_prefix( $prefix ) {
        $current = self::current_id();
        return '' === $current ? (string) $prefix : $prefix . $current . '_';
    }
}
