<?php
namespace Easy_MCP_AI\Tools\Privileged;

use Easy_MCP_AI\Tools\Privileged_Tool;
use Easy_MCP_AI\Privileged\Search_Replace\Jobs;
use Easy_MCP_AI\Privileged\Search_Replace\Job_Store;
use Easy_MCP_AI\Privileged\Search_Replace\Runner;
use Easy_MCP_AI\Privileged\Search_Replace\Replacer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once dirname( __DIR__, 2 ) . '/privileged/search-replace/class-jobs.php';
Jobs::load();











class Search_Replace_Run extends Privileged_Tool {

    const MIN_OLD_LENGTH = 3;
    
    const MAX_NAME_LENGTH = 191;
    
    const NUDGE_AFTER = 60;
    
    const LOG_CONTEXT = 40;
    



    const REGEX_FLAGS = 'imsxuADSUXJnr';

    
    private $runner = null;
    
    private $store = null;
    






    public $cancel_wait_seconds = 22.0;

    public function __construct( ?Runner $runner = null, ?Job_Store $store = null ) {
        $this->runner = $runner;
        $this->store  = $store;
    }

    public function get_name() {
        return Jobs::TOOL_NAME;
    }

    public function get_privileged_group() {
        return 'search_replace';
    }

    public function get_description() {
        return 'Search and replace a string across the database, like `wp search-replace`: serialized PHP data is unserialized, replaced and serialized again so stored lengths stay right, and the JSON-escaped form (`https:\/\/`) is replaced too. Case-sensitive. Dry run by default (`dry_run` true): nothing is written and the result shows cells and occurrences per table and column. Call again with `dry_run` false and the same arguments to apply. Default scope: every table with this site\'s prefix; `tables` (wildcards allowed), `all_tables`, `network` (multisite, super admin) and `skip_tables` change it. Never touched: the plugin\'s own tables and options, the `user_pass` column, `guid` unless `include_guid`. `siteurl` and `home` are written last. A run longer than about 20 seconds continues in the background: the result has `status` "running" and a `job_id`; call again with only `{"job_id": "…"}` every 30 seconds or so to read progress (that call needs no approval). There is no undo: `reversible` in the finished result says whether running the opposite replacement would restore the data exactly. Objects of classes other than stdClass are left untouched and listed under skipped.partial; `recurse_objects` false leaves stdClass objects untouched too. `regex` true treats `old` as a PCRE pattern (`regex_flags`, `regex_delimiter`, `regex_limit` as in WP-CLI; `new` may use $1 backreferences): every row in scope is read, which is much slower, no JSON-escaped form is searched, a cell where the pattern fails is left unchanged under skipped.regex_error, and `reversible` is false. `log` true adds `log`, the old and new text around each change per cell (`before_context` / `after_context` characters, default 40), capped with the rest counted in `log_more`; `verbose` true adds `checked`, every table.column searched (the rest counted in `checked_more`). Not handled: URL-encoded values (http%3A%2F%2F) and text that block-attribute JSON stores escaped (&, <, >, ", --).';
    }

    public function get_input_schema() {
        $names = array(
            'type'  => 'array',
            'items' => array( 'type' => 'string' ),
        );
        return array(
            'type'       => 'object',
            'properties' => array(
                'old'             => array(
                    'type'        => 'string',
                    'description' => 'Text to find, at least 3 characters. Byte-exact and case-sensitive.',
                ),
                'new'             => array(
                    'type'        => 'string',
                    'description' => 'Replacement text. May be empty (a deletion, which cannot be reversed).',
                ),
                'dry_run'         => array(
                    'type'        => 'boolean',
                    'description' => 'Default true: report what would change and write nothing. false writes.',
                    'default'     => true,
                ),
                'tables'          => $names + array( 'description' => 'Only these tables (full names; * and ? wildcards). Default: every table with this site\'s prefix.' ),
                'all_tables'      => array(
                    'type'        => 'boolean',
                    'description' => 'Every table in the database, prefix or not.',
                ),
                'network'         => array(
                    'type'        => 'boolean',
                    'description' => 'Multisite only: every site\'s tables plus the network tables. Needs a super admin.',
                ),
                'skip_tables'     => $names + array( 'description' => 'Tables to leave out (wildcards allowed).' ),
                'skip_columns'    => $names + array( 'description' => 'Columns to leave out: "column" or "table.column".' ),
                'include_columns' => $names + array( 'description' => 'Only these columns: "column" or "table.column".' ),
                'include_guid'    => array(
                    'type'        => 'boolean',
                    'description' => 'Also replace in posts.guid (skipped by default; WordPress uses it as a permanent identifier).',
                ),
                'samples'         => array(
                    'type'        => 'boolean',
                    'description' => 'Include up to 20 before/after excerpts in the result.',
                ),
                'job_id'          => array(
                    'type'        => 'string',
                    'description' => 'Alone: read the status of a job this tool started. With cancel true: stop it.',
                ),
                'cancel'          => array(
                    'type'        => 'boolean',
                    'description' => 'With job_id: stop a running job. Tables already done keep their changes.',
                ),
                'regex'           => array(
                    'type'        => 'boolean',
                    'description' => 'Treat `old` as a PCRE pattern without delimiters, as `wp search-replace --regex`; `new` may use $1 or \\1 backreferences. Every row in scope is read (much slower). A cell where the pattern fails is left unchanged and listed under skipped.regex_error. Default false.',
                ),
                'regex_flags'     => array(
                    'type'        => 'string',
                    'description' => 'With regex: PCRE modifiers, any of i m s x u A D S U X J n r (for example "i" for case-insensitive); n and r need a PHP that supports them.',
                ),
                'regex_delimiter' => array(
                    'type'        => 'string',
                    'description' => 'With regex: the one character put around `old` (not a letter, digit, backslash or space). Default chr(1), as WP-CLI; escape it inside `old` if it appears there.',
                ),
                'regex_limit'     => array(
                    'type'        => 'integer',
                    'minimum'     => -1,
                    'description' => 'With regex: the most replacements per string (per string inside a serialized value). -1, the default, is no limit; 0 is refused.',
                ),
                'recurse_objects' => array(
                    'type'        => 'boolean',
                    'default'     => true,
                    'description' => 'Default true: replace inside stdClass objects in serialized values (objects of other classes are never opened). false leaves every object untouched, as --no-recurse-objects; a cell with a match left inside one is listed under skipped.partial.',
                ),
                'log'             => array(
                    'type'        => 'boolean',
                    'description' => 'Add `log`: per changed cell, its "table.column:key" and the old and new text around each change, as `wp search-replace --log` prints it. At most 200 entries / 16 KB; the rest are counted in `log_more`. Default false.',
                ),
                'before_context'  => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'With log: characters of context before each change (default 40).',
                ),
                'after_context'   => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'With log: characters of context after each change (default 40).',
                ),
                'verbose'         => array(
                    'type'        => 'boolean',
                    'description' => 'Add `checked`: every "table.column" the run searched, in order (the "Checking:" lines of --verbose); beyond 1000 they are counted in `checked_more`. Default false.',
                ),
            ),
        );
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    
    public function records_own_history() {
        return true;
    }

    
    protected function is_status_read( array $arguments ) {
        return array( 'job_id' ) === array_keys( $arguments ) && is_string( $arguments['job_id'] );
    }

    






    public function approval_refusal( array $arguments ) {
        if ( ! class_exists( '\\Easy_MCP_AI\\Approvals\\Approval_Gate' ) ) {
            return null;
        }
        $max  = \Easy_MCP_AI\Approvals\Approval_Gate::PREVIEW_MAX;
        $text = \Easy_MCP_AI\Approvals\Approval_Gate::flatten( $this->describe( $arguments ) );
        $len  = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
        if ( $len <= $max ) {
            return null;
        }
        return sprintf( 'This search-replace is %1$d characters long to describe and the approval page shows %2$d, so it was not sent for approval: a person must see every argument they approve. Use shorter old/new strings or fewer table names.', $len, $max );
    }

    






    protected function describe_payload( array $arguments ) {
        $job_line = $this->job_line( $arguments );
        if ( null !== $job_line ) {
            return $job_line;
        }
        try {
            $args = self::normalise( $arguments );
        } catch ( \InvalidArgumentException $e ) {
            return 'Invalid arguments: ' . $e->getMessage();
        }
        $lines   = array();
        $lines[] = 'Replace: ' . self::quote( $args['old'] );
        $lines[] = 'With: ' . self::quote( $args['new'] );
        if ( ! empty( $args['regex'] ) ) {
            $lines[] = self::regex_line( $args );
        }
        if ( array_key_exists( 'recurse_objects', $args ) && false === $args['recurse_objects'] ) {
            $lines[] = 'Objects: none is opened (recurse_objects false); text inside them stays.';
        }
        $lines[] = $args['dry_run'] ? 'Mode: dry run. Nothing is written.' : 'Mode: LIVE. Writes to the database; there is no undo.';
        $lines[] = 'Scope: ' . self::describe_scope( $args );
        if ( ! $args['dry_run'] ) {
            $lines[] = $this->last_dry_run_line( self::args_hash( $args ) );
        }
        return implode( "\n", $lines );
    }

    






    private function job_line( array $arguments ): ?string {
        if ( $this->is_status_read( $arguments ) ) {
            return sprintf( 'Read the status of search-replace job %s.', (string) $arguments['job_id'] );
        }
        if ( isset( $arguments['job_id'] ) ) {
            return sprintf( 'Cancel search-replace job %s. Tables already done keep their changes.', is_string( $arguments['job_id'] ) ? $arguments['job_id'] : '' );
        }
        return null;
    }

    





    private static function regex_line( array $args ): string {
        return sprintf(
            'Match: regular expression (flags: %1$s; delimiter: %2$s; %3$s per string). Every row in scope is read.',
            '' !== $args['regex_flags'] ? $args['regex_flags'] : 'none',
            chr( 1 ) === $args['regex_delimiter'] ? 'chr(1)' : self::quote( $args['regex_delimiter'] ),
            -1 === $args['regex_limit'] ? 'no limit' : 'at most ' . $args['regex_limit']
        );
    }

    







    protected function run( array $arguments ) {
        $store = $this->store ? $this->store : new Job_Store();
        if ( $this->is_status_read( $arguments ) || ( isset( $arguments['job_id'] ) && ! empty( $arguments['cancel'] ) ) ) {
            return $this->existing_job( $arguments, $store );
        }
        if ( isset( $arguments['job_id'] ) || isset( $arguments['cancel'] ) ) {
            throw new \InvalidArgumentException( 'job_id goes alone (read a job\'s status) or with cancel true (stop it); start a new run without job_id.' );
        }
        $args   = self::normalise( $arguments );
        $runner = $this->runner();
        if ( ! $args['dry_run'] ) {
            self::refuse_held_live_run( $store );
        }
        return $runner->summary( $this->start_job( $args, $store, $runner ), true );
    }

    








    private static function refuse_held_live_run( Job_Store $store ): void {
        $live = $store->live_job();
        $held = '' !== $live ? $store->get( $live ) : null;
        if ( null !== $held && 'running' === $held['status'] ) {
            throw new \InvalidArgumentException( self::live_refusal( $held ) );
        }
    }

    










    private function start_job( array $args, Job_Store $store, Runner $runner ): array {
        $user_id = (int) \get_current_user_id();
        $job     = $runner->create( $args, Jobs::site_context( $user_id ), $this->identity( $user_id ), self::args_hash( $args ) );
        if ( ! $job['dry_run'] && ! $store->claim_live( $job['id'] ) ) {
            $store->delete( $job['id'] );
            throw new \InvalidArgumentException( self::live_refusal( $store->get( $store->live_job(), true ) ) );
        }
        if ( $store->lock( $job['id'], Runner::LOCK_TTL ) ) {
            try {
                $job = $runner->run_slice( $job );
            } finally {
                $store->unlock( $job['id'] );
            }
        }
        if ( 'running' === $job['status'] ) {
            Jobs::schedule( $job['id'] );
            if ( function_exists( 'spawn_cron' ) ) {
                \spawn_cron();
            }
        }
        return $job;
    }

    







    private function existing_job( array $arguments, Job_Store $store ): array {
        $id  = (string) $arguments['job_id'];
        $job = $store->get( $id );
        
        
        
        if ( null === $job || ( function_exists( 'get_current_blog_id' ) && (int) $job['blog_id'] !== (int) \get_current_blog_id() ) || ! self::may_read( $job ) ) {
            throw new \InvalidArgumentException( sprintf( 'No search-replace job %s on this site. Finished jobs are kept for %s.', $id, 1 === Jobs::RETENTION_DAYS ? 'a day' : Jobs::RETENTION_DAYS . ' days' ) );
        }
        $runner = $this->runner();
        if ( ! empty( $arguments['cancel'] ) ) {
            return $runner->summary( $this->cancel( $id, $store, $runner ), true );
        }
        self::nudge_stalled_job( $id, $job );
        return $runner->summary( $job, true );
    }

    









    private static function nudge_stalled_job( string $id, array $job ): void {
        if ( 'running' === $job['status'] && time() - (int) $job['heartbeat'] > self::NUDGE_AFTER && function_exists( 'spawn_cron' ) ) {
            Jobs::schedule( $id );
            \spawn_cron();
        }
    }

    







    private function cancel( string $id, Job_Store $store, Runner $runner ): array {
        $until = microtime( true ) + $this->cancel_wait_seconds;
        while ( ! $store->lock( $id, Runner::LOCK_TTL ) ) {
            if ( microtime( true ) >= $until ) {
                throw new \InvalidArgumentException( sprintf( 'Job %s is in the middle of a step; nothing was cancelled. Try again in a few seconds.', $id ) );
            }
            usleep( 250000 );
        }
        try {
            $job = $store->get( $id, true );
            if ( null === $job || 'running' !== $job['status'] ) {
                throw new \InvalidArgumentException( sprintf( 'Job %s is not running (status %s); nothing was cancelled.', $id, null === $job ? 'gone' : $job['status'] ) );
            }
            return $runner->stop( $job, 'cancelled', 'Cancelled by request.' );
        } finally {
            $store->unlock( $id );
        }
    }

    






    private static function may_read( array $job ): bool {
        return empty( $job['needs_network'] ) || Jobs::network_user( (int) \get_current_user_id() );
    }

    





    private static function live_refusal( ?array $held ): string {
        if ( null === $held || ! self::may_read( $held ) ) {
            return 'A live search-replace job is still running on this site; wait for it to end.';
        }
        return sprintf( 'Live search-replace job %1$s is still running on this site; read it with {"job_id": "%1$s"} or cancel it first.', (string) $held['id'] );
    }

    private function runner(): Runner {
        return $this->runner ? $this->runner : Jobs::runner();
    }

    
    private function identity( int $user_id ): array {
        $ctx = class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) ? \Easy_MCP_AI\History\Change_Context::all() : array();
        return array(
            'user_id'         => $user_id,
            'audit_id'        => $ctx['audit_id'] ?? null,
            'auth_source'     => $ctx['auth_source'] ?? 'legacy',
            'token_id'        => $ctx['token_id'] ?? 0,
            'oauth_client_id' => $ctx['oauth_client_id'] ?? null,
            'ip_address'      => $ctx['ip_address'] ?? null,
        );
    }

    











    public static function normalise( array $arguments ): array {
        list( $old, $new ) = self::search_strings( $arguments );
        $regex             = self::flag( $arguments['regex'] ?? false );
        
        
        
        if ( $old === $new && ! $regex ) {
            throw new \InvalidArgumentException( '`old` and `new` are the same; nothing would change.' );
        }
        self::refuse_options_without( $arguments, array( 'regex_flags', 'regex_delimiter', 'regex_limit' ), 'regex', $regex );
        $log = self::flag( $arguments['log'] ?? false );
        self::refuse_options_without( $arguments, array( 'before_context', 'after_context' ), 'log', $log );
        return self::with_wp_cli_options( self::run_arguments( $arguments, $old, $new ), $arguments, $regex, $log );
    }

    









    private static function run_arguments( array $arguments, string $old, string $new ): array {
        return array(
            'old'             => $old,
            'new'             => $new,
            'dry_run'         => array_key_exists( 'dry_run', $arguments ) ? self::flag( $arguments['dry_run'] ) : true,
            'tables'          => self::names( $arguments, 'tables' ),
            'all_tables'      => self::flag( $arguments['all_tables'] ?? false ),
            'network'         => self::flag( $arguments['network'] ?? false ),
            'skip_tables'     => self::names( $arguments, 'skip_tables' ),
            'skip_columns'    => self::names( $arguments, 'skip_columns' ),
            'include_columns' => self::names( $arguments, 'include_columns' ),
            'include_guid'    => self::flag( $arguments['include_guid'] ?? false ),
            'samples'         => self::flag( $arguments['samples'] ?? false ),
        );
    }

    







    private static function search_strings( array $arguments ): array {
        foreach ( array( 'old', 'new' ) as $key ) {
            if ( ! isset( $arguments[ $key ] ) || ! is_string( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( '`%s` is required and must be a string.', $key ) );
            }
        }
        $old = $arguments['old'];
        $len = function_exists( 'mb_strlen' ) ? mb_strlen( $old, 'UTF-8' ) : strlen( $old );
        if ( $len < self::MIN_OLD_LENGTH ) {
            throw new \InvalidArgumentException( sprintf( '`old` must be at least %d characters: a shorter string matches too much to review.', self::MIN_OLD_LENGTH ) );
        }
        return array( $old, $arguments['new'] );
    }

    









    private static function refuse_options_without( array $arguments, array $keys, string $flag, bool $on ): void {
        foreach ( $keys as $key ) {
            if ( ! $on && isset( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( '`%s` applies only with `%s` true; nothing was run.', $key, $flag ) );
            }
        }
    }

    











    private static function with_wp_cli_options( array $args, array $arguments, bool $regex, bool $log ): array {
        if ( $regex ) {
            $args = array_merge( $args, self::regex_arguments( $arguments, $args['old'] ) );
        }
        if ( isset( $arguments['recurse_objects'] ) && ! self::flag( $arguments['recurse_objects'] ) ) {
            $args['recurse_objects'] = false;
        }
        if ( $log ) {
            $args['log']            = true;
            $args['before_context'] = self::whole_number( $arguments, 'before_context', self::LOG_CONTEXT );
            $args['after_context']  = self::whole_number( $arguments, 'after_context', self::LOG_CONTEXT );
        }
        if ( self::flag( $arguments['verbose'] ?? false ) ) {
            $args['verbose'] = true;
        }
        return $args;
    }

    









    private static function regex_arguments( array $arguments, string $old ): array {
        $flags     = self::regex_flags( $arguments );
        $delimiter = self::regex_delimiter( $arguments );
        $limit     = self::regex_limit( $arguments );
        self::refuse_uncompilable( Replacer::pattern( $old, $delimiter, $flags ) );
        return array(
            'regex'           => true,
            'regex_flags'     => $flags,
            'regex_delimiter' => $delimiter,
            'regex_limit'     => $limit,
        );
    }

    






    private static function regex_flags( array $arguments ): string {
        $flags = $arguments['regex_flags'] ?? '';
        if ( ! is_string( $flags ) || strspn( $flags, self::REGEX_FLAGS ) !== strlen( $flags ) ) {
            throw new \InvalidArgumentException( '`regex_flags` may hold only the PCRE modifiers i m s x u A D S U X J n r (for example "i"); nothing was run.' );
        }
        return $flags;
    }

    







    private static function regex_delimiter( array $arguments ): string {
        $delimiter = $arguments['regex_delimiter'] ?? '';
        if ( ! is_string( $delimiter ) ) {
            throw new \InvalidArgumentException( '`regex_delimiter` must be one character; nothing was run.' );
        }
        if ( '' === $delimiter ) {
            return chr( 1 );
        }
        if ( 1 !== strlen( $delimiter ) || preg_match( '/^[A-Za-z0-9\\\\\s\x00]$/', $delimiter ) ) {
            throw new \InvalidArgumentException( '`regex_delimiter` must be one character that is not a letter, digit, backslash or space; nothing was run.' );
        }
        return $delimiter;
    }

    







    private static function regex_limit( array $arguments ): int {
        $limit = $arguments['regex_limit'] ?? -1;
        if ( ! is_numeric( $limit ) || (float) $limit !== (float) (int) $limit || ( -1 !== (int) $limit && (int) $limit < 1 ) ) {
            throw new \InvalidArgumentException( '`regex_limit` must be -1 (no limit) or a whole number of 1 or more; nothing was run.' );
        }
        return (int) $limit;
    }

    






    private static function refuse_uncompilable( string $pattern ): void {
        error_clear_last();
        if ( false === @preg_match( $pattern, '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the compile error is read back and reported.
            $error   = error_get_last();
            $message = is_array( $error ) && isset( $error['message'] ) ? preg_replace( '/^preg_match\(\):\s*/', '', (string) $error['message'] ) : 'it does not compile';
            throw new \InvalidArgumentException( sprintf( '`old` is not a valid regular expression with these flags and delimiter (%s); nothing was run.', substr( (string) $message, 0, 100 ) ) );
        }
    }

    








    private static function whole_number( array $arguments, string $key, int $default ): int {
        if ( ! isset( $arguments[ $key ] ) ) {
            return $default;
        }
        $value = $arguments[ $key ];
        if ( ! is_numeric( $value ) || (float) $value !== (float) (int) $value || (int) $value < 0 ) {
            throw new \InvalidArgumentException( sprintf( '`%s` must be a whole number of 0 or more; nothing was run.', $key ) );
        }
        return (int) $value;
    }

    
    public static function args_hash( array $args ): string {
        $key = $args;
        
        unset( $key['dry_run'], $key['samples'], $key['log'], $key['before_context'], $key['after_context'], $key['verbose'] );
        ksort( $key );
        return hash( 'sha256', (string) \wp_json_encode( $key ) );
    }

    
    private static function flag( $value ): bool {
        if ( function_exists( 'rest_sanitize_boolean' ) ) {
            return (bool) \rest_sanitize_boolean( $value );
        }
        return (bool) filter_var( $value, FILTER_VALIDATE_BOOLEAN );
    }

    
    private static function names( array $arguments, string $key ): array {
        if ( ! isset( $arguments[ $key ] ) ) {
            return array();
        }
        $value = $arguments[ $key ];
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '`%s` must be an array of strings.', $key ) );
        }
        $out = array();
        foreach ( $value as $item ) {
            if ( ! is_string( $item ) || '' === trim( $item ) || strlen( $item ) > self::MAX_NAME_LENGTH ) {
                throw new \InvalidArgumentException( sprintf( 'Every entry of `%s` must be a non-empty name of at most %d characters.', $key, self::MAX_NAME_LENGTH ) );
            }
            $out[] = trim( $item );
        }
        return array_values( array_unique( $out ) );
    }

    private static function quote( string $value ): string {
        return (string) \wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    private static function describe_scope( array $args ): string {
        if ( $args['all_tables'] ) {
            $scope = 'every table in the database';
        } elseif ( $args['network'] ) {
            $scope = 'every site\'s tables and the network tables';
        } else {
            $scope = 'this site\'s tables';
        }
        if ( $args['tables'] ) {
            $scope .= ', only ' . implode( ', ', $args['tables'] );
        }
        if ( $args['skip_tables'] ) {
            $scope .= '; skipping tables ' . implode( ', ', $args['skip_tables'] );
        }
        if ( $args['include_columns'] ) {
            $scope .= '; only columns ' . implode( ', ', $args['include_columns'] );
        }
        if ( $args['skip_columns'] ) {
            $scope .= '; skipping columns ' . implode( ', ', $args['skip_columns'] );
        }
        $scope .= $args['include_guid'] ? '; guid included' : '; guid left alone';
        return $scope . '.';
    }

    




    private function last_dry_run_line( string $hash ): string {
        $store  = $this->store ? $this->store : new Job_Store();
        $recent = array_reverse( $store->recent(), true );
        foreach ( $recent as $id => $summary ) {
            if ( $summary['args_hash'] !== $hash || empty( $summary['dry_run'] ) || 'completed' !== $summary['status'] ) {
                continue;
            }
            $job = $store->get( (string) $id );
            
            
            if ( null === $job || ! self::may_read( $job ) ) {
                continue;
            }
            $reversible = Runner::reversible( $job );
            return sprintf(
                'Last dry run with these arguments (job %1$s, finished %2$s UTC): %3$d cells, %4$d occurrences in %5$d tables; reversible: %6$s (%7$s).',
                $id,
                (string) $job['finished'],
                (int) $job['totals']['cells'],
                (int) $job['totals']['occurrences'],
                count( (array) $job['report'] ),
                $reversible['value'] ? 'yes' : 'no',
                $reversible['reason']
            );
        }
        return 'No dry run with these arguments has finished on this site.';
    }
}
