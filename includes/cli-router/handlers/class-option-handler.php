<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Envelope;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































class Option_Handler extends Command_Handler {

    
    const ROW_CAP = 1000;

    const MASK = '[REDACTED]';

    





    const SITE_SETTINGS = array(
        'blogname'        => 'title',
        'blogdescription' => 'description',
        'timezone_string' => 'timezone',
        'date_format'     => 'date_format',
        'time_format'     => 'time_format',
        'posts_per_page'  => 'posts_per_page',
        'WPLANG'          => 'language',
    );

    
    const UNTRAILING = array( 'siteurl', 'home', 'category_base', 'tag_base' );

    






    const SETTINGS_SCREENS = array(
        'general'    => array( 'blogname', 'blogdescription', 'site_icon', 'gmt_offset', 'date_format', 'time_format', 'start_of_week', 'timezone_string', 'WPLANG', 'new_admin_email' ),
        'discussion' => array( 'default_pingback_flag', 'default_ping_status', 'default_comment_status', 'comments_notify', 'moderation_notify', 'comment_moderation', 'require_name_email', 'comment_previously_approved', 'comment_max_links', 'moderation_keys', 'disallowed_keys', 'show_avatars', 'avatar_rating', 'avatar_default', 'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth', 'page_comments', 'comments_per_page', 'default_comments_page', 'comment_order', 'comment_registration', 'show_comments_cookies_opt_in', 'wp_notes_notify' ),
        'media'      => array( 'thumbnail_size_w', 'thumbnail_size_h', 'thumbnail_crop', 'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h', 'image_default_size', 'image_default_align', 'image_default_link_type' ),
        'reading'    => array( 'posts_per_page', 'posts_per_rss', 'rss_use_excerpt', 'show_on_front', 'page_on_front', 'page_for_posts', 'blog_public' ),
        'writing'    => array( 'default_category', 'default_email_category', 'default_link_category', 'default_post_format' ),
        'misc'       => array(),
        'options'    => array(),
        'privacy'    => array(),
    );

    const SITE_SETTING = "'%1\$s' is a site setting only wp_update_site_settings writes (argument `%2\$s`), and that tool only updates it. Use `option update %1\$s <value>`, which goes through it.";
    const NOT_REGISTERED = "Sorry, you are not allowed to do this: on a network, '%s' is not a setting of this site's settings screens, and wp-admin lets only a super admin with the manage_network_options capability read or change such options.";
    const OVER_ROW_CAP  = "wp_cli_run lists at most %1\$d options in one 'option list', and %2\$d match. Narrow it with --search or --exclude (wildcards * and ?), or ask for --format=count or --format=total_bytes.";
    const EMPTY_SEARCH  = "wp_cli_run refuses --search='%s': for a pattern PHP reads as false ('' or '0') WP-CLI builds no WHERE clause, the database rejects the statement and WP-CLI prints an empty list. Omit --search to list every option, or use a wildcard such as '0*'.";
    const OMITTED       = 'Self-protection: wp_cli_run left out %1$d %2$s it never prints: %3$s.';
    const NOTE_FALSE    = "WP-CLI exits 1 without output here: the option '%s' does not exist (or holds false).";
    const NOTE_NO_PATH  = "WP-CLI exits 1 without output here: the option '%s' has no value at that key path.";
    const AUTOLOAD_NOT_WRITTEN = "The autoload value of '%s' option was not written: no row was updated (the option was deleted meanwhile, or the database refused the write).";

    
    const NOT_RECORDED = "Option '%s': change history does not record this option under the site's capture settings.";
    const RAW_SQL      = 'Raw SQL: change history records this database write only while database capture is on.';

    
    
    

    
    public function preflight_read( Invocation $inv, Context $ctx ): void {
        $key = (string) $this->arg( $inv, 0 );
        $this->guard( $key, false, $ctx );
        $this->guard_network( $key, $ctx );
    }

    
    public function preflight_write( Invocation $inv, Context $ctx ): void {
        $key = (string) $this->arg( $inv, 0 );
        $this->guard( $key, true, $ctx );
        $this->guard_network( $key, $ctx );
    }

    
    public function preflight_patch( Invocation $inv, Context $ctx ): void {
        $key = (string) $this->arg( $inv, 1 );
        $this->guard( $key, true, $ctx );
        $this->guard_network( $key, $ctx );
    }

    
    public function preflight_keys( Invocation $inv, Context $ctx ): void {
        $keys = 'option delete' === $inv->command ? array_values( $inv->args ) : array( $this->arg( $inv, 0 ) );
        foreach ( $keys as $key ) {
            $this->guard( (string) $key, true, $ctx );
        }
    }

    
    
    

    
    public function get( Invocation $inv, Context $ctx ): void {
        $key = (string) $this->arg( $inv, 0 );
        $this->guard( $key, false, $ctx );

        $value = $this->get_option( $key );

        if ( false === $value ) {
            $ctx->out->error( "Could not get '{$key}' option. Does it exist?" );
        }
        if ( self::key_is_secret( $key ) ) {
            $value = self::MASK;
            $ctx->redacted( "option_value of '{$key}'" );
        }

        Values::print_value( $ctx->out, $value, $inv->assoc );
    }

    
    public function add( Invocation $inv, Context $ctx ): void {
        $args = $inv->args;
        $key  = (string) $this->arg( $inv, 0 );
        $this->guard( $key, true, $ctx );

        $value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 1 );
        $value = Read_Value::read( $ctx->out, $value, $inv->assoc );

        if ( in_array( Read_Value::flag( $inv->assoc, 'autoload' ), array( 'no', 'off' ), true ) ) {
            $autoload = 'no';
        } else {
            $autoload = 'yes';
        }

        $this->note_recording( $key, $ctx );
        if ( ! $this->add_option( $key, $value, $autoload ) ) {
            $ctx->out->error( "Could not add option '{$key}'. Does it already exist?" );
        } else {
            $ctx->mark_changed();
            $ctx->out->success( "Added '{$key}' option." );
        }
    }

    
    public function update( Invocation $inv, Context $ctx ): void {
        $args = $inv->args;
        $key  = (string) $this->arg( $inv, 0 );
        $this->guard( $key, true, $ctx );

        $value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 1 );
        $value = Read_Value::read( $ctx->out, $value, $inv->assoc );

        $autoload = Read_Value::flag( $inv->assoc, 'autoload' );
        if ( ! in_array( $autoload, array( 'on', 'off', 'yes', 'no' ), true ) ) {
            $autoload = null;
        }

        $value = $this->sanitize_option( $key, $value );
        
        if ( in_array( $key, self::UNTRAILING, true ) && is_string( $value ) ) {
            $value = rtrim( $value, '/\\' );
        }
        $old_value = $this->sanitize_option( $key, $this->get_option( $key ) );

        $this->note_recording( $key, $ctx );
        if ( $value === $old_value && null === $autoload ) {
            $ctx->out->success( "Value passed for '{$key}' option is unchanged." );
        } elseif ( $this->update_option( $key, $value, $autoload ) ) {
            $ctx->mark_changed();
            $ctx->out->success( "Updated '{$key}' option." );
        } else {
            $ctx->out->error( "Could not update option '{$key}'." );
        }
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
            
            
            if ( count( $key_path ) > 1 ) {
                $patch_value = Read_Value::read( $ctx->out, array_pop( $key_path ), $inv->assoc );
            } else {
                $patch_value = null;
            }
            if ( null === $patch_value ) {
                $ctx->out->error( 'Please provide value to update.' );
            }
        }

        
        $old_value     = $this->sanitize_option( $key, $this->get_option( $key ) );
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

        $this->note_recording( $key, $ctx );
        if ( $patched_value === $old_value ) {
            $ctx->out->success( "Value passed for '{$key}' option is unchanged." );
        } elseif ( $this->update_option( $key, $patched_value, null ) ) {
            $ctx->mark_changed();
            $ctx->out->success( "Updated '{$key}' option." );
        } else {
            $ctx->out->error( "Could not update option '{$key}'." );
        }
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        $missing = array();
        foreach ( array_values( $inv->args ) as $arg ) {
            $arg = (string) $arg;
            $this->guard( $arg, true, $ctx );
        }
        foreach ( array_values( $inv->args ) as $arg ) {
            $arg = (string) $arg;
            $this->note_recording( $arg, $ctx );
            if ( ! $this->delete_option( $arg ) ) {
                $ctx->out->warning( "Could not delete '{$arg}' option. Does it exist?" );
                $missing[] = $arg;
            } else {
                $ctx->mark_changed();
                $ctx->out->success( "Deleted '{$arg}' option." );
            }
        }
        if ( 1 === count( $missing ) ) {
            $ctx->fail_missing_target( "Could not delete '{$missing[0]}' option. Does it exist?" );
        } elseif ( $missing ) {
            $ctx->fail_missing_target( sprintf( 'Could not delete %d of the %d options named; they do not appear to exist: %s.', count( $missing ), count( $inv->args ), self::quoted_list( $missing ) ) );
        }
    }

    
    public function get_autoload( Invocation $inv, Context $ctx ): void {
        $option = (string) $this->arg( $inv, 0 );
        $this->guard( $option, false, $ctx );

        $db       = $this->db();
        $existing = $db->get_row(
            $db->prepare(
                'SELECT `autoload` FROM ' . self::ident( $db->options ) . ' WHERE `option_name` = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the table name is quoted by ident().
                $option
            )
        );
        if ( ! $existing ) {
            $ctx->out->error( "Could not get '{$option}' option. Does it exist?" );
        }
        $ctx->out->log( (string) $existing->autoload );
    }

    
    public function set_autoload( Invocation $inv, Context $ctx ): void {
        $option   = (string) $this->arg( $inv, 0 );
        $autoload = (string) $this->arg( $inv, 1 );
        $this->guard( $option, true, $ctx );

        $db       = $this->db();
        $previous = $db->get_row(
            $db->prepare(
                'SELECT `autoload`, `option_value` FROM ' . self::ident( $db->options ) . ' WHERE `option_name` = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the table name is quoted by ident().
                $option
            )
        );
        if ( ! $previous ) {
            $ctx->out->error( "Could not get '{$option}' option. Does it exist?" );
        }
        if ( $previous->autoload === $autoload ) {
            $ctx->out->success( "Autoload value passed for '{$option}' option is unchanged." );
            return;
        }

        $ctx->not_captured( self::RAW_SQL );
        $updated = $db->update(
            $db->options,
            array( 'autoload' => $autoload ),
            array( 'option_name' => $option )
        );

        
        $notoptions = $this->cache_get( 'notoptions', 'options' );
        if ( is_array( $notoptions ) && isset( $notoptions[ $option ] ) ) {
            unset( $notoptions[ $option ] );
            $this->cache_set( 'notoptions', $notoptions, 'options' );
        }

        if ( ! defined( 'WP_INSTALLING' ) ) {
            $alloptions = $this->load_alloptions();
            if ( isset( $alloptions[ $option ] ) ) {
                $alloptions[ $option ] = $previous->option_value;
                $this->cache_set( 'alloptions', $alloptions, 'options' );
            } else {
                $this->cache_set( $option, $previous->option_value, 'options' );
            }
        }

        if ( is_int( $updated ) && $updated > 0 ) {
            $ctx->mark_changed();
        } else {
            $ctx->fail_missing_target( sprintf( self::AUTOLOAD_NOT_WRITTEN, $option ) );
        }
        $ctx->out->success( "Updated autoload value for '{$option}' option." );
    }

    
    public function pluck( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        $key  = (string) $args[0];
        $this->guard( $key, false, $ctx );

        $value = $this->get_option( $key );

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
            $ctx->redacted( "option_value of '{$key}'" );
        }

        Values::print_value( $ctx->out, $value, $inv->assoc );
    }

    




    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args  = $inv->assoc;
        $db          = $this->db();
        $where       = $this->list_where( $db, $assoc_args, $ctx );
        $total_bytes = Read_Value::flag( $assoc_args, 'format' ) === 'total_bytes';
        $table       = self::ident( $db->options );

        $this->check_list_names( $db, $table, $where, $assoc_args, $ctx );
        $results = $this->list_rows( $db, $table, $where, $assoc_args, $ctx );

        if ( $total_bytes ) {
            
            $sum = null;
            foreach ( $results as $v ) {
                $sum = (int) $sum + (int) $v->size_bytes;
            }
            $ctx->out->line( null === $sum ? '' : (string) $sum );
            return;
        }

        $fields    = isset( $assoc_args['fields'] ) ? explode( ',', (string) $assoc_args['fields'] ) : array( 'option_name', 'option_value' );
        $formatter = $this->formatter( $ctx, $assoc_args, $fields );
        $formatter->display_items( $results );
    }

    
    
    

    








    private function list_where( object $db, array $assoc_args, Context $ctx ): string {
        $pattern = '%';
        $exclude = '';
        if ( isset( $assoc_args['search'] ) ) {
            $pattern = self::like_pattern( $db, (string) $assoc_args['search'] );
            if ( ! $pattern ) {
                $ctx->out->error( sprintf( self::EMPTY_SEARCH, (string) $assoc_args['search'] ) );
            }
        }

        if ( isset( $assoc_args['exclude'] ) ) {
            $exclude = self::like_pattern( $db, (string) $assoc_args['exclude'] );
        }

        $autoload_query = isset( $assoc_args['autoload'] ) ? self::autoload_clause( $assoc_args['autoload'], $ctx ) : '';

        
        if ( Read_Value::flag( $assoc_args, 'transients', false ) ) {
            
            $transients_query = " AND (option_name LIKE '\_transient\_%' OR option_name LIKE '\_site\_transient\_%')";
        } else {
            $transients_query = " AND option_name NOT LIKE '\_transient\_%' AND option_name NOT LIKE '\_site\_transient\_%'";
        }

        $where = '';
        if ( $pattern ) {
            $where .= $db->prepare( 'WHERE `option_name` LIKE %s', $pattern );
        }

        if ( $exclude ) {
            $where .= $db->prepare( ' AND `option_name` NOT LIKE %s', $exclude );
        }
        return $where . $autoload_query . $transients_query;
    }

    





    public static function like_pattern( object $db, string $value ): string {
        $pattern = self::esc_like( $db, $value );
        
        $pattern = str_replace( '*', '%', $pattern );
        return str_replace( '?', '_', $pattern );
    }

    






    private static function autoload_clause( $autoload, Context $ctx ): string {
        if ( 'on' === $autoload || 'yes' === $autoload ) {
            return " AND (autoload='on' OR autoload='yes')";
        }
        if ( 'off' === $autoload || 'no' === $autoload ) {
            return " AND (autoload='off' OR autoload='no')";
        }
        $ctx->out->error( "Value of '--autoload' should be 'on', 'off', 'yes', or 'no'." );
        return '';
    }

    







    private function check_list_names( object $db, string $table, string $where, array $assoc_args, Context $ctx ): void {
        $names                  = (array) $db->get_col( "SELECT `option_name` FROM {$table} {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- every value in $where went through prepare(); the table name is quoted by ident().
        list( $kept, $omitted ) = self::count_kept( $names );
        $format                 = Read_Value::flag( $assoc_args, 'format' );
        if ( $kept > self::ROW_CAP && ! in_array( $format, array( 'count', 'total_bytes' ), true ) ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, $kept ) );
        }
        if ( $omitted ) {
            $ctx->out->warning( self::omitted_warning( $omitted, 'option', 'options' ) );
        }
    }

    








    private function list_rows( object $db, string $table, string $where, array $assoc_args, Context $ctx ): array {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- every value in $where went through prepare(); the table name is quoted by ident().
        $rows    = (array) $db->get_results( "SELECT `option_name`,`option_value`,`autoload`,LENGTH(option_value) AS `size_bytes` FROM {$table} {$where}" );
        $results = array();
        foreach ( $rows as $row ) {
            if ( null === self::omission( (string) $row->option_name, false ) ) {
                $results[] = $row;
            }
        }

        self::order_rows( $results, Read_Value::flag( $assoc_args, 'orderby' ), Read_Value::flag( $assoc_args, 'order' ) );

        if ( true === Read_Value::flag( $assoc_args, 'unserialize', null ) ) {
            foreach ( $results as $v ) {
                if ( ! empty( $v->option_value ) ) {
                    $v->option_value = $this->maybe_unserialize( $v->option_value );
                }
            }
        }

        foreach ( $results as $v ) {
            if ( self::key_is_secret( (string) $v->option_name ) ) {
                $v->option_value = self::MASK;
                $ctx->redacted( "option_value of '{$v->option_name}'" );
            }
        }
        return $results;
    }

    







    private static function order_rows( array &$results, $orderby, $order ): void {
        
        if ( 'option_id' !== $orderby ) {
            self::sort_rows( $results, (string) $orderby, (string) $order );
        } elseif ( 'option_id' === $orderby && 'desc' === $order ) { 
            krsort( $results );
        }
    }

    
    
    

    
    public function describe_add( Invocation $inv ): array {
        $key      = (string) $this->arg( $inv, 0 );
        $new      = self::described_value( $inv, $this->arg( $inv, 1 ) );
        $autoload = in_array( Read_Value::flag( $inv->assoc, 'autoload' ), array( 'no', 'off' ), true ) ? 'no' : 'yes';
        $current  = $this->get_option( $key );
        if ( false !== $current ) {
            return array( sprintf( "Option '%s': %s → add refused: it already exists", $key, self::preview( $key, $current ) ) );
        }
        return array( sprintf( "Option '%s': (not set) → add %s (autoload %s)", $key, self::preview( $key, $new ), $autoload ) );
    }

    
    public function describe_update( Invocation $inv ): array {
        $key      = (string) $this->arg( $inv, 0 );
        $new      = self::described_value( $inv, $this->arg( $inv, 1 ) );
        $autoload = Read_Value::flag( $inv->assoc, 'autoload' );
        $current  = $this->get_option( $key );
        $line     = sprintf( "Option '%s': %s → %s", $key, false === $current ? '(not set)' : self::preview( $key, $current ), self::preview( $key, $new ) );
        if ( in_array( $autoload, array( 'on', 'off', 'yes', 'no' ), true ) ) {
            $line .= ', autoload ' . $autoload;
        }
        return array( $line );
    }

    
    public function describe_patch( Invocation $inv ): array {
        $args   = array_values( $inv->args );
        $action = (string) $args[0];
        $key    = (string) $args[1];
        $path   = array_slice( $args, 2 );
        $value  = 'delete' === $action ? null : ( count( $path ) > 1 ? array_pop( $path ) : null );
        if ( null !== $value && 'json' === Read_Value::flag( $inv->assoc, 'format' ) ) {
            $decoded = json_decode( (string) $value, true );
            $value   = null === $decoded ? $value : $decoded;
        }
        $current = $this->get_option( $key );
        $effect  = 'delete' === $action ? 'delete ' . implode( '.', $path ) : $action . ' ' . implode( '.', $path ) . ' = ' . self::preview( $key, $value );
        return array( sprintf( "Option '%s': %s → %s", $key, false === $current ? '(not set)' : self::preview( $key, $current ), $effect ) );
    }

    
    public function describe_delete( Invocation $inv ): array {
        $lines = array();
        foreach ( array_values( $inv->args ) as $key ) {
            $key     = (string) $key;
            $current = $this->get_option( $key );
            $lines[] = false === $current
                ? sprintf( "Option '%s': (not set) → nothing to delete", $key )
                : sprintf( "Option '%s': %s → delete", $key, self::preview( $key, $current ) );
        }
        return $lines;
    }

    
    public function describe_set_autoload( Invocation $inv ): array {
        $option   = (string) $this->arg( $inv, 0 );
        $autoload = (string) $this->arg( $inv, 1 );
        $db       = $this->db();
        $previous = $db->get_row(
            $db->prepare(
                'SELECT `autoload` FROM ' . self::ident( $db->options ) . ' WHERE `option_name` = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the table name is quoted by ident().
                $option
            )
        );
        if ( ! $previous ) {
            return array( sprintf( "Option '%s': (not set) → nothing to change", $option ) );
        }
        return array( sprintf( "Option '%s': autoload '%s' → '%s'", $option, (string) $previous->autoload, $autoload ) );
    }

    
    
    

    







    public function is_site_setting( Invocation $inv ): bool {
        return 'option update' === $inv->command && null !== self::site_setting_argument( (string) $this->arg( $inv, 0 ) );
    }

    




    public static function site_setting_argument( string $key ): ?string {
        $lc = strtolower( trim( $key ) );
        foreach ( self::SITE_SETTINGS as $option => $argument ) {
            if ( strtolower( $option ) === $lc ) {
                return $argument;
            }
        }
        return null;
    }

    




    public static function key_is_secret( string $key ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) && is_readable( dirname( __DIR__, 2 ) . '/class-text-redactor.php' ) ) {
            require_once dirname( __DIR__, 2 ) . '/class-text-redactor.php';
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Redactor' ) && is_readable( dirname( __DIR__, 2 ) . '/history/class-change-redactor.php' ) ) {
            require_once dirname( __DIR__, 2 ) . '/history/class-change-redactor.php';
        }
        if ( class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) && preg_match( \Easy_MCP_AI\Text_Redactor::SENSITIVE_KEY_PATTERN, $key ) ) {
            return true;
        }
        return class_exists( '\\Easy_MCP_AI\\History\\Change_Redactor' ) && \Easy_MCP_AI\History\Change_Redactor::option_name_is_sensitive( $key );
    }

    








    public static function omission( string $name, bool $write ): ?string {
        if ( null === Self_Protection::option( $name, $write ) ) {
            return null;
        }
        $trimmed = trim( $name );
        if ( 1 !== preg_match( '/^[\x21-\x7E]+\z/', $trimmed ) ) {
            return 'bytes';
        }
        $lc = strtolower( $trimmed );
        if ( isset( Self_Protection::SECRET_OPTIONS[ $lc ] ) ) {
            return 'secret';
        }
        foreach ( Self_Protection::TRANSIENT_STORAGE_PREFIXES as $prefix ) {
            if ( 0 === strpos( $lc, $prefix ) ) {
                $base = (string) substr( $lc, strlen( $prefix ) );
                $base = 0 === strpos( $base, 'timeout_' ) ? (string) substr( $base, strlen( 'timeout_' ) ) : $base;
                if ( isset( Self_Protection::UPDATE_TRANSIENTS[ $base ] ) ) {
                    return 'update';
                }
            }
        }
        return 'plugin';
    }

    







    public static function count_kept( array $names ): array {
        $omitted = array();
        $kept    = 0;
        foreach ( $names as $name ) {
            $category = self::omission( (string) $name, false );
            if ( null === $category ) {
                ++$kept;
            } else {
                $omitted[ $category ] = ( $omitted[ $category ] ?? 0 ) + 1;
            }
        }
        return array( $kept, $omitted );
    }

    





    public static function omitted_warning( array $omitted, string $noun, string $nouns ): string {
        $total = array_sum( $omitted );
        return sprintf( self::OMITTED, $total, 1 === $total ? $noun : $nouns, self::omission_summary( $omitted ) );
    }

    




    public static function omission_summary( array $omitted ): string {
        $labels = array(
            'plugin' => array( '%d of the plugin that serves this MCP connection (its settings, state, MCP sessions and rate-limit counters)', null ),
            'secret' => array( "%d of WordPress's secret keys and salts", null ),
            'update' => array( '%d update offers WordPress fetches from WordPress.org (`plugin list`, `theme list` and `core check-update` refresh them)', '%d update offer WordPress fetches from WordPress.org (`plugin list`, `theme list` and `core check-update` refresh it)' ),
            'bytes'  => array( '%d whose names are not plain ASCII', '%d whose name is not plain ASCII' ),
        );
        $parts  = array();
        foreach ( $labels as $category => $label ) {
            if ( ! empty( $omitted[ $category ] ) ) {
                $parts[] = sprintf( 1 === $omitted[ $category ] && null !== $label[1] ? $label[1] : $label[0], $omitted[ $category ] );
            }
        }
        return implode( '; ', $parts );
    }

    





    public static function preview( string $key, $value ): string {
        if ( self::key_is_secret( $key ) ) {
            return self::MASK;
        }
        $text = is_string( $value ) ? "'" . $value . "'" : (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        return strlen( $text ) > 120 ? Envelope::cut( $text, 117 ) . '...' : $text;
    }

    




    public static function esc_like( object $db, string $text ): string {
        if ( method_exists( $db, 'esc_like' ) ) {
            return (string) $db->esc_like( $text );
        }
        return addcslashes( $text, '_%\\' );
    }

    
    public static function ident( string $name ): string {
        return '`' . str_replace( '`', '``', $name ) . '`';
    }

    
    
    

    
    protected function get_option( string $key ) {
        return \get_option( $key );
    }

    
    protected function add_option( string $key, $value, string $autoload ): bool {
        return (bool) \add_option( $key, $value, '', $autoload );
    }

    
    protected function update_option( string $key, $value, ?string $autoload ): bool {
        return (bool) \update_option( $key, $value, $autoload );
    }

    protected function delete_option( string $key ): bool {
        return (bool) \delete_option( $key );
    }

    



    protected function sanitize_option( string $key, $value ) {
        return \sanitize_option( $key, $value );
    }

    
    protected function db(): object {
        global $wpdb;
        return $wpdb;
    }

    
    protected function cache_get( string $key, string $group ) {
        return \wp_cache_get( $key, $group );
    }

    
    protected function cache_set( string $key, $value, string $group ): void {
        \wp_cache_set( $key, $value, $group );
    }

    
    protected function load_alloptions(): array {
        return (array) \wp_load_alloptions( true );
    }

    



    protected function maybe_unserialize( $value ) {
        return \maybe_unserialize( $value );
    }

    







    protected function settings_screen_options(): array {
        $allowed = self::SETTINGS_SCREENS;
        if ( \apply_filters( 'enable_post_by_email_configuration', true ) ) {
            $allowed['writing'][] = 'mailserver_url';
            $allowed['writing'][] = 'mailserver_port';
            $allowed['writing'][] = 'mailserver_login';
            $allowed['writing'][] = 'mailserver_pass';
        }
        $utf8 = function_exists( 'is_utf8_charset' ) ? \is_utf8_charset() : in_array( strtolower( (string) \get_option( 'blog_charset' ) ), array( 'utf8', 'utf-8' ), true );
        if ( ! $utf8 ) {
            $allowed['reading'][] = 'blog_charset';
        }
        if ( (int) \get_site_option( 'initial_db_version' ) < 32453 ) {
            $allowed['writing'][] = 'use_smilies';
            $allowed['writing'][] = 'use_balanceTags';
        }
        $allowed = (array) \apply_filters( 'allowed_options', $allowed );
        $names   = array();
        foreach ( $allowed as $page ) {
            foreach ( (array) $page as $name ) {
                $names[] = (string) $name;
            }
        }
        foreach ( $this->registered_setting_names() as $name ) {
            $names[] = (string) $name;
        }
        return array_values( array_unique( $names ) );
    }

    




    protected function registered_setting_names(): array {
        return function_exists( 'get_registered_settings' ) ? array_map( 'strval', array_keys( (array) \get_registered_settings() ) ) : array();
    }

    
    protected function option_is_recordable( string $name ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Recorder' ) && is_readable( dirname( __DIR__, 2 ) . '/history/class-change-recorder.php' ) ) {
            require_once dirname( __DIR__, 2 ) . '/history/class-change-recorder.php';
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Recorder' ) ) {
            return true;
        }
        return \Easy_MCP_AI\History\Change_Recorder::option_is_recordable( trim( $name ) );
    }

    
    
    

    




    private function guard( string $key, bool $write, Context $ctx ): void {
        $refusal = Self_Protection::option( $key, $write );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        if ( $write ) {
            $argument = self::site_setting_argument( $key );
            if ( null !== $argument ) {
                $ctx->out->error( sprintf( self::SITE_SETTING, trim( $key ), $argument ) );
            }
        }
    }

    





    private function guard_network( string $key, Context $ctx ): void {
        if ( ! \is_multisite() ) {
            return;
        }
        if ( \is_super_admin() && \current_user_can( 'manage_network_options' ) ) {
            return;
        }
        if ( in_array( $key, $this->settings_screen_options(), true ) ) {
            return;
        }
        $ctx->out->error( sprintf( self::NOT_REGISTERED, $key ) );
    }

    
    private function note_recording( string $key, Context $ctx ): void {
        if ( ! $this->option_is_recordable( $key ) ) {
            $ctx->not_captured( sprintf( self::NOT_RECORDED, trim( $key ) ) );
        }
    }

    
    private function arg( Invocation $inv, int $index ): string {
        $args = array_values( $inv->args );
        return isset( $args[ $index ] ) ? $args[ $index ] : '';
    }

    





    private static function described_value( Invocation $inv, string $raw ) {
        if ( 'json' === Read_Value::flag( $inv->assoc, 'format' ) ) {
            $decoded = json_decode( (string) $raw, true );
            return null === $decoded ? $raw : $decoded;
        }
        return $raw;
    }

    
    private static function quoted_list( array $names ): string {
        $quoted = array();
        foreach ( $names as $name ) {
            $quoted[] = "'" . $name . "'";
        }
        return implode( ', ', $quoted );
    }

    








    private static function sort_rows( array &$rows, string $orderby, string $order ): void {
        if ( PHP_VERSION_ID >= 80000 ) {
            usort(
                $rows,
                static function ( object $a, object $b ) use ( $orderby, $order ): int {
                    return self::compare_rows( $a, $b, $orderby, $order );
                }
            );
            return;
        }
        usort(
            $rows,
            static function ( object $a, object $b ) use ( $orderby, $order ): bool {
                return self::compare_rows_as_bool( $a, $b, $orderby, $order );
            }
        );
    }

    
    private static function compare_rows( object $a, object $b, string $orderby, string $order ): int {
        $x = isset( $a->$orderby ) ? $a->$orderby : null;
        $y = isset( $b->$orderby ) ? $b->$orderby : null;
        if ( 'asc' === $order ) {
            return $x > $y ? 1 : ( $y > $x ? -1 : 0 );
        }
        return $x < $y ? 1 : ( $y < $x ? -1 : 0 );
    }

    
    private static function compare_rows_as_bool( object $a, object $b, string $orderby, string $order ): bool {
        $x = isset( $a->$orderby ) ? $a->$orderby : null;
        $y = isset( $b->$orderby ) ? $b->$orderby : null;
        
        return 'asc' === $order
            ? $x > $y
            : $x < $y;
    }
}
