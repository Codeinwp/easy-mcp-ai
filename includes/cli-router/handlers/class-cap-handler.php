<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































final class Cap_Handler extends Role_Handler {

    
    const CAP_FIELDS = array( 'name' );

    
    const MSG_NONE_SET = "None of the capabilities named is set on the '%s' role, so nothing was removed.";

    
    const NOTE_NOT_SET = "Not set on the '%s' role, so not removed: %s.";

    
    
    

    
    public function list_caps( Invocation $inv, Context $ctx ): void {
        $role_obj = self::get_role_or_error( $ctx, (string) $inv->args[0] );

        $show_grant = ! empty( $inv->assoc['show-grant'] );
        $fields     = self::CAP_FIELDS;

        if ( $show_grant ) {
            $fields[]     = 'grant';
            $capabilities = (array) $role_obj->capabilities;
        } else {
            $capabilities = array_filter( (array) $role_obj->capabilities );
        }

        $output_caps = array();
        foreach ( $capabilities as $cap => $grant ) {
            $output_cap = new \stdClass();

            $output_cap->name  = $cap;
            $output_cap->grant = $grant ? 'true' : 'false';

            $output_caps[] = $output_cap;
        }

        if ( 'list' === $inv->get( 'format' ) ) {
            foreach ( $output_caps as $cap ) {
                if ( $show_grant ) {
                    $ctx->out->line( implode( ',', array( $cap->name, $cap->grant ) ) );
                } else {
                    $ctx->out->line( (string) $cap->name );
                }
            }
        } else {
            $assoc = $inv->assoc;
            $this->formatter( $ctx, $assoc, $fields )->display_items( $output_caps );
        }
    }

    
    
    

    
    public function preflight_add( Invocation $inv, Context $ctx ): void {
        $ctx->require_cap( 'manage_options' );
        $args  = array_values( $inv->args );
        $role  = (string) array_shift( $args );
        $grant = self::grant_of( $inv->assoc );
        foreach ( array_unique( $args ) as $cap ) {
            $cap       = (string) $cap;
            $protected = Self_Protection::capability( $cap );
            if ( null !== $protected ) {
                $ctx->out->error( $protected );
            }
            if ( $grant ) {
                $refusal = self::grant_refusal( $cap, $ctx );
                if ( null !== $refusal ) {
                    $ctx->out->error( $refusal );
                }
            } elseif ( 'administrator' === $role && in_array( $cap, self::administrator_core_caps(), true ) ) {
                $ctx->out->error( sprintf( self::MSG_ADMIN_CORE_CAP, $cap ) );
            }
        }
    }

    
    public function add( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        self::persistence_check( $ctx );

        $args = array_values( $inv->args );
        $role = (string) array_shift( $args );

        $role_obj = self::get_role_or_error( $ctx, $role );

        $grant = self::grant_of( $inv->assoc );

        if ( false === $grant ) {
            $after = (array) $role_obj->capabilities;
            foreach ( $args as $cap ) {
                $after[ $cap ] = false;
            }
            self::refuse_gate_loss( $ctx, array( $role => $after ) );
        }

        $count = 0;
        self::batched(
            self::roles(),
            static function () use ( $args, $grant, $role_obj, &$count ): bool {
                $count = self::add_caps( $role_obj, $args, $grant );
                return $count > 0;
            }
        );
        if ( $count > 0 ) {
            $ctx->mark_changed();
        }

        $capability          = self::pluralize_capability( $count );
        $grant_qualification = $grant ? '' : ' as false';

        $ctx->out->success( "Added {$count} {$capability} to '{$role}' role{$grant_qualification}." );
    }

    
    public function describe_add( Invocation $inv ): array {
        $args     = array_values( $inv->args );
        $role     = (string) array_shift( $args );
        $role_obj = self::roles()->get_role( $role );
        if ( ! $role_obj ) {
            return array( sprintf( "Role '%s': does not exist → nothing changes (the command fails: '%s' role not found.)", $role, $role ) );
        }
        $grant = self::grant_of( $inv->assoc );
        $lines = array();
        foreach ( array_unique( $args ) as $cap ) {
            $state = self::cap_state( $role_obj, (string) $cap );
            if ( $grant ) {
                $effect = $role_obj->has_cap( $cap ) ? 'unchanged' : 'granted';
            } else {
                $effect = 'denied (false)' === $state ? 'unchanged' : 'denied (false)';
            }
            $lines[] = sprintf( "Role '%s', capability '%s': %s → %s", $role, (string) $cap, $state, $effect );
        }
        return $lines;
    }

    
    
    

    
    public function preflight_remove( Invocation $inv, Context $ctx ): void {
        $ctx->require_cap( 'manage_options' );
        $args = array_values( $inv->args );
        $role = (string) array_shift( $args );
        foreach ( array_unique( $args ) as $cap ) {
            $cap       = (string) $cap;
            $protected = Self_Protection::capability( $cap );
            if ( null !== $protected ) {
                $ctx->out->error( $protected );
            }
            if ( 'administrator' === $role && in_array( $cap, self::administrator_core_caps(), true ) ) {
                $ctx->out->error( sprintf( self::MSG_ADMIN_CORE_CAP, $cap ) );
            }
        }
    }

    
    public function remove( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        self::persistence_check( $ctx );

        $args = array_values( $inv->args );
        $role = (string) array_shift( $args );

        $role_obj = self::get_role_or_error( $ctx, $role );

        $after = (array) $role_obj->capabilities;
        foreach ( $args as $cap ) {
            if ( isset( $role_obj->capabilities[ $cap ] ) && ! $role_obj->capabilities[ $cap ] && ! self::caller_holds( (string) $cap ) ) {
                $ctx->out->error( sprintf( self::MSG_NOT_HELD_DENY, $cap, $role, $ctx->user_id() ) );
            }
            unset( $after[ $cap ] );
        }
        self::refuse_gate_loss( $ctx, array( $role => $after ) );

        $count   = 0;
        $not_set = array();
        self::batched(
            self::roles(),
            static function () use ( $args, $role_obj, &$count, &$not_set ): bool {
                foreach ( $args as $cap ) {
                    if ( ! isset( $role_obj->capabilities[ $cap ] ) ) {
                        $not_set[] = (string) $cap;
                        continue;
                    }

                    $role_obj->remove_cap( $cap );

                    ++$count;
                }
                return $count > 0;
            }
        );
        if ( $count > 0 ) {
            $ctx->mark_changed();
            if ( $not_set ) {
                $ctx->note( sprintf( self::NOTE_NOT_SET, $role, "'" . implode( "', '", array_values( array_unique( $not_set ) ) ) . "'" ) );
            }
        } else {
            $ctx->fail_missing_target( sprintf( self::MSG_NONE_SET, $role ) );
        }

        $capability = self::pluralize_capability( $count );

        $ctx->out->success( "Removed {$count} {$capability} from '{$role}' role." );
    }

    
    public function describe_remove( Invocation $inv ): array {
        $args     = array_values( $inv->args );
        $role     = (string) array_shift( $args );
        $role_obj = self::roles()->get_role( $role );
        if ( ! $role_obj ) {
            return array( sprintf( "Role '%s': does not exist → nothing changes (the command fails: '%s' role not found.)", $role, $role ) );
        }
        $lines = array();
        foreach ( array_unique( $args ) as $cap ) {
            $state   = self::cap_state( $role_obj, (string) $cap );
            $lines[] = sprintf( "Role '%s', capability '%s': %s → %s", $role, (string) $cap, $state, 'not set' === $state ? 'unchanged' : 'removed' );
        }
        return $lines;
    }

    
    
    

    





    private static function get_role_or_error( Context $ctx, string $role ): object {
        $role_obj = self::roles()->get_role( $role );

        if ( ! $role_obj ) {
            $ctx->out->error( "'{$role}' role not found." );
        }

        return $role_obj;
    }

    






    private static function grant_of( array $assoc ): bool {
        $grant = true;
        if ( isset( $assoc['grant'] ) ) {
            $grant = ! $assoc['grant'] || 'false' === $assoc['grant'] ? false : true;
        }
        return $grant;
    }

    



    private static function pluralize_capability( int $count ): string {
        return 1 === $count ? 'capability' : 'capabilities';
    }

    







    private static function add_caps( object $role_obj, array $args, bool $grant ): int {
        $count = 0;
        foreach ( $args as $cap ) {
            if ( true === $grant && $role_obj->has_cap( $cap ) ) {
                continue;
            }

            if ( false === $grant && isset( $role_obj->capabilities[ $cap ] ) && false === $role_obj->capabilities[ $cap ] ) {
                continue;
            }

            $role_obj->add_cap( $cap, $grant );

            ++$count;
        }
        return $count;
    }

    




    private static function cap_state( object $role_obj, string $cap ): string {
        $caps = (array) $role_obj->capabilities;
        if ( ! array_key_exists( $cap, $caps ) ) {
            return 'not set';
        }
        return $caps[ $cap ] ? 'granted' : 'denied (false)';
    }
}
