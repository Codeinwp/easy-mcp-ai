<?php





























namespace Easy_MCP_AI\Skills;

use Easy_MCP_AI\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-config.php';
require_once __DIR__ . '/class-skill-trust.php';
require_once __DIR__ . '/class-release-verifier.php';
require_once __DIR__ . '/class-local-skills.php';

final class Skill_Sync {

    const HOOK = 'easy_mcp_ai_skills_sync';

    
    const OPTION_RELEASE = 'easy_mcp_ai_skills_release';

    
    const OPTION_STATE = 'easy_mcp_ai_skills_state';

    const BASE_URL = 'https://skills.easymcpai.com';

    
    const BASE_URL_FILTER = 'easy_mcp_ai_skills_base_url';

    const TIMEOUT = 10;

    





    const MIN_INTERVAL = 72000;

    
    public static function enabled(): bool {
        return true === Config::get( 'skills_sync' ) && null === Local_Skills::directory();
    }

    






    public static function arm(): bool {
        if ( ! self::enabled() ) {
            return false;
        }
        if ( empty( self::state()['armed_at'] ) ) {
            
            $state             = self::state( true );
            $state['armed_at'] = time();
            self::write( self::OPTION_STATE, $state, true );
        }
        if ( false === \wp_next_scheduled( self::HOOK ) ) {
            \wp_schedule_event( time(), 'daily', self::HOOK );
        }
        return true;
    }

    





    public static function run(): string {
        $state = self::state( true );
        if ( ! self::enabled() ) {
            return self::finish( 'disabled' );
        }
        
        
        if ( isset( $state['last_check'] ) && time() - $state['last_check'] < self::MIN_INTERVAL ) {
            return 'recent';
        }

        $base = self::base_url();
        $sig  = self::fetch( $base . '/v1/release.sig', Skill_Trust::SIGNATURE_MAX_BYTES );
        if ( ! $sig['ok'] ) {
            return self::finish( $sig['error'], array( $sig['detail'] ) );
        }
        $file = json_decode( $sig['body'], true );
        if ( ! is_array( $file ) || ! isset( $file['serial'] ) || ! is_int( $file['serial'] ) || $file['serial'] < 1 ) {
            return self::finish( 'malformed' );
        }
        $current = isset( $state['serial'] ) ? $state['serial'] : null;
        if ( null !== $current && $file['serial'] <= $current ) {
            return self::finish( 'up_to_date' );
        }

        $body = self::fetch( $base . '/v1/releases/' . $file['serial'] . '/release.json', Skill_Trust::RELEASE_MAX_BYTES );
        if ( ! $body['ok'] ) {
            return self::finish( $body['error'], array( $body['detail'] ) );
        }
        $result = Release_Verifier::verify(
            $body['body'],
            $file,
            Skill_Trust::roots(),
            time(),
            $current,
            isset( $state['revocations_issued'] ) ? $state['revocations_issued'] : null
        );
        if ( ! $result['ok'] ) {
            return self::finish( 'refused', $result['reasons'] );
        }
        return self::store(
            array(
                'sig'  => $sig['body'],
                'body' => $body['body'],
            ),
            $file['serial'],
            $result
        );
    }

    








    private static function store( array $release, int $serial, array $result ): string {
        $latest = self::state( true );
        if ( isset( $latest['serial'] ) && $latest['serial'] >= $serial ) {
            return self::finish( 'up_to_date' );
        }
        if ( isset( $latest['revocations_issued'] ) && $latest['revocations_issued'] > $result['revocations_issued'] ) {
            return self::finish( 'refused', array( 'revocations_rollback' ) );
        }
        if ( isset( $latest['revoked'] ) && in_array( $result['public_key'], $latest['revoked'], true ) ) {
            return self::finish( 'refused', array( 'key_revoked' ) );
        }

        $revocations = array(
            'revocations_issued' => $result['revocations_issued'],
            'revoked'            => $result['revoked'],
        );
        self::write( self::OPTION_RELEASE, $release, false );
        
        
        
        
        if ( self::read( self::OPTION_RELEASE, true ) !== $release ) {
            return self::finish( 'store_failed', array(), $revocations );
        }
        return self::finish(
            'updated',
            array(),
            $revocations + array(
                'serial'       => $serial,
                'keyid'        => $result['keyid'],
                'last_success' => time(),
            )
        );
    }

    




    public static function is_revoked( string $public_key ): bool {
        $state = self::state();
        return isset( $state['revoked'] ) && in_array( $public_key, $state['revoked'], true );
    }

    






    public static function state( bool $fresh = false ): array {
        $raw   = self::read( self::OPTION_STATE, $fresh );
        $state = is_array( $raw ) ? $raw : array();
        foreach ( array( 'armed_at', 'serial', 'revocations_issued', 'last_check', 'last_success' ) as $key ) {
            if ( isset( $state[ $key ] ) && ( ! is_int( $state[ $key ] ) || $state[ $key ] < 0 ) ) {
                unset( $state[ $key ] );
            }
        }
        foreach ( array( 'revoked', 'last_reasons' ) as $key ) {
            if ( isset( $state[ $key ] ) && ! is_array( $state[ $key ] ) ) {
                unset( $state[ $key ] );
            }
        }
        return $state;
    }

    






    public static function stored(): ?array {
        $raw = self::read( self::OPTION_RELEASE );
        if ( ! is_array( $raw ) || ! isset( $raw['sig'], $raw['body'] ) || ! is_string( $raw['sig'] ) || ! is_string( $raw['body'] ) ) {
            return null;
        }
        $result = Release_Verifier::verify( $raw['body'], json_decode( $raw['sig'], true ), Skill_Trust::roots(), time() );
        if ( ! $result['ok'] || self::is_revoked( $result['public_key'] ) ) {
            return null;
        }
        return array(
            'set'   => Skill_Set::from_release( $result['release'], Skill_Set::SOURCE_SYNCED ),
            'keyid' => $result['keyid'],
        );
    }

    
    public static function base_url(): string {
        $url = \apply_filters( self::BASE_URL_FILTER, self::BASE_URL );
        if ( ! is_string( $url ) || 0 !== strpos( $url, 'https://' ) || false !== strpos( $url, '?' ) || false !== strpos( $url, '#' ) ) {
            return self::BASE_URL;
        }
        return rtrim( $url, '/' );
    }

    




    private static function fetch( string $url, int $limit ): array {
        $response = \wp_safe_remote_get(
            $url,
            array(
                'timeout'             => self::TIMEOUT,
                'redirection'         => 0,
                'limit_response_size' => $limit + 1,
                
                
                
                'user-agent'          => 'EasyMCPAI-Skills/' . ( defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0' ),
                'headers'             => array( 'Accept' => 'application/json' ),
            )
        );
        if ( \is_wp_error( $response ) ) {
            return array(
                'ok'     => false,
                'error'  => 'fetch_failed',
                'detail' => (string) $response->get_error_code(),
            );
        }
        $code = (int) \wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return array(
                'ok'     => false,
                'error'  => 'http_' . $code,
                'detail' => '',
            );
        }
        $body = (string) \wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > $limit ) {
            return array(
                'ok'     => false,
                'error'  => 'too_large',
                'detail' => '',
            );
        }
        return array(
            'ok'   => true,
            'body' => $body,
        );
    }

    









    private static function finish( string $result, array $reasons = array(), array $fields = array() ): string {
        $state = self::state( true );
        if ( isset( $fields['revocations_issued'], $state['revocations_issued'] ) && $state['revocations_issued'] > $fields['revocations_issued'] ) {
            unset( $fields['revocations_issued'], $fields['revoked'] );
        }
        $state                 = array_merge( $state, $fields );
        $state['last_check']   = time();
        $state['last_result']  = $result;
        $state['last_reasons'] = array_values( array_filter( $reasons, 'strlen' ) );
        self::write( self::OPTION_STATE, $state, true );
        if ( 'updated' === $result && class_exists( __NAMESPACE__ . '\\Skills', false ) ) {
            Skills::reset();
        }
        return $result;
    }

    









    private static function read( string $name, bool $fresh = false ) {
        if ( $fresh ) {
            self::forget_cached( $name );
        }
        return \is_multisite() ? \get_site_option( $name, null ) : \get_option( $name, null );
    }

    
    private static function forget_cached( string $name ): void {
        if ( \is_multisite() ) {
            $network = \get_current_network_id();
            \wp_cache_delete( $network . ':' . $name, 'site-options' );
            \wp_cache_delete( $network . ':notoptions', 'site-options' );
            return;
        }
        \wp_cache_delete( $name, 'options' );
        \wp_cache_delete( 'notoptions', 'options' );
        \wp_cache_delete( 'alloptions', 'options' );
    }

    




    private static function write( string $name, array $value, bool $autoload ): bool {
        if ( \is_multisite() ) {
            return \update_site_option( $name, $value );
        }
        return \update_option( $name, $value, $autoload );
    }
}
