<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Media extends Base_Tool {

    public function get_name() {
        return 'wp_update_media';
    }

    public function get_description() {
        return 'Updates metadata of an existing WordPress media attachment (PATCH semantics — does NOT replace the file). Required: `media_id`. Optional: `title`, `alt_text` (important for accessibility and SEO), `caption`, `description`. Returns { id, title, alt_text, source_url }. To replace the file itself, delete and re-upload. Passing an empty string for a text field (`title`, `alt_text`, `caption`, `description`) preserves the existing value (it is not cleared) — omit the field, or edit in wp-admin, to blank it.';
    }

    public function get_category() {
        return 'media';
    }

    public function get_required_capability() {
        return 'upload_files';
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
                'media_id'    => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the media item to update.',
                ),
                'title'       => array(
                    'type'        => 'string',
                    'description' => 'The new title for the media item.',
                ),
                'alt_text'    => array(
                    'type'        => 'string',
                    'description' => 'The new alt text for the media item.',
                ),
                'caption'     => array(
                    'type'        => 'string',
                    'description' => 'The new caption for the media item.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'The new description for the media item.',
                ),
            ),
            'required'   => array( 'media_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'media_id' ) );

        $media_id = $this->parse_required_id( $arguments['media_id'], 'media_id' );
        $params   = array();

        
        
        
        
        if ( isset( $arguments['title'] ) && '' !== $arguments['title'] ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['alt_text'] ) && '' !== $arguments['alt_text'] ) {
            $params['alt_text'] = sanitize_text_field( $arguments['alt_text'] );
        }

        if ( isset( $arguments['caption'] ) && '' !== $arguments['caption'] ) {
            $params['caption'] = sanitize_text_field( $arguments['caption'] );
        }

        if ( isset( $arguments['description'] ) && '' !== $arguments['description'] ) {
            $params['description'] = sanitize_text_field( $arguments['description'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/media/' . $media_id, $params );

        return array(
            'id'         => $data['id'],
            'title'      => $data['title']['raw'] ?? $data['title']['rendered'],
            'alt_text'   => $data['alt_text'] ?? '',
            'source_url' => $data['source_url'],
        );
    }
}
