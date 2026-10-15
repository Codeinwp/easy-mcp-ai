<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';















final class Facetwp extends Cache_Adapter {

    public function id(): string {
        return 'facetwp';
    }

    public function label(): string {
        return 'FacetWP';
    }

    public function kind(): string {
        return self::KIND_DATA;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->function_exists( 'FWP' );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $indexer = $this->indexer();
        if ( null === $indexer ) {
            return $this->helper_missing( 'FWP()->indexer->index()' );
        }
        $ids = array();
        foreach ( array_unique( $request->post_ids ) as $post_id ) {
            if ( (int) $post_id > 0 ) {
                $ids[] = (int) $post_id;
            }
        }
        if ( array() === $ids ) {
            return $this->skip( 'not_deliverable', 'The request names no post ID, so FacetWP had nothing to re-index (a call without one would be the full re-index, which is never run here).' );
        }
        foreach ( $ids as $post_id ) {
            $this->calls()->call( array( $indexer, 'index' ), array( $post_id ) );
        }
        return $this->sent( sprintf( 'FacetWP re-indexed %d post(s).', count( $ids ) ) );
    }

    




    private function indexer(): ?object {
        $calls = $this->calls();
        if ( ! $calls->function_exists( 'FWP' ) ) {
            return null;
        }
        $fwp     = $calls->call( 'FWP' );
        $indexer = is_object( $fwp ) && isset( $fwp->indexer ) ? $fwp->indexer : null;
        return is_object( $indexer ) && $calls->method_exists( $indexer, 'index' ) ? $indexer : null;
    }
}
