<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






















final class Spinupwp extends Cache_Adapter {

    
    const CF_ADDON_FUNCTION = 'spinupwp_cf_auto_purge_service';
    const CF_ADDON_SETTING  = 'spinupwp_cf_purge_enabled';

    public function id(): string {
        return 'spinupwp';
    }

    public function label(): string {
        return 'SpinupWP';
    }

    public function kind(): string {
        return self::KIND_HOST;
    }

    public function tier(): int {
        return self::TIER_PAGE;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->function_exists( 'spinupwp' );
    }

    public function also_clears( Purge_Request $request ): array {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->cloudflare_addon_on() ? array( self::ALSO_CDN ) : array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'spinupwp_purge_site' ) ) {
            return $this->helper_missing( 'spinupwp_purge_site()' );
        }
        if ( ! $this->calls()->call( 'spinupwp_purge_site' ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'SpinupWP could not purge its page cache: its cache folder could not be deleted and its purge daemon did not confirm.' ) );
        }
        return $this->sent( 'SpinupWP cleared its whole page cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'spinupwp_purge_url' ) ) {
            return $this->helper_missing( 'spinupwp_purge_url()' );
        }
        foreach ( $request->urls as $url ) {
            $this->calls()->call( 'spinupwp_purge_url', array( $url ) );
        }
        return $this->sent( sprintf( 'SpinupWP cleared %d page(s).', count( $request->urls ) ) );
    }

    




    private function cloudflare_addon_on(): bool {
        return $this->calls()->function_exists( self::CF_ADDON_FUNCTION ) && ! empty( $this->calls()->get_option( self::CF_ADDON_SETTING, false ) );
    }
}
