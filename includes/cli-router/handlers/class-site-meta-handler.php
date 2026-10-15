<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















class Site_Meta_Handler extends Meta_Command {

    protected $meta_type = 'blog';

    const NOT_MULTISITE = 'This is not a multisite installation.';

    const NO_TABLE = 'The %s table is not installed. Please run the network database upgrade.';

    const OTHER_NETWORK = 'site %1$s belongs to network %2$d, and wp_cli_run acts only on the sites of the network this MCP connection belongs to (network %3$d): a super admin of one network is not one of another.';

    const PLUGIN_KEY = "Self-protection: the site meta key '%s' is in the namespace of the plugin that serves this MCP connection, and wp_cli_run never reads or writes that plugin's data.";

    const NOT_CAPTURED = 'Site meta (wp_blogmeta) writes: change history does not record them.';

    




    public function preflight( Invocation $inv, Context $ctx ): void {
        if ( ! $this->is_multisite() ) {
            $ctx->out->error( self::NOT_MULTISITE );
        }
        if ( ! $this->is_site_meta_supported() ) {
            $ctx->out->error( sprintf( self::NO_TABLE, $this->blogmeta_table() ) );
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
        $site = $this->site_row( $object_id );
        if ( ! $site ) {
            $this->ctx->out->error( sprintf( Fetchers::MESSAGES['site'], $object_id ) );
        }
        $network = $this->current_network_id();
        if ( isset( $site->site_id ) && (int) $site->site_id !== $network ) {
            $this->ctx->out->error( sprintf( self::OTHER_NETWORK, (string) $site->blog_id, (int) $site->site_id, $network ) );
        }
        return $site->blog_id;
    }

    protected function protect_key( string $key, bool $write ): ?string {
        $refusal = parent::protect_key( $key, $write );
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( preg_match( Self_Protection::NAMESPACE_PATTERN, strtolower( trim( $key ) ) ) ) {
            return sprintf( self::PLUGIN_KEY, trim( $key ) );
        }
        return null;
    }

    
    
    

    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        return \get_site_meta( (int) $object_id, $meta_key, $single );
    }

    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \add_site_meta( (int) $object_id, $meta_key, $meta_value );
    }

    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \update_site_meta( (int) $object_id, $meta_key, $meta_value );
    }

    protected function delete_metadata( $object_id, string $meta_key, $meta_value = '' ): bool {
        return (bool) \delete_site_meta( (int) $object_id, $meta_key, $meta_value );
    }

    protected function is_multisite(): bool {
        return function_exists( 'is_multisite' ) && \is_multisite();
    }

    protected function is_site_meta_supported(): bool {
        return function_exists( 'is_site_meta_supported' ) && \is_site_meta_supported();
    }

    protected function blogmeta_table(): string {
        global $wpdb;
        return isset( $wpdb->blogmeta ) ? (string) $wpdb->blogmeta : '';
    }

    protected function current_network_id(): int {
        return function_exists( 'get_current_network_id' ) ? (int) \get_current_network_id() : 1;
    }

    





    protected function site_row( string $site_id ) {
        return Fetchers::of( 'site' )->get( $site_id );
    }
}
