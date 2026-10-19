<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















abstract class Cache_Adapter {

    const KIND_DATA    = 'data';
    const KIND_CONTENT = 'content';
    const KIND_ASSET   = 'asset';
    const KIND_PAGE    = 'page';
    const KIND_HOST    = 'host';
    const KIND_PROXY   = 'proxy';
    const KIND_CDN     = 'cdn';
    const KIND_OBJECT  = 'object';

    
    const TIER_DATA   = 10;
    const TIER_ASSET  = 20;
    const TIER_PAGE   = 30;
    const TIER_PROXY  = 40;
    const TIER_CDN    = 50;
    const TIER_OBJECT = 60;

    
    const ALSO_SITE         = 'site';
    const ALSO_CDN          = 'cdn';
    const ALSO_OBJECT_CACHE = 'object_cache';

    
    private $calls = null;

    





    public function with_calls( Vendor_Calls $calls ): self {
        $this->calls = $calls;
        return $this;
    }

    




    protected function calls(): Vendor_Calls {
        if ( null === $this->calls ) {
            $this->calls = new Vendor_Calls();
        }
        return $this->calls;
    }

    




    abstract public function id(): string;

    




    abstract public function label(): string;

    




    abstract public function kind(): string;

    




    abstract public function tier(): int;

    




    abstract public function is_present(): bool;

    






    public function enabled_by_default(): bool {
        return true;
    }

    




    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE );
    }

    







    public function also_clears( Purge_Request $request ): array {
        return array();
    }

    






    public function reaches_network( Purge_Request $request ): bool {
        return false;
    }

    






    public function stale_seconds(): int {
        return 0;
    }

    





    public function noise_options(): array {
        return array();
    }

    





    public function purge( Purge_Request $request ): Layer_Result {
        switch ( $request->scope ) {
            case Purge_Request::SCOPE_URLS:
                return $this->purge_urls( $request );
            case Purge_Request::SCOPE_POSTS:
                return $this->purge_posts( $request );
            default:
                return $this->purge_site( $request );
        }
    }

    





    protected function purge_site( Purge_Request $request ): Layer_Result {
        return $this->skip( 'api_missing', 'This adapter has no whole-site purge.' );
    }

    





    protected function purge_urls( Purge_Request $request ): Layer_Result {
        return $this->skip( 'no_url_api', 'This cache cannot clear single URLs.', array( 'needs' => 'scope=site' ) );
    }

    






    protected function purge_posts( Purge_Request $request ): Layer_Result {
        return $this->purge_urls( $request );
    }

    






    protected function layer( string $status, array $extra = array() ): Layer_Result {
        return new Layer_Result( $this->id(), $this->label(), $this->kind(), $status, $extra );
    }

    







    protected function skip( string $reason, string $detail, array $extra = array() ): Layer_Result {
        return $this->layer( Layer_Result::SKIPPED, array_merge( array( 'reason' => $reason, 'detail' => $detail ), $extra ) );
    }

    






    protected function sent( string $detail, string $applies = 'now' ): Layer_Result {
        return $this->layer( Layer_Result::CLEARED, array( 'applies' => $applies, 'detail' => $detail ) );
    }

    





    protected function parked( string $detail ): Layer_Result {
        return $this->layer( Layer_Result::QUEUED, array( 'detail' => $detail ) );
    }

    







    protected function outcome( $returned, string $detail ): Layer_Result {
        if ( $returned instanceof \WP_Error ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => sprintf( '%s refused the purge: %s', $this->label(), $returned->get_error_message() ) ) );
        }
        return $this->sent( $detail );
    }

    





    protected function helper_missing( string $helper ): Layer_Result {
        return $this->skip( 'api_missing', sprintf( '%s is active but has no %s in this version, so nothing was called.', $this->label(), $helper ) );
    }

    





    protected function path_of( string $url ): string {
        $path = \wp_parse_url( $url, PHP_URL_PATH );
        return '/' . ltrim( is_string( $path ) ? $path : '', '/' );
    }

    





    protected function is_home_url( string $url ): bool {
        return untrailingslashit( $this->path_of( $url ) ) === untrailingslashit( $this->path_of( (string) \home_url( '/' ) ) );
    }
}
