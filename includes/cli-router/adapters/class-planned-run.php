<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Cli_Exit;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Planned_Run {

    private function planned_calls( Invocation $inv, string $method, array $tools, array $reads ): array {
        foreach ( $tools as $tool ) {
            if ( ! self::planning_may_use( $tool ) ) {
                return array();
            }
        }
        $planned = array();
        $ctx     = new Context(
            new Output(),
            static function ( string $tool, array $arguments ) use ( &$planned, $reads ): array {
                $planned[] = array(
                    'tool'      => $tool,
                    'arguments' => $arguments,
                );
                return array(
                    'response' => isset( $reads[ $tool ] ) ? self::planning_read( $tool, $reads[ $tool ], $arguments ) : self::planning_text( '{"updated":true}' ),
                    'approval' => array(
                        'kind'        => 'run',
                        'approval_id' => null,
                    ),
                    'audit_id' => null,
                );
            },
            static function (): bool {
                return true;
            }
        );
        try {
            ( new static() )->$method( $inv, $ctx );
        } catch ( Cli_Exit $e ) {
            unset( $e );
        }
        return $planned;
    }

    private static function planning_read( string $tool, string $source, array $arguments ): array {
        $object = self::planning_tool( $tool, $source );
        if ( ! method_exists( $object, 'execute' ) ) {
            return self::planning_error( $tool . ' is not available.' );
        }
        \Easy_MCP_AI\MCP\Tool_Call_Stack::enter( $tool );
        try {
            $result = $object->execute( $arguments );
        } catch ( \Throwable $e ) {
            return self::planning_error( $e->getMessage() );
        } finally {
            \Easy_MCP_AI\MCP\Tool_Call_Stack::leave();
        }
        $text = function_exists( 'wp_json_encode' ) ? \wp_json_encode( $result, JSON_UNESCAPED_SLASHES ) : json_encode( $result, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- fallback outside WordPress.
        return self::planning_text( (string) $text );
    }

    private static function planning_tool( string $tool, string $source ): object {
        $object = null;
        if ( class_exists( '\\Easy_MCP_AI\\Plugin', false ) && method_exists( '\\Easy_MCP_AI\\Plugin', 'instance' ) ) {
            $registry = \Easy_MCP_AI\Plugin::instance()->get_tool_registry();
            $object   = is_object( $registry ) && method_exists( $registry, 'get_tool' ) ? $registry->get_tool( $tool ) : null;
        }
        if ( is_object( $object ) ) {
            return $object;
        }
        return new $source();
    }

    private static function planning_text( string $text ): array {
        return array( 'result' => array( 'content' => array( array( 'type' => 'text', 'text' => $text ) ) ) );
    }

    private static function planning_error( string $message ): array {
        return array(
            'result' => array(
                'content' => array( array( 'type' => 'text', 'text' => 'Error: ' . $message ) ),
                'isError' => true,
            ),
        );
    }

    private static function planning_may_use( string $tool ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
            return true;
        }
        return false !== \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
    }
}
