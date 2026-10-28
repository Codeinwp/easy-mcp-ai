<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Option_Handler extends Command_Handler {

    const NOT_MULTISITE = 'This is not a multisite installation.';

    const ROW_CAP = 1000;

    const MASK = '[REDACTED]';

    const OMITTED       = 'Self-protection: wp_cli_run left out %d site %s it never prints: %s.';
    const OTHER_NETWORK = 'wp_cli_run lists only the network options of the network this MCP connection belongs to (network %1$d); --site_id=%2$s names another network, whose super admins may differ.';
    const OVER_ROW_CAP  = "wp_cli_run lists at most %1\$d site options in one 'site option list', and %2\$d match. Narrow it with --search (wildcards * and ?), or ask for --format=count or --format=total_bytes.";
    const NOTE_FALSE    = "WP-CLI exits 1 without output here: the site option '%s' does not exist (or holds false).";
    const NOTE_NO_PATH  = "WP-CLI exits 1 without output here: the site option '%s' has no value at that key path.";

    const NOT_CAPTURED = 'Network options are recorded by change history only while its meta capture setting is on (the default), and a deleted network option is recorded without its previous value.';

    public function preflight( Invocation $inv, Context $ctx ): void {
        if ( ! $this->is_multisite() ) {
            $ctx->out->error( self::NOT_MULTISITE );
        }
    }

    public function get( Invocation $inv, Context $ctx ): void {
        list( $key ) = array_values( $inv->args );
        $this->guard( (string) $key, false, $ctx );

        $value = $this->get_site_option( (string) $key );

        if ( false === $value ) {
            $ctx->note( sprintf( self::NOTE_FALSE, $key ) );
            $ctx->out->halt( 1 );
        }
        if ( self::key_is_secret( (string) $key ) ) {
            $value = self::MASK;
            $ctx->redacted( "value of site option '{$key}'" );
        }

        Values::print_value( $ctx->out, $value, $inv->assoc );
    }

    public function add( Invocation $inv, Context $ctx ): void {
        $args = $inv->args;
        $key  = (string) $args[0];
        $this->guard( $key, true, $ctx );

        $value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 1 );
        $value = Read_Value::read( $ctx->out, $value, $inv->assoc );

        $ctx->not_captured( self::NOT_CAPTURED );
        if ( ! $this->add_site_option( $key, $value ) ) {
            $ctx->out->error( "Could not add site option '{$key}'. Does it already exist?" );
        } else {
            $ctx->mark_changed();
            $ctx->out->success( "Added '{$key}' site option." );
        }
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $args = $inv->args;
        $key  = (string) $args[0];
        $this->guard( $key, true, $ctx );

        $value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 1 );
        $value = Read_Value::read( $ctx->out, $value, $inv->assoc );

        $value     = $this->sanitize_option( $key, $value );
        $old_value = $this->sanitize_option( $key, $this->get_site_option( $key ) );

        $ctx->not_captured( self::NOT_CAPTURED );
        if ( $value === $old_value ) {
            $ctx->out->success( "Value passed for '{$key}' site option is unchanged." );
        } elseif ( $this->update_site_option( $key, $value ) ) {
            $ctx->mark_changed();
            $ctx->out->success( "Updated '{$key}' site option." );
        } else {
            $ctx->out->error( "Could not update site option '{$key}'." );
        }
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        list( $key ) = array_values( $inv->args );
        $key         = (string) $key;
        $this->guard( $key, true, $ctx );

        $ctx->not_captured( self::NOT_CAPTURED );
        if ( ! $this->delete_site_option( $key ) ) {
            $ctx->fail_missing_target( "Could not delete '{$key}' site option. Does it exist?" );
            $ctx->out->error( "Could not delete '{$key}' site option. Does it exist?" );
        } else {
            $ctx->mark_changed();
            $ctx->out->success( "Deleted '{$key}' site option." );
        }
    }

    public function pluck( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        $key  = (string) $args[0];
        $this->guard( $key, false, $ctx );

        $value = $this->get_site_option( $key );

        if ( false === $value ) {
            $ctx->note( sprintf( self::NOTE_FALSE, $key ) );
            $ctx->out->halt( 1 );
        }

        $key_path = Meta_Command::key_path( array_slice( $args, 1 ) );

        try {
            $value = Meta_Command::pluck_value( $value, $key_path );
        } catch ( \OutOfBoundsException $exception ) {
            $ctx->note( sprintf( self::NOTE_NO_PATH, $key ) );
            $ctx->out->halt( 1 );
        }
        if ( self::key_is_secret( $key ) ) {
            $value = self::MASK;
            $ctx->redacted( "value of site option '{$key}'" );
        }

        Values::print_value( $ctx->out, $value, $inv->assoc );
    }

    public function patch( Invocation $inv, Context $ctx ): void {
        $args   = array_values( $inv->args );
        $action = (string) $args[0];
        $key    = (string) $args[1];
        $this->guard( $key, true, $ctx );
        $key_path = Meta_Command::key_path( array_slice( $args, 2 ) );

        if ( 'delete' === $action ) {
            $patch_value = null;
        } else {
            if ( count( $key_path ) < 2 ) {
                $ctx->out->error( Meta_Command::PATCH_NO_VALUE );
            }
            $patch_value = Read_Value::read( $ctx->out, array_pop( $key_path ), $inv->assoc );
        }

        $old_value     = $this->sanitize_option( $key, $this->get_site_option( $key ) );
        $current_value = $old_value;
        if ( is_object( $current_value ) ) {
            $old_value = clone $current_value;
        }

        try {
            $patched = Meta_Command::patch_value( $action, $current_value, $key_path, $patch_value );
        } catch ( \RuntimeException $exception ) {
            $ctx->out->error( $exception->getMessage() );
            return;
        }

        $patched_value = $this->sanitize_option( $key, $patched );

        $ctx->not_captured( self::NOT_CAPTURED );
        if ( $patched_value === $old_value ) {
            $ctx->out->success( "Value passed for '{$key}' site option is unchanged." );
        } elseif ( $this->update_site_option( $key, $patched_value ) ) {
            $ctx->mark_changed();
            $ctx->out->success( "Updated '{$key}' site option." );
        } else {
            $ctx->out->error( "Could not update site option '{$key}'." );
        }
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $pattern    = isset( $assoc_args['search'] ) ? $this->search_pattern( (string) $assoc_args['search'] ) : '%';
        $fields     = isset( $assoc_args['fields'] ) ? explode( ',', (string) $assoc_args['fields'] ) : array( 'meta_key', 'meta_value' );

        $format      = Read_Value::flag( $assoc_args, 'format' );
        $total_bytes = 'total_bytes' === $format;
        if ( $total_bytes ) {
            $fields = array( 'size_bytes' );
        }

        $network = $this->current_network_id();
        $this->refuse_other_network( $assoc_args, $network, $ctx );

        $results = $this->sitemeta_rows( $pattern, $network );
        $prints  = ! $total_bytes && 'count' !== $format && in_array( 'meta_value', array_map( 'trim', $fields ), true );
        $kept    = $this->printable_rows( $results, $prints, $ctx );

        if ( $total_bytes ) {
            self::print_total_bytes( $kept, $ctx->out );
            return;
        }

        if ( 'count' !== $format && count( $kept ) > self::ROW_CAP ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, count( $kept ) ) );
        }

        $formatter = new Formatter( $ctx->out, $assoc_args, $fields );
        $formatter->display_items( $kept );
    }

    private function search_pattern( string $search ): string {
        $pattern = $this->esc_like( $search );
        $pattern = str_replace( '*', '%', $pattern );
        return str_replace( '?', '_', $pattern );
    }

    private function refuse_other_network( array $assoc_args, int $network, Context $ctx ): void {
        $site_id = Read_Value::flag( $assoc_args, 'site_id' );
        if ( $site_id && (int) $site_id !== $network ) {
            $ctx->out->error( sprintf( self::OTHER_NETWORK, $network, is_scalar( $site_id ) ? (string) $site_id : '' ) );
        }
    }

    private function printable_rows( array $results, bool $prints, Context $ctx ): array {
        $kept    = array();
        $omitted = array();
        foreach ( $results as $row ) {
            if ( null !== Self_Protection::option( (string) $row->meta_key, false ) ) {
                $omitted[] = (string) $row->meta_key;
                continue;
            }
            if ( self::key_is_secret( (string) $row->meta_key ) ) {
                $row->meta_value = self::MASK; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an output field.
                if ( $prints ) {
                    $ctx->redacted( "meta_value of site option '{$row->meta_key}'" );
                }
            }
            $kept[] = $row;
        }
        if ( $omitted ) {
            $omitted = array_values( array_unique( $omitted ) );
            $ctx->out->warning( sprintf( self::OMITTED, count( $omitted ), 1 === count( $omitted ) ? 'option' : 'options', Self_Protection::omitted_names_text( $omitted ) ) );
        }
        return $kept;
    }

    private static function print_total_bytes( array $kept, Output $out ): void {
        $sum = null;
        foreach ( $kept as $row ) {
            $sum = (int) $sum + (int) $row->size_bytes;
        }
        $out->line( null === $sum ? '' : (string) $sum );
    }

    public function describe( Invocation $inv ): array {
        $sub  = (string) $inv->leaf['name'];
        $args = array_values( $inv->args );
        if ( 'patch' === $sub ) {
            return $this->describe_patch( $args );
        }
        $key     = isset( $args[0] ) ? (string) $args[0] : '';
        $current = $this->preview( $key, $this->get_site_option( $key ), true );
        if ( 'delete' === $sub ) {
            return array( sprintf( "Site option '%s': %s → delete", $key, $current ) );
        }
        $new = isset( $args[1] ) ? $args[1] : '';
        if ( 'json' === Read_Value::flag( $inv->assoc, 'format' ) ) {
            $decoded = json_decode( (string) $new, true );
            $new     = null === $decoded ? $new : $decoded;
        }
        return array( sprintf( "Site option '%s': %s → %s%s", $key, $current, 'add' === $sub ? 'add ' : '', $this->preview( $key, $new ) ) );
    }

    private function describe_patch( array $args ): array {
        $key    = isset( $args[1] ) ? (string) $args[1] : '';
        $path   = array_slice( $args, 2 );
        $value  = 'delete' === $args[0] ? null : array_pop( $path );
        $effect = null === $value ? 'delete ' . implode( '.', $path ) : $args[0] . ' ' . implode( '.', $path ) . ' = ' . $this->preview( $key, $value );
        return array( sprintf( "Site option '%s': %s → %s", $key, $this->preview( $key, $this->get_site_option( $key ), true ), $effect ) );
    }

    private function guard( string $key, bool $write, Context $ctx ): void {
        $refusal = Self_Protection::option( $key, $write );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
    }

    private function preview( string $key, $value, bool $stored = false ): string {
        if ( self::key_is_secret( $key ) ) {
            return self::MASK;
        }
        if ( $stored && false === $value ) {
            return '(not set)';
        }
        $text = is_string( $value ) ? "'" . $value . "'" : (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        return strlen( $text ) > 120 ? \Easy_MCP_AI\Cli_Router\Envelope::cut( $text, 117 ) . '...' : $text;
    }

    public static function key_is_secret( string $key ): bool {
        return Option_Handler::key_is_secret( $key );
    }

    protected function is_multisite(): bool {
        return function_exists( 'is_multisite' ) && \is_multisite();
    }

    protected function current_network_id(): int {
        return function_exists( 'get_current_network_id' ) ? (int) \get_current_network_id() : 1;
    }

    protected function get_site_option( string $key ) {
        return \get_site_option( $key );
    }

    protected function add_site_option( string $key, $value ): bool {
        return (bool) \add_site_option( $key, $value );
    }

    protected function update_site_option( string $key, $value ): bool {
        return (bool) \update_site_option( $key, $value );
    }

    protected function delete_site_option( string $key ): bool {
        return (bool) \delete_site_option( $key );
    }

    protected function sanitize_option( string $key, $value ) {
        return \sanitize_option( $key, $value );
    }

    protected function esc_like( string $text ): string {
        global $wpdb;
        return (string) $wpdb->esc_like( $text );
    }

    protected function sitemeta_rows( string $pattern, int $network ): array {
        global $wpdb;
        $query  = $wpdb->prepare(
            'SELECT `meta_id`, `site_id`, `meta_key`,`meta_value`,LENGTH(meta_value) AS `size_bytes`' // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the table name is WordPress's own.
                . " FROM `$wpdb->sitemeta` WHERE `meta_key` LIKE %s",
            $pattern
        );
        $query .= $wpdb->prepare( ' AND site_id=%d', $network );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
        return array_values( (array) $wpdb->get_results( $query ) );
    }
}
