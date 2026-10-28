<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Adapter extends Command_Handler {

    const TOOL = 'wp_update_site_settings';

    const NOTE_UNCHANGED = "Nothing changed: the site language was already '%s'. WP-CLI prints the same Success line.";

    public function switch_language( Invocation $inv, Context $ctx ): void {
        list( $language_code ) = array_values( $inv->args );
        $language_code         = (string) $language_code;

        Fetchers::require_tool( $ctx, $inv, self::TOOL );

        $available = $this->get_installed_languages();

        if ( ! in_array( $language_code, $available, true ) ) {
            $ctx->out->error( 'Language not installed.' );
        }

        if ( 'en_US' === $language_code ) {
            $language_code = '';
        }

        if ( $this->get_locale() === $language_code ) {
            $ctx->out->warning( "Language '{$language_code}' already active." );

            return;
        }

        $result = $ctx->delegate( self::TOOL, array( 'language' => $language_code ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['updated'] ) ) {
            $ctx->mark_changed();
        } else {
            $ctx->note( sprintf( self::NOTE_UNCHANGED, '' === $language_code ? 'en_US' : $language_code ) );
        }

        $ctx->out->success( 'Language activated.' );
    }

    protected function get_installed_languages(): array {
        if ( ! function_exists( 'wp_get_installed_translations' ) ) {
            return array( 'en_US' );
        }
        $available   = \wp_get_installed_translations( 'core' );
        $available   = ! empty( $available['default'] ) ? array_map( 'strval', array_keys( $available['default'] ) ) : array();
        $available[] = 'en_US';

        return $available;
    }

    protected function get_locale(): string {
        return (string) \get_locale();
    }
}
