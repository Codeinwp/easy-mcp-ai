<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































































abstract class Meta_Command extends Command_Handler {

    
    protected $meta_type = '';

    
    protected $ctx = null;

    
    protected $inv = null;

    
    private $batch = null;

    const MASK = '[REDACTED]';

    
    const KEY_BYTES = 'wp_cli_run refuses to write a meta key containing a control character or a non-ASCII byte, or ending in a space: the meta tables compare keys without regard to accents, control characters or trailing spaces, so such a key can address another key\'s rows. Pass the key in plain ASCII, as `%s` prints it.';

    const DELETE_EMPTY_VALUE = "wp_cli_run refuses an empty <value>: WordPress cannot delete only the rows that hold an empty string, and WP-CLI would delete every row of '%s'. Omit <value> to delete every row of the key.";

    const DELETE_ALL_WITH_KEY = 'No need to specify a meta key while deleting all custom fields.';

    const PATCH_NO_VALUE = 'Please provide value to update.';

    const OMITTED = 'Self-protection: wp_cli_run left out %d meta %s it never reads or writes: %s.';

    
    const ROW_CAP = 1000;

    const OVER_ROW_CAP = "wp_cli_run lists at most %1\$d meta rows in one '%2\$s', and %3\$d match. Narrow it with --keys=<keys>, or ask for --format=count.";

    const NOTE_EMPTY = "WP-CLI exits 1 without output here: '%s' has no value to print.";

    const NOTE_NO_PATH = "WP-CLI exits 1 without output here: '%s' has no value at that key path.";

    
    const PREVIEW_DEPTH = 64;

    
    
    

    






    abstract protected function check_object_id( $object_id, string $subcommand );

    






    abstract protected function get_metadata( $object_id, string $meta_key = '', bool $single = false );

    





    abstract protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool;

    





    abstract protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool;

    





    abstract protected function delete_metadata( $object_id, string $meta_key, string $meta_value = '' ): bool;

    





    protected function resolve_args( array $args, string $subcommand ): array {
        return $args;
    }

    




    protected function protect_key( string $key, bool $write ): ?string {
        if ( $write && self::unsafe_key( $key ) ) {
            return sprintf( self::KEY_BYTES, $this->list_command() );
        }
        return null;
    }

    



    protected function key_is_secret( string $key ): bool {
        return Handlers\Option_Handler::key_is_secret( $key );
    }

    





    protected function sanitize_meta( string $key, $value ) {
        return function_exists( 'sanitize_meta' ) ? \sanitize_meta( $key, $value, $this->meta_type ) : $value;
    }

    





    protected function meta_rows( $object_id, string $meta_key ): array {
        throw new \LogicException( static::class . ' has no clean-duplicates backend.' );
    }

    
    protected function delete_meta_by_mid( int $meta_id ): bool {
        throw new \LogicException( static::class . ' has no clean-duplicates backend.' );
    }

    





    protected function delegate( string $tool, array $arguments ): array {
        return null !== $this->batch ? $this->batch->delegate( $tool, $arguments ) : $this->ctx->delegate( $tool, $arguments );
    }

    
    
    

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $assoc_args = $inv->assoc;
        $args       = $this->resolve_args( $inv->args, 'list' );
        $object_id  = isset( $args[0] ) ? $args[0] : '';

        $keys   = ! empty( $assoc_args['keys'] ) ? explode( ',', (string) $assoc_args['keys'] ) : array();
        $fields = $this->list_fields( $assoc_args );
        
        $prints_value = 'count' !== Read_Value::flag( $assoc_args, 'format' ) && in_array( 'meta_value', array_map( 'trim', $fields ), true );

        $object_id = $this->check_object_id( $object_id, 'list' );

        list( $items, $omitted, $redacted ) = $this->list_items( $object_id, $keys, (bool) Read_Value::flag( $assoc_args, 'unserialize' ), $prints_value );
        
        if ( count( $items ) > self::ROW_CAP && 'count' !== Read_Value::flag( $assoc_args, 'format' ) ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, $inv->command, count( $items ) ) );
        }
        foreach ( $redacted as $field ) {
            $ctx->redacted( $field );
        }
        $this->warn_omitted( $omitted );

        self::order_items( $items, $assoc_args );

        $formatter = new Formatter( $ctx->out, $assoc_args, $fields, $this->meta_type );
        $formatter->display_items( $items );
    }

    





    private function list_fields( array $assoc_args ): array {
        if ( ! empty( $assoc_args['fields'] ) ) {
            return explode( ',', (string) $assoc_args['fields'] );
        }
        return array( "{$this->meta_type}_id", 'meta_key', 'meta_value' );
    }

    










    private function list_items( $object_id, array $keys, bool $unserialize, bool $prints_value ): array {
        $metadata = $this->get_metadata( $object_id );
        if ( ! $metadata || ! is_array( $metadata ) ) {
            $metadata = array();
        }

        $items    = array();
        $omitted  = array();
        $redacted = array();
        foreach ( $metadata as $key => $values ) {

            
            if ( ! empty( $keys ) && ! in_array( $key, $keys, true ) ) {
                continue;
            }

            if ( null !== $this->protect_key( (string) $key, false ) ) {
                $omitted[] = (string) $key;
                continue;
            }
            $secret = $this->key_is_secret( (string) $key );

            foreach ( (array) $values as $item_value ) {
                $items[] = $this->list_item( $object_id, $key, $item_value, $unserialize, $secret );
                if ( $secret && $prints_value ) {
                    $redacted[] = "meta_value of '{$key}'";
                }
            }
        }
        return array( $items, $omitted, $redacted );
    }

    







    private function list_item( $object_id, $key, $item_value, bool $unserialize, bool $secret ): object {
        if ( $unserialize ) {
            $item_value = self::unserialize( $item_value );
        }
        if ( $secret ) {
            $item_value = self::MASK;
        }

        return (object) array(
            "{$this->meta_type}_id" => $object_id,
            'meta_key'              => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- an output field, not a query.
            'meta_value'            => $item_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an output field, not a query.
        );
    }

    






    private static function order_items( array &$items, array $assoc_args ): void {
        $order   = Read_Value::flag( $assoc_args, 'order' );
        $orderby = Read_Value::flag( $assoc_args, 'orderby' );

        if ( 'id' !== $orderby ) {
            self::sort_items( $items, (string) $orderby, (string) $order );
        } elseif ( 'id' === $orderby && 'desc' === $order ) { 
            krsort( $items );
        }
    }

    
    public function get( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args      = $this->resolve_args( $inv->args, 'get' );
        $object_id = isset( $args[0] ) ? $args[0] : '';
        $meta_key  = isset( $args[1] ) ? (string) $args[1] : '';
        $this->guard_key( $meta_key, false );

        $object_id = $this->check_object_id( $object_id, 'get' );
        $single    = Read_Value::flag( $inv->assoc, 'single', true );

        $value = $this->get_metadata( $object_id, $meta_key, (bool) $single );

        if ( '' === $value ) {
            $ctx->note( sprintf( self::NOTE_EMPTY, $meta_key ) );
            $ctx->out->halt( 1 );
        }
        if ( $this->key_is_secret( $meta_key ) ) {
            $value = self::MASK;
            $ctx->redacted( "meta_value of '{$meta_key}'" );
        }

        Values::print_value( $ctx->out, $value, $inv->assoc );
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args       = $this->resolve_args( $inv->args, 'delete' );
        $object_id  = isset( $args[0] ) ? $args[0] : '';
        $all        = (bool) Read_Value::flag( $inv->assoc, 'all' );
        $meta_key   = ! empty( $args[1] ) ? (string) $args[1] : '';
        $meta_value = self::delete_value( $args );

        $this->check_delete_args( $args, $meta_key, $all );
        if ( ! $all ) {
            $this->guard_key( $meta_key, true );
        }

        $object_id = $this->check_object_id( $object_id, 'delete' );

        if ( $all ) {
            $this->delete_all( $inv, $ctx, $object_id );
            return;
        }

        $success = $this->delete_metadata( $object_id, $meta_key, $meta_value );
        if ( $success ) {
            $ctx->mark_changed();
            $ctx->out->success( 'Deleted custom field.' );
        } else {
            $ctx->out->error( 'Failed to delete custom field.' );
        }
    }

    





    private static function delete_value( array $args ): string {
        $meta_value = ! empty( $args[2] ) ? (string) $args[2] : '';
        
        if ( isset( $args[2] ) && '0' === (string) $args[2] ) {
            $meta_value = '0';
        }
        return $meta_value;
    }

    






    private function check_delete_args( array $args, string $meta_key, bool $all ): void {
        if ( empty( $meta_key ) && ! $all ) {
            $this->ctx->out->error( 'Please specify a meta key, or use the --all flag.' );
        }
        if ( $all && '' !== $meta_key ) {
            $this->ctx->out->error( self::DELETE_ALL_WITH_KEY );
        }
        if ( ! $all && isset( $args[2] ) && '' === (string) $args[2] ) {
            $this->ctx->out->error( sprintf( self::DELETE_EMPTY_VALUE, $meta_key ) );
        }
    }

    
    public function add( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args      = $this->resolve_args( $inv->args, 'add' );
        $object_id = isset( $args[0] ) ? $args[0] : '';
        $meta_key  = isset( $args[1] ) ? (string) $args[1] : '';
        $this->guard_key( $meta_key, true );

        $meta_value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 2 );
        $meta_value = Read_Value::read( $ctx->out, $meta_value, $inv->assoc );

        $object_id = $this->check_object_id( $object_id, 'add' );

        $meta_value = self::slash( $meta_value );
        $success    = $this->add_metadata( $object_id, $meta_key, $meta_value );

        if ( $success ) {
            $ctx->mark_changed();
            $ctx->out->success( 'Added custom field.' );
        } else {
            $ctx->out->error( 'Failed to add custom field.' );
        }
    }

    
    public function update( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args      = $this->resolve_args( $inv->args, 'update' );
        $object_id = isset( $args[0] ) ? $args[0] : '';
        $meta_key  = isset( $args[1] ) ? (string) $args[1] : '';
        $this->guard_key( $meta_key, true );

        $meta_value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 2 );
        $meta_value = Read_Value::read( $ctx->out, $meta_value, $inv->assoc );

        $object_id = $this->check_object_id( $object_id, 'update' );

        $meta_value = $this->sanitize_meta( $meta_key, $meta_value );
        $old_value  = $this->sanitize_meta( $meta_key, $this->get_metadata( $object_id, $meta_key, true ) );

        if ( $meta_value === $old_value ) {
            $ctx->out->success( "Value passed for custom field '{$meta_key}' is unchanged." );
        } else {
            $meta_value = self::slash( $meta_value );
            $success    = $this->update_metadata( $object_id, $meta_key, $meta_value );

            if ( $success ) {
                $ctx->mark_changed();
                $ctx->out->success( "Updated custom field '{$meta_key}'." );
            } else {
                $ctx->out->error( "Failed to update custom field '{$meta_key}'." );
            }
        }
    }

    
    public function pluck( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args      = $this->resolve_args( $inv->args, 'pluck' );
        $object_id = isset( $args[0] ) ? $args[0] : '';
        $meta_key  = isset( $args[1] ) ? (string) $args[1] : '';
        $this->guard_key( $meta_key, false );

        $object_id = $this->check_object_id( $object_id, 'pluck' );
        $key_path  = self::key_path( array_slice( array_values( $args ), 2 ) );

        $value = $this->get_metadata( $object_id, $meta_key, true );

        try {
            $value = self::pluck_value( $value, $key_path );
        } catch ( \OutOfBoundsException $exception ) {
            $ctx->note( sprintf( self::NOTE_NO_PATH, $meta_key ) );
            $ctx->out->halt( 1 );
        }
        if ( $this->key_is_secret( $meta_key ) ) {
            $value = self::MASK;
            $ctx->redacted( "meta_value of '{$meta_key}'" );
        }

        Values::print_value( $ctx->out, $value, $inv->assoc );
    }

    
    public function patch( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args      = array_values( $this->resolve_args( $inv->args, 'patch' ) );
        $action    = isset( $args[0] ) ? (string) $args[0] : '';
        $object_id = isset( $args[1] ) ? $args[1] : '';
        $meta_key  = isset( $args[2] ) ? (string) $args[2] : '';
        $this->guard_key( $meta_key, true );

        $object_id = $this->check_object_id( $object_id, 'patch' );
        $key_path  = self::key_path( array_slice( $args, 3 ) );

        if ( 'delete' === $action ) {
            $patch_value = null;
        } else {
            
            
            
            if ( count( $key_path ) < 2 ) {
                $ctx->out->error( self::PATCH_NO_VALUE );
            }
            $patch_value = Read_Value::read( $ctx->out, array_pop( $key_path ), $inv->assoc );
        }

        
        $current_meta_value = $this->sanitize_meta( $meta_key, $this->get_metadata( $object_id, $meta_key, true ) );
        $old_meta_value     = $current_meta_value;
        if ( is_object( $current_meta_value ) ) {
            $old_meta_value = clone $current_meta_value;
        }

        try {
            $patched = self::patch_value( $action, $current_meta_value, $key_path, $patch_value );
        } catch ( \RuntimeException $exception ) {
            $ctx->out->error( $exception->getMessage() );
            return;
        }

        $patched_meta_value = $this->sanitize_meta( $meta_key, $patched );

        if ( $patched_meta_value === $old_meta_value ) {
            $ctx->out->success( "Value passed for custom field '{$meta_key}' is unchanged." );
        } else {
            $slashed = self::slash( $patched_meta_value );
            $success = $this->update_metadata( $object_id, $meta_key, $slashed );

            if ( $success ) {
                $ctx->mark_changed();
                $ctx->out->success( "Updated custom field '{$meta_key}'." );
            } else {
                $ctx->out->error( "Failed to update custom field '{$meta_key}'." );
            }
        }
    }

    
    public function clean_duplicates( Invocation $inv, Context $ctx ): void {
        $this->begin( $inv, $ctx );
        $args    = $inv->args;
        $post_id = isset( $args[0] ) ? $args[0] : '';
        $key     = isset( $args[1] ) ? (string) $args[1] : '';
        $this->guard_key( $key, true );

        $metas = $this->meta_rows( $post_id, $key );

        if ( empty( $metas ) ) {
            $ctx->out->error( sprintf( 'No meta values found for \'%s\'.', $key ) );
        }

        list( $uniq_metas, $dupe_metas ) = self::split_duplicates( $metas );

        if ( count( $dupe_metas ) ) {
            
            Confirm::ask(
                $inv,
                $ctx,
                sprintf(
                    'Are you sure you want to delete %d duplicate meta values and keep %d valid meta value?',
                    count( $dupe_metas ),
                    count( $uniq_metas )
                ),
                false
            );
            foreach ( $dupe_metas as $meta_id ) {
                if ( $this->delete_meta_by_mid( $meta_id ) ) {
                    $ctx->mark_changed();
                }
                $ctx->out->log( sprintf( 'Deleted meta id %d.', $meta_id ) );
            }
            $ctx->out->success( sprintf( 'Cleaned up duplicate \'%s\' meta values.', $key ) );
        } else {
            $ctx->out->success(
                sprintf(
                    'Nothing to clean up: found %d valid meta value and %d duplicates.',
                    count( $uniq_metas ),
                    count( $dupe_metas )
                )
            );
        }
    }

    
    
    

    





    public function describe( Invocation $inv ): array {
        $this->begin(
            $inv,
            new Context(
                new Output(),
                static function (): ?array {
                    return null;
                },
                static function (): bool {
                    return false;
                }
            )
        );
        $sub   = isset( $inv->leaf['name'] ) ? (string) $inv->leaf['name'] : '';
        $args  = array_values( $this->resolve_args( $inv->args, $sub ) );
        $label = self::object_label( $this->meta_type );

        if ( 'clean-duplicates' === $sub ) {
            return $this->describe_clean_duplicates( $args, $label );
        }
        if ( 'patch' === $sub ) {
            return $this->describe_patch( $args, $label );
        }

        $id  = $this->check_object_id( isset( $args[0] ) ? $args[0] : '', $sub );
        $key = isset( $args[1] ) ? (string) $args[1] : '';

        if ( 'delete' === $sub && Read_Value::flag( $inv->assoc, 'all' ) ) {
            return $this->describe_delete_all( $id, $label );
        }

        $rows = $this->get_metadata( $id, $key, false );
        $rows = is_array( $rows ) ? $rows : array();
        if ( 'delete' === $sub ) {
            return $this->describe_delete( $args, $id, $key, $rows, $label );
        }
        return $this->describe_write( $inv, $sub, $args, $id, $key, $rows, $label );
    }

    








    private function describe_delete( array $args, $id, string $key, array $rows, string $label ): array {
        $value  = isset( $args[2] ) ? (string) $args[2] : '';
        $effect = '' === $value ? 'delete every value' : 'delete the values equal to ' . $this->preview( $key, $value );
        return array( sprintf( "%s %s meta '%s': %s → %s", $label, $id, $key, self::count_values( $rows ), $effect ) );
    }

    







    private function describe_clean_duplicates( array $args, string $label ): array {
        $id                   = isset( $args[0] ) ? $args[0] : '';
        $key                  = isset( $args[1] ) ? (string) $args[1] : '';
        list( $uniq, $dupes ) = self::split_duplicates( $this->meta_rows( $id, $key ) );
        sort( $dupes );
        return array( sprintf( "%s %s meta '%s': %d rows, %d duplicates → delete meta ids %s", $label, $id, $key, count( $uniq ) + count( $dupes ), count( $dupes ), $dupes ? implode( ', ', $dupes ) : 'none' ) );
    }

    







    private function describe_patch( array $args, string $label ): array {
        $action  = isset( $args[0] ) ? $args[0] : '';
        $id      = $this->check_object_id( isset( $args[1] ) ? $args[1] : '', 'patch' );
        $key     = isset( $args[2] ) ? (string) $args[2] : '';
        $path    = array_slice( $args, 3 );
        $value   = 'delete' === $action ? null : array_pop( $path );
        $current = $this->get_metadata( $id, $key, true );
        $shown   = null === $value ? '' : ( $this->path_is_secret( $path ) ? self::MASK : $this->preview( $key, $value ) );
        $effect  = null === $value ? 'delete ' . implode( '.', $path ) : $action . ' ' . implode( '.', $path ) . ' = ' . $shown;
        return array( sprintf( "%s %s meta '%s': %s → %s", $label, $id, $key, $this->preview( $key, $current, true ), $effect ) );
    }

    





    private function describe_delete_all( $id, string $label ): array {
        $all = $this->get_metadata( $id );
        $all = is_array( $all ) ? $all : array();
        if ( ! $all ) {
            return array( sprintf( '%s %s: no meta → nothing to delete', $label, $id ) );
        }
        ksort( $all, SORT_STRING );
        $lines = array();
        foreach ( $all as $meta_key => $values ) {
            $lines[] = sprintf( "%s %s meta '%s': %s → delete", $label, $id, $meta_key, self::count_values( (array) $values ) );
        }
        return $lines;
    }

    








    private function describe_write( Invocation $inv, string $sub, array $args, $id, string $key, array $rows, string $label ): array {
        $new = isset( $args[2] ) ? $args[2] : '';
        if ( 'json' === Read_Value::flag( $inv->assoc, 'format' ) ) {
            $decoded = json_decode( (string) $new, true );
            $new     = null === $decoded ? $new : $decoded;
        }
        if ( 'add' === $sub ) {
            return array( sprintf( "%s %s meta '%s': %s → add %s", $label, $id, $key, self::count_values( $rows ), $this->preview( $key, $new ) ) );
        }
        $current = $rows ? $this->preview( $key, self::unserialize( reset( $rows ) ), true ) : '(not set)';
        return array( sprintf( "%s %s meta '%s': %s → %s", $label, $id, $key, $current, $this->preview( $key, $new ) ) );
    }

    
    
    
    

    



    public static function unsafe_key( string $key ): bool {
        return 1 !== preg_match( '/^[\x20-\x7E]*\z/', $key ) || ( '' !== $key && ' ' === substr( $key, -1 ) );
    }

    






    public static function key_path( array $words ): array {
        return array_values(
            array_map(
                static function ( $key ) {
                    if ( is_numeric( $key ) && ( (string) intval( $key ) === $key ) ) {
                        return (int) $key;
                    }
                    return $key;
                },
                $words
            )
        );
    }

    







    public static function pluck_value( $data, array $key_path ) {
        $found = self::locate( $data, $key_path );
        if ( ! $found[0] ) {
            throw new \OutOfBoundsException( "No data exists for key \"{$found[2]}\"" );
        }
        return $found[1];
    }

    
















    public static function patch_value( string $action, $data, array $key_path, $value ) {
        if ( 'update' === $action ) {
            self::set( $data, $key_path, $value );
        } elseif ( 'insert' === $action ) {
            self::insert( $data, $key_path, $value );
        } elseif ( 'delete' === $action ) {
            self::remove( $data, $key_path );
        } else {
            throw new \UnexpectedValueException( "Unknown patch action \"{$action}\"" );
        }
        return $data;
    }

    
    
    

    
    private function begin( Invocation $inv, Context $ctx ): void {
        $this->inv = $inv;
        $this->ctx = $ctx;
    }

    




    private function guard_key( string $key, bool $write ): void {
        $refusal = $this->protect_key( $key, $write );
        if ( null !== $refusal ) {
            $this->ctx->out->error( $refusal );
        }
    }

    




    private function delete_all( Invocation $inv, Context $ctx, $object_id ): void {
        $metadata = $this->get_metadata( $object_id );
        $keys     = array();
        $omitted  = array();
        foreach ( is_array( $metadata ) ? $metadata : array() as $meta_key => $values ) {
            if ( null !== $this->protect_key( (string) $meta_key, true ) ) {
                $omitted[] = (string) $meta_key;
                continue;
            }
            $keys[] = $meta_key;
        }
        $this->warn_omitted( $omitted );

        $errors      = false;
        $this->batch = new Batch( $inv, $ctx, 'custom field', 'delete' );
        try {
            $this->batch->run(
                $keys,
                function ( $meta_key ) use ( $ctx, $object_id, &$errors ): string {
                    $success = $this->delete_metadata( $object_id, (string) $meta_key );
                    if ( $success ) {
                        $ctx->mark_changed();
                        $ctx->out->log( "Deleted '{$meta_key}' custom field." );
                        return Batch::SUCCESS;
                    }
                    $ctx->out->warning( "Failed to delete '{$meta_key}' custom field." );
                    $errors = true;
                    return Batch::FAILURE;
                },
                static function ( $meta_key ): string {
                    return "meta key '{$meta_key}'";
                }
            );
        } finally {
            $this->batch = null;
        }
        if ( $errors ) {
            $ctx->out->error( 'Failed to delete all custom fields.' );
        } else {
            $ctx->out->success( 'Deleted all custom fields.' );
        }
    }

    






    private function warn_omitted( array $omitted ): void {
        if ( ! $omitted ) {
            return;
        }
        $omitted = array_values( array_unique( $omitted ) );
        $this->ctx->out->warning( sprintf( self::OMITTED, count( $omitted ), 1 === count( $omitted ) ? 'key' : 'keys', Self_Protection::omitted_names_text( $omitted ) ) );
    }

    
    private function list_command(): string {
        $command = null !== $this->inv ? $this->inv->command : $this->meta_type . ' meta x';
        $words   = explode( ' ', $command );
        array_pop( $words );
        $words[] = 'list';
        return implode( ' ', $words );
    }

    








    private static function sort_items( array &$items, string $orderby, string $order ): void {
        if ( PHP_VERSION_ID >= 80000 ) {
            usort(
                $items,
                static function ( object $a, object $b ) use ( $orderby, $order ): int {
                    if ( 'asc' === $order ) {
                        return $a->$orderby > $b->$orderby ? 1 : ( $b->$orderby > $a->$orderby ? -1 : 0 );
                    }
                    return $a->$orderby < $b->$orderby ? 1 : ( $b->$orderby < $a->$orderby ? -1 : 0 );
                }
            );
            return;
        }
        usort(
            $items,
            static function ( object $a, object $b ) use ( $orderby, $order ): bool {
                
                return 'asc' === $order
                    ? $a->$orderby > $b->$orderby
                    : $a->$orderby < $b->$orderby;
            }
        );
    }

    





    private static function split_duplicates( array $metas ): array {
        $uniq_metas = array();
        $dupe_metas = array();
        foreach ( $metas as $meta ) {
            if ( ! isset( $uniq_metas[ $meta->meta_value ] ) ) {
                $uniq_metas[ $meta->meta_value ] = (int) $meta->meta_id;
            } else {
                $dupe_metas[] = (int) $meta->meta_id;
            }
        }
        return array( $uniq_metas, $dupe_metas );
    }

    







    private static function locate( &$data, array $key_path ): array {
        $current = array_shift( $key_path );
        $result  = array( true, null, null );
        if ( null === $current ) {
            $result[1] = &$data;
            return $result;
        }
        if ( self::exists( $data, $current ) ) {
            foreach ( $data as $key => &$key_data ) {
                if ( $key === $current ) {
                    return self::locate( $key_data, $key_path );
                }
            }
            unset( $key_data );
            
            
            
            
            return array( false, null, $current, 'unmatched' );
        }
        $result    = array( false, null, $current );
        $result[1] = &$data;
        return $result;
    }

    





    private static function exists( $data, $key ): bool {
        return ( is_array( $data ) && array_key_exists( $key, $data ) ) ||
            ( is_object( $data ) && property_exists( $data, (string) $key ) );
    }

    







    private static function set( &$data, array $key_path, $value ): void {
        $found = self::locate( $data, $key_path );
        if ( ! $found[0] ) {
            throw new \OutOfBoundsException( "No data exists for key \"{$found[2]}\"" );
        }
        $found[1] = $value;
    }

    







    private static function insert( &$data, array $key_path, $value ): void {
        
        for ( $pass = 0; $pass <= count( $key_path ); $pass++ ) {
            $found = self::locate( $data, $key_path );
            if ( $found[0] ) {
                $found[1] = $value;
                return;
            }
            if ( isset( $found[3] ) ) {
                throw new \OutOfBoundsException( "No data exists for key \"{$found[2]}\"" );
            }
            $key = $found[2];
            if ( is_array( $found[1] ) ) {
                $found[1][ $key ] = null;
            } elseif ( is_object( $found[1] ) ) {
                $found[1]->{$key} = null;
            } else {
                $type = gettype( $found[1] );
                throw new \UnexpectedValueException( "Cannot create key \"{$key}\" on data type {$type}" );
            }
        }
        throw new \OutOfBoundsException( 'No data exists for key "' . (string) end( $key_path ) . '"' );
    }

    






    private static function remove( &$data, array $key_path ): void {
        $found = self::locate( $data, $key_path );
        if ( ! $found[0] || ! $key_path ) {
            throw new \OutOfBoundsException( "No data exists for key \"{$found[2]}\"" );
        }
        $last   = array_pop( $key_path );
        $parent = self::locate( $data, $key_path );
        foreach ( $parent[1] as $key => $unused ) {
            if ( $key === $last ) {
                if ( is_array( $parent[1] ) ) {
                    unset( $parent[1][ $key ] );
                } else {
                    unset( $parent[1]->$key );
                }
                return;
            }
        }
    }

    






    private static function slash( $value ) {
        return function_exists( 'wp_slash' ) ? \wp_slash( $value ) : $value;
    }

    





    private static function unserialize( $value ) {
        return function_exists( 'maybe_unserialize' ) ? \maybe_unserialize( $value ) : $value;
    }

    




    private static function count_values( array $values ): string {
        $n = count( $values );
        return 1 === $n ? '1 value' : $n . ' values';
    }

    









    private function preview( string $key, $value, bool $stored = false ): string {
        if ( $this->key_is_secret( $key ) ) {
            return self::MASK;
        }
        if ( $stored && '' === $value ) {
            return '(not set)';
        }
        if ( is_string( $value ) && $this->json_has_secret_key( $value ) ) {
            return self::MASK;
        }
        $value = $this->mask_secret_keys( $value, 0 );
        $text  = is_string( $value ) ? "'" . $value . "'" : (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        if ( class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) ) {
            $text = \Easy_MCP_AI\Text_Redactor::redact( $text );
        }
        return strlen( $text ) > 120 ? Envelope::cut( $text, 117 ) . '...' : $text;
    }

    








    private function mask_secret_keys( $value, int $depth ) {
        if ( is_string( $value ) ) {
            return $this->json_has_secret_key( $value ) ? self::MASK : $value;
        }
        if ( ! is_array( $value ) && ! is_object( $value ) ) {
            return $value;
        }
        if ( $depth >= self::PREVIEW_DEPTH ) {
            return self::MASK;
        }
        if ( $value instanceof \JsonSerializable ) {
            return $this->mask_secret_keys( $value->jsonSerialize(), $depth + 1 );
        }
        return $this->mask_entries( $value, $depth );
    }

    






    private function mask_entries( $value, int $depth ) {
        $entries = is_array( $value ) ? $value : get_object_vars( $value );
        $masked  = array();
        foreach ( $entries as $name => $entry ) {
            $masked[ $name ] = is_string( $name ) && $this->key_is_secret( $name ) ? self::MASK : $this->mask_secret_keys( $entry, $depth + 1 );
        }
        return is_array( $value ) ? $masked : (object) $masked;
    }

    
    private function json_has_secret_key( string $value ): bool {
        if ( '' === $value || ( '{' !== $value[0] && '[' !== $value[0] ) ) {
            return false;
        }
        $decoded = json_decode( $value, true );
        return is_array( $decoded ) && $this->mask_secret_keys( $decoded, 0 ) !== $decoded;
    }

    




    private function path_is_secret( array $path ): bool {
        foreach ( $path as $element ) {
            if ( is_string( $element ) && $this->key_is_secret( $element ) ) {
                return true;
            }
        }
        return false;
    }

    
    private static function object_label( string $meta_type ): string {
        $labels = array(
            'post'    => 'Post',
            'user'    => 'User',
            'term'    => 'Term',
            'comment' => 'Comment',
            'blog'    => 'Site',
            'site'    => 'Network',
        );
        return isset( $labels[ $meta_type ] ) ? $labels[ $meta_type ] : ucfirst( $meta_type );
    }
}
