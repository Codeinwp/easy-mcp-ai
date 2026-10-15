<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










































final class Catalog {

    
    private static $index = null;

    
    private static $families = array();

    
    public static function version(): string {
        return (string) self::index()['version'];
    }

    






















    public static function find( array $positional ): array {
        $rest  = array_values( $positional );
        $found = array(
            'path'      => array(),
            'leaf'      => null,
            'composite' => null,
            'rest'      => $rest,
        );
        if ( ! $rest ) {
            return $found;
        }

        $index = self::index();
        $first = (string) $rest[0];
        if ( ! in_array( $first, $index['root']['subcommands'], true ) ) {
            return $found;
        }
        array_shift( $rest );
        $path = array( $first );

        while ( true ) {
            $command = implode( ' ', $path );
            $leaf    = self::leaf( $command );
            if ( null !== $leaf ) {
                $found['leaf'] = $leaf;
                break;
            }
            if ( ! isset( $index['composites'][ $command ] ) ) {
                break;
            }
            $composite          = $index['composites'][ $command ];
            $found['composite'] = $composite;
            if ( ! $rest ) {
                break;
            }
            $word = (string) $rest[0];
            if ( in_array( $word, $composite['subcommands'], true ) ) {
                $name = $word;
            } elseif ( isset( $index['aliases'][ $command . ' ' . $word ] ) ) {
                $canonical = (string) $index['aliases'][ $command . ' ' . $word ];
                $name      = (string) substr( $canonical, strlen( $command ) + 1 );
            } else {
                break;
            }
            array_shift( $rest );
            $path[]             = $name;
            $found['composite'] = null;
        }

        $found['path'] = $path;
        $found['rest'] = $rest;
        return $found;
    }

    









    public static function not_found_message( array $found ): string {
        $word = isset( $found['rest'][0] ) ? (string) $found['rest'][0] : '';
        return $found['path']
            ? self::subcommand_not_found( $word, implode( ' ', $found['path'] ) )
            : self::command_not_found( $word );
    }

    



    private static function subcommand_not_found( string $word, string $parent_name ): string {
        $suggestion = self::suggestion( $word, self::enumerate( $parent_name ) );
        if ( 'network' === $parent_name && 'option' === $word ) {
            $suggestion = 'meta';
        }
        return sprintf(
            "'%s' is not a registered subcommand of '%s'. See 'wp help %s' for available subcommands.%s",
            $word,
            $parent_name,
            $parent_name,
            ! empty( $suggestion ) ? PHP_EOL . "Did you mean '{$suggestion}'?" : ''
        );
    }

    




    private static function command_not_found( string $word ): string {
        $suggestion = self::suggestion( $word, self::enumerate( '' ) );
        if ( function_exists( '\taxonomy_exists' ) ) {
            if ( \taxonomy_exists( $word ) ) {
                $suggestion = 'wp term <command>';
            } elseif ( function_exists( '\post_type_exists' ) && \post_type_exists( $word ) ) {
                $suggestion = "wp post --post_type={$word} <command>";
            }
        }
        return sprintf(
            "'%s' is not a registered wp command. See 'wp help' for available commands.%s",
            $word,
            ! empty( $suggestion ) ? PHP_EOL . "Did you mean '{$suggestion}'?" : ''
        );
    }

    




    public static function leaf( string $command ): ?array {
        $top    = (string) strtok( $command, ' ' );
        $family = self::family( $top );
        return isset( $family[ $command ] ) ? $family[ $command ] : null;
    }

    




    public static function commands(): array {
        $commands = array();
        foreach ( array_keys( self::index()['families'] ) as $top ) {
            foreach ( array_keys( self::family( (string) $top ) ) as $command ) {
                $commands[] = (string) $command;
            }
        }
        sort( $commands, SORT_STRING );
        return $commands;
    }

    











    public static function global_parameters(): array {
        return self::index()['globals'];
    }

    










    public static function composites(): array {
        return self::index()['composites'];
    }

    




    public static function aliases(): array {
        return self::index()['aliases'];
    }

    






    public static function root(): array {
        return self::index()['root'];
    }

    
    public static function global_help(): string {
        return (string) self::index()['global_help'];
    }

    







    public static function global_synopsis_names(): array {
        return self::index()['global_synopsis_names'];
    }

    













    public static function suggestion( string $word, array $candidates ): string {
        $suggestion_map = array(
            'add'        => 'create',
            'check'      => 'check-update',
            'capability' => 'cap',
            'clear'      => 'flush',
            'decrement'  => 'decr',
            'del'        => 'delete',
            'directory'  => 'dir',
            'exec'       => 'eval',
            'exec-file'  => 'eval-file',
            'increment'  => 'incr',
            'language'   => 'locale',
            'lang'       => 'locale',
            'new'        => 'create',
            'number'     => 'count',
            'remove'     => 'delete',
            'regen'      => 'regenerate',
            'rep'        => 'replace',
            'repl'       => 'replace',
            'trash'      => 'delete',
            'v'          => 'version',
        );

        if ( array_key_exists( $word, $suggestion_map ) && in_array( $suggestion_map[ $word ], $candidates, true ) ) {
            return $suggestion_map[ $word ];
        }

        if ( empty( $candidates ) ) {
            return '';
        }

        $levenshtein = array();
        foreach ( $candidates as $option ) {
            $levenshtein[ $option ] = levenshtein( (string) $option, $word );
        }

        
        $order = array();
        $i     = 0;
        foreach ( $levenshtein as $key => $distance ) {
            $order[] = array( $distance, $i++, $key );
        }
        usort(
            $order,
            static function ( array $a, array $b ): int {
                return $a[0] === $b[0] ? $a[1] - $b[1] : ( $a[0] < $b[0] ? -1 : 1 );
            }
        );
        $suggestion = $order[0][2];

        return $levenshtein[ $suggestion ] <= 2 && $suggestion !== $word
            ? (string) $suggestion
            : '';
    }

    
    public static function reset(): void {
        self::$index    = null;
        self::$families = array();
    }

    






    private static function enumerate( string $composite ): array {
        $index    = self::index();
        $children = '' === $composite
            ? $index['root']['subcommands']
            : ( isset( $index['composites'][ $composite ] ) ? $index['composites'][ $composite ]['subcommands'] : array() );
        $list     = array();
        foreach ( $children as $child ) {
            $relative = (string) $child;
            $list[]   = $relative;
            $full     = '' === $composite ? $relative : $composite . ' ' . $relative;
            if ( isset( $index['composites'][ $full ] ) ) {
                foreach ( self::enumerate( $full ) as $below ) {
                    $list[] = $relative . ' ' . $below;
                }
            }
        }
        return $list;
    }

    





    private static function index(): array {
        if ( null === self::$index ) {
            $file  = __DIR__ . '/catalog/index.php';
            $index = is_readable( $file ) ? include $file : null;
            if ( ! is_array( $index ) || ! isset( $index['families'], $index['composites'], $index['aliases'] ) ) {
                throw new \RuntimeException( 'The WP-CLI command catalog is missing from includes/cli-router/catalog/.' );
            }
            self::$index = $index;
        }
        return self::$index;
    }

    





    private static function family( string $top ): array {
        if ( ! isset( self::$families[ $top ] ) ) {
            self::$families[ $top ] = array();
            $index                  = self::index();
            if ( isset( $index['families'][ $top ] ) ) {
                $file   = __DIR__ . '/catalog/' . $index['families'][ $top ] . '.php';
                $family = is_readable( $file ) ? include $file : null;
                if ( is_array( $family ) ) {
                    self::$families[ $top ] = $family;
                }
            }
        }
        return self::$families[ $top ];
    }
}
