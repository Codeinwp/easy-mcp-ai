<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Handlers\Db_Handler;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Tabular;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










































class Search_Replace_Adapter extends Command_Handler {

    const TOOL = 'wp_privileged_search_replace_run';

    
    const PLUGIN_MARK = 'easy_mcp_ai_';

    
    const LOG_PREFIXES = array( '< ', '> ' );

    const LOG_FILE        = "wp_cli_run refuses --log=%s: it writes a file on the server. Use --log alone to print the log in the output.";
    const REGEX_ONLY      = 'wp_cli_run refuses --%s without --regex: WP-CLI would ignore it. Add --regex, or leave it out.';
    const LOG_ONLY        = 'wp_cli_run refuses --%s without --log: WP-CLI would ignore it. Add --log, or leave it out.';
    const CONTEXT_NUMBER  = 'wp_cli_run refuses --%s=%s: it must be a whole number of characters, and WP-CLI would silently use 40.';
    const NO_TABLE        = 'wp_cli_run found no table to search once --skip-tables and the plugin\'s own tables were left out; nothing was run.';
    const OWN_TABLES      = 'Self-protection: wp_cli_run left out %d %s that belong to the plugin that serves this MCP connection; WP-CLI would have searched %s too.';
    const BACKGROUND      = 'The search-replace continues in the background as job %1$s: %2$d of %3$d tables done, %4$d %5$s so far. WP-CLI has no command to follow it: call %6$s with {"job_id": "%1$s"} to read its progress and final report.';
    const STOPPED         = 'The search-replace job %1$s stopped (%2$s): %3$s';
    const LOG_MORE        = '%d more changed %s not in the log: the search-replace tool keeps the first 200 entries (16 KB).';
    const CHECKED_MORE    = 'The report leaves out %d searched %s: the search-replace tool names at most 1,000.';
    const REPORT_MORE     = 'The report shows 0 for %d changed %s past the 200 the search-replace tool counts one by one; the total above is exact.';

    
    const SKIPPED = array(
        'protected'          => 'Self-protection: %1$d %2$s of the plugin that serves this MCP connection (its settings, and the search-replace job record itself) matched and %3$s left unchanged.',
        'partial'            => '%1$d %2$s kept a match inside an object the search-replace tool does not open (a class other than stdClass, or any object with --no-recurse-objects); WP-CLI would have replaced %4$s there: %5$s.',
        'too_long'           => '%1$d %2$s %3$s left unchanged because the replacement would not fit the column: %5$s.',
        'primary_key_column' => '%1$d primary-key %6$s %7$s not searched, because rewriting a key moves the row: %5$s.',
    );

    
    
    

    
    public function preflight_run( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        self::check_regex_limit( $assoc, $ctx );
        if ( ! empty( Read_Value::flag( $assoc, 'regex', false ) ) ) {
            self::check_regex( (string) $inv->args[0], $assoc, $ctx );
        } else {
            self::refuse_regex_only( $assoc, $ctx );
        }
        self::check_log( $assoc, $ctx );
    }

    




    private static function check_regex_limit( array $assoc, Context $ctx ): void {
        $regex_limit = Read_Value::flag( $assoc, 'regex-limit' );
        if ( null !== $regex_limit && ( ! preg_match( '/^(?:[0-9]+|-1)$/', (string) $regex_limit ) || 0 === (int) $regex_limit ) ) {
            $ctx->out->error( '`--regex-limit` expects a non-zero positive integer or -1.' );
        }
    }

    





    private static function check_regex( string $old, array $assoc, Context $ctx ): void {
        $regex_delimiter = Read_Value::flag( $assoc, 'regex-delimiter', '' );
        $default_delim   = '' === $regex_delimiter;
        if ( $default_delim ) {
            $regex_delimiter = chr( 1 );
        }
        $regex_flags  = Read_Value::flag( $assoc, 'regex-flags', false );
        $search_regex = $regex_delimiter . $old . $regex_delimiter . ( is_string( $regex_flags ) ? $regex_flags : '' );
        error_clear_last();
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- WP-CLI's own test of the pattern.
        if ( false !== @preg_match( $search_regex, '' ) ) {
            return;
        }
        $error              = error_get_last();
        $preg_error_message = ( ! empty( $error ) && array_key_exists( 'message', $error ) ) ? "\n{$error['message']}." : '';
        if ( $default_delim ) {
            $flags_msg = $regex_flags ? "flags '$regex_flags'" : 'no flags';
            $msg       = "The regex pattern '$old' with default delimiter 'chr(1)' and {$flags_msg} fails.";
        } else {
            $msg = "The regex '$search_regex' fails.";
        }
        $ctx->out->error( $msg . $preg_error_message );
    }

    





    private static function refuse_regex_only( array $assoc, Context $ctx ): void {
        foreach ( array( 'regex-flags', 'regex-delimiter', 'regex-limit' ) as $key ) {
            if ( array_key_exists( $key, $assoc ) ) {
                $ctx->out->error( sprintf( self::REGEX_ONLY, $key ) );
            }
        }
    }

    





    private static function check_log( array $assoc, Context $ctx ): void {
        $log = Read_Value::flag( $assoc, 'log' );
        if ( null !== $log && true !== $log && '-' !== $log ) {
            $ctx->out->error( sprintf( self::LOG_FILE, (string) $log ) );
        }
        foreach ( array( 'before_context', 'after_context' ) as $key ) {
            if ( ! array_key_exists( $key, $assoc ) ) {
                continue;
            }
            if ( null === $log ) {
                $ctx->out->error( sprintf( self::LOG_ONLY, $key ) );
            }
            if ( ! preg_match( '/^[0-9]+$/', (string) $assoc[ $key ] ) ) {
                $ctx->out->error( sprintf( self::CONTEXT_NUMBER, $key, (string) $assoc[ $key ] ) );
            }
        }
    }

    
    
    

    








    public function run( Invocation $inv, Context $ctx ): void {
        $assoc   = $inv->assoc;
        $args    = array_values( array_map( 'strval', $inv->args ) );
        $old     = (string) array_shift( $args );
        $new     = (string) array_shift( $args );
        $dry_run = (bool) Read_Value::flag( $assoc, 'dry-run' );

        if ( $old === $new && ! Read_Value::flag( $assoc, 'regex', false ) ) {
            $ctx->out->warning( "Replacement value '{$old}' is identical to search value '{$new}'. Skipping operation." );
            return;
        }

        Fetchers::require_tool( $ctx, $inv, self::TOOL );

        $scope  = $this->table_list( $args, $assoc, $ctx );
        $result = $ctx->delegate( self::TOOL, self::arguments( $old, $new, $dry_run, array_values( array_diff( $scope['tables'], $scope['views'] ) ), $assoc ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $summary = $result['payload'];
        if ( ! $this->report_job( $summary, $dry_run, $ctx ) ) {
            return;
        }

        
        if ( $ctx->out->is_quiet() ) {
            return;
        }
        $this->report_result( $scope, $summary, $dry_run, $assoc, $ctx );
    }

    
    
    

    








    private function report_job( array $summary, bool $dry_run, Context $ctx ): bool {
        $job_id = (string) ( $summary['job_id'] ?? '' );
        $status = (string) ( $summary['status'] ?? '' );
        $cells  = (int) ( $summary['totals']['cells'] ?? 0 );
        $ctx->note( sprintf( 'search-replace job %s (%s)', $job_id, self::TOOL ) );

        if ( ! $dry_run && ( $cells > 0 || 'running' === $status ) ) {
            $ctx->mark_changed();
        }
        self::print_log( $summary, $ctx );
        $this->skipped_warnings( (array) ( $summary['skipped'] ?? array() ), $ctx );
        return self::report_finished( $summary, $job_id, $status, $cells, $ctx );
    }

    








    private static function report_finished( array $summary, string $job_id, string $status, int $cells, Context $ctx ): bool {
        if ( 'running' === $status ) {
            $ctx->out->warning( sprintf( self::BACKGROUND, $job_id, (int) ( $summary['progress']['tables_done'] ?? 0 ), (int) ( $summary['progress']['tables_total'] ?? 0 ), $cells, 1 === $cells ? 'replacement' : 'replacements', self::TOOL ) );
            return false;
        }
        if ( 'completed' !== $status ) {
            $ctx->out->error( sprintf( self::STOPPED, $job_id, $status, (string) ( $summary['stop_reason'] ?? $summary['next'] ?? '' ) ) );
        }
        return true;
    }

    





    private static function print_log( array $summary, Context $ctx ): void {
        foreach ( (array) ( $summary['log'] ?? array() ) as $entry ) {
            $ctx->out->out( sprintf( "%s\n%s%s\n%s%s\n", (string) $entry['row'], self::LOG_PREFIXES[0], (string) $entry['old'], self::LOG_PREFIXES[1], (string) $entry['new'] ) );
        }
        if ( ! empty( $summary['log_more'] ) ) {
            $more = (int) $summary['log_more'];
            $ctx->out->warning( sprintf( self::LOG_MORE, $more, 1 === $more ? 'cell is' : 'cells are' ) );
        }
    }

    








    private function report_result( array $scope, array $summary, bool $dry_run, array $assoc, Context $ctx ): void {
        $cells = (int) ( $summary['totals']['cells'] ?? 0 );
        if ( 'count' === Read_Value::flag( $assoc, 'format' ) ) {
            $ctx->out->line( (string) $cells );
            return;
        }

        $report_on    = Read_Value::flag( $assoc, 'report', true );
        $changed_only = Read_Value::flag( $assoc, 'report-changed-only', null !== Read_Value::flag( $assoc, 'log' ) );
        $rows         = $this->report_rows( $scope['tables'], $scope['views'], $summary, (bool) $report_on, (bool) $changed_only, $ctx );
        if ( $report_on && ! empty( $rows ) ) {
            $ctx->out->out( Tabular::render( array( 'Table', 'Column', 'Replacements', 'Type' ), $rows ) );
        }
        $this->print_success( $cells, $dry_run, $ctx );
    }

    
    private function print_success( int $cells, bool $dry_run, Context $ctx ): void {
        if ( $dry_run ) {
            $ctx->out->success( sprintf( 1 === $cells ? '%d replacement to be made.' : '%d replacements to be made.', $cells ) );
            return;
        }
        $message = 1 === $cells ? 'Made 1 replacement.' : "Made $cells replacements.";
        if ( $cells && $this->ext_object_cache() ) {
            $message .= ' Please remember to flush your persistent object cache with `wp cache flush`.';
        }
        $ctx->out->success( $message );
    }

    








    private function table_list( array $args, array $assoc, Context $ctx ): array {
        $helper = $this->tables_helper();
        $all    = $helper->table_names( $args, self::scope_flags( $assoc ), $ctx->out );

        list( $list, $own ) = self::without_skipped_and_own( $all, explode( ',', (string) Read_Value::flag( $assoc, 'skip-tables', '' ) ) );
        if ( $own ) {
            $ctx->out->warning( sprintf( self::OWN_TABLES, $own, 1 === $own ? 'table' : 'tables', 1 === $own ? 'it' : 'them' ) );
        }
        $views = $list ? array_values( array_intersect( $list, $helper->view_names() ) ) : array();
        if ( count( $views ) === count( $list ) ) {
            $ctx->out->error( self::NO_TABLE );
        }
        return array(
            'tables' => $list,
            'views'  => $views,
        );
    }

    





    private static function scope_flags( array $assoc ): array {
        $scope = array();
        foreach ( array( 'network', 'all-tables-with-prefix', 'all-tables' ) as $key ) {
            if ( array_key_exists( $key, $assoc ) ) {
                $scope[ $key ] = $assoc[ $key ];
            }
        }
        return $scope;
    }

    








    private static function without_skipped_and_own( array $all, array $skip ): array {
        $list = array();
        $own  = 0;
        foreach ( $all as $table ) {
            foreach ( $skip as $skip_table ) {
                if ( fnmatch( $skip_table, $table ) ) {
                    continue 2;
                }
            }
            if ( false !== strpos( $table, self::PLUGIN_MARK ) ) {
                ++$own;
                continue;
            }
            $list[] = $table;
        }
        return array( $list, $own );
    }

    






    public static function arguments( string $old, string $new, bool $dry_run, array $tables, array $assoc ): array {
        $skip_columns    = self::names( $assoc, 'skip-columns' );
        $include_columns = self::names( $assoc, 'include-columns' );
        $arguments       = array(
            'old'          => $old,
            'new'          => $new,
            'dry_run'      => $dry_run,
            'tables'       => array_map(
                static function ( $table ) {
                    return addcslashes( (string) $table, '\\*?[' );
                },
                $tables
            ),
            'all_tables'   => true,
            'include_guid' => ! in_array( 'guid', $skip_columns, true ),
            'verbose'      => true,
        );
        if ( $skip_columns ) {
            $arguments['skip_columns'] = $skip_columns;
        }
        if ( $include_columns ) {
            $arguments['include_columns'] = $include_columns;
        }
        if ( Read_Value::flag( $assoc, 'regex', false ) ) {
            $arguments += self::regex_arguments( $assoc );
        }
        if ( false === Read_Value::flag( $assoc, 'recurse-objects', true ) ) {
            $arguments['recurse_objects'] = false;
        }
        if ( null !== Read_Value::flag( $assoc, 'log' ) ) {
            $arguments += self::log_arguments( $assoc );
        }
        return $arguments;
    }

    






    private static function regex_arguments( array $assoc ): array {
        $arguments = array( 'regex' => true );
        $flags     = Read_Value::flag( $assoc, 'regex-flags', false );
        if ( is_string( $flags ) && '' !== $flags ) {
            $arguments['regex_flags'] = $flags;
        }
        $delimiter = Read_Value::flag( $assoc, 'regex-delimiter', '' );
        if ( is_string( $delimiter ) && '' !== $delimiter ) {
            $arguments['regex_delimiter'] = $delimiter;
        }
        $limit = Read_Value::flag( $assoc, 'regex-limit' );
        if ( null !== $limit ) {
            $arguments['regex_limit'] = (int) $limit;
        }
        return $arguments;
    }

    






    private static function log_arguments( array $assoc ): array {
        $arguments = array( 'log' => true );
        foreach ( array( 'before_context', 'after_context' ) as $key ) {
            if ( isset( $assoc[ $key ] ) ) {
                $arguments[ $key ] = (int) $assoc[ $key ];
            }
        }
        return $arguments;
    }

    











    private function report_rows( array $tables, array $views, array $summary, bool $report_on, bool $changed_only, Context $ctx ): array {
        $cells   = self::cells_by_column( $summary );
        $checked = self::checked_columns( $tables, $summary );
        $no_key  = array_merge( $views, (array) ( $summary['skipped']['no_primary_key']['items'] ?? array() ) );

        $rows = array();
        foreach ( $tables as $table ) {
            if ( in_array( $table, $no_key, true ) ) {
                $rows = array_merge( $rows, self::no_key_rows( $table, $report_on, $changed_only, $ctx ) );
                continue;
            }
            foreach ( $checked[ $table ] ?? array() as $column ) {
                $count = $cells[ $table . "\0" . $column ] ?? 0;
                if ( $report_on && ( $count || ! $changed_only ) ) {
                    $rows[] = array( $table, $column, (string) $count, 'PHP' );
                }
            }
        }
        self::report_more_warnings( $summary, $ctx );
        return $rows;
    }

    





    private static function cells_by_column( array $summary ): array {
        $cells = array();
        foreach ( (array) ( $summary['report'] ?? array() ) as $row ) {
            $cells[ (string) $row['table'] . "\0" . (string) $row['column'] ] = (int) $row['cells'];
        }
        return $cells;
    }

    






    private static function checked_columns( array $tables, array $summary ): array {
        $checked = array();
        foreach ( (array) ( $summary['checked'] ?? array() ) as $item ) {
            $item = (string) $item;
            foreach ( $tables as $table ) {
                if ( 0 === strpos( $item, $table . '.' ) ) {
                    $checked[ $table ][] = (string) substr( $item, strlen( $table ) + 1 );
                    break;
                }
            }
        }
        return $checked;
    }

    






    private static function no_key_rows( string $table, bool $report_on, bool $changed_only, Context $ctx ): array {
        if ( $changed_only ) {
            return array();
        }
        if ( $report_on ) {
            return array( array( $table, '', 'skipped', '' ) );
        }
        $ctx->out->warning( "No primary keys for table '$table'." );
        return array();
    }

    





    private static function report_more_warnings( array $summary, Context $ctx ): void {
        $more = (int) ( $summary['checked_more'] ?? 0 );
        if ( $more ) {
            $ctx->out->warning( sprintf( self::CHECKED_MORE, $more, 1 === $more ? 'column' : 'columns' ) );
        }
        $report_more = (int) ( $summary['report_more'] ?? 0 );
        if ( $report_more ) {
            $ctx->out->warning( sprintf( self::REPORT_MORE, $report_more, 1 === $report_more ? 'column' : 'columns' ) );
        }
    }

    





    private function skipped_warnings( array $skipped, Context $ctx ): void {
        foreach ( $skipped as $bucket => $list ) {
            $count = (int) ( $list['count'] ?? 0 );
            if ( ! $count || in_array( $bucket, array( 'no_primary_key', 'network_tables' ), true ) ) {
                continue;
            }
            $items = implode( ', ', array_map( 'strval', (array) ( $list['items'] ?? array() ) ) );
            $ctx->out->warning( self::skipped_text( (string) $bucket, $count, $items ) );
        }
    }

    
    private static function skipped_text( string $bucket, int $count, string $items ): string {
        if ( ! isset( self::SKIPPED[ $bucket ] ) ) {
            return sprintf( 'The search-replace tool left out %d (%s): %s.', $count, $bucket, $items );
        }
        $one = 1 === $count;
        return sprintf(
            self::SKIPPED[ $bucket ],
            $count,
            ( 'protected' === $bucket ? 'row' : 'cell' ) . ( $one ? '' : 's' ),
            $one ? 'was' : 'were',
            $one ? 'it' : 'them',
            $items,
            $one ? 'column' : 'columns',
            $one ? 'was' : 'were'
        );
    }

    







    private static function names( array $assoc, string $flag ): array {
        $value = Read_Value::flag( $assoc, $flag, '' );
        if ( ! is_string( $value ) || '' === $value ) {
            return array();
        }
        return array_values( array_filter( explode( ',', $value ), 'strlen' ) );
    }

    
    protected function tables_helper(): Db_Handler {
        return new Db_Handler();
    }

    







    protected function call( string $name, array $args, callable $real ) {
        return $real();
    }

    
    protected function ext_object_cache(): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return ! empty( $GLOBALS['_wp_using_ext_object_cache'] );
            }
        );
    }
}
