<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Preview\Preview_Link;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__, 2 ) . '/preview/class-preview-link.php';






class Create_Preview_Link extends Base_Tool {

    public function get_name() {
        return 'wp_create_preview_link';
    }

    public function get_description() {
        return 'Creates a temporary preview link for an unpublished post, page or custom post type item (draft, pending, scheduled or private). Opening the link shows the item rendered by the theme exactly as a visitor will see it once published, with no login, so a browser or fetch tool can check layout, images and styling. Required: `post_id`. Optional: `expires_in_minutes` (5–1440, default 60). Anyone with the link can view the item until it expires; a link cannot be revoked early, and stops working if you lose edit access to the item. The page shows the item as saved when it is opened, so after editing ask for a new link, since a fetch tool may return its cached copy of an old URL. A published item is refused with its public URL. Requires edit access to the item. Returns { post_id, post_type, status, url, expires_at, expires_in_minutes, notice }.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'            => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post, page or custom post type item to preview.',
                ),
                'expires_in_minutes' => array(
                    'type'        => 'integer',
                    'description' => 'How long the link works, in minutes: 5 to 1440 (24 hours). Default 60.',
                    'minimum'     => Preview_Link::MIN_MINUTES,
                    'maximum'     => Preview_Link::MAX_MINUTES,
                    'default'     => Preview_Link::DEFAULT_MINUTES,
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    public function describe( array $arguments ) {
        $id   = isset( $arguments['post_id'] ) && is_numeric( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
        $post = $id > 0 ? \get_post( $id ) : null;
        if ( ! $post || ! \current_user_can( 'edit_post', $id ) ) {
            return '';
        }
        $minutes = isset( $arguments['expires_in_minutes'] ) && is_numeric( $arguments['expires_in_minutes'] ) ? (int) $arguments['expires_in_minutes'] : Preview_Link::DEFAULT_MINUTES;
        return sprintf(
            /* translators: 1: post title, 2: post id, 3: post status, 4: minutes */
            __( "Create a preview link for '%1\$s' (ID %2\$d, %3\$s) that anyone can open for %4\$d minutes.", 'easy-mcp-ai' ),
            (string) $post->post_title,
            $id,
            (string) $post->post_status,
            $minutes
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );

        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $minutes = $this->parse_minutes( $arguments );

        $this->refuse_missing_post( $post_id );
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( sprintf( 'You cannot edit post %d, so you cannot create a preview link for it.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $post = get_post( $post_id );
        $this->refuse_unpreviewable( $post );

        $expires = time() + $minutes * 60;
        $notice  = sprintf( 'Anyone with this link can view this %1$s (status "%2$s") without logging in, until %3$s UTC. After editing it, ask for a new link.', (string) $post->post_type, (string) $post->post_status, gmdate( 'Y-m-d H:i', $expires ) );
        if ( '' !== (string) $post->post_password ) {
            $notice .= ' It is password-protected, so the page shows the password form, as a visitor would see it.';
        }

        return array(
            'post_id'            => $post_id,
            'post_type'          => (string) $post->post_type,
            'status'             => (string) $post->post_status,
            'url'                => Preview_Link::url( $post, (int) get_current_user_id(), $expires ),
            'expires_at'         => gmdate( 'Y-m-d\TH:i:s\Z', $expires ),
            'expires_in_minutes' => $minutes,
            'notice'             => $notice,
        );
    }

    








    private function parse_minutes( array $arguments ): int {
        if ( ! isset( $arguments['expires_in_minutes'] ) || '' === $arguments['expires_in_minutes'] ) {
            return Preview_Link::DEFAULT_MINUTES;
        }
        $value   = $arguments['expires_in_minutes'];
        $minutes = self::whole_number( $value );
        if ( null === $minutes || $minutes < Preview_Link::MIN_MINUTES || $minutes > Preview_Link::MAX_MINUTES ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid expires_in_minutes: %s. Use a whole number from %d to %d (24 hours).', \wp_json_encode( $value ), Preview_Link::MIN_MINUTES, Preview_Link::MAX_MINUTES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $minutes;
    }

    






    private static function whole_number( $value ): ?int {
        if ( is_int( $value ) ) {
            return $value;
        }
        return is_string( $value ) && ctype_digit( $value ) ? (int) $value : null;
    }

    






    private function refuse_unpreviewable( object $post ): void {
        $id = (int) $post->ID;
        switch ( Preview_Link::status_problem( $post ) ) {
            case '':
                return;
            case 'public':
                throw new \InvalidArgumentException( sprintf( 'Post %d is already published and anyone can view it at %s. Preview links are for items that are not public yet (draft, pending, scheduled or private).', $id, get_permalink( $post ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            case 'trash':
                throw new \InvalidArgumentException( sprintf( 'Post %d is in the trash. Restore it before creating a preview link.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            case 'auto-draft':
                throw new \InvalidArgumentException( sprintf( 'Post %d is an auto-draft that has never been saved, so there is nothing to preview.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            case 'inherit':
                throw new \InvalidArgumentException( sprintf( 'Post %d is an attachment or a revision (status "inherit"). Preview links are for posts, pages and custom post type items.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            default:
                throw new \InvalidArgumentException( sprintf( 'Post %d is a "%s", a post type with no public page on the site, so it cannot be previewed.', $id, (string) $post->post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
