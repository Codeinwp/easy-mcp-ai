<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';

























final class Pantheon extends Cache_Adapter {

    const PURGER = 'Pantheon_Advanced_Page_Cache\\Purger';

    public function id(): string {
        return 'pantheon';
    }

    public function label(): string {
        return 'Pantheon Global CDN';
    }

    public function kind(): string {
        return self::KIND_HOST;
    }

    public function tier(): int {
        return self::TIER_PROXY;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        foreach ( array( 'pantheon_clear_edge_all', 'pantheon_clear_edge_paths', 'pantheon_clear_edge_keys' ) as $function ) {
            if ( $this->calls()->function_exists( $function ) ) {
                return true;
            }
        }
        return false;
    }

    public function reaches_network( Purge_Request $request ): bool {
        return $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'pantheon_clear_edge_all' ) ) {
            return $this->helper_missing( 'pantheon_clear_edge_all()' );
        }
        return $this->outcome( $this->send( 'pantheon_clear_edge_all', array() ), 'Pantheon Global CDN cleared every page it holds for this site.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'pantheon_clear_edge_paths' ) ) {
            return $this->helper_missing( 'pantheon_clear_edge_paths()' );
        }
        $paths = $this->paths( $request->urls );
        if ( array() === $paths ) {
            return $this->skip( 'not_deliverable', 'No URL was resolved, so nothing was sent to Pantheon.' );
        }
        return $this->outcome( $this->send( 'pantheon_clear_edge_paths', array( $paths ) ), sprintf( 'Pantheon Global CDN cleared %d path(s).', count( $paths ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        $keyed = $calls->method_exists( self::PURGER, 'action_clean_post_cache' ) && $calls->function_exists( 'pantheon_clear_edge_keys' );
        if ( $keyed ) {
            foreach ( $request->post_ids as $post_id ) {
                $calls->call( array( self::PURGER, 'action_clean_post_cache' ), array( $post_id ) );
            }
        }
        $keys = sprintf( 'Pantheon Advanced Page Cache cleared the surrogate keys of %d post(s).', count( $request->post_ids ) );
        if ( array() === $request->urls ) {
            return $keyed ? $this->sent( $keys ) : $this->skip( 'not_deliverable', 'No URL of the posts was resolved, and without Pantheon Advanced Page Cache no surrogate key exists to clear, so nothing was sent.' );
        }
        $result = $this->purge_urls( $request );
        return $keyed && Layer_Result::CLEARED === $result->status() ? $result->with( array( 'detail' => $keys . ' ' . (string) $result->get( 'detail' ) ) ) : $result;
    }

    






    private function send( string $platform, array $args ) {
        $wrapper = str_replace( 'pantheon_', 'pantheon_wp_', $platform );
        return $this->calls()->call( $this->calls()->function_exists( $wrapper ) ? $wrapper : $platform, $args );
    }

    







    private function paths( array $urls ): array {
        $paths = array();
        foreach ( $urls as $url ) {
            $path    = rtrim( $this->path_of( $url ), '/' );
            $query   = \wp_parse_url( $url, PHP_URL_QUERY );
            $suffix  = is_string( $query ) && '' !== $query ? '?' . $query : '';
            $paths[] = $path . '/' . $suffix;
            if ( '' !== $path ) {
                $paths[] = $path . $suffix;
            }
        }
        return array_values( array_unique( $paths ) );
    }
}
