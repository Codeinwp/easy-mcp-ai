<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';















final class Popup_Maker extends Cache_Adapter {

    public function id(): string {
        return 'popup-maker';
    }

    public function label(): string {
        return 'Popup Maker';
    }

    public function kind(): string {
        return self::KIND_CONTENT;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    public function is_present(): bool {
        return $this->calls()->class_exists( 'PUM_AssetCache' );
    }

    




    public function noise_options(): array {
        return array( 'pum-has-cached-css', 'pum-has-cached-js' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'pum_reset_assets' ) ) {
            return $this->helper_missing( 'pum_reset_assets()' );
        }
        $this->calls()->call( 'pum_reset_assets' );
        return $this->sent( 'Popup Maker reset its bundled popup CSS and JS.' );
    }
}
