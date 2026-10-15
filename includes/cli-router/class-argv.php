<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















































final class Argv {

    
    const ALIAS_REGEX = '^@[A-Za-z0-9-_\.\-]+$';

    
    const ENV_ASSIGNMENT = '/^[A-Za-z_][A-Za-z0-9_]*\+?=/';

    






    const SPEC_MULTIPLE    = array( 'require', 'exec' );
    const SPEC_DEPRECATED  = array( 'blog' => 'Use --url instead.' );
    const SPEC_NOT_RUNTIME = array( 'disabled_commands', 'apache_modules' );

    














    public static function parse( array $words, array $global_keys ): array {
        $words = array_values( array_map( 'strval', $words ) );

        self::refuse_env_assignment( $words );
        if ( isset( $words[0] ) && 'wp' === $words[0] ) {
            array_shift( $words );
            self::refuse_env_assignment( $words );
        }

        
        
        if ( ! empty( $words[0] ) && preg_match( '#' . self::ALIAS_REGEX . '#', $words[0] ) ) {
            if ( '@all' === $words[0] ) {
                throw new Cli_Parse_Error( 'alias', "Cannot use '@all' when no aliases are registered." );
            }
            throw new Cli_Parse_Error( 'alias', "Alias '{$words[0]}' not found." );
        }

        list( $positional, $mixed )        = self::extract_assoc( $words );
        list( $assoc, $runtime, $notices ) = self::unmix_assoc_args( $mixed, self::runtime_spec( $global_keys ) );
        list( $positional, $assoc )        = self::back_compat_conversions( $positional, $assoc );

        
        
        if ( array_key_exists( 'blog', $runtime ) ) {
            $runtime['url'] = $runtime['blog'];
            unset( $runtime['blog'] );
        }

        if ( empty( $positional ) ) {
            $positional[] = 'help';
        }

        return array(
            'positional' => $positional,
            'assoc'      => $assoc,
            'runtime'    => $runtime,
            'notices'    => $notices,
        );
    }

    








    public static function extract_assoc( array $arguments ): array {
        $positional_args = array();
        $assoc_args      = array();
        $global_assoc    = array();
        $local_assoc     = array();

        foreach ( $arguments as $arg ) {
            $positional = null;
            $assoc_arg  = null;

            if ( preg_match( '|^--no-([^=]+)$|', $arg, $matches ) ) {
                $assoc_arg = array( $matches[1], false );
            } elseif ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
                $assoc_arg = array( $matches[1], true );
            } elseif ( preg_match( '|^--([^=]+)=(.*)|s', $arg, $matches ) ) {
                $assoc_arg = array( $matches[1], $matches[2] );
            } else {
                $positional = $arg;
            }

            if ( ! is_null( $assoc_arg ) ) {
                $assoc_args[] = $assoc_arg;
                if ( count( $positional_args ) ) {
                    $local_assoc[] = $assoc_arg;
                } else {
                    $global_assoc[] = $assoc_arg;
                }
            } elseif ( ! is_null( $positional ) ) {
                $positional_args[] = $positional;
            }
        }

        return array( $positional_args, $assoc_args, $global_assoc, $local_assoc );
    }

    
















    public static function back_compat_conversions( array $args, array $assoc_args ): array {
        $args = self::convert_top_level_alias( $args );
        $args = self::convert_dashed_meta( $args );
        $args = self::convert_cli_aliases( $args );

        list( $args, $assoc_args ) = self::convert_core( $args, $assoc_args );
        $args                      = self::convert_checksum( $args );
        $assoc_args                = self::convert_site_create_site_id( $args, $assoc_args );
        list( $args, $assoc_args ) = self::convert_bulk_subcommands( $args, $assoc_args );
        $args                      = self::convert_plugin_scaffold( $args );
        list( $args, $assoc_args ) = self::convert_flags( $args, $assoc_args );
        list( $args, $assoc_args ) = self::convert_url( $args, $assoc_args );
        list( $args, $assoc_args ) = self::convert_config_get( $args, $assoc_args );

        return array( $args, $assoc_args );
    }

    





    private static function convert_top_level_alias( array $args ): array {
        $top_level_aliases = array(
            'sql'  => 'db',
            'blog' => 'site',
        );
        if ( count( $args ) > 0 ) {
            foreach ( $top_level_aliases as $old => $new ) {
                if ( $old === $args[0] ) {
                    $args[0] = $new;
                    break;
                }
            }
        }
        return $args;
    }

    





    private static function convert_dashed_meta( array $args ): array {
        if ( ! empty( $args ) && preg_match( '/(post|comment|user|network)-meta/', $args[0], $matches ) ) {
            array_shift( $args );
            array_unshift( $args, 'meta' );
            array_unshift( $args, $matches[1] );
        }
        return $args;
    }

    





    private static function convert_cli_aliases( array $args ): array {
        if ( array( 'cli', 'aliases' ) === array_slice( $args, 0, 2 ) ) {
            list( $args[0], $args[1], $args[2] ) = array( 'cli', 'alias', 'list' );
        }
        return $args;
    }

    








    private static function convert_core( array $args, array $assoc_args ): array {
        if ( count( $args ) > 0 && 'core' === $args[0] && isset( $assoc_args['admin_name'] ) ) {
            $assoc_args['admin_user'] = $assoc_args['admin_name'];
            unset( $assoc_args['admin_name'] );
        }

        if ( array( 'core', 'config' ) === array_slice( $args, 0, 2 ) ) {
            list( $args[0], $args[1] ) = array( 'config', 'create' );
        }
        if ( array( 'core', 'language' ) === array_slice( $args, 0, 2 ) ) {
            list( $args[0], $args[1] ) = array( 'language', 'core' );
        }
        return array( $args, $assoc_args );
    }

    






    private static function convert_checksum( array $args ): array {
        if ( array( 'checksum', 'core' ) === array_slice( $args, 0, 2 ) ) {
            list( $args[0], $args[1] ) = array( 'core', 'verify-checksums' );
        }
        if ( array( 'checksum', 'plugin' ) === array_slice( $args, 0, 2 ) ) {
            list( $args[0], $args[1] ) = array( 'plugin', 'verify-checksums' );
        }
        return $args;
    }

    






    private static function convert_site_create_site_id( array $args, array $assoc_args ): array {
        if ( count( $args ) >= 2 && 'site' === $args[0] && 'create' === $args[1] && isset( $assoc_args['site_id'] ) ) {
            $assoc_args['network_id'] = $assoc_args['site_id'];
            unset( $assoc_args['site_id'] );
        }
        return $assoc_args;
    }

    








    private static function convert_bulk_subcommands( array $args, array $assoc_args ): array {
        if ( count( $args ) > 1 && in_array( $args[0], array( 'plugin', 'theme' ), true )
            && 'update-all' === $args[1]
        ) {
            $args[1]           = 'update';
            $assoc_args['all'] = true;
        }

        if ( count( $args ) > 1 && 'transient' === $args[0] && 'delete-expired' === $args[1] ) {
            $args[1]               = 'delete';
            $assoc_args['expired'] = true;
        }

        if ( count( $args ) > 1 && 'transient' === $args[0] && 'delete-all' === $args[1] ) {
            $args[1]           = 'delete';
            $assoc_args['all'] = true;
        }
        return array( $args, $assoc_args );
    }

    





    private static function convert_plugin_scaffold( array $args ): array {
        if ( array( 'plugin', 'scaffold' ) === array_slice( $args, 0, 2 ) ) {
            list( $args[0], $args[1] ) = array( $args[1], $args[0] );
        }
        return $args;
    }

    








    private static function convert_flags( array $args, array $assoc_args ): array {
        if ( isset( $assoc_args['help'] ) ) {
            array_unshift( $args, 'help' );
            unset( $assoc_args['help'] );
        }

        if ( count( $args ) > 1 && in_array( $args[0], array( 'post', 'user' ), true )
            && 'list' === $args[1]
            && isset( $assoc_args['ids'] )
        ) {
            $assoc_args['format'] = 'ids';
            unset( $assoc_args['ids'] );
        }

        if ( isset( $assoc_args['json'] ) ) {
            $assoc_args['format'] = 'json';
            unset( $assoc_args['json'] );
        }

        if ( empty( $args ) ) {
            $special_flags = array( 'version', 'info' );
            foreach ( $special_flags as $key ) {
                if ( isset( $assoc_args[ $key ] ) ) {
                    $args = array( 'cli', $key );
                    unset( $assoc_args[ $key ] );
                    break;
                }
            }
        }
        return array( $args, $assoc_args );
    }

    







    private static function convert_url( array $args, array $assoc_args ): array {
        if ( count( $args ) < 2 || ! in_array( $args[0], array( 'post', 'comment', 'site', 'term' ), true ) || 'url' !== $args[1] ) {
            return array( $args, $assoc_args );
        }
        switch ( $args[0] ) {
            case 'post':
                $post_ids                = array_slice( $args, 2 );
                $args                    = array( 'post', 'list' );
                $assoc_args['post__in']  = implode( ',', $post_ids );
                $assoc_args['post_type'] = 'any';
                $assoc_args['orderby']   = 'post__in';
                $assoc_args['field']     = 'url';
                break;
            case 'comment':
                $comment_ids               = array_slice( $args, 2 );
                $args                      = array( 'comment', 'list' );
                $assoc_args['comment__in'] = implode( ',', $comment_ids );
                $assoc_args['orderby']     = 'comment__in';
                $assoc_args['field']       = 'url';
                break;
            case 'site':
                $site_ids               = array_slice( $args, 2 );
                $args                   = array( 'site', 'list' );
                $assoc_args['site__in'] = implode( ',', $site_ids );
                $assoc_args['field']    = 'url';
                break;
            case 'term':
                $taxonomy = '';
                if ( isset( $args[2] ) ) {
                    $taxonomy = $args[2];
                }
                $term_ids              = array_slice( $args, 3 );
                $args                  = array( 'term', 'list', $taxonomy );
                $assoc_args['include'] = implode( ',', $term_ids );
                $assoc_args['orderby'] = 'include';
                $assoc_args['field']   = 'url';
                break;
        }
        return array( $args, $assoc_args );
    }

    








    private static function convert_config_get( array $args, array $assoc_args ): array {
        if ( count( $args ) !== 2 || 'config' !== $args[0] || 'get' !== $args[1] ) {
            return array( $args, $assoc_args );
        }
        $name = null;
        $type = null;
        if ( isset( $assoc_args['global'] ) ) {
            $name = $assoc_args['global'];
            $type = 'variable';
            unset( $assoc_args['global'] );
        } elseif ( isset( $assoc_args['constant'] ) ) {
            $name = $assoc_args['constant'];
            $type = 'constant';
            unset( $assoc_args['constant'] );
        }
        if ( ! empty( $name ) && ! empty( $type ) ) {
            $args[]             = $name;
            $assoc_args['type'] = $type;
        } else {
            
            $args[1] = 'list';
        }
        return array( $args, $assoc_args );
    }

    










    private static function unmix_assoc_args( array $mixed_args, array $spec ): array {
        $assoc_args     = array();
        $runtime_config = array();
        $notices        = array();

        foreach ( $mixed_args as $tmp ) {
            list( $key, $value ) = $tmp;

            if ( isset( $spec[ $key ] ) ) {
                if ( null !== $spec[ $key ]['deprecated'] ) {
                    $notices[] = "WP-CLI: The --{$key} global parameter is deprecated. {$spec[ $key ]['deprecated']}";
                }
                if ( $spec[ $key ]['multiple'] ) {
                    $runtime_config[ $key ][] = $value;
                } else {
                    $runtime_config[ $key ] = $value;
                }
            } else {
                $assoc_args[ $key ] = $value;
            }
        }

        return array( $assoc_args, $runtime_config, $notices );
    }

    













    private static function runtime_spec( array $global_keys ): array {
        $spec = array();
        foreach ( $global_keys as $key => $details ) {
            if ( is_int( $key ) ) {
                $name  = is_string( $details ) ? $details : '';
                $entry = self::spec_from_name( $name );
            } else {
                $name  = $key;
                $entry = is_array( $details ) ? self::spec_from_record( $details ) : null;
            }
            if ( null !== $entry ) {
                $spec[ $name ] = $entry;
            }
        }
        return $spec;
    }

    





    private static function spec_from_name( string $name ): ?array {
        if ( '' === $name || in_array( $name, self::SPEC_NOT_RUNTIME, true ) ) {
            return null;
        }
        return array(
            'multiple'   => in_array( $name, self::SPEC_MULTIPLE, true ),
            'deprecated' => array_key_exists( $name, self::SPEC_DEPRECATED ) ? self::SPEC_DEPRECATED[ $name ] : null,
        );
    }

    






    private static function spec_from_record( array $details ): ?array {
        if ( ! isset( $details['runtime'] ) || false === $details['runtime'] ) {
            return null;
        }
        return array(
            'multiple'   => ! empty( $details['multiple'] ),
            'deprecated' => isset( $details['deprecated'] ) ? (string) $details['deprecated'] : null,
        );
    }

    



    private static function refuse_env_assignment( array $words ): void {
        if ( ! isset( $words[0] ) || ! preg_match( self::ENV_ASSIGNMENT, $words[0], $m ) ) {
            return;
        }
        
        throw new Cli_Parse_Error(
            'env_assignment',
            'wp_cli_run does not run a shell: environment assignment ' . $m[0] . '... before the command. Environment variables cannot be set; remove it.'
        );
    }
}
