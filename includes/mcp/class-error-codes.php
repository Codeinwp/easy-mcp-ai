<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Error_Codes {
    const PARSE_ERROR      = -32700;
    const INVALID_REQUEST  = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS   = -32602;
    const INTERNAL_ERROR   = -32603;
    const UNAUTHORIZED        = -32001;
    
    const RESOURCE_NOT_FOUND  = -32002;
    const RATE_LIMITED        = -32003;
    const SESSION_EXPIRED     = -32004;
    const NOT_INITIALIZED     = -32005;
    const FORBIDDEN           = -32007;
    
    
    const PAUSED              = -32008;

    const HEADER_MISMATCH = -32020;
    
    const MISSING_CLIENT_CAPABILITY = -32021;
    const UNSUPPORTED_PROTOCOL_VERSION = -32022;

    const PAUSED_MESSAGE = 'This site has paused AI access. Ask the site owner to resume it.';

    private static $messages = array(
        self::PARSE_ERROR        => 'Parse error',
        self::INVALID_REQUEST    => 'Invalid Request',
        self::METHOD_NOT_FOUND   => 'Method not found',
        self::INVALID_PARAMS     => 'Invalid params',
        self::INTERNAL_ERROR     => 'Internal error',
        self::UNAUTHORIZED       => 'Unauthorized',
        self::RESOURCE_NOT_FOUND => 'Resource not found',
        self::FORBIDDEN          => 'Forbidden',
        self::RATE_LIMITED       => 'Rate limit exceeded',
        self::SESSION_EXPIRED    => 'Session expired',
        self::NOT_INITIALIZED    => 'Server not initialized',
        self::PAUSED             => self::PAUSED_MESSAGE,
    );

    public static function get_message( $code ) {
        return isset( self::$messages[ $code ] ) ? self::$messages[ $code ] : 'Unknown error';
    }
}
