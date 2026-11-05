<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Narrow_Meta_Checks {

    public function refusal( array $meta, int $object_id ): ?\WP_Error {
        $meta_type = $this->get_meta_type();
        $error     = new \WP_Error();
        foreach ( $this->get_registered_fields() as $meta_key => $args ) {
            $name = $args['name'];
            if ( ! array_key_exists( $name, $meta ) ) {
                continue;
            }
            $value = $meta[ $name ];

            if ( is_null( $value ) || ( array() === $value && ! $args['single'] ) ) {
                if ( $args['single'] && is_wp_error( rest_validate_value_from_schema( get_metadata( $meta_type, $object_id, $meta_key, true ), $args['schema'] ) ) ) {
                    $error->add( 'rest_invalid_stored_value', self::invalid_stored_value( $name ), array( 'status' => 500 ) );
                } elseif ( ! current_user_can( "delete_{$meta_type}_meta", $object_id, $meta_key ) ) {
                    $error->add( 'rest_cannot_delete', self::not_allowed( $name ), array( 'key' => $name, 'status' => rest_authorization_required_code() ) );
                }
                continue;
            }

            if ( ! $args['single'] && is_array( $value ) && count( array_filter( $value, 'is_null' ) ) ) {
                $error->add( 'rest_invalid_stored_value', self::invalid_stored_value( $name ), array( 'status' => 500 ) );
                continue;
            }

            $valid = rest_validate_value_from_schema( $value, $args['schema'], 'meta.' . $name );
            if ( is_wp_error( $valid ) ) {
                $valid->add_data( array( 'status' => 400 ) );
                $error->merge_from( $valid );
                continue;
            }

            if ( ! $this->may_write( $object_id, $meta_key, $args, rest_sanitize_value_from_schema( $value, $args['schema'] ) ) ) {
                $error->add( 'rest_cannot_update', self::not_allowed( $name ), array( 'key' => $name, 'status' => rest_authorization_required_code() ) );
            }
        }
        return $error->has_errors() ? $error : null;
    }

    private function may_write( int $object_id, string $meta_key, array $args, $value ): bool {
        $meta_type = $this->get_meta_type();
        $version   = get_bloginfo( 'version' );
        if ( $args['single'] && version_compare( $version, '6.3-alpha', '>=' ) ) {
            $stored = version_compare( $version, '6.7-alpha', '>=' )
                ? get_metadata_raw( $meta_type, $object_id, $meta_key )
                : get_metadata( $meta_type, $object_id, $meta_key );
            if ( is_array( $stored ) && 1 === count( $stored )
                && $this->is_meta_value_same_as_stored_value( $meta_key, get_object_subtype( $meta_type, $object_id ), $stored[0], $value ) ) {
                return true;
            }
        }
        return current_user_can( "edit_{$meta_type}_meta", $object_id, $meta_key );
    }

    private static function not_allowed( string $name ): string {
        return sprintf( 'Sorry, you are not allowed to edit the %s custom field.', $name );
    }

    private static function invalid_stored_value( string $name ): string {
        return sprintf( 'The %s property has an invalid stored value, and cannot be updated to null.', $name );
    }
}
