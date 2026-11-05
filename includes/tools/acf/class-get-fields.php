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
        return 'Gets all ACF (Advanced Custom Fields) field values for a post or page, or for an ACF options page. Returns fields under the "acf_fields" key keyed by field name (e.g. "my_field_name"). For a post, send `post_id` (and `post_type`); this includes groups with "Show in REST API" off when you can edit the post, as the editor screen does. For an options page, send `options_page` with its slug instead (wp_acf_list_field_groups lists them under options_pages); you need the capability the page itself requires. To update fields with wp_acf_update_fields, pass these same field names (field keys like "field_abc123" also work). Works with ACF and Secure Custom Fields (SCF); options pages need ACF PRO or SCF.';
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
                'post_id'      => array( 'type' => 'integer', 'description' => 'The ID of the post, page, or custom post type item to retrieve ACF fields from. Required unless options_page is sent.' ),
                'post_type'    => array( 'type' => 'string',  'description' => 'REST base of the post type (e.g. "posts", "pages"). Defaults to "posts". A post_type slug is also accepted. Not used with options_page.', 'default' => 'posts' ),
                'options_page' => array( 'type' => 'string',  'description' => 'Slug of an ACF options page (e.g. "general-settings") to read instead of a post; send it without post_id. wp_acf_list_field_groups lists the slugs under options_pages.' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_acf();
        $page = $this->acf_options_page_argument( $arguments );
        if ( null !== $page ) {
            return Acf_Options_Page::read( $page );
        }
        $post_id   = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
        $post_type = ! empty( $arguments['post_type'] ) ? $this->acf_post_base( $arguments['post_type'] ) : 'posts';
        return array( 'post_id' => $post_id, 'post_type' => $post_type )
            + $this->acf_read( 'post', $post_id, $this->acf_post_route( $post_type, $post_id, 'get' ) );
    }
}
