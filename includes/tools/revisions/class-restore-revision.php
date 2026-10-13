<?php
namespace Easy_MCP_AI\Tools\Revisions;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Restore_Revision extends Base_Tool {

    public function get_name() {
        return 'wp_restore_revision';
    }

    public function get_description() {
        return 'Restores a post to a specific revision. The current post state becomes a new revision (so the restore is itself undoable). Required: `revision_id`. Returns { restored: true, post_id, revision_id }. Note: WordPress core\'s `wp_restore_post_revision()` returns the parent post ID, not a revision ID. If you need the latest revision ID created as a side effect, call `wp_list_revisions` after this tool. Verifies edit_post capability on the parent post before restoring.';
    }

    public function get_category() {
        return 'revisions';
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
                'revision_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the revision to restore.',
                ),
            ),
            'required'   => array( 'revision_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'revision_id' ) );
        $revision_id = $this->parse_required_id( $arguments['revision_id'], 'revision_id' );
        $revision    = wp_get_post_revision( $revision_id );
        if ( ! $revision ) {
            throw new \RuntimeException( 'Revision not found.' );
        }
        $parent_id = (int) $revision->post_parent;
        if ( ! $parent_id || ! current_user_can( 'edit_post', $parent_id ) ) {
            throw new \RuntimeException( 'You do not have permission to restore this revision.' );
        }
        $result = wp_restore_post_revision( $revision_id );
        if ( false === $result ) {
            throw new \RuntimeException( 'Restore failed.' );
        }
        return array( 'restored' => true, 'post_id' => $parent_id, 'revision_id' => $revision_id );
    }
}
