<?php
namespace Easy_MCP_AI\Tools\Revisions;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Revision extends Base_Tool {

    public function get_name() {
        return 'wp_get_revision';
    }

    public function get_description() {
        return 'Gets a single post revision by ID. Required: `post_id` (the parent post ID) AND `revision_id` (the specific revision ID). Returns { id, author (user ID), date, title, content (raw markup), excerpt }. To restore a revision, use `wp_restore_revision` (preferred) rather than manually copying its `title`, `content`, and `excerpt` into `wp_update_post`. Use `wp_list_revisions` to discover revision IDs for a post. This tool is listed as `read` regardless of the parent post\'s type, but WordPress requires edit-level access on that specific parent (edit_posts for posts, edit_pages for pages, or a CPT\'s own capability) — a low-privilege caller may still see a permission error depending on which post_id is targeted.';
    }

    public function get_category() {
        return 'revisions';
    }

    
    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the parent post.',
                ),
                'revision_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the revision to retrieve.',
                ),
            ),
            'required'   => array( 'post_id', 'revision_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id', 'revision_id' ) );

        $post_id     = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $rest_base   = $this->resolve_post_rest_base( $post_id );
        $revision_id = $this->parse_required_id( $arguments['revision_id'], 'revision_id' );

        $data = $this->rest_request(
            'GET',
            '/wp/v2/' . $rest_base . '/' . $post_id . '/revisions/' . $revision_id,
            array( 'context' => 'edit' )
        );

        return array(
            'id'      => $data['id'],
            'author'  => $data['author'],
            'date'    => $data['date'],
            'title'   => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
            'content' => $data['content']['raw'] ?? wp_strip_all_tags( $data['content']['rendered'] ?? '' ),
            'excerpt' => $data['excerpt']['raw'] ?? wp_strip_all_tags( $data['excerpt']['rendered'] ?? '' ),
        );
    }
}
