<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Sidebar_Adapter extends Command_Handler {

    const FIELDS = array( 'name', 'id', 'description' );

    public function list_sidebars( Invocation $inv, Context $ctx ): void {
        $result = $ctx->delegate( 'wp_list_sidebars', array( 'include_markup' => true ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $sidebars = array();
        foreach ( Widget_Adapter::registered_sidebars() as $id => $registered ) {
            if ( Widget_Adapter::INACTIVE !== (string) $id ) {
                $sidebars[] = $registered;
            }
        }
        $sidebars[] = self::inactive_row();

        $assoc = $inv->assoc;
        if ( ! empty( $assoc['format'] ) && 'ids' === $assoc['format'] ) {
            $ids = array();
            foreach ( $sidebars as $sidebar ) {
                $ids[] = isset( $sidebar['id'] ) ? $sidebar['id'] : null;
            }
            $sidebars = $ids;
        }
        $this->formatter( $ctx, $assoc, self::FIELDS )->display_items( $sidebars );
    }

    public static function inactive_row(): array {
        return array(
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string, looked up as WP-CLI looks it up.
            'name'           => function_exists( '__' ) ? \__( 'Inactive Widgets' ) : 'Inactive Widgets',
            'id'             => Widget_Adapter::INACTIVE,
            // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's own string, looked up as WP-CLI looks it up.
            'description'    => function_exists( '__' ) ? \__( 'Drag widgets here to remove them from the sidebar but keep their settings.' ) : 'Drag widgets here to remove them from the sidebar but keep their settings.',
            'class'          => 'inactive-sidebar',
            'before_widget'  => '',
            'after_widget'   => '',
            'before_title'   => '',
            'after_title'    => '',
            'before_sidebar' => '',
            'after_sidebar'  => '',
            'show_in_rest'   => false,
        );
    }
}
