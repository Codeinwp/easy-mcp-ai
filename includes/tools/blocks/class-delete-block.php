<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Block extends Base_Tool {

    public function get_name() {
        return 'wp_delete_block';
    }

    public function get_description() {
        return 'Deletes a reusable block (synced pattern) by ID. Required: `block_id`. Optional: `force` (default false) — false moves to trash; true permanently deletes. Posts that embed this block will show an error placeholder after deletion. Returns { deleted, id }. Use `wp_list_blocks` to find the ID.';
    }

    public function get_category() {
        return 'blocks';
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
                'block_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the reusable block to delete.',
                ),
                'force'    => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to bypass the trash and force deletion.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'block_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'block_id' ) );

        $block_id = $this->parse_required_id( $arguments['block_id'], 'block_id' );
        $force    = isset( $arguments['force'] ) && (bool) $arguments['force'];
        $params   = array();

        if ( isset( $arguments['force'] ) ) {
            $params['force'] = $force;
        }

        $data = $this->rest_request( 'DELETE', '/wp/v2/blocks/' . $block_id, $params );

        
        $returned_id = $force ? ( $data['previous']['id'] ?? null ) : $data['id'];

        return array(
            'deleted' => true,
            'id'      => $returned_id,
        );
    }
}
