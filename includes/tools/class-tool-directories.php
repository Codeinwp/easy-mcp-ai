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
        'mail',
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
        'site-kit'            => 'site-kit',
        'contact-form-7'      => 'contact-form-7',
        'buddypress'          => 'buddypress',
        'yoast-seo'           => 'seo/yoast',
        'rank-math'           => 'seo/rank-math',
        'aioseo'              => 'seo/aioseo',
        'seopress'            => 'seo/seopress',
        'slim-seo'            => 'seo/slim-seo',
        'the-seo-framework'   => 'seo/the-seo-framework',
    );

    const EXTERNAL_DATA = array( 'ahrefs', 'gsc', 'ga', 'dfs', 'semrush', 'seranking' );

    const BUILDERS = 'builders';

    public static function enabled(): array {
        return array_values( array_unique( array_merge( self::ALWAYS_ON, self::external_data(), self::plugin_groups(), self::builders() ) ) );
    }

    public static function all(): array {
        return array_values( array_unique( array_merge( self::ALWAYS_ON, self::EXTERNAL_DATA, array_values( self::PLUGIN_GROUPS ), array( self::BUILDERS ) ) ) );
    }

    public static function external_data(): array {
        $tool_dirs = array();
        if ( \get_option( 'easy_mcp_ai_ahrefs_enabled', false ) ) {
            $tool_dirs[] = 'ahrefs';
        }
        if ( \Easy_MCP_AI\Provider_Credentials::is_configured( 'gsc' ) ) {
            $tool_dirs[] = 'gsc';
        }
        if ( \Easy_MCP_AI\Provider_Credentials::is_configured( 'ga' ) ) {
            $tool_dirs[] = 'ga';
        }
        if ( \Easy_MCP_AI\Provider_Credentials::is_configured( 'dfs' ) ) {
            $tool_dirs[] = 'dfs';
        }
        if ( \Easy_MCP_AI\Provider_Credentials::is_configured( 'semrush' ) ) {
            $tool_dirs[] = 'semrush';
        }
        if ( \Easy_MCP_AI\Provider_Credentials::is_configured( 'seranking' ) ) {
            $tool_dirs[] = 'seranking';
        }
        return $tool_dirs;
    }

    public static function builders(): array {
        return \Easy_MCP_AI\Builders\Builder_Registry::any_present() ? array( self::BUILDERS ) : array();
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
        return null === $file ? null : self::relative_dir( $file, __DIR__ );
    }

    public static function relative_dir( string $file, string $root ): ?string {
        $file = \wp_normalize_path( $file );
        $root = rtrim( \wp_normalize_path( $root ), '/' ) . '/';
        if ( 0 !== strpos( $file, $root ) ) {
            return null;
        }
        return dirname( substr( $file, strlen( $root ) ) );
    }
}
