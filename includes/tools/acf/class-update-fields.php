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
        return 'Updates one or more ACF field values on a post or page, or on an ACF options page. Pass field names and values as an object in the "fields" parameter (e.g. {"my_field_name": "value"}). Field names are the canonical form; field keys (e.g. field_abc123) also work. For a post, send `post_id` (and `post_type`): the values go through the ACF REST write path, which reaches every field group assigned to the post, including groups with "Show in REST API" off. For an options page, send `options_page` with its slug instead (wp_acf_list_field_groups lists them under options_pages): the values are validated and saved as the options page\'s Save button does, so a value ACF refuses saves nothing and names the field; a Repeater value replaces all its rows. Any field that was not saved is listed in `fields_ignored` with a `notice` giving the reason (misspelled name, or a group not assigned to this post or page). Options pages need ACF PRO or Secure Custom Fields. On a post, only the fields are written and the post itself is not saved (its modified date and revisions stay as they are); send `resave: true` to save the whole post too.';
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
                'post_id'      => array( 'type' => 'integer', 'description' => 'The ID of the post, page, or CPT item to update ACF fields on. Required unless options_page is sent.' ),
                'post_type'    => array( 'type' => 'string',  'description' => 'REST base of the post type (e.g. "posts", "pages"). Defaults to "posts". A post_type slug is also accepted. Not used with options_page.', 'default' => 'posts' ),
                'options_page' => array( 'type' => 'string',  'description' => 'Slug of an ACF options page (e.g. "general-settings") to update instead of a post; send it without post_id. wp_acf_list_field_groups lists the slugs under options_pages.' ),
                'fields'       => array( 'type' => 'object',  'description' => 'Key-value pairs of ACF field names and their new values. Use field names (e.g. my_field_name); field keys (e.g. field_abc123) also work. Group, Repeater and Flexible Content values take sub-field names, as wp_acf_get_fields returns them.' ),
                'resave'       => self::resave_property( 'ACF fields' ),
            ),
            'required' => array( 'fields' ),
        );
    }

    public function precheck( array $arguments ): void {
        $page = $this->acf_options_page_argument( $arguments );
        if ( null !== $page ) {
            $this->require_acf();
            Acf_Options_Page::resolve( $page );
            return;
        }
        try {
            $post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
        } catch ( \InvalidArgumentException $e ) {
            return;
        }
        $this->acf_post_route( ! empty( $arguments['post_type'] ) ? $this->acf_post_base( $arguments['post_type'] ) : 'posts', $post_id, 'update' );
    }

    public function execute( array $arguments ) {
        $this->require_acf();
        $page = $this->acf_options_page_argument( $arguments );
        if ( null !== $page ) {
            $this->validate_required( $arguments, array( 'fields' ) );
            if ( ! empty( $arguments['resave'] ) ) {
                throw new \InvalidArgumentException( 'resave saves a post; an options page is not one. Send it without resave.' );
            }
            return Acf_Options_Page::write( $page, $this->parse_json_param( $arguments['fields'], 'fields' ) );
        }
        $this->validate_required( $arguments, array( 'post_id', 'fields' ) );
        $post_id   = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $post_type = ! empty( $arguments['post_type'] ) ? $this->acf_post_base( $arguments['post_type'] ) : 'posts';
        $route     = $this->acf_post_route( $post_type, $post_id, 'update' );
        $fields    = $this->parse_json_param( $arguments['fields'], 'fields' );
        return array( 'post_id' => $post_id, 'post_type' => $post_type )
            + $this->acf_write( 'post', $post_id, $route, $fields, ! empty( $arguments['resave'] ) );
    }
}
