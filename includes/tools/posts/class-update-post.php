<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Post extends Base_Tool {

    public function get_name() {
        return 'wp_update_post';
    }

    public function get_description() {
        return 'Updates an existing WordPress post (PATCH semantics — only supplied fields change). Required: `post_id`. Optional: `title`, `content` (HTML/Gutenberg blocks), `status` (publish/draft/pending/private/future — "trash" is NOT accepted here and throws; to move a post to trash use `wp_delete_post`), `date` (ISO 8601, use with status="future" to reschedule), `excerpt`, `categories` (array of IDs, replaces existing), `tags` (array of IDs, replaces existing), `featured_media`, `slug`, `format`, `author`, `template` (theme file slug for the post template), `comment_status`, `ping_status`, `sticky`. Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, link, modified }. Passing an empty string for a text field (`title`, `content`, `excerpt`) preserves the existing value (it is not cleared) — omit the field, or edit in wp-admin, to blank it.';
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
                'post_id'        => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to update.',
                ),
                'title'          => array(
                    'type'        => 'string',
                    'description' => 'The new title for the post.',
                ),
                'content'        => array(
                    'type'        => 'string',
                    'description' => 'The new content for the post. HTML is allowed and will be sanitized.',
                ),
                'status'         => array(
                    'type'        => 'string',
                    'description' => 'The new status for the post. To move a post to trash, use wp_delete_post instead.',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                ),
                'excerpt'        => array(
                    'type'        => 'string',
                    'description' => 'The new excerpt for the post. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
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
                    'description' => 'URL-friendly slug for the post.',
                ),
                'date'           => array(
                    'type'        => 'string',
                    'description' => 'Publication date in ISO 8601 format. Useful for rescheduling.',
                ),
                'format'         => array(
                    'type'        => 'string',
                    'description' => 'The post format.',
                    'enum'        => array( 'standard', 'aside', 'chat', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio' ),
                ),
                'author'         => array(
                    'type'        => 'integer',
                    'description' => 'Reassign the post to this user ID.',
                ),
                'template'       => array(
                    'type'        => 'string',
                    'description' => 'The theme file slug to use as the post template.',
                ),
                'comment_status' => array(
                    'type'        => 'string',
                    'description' => 'Whether comments are open or closed.',
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
            'required'   => array( 'post_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );

        if ( isset( $arguments['status'] ) && 'trash' === $arguments['status'] ) {
            throw new \InvalidArgumentException( 'Cannot set status to "trash" via wp_update_post. To move a post to trash, use wp_delete_post instead.' );
        }

        $this->validate_title_length( $arguments['title'] ?? null );

        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $params  = array();

        
        
        
        
        if ( isset( $arguments['title'] ) && '' !== $arguments['title'] ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['content'] ) && '' !== $arguments['content'] ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }

        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }

        if ( isset( $arguments['excerpt'] ) && '' !== $arguments['excerpt'] ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }

        if ( isset( $arguments['categories'] ) ) {
            $params['categories'] = array_map( 'absint', $this->parse_json_param( $arguments['categories'], 'categories' ) );
        }

        if ( isset( $arguments['tags'] ) ) {
            $params['tags'] = array_map( 'absint', $this->parse_json_param( $arguments['tags'], 'tags' ) );
        }

        if ( isset( $arguments['featured_media'] ) ) {
            $params['featured_media'] = absint( $arguments['featured_media'] );
        }

        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }

        if ( ! empty( $arguments['date'] ) ) {
            $params['date'] = sanitize_text_field( $arguments['date'] );
        }

        if ( ! empty( $arguments['format'] ) ) {
            $params['format'] = sanitize_text_field( $arguments['format'] );
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

        
        
        
        
        $data = $this->rest_request( 'PUT', '/wp/v2/posts/' . $post_id, $params, 'id,title,status,modified,link' . ( $meta_sent ? ',meta' : '' ) );

        $result = array(
            'id'       => $data['id'],
            'title'    => $data['title']['raw'] ?? $data['title']['rendered'],
            'status'   => $data['status'],
            'modified' => $data['modified'],
            'link'     => $data['link'],
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], isset( $data['meta'] ) ? $data['meta'] : array() ) );
        }

        return $result;
    }
}
