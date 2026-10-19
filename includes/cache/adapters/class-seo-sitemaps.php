<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}























final class Seo_Sitemaps extends Cache_Adapter {

    const YOAST = 'yoast';
    const TSF   = 'tsf';

    const VALIDATOR = 'WPSEO_Sitemaps_Cache_Validator';

    
    const INDEX = '1';

    
    const NAMES = array(
        self::YOAST => 'Yoast SEO',
        self::TSF   => 'The SEO Framework',
    );

    public function id(): string {
        return 'seo-sitemaps';
    }

    




    public function label(): string {
        $names = $this->names( $this->present() );
        if ( array() === $names ) {
            return 'SEO plugin sitemap caches';
        }
        return implode( ' and ', $names ) . ( count( $names ) > 1 ? ' sitemap caches' : ' sitemap cache' );
    }

    public function kind(): string {
        return self::KIND_DATA;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    





    public function scopes(): array {
        if ( array( self::YOAST ) === $this->present() ) {
            return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_POSTS );
        }
        return array( Purge_Request::SCOPE_SITE );
    }

    public function is_present(): bool {
        return array() !== $this->present();
    }

    public function noise_options(): array {
        return array( 'wpseo_sitemap_cache_validator_global', 'wpseo_sitemap_1_cache_validator', 'wpseo_sitemap_post_cache_validator', 'wpseo_sitemap_page_cache_validator' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $present = $this->present();
        $missing = $this->missing_helper( $present );
        if ( '' !== $missing ) {
            return $this->helper_missing( $missing );
        }
        $sent = array();
        foreach ( $present as $member ) {
            if ( self::YOAST === $member ) {
                $this->calls()->call( array( self::VALIDATOR, 'invalidate_storage' ) );
                $sent[] = 'Yoast SEO rotated its sitemap cache key.';
            } else {
                $this->calls()->call( array( $this->tsf_cache(), 'clear_sitemap_caches' ) );
                $sent[] = 'The SEO Framework cleared its cached sitemaps.';
            }
        }
        return $this->sent( implode( ' ', $sent ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $types = $this->post_types( $request->post_ids );
        if ( array() === $types ) {
            return $this->skip( 'no_url_api', 'Yoast SEO keeps its sitemap cache per post type, and none of these IDs is an existing post (revisions are skipped, as Yoast skips them).', array( 'needs' => 'scope=site' ) );
        }
        if ( ! $this->calls()->method_exists( self::VALIDATOR, 'invalidate_storage' ) ) {
            return $this->helper_missing( 'WPSEO_Sitemaps_Cache_Validator::invalidate_storage()' );
        }
        $types = array_merge( array( self::INDEX ), $types );
        foreach ( $types as $type ) {
            $this->calls()->call( array( self::VALIDATOR, 'invalidate_storage' ), array( $type ) );
        }
        return $this->sent( sprintf( 'Yoast SEO rotated its sitemap cache key for the index and %s sitemap(s).', implode( ', ', array_slice( $types, 1 ) ) ) );
    }

    





    private function missing_helper( array $members ): string {
        foreach ( $members as $member ) {
            if ( self::YOAST === $member && ! $this->calls()->method_exists( self::VALIDATOR, 'invalidate_storage' ) ) {
                return 'WPSEO_Sitemaps_Cache_Validator::invalidate_storage()';
            }
            if ( self::TSF === $member && ( null === $this->tsf_cache() || ! $this->calls()->method_exists( $this->tsf_cache(), 'clear_sitemap_caches' ) ) ) {
                return 'tsf()->sitemap()->cache()->clear_sitemap_caches()';
            }
        }
        return '';
    }

    




    private function present(): array {
        $calls   = $this->calls();
        $members = array();
        if ( $calls->defined( 'WPSEO_VERSION' ) ) {
            $members[] = self::YOAST;
        }
        if ( $calls->defined( 'THE_SEO_FRAMEWORK_VERSION' ) && $calls->function_exists( 'tsf' ) ) {
            $members[] = self::TSF;
        }
        return $members;
    }

    




    private function tsf_cache(): ?object {
        $calls = $this->calls();
        if ( ! $calls->function_exists( 'tsf' ) ) {
            return null;
        }
        $object = $calls->call( 'tsf' );
        foreach ( array( 'sitemap', 'cache' ) as $method ) {
            if ( ! is_object( $object ) || ! $calls->method_exists( $object, $method ) ) {
                return null;
            }
            $object = $calls->call( array( $object, $method ) );
        }
        return is_object( $object ) ? $object : null;
    }

    





    private function post_types( array $post_ids ): array {
        $types = array();
        foreach ( $post_ids as $post_id ) {
            $type = $this->calls()->call( 'get_post_type', array( $post_id ) );
            if ( is_string( $type ) && '' !== $type && 'revision' !== $type ) {
                $types[] = $type;
            }
        }
        return array_values( array_unique( $types ) );
    }

    





    private function names( array $members ): array {
        $names = array();
        foreach ( $members as $member ) {
            $names[] = self::NAMES[ $member ];
        }
        return $names;
    }
}
