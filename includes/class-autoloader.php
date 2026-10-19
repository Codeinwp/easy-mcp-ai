<?php






namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}























final class Autoloader {

    const PREFIX = 'Easy_MCP_AI\\';

    
    const IDENTIFIER_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_';

    
    const FILE_PREFIXES = array( 'class-', 'trait-', 'interface-' );

    




    const EXCEPTIONS = array(
        'easy_mcp_ai\\tools\\lifecycle_guard'                => 'tools/plugins/trait-lifecycle-guard.php',
        'easy_mcp_ai\\tools\\package_guard'                  => 'tools/plugins/trait-package-guard.php',
        'easy_mcp_ai\\tools\\seo\\aioseo_get_breadcrumb'     => 'tools/seo/aioseo/class-aioseo-get-breadcrumb.php',
        'easy_mcp_ai\\tools\\seo\\aioseo_get_post_seo'       => 'tools/seo/aioseo/class-aioseo-get-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\aioseo_update_post_seo'    => 'tools/seo/aioseo/class-aioseo-update-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\rankmath_get_head'         => 'tools/seo/rank-math/class-rankmath-get-head.php',
        'easy_mcp_ai\\tools\\seo\\rankmath_get_post_seo'     => 'tools/seo/rank-math/class-rankmath-get-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\rankmath_update_post_seo'  => 'tools/seo/rank-math/class-rankmath-update-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\seopress_get_content_analysis' => 'tools/seo/seopress/class-seopress-get-content-analysis.php',
        'easy_mcp_ai\\tools\\seo\\seopress_get_post_seo'     => 'tools/seo/seopress/class-seopress-get-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\seopress_get_term_seo'     => 'tools/seo/seopress/class-seopress-get-term-seo.php',
        'easy_mcp_ai\\tools\\seo\\seopress_update_keywords'  => 'tools/seo/seopress/class-seopress-update-keywords.php',
        'easy_mcp_ai\\tools\\seo\\seopress_update_robots'    => 'tools/seo/seopress/class-seopress-update-robots.php',
        'easy_mcp_ai\\tools\\seo\\seopress_update_social'    => 'tools/seo/seopress/class-seopress-update-social.php',
        'easy_mcp_ai\\tools\\seo\\seopress_update_title_desc' => 'tools/seo/seopress/class-seopress-update-title-desc.php',
        'easy_mcp_ai\\tools\\seo\\slimseo_get_post_seo'      => 'tools/seo/slim-seo/class-slimseo-get-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\slimseo_update_post_seo'   => 'tools/seo/slim-seo/class-slimseo-update-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\tsf_get_post_seo'          => 'tools/seo/the-seo-framework/class-tsf-get-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\tsf_update_post_seo'       => 'tools/seo/the-seo-framework/class-tsf-update-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\yoast_get_head'            => 'tools/seo/yoast/class-yoast-get-head.php',
        'easy_mcp_ai\\tools\\seo\\yoast_get_post_seo'        => 'tools/seo/yoast/class-yoast-get-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\yoast_get_term_seo'        => 'tools/seo/yoast/class-yoast-get-term-seo.php',
        'easy_mcp_ai\\tools\\seo\\yoast_update_post_seo'     => 'tools/seo/yoast/class-yoast-update-post-seo.php',
        'easy_mcp_ai\\tools\\seo\\yoast_update_term_seo'     => 'tools/seo/yoast/class-yoast-update-term-seo.php',
    );

    private static bool $registered = false;

    
    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        spl_autoload_register( array( __CLASS__, 'load' ) );
    }

    




    public static function load( string $class ): void {
        $file = self::file_for( $class );
        if ( null !== $file ) {
            require_once $file;
        }
    }

    





    public static function file_for( string $class ): ?string {
        foreach ( self::candidates( $class ) as $file ) {
            if ( is_readable( $file ) ) {
                return $file;
            }
        }
        return null;
    }

    






    private static function candidates( string $class ): array {
        $lower = strtolower( ltrim( $class, '\\' ) );
        if ( 0 !== strpos( $lower, strtolower( self::PREFIX ) ) ) {
            return array();
        }
        $includes = __DIR__ . '/';
        if ( isset( self::EXCEPTIONS[ $lower ] ) ) {
            return array( $includes . self::EXCEPTIONS[ $lower ] );
        }
        $parts = explode( '\\', substr( $lower, strlen( self::PREFIX ) ) );
        foreach ( $parts as $part ) {
            
            
            
            
            if ( '' === $part || strspn( $part, self::IDENTIFIER_CHARS ) !== strlen( $part ) ) {
                return array();
            }
        }
        $short = str_replace( '_', '-', (string) array_pop( $parts ) );
        $dir   = $includes;
        foreach ( $parts as $part ) {
            $dir .= str_replace( '_', '-', $part ) . '/';
        }
        $paths = array();
        foreach ( self::FILE_PREFIXES as $prefix ) {
            $paths[] = $dir . $prefix . $short . '.php';
        }
        return $paths;
    }
}
