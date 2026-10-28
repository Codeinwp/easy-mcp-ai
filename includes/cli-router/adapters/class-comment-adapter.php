<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Db_Object_Command;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Comment_Adapter extends Db_Object_Command {

    protected $obj_type   = 'comment';
    protected $obj_id_key = 'comment_ID';
    protected $obj_fields = array( 'comment_ID', 'comment_post_ID', 'comment_date', 'comment_approved', 'comment_author', 'comment_author_email' );

    const MODERATION_TOOLS = array(
        'approve'   => 'wp_approve_comment',
        'unapprove' => 'wp_unapprove_comment',
        'spam'      => 'wp_spam_comment',
        'unspam'    => 'wp_unspam_comment',
        'trash'     => 'wp_trash_comment',
        'untrash'   => 'wp_untrash_comment',
    );

    const CALL_TEXT = array(
        'spam'    => array( 'Marked %s as spam.', 'Failed marking %s as spam.' ),
        'unspam'  => array( 'Unspammed %s.', 'Failed unspamming %s.' ),
        'trash'   => array( 'Trashed %s.', 'Failed trashing %s.' ),
        'untrash' => array( 'Untrashed %s.', 'Failed untrashing %s.' ),
    );

    const SET_STATUS_TEXT = array(
        'approve'   => 'Approved',
        'unapprove' => 'Unapproved',
    );

    const STATUS_UNCHANGED = 'Could not update comment status.';

    const UPDATE_FAILED = 'Could not update comment.';

    const NOT_IN_STATE = 'Comment %d is not %s (its status is %s), so nothing changed. WP-CLI would restore a saved status here, which can unapprove or untrash it.';

    const ALREADY = 'Comment %d is already %s, so nothing changed: WordPress updated no row, which WP-CLI reports as this error.';

    const UPDATE_SAME = 'Comment %d already holds these values, so nothing changed: wp_update_comment() updated no row, which WP-CLI reports as a failure.';

    const MISSING = 'Could not find the comment with ID %d.';

    const LIST_CAP = 1000;

    const PAGE = 100;

    const LIST_OVER_CAP = "wp_cli_run returns at most %d comments in one 'wp comment list', and this query matches %d. Narrow it, or page through it with --number and --offset (or --paged).";

    const BAD_ID = "wp_cli_run refuses '%s' as %s: it is not a whole number in digits, and WP-CLI would convert it to a different ID rather than refuse it. Pass the ID as digits only.";

    const NEEDS_VALUE = 'wp_cli_run needs a value for --%s.';

    const FIELD_MAP = array(
        'comment_post_ID'      => 'post',
        'comment_content'      => 'content',
        'comment_author'       => 'author_name',
        'comment_author_email' => 'author_email',
        'comment_author_url'   => 'author_url',
        'comment_author_IP'    => 'author_ip',
        'comment_agent'        => 'author_user_agent',
        'comment_date'         => 'date',
        'comment_date_gmt'     => 'date_gmt',
        'comment_parent'       => 'parent',
        'user_id'              => 'author',
        'comment_approved'     => 'status',
    );

    const FIELD_REFUSED = array(
        'comment_type'  => 'the comment tools cannot set a comment type: WordPress\'s REST API takes only comment and note',
        'comment_karma' => 'WordPress\'s REST API has no karma field, so the comment tools cannot set it',
        'comment_meta'  => 'WP-CLI passes it as a string, which WordPress ignores; use `comment meta add` or `comment meta update`',
    );

    const STATUS_MAP = array(
        '1'       => 'approve',
        'approve' => 'approve',
        '0'       => 'hold',
        'hold'    => 'hold',
        'spam'    => 'spam',
        'trash'   => 'trash',
    );

    const LIST_VARS = array(
        'post_id'        => 'post_id',
        'post__in'       => 'post_ids',
        'post__not_in'   => 'post_exclude',
        'status'         => 'status',
        'type'           => 'type',
        'search'         => 'search',
        'user_id'        => 'author',
        'author__in'     => 'author',
        'author__not_in' => 'author_exclude',
        'author_email'   => 'author_email',
        'author_url'     => 'author_url',
        'comment__in'    => 'include',
        'comment__not_in' => 'exclude',
        'parent'         => 'parent',
        'parent__in'     => 'parent',
        'parent__not_in' => 'parent_exclude',
        'post_author'    => 'post_author',
        'post_name'      => 'post_name',
        'post_parent'    => 'post_parent',
        'post_status'    => 'post_status',
        'post_type'      => 'post_type',
        'karma'          => 'karma',
        'meta_key'       => 'meta_key',
        'meta_value'     => 'meta_value',
        'meta_compare'   => 'meta_compare',
        'orderby'        => 'orderby',
        'order'          => 'order',
        'number'         => null,
        'offset'         => null,
        'paged'          => null,
    );

    const LIST_ID_VARS = array( 'post__in', 'post__not_in', 'author__in', 'author__not_in', 'comment__in', 'comment__not_in', 'parent__in', 'parent__not_in' );

    const ORDERBY_MAP = array(
        'comment_date'     => 'date',
        'comment_date_gmt' => 'date_gmt',
        'comment_ID'       => 'id',
        'comment__in'      => 'include',
        'comment_post_ID'  => 'post',
        'comment_parent'   => 'parent',
        'comment_type'     => 'type',
    );

    const LIST_REFUSED = array(
        'count'                     => 'it changes what the query returns; use --format=count',
        'fields'                    => 'it is the output field list here',
        'hierarchical'              => 'it changes the shape of the result',
        'no_found_rows'             => 'it is an internal switch of WP_Comment_Query',
        'update_comment_meta_cache' => 'it is an internal switch of WP_Comment_Query',
        'update_comment_post_cache' => 'it is an internal switch of WP_Comment_Query',
        'cache_domain'              => 'it is an internal switch of WP_Comment_Query',
        'date_query'                => 'it takes an array, which a command line cannot pass',
        'meta_query'                => 'it takes an array, which a command line cannot pass; use --meta_key with --meta_value and --meta_compare',
        'include_unapproved'        => 'wp_list_comments has no such filter',
        'type__in'                  => 'wp_list_comments filters by one --type',
        'type__not_in'              => 'wp_list_comments filters by one --type',
        'post_author__in'           => 'wp_list_comments filters by one --post_author',
        'post_author__not_in'       => 'wp_list_comments filters by one --post_author',
    );

    public function preflight_ids( Invocation $inv, Context $ctx ): void {
        foreach ( $inv->args as $arg ) {
            self::require_digits( $ctx, (string) $arg, 'a comment ID' );
        }
    }

    public function preflight_create( Invocation $inv, Context $ctx ): void {
        $fields = self::fields( $inv );
        foreach ( array( 'comment_post_ID' => 'the post to comment on', 'comment_content' => 'the comment text' ) as $key => $what ) {
            if ( ! isset( $fields[ $key ] ) || '' === $fields[ $key ] ) {
                $ctx->out->error( sprintf( 'wp_cli_run needs --%s (%s): comments are created through WordPress\'s REST API, which refuses a comment without it. WP-CLI would store it anyway.', $key, $what ) );
            }
        }
        self::map_fields( $ctx, $fields, true );
    }

    public function preflight_update( Invocation $inv, Context $ctx ): void {
        $fields = self::fields( $inv );
        if ( ! $fields ) {
            $ctx->out->error( 'Need some fields to update.' );
        }
        $this->preflight_ids( $inv, $ctx );
        self::map_fields( $ctx, $fields, false );
    }

    public function preflight_list( Invocation $inv, Context $ctx ): void {
        self::list_arguments( $ctx, self::query_vars( $inv ) );
    }

    public function plan_moderate( Invocation $inv ): array {
        $tool = self::MODERATION_TOOLS[ self::verb( $inv ) ];
        $plan = array();
        foreach ( $inv->args as $id ) {
            $plan[] = array(
                'tool'      => $tool,
                'arguments' => array( 'comment_id' => self::id_value( $id ) ),
            );
        }
        return $plan;
    }

    public function plan_delete( Invocation $inv ): array {
        $plan = array();
        foreach ( $inv->args as $id ) {
            $plan[] = array(
                'tool'      => 'wp_delete_comment',
                'arguments' => array(
                    'comment_id' => self::id_value( $id ),
                    'force'      => self::delete_force( $inv, $id ),
                ),
            );
        }
        return $plan;
    }

    public function plan_update( Invocation $inv ): array {
        $mapped = self::map_fields_quietly( self::fields( $inv ), false );
        $plan   = array();
        foreach ( $inv->args as $id ) {
            $plan[] = array(
                'tool'      => 'wp_update_comment',
                'arguments' => array_merge( array( 'comment_id' => self::id_value( $id ) ), $mapped ),
            );
        }
        return $plan;
    }

    public function moderate( Invocation $inv, Context $ctx ): void {
        $verb  = self::verb( $inv );
        $tool  = self::MODERATION_TOOLS[ $verb ];
        $batch = new Batch( $inv, $ctx, 'comment', $verb );
        $batch->run(
            array_values( $inv->args ),
            function ( string $id, Batch $batch ) use ( $verb, $tool, $ctx ): string {
                return $this->moderate_one( $ctx, $batch, $verb, $tool, (int) $id );
            }
        );
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_delete_comment' );
        $assoc = $inv->assoc;
        $this->_delete(
            $inv,
            $ctx,
            array_values( $inv->args ),
            $assoc,
            function ( string $comment_id, array $assoc_args, Batch $batch ) use ( $inv, $ctx ): array {
                $id      = (int) $comment_id;
                $comment = self::read_comment( $id );
                if ( null === $comment ) {
                    $ctx->fail_missing_target( sprintf( self::MISSING, $id ) );
                    return array( 'error', "Failed deleting comment {$id}." );
                }
                $flag   = (bool) Read_Value::flag( $assoc_args, 'force' );
                $status = self::wp_status( $comment );
                $result = $batch->delegate(
                    'wp_delete_comment',
                    array(
                        'comment_id' => $id,
                        'force'      => self::delete_force( $inv, $comment_id ),
                    )
                );
                if ( ! $result['ok'] ) {
                    if ( ! Batch::is_rate_limited( $result ) ) {
                        $ctx->note( sprintf( 'wp_delete_comment refused comment %d: %s', $id, (string) $result['error'] ) );
                    }
                    return array( 'error', "Failed deleting comment {$id}." );
                }
                $verb = ( $flag || 'trash' === $status ) ? 'Deleted' : 'Trashed';
                return array( 'success', "{$verb} comment {$id}." );
            }
        );
    }

    public function create( Invocation $inv, Context $ctx ): void {
        $fields = self::fields( $inv );
        $mapped = self::map_fields( $ctx, $fields, true );
        if ( ! isset( $mapped['status'] ) ) {
            $mapped['status'] = 'approve';
        }
        $assoc = $fields;
        if ( $inv->has( 'porcelain' ) ) {
            $assoc['porcelain'] = $inv->assoc['porcelain'];
        }
        $this->_create(
            $inv,
            $ctx,
            $inv->args,
            $assoc,
            static function () use ( $inv, $ctx, $mapped ) {
                Fetchers::require_tool( $ctx, $inv, 'wp_create_comment' );
                $post_id = (int) $mapped['post'];
                if ( ! \get_post( $post_id ) ) {
                    return self::failure( "Can't find post {$post_id}." );
                }
                $result = $ctx->delegate( 'wp_create_comment', $mapped );
                if ( ! $result['ok'] ) {
                    return self::failure( (string) $result['error'] );
                }
                return isset( $result['payload']['id'] ) ? (int) $result['payload']['id'] : self::failure( 'wp_create_comment returned no comment ID.' );
            }
        );
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $fields = self::fields( $inv );
        $mapped = $fields ? self::map_fields( $ctx, $fields, false ) : array();
        $this->_update(
            $inv,
            $ctx,
            array_values( $inv->args ),
            $fields,
            function ( array $params, Batch $batch ) use ( $ctx, $mapped ) {
                $id     = (int) $params['comment_ID'];
                $result = $batch->delegate( 'wp_update_comment', array_merge( array( 'comment_id' => $id ), $mapped ) );
                if ( ! $result['ok'] ) {
                    if ( Batch::is_rate_limited( $result ) ) {
                        return self::failure( self::UPDATE_FAILED );
                    }
                    if ( $this->comment_missing( $ctx, 'wp_update_comment', $id ) ) {
                        $ctx->fail_missing_target( sprintf( self::MISSING, $id ) );
                        return self::failure( self::UPDATE_FAILED );
                    }
                    return self::failure( (string) $result['error'] );
                }
                if ( empty( $result['payload']['changed'] ) ) {
                    $ctx->note( sprintf( self::UPDATE_SAME, $id ) );
                    return self::failure( self::UPDATE_FAILED );
                }
                return true;
            }
        );
    }

    public function get( Invocation $inv, Context $ctx ): void {
        $id     = (int) $inv->args[0];
        $result = $ctx->delegate( 'wp_get_comment', array( 'comment_id' => $id ) );
        if ( ! $result['ok'] ) {
            if ( $this->comment_missing( $ctx, 'wp_get_comment', $id ) ) {
                $ctx->out->error( 'Invalid comment ID.' );
            }
            $ctx->out->error( (string) $result['error'] );
        }
        $comment = $this->comment_instance( self::comment_object( $result['payload'], false ) );
        $assoc   = $inv->assoc;
        if ( empty( $assoc['fields'] ) ) {
            $assoc['fields'] = array_keys( get_object_vars( $comment ) );
        }
        $this->get_formatter( $ctx, $assoc )->display_item( $comment );
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc     = $inv->assoc;
        $formatter = $this->get_formatter( $ctx, $assoc );
        $format    = $formatter->format;
        $list      = self::list_arguments( $ctx, self::query_vars( $inv ) );

        if ( 'count' === $format ) {
            $page = $this->list_page( $ctx, $list['arguments'], $list['start'], 1, false );
            $ctx->out->out( (string) ( null !== $list['number'] && $list['start'] > 0 ? 0 : (int) $page['total'] ) );
            return;
        }

        $rows = $this->list_rows( $ctx, $list, 'ids' !== $format );
        $formatter->display_items( self::list_items( $rows, $format ) );
    }

    public function exists( Invocation $inv, Context $ctx ): void {
        $raw    = (string) $inv->args[0];
        $id     = (int) $raw;
        $result = $ctx->delegate( 'wp_get_comment', array( 'comment_id' => $id ) );
        if ( $result['ok'] || $this->orphan_after_refusal( $ctx, $id ) ) {
            $ctx->out->success( "Comment with ID {$raw} exists." );
            return;
        }
        if ( $this->comment_missing( $ctx, 'wp_get_comment', $id ) ) {
            $ctx->note( "Comment {$raw} does not exist: WP-CLI 2.12.0 prints nothing and exits 0 here." );
            return;
        }
        $ctx->out->error( (string) $result['error'] );
    }

    public function status( Invocation $inv, Context $ctx ): void {
        $raw    = (string) $inv->args[0];
        $id     = (int) $raw;
        $result = $ctx->delegate( 'wp_get_comment', array( 'comment_id' => $id ) );
        $status = false;
        if ( $result['ok'] ) {
            $status = self::status_word( isset( $result['payload']['comment_approved'] ) ? $result['payload']['comment_approved'] : null );
        } elseif ( $this->orphan_after_refusal( $ctx, $id ) ) {
            $status = self::wp_status( self::read_comment( $id ) );
        } elseif ( ! $this->comment_missing( $ctx, 'wp_get_comment', $id ) ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( false === $status ) {
            $ctx->out->error( "Could not check status of comment {$raw}." );
        }
        $ctx->out->line( (string) $status );
    }

    private function moderate_one( Context $ctx, Batch $batch, string $verb, string $tool, int $id ): string {
        $result = $batch->delegate( $tool, array( 'comment_id' => $id ) );
        $call   = isset( self::CALL_TEXT[ $verb ] ) ? self::CALL_TEXT[ $verb ] : null;
        if ( ! $result['ok'] ) {
            if ( Batch::is_rate_limited( $result ) ) {
                return Batch::FAILURE;
            }
            $this->moderation_refused( $ctx, $tool, $id, $call, (string) $result['error'] );
        }

        if ( empty( $result['payload']['changed'] ) ) {
            $state = self::moderation_state( $result['payload'] );
            if ( 'unspam' === $verb || 'untrash' === $verb ) {
                $ctx->out->warning( sprintf( self::NOT_IN_STATE, $id, 'unspam' === $verb ? 'in spam' : 'in the Trash', $state ) );
                return Batch::SKIP;
            }
            self::moderation_unchanged( $ctx, $id, $call, $state );
        }

        $ctx->mark_changed();
        $ctx->out->success( null === $call ? self::SET_STATUS_TEXT[ $verb ] . " comment {$id}." : sprintf( $call[0], "comment {$id}" ) );
        return Batch::SUCCESS;
    }

    private function moderation_refused( Context $ctx, string $tool, int $id, ?array $call, string $error ): void {
        if ( $this->comment_missing( $ctx, $tool, $id ) ) {
            $ctx->fail_missing_target( sprintf( self::MISSING, $id ) );
            $ctx->out->error( null === $call ? sprintf( self::MISSING, $id ) : sprintf( $call[1], "comment {$id}" ) );
        }
        $ctx->out->error( $error );
    }

    private static function moderation_state( array $payload ): string {
        $state = self::status_word( isset( $payload['comment_approved'] ) ? $payload['comment_approved'] : null );
        return false === $state ? (string) ( isset( $payload['status'] ) ? $payload['status'] : 'unknown' ) : $state;
    }

    private static function moderation_unchanged( Context $ctx, int $id, ?array $call, string $state ): void {
        $ctx->note( sprintf( self::ALREADY, $id, $state ) );
        $ctx->out->error( null === $call ? self::STATUS_UNCHANGED : sprintf( $call[1], "comment {$id}" ) );
    }

    private function list_rows( Context $ctx, array $list, bool $details ): array {
        $arguments = $list['arguments'];
        $number    = $list['number'];
        $start     = $list['start'];
        $first     = $this->list_page( $ctx, $arguments, $start, null !== $number ? min( self::PAGE, $number ) : self::PAGE, $details );
        $total     = (int) $first['total'];
        $wanted    = max( 0, $total - $start );
        if ( null !== $number ) {
            $wanted = min( $wanted, $number );
        }
        if ( $wanted > self::LIST_CAP ) {
            $ctx->out->error( sprintf( self::LIST_OVER_CAP, self::LIST_CAP, $total - $start ) );
        }
        $rows = $first['rows'];
        while ( count( $rows ) < $wanted ) {
            $page = $this->list_page( $ctx, $arguments, $start + count( $rows ), min( self::PAGE, $wanted - count( $rows ) ), $details );
            if ( ! $page['rows'] ) {
                break;
            }
            $rows = array_merge( $rows, $page['rows'] );
        }
        return array_slice( $rows, 0, $wanted );
    }

    private static function list_items( array $rows, string $format ): array {
        $items = array();
        foreach ( $rows as $row ) {
            $items[] = 'ids' === $format ? (string) $row['id'] : self::comment_object( $row, true );
        }
        return $items;
    }

    private function list_page( Context $ctx, array $arguments, int $offset, int $per_page, bool $details ): array {
        $arguments['per_page'] = max( 1, $per_page );
        $arguments['offset']   = $offset;
        if ( $details ) {
            $arguments['include_details'] = true;
        }
        $result = $ctx->delegate( 'wp_list_comments', $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $rows = isset( $result['payload']['comments'] ) && is_array( $result['payload']['comments'] ) ? array_values( $result['payload']['comments'] ) : array();
        return array(
            'rows'  => $rows,
            'total' => isset( $result['payload']['total'] ) ? (int) $result['payload']['total'] : count( $rows ),
        );
    }

    private function comment_missing( Context $ctx, string $tool, int $id ): bool {
        return $ctx->may_use( $tool ) && null === self::read_comment( $id );
    }

    private function orphan_after_refusal( Context $ctx, int $id ): bool {
        if ( ! $ctx->may_use( 'wp_get_comment' ) ) {
            return false;
        }
        $comment = self::read_comment( $id );
        return null !== $comment && ! empty( $comment->comment_post_ID ) && ! \get_post( (int) $comment->comment_post_ID );
    }

    private static function read_comment( int $id ): ?object {
        if ( $id < 1 ) {
            return null;
        }
        $comment = \get_comment( $id );
        return $comment ? $comment : null;
    }

    private static function delete_force( Invocation $inv, string $id ): bool {
        if ( $inv->has( 'force' ) ) {
            return true;
        }
        if ( defined( 'EMPTY_TRASH_DAYS' ) && ! EMPTY_TRASH_DAYS ) {
            return true;
        }
        if ( ! preg_match( '/^[0-9]+\z/', $id ) || ! self::caller_may_use( 'wp_delete_comment' ) || ! \current_user_can( 'moderate_comments' ) ) {
            return false;
        }
        return in_array( self::wp_status( self::read_comment( (int) $id ) ), array( 'trash', 'spam' ), true );
    }

    private static function caller_may_use( string $tool ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
            return true;
        }
        return false !== \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
    }

    private static function wp_status( ?object $comment ) {
        if ( null === $comment || ! isset( $comment->comment_approved ) ) {
            return false;
        }
        return self::status_word( $comment->comment_approved );
    }

    private static function status_word( $approved ) {
        // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- core's own loose comparison.
        if ( '1' == $approved ) {
            return 'approved';
        }
        // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- core's own loose comparison.
        if ( '0' == $approved ) {
            return 'unapproved';
        }
        if ( 'spam' === $approved ) {
            return 'spam';
        }
        if ( 'trash' === $approved ) {
            return 'trash';
        }
        return false;
    }

    private static function comment_object( array $row, bool $list_row ): \stdClass {
        return (object) array(
            'comment_ID'           => self::text( self::row_value( $row, 'id' ) ),
            'comment_post_ID'      => self::text( self::row_value( $row, 'post' ) ),
            'comment_author'       => self::row_value( $row, 'author_name' ),
            'comment_author_email' => self::row_value( $row, 'author_email' ),
            'comment_author_url'   => self::row_value( $row, 'author_url' ),
            'comment_author_IP'    => self::row_value( $row, 'author_ip' ),
            'comment_date'         => self::mysql_date( self::row_value( $row, 'date' ) ),
            'comment_date_gmt'     => self::mysql_date( self::row_value( $row, 'date_gmt' ) ),
            'comment_content'      => self::row_value( $row, $list_row ? 'content_raw' : 'content' ),
            'comment_karma'        => self::text( self::row_value( $row, 'karma' ) ),
            'comment_approved'     => self::text( self::row_value( $row, 'comment_approved' ) ),
            'comment_agent'        => self::row_value( $row, 'author_user_agent' ),
            'comment_type'         => self::row_value( $row, 'type' ),
            'comment_parent'       => self::text( self::row_value( $row, 'parent' ) ),
            'user_id'              => self::text( self::row_value( $row, 'author' ) ),
            'url'                  => self::row_value( $row, 'link' ),
        );
    }

    private static function row_value( array $row, string $key ) {
        return isset( $row[ $key ] ) ? $row[ $key ] : null;
    }

    protected function comment_instance( \stdClass $fields ): object {
        return class_exists( '\\WP_Comment' ) ? new \WP_Comment( $fields ) : $fields;
    }

    private static function text( $value ): ?string {
        return null === $value ? null : (string) $value;
    }

    private static function mysql_date( $value ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }
        if ( '-0001-11-30T00:00:00' === $value ) {
            return '0000-00-00 00:00:00';
        }
        if ( preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})\z/', $value, $m ) ) {
            return $m[1] . ' ' . $m[2];
        }
        return $value;
    }

    private static function verb( Invocation $inv ): string {
        $words = explode( ' ', $inv->command );
        return (string) end( $words );
    }

    private static function id_value( string $id ) {
        return preg_match( '/^[0-9]+\z/', $id ) ? (int) $id : $id;
    }

    private static function require_digits( Context $ctx, string $value, string $what ): void {
        if ( ! preg_match( '/^[0-9]+\z/', $value ) ) {
            $ctx->out->error( sprintf( self::BAD_ID, $value, $what ) );
        }
    }

    private static function fields( Invocation $inv ): array {
        $named = array();
        foreach ( isset( $inv->leaf['params'] ) ? (array) $inv->leaf['params'] : array() as $param ) {
            if ( in_array( $param['type'], array( 'assoc', 'flag' ), true ) ) {
                $named[ (string) $param['name'] ] = true;
            }
        }
        $fields = array();
        foreach ( $inv->assoc as $key => $value ) {
            if ( ! isset( $named[ (string) $key ] ) ) {
                $fields[ (string) $key ] = $value;
            }
        }
        return $fields;
    }

    private static function map_fields( Context $ctx, array $fields, bool $create ): array {
        $mapped = array();
        foreach ( $fields as $key => $value ) {
            $refusal = self::field_refusal( $key, $value, $create );
            if ( null !== $refusal ) {
                $ctx->out->error( $refusal );
            }
            $mapped[ self::FIELD_MAP[ $key ] ] = self::field_value( $key, (string) $value );
        }
        return $mapped;
    }

    private static function map_fields_quietly( array $fields, bool $create ): array {
        $mapped = array();
        foreach ( $fields as $key => $value ) {
            if ( null === self::field_refusal( $key, $value, $create ) ) {
                $mapped[ self::FIELD_MAP[ $key ] ] = self::field_value( $key, (string) $value );
            }
        }
        return $mapped;
    }

    private static function field_refusal( string $key, $value, bool $create ): ?string {
        $refusal = self::field_name_refusal( $key, $create );
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( ! is_string( $value ) ) {
            return sprintf( self::NEEDS_VALUE, $key );
        }
        if ( in_array( $key, array( 'comment_post_ID', 'comment_parent', 'user_id' ), true ) && ! preg_match( '/^[0-9]+\z/', $value ) ) {
            return sprintf( self::BAD_ID, $value, '--' . $key );
        }
        if ( 'comment_approved' === $key && ! isset( self::STATUS_MAP[ $value ] ) ) {
            return sprintf( 'wp_cli_run refuses --comment_approved=%s: use 1 (or approve), 0 (or hold), spam or trash. WordPress would store any other word as the status, and no comment screen lists such a comment.', $value );
        }
        return null;
    }

    private static function field_name_refusal( string $key, bool $create ): ?string {
        if ( 'comment_ID' === $key ) {
            return $create
                ? 'wp_cli_run refuses --comment_ID: WP-CLI ignores it, because the database chooses a new comment\'s ID.'
                : 'wp_cli_run refuses --comment_ID: the comment to update is the <id> argument, and WP-CLI ignores this field.';
        }
        if ( isset( self::FIELD_REFUSED[ $key ] ) ) {
            return sprintf( 'wp_cli_run refuses --%s: %s.', $key, self::FIELD_REFUSED[ $key ] );
        }
        if ( ! isset( self::FIELD_MAP[ $key ] ) ) {
            return sprintf( 'wp_cli_run refuses --%s: it is not a field %s stores, and WP-CLI would drop it without saying so.', $key, $create ? 'wp_insert_comment()' : 'wp_update_comment()' );
        }
        return null;
    }

    private static function field_value( string $key, string $value ) {
        if ( in_array( $key, array( 'comment_post_ID', 'comment_parent', 'user_id' ), true ) ) {
            return (int) $value;
        }
        if ( 'comment_approved' === $key ) {
            return self::STATUS_MAP[ $value ];
        }
        return $value;
    }

    private static function query_vars( Invocation $inv ): array {
        $vars = $inv->assoc;
        unset( $vars['format'], $vars['fields'], $vars['field'] );
        return $vars;
    }

    private static function list_arguments( Context $ctx, array $vars ): array {
        self::refuse_list_vars( $ctx, $vars );
        $paging    = self::list_paging( $ctx, $vars );
        $arguments = array(
            'status' => 'all',
            'type'   => 'all',
        );
        foreach ( $vars as $key => $value ) {
            $key = (string) $key;
            if ( null !== self::LIST_VARS[ $key ] ) {
                $arguments = self::list_argument( $ctx, $arguments, $key, (string) $value );
            }
        }
        return array(
            'arguments' => $arguments,
            'number'    => $paging['number'],
            'start'     => $paging['start'],
        );
    }

    private static function refuse_list_vars( Context $ctx, array $vars ): void {
        foreach ( $vars as $key => $value ) {
            $key = (string) $key;
            if ( ! array_key_exists( $key, self::LIST_VARS ) ) {
                $reason = isset( self::LIST_REFUSED[ $key ] ) ? self::LIST_REFUSED[ $key ] : 'wp_list_comments has no filter for it, and WP-CLI would pass it to WP_Comment_Query';
                $ctx->out->error( sprintf( 'wp_cli_run refuses --%s for comment list: %s.', $key, $reason ) );
            }
            if ( ! is_string( $value ) ) {
                $ctx->out->error( sprintf( self::NEEDS_VALUE, $key ) );
            }
        }
    }

    private static function list_paging( Context $ctx, array $vars ): array {
        $number = self::list_int( $ctx, $vars, 'number' );
        $offset = self::list_int( $ctx, $vars, 'offset' );
        $paged  = self::list_int( $ctx, $vars, 'paged' );
        if ( null === $number || 0 === $number ) {
            foreach ( array( 'offset' => $offset, 'paged' => $paged ) as $key => $given ) {
                if ( null !== $given ) {
                    $ctx->out->error( sprintf( 'wp_cli_run refuses --%s without --number: WP_Comment_Query ignores it then and WP-CLI lists every comment. Add --number.', $key ) );
                }
            }
            $number = null;
        }
        if ( null !== $paged && $paged < 1 ) {
            $ctx->out->error( 'wp_cli_run refuses --paged=0: pages start at 1, and WP_Comment_Query fails on page 0.' );
        }
        $start = 0;
        if ( null !== $number ) {
            $start = $offset ? $offset : $number * ( ( null !== $paged ? $paged : 1 ) - 1 );
        }
        return array(
            'number' => $number,
            'start'  => $start,
        );
    }

    private static function list_argument( Context $ctx, array $arguments, string $key, string $value ): array {
        switch ( $key ) {
            case 'post_id':
                return self::list_post_id( $ctx, $arguments, $value );
            case 'status':
                return self::list_status( $arguments, $value );
            case 'type':
                return self::list_type( $ctx, $arguments, $value );
            case 'parent':
                $arguments['parent'] = array( self::list_id( $ctx, $key, $value ) );
                return $arguments;
            case 'orderby':
                return self::list_orderby( $ctx, $arguments, $value );
        }
        return self::list_filter( $ctx, $arguments, $key, $value );
    }

    private static function list_filter( Context $ctx, array $arguments, string $key, string $value ): array {
        $arg = (string) self::LIST_VARS[ $key ];
        switch ( $key ) {
            case 'user_id':
            case 'author__in':
                $arguments['author'] = self::list_author( $ctx, $arguments, $key, $value );
                return $arguments;
            case 'post_author':
            case 'post_parent':
            case 'karma':
                $arguments[ $arg ] = preg_match( '/^-?[0-9]+\z/', $value ) ? (int) $value : $value;
                return $arguments;
        }
        $arguments[ $arg ] = in_array( $key, self::LIST_ID_VARS, true ) ? self::list_id_var( $ctx, $arguments, $key, $value ) : $value;
        return $arguments;
    }

    private static function list_post_id( Context $ctx, array $arguments, string $value ): array {
        $id = self::list_id( $ctx, 'post_id', $value );
        if ( $id > 0 ) {
            $arguments['post_id'] = $id;
        }
        return $arguments;
    }

    private static function list_status( array $arguments, string $value ): array {
        $statuses = array();
        foreach ( preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY ) as $status ) {
            $statuses[] = '1' === $status ? 'approve' : ( '0' === $status ? 'hold' : $status );
        }
        $statuses = array_values( array_unique( $statuses ) );
        if ( ! $statuses ) {
            $arguments['status'] = 'all';
        } elseif ( 1 === count( $statuses ) ) {
            $arguments['status'] = $statuses[0];
        } else {
            unset( $arguments['status'] );
            $arguments['statuses'] = $statuses;
        }
        return $arguments;
    }

    private static function list_type( Context $ctx, array $arguments, string $value ): array {
        if ( 'all' === $value ) {
            $ctx->out->error( 'wp_cli_run refuses --type=all for comment list: WordPress lists block editor notes for it, which wp_list_comments cannot include. Omit --type for every type but notes, or pass --type=note.' );
        }
        $arguments['type'] = '' === $value ? 'all' : $value;
        return $arguments;
    }

    private static function list_orderby( Context $ctx, array $arguments, string $value ): array {
        if ( ! isset( self::ORDERBY_MAP[ $value ] ) ) {
            $ctx->out->error( sprintf( 'wp_cli_run refuses --orderby=%s for comment list: wp_list_comments sorts only by %s.', $value, implode( ', ', array_keys( self::ORDERBY_MAP ) ) ) );
        }
        $arguments['orderby'] = self::ORDERBY_MAP[ $value ];
        return $arguments;
    }

    private static function list_author( Context $ctx, array $arguments, string $key, string $value ): int {
        $ids = 'user_id' === $key ? array( self::list_id( $ctx, $key, $value ) ) : self::list_ids( $ctx, $key, $value );
        if ( 1 !== count( $ids ) || $ids[0] < 1 ) {
            $ctx->out->error( sprintf( 'wp_cli_run refuses --%s=%s for comment list: wp_list_comments filters by one registered author (a user ID of 1 or more).', $key, $value ) );
        }
        if ( isset( $arguments['author'] ) && $arguments['author'] !== $ids[0] ) {
            $ctx->out->error( 'wp_cli_run refuses --user_id together with a different --author__in: wp_list_comments filters by one author.' );
        }
        return $ids[0];
    }

    private static function list_id_var( Context $ctx, array $arguments, string $key, string $value ): array {
        $ids = self::list_ids( $ctx, $key, $value );
        if ( 'parent__in' === $key && isset( $arguments['parent'] ) ) {
            $ids = array_values( array_unique( array_merge( $arguments['parent'], $ids ) ) );
        }
        return $ids;
    }

    private static function list_int( Context $ctx, array $vars, string $key ): ?int {
        if ( ! isset( $vars[ $key ] ) ) {
            return null;
        }
        return self::list_id( $ctx, $key, (string) $vars[ $key ] );
    }

    private static function list_id( Context $ctx, string $key, string $value ): int {
        if ( ! preg_match( '/^[0-9]+\z/', $value ) ) {
            $ctx->out->error( sprintf( 'wp_cli_run refuses --%s=%s for comment list: pass a whole number in digits. WP-CLI would convert it to a different number rather than refuse it.', $key, $value ) );
        }
        return (int) $value;
    }

    private static function list_ids( Context $ctx, string $key, string $value ): array {
        $ids = array();
        foreach ( explode( ',', $value ) as $part ) {
            $ids[] = self::list_id( $ctx, $key, trim( $part ) );
        }
        return array_values( array_unique( $ids ) );
    }
}
