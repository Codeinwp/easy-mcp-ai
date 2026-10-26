<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












trait Store_Setup {

    
    private static $location_types = array( 'postcode', 'state', 'country', 'continent' );

    
    private static $continents = array( 'AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA' );

    


    protected function require_woocommerce(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }
    }

    









    protected function parse_bool( $value, string $label ): bool {
        if ( is_bool( $value ) ) {
            return $value;
        }
        if ( in_array( $value, array( 1, '1', 'true' ), true ) ) {
            return true;
        }
        if ( in_array( $value, array( 0, '0', 'false' ), true ) ) {
            return false;
        }
        throw new \InvalidArgumentException( sprintf( 'Invalid %s: send true or false.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    









    protected function parse_non_negative_int( $value, string $label ): int {
        if ( is_int( $value ) && $value >= 0 ) {
            return $value;
        }
        if ( is_string( $value ) && ctype_digit( $value ) ) {
            return (int) $value;
        }
        
        
        if ( is_float( $value ) && $value >= 0 && floor( $value ) === $value && $value <= 2147483647 ) {
            return (int) $value;
        }
        throw new \InvalidArgumentException( sprintf( 'Invalid %s: a whole number of 0 or more.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    











    protected function refuse_catch_all_zone( array $locations ): void {
        if ( empty( $locations ) ) {
            throw new \InvalidArgumentException( 'A shipping zone needs at least one location: WooCommerce matches a zone with none to every address, ahead of every zone after it. For "everywhere else" use zone 0 ("Rest of the world"); to stop using a zone, delete it with wp_wc_delete_shipping_zone.' );
        }
    }

    




    protected static function locations_schema(): array {
        return array(
            'type'        => 'array',
            'description' => 'The zone\'s full list of locations; it replaces the current list, and must not be empty (WooCommerce matches a zone with no locations to every address; postcodes alone match those postcodes in every country). Each item: `code` (country "US", state "US:CA", continent "EU", or postcode "90210", "902*" or range "90210...90299") and `type` (country, state, continent or postcode). Codes: wp_wc_list_countries.',
            'items'       => array(
                'type'       => 'object',
                'properties' => array(
                    'code' => array( 'type' => 'string' ),
                    'type' => array(
                        'type' => 'string',
                        'enum' => self::$location_types,
                    ),
                ),
                'required'   => array( 'code', 'type' ),
            ),
        );
    }

    








    protected function parse_locations( $value ): array {
        if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
            throw new \InvalidArgumentException( 'Invalid locations: must be a list of { code, type } objects.' );
        }
        $locations = array();
        foreach ( $value as $index => $entry ) {
            $code = is_array( $entry ) && isset( $entry['code'] ) && is_string( $entry['code'] ) ? trim( $entry['code'] ) : '';
            $type = is_array( $entry ) && isset( $entry['type'] ) ? $entry['type'] : null;
            if ( '' === $code ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid locations[%d]: `code` must be a non-empty string.', $index ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( ! in_array( $type, self::$location_types, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid locations[%d].type: use country, state, continent or postcode.', $index ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $locations[] = array(
                
                'code' => 'postcode' === $type ? $code : strtoupper( $code ),
                'type' => $type,
            );
        }
        return $locations;
    }

    








    protected function refuse_unknown_location_codes( array $locations ): void {
        $countries = null;
        foreach ( $locations as $index => $location ) {
            $code = strtoupper( $location['code'] );
            if ( 'continent' === $location['type'] ) {
                $known = in_array( $code, self::$continents, true );
            } elseif ( 'country' === $location['type'] || 'state' === $location['type'] ) {
                if ( null === $countries ) {
                    $countries = $this->country_states();
                }
                $parts = explode( ':', $code, 2 );
                $known = 'country' === $location['type']
                    ? 1 === count( $parts ) && isset( $countries[ $code ] )
                    : 2 === count( $parts ) && in_array( $parts[1], $countries[ $parts[0] ] ?? array(), true );
            } else {
                continue;
            }
            if ( ! $known ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid locations[%1$d]: WooCommerce has no %2$s "%3$s". Codes: wp_wc_list_countries (a state is "US:CA"); continents: %4$s.', $index, $location['type'], $location['code'], implode( ', ', self::$continents ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    




    private function country_states(): array {
        $out = array();
        foreach ( (array) $this->rest_request( 'GET', '/wc/v3/data/countries' ) as $country ) {
            if ( ! is_array( $country ) || ! isset( $country['code'] ) ) {
                continue;
            }
            $states = array();
            foreach ( isset( $country['states'] ) && is_array( $country['states'] ) ? $country['states'] : array() as $state ) {
                if ( is_array( $state ) && isset( $state['code'] ) ) {
                    $states[] = strtoupper( (string) $state['code'] );
                }
            }
            $out[ strtoupper( (string) $country['code'] ) ] = $states;
        }
        return $out;
    }

    










    protected function parse_editable_zone_id( $value, string $verb ): int {
        if ( 0 === $value || '0' === $value ) {
            throw new \InvalidArgumentException( sprintf( 'Zone 0 is WooCommerce\'s "Rest of the world" zone (locations not covered by your other zones); it cannot be %s. Its shipping methods can be changed with wp_wc_add_shipping_method and wp_wc_update_shipping_method.', $verb ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $this->parse_required_id( $value, 'id' );
    }

    







    protected function parse_zone_id( $value ): int {
        if ( 0 === $value || '0' === $value ) {
            return 0;
        }
        return $this->parse_required_id( $value, 'zone_id' );
    }

    





    protected static function present_shipping_class( $class ): array {
        $class = is_array( $class ) ? $class : array();
        return array(
            'id'          => (int) ( $class['id'] ?? 0 ),
            'name'        => (string) ( $class['name'] ?? '' ),
            'slug'        => (string) ( $class['slug'] ?? '' ),
            'description' => (string) ( $class['description'] ?? '' ),
            'count'       => (int) ( $class['count'] ?? 0 ),
        );
    }

    





    protected static function tax_rate_properties(): array {
        return array(
            'country'   => array(
                'type'        => 'string',
                'description' => 'Two-letter country code (wp_wc_list_countries); empty for every country.',
            ),
            'state'     => array(
                'type'        => 'string',
                'description' => 'State code without the country, e.g. "CA"; empty for every state.',
            ),
            'postcodes' => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'string' ),
                'description' => 'Postcodes the rate applies to; "902*" and ranges "90210...90299" work. Replaces the current list.',
            ),
            'cities'    => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'string' ),
                'description' => 'Cities the rate applies to. Replaces the current list.',
            ),
            'rate'      => array(
                'type'        => 'string',
                'description' => 'Percentage as a decimal string, e.g. "20" or "7.25".',
            ),
            'name'      => array(
                'type'        => 'string',
                'description' => 'Name shown to customers, e.g. "VAT".',
            ),
            'priority'  => array(
                'type'        => 'integer',
                'minimum'     => 0,
                'description' => 'Priority; one rate per priority applies. Default 1.',
            ),
            'compound'  => array(
                'type'        => 'boolean',
                'description' => 'Applied on top of the other taxes. Default false.',
            ),
            'shipping'  => array(
                'type'        => 'boolean',
                'description' => 'Also applied to shipping. Default true.',
            ),
            'order'     => array(
                'type'        => 'integer',
                'minimum'     => 0,
                'description' => 'Sort order.',
            ),
            'class'     => array(
                'type'        => 'string',
                'description' => 'Tax class slug (wp_wc_list_tax_classes). Default "standard".',
            ),
        );
    }

    







    protected function tax_rate_params( array $arguments ): array {
        $params = array();
        foreach ( array( 'country', 'state', 'name', 'class' ) as $field ) {
            if ( array_key_exists( $field, $arguments ) ) {
                if ( ! is_string( $arguments[ $field ] ) ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $params[ $field ] = sanitize_text_field( $arguments[ $field ] );
            }
        }
        if ( isset( $params['country'] ) ) {
            $params['country'] = strtoupper( $params['country'] );
        }
        if ( array_key_exists( 'rate', $arguments ) ) {
            $rate = is_string( $arguments['rate'] ) || is_int( $arguments['rate'] ) || is_float( $arguments['rate'] ) ? trim( (string) $arguments['rate'] ) : '';
            if ( ! is_numeric( $rate ) || (float) $rate < 0 ) {
                throw new \InvalidArgumentException( 'Invalid rate: a percentage of 0 or more, as a decimal string, e.g. "20" or "7.25".' );
            }
            $params['rate'] = $rate;
        }
        foreach ( array( 'postcodes', 'cities' ) as $field ) {
            if ( array_key_exists( $field, $arguments ) ) {
                if ( ! is_array( $arguments[ $field ] ) || array_values( $arguments[ $field ] ) !== $arguments[ $field ] ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: send a list of strings.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $params[ $field ] = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $arguments[ $field ] ) ), 'strlen' ) );
            }
        }
        foreach ( array( 'priority', 'order' ) as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $params[ $field ] = $this->parse_non_negative_int( $arguments[ $field ], $field );
            }
        }
        foreach ( array( 'compound', 'shipping' ) as $field ) {
            if ( array_key_exists( $field, $arguments ) ) {
                $params[ $field ] = $this->parse_bool( $arguments[ $field ], $field );
            }
        }
        return $params;
    }

    





    protected static function present_tax_rate( $rate ): array {
        $rate = is_array( $rate ) ? $rate : array();
        return array(
            'id'        => (int) ( $rate['id'] ?? 0 ),
            'country'   => (string) ( $rate['country'] ?? '' ),
            'state'     => (string) ( $rate['state'] ?? '' ),
            'postcodes' => array_values( (array) ( $rate['postcodes'] ?? array() ) ),
            'cities'    => array_values( (array) ( $rate['cities'] ?? array() ) ),
            'rate'      => (string) ( $rate['rate'] ?? '' ),
            'name'      => (string) ( $rate['name'] ?? '' ),
            'priority'  => (int) ( $rate['priority'] ?? 0 ),
            'compound'  => (bool) ( $rate['compound'] ?? false ),
            'shipping'  => (bool) ( $rate['shipping'] ?? false ),
            'order'     => (int) ( $rate['order'] ?? 0 ),
            'class'     => (string) ( $rate['class'] ?? '' ),
        );
    }

    







    protected static function present_method( $method, int $zone_id ): array {
        $method   = is_array( $method ) ? $method : array();
        $settings = array();
        foreach ( self::method_fields( $method ) as $key => $field ) {
            $settings[ $key ] = array_key_exists( 'value', $field ) ? $field['value'] : null;
        }
        return array(
            'zone_id'     => $zone_id,
            'instance_id' => (int) ( $method['instance_id'] ?? 0 ),
            'method_id'   => (string) ( $method['method_id'] ?? '' ),
            
            
            'title'       => (string) ( $settings['title'] ?? $method['title'] ?? '' ),
            'enabled'     => (bool) ( $method['enabled'] ?? false ),
            'order'       => (int) ( $method['order'] ?? 0 ),
            'settings'    => $settings,
        );
    }

    







    protected static function method_fields( $method ): array {
        $fields = array();
        foreach ( is_array( $method ) && isset( $method['settings'] ) && is_array( $method['settings'] ) ? $method['settings'] : array() as $key => $field ) {
            if ( is_array( $field ) && 'title' !== ( $field['type'] ?? '' ) ) {
                $fields[ (string) $key ] = $field;
            }
        }
        return $fields;
    }

    










    protected function method_settings( $sent, array $definitions, string $method_id ): array {
        if ( ! is_array( $sent ) || ( ! empty( $sent ) && array_values( $sent ) === $sent ) ) {
            throw new \InvalidArgumentException( 'Invalid settings: send an object of setting id to value, e.g. { "cost": "10" }.' );
        }
        $this->refuse_unknown_keys( $sent, array_map( 'strval', array_keys( $definitions ) ), 'setting for shipping method ' . $method_id );
        $settings = array();
        foreach ( $sent as $key => $value ) {
            $settings[ (string) $key ] = $this->normalize_setting_value( (string) $key, $value, $definitions[ $key ], 'the `settings` of wp_wc_list_shipping_methods' );
        }
        return $settings;
    }

    





    protected function read_zone( int $zone_id ): array {
        $zone = $this->rest_request( 'GET', '/wc/v3/shipping/zones/' . $zone_id );
        return array(
            'id'        => $zone_id,
            'name'      => (string) ( $zone['name'] ?? '' ),
            'order'     => (int) ( $zone['order'] ?? 0 ),
            'locations' => $this->zone_locations( $zone_id ),
        );
    }

    






    protected function zone_locations( int $zone_id ): array {
        if ( 0 === $zone_id ) {
            return array();
        }
        $locations = array();
        foreach ( (array) $this->rest_request( 'GET', '/wc/v3/shipping/zones/' . $zone_id . '/locations' ) as $location ) {
            if ( is_array( $location ) && isset( $location['code'] ) ) {
                $locations[] = array(
                    'code' => (string) $location['code'],
                    'type' => (string) ( $location['type'] ?? '' ),
                );
            }
        }
        return $locations;
    }

    














    protected function normalize_setting_value( string $id, $value, array $setting, string $reader ) {
        $type    = (string) ( $setting['type'] ?? 'text' );
        $options = isset( $setting['options'] ) && is_array( $setting['options'] ) ? array_map( 'strval', array_keys( $setting['options'] ) ) : array();
        if ( in_array( $type, array( 'password', 'image_width' ), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Setting %1$s is a %2$s field, which these tools do not change.', $id, $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 'checkbox' === $type ) {
            if ( is_bool( $value ) ) {
                return $value ? 'yes' : 'no';
            }
            if ( in_array( $value, array( 'yes', 'no' ), true ) ) {
                return $value;
            }
            throw new \InvalidArgumentException( sprintf( 'Invalid value for %s: a checkbox takes true, false, "yes" or "no".', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 'multiselect' === $type ) {
            if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid value for %s: send the full list of codes, e.g. ["US", "CA"]; an empty list clears it.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $codes   = array_map(
                static function ( $code ): string {
                    return is_string( $code ) || is_int( $code ) ? (string) $code : '';
                },
                $value
            );
            $invalid = array_diff( $codes, $options );
            if ( ! empty( $invalid ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid value for %1$s: %2$s not allowed. Read the allowed values with %3$s.', $id, implode( ', ', $invalid ), sprintf( $reader, $id ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return array_values( array_unique( $codes ) );
        }
        if ( ! is_scalar( $value ) || is_bool( $value ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid value for %1$s: a %2$s setting takes a string.', $id, $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $value = (string) $value;
        $this->refuse_backslash( $value, $id );
        if ( in_array( $type, array( 'select', 'radio' ), true ) && ! in_array( $value, $options, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid value for %1$s: "%2$s" is not one of its options. Read them with %3$s.', $id, $value, sprintf( $reader, $id ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $value;
    }

    











    protected function refuse_backslash( string $value, string $label ): void {
        if ( false !== strpos( $value, '\\' ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid value for %s: backslashes are removed from this text when it is saved, so it would not be saved as sent. Leave the backslashes out.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    protected function refuse_unknown_keys( array $sent, array $known, string $label ): void {
        $unknown = array_diff( array_map( 'strval', array_keys( $sent ) ), $known );
        if ( ! empty( $unknown ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown %1$s: %2$s. Accepted: %3$s.', $label, implode( ', ', $unknown ), empty( $known ) ? 'none' : implode( ', ', $known ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    protected static function changed_fields( array $before, array $after, array $fields ): array {
        $changed = array();
        foreach ( $fields as $field ) {
            $old = array_key_exists( $field, $before ) ? $before[ $field ] : null;
            $new = array_key_exists( $field, $after ) ? $after[ $field ] : null;
            if ( is_array( $old ) || is_array( $new ) ) {
                $differs = $old !== $new;
            } else {
                $differs = self::scalar_text( $old ) !== self::scalar_text( $new );
            }
            if ( $differs ) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    





    private static function scalar_text( $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? 'yes' : 'no';
        }
        return is_scalar( $value ) ? (string) $value : '';
    }

    






    protected function refuse_empty_update( array $params, array $fields ): void {
        if ( empty( $params ) ) {
            throw new \InvalidArgumentException( sprintf( 'Nothing to update: send at least one of %s.', implode( ', ', $fields ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
