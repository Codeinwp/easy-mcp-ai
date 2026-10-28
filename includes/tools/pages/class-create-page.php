<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\CPT\Create_CPT_Item;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Page extends Base_Tool {

    public function get_name() {
        return 'wp_create_page';
    }

    public function get_description() {
        return 'Creates a new WordPress page. Required: `title` (unless `allow_empty_title` is true and `content` or `excerpt` is given). Optional: `content` (HTML/Gutenberg blocks; sanitized by WordPress per the calling user capability), `status` (default draft; publish/draft/pending/private/future or a custom status registered on this site; use "future" with `date` or `date_gmt` to schedule), `date` (ISO 8601, site time) or `date_gmt` (UTC) — never both, `excerpt`, `parent` (parent page ID — 0 = top-level; pages support hierarchical nesting), `template` (theme template file slug), `menu_order` (integer, for ordering), `slug`, `author` (user ID), `featured_media` (attachment ID), `comment_status` (open/closed), `ping_status` (open/closed), `password` (protects the content and excerpt), `tax_input` ({ taxonomy slug: [term IDs] } for taxonomies registered on pages and shown in the REST API — core registers none; every term must exist in that taxonomy), `allow_empty_title` (boolean, default false). If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, link }.';
    }

    public function get_category() {
        return 'pages';
    }

    public function get_required_capability() {
        return 'edit_pages';
    }

    public function get_redacted_arguments() {
        return array( 'password' );
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
                    'description' => 'The title for the page. Required unless allow_empty_title is true.',
                ),
                'content'    => array(
                    'type'        => 'string',
                    'description' => 'The content for the page. HTML is allowed and will be sanitized.',
                ),
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'The status for the page: any status the REST API accepts on this site (core: publish, draft, pending, private, future; plus custom statuses plugins registered). "future" needs date or date_gmt.',
                    'enum'        => Create_CPT_Item::status_enum(),
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
                'date_gmt'   => array(
                    'type'        => 'string',
                    'description' => 'Publication date in UTC, ISO 8601 (e.g. 2026-06-01T09:00:00). Use instead of date, never with it: WordPress uses date and ignores date_gmt when both are present.',
                ),
                'password'   => array(
                    'type'        => 'string',
                    'description' => 'Password that protects the page\'s content and excerpt.',
                ),
                'tax_input'  => array(
                    'type'        => 'object',
                    'description' => 'Terms to assign, as { taxonomy slug: [term IDs] }. Only taxonomies registered for pages and shown in the REST API are accepted (WordPress core registers none for pages; a plugin may); every term ID must exist in that taxonomy.',
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
                'allow_empty_title' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, title may be omitted or empty as long as content or excerpt is given. Default false, which keeps title required.',
                    'default'     => false,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        if ( Create_CPT_Item::allows_empty_title( $arguments ) ) {
            Create_CPT_Item::require_title_content_or_excerpt( $arguments );
        } else {
            $this->validate_required( $arguments, array( 'title' ) );
        }

        $params = array_merge(
            $this->content_params( $arguments ),
            self::detail_params( $arguments ),
            self::cli_params( $arguments )
        );

        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        if ( isset( $arguments['tax_input'] ) ) {
            $params = array_merge( $params, Create_CPT_Item::tax_input_params( $arguments['tax_input'], 'page', $params ) );
        }

        self::refuse_future_without_date( $params );

        $this->maybe_force_draft( $params );

        $data = $this->rest_request( 'POST', '/wp/v2/pages', $params, 'id,title,status,link' . ( $meta_sent ? ',meta' : '' ) );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'],
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'post', 'page' ) );
        }

        return $result;
    }

    private function content_params( array $arguments ): array {
        $params = array(
            'status' => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'draft',
        );
        if ( Create_CPT_Item::has_text( $arguments, 'title' ) ) {
            $this->validate_title_length( $arguments['title'] );
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

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
        return $params;
    }

    private static function detail_params( array $arguments ): array {
        $params = array();
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
        return $params;
    }

    private static function cli_params( array $arguments ): array {
        $params = array();
        Create_CPT_Item::refuse_both_dates( $arguments );
        if ( ! empty( $arguments['date_gmt'] ) ) {
            $params['date_gmt'] = sanitize_text_field( (string) $arguments['date_gmt'] );
        }
        if ( isset( $arguments['password'] ) ) {
            if ( ! is_string( $arguments['password'] ) ) {
                throw new \InvalidArgumentException( 'Invalid password: must be a string.' );
            }
            $params['password'] = $arguments['password'];
        }
        return $params;
    }

    private static function refuse_future_without_date( array $params ): void {
        if ( isset( $params['status'] ) && 'future' === $params['status'] && empty( $params['date'] ) && empty( $params['date_gmt'] ) ) {
            throw new \InvalidArgumentException( 'The "date" or "date_gmt" field is required when status is "future".' );
        }
    }
}
