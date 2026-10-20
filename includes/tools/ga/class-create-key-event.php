<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







class Create_Key_Event extends Base_Tool {

    
    const COUNTING_METHODS = array( 'ONCE_PER_EVENT', 'ONCE_PER_SESSION' );

    public function get_name() { return 'wp_ga_create_key_event'; }

    public function get_description() {
        return 'Marks an event as a key event (a conversion) on a Google Analytics 4 property. Required: `event_name`, the event\'s name exactly as GA4 collects it (e.g. "generate_lead"). Optional: `counting_method` (ONCE_PER_EVENT, the default, or ONCE_PER_SESSION), and `default_value` with `currency_code` to give the conversion a value when the event carries none. Returns { created: true, key_event }. Refused when the event is already a key event (see wp_ga_list_conversion_events). Undo with wp_ga_delete_key_event. The configured service account needs the Editor role on the property.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Create Analytics key event',
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'property_id'     => array( 'type' => 'string', 'description' => 'Numeric GA4 property ID, or "properties/{id}". Omit it to use the configured default property; a value that names no property (such as "0" or "") is refused, never replaced by the default.' ),
                'event_name'      => array( 'type' => 'string', 'description' => 'Event name to mark as a key event, exactly as GA4 collects it, e.g. "generate_lead". Starts with a letter; letters (any language), numbers and underscores; at most 40 characters.' ),
                'counting_method' => array(
                    'type'        => 'string',
                    'enum'        => self::COUNTING_METHODS,
                    'default'     => 'ONCE_PER_EVENT',
                    'description' => 'ONCE_PER_EVENT counts every occurrence; ONCE_PER_SESSION counts at most one per session per user.',
                ),
                'default_value'   => array( 'type' => 'number', 'description' => 'Value used for occurrences that carry no `value` parameter. Requires currency_code.' ),
                'currency_code'   => array( 'type' => 'string', 'description' => 'ISO 4217 code for default_value, e.g. "USD". Requires default_value.' ),
            ),
            'required'   => array( 'event_name' ),
        );
    }

    




    public function describe( array $arguments ) {
        $event = self::text_arg( $arguments, 'event_name' );
        try {
            $property = GA_Client::write_property( $arguments );
        } catch ( \RuntimeException $e ) {
            return self::generic_description( $this, $arguments );
        }
        return sprintf( 'Mark the Google Analytics event "%s" as a key event on %s', $event, $property );
    }

    public function execute( array $arguments ) {
        $body     = self::build_body( $arguments );
        $property = GA_Client::write_property( $arguments );

        foreach ( GA_Client::key_events( $property ) as $existing ) {
            if ( isset( $existing['eventName'] ) && $existing['eventName'] === $body['eventName'] ) {
                $method = isset( $existing['countingMethod'] ) ? (string) $existing['countingMethod'] : 'unknown';
                throw new \RuntimeException(
                    "\"{$body['eventName']}\" is already a key event on {$property} (counting method {$method}); nothing was changed." // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
        }

        $created = GA_Client::edit( 'POST', "https://analyticsadmin.googleapis.com/v1beta/{$property}/keyEvents", $body );
        if ( empty( $created['name'] ) ) {
            throw new \RuntimeException( 'Google Analytics accepted the request but returned no key event; check wp_ga_list_conversion_events before retrying.' );
        }
        return array(
            'created'   => true,
            'key_event' => $created,
        );
    }

    







    private static function build_body( array $arguments ): array {
        $event = self::text_arg( $arguments, 'event_name' );
        if ( '' === $event ) {
            throw new \RuntimeException( 'event_name is required.' );
        }
        
        
        
        
        
        
        if ( 1 !== preg_match( '/^\p{L}[\p{L}\p{M}\p{N}_]*\z/u', $event ) || preg_match_all( '/./us', $event ) > 40 ) {
            throw new \RuntimeException( 'event_name must start with a letter and contain only letters, numbers and underscores, at most 40 characters.' );
        }

        $method = isset( $arguments['counting_method'] ) && '' !== $arguments['counting_method'] ? $arguments['counting_method'] : 'ONCE_PER_EVENT';
        if ( ! is_string( $method ) || ! in_array( $method, self::COUNTING_METHODS, true ) ) {
            throw new \RuntimeException( 'counting_method must be ONCE_PER_EVENT or ONCE_PER_SESSION.' );
        }

        $body = array(
            'eventName'      => $event,
            'countingMethod' => $method,
        );

        $has_value    = isset( $arguments['default_value'] ) && '' !== $arguments['default_value'];
        $has_currency = isset( $arguments['currency_code'] ) && '' !== $arguments['currency_code'];
        if ( $has_value !== $has_currency ) {
            throw new \RuntimeException( 'default_value and currency_code must be given together.' );
        }
        if ( $has_value ) {
            if ( ! is_numeric( $arguments['default_value'] ) ) {
                throw new \RuntimeException( 'default_value must be a number.' );
            }
            $currency = is_string( $arguments['currency_code'] ) ? strtoupper( trim( $arguments['currency_code'] ) ) : '';
            if ( ! preg_match( '/^[A-Z]{3}\z/', $currency ) ) {
                throw new \RuntimeException( 'currency_code must be a three-letter ISO 4217 code, e.g. "USD".' );
            }
            $body['defaultValue'] = array(
                'numericValue' => (float) $arguments['default_value'],
                'currencyCode' => $currency,
            );
        }
        return $body;
    }
}
