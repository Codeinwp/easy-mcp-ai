<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Comment extends Base_Tool {

    public function get_name() {
        return 'wp_get_comment';
    }

    public function get_description() {
        return 'Gets a single WordPress comment by ID. Returns { id, post (post ID), author_name, author_email, content (raw), status (approve/hold/spam/trash), date, parent (0 if top-level), link }. Use `wp_list_comments` to discover comment IDs.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'comment_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the comment to retrieve.',
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'comment_id' ) );

        $comment_id = $this->parse_required_id( $arguments['comment_id'], 'comment_id' );
        $data       = $this->rest_request( 'GET', '/wp/v2/comments/' . $comment_id, array( 'context' => 'edit' ) );

        return self::format( $data );
    }

    














    public static function format( array $data ) {
        return array(
            'id'           => $data['id'],
            'post'         => $data['post'],
            'author_name'  => $data['author_name'],
            'author_email' => $data['author_email'],
            'content'      => $data['content']['raw'],
            'status'       => $data['status'],
            'date'         => $data['date'],
            'parent'       => $data['parent'],
            'link'         => $data['link'],
        );
    }
}
