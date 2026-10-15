<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































class Embed_Handler extends Command_Handler {

    
    const NOT_CAPTURED_FETCH = 'oEmbed cache: the HTML WordPress may store for this URL (an oembed_cache post, or _oembed_ meta of --post-id) is a cache it refreshes itself, so wp_cli_run reports changed:false for it.';

    
    const SKIP_SANITIZATION = "Sorry, you are not allowed to do this: --skip-sanitization caches the provider's HTML unfiltered and WordPress renders it on the site, so wp_cli_run needs the unfiltered_html capability for it.";

    
    
    

    
    public function preflight_fetch( Invocation $inv, Context $ctx ): void {
        $raw = Read_Value::flag( $inv->assoc, 'raw' );
        if ( ! $raw && Read_Value::flag( $inv->assoc, 'skip-sanitization' ) && ! $this->user_can( 'unfiltered_html' ) ) {
            $ctx->out->error( self::SKIP_SANITIZATION );
        }
        $post_id = Read_Value::flag( $inv->assoc, 'post-id' );
        if ( ! $raw && $post_id && null !== $this->post_row( (string) $post_id ) && ! $this->user_can( 'edit_post', (int) $post_id ) ) {
            $ctx->out->error( sprintf( 'Sorry, you are not allowed to do this: --post-id=%s caches the embed in that post\'s meta, and wp_cli_run needs the edit_post capability for post %d.', (string) $post_id, (int) $post_id ) );
        }
    }

    
    public function preflight_trigger( Invocation $inv, Context $ctx ): void {
        Ids::require_digits( $ctx, (string) $inv->args[0], 'a post ID' );
        $post = $this->post_row( (string) $inv->args[0] );
        if ( null !== $post && ! $this->user_can( 'edit_post', (int) $post['ID'] ) ) {
            $ctx->out->error( sprintf( 'Sorry, you are not allowed to do this: wp_cli_run needs the edit_post capability for post %d.', (int) $post['ID'] ) );
        }
    }

    
    public function is_raw( Invocation $inv ): bool {
        return (bool) Read_Value::flag( $inv->assoc, 'raw' );
    }

    
    
    

    




    public function fetch( Invocation $inv, Context $ctx ): void {
        $assoc_args          = $inv->assoc;
        $url                 = (string) $inv->args[0];
        $raw                 = Read_Value::flag( $assoc_args, 'raw' );
        $raw_format          = Read_Value::flag( $assoc_args, 'raw-format' );
        $post_id             = Read_Value::flag( $assoc_args, 'post-id' );
        $discover            = Read_Value::flag( $assoc_args, 'discover' );
        $response_size_limit = Read_Value::flag( $assoc_args, 'limit-response-size' );

        $oembed_args = self::oembed_args( Read_Value::flag( $assoc_args, 'width' ), Read_Value::flag( $assoc_args, 'height' ), $discover );

        $discover = null === $discover ? true : (bool) $discover;

        self::fetch_usage_errors( $discover, $response_size_limit, $raw, $raw_format, $ctx );

        if ( $raw ) {
            $oembed_args['discover'] = $discover;
            $this->print_raw( $url, $oembed_args, $discover, $response_size_limit ? (string) $response_size_limit : null, $raw_format, $ctx );
            return;
        }

        $ctx->not_captured( self::NOT_CAPTURED_FETCH );

        if ( $post_id && null === $this->post_row( (string) $post_id ) ) {
            $ctx->out->warning( sprintf( "Post id '%s' not found.", (string) $post_id ) );
        }

        $html = $this->html_fetch(
            $url,
            $oembed_args,
            array(
                'post_id'           => $post_id ? (string) $post_id : null,
                'discover'          => $discover,
                'skip_sanitization' => (bool) Read_Value::flag( $assoc_args, 'skip-sanitization' ),
                'skip_cache'        => (bool) Read_Value::flag( $assoc_args, 'skip-cache' ),
                'do_shortcode'      => (bool) Read_Value::flag( $assoc_args, 'do-shortcode' ),
                'limit'             => $response_size_limit ? (string) $response_size_limit : null,
            )
        );

        if ( false === $html ) {
            $ctx->out->error( 'There was an error fetching the oEmbed data.' );
        }

        $ctx->out->log( (string) $html );
    }

    








    private static function oembed_args( $width, $height, $discover ): array {
        $oembed_args = array();
        if ( null !== $width ) {
            $oembed_args['width'] = $width;
        }
        if ( null !== $height ) {
            $oembed_args['height'] = $height;
        }
        if ( null !== $discover ) {
            $oembed_args['discover'] = $discover ? '1' : '0';
        }
        return $oembed_args;
    }

    








    private static function fetch_usage_errors( bool $discover, $response_size_limit, $raw, $raw_format, Context $ctx ): void {
        if ( ! $discover && null !== $response_size_limit ) {
            $ctx->out->error( "The 'limit-response-size' option can only be used with discovery." );
        }
        if ( ! $raw && null !== $raw_format ) {
            $ctx->out->error( "The 'raw-format' option can only be used with the 'raw' option." );
        }
    }

    







    private function print_raw( string $url, array $oembed_args, bool $discover, ?string $limit, $raw_format, Context $ctx ): void {
        $result = $this->raw_fetch( $url, $oembed_args, $limit );
        if ( 'no_provider' === $result['state'] ) {
            $ctx->out->error( $discover ? 'No oEmbed provider found for given URL.' : 'No oEmbed provider found for given URL. Maybe try discovery?' );
        }
        if ( 'error' === $result['state'] ) {
            $ctx->out->error( 'There was an error fetching the oEmbed data.' );
        }
        if ( 'xml' !== $raw_format ) {
            $ctx->out->log( (string) $result['json'] );
            return;
        }
        if ( ! class_exists( 'SimpleXMLElement' ) ) {
            $ctx->out->error( "The PHP extension 'SimpleXMLElement' is not available but is required for XML-formatted output." );
        }
        $xml = $this->oembed_create_xml( (array) json_decode( (string) $result['json'] ) );
        $ctx->out->log( false === $xml ? '' : (string) $xml );
    }

    





    public function describe_fetch( Invocation $inv ): array {
        $url     = self::url_without_query( (string) $inv->args[0] );
        $post_id = Read_Value::flag( $inv->assoc, 'post-id' );
        if ( Read_Value::flag( $inv->assoc, 'raw' ) ) {
            return array( sprintf( '%s → fetch the oEmbed data from its provider; nothing is cached', $url ) );
        }
        if ( $post_id && null !== $this->post_row( (string) $post_id ) ) {
            return array( sprintf( '%s → fetch its embed HTML; WordPress caches it in post %d\'s _oembed_ meta', $url, (int) $post_id ) );
        }
        return array( sprintf( '%s → fetch its embed HTML; WordPress caches it as an oembed_cache post', $url ) );
    }

    
    public function cache_find( Invocation $inv, Context $ctx ): void {
        $url      = (string) $inv->args[0];
        $width    = Read_Value::flag( $inv->assoc, 'width' );
        $height   = Read_Value::flag( $inv->assoc, 'height' );
        $discover = Read_Value::flag( $inv->assoc, 'discover' );

        $oembed_args = array();
        if ( null !== $width ) {
            $oembed_args['width'] = $width;
        }
        if ( null !== $height ) {
            $oembed_args['height'] = $height;
        }
        if ( null !== $discover ) {
            $discovers = array( $discover ? '1' : '0' );
        } else {
            $discovers = array( null, '1', '0' );
        }

        $attr = array_merge( $this->embed_defaults( $url ), $oembed_args );

        foreach ( $discovers as $value ) {
            if ( null !== $value ) {
                $attr['discover'] = $value;
            }
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- WP_Embed::shortcode()'s cache key.
            $key_suffix     = md5( $url . serialize( $attr ) );
            $cached_post_id = $this->find_oembed_post_id( $key_suffix );
            if ( $cached_post_id ) {
                $ctx->out->line( (string) $cached_post_id );
                return;
            }
        }

        $ctx->out->error( 'No cache post ID found!' );
    }

    
    public function cache_trigger( Invocation $inv, Context $ctx ): void {
        $post_id = (string) $inv->args[0];
        $post    = $this->post_row( $post_id );

        if ( null === $post || empty( $post['ID'] ) ) {
            $ctx->out->warning( sprintf( "Post id '%s' not found.", $post_id ) );
            $ctx->fail_missing_target( sprintf( "Post id '%s' not found.", $post_id ) );
            return;
        }
        $ctx->require_cap( 'edit_post', (int) $post['ID'] );

        if ( ! in_array( $post['post_type'], $this->cache_oembed_types(), true ) ) {
            $ctx->out->warning( sprintf( "Cannot cache oEmbed results for '%s' post type", (string) $post['post_type'] ) );
            return;
        }

        $before = $this->oembed_meta( (int) $post['ID'] );
        $this->cache_oembed( (int) $post['ID'] );
        if ( $this->oembed_meta( (int) $post['ID'] ) !== $before ) {
            $ctx->mark_changed();
        }

        $ctx->out->success( 'Caching triggered!' );
    }

    




    public function describe_trigger( Invocation $inv ): array {
        $post = $this->post_row( (string) $inv->args[0] );
        if ( null === $post ) {
            return array( sprintf( "Post id '%s' not found → nothing is cached", (string) $inv->args[0] ) );
        }
        return array(
            sprintf(
                "Post %d ('%s', %s, %d _oembed_ meta keys) → fetch every embed in its content and cache the HTML in its _oembed_ meta",
                (int) $post['ID'],
                (string) $post['post_title'],
                (string) $post['post_type'],
                count( $this->oembed_meta( (int) $post['ID'] ) )
            ),
        );
    }

    
    public function handler_list( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        $this->formatter( $ctx, $assoc, array( 'id', 'regex' ) )->display_items( $this->embed_handlers() );
    }

    
    public function provider_list( Invocation $inv, Context $ctx ): void {
        $assoc       = $inv->assoc;
        $force_regex = Read_Value::flag( $assoc, 'force-regex' );
        $providers   = array();
        foreach ( $this->oembed_providers() as $matchmask => $data ) {
            list( $providerurl, $regex ) = $data;
            if ( $force_regex && ! $regex ) {
                $matchmask = self::mask_to_regex( (string) $matchmask );
            }
            $providers[] = array(
                'format'   => $matchmask,
                'endpoint' => $providerurl,
                'regex'    => $regex ? '1' : '0',
            );
        }
        $this->formatter( $ctx, $assoc, array( 'format', 'endpoint' ) )->display_items( $providers );
    }

    
    public function provider_match( Invocation $inv, Context $ctx ): void {
        $url                 = (string) $inv->args[0];
        $discover            = Read_Value::flag( $inv->assoc, 'discover', true );
        $response_size_limit = Read_Value::flag( $inv->assoc, 'limit-response-size' );
        $link_type           = Read_Value::flag( $inv->assoc, 'link-type' );

        if ( ! $discover && ( null !== $response_size_limit || null !== $link_type ) ) {
            $ctx->out->error( self::discovery_only( $response_size_limit, $link_type ) );
        }

        $provider = $this->match_provider( $url, (bool) $discover, $response_size_limit ? (string) $response_size_limit : null, $link_type ? (string) $link_type : null );

        if ( ! $provider ) {
            $ctx->out->error( ! $discover ? 'No oEmbed provider found for given URL. Maybe try discovery?' : 'No oEmbed provider found for given URL.' );
        }

        $ctx->out->line( (string) $provider );
    }

    
    
    

    






    private static function discovery_only( $response_size_limit, $link_type ): string {
        if ( null !== $response_size_limit && null !== $link_type ) {
            return "The 'limit-response-size' and 'link-type' options can only be used with discovery.";
        }
        if ( null !== $response_size_limit ) {
            return "The 'limit-response-size' option can only be used with discovery.";
        }
        return "The 'link-type' option can only be used with discovery.";
    }

    
    public static function mask_to_regex( string $matchmask ): string {
        $matchmask = '#' . str_replace( '___wildcard___', '(.+)', preg_quote( str_replace( '*', '___wildcard___', $matchmask ), '#' ) ) . '#i';
        return (string) preg_replace( '|^#http\\\://|', '#https?\://', $matchmask );
    }

    
    private static function url_without_query( string $url ): string {
        $cut = strcspn( $url, '?#' );
        return $cut < strlen( $url ) ? substr( $url, 0, $cut ) . '?[…]' : $url;
    }

    






    protected function oembed_create_xml( $data, ?\SimpleXMLElement $node = null ) {
        if ( ! is_array( $data ) || empty( $data ) ) {
            return false;
        }
        if ( null === $node ) {
            $node = new \SimpleXMLElement( '<oembed></oembed>' );
        }
        foreach ( $data as $key => $value ) {
            if ( is_numeric( $key ) ) {
                $key = 'oembed';
            }
            if ( is_array( $value ) ) {
                $item = $node->addChild( (string) $key );
                $this->oembed_create_xml( $value, $item );
            } else {
                $node->addChild( (string) $key, \esc_html( $value ) );
            }
        }
        return $node->asXML();
    }

    
    
    

    








    protected function call( string $name, array $args, callable $real ) {
        return $real();
    }

    
    protected function user_can( string $cap, int ...$args ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array_merge( array( $cap ), $args ),
            static function () use ( $cap, $args ) {
                return \current_user_can( $cap, ...$args );
            }
        );
    }

    




    protected function post_row( string $id ): ?array {
        $row = $this->call(
            __FUNCTION__,
            array( $id ),
            static function () use ( $id ) {
                $post = \get_post( $id );
                return $post ? array(
                    'ID'         => (int) $post->ID,
                    'post_type'  => (string) $post->post_type,
                    'post_title' => (string) $post->post_title,
                ) : null;
            }
        );
        return is_array( $row ) ? $row : null;
    }

    




    protected function oembed_meta( int $post_id ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $post_id ),
            static function () use ( $post_id ) {
                $out = array();
                foreach ( (array) \get_post_custom( $post_id ) as $key => $values ) {
                    if ( '_oembed_' === substr( (string) $key, 0, 8 ) ) {
                        $out[ (string) $key ] = array_values( array_map( 'strval', (array) $values ) );
                    }
                }
                ksort( $out );
                return $out;
            }
        );
    }

    
    protected function cache_oembed_types(): array {
        return (array) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook.
                return array_values( (array) \apply_filters( 'embed_cache_oembed_types', \get_post_types( array( 'show_ui' => true ) ) ) );
            }
        );
    }

    
    protected function cache_oembed( int $post_id ): void {
        $this->call(
            __FUNCTION__,
            array( $post_id ),
            static function () use ( $post_id ) {
                global $wp_embed;
                $post_id_before  = $wp_embed->post_ID;
                $usecache_before = $wp_embed->usecache;
                try {
                    $wp_embed->cache_oembed( $post_id );
                } finally {
                    $wp_embed->post_ID  = $post_id_before;
                    $wp_embed->usecache = $usecache_before;
                }
                return null;
            }
        );
    }

    
    protected function embed_defaults( string $url ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $url ),
            static function () use ( $url ) {
                return \wp_embed_defaults( $url );
            }
        );
    }

    




    protected function find_oembed_post_id( string $key ) {
        return $this->call(
            __FUNCTION__,
            array( $key ),
            static function () use ( $key ) {
                global $wp_embed;
                return $wp_embed->find_oembed_post_id( $key );
            }
        );
    }

    
    protected function embed_handlers(): array {
        return (array) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                global $wp_embed;
                $handlers = (array) $wp_embed->handlers;
                
                ksort( $handlers );
                $rows = array();
                foreach ( $handlers as $priority => $list ) {
                    foreach ( (array) $list as $id => $handler ) {
                        $rows[] = array(
                            'id'       => $id,
                            'regex'    => $handler['regex'],
                            'callback' => $handler['callback'],
                            'priority' => $priority,
                        );
                    }
                }
                return $rows;
            }
        );
    }

    
    protected function oembed_providers(): array {
        return (array) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                $oembed = self::new_oembed();
                try {
                    return (array) $oembed->providers;
                } finally {
                    self::drop_oembed( $oembed );
                }
            }
        );
    }

    




    protected function match_provider( string $url, bool $discover, ?string $limit, ?string $link_type ) {
        return $this->call(
            __FUNCTION__,
            array( $url, $discover, $limit, $link_type ),
            static function () use ( $url, $discover, $limit, $link_type ) {
                $filters = array();
                if ( $limit ) {
                    $filters['oembed_remote_get_args'] = array(
                        static function ( $args ) use ( $limit ) {
                            $args['limit_response_size'] = $limit;
                            return $args;
                        },
                        10,
                    );
                }
                if ( $link_type ) {
                    $filters['oembed_linktypes'] = array(
                        static function ( $linktypes ) use ( $link_type ) {
                            foreach ( $linktypes as $mime_type => $linktype_format ) {
                                if ( $link_type !== $linktype_format ) {
                                    unset( $linktypes[ $mime_type ] );
                                }
                            }
                            return $linktypes;
                        },
                        10,
                    );
                }
                $oembed = self::new_oembed();
                self::add_filters( $filters );
                try {
                    return $oembed->get_provider( $url, array( 'discover' => $discover ) );
                } finally {
                    self::remove_filters( $filters );
                    self::drop_oembed( $oembed );
                }
            }
        );
    }

    





    protected function raw_fetch( string $url, array $oembed_args, ?string $limit ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $url, $oembed_args, $limit ),
            static function () use ( $url, $oembed_args, $limit ) {
                $filters = array(
                    'oembed_dataparse' => array(
                        static function ( $ret, $data, $u ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
                            return $data;
                        },
                        PHP_INT_MAX,
                        3,
                    ),
                );
                if ( $limit ) {
                    $filters['oembed_remote_get_args'] = array( self::limit_filter( $limit ), PHP_INT_MAX );
                }
                $oembed = self::new_oembed();
                self::add_filters( $filters );
                try {
                    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook.
                    $data = \apply_filters( 'pre_oembed_result', null, $url, $oembed_args );
                    if ( null === $data ) {
                        $provider = $oembed->get_provider( $url, $oembed_args );
                        if ( ! $provider ) {
                            return array(
                                'state' => 'no_provider',
                                'json'  => null,
                            );
                        }
                        $data = $oembed->fetch( $provider, $url, $oembed_args );
                    }
                    if ( false === $data ) {
                        return array(
                            'state' => 'error',
                            'json'  => null,
                        );
                    }
                    return array(
                        'state' => 'ok',
                        'json'  => json_encode( $data ), // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
                    );
                } finally {
                    self::remove_filters( $filters );
                    self::drop_oembed( $oembed );
                }
            }
        );
    }

    








    protected function html_fetch( string $url, array $oembed_args, array $options ) {
        return $this->call(
            __FUNCTION__,
            array( $url, $oembed_args, $options ),
            static function () use ( $url, $oembed_args, $options ) {
                return self::shortcode_html( $url, $oembed_args, $options );
            }
        );
    }

    









    private static function shortcode_html( string $url, array $oembed_args, array $options ) {
        global $wp_embed;
        $filters           = self::html_fetch_filters( $options );
        $had_post          = array_key_exists( 'post', $GLOBALS );
        $post_before       = $had_post ? $GLOBALS['post'] : null;
        $usecache_before   = $wp_embed->usecache;
        $sanitizer_removed = false;
        try {
            if ( null !== $options['post_id'] ) {
                // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP-CLI's own override, restored below.
                $GLOBALS['post'] = \get_post( $options['post_id'] );
            }
            if ( $options['skip_sanitization'] ) {
                $sanitizer_removed = \remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
            }
            $wp_embed->usecache = ! $options['skip_cache'];
            self::add_filters( $filters );

            $html = $wp_embed->shortcode( $oembed_args, $url );

            if ( false !== $html && '[' === substr( (string) $html, 0, 1 ) && $options['do_shortcode'] ) {
                $html = \do_shortcode( $html, true );
            }
            return $html;
        } finally {
            self::remove_filters( $filters );
            if ( $sanitizer_removed ) {
                \add_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10, 3 );
            }
            $wp_embed->usecache = $usecache_before;
            if ( $had_post ) {
                // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the request's own value.
                $GLOBALS['post'] = $post_before;
            } else {
                unset( $GLOBALS['post'] );
            }
        }
    }

    






    private static function html_fetch_filters( array $options ): array {
        $filters = array(
            'embed_oembed_discover' => array( $options['discover'] ? '__return_true' : '__return_false', PHP_INT_MAX ),
        );
        if ( $options['skip_cache'] ) {
            $filters['oembed_ttl'] = array( '__return_zero', PHP_INT_MAX );
        }
        if ( $options['limit'] ) {
            $filters['oembed_remote_get_args'] = array( self::limit_filter( (string) $options['limit'] ), PHP_INT_MAX );
        }
        return $filters;
    }

    
    
    

    
    private static function limit_filter( string $limit ): callable {
        return static function ( $args ) use ( $limit ) {
            $args['limit_response_size'] = $limit;
            return $args;
        };
    }

    
    private static function add_filters( array $filters ): void {
        foreach ( $filters as $hook => $filter ) {
            \add_filter( $hook, $filter[0], $filter[1], isset( $filter[2] ) ? $filter[2] : 1 );
        }
    }

    
    private static function remove_filters( array $filters ): void {
        foreach ( $filters as $hook => $filter ) {
            \remove_filter( $hook, $filter[0], $filter[1] );
        }
    }

    
    private static function new_oembed(): \WP_oEmbed {
        if ( ! class_exists( '\\WP_oEmbed' ) ) {
            require_once ABSPATH . WPINC . '/class-wp-oembed.php';
        }
        return new \WP_oEmbed();
    }

    
    private static function drop_oembed( \WP_oEmbed $oembed ): void {
        \remove_filter( 'oembed_dataparse', array( $oembed, '_strip_newlines' ), 10 );
    }
}
