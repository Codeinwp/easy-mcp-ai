<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\CPT\Create_CPT_Item;
use Easy_MCP_AI\Tools\Posts\Update_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Page extends Base_Tool {
    const ECHOED_FIELDS = array( 'slug', 'date', 'date_gmt', 'author', 'parent', 'menu_order', 'template', 'featured_media', 'comment_status', 'ping_status' );

    public function get_name() {
        return 'wp_update_page';
    }

    public function get_description() {
        return 'Updates an existing WordPress page (PATCH semantics — only supplied fields change). Required: `page_id`. Optional: `title`, `content` (HTML/Gutenberg blocks), `status` (publish/draft/pending/private/future or a custom status registered on this site — "trash" is NOT accepted here and is rejected by the WordPress REST API as an internal status; to move a page to trash use `wp_delete_page`), `date` (ISO 8601, site time, use with status="future" to reschedule) or `date_gmt` (UTC) — never both, `excerpt`, `parent` (re-parent the page — 0 for top-level), `template`, `menu_order`, `slug`, `author`, `featured_media`, `comment_status`, `ping_status`, `password` (an empty string removes the password), `tax_input` ({ taxonomy slug: [term IDs] }, replacing that taxonomy\'s terms; only taxonomies registered on pages and shown in the REST API). Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, modified, link, updated }, where `updated` maps each sent field among slug, date, date_gmt, author, parent, menu_order, template, featured_media, comment_status, ping_status and excerpt to the value WordPress stored (a taken slug comes back with its suffix). Passing an empty string for a text field (`title`, `content`, `excerpt`) preserves the existing value (it is not cleared) — to blank one, list it in `clear` (e.g. `clear: ["excerpt"]`). For CPT items use `wp_update_cpt_item` instead.';
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
                'page_id'    => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the page to update.',
                ),
                'title'      => array(
                    'type'        => 'string',
                    'description' => 'The new title for the page.',
                ),
                'content'    => array(
                    'type'        => 'string',
                    'description' => 'The new content for the page. HTML is allowed and will be sanitized.',
                ),
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'The new status for the page: any status the REST API accepts on this site (core: publish, draft, pending, private, future; plus custom statuses plugins registered). To move a page to trash, use wp_delete_page instead.',
                    'enum'        => Create_CPT_Item::status_enum(),
                ),
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the new parent page.',
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
                    'description' => 'Publication date in ISO 8601 format. Useful for rescheduling.',
                ),
                'date_gmt'   => array(
                    'type'        => 'string',
                    'description' => 'New publication date in UTC, ISO 8601. Use instead of date, never with it: WordPress uses date and ignores date_gmt when both are present.',
                ),
                'password'   => array(
                    'type'        => 'string',
                    'description' => 'New password protecting the page\'s content and excerpt; an empty string removes the password.',
                ),
                'tax_input'  => array(
                    'type'        => 'object',
                    'description' => 'Terms to set, as { taxonomy slug: [term IDs] }. Each listed taxonomy\'s terms are replaced by the list given; an empty list removes them all. Only taxonomies registered for pages and shown in the REST API are accepted (WordPress core registers none for pages; a plugin may); every term ID must exist in that taxonomy.',
                ),
                'clear'      => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => array( 'title', 'content', 'excerpt' ),
                    ),
                    'description' => 'Fields to set to an empty string: any of title, content, excerpt. An empty string passed for one of those fields keeps the current value; naming it here is how to blank it. A field listed here must not also be sent with a value.',
                ),
                'excerpt'    => array(
                    'type'        => 'string',
                    'description' => 'Page excerpt. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
                ),
                'author'     => array(
                    'type'        => 'integer',
                    'description' => 'Reassign the page to this user ID.',
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
            'required'   => array( 'page_id' ),
        );
    }

    public function precheck( array $arguments ): void {
        $this->precheck_other_type( $arguments, 'page_id', 'page', 'update' );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'page_id' ) );

        $this->validate_title_length( $arguments['title'] ?? null );

        $page_id = $this->parse_required_id( $arguments['page_id'], 'page_id' );
        $this->refuse_other_type( $page_id, 'page', 'update' );

        $params = array_merge(
            self::content_params( $arguments ),
            self::detail_params( $arguments ),
            self::cli_params( $arguments )
        );

        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        if ( isset( $arguments['tax_input'] ) ) {
            $params = array_merge( $params, Create_CPT_Item::tax_input_params( $arguments['tax_input'], 'page', $params ) );
        }

        $this->refuse_builder_owned_write( $page_id, array_key_exists( 'content', $params ), (array) ( $params['meta'] ?? array() ) );

        $echoed = Update_Post::echoed_fields( $params, self::ECHOED_FIELDS );
        $data   = $this->rest_request( 'PUT', '/wp/v2/pages/' . $page_id, $params, implode( ',', array_merge( array( 'id', 'title', 'status', 'modified', 'link' ), $echoed, $meta_sent ? array( 'meta' ) : array() ) ) );

        $result = array(
            'id'       => $data['id'],
            'title'    => $data['title']['raw'] ?? $data['title']['rendered'],
            'status'   => $data['status'],
            'modified' => $data['modified'],
            'link'     => $data['link'],
        );
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'post', 'page' ) );
        }

        return Update_Post::with_updated( $result, $data, $echoed, $params, $page_id );
    }

    private static function content_params( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['title'] ) && '' !== $arguments['title'] ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['content'] ) && '' !== $arguments['content'] ) {
            $params['content'] = $arguments['content'];
        }

        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
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

        if ( isset( $arguments['excerpt'] ) && '' !== $arguments['excerpt'] ) {
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
        foreach ( Create_CPT_Item::clear_fields( $arguments ) as $field ) {
            $params[ $field ] = '';
        }
        return $params;
    }
}
