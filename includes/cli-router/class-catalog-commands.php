<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






























final class Catalog_Commands extends Command_Handler {

    
    const PAGE_BYTES = 65536;

    
    const ANSWERED = array(
        'help'            => 'help',
        'cli version'     => 'cli_version',
        'cli info'        => 'cli_info',
        'cli cmd-dump'    => 'cli_cmd_dump',
        'cli has-command' => 'cli_has_command',
    );

    



    const EXCLUDED_FAMILIES = array(
        'cli'      => 'Developer tooling with no meaning inside a web request.',
        'config'   => 'Edits or prints wp-config.php; config get returns the database password and salts.',
        'i18n'     => 'Developer tooling with no meaning inside a web request.',
        'package'  => 'Developer tooling with no meaning inside a web request.',
        'scaffold' => 'Developer tooling with no meaning inside a web request.',
    );

    
    const EXCLUDED_COMMANDS = array(
        'comment generate'           => 'Dummy-content generator.',
        'core download'              => 'Installs WordPress or converts the site.',
        'core install'               => 'Installs WordPress or converts the site.',
        'core multisite-convert'     => 'Installs WordPress or converts the site.',
        'core multisite-install'     => 'Installs WordPress or converts the site.',
        'db clean'                   => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db cli'                     => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db create'                  => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db drop'                    => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db export'                  => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db import'                  => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db query'                   => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db reset'                   => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'db search'                  => 'Raw SQL, whole-database dumps and restores, or destroying tables.',
        'eval'                       => 'Runs arbitrary PHP.',
        'eval-file'                  => 'Runs arbitrary PHP.',
        'export'                     => 'Reads and writes files on the server.',
        'import'                     => 'Reads and writes files on the server.',
        'maintenance-mode activate'  => 'Answers 503 to every request for up to 10 minutes, this MCP endpoint included, so its undo cannot reach the site.',
        'post edit'                  => 'Needs an interactive editor.',
        'post generate'              => 'Dummy-content generator.',
        'server'                     => 'Developer tooling with no meaning inside a web request.',
        'shell'                      => 'Runs arbitrary PHP.',
        'site generate'              => 'Dummy-content generator.',
        'term generate'              => 'Dummy-content generator.',
        'user generate'              => 'Dummy-content generator.',
    );

    
    public function help( Invocation $inv, Context $ctx ): void {
        $text = Help::render( $inv->args );
        if ( '' === $text ) {
            $found = Catalog::find( $inv->args );
            if ( ! $found['path'] ) {
                $ctx->note( 'Commands added by plugins are not available through wp_cli_run.' );
            }
            
            
            $ctx->out->error( Catalog::not_found_message( $found ) );
        }
        $ctx->out->out( $text );
        $note = self::refused_note( $inv->args );
        if ( null !== $note ) {
            $ctx->note( $note );
        }
    }

    
    public function cli_version( Invocation $inv, Context $ctx ): void {
        $ctx->out->line( 'WP-CLI ' . Catalog::version() );
    }

    









    public function cli_info( Invocation $inv, Context $ctx ): void {
        $system_os = function_exists( 'php_uname' )
            ? sprintf( '%s %s %s %s', php_uname( 's' ), php_uname( 'r' ), php_uname( 'v' ), php_uname( 'm' ) )
            : '';
        $version   = Catalog::version();

        if ( 'json' === $inv->get( 'format' ) ) {
            $info = array(
                'system_os'                => $system_os,
                'shell'                    => false,
                'php_binary_path'          => '',
                'php_version'              => PHP_VERSION,
                'php_ini_used'             => false,
                'mysql_binary_path'        => '',
                'mysql_version'            => '',
                'sql_modes'                => array(),
                'wp_cli_dir_path'          => '',
                'wp_cli_vendor_path'       => '',
                'wp_cli_phar_path'         => '',
                'wp_cli_packages_dir_path' => null,
                'wp_cli_cache_dir_path'    => '',
                'global_config_path'       => false,
                'project_config_path'      => false,
                'wp_cli_version'           => $version,
            );
            $ctx->out->line( (string) json_encode( $info ) );
        } else {
            $ctx->out->line( "OS:\t" . $system_os );
            $ctx->out->line( "Shell:\t" );
            $ctx->out->line( "PHP binary:\t" );
            $ctx->out->line( "PHP version:\t" . PHP_VERSION );
            $ctx->out->line( "php.ini used:\t" );
            $ctx->out->line( "MySQL binary:\t" );
            $ctx->out->line( "MySQL version:\t" );
            $ctx->out->line( "SQL modes:\t" );
            $ctx->out->line( "WP-CLI root dir:\t" );
            $ctx->out->line( "WP-CLI vendor dir:\t" );
            $ctx->out->line( "WP_CLI phar path:\t" );
            $ctx->out->line( "WP-CLI packages dir:\t" );
            $ctx->out->line( "WP-CLI cache dir:\t" );
            $ctx->out->line( "WP-CLI global config:\t" );
            $ctx->out->line( "WP-CLI project config:\t" );
            $ctx->out->line( "WP-CLI version:\t" . $version );
        }
        $ctx->note( 'wp_cli_run runs inside a web request: there is no shell, PHP or MySQL binary, WP-CLI install, cache, package or config file, so those values are empty, and no server path is shown.' );
    }

    





    public function cli_cmd_dump( Invocation $inv, Context $ctx ): void {
        $ctx->out->out( (string) json_encode( self::dump_node( '', array( __CLASS__, 'runs' ) ) ) );
    }

    







    public function cli_has_command( Invocation $inv, Context $ctx ): void {
        $words = explode( ' ', implode( ' ', $inv->args ) );
        $found = Catalog::find( $words );
        $runs  = false;
        if ( null !== $found['leaf'] ) {
            $runs = self::runs( (string) $found['leaf']['command'] );
        } elseif ( null !== $found['composite'] && ! $found['rest'] ) {
            $runs = self::composite_runs( (string) $found['composite']['command'] );
        }
        $ctx->out->halt( $runs ? 0 : 1 );
    }

    









    public static function page( string $full, int $offset ): array {
        if ( $offset < 0 ) {
            throw new \InvalidArgumentException( 'output_offset must be 0 or more.' );
        }
        $total = strlen( $full );
        while ( $offset < $total && self::is_continuation_byte( $full[ $offset ] ) ) {
            ++$offset;
        }
        if ( $offset >= $total ) {
            return array(
                'chunk'       => '',
                'next_offset' => null,
            );
        }
        if ( $total - $offset <= self::PAGE_BYTES ) {
            return array(
                'chunk'       => substr( $full, $offset ),
                'next_offset' => null,
            );
        }
        $length = self::PAGE_BYTES;
        while ( $length > 0 && self::is_continuation_byte( $full[ $offset + $length ] ) ) {
            --$length;
        }
        if ( 0 === $length ) {
            $length = self::PAGE_BYTES; 
        }
        return array(
            'chunk'       => substr( $full, $offset, $length ),
            'next_offset' => $offset + $length,
        );
    }

    


    public static function runs( string $command ): bool {
        $route = Routes::get( $command );
        if ( null === $route ) {
            return null === self::excluded_reason( $command );
        }
        $kind = isset( $route['route'] ) ? $route['route'] : null;
        return in_array( $kind, array( 'catalog', 'delegate', 'own' ), true );
    }

    




    public static function refusal( string $command ): ?string {
        if ( self::runs( $command ) ) {
            return null;
        }
        $route = Routes::get( $command );
        return null === $route ? self::excluded_reason( $command ) : self::route_refusal( $route );
    }

    






    private static function route_refusal( array $route ): string {
        $kind = isset( $route['route'] ) ? $route['route'] : null;
        if ( 'refuse' === $kind && isset( $route['reason'] ) && is_string( $route['reason'] ) && '' !== $route['reason'] ) {
            return $route['reason'];
        }
        if ( 'unimplemented' === $kind ) {
            $instead = isset( $route['instead'] ) && is_string( $route['instead'] ) && '' !== $route['instead'] ? ' Use the ' . $route['instead'] . ' tool instead.' : '';
            return 'Not available through wp_cli_run yet.' . $instead;
        }
        return 'Not available through wp_cli_run.';
    }

    
    private static function excluded_reason( string $command ): ?string {
        if ( isset( self::ANSWERED[ $command ] ) ) {
            return null;
        }
        if ( isset( self::EXCLUDED_COMMANDS[ $command ] ) ) {
            return self::EXCLUDED_COMMANDS[ $command ];
        }
        $top = (string) strtok( $command, ' ' );
        return isset( self::EXCLUDED_FAMILIES[ $top ] ) ? self::EXCLUDED_FAMILIES[ $top ] : null;
    }

    
    private static function composite_runs( string $composite ): bool {
        $prefix = $composite . ' ';
        foreach ( Catalog::commands() as $command ) {
            if ( 0 === strpos( $command, $prefix ) && self::runs( $command ) ) {
                return true;
            }
        }
        return false;
    }

    





    private static function refused_note( array $words ): ?string {
        if ( ! $words ) {
            $parent   = '';
            $children = Catalog::root()['subcommands'];
        } else {
            $found = Catalog::find( $words );
            if ( null !== $found['leaf'] ) {
                $command = (string) $found['leaf']['command'];
                $reason  = self::refusal( $command );
                return null === $reason ? null : "wp_cli_run refuses 'wp {$command}': {$reason}";
            }
            if ( null === $found['composite'] ) {
                return null;
            }
            $parent   = (string) $found['composite']['command'];
            $children = $found['composite']['subcommands'];
        }

        $refused = self::refused_children( $parent, $children );
        if ( ! $refused ) {
            return null;
        }
        $scope = '' === $parent ? 'commands' : "subcommands of 'wp {$parent}'";
        return "wp_cli_run refuses these {$scope} listed above: " . implode( ', ', $refused ) . '. Run help on one for the reason.';
    }

    






    private static function refused_children( string $parent, array $children ): array {
        $composites = Catalog::composites();
        $refused    = array();
        foreach ( $children as $child ) {
            $full = '' === $parent ? (string) $child : $parent . ' ' . $child;
            if ( isset( $composites[ $full ] ) ? ! self::composite_runs( $full ) : ! self::runs( $full ) ) {
                $refused[] = (string) $child;
            }
        }
        return $refused;
    }

    







    private static function dump_node( string $path, callable $keep ): ?array {
        $composites = Catalog::composites();
        if ( '' !== $path && ! isset( $composites[ $path ] ) ) {
            return self::dump_leaf( $path, $keep );
        }
        list( $dump, $children ) = '' === $path ? self::dump_root() : self::dump_composite( $path, $composites[ $path ] );

        foreach ( $children as $child ) {
            $node = self::dump_node( '' === $path ? (string) $child : $path . ' ' . $child, $keep );
            if ( null !== $node ) {
                $dump['subcommands'][] = $node;
            }
        }
        if ( '' !== $path && empty( $dump['subcommands'] ) ) {
            return null;
        }
        return $dump;
    }

    




    private static function dump_root(): array {
        $root = Catalog::root();
        return array(
            array(
                'name'        => 'wp',
                'description' => $root['shortdesc'],
                'longdesc'    => $root['longdesc'],
                'hook'        => null,
            ),
            $root['subcommands'],
        );
    }

    





    private static function dump_composite( string $path, array $composite ): array {
        return array(
            array(
                'name'        => self::last_word( $path ),
                'description' => $composite['shortdesc'],
                'longdesc'    => $composite['longdesc'] . $composite['global_prefix'] . Catalog::global_help(),
                'hook'        => isset( $composite['hook'] ) ? $composite['hook'] : null,
            ),
            $composite['subcommands'],
        );
    }

    






    private static function dump_leaf( string $path, callable $keep ): ?array {
        $leaf = Catalog::leaf( $path );
        if ( null === $leaf || ! $keep( $path ) ) {
            return null;
        }
        return array(
            'name'        => $leaf['name'],
            'description' => $leaf['shortdesc'],
            'longdesc'    => $leaf['longdesc'] . Catalog::global_help(),
            'hook'        => isset( $leaf['hook'] ) ? $leaf['hook'] : null,
            'synopsis'    => (string) $leaf['synopsis'],
        );
    }

    
    private static function last_word( string $path ): string {
        $space = strrpos( $path, ' ' );
        return false === $space ? $path : substr( $path, $space + 1 );
    }

    
    private static function is_continuation_byte( string $byte ): bool {
        return 0x80 === ( ord( $byte ) & 0xC0 );
    }
}
