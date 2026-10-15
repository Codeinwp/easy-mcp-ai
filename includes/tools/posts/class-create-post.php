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
        return 'Creates a new WordPress post. Required: `title` (unless `allow_empty_title` is true and `content` or `excerpt` is given). Optional: `content` (HTML/Gutenberg blocks accepted; sanitized by WordPress per the calling user capability), `status` (publish/draft/pending/private/future, or any other non-internal status registered on this site — default draft; use "future" with `date` or `date_gmt` to schedule), `date` (ISO 8601 site time, e.g. 2026-06-01T09:00:00), `date_gmt` (ISO 8601 GMT; send `date` or `date_gmt`, not both), `excerpt`, `categories` (array of category IDs), `tags` (array of tag IDs), `tag_names` (array of tag names or slugs; a missing tag is created, as WordPress does for tags typed in the editor), `tax_input` (object: taxonomy slug → array of term IDs, for any taxonomy of posts shown in the REST API), `password` (protects the post; a password-protected post cannot be sticky), `featured_media` (attachment ID), `slug`, `format` (standard/aside/chat/gallery/link/image/quote/status/video/audio), `author` (user ID), `template` (theme file slug for the post template), `comment_status` (open/closed), `ping_status` (open/closed), `sticky`, `allow_empty_title` (boolean, default false: WordPress itself refuses only a post whose title, content and excerpt are all empty). If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, link, date }, plus `created_tag_ids` when `tag_names` was given. Revisions are created automatically on each update.';
    }

    





    public function get_redacted_arguments() {
        return array( 'password' );
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
                    'description' => 'The status for the post. Use "future" with the "date" (or "date_gmt") field to schedule a post. Besides the core statuses, any non-internal status registered on this site (a custom status) is accepted, as the REST API accepts it.',
                    'enum'        => self::status_enum(),
                    'default'     => 'draft',
                ),
                'date'           => array(
                    'type'        => 'string',
                    'description' => 'Scheduled publication date in ISO 8601 format, site time (e.g. 2026-06-01T09:00:00). Required when status is "future" unless date_gmt is given.',
                ),
                'date_gmt'       => array(
                    'type'        => 'string',
                    'description' => 'Publication date in GMT, ISO 8601 (e.g. 2026-06-01T07:00:00). Send date or date_gmt, not both: the REST API ignores date_gmt when date is set.',
                ),
                'password'       => array(
                    'type'        => 'string',
                    'description' => 'Password that protects the post. A password-protected post cannot be sticky.',
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
                'tag_names'      => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Tag names or slugs to assign, together with any tags IDs. An existing tag is matched by slug, then by name; a missing tag is created first (WordPress lets anyone who may assign tags create one). A tag created for a call that is then refused stays, unused.',
                ),
                'tax_input'      => array(
                    'type'        => 'object',
                    'description' => 'Terms per taxonomy: keys are taxonomy slugs registered for posts and shown in the REST API, values are arrays of term IDs, e.g. {"genre":[4,9]}. Each list sets that taxonomy\'s terms. Do not name a taxonomy that categories, tags or tag_names already sets (category, post_tag). post_format is set with format.',
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
                'allow_empty_title' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, title may be omitted or empty as long as content or excerpt is given: WordPress refuses only a post whose title, content and excerpt are all empty. Default false, which keeps title required.',
                    'default'     => false,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $has_title = isset( $arguments['title'] ) && '' !== $arguments['title'];
        $this->require_title_or_body( $arguments, $has_title );

        if ( $has_title ) {
            $this->validate_title_length( $arguments['title'] );
        }

        $params = array_merge(
            self::body_params( $arguments, $has_title ),
            $this->term_and_media_params( $arguments ),
            self::detail_params( $arguments )
        );

        self::apply_date_gmt_and_password( $arguments, $params );

        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        self::refuse_future_without_date( $params );

        $tag_names = self::prepare_term_arguments( $arguments, $params );

        $this->maybe_force_draft( $params );

        
        
        $created_tag_ids = null === $tag_names ? null : self::resolve_tag_names( $tag_names, $params );

        
        
        
        $data = $this->rest_request( 'POST', '/wp/v2/posts', $params, 'id,title,status,link,date' . ( $meta_sent ? ',meta' : '' ) );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'],
            'date'   => $data['date'],
        );
        if ( null !== $created_tag_ids ) {
            $result['created_tag_ids'] = $created_tag_ids;
        }
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'post', 'post' ) );
        }

        return $result;
    }

    








    private function require_title_or_body( array $arguments, bool $has_title ): void {
        $allow_empty_title = isset( $arguments['allow_empty_title'] ) && rest_sanitize_boolean( $arguments['allow_empty_title'] );
        if ( ! $allow_empty_title ) {
            $this->validate_required( $arguments, array( 'title' ) );
        } elseif ( ! $has_title && ! self::non_empty_text( $arguments, 'content' ) && ! self::non_empty_text( $arguments, 'excerpt' ) ) {
            
            
            throw new \InvalidArgumentException( 'Missing required parameters: title, content or excerpt (at least one must be non-empty; WordPress refuses a post whose title, content and excerpt are all empty).' );
        }
    }

    







    private static function body_params( array $arguments, bool $has_title ): array {
        $params = array(
            'status' => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'draft',
        );
        if ( $has_title ) {
            $params = array_merge( array( 'title' => sanitize_text_field( $arguments['title'] ) ), $params );
        }

        if ( isset( $arguments['content'] ) ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }

        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }
        return $params;
    }

    






    private function term_and_media_params( array $arguments ): array {
        $params = array();
        if ( ! empty( $arguments['categories'] ) ) {
            $params['categories'] = array_map( 'absint', $this->parse_json_param( $arguments['categories'], 'categories' ) );
        }

        if ( ! empty( $arguments['tags'] ) ) {
            $params['tags'] = array_map( 'absint', $this->parse_json_param( $arguments['tags'], 'tags' ) );
        }

        if ( isset( $arguments['featured_media'] ) ) {
            $params['featured_media'] = absint( $arguments['featured_media'] );
        }
        return $params;
    }

    






    private static function detail_params( array $arguments ): array {
        $params = array();
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
        return $params;
    }

    






    private static function refuse_future_without_date( array $params ): void {
        if ( isset( $params['status'] ) && 'future' === $params['status'] && empty( $params['date'] ) && empty( $params['date_gmt'] ) ) {
            throw new \InvalidArgumentException( 'The "date" field is required when status is "future".' );
        }
    }

    

    










    public static function status_enum(): array {
        $core = array( 'publish', 'draft', 'pending', 'private', 'future' );
        if ( ! function_exists( 'get_post_stati' ) ) {
            return $core;
        }
        $registered = array();
        foreach ( (array) get_post_stati( array( 'internal' => false ) ) as $key => $name ) {
            $name = is_string( $name ) ? $name : ( is_string( $key ) ? $key : '' );
            if ( '' !== $name ) {
                $registered[] = $name;
            }
        }
        $extra = array_diff( $registered, $core, array( 'trash', 'auto-draft', 'inherit' ) );
        return array_values( array_unique( array_merge( $core, $extra ) ) );
    }

    












    public static function apply_date_gmt_and_password( array $arguments, array &$params ): void {
        if ( ! empty( $arguments['date_gmt'] ) ) {
            if ( ! empty( $params['date'] ) ) {
                throw new \InvalidArgumentException( 'Send date or date_gmt, not both: the REST API ignores date_gmt when date is set.' );
            }
            $params['date_gmt'] = sanitize_text_field( (string) $arguments['date_gmt'] );
        }
        if ( isset( $arguments['password'] ) ) {
            if ( ! is_string( $arguments['password'] ) ) {
                throw new \InvalidArgumentException( 'password must be a string.' );
            }
            
            
            $params['password'] = $arguments['password'];
        }
    }

    









    public static function prepare_term_arguments( array $arguments, array &$params ): ?array {
        $tag_names = isset( $arguments['tag_names'] ) ? self::tag_names_arg( $arguments['tag_names'] ) : null;

        if ( isset( $arguments['tax_input'] ) ) {
            self::apply_tax_input( self::tax_input_object( $arguments['tax_input'] ), $params, $tag_names );
        }

        return $tag_names;
    }

    







    private static function tag_names_arg( $raw ): array {
        if ( is_string( $raw ) ) {
            $decoded = json_decode( $raw, true );
            $raw     = is_array( $decoded ) ? $decoded : null;
        }
        if ( ! is_array( $raw ) || ( array() !== $raw && array_keys( $raw ) !== range( 0, count( $raw ) - 1 ) ) ) {
            throw new \InvalidArgumentException( 'tag_names must be an array of tag names.' );
        }
        $tag_names = array();
        foreach ( $raw as $name ) {
            $name = is_string( $name ) ? trim( $name ) : '';
            if ( '' === $name ) {
                throw new \InvalidArgumentException( 'tag_names entries must be non-empty strings.' );
            }
            $tag_names[] = $name;
        }
        return $tag_names;
    }

    







    private static function tax_input_object( $tax_input ): array {
        if ( is_string( $tax_input ) ) {
            $decoded   = json_decode( $tax_input, true );
            $tax_input = is_array( $decoded ) ? $decoded : null;
        }
        if ( ! is_array( $tax_input ) || ( array() !== $tax_input && array_keys( $tax_input ) === range( 0, count( $tax_input ) - 1 ) ) ) {
            throw new \InvalidArgumentException( 'tax_input must be an object of taxonomy slug → array of term IDs.' );
        }
        return $tax_input;
    }

    









    private static function apply_tax_input( array $tax_input, array &$params, ?array $tag_names ): void {
        $map = self::rest_taxonomy_bases( 'post' );
        foreach ( $tax_input as $taxonomy => $ids ) {
            $taxonomy = (string) $taxonomy;
            if ( 'post_format' === $taxonomy ) {
                throw new \InvalidArgumentException( 'tax_input: post_format is not in the REST API; use format instead.' );
            }
            if ( ! isset( $map[ $taxonomy ] ) ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'tax_input: "%s" is not a taxonomy of posts shown in the REST API. Valid: %s.',
                        $taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        empty( $map ) ? '(none)' : implode( ', ', array_keys( $map ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    )
                );
            }
            $base = $map[ $taxonomy ];
            if ( array_key_exists( $base, $params ) || ( 'post_tag' === $taxonomy && null !== $tag_names ) ) {
                throw new \InvalidArgumentException( sprintf( 'tax_input names %s, which another argument already sets (%s); send it once.', $taxonomy, 'post_tag' === $taxonomy ? 'tags or tag_names' : $base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $params[ $base ] = self::parse_term_ids( $ids, 'tax_input.' . $taxonomy );
        }
    }

    













    public static function resolve_tag_names( array $tag_names, array &$params ): array {
        $ids     = isset( $params['tags'] ) && is_array( $params['tags'] ) ? $params['tags'] : array();
        $created = array();
        $tax     = function_exists( 'get_taxonomy' ) ? get_taxonomy( 'post_tag' ) : null;
        $base    = is_object( $tax ) && ! empty( $tax->rest_base ) ? (string) $tax->rest_base : 'tags';
        foreach ( $tag_names as $name ) {
            $found = term_exists( $name, 'post_tag' );
            if ( is_array( $found ) && ! empty( $found['term_id'] ) ) {
                $ids[] = (int) $found['term_id'];
                continue;
            }
            $request = new \WP_REST_Request( 'POST', '/wp/v2/' . $base );
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( wp_json_encode( array( 'name' => $name ) ) );
            $request->set_param( '_fields', 'id' );
            $response = rest_do_request( $request );
            if ( $response->is_error() ) {
                $message = self::rest_error_message_or_empty( $response );
                throw new \RuntimeException( sprintf( 'Could not create tag "%s": %s', $name, '' === $message ? self::rest_status_message( 'POST', '/wp/v2/' . $base, $response ) : $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $data = $response->get_data();
            $id   = is_array( $data ) && isset( $data['id'] ) ? (int) $data['id'] : 0;
            if ( $id < 1 ) {
                throw new \RuntimeException( sprintf( 'Could not create tag "%s": the REST API returned no ID.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $created[] = $id;
            $ids[]     = $id;
        }
        $params['tags'] = array_values( array_unique( array_map( 'intval', $ids ) ) );
        return $created;
    }

    






    private static function rest_taxonomy_bases( string $object_type ): array {
        $map = array();
        foreach ( (array) get_object_taxonomies( $object_type, 'objects' ) as $key => $tax ) {
            if ( ! is_object( $tax ) || empty( $tax->show_in_rest ) ) {
                continue;
            }
            $slug         = ! empty( $tax->name ) ? (string) $tax->name : (string) $key;
            $map[ $slug ] = ! empty( $tax->rest_base ) ? (string) $tax->rest_base : $slug;
        }
        return $map;
    }

    








    private static function parse_term_ids( $ids, string $label ): array {
        if ( is_scalar( $ids ) ) {
            $ids = array( $ids );
        }
        if ( ! is_array( $ids ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an array of term IDs.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $out = array();
        foreach ( $ids as $id ) {
            if ( is_int( $id ) && $id > 0 ) {
                $out[] = $id;
            } elseif ( is_string( $id ) && ctype_digit( $id ) && (int) $id > 0 ) {
                $out[] = (int) $id;
            } else {
                throw new \InvalidArgumentException( sprintf( '%s: %s is not a term ID (a positive integer).', $label, is_scalar( $id ) ? (string) $id : gettype( $id ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        return array_values( array_unique( $out ) );
    }

    






    private static function non_empty_text( array $arguments, string $key ): bool {
        return isset( $arguments[ $key ] ) && is_string( $arguments[ $key ] ) && '' !== $arguments[ $key ];
    }
}
