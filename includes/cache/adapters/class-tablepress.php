<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















final class Tablepress extends Cache_Adapter {

    public function id(): string {
        return 'tablepress';
    }

    public function label(): string {
        return 'TablePress';
    }

    public function kind(): string {
        return self::KIND_CONTENT;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    public function is_present(): bool {
        return $this->calls()->class_exists( 'TablePress' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        
        $model = $this->calls()->call(
            static function () {
                return isset( \TablePress::$model_table ) ? \TablePress::$model_table : null;
            }
        );
        if ( ! is_object( $model ) || ! $this->calls()->method_exists( $model, 'invalidate_table_output_caches' ) ) {
            return $this->helper_missing( 'TablePress::$model_table->invalidate_table_output_caches()' );
        }
        $this->calls()->call( array( $model, 'invalidate_table_output_caches' ) );
        return $this->sent( 'TablePress cleared the cached output of every table.' );
    }
}
