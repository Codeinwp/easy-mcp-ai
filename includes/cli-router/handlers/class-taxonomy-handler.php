<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















final class Taxonomy_Handler extends Command_Handler {

    
    const FIELDS = array( 'name', 'label', 'description', 'object_type', 'show_tagcloud', 'hierarchical', 'public' );

    const MSG_FILTER_NEEDS_STRICT = "Parameter errors:\n%s";

    const MSG_FILTER = " --%s parameter is not available without --strict: WP-CLI ignores every filter but --object_type unless --strict is given, so it would list taxonomies that do not match. Add --strict to filter by it.";

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $formatter  = $this->taxonomy_formatter( $ctx, $assoc_args );

        
        $strict = Read_Value::flag( $assoc_args, 'strict', false );

        unset( $assoc_args['strict'] );

        if ( ! $strict ) {
            $ignored = array();
            foreach ( array_keys( $assoc_args ) as $key ) {
                if ( 'object_type' !== $key ) {
                    $ignored[] = sprintf( self::MSG_FILTER, $key );
                }
            }
            if ( $ignored ) {
                $ctx->out->error( sprintf( self::MSG_FILTER_NEEDS_STRICT, implode( "\n", $ignored ) ) );
            }
        }

        if ( isset( $assoc_args['object_type'] ) ) {
            $assoc_args['object_type'] = array( $assoc_args['object_type'] );
            $taxonomy_object           = $assoc_args['object_type'];
        } else {
            $taxonomy_object = \get_post_types();
        }

        $fields     = $formatter->fields;
        $taxonomies = ( isset( $taxonomy_object ) && ! $strict )
            ? \get_object_taxonomies( $taxonomy_object, 'objects' )
            : \get_taxonomies( $assoc_args, 'objects' );
        $taxonomies = is_array( $taxonomies ) ? $taxonomies : array();

        $counts = array();

        if ( count( $taxonomies ) > 0 && in_array( 'count', (array) $fields, true ) ) {
            $names = array();
            foreach ( $taxonomies as $taxonomy ) {
                $names[] = (string) $taxonomy->name;
            }
            $counts = self::get_counts( $names );
        }

        $listed = array();
        foreach ( $taxonomies as $key => $taxonomy ) {
            $copy              = clone $taxonomy;
            $copy->object_type = implode( ', ', (array) $copy->object_type );
            $copy->count       = isset( $counts[ $copy->name ] ) ? $counts[ $copy->name ] : 0;
            $listed[ $key ]    = $copy;
        }

        $formatter->display_items( $listed );
    }

    
    public function get( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $arg        = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        $taxonomy   = \get_taxonomy( $arg );

        if ( ! $taxonomy ) {
            $ctx->out->error( "Taxonomy {$arg} doesn't exist." );
        }

        if ( empty( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = array_merge( self::FIELDS, array( 'labels', 'cap' ) );
        }

        $formatter = $this->taxonomy_formatter( $ctx, $assoc_args );
        $fields    = $formatter->fields;
        $count     = 0;

        if ( in_array( 'count', (array) $fields, true ) ) {
            $count = self::get_counts( array( $taxonomy->name ) );
            $count = $count[ $taxonomy->name ];
        }

        $data = array(
            'name'          => $taxonomy->name,
            'label'         => $taxonomy->label,
            'description'   => $taxonomy->description,
            'object_type'   => $taxonomy->object_type,
            'show_tagcloud' => $taxonomy->show_tagcloud,
            'hierarchical'  => $taxonomy->hierarchical,
            'public'        => $taxonomy->public,
            'labels'        => $taxonomy->labels,
            'cap'           => $taxonomy->cap,
            'count'         => $count,
        );
        $formatter->display_item( $data );
    }

    






    public static function get_counts( array $taxonomies ): array {
        global $wpdb;

        if ( count( $taxonomies ) <= 0 ) {
            return array();
        }

        $query = $wpdb->prepare(
            "SELECT `taxonomy`, COUNT(*) AS `count`
			FROM $wpdb->term_taxonomy
			WHERE `taxonomy` IN (" . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ')
			GROUP BY `taxonomy`',
            $taxonomies
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared above; WP-CLI's own query.
        $counts = $wpdb->get_results( $query );

        
        $plucked = array();
        foreach ( is_array( $counts ) ? $counts : array() as $row ) {
            $plucked[ $row->taxonomy ] = $row->count;
        }
        return array_merge( array_fill_keys( $taxonomies, 0 ), $plucked );
    }

    
    private function taxonomy_formatter( Context $ctx, array &$assoc_args ): Formatter {
        return new Formatter( $ctx->out, $assoc_args, self::FIELDS, 'taxonomy' );
    }
}
