<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Terms_Command extends Command_Handler {

    protected $obj_type = '';

    protected $obj_id = null;

    protected $obj_fields = array( 'term_id', 'name', 'slug', 'taxonomy' );

    protected $ctx = null;

    protected $inv = null;

    const UNKNOWN_ID = "Term %s doesn't exist in the '%s' taxonomy. wp_cli_run refuses --by=id with an unknown ID, which WP-CLI would skip without a word%s.";

    const UNSAFE_TERM = "wp_cli_run refuses to write a term named with a control character or a non-ASCII byte, or ending in a space: the term tables compare names and slugs without regard to accents, control characters or trailing spaces, so such a value can address another term. Name the term with --by=id, as `%s --fields=term_id,name,slug` prints it.";

    const NOT_REMOVED = "None of the terms named is on %s %s in the '%s' taxonomy, so nothing was removed.";

    abstract protected function get_object_type(): string;

    abstract protected function object_taxonomies( string $object_type ): array;

    abstract protected function get_object_terms( $object_id, array $taxonomies, array $args );

    abstract protected function term_slug_by_id( $term_id, string $taxonomy ): ?string;

    abstract protected function set_object_terms( $object_id, $terms, string $taxonomy, bool $append );

    abstract protected function remove_object_terms( $object_id, array $terms, string $taxonomy );

    abstract protected function delete_object_term_relationships( $object_id, string $taxonomy );

    protected function default_category(): int {
        return function_exists( 'get_option' ) ? (int) \get_option( 'default_category' ) : 0;
    }

    protected function object_term_ids( $object_id, string $taxonomy ): array {
        $ids = $this->get_object_terms( $object_id, array( $taxonomy ), array( 'fields' => 'ids' ) );
        if ( ! is_array( $ids ) ) {
            return array();
        }
        $ids = array_map( 'intval', $ids );
        sort( $ids );
        return array_values( $ids );
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $assoc_args = array_merge( array( 'format' => 'table' ), $inv->assoc );

        $args           = array_values( $inv->args );
        $object_id      = array_shift( $args );
        $taxonomy_names = $args;
        $taxonomy_args  = array();

        $this->set_obj_id( $object_id );

        foreach ( $taxonomy_names as $taxonomy ) {
            $this->taxonomy_exists( (string) $taxonomy );
        }

        if ( 'ids' === $assoc_args['format'] ) {
            $taxonomy_args['fields'] = 'ids';
        }

        $items = $this->get_object_terms( $object_id, $taxonomy_names, $taxonomy_args );

        $formatter = $this->get_formatter( $assoc_args );
        $formatter->display_items( $items );
    }

    public function remove( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $assoc_args = $inv->assoc;
        $args       = array_values( $inv->args );
        $object_id  = array_shift( $args );
        $taxonomy   = (string) array_shift( $args );
        $terms      = $args;

        $this->set_obj_id( $object_id );

        $this->taxonomy_exists( $taxonomy );

        $terms = $this->named_terms( Read_Value::flag( $assoc_args, 'by' ), $terms, $taxonomy );

        if ( Read_Value::flag( $assoc_args, 'all' ) ) {
            $this->remove_all( $object_id, $taxonomy, $terms );
            return;
        }

        if ( ! $terms ) {
            $ctx->out->error( 'Please specify one or more terms, or use --all.' );
        }

        $before = $this->object_term_ids( $object_id, $taxonomy );

        $result = $this->remove_object_terms( $object_id, $terms, $taxonomy );

        $label = count( $terms ) > 1 ? 'terms' : 'term';

        if ( Db_Object_Command::is_failure( $result ) ) {
            $this->mark_if_changed( $object_id, $taxonomy, $before );
            $ctx->out->error( "Failed to remove {$label}." );
        }

        if ( ! $this->mark_if_changed( $object_id, $taxonomy, $before ) ) {
            $ctx->fail_missing_target( sprintf( self::NOT_REMOVED, $this->obj_type, $object_id, $taxonomy ) );
        }

        $ctx->out->success( "Removed {$label}." );
    }

    private function remove_all( ?string $object_id, string $taxonomy, array $terms ): void {
        if ( $terms ) {
            $this->ctx->out->error( 'No need to specify terms while removing all terms.' );
        }

        $before = $this->object_term_ids( $object_id, $taxonomy );

        $result = $this->delete_object_term_relationships( $object_id, $taxonomy );

        $message = 'Removed all terms.';
        if ( 'category' === $taxonomy ) {
            $this->set_default_category( $object_id, $taxonomy, $before );
            $message = 'Removed all terms and set default term.';
        }

        $this->mark_if_changed( $object_id, $taxonomy, $before );

        if ( Db_Object_Command::is_failure( $result ) ) {
            $this->ctx->out->error( 'Failed to remove all terms.' );
        }

        $this->ctx->out->success( $message );
    }

    private function set_default_category( ?string $object_id, string $taxonomy, array $before ): void {
        $default_category = $this->default_category();
        $default_category = ( ! empty( $default_category ) ) ? $default_category : 1;
        $default_category = $this->set_object_terms( $object_id, array( $default_category ), $taxonomy, true );

        if ( Db_Object_Command::is_failure( $default_category ) ) {
            $this->mark_if_changed( $object_id, $taxonomy, $before );
            $this->ctx->out->error( 'Failed to set default term.' );
        }
    }

    public function add( Invocation $inv, Context $ctx ): void {
        $this->write_terms( $inv, $ctx, true );
    }

    public function set( Invocation $inv, Context $ctx ): void {
        $this->write_terms( $inv, $ctx, false );
    }

    public function describe( Invocation $inv ): array {
        $this->begin(
            $inv,
            new Context(
                new Output(),
                static function (): ?array {
                    return null;
                },
                static function (): bool {
                    return false;
                }
            )
        );
        $args      = array_values( $inv->args );
        $object_id = array_shift( $args );
        $taxonomy  = (string) array_shift( $args );
        $this->set_obj_id( $object_id );
        $current = $this->object_term_ids( $object_id, $taxonomy );
        $now     = $current ? 'term ids ' . implode( ', ', $current ) : 'no terms';
        $sub     = isset( $inv->leaf['name'] ) ? (string) $inv->leaf['name'] : '';
        $by      = (string) Read_Value::flag( $inv->assoc, 'by', 'slug' );
        $named   = $args ? ( 'id' === $by ? 'term ids ' : '' ) . implode( ', ', array_map( 'strval', $args ) ) : '';
        $effect  = self::describe_effect( $sub, $taxonomy, $named, (bool) Read_Value::flag( $inv->assoc, 'all' ) );
        return array( sprintf( "%s %s, taxonomy '%s': %s → %s", ucfirst( $this->obj_type ), $object_id, $taxonomy, $now, $effect ) );
    }

    private static function describe_effect( string $sub, string $taxonomy, string $named, bool $all ): string {
        if ( 'remove' === $sub && $all ) {
            return 'remove every term' . ( 'category' === $taxonomy ? ', then set the default category' : '' );
        }
        if ( 'remove' === $sub ) {
            return 'remove ' . $named;
        }
        if ( 'set' === $sub ) {
            return 'replace with ' . $named;
        }
        return 'add ' . $named;
    }

    protected function taxonomy_exists( string $taxonomy ): void {
        $taxonomy_names = $this->object_taxonomies( $this->get_object_type() );

        if ( ! in_array( $taxonomy, $taxonomy_names, true ) ) {
            $this->ctx->out->error( "Invalid taxonomy {$taxonomy}." );
        }
    }

    protected function prepare_terms( string $field, array $terms, string $taxonomy ): array {
        if ( 'id' === $field ) {
            $new_terms = array();
            foreach ( $terms as $term_id ) {
                $slug = $this->term_slug_by_id( $term_id, $taxonomy );
                if ( null === $slug ) {
                    $sub = isset( $this->inv->leaf['name'] ) ? (string) $this->inv->leaf['name'] : '';
                    $this->ctx->out->error( sprintf( self::UNKNOWN_ID, $term_id, $taxonomy, 'set' === $sub ? ', and then remove every other term' : '' ) );
                }
                $new_terms[] = $slug;
            }
            $terms = $new_terms;
        }
        return $terms;
    }

    protected function set_obj_id( ?string $obj_id ): void {
        $this->obj_id = $obj_id;
    }

    protected function get_obj_id(): ?string {
        return $this->obj_id;
    }

    protected function get_formatter( array &$assoc_args ): Formatter {
        return new Formatter( $this->ctx->out, $assoc_args, $this->obj_fields, $this->obj_type );
    }

    private function begin( Invocation $inv, Context $ctx ): void {
        $this->inv = $inv;
        $this->ctx = $ctx;
    }

    private function write_terms( Invocation $inv, Context $ctx, bool $append ): void {
        $this->begin( $inv, $ctx );
        $args      = array_values( $inv->args );
        $object_id = array_shift( $args );
        $taxonomy  = (string) array_shift( $args );
        $terms     = $args;

        $this->set_obj_id( $object_id );

        $this->taxonomy_exists( $taxonomy );

        $terms = $this->named_terms( Read_Value::flag( $inv->assoc, 'by' ), $terms, $taxonomy );

        $before = $this->object_term_ids( $object_id, $taxonomy );
        $result = $this->set_object_terms( $object_id, $terms, $taxonomy, $append );
        $this->mark_if_changed( $object_id, $taxonomy, $before );

        $label = count( $terms ) > 1 ? 'terms' : 'term';
        $done  = $append ? 'Added' : 'Set';
        $verb  = $append ? 'add' : 'set';
        if ( ! Db_Object_Command::is_failure( $result ) ) {
            $ctx->out->success( "{$done} {$label}." );
        } else {
            $ctx->out->error( "Failed to {$verb} {$label}." );
        }
    }

    private function named_terms( $field, array $terms, string $taxonomy ): array {
        if ( 'id' !== $field ) {
            $this->guard_terms( $terms );
        }
        if ( $field ) {
            $terms = $this->prepare_terms( (string) $field, $terms, $taxonomy );
        }
        return $terms;
    }

    private function guard_terms( array $terms ): void {
        foreach ( $terms as $term ) {
            if ( Meta_Command::unsafe_key( (string) $term ) ) {
                $words = explode( ' ', $this->inv->command );
                array_pop( $words );
                $words[] = 'list';
                $this->ctx->out->error( sprintf( self::UNSAFE_TERM, implode( ' ', $words ) ) );
            }
        }
    }

    private function mark_if_changed( $object_id, string $taxonomy, array $before ): bool {
        if ( $this->object_term_ids( $object_id, $taxonomy ) !== $before ) {
            $this->ctx->mark_changed();
            return true;
        }
        return false;
    }
}
