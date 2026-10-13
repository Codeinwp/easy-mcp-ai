<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Media extends Base_Tool {

    public function get_name() {
        return 'wp_delete_media';
    }

    public function get_description() {
        return 'Permanently deletes a WordPress media attachment by ID. Required: `media_id`. Optional: `force` (boolean, default true). Media items bypass the trash — deletion is immediate and irreversible. Leave `force` at its default: passing `force=false` asks WordPress to trash the item instead, which fails with a 501 error on a default install, because trashing attachments requires the `MEDIA_TRASH` constant to be enabled in wp-config.php (it is off by default). The physical file on disk is also deleted along with all generated image sizes. Posts that reference this attachment via `featured_media` or inline `<img>` tags will show broken images. Returns { deleted: true, id }.';
    }

    public function get_category() {
        return 'media';
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
                'media_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the media item to delete.',
                ),
                'force'    => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to permanently delete rather than trash. Default true, and it should be left at true: attachments only support the trash when the MEDIA_TRASH constant is enabled in wp-config.php (off by default), so force=false returns a 501 rest_trash_not_supported error on a default install.',
                    'default'     => true,
                ),
            ),
            'required'   => array( 'media_id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['media_id'] ) ? (int) $arguments['media_id'] : 0;
        $media = $id > 0 ? $this->peek( '/wp/v2/media/' . $id, 'id,title,source_url,mime_type' ) : null;
        if ( ! $media ) {
            return '';
        }
        $file = isset( $media['source_url'] ) ? basename( (string) $media['source_url'] ) : '';
        return sprintf(
            /* translators: 1: media title, 2: file name, 3: media id */
            __( "Permanently delete media '%1\$s' (%2\$s, ID %3\$d) and its file.", 'easy-mcp-ai' ),
            self::rest_title( $media ), '' !== $file ? $file : __( 'unknown file', 'easy-mcp-ai' ), $id
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'media_id' ) );

        $media_id = $this->parse_required_id( $arguments['media_id'], 'media_id' );
        $force    = isset( $arguments['force'] ) ? (bool) $arguments['force'] : true;

        $data = $this->rest_request( 'DELETE', '/wp/v2/media/' . $media_id, array( 'force' => $force ) );

        
        
        
        $id = $data['previous']['id'] ?? null;

        return array(
            'deleted' => true,
            'id'      => $id,
        );
    }
}
