<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Media extends Base_Tool {

    public function get_name() {
        return 'wp_get_media';
    }

    public function get_description() {
        return 'Gets a single WordPress media attachment by ID. Returns { id, title, alt_text, caption, description, mime_type, source_url (original file URL), media_type (image/video/audio/file), date, post (ID of the post it\'s attached to, 0 if unattached), terms: { "<taxonomy slug>": [term IDs] } (membership in every attachment taxonomy exposed in the REST API, e.g. media folders such as `mlo-category`; empty when none is registered — resolve the IDs with `wp_list_terms`) }. For image attachments the response also includes top-level `width`, `height`, and `file_size` (in bytes) when those details are available.';
    }

    public function get_category() {
        return 'media';
    }

    public function get_required_capability() {
        return 'edit_posts'; 
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
                'media_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the media item to retrieve.',
                ),
            ),
            'required'   => array( 'media_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'media_id' ) );

        $media_id = $this->parse_required_id( $arguments['media_id'], 'media_id' );
        $data     = $this->rest_request( 'GET', '/wp/v2/media/' . $media_id, array( 'context' => 'edit' ) );

        $result = array(
            'id'          => $data['id'],
            'title'       => $data['title']['raw'],
            'caption'     => $data['caption']['raw'],
            'description' => $data['description']['raw'],
            'alt_text'    => $data['alt_text'] ?? '',
            'mime_type'   => $data['mime_type'],
            'source_url'  => $data['source_url'],
            'date'        => $data['date'],
            'media_type'  => $data['media_type'],
            'post'        => isset( $data['post'] ) ? (int) $data['post'] : 0,
            'terms'       => $this->item_taxonomy_terms( $data, $this->rest_taxonomies_for( 'attachment' ) ),
        );

        if ( ! empty( $data['media_details']['width'] ) ) {
            $result['width'] = $data['media_details']['width'];
        }

        if ( ! empty( $data['media_details']['height'] ) ) {
            $result['height'] = $data['media_details']['height'];
        }

        if ( ! empty( $data['media_details']['filesize'] ) ) {
            $result['file_size'] = $data['media_details']['filesize'];
        }

        return $result;
    }
}
