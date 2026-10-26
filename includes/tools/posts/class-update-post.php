<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Post extends Base_Tool {

    
    const CLEARABLE = array( 'title', 'content', 'excerpt' );

    






    const ECHOED_FIELDS = array( 'slug', 'date', 'date_gmt', 'author', 'sticky', 'comment_status', 'ping_status', 'format', 'template', 'featured_media', 'categories', 'tags' );

    public function get_name() {
        return 'wp_update_post';
    }

    public function get_description() {
        return 'Updates an existing WordPress post (PATCH semantics — only supplied fields change). Required: `post_id`. Optional: `title`, `content` (HTML/Gutenberg blocks), `status` (publish/draft/pending/private/future, or any other non-internal status registered on this site — "trash" is NOT accepted here and throws; to move a post to trash use `wp_delete_post`), `date` (ISO 8601 site time, use with status="future" to reschedule), `date_gmt` (ISO 8601 GMT; send `date` or `date_gmt`, not both), `excerpt`, `categories` (array of IDs, replaces existing), `tags` (array of IDs, replaces existing), `tag_names` (array of tag names or slugs; with `tags` they replace the post\'s tags, and a missing tag is created), `tax_input` (object: taxonomy slug → array of term IDs; each list replaces that taxonomy\'s terms), `password` (an empty string removes it), `featured_media`, `slug`, `format`, `author`, `template` (theme file slug for the post template), `comment_status`, `ping_status`, `sticky`, `clear` (array of title/content/excerpt to set to an empty string). Optional `meta` (object of meta key-value pairs; only REST-registered keys persist, including the SEO keys of enabled plugin integrations, and dropped keys are reported back as `meta_ignored` with a `notice`). Returns { id, title, status, link, modified, updated }, where `updated` maps each sent field among slug, date, date_gmt, author, sticky, comment_status, ping_status, format, template, featured_media, categories, tags and excerpt to the value WordPress stored (a taken slug comes back with its suffix), plus `created_tag_ids` when `tag_names` was given. Passing an empty string for a text field (`title`, `content`, `excerpt`) preserves the existing value (it is not cleared) — name the field in `clear` to blank it. For CPT items use `wp_update_cpt_item` instead.';
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
                    'description' => 'The new status for the post. Besides the core statuses, any non-internal status registered on this site (a custom status) is accepted. To move a post to trash, use wp_delete_post instead.',
                    'enum'        => Create_Post::status_enum(),
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
                'tag_names'      => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Tag names or slugs. Together with any tags IDs they REPLACE the post\'s tags. An existing tag is matched by slug, then by name; a missing tag is created first (WordPress lets anyone who may assign tags create one). A tag created for a call that is then refused stays, unused.',
                ),
                'tax_input'      => array(
                    'type'        => 'object',
                    'description' => 'Terms per taxonomy: keys are taxonomy slugs registered for posts and shown in the REST API, values are arrays of term IDs, e.g. {"genre":[4,9]}. Each list REPLACES that taxonomy\'s terms (an empty list removes them). Do not name a taxonomy that categories, tags or tag_names already sets (category, post_tag). post_format is set with format.',
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
                    'description' => 'Publication date in ISO 8601 format, site time. Useful for rescheduling.',
                ),
                'date_gmt'       => array(
                    'type'        => 'string',
                    'description' => 'Publication date in GMT, ISO 8601. Send date or date_gmt, not both: the REST API ignores date_gmt when date is set.',
                ),
                'password'       => array(
                    'type'        => 'string',
                    'description' => 'Password that protects the post; an empty string removes the password. A password-protected post cannot be sticky.',
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
                'clear'          => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => self::CLEARABLE,
                    ),
                    'description' => 'Fields to set to an empty string: any of title, content, excerpt. An empty string passed for one of those fields keeps the current value; naming it here is how to blank it. WordPress refuses an update that would leave title, content and excerpt all empty.',
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    
    public function precheck( array $arguments ): void {
        $this->precheck_other_type( $arguments, 'post_id', 'post', 'update' );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );

        if ( isset( $arguments['status'] ) && 'trash' === $arguments['status'] ) {
            throw new \InvalidArgumentException( 'Cannot set status to "trash" via wp_update_post. To move a post to trash, use wp_delete_post instead.' );
        }

        $this->validate_title_length( $arguments['title'] ?? null );

        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $this->refuse_other_type( $post_id, 'post', 'update' );

        $clear  = $this->parse_clear( $arguments );
        $params = array_merge(
            self::text_params( $arguments ),
            $this->term_and_media_params( $arguments ),
            self::detail_params( $arguments )
        );

        Create_Post::apply_date_gmt_and_password( $arguments, $params );

        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        
        
        
        foreach ( $clear as $field ) {
            $params[ $field ] = '';
        }

        $this->refuse_builder_owned_write( $post_id, array_key_exists( 'content', $params ), (array) ( $params['meta'] ?? array() ) );

        $tag_names       = Create_Post::prepare_term_arguments( $arguments, $params );
        $created_tag_ids = null === $tag_names ? null : Create_Post::resolve_tag_names( $tag_names, $params );

        
        
        
        
        $echoed = self::echoed_fields( $params, self::ECHOED_FIELDS );
        $data   = $this->rest_request( 'PUT', '/wp/v2/posts/' . $post_id, $params, implode( ',', array_merge( array( 'id', 'title', 'status', 'modified', 'link' ), $echoed, $meta_sent ? array( 'meta' ) : array() ) ) );

        $result = $this->result( $data, $params, $created_tag_ids, $meta_sent );
        return self::with_updated( $result, $data, $echoed, $params, $post_id );
    }

    










    private static function text_params( array $arguments ): array {
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

        if ( isset( $arguments['excerpt'] ) && '' !== $arguments['excerpt'] ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }
        return $params;
    }

    







    private function term_and_media_params( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['categories'] ) ) {
            $params['categories'] = array_map( 'absint', $this->parse_json_param( $arguments['categories'], 'categories' ) );
        }

        if ( isset( $arguments['tags'] ) ) {
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
        return $params;
    }

    









    private function result( array $data, array $params, ?array $created_tag_ids, bool $meta_sent ): array {
        $result = array(
            'id'       => $data['id'],
            'title'    => $data['title']['raw'] ?? $data['title']['rendered'],
            'status'   => $data['status'],
            'modified' => $data['modified'],
            'link'     => $data['link'],
        );
        if ( null !== $created_tag_ids ) {
            $result['created_tag_ids'] = $created_tag_ids;
        }
        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'post', 'post' ) );
        }
        return $result;
    }

    






    public static function echoed_fields( array $params, array $echoable ): array {
        return array_values( array_filter( $echoable, static function ( $key ) use ( $params ) {
            return array_key_exists( $key, $params );
        } ) );
    }

    













    public static function with_updated( array $result, array $data, array $echoed, array $params, int $post_id ): array {
        $updated = array();
        foreach ( $echoed as $key ) {
            if ( array_key_exists( $key, $data ) ) {
                $updated[ $key ] = $data[ $key ];
            }
        }
        if ( array_key_exists( 'excerpt', $params ) ) {
            $post               = get_post( $post_id );
            $updated['excerpt'] = is_object( $post ) && isset( $post->post_excerpt ) ? (string) $post->post_excerpt : null;
        }
        if ( array() !== $updated ) {
            $result['updated'] = $updated;
        }
        return $result;
    }

    







    private function parse_clear( array $arguments ): array {
        if ( ! isset( $arguments['clear'] ) ) {
            return array();
        }
        $clear = $arguments['clear'];
        if ( is_string( $clear ) ) {
            $decoded = json_decode( $clear, true );
            $clear   = is_array( $decoded ) ? $decoded : array( $clear );
        }
        if ( ! is_array( $clear ) ) {
            throw new \InvalidArgumentException( 'clear must be an array of field names: title, content, excerpt.' );
        }
        $out = array();
        foreach ( $clear as $field ) {
            $field = is_string( $field ) ? $field : '';
            if ( ! in_array( $field, self::CLEARABLE, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'clear: unknown field "%s". Use title, content or excerpt.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( isset( $arguments[ $field ] ) && '' !== $arguments[ $field ] ) {
                throw new \InvalidArgumentException( sprintf( '%1$s is both given a value and named in clear; send one of them.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = $field;
        }
        return array_values( array_unique( $out ) );
    }
}
