<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Post extends Base_Tool {

    public function get_name() {
        return 'wp_create_post';
    }

    public function get_description() {
        return 'Creates a new WordPress post. Required: `title`. Optional: `content` (HTML/Gutenberg blocks accepted; sanitized by WordPress per the calling user capability), `status` (publish/draft/pending/private/future — default draft; use "future" with `date` to schedule), `date` (ISO 8601, e.g. 2026-06-01T09:00:00), `excerpt`, `categories` (array of category IDs), `tags` (array of tag IDs), `featured_media` (attachment ID), `slug`, `format` (standard/aside/chat/gallery/link/image/quote/status/video/audio), `author` (user ID), `template` (theme file slug for the post template), `comment_status` (open/closed), `ping_status` (open/closed), `sticky`. If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, link, date }. Revisions are created automatically on each update.';
    }

    public function get_category() {
        return 'posts';
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
                'title'          => array(
                    'type'        => 'string',
                    'description' => 'The title for the post.',
                ),
                'content'        => array(
                    'type'        => 'string',
                    'description' => 'The content for the post. HTML is allowed and will be sanitized.',
                ),
                'status'         => array(
                    'type'        => 'string',
                    'description' => 'The status for the post. Use "future" with the "date" field to schedule a post.',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                    'default'     => 'draft',
                ),
                'date'           => array(
                    'type'        => 'string',
                    'description' => 'Scheduled publication date in ISO 8601 format (e.g. 2026-06-01T09:00:00). Required when status is "future".',
                ),
                'excerpt'        => array(
                    'type'        => 'string',
                    'description' => 'The excerpt for the post. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
                ),
                'categories'     => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of category IDs to assign to the post.',
                ),
                'tags'           => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of tag IDs to assign to the post.',
                ),
                'featured_media' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the featured media attachment.',
                ),
                'slug'           => array(
                    'type'        => 'string',
                    'description' => 'The slug (URL-friendly name) for the post.',
                ),
                'format'         => array(
                    'type'        => 'string',
                    'description' => 'The format for the post.',
                    'enum'        => array( 'standard', 'aside', 'chat', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio' ),
                ),
                'author'         => array(
                    'type'        => 'integer',
                    'description' => 'The user ID to assign as the post author.',
                ),
                'template'       => array(
                    'type'        => 'string',
                    'description' => 'The theme file slug to use as the post template.',
                ),
                'comment_status' => array(
                    'type'        => 'string',
                    'description' => 'Whether comments are open or closed for this post.',
                    'enum'        => array( 'open', 'closed' ),
                ),
                'ping_status'    => array(
                    'type'        => 'string',
                    'description' => 'Whether pingbacks/trackbacks are open or closed.',
                    'enum'        => array( 'open', 'closed' ),
                ),
                'sticky'         => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the post should be sticky.',
                ),
                'meta'           => array(
                    'type'        => 'object',
                    'description' => 'Meta key-value pairs to set. Only keys registered for the REST API persist, including the SEO keys of enabled plugin integrations (Rank Math, Yoast, SEOPress, The SEO Framework — see wp_update_post_meta for the list on this site); dropped keys are reported back as meta_ignored.',
                ),
            ),
            'required'   => array( 'title' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'title' ) );

        $this->validate_title_length( $arguments['title'] );

        $params = array(
            'title'  => sanitize_text_field( $arguments['title'] ),
            'status' => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'draft',
        );

        if ( isset( $arguments['content'] ) ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }

        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }

        if ( ! empty( $arguments['categories'] ) ) {
            $params['categories'] = array_map( 'absint', $this->parse_json_param( $arguments['categories'], 'categories' ) );
        }

        if ( ! empty( $arguments['tags'] ) ) {
            $params['tags'] = array_map( 'absint', $this->parse_json_param( $arguments['tags'], 'tags' ) );
        }

        if ( isset( $arguments['featured_media'] ) ) {
            $params['featured_media'] = absint( $arguments['featured_media'] );
        }

        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }

        if ( ! empty( $arguments['format'] ) ) {
            $params['format'] = sanitize_text_field( $arguments['format'] );
        }

        if ( ! empty( $arguments['date'] ) ) {
            $params['date'] = sanitize_text_field( $arguments['date'] );
        }

        if ( isset( $arguments['author'] ) ) {
            $params['author'] = absint( $arguments['author'] );
        }

        if ( ! empty( $arguments['template'] ) ) {
            $params['template'] = sanitize_text_field( $arguments['template'] );
        }

        if ( isset( $arguments['comment_status'] ) ) {
            $params['comment_status'] = sanitize_text_field( $arguments['comment_status'] );
        }

        if ( isset( $arguments['ping_status'] ) ) {
            $params['ping_status'] = sanitize_text_field( $arguments['ping_status'] );
        }

        if ( isset( $arguments['sticky'] ) ) {
            $params['sticky'] = (bool) $arguments['sticky'];
        }

        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        if ( isset( $params['status'] ) && 'future' === $params['status'] && empty( $params['date'] ) ) {
            throw new \InvalidArgumentException( 'The "date" field is required when status is "future".' );
        }

        $this->maybe_force_draft( $params );

        
        
        
        $data = $this->rest_request( 'POST', '/wp/v2/posts', $params, 'id,title,status,link,date' . ( $meta_sent ? ',meta' : '' ) );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'],
            'date'   => $data['date'],
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], isset( $data['meta'] ) ? $data['meta'] : array() ) );
        }

        return $result;
    }
}
