<?php





















namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Provider_Credentials {

    
    const SECRETS = array(
        'ga'        => array( 'easy_mcp_ai_ga_service_account_json' ),
        'gsc'       => array( 'easy_mcp_ai_gsc_service_account_json' ),
        'dfs'       => array( 'easy_mcp_ai_dfs_login', 'easy_mcp_ai_dfs_api_password' ),
        'semrush'   => array( 'easy_mcp_ai_semrush_api_key' ),
        'seranking' => array( 'easy_mcp_ai_seranking_api_key' ),
        'ahrefs'    => array( 'easy_mcp_ai_ahrefs_api_key' ),
    );

    
    const GOOGLE = array( 'ga', 'gsc' );

    
    const GOOGLE_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    



    public static function constant_name( string $option ): string {
        return 'EASY_MCP_AI_' . strtoupper( preg_replace( '/^easy_mcp_ai_/', '', $option ) );
    }

    
    public static function provider_of( string $option ): ?string {
        foreach ( self::SECRETS as $provider => $options ) {
            if ( in_array( $option, $options, true ) ) {
                return $provider;
            }
        }
        return null;
    }

    






    public static function source( string $option ): string {
        $name = self::constant_name( $option );
        if ( defined( $name ) ) {
            return 'constant';
        }
        return false !== getenv( $name ) ? 'environment' : '';
    }

    







    public static function is_deployed( string $provider ): bool {
        foreach ( self::SECRETS[ $provider ] ?? array() as $option ) {
            if ( '' !== self::source( $option ) ) {
                return true;
            }
        }
        return false;
    }

    








    public static function is_configured( string $provider ): bool {
        if ( ! isset( self::SECRETS[ $provider ] ) ) {
            return false;
        }
        if ( self::is_deployed( $provider ) ) {
            return true;
        }
        foreach ( self::SECRETS[ $provider ] as $option ) {
            $stored = \get_option( $option, '' );
            if ( ! is_string( $stored ) || '' === $stored ) {
                return false;
            }
        }
        return true;
    }

    





    public static function deployed_constants( string $provider ): array {
        $result = array();
        if ( ! self::is_deployed( $provider ) ) {
            return $result;
        }
        foreach ( self::SECRETS[ $provider ] as $option ) {
            $result[ self::constant_name( $option ) ] = self::source( $option );
        }
        return $result;
    }

    







    public static function deployed_secret( string $option ): ?string {
        $provider = self::provider_of( $option );
        if ( null === $provider || ! self::is_deployed( $provider ) ) {
            return null;
        }
        $name = self::constant_name( $option );
        $raw  = self::raw( $option );
        if ( null === $raw ) {
            throw new \RuntimeException(
                sprintf( '%1$s is not defined, but another %2$s credential is. Define both DataForSEO constants in wp-config.php, or neither.', $name, self::label( $provider ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \RuntimeException(
                sprintf( '%s is defined but empty or not a string. Set it to the %s credential, or remove it.', $name, self::label( $provider ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return trim( $raw );
    }

    










    public static function google_key( string $provider ): ?array {
        if ( ! in_array( $provider, self::GOOGLE, true ) || ! self::is_deployed( $provider ) ) {
            return null;
        }
        $option = self::SECRETS[ $provider ][0];
        $name   = self::constant_name( $option );
        $raw    = self::deployed_secret( $option );
        if ( 0 !== strpos( ltrim( $raw ), '{' ) ) {
            throw new \RuntimeException(
                sprintf( '%s must hold the service account key JSON itself, not a file path. Paste the contents of the key file downloaded from Google Cloud Console.', $name ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return self::check_google_key( $name, $raw );
    }

    






    public static function deployed_error( string $provider ): string {
        $problems = self::problems( $provider );
        return $problems ? (string) reset( $problems ) : '';
    }

    






    public static function report(): array {
        $deployed = array();
        $invalid  = array();
        foreach ( array_keys( self::SECRETS ) as $provider ) {
            foreach ( self::deployed_constants( $provider ) as $name => $source ) {
                $deployed[ $name ] = $source;
            }
            foreach ( array_keys( self::problems( $provider ) ) as $name ) {
                $invalid[] = $name;
            }
        }
        return array( 'deployed' => $deployed, 'invalid' => $invalid );
    }

    







    private static function problems( string $provider ): array {
        if ( ! self::is_deployed( $provider ) ) {
            return array();
        }
        $problems = array();
        foreach ( self::SECRETS[ $provider ] as $option ) {
            try {
                if ( in_array( $provider, self::GOOGLE, true ) ) {
                    self::google_key( $provider );
                } else {
                    self::deployed_secret( $option );
                }
            } catch ( \RuntimeException $e ) {
                $problems[ self::constant_name( $option ) ] = $e->getMessage();
            }
        }
        return $problems;
    }

    










    public static function fingerprint( string $provider ): string {
        if ( ! isset( self::SECRETS[ $provider ] ) ) {
            return '';
        }
        $deployed = self::is_deployed( $provider );
        $parts    = array( $deployed ? 'deployed' : 'stored' );
        foreach ( self::SECRETS[ $provider ] as $option ) {
            if ( ! $deployed ) {
                $stored = \get_option( $option, '' );
                $parts[] = is_string( $stored ) ? $stored : '';
            } elseif ( in_array( $provider, self::GOOGLE, true ) ) {
                $parts[] = (string) self::google_email( $provider );
            } else {
                $raw     = self::raw( $option );
                $parts[] = is_string( $raw ) ? trim( $raw ) : '';
            }
        }
        if ( 1 === count( array_filter( $parts, 'strlen' ) ) ) {
            return '';
        }
        return substr( hash( 'sha256', implode( "\0", $parts ) ), 0, 16 );
    }

    






    public static function google_email( string $provider ): ?string {
        try {
            $key = self::google_key( $provider );
        } catch ( \RuntimeException $e ) {
            return null;
        }
        return is_array( $key ) ? (string) $key['client_email'] : null;
    }

    
    private static function check_google_key( string $name, string $json ): array {
        $key = json_decode( $json, true );
        if ( ! is_array( $key ) ) {
            throw new \RuntimeException(
                sprintf( '%s does not hold valid JSON. Use the service account key file downloaded from Google Cloud Console.', $name ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( 'service_account' !== ( $key['type'] ?? '' ) ) {
            throw new \RuntimeException(
                sprintf( '%s is not a service account key (its type must be "service_account").', $name ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        foreach ( array( 'private_key', 'client_email', 'token_uri' ) as $field ) {
            if ( empty( $key[ $field ] ) || ! is_string( $key[ $field ] ) ) {
                throw new \RuntimeException(
                    sprintf( '%1$s is missing the required field %2$s.', $name, $field ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
        }
        if ( self::GOOGLE_TOKEN_URI !== $key['token_uri'] ) {
            throw new \RuntimeException(
                sprintf( '%1$s contains an unexpected token_uri. Only %2$s is accepted.', $name, self::GOOGLE_TOKEN_URI ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        
        
        
        
        if ( ! function_exists( 'openssl_pkey_get_private' ) ) {
            throw new \RuntimeException(
                sprintf( '%s cannot be used: the PHP OpenSSL extension, which signs Google requests, is not available on this server.', $name ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        
        
        $readable = 0 === strpos( ltrim( $key['private_key'] ), '-----BEGIN ' );
        if ( $readable ) {
            try {
                $readable = false !== openssl_pkey_get_private( $key['private_key'] );
            } catch ( \Throwable $e ) {
                
                $readable = false;
            }
        }
        if ( ! $readable ) {
            while ( openssl_error_string() ) {
                
            }
            throw new \RuntimeException(
                sprintf( '%s has a private_key OpenSSL cannot read. Use the service account key file downloaded from Google Cloud Console, unedited.', $name ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $key;
    }

    
    private static function raw( string $option ) {
        $name = self::constant_name( $option );
        if ( defined( $name ) ) {
            return constant( $name );
        }
        $env = getenv( $name );
        return false === $env ? null : $env;
    }

    private static function label( string $provider ): string {
        $labels = array(
            'ga'        => 'Google Analytics',
            'gsc'       => 'Google Search Console',
            'dfs'       => 'DataForSEO',
            'semrush'   => 'Semrush',
            'seranking' => 'SE Ranking',
            'ahrefs'    => 'Ahrefs',
        );
        return $labels[ $provider ] ?? $provider;
    }
}
