<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';























final class Clearfy extends Cache_Adapter {

    
    const HELPERS = 'WCL_Cache_Helpers';

    
    const PLUGIN = 'WCL_Plugin';

    public function id(): string {
        return 'clearfy';
    }

    public function label(): string {
        return 'Clearfy';
    }

    public function kind(): string {
        return self::KIND_PAGE;
    }

    public function tier(): int {
        return self::TIER_PAGE;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->defined( 'WCACHE_PLUGIN_ACTIVE' ) && $calls->class_exists( self::HELPERS ) && $calls->class_exists( self::PLUGIN );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope
            && $this->calls()->is_multisite()
            && true !== $this->calls()->constant( 'SUBDOMAIN_INSTALL' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::HELPERS, 'deleteCache' ) ) {
            return $this->helper_missing( 'WCL_Cache_Helpers::deleteCache()' );
        }
        if ( true !== $this->calls()->call( array( self::HELPERS, 'deleteCache' ) ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'Clearfy could not move its cache folder aside (wp-content/cache is probably not writable), so nothing was cleared.' ) );
        }
        return $this->sent( 'Clearfy cleared its page, mobile and widget caches.' );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::HELPERS, 'singleDeleteCache' ) ) {
            return $this->helper_missing( 'WCL_Cache_Helpers::singleDeleteCache()' );
        }
        $sent = 0;
        $left = array();
        foreach ( $request->post_ids as $post_id ) {
            $why = $this->widens( $post_id );
            if ( '' !== $why ) {
                $left[] = sprintf( 'post %d (%s)', $post_id, $why );
                continue;
            }
            $this->calls()->call( array( self::HELPERS, 'singleDeleteCache' ), array( false, $post_id ) );
            ++$sent;
        }
        if ( array() === $left ) {
            return $this->sent( sprintf( 'Clearfy cleared %d post(s) with the home, author, sitemap and term archive pages.', $sent ) );
        }
        $note = sprintf( 'Left out: %s. Use scope=site to clear them.', implode( '; ', $left ) );
        if ( 0 === $sent ) {
            return $this->skip( 'would_purge_site', 'Nothing was sent to Clearfy. ' . $note, array( 'needs' => 'scope=site' ) );
        }
        return $this->sent( sprintf( 'Clearfy cleared %d post(s) with the home, author, sitemap and term archive pages. %s', $sent, $note ) )->with( array( 'needs' => 'scope=site' ) );
    }

    





    private function widens( int $post_id ): string {
        $calls = $this->calls();
        if ( true !== $calls->call( 'is_post_publicly_viewable', array( $post_id ) ) ) {
            return 'it is not published, so Clearfy\'s post purge cannot reach its old address; Clearfy clears its whole cache itself when a post is unpublished';
        }
        $link = $calls->call( 'get_permalink', array( $post_id ) );
        if ( '/' !== $this->path_of( (string) \home_url( '/' ) ) && is_string( $link ) && $this->is_home_url( $link ) ) {
            return 'it is the front page of a site in a subfolder, and Clearfy\'s post purge would delete that subfolder, which holds every cached page';
        }
        return '';
    }
}
