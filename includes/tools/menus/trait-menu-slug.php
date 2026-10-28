<?php
namespace Easy_MCP_AI\Tools\Menus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Menu_Slug {

    protected function requested_menu_slug( $raw, int $menu_id ): string {
        if ( ! is_string( $raw ) ) {
            throw new \InvalidArgumentException( '"slug" must be a string.' );
        }
        $slug = sanitize_title( $raw );
        if ( '' === $slug ) {
            throw new \InvalidArgumentException( sprintf( 'The slug "%s" has no letters or numbers WordPress can keep.', $raw ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( empty( $slug ) ) {
            throw new \InvalidArgumentException( sprintf( 'The slug "%s" cannot be stored: WordPress treats it as empty and derives the slug from the name.', $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $taken = get_term_by( 'slug', $slug, 'nav_menu' );
        if ( is_object( $taken ) && ! is_wp_error( $taken ) && (int) $taken->term_id !== $menu_id ) {
            throw new \InvalidArgumentException( sprintf( 'The slug "%1$s" is already used by the menu "%2$s" (ID %3$d). Choose another slug.', $slug, $taken->name, (int) $taken->term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $slug;
    }

    protected function store_menu_slug( int $menu_id, string $slug ): string {
        $term = get_term( $menu_id, 'nav_menu' );
        if ( is_object( $term ) && ! is_wp_error( $term ) && $term->slug === $slug ) {
            return $slug;
        }
        $result = wp_update_term( $menu_id, 'nav_menu', array( 'slug' => $slug ) );
        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $term   = get_term( $menu_id, 'nav_menu' );
        $stored = is_object( $term ) && ! is_wp_error( $term ) ? (string) $term->slug : $slug;
        if ( $stored !== $slug ) {
            throw new \RuntimeException( sprintf( 'WordPress stored the slug "%1$s" instead of "%2$s".', $stored, $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $slug;
    }
}
