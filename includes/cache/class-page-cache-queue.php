<?php

namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Page_Cache_Queue {

    const ACTION = 'easy_mcp_ai_clear_page_cache';

    const TIME_BUDGET = 10;

    private static $post_ids = array();

    private static $urls = array();

    private static $listening = false;

    private static $purger = null;

    public static function listen(): void {
        if ( self::$listening ) {
            return;
        }
        self::$listening = true;
        \add_action( self::ACTION, array( self::class, 'queue' ), 10, 2 );
    }

    public static function queue( $post_ids, $urls = array() ): void {
        foreach ( (array) $post_ids as $id ) {
            $id = is_int( $id ) || is_string( $id ) ? filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
            if ( false !== $id ) {
                self::$post_ids[ $id ] = true;
            }
        }
        foreach ( (array) $urls as $url ) {
            if ( is_string( $url ) && '' !== $url ) {
                self::$urls[ $url ] = true;
            }
        }
    }

    public static function flush(): void {
        if ( array() === self::$post_ids && array() === self::$urls ) {
            return;
        }
        $post_ids       = array_keys( self::$post_ids );
        $urls           = array_keys( self::$urls );
        self::$post_ids = array();
        self::$urls     = array();

        $deadline = Cache_Purger::deadline( self::TIME_BUDGET );
        if ( array() !== $post_ids ) {
            self::clear_posts( $post_ids, $deadline );
        }
        if ( array() !== $urls ) {
            self::clear_urls( $urls, $deadline );
        }
    }

    public static function use_purger( ?Cache_Purger $purger ): void {
        self::$purger = $purger;
    }

    public static function reset(): void {
        self::$post_ids  = array();
        self::$urls      = array();
        self::$listening = false;
        self::$purger    = null;
    }

    private static function clear_posts( array $post_ids, float $deadline ): void {
        try {
            $expanded = Post_Urls::expand( $post_ids );
            self::run( new Purge_Request( Purge_Request::SCOPE_POSTS, array_column( $expanded['rows'], 'url' ), $post_ids ), $expanded['rows'], $deadline );
        } catch ( \Throwable $e ) {
            unset( $e );
        }
    }

    private static function clear_urls( array $urls, float $deadline ): void {
        try {
            $rows = array();
            foreach ( $urls as $url ) {
                try {
                    $url = Url_Guard::check( $url );
                } catch ( \InvalidArgumentException $e ) {
                    continue;
                }
                $rows[ $url ] = array( 'url' => $url, 'source' => 'url' );
            }
            if ( array() !== $rows ) {
                self::run( new Purge_Request( Purge_Request::SCOPE_URLS, array_keys( $rows ) ), array_values( $rows ), $deadline );
            }
        } catch ( \Throwable $e ) {
            unset( $e );
        }
    }

    private static function run( Purge_Request $request, array $rows, float $deadline ): void {
        if ( null === self::$purger ) {
            self::$purger = new Cache_Purger();
        }
        $request->deadline = $deadline;
        self::$purger->run( $request, $rows );
    }
}
