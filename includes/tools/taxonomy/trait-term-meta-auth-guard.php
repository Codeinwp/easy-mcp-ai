<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}































trait Term_Meta_Auth_Guard {

    











    protected function assert_term_meta_key_allowed( $key, $term, $cap ) {
        if ( ! is_protected_meta( $key, 'term' ) ) {
            return;
        }
        if ( current_user_can( $cap, (int) $term->term_id, $key ) ) {
            return;
        }
        if ( $this->term_meta_key_is_registered( $key, $term ) ) {
            throw new \RuntimeException( sprintf( 'WordPress refused %1$s for the protected key "%2$s" on this term: the key is registered, and its registered auth callback or an auth_term_meta filter denied the current user.', $cap, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        throw new \RuntimeException( sprintf( 'Protected meta keys require administrator privileges when nothing has registered them, and no plugin registers "%1$s" for taxonomy %2$s.', $key, $term->taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    









    private function term_meta_key_is_registered( $key, $term ) {
        $taxonomy = (string) $term->taxonomy;
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            foreach ( array( $taxonomy, '' ) as $subtype ) {
                $registered = get_registered_meta_keys( 'term', $subtype );
                if ( isset( $registered[ $key ] ) ) {
                    return true;
                }
            }
        }
        return has_filter( "auth_term_meta_{$key}_for_{$taxonomy}" ) || has_filter( "auth_term_meta_{$key}" );
    }
}
