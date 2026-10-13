<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Page extends Base_Tool {

    public function get_name() {
        return 'wp_delete_page';
    }

    public function get_description() {
        return 'Deletes a WordPress page by ID. Required: `page_id`. Optional: `force` (default false) — false moves to trash (recoverable); true permanently deletes the page and its meta/revisions (irreversible). Child pages are NOT deleted when a parent is deleted. On a permanent delete (force=true), children are re-parented to the deleted page\'s own parent (not necessarily top-level). When the page is merely trashed (the default, force=false), children are left untouched. Returns { deleted, id, title }.';
    }

    public function get_category() {
        return 'pages';
    }

    public function get_required_capability() {
        return 'delete_pages';
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
                'page_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the page to delete.',
                ),
                'force'   => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to bypass the trash and force deletion. Default is false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'page_id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['page_id'] ) ? (int) $arguments['page_id'] : 0;
        $page = $id > 0 ? $this->peek( '/wp/v2/pages/' . $id, 'id,title,status' ) : null;
        if ( ! $page ) {
            return '';
        }
        $force = ! empty( $arguments['force'] ) && \rest_sanitize_boolean( $arguments['force'] );
        return sprintf(
            /* translators: 1: page title, 2: page id, 3: page status, 4: what happens */
            __( "Delete page '%1\$s' (ID %2\$d, %3\$s) — %4\$s.", 'easy-mcp-ai' ),
            self::rest_title( $page ), $id, isset( $page['status'] ) ? $page['status'] : 'unknown',
            $force ? __( 'permanently, bypassing the trash', 'easy-mcp-ai' ) : __( 'moves it to the trash', 'easy-mcp-ai' )
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'page_id' ) );

        $page_id = $this->parse_required_id( $arguments['page_id'], 'page_id' );
        $force   = isset( $arguments['force'] ) && (bool) $arguments['force'];
        $params  = array();

        if ( isset( $arguments['force'] ) ) {
            $params['force'] = $force;
        }

        $data = $this->rest_request( 'DELETE', '/wp/v2/pages/' . $page_id, $params );

        
        $page = $force ? ( $data['previous'] ?? array() ) : $data;

        return array(
            'deleted' => true,
            'id'      => $page['id'] ?? null,
            'title'   => $page['title']['raw'] ?? ( $page['title']['rendered'] ?? '' ),
        );
    }
}
