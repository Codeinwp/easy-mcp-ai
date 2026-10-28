<?php

namespace Easy_MCP_AI\Preview;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Preview_Link {

    const QUERY_VAR = 'easy_mcp_ai_preview';

    const MIN_MINUTES     = 5;
    const MAX_MINUTES     = 1440;
    const DEFAULT_MINUTES = 60;

    const SIGNATURE_LENGTH = 32;

    const UNPREVIEWABLE_STATUSES = array( 'trash', 'auto-draft', 'inherit' );

    private static ?int $query_id = null;

    private static int $post_id = 0;

    private static string $link = '';

    public static function url( object $post, int $user_id, int $expires ): string {
        return self::link_for( $post, self::token( (int) $post->ID, $user_id, $expires ) );
    }

    public static function token( int $post_id, int $user_id, int $expires ): string {
        return $user_id . '.' . $expires . '.' . self::signature( $post_id, $user_id, $expires );
    }

    public static function status_problem( object $post ): string {
        $status = (string) $post->post_status;
        if ( in_array( $status, self::UNPREVIEWABLE_STATUSES, true ) ) {
            return $status;
        }
        if ( ! \is_post_type_viewable( (string) $post->post_type ) ) {
            return 'not_viewable';
        }
        if ( self::is_public( $post ) ) {
            return 'public';
        }
        return '';
    }

    public static function is_public( object $post ): bool {
        $object = \get_post_status_object( (string) $post->post_status );
        return $object && ! empty( $object->public );
    }

    public static function decide( $raw, object $post, int $now ): array {
        if ( self::is_public( $post ) ) {
            return array(
                'action'   => 'redirect',
                'status'   => 302,
                'message'  => '',
                'location' => \get_permalink( $post ),
            );
        }
        $parts = self::parse( $raw );
        if ( null === $parts || ! self::matches( $parts, (int) $post->ID ) ) {
            return self::refusal( 403, \__( 'This preview link is not valid.', 'easy-mcp-ai' ) );
        }
        if ( $parts['expires'] <= $now ) {
            /* translators: %s: the date and time the link expired, in UTC. */
            return self::refusal( 410, sprintf( \__( 'This preview link expired on %s. Ask for a new one.', 'easy-mcp-ai' ), gmdate( 'Y-m-d H:i', $parts['expires'] ) . ' UTC' ) );
        }
        if ( '' !== self::status_problem( $post ) ) {
            return self::refusal( 404, \__( 'This post can no longer be previewed.', 'easy-mcp-ai' ) );
        }
        if ( ! \user_can( $parts['user_id'], 'edit_post', (int) $post->ID ) ) {
            return self::refusal( 403, \__( 'This preview link is no longer valid: the person who created it can no longer edit this post.', 'easy-mcp-ai' ) );
        }
        return array(
            'action'  => 'show',
            'status'  => 200,
            'message' => '',
        );
    }

    public static function register_front_end(): void {
        \add_action( 'pre_get_posts', array( __CLASS__, 'on_pre_get_posts' ) );
    }

    public static function on_pre_get_posts( object $query ): void {
        if ( ! $query->is_main_query() || ! $query->is_preview() || ! $query->is_singular() || $query->is_feed() ) {
            return;
        }
        \remove_action( 'pre_get_posts', array( __CLASS__, 'on_pre_get_posts' ) );
        self::$query_id = spl_object_id( $query );
        self::send_private_headers();
        \add_filter( 'wp_headers', array( __CLASS__, 'filter_wp_headers' ), PHP_INT_MAX );
        \add_filter( 'posts_results', array( __CLASS__, 'on_posts_results' ), 10, 2 );
    }

    public static function on_posts_results( $posts, object $query ) {
        if ( spl_object_id( $query ) !== self::$query_id ) {
            return $posts;
        }
        \remove_filter( 'posts_results', array( __CLASS__, 'on_posts_results' ), 10 );
        self::$query_id = null;
        if ( empty( $posts ) || ! is_array( $posts ) ) {
            return $posts;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The link's signature is the check.
        $raw      = isset( $_GET[ self::QUERY_VAR ] ) ? \sanitize_text_field( \wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
        $post     = self::pick( $raw, array_values( $posts ) );
        $decision = self::decide( $raw, $post, time() );
        if ( 'redirect' === $decision['action'] ) {
            \wp_safe_redirect( $decision['location'], $decision['status'] );
            exit;
        }
        if ( 'refuse' === $decision['action'] ) {
            \wp_die( \esc_html( $decision['message'] ), \esc_html__( 'Preview link', 'easy-mcp-ai' ), array( 'response' => $decision['status'] ) );
        }
        self::show( $post, $raw );
        return array( $post );
    }

    public static function allow_reading( array $caps, string $cap, int $user_id, array $args ): array {
        if ( 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === self::$post_id ) {
            return array( 'exist' );
        }
        return $caps;
    }

    public static function close_for_post( $open, int $post_id ) {
        return (int) $post_id === self::$post_id ? false : $open;
    }

    public static function page_link( $link, int $page ) {
        if ( '' === self::$link || (int) \get_the_ID() !== self::$post_id || ! is_string( $link ) ) {
            return $link;
        }
        $url = $page > 1 ? \add_query_arg( 'page', $page, self::$link ) : self::$link;
        return (string) preg_replace_callback(
            '/href=(["\'])[^"\']*\1/',
            static function ( array $m ) use ( $url ): string {
                return 'href=' . $m[1] . \esc_url( $url ) . $m[1];
            },
            $link,
            1
        );
    }

    public static function filter_wp_headers( $headers ): array {
        return array_merge( (array) $headers, \wp_get_nocache_headers(), self::private_headers() );
    }

    public static function private_headers(): array {
        return array(
            'Cache-Control'   => 'no-cache, must-revalidate, max-age=0, no-store, private',
            'X-Robots-Tag'    => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        );
    }

    public static function reset(): void {
        self::$query_id = null;
        self::$post_id  = 0;
        self::$link     = '';
    }

    private static function link_for( object $post, string $token ): string {
        return \add_query_arg(
            array(
                'preview'       => 'true',
                self::QUERY_VAR => $token,
            ),
            \get_permalink( $post )
        );
    }

    private static function signature( int $post_id, int $user_id, int $expires ): string {
        return substr( hash_hmac( 'sha256', 'easy-mcp-ai-preview|' . $post_id . '|' . $user_id . '|' . $expires, \wp_salt( 'auth' ) ), 0, self::SIGNATURE_LENGTH );
    }

    private static function matches( array $parts, int $post_id ): bool {
        return hash_equals( self::signature( $post_id, $parts['user_id'], $parts['expires'] ), $parts['signature'] );
    }

    private static function pick( string $raw, array $posts ): object {
        $parts = self::parse( $raw );
        if ( null !== $parts ) {
            foreach ( $posts as $post ) {
                if ( is_object( $post ) && self::matches( $parts, (int) $post->ID ) ) {
                    return $post;
                }
            }
        }
        return $posts[0];
    }

    private static function parse( $raw ): ?array {
        if ( ! is_string( $raw ) || ! preg_match( '/^([1-9][0-9]{0,18})\.([1-9][0-9]{0,18})\.([0-9a-f]{' . self::SIGNATURE_LENGTH . '})\z/', $raw, $m ) ) {
            return null;
        }
        return array(
            'user_id'   => (int) $m[1],
            'expires'   => (int) $m[2],
            'signature' => $m[3],
        );
    }

    private static function refusal( int $status, string $message ): array {
        return array(
            'action'  => 'refuse',
            'status'  => $status,
            'message' => $message,
        );
    }

    private static function show( object $post, string $raw ): void {
        self::$link        = self::link_for( $post, $raw );
        $post->post_status = 'publish';
        self::$post_id     = (int) $post->ID;
        \add_filter( 'wp_link_pages_link', array( __CLASS__, 'page_link' ), 10, 2 );
        \add_filter( 'map_meta_cap', array( __CLASS__, 'allow_reading' ), 10, 4 );
        \add_filter( 'comments_open', array( __CLASS__, 'close_for_post' ), 10, 2 );
        \add_filter( 'pings_open', array( __CLASS__, 'close_for_post' ), 10, 2 );
        \add_filter( 'wp_robots', 'wp_robots_no_robots' );
    }

    private static function send_private_headers(): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        if ( headers_sent() ) {
            return;
        }
        \nocache_headers();
        foreach ( self::private_headers() as $name => $value ) {
            header( $name . ': ' . $value );
        }
    }
}
