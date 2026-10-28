<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cloudflare extends Cache_Adapter {

    const CHUNK = 30;

    public function id(): string {
        return 'cloudflare';
    }

    public function label(): string {
        return 'Cloudflare';
    }

    public function kind(): string {
        return self::KIND_CDN;
    }

    public function tier(): int {
        return self::TIER_CDN;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->defined( 'CLOUDFLARE_PLUGIN_DIR' ) && '' !== $this->vendor_namespace();
    }

    public function also_clears( Purge_Request $request ): array {
        return Purge_Request::SCOPE_SITE === $request->scope ? array( self::ALSO_CDN ) : array();
    }

    public function purge( Purge_Request $request ): Layer_Result {
        $domain = strtolower( $this->zone_domain() );
        $home   = strtolower( (string) \wp_parse_url( (string) \home_url( '/' ), PHP_URL_HOST ) );
        if ( '' !== $domain && '' !== $home && $home !== $domain && substr( $home, -strlen( '.' . $domain ) ) !== '.' . $domain ) {
            return $this->skip( 'not_deliverable', sprintf( 'The Cloudflare plugin holds the zone %s, but this site is %s, so it was probably copied from another site and a purge would clear that site\'s zone. An administrator has to reconnect the Cloudflare plugin on this site.', $domain, $home ) );
        }
        return parent::purge( $request );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope;
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( $this->vendor_namespace() . 'WordPress\\Hooks', 'purgeCacheEverything' ) ) {
            return $this->helper_missing( 'Hooks::purgeCacheEverything()' );
        }
        $this->calls()->call( array( $this->hooks(), 'purgeCacheEverything' ) );
        return $this->sent( 'Cloudflare cleared the whole zone.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $missing = $this->missing_client_api();
        if ( '' !== $missing ) {
            return $this->helper_missing( $missing );
        }
        $calls  = $this->calls();
        $client = $this->client();
        $domain = $this->zone_domain();
        $zone   = '' === $domain ? null : $calls->call( array( $client, 'getZoneTag' ), array( $domain ) );
        if ( ! is_string( $zone ) || '' === $zone ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'The Cloudflare plugin has no active Cloudflare zone for this site (an administrator has not opened Settings > Cloudflare yet, or Cloudflare found no active zone of that name for the stored credentials), so nothing was sent.' ) );
        }
        $batches = $this->file_batches( $request->urls );
        $refused = 0;
        foreach ( $batches as $batch ) {
            if ( true !== (bool) $calls->call( array( $client, 'zonePurgeFiles' ), array( $zone, $batch ) ) ) {
                ++$refused;
            }
        }
        if ( $refused > 0 ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => sprintf( 'Cloudflare did not accept %d of %d purge request(s); the Cloudflare plugin writes the reason to the PHP error log.', $refused, count( $batches ) ) ) );
        }
        return $this->sent( sprintf( 'Cloudflare cleared %d URL(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( $this->vendor_namespace() . 'WordPress\\Hooks', 'purgeCacheByRelevantURLs' ) ) {
            return $this->helper_missing( 'Hooks::purgeCacheByRelevantURLs()' );
        }
        $ids = $request->post_ids;
        $this->calls()->call( array( $this->hooks(), 'purgeCacheByRelevantURLs' ), array( 1 === count( $ids ) ? $ids[0] : $ids ) );
        return $this->sent( sprintf( 'Cloudflare cleared the related URLs of %d post(s).', count( $ids ) ) );
    }

    private function file_batches( array $urls ): array {
        $batches = array_chunk( $urls, self::CHUNK );
        if ( ! $this->setting_on( 'automatic_platform_optimization_cache_by_device_type' ) ) {
            return $batches;
        }
        foreach ( array_chunk( $urls, self::CHUNK ) as $chunk ) {
            $batches[] = array_map(
                static function ( string $url ): array {
                    return array(
                        'url'     => $url,
                        'headers' => array( 'CF-Device-Type' => 'mobile' ),
                    );
                },
                $chunk
            );
        }
        return $batches;
    }

    private function zone_domain(): string {
        $constant = $this->calls()->constant( 'CLOUDFLARE_DOMAIN_NAME' );
        if ( is_string( $constant ) && '' !== $constant ) {
            return $constant;
        }
        $cached = $this->calls()->get_option( 'cloudflare_cached_domain_name', null );
        return is_string( $cached ) ? $cached : '';
    }

    private function setting_on( string $name ): bool {
        $setting = $this->calls()->get_option( $name, null );
        if ( empty( $setting ) ) {
            return false;
        }
        $value = is_array( $setting ) && array_key_exists( 'value', $setting ) ? $setting['value'] : null;
        return false !== $value && 'off' !== $value;
    }

    private function hooks() {
        $global = $this->calls()->global_value( 'cloudflareHooks' );
        if ( is_object( $global ) && $this->calls()->method_exists( $global, 'purgeCacheEverything' ) && $this->calls()->method_exists( $global, 'purgeCacheByRelevantURLs' ) ) {
            return $global;
        }
        return $this->calls()->instantiate( $this->vendor_namespace() . 'WordPress\\Hooks' );
    }

    private function client() {
        $ns          = $this->vendor_namespace();
        $calls       = $this->calls();
        $config      = $calls->instantiate( $ns . 'Integration\\DefaultConfig' );
        $logger      = $calls->instantiate( $ns . 'Integration\\DefaultLogger', array( false ) );
        $store       = $calls->instantiate( $ns . 'WordPress\\DataStore', array( $logger ) );
        $api         = $calls->instantiate( $ns . 'WordPress\\WordPressAPI', array( $store ) );
        $integration = $calls->instantiate( $ns . 'Integration\\DefaultIntegration', array( $config, $api, $store, $logger ) );
        return $calls->instantiate( $ns . 'WordPress\\WordPressClientAPI', array( $integration ) );
    }

    private function missing_client_api(): string {
        $ns = $this->vendor_namespace();
        foreach ( array( 'Integration\\DefaultConfig', 'Integration\\DefaultLogger', 'Integration\\DefaultIntegration', 'WordPress\\DataStore', 'WordPress\\WordPressAPI' ) as $class ) {
            if ( ! $this->calls()->class_exists( $ns . $class ) ) {
                return $ns . $class;
            }
        }
        foreach ( array( 'getZoneTag', 'zonePurgeFiles' ) as $method ) {
            if ( ! $this->calls()->method_exists( $ns . 'WordPress\\WordPressClientAPI', $method ) ) {
                return 'WordPressClientAPI::' . $method . '()';
            }
        }
        return '';
    }

    private function vendor_namespace(): string {
        foreach ( array( 'Cloudflare\\APO\\', 'CF\\' ) as $ns ) {
            if ( $this->calls()->class_exists( $ns . 'WordPress\\Hooks' ) ) {
                return $ns;
            }
        }
        return '';
    }
}
