<?php







namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

































final class Tec_Event_Update_Fill extends Compat_Shim {

    const ID   = 'tec-event-update-fill';
    const TOOL = 'wp_tec_update_event';
    const HOOK = 'tribe_events_rest_event_prepare_postarr';

    
    private $stack = array();

    


    public function id() {
        return self::ID;
    }

    


    public function applies_to( $tool ) {
        return is_object( $tool )
            && method_exists( $tool, 'get_name' )
            && self::TOOL === $tool->get_name()
            && method_exists( $tool, 'sent_fields' );
    }

    


    public function is_required() {
        return true;
    }

    


    public function is_needed() {
        return class_exists( 'Tribe__Events__Main' );
    }

    


    public function enter( array $arguments = array(), $tool = null ) {
        $arming           = new \stdClass();
        $arming->event_id = isset( $arguments['id'] ) && is_scalar( $arguments['id'] ) ? absint( $arguments['id'] ) : 0;
        $arming->sent     = $this->applies_to( $tool ) ? array_values( (array) $tool->sent_fields( $arguments ) ) : array();
        $arming->used     = false;
        $stack            = &$this->stack;
        $arming->filter   = static function ( $postarr, $request = null ) use ( $arming, &$stack ) {
            if ( $arming->used || end( $stack ) !== $arming || ! self::is_for( $request, $arming->event_id ) ) {
                return $postarr;
            }
            $arming->used = true;
            return self::fill( $postarr, $request, $arming->event_id, $arming->sent );
        };
        add_filter( self::HOOK, $arming->filter, 10, 2 );
        $this->stack[] = $arming;
        return $arming;
    }

    


    public function leave( $state = null ): void {
        if ( ! is_object( $state ) || ! isset( $state->filter ) ) {
            return;
        }
        remove_filter( self::HOOK, $state->filter, 10 );
        foreach ( $this->stack as $index => $arming ) {
            if ( $arming === $state ) {
                unset( $this->stack[ $index ] );
            }
        }
        $this->stack = array_values( $this->stack );
    }

    






    private static function is_for( $request, int $event_id ): bool {
        return $event_id > 0
            && is_object( $request )
            && method_exists( $request, 'get_param' )
            && absint( $request->get_param( 'id' ) ) === $event_id;
    }

    











    public static function fill( $postarr, $request, int $event_id, array $sent ) {
        if ( ! is_array( $postarr ) || ! self::is_for( $request, $event_id ) ) {
            return $postarr;
        }
        $post = get_post( $event_id );
        if ( ! $post || 'tribe_events' !== $post->post_type ) {
            return $postarr;
        }
        $sent    = array_fill_keys( $sent, true );
        $postarr = self::drop_unsent( $postarr, $sent );
        if ( ! isset( $sent['cost'] ) ) {
            $postarr = self::fill_cost( $postarr, $event_id );
        }
        $postarr = self::fill_schedule( $postarr, $event_id, $sent );
        $postarr = self::fill_organizers( $postarr, $event_id, $sent );
        return self::fill_flags( $postarr, $post, $sent );
    }

    










    private static function drop_unsent( array $postarr, array $sent ): array {
        $keys = array(
            'date'     => 'post_date',
            'date_utc' => 'post_date_gmt',
            'status'   => 'post_status',
            'image'    => 'FeaturedImage',
            'website'  => 'EventURL',
        );
        foreach ( $keys as $field => $key ) {
            if ( ! isset( $sent[ $field ] ) ) {
                $postarr[ $key ] = null;
            }
        }
        return $postarr;
    }

    







    private static function fill_cost( array $postarr, int $event_id ): array {
        foreach ( array( 'EventCurrencySymbol', 'EventCurrencyPosition' ) as $key ) {
            $postarr[ $key ] = metadata_exists( 'post', $event_id, '_' . $key ) ? get_post_meta( $event_id, '_' . $key, true ) : null;
        }
        $costs = (array) get_post_meta( $event_id, '_EventCost', false );
        if ( ! $costs ) {
            return $postarr;
        }
        $postarr['EventCost'] = 1 === count( $costs ) ? reset( $costs ) : array_values( $costs );
        return $postarr;
    }

    










    private static function fill_schedule( array $postarr, int $event_id, array $sent ): array {
        if ( ! isset( $sent['all_day'] ) && self::truthy( get_post_meta( $event_id, '_EventAllDay', true ) ) ) {
            $postarr['EventAllDay'] = true;
        }
        $timezone = get_post_meta( $event_id, '_EventTimezone', true );
        if ( ! isset( $sent['timezone'] ) && is_string( $timezone ) && '' !== $timezone ) {
            $postarr['EventTimezone'] = $timezone;
            
            $postarr['EventTimezoneAbbr'] = '';
        }
        
        $pairs = array(
            'start_date' => array( 'end_date', '_EventEndDate', 'EventEndDate', 'EventEndTime' ),
            'end_date'   => array( 'start_date', '_EventStartDate', 'EventStartDate', 'EventStartTime' ),
        );
        foreach ( $pairs as $given => $other ) {
            if ( ! isset( $sent[ $given ] ) || isset( $sent[ $other[0] ] ) ) {
                continue;
            }
            $stored = (string) get_post_meta( $event_id, $other[1], true );
            if ( 1 === preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/', $stored, $parts ) ) {
                $postarr[ $other[2] ] = $parts[1];
                $postarr[ $other[3] ] = $parts[2];
            }
        }
        return $postarr;
    }

    







    private static function fill_organizers( array $postarr, int $event_id, array $sent ): array {
        if ( isset( $sent['organizer'] ) ) {
            return $postarr;
        }
        $organizers = array_values( array_filter( array_map( 'absint', (array) get_post_meta( $event_id, '_EventOrganizerID', false ) ) ) );
        if ( $organizers ) {
            $postarr['organizer'] = array( 'OrganizerID' => $organizers );
        }
        return $postarr;
    }

    









    private static function fill_flags( array $postarr, object $post, array $sent ): array {
        $event_id = (int) $post->ID;
        foreach ( array( 'show_map' => '_EventShowMap', 'show_map_link' => '_EventShowMapLink' ) as $field => $meta_key ) {
            if ( ! isset( $sent[ $field ] ) ) {
                $postarr[ ltrim( $meta_key, '_' ) ] = metadata_exists( 'post', $event_id, $meta_key ) ? self::truthy( get_post_meta( $event_id, $meta_key, true ) ) : true;
            }
        }
        if ( ! isset( $sent['hide_from_listings'] ) && self::truthy( get_post_meta( $event_id, '_EventHideFromUpcoming', true ) ) ) {
            $postarr['EventHideFromUpcoming'] = 'yes';
        }
        if ( ! isset( $sent['sticky'] ) ) {
            $postarr['EventShowInCalendar'] = -1 === (int) $post->menu_order;
        }
        if ( ! isset( $sent['featured'] ) ) {
            $postarr['feature_event'] = self::truthy( get_post_meta( $event_id, '_tribe_featured', true ) );
        }
        return $postarr;
    }

    





    private static function truthy( $value ): bool {
        if ( is_string( $value ) ) {
            return in_array( strtolower( $value ), array( '1', 'yes', 'true' ), true );
        }
        return true === $value || 1 === $value;
    }
}
