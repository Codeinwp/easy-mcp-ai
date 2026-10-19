<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








final class Url_Guard {

    
    const MAX_URLS = 100;

    
    const UNSAFE = array( '..', '\\', "\0", '*', '(' );

    






    public static function check( string $input ): string {
        $input = trim( $input );
        if ( '' === $input ) {
            throw new \InvalidArgumentException( 'An empty URL was given. Pass absolute URLs of this site, or paths starting with /.' );
        }
        self::refuse_unsafe( $input );
        $url   = self::absolute( $input );
        $parts = \wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Not an http(s) URL: %s. Pass absolute URLs of this site, or paths starting with /.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
            throw new \InvalidArgumentException( sprintf( 'Remove the query string or fragment from %s: caches store pages without them, so such a URL clears nothing or the wrong page.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return self::rebuild( $input, (string) $parts['host'], isset( $parts['path'] ) ? (string) $parts['path'] : '/' );
    }

    








    private static function refuse_unsafe( string $input ): void {
        if ( preg_match( '/[\x00-\x20\x7f]/', $input ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: a page address cannot contain spaces or control characters.', \wp_json_encode( $input ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $value = $input;
        for ( $i = 0; $i < 5; $i++ ) {
            foreach ( self::UNSAFE as $needle ) {
                if ( false !== strpos( $value, $needle ) ) {
                    throw new \InvalidArgumentException( sprintf( 'Refused %s: URLs may not contain "..", a backslash, a NUL byte, "*" or "(", encoded or not.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
            $decoded = rawurldecode( $value );
            if ( $decoded === $value ) {
                self::refuse_encoded_ascii( $input );
                return;
            }
            $value = $decoded;
        }
        throw new \InvalidArgumentException( sprintf( 'Refused %s: it is encoded too many times.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    












    private static function refuse_encoded_ascii( string $input ): void {
        if ( 1 === preg_match( '/%[0-7][0-9a-f]/i', $input ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: it holds a percent-encoded ASCII character. Only characters outside ASCII (such as %%C3%%A9) may be percent-encoded, because a cache that decodes them would clear a different folder.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    






    private static function absolute( string $input ): string {
        if ( 0 === strpos( $input, '//' ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: give the scheme (https://…) or a path starting with a single /.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 0 !== strpos( $input, '/' ) ) {
            return $input;
        }
        $home = self::home();
        return $home['scheme'] . '://' . $home['authority'] . $input;
    }

    









    private static function rebuild( string $input, string $host, string $path ): string {
        $home      = self::home();
        $canonical = \Easy_MCP_AI\Site_Host::normalize( $host );
        if ( '' === $canonical || ! in_array( $canonical, self::allowed_hosts(), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: it is not on this site (%s). Only this site\'s own pages can be cleared.', $input, $home['authority'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $path = '' === $path ? '/' : $path;
        
        
        
        
        if ( 1 === preg_match( '#[^A-Za-z0-9._~!$&\'()*+,;=:@/%\x80-\xff-]#', $path ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: a page address may hold only letters, digits, characters outside ASCII and - . _ ~ ! $ & \' ) + , ; = : @ / %%; a cache plugin strips other characters, which can point the clear at a different folder.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        
        
        $plain = self::decoded( $path );
        if ( false !== strpos( $plain, '//' ) || 1 === preg_match( '#(^|/)\.|\.(/|$)#', $plain ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: a path may not hold an empty segment, or one that starts or ends with "."; a cache that rewrites it would clear a different folder, up to the whole site.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 0 !== strpos( rtrim( $plain, '/' ) . '/', rtrim( $home['path'], '/' ) . '/' ) ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: it is outside this site\'s address %s.', $input, $home['scheme'] . '://' . $home['authority'] . $home['path'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $authority = \Easy_MCP_AI\Site_Host::normalize( $home['authority'] ) === $canonical ? $home['authority'] : strtolower( $host );
        $url       = $home['scheme'] . '://' . $authority . $path;
        self::refuse_other_blog( $input, $authority, $plain );
        return $url;
    }

    






    private static function decoded( string $path ): string {
        for ( $i = 0; $i < 5; $i++ ) {
            $next = rawurldecode( $path );
            if ( $next === $path ) {
                break;
            }
            $path = $next;
        }
        return $path;
    }

    








    private static function refuse_other_blog( string $input, string $authority, string $path ): void {
        if ( ! \is_multisite() || ! function_exists( 'get_site_by_path' ) ) {
            return;
        }
        $site = \get_site_by_path( (string) strtok( $authority, ':' ), $path );
        if ( $site && (int) $site->blog_id !== (int) \get_current_blog_id() ) {
            throw new \InvalidArgumentException( sprintf( 'Refused %s: it belongs to another site of this network.', $input ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    






    public static function home(): array {
        $home   = (string) \get_option( 'home' );
        $parts  = \wp_parse_url( $home );
        $parts  = is_array( $parts ) ? $parts : array();
        $scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : 'https';
        $host   = isset( $parts['host'] ) ? (string) $parts['host'] : '';
        $port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
        $path   = isset( $parts['path'] ) ? '/' . trim( (string) $parts['path'], '/' ) : '/';
        return array(
            'scheme'    => $scheme,
            'authority' => $host . $port,
            'path'      => '/' === $path ? '/' : $path . '/',
        );
    }

    





    public static function allowed_hosts(): array {
        $hosts = array( \Easy_MCP_AI\Site_Host::normalize( (string) \get_option( 'home' ) ) );
        foreach ( self::language_domains() as $domain ) {
            $hosts[] = \Easy_MCP_AI\Site_Host::normalize( $domain );
        }
        return array_values( array_unique( array_filter( $hosts ) ) );
    }

    




    private static function language_domains(): array {
        $domains = array();
        $wpml    = \apply_filters( 'wpml_setting', array(), 'language_domains' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
        if ( is_array( $wpml ) ) {
            $domains = array_merge( $domains, array_values( array_map( 'strval', $wpml ) ) );
        }
        if ( function_exists( 'PLL' ) && is_object( \PLL() ) && isset( \PLL()->options['domains'] ) && is_array( \PLL()->options['domains'] ) ) {
            $domains = array_merge( $domains, array_values( array_map( 'strval', \PLL()->options['domains'] ) ) );
        }
        return $domains;
    }
}
