<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Term_Handler extends Command_Handler {

    const NONE_EXISTS = 'None of the taxonomies named exists, so no term count was recounted.';

    public function preflight_recount( Invocation $inv, Context $ctx ): void {
        foreach ( $inv->args as $taxonomy ) {
            $tax = \taxonomy_exists( (string) $taxonomy ) ? \get_taxonomy( (string) $taxonomy ) : null;
            if ( $tax ) {
                $ctx->require_cap( (string) $tax->cap->edit_terms );
            }
        }
    }

    public function recount( Invocation $inv, Context $ctx ): void {
        foreach ( (array) ( $inv->route['not_captured'] ?? array() ) as $phrase ) {
            $ctx->not_captured( (string) $phrase );
        }
        $recounted = false;
        foreach ( $inv->args as $taxonomy ) {
            $taxonomy = (string) $taxonomy;
            if ( ! \taxonomy_exists( $taxonomy ) ) {
                $ctx->out->warning( "Taxonomy {$taxonomy} does not exist." );
                continue;
            }
            $ctx->require_cap( (string) \get_taxonomy( $taxonomy )->cap->edit_terms );

            $before            = self::stored_counts( $taxonomy );
            $term_taxonomy_ids = array_keys( $before );

            \wp_update_term_count( $term_taxonomy_ids, $taxonomy );
            $recounted = true;

            if ( self::stored_counts( $taxonomy ) !== $before ) {
                $ctx->mark_changed();
            }
            $ctx->out->success( "Updated {$taxonomy} term count." );
        }
        if ( ! $recounted ) {
            $ctx->fail_missing_target( self::NONE_EXISTS );
        }
    }

    public function describe_recount( Invocation $inv ): array {
        $lines = array();
        foreach ( $inv->args as $taxonomy ) {
            $taxonomy = (string) $taxonomy;
            if ( ! \taxonomy_exists( $taxonomy ) ) {
                $lines[] = "Taxonomy '{$taxonomy}': does not exist → skipped with a Warning";
                continue;
            }
            $counts  = self::stored_counts( $taxonomy );
            $lines[] = sprintf(
                "Taxonomy '%s': %d %s, stored counts totalling %d → recount every term's count",
                $taxonomy,
                count( $counts ),
                1 === count( $counts ) ? 'term' : 'terms',
                array_sum( $counts )
            );
        }
        return $lines;
    }

    private static function stored_counts( string $taxonomy ): array {
        $terms = \get_terms(
            array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            )
        );
        $counts = array();
        if ( ! is_array( $terms ) ) {
            return $counts;
        }
        foreach ( $terms as $term ) {
            if ( is_object( $term ) && isset( $term->term_taxonomy_id ) ) {
                $counts[ (int) $term->term_taxonomy_id ] = (int) $term->count;
            }
        }
        return $counts;
    }
}
