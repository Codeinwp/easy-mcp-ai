<?php
namespace Easy_MCP_AI\Privileged\Search_Replace;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Runner {

    const SLICE_SECONDS  = 20;
    const BATCH          = 100;
    const NAMED_MAX      = 20;
    const SAMPLES_MAX    = 20;
    const SAMPLE_CONTEXT = 40;
    const REPORT_MAX     = 200;
    const LOCK_TTL       = 120;
    const SAVE_EVERY = 2;
    const STALLED_AFTER = 300;
    const LOG_MAX   = 200;
    const LOG_BYTES = 16384;
    const CHECKED_MAX = 1000;
    const RESULT_BUDGET = 60000;

    const PLUGIN_MARK          = 'easy_mcp_ai_';
    const PLUGIN_LIKE          = '%easy\\_mcp\\_ai\\_%';
    const SITE_ADDRESS_OPTIONS = array( 'siteurl', 'home' );
    const NAME_COLUMNS         = array( 'option_name', 'meta_key' );

    private $db;
    private $store;
    private $clock;
    private $on_finish;
    private $flush_cache;
    private $reverse = null;

    public function __construct( Database $db, Job_Store $store, ?callable $clock = null, ?callable $on_finish = null, ?callable $flush_cache = null ) {
        $this->db          = $db;
        $this->store       = $store;
        $this->clock       = $clock ? $clock : static function () {
            return microtime( true );
        };
        $this->on_finish   = $on_finish;
        $this->flush_cache = $flush_cache;
    }

    public static function resolve_tables( array $all, array $args, array $site ): array {
        $prefix      = (string) $site['prefix'];
        $base        = (string) $site['base_prefix'];
        $multisite   = ! empty( $site['multisite'] );
        $base_site   = $multisite && $prefix === $base;
        $globals     = array_map( 'strval', (array) ( $site['global_tables'] ?? array() ) );
        $network_ok  = ! empty( $site['network_user'] );
        $all_tables  = ! empty( $args['all_tables'] );
        $network     = ! empty( $args['network'] );
        $named       = (array) ( $args['tables'] ?? array() );
        $other_site  = '/^' . preg_quote( $base, '/' ) . '\d+_/';

        if ( $network && ! $multisite ) {
            throw new \InvalidArgumentException( '`network` applies only to a multisite network; this is a single site.' );
        }
        $ours       = array();
        $candidates = array();
        foreach ( $all as $table ) {
            if ( false !== strpos( $table, self::PLUGIN_MARK ) ) {
                $ours[] = $table;
            } else {
                $candidates[] = $table;
            }
        }

        if ( $all_tables ) {
            $scope = $candidates;
        } elseif ( $network ) {
            $scope = array_values( array_filter( $candidates, static function ( $t ) use ( $base ) {
                return 0 === strpos( $t, $base );
            } ) );
        } else {
            $scope = array_values( array_filter( $candidates, static function ( $t ) use ( $prefix, $base_site, $other_site ) {
                return 0 === strpos( $t, $prefix ) && ! ( $base_site && preg_match( $other_site, $t ) );
            } ) );
        }

        if ( $named ) {
            $picked = array();
            foreach ( $named as $pattern ) {
                $pattern = (string) $pattern;
                foreach ( $ours as $table ) {
                    if ( fnmatch( $pattern, $table ) ) {
                        throw new \InvalidArgumentException( sprintf( 'The table %s belongs to Easy MCP AI and is never changed by a search-replace.', $table ) );
                    }
                }
                $matched = array_values( array_filter( $scope, static function ( $t ) use ( $pattern ) {
                    return fnmatch( $pattern, $t );
                } ) );
                if ( ! $matched ) {
                    throw new \InvalidArgumentException( sprintf( 'No table in scope matches "%s". Other sites\' tables need network (multisite, super admin); tables without this site\'s prefix need all_tables.', $pattern ) );
                }
                $picked = array_merge( $picked, $matched );
            }
            $scope = array_values( array_filter( $scope, static function ( $t ) use ( $picked ) {
                return in_array( $t, $picked, true );
            } ) );
        }

        foreach ( (array) ( $args['skip_tables'] ?? array() ) as $pattern ) {
            $scope = array_values( array_filter( $scope, static function ( $t ) use ( $pattern ) {
                return ! fnmatch( (string) $pattern, $t );
            } ) );
        }

        $foreign = array();
        if ( $multisite ) {
            foreach ( $scope as $table ) {
                $own = 0 === strpos( $table, $prefix ) && ! ( $base_site && ( preg_match( $other_site, $table ) || in_array( $table, $globals, true ) ) );
                if ( ! $own ) {
                    $foreign[] = $table;
                }
            }
        }
        $left_out = array();
        if ( $foreign && ! $network_ok ) {
            if ( $all_tables || $network || $named ) {
                throw new \InvalidArgumentException( sprintf( 'Changing %s needs a super admin with manage_network_options: it is not this site\'s own table.', $foreign[0] ) );
            }
            $left_out = $foreign;
            $scope    = array_values( array_diff( $scope, $foreign ) );
            $foreign  = array();
        }
        if ( ! $scope ) {
            throw new \InvalidArgumentException( 'No table is in scope.' );
        }
        return array(
            'tables'        => $scope,
            'left_out'      => $left_out,
            'needs_network' => (bool) $foreign,
        );
    }

    public function create( array $args, array $site, array $identity, string $args_hash ): array {
        $scope = self::resolve_tables( $this->db->base_tables(), $args, $site );
        $now   = (int) floor( ( $this->clock )() );
        $job   = array(
            'id'            => Job_Store::new_id(),
            'status'        => 'running',
            'dry_run'       => (bool) $args['dry_run'],
            'args'          => $args,
            'args_hash'     => $args_hash,
            'user_id'       => (int) ( $identity['user_id'] ?? 0 ),
            'blog_id'       => (int) ( $site['blog_id'] ?? 1 ),
            'identity'      => $identity,
            'needs_network' => $scope['needs_network'],
            'tables'        => $scope['tables'],
            'created'       => $now,
            'updated'       => $now,
            'heartbeat'     => $now,
            'finished'      => '',
            'stop_reason'   => '',
            'cursor'        => array(
                'phase'   => 'scan',
                't'       => 0,
                'table'   => null,
                'c'       => 0,
                'after'   => null,
                'started' => false,
            ),
            'report'        => array(),
            'totals'        => array( 'cells' => 0, 'occurrences' => 0 ),
            'new_present'   => 0,
            'skipped'       => array( 'network_tables' => self::named_list( $scope['left_out'] ) ),
            'site_address'  => array(),
            'samples'       => array(),
            'cache'         => array( 'flushed' => false ),
        );
        $this->store->save( $job );
        return $job;
    }

    public function run_slice( array $job, float $seconds = self::SLICE_SECONDS ): array {
        if ( 'running' !== $job['status'] ) {
            return $job;
        }
        if ( ! empty( $job['in_batch'] ) ) {
            self::add_named( $job, 'interrupted', (string) $job['in_batch'] );
            $job['in_batch'] = '';
        }
        $deadline  = ( $this->clock )() + $seconds;
        $last_save = ( $this->clock )();
        $options   = self::replacer_options( $job['args'] );
        $replacer  = new Replacer( (string) $job['args']['old'], (string) $job['args']['new'], $options );
        $this->reverse = ( '' === (string) $job['args']['new'] || $replacer->is_regex() )
            ? null
            : new Replacer( (string) $job['args']['new'], (string) $job['args']['old'], array( 'recurse_objects' => $options['recurse_objects'] ) );
        do {
            $job = $this->step( $job, $replacer );
            $job['heartbeat'] = (int) floor( ( $this->clock )() );
            $job['updated']   = $job['heartbeat'];
            if ( 'done' === $job['cursor']['phase'] ) {
                return $this->complete( $job );
            }
            if ( ( $this->clock )() - $last_save >= self::SAVE_EVERY ) {
                $this->store->save( $job );
                $last_save = ( $this->clock )();
            }
        } while ( ( $this->clock )() < $deadline );
        $this->store->save( $job );
        return $job;
    }

    public function stop( array $job, string $status, string $reason ): array {
        if ( 'running' !== $job['status'] ) {
            return $job;
        }
        $job['status']      = $status;
        $job['stop_reason'] = $reason;
        return $this->end( $job );
    }

    private function step( array $job, Replacer $replacer ): array {
        $phase = $job['cursor']['phase'];
        if ( 'site_address' === $phase ) {
            $job                    = $this->site_address( $job, $replacer );
            $job['cursor']['phase'] = 'done';
            return $job;
        }
        $t = (int) $job['cursor']['t'];
        if ( $t >= count( $job['tables'] ) ) {
            $job['cursor']['phase'] = 'site_address';
            return $job;
        }
        $name = $job['tables'][ $t ];
        if ( null === $job['cursor']['table'] ) {
            $table = $this->open_table( $job, $name );
            if ( null === $table ) {
                return self::next_table( $job );
            }
            $job['cursor']['table'] = $table;
            return $job;
        }
        $table = $job['cursor']['table'];
        $c     = (int) $job['cursor']['c'];
        if ( $c >= count( $table['columns'] ) ) {
            return self::next_table( $job );
        }
        $column = $table['columns'][ $c ];
        if ( ! $job['cursor']['started'] ) {
            $job                      = $this->open_column( $job, $name, $table, $column );
            $job['cursor']['started'] = true;
            return $job;
        }
        $from = $job['cursor']['after'];
        $rows = $this->db->fetch( $name, $column['name'], $table['pk'], $replacer->needles(), $from, self::BATCH, $table['scan'] );
        if ( count( $rows ) < self::BATCH ) {
            $job['cursor']['c']       = $c + 1;
            $job['cursor']['after']   = null;
            $job['cursor']['started'] = false;
        } else {
            $last                   = end( $rows );
            $job['cursor']['after'] = array_map( static function ( $key ) use ( $last ) {
                return (string) $last[ $key ];
            }, $table['pk'] );
        }
        if ( $rows && ! $job['dry_run'] ) {
            $first = reset( $rows );
            $last  = end( $rows );
            $ahead = $job;
            $ahead['in_batch'] = sprintf(
                '%s.%s %s to %s',
                $name,
                $column['name'],
                implode( ',', array_map( static function ( $key ) use ( $first ) {
                    return (string) $first[ $key ];
                }, $table['pk'] ) ),
                implode( ',', array_map( static function ( $key ) use ( $last ) {
                    return (string) $last[ $key ];
                }, $table['pk'] ) )
            );
            $this->store->save( $ahead );
        }
        foreach ( $rows as $row ) {
            $job = $this->process_row( $job, $replacer, $name, $table, $column, $row );
        }
        return $job;
    }

    private static function next_table( array $job ): array {
        $job['cursor']['t']       = (int) $job['cursor']['t'] + 1;
        $job['cursor']['table']   = null;
        $job['cursor']['c']       = 0;
        $job['cursor']['after']   = null;
        $job['cursor']['started'] = false;
        return $job;
    }

    private function open_table( array &$job, string $name ) {
        $pk = $this->db->primary_key( $name );
        if ( ! $pk ) {
            self::add_named( $job, 'no_primary_key', $name );
            return null;
        }
        $args     = $job['args'];
        $include  = array_map( 'strval', (array) ( $args['include_columns'] ?? array() ) );
        $skip     = array_map( 'strval', (array) ( $args['skip_columns'] ?? array() ) );
        $all_cols = $this->db->text_columns( $name );
        $names    = array_map( static function ( $c ) {
            return $c['name'];
        }, $all_cols );
        $columns  = array();
        foreach ( $all_cols as $column ) {
            $col = $column['name'];
            $ids = array( $col, $name . '.' . $col );
            if ( 'user_pass' === $col || array_intersect( $ids, $skip ) ) {
                continue;
            }
            if ( $include && ! array_intersect( $ids, $include ) ) {
                continue;
            }
            if ( 'guid' === $col && empty( $args['include_guid'] ) && ! array_intersect( $ids, $include ) ) {
                continue;
            }
            if ( in_array( $col, $pk, true ) ) {
                self::add_named( $job, 'primary_key_column', $name . '.' . $col );
                continue;
            }
            $columns[] = $column;
        }
        if ( ! $columns ) {
            return null;
        }
        $protect     = array();
        $name_column = '';
        foreach ( self::NAME_COLUMNS as $candidate ) {
            if ( in_array( $candidate, $names, true ) ) {
                $name_column = $candidate;
                $protect[]   = array( $candidate, 'not_like', self::PLUGIN_LIKE );
                break;
            }
        }
        $scan = $protect;
        if ( self::is_options_table( $name, $names ) ) {
            $scan[] = array( 'option_name', 'not_in', self::SITE_ADDRESS_OPTIONS );
        }
        return array(
            'pk'          => $pk,
            'columns'     => $columns,
            'scan'        => $scan,
            'protect'     => $protect,
            'name_column' => $name_column,
        );
    }

    private function open_column( array $job, string $name, array $table, array $column ): array {
        if ( ! empty( $job['args']['verbose'] ) ) {
            self::add_checked( $job, $name . '.' . $column['name'] );
        }
        if ( ! empty( $job['args']['regex'] ) ) {
            return $job;
        }
        $new = (string) $job['args']['new'];
        if ( '' !== $new ) {
            $new_json = Replacer::json_inner( $new );
            $needles  = $new_json === $new ? array( $new ) : array( $new, $new_json );
            $job['new_present'] += $this->db->count( $name, $column['name'], $needles, $table['protect'] );
        }
        if ( '' !== $table['name_column'] ) {
            $old      = (string) $job['args']['old'];
            $old_json = Replacer::json_inner( $old );
            $needles  = $old_json === $old ? array( $old ) : array( $old, $old_json );
            $names    = $this->db->column_values( $name, $table['name_column'], $column['name'], $needles, array( array( $table['name_column'], 'like', self::PLUGIN_LIKE ) ), self::NAMED_MAX );
            foreach ( $names as $row_name ) {
                self::add_named( $job, 'protected', $name . '.' . $row_name );
            }
        }
        return $job;
    }

    private function process_row( array $job, Replacer $replacer, string $name, array $table, array $column, array $row ): array {
        $col   = $column['name'];
        $value = $row[ $col ] ?? null;
        if ( null === $value ) {
            return $job;
        }
        $key = self::row_key( $table['pk'], $row );
        if ( '' === (string) $value && $replacer->is_regex() ) {
            return $job;
        }
        $label  = $name . '.' . $col . ' ' . implode( ',', $key );
        $result = $replacer->replace( (string) $value );
        if ( self::note_result( $job, $result, $label ) || $result['value'] === $value ) {
            return $job;
        }
        $refused = $this->write_cell( (bool) $job['dry_run'], $name, $column, $key, $result['value'] );
        if ( null !== $refused ) {
            self::add_named( $job, $refused, $label );
            return $job;
        }
        $cell = array(
            'table'  => $name,
            'column' => $col,
            'key'    => $key,
            'label'  => $label,
            'before' => (string) $value,
            'after'  => $result['value'],
        );
        return $this->record_change( $job, $replacer, $cell, $result, true );
    }

    private static function row_key( array $pk, array $row ): array {
        $key = array();
        foreach ( $pk as $column ) {
            $key[ $column ] = (string) $row[ $column ];
        }
        return $key;
    }

    private static function note_result( array &$job, array $result, string $label ): bool {
        if ( in_array( $result['status'], array( 'corrupt', 'too_deep', 'unsafe_object', 'regex_error' ), true ) ) {
            self::add_named( $job, $result['status'], $label );
            return true;
        }
        if ( 'partial' === $result['status'] ) {
            self::add_named( $job, 'partial', $label . ' (' . implode( ', ', $result['classes'] ) . ')' );
        }
        return false;
    }

    private function write_cell( bool $dry_run, string $name, array $column, array $key, string $new ): ?string {
        if ( in_array( $column['name'], self::NAME_COLUMNS, true ) && false !== strpos( $new, self::PLUGIN_MARK ) ) {
            return 'protected';
        }
        if ( self::too_long( $new, $column ) ) {
            return 'too_long';
        }
        if ( ! $dry_run && ! $this->db->update( $name, $column['name'], $key, $new ) ) {
            return 'failed';
        }
        return null;
    }

    private function record_change( array $job, Replacer $replacer, array $cell, array $result, bool $with_sample ): array {
        $table  = $cell['table'];
        $column = $cell['column'];
        if ( ! isset( $job['report'][ $table ][ $column ] ) ) {
            $job['report'][ $table ][ $column ] = array( 'cells' => 0, 'occurrences' => 0 );
        }
        $job['report'][ $table ][ $column ]['cells']       += 1;
        $job['report'][ $table ][ $column ]['occurrences'] += $result['occurrences'];
        $job['totals']['cells']       += 1;
        $job['totals']['occurrences'] += $result['occurrences'];
        $job = $this->note_reverse( $job, $cell['before'], $cell['after'] );
        if ( $with_sample && ! empty( $job['args']['samples'] ) && count( $job['samples'] ) < self::SAMPLES_MAX ) {
            $job['samples'][] = self::sample( $cell['label'], $cell['before'], $cell['after'], $replacer );
        }
        if ( ! empty( $job['args']['log'] ) ) {
            $job = self::add_log( $job, $table, $column, $cell['key'], (array) ( $result['log_data'] ?? array() ), $replacer );
        }
        return $job;
    }

    private function site_address( array $job, Replacer $replacer ): array {
        foreach ( $job['tables'] as $name ) {
            $pk = $this->site_address_key( $job, $name );
            if ( null === $pk ) {
                continue;
            }
            foreach ( self::SITE_ADDRESS_OPTIONS as $option ) {
                $job = $this->replace_site_address( $job, $replacer, $name, $pk, $option );
            }
        }
        if ( ! $job['dry_run'] && $job['totals']['cells'] > 0 && $this->flush_cache ) {
            ( $this->flush_cache )();
            $job['cache']['flushed'] = true;
        }
        return $job;
    }

    private function site_address_key( array $job, string $name ): ?array {
        $names = array_map(
            static function ( array $c ): string {
                return $c['name'];
            },
            $this->db->text_columns( $name )
        );
        if ( ! self::is_options_table( $name, $names ) || ! in_array( 'option_value', $names, true ) ) {
            return null;
        }
        if ( ! self::column_in_scope( $job['args'], $name, 'option_value' ) ) {
            return null;
        }
        $pk = $this->db->primary_key( $name );
        return $pk ? $pk : null;
    }

    private static function column_in_scope( array $args, string $table, string $column ): bool {
        $skip    = array_map( 'strval', (array) ( $args['skip_columns'] ?? array() ) );
        $include = array_map( 'strval', (array) ( $args['include_columns'] ?? array() ) );
        $ids     = array( $column, $table . '.' . $column );
        return ! ( array_intersect( $ids, $skip ) || ( $include && ! array_intersect( $ids, $include ) ) );
    }

    private function replace_site_address( array $job, Replacer $replacer, string $name, array $pk, string $option ): array {
        $rows = $this->db->fetch( $name, 'option_value', $pk, $replacer->needles(), null, 1, array( array( 'option_name', 'in', array( $option ) ) ) );
        if ( ! $rows ) {
            return $job;
        }
        $row    = $rows[0];
        $value  = (string) ( $row['option_value'] ?? '' );
        $result = $replacer->replace( $value );
        if ( $result['value'] === $value ) {
            return $job;
        }
        $key = self::row_key( $pk, $row );
        if ( ! $job['dry_run'] && ! $this->db->update( $name, 'option_value', $key, $result['value'] ) ) {
            self::add_named( $job, 'failed', $name . '.option_value ' . $option );
            return $job;
        }
        $job['site_address'][] = array(
            'table'  => $name,
            'option' => $option,
            'before' => $value,
            'after'  => $result['value'],
        );
        $cell = array(
            'table'  => $name,
            'column' => 'option_value',
            'key'    => $key,
            'label'  => $name . '.option_value ' . $option,
            'before' => $value,
            'after'  => $result['value'],
        );
        return $this->record_change( $job, $replacer, $cell, $result, false );
    }

    private function complete( array $job ): array {
        $job['status'] = 'completed';
        return $this->end( $job );
    }

    private function end( array $job ): array {
        $job['finished'] = gmdate( 'Y-m-d H:i:s', (int) floor( ( $this->clock )() ) );
        $job['updated']  = (int) floor( ( $this->clock )() );
        $this->store->save( $job );
        if ( $this->on_finish ) {
            ( $this->on_finish )( $job );
        }
        return $job;
    }

    public function summary( array $job, bool $with_lists = false ): array {
        $now     = (int) floor( ( $this->clock )() );
        $status  = (string) $job['status'];
        $stalled = 'running' === $status && $now - (int) $job['heartbeat'] > self::STALLED_AFTER;

        list( $rows, $more ) = self::report_rows( $job['report'] );
        $out                 = array(
            'job_id'   => (string) $job['id'],
            'status'   => $stalled ? 'stalled' : $status,
            'dry_run'  => (bool) $job['dry_run'],
            'old'      => (string) $job['args']['old'],
            'new'      => (string) $job['args']['new'],
            'progress' => self::progress( $job, $status ),
            'totals'   => array(
                'cells'       => (int) $job['totals']['cells'],
                'occurrences' => (int) $job['totals']['occurrences'],
                'tables'      => count( $job['report'] ),
            ),
            'report'   => $rows,
        );
        if ( $more ) {
            $out['report_more'] = $more;
        }
        if ( ! $job['dry_run'] ) {
            $out['changed'] = (int) $job['totals']['cells'] > 0;
        }
        $out         = self::with_outcome( $out, $job, $status );
        $out['next'] = self::next_hint( $job, $stalled );
        if ( $with_lists ) {
            $out = self::fit( self::with_lists( $out, $job ) );
        }
        return $out;
    }

    private static function report_rows( array $report ): array {
        $rows = array();
        $more = 0;
        foreach ( $report as $table => $columns ) {
            foreach ( $columns as $column => $counts ) {
                if ( count( $rows ) >= self::REPORT_MAX ) {
                    ++$more;
                    continue;
                }
                $rows[] = array(
                    'table'       => (string) $table,
                    'column'      => (string) $column,
                    'cells'       => (int) $counts['cells'],
                    'occurrences' => (int) $counts['occurrences'],
                );
            }
        }
        return array( $rows, $more );
    }

    private static function progress( array $job, string $status ): array {
        return array(
            'tables_done'  => min( (int) $job['cursor']['t'], count( $job['tables'] ) ),
            'tables_total' => count( $job['tables'] ),
            'current'      => 'running' === $status && isset( $job['tables'][ $job['cursor']['t'] ] ) ? (string) $job['tables'][ $job['cursor']['t'] ] : '',
        );
    }

    private static function with_outcome( array $out, array $job, string $status ): array {
        $skipped = array_filter(
            (array) $job['skipped'],
            static function ( $list ): bool {
                return is_array( $list ) && ! empty( $list['count'] );
            }
        );
        if ( $skipped ) {
            $out['skipped'] = $skipped;
        }
        if ( 'running' !== $status ) {
            $out['reversible'] = self::reversible( $job );
        }
        if ( $job['site_address'] ) {
            $out['site_address'] = self::site_address_summary( $job );
        }
        if ( ! $job['dry_run'] && 'running' !== $status ) {
            $out['cache'] = array(
                'object_cache_flushed' => (bool) $job['cache']['flushed'],
                'note'                 => 'Page caches (caching plugins, the host, a CDN) were not purged.',
            );
        }
        if ( $job['samples'] ) {
            $out['samples'] = $job['samples'];
        }
        if ( '' !== (string) $job['stop_reason'] ) {
            $out['stop_reason'] = (string) $job['stop_reason'];
        }
        return $out;
    }

    private static function site_address_summary( array $job ): array {
        return array(
            'changed' => ! $job['dry_run'],
            'rows'    => $job['site_address'],
            'note'    => $job['dry_run']
                ? 'The live run will change the site address. API keys are bound to the site\'s host and are refused on a new host until re-bound (Connections > API tokens > Re-bind to this site); OAuth grants issued for the old address stop working.'
                : 'The site address changed. API keys bound to the old host are refused until re-bound (Connections > API tokens > Re-bind to this site); OAuth grants issued for the old address stop working, and clients must authorize again.',
        );
    }

    private static function with_lists( array $out, array $job ): array {
        if ( ! empty( $job['args']['log'] ) ) {
            $out['log']      = array_values( (array) ( $job['log'] ?? array() ) );
            $out['log_more'] = (int) ( $job['log_more'] ?? 0 );
        }
        if ( ! empty( $job['args']['verbose'] ) ) {
            $out['checked']      = array_values( (array) ( $job['checked'] ?? array() ) );
            $out['checked_more'] = (int) ( $job['checked_more'] ?? 0 );
        }
        return $out;
    }

    private static function fit( array $out ): array {
        $size = static function ( $value ): int {
            $json = \wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            return is_string( $json ) ? strlen( $json ) : 0;
        };
        $total = $size( $out );
        foreach ( array( 'log', 'checked' ) as $list ) {
            while ( $total > self::RESULT_BUDGET && ! empty( $out[ $list ] ) ) {
                $dropped = array_pop( $out[ $list ] );
                ++$out[ $list . '_more' ];
                $total -= $size( $dropped ) + 16;
                if ( $total <= self::RESULT_BUDGET ) {
                    $total = $size( $out );
                }
            }
        }
        return $out;
    }

    public static function replacer_options( array $args ): array {
        return array(
            'regex'           => ! empty( $args['regex'] ),
            'regex_flags'     => (string) ( $args['regex_flags'] ?? '' ),
            'regex_delimiter' => (string) ( $args['regex_delimiter'] ?? '' ),
            'regex_limit'     => isset( $args['regex_limit'] ) ? (int) $args['regex_limit'] : -1,
            'recurse_objects' => ! array_key_exists( 'recurse_objects', $args ) || (bool) $args['recurse_objects'],
            'log'             => ! empty( $args['log'] ),
        );
    }

    private static function add_log( array $job, string $table, string $column, array $key, array $leaves, Replacer $replacer ): array {
        $row   = $table . '.' . $column . ( $key ? ':' . implode( ',', array_values( $key ) ) : '' );
        $entry = self::log_entry( $job['args'], $row, $leaves, $replacer );
        if ( null === $entry ) {
            return $job;
        }
        $bytes = strlen( $entry['row'] ) + strlen( $entry['old'] ) + strlen( $entry['new'] );
        $used  = (int) ( $job['log_bytes'] ?? 0 );
        if ( ! empty( $job['log_more'] ) || count( (array) ( $job['log'] ?? array() ) ) >= self::LOG_MAX || $used + $bytes > self::LOG_BYTES ) {
            $job['log_more'] = (int) ( $job['log_more'] ?? 0 ) + 1;
            return $job;
        }
        $job['log'][]     = $entry;
        $job['log_bytes'] = $used + $bytes;
        return $job;
    }

    private static function log_entry( array $args, string $row, array $leaves, Replacer $replacer ): ?array {
        $before   = isset( $args['before_context'] ) ? (int) $args['before_context'] : 40;
        $after    = isset( $args['after_context'] ) ? (int) $args['after_context'] : 40;
        $old_bits = array();
        $new_bits = array();
        foreach ( $leaves as $leaf ) {
            list( $o, $n ) = $replacer->log_bits( (string) $leaf, $before, $after );
            $old_bits      = array_merge( $old_bits, $o );
            $new_bits      = array_merge( $new_bits, $n );
        }
        if ( ! $old_bits ) {
            return null;
        }
        return array(
            'row' => $row,
            'old' => str_replace( array( "\r\n", "\n" ), ' ', implode( ' [...] ', $old_bits ) ),
            'new' => str_replace( array( "\r\n", "\n" ), ' ', implode( ' [...] ', $new_bits ) ),
        );
    }

    private static function add_checked( array &$job, string $item ): void {
        if ( count( (array) ( $job['checked'] ?? array() ) ) >= self::CHECKED_MAX ) {
            $job['checked_more'] = (int) ( $job['checked_more'] ?? 0 ) + 1;
            return;
        }
        $job['checked'][] = $item;
    }

    private function note_reverse( array $job, string $before, string $after ): array {
        if ( null !== $this->reverse && $this->reverse->replace( $after )['value'] !== $before ) {
            $job['reverse_differs'] = (int) ( $job['reverse_differs'] ?? 0 ) + 1;
        }
        return $job;
    }

    public static function reversible( array $job ): array {
        if ( ! empty( $job['args']['regex'] ) ) {
            return array( 'value' => false, 'reason' => 'regex' );
        }
        if ( '' === (string) $job['args']['new'] ) {
            return array( 'value' => false, 'reason' => 'empty_new' );
        }
        if ( (int) ( $job['reverse_differs'] ?? 0 ) > 0 ) {
            return array( 'value' => false, 'reason' => 'reverse_differs', 'cells_not_restored' => (int) $job['reverse_differs'] );
        }
        if ( (int) $job['new_present'] > 0 ) {
            return array( 'value' => false, 'reason' => 'new_already_present', 'cells_with_new' => (int) $job['new_present'] );
        }
        return array( 'value' => true, 'reason' => 'new_absent_before' );
    }

    private static function next_hint( array $job, bool $stalled ): string {
        $id = (string) $job['id'];
        if ( $stalled ) {
            return sprintf( 'No background step for over %d minutes. WP-Cron may be off (DISABLE_WP_CRON without a system cron) or not triggered on this site. Polling with {"job_id": "%s"} nudges WP-Cron; ask the site owner to check WP-Cron if it stays stalled.', (int) ( self::STALLED_AFTER / 60 ), $id );
        }
        switch ( (string) $job['status'] ) {
            case 'running':
                return sprintf( 'Still running in the background. Call this tool again with only {"job_id": "%s"} in about 30 seconds to see progress; do not start the same run again.', $id );
            case 'completed':
                if ( $job['dry_run'] ) {
                    return 0 === (int) $job['totals']['cells']
                        ? 'Dry run: nothing in scope would change.'
                        : 'Dry run: nothing was changed. To apply exactly this, call the tool again with the same arguments and dry_run false.';
                }
                return 0 === (int) $job['totals']['cells'] ? 'Nothing in scope matched; nothing was changed.' : 'Done. To undo, run the opposite replacement only when reversible.value is true.';
            case 'cancelled':
                return 'Cancelled. Tables before the stopping point keep their changes; none after it were touched.';
        }
        return 'Stopped. Tables before the stopping point keep their changes; none after it were touched. Read stop_reason.';
    }

    private static function too_long( string $value, array $column ): bool {
        if ( null !== ( $column['max_chars'] ?? null ) ) {
            $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
            return $length > (int) $column['max_chars'];
        }
        if ( null !== ( $column['max_bytes'] ?? null ) ) {
            return strlen( $value ) > (int) $column['max_bytes'];
        }
        return false;
    }

    private static function is_options_table( string $table, array $names ): bool {
        return '' !== $table && 'options' === substr( $table, -7 ) && in_array( 'option_name', $names, true );
    }

    private static function named_list( array $items ): array {
        return array(
            'count' => count( $items ),
            'items' => array_slice( array_values( $items ), 0, self::NAMED_MAX ),
        );
    }

    private static function add_named( array &$job, string $bucket, string $item ): void {
        if ( ! isset( $job['skipped'][ $bucket ] ) ) {
            $job['skipped'][ $bucket ] = array( 'count' => 0, 'items' => array() );
        }
        ++$job['skipped'][ $bucket ]['count'];
        if ( count( $job['skipped'][ $bucket ]['items'] ) < self::NAMED_MAX ) {
            $job['skipped'][ $bucket ]['items'][] = $item;
        }
    }

    private static function sample( string $label, string $before, string $after, Replacer $replacer ): array {
        $pos   = $replacer->first_match( $before );
        $start = max( 0, (int) $pos - self::SAMPLE_CONTEXT );
        $cut   = static function ( string $text ) use ( $start ): string {
            $piece = function_exists( 'mb_strcut' ) ? mb_strcut( $text, $start, 2 * self::SAMPLE_CONTEXT + 60, 'UTF-8' ) : substr( $text, $start, 2 * self::SAMPLE_CONTEXT + 60 );
            return (string) $piece;
        };
        return array(
            'cell'   => $label,
            'before' => $cut( $before ),
            'after'  => $cut( $after ),
        );
    }
}
