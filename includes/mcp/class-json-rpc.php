<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JSON_RPC {

    public static function parse_request( $raw_body ) {
        $data = json_decode( $raw_body, true );
        if ( null === $data ) {
            return new \WP_Error( 'parse_error', Error_Codes::get_message( Error_Codes::PARSE_ERROR ), Error_Codes::PARSE_ERROR );
        }
        
        
        
        
        
        
        $is_batch = is_array( $data ) && ( empty( $data ) || array_key_exists( 0, $data ) );
        if ( $is_batch ) {
            
            if ( empty( $data ) ) {
                return new \WP_Error( 'invalid_request', 'Empty batch array is not allowed', Error_Codes::INVALID_REQUEST );
            }
            
            
            
            $messages = array();
            foreach ( $data as $item ) {
                $messages[] = self::validate_message( $item );
            }
            return $messages;
        }
        return self::validate_message( $data );
    }

    private static function validate_message( $data ) {
        if ( ! is_array( $data ) ) {
            return new \WP_Error( 'invalid_request', Error_Codes::get_message( Error_Codes::INVALID_REQUEST ), Error_Codes::INVALID_REQUEST );
        }
        if ( ! isset( $data['jsonrpc'] ) || '2.0' !== $data['jsonrpc'] ) {
            return new \WP_Error( 'invalid_request', 'Invalid JSON-RPC version', Error_Codes::INVALID_REQUEST );
        }
        if ( ! isset( $data['method'] ) || ! is_string( $data['method'] ) ) {
            return new \WP_Error( 'invalid_request', 'Missing or invalid method', Error_Codes::INVALID_REQUEST );
        }
        
        
        if ( array_key_exists( 'id', $data ) && null === $data['id'] ) {
            return new \WP_Error( 'invalid_request', 'Request id MUST NOT be null; omit the id field to send a notification', Error_Codes::INVALID_REQUEST );
        }
        $normalized = array(
            'jsonrpc' => '2.0',
            'method'  => $data['method'],
            'params'  => isset( $data['params'] ) ? $data['params'] : array(),
        );
        
        if ( array_key_exists( 'id', $data ) ) {
            $normalized['id'] = $data['id'];
        }
        return $normalized;
    }

    public static function success_response( $id, $result ) {
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
    }

    public static function error_response( $id, $code, $message, $data = null ) {
        $error = array( 'code' => $code, 'message' => $message );
        if ( null !== $data ) {
            $error['data'] = $data;
        }
        return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => $error );
    }

    public static function is_notification( $message ) {
        
        return ! array_key_exists( 'id', $message );
    }
}
