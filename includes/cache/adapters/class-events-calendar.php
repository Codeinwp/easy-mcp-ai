<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';
















final class Events_Calendar extends Cache_Adapter {

    
    const TRIGGER_CONSTANT = 'Tribe__Cache_Listener::TRIGGER_SAVE_POST';

    public function id(): string {
        return 'the-events-calendar';
    }

    public function label(): string {
        return 'The Events Calendar';
    }

    public function kind(): string {
        return self::KIND_CONTENT;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    public function is_present(): bool {
        return $this->calls()->class_exists( 'Tribe__Events__Main' );
    }

    




    public function noise_options(): array {
        return array( 'tribe_last_save_post' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls   = $this->calls();
        $cache   = $calls->function_exists( 'tribe_cache' ) ? $calls->call( 'tribe_cache' ) : null;
        $trigger = $calls->constant( self::TRIGGER_CONSTANT );
        if ( ! is_object( $cache ) || ! $calls->method_exists( $cache, 'set_last_occurrence' ) || ! is_string( $trigger ) || 1 !== preg_match( '/^[a-z_]+$/', $trigger ) ) {
            return $this->helper_missing( 'tribe_cache()->set_last_occurrence( Tribe__Cache_Listener::TRIGGER_SAVE_POST )' );
        }
        $calls->call( array( $cache, 'set_last_occurrence' ), array( $trigger ) );
        return $this->sent( 'The Events Calendar rotated its cache keys.' );
    }
}
