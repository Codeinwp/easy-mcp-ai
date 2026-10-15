<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















































final class Term_Adapter extends Command_Handler {

    
    const FIELDS = array( 'term_id', 'term_taxonomy_id', 'name', 'slug', 'description', 'parent', 'count' );

    
    const ROW_CAP = 1000;

    
    const PAGE = 100;

    
    const TERM_MISSING = "Term doesn't exist.";

    
    const INVALID_TAXONOMY = 'Invalid taxonomy.';

    
    const MISSING_PARENT = 'Parent term does not exist.';

    
    const EMPTY_NAME = 'A name is required for this term.';

    
    const MISSING_ERRORS = array( 'Term not found.', 'Term does not exist.' );

    
    const QUERY_ARGS = array(
        'search'            => 'text',
        'parent'            => 'id0',
        'hide_empty'        => 'bool',
        'orderby'           => 'orderby',
        'order'             => 'order',
        'include'           => 'ids',
        'exclude'           => 'ids',
        'exclude_tree'      => 'ids',
        'term_taxonomy_id'  => 'ids',
        'slug'              => 'list',
        'name'              => 'list',
        'child_of'          => 'id0',
        'childless'         => 'bool',
        'name__like'        => 'text',
        'description__like' => 'text',
        'pad_counts'        => 'bool',
        'meta_key'          => 'text',
        'meta_value'        => 'text',
        'meta_compare'      => 'text',
        'number'            => 'paging',
        'offset'            => 'paging',
        'term_id'           => 'term_id',
    );

    
    const ORDERBY = array( 'id', 'name', 'slug', 'count', 'include', 'term_id', 'term_group', 'description', 'parent', 'term_taxonomy_id', 'none', 'meta_value', 'meta_value_num' );

    
    const QUERY_REFUSED = array(
        'get'                    => 'it changes which terms WordPress returns in ways wp_list_terms does not offer',
        'hierarchical'           => 'it changes which terms WordPress returns in ways wp_list_terms does not offer',
        'object_ids'             => 'wp_list_terms lists a taxonomy, not the terms of given objects; use `post term list`',
        'meta_query'             => 'a nested meta query could name a meta key this connection may not read; use --meta_key, --meta_value and --meta_compare',
        'cache_domain'           => 'it is a cache setting of the query, with no effect on the result',
        'cache_results'          => 'it is a cache setting of the query, with no effect on the result',
        'update_term_meta_cache' => 'it is a cache setting of the query, with no effect on the result',
        'lazy_load_term_meta'    => 'it is a cache setting of the query, with no effect on the result',
        'suppress_filter'        => 'it would skip the filters other plugins rely on to shape the result',
    );

    const PARAM_REFUSED = ' --%s parameter is not available through wp_cli_run: %s';

    const NO_FIELD = "wp_cli_run refuses 'wp term update' with no field to change: WP-CLI would print 'Success: Term updated.' having changed nothing. Pass --name, --slug, --description or --parent.";

    const NEEDS_VALUE = '--%s needs a value: wp_cli_run does not pass a bare --%s on, which WP-CLI would store as the text 1.';

    const EMPTY_SLUG = "--slug='' is not available through wp_cli_run: WP-CLI regenerates the slug from the name, and wp_update_term refuses an empty slug. Pass the slug to set.";

    const NOT_AN_ID = '--%s=%s is not a term ID: wp_cli_run passes only a whole number of 0 or more, where WordPress would read %s as %d.';

    const OVER_ROW_CAP = "wp_cli_run returns at most %d terms in one 'wp term list' command, and this one matches %d. Page through them with --number=<n> --offset=<n>, or count them with --format=count.";

    const COUNT_OVER_CAP = "wp_cli_run counts at most %d terms of the hierarchical taxonomy '%s' with --hide_empty, and this one matches more: WordPress keeps an empty parent of a non-empty term in the list but leaves it out of its own count, so wp_cli_run counts the list itself. Narrow the list with another filter.";

    const TAXONOMY_ARGS = "wp_cli_run cannot list taxonomy '%s' as WP-CLI does: it is registered with default query arguments (%s), which the REST terms route wp_list_terms reads applies over the command's own, while WP-CLI's get_terms() ignores them. wp_list_terms lists its terms with those arguments applied.";

    const SEVERAL_TAXONOMIES = 'wp_cli_run lists several taxonomies only with --orderby=term_id (or id) or --format=count: WordPress sorts the combined list in the database by its collation, which cannot be rebuilt from one list per taxonomy. List one taxonomy per command.';

    const FLAT_PARENT = "--parent is not available through wp_cli_run for the non-hierarchical taxonomy '%s': the REST terms route wp_list_terms reads ignores it there, and would return every term.";

    const ORDER_VALUE = "--order=%s is not available through wp_cli_run: WordPress reads any value but ASC as DESC. Pass --order=ASC or --order=DESC.";

    const ORDERBY_VALUE = '--orderby=%s is not available through wp_cli_run: WordPress sorts by name for a value it does not know, and wp_list_terms takes only: %s.';

    const OFFSET_ALONE = '--offset without --number is not available through wp_cli_run: WordPress ignores an offset when no number is set, and would return every term.';

    const TERM_ID_ALONE = '--term_id lists one term, and WP-CLI ignores every other filter beside it (%s) and every taxonomy after the first. Pass --term_id alone, with one taxonomy.';

    const TERM_ID_EMPTY = '--term_id=%s names no term: WP-CLI ignores it and lists every term. Pass a term ID, or leave --term_id out.';

    const TERM_ID_NONE = "--term_id=%s is not a term of '%s', so the list is empty. WP-CLI 2.12.0 ends with a PHP error here instead.";

    const INCLUDE_EXCLUDE = '--include cannot be combined with --exclude or --exclude_tree: WordPress ignores both exclusions when --include is given.';

    const CHILD_OF_SEVERAL = '--child_of is not available with several taxonomies: WordPress ignores it then.';

    const EMPTY_LIST = "--%s='' is not available through wp_cli_run: WordPress reads an empty list as no filter and returns every term.";

    const NOT_A_LIST = '--%s=%s is not a list of term IDs: wp_cli_run passes only whole numbers above 0, separated by commas or spaces.';

    const TEXT_REWRITTEN = '--%s=%s is not available through wp_cli_run: wp_list_terms cleans it with sanitize_text_field() into %s, so it would search for something else.';

    const MIGRATE_CAP = "wp_cli_run cannot migrate a term of '%s': the migration deletes the old term, and that needs the %s capability.";

    const DELETE_DEFAULT = "%s %d is the default term of '%s', and the default term of a taxonomy can never be deleted: WordPress refuses it to every role, administrators included. Make another term the default first.";

    const MIGRATE_DELETE_DENIED = "wp_cli_run cannot migrate term %d: WordPress does not let you delete it (the default term of a taxonomy can never be deleted), and a migration ends by deleting the old term.";

    const MIGRATE_ASSIGN_CAP = "wp_cli_run cannot migrate this term to '%s': assigning its terms to posts needs the %s capability.";

    const MIGRATE_EDIT_POST = 'wp_cli_run cannot migrate this term: you may not edit post %s, which carries it, and a migration assigns the new term to every such post.';

    const MIGRATE_OTHER_TYPE = "wp_cli_run cannot migrate this term to '%3\$s': post %1\$s, which carries it, is a %2\$s, and '%3\$s' is not registered for that post type. wp_add_post_terms refuses that pairing (WP-CLI would attach the term anyway), and a migration does not stop halfway. Nothing was changed.";

    const MIGRATE_NOT_REST = "Taxonomy %s is not exposed in the REST API (show_in_rest is false), so wp_cli_run cannot migrate a term %s it: the term tools refuse such a taxonomy.";

    const MIGRATE_PAUSES = "'wp term migrate' runs %s, which needs a person's approval on this site, and wp_cli_run does not start a migration it could not finish in one run (the term would be left in both taxonomies). Run its steps one by one instead: `term create`, `post term add` for each post, then `term delete`.";

    
    
    

    
    public function create( Invocation $inv, Context $ctx ): void {
        $args     = array_values( $inv->args );
        $taxonomy = (string) $args[0];
        $name     = (string) $args[1];

        $slug        = $this->text_arg( $inv, $ctx, 'slug' );
        $description = $this->text_arg( $inv, $ctx, 'description' );
        $parent      = $this->id_arg( $inv, $ctx, 'parent' );
        $porcelain   = Read_Value::flag( $inv->assoc, 'porcelain' );

        Fetchers::require_tool( $ctx, $inv, 'wp_create_term' );
        self::refuse_create( $ctx, $taxonomy, $name, $parent );

        $result = $ctx->delegate( 'wp_create_term', self::create_arguments( $taxonomy, $name, $slug, $description, $parent ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $term_id = (int) ( $result['payload']['id'] ?? 0 );
        $ctx->mark_changed();
        if ( $porcelain ) {
            $ctx->out->line( (string) $term_id );
        } else {
            $ctx->out->success( "Created {$taxonomy} {$term_id}." );
        }
    }

    







    private static function refuse_create( Context $ctx, string $taxonomy, string $name, ?int $parent ): void {
        if ( null !== $parent && $parent > 0 && ! \term_exists( $parent ) ) {
            $ctx->out->error( self::MISSING_PARENT );
        }
        if ( ! \taxonomy_exists( $taxonomy ) ) {
            $ctx->out->error( self::INVALID_TAXONOMY );
        }
        if ( '' === trim( $name ) ) {
            $ctx->out->error( self::EMPTY_NAME );
        }
    }

    








    private static function create_arguments( string $taxonomy, string $name, ?string $slug, ?string $description, ?int $parent ): array {
        $arguments = array(
            'name'     => $name,
            'taxonomy' => $taxonomy,
            'slug'     => null !== $slug ? $slug : \sanitize_title( $name ),
        );
        if ( '' === $arguments['slug'] ) {
            unset( $arguments['slug'] );
        }
        if ( null !== $description && '' !== $description ) {
            $arguments['description'] = $description;
        }
        if ( null !== $parent && $parent > 0 ) {
            $arguments['parent'] = $parent;
        }
        return $arguments;
    }

    
    
    

    
    public function get( Invocation $inv, Context $ctx ): void {
        $args     = array_values( $inv->args );
        $taxonomy = (string) $args[0];
        $id       = $this->resolve_term( $inv, $ctx, 'wp_get_term', $taxonomy, (string) $args[1], 'assign_terms', null );

        $result = $ctx->delegate(
            'wp_get_term',
            array(
                'term_id'  => $id,
                'taxonomy' => $taxonomy,
            )
        );
        if ( ! $result['ok'] ) {
            $ctx->out->error( self::term_error( (string) $result['error'] ) );
        }
        $term = self::term_object( $result['payload'], $taxonomy );

        $assoc_args = $inv->assoc;
        if ( empty( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = array_keys( get_object_vars( $term ) );
        }
        $formatter = new Formatter( $ctx->out, $assoc_args, self::FIELDS, 'term' );
        $formatter->display_item( $term );
    }

    
    
    

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $taxonomies = array_map( 'strval', array_values( $inv->args ) );

        Fetchers::require_tool( $ctx, $inv, 'wp_list_terms' );
        foreach ( $taxonomies as $taxonomy ) {
            if ( ! \taxonomy_exists( $taxonomy ) ) {
                $ctx->out->error( "Taxonomy {$taxonomy} doesn't exist." );
            }
        }

        $assoc_args = $inv->assoc;
        $formatter  = new Formatter( $ctx->out, $assoc_args, self::FIELDS, 'term' );
        
        $query = $assoc_args;

        if ( array_key_exists( 'term_id', $query ) ) {
            $terms = $this->list_one( $inv, $ctx, $taxonomies, $query );
        } else {
            $terms = $this->list_many( $inv, $ctx, $taxonomies, $query, $formatter );
            if ( null === $terms ) {
                return;
            }
        }

        if ( 'ids' === $formatter->format ) {
            $ids = array();
            foreach ( $terms as $term ) {
                $ids[] = $term->term_id;
            }
            $ctx->out->out( implode( ' ', $ids ) );
            return;
        }
        $formatter->display_items( $terms );
    }

    







    private function list_one( Invocation $inv, Context $ctx, array $taxonomies, array $query ): array {
        $raw = $query['term_id'];
        unset( $query['term_id'] );
        if ( $query || count( $taxonomies ) > 1 ) {
            $others = array_map(
                static function ( string $key ): string {
                    return '--' . $key;
                },
                array_keys( $query )
            );
            if ( count( $taxonomies ) > 1 ) {
                $others[] = 'taxonomy ' . implode( ', ', array_slice( $taxonomies, 1 ) );
            }
            $ctx->out->error( sprintf( self::TERM_ID_ALONE, implode( ', ', $others ) ) );
        }
        if ( ! is_string( $raw ) ) {
            $ctx->out->error( sprintf( self::NEEDS_VALUE, 'term_id', 'term_id' ) );
        }
        if ( empty( $raw ) ) {
            $ctx->out->error( sprintf( self::TERM_ID_EMPTY, "'" . $raw . "'" ) );
        }
        
        $id = (int) $raw;
        $result = $id > 0 ? $ctx->delegate(
            'wp_get_term',
            array(
                'term_id'  => $id,
                'taxonomy' => $taxonomies[0],
            )
        ) : array(
            'ok'    => false,
            'error' => self::MISSING_ERRORS[0],
        );
        if ( ! $result['ok'] ) {
            if ( null !== self::missing( (string) $result['error'] ) ) {
                
                
                $ctx->note( sprintf( self::TERM_ID_NONE, $raw, $taxonomies[0] ) );
                return array();
            }
            $ctx->out->error( (string) $result['error'] );
        }
        return array( self::term_object( $result['payload'], $taxonomies[0] ) );
    }

    






    private function list_many( Invocation $inv, Context $ctx, array $taxonomies, array $query, Formatter $formatter ): ?array {
        self::refuse_taxonomy_args( $ctx, $taxonomies );
        $filters                 = $this->query_filters( $ctx, $taxonomies, $query );
        list( $number, $offset ) = self::list_window( $ctx, $query );

        $count_only = 'count' === $formatter->format && ! $formatter->field;
        $several    = count( $taxonomies ) > 1;
        $orderby    = isset( $filters['orderby'] ) ? (string) $filters['orderby'] : 'name';
        if ( $several && ! $count_only && ! in_array( $orderby, array( 'id', 'term_id' ), true ) ) {
            $ctx->out->error( self::SEVERAL_TAXONOMIES );
        }
        if ( $count_only && 0 === $number ) {
            $ctx->out->out( (string) $this->count_terms( $ctx, $taxonomies, $filters ) );
            return null;
        }
        if ( ! $several ) {
            $rows = $this->fetch( $ctx, $taxonomies[0], $filters, $offset, $number, false )['rows'];
            return self::term_objects( $rows, $taxonomies[0] );
        }
        return $this->merged_terms( $ctx, $taxonomies, $filters, $offset, $number );
    }

    









    private static function refuse_taxonomy_args( Context $ctx, array $taxonomies ): void {
        foreach ( $taxonomies as $taxonomy ) {
            $tax = \get_taxonomy( $taxonomy );
            if ( is_object( $tax ) && isset( $tax->args ) && is_array( $tax->args ) && $tax->args ) {
                $ctx->out->error( sprintf( self::TAXONOMY_ARGS, $taxonomy, implode( ', ', array_map( 'strval', array_keys( $tax->args ) ) ) ) );
            }
        }
    }

    








    private static function list_window( Context $ctx, array $query ): array {
        $number = isset( $query['number'] ) ? (int) $query['number'] : 0;
        $offset = isset( $query['offset'] ) ? (int) $query['offset'] : 0;
        if ( $offset > 0 && 0 === $number ) {
            $ctx->out->error( self::OFFSET_ALONE );
        }
        if ( $number > self::ROW_CAP ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, $number ) );
        }
        return array( $number, $offset );
    }

    









    private function count_terms( Context $ctx, array $taxonomies, array $filters ): int {
        $total = 0;
        foreach ( $taxonomies as $taxonomy ) {
            $exact = empty( $filters['hide_empty'] ) || ! \is_taxonomy_hierarchical( $taxonomy );
            if ( $exact ) {
                $total += $this->fetch( $ctx, $taxonomy, $filters, 0, 0, true )['total'];
            } else {
                $total += count( $this->fetch( $ctx, $taxonomy, $filters, 0, 0, false, sprintf( self::COUNT_OVER_CAP, self::ROW_CAP, $taxonomy ) )['rows'] );
            }
        }
        return $total;
    }

    








    private function merged_terms( Context $ctx, array $taxonomies, array $filters, int $offset, int $number ): array {
        $terms = array();
        foreach ( $taxonomies as $taxonomy ) {
            foreach ( self::term_objects( $this->fetch( $ctx, $taxonomy, $filters, 0, 0, false )['rows'], $taxonomy ) as $term ) {
                $terms[] = $term;
                if ( count( $terms ) > self::ROW_CAP ) {
                    $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, count( $terms ) ) );
                }
            }
        }
        $terms = self::sort_by_term_id( $terms, isset( $filters['order'] ) && 'desc' === $filters['order'] );
        return $number > 0 ? array_slice( $terms, $offset, $number ) : $terms;
    }

    






    private static function sort_by_term_id( array $terms, bool $desc ): array {
        $index = array();
        foreach ( $terms as $i => $term ) {
            $index[ spl_object_hash( $term ) ] = $i;
        }
        usort(
            $terms,
            static function ( object $a, object $b ) use ( $desc, $index ): int {
                if ( $a->term_id !== $b->term_id ) {
                    return ( $a->term_id < $b->term_id ? -1 : 1 ) * ( $desc ? -1 : 1 );
                }
                return $index[ spl_object_hash( $a ) ] - $index[ spl_object_hash( $b ) ];
            }
        );
        return $terms;
    }

    









    private function query_filters( Context $ctx, array $taxonomies, array $query ): array {
        $errors  = array();
        $filters = array( 'hide_empty' => false );
        foreach ( $query as $key => $value ) {
            $filters = array_merge( $filters, self::query_filter( (string) $key, $value, $errors ) );
        }
        $errors = array_merge( $errors, self::query_conflicts( $taxonomies, $filters ) );
        if ( $errors ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
        return $filters;
    }

    








    private static function query_filter( string $key, $value, array &$errors ): array {
        if ( ! isset( self::QUERY_ARGS[ $key ] ) ) {
            $errors[] = sprintf( self::PARAM_REFUSED, $key, isset( self::QUERY_REFUSED[ $key ] ) ? self::QUERY_REFUSED[ $key ] : 'it is not a get_terms() argument wp_list_terms takes, and WordPress would ignore it' );
            return array();
        }
        $kind = self::QUERY_ARGS[ $key ];
        if ( 'bool' === $kind ) {
            
            
            return array( $key => (bool) $value );
        }
        if ( true === $value || false === $value ) {
            $errors[] = sprintf( self::PARAM_REFUSED, $key, sprintf( 'it needs a value (--%s=<value>)', $key ) );
            return array();
        }
        return self::query_value( $key, $kind, (string) $value, $errors );
    }

    






    private static function query_value( string $key, string $kind, string $value, array &$errors ): array {
        switch ( $kind ) {
            case 'id0':
            case 'paging':
                return self::query_id( $key, $kind, $value, $errors );
            case 'ids':
                return self::query_ids( $key, $value, $errors );
            case 'list':
                if ( '' === $value ) {
                    $errors[] = ' ' . sprintf( self::EMPTY_LIST, $key );
                    return array();
                }
                return array( $key => array( $value ) );
            case 'orderby':
                return self::query_orderby( $value, $errors );
            case 'order':
                return self::query_order( $value, $errors );
        }
        return self::query_text( $key, $value, $errors );
    }

    








    private static function query_id( string $key, string $kind, string $value, array &$errors ): array {
        if ( ! ctype_digit( $value ) ) {
            $errors[] = ' ' . sprintf( self::NOT_AN_ID, $key, $value, "'" . $value . "'", (int) $value );
            return array();
        }
        if ( 'paging' === $kind || ( 'child_of' === $key && 0 === (int) $value ) ) {
            return array();
        }
        return array( $key => (int) $value );
    }

    






    private static function query_ids( string $key, string $value, array &$errors ): array {
        if ( '' === trim( $value ) ) {
            $errors[] = ' ' . sprintf( self::EMPTY_LIST, $key );
            return array();
        }
        $ids = array();
        foreach ( (array) preg_split( '/[\s,]+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY ) as $id ) {
            if ( ! ctype_digit( (string) $id ) || 0 === (int) $id ) {
                $errors[] = ' ' . sprintf( self::NOT_A_LIST, $key, $value );
                return array();
            }
            $ids[] = (int) $id;
        }
        return array( $key => array_values( array_unique( $ids ) ) );
    }

    






    private static function query_orderby( string $value, array &$errors ): array {
        $lower = strtolower( $value );
        if ( ! in_array( $lower, self::ORDERBY, true ) ) {
            $errors[] = ' ' . sprintf( self::ORDERBY_VALUE, $value, implode( ', ', self::ORDERBY ) );
            return array();
        }
        return array( 'orderby' => $lower );
    }

    





    private static function query_order( string $value, array &$errors ): array {
        $upper = strtoupper( $value );
        if ( 'ASC' !== $upper && 'DESC' !== $upper ) {
            $errors[] = ' ' . sprintf( self::ORDER_VALUE, $value );
            return array();
        }
        return array( 'order' => strtolower( $upper ) );
    }

    







    private static function query_text( string $key, string $value, array &$errors ): array {
        if ( 'search' === $key && function_exists( 'sanitize_text_field' ) && \sanitize_text_field( $value ) !== $value ) {
            $errors[] = ' ' . sprintf( self::TEXT_REWRITTEN, $key, $value, "'" . \sanitize_text_field( $value ) . "'" );
            return array();
        }
        if ( '' === $value && in_array( $key, array( 'search', 'name__like', 'description__like' ), true ) ) {
            return array();
        }
        return array( $key => $value );
    }

    








    private static function query_conflicts( array $taxonomies, array $filters ): array {
        $errors = array();
        if ( isset( $filters['include'] ) && ( isset( $filters['exclude'] ) || isset( $filters['exclude_tree'] ) ) ) {
            $errors[] = ' ' . self::INCLUDE_EXCLUDE;
        }
        if ( isset( $filters['child_of'] ) && count( $taxonomies ) > 1 ) {
            $errors[] = ' ' . self::CHILD_OF_SEVERAL;
        }
        if ( isset( $filters['parent'] ) ) {
            foreach ( $taxonomies as $taxonomy ) {
                if ( ! \is_taxonomy_hierarchical( $taxonomy ) ) {
                    $errors[] = ' ' . sprintf( self::FLAT_PARENT, $taxonomy );
                }
            }
        }
        return $errors;
    }

    

















    private function fetch( Context $ctx, string $taxonomy, array $filters, int $offset, int $number, bool $total_only, ?string $over_cap = null ): array {
        $per_page = $total_only ? 1 : self::PAGE;
        $page     = intdiv( $offset, $per_page ) + 1;
        $payload  = $this->fetch_page( $ctx, $taxonomy, $filters, $per_page, $page );
        $total    = (int) ( $payload['total'] ?? 0 );
        if ( $total_only ) {
            return array(
                'rows'  => array(),
                'total' => $total,
            );
        }
        if ( 0 === $number && $total > self::ROW_CAP ) {
            $ctx->out->error( self::over_cap( $over_cap, $total ) );
        }
        return array(
            'rows'  => $this->fetch_rows( $ctx, $taxonomy, $filters, $payload, $page, $offset % $per_page, $number, $over_cap ),
            'total' => $total,
        );
    }

    








    private function fetch_rows( Context $ctx, string $taxonomy, array $filters, array $payload, int $page, int $skip, int $number, ?string $over_cap ): array {
        $rows        = array();
        $undercounts = ! empty( $filters['hide_empty'] ) && \is_taxonomy_hierarchical( $taxonomy );
        while ( true ) {
            $page_rows = isset( $payload['terms'] ) && is_array( $payload['terms'] ) ? array_values( $payload['terms'] ) : array();
            if ( self::take_rows( $ctx, $rows, array_slice( $page_rows, $skip ), $number, $over_cap ) ) {
                return $rows;
            }
            if ( count( $page_rows ) < self::PAGE || ( ! $undercounts && $page >= (int) ( $payload['total_pages'] ?? 0 ) ) ) {
                return $rows;
            }
            $skip    = 0;
            $payload = $this->fetch_page( $ctx, $taxonomy, $filters, self::PAGE, ++$page );
        }
    }

    








    private static function take_rows( Context $ctx, array &$rows, array $page_rows, int $number, ?string $over_cap ): bool {
        foreach ( $page_rows as $row ) {
            $rows[] = (array) $row;
            if ( $number > 0 && count( $rows ) >= $number ) {
                return true;
            }
            if ( count( $rows ) > self::ROW_CAP ) {
                $ctx->out->error( self::over_cap( $over_cap, count( $rows ) ) );
            }
        }
        return false;
    }

    
    private static function over_cap( ?string $over_cap, int $count ): string {
        return null !== $over_cap ? $over_cap : sprintf( self::OVER_ROW_CAP, self::ROW_CAP, $count );
    }

    






    private function fetch_page( Context $ctx, string $taxonomy, array $filters, int $per_page, int $page ): array {
        $arguments = array_merge(
            array( 'taxonomy' => $taxonomy ),
            $filters,
            array(
                'per_page'    => $per_page,
                'page'        => $page,
                'include_url' => true,
            )
        );
        $result    = $ctx->delegate( 'wp_list_terms', $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        return $result['payload'];
    }

    
    
    

    





    public function preflight_update( Invocation $inv, Context $ctx ): void {
        self::require_term_ids( $inv, $ctx, array_slice( array_values( $inv->args ), 1, 1 ) );
    }

    




    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        self::require_term_ids( $inv, $ctx, array_slice( array_values( $inv->args ), 1 ) );
    }

    




    public function preflight_migrate( Invocation $inv, Context $ctx ): void {
        self::require_term_ids( $inv, $ctx, array_slice( array_values( $inv->args ), 0, 1 ) );
    }

    





    private static function require_term_ids( Invocation $inv, Context $ctx, array $refs ): void {
        if ( 'id' === (string) Read_Value::flag( $inv->assoc, 'by', 'id' ) ) {
            Ids::require_all( $ctx, $refs, 'a term ID' );
        }
    }

    
    
    

    
    public function update( Invocation $inv, Context $ctx ): void {
        $args     = array_values( $inv->args );
        $taxonomy = (string) $args[0];

        $fields = $this->update_fields( $inv, $ctx );
        if ( ! $fields ) {
            $ctx->out->error( self::NO_FIELD );
        }

        $id = $this->resolve_term( $inv, $ctx, 'wp_update_term', $taxonomy, (string) $args[1], 'edit_terms', 'edit_term' );

        $result = $ctx->delegate(
            'wp_update_term',
            array_merge(
                array(
                    'term_id'  => $id,
                    'taxonomy' => $taxonomy,
                ),
                $fields
            )
        );
        if ( ! $result['ok'] ) {
            $ctx->out->error( self::term_error( (string) $result['error'] ) );
        }
        
        
        if ( ! array_key_exists( 'changed', $result['payload'] ) || ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
        $ctx->out->success( 'Term updated.' );
    }

    






    private function update_fields( Invocation $inv, Context $ctx ): array {
        $fields = array();
        foreach ( array( 'name', 'slug', 'description', 'parent' ) as $key ) {
            if ( ! array_key_exists( $key, $inv->assoc ) ) {
                continue;
            }
            if ( 'parent' === $key ) {
                $fields['parent'] = $this->id_arg( $inv, $ctx, 'parent' );
                continue;
            }
            $value = $this->text_arg( $inv, $ctx, $key );
            if ( 'slug' === $key && '' === $value ) {
                $ctx->out->error( self::EMPTY_SLUG );
            }
            if ( 'description' === $key && '' === $value ) {
                $fields['clear_description'] = true;
                continue;
            }
            if ( 'name' === $key && '' === trim( (string) $value ) ) {
                $ctx->out->error( self::EMPTY_NAME );
            }
            $fields[ $key ] = $value;
        }
        return $fields;
    }

    
    
    

    









    public function plan_delete( Invocation $inv ): array {
        $args     = array_values( $inv->args );
        $taxonomy = (string) array_shift( $args );
        if ( 'id' !== (string) Read_Value::flag( $inv->assoc, 'by', 'id' ) || ! function_exists( 'taxonomy_exists' ) || ! \taxonomy_exists( $taxonomy ) ) {
            return array();
        }
        $plan = array();
        foreach ( $args as $ref ) {
            if ( ! Ids::is_digits( $ref ) ) {
                return array(); 
            }
            $id = (int) $ref;
            if ( $id > 0 && ! self::is_default_term( $id, $taxonomy ) ) {
                $plan[] = array(
                    'tool'      => 'wp_delete_term',
                    'arguments' => self::delete_arguments( $id, $taxonomy ),
                );
            }
        }
        return $plan;
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        $args     = array_values( $inv->args );
        $taxonomy = (string) array_shift( $args );
        $by       = (string) Read_Value::flag( $inv->assoc, 'by', 'id' );

        Fetchers::require_tool( $ctx, $inv, 'wp_delete_term' );
        $known = \taxonomy_exists( $taxonomy );
        if ( 'slug' === $by && $known ) {
            $tax = \get_taxonomy( $taxonomy );
            $ctx->require_cap( (string) $tax->cap->delete_terms );
        }

        $batch = new Batch( $inv, $ctx, 'term', 'delete' );
        $batch->run(
            $args,
            static function ( string $ref, Batch $batch ) use ( $ctx, $taxonomy, $by, $known ): string {
                return self::delete_one( $ctx, $batch, $taxonomy, $by, $known, $ref );
            },
            static function ( string $ref ) use ( $taxonomy ): string {
                return "{$taxonomy} term {$ref}";
            }
        );
        $batch->report();
    }

    






    private static function delete_one( Context $ctx, Batch $batch, string $taxonomy, string $by, bool $known, string $ref ): string {
        list( $id, $label ) = self::delete_target( $taxonomy, $by, $known, $ref );
        if ( $id <= 0 ) {
            return self::delete_missing( $ctx, $taxonomy, $label );
        }
        if ( self::is_default_term( $id, $taxonomy ) ) {
            
            
            $ctx->out->warning( sprintf( self::DELETE_DEFAULT, $taxonomy, $id, $taxonomy ) );
            return Batch::FAILURE;
        }
        $result = $batch->delegate( 'wp_delete_term', self::delete_arguments( $id, $taxonomy ) );
        if ( $result['ok'] ) {
            if ( ! empty( $result['payload']['deleted'] ) ) {
                $ctx->mark_changed();
            }
            $ctx->out->log( "Deleted {$taxonomy} {$label}." );
            return Batch::SUCCESS;
        }
        if ( Batch::is_rate_limited( $result ) ) {
            return Batch::FAILURE; 
        }
        if ( null !== self::missing( (string) $result['error'] ) ) {
            return self::delete_missing( $ctx, $taxonomy, $label );
        }
        $ctx->out->warning( (string) $result['error'] );
        return Batch::FAILURE;
    }

    







    private static function delete_target( string $taxonomy, string $by, bool $known, string $ref ): array {
        if ( 'slug' !== $by ) {
            return array( $known ? (int) $ref : 0, $ref );
        }
        $term = $known ? Fetchers::term_by( 'slug', $ref, $taxonomy ) : false;
        if ( ! $term ) {
            return array( 0, $ref );
        }
        return array( (int) $term->term_id, (string) (int) $term->term_id );
    }

    
    private static function delete_arguments( int $id, string $taxonomy ): array {
        return array(
            'term_id'  => $id,
            'taxonomy' => $taxonomy,
        );
    }

    






    private static function is_default_term( int $id, string $taxonomy ): bool {
        if ( ! function_exists( 'get_option' ) ) {
            return false;
        }
        return (int) \get_option( 'default_' . $taxonomy ) === $id || (int) \get_option( 'default_term_' . $taxonomy ) === $id;
    }

    
    private static function delete_missing( Context $ctx, string $taxonomy, string $label ): string {
        $message = "{$taxonomy} {$label} doesn't exist.";
        $ctx->out->warning( $message );
        $ctx->fail_missing_target( $message );
        return Batch::SKIP;
    }

    
    
    

    
    public function migrate( Invocation $inv, Context $ctx ): void {
        $ref  = (string) array_values( $inv->args )[0];
        $by   = (string) Read_Value::flag( $inv->assoc, 'by', 'id' );
        $from = self::taxonomy_flag( $inv, 'from' );
        $to   = self::taxonomy_flag( $inv, 'to' );

        foreach ( array( 'wp_create_term', 'wp_add_post_terms', 'wp_delete_term' ) as $tool ) {
            Fetchers::require_tool( $ctx, $inv, $tool );
        }

        list( $from_tax, $term ) = self::migrate_source( $ctx, $by, $ref, $from );
        $term_id                 = (int) $term->term_id;
        $to_tax                  = self::migrate_destination( $ctx, $from, $to, $from_tax, $term_id );
        $post_ids                = self::objects_in_term( $ctx, $term_id, $from_tax->name );
        $targets                 = self::migrate_targets( $ctx, $inv, $post_ids, $from_tax, $to, $to_tax );

        $create = array(
            'name'        => (string) $term->name,
            'taxonomy'    => $to,
            'slug'        => (string) $term->slug,
            'description' => (string) $term->description,
        );
        $delete = self::delete_arguments( $term_id, $from_tax->name );
        self::refuse_pausing_steps( $ctx, $create, $targets, $to, $delete );

        $created = $ctx->delegate( 'wp_create_term', $create );
        if ( ! $created['ok'] ) {
            $ctx->out->error( (string) $created['error'] );
        }
        $ctx->mark_changed();
        $new_id = (int) ( $created['payload']['id'] ?? 0 );
        $slug   = (string) $term->slug;

        $batch = new Batch( $inv, $ctx, 'post', 'assign' );
        $batch->run(
            $targets,
            static function ( int $post_id, Batch $batch ) use ( $ctx, $to, $new_id, $slug ): string {
                $result = $batch->delegate( 'wp_add_post_terms', self::assign_arguments( $post_id, $to, $new_id ) );
                if ( Batch::is_rate_limited( $result ) ) {
                    return Batch::FAILURE; 
                }
                if ( ! $result['ok'] ) {
                    $ctx->out->error( "Failed to assign the term '{$slug}' to the post {$post_id}. Reason: " . $result['error'] );
                }
                $ctx->out->log( "Term '{$slug}' assigned to post {$post_id}." );
                return Batch::SUCCESS;
            }
        );

        $ctx->out->log( "Term '{$slug}' migrated." );

        $deleted = $ctx->delegate( 'wp_delete_term', $delete );
        if ( ! $deleted['ok'] ) {
            $ctx->out->error( "Failed to delete the term '{$slug}'. Reason: " . $deleted['error'] );
        }

        $ctx->out->log( "Old instance of term '{$slug}' removed from its original taxonomy." );
        $post_count = count( $post_ids );
        $ctx->out->success( "Migrated the term '{$slug}' from taxonomy '{$from_tax->name}' to taxonomy '{$to}' for {$post_count} " . ( 1 === $post_count ? 'post' : 'posts' ) . '.' );
    }

    







    private static function migrate_source( Context $ctx, string $by, string $ref, string $from ): array {
        $missing  = "Taxonomy term '{$ref}' for taxonomy '{$from}' doesn't exist.";
        $from_tax = '' !== $from ? \get_taxonomy( $from ) : false;
        if ( ! $from_tax ) {
            $ctx->out->error( $missing );
        }
        if ( ! \current_user_can( $from_tax->cap->delete_terms ) ) {
            $ctx->out->error( sprintf( self::MIGRATE_CAP, $from, $from_tax->cap->delete_terms ) );
        }
        $term = Fetchers::term_by( $by, $ref, $from );
        if ( ! $term ) {
            $ctx->out->error( $missing );
        }
        return array( $from_tax, $term );
    }

    









    private static function migrate_destination( Context $ctx, string $from, string $to, object $from_tax, int $term_id ): object {
        if ( '' === $to || ! \taxonomy_exists( $to ) ) {
            $ctx->out->error( self::INVALID_TAXONOMY );
        }
        if ( ! \current_user_can( 'delete_term', $term_id ) ) {
            $ctx->out->error( sprintf( self::MIGRATE_DELETE_DENIED, $term_id ) );
        }
        if ( empty( $from_tax->show_in_rest ) ) {
            $ctx->out->error( sprintf( self::MIGRATE_NOT_REST, $from, 'out of' ) );
        }
        $to_tax = \get_taxonomy( $to );
        if ( empty( $to_tax->show_in_rest ) ) {
            $ctx->out->error( sprintf( self::MIGRATE_NOT_REST, $to, 'into' ) );
        }
        return $to_tax;
    }

    





    private static function objects_in_term( Context $ctx, int $term_id, string $taxonomy ): array {
        $post_ids = \get_objects_in_term( $term_id, $taxonomy );
        if ( function_exists( 'is_wp_error' ) && \is_wp_error( $post_ids ) ) {
            $ctx->out->error( $post_ids->get_error_message() );
        }
        return is_array( $post_ids ) ? array_values( $post_ids ) : array();
    }

    











    private static function migrate_targets( Context $ctx, Invocation $inv, array $post_ids, object $from_tax, string $to, object $to_tax ): array {
        $targets = array();
        foreach ( $post_ids as $post_id ) {
            if ( in_array( \get_post_type( $post_id ), (array) $from_tax->object_type, true ) ) {
                $targets[] = $post_id;
            }
        }
        $cap = Batch::cap( $inv );
        if ( count( $targets ) > $cap ) {
            $ctx->out->error( sprintf( Batch::OVER_CAP, $cap, $inv->command, count( $targets ) ) );
        }
        if ( $targets && ! \current_user_can( $to_tax->cap->assign_terms ) ) {
            $ctx->out->error( sprintf( self::MIGRATE_ASSIGN_CAP, $to, $to_tax->cap->assign_terms ) );
        }
        foreach ( $targets as $post_id ) {
            if ( ! \current_user_can( 'edit_post', (int) $post_id ) ) {
                $ctx->out->error( sprintf( self::MIGRATE_EDIT_POST, $post_id ) );
            }
            
            
            
            $type = (string) \get_post_type( $post_id );
            
            
            $post   = 'attachment' === $type ? \get_post( (int) $post_id ) : null;
            $covers = $post
                ? in_array( $to, (array) \get_object_taxonomies( $post, 'names' ), true )
                : in_array( $type, (array) $to_tax->object_type, true );
            if ( ! $covers ) {
                $ctx->out->error( sprintf( self::MIGRATE_OTHER_TYPE, $post_id, $type, $to ) );
            }
        }
        return $targets;
    }

    








    private static function refuse_pausing_steps( Context $ctx, array $create, array $targets, string $to, array $delete ): void {
        $steps = array( array( 'wp_create_term', $create ) );
        foreach ( $targets as $post_id ) {
            $steps[] = array( 'wp_add_post_terms', self::assign_arguments( (int) $post_id, $to, 0 ) );
        }
        $steps[] = array( 'wp_delete_term', $delete );
        foreach ( $steps as $step ) {
            if ( self::would_pause( $step[0], $step[1] ) ) {
                $ctx->out->error( sprintf( self::MIGRATE_PAUSES, $step[0] ) );
            }
        }
    }

    
    private static function assign_arguments( int $post_id, string $taxonomy, int $term_id ): array {
        return array(
            'post_id'  => $post_id,
            'taxonomy' => $taxonomy,
            'terms'    => $term_id > 0 ? array( $term_id ) : array(),
            'append'   => true,
            'by'       => 'id',
        );
    }

    








    private static function would_pause( string $tool, array $arguments ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\Approvals\\Approval_Gate' ) && class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) && method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'load_approval_classes' ) ) {
            \Easy_MCP_AI\MCP\Server::load_approval_classes();
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\Approvals\\Approval_Gate' ) || ! \Easy_MCP_AI\Approvals\Approval_Gate::enabled() ) {
            return false;
        }
        $object = self::registered_tool( $tool );
        return null === $object || \Easy_MCP_AI\Approvals\Approval_Gate::requires_approval( $object, $arguments );
    }

    
    private static function registered_tool( string $tool ): ?object {
        if ( ! class_exists( '\\Easy_MCP_AI\\Plugin', false ) || ! method_exists( '\\Easy_MCP_AI\\Plugin', 'instance' ) ) {
            return null;
        }
        $registry = \Easy_MCP_AI\Plugin::instance()->get_tool_registry();
        $object   = is_object( $registry ) && method_exists( $registry, 'get_tool' ) ? $registry->get_tool( $tool ) : null;
        return is_object( $object ) ? $object : null;
    }

    
    
    

    










    private function resolve_term( Invocation $inv, Context $ctx, string $tool, string $taxonomy, string $ref, string $tax_cap, ?string $object_cap ): int {
        if ( 'slug' !== (string) Read_Value::flag( $inv->assoc, 'by', 'id' ) ) {
            $id = (int) $ref;
            if ( $id <= 0 ) {
                $ctx->out->error( self::TERM_MISSING );
            }
            return $id;
        }
        Fetchers::require_tool( $ctx, $inv, $tool );
        $tax = \get_taxonomy( $taxonomy );
        if ( ! $tax ) {
            $ctx->out->error( self::TERM_MISSING );
        }
        if ( ! \current_user_can( $tax->cap->{$tax_cap} ) ) {
            $ctx->out->error( sprintf( 'Insufficient capability for taxonomy %s.', $taxonomy ) );
        }
        $term = Fetchers::term_by( 'slug', $ref, $taxonomy );
        if ( ! $term ) {
            $ctx->out->error( self::TERM_MISSING );
        }
        if ( null !== $object_cap ) {
            $ctx->require_cap( $object_cap, (int) $term->term_id );
        }
        return (int) $term->term_id;
    }

    



    private static function taxonomy_flag( Invocation $inv, string $key ): string {
        $value = Read_Value::flag( $inv->assoc, $key );
        if ( is_string( $value ) ) {
            return $value;
        }
        return true === $value ? '1' : '';
    }

    



    private function text_arg( Invocation $inv, Context $ctx, string $key ): ?string {
        if ( ! array_key_exists( $key, $inv->assoc ) ) {
            return null;
        }
        $value = $inv->assoc[ $key ];
        if ( ! is_string( $value ) ) {
            $ctx->out->error( sprintf( self::NEEDS_VALUE, $key, $key ) );
        }
        return (string) $value;
    }

    
    private function id_arg( Invocation $inv, Context $ctx, string $key ): ?int {
        $value = $this->text_arg( $inv, $ctx, $key );
        if ( null === $value ) {
            return null;
        }
        if ( ! ctype_digit( $value ) ) {
            $ctx->out->error( sprintf( self::NOT_AN_ID, $key, $value, "'" . $value . "'", (int) $value ) );
        }
        return (int) $value;
    }

    
    private static function term_error( string $error ): string {
        $missing = self::missing( $error );
        return null !== $missing ? $missing : $error;
    }

    
    private static function missing( string $error ): ?string {
        if ( in_array( $error, self::MISSING_ERRORS, true ) || 0 === strpos( $error, 'Unknown taxonomy: ' ) ) {
            return self::TERM_MISSING;
        }
        return null;
    }

    






    private static function term_object( array $payload, string $taxonomy ): \stdClass {
        return (object) array(
            'term_id'          => (int) ( $payload['id'] ?? 0 ),
            'name'             => (string) ( $payload['name'] ?? '' ),
            'slug'             => (string) ( $payload['slug'] ?? '' ),
            'term_group'       => (int) ( $payload['term_group'] ?? 0 ),
            'term_taxonomy_id' => (int) ( $payload['term_taxonomy_id'] ?? 0 ),
            'taxonomy'         => isset( $payload['taxonomy'] ) ? (string) $payload['taxonomy'] : $taxonomy,
            'description'      => (string) ( $payload['description'] ?? '' ),
            'parent'           => (int) ( $payload['parent'] ?? 0 ),
            'count'            => (int) ( $payload['count'] ?? 0 ),
            'filter'           => isset( $payload['filter'] ) ? (string) $payload['filter'] : 'raw',
            'url'              => $payload['url'] ?? null,
        );
    }

    






    private static function term_objects( array $rows, string $taxonomy ): array {
        $terms = array();
        foreach ( $rows as $row ) {
            $terms[] = self::term_object(
                array_merge(
                    $row,
                    array(
                        'taxonomy' => $taxonomy,
                        'filter'   => 'raw',
                    )
                ),
                $taxonomy
            );
        }
        return $terms;
    }
}
