<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Capability_Grants;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















































class Role_Handler extends Command_Handler {

    
    const DEFAULT_ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

    
    const FIELDS = array( 'name', 'role' );

    
    const GATE_CAPS = array( 'manage_options', 'promote_users' );

    
    const NETWORK_CAPS = Capability_Grants::NETWORK_CAPS;

    
    const NOT_CAPTURED = 'Role and capability changes are recorded only as an update of the roles option ({prefix}user_roles), and only while change history records every option (not in allowlist mode); wp_history_apply cannot restore role definitions.';

    





    const MSG_NOT_HELD       = "wp_cli_run grants a role only capabilities you have yourself, and user %d does not have '%s'.";
    const MSG_NOT_HELD_DENY  = "wp_cli_run lifts a denial only for capabilities you have: removing '%s' from '%s' lets its holders gain it, and user %d does not have it.";
    const MSG_DELETE_DENY    = "wp_cli_run lifts a denial only for capabilities you have: deleting '%s' lifts its denial of '%s' from its holders, and user %d does not have it.";
    const MSG_NETWORK_CAP    = "'%s' is a network capability (it reaches Network Admin), and wp_cli_run lets only a super admin grant it.";
    const MSG_ADMIN_CORE_CAP = "wp_cli_run never removes or denies '%s' on the administrator role: WordPress gives it to administrators, and taking it from all of them can lock them out.";
    const MSG_ADMINISTRATOR  = 'wp_cli_run never deletes the administrator role: every administrator would lose every capability at once.';
    const MSG_DEFAULT_ROLE   = "wp_cli_run does not delete '%s': it is the role new accounts receive (default_role). Choose another default role under Settings › General first.";
    const MSG_OWN_GATE       = 'wp_cli_run refuses this: it would take %s from your own user (user %d), which these commands need. Make the change as another administrator.';
    const MSG_CLONE_SKIPPED  = "Skipped '%s' while cloning '%s': it belongs to the plugin that serves this MCP connection, and wp_cli_run never grants that plugin's capabilities.";
    const MSG_RESET_KEPT     = "Kept '%s' on the '%s' role: it belongs to the plugin that serves this MCP connection, and WordPress's defaults do not include it.";
    const MSG_CLONE_REFUSED  = "Cloning '%s' refused: %s%s";
    const MSG_RESET_REFUSED  = "Resetting '%s' refused: %s%s";

    
    
    

    
    public function list_roles( Invocation $inv, Context $ctx ): void {
        $output_roles = array();
        foreach ( (array) self::roles()->roles as $key => $role ) {
            $output_role = new \stdClass();

            $output_role->name = $role['name'];
            $output_role->role = $key;

            $output_roles[] = $output_role;
        }

        $assoc = $inv->assoc;
        $this->formatter( $ctx, $assoc, self::FIELDS )->display_items( $output_roles );
    }

    





    public function exists( Invocation $inv, Context $ctx ): void {
        $key = $inv->args[0];
        if ( ! in_array( $key, array_keys( (array) self::roles()->roles ), true ) ) {
            $ctx->out->err( "Error: Role with ID '{$key}' does not exist.\n" );
            $ctx->out->halt( 1 );
        }

        $ctx->out->success( "Role with ID '{$key}' exists." );
    }

    
    
    

    
    public function create( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        $roles = self::roles();
        self::persistence_check( $ctx );

        $role_key  = isset( $inv->args[0] ) ? $inv->args[0] : null;
        $role_name = isset( $inv->args[1] ) ? $inv->args[1] : null;

        if ( empty( $role_key ) || empty( $role_name ) ) {
            $ctx->out->error( "Can't create role, insufficient information provided." );
        }

        $clone        = $inv->get( 'clone' );
        $capabilities = self::clone_source_caps( $roles, $clone, $ctx );

        
        
        if ( isset( $roles->roles[ $role_key ] ) ) {
            $ctx->out->error( "Role couldn't be created." );
        }

        $caps = null !== $capabilities ? self::cloned_caps( $capabilities, (string) $clone, $ctx ) : array();

        $role = self::write_role( $roles, (string) $role_key, (string) $role_name, $caps );
        if ( ! $role ) {
            $ctx->out->error( "Role couldn't be created." );
        }
        $ctx->mark_changed();
        if ( ! empty( $caps ) ) {
            $ctx->out->success( "Role with key '{$role_key}' created. Cloned capabilities from '{$clone}'." );
        } else {
            $ctx->out->success( "Role with key '{$role_key}' created." );
        }
    }

    







    private static function clone_source_caps( object $roles, $clone, Context $ctx ): ?array {
        if ( empty( $clone ) ) {
            return null;
        }
        $role_obj = $roles->get_role( $clone );
        if ( ! $role_obj ) {
            $ctx->out->error( "'{$clone}' role not found." );
        }
        return (array) $role_obj->capabilities;
    }

    









    private static function cloned_caps( array $capabilities, string $clone, Context $ctx ): array {
        $caps     = array();
        $refusals = array();
        foreach ( $capabilities as $cap => $grant ) {
            $cap = (string) $cap;
            if ( self::is_plugin_capability( $cap ) ) {
                $ctx->out->warning( sprintf( self::MSG_CLONE_SKIPPED, $cap, $clone ) );
                continue;
            }
            if ( $grant ) {
                $refusal = self::grant_refusal( $cap, $ctx );
                if ( null !== $refusal ) {
                    $refusals[] = $refusal;
                }
            }
            $caps[ $cap ] = (bool) $grant;
        }
        if ( $refusals ) {
            $more = count( $refusals ) > 1 ? sprintf( ' (%d more refused)', count( $refusals ) - 1 ) : '';
            $ctx->out->error( sprintf( self::MSG_CLONE_REFUSED, $clone, $refusals[0], $more ) );
        }
        return $caps;
    }

    





    public function describe_create( Invocation $inv ): array {
        $roles = self::roles();
        $key   = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        $name  = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        if ( empty( $key ) || empty( $name ) ) {
            return array( sprintf( "Role '%s': nothing changes (the command fails: Can't create role, insufficient information provided.)", $key ) );
        }
        if ( isset( $roles->roles[ $key ] ) ) {
            return array( sprintf( "Role '%s': exists → unchanged (the command fails: Role couldn't be created.)", $key ) );
        }
        $clone = $inv->get( 'clone' );
        if ( empty( $clone ) ) {
            return array( sprintf( "Role '%s': does not exist → created as '%s' with no capabilities", $key, $name ) );
        }
        $source = $roles->get_role( $clone );
        if ( ! $source ) {
            return array( sprintf( "Role '%s': does not exist → not created (the clone source '%s' does not exist)", $key, (string) $clone ) );
        }
        list( $granted, $denied ) = self::count_grants( (array) $source->capabilities );
        return array( sprintf( "Role '%s': does not exist → created as '%s' with the capabilities of '%s' (%d granted, %d denied)", $key, $name, (string) $clone, $granted, $denied ) );
    }

    






    private static function count_grants( array $capabilities ): array {
        $granted = 0;
        $denied  = 0;
        foreach ( $capabilities as $cap => $grant ) {
            if ( self::is_plugin_capability( (string) $cap ) ) {
                continue;
            }
            $grant ? ++$granted : ++$denied;
        }
        return array( $granted, $denied );
    }

    
    
    

    




    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        $ctx->require_cap( 'manage_options' );
        if ( isset( $inv->args[0] ) && 'administrator' === $inv->args[0] ) {
            $ctx->out->error( self::MSG_ADMINISTRATOR );
        }
        if ( isset( $inv->args[0] ) && '' !== (string) $inv->args[0] && function_exists( 'get_option' ) && (string) \get_option( 'default_role' ) === (string) $inv->args[0] ) {
            $ctx->out->error( sprintf( self::MSG_DEFAULT_ROLE, $inv->args[0] ) );
        }
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        $roles = self::roles();
        self::persistence_check( $ctx );

        $role_key = isset( $inv->args[0] ) ? $inv->args[0] : null;

        if ( empty( $role_key ) || ! isset( $roles->roles[ $role_key ] ) ) {
            $ctx->out->error( 'Role key not provided, or is invalid.' );
        }

        if ( function_exists( 'get_option' ) && (string) \get_option( 'default_role' ) === (string) $role_key ) {
            $ctx->out->error( sprintf( self::MSG_DEFAULT_ROLE, $role_key ) );
        }
        
        
        
        foreach ( (array) $roles->roles[ $role_key ]['capabilities'] as $cap => $grant ) {
            if ( ! $grant && ! self::caller_holds( (string) $cap ) ) {
                $ctx->out->error( sprintf( self::MSG_DELETE_DENY, $role_key, $cap, $ctx->user_id() ) );
            }
        }
        self::refuse_gate_loss( $ctx, array( (string) $role_key => null ) );

        $roles->remove_role( $role_key );

        
        
        if ( ! isset( $roles->roles[ $role_key ] ) ) {
            $ctx->mark_changed();
            $ctx->out->success( "Role with key '{$role_key}' deleted." );
        } else {
            $ctx->out->error( "Role with key '{$role_key}' could not be deleted." );
        }
    }

    
    public function describe_delete( Invocation $inv ): array {
        $roles = self::roles();
        $key   = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        if ( '' === $key || ! isset( $roles->roles[ $key ] ) ) {
            return array( sprintf( "Role '%s': does not exist → nothing changes (the command fails: Role key not provided, or is invalid.)", $key ) );
        }
        $role = $roles->roles[ $key ];
        return array( sprintf( "Role '%s' ('%s', %d capabilities): exists → deleted; users who hold it keep the role name in their record and lose its capabilities", $key, (string) $role['name'], count( (array) $role['capabilities'] ) ) );
    }

    
    
    

    





    public function reset( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        $roles = self::roles();
        self::persistence_check( $ctx );

        $all  = (bool) Read_Value::flag( $inv->assoc, 'all' );
        $args = array_values( $inv->args );
        if ( ! $all && empty( $args ) ) {
            $ctx->out->error( 'Role key not provided, or is invalid.' );
        }

        $targets = self::reset_targets( $args, $all, array_keys( (array) $roles->roles ), $ctx );

        $plan = self::reset_plan( $roles, $targets, $ctx );
        self::refuse_reset_grants( $roles, $plan['changes'], $ctx );
        self::refuse_gate_loss( $ctx, $plan['changes'] );
        foreach ( $plan['kept'] as $role_key => $caps ) {
            foreach ( $caps as $cap ) {
                $ctx->out->warning( sprintf( self::MSG_RESET_KEPT, $cap, $role_key ) );
            }
        }

        if ( $plan['num_reset'] ) {
            self::write_roles( $roles, $plan['final'] );
            $ctx->mark_changed();
        }

        self::report_reset( $targets, $plan, $ctx );
    }

    










    private static function reset_targets( array $args, bool $all, array $all_roles, Context $ctx ): array {
        if ( $all ) {
            $args               = array_merge( $args, self::DEFAULT_ROLES );
            $not_affected_roles = array_diff( $all_roles, self::DEFAULT_ROLES );
        } else {
            $not_affected_roles = array_diff( $args, self::DEFAULT_ROLES );
        }
        foreach ( $not_affected_roles as $not_affected_role ) {
            $ctx->out->log( "Custom role '{$not_affected_role}' not affected." );
        }

        $targets = self::default_role_targets( $args );
        
        if ( ! $all && ! $targets ) {
            $ctx->out->error( 'Must specify a default role to reset.' );
        }
        return $targets;
    }

    





    private static function default_role_targets( array $args ): array {
        $targets = array();
        foreach ( $args as $role_key ) {
            if ( in_array( $role_key, self::DEFAULT_ROLES, true ) ) {
                $targets[] = $role_key;
            }
        }
        return array_values( array_unique( $targets ) );
    }

    






    private static function report_reset( array $targets, array $plan, Context $ctx ): void {
        foreach ( $targets as $role_key ) {
            if ( isset( $plan['report'][ $role_key ] ) ) {
                list( $restored_cap_count, $removed_cap_count ) = $plan['report'][ $role_key ];
                $restored_text = ( 1 === $restored_cap_count ) ? '%d capability' : '%d capabilities';
                $removed_text  = ( 1 === $removed_cap_count ) ? '%d capability' : '%d capabilities';
                $message       = "Restored {$restored_text} to and removed {$removed_text} from '%s' role.";
                $ctx->out->log( sprintf( $message, $restored_cap_count, $removed_cap_count, $role_key ) );
            } else {
                $ctx->out->log( "No changes necessary for '{$role_key}' role." );
            }
        }
        $num_reset    = $plan['num_reset'];
        $num_to_reset = count( $targets );
        if ( $num_reset ) {
            if ( 1 === $num_to_reset ) {
                $ctx->out->success( 'Role reset.' );
            } else {
                $ctx->out->success( "{$num_reset} of {$num_to_reset} roles reset." );
            }
        } elseif ( 1 === $num_to_reset ) {
            $ctx->out->success( 'Role didn\'t need resetting.' );
        } else {
            $ctx->out->success( 'No roles needed resetting.' );
        }
    }

    
    public function describe_reset( Invocation $inv ): array {
        $roles = self::roles();
        $args  = array_values( $inv->args );
        if ( Read_Value::flag( $inv->assoc, 'all' ) ) {
            $args = array_merge( $args, self::DEFAULT_ROLES );
        }
        $lines   = array();
        $targets = self::default_role_targets( $args );
        $plan    = self::reset_plan( $roles, $targets, null );
        foreach ( $targets as $role_key ) {
            $count = isset( $roles->roles[ $role_key ] ) ? count( (array) $roles->roles[ $role_key ]['capabilities'] ) : 0;
            $state = isset( $roles->roles[ $role_key ] ) ? sprintf( '%d capabilities', $count ) : 'does not exist';
            if ( isset( $plan['report'][ $role_key ] ) ) {
                $lines[] = sprintf( "Role '%s' (%s) → WordPress's default capabilities: restores %d, removes %d", $role_key, $state, $plan['report'][ $role_key ][0], $plan['report'][ $role_key ][1] );
            } else {
                $lines[] = sprintf( "Role '%s' (%s) → unchanged: it already has WordPress's default capabilities", $role_key, $state );
            }
            foreach ( $plan['kept'][ $role_key ] ?? array() as $cap ) {
                $lines[] = sprintf( "Role '%s': keeps '%s' (the plugin that serves this MCP connection)", $role_key, $cap );
            }
        }
        if ( ! $targets ) {
            $lines[] = 'No default role is named → nothing changes (the command fails: Must specify a default role to reset.)';
        }
        return $lines;
    }

    











    private static function reset_plan( object $roles, array $targets, ?Context $ctx ): array {
        $defaults = self::default_roles();
        $final    = (array) $roles->roles;
        $changes  = array();
        $report   = array();
        $kept     = array();
        foreach ( $targets as $role_key ) {
            if ( ! isset( $defaults[ $role_key ] ) ) {
                if ( null !== $ctx ) {
                    $ctx->out->error( "wp_cli_run cannot read WordPress's default definition of the '{$role_key}' role (populate_roles() did not build it), so it resets nothing." );
                }
                continue;
            }
            $before                     = isset( $roles->roles[ $role_key ] ) ? $roles->roles[ $role_key ] : null;
            list( $after, $plugin_caps ) = self::default_with_plugin_caps( $defaults[ $role_key ], $before );
            if ( $plugin_caps ) {
                $kept[ $role_key ] = $plugin_caps;
            }
            if ( self::same_definition( $before, $after ) ) {
                continue;
            }
            $before_capabilities  = null !== $before ? (array) $before['capabilities'] : array();
            $restored_cap         = array_diff_key( $after['capabilities'], $before_capabilities );
            $removed_cap          = array_diff_key( $before_capabilities, $after['capabilities'] );
            $report[ $role_key ]  = array( count( $restored_cap ), count( $removed_cap ) );
            $final[ $role_key ]   = $after;
            $changes[ $role_key ] = $after['capabilities'];
        }
        return array(
            'final'     => $final,
            'changes'   => $changes,
            'report'    => $report,
            'kept'      => $kept,
            'num_reset' => count( $report ),
        );
    }

    







    private static function default_with_plugin_caps( array $default, ?array $before ): array {
        $after = $default;
        $kept  = array();
        foreach ( null !== $before ? (array) $before['capabilities'] : array() as $cap => $grant ) {
            if ( self::is_plugin_capability( (string) $cap ) ) {
                $after['capabilities'][ $cap ] = $grant;
                $kept[]                        = (string) $cap;
            }
        }
        return array( $after, $kept );
    }

    








    private static function same_definition( ?array $before, array $after ): bool {
        // phpcs:ignore Universal.Operators.StrictComparisons -- loose, as WP-CLI's `!=`.
        return null !== $before && $before['name'] == $after['name'] && (array) $before['capabilities'] == $after['capabilities'];
    }

    










    private static function refuse_reset_grants( object $roles, array $changes, Context $ctx ): void {
        foreach ( $changes as $role_key => $after ) {
            $before   = isset( $roles->roles[ $role_key ] ) ? (array) $roles->roles[ $role_key ]['capabilities'] : array();
            $refusals = array();
            foreach ( $after as $cap => $grant ) {
                if ( ! $grant || ! empty( $before[ $cap ] ) ) {
                    continue;
                }
                $refusal = self::grant_refusal( (string) $cap, $ctx );
                if ( null !== $refusal ) {
                    $refusals[] = $refusal;
                }
            }
            if ( $refusals ) {
                $more = count( $refusals ) > 1 ? sprintf( ' (%d more refused)', count( $refusals ) - 1 ) : '';
                $ctx->out->error( sprintf( self::MSG_RESET_REFUSED, $role_key, $refusals[0], $more ) );
            }
        }
    }

    
    
    

    
    public function preflight_write( Invocation $inv, Context $ctx ): void {
        $ctx->require_cap( 'manage_options' );
    }

    





    protected static function roles(): object {
        if ( isset( $GLOBALS['wp_roles'] ) && is_object( $GLOBALS['wp_roles'] ) ) {
            return $GLOBALS['wp_roles'];
        }
        return \wp_roles();
    }

    




    protected static function persistence_check( Context $ctx ): void {
        if ( ! self::roles()->use_db ) {
            $ctx->out->error( 'Role definitions are not persistent.' );
        }
    }

    
    protected static function is_plugin_capability( string $cap ): bool {
        return 1 === preg_match( Self_Protection::NAMESPACE_PATTERN, strtolower( trim( $cap ) ) );
    }

    






    protected static function grant_refusal( string $cap, Context $ctx ): ?string {
        $protected = Self_Protection::capability( $cap );
        if ( null !== $protected ) {
            return $protected;
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

    
    protected static function caller_holds( string $cap ): bool {
        return Capability_Grants::holds( $cap );
    }

    








    protected static function default_roles(): array {
        static $cache = array();
        $version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
        if ( isset( $cache[ $version ] ) ) {
            return $cache[ $version ];
        }
        if ( ! function_exists( 'populate_roles' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/schema.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/schema.php';
        }
        $live               = self::roles();
        $copy               = clone $live;
        $copy->use_db       = false;
        $copy->roles        = array();
        $copy->role_objects = array();
        $copy->role_names   = array();
        $had                = array_key_exists( 'wp_roles', $GLOBALS );
        $previous           = $had ? $GLOBALS['wp_roles'] : null;
        $GLOBALS['wp_roles'] = $copy;
        try {
            \populate_roles();
        } finally {
            if ( $had ) {
                $GLOBALS['wp_roles'] = $previous;
            } else {
                unset( $GLOBALS['wp_roles'] );
            }
        }
        $defaults = array();
        foreach ( (array) $copy->roles as $key => $role ) {
            $defaults[ $key ] = array(
                'name'         => (string) $role['name'],
                'capabilities' => (array) $role['capabilities'],
            );
        }
        $cache[ $version ] = $defaults;
        return $defaults;
    }

    




    protected static function administrator_core_caps(): array {
        $defaults = self::default_roles();
        $caps     = array();
        foreach ( isset( $defaults['administrator'] ) ? $defaults['administrator']['capabilities'] : array() as $cap => $grant ) {
            if ( $grant ) {
                $caps[] = (string) $cap;
            }
        }
        return $caps;
    }

    










    protected static function gate_caps_lost( array $changes ): array {
        if ( \is_multisite() && \is_super_admin() ) {
            return array();
        }
        $user      = function_exists( 'get_userdata' ) ? \get_userdata( (int) \get_current_user_id() ) : false;
        $user_caps = ( is_object( $user ) && isset( $user->caps ) && is_array( $user->caps ) ) ? $user->caps : array();
        $roles     = (array) self::roles()->roles;
        $before    = self::role_derived_caps( $user_caps, $roles, array() );
        $after     = self::role_derived_caps( $user_caps, $roles, $changes );
        $lost      = array();
        foreach ( self::GATE_CAPS as $gate ) {
            if ( ! empty( $before[ $gate ] ) && empty( $after[ $gate ] ) ) {
                $lost[] = $gate;
            }
        }
        return $lost;
    }

    



    protected static function refuse_gate_loss( Context $ctx, array $changes ): void {
        $lost = self::gate_caps_lost( $changes );
        if ( $lost ) {
            $ctx->out->error( sprintf( self::MSG_OWN_GATE, implode( ' and ', $lost ), $ctx->user_id() ) );
        }
    }

    







    private static function role_derived_caps( array $user_caps, array $roles, array $changes ): array {
        $allcaps = array();
        foreach ( array_keys( $user_caps ) as $key ) {
            if ( array_key_exists( $key, $changes ) ) {
                if ( null !== $changes[ $key ] ) {
                    $allcaps = array_merge( $allcaps, (array) $changes[ $key ] );
                }
            } elseif ( isset( $roles[ $key ] ) ) {
                $allcaps = array_merge( $allcaps, (array) $roles[ $key ]['capabilities'] );
            }
        }
        return array_merge( $allcaps, $user_caps );
    }

    








    protected static function write_role( object $roles, string $key, string $name, array $caps ): ?object {
        $use_db        = $roles->use_db;
        $roles->use_db = false;
        try {
            $role = $roles->add_role( $key, $name );
            if ( $role ) {
                foreach ( $caps as $cap => $grant ) {
                    $role->add_cap( $cap, $grant );
                }
            }
        } finally {
            $roles->use_db = $use_db;
        }
        if ( $role && $use_db ) {
            \update_option( $roles->role_key, $roles->roles, true );
        }
        return $role;
    }

    







    protected static function write_roles( object $roles, array $final ): void {
        \update_option( $roles->role_key, $final, true );
        $roles->roles        = $final;
        $roles->role_objects = array();
        $roles->role_names   = array();
        foreach ( $final as $key => $role ) {
            $roles->role_objects[ $key ] = new \WP_Role( $key, $role['capabilities'] );
            $roles->role_names[ $key ]   = $role['name'];
        }
    }

    






    protected static function batched( object $roles, callable $fn ): bool {
        $use_db        = $roles->use_db;
        $roles->use_db = false;
        try {
            $changed = (bool) $fn();
        } finally {
            $roles->use_db = $use_db;
        }
        if ( $changed && $use_db ) {
            \update_option( $roles->role_key, $roles->roles );
        }
        return $changed;
    }
}
