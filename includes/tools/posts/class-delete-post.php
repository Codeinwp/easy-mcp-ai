<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Post extends Base_Tool {

    public function get_name() {
        return 'wp_delete_post';
    }

    public function get_description() {
        return 'Deletes a WordPress post by ID. Required: `post_id`. Optional: `force` (default false) — false moves the post to trash (recoverable via `wp_update_post` setting status="publish"/"draft"); true permanently deletes the post and its meta/revisions (irreversible). Returns { deleted, id, title }. Prefer false (trash) unless permanent deletion is explicitly requested.';
    }

    public function get_category() {
        return 'posts';
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
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to delete.',
                ),
                'force'   => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to bypass the trash and force deletion. Default is false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
        $post = $id > 0 ? $this->peek( '/wp/v2/posts/' . $id, 'id,title,status' ) : null;
        if ( ! $post ) {
            return '';
        }
        $force = ! empty( $arguments['force'] ) && \rest_sanitize_boolean( $arguments['force'] );
        return sprintf(
            /* translators: 1: post title, 2: post id, 3: post status, 4: what happens */
            __( "Delete post '%1\$s' (ID %2\$d, %3\$s) — %4\$s.", 'easy-mcp-ai' ),
            self::rest_title( $post ), $id, isset( $post['status'] ) ? $post['status'] : 'unknown',
            $force ? __( 'permanently, bypassing the trash', 'easy-mcp-ai' ) : __( 'moves it to the trash', 'easy-mcp-ai' )
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );

        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $force   = isset( $arguments['force'] ) && (bool) $arguments['force'];
        $params  = array();

        if ( isset( $arguments['force'] ) ) {
            $params['force'] = $force;
        }

        $data = $this->rest_request( 'DELETE', '/wp/v2/posts/' . $post_id, $params );

        
        if ( $force ) {
            $post = $data['previous'] ?? array();
        } else {
            $post = $data;
        }

        return array(
            'deleted' => true,
            'id'      => $post['id'] ?? null,
            'title'   => $post['title']['raw'] ?? ( $post['title']['rendered'] ?? '' ),
        );
    }
}
