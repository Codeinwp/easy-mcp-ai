<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Comment_Handler extends Command_Handler {

    const NOT_CAPTURED_RECOUNT = 'posts.comment_count is written by a direct database update, which change history records only while its database capture is on';

    const BAD_ID = "wp_cli_run refuses '%s' as a post ID: it is not a whole number in digits, and WP-CLI would convert it to a different ID rather than refuse it. Pass the ID as digits only.";

    const NOT_EDITABLE = 'Sorry, you are not allowed to recount the comments of post %s: recounting updates the post, which needs permission to edit it. Nothing was recounted.';

    const MISSING = "Post %s doesn't exist.";

    public function preflight_count( Invocation $inv, Context $ctx ): void {
        if ( isset( $inv->args[0] ) ) {
            self::require_digits( $ctx, (string) $inv->args[0] );
        }
    }

    public function preflight_recount( Invocation $inv, Context $ctx ): void {
        foreach ( $inv->args as $id ) {
            self::require_digits( $ctx, (string) $id );
        }
        foreach ( $inv->args as $id ) {
            if ( null !== $this->post( (int) $id ) && ! \current_user_can( 'edit_post', (int) $id ) ) {
                $ctx->out->error( sprintf( self::NOT_EDITABLE, (string) $id ) );
            }
        }
    }

    public function count( Invocation $inv, Context $ctx ): void {
        $post_id = isset( $inv->args[0] ) ? $inv->args[0] : 0;

        $count = $this->count_comments( $post_id );

        $total = $count->total_comments;
        unset( $count->total_comments );
        $count->total_comments = $total;

        foreach ( $count as $status => $number ) {
            $ctx->out->line( str_pad( "$status:", 17 ) . $number );
        }
    }

    public function recount( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED_RECOUNT );
        foreach ( $inv->args as $id ) {
            $before = $this->post( (int) $id );
            if ( null !== $before && ! \current_user_can( 'edit_post', (int) $id ) ) {
                $ctx->out->error( sprintf( self::NOT_EDITABLE, (string) $id ) );
            }
            $old = null !== $before ? (string) $before->comment_count : null;
            if ( null !== $before && $this->update_count( $id ) ) {
                $post = $this->post( (int) $id );
                if ( null === $post ) {
                    $ctx->out->warning( sprintf( self::MISSING, $id ) );
                    $ctx->fail_missing_target( sprintf( self::MISSING, $id ) );
                    continue;
                }
                if ( (string) $post->comment_count !== $old ) {
                    $ctx->mark_changed();
                }
                $ctx->out->log( "Updated post {$post->ID} comment count to {$post->comment_count}." );
            } else {
                $ctx->out->warning( sprintf( self::MISSING, $id ) );
                $ctx->fail_missing_target( sprintf( self::MISSING, $id ) );
            }
        }
    }

    public function describe_recount( Invocation $inv ): array {
        $lines = array();
        foreach ( $inv->args as $id ) {
            $post = $this->post( (int) $id );
            if ( null === $post ) {
                $lines[] = sprintf( 'Post %s: does not exist → nothing to recount', (string) $id );
                continue;
            }
            $lines[] = sprintf( 'Post %d comment_count: %s → %d', (int) $post->ID, (string) $post->comment_count, $this->approved_count( (int) $post->ID, (int) $post->comment_count ) );
        }
        return $lines;
    }

    protected function count_comments( $post_id ): object {
        return \wp_count_comments( $post_id );
    }

    protected function update_count( string $post_id ): bool {
        return (bool) \wp_update_comment_count( $post_id );
    }

    protected function post( int $post_id ): ?object {
        if ( $post_id < 1 ) {
            return null;
        }
        $post = \get_post( $post_id );
        return $post ? $post : null;
    }

    protected function approved_count( int $post_id, int $old ): int {
        global $wpdb, $wp_version;
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, read as wp_update_comment_count_now() reads it.
        $new = function_exists( 'apply_filters' ) ? \apply_filters( 'pre_wp_update_comment_count_now', null, $old, $post_id ) : null;
        if ( null !== $new ) {
            return (int) $new;
        }
        if ( ! is_object( $wpdb ) ) {
            return 0;
        }
        $notes = is_string( $wp_version ) && version_compare( $wp_version, '6.9-alpha', '>=' ) ? " AND comment_type != 'note'" : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the count core's wp_update_comment_count_now() runs, read for the approval preview; $notes is one of two fixed strings.
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1'{$notes}", $post_id ) );
    }

    private static function require_digits( Context $ctx, string $value ): void {
        if ( ! preg_match( '/^[0-9]+\z/', $value ) ) {
            $ctx->out->error( sprintf( self::BAD_ID, $value ) );
        }
    }
}
