<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Cli_Exit;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

































class Maintenance_Mode_Handler extends Command_Handler {

    const REASON_FILESYSTEM = "WordPress cannot write to its own folder directly here (its filesystem method is '%s'), and wp_cli_run cannot answer an FTP or SSH credentials prompt; WP-CLI forces direct file access. Remove the .maintenance file on the server.";

    
    const NOT_CAPTURED_FILE = 'The .maintenance file: change history does not record files.';

    
    public function preflight_deactivate( Invocation $inv, Context $ctx ): void {
        Core_Handler::require_update_core( $inv->command, $ctx );
        $method = $this->wp( 'filesystem_method' );
        if ( 'direct' !== $method ) {
            $ctx->out->error( sprintf( self::REASON_FILESYSTEM, $method ) );
        }
    }

    
    public function deactivate( Invocation $inv, Context $ctx ): void {
        if ( ! $this->get_maintenance_mode_status( $ctx ) ) {
            $ctx->out->error( 'Maintenance mode already deactivated.' );
        }

        $result = (array) $this->wp( 'maintenance_mode_off' );
        Language_Handler::print_feedback( $ctx, (array) ( $result['feedback'] ?? array() ) );
        if ( ! empty( $result['deleted'] ) ) {
            $ctx->mark_changed();
            $ctx->not_captured( self::NOT_CAPTURED_FILE );
        }
        $ctx->out->success( 'Deactivated Maintenance mode.' );
    }

    
    public function status( Invocation $inv, Context $ctx ): void {
        $status = $this->get_maintenance_mode_status( $ctx ) ? 'active' : 'not active';
        $ctx->out->line( "Maintenance mode is {$status}." );
    }

    
    public function is_active( Invocation $inv, Context $ctx ): void {
        $ctx->out->halt( $this->get_maintenance_mode_status( $ctx ) ? 0 : 1 );
    }

    
    public function describe_deactivate( Invocation $inv ): array {
        $file = (array) $this->wp( 'maintenance_file' );
        if ( empty( $file['exists'] ) ) {
            return array( '.maintenance: (no file) → nothing to delete; WP-CLI answers "already deactivated"' );
        }
        $upgrading = self::timestamp( (string) $file['contents'] );
        if ( null === $upgrading ) {
            return array( '.maintenance: present, no readable timestamp (maintenance mode off) → nothing deleted; WP-CLI answers "already deactivated"' );
        }
        return array( sprintf( '.maintenance: maintenance mode since %s UTC (off ten minutes after that) → delete the file if still active', gmdate( 'Y-m-d H:i:s', $upgrading ) ) );
    }

    
    
    

    




    private function get_maintenance_mode_status( Context $ctx ): bool {
        $file = (array) $this->wp( 'maintenance_file' );
        if ( empty( $file['exists'] ) ) {
            return false;
        }

        
        
        $upgrading = self::timestamp( (string) $file['contents'] );
        if ( null === $upgrading ) {
            $upgrading = 0;
            $ctx->out->warning( 'Unable to read the maintenance file timestamp, non-numeric value detected.' );
        }
        
        if ( ( (int) $this->wp( 'now' ) - $upgrading ) >= 10 * 60 ) {
            return false;
        }
        return true;
    }

    
    private static function timestamp( string $contents ): ?int {
        $matches = array();
        if ( preg_match( '/upgrading\s*=\s*(\d+)\s*;/i', $contents, $matches ) ) {
            return (int) $matches[1];
        }
        return null;
    }

    
    
    

    







    protected function wp( string $call, array $args = array() ) {
        return $this->{'call_' . $call}( ...$args );
    }

    
    protected function call_maintenance_file(): array {
        $file = trailingslashit( ABSPATH ) . '.maintenance';
        if ( ! file_exists( $file ) ) {
            return array( 'exists' => false );
        }
        $contents = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP_Filesystem_Direct::get_contents() is this call.
        return array(
            'exists'   => true,
            'contents' => false === $contents ? '' : $contents,
        );
    }

    
    protected function call_now(): int {
        return time();
    }

    
    protected function call_filesystem_method(): string {
        if ( ! function_exists( 'get_filesystem_method' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        return (string) \get_filesystem_method( array(), ABSPATH );
    }

    





    protected function call_maintenance_mode_off(): array {
        if ( ! class_exists( 'WP_Upgrader', false ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        \WP_Filesystem();
        $feedback = new \ArrayObject();
        $upgrader = new \WP_Upgrader( Language_Handler::new_skin( $feedback ) );
        $upgrader->init();
        $upgrader->maintenance_mode( false );
        return array(
            'feedback' => $feedback->getArrayCopy(),
            'deleted'  => ! file_exists( trailingslashit( ABSPATH ) . '.maintenance' ),
        );
    }
}
