<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








class Delete_Key_Event extends Base_Tool {

    
    const NAME_PATTERN = '#^properties/(\d+)/keyEvents/([A-Za-z0-9_-]+)\z#';

    public function get_name() { return 'wp_ga_delete_key_event'; }

    public function get_description() {
        return 'Removes a key event (a conversion) from a Google Analytics 4 property. The event is still collected; it stops counting as a key event from now on. Give exactly one of `event_name` (e.g. "generate_lead") or `key_event` (its ID or full resource name "properties/{id}/keyEvents/{id}", from wp_ga_list_conversion_events). Returns { deleted: true, key_event } with the key event as it was. Refused when no such key event exists, and when Google marks it as not deletable. Recreate it with wp_ga_create_key_event. The configured service account needs the Editor role on the property.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Delete Analytics key event',
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'idempotentHint'  => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'property_id' => array( 'type' => 'string', 'description' => 'Numeric GA4 property ID, or "properties/{id}". Omit it to use the configured default property; a value that names no property (such as "0" or "") is refused, never replaced by the default. Must match the property in a full key_event resource name.' ),
                'event_name'  => array( 'type' => 'string', 'description' => 'Name of the event whose key event to remove, e.g. "generate_lead". Give this or key_event.' ),
                'key_event'   => array( 'type' => 'string', 'description' => 'Key event ID, or "properties/{id}/keyEvents/{id}". Give this or event_name.' ),
            ),
        );
    }

    





    public function describe( array $arguments ) {
        $event_name = self::text_arg( $arguments, 'event_name' );
        $key_event  = self::text_arg( $arguments, 'key_event' );
        try {
            if ( '' === $event_name && '' !== $key_event ) {
                return sprintf( 'Remove the Google Analytics key event %s', self::resource_name( $key_event, $arguments ) );
            }
            return sprintf( 'Remove the key event "%s" from Google Analytics %s', $event_name, self::property( $arguments ) );
        } catch ( \RuntimeException $e ) {
            return self::generic_description( $this, $arguments );
        }
    }

    public function execute( array $arguments ) {
        $event_name = self::text_arg( $arguments, 'event_name' );
        $key_event  = self::text_arg( $arguments, 'key_event' );
        if ( ( '' === $event_name ) === ( '' === $key_event ) ) {
            throw new \RuntimeException( 'Give exactly one of event_name or key_event.' );
        }

        $existing = '' !== $key_event
            ? self::by_resource_name( self::resource_name( $key_event, $arguments ) )
            : self::by_event_name( self::property( $arguments ), $event_name );

        
        
        if ( ! preg_match( self::NAME_PATTERN, $existing['name'] ) ) {
            throw new \RuntimeException( 'Google Analytics returned an unexpected key event name; nothing was changed.' );
        }
        if ( isset( $existing['deletable'] ) && false === $existing['deletable'] ) {
            $label = isset( $existing['eventName'] ) && is_string( $existing['eventName'] ) ? $existing['eventName'] : $existing['name'];
            throw new \RuntimeException(
                "Google Analytics does not allow deleting the key event \"{$label}\" ({$existing['name']}); nothing was changed." // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        GA_Client::edit( 'DELETE', 'https://analyticsadmin.googleapis.com/v1beta/' . $existing['name'] );
        return array(
            'deleted'   => true,
            'key_event' => $existing,
        );
    }

    



    private static function property( array $arguments ): string {
        return GA_Client::write_property( $arguments );
    }

    








    private static function resource_name( string $key_event, array $arguments ): string {
        if ( preg_match( self::NAME_PATTERN, $key_event, $m ) ) {
            $property = 'properties/' . $m[1];
            if ( 1 === preg_match( '/^0+\z/', $m[1] ) ) {
                throw new \RuntimeException( 'key_event must name a GA4 property; properties/0 is not one. Nothing was changed.' );
            }
            if ( array_key_exists( 'property_id', $arguments ) && null !== $arguments['property_id'] && GA_Client::write_property( $arguments ) !== $property ) {
                throw new \RuntimeException( 'key_event belongs to a different property than property_id; nothing was changed.' );
            }
            return $key_event;
        }
        if ( preg_match( '/^[A-Za-z0-9_-]+\z/', $key_event ) ) {
            return self::property( $arguments ) . '/keyEvents/' . $key_event;
        }
        throw new \RuntimeException( 'key_event must be a key event ID or "properties/{id}/keyEvents/{id}".' );
    }

    



    private static function by_resource_name( string $name ): array {
        $event = GA_Client::edit( 'GET', 'https://analyticsadmin.googleapis.com/v1beta/' . $name );
        if ( empty( $event['name'] ) || ! is_string( $event['name'] ) ) {
            throw new \RuntimeException( "No key event {$name} was found; nothing was changed." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $event;
    }

    



    private static function by_event_name( string $property, string $event_name ): array {
        foreach ( GA_Client::key_events( $property ) as $event ) {
            if ( isset( $event['eventName'], $event['name'] ) && $event['eventName'] === $event_name && is_string( $event['name'] ) ) {
                return $event;
            }
        }
        throw new \RuntimeException( "\"{$event_name}\" is not a key event on {$property}; nothing was changed." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
