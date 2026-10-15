<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Adapters\Menu_Adapter;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























final class Menu_Handler extends Command_Handler {

    
    const LOCATION_FIELDS = array( 'location', 'description' );

    const NOT_RECORDED = "Menu locations are stored in the active theme's theme_mods option, which change history's option mode on this site does not record.";

    
    public function location_list( Invocation $inv, Context $ctx ): void {
        $locations = self::registered_locations();
        $objects   = array();
        foreach ( $locations as $location => $description ) {
            $objects[] = (object) array(
                'location'    => $location,
                'description' => $description,
            );
        }
        $assoc     = $inv->assoc;
        $formatter = $this->formatter( $ctx, $assoc, self::LOCATION_FIELDS );
        if ( 'ids' === $formatter->format ) {
            $ids = array();
            foreach ( $objects as $object ) {
                $ids[] = $object->location;
            }
            $formatter->display_items( $ids );
        } else {
            $formatter->display_items( $objects );
        }
    }

    
    public function location_assign( Invocation $inv, Context $ctx ): void {
        $menu_arg = (string) $inv->args[0];
        $location = (string) $inv->args[1];
        $refusal  = null;
        $menu     = Menu_Adapter::resolve_menu( $menu_arg, true, $refusal );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        if ( null === $menu ) {
            $ctx->out->error( "Invalid menu {$menu_arg}." );
        }
        if ( ! array_key_exists( $location, self::registered_locations() ) ) {
            $ctx->out->error( "Invalid location {$location}." );
        }
        $this->protect( $ctx );

        $locations = self::locations();
        $before    = array_key_exists( $location, $locations ) ? $locations[ $location ] : null;
        if ( $before !== $menu['term_id'] ) {
            $locations[ $location ] = $menu['term_id'];
            Menu_Adapter::wp( 'set_locations', array( $locations ), array( __CLASS__, 'live_set_locations' ) );
            $ctx->mark_changed();
            $this->history( $ctx );
        }
        $ctx->out->success( "Assigned location {$location} to menu {$menu_arg}." );
    }

    
    public function location_remove( Invocation $inv, Context $ctx ): void {
        $location = (string) $inv->args[1];
        $refusal  = null;
        $menu     = Menu_Adapter::resolve_menu( (string) $inv->args[0], true, $refusal );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        if ( null === $menu ) {
            $ctx->out->error( 'Invalid menu.' );
        }
        $locations = self::locations();
        
        if ( ( isset( $locations[ $location ] ) ? $locations[ $location ] : null ) !== $menu['term_id'] ) {
            $ctx->out->error( "Menu isn't assigned to location." );
        }
        $this->protect( $ctx );
        $locations[ $location ] = 0;
        Menu_Adapter::wp( 'set_locations', array( $locations ), array( __CLASS__, 'live_set_locations' ) );
        $ctx->mark_changed();
        $this->history( $ctx );
        $ctx->out->success( 'Removed location from menu.' );
    }

    





    public function describe_assign( Invocation $inv ): array {
        $menu_arg = (string) $inv->args[0];
        $location = (string) $inv->args[1];
        $refusal  = null;
        $menu     = Menu_Adapter::resolve_menu( $menu_arg, true, $refusal );
        if ( null === $menu ) {
            return array( sprintf( "Menu '%s' was not found: nothing would change.", $menu_arg ) );
        }
        if ( ! array_key_exists( $location, self::registered_locations() ) ) {
            return array( sprintf( "Location '%s' is not registered by the active theme: nothing would change.", $location ) );
        }
        $locations = self::locations();
        $before    = array_key_exists( $location, $locations ) ? $locations[ $location ] : null;
        return array( sprintf( "Menu location '%s': %s -> %s", $location, self::menu_label( $before ), self::menu_label( $menu['term_id'], $menu ) ) );
    }

    




    public function describe_remove( Invocation $inv ): array {
        $menu_arg = (string) $inv->args[0];
        $location = (string) $inv->args[1];
        $refusal  = null;
        $menu     = Menu_Adapter::resolve_menu( $menu_arg, true, $refusal );
        if ( null === $menu ) {
            return array( sprintf( "Menu '%s' was not found: nothing would change.", $menu_arg ) );
        }
        $locations = self::locations();
        $before    = isset( $locations[ $location ] ) ? $locations[ $location ] : null;
        if ( $before !== $menu['term_id'] ) {
            return array( sprintf( "Menu location '%s' is not held by menu %d: nothing would change.", $location, $menu['term_id'] ) );
        }
        return array( sprintf( "Menu location '%s': %s -> unassigned (0)", $location, self::menu_label( $before, $menu ) ) );
    }

    
    
    

    
    public static function live_registered_locations(): array {
        return (array) \get_registered_nav_menus();
    }

    
    public static function live_locations(): array {
        return (array) \get_nav_menu_locations();
    }

    




    public static function live_set_locations( array $locations ): bool {
        return (bool) \set_theme_mod( 'nav_menu_locations', $locations );
    }

    
    public static function live_theme_mods_option(): string {
        return 'theme_mods_' . (string) \get_option( 'stylesheet' );
    }

    
    public static function live_option_recordable( string $option ): ?bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Recorder' ) || ! method_exists( '\\Easy_MCP_AI\\History\\Change_Recorder', 'option_is_recordable' ) ) {
            return null;
        }
        return (bool) \Easy_MCP_AI\History\Change_Recorder::option_is_recordable( $option );
    }

    
    
    

    
    private static function registered_locations(): array {
        $locations = Menu_Adapter::wp( 'registered_locations', array(), array( __CLASS__, 'live_registered_locations' ) );
        return is_array( $locations ) ? $locations : array();
    }

    
    private static function locations(): array {
        $locations = Menu_Adapter::wp( 'locations', array(), array( __CLASS__, 'live_locations' ) );
        return is_array( $locations ) ? $locations : array();
    }

    




    private function protect( Context $ctx ): void {
        $option = (string) Menu_Adapter::wp( 'theme_mods_option', array(), array( __CLASS__, 'live_theme_mods_option' ) );
        $reason = Self_Protection::option( $option, true );
        if ( null !== $reason ) {
            $ctx->out->error( $reason );
        }
    }

    
    private function history( Context $ctx ): void {
        $option     = (string) Menu_Adapter::wp( 'theme_mods_option', array(), array( __CLASS__, 'live_theme_mods_option' ) );
        $recordable = Menu_Adapter::wp( 'option_recordable', array( $option ), array( __CLASS__, 'live_option_recordable' ) );
        if ( false === $recordable ) {
            $ctx->not_captured( self::NOT_RECORDED );
        }
    }

    







    private static function menu_label( $term_id, ?array $known = null ): string {
        if ( null === $term_id || 0 === $term_id || '0' === $term_id ) {
            return 'unassigned';
        }
        if ( null === $known && is_int( $term_id ) ) {
            $known = Menu_Adapter::resolve_menu( (string) $term_id, false );
        }
        if ( null !== $known && $known['term_id'] === $term_id ) {
            return sprintf( "menu %d ('%s')", $term_id, $known['name'] );
        }
        return sprintf( 'menu %s', is_scalar( $term_id ) ? (string) $term_id : (string) json_encode( $term_id ) );
    }
}
