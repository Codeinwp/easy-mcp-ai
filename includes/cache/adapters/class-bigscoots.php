<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























final class Bigscoots extends Cache_Adapter {

    
    const VENDOR = 'BigScoots_Cache';

    
    const SUPPRESS = array( 'bs_cache_disable_clear_opcache', 'bs_cache_disable_clear_object_cache' );

    
    const FULL_PURGE_TYPES = array( 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles' );

    
    const POST_STATUSES = array( 'publish', 'private' );

    public function id(): string {
        return 'bigscoots';
    }

    public function label(): string {
        return 'BigScoots Cache';
    }

    public function kind(): string {
        return self::KIND_CDN;
    }

    public function tier(): int {
        return self::TIER_PROXY;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->class_exists( self::VENDOR );
    }


    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::VENDOR, 'clear_cache' ) ) {
            return $this->helper_missing( 'BigScoots_Cache::clear_cache()' );
        }
        return $this->answer( array( $this->clear( array( 'purge_page_cache' ) ) ), 'BigScoots Cache cleared every cached page of this site; not its images, CSS or JavaScript.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::VENDOR, 'clear_cache' ) ) {
            return $this->helper_missing( 'BigScoots_Cache::clear_cache()' );
        }
        if ( array() === $request->urls ) {
            return $this->skip( 'not_deliverable', 'No URL was resolved, so nothing was sent to BigScoots Cache.' );
        }
        return $this->answer( array( $this->clear( array( $request->urls, false, false, true ) ) ), sprintf( 'BigScoots Cache cleared exactly %d URL(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::VENDOR, 'clear_cache' ) ) {
            return $this->helper_missing( 'BigScoots_Cache::clear_cache()' );
        }
        $posts   = $this->post_purge_ids( $request );
        $results = array();
        if ( array() !== $posts ) {
            $results[] = $this->clear( array( $posts, true ) );
        }
        if ( array() !== $request->urls ) {
            $results[] = $this->clear( array( $request->urls, false, false, true ) );
        }
        if ( array() === $results ) {
            return $this->skip( 'not_deliverable', 'None of these posts is published or private and no URL was resolved for them, so nothing was sent to BigScoots Cache.' );
        }
        return $this->answer( $results, sprintf( 'BigScoots Cache cleared %d post(s) with their related pages, and exactly %d URL(s).', count( $posts ), count( $request->urls ) ) );
    }

    







    private function post_purge_ids( Purge_Request $request ): array {
        $types = $this->calls()->apply_filters( 'bs_cache_full_purge_post_types', self::FULL_PURGE_TYPES );
        $types = is_array( $types ) ? $types : self::FULL_PURGE_TYPES;
        $ids   = array();
        foreach ( $request->post_ids as $post_id ) {
            if ( in_array( $this->calls()->call( 'get_post_status', array( $post_id ) ), self::POST_STATUSES, true )
                && ! in_array( $this->calls()->call( 'get_post_type', array( $post_id ) ), $types, true ) ) {
                $ids[] = $post_id;
            }
        }
        return $ids;
    }

    





    private function clear( array $args ) {
        $calls = $this->calls();
        $yes   = static function (): bool {
            return true;
        };
        foreach ( self::SUPPRESS as $hook ) {
            $calls->add_filter( $hook, $yes, PHP_INT_MAX, 0 );
        }
        try {
            return $calls->call( array( self::VENDOR, 'clear_cache' ), $args );
        } finally {
            foreach ( self::SUPPRESS as $hook ) {
                $calls->remove_filter( $hook, $yes, PHP_INT_MAX );
            }
        }
    }

    







    private function answer( array $results, string $detail ): Layer_Result {
        $refusals = array();
        foreach ( $results as $result ) {
            if ( $result instanceof \WP_Error ) {
                $refusals[] = $result->get_error_message();
            } elseif ( is_array( $result ) && array_key_exists( 'success', $result ) && true !== $result['success'] ) {
                $refusals[] = isset( $result['message'] ) && is_string( $result['message'] ) ? $result['message'] : 'no reason given';
            }
        }
        if ( count( $refusals ) === count( $results ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'BigScoots Cache refused the purge: ' . implode( ' ', $refusals ) ) );
        }
        $refused = array() === $refusals ? '' : ' Part of it was refused: ' . implode( ' ', $refusals );
        return $this->sent( $detail . $refused, 'async' );
    }
}
