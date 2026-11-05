<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Narrow_Post_Write {

    const STOPPED = 'easy_mcp_ai_narrow_write_stopped';

    const NOT_FIELDS = array( '_fields', 'context' );

    const BEFORE_META = array( 'format', 'featured_media', 'sticky', 'template' );

    public static function dispatch( \WP_REST_Request $request, int $post_id, bool $resave = false ): ?array {
        $controller = self::controller( $request, $post_id );
        if ( null === $controller ) {
            return null;
        }
        $post_type = (string) get_post_type( $post_id );
        $writer    = $resave ? null : self::writer( $controller, $post_type );

        $matched = false;
        $stopped = false;
        $stop    = static function ( $prepared, $current ) use ( $request, $post_id, $post_type, $controller, $writer, &$matched, &$stopped ) {
            if ( $matched || $current !== $request ) {
                return $prepared;
            }
            $matched = true;
            $given = array_merge( (array) $request->get_query_params(), (array) $request->get_body_params(), (array) $request->get_json_params() );
            $sent  = array_values( array_diff( array_keys( $given ), self::NOT_FIELDS ) );
            if ( ! self::changes_no_column( $prepared, $post_id ) ) {
                return $prepared;
            }
            $before_meta = array_merge( self::BEFORE_META, self::taxonomy_fields( $post_type ) );
            $refusal     = array() === array_intersect( $sent, $before_meta ) ? self::meta_refusal( $controller, $request, $post_id ) : null;
            if ( null !== $refusal ) {
                return $refusal;
            }
            if ( null === $writer || ! $writer->writes_only_beside( $sent ) ) {
                return $prepared;
            }
            $stopped = true;
            return new \WP_Error( self::STOPPED, 'Stopped before the post save.' );
        };
        $hook = 'rest_pre_insert_' . $post_type;
        add_filter( $hook, $stop, PHP_INT_MAX, 2 );
        try {
            $response = rest_do_request( $request );
        } finally {
            remove_filter( $hook, $stop, PHP_INT_MAX );
        }

        if ( ! $stopped ) {
            return array( 'response' => $response, 'wrote' => false );
        }

        $written = $writer->write( $request, $post_id );
        if ( is_wp_error( $written ) ) {
            return array( 'response' => rest_convert_error_to_response( $written ), 'wrote' => true );
        }
        return array( 'response' => $writer->respond( $request, $post_id ), 'wrote' => true );
    }

    private static function meta_refusal( \WP_REST_Posts_Controller $controller, \WP_REST_Request $request, int $post_id ): ?\WP_Error {
        if ( ! isset( $request['meta'] ) || ! is_array( $request['meta'] ) || empty( $controller->get_item_schema()['properties']['meta'] ) ) {
            return null;
        }
        $handler = ( function () {
            return $this->meta;
        } )->call( $controller );
        if ( ! is_object( $handler ) || 'WP_REST_Post_Meta_Fields' !== get_class( $handler ) ) {
            return null;
        }
        $checks = new class( (string) get_post_type( $post_id ) ) extends \WP_REST_Post_Meta_Fields {
            use Narrow_Meta_Checks;
        };
        return $checks->refusal( $request['meta'], $post_id );
    }

    private static function changes_no_column( $prepared, int $post_id ): bool {
        if ( ! $prepared instanceof \stdClass ) {
            return false;
        }
        $expected = array(
            'ID'            => $post_id,
            'post_type'     => get_post_type( $post_id ),
            'page_template' => null,
        );
        foreach ( get_object_vars( $prepared ) as $name => $value ) {
            if ( ! array_key_exists( $name, $expected ) || ( 'ID' === $name ? (int) $value !== $post_id : $value !== $expected[ $name ] ) ) {
                return false;
            }
        }
        return true;
    }

    public static function taxonomy_fields( string $post_type ): array {
        $fields = array();
        foreach ( wp_list_filter( get_object_taxonomies( $post_type, 'objects' ), array( 'show_in_rest' => true ) ) as $taxonomy ) {
            $fields[] = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;
        }
        return $fields;
    }

    private static function controller( \WP_REST_Request $request, int $post_id ): ?\WP_REST_Posts_Controller {
        $type = get_post_type_object( (string) get_post_type( $post_id ) );
        if ( ! $type instanceof \WP_Post_Type ) {
            return null;
        }
        $controller = $type->get_rest_controller();
        if ( ! $controller instanceof \WP_REST_Posts_Controller ) {
            return null;
        }
        $namespace = is_string( $type->rest_namespace ) && '' !== $type->rest_namespace ? $type->rest_namespace : 'wp/v2';
        $base      = is_string( $type->rest_base ) && '' !== $type->rest_base ? $type->rest_base : $type->name;
        if ( untrailingslashit( $request->get_route() ) !== '/' . $namespace . '/' . $base . '/' . $post_id ) {
            return null;
        }
        return $controller;
    }

    private static function writer( \WP_REST_Posts_Controller $controller, string $post_type ) {
        switch ( get_class( $controller ) ) {
            case 'WP_REST_Posts_Controller':
                return self::post_writer( $post_type );
            case 'WP_REST_Attachments_Controller':
                return self::attachment_writer( $post_type );
            default:
                return null;
        }
    }

    private static function post_writer( string $post_type ): \WP_REST_Posts_Controller {
        return new class( $post_type ) extends \WP_REST_Posts_Controller {
            use Narrow_Post_Steps;

            protected function beside_post_fields(): array {
                $properties = $this->get_item_schema()['properties'];
                $fields     = array_values( array_intersect( array_merge( array( 'meta' ), Narrow_Post_Write::BEFORE_META ), array_keys( $properties ) ) );
                $fields     = array_merge( $fields, Narrow_Post_Write::taxonomy_fields( $this->post_type ) );
                foreach ( $this->get_additional_fields() as $name => $options ) {
                    if ( ! empty( $options['update_callback'] ) ) {
                        $fields[] = (string) $name;
                    }
                }
                return $fields;
            }
        };
    }

    private static function attachment_writer( string $post_type ): \WP_REST_Attachments_Controller {
        return new class( $post_type ) extends \WP_REST_Attachments_Controller {
            use Narrow_Post_Steps {
                write as private write_post_steps;
            }

            protected function beside_post_fields(): array {
                return array_values( array_intersect( array( 'alt_text', 'meta' ), array_keys( $this->get_item_schema()['properties'] ) ) );
            }

            public function write( \WP_REST_Request $request, int $post_id ) {
                $written = $this->write_post_steps( $request, $post_id );
                if ( true === $written && isset( $request['alt_text'] ) ) {
                    update_post_meta( $post_id, '_wp_attachment_image_alt', $request['alt_text'] );
                }
                return $written;
            }
        };
    }
}
