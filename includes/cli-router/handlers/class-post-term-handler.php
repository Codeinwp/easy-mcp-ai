<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Terms_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Post_Term_Handler extends Terms_Command {

    protected $obj_type = 'post';

    private $post = null;

    const MSG_ASSIGN_CAP = "Sorry, you are not allowed to do this: wp_cli_run needs the assign_term capability for term %d ('%s') in '%s'.";

    public function preflight_ids( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        Ids::require_digits( $ctx, isset( $args[0] ) ? $args[0] : '', 'a post ID' );
        if ( 'id' === (string) Read_Value::flag( $inv->assoc, 'by' ) ) {
            Ids::require_all( $ctx, array_slice( $args, 2 ), 'a term ID' );
        }
    }

    protected function get_object_type(): string {
        if ( null === $this->post ) {
            $this->post = Fetchers::of( 'post' )->get_check( $this->get_obj_id(), $this->ctx->out );
            $this->ctx->require_cap( 'edit_post', (int) $this->post->ID );
        }
        return (string) $this->post->post_type;
    }

    protected function object_taxonomies( string $object_type ): array {
        return array_values( array_map( 'strval', (array) \get_object_taxonomies( $object_type ) ) );
    }

    protected function get_object_terms( $object_id, array $taxonomies, array $args ) {
        return \wp_get_object_terms( (int) $object_id, 1 === count( $taxonomies ) ? (string) reset( $taxonomies ) : $taxonomies, $args );
    }

    protected function term_slug_by_id( $term_id, string $taxonomy ): ?string {
        $term = Fetchers::term_by( 'id', $term_id, $taxonomy );
        return $term ? (string) $term->slug : null;
    }

    protected function set_object_terms( $object_id, $terms, string $taxonomy, bool $append ) {
        foreach ( (array) $terms as $term ) {
            $found = \term_exists( is_numeric( $term ) ? (int) $term : $term, $taxonomy );
            if ( is_array( $found ) ) {
                $this->require_assign( (int) $found['term_id'], $taxonomy );
            }
        }
        return \wp_set_object_terms( (int) $object_id, $terms, $taxonomy, $append );
    }

    protected function remove_object_terms( $object_id, array $terms, string $taxonomy ) {
        $remove = array();
        foreach ( $terms as $term ) {
            $info = \term_exists( $term, $taxonomy );
            if ( is_array( $info ) ) {
                $remove[ (int) $info['term_taxonomy_id'] ] = (int) $info['term_id'];
            }
        }
        return $this->write_remaining( (int) $object_id, $taxonomy, $remove );
    }

    protected function delete_object_term_relationships( $object_id, string $taxonomy ) {
        $remove = array();
        $terms  = \wp_get_object_terms( (int) $object_id, $taxonomy );
        foreach ( is_array( $terms ) ? $terms : array() as $term ) {
            $remove[ (int) $term->term_taxonomy_id ] = (int) $term->term_id;
        }
        return $this->write_remaining( (int) $object_id, $taxonomy, $remove );
    }

    private function write_remaining( int $object_id, string $taxonomy, array $remove ) {
        $current = \wp_get_object_terms( $object_id, $taxonomy );
        if ( ! is_array( $current ) ) {
            return $current;
        }
        $remaining = array();
        $removing  = false;
        foreach ( $current as $term ) {
            if ( isset( $remove[ (int) $term->term_taxonomy_id ] ) ) {
                $this->require_assign( (int) $term->term_id, $taxonomy, (string) $term->name );
                $removing = true;
                continue;
            }
            $remaining[] = (int) $term->term_id;
        }
        if ( ! $removing ) {
            return false;
        }
        $result = \wp_set_object_terms( $object_id, $remaining, $taxonomy, false );
        return ( function_exists( 'is_wp_error' ) && \is_wp_error( $result ) ) ? $result : true;
    }

    private function require_assign( int $term_id, string $taxonomy, string $name = '' ): void {
        if ( ! \current_user_can( 'assign_term', $term_id ) ) {
            if ( '' === $name ) {
                $term = Fetchers::term_by( 'id', $term_id, $taxonomy );
                $name = $term ? (string) $term->name : '';
            }
            $this->ctx->out->error( sprintf( self::MSG_ASSIGN_CAP, $term_id, $name, $taxonomy ) );
        }
    }
}
