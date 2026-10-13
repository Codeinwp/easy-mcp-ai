<?php
namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Fields extends Base_Tool {
    use Acf_Rest_Tool;

    public function get_name() {
        return 'wp_acf_update_fields';
    }

    public function get_description() {
        return 'Updates one or more ACF field values on a post or page. Pass field names and values as an object in the "fields" parameter (e.g. {"my_field_name": "value"}). Field names are the canonical documented form and are resolved by the ACF REST write path; field keys (e.g. field_abc123) also work. Reaches every field group assigned to the post, including groups with "Show in REST API" off. Any field that was not saved is listed in `fields_ignored` with a `notice` giving the reason (misspelled name, or a group not assigned to this post).';
    }

    public function get_category() {
        return 'acf';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array( 'title' => $this->get_title(), 'readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'   => array( 'type' => 'integer', 'description' => 'The ID of the post, page, or CPT item to update ACF fields on.' ),
                'post_type' => array( 'type' => 'string',  'description' => 'REST base of the post type (e.g. "posts", "pages"). Defaults to "posts".', 'default' => 'posts' ),
                'fields'    => array( 'type' => 'object',  'description' => 'Key-value pairs of ACF field names and their new values. Use field names (e.g. my_field_name); field keys (e.g. field_abc123) also work.' ),
            ),
            'required' => array( 'post_id', 'fields' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_acf();
        $this->validate_required( $arguments, array( 'post_id', 'fields' ) );
        $post_id   = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $post_type = ! empty( $arguments['post_type'] ) ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' ) : 'posts';
        $fields    = $this->parse_json_param( $arguments['fields'], 'fields' );
        return array( 'post_id' => $post_id, 'post_type' => $post_type )
            + $this->acf_write( 'post', $post_id, '/wp/v2/' . $post_type . '/' . $post_id, $fields );
    }
}
