<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
































final class Help {

    
    const WIDTH = 80;

    



    public static function render( array $path ): string {
        $words = array();
        foreach ( $path as $word ) {
            $words[] = (string) $word;
        }

        if ( ! $words ) {
            $root = Catalog::root();
            return self::show_help(
                self::man( 'wp', (string) $root['shortdesc'], 'wp <command>', '', self::render_subcommands( '', $root['subcommands'] ) ),
                (string) $root['longdesc']
            );
        }

        
        
        $found = Catalog::find( $words );
        if ( null !== $found['leaf'] ) {
            $leaf = $found['leaf'];
            $name = 'wp ' . $leaf['command'];
            return self::show_help(
                self::man(
                    $name,
                    self::shortdesc_with_hook( (string) $leaf['shortdesc'], isset( $leaf['hook'] ) ? (string) $leaf['hook'] : '', false ),
                    $name . ' ' . $leaf['synopsis'],
                    isset( $leaf['alias'] ) ? (string) $leaf['alias'] : '',
                    null
                ),
                $leaf['longdesc'] . Catalog::global_help()
            );
        }
        if ( null !== $found['composite'] && ! $found['rest'] ) {
            $composite = $found['composite'];
            $name      = 'wp ' . $composite['command'];
            return self::show_help(
                self::man(
                    $name,
                    self::shortdesc_with_hook( (string) $composite['shortdesc'], isset( $composite['hook'] ) ? (string) $composite['hook'] : '', true ),
                    $name . ' <command>',
                    '', 
                    self::render_subcommands( (string) $composite['command'], $composite['subcommands'] )
                ),
                $composite['longdesc'] . $composite['global_prefix'] . Catalog::global_help()
            );
        }
        return '';
    }

    




    private static function shortdesc_with_hook( string $shortdesc, string $hook, bool $has_subcommands ): string {
        $events = array(
            'find_command_to_run_pre'     => 'just before WP-CLI finds the command to run.',
            'before_registering_contexts' => 'before the contexts are registered.',
            'before_wp_load'              => 'just before the WP load process begins.',
            'before_wp_config_load'       => 'after wp-config.php has been located.',
            'after_wp_config_load'        => 'after wp-config.php has been loaded into scope.',
            'after_wp_load'               => 'just after the WP load process has completed.',
        );
        $hook_description = ( '' !== $hook && array_key_exists( $hook, $events ) ) ? $events[ $hook ] : null;
        if ( $hook_description && 'after_wp_load' !== $hook ) {
            if ( $has_subcommands ) {
                $shortdesc .= "\n\nUnless overridden, these commands run on the '$hook' hook, $hook_description";
            } else {
                $shortdesc .= "\n\nThis command runs on the '$hook' hook, $hook_description";
            }
        }
        return $shortdesc;
    }

    






    private static function man( string $name, string $shortdesc, string $synopsis, string $alias, ?array $subcommands ): string {
        $out = "## NAME\n\n\t" . $name . "\n\n";
        if ( ! empty( $shortdesc ) ) {
            $out .= "## DESCRIPTION\n\n\t" . $shortdesc . "\n\n";
        }
        $out .= "## SYNOPSIS\n\n\t" . $synopsis . "\n\n";
        if ( ! empty( $alias ) ) {
            $out .= "## ALIAS\n\n\t" . $alias . "\n\n";
        }
        if ( null !== $subcommands ) {
            $out .= "## SUBCOMMANDS\n\n";
            foreach ( $subcommands as $line ) {
                $out .= "\t" . $line . "\n";
            }
            $out .= "\n";
        }
        return $out;
    }

    









    private static function render_subcommands( string $parent, array $children ): array {
        $composites  = Catalog::composites();
        $subcommands = array();
        foreach ( $children as $child ) {
            $child = (string) $child;
            $full  = '' === $parent ? $child : $parent . ' ' . $child;
            $leaf  = Catalog::leaf( $full );
            if ( null !== $leaf ) {
                $subcommands[ $child ] = (string) $leaf['shortdesc'];
            } elseif ( isset( $composites[ $full ] ) ) {
                $subcommands[ $child ] = (string) $composites[ $full ]['shortdesc'];
            } else {
                $subcommands[ $child ] = '';
            }
        }

        $max_len = 0;
        foreach ( array_keys( $subcommands ) as $str ) {
            $len = strlen( (string) $str );
            if ( $len > $max_len ) {
                $max_len = $len;
            }
        }

        $lines = array();
        foreach ( $subcommands as $name => $desc ) {
            $lines[] = str_pad( (string) $name, $max_len ) . "\t\t\t" . $desc;
        }
        return $lines;
    }

    




    private static function show_help( string $initial_markdown, string $longdesc ): string {
        $out = $initial_markdown;

        
        $subcommands        = '';
        $subcommands_header = "## SUBCOMMANDS\n";
        $column_subpattern  = '[ \t]+[^\t]+\t+';
        $matched            = preg_match( '/(^## SUBCOMMANDS[^\n]*\n+' . $column_subpattern . '.+?)(?:^##|\z)/ms', $out, $matches, PREG_OFFSET_CAPTURE );
        self::check( $matched );
        if ( $matched ) {
            $subcommands = $matches[1][0];
            $out         = substr_replace( $out, $subcommands_header, $matches[1][1], strlen( $subcommands ) );
        }

        $out .= self::parse_reference_links( $longdesc );

        
        $out = self::check( preg_replace_callback( '/([^\n]+)\n: (.+?)(\n\n|$)/s', array( __CLASS__, 'rewrap_param_desc' ), $out ) );

        
        $out = self::check( preg_replace( '/^((?! |\t|##).)/m', "\t$1", $out ) );

        $tab = str_repeat( ' ', 2 );

        
        $out = str_replace( "\t", $tab, $out );

        $wordwrap_width = self::WIDTH;

        
        $out = self::check(
            preg_replace_callback(
                '/^( *)([^\n]+)\n/m',
                static function ( array $matches ) use ( $wordwrap_width ): string {
                    return $matches[1] . str_replace( "\n", "\n{$matches[1]}", wordwrap( $matches[2], $wordwrap_width - strlen( $matches[1] ) ) ) . "\n";
                },
                $out
            )
        );

        if ( $subcommands ) {
            
            $subcommands = self::check(
                preg_replace_callback(
                    '/^(' . $column_subpattern . ')([^\n]+)\n/m',
                    static function ( array $matches ) use ( $wordwrap_width, $tab ): string {
                        
                        $matches[1]  = str_replace( "\t", $tab, $matches[1] );
                        $matches[2]  = str_replace( "\t", $tab, $matches[2] );
                        $padding_len = strlen( $matches[1] );
                        $padding     = str_repeat( ' ', $padding_len );
                        return $matches[1] . str_replace( "\n", "\n$padding", wordwrap( $matches[2], $wordwrap_width - $padding_len ) ) . "\n";
                    },
                    $subcommands
                )
            );

            
            $out = str_replace( $subcommands_header, $subcommands, $out );
        }

        
        return self::check( preg_replace( '/^## ([A-Z ]+)/m', '\1', $out ) );
    }

    





    private static function rewrap_param_desc( array $matches ): string {
        $param = $matches[1];
        $desc  = self::indent( "\t\t", $matches[2] );
        return "\t$param\n$desc\n\n";
    }

    
    private static function indent( string $whitespace, string $text ): string {
        $lines = explode( "\n", $text );
        foreach ( $lines as &$line ) {
            $line = $whitespace . $line;
        }
        unset( $line );
        return implode( "\n", $lines );
    }

    





    private static function parse_reference_links( string $longdesc ): string {
        $description = '';
        foreach ( explode( "\n", $longdesc ) as $line ) {
            if ( 0 === strpos( $line, '#' ) ) {
                break;
            }
            $description .= $line . "\n";
        }

        
        if ( $description ) {
            $links   = array(); 
            $count   = 0;
            $pattern = '/\[.+?\]\((https?:\/\/.+?)\)/';
            $newdesc = self::check(
                preg_replace_callback(
                    $pattern,
                    static function ( array $matches ) use ( &$links, &$count ): string {
                        ++$count;
                        $links[] = $matches[1];
                        return str_replace( '(' . $matches[1] . ')', '[' . $count . ']', $matches[0] );
                    },
                    $description
                )
            );

            $footnote   = '';
            $link_count = count( $links );
            for ( $i = 0; $i < $link_count; $i++ ) {
                $n         = $i + 1;
                $footnote .= '[' . $n . '] ' . $links[ $i ] . "\n";
            }

            if ( $footnote ) {
                $newdesc  = trim( $newdesc ) . "\n\n---\n" . $footnote;
                $longdesc = str_replace( trim( $description ), trim( $newdesc ), $longdesc );
            }
        }

        return $longdesc;
    }

    








    private static function check( $result ) {
        if ( null === $result || false === $result ) {
            throw new \RuntimeException( 'wp_cli_run could not render this help page: a regular expression failed (PCRE error ' . preg_last_error() . ').' );
        }
        return $result;
    }
}
