<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Terms_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




































final class Post_Term_Adapter extends Terms_Command {

    protected $obj_type = 'post';

    
    private ?object $post = null;

    const MSG_READ_CAP = "'wp %s' needs the read_post capability for post %d: wp_get_post_full reads it through the REST API.";

    const MSG_EDIT_CAP = "'wp %s' needs the edit_post capability for post %d: wp_add_post_terms changes its terms only for users who can edit it.";

    const MSG_TRUNCATED = "Post %d has more terms in '%s' than wp_get_post_full returns in one call, so 'wp %s' cannot list them all.";

    const MSG_CLEANED = "wp_cli_run refuses the term '%s': wp_add_post_terms stores slugs and names through sanitize_text_field(), which would change it, so a different term than the one named would be attached or created. Name the term with --by=id.";

    const MSG_TAXONOMY_KEY = "wp_cli_run refuses the taxonomy '%s': wp_add_post_terms reads taxonomy names through sanitize_key(), which would change it.";

    
    
    

    





    public function preflight_ids( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        Ids::require_digits( $ctx, isset( $args[0] ) ? $args[0] : '', 'a post ID' );
        if ( 'id' === (string) Read_Value::flag( $inv->assoc, 'by' ) ) {
            Ids::require_all( $ctx, array_slice( $args, 2 ), 'a term ID' );
        }
    }

    




    public function plan_add( Invocation $inv ): array {
        return self::plan_write( $inv, true );
    }

    




    public function plan_set( Invocation $inv ): array {
        return self::plan_write( $inv, false );
    }

    







    private static function plan_write( Invocation $inv, bool $append ): array {
        $args     = array_values( $inv->args );
        $post_id  = array_shift( $args );
        $taxonomy = (string) array_shift( $args );
        $by_id    = 'id' === (string) Read_Value::flag( $inv->assoc, 'by' );
        if ( ! Ids::is_digits( $post_id ) || ! $args ) {
            return array();
        }
        $terms = array();
        foreach ( $args as $term ) {
            if ( $by_id && ! Ids::is_digits( $term ) ) {
                return array();
            }
            $terms[] = $by_id ? (int) $term : (string) $term;
        }
        return array(
            array(
                'tool'      => 'wp_add_post_terms',
                'arguments' => array(
                    'post_id'  => (int) $post_id,
                    'taxonomy' => $taxonomy,
                    'terms'    => $terms,
                    'append'   => $append,
                    'by'       => $by_id ? 'id' : 'slug',
                ),
            ),
        );
    }

    
    
    

    





    protected function get_object_type(): string {
        if ( null === $this->post ) {
            $listing = 'list' === $this->subcommand();
            $tool    = $listing ? 'wp_get_post_full' : 'wp_add_post_terms';
            $command = $this->inv->command;
            $this->post = Fetchers::of( 'post' )->get_check_for(
                $this->ctx,
                $this->inv,
                $tool,
                $this->get_obj_id(),
                static function ( object $post ) use ( $listing, $command ): ?string {
                    if ( $listing ) {
                        return \current_user_can( 'read_post', (int) $post->ID ) ? null : sprintf( self::MSG_READ_CAP, $command, (int) $post->ID );
                    }
                    return \current_user_can( 'edit_post', (int) $post->ID ) ? null : sprintf( self::MSG_EDIT_CAP, $command, (int) $post->ID );
                }
            );
        }
        return (string) $this->post->post_type;
    }

    
    protected function object_taxonomies( string $object_type ): array {
        return array_values( array_map( 'strval', (array) \get_object_taxonomies( $object_type ) ) );
    }

    










    protected function get_object_terms( $object_id, array $taxonomies, array $args ) {
        if ( 'list' !== $this->subcommand() ) {
            return \wp_get_object_terms( (int) $object_id, 1 === count( $taxonomies ) ? (string) reset( $taxonomies ) : $taxonomies, $args );
        }

        $terms = $this->listed_terms( (int) $object_id, $taxonomies );
        if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
            $ids = array();
            foreach ( $terms as $term ) {
                $ids[] = $term->term_id;
            }
            return $ids;
        }
        return $terms;
    }

    







    private function listed_terms( int $object_id, array $taxonomies ): array {
        $by_taxonomy = $this->read_terms( $object_id, $taxonomies );
        $terms       = array();
        foreach ( array_values( array_unique( array_map( 'strval', $taxonomies ) ) ) as $taxonomy ) {
            foreach ( isset( $by_taxonomy[ $taxonomy ] ) && is_array( $by_taxonomy[ $taxonomy ] ) ? $by_taxonomy[ $taxonomy ] : array() as $term ) {
                $terms[] = self::term_object( $term, $taxonomy );
            }
        }
        if ( count( $taxonomies ) > 1 ) {
            $terms = self::sort_by_name( $terms );
        }
        return $terms;
    }

    






    private function read_terms( int $object_id, array $taxonomies ): array {
        $base = Post_Adapter::read_base( $this->get_object_type(), $this->inv->command );
        if ( ! is_array( $base ) ) {
            $this->ctx->out->error( $base );
        }
        $result = $this->ctx->delegate(
            'wp_get_post_full',
            array(
                'id'               => $object_id,
                'post_type'        => $base[0],
                'include'          => array( 'terms' ),
                'terms_taxonomies' => array_values( array_unique( array_map( 'strval', $taxonomies ) ) ),
                'full_terms'       => true,
            )
        );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['terms_truncated'] ) && is_array( $result['payload']['terms_truncated'] ) ) {
            $this->ctx->out->error( sprintf( self::MSG_TRUNCATED, $object_id, implode( ', ', array_keys( $result['payload']['terms_truncated'] ) ), $this->inv->command ) );
        }
        return isset( $result['payload']['terms'] ) && is_array( $result['payload']['terms'] ) ? $result['payload']['terms'] : array();
    }

    






    private static function term_object( array $term, string $taxonomy ): object {
        return (object) array(
            'term_id'          => (int) $term['id'],
            'name'             => (string) $term['name'],
            'slug'             => (string) $term['slug'],
            'term_group'       => isset( $term['term_group'] ) ? (int) $term['term_group'] : 0,
            'term_taxonomy_id' => isset( $term['term_taxonomy_id'] ) ? (int) $term['term_taxonomy_id'] : 0,
            'taxonomy'         => $taxonomy,
            'description'      => isset( $term['description'] ) ? (string) $term['description'] : '',
            'parent'           => isset( $term['parent'] ) ? (int) $term['parent'] : 0,
            'count'            => isset( $term['count'] ) ? (int) $term['count'] : 0,
            'filter'           => 'raw',
        );
    }

    
    protected function term_slug_by_id( $term_id, string $taxonomy ): ?string {
        $term = Fetchers::term_by( 'id', $term_id, $taxonomy );
        return $term ? (string) $term->slug : null;
    }

    






    protected function prepare_terms( string $field, array $terms, string $taxonomy ): array {
        if ( 'id' !== $field ) {
            return $terms;
        }
        parent::prepare_terms( $field, $terms, $taxonomy );
        $ids = array();
        foreach ( $terms as $term_id ) {
            $ids[] = (int) $term_id;
        }
        return $ids;
    }

    







    protected function set_object_terms( $object_id, $terms, string $taxonomy, bool $append ): array {
        if ( function_exists( 'sanitize_key' ) && \sanitize_key( $taxonomy ) !== $taxonomy ) {
            $this->ctx->out->error( sprintf( self::MSG_TAXONOMY_KEY, $taxonomy ) );
        }
        $terms  = array_values( (array) $terms );
        $by_ids = $this->all_ids( $terms );
        $result = $this->ctx->delegate(
            'wp_add_post_terms',
            array(
                'post_id'  => (int) $object_id,
                'taxonomy' => $taxonomy,
                'terms'    => $by_ids ? $terms : array_map( 'strval', $terms ),
                'append'   => $append,
                'by'       => $by_ids ? 'id' : 'slug',
            )
        );
        if ( ! $result['ok'] ) {
            
            
            $this->ctx->out->error( (string) $result['error'] );
        }
        return isset( $result['payload']['term_taxonomy_ids'] ) ? (array) $result['payload']['term_taxonomy_ids'] : array();
    }

    







    private function all_ids( array $terms ): bool {
        $by_ids = true;
        foreach ( $terms as $term ) {
            if ( is_int( $term ) ) {
                continue;
            }
            $by_ids = false;
            if ( function_exists( 'sanitize_text_field' ) && \sanitize_text_field( (string) $term ) !== (string) $term ) {
                $this->ctx->out->error( sprintf( self::MSG_CLEANED, (string) $term ) );
            }
        }
        return $by_ids;
    }

    






    protected function remove_object_terms( $object_id, array $terms, string $taxonomy ): bool {
        throw new \LogicException( 'post term remove is an own handler (Post_Term_Handler).' );
    }

    





    protected function delete_object_term_relationships( $object_id, string $taxonomy ): void {
        throw new \LogicException( 'post term remove is an own handler (Post_Term_Handler).' );
    }

    
    
    

    
    private function subcommand(): string {
        return isset( $this->inv->leaf['name'] ) ? (string) $this->inv->leaf['name'] : '';
    }

    







    private static function sort_by_name( array $terms ): array {
        $keyed = array();
        foreach ( $terms as $index => $term ) {
            $name    = (string) $term->name;
            $name    = function_exists( 'remove_accents' ) ? \remove_accents( $name ) : $name;
            $keyed[] = array( strtolower( $name ), $index, $term );
        }
        usort(
            $keyed,
            static function ( array $a, array $b ): int {
                $cmp = strcmp( $a[0], $b[0] );
                return 0 !== $cmp ? $cmp : $a[1] - $b[1];
            }
        );
        $sorted = array();
        foreach ( $keyed as $entry ) {
            $sorted[] = $entry[2];
        }
        return $sorted;
    }
}
