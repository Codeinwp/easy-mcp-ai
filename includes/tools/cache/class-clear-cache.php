<?php
namespace Easy_MCP_AI\Tools\Cache;

use Easy_MCP_AI\Cache\Cache_Purger;
use Easy_MCP_AI\Cache\Cache_Registry;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Post_Urls;
use Easy_MCP_AI\Cache\Purge_Request;
use Easy_MCP_AI\Cache\Url_Guard;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__, 2 ) . '/cache/class-cache-purger.php';
require_once dirname( __DIR__, 2 ) . '/cache/class-post-urls.php';
require_once dirname( __DIR__, 2 ) . '/cache/class-url-guard.php';











class Clear_Cache extends Base_Tool {

    
    const MAX_ITEMS = 50;

    
    const COOLDOWN_SECONDS = 300;

    
    const COOLDOWN_OPTION = 'easy_mcp_ai_cache_site_purge_at';

    
    const TIME_BUDGET = 25;

    
    private $purger = null;

    public function get_name() {
        return 'wp_clear_cache';
    }

    public function get_description() {
        return 'Clears this site\'s page, asset and CDN caches through each cache plugin\'s own purge code. Caching plugins already clear a post\'s page when it is saved, and this site\'s SEO, custom-field and post-term tools clear it after they write, so use this when a change is still not visible, or after changes neither covers: `scope: "posts"` with `post_ids` after unpublishing or trashing an item; `scope: "site"` (administrators only) after changing menus, widgets, theme or Customizer settings, CSS, global styles, templates, patterns or site settings, or switching a theme or plugin (at most once every 5 minutes); `scope: "urls"` with this site\'s URLs (no query string), for example a redirect\'s source. `dry_run: true` shows what would be cleared. `include_cdn` and `include_object_cache` (`site` only) also clear the whole CDN zone and the object cache. Each `layers[]` entry has a `status`: `cleared` (the cache plugin\'s own purge ran), `queued`, `skipped` (with a reason) or `failed`; `changed: false` means no cache was cleared, and `not_covered` lists caches only the site owner can clear. To check a page, fetch `verify.urls` after `verify.after_seconds`.';
    }

    public function get_category() {
        return 'cache';
    }

    public function get_required_capability() {
        
        
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'scope'                => array(
                    'type'        => 'string',
                    'enum'        => Purge_Request::SCOPES,
                    'description' => 'What to clear: `urls` (the given URLs), `posts` (the given posts and the pages that list them) or `site` (everything).',
                ),
                'urls'                 => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'maxItems'    => self::MAX_ITEMS,
                    'description' => 'For scope `urls` only: absolute URLs of this site, or paths starting with /, with no query string. At most 50.',
                ),
                'post_ids'             => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'maxItems'    => self::MAX_ITEMS,
                    'description' => 'For scope `posts` only: IDs of posts, pages or custom post type items. At most 50.',
                ),
                'caches'               => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => Cache_Registry::ids(),
                    ),
                    'description' => 'Optional: clear only these caches (ids as `layers[].id` shows them). Omit to clear every cache that applies. An id not active on this site is refused.',
                ),
                'dry_run'              => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Report what each cache would do without clearing anything.',
                ),
                'include_cdn'          => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Scope `site` only: also purge a whole CDN zone (for example Cloudflare), which every visitor then refills from the server.',
                ),
                'include_object_cache' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Scope `site` only: also flush the persistent object cache (Redis, Memcached). It resets this plugin\'s rate limits and makes the server forget this session\'s settings; on a multisite network it needs a super admin.',
                ),
            ),
            'required'   => array( 'scope' ),
        );
    }

    








    public function get_approval_policy( array $arguments ) {
        $site = isset( $arguments['scope'] ) && Purge_Request::SCOPE_SITE === $arguments['scope'];
        return $site && ! self::flag( $arguments, 'dry_run' ) ? 'always' : 'never';
    }

    







    public function describe( array $arguments ) {
        $text = __( 'Clear every page and asset cache of this site that its cache plugins let you clear.', 'easy-mcp-ai' );
        if ( self::flag( $arguments, 'include_cdn' ) ) {
            $text .= ' ' . __( 'Also purge the whole CDN zone (for example Cloudflare), which every visitor then refills from the server.', 'easy-mcp-ai' );
        }
        if ( self::flag( $arguments, 'include_object_cache' ) ) {
            $text .= ' ' . __( 'Also flush the object cache (Redis or Memcached), which resets the rate limits and sign-in lockouts of AI connections.', 'easy-mcp-ai' );
        }
        return $text . ' ' . __( 'Visitors refill the caches from the server afterwards; no content or setting is deleted.', 'easy-mcp-ai' );
    }

    







    public function precheck( array $arguments ): void {
        $request = $this->build_request( $arguments );
        $this->refuse_cooldown( $request['request'] );
    }

    






    public function execute( array $arguments ) {
        $built   = $this->build_request( $arguments );
        $request = $built['request'];
        $claim   = Purge_Request::SCOPE_SITE === $request->scope && ! $request->dry_run ? $this->claim_site_slot() : null;
        $request->deadline = Cache_Purger::deadline( self::TIME_BUDGET );

        try {
            $reply = $this->purger()->run( $request, $built['rows'] );
        } catch ( \Throwable $e ) {
            if ( null !== $claim ) {
                $this->release_site_slot( $claim );
            }
            throw $e;
        }
        if ( array() !== $built['dropped'] ) {
            $reply['dropped_urls'] = $built['dropped'];
        }
        if ( $request->include_object_cache && ! in_array( 'object-cache', array_column( $reply['layers'], 'id' ), true ) ) {
            
            $reply['notes'][] = 'No persistent object cache (Redis, Memcached) is in use on this site, so there was no object cache to flush.';
        }
        if ( null !== $claim && ! self::attempted( $reply ) ) {
            
            $this->release_site_slot( $claim );
        }
        return $reply;
    }

    





    public function with_purger( Cache_Purger $purger ): self {
        $this->purger = $purger;
        return $this;
    }

    




    private function purger(): Cache_Purger {
        if ( null === $this->purger ) {
            $this->purger = new Cache_Purger();
        }
        return $this->purger;
    }

    






    private function build_request( array $arguments ): array {
        $scope = $this->parse_scope( $arguments );
        $this->refuse_site_without_manage_options( $scope );
        $caches = $this->parse_caches( $arguments );
        $this->refuse_unused( $arguments, $scope );
        $ids  = Purge_Request::SCOPE_POSTS === $scope ? $this->parse_post_ids( $arguments ) : array();
        $urls = Purge_Request::SCOPE_URLS === $scope ? $this->parse_urls( $arguments ) : array();

        $rows    = array();
        $dropped = array();
        if ( Purge_Request::SCOPE_POSTS === $scope ) {
            $expanded = Post_Urls::expand( $ids );
            $rows     = $expanded['rows'];
            $dropped  = $expanded['dropped'];
        } else {
            foreach ( $urls as $url ) {
                $rows[] = array( 'url' => $url, 'source' => 'url' );
            }
        }
        if ( count( $rows ) > Url_Guard::MAX_URLS ) {
            throw new \InvalidArgumentException( sprintf( 'These posts resolve to %d URLs; one call clears at most %d. Pass fewer post_ids.', count( $rows ), Url_Guard::MAX_URLS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $request = new Purge_Request( $scope, array_column( $rows, 'url' ), $ids );
        $request->caches               = $caches;
        $request->dry_run              = self::flag( $arguments, 'dry_run' );
        $request->include_cdn          = self::flag( $arguments, 'include_cdn' );
        $request->include_object_cache = self::flag( $arguments, 'include_object_cache' );
        $this->refuse_inactive_caches( $caches );
        return array(
            'request' => $request,
            'rows'    => $rows,
            'dropped' => $dropped,
        );
    }

    









    private function refuse_site_without_manage_options( string $scope ): void {
        if ( Purge_Request::SCOPE_SITE === $scope && ! \current_user_can( 'manage_options' ) ) {
            throw new \RuntimeException( 'Clearing the whole site needs the manage_options capability (administrators). Clear the pages that changed with scope "posts" or "urls" instead.' );
        }
    }

    




    private function parse_scope( array $arguments ): string {
        $scope = isset( $arguments['scope'] ) ? $arguments['scope'] : null;
        if ( ! is_string( $scope ) || ! in_array( $scope, Purge_Request::SCOPES, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid scope: %s. It must be one of: urls, posts, site.', \wp_json_encode( $scope ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $scope;
    }

    




    private function parse_caches( array $arguments ): ?array {
        if ( ! isset( $arguments['caches'] ) || array() === $arguments['caches'] ) {
            return null;
        }
        if ( ! is_array( $arguments['caches'] ) ) {
            throw new \InvalidArgumentException( 'Invalid caches: it must be a list of cache ids.' );
        }
        $ids = array();
        foreach ( $arguments['caches'] as $id ) {
            if ( ! is_string( $id ) || ! in_array( $id, Cache_Registry::ids(), true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid caches value %s: it must be one of: %s.', \wp_json_encode( $id ), implode( ', ', Cache_Registry::ids() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $ids[] = $id;
        }
        return array_values( array_unique( $ids ) );
    }

    








    private function refuse_unused( array $arguments, string $scope ): void {
        $belongs = array(
            'urls'     => Purge_Request::SCOPE_URLS,
            'post_ids' => Purge_Request::SCOPE_POSTS,
        );
        foreach ( $belongs as $name => $owner ) {
            if ( $owner !== $scope && isset( $arguments[ $name ] ) && array() !== $arguments[ $name ] ) {
                throw new \InvalidArgumentException( sprintf( '%s is only used with scope "%s"; this call has scope "%s".', $name, $owner, $scope ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        foreach ( array( 'include_cdn', 'include_object_cache' ) as $name ) {
            if ( Purge_Request::SCOPE_SITE !== $scope && self::flag( $arguments, $name ) ) {
                throw new \InvalidArgumentException( sprintf( '%s only works with scope "site".', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    




    private function parse_post_ids( array $arguments ): array {
        $list = $this->required_list( $arguments, 'post_ids', 'posts' );
        $ids  = array();
        foreach ( $list as $value ) {
            $id = $this->parse_required_id( $value, 'post_ids value' );
            $this->refuse_missing_post( $id );
            if ( ! \current_user_can( 'read_post', $id ) ) {
                throw new \RuntimeException( sprintf( 'You cannot read post %d, so its pages cannot be cleared through you.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $ids[] = $id;
        }
        return array_values( array_unique( $ids ) );
    }

    




    private function parse_urls( array $arguments ): array {
        $urls = array();
        foreach ( $this->required_list( $arguments, 'urls', 'urls' ) as $value ) {
            if ( ! is_string( $value ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid urls value %s: each must be a URL string.', \wp_json_encode( $value ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $urls[] = Url_Guard::check( $value );
        }
        return array_values( array_unique( $urls ) );
    }

    








    private function required_list( array $arguments, string $name, string $scope ): array {
        if ( ! isset( $arguments[ $name ] ) || ! is_array( $arguments[ $name ] ) || array() === $arguments[ $name ] ) {
            throw new \InvalidArgumentException( sprintf( 'Missing %1$s: scope "%2$s" needs a non-empty %1$s list.', $name, $scope ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( count( $arguments[ $name ] ) > self::MAX_ITEMS ) {
            throw new \InvalidArgumentException( sprintf( 'Too many %s: at most %d per call.', $name, self::MAX_ITEMS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array_values( $arguments[ $name ] );
    }

    






    private function refuse_inactive_caches( ?array $caches ): void {
        if ( null === $caches ) {
            return;
        }
        $present = $this->purger()->present_ids();
        foreach ( $caches as $id ) {
            if ( ! in_array( $id, $present, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Cache "%s" is not active on this site. Active caches: %s.', $id, array() === $present ? 'none' : implode( ', ', $present ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    






    private function refuse_cooldown( Purge_Request $request ): void {
        if ( Purge_Request::SCOPE_SITE !== $request->scope || $request->dry_run ) {
            return;
        }
        self::refuse_if_recent( self::stamp( self::stored_stamp() ), time() );
    }

    






    private static function stored_stamp(): ?string {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
            $value = \get_option( self::COOLDOWN_OPTION, null );
            return null === $value ? null : (string) $value;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the cache is what can be stale.
        $value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::COOLDOWN_OPTION ) );
        return null === $value ? null : (string) $value;
    }

    





    private static function forget_cached_stamp(): void {
        $missing = \wp_cache_get( 'notoptions', 'options' );
        if ( is_array( $missing ) && isset( $missing[ self::COOLDOWN_OPTION ] ) ) {
            unset( $missing[ self::COOLDOWN_OPTION ] );
            \wp_cache_set( 'notoptions', $missing, 'options' );
        }
        \wp_cache_delete( self::COOLDOWN_OPTION, 'options' );
    }

    









    private function claim_site_slot(): array {
        global $wpdb;
        $now = time();
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            $previous = \get_option( self::COOLDOWN_OPTION, null );
            self::refuse_if_recent( self::stamp( $previous ), $now );
            \update_option( self::COOLDOWN_OPTION, $now, false );
            return array( 'previous' => null === $previous ? null : (string) $previous );
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic claim cannot go through the options cache.
        if ( 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::COOLDOWN_OPTION, (string) $now ) ) ) {
            self::forget_cached_stamp();
            return array( 'previous' => null );
        }
        $held = (string) self::stored_stamp();
        self::refuse_if_recent( self::stamp( $held ), $now );
        $won = 1 === (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, self::COOLDOWN_OPTION, $held ) );
        // phpcs:enable
        self::forget_cached_stamp();
        if ( ! $won ) {
            throw new \RuntimeException( sprintf( 'Another whole-site cache clear started at the same moment. Wait %1$d seconds (retry_after: %1$d) or clear specific URLs or posts instead.', self::COOLDOWN_SECONDS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( 'previous' => $held );
    }

    





    private function release_site_slot( array $claim ): void {
        if ( null === $claim['previous'] ) {
            \delete_option( self::COOLDOWN_OPTION );
            return;
        }
        \update_option( self::COOLDOWN_OPTION, $claim['previous'], false );
    }

    






    private static function stamp( $value ): int {
        return is_numeric( $value ) ? (int) $value : 0;
    }

    







    private static function refuse_if_recent( int $last, int $now ): void {
        $wait = $last + self::COOLDOWN_SECONDS - $now;
        if ( $last > 0 && $wait > 0 ) {
            throw new \RuntimeException( sprintf( 'A whole-site cache clear ran %d seconds ago. Wait %d seconds (retry_after: %d) or clear specific URLs or posts instead.', $now - $last, $wait, $wait ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    





    private static function attempted( array $reply ): bool {
        foreach ( (array) $reply['layers'] as $layer ) {
            if ( in_array( $layer['status'], array( Layer_Result::CLEARED, Layer_Result::QUEUED, Layer_Result::FAILED ), true ) ) {
                return true;
            }
        }
        return false;
    }

    






    private static function flag( array $arguments, string $name ): bool {
        return isset( $arguments[ $name ] ) && \rest_sanitize_boolean( $arguments[ $name ] );
    }
}
