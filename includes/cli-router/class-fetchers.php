<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
















































final class Fetchers {

    
    const MESSAGES = array(
        'post'    => 'Could not find the post with ID %d.',
        'user'    => "Invalid user ID, email or login: '%s'",
        'comment' => 'Could not find the comment with ID %d.',
        'site'    => 'Could not find the site with ID %d.',
        'signup'  => "Invalid signup ID, email, login, or activation key: '%s'",
        'plugin'  => "The '%s' plugin could not be found.",
        'theme'   => "The '%s' theme could not be found.",
    );

    
    const TERM_MISSING = "Term doesn't exist.";

    
    const USER_AMBIGUOUS = 'Ambiguous user match detected (both ID and user_login exist for identifier \'%d\'). WP-CLI will default to the ID, but you can force user_login instead with WP_CLI_FORCE_USER_LOGIN=1.';

    




    const TOOL_UNAVAILABLE = "'wp %s' runs through the %s tool, and this connection cannot use it: the tool is switched off, not granted to this API key or OAuth grant, or needs a capability your WordPress user lacks.";

    
    const TOOL_OUT_OF_REACH = "'wp %s' runs through the %s tool, which %s";

    
    private $kind;

    
    private $msg;

    
    private function __construct( string $kind ) {
        $this->kind = $kind;
        $this->msg  = self::MESSAGES[ $kind ];
    }

    




    public static function of( string $kind ): self {
        if ( ! isset( self::MESSAGES[ $kind ] ) ) {
            throw new \InvalidArgumentException( 'Unknown fetcher kind: ' . $kind );
        }
        return new self( $kind );
    }

    
    public function message(): string {
        return $this->msg;
    }

    






    public function get( $arg, ?Output $out = null ) {
        switch ( $this->kind ) {
            case 'post':
                return self::post( $arg );
            case 'user':
                return self::user( $arg, $out );
            case 'comment':
                return self::comment( $arg );
            case 'site':
                return self::site( $arg );
            case 'signup':
                return self::signup( $arg );
            case 'plugin':
                return self::plugin( $arg );
            default:
                return $this->theme( $arg );
        }
    }

    






    public function get_check( $arg, Output $out ) {
        $item = $this->get( $arg, $out );
        if ( ! $item ) {
            $out->error( sprintf( $this->msg, $arg ) );
        }
        return $item;
    }

    





    public function get_many( array $args, Output $out ): array {
        $items = array();
        foreach ( $args as $arg ) {
            $item = $this->get( $arg, $out );
            if ( $item ) {
                $items[] = $item;
            } else {
                $out->warning( sprintf( $this->msg, $arg ) );
            }
        }
        return $items;
    }

    









    public function get_check_for( Context $ctx, Invocation $inv, string $tool, $arg, ?callable $object_check = null ) {
        self::require_tool( $ctx, $inv, $tool );
        $item = $this->get_check( $arg, $ctx->out );
        if ( null !== $object_check ) {
            $refusal = call_user_func( $object_check, $item );
            if ( is_string( $refusal ) && '' !== $refusal ) {
                $ctx->out->error( $refusal );
            }
        }
        return $item;
    }

    





    public static function require_tool( Context $ctx, Invocation $inv, string $tool ): void {
        if ( ! $ctx->may_use( $tool ) ) {
            $ctx->out->error( self::tool_unavailable( $ctx, $inv->command, $tool ) );
        }
    }

    










    public static function tool_unavailable( Context $ctx, string $command, string $tool ): string {
        $status = $ctx->tool_status( $tool );
        if ( null !== $status ) {
            
            
            require_once dirname( __DIR__ ) . '/mcp/class-tool-status.php';
            $predicate = \Easy_MCP_AI\MCP\Tool_Status::predicate( $status );
            if ( '' !== $predicate ) {
                return sprintf( self::TOOL_OUT_OF_REACH, $command, $tool, $predicate );
            }
        }
        return sprintf( self::TOOL_UNAVAILABLE, $command, $tool );
    }

    







    public static function term_by( string $field, $value, string $taxonomy ) {
        if ( ! function_exists( 'get_term_by' ) ) {
            return false;
        }
        $term = \get_term_by( $field, $value, $taxonomy );
        return $term ? $term : false;
    }

    
    
    

    





    private static function post( $arg ) {
        $post = \get_post( $arg );
        return null === $post ? false : $post;
    }

    







    public static function user( $arg, ?Output $out = null ) {
        if ( ! function_exists( 'get_user_by' ) ) {
            return false;
        }
        if ( ! is_string( $arg ) ) {
            return \get_user_by( 'login', true === $arg ? '1' : ( is_scalar( $arg ) && false !== $arg ? (string) $arg : '' ) );
        }
        if ( is_numeric( $arg ) ) {
            return self::user_by_id( $arg, $out );
        }
        if ( function_exists( 'is_email' ) && \is_email( $arg ) ) {
            return self::user_by_email( $arg );
        }
        return \get_user_by( 'login', $arg );
    }

    





    private static function user_by_id( string $arg, ?Output $out ) {
        $check = \get_user_by( 'login', $arg );
        $user  = \get_user_by( 'id', $arg );
        if ( $check && $user && null !== $out ) {
            $out->warning( sprintf( self::USER_AMBIGUOUS, $arg ) );
        }
        return $user;
    }

    





    private static function user_by_email( string $arg ) {
        $user = \get_user_by( 'email', $arg );
        
        if ( ! $user ) {
            $user = \get_user_by( 'login', $arg );
        }
        return $user;
    }

    





    private static function comment( $arg ) {
        $comment = \get_comment( (int) $arg );
        return null === $comment ? false : $comment;
    }

    





    private static function site( $arg ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's Site fetcher reads the row itself.
        $site = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->blogs} WHERE blog_id = %d", $arg ) );
        return ! empty( $site ) ? $site : false;
    }

    






    private static function signup( $arg ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) ) {
            return false;
        }
        $signup_object = null;
        if ( is_numeric( $arg ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's Signup fetcher reads the row itself.
            $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE signup_id = %d", $arg ) );
            if ( $result ) {
                $signup_object = $result;
            }
        }
        if ( ! $signup_object ) {
            foreach ( array( 'user_login', 'user_email', 'activation_key' ) as $field ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $field is one of three fixed column names.
                $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->signups WHERE $field = %s", $arg ) );
                if ( $result ) {
                    $signup_object = $result;
                    break;
                }
            }
        }
        return $signup_object ? $signup_object : false;
    }

    





    private static function plugin( $name ) {
        if ( ! self::load_plugin_api() ) {
            return false;
        }
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, as WP-CLI reads it.
        foreach ( \apply_filters( 'all_plugins', \get_plugins() ) as $file => $_ ) {
            if ( "$name.php" === $file ||
                ( $name && $file === $name ) ||
                ( dirname( $file ) === $name && '.' !== $name ) ) {
                return (object) compact( 'name', 'file' );
            }
        }
        return false;
    }

    



    private static function load_plugin_api(): bool {
        if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return function_exists( 'get_plugins' );
    }

    






    private function theme( $name ) {
        if ( ! function_exists( 'wp_get_themes' ) ) {
            return false;
        }
        $existing_themes      = \wp_get_themes( array( 'errors' => null ) );
        $existing_stylesheets = array_keys( $existing_themes );
        if ( ! in_array( $name, $existing_stylesheets, true ) ) {
            $inexact_match = self::find_inexact_match( (string) $name, $existing_themes );
            if ( false !== $inexact_match ) {
                $this->msg .= sprintf( " Did you mean '%s'?", str_replace( '%', '%%', $inexact_match ) );
            }
            return false;
        }
        return $existing_themes[ $name ];
    }

    







    private static function find_inexact_match( string $name, array $existing_themes ) {
        $target = strtolower( $name );
        $themes = array_map( 'strtolower', array_map( 'strval', array_keys( $existing_themes ) ) );
        if ( in_array( $target, $themes, true ) ) {
            return $target;
        }
        $suggestion = Catalog::suggestion( $target, $themes );
        if ( '' !== $suggestion ) {
            return $suggestion;
        }
        return false;
    }
}
