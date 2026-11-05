<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Narrow_Post_Steps {

    abstract protected function beside_post_fields(): array;

    public function writes_only_beside( array $fields ): bool {
        return array() !== $fields && array() === array_diff( $fields, $this->beside_post_fields() );
    }

    public function write( \WP_REST_Request $request, int $post_id ) {
        $post   = get_post( $post_id );
        $schema = $this->get_item_schema();

        if ( ! empty( $schema['properties']['format'] ) && ! empty( $request['format'] ) ) {
            set_post_format( $post, $request['format'] );
        }
        if ( ! empty( $schema['properties']['featured_media'] ) && isset( $request['featured_media'] ) ) {
            $this->handle_featured_media( $request['featured_media'], $post_id );
        }
        if ( ! empty( $schema['properties']['sticky'] ) && isset( $request['sticky'] ) ) {
            if ( ! empty( $request['sticky'] ) ) {
                stick_post( $post_id );
            } else {
                unstick_post( $post_id );
            }
        }
        if ( ! empty( $schema['properties']['template'] ) && isset( $request['template'] ) ) {
            $this->handle_template( $request['template'], $post_id );
        }

        $terms = $this->handle_terms( $post_id, $request );
        if ( is_wp_error( $terms ) ) {
            return $terms;
        }

        if ( ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {
            $meta = $this->meta->update_value( $request['meta'], $post_id );
            if ( is_wp_error( $meta ) ) {
                return $meta;
            }
        }

        $fields = $this->update_additional_fields_for_object( get_post( $post_id ), $request );
        if ( is_wp_error( $fields ) ) {
            return $fields;
        }
        return true;
    }

    public function respond( \WP_REST_Request $request, int $post_id ): \WP_REST_Response {
        $request->set_param( 'context', 'edit' );
        $response = $this->prepare_item_for_response( get_post( $post_id ), $request );
        return is_wp_error( $response ) ? rest_convert_error_to_response( $response ) : rest_ensure_response( $response );
    }
}
