<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Event extends Base_Tool {

    public function get_name() {
        return 'wp_tec_create_event';
    }

    public function get_description() {
        return 'Creates a new event in The Events Calendar. Required: title, start_date, end_date (YYYY-MM-DD HH:MM:SS format). Optional: description, venue, organizer, all_day, cost, url, status. Requires The Events Calendar plugin active.';
    }

    public function get_category() {
        return 'events-calendar';
    }

    public function get_required_capability() {
        return 'publish_tribe_events';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'title'       => array(
                    'type'        => 'string',
                    'description' => 'The event title.',
                ),
                'start_date'  => array(
                    'type'        => 'string',
                    'description' => 'Event start date and time (YYYY-MM-DD HH:MM:SS).',
                ),
                'end_date'    => array(
                    'type'        => 'string',
                    'description' => 'Event end date and time (YYYY-MM-DD HH:MM:SS).',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'Event description.',
                ),
                'all_day'     => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the event is an all-day event.',
                    'default'     => false,
                ),
                'venue'       => array(
                    'type'        => 'integer',
                    'description' => 'Venue post ID.',
                ),
                'organizer'   => array(
                    'type'        => 'array',
                    'description' => 'Array of organizer post IDs. Pass a single ID as a one-element array.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'cost'        => array(
                    'type'        => 'string',
                    'description' => 'Event cost.',
                ),
                'url'     => array(
                    'type'        => 'string',
                    'description' => 'Event website URL.',
                ),
                'status'      => array(
                    'type'        => 'string',
                    'description' => 'Event post status.',
                    'enum'        => array( 'publish', 'draft', 'pending' ),
                    'default'     => 'draft',
                ),
            ),
            'required'   => array( 'title', 'start_date', 'end_date' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            throw new \RuntimeException( 'The Events Calendar is not active on this site. Please install and activate The Events Calendar plugin.' );
        }

        $this->validate_required( $arguments, array( 'title', 'start_date', 'end_date' ) );

        $params = array(
            'title'      => sanitize_text_field( $arguments['title'] ),
            'start_date' => sanitize_text_field( $arguments['start_date'] ),
            'end_date'   => sanitize_text_field( $arguments['end_date'] ),
            'status'     => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'draft',
        );

        if ( isset( $arguments['description'] ) ) {
            
            
            $params['description'] = (string) $arguments['description'];
        }
        if ( isset( $arguments['url'] ) ) {
            $params['website'] = sanitize_url( $arguments['url'] );
        }
        if ( isset( $arguments['all_day'] ) ) {
            $params['all_day'] = (bool) $arguments['all_day'];
        }
        if ( isset( $arguments['venue'] ) && absint( $arguments['venue'] ) > 0 ) {
            $params['venue'] = absint( $arguments['venue'] );
        }
        if ( isset( $arguments['organizer'] ) ) {
            $raw_organizer = is_string( $arguments['organizer'] ) ? json_decode( $arguments['organizer'], true ) : $arguments['organizer'];
            $organizer_ids = is_array( $raw_organizer )
                ? array_values( array_filter( array_map( 'absint', $raw_organizer ) ) )
                : array_filter( array( absint( $raw_organizer ) ) );
            if ( ! empty( $organizer_ids ) ) {
                $params['organizer'] = $organizer_ids;
            }
        }
        if ( isset( $arguments['cost'] ) ) {
            $params['cost'] = sanitize_text_field( $arguments['cost'] );
        }

        $this->maybe_force_draft( $params );

        $data = $this->rest_request( 'POST', '/tribe/events/v1/events', $params );

        return array(
            'id'         => $data['id'],
            'title'      => $data['title'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'url'        => $data['url'],
        );
    }
}
