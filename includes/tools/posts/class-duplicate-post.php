<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Post_Type_Rest_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Duplicate_Post extends Base_Tool {

    const REFUSED_TYPES = array(
        'attachment'          => 'a media item',
        'revision'            => 'a revision',
        'nav_menu_item'       => 'a menu item',
        'wp_template'         => 'a site editor template',
        'wp_template_part'    => 'a site editor template part',
        'wp_global_styles'    => 'the site editor\'s global styles',
        'wp_navigation'       => 'a site editor navigation menu',
        'wp_font_family'      => 'an installed font',
        'wp_font_face'        => 'an installed font',
        'customize_changeset' => 'a Customizer changeset',
        'product'             => 'a WooCommerce product (its SKU must be unique and its lookup tables would not be copied)',
        'product_variation'   => 'a WooCommerce product variation',
    );

    const SKIPPED_META = array(
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_old_date',
        '_wp_desired_post_slug',
        '_encloseme',
        '_pingme',
        '_thumbnail_id',
        '_wp_page_template',
        '_elementor_css',
        '_elementor_element_cache',
        '_elementor_page_assets',
    );

    const SKIPPED_META_PREFIXES = array(
        '_wp_trash_meta_',
        '_dp_',
    );

    const STATUSES = array( 'draft', 'pending', 'private', 'publish' );

    public function get_name() {
        return 'wp_duplicate_post';
    }

    public function get_description() {
        return 'Duplicates a post, page or custom post type item into a new one of the same type. Required: `post_id`. Optional: `title` (default: the original\'s title), `status` (draft/pending/private/publish, default draft; Force draft on create applies). Copies the content, excerpt, terms of every taxonomy shown in the REST API, featured image, page template, post format, parent, menu order, comment and ping settings, password and post meta, page-builder layouts such as Elementor\'s included. Not copied: the date, slug (WordPress makes a new one), author (you become the author), sticky flag, comments, child pages and the original\'s editing history. Requires permission to edit the original and, for a custom post type item, the access wp_create_cpt_item needs. Refuses media, revisions, menu items, site editor entities and WooCommerce products. Returns { id, source_id, post_type, title, status, link, copied_meta_keys }, plus `skipped_meta_keys`, `skipped_taxonomies`, `skipped_template` or `skipped_fields` (menu_order, parent) when something was left out, `content_filtered: true` when WordPress filtered HTML from the content because you cannot post unfiltered HTML, and `status_error` when the copy was created but stayed a draft. An error after the copy was created (a meta key WordPress refused) names the draft.';
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
            'idempotentHint'  => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post, page or custom post type item to duplicate.',
                ),
                'title'   => array(
                    'type'        => 'string',
                    'description' => 'Title of the copy. Default: the original\'s title.',
                ),
                'status'  => array(
                    'type'        => 'string',
                    'description' => 'Status of the copy. "publish" and "private" need permission to publish this post type.',
                    'enum'        => self::STATUSES,
                    'default'     => 'draft',
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    public function precheck( array $arguments ): void {
        try {
            $post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
        } catch ( \InvalidArgumentException $e ) {
            return;
        }
        $this->checked_source( $post_id, $arguments );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );
        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        list( $source, $rest_base, $status ) = $this->checked_source( $post_id, $arguments );

        $route    = self::post_type_route( $rest_base, null, 'POST' );
        $fields   = $this->copied_fields( $source, $arguments );
        $created  = $this->create_copy( $route, (string) $source->post_type, $fields );
        $new_id   = (int) $created['id'];
        self::refuse_other_created_type( $new_id, (string) $source->post_type );
        try {
            $meta = self::copy_meta( (int) $source->ID, $new_id );
        } catch ( \Throwable $e ) {
            throw new Detailed_Tool_Error( sprintf( 'Post %d was created as a draft copy, but copying its meta failed: delete it, or finish it by hand.', $new_id ), 'The error was: ' . $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( array() !== $meta['failed'] ) {
            throw new Detailed_Tool_Error( sprintf( 'Post %1$d was created as a draft copy, but WordPress refused %2$d of its meta keys: delete it, or finish it by hand.', $new_id, count( $meta['failed'] ) ), self::refused_meta_details( $meta['failed'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $reply = array(
            'id'               => $new_id,
            'source_id'        => (int) $source->ID,
            'post_type'        => $source->post_type,
            'title'            => $fields['title'],
            'status'           => (string) $created['status'],
            'link'             => (string) ( $created['link'] ?? '' ),
            'copied_meta_keys' => $meta['copied'],
        );
        $reply = array_merge( $reply, $this->apply_status( $route . '/' . $new_id, $status ) );

        $copy = get_post( $new_id );
        if ( is_object( $copy ) && $copy->post_content !== $source->post_content ) {
            $reply['content_filtered'] = true;
        }
        if ( array() !== $meta['skipped'] ) {
            $reply['skipped_meta_keys'] = $meta['skipped'];
        }
        $unset = self::columns_not_stored( $source, $new_id );
        if ( array() !== $unset ) {
            $reply['skipped_fields'] = $unset;
        }
        return array_merge( $reply, $fields['skipped'] );
    }

    private function checked_source( int $post_id, array $arguments ): array {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || ! get_post_type_object( $post->post_type ) || ! current_user_can( 'read_post', $post_id ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d not found.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( self::REFUSED_TYPES[ $post->post_type ] ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d is %2$s, which this tool does not duplicate.', $post_id, self::REFUSED_TYPES[ $post->post_type ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \InvalidArgumentException( sprintf( 'You cannot duplicate post %d: duplicating needs permission to edit the original.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) && false === $this->caller_may_create_custom_items() ) {
            throw new \InvalidArgumentException( sprintf( 'Duplicating a "%s" item needs access to wp_create_cpt_item, which this connection does not have (for OAuth: the custom post types write permission).', $post->post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $rest_base = $this->resolve_post_rest_base( $post_id );
        $served    = Post_Type_Rest_Base::served_type( $rest_base );
        if ( null === $served || $served[0] !== $post->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d has post type "%2$s", which the REST API does not serve at /wp/v2/%3$s, so this tool cannot create its copy.', $post_id, $post->post_type, $rest_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        self::post_type_route( $rest_base, null, 'POST' );
        if ( isset( $arguments['title'] ) && '' !== $arguments['title'] ) {
            $this->validate_title_length( (string) $arguments['title'] );
        }
        return array( $post, $rest_base, self::checked_status( $arguments, get_post_type_object( $post->post_type ) ) );
    }

    protected function caller_may_create_custom_items(): ?bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) ) {
            return null;
        }
        return \Easy_MCP_AI\MCP\Server::current_call_may_use( 'wp_create_cpt_item' );
    }

    private static function refused_meta_details( array $failed ): string {
        $head  = 'Refused by a plugin or the database: ';
        $tail  = '. A key listed without a count has no value on the copy. Every other key was copied.';
        $list  = static function ( array $shown ) use ( $failed ): string {
            $left = count( $failed ) - count( $shown );
            if ( array() === $shown ) {
                return sprintf( '%d keys (compare the copy\'s meta with the original\'s)', $left );
            }
            return implode( ', ', $shown ) . ( $left > 0 ? sprintf( ' and %d more (compare the copy\'s meta with the original\'s)', $left ) : '' );
        };
        $shown = array();
        foreach ( $failed as $entry ) {
            $candidate = array_merge( $shown, array( $entry ) );
            if ( strlen( \Easy_MCP_AI\MCP\Server::sanitize_error_message( $head . $list( $candidate ) . $tail, PHP_INT_MAX ) ) > Detailed_Tool_Error::MAX_DETAILS ) {
                break;
            }
            $shown = $candidate;
        }
        return $head . $list( $shown ) . $tail;
    }

    private static function refuse_other_created_type( int $new_id, string $post_type ): void {
        $created_type = (string) get_post_type( $new_id );
        if ( $created_type === $post_type ) {
            return;
        }
        $what    = sprintf( 'Post %1$d was created as a "%2$s", not a "%3$s": another post type answers this REST route.', $new_id, $created_type, $post_type );
        $details = 'It held the original\'s title, content and other fields; no meta was copied to it. Plugins had already seen it saved, and the change history keeps its creation';
        if ( ! wp_delete_post( $new_id, true ) ) {
            throw new Detailed_Tool_Error( $what . ' It could not be deleted; delete it with wp_delete_post.', $details . '. A plugin refused to delete it.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        throw new Detailed_Tool_Error( $what . ' It was deleted again.', $details . ' and deletion.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function checked_status( array $arguments, object $post_type_object ): string {
        $status = isset( $arguments['status'] ) && '' !== $arguments['status'] ? (string) $arguments['status'] : 'draft';
        if ( ! in_array( $status, self::STATUSES, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid status: use one of %1$s (got "%2$s").', implode( ', ', self::STATUSES ), $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
            throw new \InvalidArgumentException( sprintf( 'You cannot give the copy status "%s": that needs permission to publish this post type. Use "draft" or "pending".', $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $status;
    }

    private function copied_fields( object $source, array $arguments ): array {
        $title = $source->post_title;
        if ( isset( $arguments['title'] ) && '' !== $arguments['title'] ) {
            $title = sanitize_text_field( (string) $arguments['title'] );
        }
        $params  = array(
            'status'         => 'draft',
            'title'          => $title,
            'content'        => $source->post_content,
            'excerpt'        => $source->post_excerpt,
            'comment_status' => $source->comment_status,
            'ping_status'    => $source->ping_status,
            'password'       => $source->post_password,
            'menu_order'     => (int) $source->menu_order,
            'parent'         => (int) $source->post_parent,
        );
        $skipped = array();

        $thumbnail = (int) get_post_thumbnail_id( (int) $source->ID );
        if ( $thumbnail > 0 ) {
            $params['featured_media'] = $thumbnail;
        }
        $format = get_post_format( (int) $source->ID );
        if ( is_string( $format ) && '' !== $format ) {
            $params['format'] = $format;
        }
        $template = (string) get_page_template_slug( (int) $source->ID );
        if ( '' !== $template ) {
            if ( array_key_exists( $template, wp_get_theme()->get_page_templates( null, $source->post_type ) ) ) {
                $params['template'] = $template;
            } else {
                $skipped['skipped_template'] = $template;
            }
        }

        $item_fields = self::rest_item_fields( get_post_type_object( $source->post_type ) );
        $columns     = array();
        if ( null !== $item_fields ) {
            foreach ( array( 'menu_order' => 'menu_order', 'parent' => 'post_parent' ) as $field => $column ) {
                if ( ! in_array( $field, $item_fields, true ) && 0 !== $params[ $field ] ) {
                    $columns[ $column ] = $params[ $field ];
                }
            }
            $params = array_intersect_key( $params, array_flip( array_merge( $item_fields, array( 'status' ) ) ) );
        }

        $terms = $this->copied_terms( $source );
        if ( array() !== $terms['skipped'] ) {
            $skipped['skipped_taxonomies'] = $terms['skipped'];
        }
        return array(
            'title'   => isset( $params['title'] ) ? (string) $title : '',
            'params'  => array_merge( $params, $terms['params'] ),
            'columns' => $columns,
            'skipped' => $skipped,
        );
    }

    private function create_copy( string $route, string $post_type, array $fields ): array {
        if ( array() === $fields['columns'] ) {
            return $this->rest_request( 'POST', $route, $fields['params'], 'id,status,link' );
        }
        $copy    = self::build_rest_request( 'POST', $route, $fields['params'], 'id,status,link' );
        $pending = true;
        $set     = static function ( $prepared, $request ) use ( &$pending, $copy, $fields ) {
            if ( $pending && $prepared instanceof \stdClass && $request === $copy ) {
                $pending = false;
                foreach ( $fields['columns'] as $column => $value ) {
                    $prepared->$column = $value;
                }
            }
            return $prepared;
        };
        $hook = 'rest_pre_insert_' . $post_type;
        add_filter( $hook, $set, PHP_INT_MAX, 2 );
        try {
            return $this->dispatch_rest_request( $copy );
        } finally {
            remove_filter( $hook, $set, PHP_INT_MAX );
        }
    }

    private static function columns_not_stored( object $source, int $new_id ): array {
        $copy  = get_post( $new_id );
        $unset = array();
        foreach ( array( 'menu_order' => 'menu_order', 'post_parent' => 'parent' ) as $column => $name ) {
            if ( ! is_object( $copy ) || (int) $copy->$column !== (int) $source->$column ) {
                $unset[] = $name;
            }
        }
        return $unset;
    }

    private function copied_terms( object $source ): array {
        $rest_bases = $this->rest_taxonomies_for( $source->post_type );
        $params     = array();
        $skipped    = array();
        foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
            if ( 'post_format' === $taxonomy ) {
                continue;
            }
            $args = array( 'fields' => 'ids' );
            $tax  = get_taxonomy( $taxonomy );
            if ( is_object( $tax ) && ! empty( $tax->sort ) ) {
                $args['orderby'] = 'term_order';
            }
            $ids = wp_get_object_terms( (int) $source->ID, $taxonomy, $args );
            if ( ! is_array( $ids ) ) {
                continue;
            }
            if ( isset( $rest_bases[ $taxonomy ] ) ) {
                $params[ $rest_bases[ $taxonomy ] ] = array_map( 'intval', $ids );
            } elseif ( array() !== $ids ) {
                $skipped[] = $taxonomy;
            }
        }
        return array(
            'params'  => $params,
            'skipped' => $skipped,
        );
    }

    public static function copy_meta( int $source_id, int $copy_id ): array {
        $all     = get_post_meta( $source_id );
        $copied  = array();
        $skipped = array();
        foreach ( array_keys( is_array( $all ) ? $all : array() ) as $key ) {
            $key    = (string) $key;
            $values = (array) get_post_meta( $source_id, $key );
            if ( self::is_skipped_meta( $key ) || self::holds_unloadable_object( $values ) ) {
                $skipped[] = $key;
            } else {
                $copied[ $key ] = $values;
            }
        }
        $failed = array();
        foreach ( $copied as $key => $values ) {
            self::delete_exact_meta( $copy_id, (string) $key );
            $refused = 0;
            foreach ( $values as $value ) {
                if ( ! add_post_meta( $copy_id, wp_slash( (string) $key ), self::slash_strings( $value ) ) ) {
                    ++$refused;
                }
            }
            if ( $refused > 0 ) {
                $failed[] = count( $values ) === $refused ? (string) $key : sprintf( '%1$s (%2$d of %3$d values)', $key, $refused, count( $values ) );
            }
        }
        return array(
            'copied'  => count( $copied ) - count( $failed ),
            'skipped' => $skipped,
            'failed'  => $failed,
        );
    }

    private static function delete_exact_meta( int $post_id, string $key ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- meta IDs of one post, read once for this delete.
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, $key ) );
        foreach ( (array) $rows as $row ) {
            if ( $key === $row->meta_key ) {
                delete_metadata_by_mid( 'post', (int) $row->meta_id );
            }
        }
    }

    private static function holds_unloadable_object( $value ): bool {
        if ( $value instanceof \__PHP_Incomplete_Class ) {
            return true;
        }
        if ( is_object( $value ) ) {
            $value = get_object_vars( $value );
        }
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( self::holds_unloadable_object( $item ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function is_skipped_meta( string $key ): bool {
        if ( in_array( $key, self::SKIPPED_META, true ) ) {
            return true;
        }
        foreach ( self::SKIPPED_META_PREFIXES as $prefix ) {
            if ( 0 === strpos( $key, $prefix ) ) {
                return true;
            }
        }
        return false;
    }

    public static function slash_strings( $value ) {
        return map_deep(
            $value,
            static function ( $item ) {
                return is_string( $item ) ? addslashes( $item ) : $item;
            }
        );
    }

    private function apply_status( string $route, string $status ): array {
        $params = array( 'status' => $status );
        $this->maybe_force_draft( $params );
        if ( 'draft' === $params['status'] ) {
            return array();
        }
        try {
            $updated = $this->rest_request( 'POST', $route, $params, 'id,status,link' );
        } catch ( \RuntimeException $e ) {
            return array( 'status_error' => sprintf( 'The copy was created as a draft; setting status "%1$s" failed: %2$s', $status, $e->getMessage() ) );
        }
        return array(
            'status' => (string) $updated['status'],
            'link'   => (string) ( $updated['link'] ?? '' ),
        );
    }
}
