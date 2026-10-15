<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




























final class Super_Admin_Handler extends Command_Handler {

    
    const FIELDS = array( 'user_login' );

    
    const NOT_CAPTURED = 'Super-admin changes are recorded only as an update of the site_admins network option, and only while change history captures meta and records every option (not in allowlist mode); wp_history_apply cannot restore the super-admin list.';

    const MSG_NOT_MULTISITE  = 'This is not a multisite installation.';
    
    const MSG_GLOBAL         = 'wp_cli_run does not change super admins here: the $super_admins global overrides the site_admins option, so the change would have no effect. Edit $super_admins where it is set.';
    const MSG_SELF           = 'wp_cli_run never revokes the super-admin capabilities of your own user (user %d): you would lose this command and Network Admin. Ask another super admin.';
    const MSG_LAST           = 'wp_cli_run never leaves the network without a super admin: no login left in site_admins would belong to an existing user.';
    const NOTE_STALE_LOGIN   = "The super-admin list names '%s', which matches no user, so its id is empty in this output; WP-CLI also prints PHP's \"Attempt to read property\" warning here.";
    const NOTE_GLOBAL_LIST   = 'The $super_admins global overrides this list on this network: WP-CLI, and this command, print the site_admins network option, which WordPress does not read while the global is set.';

    
    
    

    
    public function preflight_list( Invocation $inv, Context $ctx ): void {
        if ( ! \is_multisite() ) {
            $ctx->out->error( self::MSG_NOT_MULTISITE );
        }
    }

    
    public function preflight_write( Invocation $inv, Context $ctx ): void {
        $this->preflight_list( $inv, $ctx );
        $ctx->require_network( 'manage_network_options' );
        if ( isset( $GLOBALS['super_admins'] ) ) {
            $ctx->out->error( self::MSG_GLOBAL );
        }
    }

    






    public function preflight_remove( Invocation $inv, Context $ctx ): void {
        $this->preflight_write( $inv, $ctx );
        $caller = function_exists( 'get_userdata' ) ? \get_userdata( $ctx->user_id() ) : false;
        if ( ! is_object( $caller ) ) {
            return;
        }
        $login = isset( $caller->user_login ) ? (string) $caller->user_login : '';
        $email = isset( $caller->user_email ) ? (string) $caller->user_email : '';
        foreach ( $inv->args as $arg ) {
            if ( self::names_caller( (string) $arg, $ctx->user_id(), $login, $email ) ) {
                $ctx->out->error( sprintf( self::MSG_SELF, $ctx->user_id() ) );
            }
        }
    }

    




    private static function names_caller( string $arg, int $caller_id, string $login, string $email ): bool {
        if ( is_numeric( $arg ) ) {
            
            return (int) $arg === $caller_id;
        }
        if ( function_exists( 'is_email' ) && \is_email( $arg ) ) {
            return ( '' !== $email && $arg === $email ) || ( '' !== $login && $arg === $login );
        }
        return '' !== $login && $arg === $login;
    }

    
    
    

    
    public function list_admins( Invocation $inv, Context $ctx ): void {
        $super_admins = self::get_admins();
        if ( isset( $GLOBALS['super_admins'] ) ) {
            $ctx->note( self::NOTE_GLOBAL_LIST );
        }

        $assoc = $inv->assoc;
        if ( 'list' === $inv->get( 'format' ) ) {
            foreach ( $super_admins as $user_login ) {
                $ctx->out->line( (string) $user_login );
            }
        } else {
            $output_users = array();
            foreach ( $super_admins as $user_login ) {
                $output_user = new \stdClass();

                $output_user->user_login = $user_login;

                $output_users[] = $output_user;
            }

            if ( ! empty( $assoc['format'] ) && 'ids' === $assoc['format'] ) {
                $formatter = $this->formatter( $ctx, $assoc );

                $user_ids = array();
                foreach ( $super_admins as $user_login ) {
                    $user_obj = \get_user_by( 'login', $user_login );
                    if ( ! $user_obj ) {
                        $ctx->note( sprintf( self::NOTE_STALE_LOGIN, (string) $user_login ) );
                    }
                    $user_ids[] = $user_obj ? $user_obj->ID : null;
                }
                $formatter->display_items( $user_ids );
            } else {
                $formatter = $this->formatter( $ctx, $assoc, self::FIELDS );
                $formatter->display_items( $output_users );
            }
        }
    }

    
    
    

    
    public function add( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        $args = array_values( $inv->args );

        $successes = 0;
        $errors    = 0;
        $users     = Fetchers::of( 'user' )->get_many( $args, $ctx->out );

        if ( count( $users ) !== count( $args ) ) {
            $errors = count( $args ) - count( $users );
        }

        $super_admins     = self::get_admins();
        $num_super_admins = count( $super_admins );

        $new_super_admins = array();
        foreach ( $users as $user ) {
            \do_action( 'grant_super_admin', (int) $user->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook, fired where WP-CLI fires it.

            if ( in_array( $user->user_login, $super_admins, true ) ) {
                $ctx->out->warning( "User '{$user->user_login}' already has super-admin capabilities." );
                continue;
            }

            $new_super_admins[] = $user->ID;
            $super_admins[]     = $user->user_login;
            ++$successes;
        }

        if ( count( $super_admins ) === $num_super_admins ) {
            if ( $errors ) {
                $user_count = count( $args );
                $ctx->out->error( "Couldn't grant super-admin capabilities to {$errors} of {$user_count} users." );
            } else {
                $ctx->out->success( 'Super admins remain unchanged.' );
            }
        } elseif ( \update_site_option( 'site_admins', $super_admins ) ) {
            $ctx->mark_changed();
            if ( $errors ) {
                $user_count = count( $args );
                $ctx->out->error( "Only granted super-admin capabilities to {$successes} of {$user_count} users." );
            } else {
                $message = $successes > 1 ? 'users' : 'user';
                $ctx->out->success( "Granted super-admin capabilities to {$successes} {$message}." );
            }
        } else {
            $ctx->out->error( 'Site options update failed.' );
        }

        foreach ( $new_super_admins as $user_id ) {
            \do_action( 'granted_super_admin', (int) $user_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook, fired where WP-CLI fires it.
        }
    }

    
    public function describe_add( Invocation $inv ): array {
        $admins = self::get_admins();
        $lines  = array();
        foreach ( array_values( array_unique( $inv->args ) ) as $arg ) {
            $user = Fetchers::user( (string) $arg );
            if ( ! $user ) {
                $lines[] = sprintf( "'%s': no such user → skipped (the command ends with an Error)", (string) $arg );
            } elseif ( in_array( $user->user_login, $admins, true ) ) {
                $lines[] = sprintf( "User '%s' (ID %d): a super admin → unchanged", $user->user_login, (int) $user->ID );
            } else {
                $lines[] = sprintf( "User '%s' (ID %d): not a super admin → granted super-admin capabilities on the whole network", $user->user_login, (int) $user->ID );
            }
        }
        return $lines;
    }

    
    
    

    
    public function remove( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        $args              = array_values( $inv->args );
        $users             = Fetchers::of( 'user' )->get_many( $args, $ctx->out );
        $user_logins       = $users ? array_values( array_unique( self::pluck_logins( $users ) ) ) : array();
        $user_logins_count = count( $user_logins );

        $planned = self::refuse_lockout( $users, $args, $user_logins, $ctx );

        $user_ids = array();
        foreach ( $users as $user ) {
            $user_ids[ $user->user_login ] = $user->ID;

            \do_action( 'revoke_super_admin', (int) $user->ID ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook, fired where WP-CLI fires it.
        }

        $super_admins = self::get_admins();
        if ( ! $super_admins ) {
            $ctx->out->error( 'No super admins to revoke super-admin privileges from.' );
        }

        if ( $user_logins_count < count( $args ) ) {
            $user_logins       = $planned;
            $user_logins_count = count( $user_logins );
        }
        if ( ! $user_logins ) {
            $ctx->out->error( 'No valid user logins given to revoke super-admin privileges from.' );
        }

        $update_super_admins = array_diff( $super_admins, $user_logins );
        if ( $update_super_admins === $super_admins ) {
            $ctx->out->error( $user_logins_count > 1 ? 'None of the given users is a super admin.' : 'The given user is not a super admin.' );
        }

        
        
        
        if ( ! \update_site_option( 'site_admins', $update_super_admins ) ) {
            $ctx->out->error( 'Site options update failed.' );
        }
        $ctx->mark_changed();

        $successes = count( $super_admins ) - count( $update_super_admins );
        $ctx->out->success( self::revoked_message( $successes, $user_logins_count, empty( $update_super_admins ) ) );

        self::fire_revoked( array_intersect( $user_logins, $super_admins ), $user_ids );
    }

    










    private static function refuse_lockout( array $users, array $args, array $user_logins, Context $ctx ): array {
        $caller = $ctx->user_id();
        foreach ( $users as $user ) {
            if ( (int) $user->ID === $caller ) {
                $ctx->out->error( sprintf( self::MSG_SELF, $caller ) );
            }
        }
        $planned = self::planned_logins( $args, $user_logins );
        $current = self::get_admins();
        $remain  = array_diff( $current, $planned );
        if ( $current && $planned && $remain !== $current && ! self::any_user_exists( $remain ) ) {
            $ctx->out->error( self::MSG_LAST );
        }
        return $planned;
    }

    
    private static function revoked_message( int $successes, int $user_logins_count, bool $none_left ): string {
        if ( $successes === $user_logins_count ) {
            $message = $user_logins_count > 1 ? 'users' : 'user';
            $msg     = "Revoked super-admin capabilities from {$user_logins_count} {$message}.";
        } else {
            $msg = "Revoked super-admin capabilities from {$successes} of {$user_logins_count} users.";
        }
        if ( $none_left ) {
            $msg .= ' There are no remaining super admins.';
        }
        return $msg;
    }

    






    private static function fire_revoked( array $removed_logins, array $user_ids ): void {
        foreach ( $removed_logins as $user_login ) {
            $user_id = null;

            if ( array_key_exists( $user_login, $user_ids ) ) {
                $user_id = $user_ids[ $user_login ];
            } else {
                $user = \get_user_by( 'login', $user_login );
                if ( is_object( $user ) && isset( $user->ID ) ) {
                    $user_id = $user->ID;
                }
            }

            if ( null !== $user_id ) {
                \do_action( 'revoked_super_admin', (int) $user_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's hook, fired where WP-CLI fires it.
            }
        }
    }

    
    public function describe_remove( Invocation $inv ): array {
        $admins = self::get_admins();
        $lines  = array();
        foreach ( array_values( array_unique( $inv->args ) ) as $arg ) {
            $user  = Fetchers::user( (string) $arg );
            $login = $user ? (string) $user->user_login : (string) $arg;
            $label = $user ? sprintf( "User '%s' (ID %d)", $login, (int) $user->ID ) : sprintf( "'%s' (no such user; matched as a login)", $login );
            $lines[] = sprintf( '%s: %s', $label, in_array( $login, $admins, true ) ? 'a super admin → super-admin capabilities revoked on the whole network' : 'not a super admin → unchanged' );
        }
        return $lines;
    }

    
    
    

    





    private static function get_admins(): array {
        return (array) \get_site_option( 'site_admins', array( 'admin' ) );
    }

    





    private static function pluck_logins( array $users ): array {
        $logins = array();
        foreach ( $users as $user ) {
            $logins[] = $user->user_login;
        }
        return $logins;
    }

    










    private static function planned_logins( array $args, array $user_logins ): array {
        if ( count( $user_logins ) >= count( $args ) ) {
            return $user_logins;
        }
        $flipped_user_logins = array_flip( $user_logins );
        return array_merge(
            $user_logins,
            array_unique(
                array_filter(
                    $args,
                    static function ( $v ) use ( $flipped_user_logins ) {
                        
                        return ! isset( $flipped_user_logins[ $v ] ) && ! is_numeric( $v ) && ! \is_email( $v );
                    }
                )
            )
        );
    }

    
    private static function any_user_exists( array $logins ): bool {
        foreach ( $logins as $login ) {
            if ( \get_user_by( 'login', (string) $login ) ) {
                return true;
            }
        }
        return false;
    }
}
