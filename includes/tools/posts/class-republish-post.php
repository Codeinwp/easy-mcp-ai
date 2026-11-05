<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Builders\Builder_Registry;
use Easy_MCP_AI\Builders\Elementor_Adapter;
use Easy_MCP_AI\Builders\Layout_Adapter;
use Easy_MCP_AI\Builders\Layout_Lock;
use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Meta\Meta_Exposure;
use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Menus\Navigation_Labels;
use Easy_MCP_AI\Tools\Post_Type_Rest_Base;
use Easy_MCP_AI\Tools\Post_Type_Tool_Hint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Republish_Post extends Base_Tool {

    use Post_Copy_Fields;

    const COPY_STATUSES = array( 'draft', 'pending', 'private' );

    const YOAST_ORIGINAL_KEY = '_dp_original';

    const YOAST_REWRITE_KEY = '_dp_is_rewrite_republish_copy';

    const YOAST_HAS_COPY_KEY = '_dp_has_rewrite_republish_copy';

    const MENU_LOCK_WAIT = 3;

    const HOOKED_BLOCKS_META = '_wp_ignored_hooked_blocks';

    public function get_name() {
        return 'wp_republish_post';
    }

    public function get_description() {
        return 'Puts a reviewed draft copy live in place of its published original in one call, so the original keeps its ID, URL, comments and menu entries. Use it when the user approves a draft copy (from wp_duplicate_post, or Yoast Duplicate Post\'s Rewrite & Republish) and asks to put it live, publish it or make it replace the page: never publish the copy itself (that makes a second page at another URL), and never unpublish the original or move its slug. Required: `copy_id`. Optional: `original_id`, by default the post the copy records it was copied from; pass it for a draft made another way. Writes the copy\'s title, content, excerpt, featured image, template, format, terms and meta into the original; meta keys only the original has are kept. A copy built with Elementor brings its layout through Elementor\'s own save, and an Elementor original replaced by a copy built without Elementor is switched off Elementor. The original keeps its slug, status, date, author, parent, menu order and comment settings. Saves a revision of the original first, then moves the copy to the trash. When the title changes, block-theme menu links to the original still labelled with the old title get the new one (WordPress keeps their stored label); other labels are kept. Refuses when the copy is already published or scheduled, or the original is not published, when they are of different types, when the copy would change a field a plugin (Yoast SEO, Rank Math) restricts for your role, when someone else has the original open in an editor, or while a page builder edit of the original is being saved. Returns { changed, id, copy_id, post_type, title, link, revision_id, audit_id, copy_status, copied_meta_keys, restore (how to undo it) }, plus `kept_meta_keys`, `skipped_meta_keys`, `skipped_taxonomies`, `skipped_template`, `builder`, `content_filtered`, `copy_error`, `warnings`, `navigation_labels_updated` (menus changed, each with the revision that undoes it) and `navigation_labels_kept` (links left as they are, and why) when they apply.';
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
            'destructiveHint' => true,
            'idempotentHint'  => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'copy_id'     => array(
                    'type'        => 'integer',
                    'description' => 'ID of the reviewed copy: a draft, pending or private post of the same type as the original.',
                ),
                'original_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of the published post the copy replaces. Default: the post the copy was made from (wp_duplicate_post and Yoast Duplicate Post record it).',
                ),
            ),
            'required'   => array( 'copy_id' ),
        );
    }

    public function describe( array $arguments ) {
        $copy = self::readable_or_null( $arguments['copy_id'] ?? null );
        if ( null === $copy ) {
            return '';
        }
        $original = self::readable_or_null( $arguments['original_id'] ?? self::recorded_original( $copy ) );
        if ( null === $original ) {
            return '';
        }
        $text = sprintf(
            /* translators: 1: original title, 2: original id, 3: copy title, 4: copy id */
            __( "Replace the live post '%1\$s' (ID %2\$d) with the copy '%3\$s' (ID %4\$d), keeping its URL, then move the copy to the trash.", 'easy-mcp-ai' ),
            $original->post_title,
            (int) $original->ID,
            $copy->post_title,
            (int) $copy->ID
        );
        if ( $copy->post_title !== $original->post_title ) {
            $text .= ' ' . __( 'Block-theme menu links that still show the old title get the new one.', 'easy-mcp-ai' );
        }
        return $text;
    }

    public function precheck( array $arguments ): void {
        try {
            $this->parse_required_id( $arguments['copy_id'] ?? null, 'copy_id' );
        } catch ( \InvalidArgumentException $e ) {
            return;
        }
        $this->checked_pair( $arguments );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'copy_id' ) );
        $pair     = $this->checked_pair( $arguments );
        $copy     = $pair['copy'];
        $original = $pair['original'];
        $id       = (int) $original->ID;
        $route    = self::post_type_route( $pair['rest_base'], $id, 'POST' );

        $lock = $pair['lock'] ? Layout_Lock::acquire( $id ) : '';
        if ( $pair['lock'] && '' === $lock ) {
            throw new \RuntimeException( sprintf( 'Another edit of post %d is being saved, so nothing was written. Try again once it is saved.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        try {
            if ( '' !== $lock ) {
                wp_cache_delete( $id, 'post_meta' );
            }
            $before   = self::state( $id );
            $titles   = Navigation_Labels::titles( $original );
            $kept     = self::kept_meta_keys( $original, $copy );
            $revision = self::save_revision( $id );
            $audit_id = self::audit_id();
            $restore  = self::restore_sentence( $revision, $audit_id, null !== $pair['elementor'] );
            self::require_lock( $id, $lock );
            $keep      = self::revision_keeper( $id );
            $keep_hook = 'wp_' . $original->post_type . '_revisions_to_keep';
            add_filter( $keep_hook, $keep, PHP_INT_MAX, 2 );
            try {
                $meta = Duplicate_Post::copy_meta( (int) $copy->ID, $id, $pair['layout_copy'] ? array( Elementor_Adapter::DATA_KEY ) : array() );
                if ( array() !== $meta['failed'] ) {
                    throw new \RuntimeException( sprintf( 'WordPress refused %1$d of the copy\'s meta keys: %2$s.', count( $meta['failed'] ), implode( ', ', $meta['failed'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                self::require_lock( $id, $lock );
                $builder = $this->write_builder( $pair, $original, $copy );
                self::require_lock( $id, $lock );
                $fields = $this->write_fields( $route, $copy );
                self::require_lock( $id, $lock );
                self::release_yoast_link( $original, $copy );
            } catch ( \Throwable $e ) {
                throw new Detailed_Tool_Error( sprintf( 'Post %1$d was partly updated from copy %2$d before an error; the copy was left as it is.', $id, (int) $copy->ID ), 'The error was: ' . $e->getMessage() . ' ' . $restore ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            } finally {
                remove_filter( $keep_hook, $keep, PHP_INT_MAX );
            }
        } finally {
            if ( '' !== $lock ) {
                Layout_Lock::release( $id, $lock );
            }
        }
        if ( (int) wp_is_post_revision( $revision ) !== $id ) {
            $revision = null;
            $restore  = self::restore_sentence( null, $audit_id, null !== $pair['elementor'] );
        }

        $reply = array(
            'changed'          => self::state( $id ) !== $before,
            'id'               => $id,
            'copy_id'          => (int) $copy->ID,
            'post_type'        => (string) $original->post_type,
            'title'            => (string) get_the_title( $id ),
            'link'             => $fields['link'],
            'revision_id'      => $revision,
            'audit_id'         => $audit_id,
            'copied_meta_keys' => $meta['copied'],
            'restore'          => $restore,
        );
        $reply      = array_merge( $reply, $this->trash_copy( $pair['rest_base'], $copy ) );
        $navigation = $this->relabel_navigation( $id, $titles );
        if ( array() !== array_filter( array_column( $navigation['navigation_labels_updated'], 'revision_id' ) ) ) {
            $reply['restore'] .= ' wp_restore_revision with the revision_id of a navigation_labels_updated entry puts that menu\'s labels back.';
        }
        $now   = get_post( $id );
        if ( is_object( $now ) && $now->post_content !== $copy->post_content ) {
            $reply['content_filtered'] = true;
        }
        $optional = array(
            'kept_meta_keys'     => $kept,
            'skipped_meta_keys'  => $meta['skipped'],
            'skipped_taxonomies' => $fields['skipped_taxonomies'],
            'skipped_template'   => $fields['skipped_template'],
            'builder'            => $builder,
            'warnings'           => $pair['warnings'],
        ) + $navigation;
        foreach ( $optional as $key => $value ) {
            if ( '' !== $value && array() !== $value ) {
                $reply[ $key ] = $value;
            }
        }
        return $reply;
    }

    private function checked_pair( array $arguments ): array {
        $copy        = $this->readable_post( $this->parse_required_id( $arguments['copy_id'] ?? null, 'copy_id' ) );
        $recorded    = self::recorded_original( $copy );
        $original_id = isset( $arguments['original_id'] ) && '' !== $arguments['original_id'] ? $this->parse_required_id( $arguments['original_id'], 'original_id' ) : $recorded;
        if ( 0 === $original_id ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d does not record which post it was copied from: pass original_id, the published post it replaces.', (int) $copy->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 0 !== $recorded && $recorded !== $original_id ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d is a copy of post %2$d, not of post %3$d: leave original_id out, or pass %2$d.', (int) $copy->ID, $recorded, $original_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( (int) $copy->ID === $original_id ) {
            throw new \InvalidArgumentException( 'copy_id and original_id name the same post.' );
        }
        $original = $this->readable_post( $original_id );
        foreach ( array( $copy, $original ) as $post ) {
            if ( isset( Duplicate_Post::REFUSED_TYPES[ $post->post_type ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Post %1$d is %2$s, which this tool does not republish.', (int) $post->ID, Duplicate_Post::REFUSED_TYPES[ $post->post_type ] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        if ( $copy->post_type !== $original->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d is a "%2$s" and post %3$d a "%4$s": a copy replaces only a post of its own type.', (int) $copy->ID, $copy->post_type, $original_id, $original->post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        self::check_statuses( $copy, $original );
        $this->check_permissions( $copy, $original );
        self::check_restricted_meta( $copy, $original );
        $rest_base = $this->served_rest_base( $original );
        self::post_type_route( $rest_base, (int) $copy->ID, 'DELETE' );
        $editing = Layout_Adapter::editing_user( $original_id );
        if ( null !== $editing ) {
            throw new \InvalidArgumentException( sprintf( '%1$s has post %2$d open in the editor; their next save there would replace what this call writes. Try again once they close it.', $editing, $original_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array_merge(
            array(
                'copy'      => $copy,
                'original'  => $original,
                'rest_base' => $rest_base,
            ),
            self::builder_plan( $copy, $original )
        );
    }

    private function readable_post( int $post_id ): object {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || ! get_post_type_object( $post->post_type ) || ! current_user_can( 'read_post', $post_id ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d not found.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $post;
    }

    private static function readable_or_null( $value ): ?object {
        $post_id = is_numeric( $value ) ? (int) $value : 0;
        $post    = $post_id > 0 ? get_post( $post_id ) : null;
        return is_object( $post ) && current_user_can( 'read_post', $post_id ) ? $post : null;
    }

    private static function recorded_original( object $copy ): int {
        foreach ( array( Duplicate_Post::COPY_OF_KEY, self::YOAST_ORIGINAL_KEY ) as $key ) {
            $id = (int) get_post_meta( (int) $copy->ID, $key, true );
            if ( $id > 0 ) {
                return $id;
            }
        }
        return 0;
    }

    private static function check_statuses( object $copy, object $original ): void {
        if ( 'publish' === $copy->post_status || 'future' === $copy->post_status ) {
            $message = sprintf( 'Post %1$d is %2$s at its own URL. To make it replace post %3$d instead, set it back to draft, then call this again.', (int) $copy->ID, 'future' === $copy->post_status ? 'scheduled to publish' : 'already published', (int) $original->ID );
            $tool = Post_Type_Tool_Hint::usable_tool( $copy, 'update' );
            if ( null === $tool ) {
                throw new \InvalidArgumentException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            throw new Detailed_Tool_Error( $message, sprintf( 'Set it back to draft with %1$s: %2$s, status "draft".', $tool[0], $tool[1] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! in_array( $copy->post_status, self::COPY_STATUSES, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d has status "%2$s"; only a draft, pending or private copy can replace its original.', (int) $copy->ID, $copy->post_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 'publish' !== $original->post_status ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d is not published (status "%2$s"): this tool replaces a live post. Edit it directly instead.', (int) $original->ID, $original->post_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function check_permissions( object $copy, object $original ): void {
        if ( ! current_user_can( 'edit_post', (int) $original->ID ) || ! current_user_can( 'publish_post', (int) $original->ID ) ) {
            throw new \InvalidArgumentException( sprintf( 'You cannot republish onto post %d: that needs permission to edit and publish it.', (int) $original->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'edit_post', (int) $copy->ID ) || ! current_user_can( 'delete_post', (int) $copy->ID ) ) {
            throw new \InvalidArgumentException( sprintf( 'You cannot republish post %d: that needs permission to edit it and move it to the trash.', (int) $copy->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! in_array( $original->post_type, array( 'post', 'page' ), true ) && false === $this->caller_may_update_custom_items() ) {
            throw new \InvalidArgumentException( sprintf( 'Republishing a "%s" item needs access to wp_update_cpt_item, which this connection does not have (for OAuth: the custom post types write permission).', $original->post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function check_restricted_meta( object $copy, object $original ): void {
        $on_original = (array) get_post_meta( (int) $original->ID );
        $refused     = array();
        foreach ( (array) get_post_meta( (int) $copy->ID ) as $key => $values ) {
            $key = (string) $key;
            if ( Duplicate_Post::is_skipped_meta( $key ) || ( isset( $on_original[ $key ] ) && $on_original[ $key ] === $values ) ) {
                continue;
            }
            $provider = Meta_Exposure::provider_for_key( $key );
            if ( null === $provider || ! $provider->is_active() || ! array_key_exists( $key, $provider->get_fields() ) ) {
                continue;
            }
            if ( ! $provider->can_edit_field( $key, get_current_user_id(), (int) $original->ID ) ) {
                $refused[] = sprintf( '"%1$s" (%2$s restricts this field for your role)', $key, $provider->get_label() );
            }
        }
        if ( array() !== $refused ) {
            throw new \InvalidArgumentException( sprintf( 'You are not allowed to change %1$s on post %2$d, which the copy holds with another value. Nothing was written.', implode( ', ', $refused ), (int) $original->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function caller_may_update_custom_items(): ?bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) ) {
            return null;
        }
        return \Easy_MCP_AI\MCP\Server::current_call_may_use( 'wp_update_cpt_item' );
    }

    private function served_rest_base( object $original ): string {
        $rest_base = $this->resolve_post_rest_base( (int) $original->ID );
        $served    = Post_Type_Rest_Base::served_type( $rest_base );
        if ( null === $served || $served[0] !== $original->post_type ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d has post type "%2$s", which the REST API does not serve at /wp/v2/%3$s, so this tool cannot update it.', (int) $original->ID, $original->post_type, $rest_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $rest_base;
    }

    private static function builder_plan( object $copy, object $original ): array {
        $builders = array();
        $any      = array();
        foreach ( array( 'copy' => $copy, 'original' => $original ) as $role => $post ) {
            $adapter      = Builder_Registry::for_post( $post );
            $any[ $role ] = null !== $adapter;
            if ( null === $adapter && 'builder' === get_post_meta( (int) $post->ID, Elementor_Adapter::EDIT_MODE_KEY, true ) ) {
                throw new \InvalidArgumentException( sprintf( 'Post %d is built with Elementor, which is not active, so this tool cannot carry its layout.', (int) $post->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( null !== $adapter && ! $adapter instanceof Elementor_Adapter && array() !== $adapter->layout_meta_keys() ) {
                throw new \InvalidArgumentException( sprintf( 'Post %1$d is built with %2$s, which keeps its layout outside the post content; this tool cannot carry it.', (int) $post->ID, $adapter->label() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $builders[ $role ] = $adapter instanceof Elementor_Adapter ? $adapter : null;
        }
        $elementor = $builders['copy'] ?? $builders['original'];
        $warnings  = self::autosave_warnings( (int) $original->ID );
        if ( null !== $builders['copy'] ) {
            $problems = $builders['copy']->replace_problems( $original, $copy, $warnings );
            if ( array() !== $problems ) {
                throw Layout_Adapter::refusal( array_merge( array( sprintf( 'Elementor would not keep the layout of post %1$d as it is when saving it onto post %2$d for this account, so nothing was written.', (int) $copy->ID, (int) $original->ID ) ), $problems ) );
            }
        }
        return array(
            'elementor'   => $elementor,
            'layout_copy' => null !== $builders['copy'],
            'release'     => null === $builders['copy'] && null !== $builders['original'],
            'lock'        => $any['original'] || null !== $builders['copy'],
            'warnings'    => $warnings,
        );
    }

    private static function autosave_warnings( int $post_id ): array {
        $warnings = array();
        foreach ( wp_get_post_revisions( $post_id, array( 'check_enabled' => false ) ) as $revision ) {
            if ( false === strpos( (string) $revision->post_name, '-autosave' ) ) {
                continue;
            }
            $user       = get_userdata( (int) $revision->post_author );
            $warnings[] = sprintf( 'Post %1$d has an unsaved autosave by %2$s; restoring it in the editor, or Elementor\'s publish-document, would replace what this call wrote.', $post_id, is_object( $user ) && ! empty( $user->display_name ) ? $user->display_name : sprintf( 'user %d', (int) $revision->post_author ) );
        }
        return $warnings;
    }

    private static function kept_meta_keys( object $original, object $copy ): array {
        $on_original = array_map( 'strval', array_keys( (array) get_post_meta( (int) $original->ID ) ) );
        $on_copy     = array_map( 'strval', array_keys( (array) get_post_meta( (int) $copy->ID ) ) );
        $kept        = array();
        foreach ( array_diff( $on_original, $on_copy ) as $key ) {
            if ( ! Duplicate_Post::is_skipped_meta( $key ) && ! in_array( $key, array( Elementor_Adapter::EDIT_MODE_KEY, Elementor_Adapter::DATA_KEY, self::YOAST_HAS_COPY_KEY ), true ) ) {
                $kept[] = $key;
            }
        }
        return array_values( $kept );
    }

    private static function revision_keeper( int $post_id ): callable {
        return static function ( $count, $post ) use ( $post_id ) {
            return is_object( $post ) && (int) $post->ID === $post_id && (int) $count > 0 ? (int) $count + 2 : $count;
        };
    }

    private static function save_guard( int $post_id, string $planned, callable $held ): callable {
        $stopped = '';
        return static function ( $maybe_empty, $postarr ) use ( $post_id, $planned, $held, &$stopped ) {
            if ( null === $maybe_empty ) {
                return $stopped;
            }
            if ( (int) ( $postarr['ID'] ?? 0 ) !== $post_id ) {
                return (bool) $maybe_empty;
            }
            if ( ! $held() ) {
                $stopped = 'lock';
                return true;
            }
            if ( wp_unslash( (string) ( $postarr['post_content'] ?? '' ) ) !== $planned ) {
                $stopped = 'content';
                return true;
            }
            return (bool) $maybe_empty;
        };
    }

    private static function save_revision( int $post_id ): ?int {
        $post = get_post( $post_id );
        if ( ! is_object( $post ) || ! wp_revisions_enabled( $post ) ) {
            return null;
        }
        $always = static function () {
            return false;
        };
        add_filter( 'wp_save_post_revision_check_for_changes', $always, PHP_INT_MAX );
        try {
            $revision = wp_save_post_revision( $post_id );
        } finally {
            remove_filter( 'wp_save_post_revision_check_for_changes', $always, PHP_INT_MAX );
        }
        if ( ! is_int( $revision ) || $revision <= 0 ) {
            $why = is_wp_error( $revision ) ? ': ' . $revision->get_error_message() : '';
            throw new \RuntimeException( sprintf( 'Could not save a revision of post %1$d to restore it from%2$s, so nothing was written.', $post_id, $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $revision;
    }

    private static function audit_id(): ?int {
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) || ! \Easy_MCP_AI\History\Change_Context::is_active() ) {
            return null;
        }
        $audit_id = (int) \Easy_MCP_AI\History\Change_Context::get( 'audit_id', 0 );
        return $audit_id > 0 ? $audit_id : null;
    }

    private static function restore_sentence( ?int $revision, ?int $audit_id, bool $elementor ): string {
        $parts = array();
        if ( null !== $revision ) {
            $parts[] = sprintf( 'wp_restore_revision { "revision_id": %1$d } puts back the title, content and excerpt%2$s', $revision, $elementor ? ', and the Elementor layout and whether the page is built with Elementor' : '' );
        }
        if ( null !== $audit_id ) {
            $parts[] = sprintf( 'wp_history_apply { "audit_id": %d } puts back the other fields and the meta keys registered for the REST API, and names each other meta key it replaced, whose old value wp_history_get shows', $audit_id );
        }
        if ( array() === $parts ) {
            return 'No revision of this post and no change history entry are available, so this cannot be undone from here.';
        }
        return 'To undo: ' . implode( '; ', $parts ) . '.';
    }

    private static function require_lock( int $post_id, string $lock ): void {
        if ( '' !== $lock && ! Layout_Lock::holds( $post_id, $lock ) ) {
            throw new \RuntimeException( sprintf( 'This republish of post %d ran longer than %d seconds and another edit took the post over, so it stopped.', $post_id, Layout_Lock::TTL ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function write_builder( array $pair, object $original, object $copy ): string {
        if ( $pair['layout_copy'] ) {
            $pair['elementor']->replace_layout( $original, $copy );
            return 'The copy\'s Elementor layout was saved onto the original through Elementor.';
        }
        if ( $pair['release'] ) {
            $pair['elementor']->release( $original );
            return 'The original was switched off Elementor, so it shows the copy\'s content; its Elementor layout is kept and a revision restore switches it back.';
        }
        return '';
    }

    private function write_fields( string $route, object $copy ): array {
        $format   = get_post_format( (int) $copy->ID );
        $template = self::copied_template( $copy );
        $params   = array(
            'title'          => $copy->post_title,
            'content'        => $copy->post_content,
            'excerpt'        => $copy->post_excerpt,
            'featured_media' => (int) get_post_thumbnail_id( (int) $copy->ID ),
            'format'         => is_string( $format ) && '' !== $format ? $format : 'standard',
        );
        if ( ! isset( $template['skipped_template'] ) ) {
            $params['template'] = $template['template'] ?? '';
        }
        $item_fields = self::rest_item_fields( get_post_type_object( $copy->post_type ) );
        if ( null !== $item_fields ) {
            $params = array_intersect_key( $params, array_flip( $item_fields ) );
        }
        $terms = $this->copied_terms( $copy );
        $updated = $this->rest_request( 'POST', $route, array_replace( $params, $terms['params'] ), 'id,link' );
        return array(
            'link'               => (string) ( $updated['link'] ?? '' ),
            'skipped_taxonomies' => $terms['skipped'],
            'skipped_template'   => $template['skipped_template'] ?? '',
        );
    }

    private static function release_yoast_link( object $original, object $copy ): void {
        if ( 1 === (int) get_post_meta( (int) $copy->ID, self::YOAST_REWRITE_KEY, true ) && (int) get_post_meta( (int) $original->ID, self::YOAST_HAS_COPY_KEY, true ) === (int) $copy->ID ) {
            delete_post_meta( (int) $original->ID, self::YOAST_HAS_COPY_KEY );
        }
    }

    private function trash_copy( string $rest_base, object $copy ): array {
        try {
            $trashed = $this->rest_request( 'DELETE', self::post_type_route( $rest_base, (int) $copy->ID, 'DELETE' ), array(), 'id,status' );
        } catch ( \RuntimeException $e ) {
            return array(
                'copy_status' => (string) get_post_status( (int) $copy->ID ),
                'copy_error'  => 'The original is live; moving the copy to the trash failed: ' . $e->getMessage(),
            );
        }
        return array( 'copy_status' => (string) ( $trashed['status'] ?? get_post_status( (int) $copy->ID ) ) );
    }

    private function relabel_navigation( int $post_id, array $old_titles ): array {
        $updated = array();
        $kept    = array();
        $title   = (string) get_post_field( 'post_title', $post_id, 'raw' );
        if ( in_array( Navigation_Labels::comparable( $title ), $old_titles, true ) ) {
            return array(
                'navigation_labels_updated' => $updated,
                'navigation_labels_kept'    => $kept,
            );
        }
        foreach ( Navigation_Labels::holders() as $holder ) {
            $plan = Navigation_Labels::relabel( $holder->post_content, $post_id, $old_titles, $title );
            if ( array() === $plan['updated'] && array() === $plan['kept'] ) {
                continue;
            }
            $menu = array(
                'id'        => (int) $holder->ID,
                'post_type' => (string) $holder->post_type,
            );
            if ( ! current_user_can( 'edit_post', (int) $holder->ID ) ) {
                if ( array() !== $plan['updated'] ) {
                    $kept[] = $menu + array( 'reason' => sprintf( 'You cannot edit this menu (it needs the edit_theme_options capability), so %d link(s) in it still show the old title.', count( $plan['updated'] ) ) );
                }
                continue;
            }
            $menu['title'] = (string) $holder->post_title;
            $result        = array() === $plan['updated']
                ? array(
                    'updated' => array(),
                    'kept'    => self::kept_by_hand( $menu, $plan['kept'] ),
                )
                : $this->relabel_menu( $holder, $menu, $plan['kept'], $post_id, $old_titles, $title );
            $updated       = array_merge( $updated, $result['updated'] );
            $kept          = array_merge( $kept, $result['kept'] );
        }
        return array(
            'navigation_labels_updated' => $updated,
            'navigation_labels_kept'    => $kept,
        );
    }

    private function relabel_menu( object $holder, array $menu, array $by_hand, int $post_id, array $old_titles, string $title ): array {
        $id = (int) $holder->ID;
        try {
            $lock = Layout_Lock::acquire_waiting( $id, self::MENU_LOCK_WAIT );
        } catch ( \RuntimeException $e ) {
            $kept   = self::kept_by_hand( $menu, $by_hand );
            $kept[] = $menu + array( 'reason' => sprintf( 'It still shows the old title: another save of this menu was still running after %1$d seconds, or the database refused its lock, so its labels were left as they are. %2$s changes them.', self::MENU_LOCK_WAIT, 'wp_navigation' === $holder->post_type ? 'wp_update_cpt_item with rest_base "navigation"' : 'wp_update_template' ) );
            return array(
                'updated' => array(),
                'kept'    => $kept,
            );
        }
        try {
            wp_cache_delete( $id, 'posts' );
            $before = (string) get_post_field( 'post_content', $id, 'raw' );
            $plan   = Navigation_Labels::relabel( $before, $post_id, $old_titles, $title );
            $kept   = self::kept_by_hand( $menu, $plan['kept'] );
            if ( array() === $plan['updated'] ) {
                return array(
                    'updated' => array(),
                    'kept'    => $kept,
                );
            }
            $route = Navigation_Labels::route( $holder );
            $shown = '' === $route ? null : $this->shown_differently( $holder, $route, $before );
            if ( null !== $shown ) {
                return array(
                    'updated' => array(),
                    'kept'    => array_merge( $kept, self::not_saved( $menu, $plan['updated'], $shown ) ),
                );
            }
            $keep      = self::revision_keeper( $id );
            $keep_hook = 'wp_' . $holder->post_type . '_revisions_to_keep';
            $guard     = self::save_guard(
                $id,
                $plan['content'],
                static function () use ( $id, $lock ): bool {
                    return Layout_Lock::holds( $id, $lock );
                }
            );
            $revision  = null;
            $why       = null;
            $lost      = false;
            $hooked    = null;
            add_filter( $keep_hook, $keep, PHP_INT_MAX, 2 );
            add_filter( 'wp_insert_post_empty_content', $guard, PHP_INT_MAX, 2 );
            try {
                if ( '' === $route ) {
                    throw new \RuntimeException( sprintf( 'post type "%s" is not served over REST.', $holder->post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $revision = self::save_revision( $id );
                if ( ! Layout_Lock::holds( $id, $lock ) ) {
                    $lost = true;
                    throw new \RuntimeException( self::menu_taken_over() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $hooked = self::hooked_blocks_meta( $id );
                $this->rest_request( 'POST', $route, array( 'content' => $plan['content'] ), 'id' );
            } catch ( \RuntimeException $e ) {
                $why = $e->getMessage();
            } finally {
                remove_filter( $keep_hook, $keep, PHP_INT_MAX );
                remove_filter( 'wp_insert_post_empty_content', $guard, PHP_INT_MAX );
            }
            if ( null !== $revision && (int) wp_is_post_revision( $revision ) !== $id ) {
                $revision = null;
            }
            wp_cache_delete( $id, 'posts' );
            $stored = (string) get_post_field( 'post_content', $id, 'raw' );
            if ( null !== $hooked && $stored === $before ) {
                $renewed = Layout_Lock::renew( $id, $lock );
                if ( '' !== $renewed ) {
                    $lock = $renewed;
                    self::restore_hooked_blocks_meta( $id, $hooked );
                }
            }
            $stopped = $guard( null, array() );
            if ( $lost || 'lock' === $stopped ) {
                return self::after_lost_lock( $menu, $stored, $before, $plan['content'], ! $lost, $post_id, $old_titles, $title );
            }
            if ( $stored === $plan['content'] ) {
                return array(
                    'updated' => array(
                        $menu + array(
                            'links'       => count( $plan['updated'] ),
                            'revision_id' => $revision,
                        ),
                    ),
                    'kept'    => $kept,
                );
            }
            if ( $stored !== $before ) {
                $kept[] = $menu + self::stored_otherwise( $revision, Layout_Lock::holds( $id, $lock ) );
                return array(
                    'updated' => array(),
                    'kept'    => $kept,
                );
            }
            if ( 'content' === $stopped ) {
                $why = 'WordPress\'s save filters would have stored something other than the planned labels in it for your account, so it was not saved.';
            }
            return array(
                'updated' => array(),
                'kept'    => array_merge( $kept, self::not_saved( $menu, $plan['updated'], $why ?? 'WordPress did not save it.' ) ),
            );
        } finally {
            Layout_Lock::release( $id, $lock );
        }
    }

    private static function after_lost_lock( array $menu, string $stored, string $before, string $planned, bool $sent, int $post_id, array $old_titles, string $title ): array {
        $now     = Navigation_Labels::relabel( $stored, $post_id, $old_titles, $title );
        $updated = array();
        if ( array() !== $now['current'] ) {
            $updated[] = $menu + array(
                'links'       => count( $now['current'] ),
                'revision_id' => null,
            );
        }
        $kept = self::kept_by_hand( $menu, $now['kept'] );
        if ( $stored !== $before && $stored !== $planned ) {
            $kept[] = $menu + array(
                'reason'      => 'It holds other changes than the planned labels: another edit took the menu over and saved it' . ( $sent ? ', or a later filter let this call\'s stopped save through' : '' ) . '. No revision is offered to undo it.',
                'revision_id' => null,
            );
        }
        return array(
            'updated' => $updated,
            'kept'    => array_merge( $kept, self::not_saved( $menu, $now['updated'], self::menu_taken_over() ) ),
        );
    }

    private function shown_differently( object $holder, string $route, string $stored ): ?string {
        try {
            $view = $this->rest_request( 'GET', $route, array( 'context' => 'edit' ), 'content' );
        } catch ( \RuntimeException $e ) {
            return 'WordPress did not show this menu as it would be saved (' . rtrim( $e->getMessage(), '.' ) . '), so its labels were left as they are.';
        }
        if ( ( $view['content']['raw'] ?? null ) === $stored ) {
            return null;
        }
        return 'WordPress shows this menu differently from how it is stored (it adds blocks other plugins hook into it, or rewrites its markup), and a save from here could change what it shows, so its labels were left as they are. ' . ( 'wp_navigation' === $holder->post_type ? 'wp_update_cpt_item with the content wp_get_cpt_item returns changes them.' : 'Change them in the Site Editor.' );
    }

    private static function hooked_blocks_meta( int $post_id ): array {
        wp_cache_delete( $post_id, 'post_meta' );
        return array_values( array_map( 'strval', (array) get_post_meta( $post_id, self::HOOKED_BLOCKS_META, false ) ) );
    }

    private static function restore_hooked_blocks_meta( int $post_id, array $before ): void {
        if ( self::hooked_blocks_meta( $post_id ) === $before ) {
            return;
        }
        delete_post_meta( $post_id, self::HOOKED_BLOCKS_META );
        foreach ( $before as $value ) {
            add_post_meta( $post_id, self::HOOKED_BLOCKS_META, wp_slash( $value ) );
        }
    }

    private static function not_saved( array $menu, array $labels, string $why ): array {
        $kept = array();
        foreach ( $labels as $label ) {
            $kept[] = $menu + array(
                'label'  => $label,
                'reason' => 'It still shows the old title: ' . $why,
            );
        }
        return $kept;
    }

    private static function stored_otherwise( ?int $revision, bool $held ): array {
        if ( null === $revision ) {
            $undo = ' No revision of it is available to undo it.';
        } elseif ( $held ) {
            $undo = ' wp_restore_revision with this revision_id puts it back as it was.';
        } else {
            $undo     = ' Another edit may have saved it since, so no revision is offered to undo it.';
            $revision = null;
        }
        return array(
            'reason'      => 'It was saved, but with other changes than the planned labels.' . $undo,
            'revision_id' => $revision,
        );
    }

    private static function menu_taken_over(): string {
        return sprintf( 'this call could no longer confirm it held the menu\'s lock (held past %d seconds and taken over by another edit, or the lock could not be read), so it was not saved.', Layout_Lock::TTL );
    }

    private static function kept_by_hand( array $menu, array $labels ): array {
        $kept = array();
        foreach ( $labels as $label ) {
            $kept[] = $menu + array(
                'label'  => $label,
                'reason' => 'Its label is not the old title, so it was left as it was set.',
            );
        }
        return $kept;
    }

    private static function state( int $post_id ): string {
        clean_post_cache( $post_id );
        $post  = get_post( $post_id );
        $meta  = array();
        $terms = array();
        foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
            if ( ! Duplicate_Post::is_skipped_meta( (string) $key ) ) {
                $meta[ (string) $key ] = $values;
            }
        }
        ksort( $meta );
        foreach ( get_object_taxonomies( (string) $post->post_type ) as $taxonomy ) {
            $tax    = get_taxonomy( $taxonomy );
            $sorted = is_object( $tax ) && ! empty( $tax->sort );
            $ids    = wp_get_object_terms( $post_id, $taxonomy, $sorted ? array( 'fields' => 'ids', 'orderby' => 'term_order' ) : array( 'fields' => 'ids' ) );
            if ( is_array( $ids ) ) {
                if ( ! $sorted ) {
                    sort( $ids );
                }
                $terms[ $taxonomy ] = $ids;
            }
        }
        return (string) wp_json_encode(
            array(
                $post->post_title,
                $post->post_content,
                $post->post_excerpt,
                get_page_template_slug( $post_id ),
                (int) get_post_thumbnail_id( $post_id ),
                get_post_format( $post_id ),
                $terms,
                $meta,
            )
        );
    }
}
