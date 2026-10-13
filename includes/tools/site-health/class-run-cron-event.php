<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Text_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















class Run_Cron_Event extends Base_Tool {

    
    const OWN_PREFIX = 'easy_mcp_ai_';

    
    const OUTPUT_LIMIT = 16384;

    
    const TIME_LIMIT = 300;

    public function get_name() {
        return 'wp_run_cron_event';
    }

    public function get_description() {
        return 'Run one scheduled WP-Cron event now, the way wp-cron.php and `wp cron event run` do: a recurring event is rescheduled to its next occurrence and the due instance removed, a one-off event is removed, then the hook fires with its STORED arguments. Required: `hook` (exact name). Optional: `key` (instance key from wp_list_cron_events) or `args` (the exact stored argument array) to pick one instance when the hook has several; with neither, exactly one instance must exist. Refuses a hook that is not scheduled, and every easy_mcp_ai_* hook (use the plugin admin screens for those). The callback runs as no logged-in user, exactly like real cron, so it cannot borrow your permissions. Returns { hook, key, args (redacted), schedule, next_run (after this run, or null), had_callbacks, duration_ms, output (echoed text, redacted, truncated at 16 KB), output_truncated, error (null, or the callback\'s exception message) }. Requires manage_options; on multisite only a network administrator, because core\'s update-check and automatic-update events act on the whole network.';
    }

    public function get_category() {
        return 'site_health';
    }

    public function get_required_capability() {
        return 'manage_options';
    }

    public function get_annotations() {
        
        
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'hook' => array(
                    'type'        => 'string',
                    'description' => 'Exact hook name of a scheduled event, as listed by wp_list_cron_events.',
                ),
                'key'  => array(
                    'type'        => 'string',
                    'description' => 'Instance key from wp_list_cron_events, to pick one instance of a hook scheduled several times.',
                ),
                'args' => array(
                    'type'        => 'array',
                    'items'       => new \stdClass(),
                    'description' => 'The exact stored argument array of the instance to run. Must match the scheduled arguments exactly; it never replaces them.',
                ),
            ),
            'required'   => array( 'hook' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'hook' ) );
        Site_Health_Guard::require_network_admin_on_multisite( 'run cron events' );

        $hook = is_string( $arguments['hook'] ) ? trim( $arguments['hook'] ) : '';
        if ( '' === $hook ) {
            throw new \InvalidArgumentException( 'hook must be a non-empty string.' );
        }
        
        
        
        if ( 0 === strpos( $hook, self::OWN_PREFIX ) ) {
            throw new \RuntimeException( sprintf( 'Refused: %s belongs to Easy MCP AI. Its scheduled tasks are run from the plugin\'s own admin screens, not through this tool.', $hook ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $event = $this->select_event( $hook, $arguments );

        return $this->consume_and_run( $event );
    }

    







    private function select_event( $hook, array $arguments ) {
        $matches = array();
        foreach ( Site_Health_Guard::cron_events() as $event ) {
            if ( $event['hook'] === $hook ) {
                $matches[] = $event;
            }
        }
        if ( empty( $matches ) ) {
            throw new \RuntimeException( sprintf( 'No scheduled event for hook %s. Only scheduled events can be run; see wp_list_cron_events.', $hook ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $want = self::wanted_key( $arguments );
        if ( null !== $want ) {
            $matches = array_values( array_filter( $matches, static function ( $e ) use ( $want ) {
                return $e['key'] === $want;
            } ) );
            if ( empty( $matches ) ) {
                throw new \RuntimeException( sprintf( 'Hook %s has no scheduled instance with those arguments. Use the key from wp_list_cron_events.', $hook ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        self::refuse_if_ambiguous( $hook, $matches );

        
        
        return $matches[0];
    }

    









    private static function wanted_key( array $arguments ) {
        if ( isset( $arguments['key'] ) && '' !== $arguments['key'] ) {
            return (string) $arguments['key'];
        }
        if ( ! array_key_exists( 'args', $arguments ) || null === $arguments['args'] ) {
            return null;
        }
        if ( ! is_array( $arguments['args'] ) ) {
            throw new \InvalidArgumentException( 'args must be an array.' );
        }
        return md5( serialize( $arguments['args'] ) );
    }

    








    private static function refuse_if_ambiguous( $hook, array $matches ) {
        if ( count( array_unique( array_column( $matches, 'key' ) ) ) <= 1 ) {
            return;
        }
        $list = array();
        foreach ( $matches as $e ) {
            $list[] = $e['key'] . ' (args ' . wp_json_encode( Text_Redactor::redact_value( $e['args'] ) ) . ', next run ' . Site_Health_Guard::iso_utc( $e['timestamp'] ) . ')';
        }
        throw new \RuntimeException( sprintf( 'Hook %1$s is scheduled %2$d times with different arguments; pass `key` to choose one: %3$s', $hook, count( $matches ), implode( '; ', $list ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    













    private function consume_and_run( array $event ) {
        $hook = $event['hook'];
        $args = $event['args'];
        $ts   = $event['timestamp'];

        if ( false !== $event['schedule'] ) {
            $rescheduled = \wp_reschedule_event( $ts, $event['schedule'], $hook, $args, true );
            if ( \is_wp_error( $rescheduled ) || false === $rescheduled ) {
                $reason = \is_wp_error( $rescheduled ) ? $rescheduled->get_error_message() : 'unknown error';
                throw new \RuntimeException( sprintf( 'Not run: the recurring event %1$s could not be rescheduled (%2$s). Nothing was changed.', $hook, $reason ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $unscheduled = \wp_unschedule_event( $ts, $hook, $args, true );
        if ( \is_wp_error( $unscheduled ) || false === $unscheduled ) {
            $reason = \is_wp_error( $unscheduled ) ? $unscheduled->get_error_message() : 'unknown error';
            
            
            throw new \RuntimeException( sprintf( 'Not run: the due instance of %1$s could not be removed from the schedule (%2$s), so running it now could run it twice.', $hook, $reason ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $had_callbacks = function_exists( 'has_action' ) ? (bool) \has_action( $hook ) : true;
        $run           = $this->run_callbacks( $hook, $args );

        $next = null;
        foreach ( Site_Health_Guard::cron_events() as $row ) {
            if ( $row['hook'] === $hook && $row['key'] === $event['key'] ) {
                $next = $row['timestamp'];
                break;
            }
        }

        return array(
            'hook'             => $hook,
            'key'              => $event['key'],
            'args'             => Text_Redactor::redact_value( $args ),
            'schedule'         => Site_Health_Guard::describe_schedule( $event['schedule'], $event['interval'] ),
            'scheduled_for'    => Site_Health_Guard::iso_utc( $ts ),
            'next_run'         => Site_Health_Guard::iso_utc( $next ),
            'had_callbacks'    => $had_callbacks,
            'duration_ms'      => $run['duration_ms'],
            'output'           => $run['output'],
            'output_truncated' => $run['output_truncated'],
            'error'            => $run['error'],
        );
    }

    




















    private function run_callbacks( $hook, array $args ) {
        $this->raise_time_limit();

        $captured  = '';
        $truncated = false;
        $error     = null;
        $previous  = \get_current_user_id();
        $level     = ob_get_level();
        $started   = microtime( true );

        ob_start(
            static function ( $buffer ) use ( &$captured, &$truncated ) {
                $room = self::OUTPUT_LIMIT - strlen( $captured );
                if ( $room > 0 ) {
                    $captured .= substr( $buffer, 0, $room );
                }
                if ( strlen( $buffer ) > max( 0, $room ) ) {
                    $truncated = true;
                }
                return '';
            },
            4096
        );
        \add_filter( 'wp_doing_cron', '__return_true', PHP_INT_MAX );
        \wp_set_current_user( 0 );
        try {
            \do_action_ref_array( $hook, $args );
        } catch ( \Throwable $e ) {
            $error = get_class( $e ) . ': ' . $e->getMessage();
        } finally {
            \wp_set_current_user( $previous );
            \remove_filter( 'wp_doing_cron', '__return_true', PHP_INT_MAX );
            
            while ( ob_get_level() > $level ) {
                ob_end_clean();
            }
        }

        if ( null !== $error && class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) ) {
            $error = \Easy_MCP_AI\MCP\Server::sanitize_error_message( $error );
        }

        return array(
            'duration_ms'      => (int) round( ( microtime( true ) - $started ) * 1000 ),
            'output'           => Text_Redactor::redact( $captured ),
            'output_truncated' => $truncated,
            'error'            => null === $error ? null : Text_Redactor::redact( $error ),
        );
    }

    
    private function raise_time_limit() {
        $current = (int) ini_get( 'max_execution_time' );
        if ( 0 === $current || $current >= self::TIME_LIMIT ) {
            return;
        }
        if ( function_exists( 'set_time_limit' ) && false === strpos( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
            @set_time_limit( self::TIME_LIMIT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }
}
