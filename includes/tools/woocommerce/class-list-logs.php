<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Logs extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_logs';
    }

    public function get_description() {
        return 'List WooCommerce\'s own logs (WooCommerce › Status › Logs): fatal errors, payment gateway, webhook and extension logs. The PHP error log is wp_get_error_log. Read a log with wp_wc_get_log, by its `source` or `file_id`. Optional: `source` (prefix, e.g. "stripe"), `limit` (default 50, max 200). Returns { storage ("file", "database", or "other" for a custom handler that cannot be read), handler, logging_enabled, level_threshold, retention_days, files: [{ file_id, source, rotation, date, size_bytes, modified }] newest first (database storage: sources: [{ source, entries, oldest, newest }]), total, returned }. Requires manage_woocommerce.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_woocommerce';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'source' => array(
                    'type'        => 'string',
                    'description' => 'Only logs whose source starts with this text (case-insensitive), e.g. "fatal-errors" or "stripe".',
                ),
                'limit'  => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 200,
                    'default'     => 50,
                    'description' => 'How many files (or database sources) to return, 1-200.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        WC_Logs::require_woocommerce();

        $prefix = isset( $arguments['source'] ) && is_scalar( $arguments['source'] ) ? trim( (string) $arguments['source'] ) : '';
        $limit  = isset( $arguments['limit'] ) ? max( 1, min( 200, (int) $arguments['limit'] ) ) : 50;

        $storage = WC_Logs::storage();
        $result  = array_merge( $storage, WC_Logs::settings() );

        if ( WC_Logs::STORAGE_DATABASE === $storage['storage'] ) {
            $found = WC_Logs::db_sources( $prefix, $limit );
            return array_merge( $result, array(
                'sources'  => $found['sources'],
                'total'    => $found['total'],
                'returned' => count( $found['sources'] ),
            ) );
        }

        if ( WC_Logs::STORAGE_OTHER === $storage['storage'] ) {
            return self::empty_listing( $result, 'WooCommerce writes its logs with ' . $storage['handler'] . ', a custom log handler; these tools read only WooCommerce\'s file and database handlers.' );
        }

        $dir = WC_Logs::directory();
        if ( null === $dir ) {
            return self::empty_listing( $result, 'WooCommerce\'s log folder does not exist: nothing has been logged to a file on this site yet.' );
        }
        WC_Logs::require_site_scope( $dir );

        $files = WC_Logs::files( $dir );
        if ( '' !== $prefix ) {
            $files = array_values( array_filter( $files, static function ( array $file ) use ( $prefix ): bool {
                return 0 === stripos( $file['source'], $prefix );
            } ) );
        }
        $page = array_map( array( WC_Logs::class, 'public_file' ), array_slice( $files, 0, $limit ) );

        return array_merge( $result, array(
            'files'    => $page,
            'total'    => count( $files ),
            'returned' => count( $page ),
        ) );
    }

    private static function empty_listing( array $result, string $reason ): array {
        return array_merge( $result, array(
            'files'    => array(),
            'total'    => 0,
            'returned' => 0,
            'reason'   => $reason,
        ) );
    }
}
