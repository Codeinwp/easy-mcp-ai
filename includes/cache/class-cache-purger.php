<?php







namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-cache-registry.php';
require_once __DIR__ . '/class-unmanaged-layers.php';













final class Cache_Purger {

    
    const VERIFY_AFTER_SECONDS = 5;

    
    private $adapters;

    
    private $calls;

    



    public function __construct( ?array $adapters = null, ?Vendor_Calls $calls = null ) {
        $this->calls    = $calls ? $calls : new Vendor_Calls();
        $this->adapters = null === $adapters ? Cache_Registry::all() : $adapters;
        foreach ( $this->adapters as $adapter ) {
            $adapter->with_calls( $this->calls );
        }
    }

    






    public static function deadline( int $budget ): float {
        $now     = microtime( true );
        $seconds = (float) $budget;
        $limit   = (int) ini_get( 'max_execution_time' );
        if ( $limit > 0 ) {
            $started = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $now;
            $seconds = min( $seconds, max( 1.0, $limit - ( $now - $started ) - 5 ) );
        }
        return $now + $seconds;
    }

    




    public function present_ids(): array {
        $ids = array();
        foreach ( $this->adapters as $id => $adapter ) {
            if ( $this->is_present( $adapter ) ) {
                $ids[] = (string) $id;
            }
        }
        return $ids;
    }

    






    public function run( Purge_Request $request, array $resolved = array() ): array {
        $layers = array();
        foreach ( $this->adapters as $adapter ) {
            if ( $this->is_present( $adapter ) ) {
                $layers[] = $this->layer_for( $adapter, $request );
            }
        }
        return $this->reply( $request, $layers, $resolved );
    }

    





    private function is_present( Cache_Adapter $adapter ): bool {
        try {
            return $adapter->is_present();
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    






    private function layer_for( Cache_Adapter $adapter, Purge_Request $request ): Layer_Result {
        try {
            $refusal = $this->refusal( $adapter, $request );
        } catch ( \Throwable $e ) {
            return $this->failed( $adapter, $e );
        }
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( $request->dry_run ) {
            return new Layer_Result( $adapter->id(), $adapter->label(), $adapter->kind(), Layer_Result::WOULD_CLEAR );
        }
        return $this->call( $adapter, $request );
    }

    






    private function refusal( Cache_Adapter $adapter, Purge_Request $request ): ?Layer_Result {
        $skip = $this->availability( $adapter, $request );
        if ( null === $skip ) {
            $skip = $this->reach( $adapter, $request );
        }
        if ( null === $skip && $request->deadline > 0 && $this->calls->now() >= $request->deadline ) {
            $skip = $this->skipped( $adapter, 'time_budget', 'Not started: the time this call may take was used up by the caches before it. Call again with `caches` naming it.' );
        }
        return $skip;
    }

    






    private function availability( Cache_Adapter $adapter, Purge_Request $request ): ?Layer_Result {
        if ( ! $request->selects( $adapter->id() ) ) {
            return $this->skipped( $adapter, 'not_selected', 'Not in the `caches` list of this call.' );
        }
        if ( ! Cache_Registry::is_switched_on( $adapter ) ) {
            $detail = $adapter->enabled_by_default()
                ? 'Switched off on this site by the easy_mcp_ai_cache_adapter_enabled filter.'
                : 'This cache ships switched off; a site can switch it on with the easy_mcp_ai_cache_adapter_enabled filter.';
            return $this->skipped( $adapter, 'disabled_by_filter', $detail );
        }
        if ( ! in_array( $request->scope, $adapter->scopes(), true ) ) {
            return $this->skipped( $adapter, 'no_url_api', sprintf( '%s cannot clear single URLs or posts; only a whole-site clear reaches it.', $adapter->label() ), array( 'needs' => 'scope=site' ) );
        }
        return null;
    }

    







    private function reach( Cache_Adapter $adapter, Purge_Request $request ): ?Layer_Result {
        $also = $adapter->also_clears( $request );
        $site = Purge_Request::SCOPE_SITE === $request->scope;
        if ( ! $site && in_array( Cache_Adapter::ALSO_SITE, $also, true ) ) {
            return $this->skipped( $adapter, 'would_purge_site', sprintf( '%s clears the whole site even for single pages here, so it runs only with scope "site".', $adapter->label() ), array( 'needs' => 'scope=site' ) );
        }
        if ( ! $request->include_cdn && in_array( Cache_Adapter::ALSO_CDN, $also, true ) ) {
            return $this->skipped( $adapter, 'cdn_not_requested', sprintf( '%s also purges a whole CDN zone here, so it runs only with include_cdn.', $adapter->label() ), array( 'needs' => $site ? 'include_cdn=true' : 'scope=site, include_cdn=true' ) );
        }
        if ( ! $request->include_object_cache && in_array( Cache_Adapter::ALSO_OBJECT_CACHE, $also, true ) ) {
            return $this->skipped( $adapter, 'object_cache_not_requested', sprintf( '%s also flushes the whole object cache here, which resets the rate limits and sign-in lockouts of AI connections, so it runs only with include_object_cache.', $adapter->label() ), array( 'needs' => $site ? 'include_object_cache=true' : 'scope=site, include_object_cache=true' ) );
        }
        if ( $this->calls->is_multisite() && $adapter->reaches_network( $request ) && ! ( $this->calls->is_super_admin() && $this->calls->current_user_can( 'manage_network_options' ) ) ) {
            return $this->skipped( $adapter, 'network_scope', sprintf( 'On this multisite network a %s purge here reaches other sites, so it needs a super admin with the manage_network_options capability.', $adapter->label() ) );
        }
        return null;
    }

    






    private function call( Cache_Adapter $adapter, Purge_Request $request ): Layer_Result {
        $level = ob_get_level();
        ob_start();
        try {
            $result = $adapter->purge( $request );
        } catch ( \Throwable $e ) {
            $result = $this->failed( $adapter, $e );
        } finally {
            while ( ob_get_level() > $level ) {
                ob_end_clean();
            }
        }
        return $result;
    }

    








    private function skipped( Cache_Adapter $adapter, string $reason, string $detail, array $extra = array() ): Layer_Result {
        return new Layer_Result( $adapter->id(), $adapter->label(), $adapter->kind(), Layer_Result::SKIPPED, array_merge( array( 'reason' => $reason, 'detail' => $detail ), $extra ) );
    }

    






    private function failed( Cache_Adapter $adapter, \Throwable $e ): Layer_Result {
        return new Layer_Result( $adapter->id(), $adapter->label(), $adapter->kind(), Layer_Result::FAILED, array( 'detail' => sprintf( '%s raised an error: %s', $adapter->label(), $e->getMessage() ) ) );
    }

    







    private function reply( Purge_Request $request, array $layers, array $resolved ): array {
        $changed = false;
        foreach ( $layers as $layer ) {
            $changed = $changed || $layer->changed();
        }
        $wait  = $this->wait_seconds( $layers );
        $reply = array(
            'summary'     => self::summary( $request, $layers, $changed ),
            'changed'     => $changed,
            'dry_run'     => $request->dry_run,
            'scope'       => $request->scope,
            'urls'        => $resolved,
            'layers'      => array_map(
                static function ( Layer_Result $layer ): array {
                    return $layer->to_array();
                },
                $layers
            ),
            'not_covered' => Unmanaged_Layers::detect( $this->calls, $layers ),
        );
        if ( $changed ) {
            $reply['verify'] = $this->verify( $resolved, $wait );
        }
        return $reply;
    }

    







    private function wait_seconds( array $layers ): int {
        $wait = self::VERIFY_AFTER_SECONDS;
        foreach ( $layers as $layer ) {
            if ( $layer->changed() && isset( $this->adapters[ $layer->id() ] ) ) {
                $wait = max( $wait, $this->adapters[ $layer->id() ]->stale_seconds() );
            }
        }
        return $wait;
    }

    






    private function verify( array $resolved, int $wait ): array {
        $urls = array();
        foreach ( $resolved as $row ) {
            if ( in_array( $row['source'], array( 'url', 'permalink' ), true ) ) {
                $urls[] = $row['url'];
            }
        }
        if ( array() === $urls ) {
            $urls[] = (string) \home_url( '/' );
        }
        return array(
            'urls'          => array_slice( array_values( array_unique( $urls ) ), 0, 5 ),
            'after_seconds' => $wait,
            'how'           => 'Fetch each URL exactly as written, with no added query string (a query string bypasses or changes the cache key), and check that the change is in the HTML.',
        );
    }

    







    private static function summary( Purge_Request $request, array $layers, bool $changed ): string {
        if ( array() === $layers ) {
            return 'No cache this tool can clear is active on this site, so nothing was cleared.';
        }
        $counts = array_count_values(
            array_map(
                static function ( Layer_Result $layer ): string {
                    return $layer->status();
                },
                $layers
            )
        );
        $parts  = array();
        foreach ( array( Layer_Result::WOULD_CLEAR => 'would be cleared', Layer_Result::CLEARED => 'cleared', Layer_Result::QUEUED => 'queued to clear later', Layer_Result::SKIPPED => 'skipped', Layer_Result::FAILED => 'failed' ) as $status => $words ) {
            if ( ! empty( $counts[ $status ] ) ) {
                $parts[] = sprintf( '%d %s', $counts[ $status ], $words );
            }
        }
        $lead = $request->dry_run ? 'Dry run, nothing was sent: ' : ( $changed ? '' : 'Nothing was cleared: ' );
        return $lead . implode( ', ', $parts ) . '. See layers[] for each cache.';
    }
}
