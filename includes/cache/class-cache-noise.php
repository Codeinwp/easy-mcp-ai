<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










final class Cache_Noise {

    const OPTIONS = array(
        'rank_math_sitemap_cache_files',
        'wpseo_sitemap_cache_validator_global',
        'wpseo_sitemap_1_cache_validator',
        'wpseo_sitemap_post_cache_validator',
        'wpseo_sitemap_page_cache_validator',
        'tribe_last_save_post',
        'pum-has-cached-css',
        'pum-has-cached-js',
        'elementor-custom-breakpoints-files',
        '_elementor_assets_data',
        'litespeed.admin_display.messages',
        'litespeed.crawler._summary',
        'litespeed.purge.queue',
        'wpsc_feed_list',
        'WpFastestCacheHTML',
        'WpFastestCacheHTMLSIZE',
        'WpFastestCacheMOBILE',
        'WpFastestCacheMOBILESIZE',
        'breeze_minify_purged_at',
        'breeze_minified_hashes',
        'breeze_minify_unwritable_file',
        'breeze_preload_queue',
        'breeze_preload_queue_',
        'breeze_preload_last_warm',
        'breeze_preload_last_warm_',
        'speedycache_html_size',
        'wphb-notice-cache-cleaned-show',
        'xspeed_activity_log',
        'xspeed_stats',
        'xspeed_cloudflare_health',
        'siteground_optimizer_file_cache_secret',
        'wpaas_cache_cnt',
        'gd_system_last_cache_flush',
        'gd_system_polling_invalidation_id',
        'swcfpc_fc_ttl_registry',
        'swcfpc_preloader_lock',
    );
}
