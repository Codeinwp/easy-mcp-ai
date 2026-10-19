<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




























final class Smash_Balloon extends Cache_Adapter {

    
    const PRODUCTS = array(
        'instagram' => array( 'Instagram Feed', 'SBIVER' ),
        'facebook'  => array( 'Custom Facebook Feed', 'CFFVER' ),
        'youtube'   => array( 'Feeds for YouTube', 'SBYVER' ),
        'twitter'   => array( 'Custom Twitter Feeds', 'CTF_VERSION' ),
    );

    const CFF_CACHE   = 'CustomFacebookFeed\\CFF_Cache';
    const CTF_HANDLER = 'TwitterFeed\\Admin\\CTF_Cache_Handler';
    const SBI_CACHE   = 'SB_Instagram_Cache';

    public function id(): string {
        return 'smash-balloon';
    }

    public function label(): string {
        return 'Smash Balloon feeds';
    }

    public function kind(): string {
        return self::KIND_CONTENT;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    public function is_present(): bool {
        return array() !== $this->present();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $cleared  = array();
        $left     = array();
        $selected = false;
        foreach ( $this->present() as $key ) {
            if ( ! $this->selected( $key, $request ) ) {
                $left[] = 'Custom Twitter Feeds was not cleared: its free tier refetches through a relay limited to about one request every few days, so it is cleared only when `caches` names smash-balloon.';
                continue;
            }
            $selected = true;
            $why      = $this->clear( $key );
            if ( '' === $why ) {
                $cleared[] = self::PRODUCTS[ $key ][0];
            } else {
                $left[] = $why;
            }
        }
        if ( array() === $cleared ) {
            return $this->skip( $selected ? 'api_missing' : 'not_selected', implode( ' ', $left ) );
        }
        return $this->sent( trim( sprintf( 'Smash Balloon cleared the stored feeds of %s. %s', implode( ', ', $cleared ), implode( ' ', $left ) ) ) );
    }

    




    private function present(): array {
        $found = array();
        foreach ( self::PRODUCTS as $key => $product ) {
            if ( $this->calls()->defined( $product[1] ) ) {
                $found[] = $key;
            }
        }
        return $found;
    }

    






    private function selected( string $key, Purge_Request $request ): bool {
        return 'twitter' !== $key || ( is_array( $request->caches ) && in_array( $this->id(), $request->caches, true ) );
    }

    





    private function clear( string $key ): string {
        $calls   = $this->calls();
        $missing = sprintf( '%s is loaded but its clear function is not available in this version, so it was not cleared.', self::PRODUCTS[ $key ][0] );
        switch ( $key ) {
            case 'instagram':
                return $this->clear_instagram() ? '' : $missing;
            case 'facebook':
                if ( ! $calls->method_exists( self::CFF_CACHE, 'clear_legacy' ) || ! $calls->method_exists( self::CFF_CACHE, 'clear_all_builder' ) ) {
                    return $missing;
                }
                $calls->call( array( self::CFF_CACHE, 'clear_legacy' ) );
                $calls->call( array( self::CFF_CACHE, 'clear_all_builder' ) );
                return '';
            case 'youtube':
                if ( ! $calls->function_exists( 'sby_clear_cache' ) ) {
                    return $missing;
                }
                $loaded = $this->youtube_cascade();
                if ( array() !== $loaded ) {
                    return sprintf( 'Feeds for YouTube was not cleared: its only clear function, sby_clear_cache(), also empties every cache of %s with no way to stop it. Clear it in its own settings, or wait for its cache time.', implode( ', ', $loaded ) );
                }
                $calls->call( 'sby_clear_cache' );
                return '';
            default:
                if ( ! $calls->method_exists( self::CTF_HANDLER, 'clear_db_caches' ) ) {
                    return $missing;
                }
                $calls->call( array( $calls->instantiate( self::CTF_HANDLER ), 'clear_db_caches' ) );
                return '';
        }
    }

    




    private function clear_instagram(): bool {
        $calls = $this->calls();
        $all   = $calls->function_exists( 'sbi_clear_all_feed_caches' );
        if ( ! $all && ! $calls->function_exists( 'sbi_clear_caches' ) ) {
            return false;
        }
        $veto = static function (): bool {
            return false;
        };
        $calls->add_filter( 'sbi_clear_page_caches', $veto, PHP_INT_MAX );
        try {
            if ( $all ) {
                $calls->call( 'sbi_clear_all_feed_caches' );
            } else {
                $calls->call( 'sbi_clear_caches' );
                if ( $calls->method_exists( self::SBI_CACHE, 'clear_legacy' ) ) {
                    $calls->call( array( self::SBI_CACHE, 'clear_legacy' ), array( true ) );
                }
            }
        } finally {
            $calls->remove_filter( 'sbi_clear_page_caches', $veto, PHP_INT_MAX );
        }
        return true;
    }

    




    private function youtube_cascade(): array {
        $calls  = $this->calls();
        $wpfc   = $calls->global_value( 'wp_fastest_cache' );
        $checks = array(
            'WP Fastest Cache' => is_object( $wpfc ) && $calls->method_exists( $wpfc, 'deleteCache' ),
            'WP Super Cache'   => $calls->function_exists( 'wp_cache_clear_cache' ),
            'W3 Total Cache'   => $calls->class_exists( 'W3_Plugin_TotalCacheAdmin' ),
            'WP Rocket'        => $calls->function_exists( 'rocket_clean_domain' ),
            'Autoptimize'      => $calls->class_exists( 'autoptimizeCache' ),
        );
        return array_keys( array_filter( $checks ) );
    }
}
