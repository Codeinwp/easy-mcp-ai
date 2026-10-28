<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Command_Handler {

    protected function formatter( Context $ctx, array &$assoc, ?array $fields = null, ?string $prefix = null ): Formatter {
        return new Formatter( $ctx->out, $assoc, $fields, $prefix );
    }

    protected function default_fields( Invocation $inv, array $fallback = array() ): array {
        $fields = $inv->leaf['fields']['default'] ?? null;
        return is_array( $fields ) && $fields ? array_values( $fields ) : $fallback;
    }
}
