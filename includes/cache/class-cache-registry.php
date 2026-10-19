<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






final class Cache_Registry {

    const ADAPTERS = array(
        
        'rank-math-sitemap'    => 'Easy_MCP_AI\\Cache\\Adapters\\Rank_Math_Sitemap',
        'seo-sitemaps'         => 'Easy_MCP_AI\\Cache\\Adapters\\Seo_Sitemaps',
        'woocommerce'          => 'Easy_MCP_AI\\Cache\\Adapters\\Woocommerce',
        'the-events-calendar'  => 'Easy_MCP_AI\\Cache\\Adapters\\Events_Calendar',
        'smart-slider-3'       => 'Easy_MCP_AI\\Cache\\Adapters\\Smart_Slider',
        'tablepress'           => 'Easy_MCP_AI\\Cache\\Adapters\\Tablepress',
        'popup-maker'          => 'Easy_MCP_AI\\Cache\\Adapters\\Popup_Maker',
        'smash-balloon'        => 'Easy_MCP_AI\\Cache\\Adapters\\Smash_Balloon',
        'facetwp'              => 'Easy_MCP_AI\\Cache\\Adapters\\Facetwp',
        
        'optimole'             => 'Easy_MCP_AI\\Cache\\Adapters\\Optimole',
        'elementor'            => 'Easy_MCP_AI\\Cache\\Adapters\\Elementor',
        'autoptimize'          => 'Easy_MCP_AI\\Cache\\Adapters\\Autoptimize',
        
        'litespeed-cache'      => 'Easy_MCP_AI\\Cache\\Adapters\\Litespeed_Cache',
        'wp-rocket'            => 'Easy_MCP_AI\\Cache\\Adapters\\Wp_Rocket',
        'wp-super-cache'       => 'Easy_MCP_AI\\Cache\\Adapters\\Wp_Super_Cache',
        'wp-fastest-cache'     => 'Easy_MCP_AI\\Cache\\Adapters\\Wp_Fastest_Cache',
        'wp-optimize'          => 'Easy_MCP_AI\\Cache\\Adapters\\Wp_Optimize',
        'w3-total-cache'       => 'Easy_MCP_AI\\Cache\\Adapters\\W3_Total_Cache',
        'jetpack-boost'        => 'Easy_MCP_AI\\Cache\\Adapters\\Jetpack_Boost',
        'breeze'               => 'Easy_MCP_AI\\Cache\\Adapters\\Breeze',
        'sg-cachepress'        => 'Easy_MCP_AI\\Cache\\Adapters\\Sg_Cachepress',
        'cache-enabler'        => 'Easy_MCP_AI\\Cache\\Adapters\\Cache_Enabler',
        'speedycache'          => 'Easy_MCP_AI\\Cache\\Adapters\\Speedycache',
        'hummingbird'          => 'Easy_MCP_AI\\Cache\\Adapters\\Hummingbird',
        'nitropack'            => 'Easy_MCP_AI\\Cache\\Adapters\\Nitropack',
        'clearfy'              => 'Easy_MCP_AI\\Cache\\Adapters\\Clearfy',
        'xspeed'               => 'Easy_MCP_AI\\Cache\\Adapters\\Xspeed',
        'spinupwp'             => 'Easy_MCP_AI\\Cache\\Adapters\\Spinupwp',
        
        'nginx-helper'         => 'Easy_MCP_AI\\Cache\\Adapters\\Nginx_Helper',
        'proxy-cache-purge'    => 'Easy_MCP_AI\\Cache\\Adapters\\Proxy_Cache_Purge',
        'kinsta'               => 'Easy_MCP_AI\\Cache\\Adapters\\Kinsta',
        'wp-engine'            => 'Easy_MCP_AI\\Cache\\Adapters\\Wp_Engine',
        'godaddy'              => 'Easy_MCP_AI\\Cache\\Adapters\\Godaddy',
        'pressable'            => 'Easy_MCP_AI\\Cache\\Adapters\\Pressable',
        'pressidium'           => 'Easy_MCP_AI\\Cache\\Adapters\\Pressidium',
        'flywp'                => 'Easy_MCP_AI\\Cache\\Adapters\\Flywp',
        'pantheon'             => 'Easy_MCP_AI\\Cache\\Adapters\\Pantheon',
        'wordpress-vip'        => 'Easy_MCP_AI\\Cache\\Adapters\\Wordpress_Vip',
        'bigscoots'            => 'Easy_MCP_AI\\Cache\\Adapters\\Bigscoots',
        
        'super-page-cache'     => 'Easy_MCP_AI\\Cache\\Adapters\\Super_Page_Cache',
        'cloudflare'           => 'Easy_MCP_AI\\Cache\\Adapters\\Cloudflare',
        
        'object-cache'         => 'Easy_MCP_AI\\Cache\\Adapters\\Object_Cache',
    );

    
    const FILTER = 'easy_mcp_ai_cache_adapter_enabled';

    
    private static $adapters = null;

    




    public static function all(): array {
        if ( null === self::$adapters ) {
            $loaded = \Easy_MCP_AI\Class_Map_Loader::load( self::ADAPTERS, Cache_Adapter::class );
            uasort(
                $loaded,
                static function ( Cache_Adapter $a, Cache_Adapter $b ): int {
                    return $a->tier() <=> $b->tier();
                }
            );
            self::$adapters = $loaded;
        }
        return self::$adapters;
    }

    




    public static function ids(): array {
        return array_keys( self::ADAPTERS );
    }

    







    public static function is_switched_on( Cache_Adapter $adapter ): bool {
        $default = $adapter->enabled_by_default();
        $value   = \apply_filters( self::FILTER, $default, $adapter->id() );
        return $default ? false !== $value : true === $value;
    }

    




    public static function noise_options(): array {
        return Cache_Noise::OPTIONS;
    }

    




    public static function reset(): void {
        self::$adapters = null;
    }
}
