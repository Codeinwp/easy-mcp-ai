<?php
namespace Easy_MCP_AI\Tools\Revisions;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Revision extends Base_Tool {

    public function get_name() {
        return 'wp_delete_revision';
    }

    public function get_description() {
        return 'Permanently deletes one post revision, but only where the site\'s own code allows it: WordPress core refuses to delete single revisions for every user, administrators included, to protect post history, so on a standard site this always fails and no role or Easy MCP setting changes that. To go back to an older version use wp_restore_revision; to limit how many revisions are kept, set WP_POST_REVISIONS in wp-config.php; a database-cleanup plugin or WP-CLI can purge old ones. Required: `post_id` and `revision_id` (both from `wp_list_revisions`). Returns { deleted, revision_id, post_id }.';
    }

    public function get_category() {
        return 'revisions';
    }

    public function get_required_capability() {
        return 'delete_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
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
                    'description' => 'The ID of the revision to delete.',
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

        
        
        
        
        
        
        
        
        $revision = get_post( $revision_id );
        if ( $revision && 'revision' === $revision->post_type && (int) $revision->post_parent === $post_id
            && current_user_can( 'delete_post', $post_id ) && ! current_user_can( 'delete_post', $revision_id ) ) {
            throw new \RuntimeException( 'WordPress core blocks deleting single revisions for every user, admins included; no role or setting change helps. Cap revisions with WP_POST_REVISIONS; to go back, use wp_restore_revision.' );
        }

        $data = $this->rest_request(
            'DELETE',
            '/wp/v2/' . $rest_base . '/' . $post_id . '/revisions/' . $revision_id,
            array( 'force' => true )
        );

        $deleted = is_array( $data ) && ! empty( $data['deleted'] );

        return array(
            'deleted'     => $deleted,
            'revision_id' => $revision_id,
            'post_id'     => $post_id,
        );
    }
}
