<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
























class Network_Meta_Handler extends Meta_Command {

    protected $meta_type = 'site';

    const NOT_MULTISITE = 'This is not a multisite installation.';

    const OTHER_NETWORK = 'wp_cli_run reads and writes only the network this MCP connection belongs to (network %1$d); network %2$s is another network, whose super admins may differ.';

    const NOT_CAPTURED = 'Network meta writes go through the metadata API (wp_sitemeta), which change history does not record.';

    




    public function preflight( Invocation $inv, Context $ctx ): void {
        if ( ! $this->is_multisite() ) {
            $ctx->out->error( self::NOT_MULTISITE );
        }
    }

    public function add( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        parent::add( $inv, $ctx );
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        parent::update( $inv, $ctx );
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        parent::delete( $inv, $ctx );
    }

    public function patch( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED );
        parent::patch( $inv, $ctx );
    }

    








    protected function check_object_id( $object_id, string $subcommand ) {
        $network = $this->current_network_id();
        if ( ! is_scalar( $object_id ) || (int) $object_id !== $network ) {
            $this->ctx->out->error( sprintf( self::OTHER_NETWORK, $network, is_scalar( $object_id ) ? (string) $object_id : '' ) );
        }
        return $object_id;
    }

    protected function protect_key( string $key, bool $write ): ?string {
        $refusal = parent::protect_key( $key, $write );
        if ( null !== $refusal ) {
            return $refusal;
        }
        return Self_Protection::option( $key, $write );
    }

    
    
    

    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        return \get_metadata( $this->meta_type, $object_id, $meta_key, $single );
    }

    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \add_metadata( $this->meta_type, $object_id, $meta_key, $meta_value, false );
    }

    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \update_metadata( $this->meta_type, $object_id, $meta_key, $meta_value, '' );
    }

    protected function delete_metadata( $object_id, string $meta_key, $meta_value = '' ): bool {
        return (bool) \delete_metadata( $this->meta_type, $object_id, $meta_key, $meta_value, false );
    }

    protected function is_multisite(): bool {
        return function_exists( 'is_multisite' ) && \is_multisite();
    }

    protected function current_network_id(): int {
        return function_exists( 'get_current_network_id' ) ? (int) \get_current_network_id() : 1;
    }
}
