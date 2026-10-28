<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Terms_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class User_Term_Handler extends Terms_Command {

    protected $obj_type = 'user';

    const WRITE_REFUSED = 'user term add, set and remove are refused by the route table (plan Q19).';

    protected function get_object_type(): string {
        return $this->obj_type;
    }

    protected function object_taxonomies( string $object_type ): array {
        return array_values( (array) \get_object_taxonomies( $object_type ) );
    }

    protected function get_object_terms( $object_id, array $taxonomies, array $args ) {
        return \wp_get_object_terms( $object_id, $taxonomies, $args );
    }

    protected function term_slug_by_id( $term_id, string $taxonomy ): ?string {
        throw new \LogicException( self::WRITE_REFUSED );
    }

    protected function set_object_terms( $object_id, $terms, string $taxonomy, bool $append ) {
        throw new \LogicException( self::WRITE_REFUSED );
    }

    protected function remove_object_terms( $object_id, array $terms, string $taxonomy ) {
        throw new \LogicException( self::WRITE_REFUSED );
    }

    protected function delete_object_term_relationships( $object_id, string $taxonomy ) {
        throw new \LogicException( self::WRITE_REFUSED );
    }
}
