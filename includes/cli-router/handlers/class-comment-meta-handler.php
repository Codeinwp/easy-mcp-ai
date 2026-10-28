<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Comment_Meta_Handler extends Meta_Command {

    protected $meta_type = 'comment';

    const WRITE_CAPS = array(
        'add'    => 'add_comment_meta',
        'update' => 'edit_comment_meta',
        'patch'  => 'edit_comment_meta',
        'delete' => 'delete_comment_meta',
    );

    const NOT_CAPTURED_META = 'comment meta: change history\'s meta capture (easy_mcp_ai_change_log_capture_meta) is off';

    const BAD_ID = "wp_cli_run refuses '%s' as a comment ID: it is not a whole number in digits, and WP-CLI would convert it to a different ID rather than refuse it. Pass the ID as digits only.";

    const NOT_EDITABLE = 'Sorry, you are not allowed to read or change the meta of comment %d: that needs permission to edit the comment, which WordPress gives to whoever can edit the post it belongs to.';

    const READ_PROTECTED = "Reading the protected meta key '%s' requires the manage_options capability.";

    const KEY_DENIED = "WordPress refused %s for the meta key '%s' on comment %d: the key is registered or filtered, and its auth callback or an auth_comment_meta filter denied you.";

    const KEY_UNREGISTERED = "Protected meta keys need the manage_options capability when nothing has registered them, and nothing registers '%s' for comments.";

    const KEYS_DENIED_ALL = 'Sorry, you are not allowed to delete every meta key of comment %d: WordPress refused delete_comment_meta for %s. Nothing was deleted.';

    public function preflight( Invocation $inv, Context $ctx ): void {
        $this->inv = $inv;
        $this->ctx = $ctx;
        $sub       = isset( $inv->leaf['name'] ) ? (string) $inv->leaf['name'] : '';

        if ( 'add' === $sub || 'update' === $sub ) {
            $this->preflight_set( $inv, $sub );
            return;
        }
        if ( 'delete' === $sub ) {
            $this->preflight_delete( $inv );
            return;
        }
        if ( in_array( $sub, array( 'get', 'list', 'pluck' ), true ) ) {
            $this->preflight_read( $inv );
            return;
        }
        if ( 'patch' === $sub ) {
            $this->preflight_patch( $inv );
        }
    }

    private function preflight_set( Invocation $inv, string $sub ): void {
        $out  = $this->ctx->out;
        $args = $inv->args;
        $this->refuse_key( isset( $args[1] ) ? (string) $args[1] : '' );
        $value = Read_Value::from_arg_or_stdin( $out, $args, 2 );
        Read_Value::read( $out, $value, $inv->assoc );
        $this->check_object_id( isset( $args[0] ) ? $args[0] : '', $sub );
    }

    private function preflight_delete( Invocation $inv ): void {
        $args = $inv->args;
        $all  = (bool) Read_Value::flag( $inv->assoc, 'all' );
        $key  = ! empty( $args[1] ) ? (string) $args[1] : '';
        $this->refuse_delete_args( $args, $all, $key );
        if ( ! $all ) {
            $this->refuse_key( $key );
        }
        $this->check_object_id( isset( $args[0] ) ? $args[0] : '', 'delete' );
    }

    private function refuse_delete_args( array $args, bool $all, string $key ): void {
        $out = $this->ctx->out;
        if ( empty( $key ) && ! $all ) {
            $out->error( 'Please specify a meta key, or use the --all flag.' );
        }
        if ( $all && '' !== $key ) {
            $out->error( self::DELETE_ALL_WITH_KEY );
        }
        if ( ! $all && isset( $args[2] ) && '' === (string) $args[2] ) {
            $out->error( sprintf( self::DELETE_EMPTY_VALUE, $key ) );
        }
    }

    private function preflight_read( Invocation $inv ): void {
        $args = $inv->args;
        $raw  = isset( $args[0] ) && is_scalar( $args[0] ) ? (string) $args[0] : '';
        if ( ! preg_match( '/^[0-9]+\z/', $raw ) ) {
            $this->ctx->out->error( sprintf( self::BAD_ID, $raw ) );
        }
    }

    private function preflight_patch( Invocation $inv ): void {
        $out  = $this->ctx->out;
        $args = array_values( $inv->args );
        $this->refuse_key( isset( $args[2] ) ? (string) $args[2] : '' );
        $this->check_object_id( isset( $args[1] ) ? $args[1] : '', 'patch' );
        if ( 'delete' !== ( isset( $args[0] ) ? (string) $args[0] : '' ) ) {
            $key_path = self::key_path( array_slice( $args, 3 ) );
            if ( count( $key_path ) < 2 ) {
                $out->error( self::PATCH_NO_VALUE );
            }
            Read_Value::read( $out, array_pop( $key_path ), $inv->assoc );
        }
    }

    public function add( Invocation $inv, Context $ctx ): void {
        $this->note_history( $ctx );
        parent::add( $inv, $ctx );
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $this->note_history( $ctx );
        parent::update( $inv, $ctx );
    }

    public function patch( Invocation $inv, Context $ctx ): void {
        $this->note_history( $ctx );
        parent::patch( $inv, $ctx );
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        $this->note_history( $ctx );
        parent::delete( $inv, $ctx );
    }

    protected function check_object_id( $object_id, string $subcommand ) {
        $out = $this->ctx->out;
        $raw = is_scalar( $object_id ) ? (string) $object_id : '';
        if ( ! preg_match( '/^[0-9]+\z/', $raw ) ) {
            $out->error( sprintf( self::BAD_ID, $raw ) );
        }
        $comment = (int) $raw > 0 ? Fetchers::of( 'comment' )->get( $raw ) : false;
        if ( ! $comment ) {
            $out->error( sprintf( Fetchers::MESSAGES['comment'], $raw ) );
        }
        $id = (int) $comment->comment_ID;
        if ( ! \current_user_can( 'edit_comment', $id ) ) {
            $out->error( sprintf( self::NOT_EDITABLE, $id ) );
        }
        if ( isset( self::WRITE_CAPS[ $subcommand ] ) ) {
            $this->check_write_keys( $subcommand, $id );
        }
        return $comment->comment_ID;
    }

    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        return \get_comment_meta( (int) $object_id, $meta_key, $single );
    }

    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \add_comment_meta( (int) $object_id, $meta_key, $meta_value, false );
    }

    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \update_comment_meta( (int) $object_id, $meta_key, $meta_value );
    }

    protected function delete_metadata( $object_id, string $meta_key, $meta_value = '' ): bool {
        return (bool) \delete_comment_meta( (int) $object_id, $meta_key, $meta_value );
    }

    protected function protect_key( string $key, bool $write ): ?string {
        $refusal = parent::protect_key( $key, $write );
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( ! $write && \is_protected_meta( $key, 'comment' ) && ! \current_user_can( 'manage_options' ) ) {
            return sprintf( self::READ_PROTECTED, $key );
        }
        return null;
    }

    private function refuse_key( string $key ): void {
        $refusal = $this->protect_key( $key, true );
        if ( null !== $refusal ) {
            $this->ctx->out->error( $refusal );
        }
    }

    private function check_write_keys( string $subcommand, int $id ): void {
        $cap  = self::WRITE_CAPS[ $subcommand ];
        $args = array_values( $this->inv->args );
        if ( 'delete' === $subcommand && Read_Value::flag( $this->inv->assoc, 'all' ) ) {
            $this->check_delete_all_keys( $cap, $id );
            return;
        }
        $key     = (string) ( 'patch' === $subcommand ? ( isset( $args[2] ) ? $args[2] : '' ) : ( isset( $args[1] ) ? $args[1] : '' ) );
        $refusal = $this->key_refusal( $cap, $id, $key );
        if ( null !== $refusal ) {
            $this->ctx->out->error( $refusal );
        }
    }

    private function check_delete_all_keys( string $cap, int $id ): void {
        $denied = array();
        $all    = $this->get_metadata( $id );
        foreach ( is_array( $all ) ? array_keys( $all ) : array() as $key ) {
            $key = (string) $key;
            if ( null === parent::protect_key( $key, true ) && null !== $this->key_refusal( $cap, $id, $key ) ) {
                $denied[] = $key;
            }
        }
        if ( $denied ) {
            sort( $denied, SORT_STRING );
            $this->ctx->out->error( sprintf( self::KEYS_DENIED_ALL, $id, "'" . implode( "', '", $denied ) . "'" ) );
        }
    }

    private function key_refusal( string $cap, int $id, string $key ): ?string {
        if ( \current_user_can( $cap, $id, $key ) ) {
            return null;
        }
        $claimed = $this->key_is_claimed( $key );
        if ( ! $claimed && \is_protected_meta( $key, 'comment' ) ) {
            return \current_user_can( 'manage_options' ) ? null : sprintf( self::KEY_UNREGISTERED, $key );
        }
        return sprintf( self::KEY_DENIED, $cap, $key, $id );
    }

    private function key_is_claimed( string $key ): bool {
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            foreach ( array( 'comment', '' ) as $subtype ) {
                $registered = \get_registered_meta_keys( 'comment', $subtype );
                if ( isset( $registered[ $key ] ) ) {
                    return true;
                }
            }
        }
        return function_exists( 'has_filter' ) && ( \has_filter( "auth_comment_meta_{$key}_for_comment" ) || \has_filter( "auth_comment_meta_{$key}" ) );
    }

    private function note_history( Context $ctx ): void {
        $on = true;
        if ( class_exists( '\\Easy_MCP_AI\\Config' ) ) {
            $on = (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_capture_meta', true );
        }
        if ( ! $on ) {
            $ctx->not_captured( self::NOT_CAPTURED_META );
        }
    }
}
