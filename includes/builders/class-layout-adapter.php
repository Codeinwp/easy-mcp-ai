<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Layout_Adapter {

    const EXCERPT_LENGTH = 160;

    abstract public function id(): string;

    abstract public function label(): string;

    abstract public function is_present(): bool;

    abstract public function version(): string;

    abstract public function owns( object $post ): bool;

    abstract public function nodes( object $post ): array;

    abstract public function source( object $post ): string;

    abstract public function element_types(): array;

    abstract public function element_fields( string $type ): ?array;

    public function vendor_route( object $post, array $nodes ): ?array {
        unset( $post, $nodes );
        return null;
    }

    public function references( array $nodes ): array {
        unset( $nodes );
        return array();
    }

    public function renders_post_content( object $post ): bool {
        unset( $post );
        return true;
    }

    public function released_by( array $meta ): bool {
        unset( $meta );
        return false;
    }

    public function layout_meta_keys(): array {
        return array();
    }

    public function version_token( object $post ): string {
        return substr( sha1( $this->id() . '|' . $post->post_modified_gmt . '|' . $this->source( $post ) ), 0, 16 );
    }

    public function edit_refusal( object $post ): ?string {
        unset( $post );
        return sprintf( 'Editing %1$s layouts is not available yet: it has not been checked against a live %1$s site. Read the layout with wp_get_page_layout.', $this->label() );
    }

    public function edit( object $post, array $ops, bool $dry_run, ?callable $fence = null ): array {
        unset( $ops, $dry_run, $fence );
        throw self::refusal( array( (string) $this->edit_refusal( $post ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    public static function library_image( int $attachment_id ): ?array {
        if ( ! wp_attachment_is_image( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) ) {
            return null;
        }
        $url = wp_get_attachment_url( $attachment_id );
        return is_string( $url ) && '' !== $url ? array( 'id' => $attachment_id, 'url' => $url ) : null;
    }

    protected static function editing_user( int $post_id ): ?string {
        if ( ! function_exists( 'wp_check_post_lock' ) && is_readable( ABSPATH . 'wp-admin/includes/post.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/post.php';
        }
        if ( ! function_exists( 'wp_check_post_lock' ) ) {
            return null;
        }
        $user_id = wp_check_post_lock( $post_id );
        if ( ! $user_id ) {
            return null;
        }
        $user = get_userdata( (int) $user_id );
        return is_object( $user ) && ! empty( $user->display_name ) ? (string) $user->display_name : sprintf( 'User %d', (int) $user_id );
    }

    public static function refusal( array $problems ): \Easy_MCP_AI\MCP\Detailed_Tool_Error {
        if ( 1 === count( $problems ) && strlen( $problems[0] ) <= \Easy_MCP_AI\MCP\Detailed_Tool_Error::MAX_MESSAGE ) {
            return new \Easy_MCP_AI\MCP\Detailed_Tool_Error( $problems[0] );
        }
        $summary = 1 === count( $problems ) ? 'Nothing was saved; the reason follows.' : sprintf( 'Nothing was saved: %d problems, each listed below.', count( $problems ) );
        return new \Easy_MCP_AI\MCP\Detailed_Tool_Error( $summary, '- ' . implode( "\n- ", $problems ) );
    }

    public static function excerpt( $value ): ?string {
        if ( ! is_string( $value ) ) {
            return null;
        }
        $value = (string) preg_replace( '#<(?:br|/p|/h[1-6]|/li|/div|/td|/th|/blockquote)\b[^>]*>#i', '$0 ', $value );
        $text  = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) ) );
        if ( '' === $text ) {
            return null;
        }
        if ( mb_strlen( $text, 'UTF-8' ) > self::EXCERPT_LENGTH ) {
            return rtrim( mb_substr( $text, 0, self::EXCERPT_LENGTH - 1, 'UTF-8' ) ) . '…';
        }
        return $text;
    }

    protected static function node( array $node ): array {
        return array_merge(
            array(
                'address'  => '',
                'type'     => '',
                'label'    => '',
                'text'     => null,
                'image'    => null,
                'link'     => null,
                'settings' => array(),
                'flags'    => array(),
                'children' => array(),
            ),
            $node
        );
    }
}
