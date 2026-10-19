<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















































final class Post_Meta_Adapter extends Meta_Command {

    use Planned_Run;

    protected $meta_type = 'post';

    
    private ?string $read_base = null;

    
    private string $subcommand = '';

    const MSG_EDIT_CAP = "'wp %s' needs the edit_post capability for post %d: %s reads and writes protected meta only for users who can edit the post.";

    const MSG_TRUNCATED = "Post %d has more meta keys than wp_get_post_full returns in one call, so 'wp %s' cannot list them all. Name the keys with --keys=<key1,key2>.";

    const MSG_META_CAP = "WordPress refused edit_post_meta for the key '%s' on post %d: wp_update_post_meta writes it through the REST API, which asks that capability first, and the key's auth callback or an auth_post_meta filter denied your user.";

    
    
    

    





    public function preflight_id( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        $at   = isset( $inv->leaf['name'] ) && 'patch' === $inv->leaf['name'] ? 1 : 0;
        Ids::require_digits( $ctx, isset( $args[ $at ] ) ? $args[ $at ] : '', 'a post ID' );
    }

    








    public function plan_patch( Invocation $inv ): array {
        $args = array_values( $inv->args );
        if ( ! Ids::is_digits( isset( $args[1] ) ? $args[1] : '' ) ) {
            return array();
        }
        return $this->planned_calls(
            $inv,
            'patch',
            array( 'wp_get_post_full', 'wp_update_post_meta' ),
            array( 'wp_get_post_full' => '\\Easy_MCP_AI\\Tools\\Posts\\Get_Post_Full' )
        );
    }

    
    
    

    







    protected function check_object_id( $object_id, string $subcommand ) {
        $this->subcommand = $subcommand;
        $writes           = in_array( $subcommand, array( 'update', 'patch' ), true );
        $tool             = 'delete' === $subcommand ? 'wp_delete_post_meta' : ( $writes ? 'wp_update_post_meta' : 'wp_get_post_full' );
        $command          = $this->inv->command;
        if ( $writes ) {
            
            Fetchers::require_tool( $this->ctx, $this->inv, 'wp_get_post_full' );
        }
        $post             = Fetchers::of( 'post' )->get_check_for(
            $this->ctx,
            $this->inv,
            $tool,
            $object_id,
            static function ( object $post ) use ( $command, $tool ): ?string {
                return \current_user_can( 'edit_post', (int) $post->ID ) ? null : sprintf( self::MSG_EDIT_CAP, $command, (int) $post->ID, $tool );
            }
        );
        if ( 'delete' !== $subcommand ) {
            $base = Post_Adapter::read_base( (string) $post->post_type, $command );
            if ( ! is_array( $base ) ) {
                $this->ctx->out->error( $base );
            }
            $this->read_base = $base[0];
        }
        if ( $writes ) {
            $this->require_meta_cap( (int) $post->ID, $subcommand );
        }
        return $post->ID;
    }

    





    private function require_meta_cap( int $post_id, string $subcommand ): void {
        $args = array_values( $this->inv->args );
        $key  = (string) ( 'patch' === $subcommand ? ( $args[2] ?? '' ) : ( $args[1] ?? '' ) );
        if ( ! \current_user_can( 'edit_post_meta', $post_id, $key ) ) {
            $this->ctx->out->error( sprintf( self::MSG_META_CAP, $key, $post_id ) );
        }
    }

    






    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        if ( 'delete' === $this->subcommand ) {
            
            $all = \get_post_meta( (int) $object_id );
            return is_array( $all ) ? $all : array();
        }

        $meta = $this->read_meta( (int) $object_id, $meta_key );
        if ( '' === $meta_key ) {
            return self::all_rows( $meta );
        }

        
        
        $values = self::key_rows( $meta, $meta_key );
        if ( $single ) {
            return $values ? self::maybe_unserialize( $values[0] ) : '';
        }
        return array_map( array( __CLASS__, 'maybe_unserialize' ), $values );
    }

    







    private function read_meta( int $object_id, string $meta_key ): array {
        $args = array(
            'id'              => (int) $object_id,
            'post_type'       => (string) $this->read_base,
            'include'         => array( 'meta' ),
            'context'         => 'edit',
            'meta_all_values' => true,
        );
        $keys = $this->keys_to_read( $meta_key );
        if ( $keys ) {
            $args['meta_keys'] = $keys;
        }

        $result = $this->ctx->delegate( 'wp_get_post_full', $args );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['meta_truncated'] ) ) {
            $this->ctx->out->error( sprintf( self::MSG_TRUNCATED, $object_id, $this->inv->command ) );
        }
        return isset( $result['payload']['meta'] ) && is_array( $result['payload']['meta'] ) ? $result['payload']['meta'] : array();
    }

    






    private function keys_to_read( string $meta_key ): array {
        if ( '' !== $meta_key ) {
            return array( $meta_key );
        }
        if ( 'list' === $this->subcommand && ! empty( $this->inv->assoc['keys'] ) ) {
            return array_values( array_unique( array_filter( explode( ',', (string) $this->inv->assoc['keys'] ), 'strlen' ) ) );
        }
        return array();
    }

    





    private static function all_rows( array $meta ): array {
        $all = array();
        foreach ( $meta as $key => $values ) {
            $all[ $key ] = is_array( $values ) ? array_values( $values ) : array( $values );
        }
        return $all;
    }

    





    private static function key_rows( array $meta, string $meta_key ): array {
        if ( ! isset( $meta[ $meta_key ] ) ) {
            return array();
        }
        return is_array( $meta[ $meta_key ] ) ? array_values( $meta[ $meta_key ] ) : array( $meta[ $meta_key ] );
    }

    






    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        throw new \LogicException( 'post meta add is an own handler (Post_Meta_Handler).' );
    }

    





    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        $result = $this->delegate(
            'wp_update_post_meta',
            array(
                'post_id'   => (int) $object_id,
                'meta'      => array( $meta_key => function_exists( 'wp_unslash' ) ? \wp_unslash( $meta_value ) : $meta_value ),
                'post_type' => (string) $this->read_base,
            )
        );
        if ( ! $result['ok'] ) {
            
            
            $this->ctx->out->error( (string) $result['error'] );
        }
        return true;
    }

    







    protected function delete_metadata( $object_id, string $meta_key, string $meta_value = '' ): bool {
        $args = array(
            'post_id'   => (int) $object_id,
            
            
            'key'       => function_exists( 'wp_unslash' ) ? (string) \wp_unslash( $meta_key ) : $meta_key,
            'exact_key' => true,
        );
        if ( '' !== $meta_value ) {
            $args['value'] = $meta_value;
        }
        $result = $this->delegate( 'wp_delete_post_meta', $args );
        if ( ! $result['ok'] ) {
            if ( Batch::is_rate_limited( $result ) ) {
                
                return false;
            }
            if ( $this->in_bulk() ) {
                $this->ctx->out->warning( (string) $result['error'] );
                return false;
            }
            
            $this->ctx->out->error( (string) $result['error'] );
        }
        return ! empty( $result['payload']['deleted'] );
    }

    
    
    

    
    private function in_bulk(): bool {
        return 'delete' === $this->subcommand && ! empty( $this->inv->assoc['all'] );
    }

    





    public static function maybe_unserialize( $value ) {
        return function_exists( 'maybe_unserialize' ) ? \maybe_unserialize( $value ) : $value;
    }
}
