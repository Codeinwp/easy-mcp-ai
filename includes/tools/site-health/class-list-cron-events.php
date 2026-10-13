<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Text_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










class List_Cron_Events extends Base_Tool {

    
    const OVERDUE_AFTER = 3600;

    public function get_name() {
        return 'wp_list_cron_events';
    }

    public function get_description() {
        return 'List scheduled WP-Cron events, soonest first. Optional: `hook` (case-insensitive substring filter on the hook name), `limit` (default 50, max 500). Returns { events: [{ hook, key, next_run (ISO 8601 UTC), seconds_until_run (negative when past due), overdue (more than 1 hour past due), schedule: { name, interval_seconds, display, registered } or null for a one-off event, args (secrets redacted) }], total, returned, overdue_count, cron_disabled (DISABLE_WP_CRON), now }. `key` identifies one instance (core\'s md5 of its arguments) — pass it to wp_run_cron_event when a hook has several instances. Requires manage_options; on multisite only a network administrator may use it, because the same tool family can run events with network-wide effects.';
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
                'limit' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 500,
                    'default'     => 50,
                    'description' => 'Maximum events to return (1-500).',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        Site_Health_Guard::require_network_admin_on_multisite( 'list scheduled cron events' );

        $limit  = isset( $arguments['limit'] ) ? max( 1, min( 500, (int) $arguments['limit'] ) ) : 50;
        $filter = isset( $arguments['hook'] ) && is_scalar( $arguments['hook'] ) ? trim( (string) $arguments['hook'] ) : '';
        $now    = time();

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
            if ( count( $events ) >= $limit ) {
                continue; 
            }
            $events[] = array(
                'hook'              => $event['hook'],
                'key'               => $event['key'],
                'next_run'          => Site_Health_Guard::iso_utc( $event['timestamp'] ),
                'seconds_until_run' => $event['timestamp'] - $now,
                'overdue'           => $is_overdue,
                'schedule'          => Site_Health_Guard::describe_schedule( $event['schedule'], $event['interval'] ),
                
                
                
                'args'              => Text_Redactor::redact_value( $event['args'] ),
            );
        }

        return array(
            'events'        => $events,
            'total'         => $total,
            'returned'      => count( $events ),
            'overdue_count' => $overdue,
            'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
            'now'           => Site_Health_Guard::iso_utc( $now ),
        );
    }
}
