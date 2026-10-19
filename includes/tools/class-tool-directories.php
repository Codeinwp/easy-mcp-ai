<?php






namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\Autoloader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















final class Tool_Directories {

    
    const ALWAYS_ON = array(
        'posts', 'pages', 'media', 'taxonomy', 'comments',
        'users', 'site', 'menus', 'plugins', 'themes',
        'revisions', 'meta', 'search', 'blocks', 'cpt', 'templates', 'styles',
        'appearance', 'widgets', 'history', 'audit', 'site-health', 'approvals',
        'feedback',
        
        'cache',
        
        'skills',
        
        'rest',
        
        'privileged',
        
        
        'cli',
    );

    
    const PLUGIN_GROUPS = array(
        'woocommerce'         => 'woocommerce',
        'acf'                 => 'acf',
        'the-events-calendar' => 'events-calendar',
        'redirection'         => 'redirection',
        'buddypress'          => 'buddypress',
        'yoast-seo'           => 'seo/yoast',
        'rank-math'           => 'seo/rank-math',
        'aioseo'              => 'seo/aioseo',
        'seopress'            => 'seo/seopress',
        'slim-seo'            => 'seo/slim-seo',
        'the-seo-framework'   => 'seo/the-seo-framework',
    );

    
    const EXTERNAL_DATA = array( 'ahrefs', 'gsc', 'ga', 'dfs', 'semrush', 'seranking' );

    




    public static function enabled(): array {
        return array_values( array_unique( array_merge( self::ALWAYS_ON, self::external_data(), self::plugin_groups() ) ) );
    }

    





    public static function all(): array {
        return array_values( array_unique( array_merge( self::ALWAYS_ON, self::EXTERNAL_DATA, array_values( self::PLUGIN_GROUPS ) ) ) );
    }

    





    public static function external_data(): array {
        $tool_dirs = array();
        
        
        
        
        if ( \get_option( 'easy_mcp_ai_ahrefs_enabled', false ) ) {
            $tool_dirs[] = 'ahrefs';
        }
        
        if ( ! empty( \get_option( \Easy_MCP_AI\GSC\GSC_Client::OPTION_JSON, '' ) ) ) {
            $tool_dirs[] = 'gsc';
        }
        
        if ( ! empty( \get_option( \Easy_MCP_AI\GA\GA_Client::OPTION_JSON, '' ) ) ) {
            $tool_dirs[] = 'ga';
        }
        
        $dfs_login    = \get_option( \Easy_MCP_AI\DFS\DataforSEO_Client::OPTION_LOGIN, '' );
        $dfs_api_pwd  = \get_option( \Easy_MCP_AI\DFS\DataforSEO_Client::OPTION_API_PASSWORD, '' );
        if ( ! empty( $dfs_login ) && ! empty( $dfs_api_pwd ) ) {
            $tool_dirs[] = 'dfs';
        }
        
        if ( ! empty( \get_option( \Easy_MCP_AI\Semrush\Semrush_Client::OPTION_API_KEY, '' ) ) ) {
            $tool_dirs[] = 'semrush';
        }
        
        if ( ! empty( \get_option( \Easy_MCP_AI\SeRanking\SeRanking_Client::OPTION_API_KEY, '' ) ) ) {
            $tool_dirs[] = 'seranking';
        }
        return $tool_dirs;
    }

    




    public static function plugin_groups(): array {
        $dirs_on = array();
        foreach ( (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() ) as $group_slug ) {
            if ( isset( self::PLUGIN_GROUPS[ $group_slug ] ) ) {
                $dirs_on[ self::PLUGIN_GROUPS[ $group_slug ] ] = true;
            }
        }
        return array_keys( $dirs_on );
    }

    





    public static function of( string $class ): ?string {
        $file = Autoloader::file_for( $class );
        $root = __DIR__ . '/';
        if ( null === $file || 0 !== strpos( $file, $root ) ) {
            return null;
        }
        return dirname( substr( $file, strlen( $root ) ) );
    }
}
