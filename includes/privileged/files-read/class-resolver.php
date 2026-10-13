<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















final class Resolver {

    const MAX_PATH_LENGTH = 1024;

    
    const EXTENSIONS = array(
        'php', 'inc', 'css', 'scss', 'sass', 'less', 'js', 'mjs', 'cjs', 'jsx', 'ts', 'tsx',
        'json', 'html', 'htm', 'twig', 'txt', 'md', 'xml', 'svg', 'yml', 'yaml', 'po', 'pot',
    );

    
    const BLOCKED_EXTENSIONS = array(
        'sql', 'log', 'gz', 'zip', 'tar', 'tgz', 'wpress', 'daf', 'bak', 'old', 'orig', 'swp', 'env',
        'ini', 'conf', 'cnf', 'key', 'pem', 'p12', 'pfx', 'crt', 'db', 'sqlite', 'dat', 'crypt',
    );

    






    const DENIED_TOP_LEVEL = array(
        'uploads', 'updraft', 'ai1wm-backups', 'duplicator-backups', 'wpvividbackups', 'aiowps_backups',
        'backup-guard', 'wp_all_backup', 'w3tc-config', 'cache',
        
        'blogs.dir',
    );

    
    const DENIED_TOP_LEVEL_PREFIXES = array( 'backups-dup-', 'backup-migration-' );

    
    const NOISE_DIRS = array( 'vendor', 'node_modules' );

    const EXTENSIONS_FILTER = 'easy_mcp_ai_files_read_extensions';
    const MU_PLUGINS_FILTER = 'easy_mcp_ai_files_read_include_mu_plugins';

    
    private $content_root;

    
    private $linked_roots = array();

    
    private $denied_dirs = array();

    
    private $include_mu_plugins;

    
    private $extensions;

    






    public function __construct( string $content_dir, array $plugin_links = array(), array $denied_dirs = array(), bool $include_mu_plugins = false, ?array $extensions = null ) {
        $root = self::real_dir( $content_dir );
        if ( null === $root ) {
            
            
            throw new \RuntimeException( 'The wp-content directory cannot be resolved; no file can be read.' );
        }
        $this->content_root = $root;
        foreach ( $plugin_links as $link => $target ) {
            $display = self::relative_to( wp_normalize_path_fallback( (string) $link ), $this->content_root, $content_dir );
            $real    = self::real_dir( (string) $target );
            if ( null !== $display && null !== $real && 0 !== strpos( $real, $this->content_root ) ) {
                $this->linked_roots[ $real ] = rtrim( $display, '/' ) . '/';
            }
        }
        foreach ( $denied_dirs as $dir ) {
            $real = self::real_dir( (string) $dir );
            if ( null !== $real ) {
                $this->denied_dirs[] = strtolower( $real );
            }
        }
        $this->include_mu_plugins = $include_mu_plugins;
        $this->extensions         = null === $extensions ? self::filtered_extensions() : self::clean_extensions( $extensions );
    }

    
    public static function for_site(): self {
        global $wp_plugin_paths;
        $include_mu = function_exists( 'apply_filters' ) && true === \apply_filters( self::MU_PLUGINS_FILTER, false );
        $denied     = array();
        if ( function_exists( 'wp_upload_dir' ) ) {
            $uploads = \wp_upload_dir( null, false );
            $denied  = empty( $uploads['basedir'] ) ? array() : self::upload_dirs( (string) $uploads['basedir'], function_exists( 'is_multisite' ) && \is_multisite() );
        }
        if ( defined( 'EASY_MCP_AI_PLUGIN_DIR' ) ) {
            $denied[] = (string) EASY_MCP_AI_PLUGIN_DIR;
        }
        if ( ! $include_mu && defined( 'WPMU_PLUGIN_DIR' ) ) {
            $denied[] = (string) WPMU_PLUGIN_DIR;
        }
        return new self( (string) WP_CONTENT_DIR, is_array( $wp_plugin_paths ) ? $wp_plugin_paths : array(), $denied, $include_mu );
    }

    









    public static function upload_dirs( string $basedir, bool $multisite ): array {
        if ( ! $multisite ) {
            return array( $basedir );
        }
        $network = (string) preg_replace( '#/sites/\d+/?\z#', '', $basedir );
        return array_values( array_unique( array( $basedir, $network ) ) );
    }

    






    public function resolve( string $path, bool $want_dir ): array {
        if ( null !== self::input_problem( $path, $want_dir ) ) {
            throw Refusal::for_path( $path );
        }
        $real = self::real( $this->content_root . $path );
        if ( null === $real ) {
            throw Refusal::for_path( $path );
        }
        $rel = $this->display_path( $real );
        if ( null === $rel || $this->denied( $rel, $real ) ) {
            throw Refusal::for_path( $path );
        }
        if ( $want_dir ? ! is_dir( $real ) : ! $this->readable_file( $real ) ) {
            throw Refusal::for_path( $path );
        }
        return array( 'abs' => $real, 'rel' => $rel );
    }

    










    public function walk( array $dir, int $max_depth, int $max_entries, float $deadline, callable $visit, array &$skipped ): bool {
        $walk = array(
            'max_depth'   => $max_depth,
            'max_entries' => $max_entries,
            'deadline'    => $deadline,
            'noise_ok'    => self::names_noise_dir( $dir['rel'] ),
            'entries'     => 0,
        );
        return $this->walk_dir( $dir['abs'], $dir['rel'], 1, $walk, $visit, $skipped );
    }

    







    private function walk_dir( string $abs, string $rel, int $depth, array &$walk, callable $visit, array &$skipped ): bool {
        $names = @scandir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir warnings name server paths.
        if ( false === $names ) {
            return true;
        }
        foreach ( array_diff( $names, array( '.', '..' ) ) as $name ) {
            if ( $walk['entries'] >= $walk['max_entries'] || microtime( true ) >= $walk['deadline'] ) {
                return false;
            }
            $child     = $abs . '/' . $name;
            $child_rel = '' === $rel ? $name : $rel . '/' . $name;
            $reason    = $this->skip_reason( $child, $child_rel, $walk['noise_ok'] );
            if ( null !== $reason ) {
                self::count( $skipped, $reason );
                continue;
            }
            ++$walk['entries'];
            $entry = $this->entry( $child, $child_rel );
            if ( false === $visit( $entry, $child ) ) {
                return false;
            }
            if ( self::descends( $entry, $walk, $depth ) && ! $this->walk_dir( $child, $child_rel, $depth + 1, $walk, $visit, $skipped ) ) {
                return false;
            }
        }
        return true;
    }

    
    private function skip_reason( string $child, string $child_rel, bool $noise_ok ): ?string {
        if ( is_link( $child ) ) {
            return 'symlink';
        }
        $is_dir = is_dir( $child );
        if ( $this->denied( $child_rel . ( $is_dir ? '/' : '' ), $child ) ) {
            return 'denied';
        }
        if ( $is_dir && ! $noise_ok && in_array( strtolower( basename( $child_rel ) ), self::NOISE_DIRS, true ) ) {
            return 'vendor';
        }
        return null;
    }

    
    private function entry( string $child, string $child_rel ): array {
        $is_dir = is_dir( $child );
        return array(
            'path'     => $child_rel,
            'type'     => $is_dir ? 'dir' : 'file',
            'size'     => $is_dir ? 0 : (int) @filesize( $child ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            'modified' => gmdate( 'Y-m-d\TH:i:s\Z', (int) @filemtime( $child ) ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            'readable' => ! $is_dir && $this->readable_file( $child ),
        );
    }

    





    private static function descends( array $entry, array $walk, int $depth ): bool {
        return 'dir' === $entry['type'] && $depth < $walk['max_depth'];
    }

    
    public function readable_file( string $real ): bool {
        if ( ! is_file( $real ) ) {
            return false;
        }
        $extension = strtolower( (string) pathinfo( $real, PATHINFO_EXTENSION ) );
        if ( '' === $extension || ! in_array( $extension, $this->extensions, true ) ) {
            return false;
        }
        $stat = @stat( $real ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        
        return is_array( $stat ) && 1 === (int) $stat['nlink'];
    }

    




    public function denied( string $rel, string $real ): bool {
        $segments = array_values( array_filter( explode( '/', strtolower( $rel ) ), 'strlen' ) );
        foreach ( $segments as $i => $segment ) {
            if ( '.' === $segment[0] ) {
                return true;
            }
            if ( 0 === $i && self::denied_top_level( $segment, $this->include_mu_plugins ) ) {
                return true;
            }
        }
        $last = end( $segments );
        if ( false !== $last && 0 === strpos( $last, 'wp-config' ) ) {
            return true;
        }
        
        
        $real = strtolower( rtrim( $real, '/' ) . '/' );
        foreach ( $this->denied_dirs as $dir ) {
            if ( 0 === strpos( $real, $dir ) ) {
                return true;
            }
        }
        return false;
    }

    
    public function extensions(): array {
        return $this->extensions;
    }

    






    public static function input_problem( string $path, bool $want_dir ): ?string {
        if ( '' === $path ) {
            return $want_dir ? null : 'empty';
        }
        if ( strlen( $path ) > self::MAX_PATH_LENGTH ) {
            return 'length';
        }
        if ( preg_match( '/[\x00-\x1f\x7f]/', $path ) ) {
            return 'control';
        }
        
        if ( false !== strpbrk( $path, ':\\' ) ) {
            return 'scheme';
        }
        
        foreach ( explode( '/', rtrim( $path, '/' ) ) as $segment ) {
            if ( self::segment_refused( $segment ) ) {
                return 'segment';
            }
        }
        return null;
    }

    



    private static function segment_refused( string $segment ): bool {
        if ( '' === $segment || '.' === $segment[0] ) {
            return true;
        }
        return '.' === substr( $segment, -1 ) || ' ' === substr( $segment, -1 ) || 1 === preg_match( '/~\d/', $segment );
    }

    private static function denied_top_level( string $segment, bool $include_mu_plugins ): bool {
        if ( in_array( $segment, self::DENIED_TOP_LEVEL, true ) || ( ! $include_mu_plugins && 'mu-plugins' === $segment ) ) {
            return true;
        }
        foreach ( self::DENIED_TOP_LEVEL_PREFIXES as $prefix ) {
            if ( 0 === strpos( $segment, $prefix ) ) {
                return true;
            }
        }
        return false;
    }

    




    private function display_path( string $real ): ?string {
        $probe = rtrim( $real, '/' ) . '/';
        if ( 0 === strpos( $probe, $this->content_root ) ) {
            return rtrim( substr( $real, strlen( $this->content_root ) ), '/' );
        }
        foreach ( $this->linked_roots as $target => $display ) {
            if ( 0 === strpos( $probe, $target ) ) {
                return rtrim( $display . substr( $real, strlen( $target ) ), '/' );
            }
        }
        return null;
    }

    private static function names_noise_dir( string $rel ): bool {
        foreach ( explode( '/', strtolower( $rel ) ) as $segment ) {
            if ( in_array( $segment, self::NOISE_DIRS, true ) ) {
                return true;
            }
        }
        return false;
    }

    
    public static function real( string $path ): ?string {
        try {
            $real = @realpath( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        } catch ( \Throwable $e ) {
            return null;
        }
        return false === $real ? null : str_replace( '\\', '/', $real );
    }

    
    private static function real_dir( string $dir ): ?string {
        $real = self::real( $dir );
        return null !== $real && is_dir( $real ) ? rtrim( $real, '/' ) . '/' : null;
    }

    
    private static function relative_to( string $path, string $content_root, string $content_dir ): ?string {
        foreach ( array( $content_root, rtrim( wp_normalize_path_fallback( $content_dir ), '/' ) . '/' ) as $base ) {
            if ( 0 === strpos( $path, $base ) ) {
                return substr( $path, strlen( $base ) );
            }
        }
        return null;
    }

    
    private static function filtered_extensions(): array {
        $list = function_exists( 'apply_filters' ) ? \apply_filters( self::EXTENSIONS_FILTER, self::EXTENSIONS ) : self::EXTENSIONS;
        return self::clean_extensions( is_array( $list ) ? $list : self::EXTENSIONS );
    }

    





    private static function clean_extensions( array $list ): array {
        $out = array();
        foreach ( $list as $extension ) {
            if ( ! is_string( $extension ) ) {
                continue;
            }
            $extension = strtolower( ltrim( trim( $extension ), '.' ) );
            if ( '' !== $extension && preg_match( '/^[a-z0-9]+\z/', $extension ) && ! in_array( $extension, self::BLOCKED_EXTENSIONS, true ) ) {
                $out[] = $extension;
            }
        }
        return array_values( array_unique( $out ) );
    }

    
    private static function count( array &$skipped, string $reason ): void {
        $skipped[ $reason ] = ( $skipped[ $reason ] ?? 0 ) + 1;
    }
}





function wp_normalize_path_fallback( string $path ): string {
    if ( function_exists( 'wp_normalize_path' ) ) {
        return \wp_normalize_path( $path );
    }
    return (string) preg_replace( '|(?<=.)/+|', '/', str_replace( '\\', '/', $path ) );
}
