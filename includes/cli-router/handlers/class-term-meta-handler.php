<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































final class Term_Meta_Handler extends Meta_Command {

    use \Easy_MCP_AI\Tools\Taxonomy\Term_Meta_Auth_Guard;

    
    protected $meta_type = 'term';

    const NOT_FOUND = 'Could not find the term with ID %s.';

    const YOAST = 'Yoast SEO does not read term meta "%s": it keeps category and tag SEO in its own settings, so this row would change nothing on the site. Change term SEO with Yoast\'s own tools.';

    const BLOCKED = 'This meta key cannot be modified via MCP.';

    




    public function preflight_id( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        Ids::require_digits( $ctx, isset( $args[0] ) ? $args[0] : '', 'a term ID' );
    }

    
    public function add( Invocation $inv, Context $ctx ): void {
        foreach ( (array) ( $inv->route['not_captured'] ?? array() ) as $phrase ) {
            $ctx->not_captured( (string) $phrase );
        }
        parent::add( $inv, $ctx );
    }

    






    protected function check_object_id( $object_id, string $subcommand ) {
        $term = self::term( $object_id );
        if ( null === $term ) {
            $this->ctx->out->error( sprintf( self::NOT_FOUND, $object_id ) );
        }
        return (int) $term->term_id;
    }

    





    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        return \get_term_meta( (int) $object_id, $meta_key, $single );
    }

    






    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        $term_id = (int) $object_id;
        $this->ctx->require_cap( 'edit_term', $term_id );
        $refusal = self::integration_refusal( $meta_key );
        if ( null === $refusal ) {
            try {
                $this->assert_term_meta_key_allowed( $meta_key, self::term( $term_id ), 'add_term_meta' );
            } catch ( \RuntimeException $e ) {
                $refusal = $e->getMessage();
            }
        }
        if ( null !== $refusal ) {
            $this->ctx->out->error( $refusal );
        }
        $result = \add_term_meta( $term_id, $meta_key, $meta_value );
        
        
        return ! ( function_exists( 'is_wp_error' ) && \is_wp_error( $result ) ) && false !== $result && null !== $result;
    }

    






    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        throw new \LogicException( 'term meta update is Adapters\\Term_Meta_Adapter.' );
    }

    






    protected function delete_metadata( $object_id, string $meta_key, $meta_value = '' ): bool {
        throw new \LogicException( 'term meta delete is Adapters\\Term_Meta_Adapter.' );
    }

    
    
    

    








    public static function term( $object_id ): ?object {
        if ( ! is_scalar( $object_id ) || ! function_exists( 'get_term' ) ) {
            return null;
        }
        $term = \get_term( $object_id );
        if ( ! is_object( $term ) || ( function_exists( 'is_wp_error' ) && \is_wp_error( $term ) ) || ! isset( $term->term_id, $term->taxonomy ) ) {
            return null;
        }
        return $term;
    }

    




    public static function integration_refusal( string $key ): ?string {
        if ( 0 === strpos( $key, '_yoast_wpseo_' ) && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
            return sprintf( self::YOAST, $key );
        }
        if ( function_exists( 'apply_filters' ) ) {
            foreach ( (array) \apply_filters( 'easy_mcp_ai_term_meta_blocked_key_patterns', array() ) as $pattern ) {
                if ( is_string( $pattern ) && '' !== $pattern && fnmatch( $pattern, $key ) ) {
                    return self::BLOCKED;
                }
            }
        }
        return null;
    }
}
