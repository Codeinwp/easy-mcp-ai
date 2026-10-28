<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Text_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Cron_Events extends Base_Tool {

    const OVERDUE_AFTER = 3600;

    const MALFORMED_MAX = 50;

    public function get_name() {
        return 'wp_list_cron_events';
    }

    public function get_description() {
        return 'List scheduled WP-Cron events, soonest first. Optional: `hook` (case-insensitive substring filter on the hook name), `limit` (default 50, max 500), `offset` (default 0: skip that many matching events, to page through more than 500 — keep `hook` the same and add `returned` to `offset` until `offset` reaches `total`). Returns { events: [{ hook, key, next_run (ISO 8601 UTC), seconds_until_run (negative when past due), overdue (more than 1 hour past due), schedule: { name, interval_seconds (the registered schedule\'s interval, or the stored one when the schedule is not registered), stored_interval_seconds (the interval saved with this event; 0 when it saved none), display, registered } or null for a one-off event, args (secrets redacted) }], total, returned, offset, overdue_count, malformed_timestamps (cron keys that are not integer timestamps, which WP-CLI warns about and ignores; events under a non-numeric key are not listed), cron_disabled (DISABLE_WP_CRON), now }. `key` identifies one instance (core\'s md5 of its arguments) — pass it to wp_run_cron_event when a hook has several instances. Requires manage_options; on multisite only a network administrator may use it, because the same tool family can run events with network-wide effects.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'hook'  => array(
                    'type'        => 'string',
                    'description' => 'Only events whose hook name contains this text (case-insensitive).',
                ),
                'limit'  => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 500,
                    'default'     => 50,
                    'description' => 'Maximum events to return (1-500).',
                ),
                'offset' => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'default'     => 0,
                    'description' => 'Skip this many matching events first (soonest-first order), to read past `limit`. Default 0.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        Site_Health_Guard::require_network_admin_on_multisite( 'list scheduled cron events' );

        $limit  = isset( $arguments['limit'] ) ? max( 1, min( 500, (int) $arguments['limit'] ) ) : 50;
        $offset = self::offset( $arguments );
        $filter = isset( $arguments['hook'] ) && is_scalar( $arguments['hook'] ) ? trim( (string) $arguments['hook'] ) : '';
        $now    = time();

        list( $events, $total, $overdue ) = self::collect_events( $filter, $offset, $limit, $now );

        $result = array(
            'events'               => $events,
            'total'                => $total,
            'returned'             => count( $events ),
            'offset'               => $offset,
            'overdue_count'        => $overdue,
            'malformed_timestamps' => array(),
            'cron_disabled'        => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'now'                  => Site_Health_Guard::iso_utc( $now ),
        );
        return self::with_malformed_timestamps( $result );
    }

    private static function collect_events( string $filter, int $offset, int $limit, int $now ): array {
        $events  = array();
        $total   = 0;
        $overdue = 0;
        foreach ( Site_Health_Guard::cron_events() as $event ) {
            if ( '' !== $filter && false === stripos( $event['hook'], $filter ) ) {
                continue;
            }
            ++$total;
            $is_overdue = $event['timestamp'] < ( $now - self::OVERDUE_AFTER );
            if ( $is_overdue ) {
                ++$overdue;
            }
            if ( $total <= $offset || count( $events ) >= $limit ) {
                continue;
            }
            $events[] = self::event_row( $event, $now, $is_overdue );
        }
        return array( $events, $total, $overdue );
    }

    private static function event_row( array $event, int $now, bool $is_overdue ): array {
        $schedule = Site_Health_Guard::describe_schedule( $event['schedule'], $event['interval'] );
        if ( null !== $schedule ) {
            $schedule['stored_interval_seconds'] = (int) $event['interval'];
        }
        return array(
            'hook'              => $event['hook'],
            'key'               => $event['key'],
            'next_run'          => Site_Health_Guard::iso_utc( $event['timestamp'] ),
            'seconds_until_run' => $event['timestamp'] - $now,
            'overdue'           => $is_overdue,
            'schedule'          => $schedule,
            'args'              => Text_Redactor::redact_value( $event['args'] ),
        );
    }

    private static function with_malformed_timestamps( array $result ): array {
        $malformed                      = self::malformed_timestamps();
        $result['malformed_timestamps'] = array_slice( $malformed, 0, self::MALFORMED_MAX );
        if ( count( $malformed ) > self::MALFORMED_MAX ) {
            $result['malformed_timestamps_more'] = count( $malformed ) - self::MALFORMED_MAX;
        }
        return $result;
    }

    private static function offset( array $arguments ): int {
        if ( ! isset( $arguments['offset'] ) ) {
            return 0;
        }
        $value = $arguments['offset'];
        if ( ! is_numeric( $value ) || (int) $value < 0 || (float) $value !== (float) (int) $value ) {
            throw new \InvalidArgumentException( 'offset must be a whole number of 0 or more.' );
        }
        return (int) $value;
    }

    private static function malformed_timestamps(): array {
        $crons = function_exists( '_get_cron_array' ) ? \_get_cron_array() : array();
        if ( ! is_array( $crons ) ) {
            return array();
        }
        $out = array();
        foreach ( array_keys( $crons ) as $key ) {
            if ( is_string( $key ) && 'version' !== $key ) {
                $out[] = $key;
            }
        }
        return $out;
    }
}
