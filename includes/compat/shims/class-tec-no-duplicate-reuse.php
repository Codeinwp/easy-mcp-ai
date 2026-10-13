<?php







namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






























final class Tec_No_Duplicate_Reuse extends Compat_Shim {

    const ID = 'tec-no-duplicate-reuse';

    



    const TOOLS = array(
        'wp_tec_create_venue'     => array( 'venue', 'tribe_events_rest_venue_insert_avoid_duplicates', 'Venue' ),
        'wp_tec_create_organizer' => array( 'organizer', 'tribe_events_rest_organizer_insert_avoid_duplicates', 'Organizer' ),
    );

    


    public function id() {
        return self::ID;
    }

    


    public function applies_to( $tool ) {
        return is_object( $tool ) && method_exists( $tool, 'get_name' ) && isset( self::TOOLS[ $tool->get_name() ] );
    }

    


    public function is_required() {
        return true;
    }

    


    public function is_needed() {
        return class_exists( 'Tribe__Events__Main' );
    }

    




    public function enter( array $arguments = array(), $tool = null ) {
        if ( ! $this->applies_to( $tool ) ) {
            return null;
        }
        list( $argument, $hook, $title_key ) = self::TOOLS[ $tool->get_name() ];

        $arming       = new \stdClass();
        $arming->hook = $hook;
        $arming->used = false;
        $name           = isset( $arguments[ $argument ] ) && is_scalar( $arguments[ $argument ] ) ? self::comparable( (string) $arguments[ $argument ] ) : '';
        $arming->filter = static function ( $avoid, $postarr = null ) use ( $arming, $name, $title_key ) {
            if ( $arming->used ) {
                return $avoid;
            }
            $theirs = is_array( $postarr ) && isset( $postarr[ $title_key ] ) && is_scalar( $postarr[ $title_key ] ) ? self::comparable( (string) $postarr[ $title_key ] ) : null;
            if ( null !== $theirs && '' !== $name && $theirs !== $name ) {
                return $avoid;
            }
            $arming->used = true;
            return false;
        };
        add_filter( $hook, $arming->filter, PHP_INT_MAX, 2 );
        return $arming;
    }

    






    private static function comparable( string $name ): string {
        return sanitize_text_field( wp_specialchars_decode( wp_unslash( $name ), ENT_QUOTES ) );
    }

    


    public function leave( $state = null ): void {
        if ( ! is_object( $state ) || ! isset( $state->filter, $state->hook ) ) {
            return;
        }
        remove_filter( $state->hook, $state->filter, PHP_INT_MAX );
    }
}
