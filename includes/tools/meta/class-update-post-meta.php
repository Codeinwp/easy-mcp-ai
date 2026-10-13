<?php
namespace Easy_MCP_AI\Tools\Meta;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Post_Meta extends Base_Tool {

    public function get_name() {
        return 'wp_update_post_meta';
    }

    public function get_description() {
        $description = 'Updates REST-API-visible meta fields for a post. Only fields registered with show_in_rest can be updated. Pass a JSON object of key-value pairs. Nested arrays/objects are passed through as-is and are accepted only when the key is registered with an array or object show_in_rest schema; a value of the wrong type is refused with the key, its registered type and what was sent. Optional `post_type` (REST base, default `posts`) lets this tool also operate on pages or any custom post type, not just posts. Returns { post_id, updated_meta } with only the requested keys that were saved. If none was saved the call is refused, naming the keys (its details list the keys this post type accepts); if only some were, `ignored_keys` and `notice` come first. SEO plugin keys (Rank Math, Yoast, SEOPress, The SEO Framework) are writable here when that plugin\'s integration is enabled under Easy MCP AI → Tools → Plugins.';
        
        
        if ( class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
            $description .= \Easy_MCP_AI\Meta\Meta_Exposure::describe_writable_keys();
        }
        return $description . $this->acf_hint( ' ' );
    }

    












    private function acf_hint( $prefix = '' ) {
        if ( class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) && \Easy_MCP_AI\Meta\Meta_Exposure::is_group_enabled( 'acf' ) ) {
            return $prefix . 'ACF fields: use wp_acf_update_fields, not this tool. ';
        }
        return '';
    }

    public function get_category() {
        return 'meta';
    }

    






    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'   => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to update meta for.',
                ),
                'meta'      => array(
                    'type'        => 'object',
                    'description' => 'Object of meta key-value pairs to set or update.',
                ),
                'post_type' => array(
                    'type'        => 'string',
                    'description' => 'The REST base for the post type (e.g. posts, pages). Default: posts.',
                    'default'     => 'posts',
                ),
            ),
            'required'   => array( 'post_id', 'meta' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id', 'meta' ) );

        $meta = $this->parse_json_param( $arguments['meta'], 'meta' );

        $post_id   = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $post_type = ! empty( $arguments['post_type'] )
            ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' )
            : 'posts';

        
        
        
        
        
        $request = new \WP_REST_Request( 'POST', '/wp/v2/' . $post_type . '/' . $post_id );
        $request->set_header( 'content-type', 'application/json' );
        $request->set_body( wp_json_encode( array( 'meta' => $meta ) ) );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            throw new \RuntimeException( $this->explain_rejected_meta( $response, $meta, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        $read_data = $this->rest_request( 'GET', '/wp/v2/' . $post_type . '/' . $post_id, array(
            'context' => 'edit',
        ) );

        
        
        
        $persisted_meta = isset( $read_data['meta'] ) ? $read_data['meta'] : array();
        $saved          = array();
        $ignored_keys   = array();
        foreach ( array_keys( $meta ) as $key ) {
            if ( array_key_exists( $key, $persisted_meta ) ) {
                $saved[ $key ] = $persisted_meta[ $key ];
            } else {
                $ignored_keys[] = $key;
            }
        }

        if ( empty( $ignored_keys ) ) {
            return array(
                'post_id'      => $post_id,
                'updated_meta' => ! empty( $saved ) ? $saved : new \stdClass(),
            );
        }

        $notice = $this->ignored_keys_notice( $ignored_keys );

        
        
        
        if ( empty( $saved ) ) {
            $accepted = array_keys( $persisted_meta );
            throw new \Easy_MCP_AI\MCP\Detailed_Tool_Error(
                $this->none_saved_message( $ignored_keys ),
                $this->acf_hint() . $notice . ' Meta keys this post type accepts: ' . ( empty( $accepted ) ? 'none' : implode( ', ', $accepted ) ) . '.'
            );
        }

        
        
        return array(
            'ignored_keys' => $ignored_keys,
            'notice'       => $notice,
            'post_id'      => $post_id,
            'updated_meta' => $saved,
        );
    }

    






    private function ignored_keys_notice( array $ignored_keys ) {
        $notice = sprintf(
            'The following meta keys were sent but not persisted (they may not be registered with show_in_rest=true): %s. Meta fields must be registered by the theme or a plugin to be writable via the REST API.',
            implode( ', ', $ignored_keys )
        );
        if ( class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
            $explained = \Easy_MCP_AI\Meta\Meta_Exposure::explain_ignored_keys( $ignored_keys );
            if ( ! empty( $explained ) ) {
                $notice .= ' ' . implode( ' ', array_values( $explained ) );
            }
        }
        return $notice;
    }

    






    private function none_saved_message( array $keys ) {
        $head  = 'Nothing was saved. Not writable through the REST API for this post: ';
        $tail  = '. Details: why, and the keys it accepts.';
        $room  = \Easy_MCP_AI\MCP\Detailed_Tool_Error::MAX_MESSAGE - strlen( $head ) - strlen( $tail );
        $total = count( $keys );
        $shown = array();
        foreach ( $keys as $key ) {
            $candidate = array_merge( $shown, array( (string) $key ) );
            $left      = $total - count( $candidate );
            if ( strlen( implode( ', ', $candidate ) . ( $left > 0 ? sprintf( ' and %d more', $left ) : '' ) ) > $room ) {
                break;
            }
            $shown = $candidate;
        }
        $left = $total - count( $shown );
        $list = empty( $shown )
            ? sprintf( '%d keys', $total )
            : implode( ', ', $shown ) . ( $left > 0 ? sprintf( ' and %d more', $left ) : '' );
        return $head . $list . $tail;
    }

    

































    private function explain_rejected_meta( $response, array $meta, $post_id ) {
        $error   = $response->as_error();
        $message = $error ? $error->get_error_message() : 'REST error';
        $keys    = $this->rejected_meta_keys( $response, $error );
        if ( array() === $keys ) {
            return $message;
        }

        $post      = get_post( $post_id );
        $post_type = ( $post && ! empty( $post->post_type ) ) ? (string) $post->post_type : '';
        
        
        $registered = array();
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            $registered = get_registered_meta_keys( 'post', $post_type ) + get_registered_meta_keys( 'post', '' );
        }

        $parts = array();
        foreach ( $keys as $key ) {
            $sent = array_key_exists( $key, $meta ) ? wp_json_encode( $meta[ $key ] ) : 'nothing';
            if ( ! is_string( $sent ) ) {
                $sent = 'an unencodable value';
            } elseif ( strlen( $sent ) > 200 ) {
                $sent = substr( $sent, 0, 200 ) . '…';
            }
            if ( isset( $registered[ $key ] ) && is_array( $registered[ $key ] ) ) {
                $args = $registered[ $key ];
                $type = isset( $args['show_in_rest']['schema']['type'] ) ? $args['show_in_rest']['schema']['type'] : ( isset( $args['type'] ) ? $args['type'] : 'string' );
                $type = is_array( $type ) ? implode( '|', $type ) : (string) $type;
                if ( empty( $args['single'] ) ) {
                    $type .= ' (multiple values: send an array of them)';
                }
                $parts[] = sprintf( '"%s" is registered for post type "%s" as %s but received %s', $key, $post_type, $type, $sent );
            } else {
                $parts[] = sprintf( '"%s" received %s (no registered type found for post type "%s")', $key, $sent, $post_type );
            }
        }

        return sprintf(
            '%sMeta value rejected: %s %s. Send each value in its registered type; nested arrays/objects only for a key registered with an array/object show_in_rest schema.',
            $this->acf_hint(),
            rtrim( $message, '.' ) . '.',
            implode( '; ', $parts )
        );
    }

    











    private function rejected_meta_keys( $response, $error ) {
        $params = array();

        
        $payload = $response->get_data();
        if ( is_array( $payload ) ) {
            $this->collect_params_from_data( $params, $payload );
        }

        
        
        if ( $error instanceof \WP_Error ) {
            foreach ( $error->get_error_codes() as $code ) {
                $all_data = method_exists( $error, 'get_all_error_data' )
                    ? $error->get_all_error_data( $code )
                    : array( $error->get_error_data( $code ) );
                foreach ( $all_data as $data ) {
                    if ( is_array( $data ) ) {
                        $this->collect_params_from_data( $params, array( 'data' => $data ) );
                    }
                }
            }
        }

        
        
        if ( array() === $params && $error instanceof \WP_Error ) {
            foreach ( $error->get_error_messages() as $text ) {
                if ( preg_match_all( '/\bmeta(?:\.[A-Za-z0-9_\-]+|\[[^\]]+\])/', (string) $text, $m ) ) {
                    $params = array_merge( $params, $m[0] );
                }
            }
        }

        $keys = array();
        foreach ( $params as $param ) {
            
            if ( preg_match( '/^meta(?:\.(.+)|\[(.+)\])$/', $param, $m ) ) {
                $keys[] = isset( $m[2] ) && '' !== $m[2] ? $m[2] : $m[1];
            }
        }
        return array_values( array_unique( $keys ) );
    }

    









    private function collect_params_from_data( array &$params, array $entry ) {
        $data_entries = array();
        if ( isset( $entry['data'] ) && is_array( $entry['data'] ) ) {
            $data_entries[] = $entry['data'];
        }
        if ( isset( $entry['additional_data'] ) && is_array( $entry['additional_data'] ) ) {
            foreach ( $entry['additional_data'] as $extra ) {
                if ( is_array( $extra ) ) {
                    $data_entries[] = $extra;
                }
            }
        }
        foreach ( $data_entries as $data ) {
            if ( isset( $data['param'] ) && is_string( $data['param'] ) ) {
                $params[] = $data['param'];
            }
            if ( isset( $data['details'] ) && is_array( $data['details'] ) ) {
                foreach ( $data['details'] as $detail ) {
                    if ( isset( $detail['data']['param'] ) && is_string( $detail['data']['param'] ) ) {
                        $params[] = $detail['data']['param'];
                    }
                }
            }
        }
        if ( isset( $entry['additional_errors'] ) && is_array( $entry['additional_errors'] ) ) {
            foreach ( $entry['additional_errors'] as $additional ) {
                if ( is_array( $additional ) ) {
                    unset( $additional['additional_errors'] ); 
                    $this->collect_params_from_data( $params, $additional );
                }
            }
        }
    }
}
