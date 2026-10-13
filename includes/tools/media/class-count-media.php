<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Count_Media extends Base_Tool {

    public function get_name() {
        return 'wp_count_media';
    }

    public function get_description() {
        return 'Returns attachment counts grouped by MIME type. Optional: `mime_type` (e.g. \'image/jpeg\') to filter to a single type. Returns { mime_type_filter (the `mime_type` echoed back, or "" when unfiltered), counts } where `counts` is the `wp_count_attachments()` object: { \'image/jpeg\': 12, \'image/png\': 5, ..., \'trash\': 3 } — each value is an INTEGER count, not an object and not a numeric string.';
    }

    public function get_category() {
        return 'media';
    }

    public function get_required_capability() {
        return 'read';
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
                'mime_type' => array(
                    'type'        => 'string',
                    'description' => 'Optional MIME type filter (e.g. "image/jpeg").',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $mime = isset( $arguments['mime_type'] ) && '' !== $arguments['mime_type']
            ? sanitize_text_field( (string) $arguments['mime_type'] )
            : '';
        $counts = $mime ? wp_count_attachments( $mime ) : wp_count_attachments();
        return array( 'mime_type_filter' => $mime, 'counts' => $this->normalize_counts( $counts ) );
    }
}
