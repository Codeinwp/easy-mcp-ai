<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Capability_Grants;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

























final class User_Handler extends Command_Handler {

    
    const NETWORK_CAPS = Capability_Grants::NETWORK_CAPS;

    const NOT_CAPTURED_CAPS = 'User capability changes are stored in the user\'s capabilities meta, which change history records only while user meta capture is on.';
    const NOT_CAPTURED_SPAM = 'The spam flags of the user\'s sites are written to the sites table with direct database queries, which change history records only while database capture is on.';

    const MSG_NOT_HELD      = "wp_cli_run grants a user only capabilities your own WordPress user has, and you (user %d) do not have '%s'. A user who has it can grant it.";
    const MSG_NETWORK_CAP   = "'%s' is a network capability: a user who holds it reaches Network Admin, so wp_cli_run lets only a super admin grant it.";
    const MSG_ROLE_CAP      = "'%s' is a role, so granting it as a capability gives the user that role, and you may not assign it: %s";
    const MSG_NOT_HELD_DENY = "removing the explicit denial of '%s' from %s (%d) would let the user gain it from a role, and wp_cli_run lifts a denial only for capabilities your own WordPress user (user %d) has.";
    const MSG_TARGET        = 'Sorry, you are not allowed to change the capabilities of %s (%d): wp_cli_run needs the edit_user and promote_user capabilities on that user.';
    const MSG_LAST_ADMIN    = "removing the 'administrator' role from %s (%d) would leave this site without an administrator. Promote another user to administrator first.";
    const MSG_OWN_GATE      = "removing the '%s' role from your own WordPress user (user %d) would leave it without a role that can edit users. Do it as another administrator.";
    const MSG_SITES_NOTE    = 'User %d %s: WP-CLI also set the spam flag to %s on every site of the user except the main site: %s.';

    
    
    

    



    public function preflight_cap( Invocation $inv, Context $ctx ): void {
        $cap     = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        $refusal = Self_Protection::capability( $cap );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        if ( 'user add-cap' === $inv->command ) {
            $refusal = self::grant_refusal( $ctx, $cap );
            if ( null !== $refusal ) {
                $ctx->out->error( $refusal );
            }
        } elseif ( self::is_role( $cap ) && Read_Value::flag( $inv->assoc, 'force' ) ) {
            $refusal = self::role_refusal( $cap );
            if ( null !== $refusal ) {
                $ctx->out->error( sprintf( self::MSG_ROLE_CAP, $cap, $refusal ) );
            }
        }
    }

    
    public function add_cap( Invocation $inv, Context $ctx ): void {
        $user = Fetchers::of( 'user' )->get_check( isset( $inv->args[0] ) ? $inv->args[0] : '', $ctx->out );
        $this->require_target( $ctx, $user );
        $cap = (string) $inv->args[1];
        $ctx->not_captured( self::NOT_CAPTURED_CAPS );

        $caps = isset( $user->caps ) ? (array) $user->caps : array();
        if ( ! ( array_key_exists( $cap, $caps ) && true === $caps[ $cap ] ) ) {
            $user->add_cap( $cap );
            $ctx->mark_changed();
        }
        $ctx->out->success( "Added '{$cap}' capability for {$user->user_login} ({$user->ID})." );
    }

    
    public function remove_cap( Invocation $inv, Context $ctx ): void {
        $user = Fetchers::of( 'user' )->get_check( isset( $inv->args[0] ) ? $inv->args[0] : '', $ctx->out );
        $this->require_target( $ctx, $user );
        $cap  = (string) $inv->args[1];
        $caps = isset( $user->caps ) ? (array) $user->caps : array();
        if ( ! isset( $caps[ $cap ] ) ) {
            self::refuse_not_direct( $ctx, $user, $cap );
        }
        $this->guard_removal( $inv, $ctx, $user, $cap, $caps );

        $ctx->not_captured( self::NOT_CAPTURED_CAPS );
        $user->remove_cap( $cap );
        $ctx->mark_changed();
        $ctx->out->success( "Removed '{$cap}' cap for {$user->user_login} ({$user->ID})." );
    }

    






    private static function refuse_not_direct( Context $ctx, object $user, string $cap ): void {
        $allcaps = isset( $user->allcaps ) ? (array) $user->allcaps : array();
        if ( isset( $allcaps[ $cap ] ) ) {
            $ctx->out->error( "The '{$cap}' cap for {$user->user_login} ({$user->ID}) is inherited from a role." );
        }
        $ctx->out->error( "No such '{$cap}' cap for {$user->user_login} ({$user->ID})." );
    }

    









    private function guard_removal( Invocation $inv, Context $ctx, object $user, string $cap, array $caps ): void {
        $user_roles = isset( $user->roles ) ? array_values( (array) $user->roles ) : array();
        if ( ! empty( $user_roles ) && in_array( $cap, $user_roles, true ) ) {
            if ( ! Read_Value::flag( $inv->assoc, 'force' ) ) {
                $ctx->out->error( "Aborting because a role has the same name as '{$cap}'. Use `wp user remove-cap {$user->ID} {$cap} --force` to proceed with the removal." );
            }
            $this->guard_role_removal( $ctx, $user, $cap, $user_roles );
        } elseif ( false === $caps[ $cap ] ) {
            $refusal = self::grant_refusal( $ctx, $cap );
            if ( null !== $refusal ) {
                $ctx->out->error( sprintf( self::MSG_NOT_HELD_DENY, $cap, $user->user_login, (int) $user->ID, $ctx->user_id() ) );
            }
        }
    }

    





    public function describe_add_cap( Invocation $inv ): array {
        $cap  = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        $user = self::peek_user( isset( $inv->args[0] ) ? $inv->args[0] : '' );
        if ( ! $user ) {
            return array( sprintf( "User '%s': not found → nothing to grant", isset( $inv->args[0] ) ? $inv->args[0] : '' ) );
        }
        return array( sprintf( "User %s (%d) capability '%s': %s → grant directly", $user->user_login, (int) $user->ID, $cap, self::hold( $user, $cap ) ) );
    }

    




    public function describe_remove_cap( Invocation $inv ): array {
        $cap  = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        $user = self::peek_user( isset( $inv->args[0] ) ? $inv->args[0] : '' );
        if ( ! $user ) {
            return array( sprintf( "User '%s': not found → nothing to remove", isset( $inv->args[0] ) ? $inv->args[0] : '' ) );
        }
        $roles  = isset( $user->roles ) ? (array) $user->roles : array();
        $effect = in_array( $cap, $roles, true ) ? 'remove the role' : 'remove the direct grant';
        return array( sprintf( "User %s (%d) capability '%s': %s → %s", $user->user_login, (int) $user->ID, $cap, self::hold( $user, $cap ), $effect ) );
    }

    
    
    

    
    public function spam( Invocation $inv, Context $ctx ): void {
        $this->update_msuser_status( $inv, $ctx, '1' );
    }

    
    public function unspam( Invocation $inv, Context $ctx ): void {
        $this->update_msuser_status( $inv, $ctx, '0' );
    }

    




    public function describe_spam( Invocation $inv ): array {
        return self::describe_status( $inv, '1' );
    }

    




    public function describe_unspam( Invocation $inv ): array {
        return self::describe_status( $inv, '0' );
    }

    
    
    

    



    private function update_msuser_status( Invocation $inv, Context $ctx, string $value ): void {
        if ( ! \is_multisite() ) {
            $ctx->out->error( 'This is not a multisite installation.' );
        }
        $action   = (int) $value ? 'marked as spam' : 'removed from spam';
        $verb     = (int) $value ? 'spam' : 'unspam';
        $user_ids = array_values( $inv->args );

        $successes = 0;
        $errors    = 0;
        $users     = Fetchers::of( 'user' )->get_many( $user_ids, $ctx->out );
        if ( count( $users ) < count( $user_ids ) ) {
            $errors = count( $user_ids ) - count( $users );
        }

        if ( '1' === $value ) {
            self::refuse_current_site( $users, $value, $ctx );
        }
        $ctx->not_captured( self::NOT_CAPTURED_SPAM );

        foreach ( $users as $user ) {
            if ( self::set_spam( $user, $value, $action, $ctx ) ) {
                ++$successes;
            }
        }

        Values::report_batch( $ctx->out, 'user', $verb, count( $user_ids ), $successes, $errors );
    }

    







    private static function refuse_current_site( array $users, string $value, Context $ctx ): void {
        foreach ( $users as $user ) {
            if ( self::is_network_admin( $user ) || $value === $user->spam ) {
                continue;
            }
            foreach ( self::spam_sites( (int) $user->ID ) as $site ) {
                $refusal = Self_Protection::current_site( (int) $site->userblog_id );
                if ( null !== $refusal ) {
                    $ctx->out->error( $refusal );
                }
            }
        }
    }

    







    private static function set_spam( object $user, string $value, string $action, Context $ctx ): bool {
        $user_id = $user->ID;

        
        if ( self::is_network_admin( $user ) ) {
            $ctx->out->warning( "User cannot be modified. The user {$user->ID} is a network administrator." );
            return false;
        }

        
        if ( $value === $user->spam ) {
            $ctx->out->warning( "User {$user_id} already {$action}." );
            return false;
        }

        $named = array();
        foreach ( self::spam_sites( (int) $user_id ) as $details ) {
            \update_blog_status( $details->userblog_id, 'spam', $value );
            $named[] = self::site_label( $details );
        }

        \wp_update_user(
            array(
                'ID'   => $user_id,
                'spam' => $value,
            )
        );
        $ctx->mark_changed();
        if ( $named ) {
            $ctx->note( sprintf( self::MSG_SITES_NOTE, (int) $user_id, $action, $value, implode( ', ', $named ) ) );
        }

        $ctx->out->log( "User {$user_id} {$action}." );
        return true;
    }

    








    private static function spam_sites( int $user_id ): array {
        $sites = array();
        foreach ( (array) \get_blogs_of_user( $user_id, true ) as $details ) {
            
            if ( \get_main_site_id( (int) $details->site_id ) !== (int) $details->userblog_id ) {
                $sites[] = $details;
            }
        }
        return $sites;
    }

    




    private static function site_label( object $details ): string {
        $url = isset( $details->siteurl ) ? (string) $details->siteurl : ( isset( $details->domain ) ? (string) $details->domain . ( isset( $details->path ) ? (string) $details->path : '' ) : '' );
        return (int) $details->userblog_id . ( '' !== $url ? ' (' . $url . ')' : '' );
    }

    





    private static function is_network_admin( object $user ): bool {
        $login = isset( $user->user_login ) ? (string) $user->user_login : '';
        return function_exists( 'get_super_admins' ) && in_array( $login, (array) \get_super_admins(), true );
    }

    
    private static function describe_status( Invocation $inv, string $value ): array {
        $lines  = array();
        $action = (int) $value ? 'mark as spam' : 'remove from spam';
        foreach ( $inv->args as $arg ) {
            $user    = self::peek_user( $arg );
            $lines[] = $user ? self::describe_user_status( $user, $value, $action ) : sprintf( "User '%s': not found → nothing to change", $arg );
        }
        return $lines;
    }

    




    private static function describe_user_status( object $user, string $value, string $action ): string {
        if ( self::is_network_admin( $user ) ) {
            return sprintf( 'User %s (%d): network administrator → skipped', $user->user_login, (int) $user->ID );
        }
        if ( isset( $user->spam ) && $value === $user->spam ) {
            return sprintf( 'User %s (%d): already %s → nothing to change', $user->user_login, (int) $user->ID, (int) $value ? 'spam' : 'not spam' );
        }
        $named = array();
        if ( \is_multisite() ) {
            foreach ( self::spam_sites( (int) $user->ID ) as $site ) {
                $named[] = self::site_label( $site );
            }
        }
        sort( $named, SORT_STRING );
        return sprintf(
            'User %s (%d): %s → %s; sites changed too: %s',
            $user->user_login,
            (int) $user->ID,
            (int) $value ? 'not spam' : 'spam',
            $action,
            $named ? implode( ', ', $named ) : 'none'
        );
    }

    






    private function require_target( Context $ctx, object $user ): void {
        $id = (int) $user->ID;
        if ( ! \current_user_can( 'edit_user', $id ) || ! \current_user_can( 'promote_user', $id ) ) {
            $ctx->out->error( sprintf( self::MSG_TARGET, $user->user_login, $id ) );
        }
    }

    









    private function guard_role_removal( Context $ctx, object $user, string $role, array $user_roles ): void {
        $refusal = self::role_refusal( $role );
        if ( null !== $refusal ) {
            $ctx->out->error( sprintf( self::MSG_ROLE_CAP, $role, $refusal ) );
        }
        if ( 'administrator' === $role ) {
            $admins = \get_users(
                array(
                    'role'   => 'administrator',
                    'fields' => 'ID',
                    'number' => 2,
                )
            );
            if ( count( (array) $admins ) <= 1 ) {
                $ctx->out->error( sprintf( self::MSG_LAST_ADMIN, $user->user_login, (int) $user->ID ) );
            }
        }
        if ( (int) $user->ID === $ctx->user_id() && ! ( \is_multisite() && \current_user_can( 'manage_sites' ) ) ) {
            foreach ( array_diff( $user_roles, array( $role ) ) as $kept ) {
                $object = \get_role( $kept );
                if ( is_object( $object ) && $object->has_cap( 'edit_users' ) ) {
                    return;
                }
            }
            $ctx->out->error( sprintf( self::MSG_OWN_GATE, $role, $ctx->user_id() ) );
        }
    }

    





    private static function grant_refusal( Context $ctx, string $cap ): ?string {
        if ( self::is_role( $cap ) ) {
            $refusal = self::role_refusal( $cap );
            return null === $refusal ? null : sprintf( self::MSG_ROLE_CAP, $cap, $refusal );
        }
        $refusal = Capability_Grants::refusal( $cap );
        if ( 'network' === $refusal ) {
            return sprintf( self::MSG_NETWORK_CAP, $cap );
        }
        if ( 'not_held' === $refusal ) {
            return sprintf( self::MSG_NOT_HELD, $ctx->user_id(), $cap );
        }
        return null;
    }

    




    private static function role_refusal( string $role ): ?string {
        if ( ! \current_user_can( 'promote_users' ) ) {
            return 'assigning a role needs the promote_users capability.';
        }
        if ( 'administrator' === $role && ! \current_user_can( 'manage_options' ) ) {
            return 'assigning administrator needs the manage_options capability.';
        }
        if ( ! function_exists( 'get_editable_roles' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/user.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if ( ! function_exists( 'get_editable_roles' ) ) {
            return 'the roles you may assign cannot be read (get_editable_roles() is not available).';
        }
        $editable = \get_editable_roles();
        if ( empty( $editable[ $role ] ) ) {
            return 'it is not one of the roles you may assign (get_editable_roles()).';
        }
        return null;
    }

    
    private static function is_role( string $name ): bool {
        $roles = function_exists( 'wp_roles' ) ? \wp_roles() : null;
        return is_object( $roles ) && method_exists( $roles, 'is_role' ) && (bool) $roles->is_role( $name );
    }

    




    private static function hold( object $user, string $cap ): string {
        $caps    = isset( $user->caps ) ? (array) $user->caps : array();
        $allcaps = isset( $user->allcaps ) ? (array) $user->allcaps : array();
        if ( array_key_exists( $cap, $caps ) ) {
            return true === $caps[ $cap ] ? 'granted directly' : ( false === $caps[ $cap ] ? 'denied directly' : 'set directly' );
        }
        return ! empty( $allcaps[ $cap ] ) ? 'inherited from a role' : 'not held';
    }

    




    private static function peek_user( string $arg ): ?object {
        $user = Fetchers::user( $arg, new Output() );
        return $user ? $user : null;
    }
}
