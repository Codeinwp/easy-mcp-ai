<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Comment extends Base_Tool {

    public function get_name() {
        return 'wp_delete_comment';
    }

    public function get_description() {
        return 'Deletes a WordPress comment by ID. Required: `comment_id`. Optional: `force` (default false) — false moves to trash (status becomes "trash", recoverable via `wp_update_comment` setting status="approve"); true permanently deletes (irreversible). Deleting a comment does not delete its replies — WordPress reparents child comments to the deleted comment\'s parent. Returns { deleted, id }.';
    }

    public function get_category() {
        return 'comments';
    }

    public function get_required_capability() {
        return 'moderate_comments';
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
                'comment_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the comment to delete.',
                ),
                'force'      => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to bypass the trash and force deletion. Default is false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['comment_id'] ) ? (int) $arguments['comment_id'] : 0;
        $comment = $id > 0 ? $this->peek( '/wp/v2/comments/' . $id, 'id,author_name,post,status' ) : null;
        if ( ! $comment ) {
            return '';
        }
        $force = ! empty( $arguments['force'] ) && \rest_sanitize_boolean( $arguments['force'] );
        return sprintf(
            /* translators: 1: comment id, 2: author name, 3: post id, 4: what happens */
            __( "Delete comment #%1\$d by '%2\$s' on post #%3\$d — %4\$s.", 'easy-mcp-ai' ),
            $id, isset( $comment['author_name'] ) ? \wp_strip_all_tags( (string) $comment['author_name'] ) : '?', isset( $comment['post'] ) ? (int) $comment['post'] : 0,
            $force ? __( 'permanently, bypassing the trash', 'easy-mcp-ai' ) : __( 'moves it to the trash', 'easy-mcp-ai' )
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'comment_id' ) );

        $comment_id = $this->parse_required_id( $arguments['comment_id'], 'comment_id' );
        $force      = isset( $arguments['force'] ) && (bool) $arguments['force'];
        $params     = array();

        if ( isset( $arguments['force'] ) ) {
            $params['force'] = $force;
        }

        $data = $this->rest_request( 'DELETE', '/wp/v2/comments/' . $comment_id, $params );

        
        $returned_id = $force ? ( $data['previous']['id'] ?? null ) : $data['id'];

        return array(
            'deleted' => true,
            'id'      => $returned_id,
        );
    }
}
