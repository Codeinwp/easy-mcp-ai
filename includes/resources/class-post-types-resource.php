<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Post_Types_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/post-types';
    }

    public function get_name() {
        return 'Post Types';
    }

    public function get_description() {
        return 'All public registered post types with labels, REST bases, and supported features.';
    }

    public function read() {
        $types  = get_post_types( array( 'public' => true ), 'objects' );
        $result = array();

        foreach ( $types as $type ) {
            $result[] = array(
                'name'         => $type->name,
                'label'        => $type->label,
                'rest_base'    => ! empty( $type->rest_base ) ? $type->rest_base : $type->name,
                'has_archive'  => (bool) $type->has_archive,
                'hierarchical' => (bool) $type->hierarchical,
                'supports'     => get_all_post_type_supports( $type->name ),
            );
        }

        return array( 'post_types' => $result );
    }
}
