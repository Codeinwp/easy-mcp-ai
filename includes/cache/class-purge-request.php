<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





final class Purge_Request {

    const SCOPE_URLS  = 'urls';
    const SCOPE_POSTS = 'posts';
    const SCOPE_SITE  = 'site';

    
    const SCOPES = array( self::SCOPE_URLS, self::SCOPE_POSTS, self::SCOPE_SITE );

    
    public $scope;

    
    public $urls = array();

    
    public $post_ids = array();

    
    public $caches = null;

    
    public $dry_run = false;

    
    public $include_cdn = false;

    
    public $include_object_cache = false;

    
    public $deadline = 0.0;

    





    public function __construct( string $scope, array $urls = array(), array $post_ids = array() ) {
        if ( ! in_array( $scope, self::SCOPES, true ) ) {
            throw new \InvalidArgumentException( 'Unknown purge scope: ' . $scope ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->scope    = $scope;
        $this->urls     = array_values( array_map( 'strval', $urls ) );
        $this->post_ids = array_values( array_map( 'intval', $post_ids ) );
    }

    





    public function selects( string $id ): bool {
        return null === $this->caches || in_array( $id, $this->caches, true );
    }
}
