<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






















































abstract class Db_Object_Command extends Command_Handler {

    
    protected $obj_type = '';

    
    protected $obj_id_key = 'ID';

    
    protected $obj_fields = null;

    
    const DEFER_NOTE = '--defer-term-counting had no effect: each item ran through %s, which counts terms itself; the counts are the same.';

    







    protected function _create( Invocation $inv, Context $ctx, array $args, array $assoc_args, callable $callback ): void {
        unset( $assoc_args[ $this->obj_id_key ] );

        $obj_id = call_user_func( $callback, $assoc_args );

        if ( self::is_failure( $obj_id ) ) {
            $ctx->out->error( self::error_to_string( $obj_id ) );
        }

        $ctx->mark_changed();
        if ( Read_Value::flag( $assoc_args, 'porcelain' ) ) {
            $ctx->out->line( (string) $obj_id );
        } else {
            $ctx->out->success( "Created {$this->obj_type} {$obj_id}." );
        }
    }

    







    protected function _update( Invocation $inv, Context $ctx, array $args, array $assoc_args, callable $callback ): void {
        if ( empty( $assoc_args ) ) {
            $ctx->out->error( 'Need some fields to update.' );
        }
        $this->loop(
            $inv,
            $ctx,
            $args,
            $assoc_args,
            'update',
            function ( string $obj_id, Batch $batch ) use ( $assoc_args, $callback ): array {
                $params = array_merge( $assoc_args, array( $this->obj_id_key => $obj_id ) );
                $result = call_user_func( $callback, $params, $batch );
                return ( is_array( $result ) && ! self::is_failure( $result ) ) ? $result : $this->wp_error_to_resp( $result, "Updated {$this->obj_type} {$obj_id}." );
            }
        );
    }

    







    protected function _delete( Invocation $inv, Context $ctx, array $args, array $assoc_args, callable $callback ): void {
        $this->loop(
            $inv,
            $ctx,
            $args,
            $assoc_args,
            'delete',
            static function ( string $obj_id, Batch $batch ) use ( $assoc_args, $callback ) {
                return call_user_func( $callback, $obj_id, $assoc_args, $batch );
            }
        );
    }

    





    protected static function process_csv_arguments_to_arrays( array $assoc_args ): array {
        foreach ( $assoc_args as $k => $v ) {
            if ( false !== strpos( (string) $k, '__' ) ) {
                $assoc_args[ $k ] = explode( ',', (string) $v );
            }
        }
        return $assoc_args;
    }

    





    protected function wp_error_to_resp( $response, string $success_msg ): array {
        return self::is_failure( $response )
            ? array( 'error', self::failure_message( $response ) )
            : array( 'success', $success_msg );
    }

    





    protected function success_or_failure( Context $ctx, array $response ): int {
        return $this->respond( $ctx, $response );
    }

    




    protected function get_formatter( Context $ctx, array &$assoc_args ): Formatter {
        if ( ! empty( $assoc_args['fields'] ) ) {
            if ( is_string( $assoc_args['fields'] ) ) {
                $fields = explode( ',', $assoc_args['fields'] );
            } else {
                $fields = (array) $assoc_args['fields'];
            }
        } else {
            $fields = $this->obj_fields;
        }
        return new Formatter( $ctx->out, $assoc_args, $fields, $this->obj_type );
    }

    






    protected function _url( Context $ctx, array $args, Fetchers $fetcher, callable $callback ): void {
        foreach ( $args as $obj_id ) {
            $object = $fetcher->get_check( $obj_id, $ctx->out );
            $key    = $this->obj_id_key;
            $ctx->out->line( (string) call_user_func( $callback, is_object( $object ) ? $object->$key : $object[ $key ] ) );
        }
    }

    







    public static function error_to_string( $errors ): string {
        if ( is_string( $errors ) ) {
            return $errors;
        }
        if ( is_array( $errors ) && isset( $errors['error'] ) ) {
            return (string) $errors['error'];
        }

        
        $render_data = static function ( $data ) {
            if ( is_array( $data ) || is_object( $data ) ) {
                return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the exact call WP-CLI makes.
            }
            return '"' . $data . '"';
        };

        if ( $errors instanceof \WP_Error ) {
            foreach ( $errors->get_error_messages() as $message ) {
                if ( $errors->get_error_data() ) {
                    return $message . ' ' . $render_data( $errors->get_error_data() );
                }
                return $message;
            }
            return '';
        }

        if ( $errors instanceof \Throwable ) {
            return get_class( $errors ) . ': ' . $errors->getMessage();
        }

        return '';
    }

    





    public static function failure( string $message ) {
        if ( class_exists( '\\WP_Error' ) ) {
            return new \WP_Error( 'wp_cli_run', $message );
        }
        return array( 'error' => $message );
    }

    





    public static function missing( string $message ): array {
        return array( 'missing', $message );
    }

    




    public static function is_failure( $value ): bool {
        return ( function_exists( 'is_wp_error' ) && \is_wp_error( $value ) ) || $value instanceof \WP_Error || ( is_array( $value ) && isset( $value['error'] ) && 1 === count( $value ) );
    }

    
    
    

    







    private function loop( Invocation $inv, Context $ctx, array $args, array $assoc_args, string $verb, callable $each ): void {
        $deferring = self::start_deferral( $inv, $ctx, $assoc_args );

        $status = 0;
        $batch  = new Batch( $inv, $ctx, $this->obj_type, $verb );
        try {
            $batch->run(
                array_values( $args ),
                function ( string $obj_id, Batch $batch ) use ( $ctx, $each, &$status ): string {
                    $status = $this->respond( $ctx, (array) call_user_func( $each, (string) $obj_id, $batch ) );
                    return 0 === $status ? Batch::SUCCESS : Batch::FAILURE;
                }
            );
        } finally {
            if ( $deferring ) {
                \wp_defer_term_counting( false );
            }
        }

        if ( 0 !== $status ) {
            $ctx->out->halt( $status );
        }
    }

    







    private static function start_deferral( Invocation $inv, Context $ctx, array $assoc_args ): bool {
        $defer = (bool) Read_Value::flag( $assoc_args, 'defer-term-counting' );
        $own   = isset( $inv->route['route'] ) && 'own' === $inv->route['route'];
        if ( $defer && ! $own ) {
            $tools = isset( $inv->route['tools'] ) ? implode( ', ', (array) $inv->route['tools'] ) : 'its tool';
            $ctx->note( sprintf( self::DEFER_NOTE, $tools ) );
        }
        $deferring = $defer && $own && function_exists( 'wp_defer_term_counting' );
        if ( $deferring ) {
            \wp_defer_term_counting( true );
        }
        return $deferring;
    }

    




    private function respond( Context $ctx, array $response ): int {
        $type = isset( $response[0] ) ? (string) $response[0] : 'error';
        $msg  = isset( $response[1] ) ? self::error_to_string( $response[1] ) : '';
        if ( 'success' === $type ) {
            $ctx->out->success( $msg );
            if ( ! array_key_exists( 2, $response ) || false !== $response[2] ) {
                $ctx->mark_changed();
            }
            return 0;
        }
        $ctx->out->warning( $msg );
        if ( 'missing' === $type ) {
            $ctx->fail_missing_target( $msg );
        }
        return 1;
    }

    





    private static function failure_message( $failure ): string {
        if ( $failure instanceof \WP_Error ) {
            return (string) $failure->get_error_message();
        }
        return is_array( $failure ) && isset( $failure['error'] ) ? (string) $failure['error'] : '';
    }
}
