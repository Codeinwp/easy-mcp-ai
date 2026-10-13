<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Page extends Base_Tool {

    public function get_name() {
        return 'wp_create_page';
    }

    public function get_description() {
        return 'Creates a new WordPress page. Required: `title`. Optional: `content` (HTML/Gutenberg blocks; sanitized by WordPress per the calling user capability), `status` (publish/draft/pending/private/future — default draft; use "future" with `date` to schedule), `date` (ISO 8601), `excerpt`, `parent` (parent page ID — 0 = top-level; pages support hierarchical nesting), `template` (theme template file slug), `menu_order` (integer, for ordering), `slug`, `author` (user ID), `featured_media` (attachment ID), `comment_status` (open/closed), `ping_status` (open/closed). If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, link }.';
    }

    public function get_category() {
        return 'pages';
    }

    public function get_required_capability() {
        return 'edit_pages';
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
                'title'      => array(
                    'type'        => 'string',
                    'description' => 'The title for the page.',
                ),
                'content'    => array(
                    'type'        => 'string',
                    'description' => 'The content for the page. HTML is allowed and will be sanitized.',
                ),
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'The status for the page.',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future' ),
                    'default'     => 'draft',
                ),
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the parent page.',
                ),
                'template'   => array(
                    'type'        => 'string',
                    'description' => 'The page template to use.',
                ),
                'menu_order' => array(
                    'type'        => 'integer',
                    'description' => 'The order of the page in menus and lists.',
                ),
                'slug'       => array(
                    'type'        => 'string',
                    'description' => 'URL-friendly slug for the page.',
                ),
                'date'       => array(
                    'type'        => 'string',
                    'description' => 'Publication date in ISO 8601 format. Use with status "future" to schedule.',
                ),
                'excerpt'    => array(
                    'type'        => 'string',
                    'description' => 'Page excerpt. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
                ),
                'author'     => array(
                    'type'        => 'integer',
                    'description' => 'The user ID to assign as the page author.',
                ),
                'featured_media' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the featured media attachment.',
                ),
                'comment_status' => array(
                    'type'        => 'string',
                    'description' => 'Whether comments are open or closed.',
                    'enum'        => array( 'open', 'closed' ),
                ),
                'ping_status' => array(
                    'type'        => 'string',
                    'description' => 'Whether pingbacks/trackbacks are open or closed.',
                    'enum'        => array( 'open', 'closed' ),
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

        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }

        if ( isset( $arguments['template'] ) ) {
            $params['template'] = sanitize_text_field( $arguments['template'] );
        }

        if ( isset( $arguments['menu_order'] ) ) {
            $params['menu_order'] = intval( $arguments['menu_order'] );
        }

        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }

        if ( ! empty( $arguments['date'] ) ) {
            $params['date'] = sanitize_text_field( $arguments['date'] );
        }

        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }

        if ( isset( $arguments['author'] ) ) {
            $params['author'] = absint( $arguments['author'] );
        }

        if ( isset( $arguments['featured_media'] ) ) {
            $params['featured_media'] = absint( $arguments['featured_media'] );
        }

        if ( isset( $arguments['comment_status'] ) ) {
            $params['comment_status'] = sanitize_text_field( $arguments['comment_status'] );
        }

        if ( isset( $arguments['ping_status'] ) ) {
            $params['ping_status'] = sanitize_text_field( $arguments['ping_status'] );
        }

        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        if ( isset( $params['status'] ) && 'future' === $params['status'] && empty( $params['date'] ) ) {
            throw new \InvalidArgumentException( 'The "date" field is required when status is "future".' );
        }

        $this->maybe_force_draft( $params );

        
        
        
        $data = $this->rest_request( 'POST', '/wp/v2/pages', $params, 'id,title,status,link' . ( $meta_sent ? ',meta' : '' ) );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'],
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], isset( $data['meta'] ) ? $data['meta'] : array() ) );
        }

        return $result;
    }
}
