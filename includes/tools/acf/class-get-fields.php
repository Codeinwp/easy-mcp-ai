<?php
namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Fields extends Base_Tool {
    use Acf_Rest_Tool;

    public function get_name() {
        return 'wp_acf_get_fields';
    }

    public function get_description() {
        return 'Gets all ACF (Advanced Custom Fields) field values for a post or page. Returns fields under the "acf_fields" key keyed by field name (e.g. "my_field_name"). Includes groups with "Show in REST API" off when you can edit the post, as the editor screen does. To update fields with wp_acf_update_fields, pass these same field names (field keys like "field_abc123" also work). Works with ACF and Secure Custom Fields (SCF).';
    }

    public function get_category() {
        return 'acf';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array( 'title' => $this->get_title(), 'readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'   => array( 'type' => 'integer', 'description' => 'The ID of the post, page, or custom post type item to retrieve ACF fields from.' ),
                'post_type' => array( 'type' => 'string',  'description' => 'REST base of the post type (e.g. "posts", "pages", or a CPT slug). Defaults to "posts".', 'default' => 'posts' ),
            ),
            'required' => array( 'post_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_acf();
        $post_id   = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
        $post_type = ! empty( $arguments['post_type'] ) ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' ) : 'posts';
        return array( 'post_id' => $post_id, 'post_type' => $post_type )
            + $this->acf_read( 'post', $post_id, '/wp/v2/' . $post_type . '/' . $post_id );
    }
}
