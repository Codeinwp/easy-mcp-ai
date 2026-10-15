<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




















final class Post_Type_Handler extends Command_Handler {

    
    const FIELDS = array( 'name', 'label', 'description', 'hierarchical', 'public', 'capability_type' );

    const MSG_COUNT_CAP = "Sorry, you are not allowed to do this: the count field counts every post of type '%s', and wp_cli_run shows it only to users with that type's %s capability.";

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $formatter  = $this->post_type_formatter( $ctx, $assoc_args );

        $fields = $formatter->fields;
        $types  = \get_post_types( $assoc_args, 'objects' );
        $types  = is_array( $types ) ? $types : array();
        $counts = array();

        
        
        if ( count( $types ) > 0 && in_array( 'count', (array) $fields, true ) ) {
            foreach ( $types as $type ) {
                $this->require_count_cap( $ctx, $type );
            }
            $counts = self::get_counts( self::pluck_names( $types ) );
        }

        $listed = array();
        foreach ( $types as $key => $type ) {
            $copy           = clone $type;
            $copy->count    = isset( $counts[ $copy->name ] ) ? $counts[ $copy->name ] : 0;
            $listed[ $key ] = $copy;
        }

        $formatter->display_items( $listed );
    }

    
    public function get( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $arg        = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        $post_type  = \get_post_type_object( $arg );

        if ( ! $post_type ) {
            $ctx->out->error( "Post type {$arg} doesn't exist." );
        }

        if ( empty( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = array_merge( self::FIELDS, array( 'labels', 'cap', 'supports' ) );
        }

        $formatter = $this->post_type_formatter( $ctx, $assoc_args );
        $fields    = $formatter->fields;
        $count     = 0;

        if ( in_array( 'count', (array) $fields, true ) ) {
            $this->require_count_cap( $ctx, $post_type );
            $count = self::get_counts( array( $post_type->name ) );
            $count = $count[ $post_type->name ];
        }

        $data = array(
            'name'            => $post_type->name,
            'label'           => $post_type->label,
            'description'     => $post_type->description,
            'hierarchical'    => $post_type->hierarchical,
            'public'          => $post_type->public,
            'capability_type' => $post_type->capability_type,
            'labels'          => $post_type->labels,
            'cap'             => $post_type->cap,
            'supports'        => \get_all_post_type_supports( $post_type->name ),
            'count'           => $count,
        );
        $formatter->display_item( $data );
    }

    







    public static function get_counts( array $post_types ): array {
        global $wpdb;

        if ( count( $post_types ) <= 0 ) {
            return array();
        }

        $query = $wpdb->prepare(
            "SELECT `post_type`, COUNT(*) AS `count`
			FROM $wpdb->posts
			WHERE `post_type` IN (" . implode( ',', array_fill( 0, count( $post_types ), '%s' ) ) . ')
			GROUP BY `post_type`',
            $post_types
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared above; WP-CLI's own query.
        $counts = $wpdb->get_results( $query );

        
        $plucked = array();
        foreach ( is_array( $counts ) ? $counts : array() as $row ) {
            $plucked[ $row->post_type ] = $row->count;
        }
        return array_merge( array_fill_keys( $post_types, 0 ), $plucked );
    }

    




    private function post_type_formatter( Context $ctx, array &$assoc_args ): Formatter {
        return new Formatter( $ctx->out, $assoc_args, self::FIELDS, 'post-type' );
    }

    






    private function require_count_cap( Context $ctx, object $type ): void {
        $cap = isset( $type->cap->edit_posts ) ? (string) $type->cap->edit_posts : 'edit_posts';
        if ( ! \current_user_can( $cap ) ) {
            $ctx->out->error( sprintf( self::MSG_COUNT_CAP, (string) $type->name, $cap ) );
        }
    }

    





    private static function pluck_names( array $types ): array {
        $names = array();
        foreach ( $types as $type ) {
            $names[] = (string) $type->name;
        }
        return $names;
    }
}
