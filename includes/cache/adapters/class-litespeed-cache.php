<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
























final class Litespeed_Cache extends Cache_Adapter {

    public function id(): string {
        return 'litespeed-cache';
    }

    public function label(): string {
        return 'LiteSpeed Cache';
    }

    public function kind(): string {
        return self::KIND_PAGE;
    }

    public function tier(): int {
        return self::TIER_PAGE;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->defined( 'LSCWP_V' );
    }


    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->empties_server();
    }

    





    public function noise_options(): array {
        return array( 'litespeed.admin_display.messages', 'litespeed.crawler._summary', 'litespeed.purge.queue' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( $this->empties_server() ) {
            return $this->skip( 'network_scope', 'LSWCP_EMPTYCACHE is defined on this site, so LiteSpeed would turn a whole-site purge into one of every page the web server caches, other sites included; this tool never sends it.', array( 'needs' => 'scope=urls' ) );
        }
        if ( ! $this->calls()->has_action( 'litespeed_purge' ) ) {
            return $this->helper_missing( 'litespeed_purge action' );
        }
        $this->calls()->do_action( 'litespeed_purge', '*' );
        return $this->delivered( 'LiteSpeed Cache cleared this site\'s page cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->has_action( 'litespeed_purge_url' ) ) {
            return $this->helper_missing( 'litespeed_purge_url action' );
        }
        foreach ( $request->urls as $url ) {
            $this->calls()->do_action( 'litespeed_purge_url', urldecode( $this->path_of( $url ) ) );
        }
        return $this->delivered( sprintf( 'LiteSpeed Cache cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->has_action( 'litespeed_purge_post' ) ) {
            return $this->helper_missing( 'litespeed_purge_post action' );
        }
        if ( $this->conf( 'purge-post_all' ) ) {
            $this->purge_urls( $request );
            return $this->delivered( sprintf( 'LiteSpeed Cache cleared %d page(s); not its post purge, which would clear the whole site here ("Purge All On Upgrade/Update" for posts is on).', count( $request->urls ) ) );
        }
        foreach ( $request->post_ids as $post_id ) {
            $this->calls()->do_action( 'litespeed_purge_post', $post_id );
        }
        $this->purge_urls( $request );
        return $this->delivered( sprintf( 'LiteSpeed Cache cleared %d post(s) with their related pages, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }

    






    private function delivered( string $detail ): Layer_Result {
        if ( $this->calls()->defined( 'LITESPEED_CLI' ) ) {
            return $this->parked( $detail );
        }
        return $this->sent( $detail, 'after_response' );
    }

    





    private function empties_server(): bool {
        return ! empty( $this->calls()->constant( 'LSWCP_EMPTYCACHE' ) );
    }

    






    private function conf( string $id ): bool {
        $calls = $this->calls();
        if ( ! $calls->has_action( 'litespeed_conf' ) ) {
            return false;
        }
        $value = $calls->apply_filters( 'litespeed_conf', $id );
        return $value !== $id && ! empty( $value );
    }
}
