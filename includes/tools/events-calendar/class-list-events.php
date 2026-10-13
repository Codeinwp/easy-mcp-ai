<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Events extends Base_Tool {

    public function get_name() {
        return 'wp_tec_list_events';
    }

    public function get_description() {
        return 'Lists events from The Events Calendar with optional filtering by date range, venue, organizer, category, tag, post status, featured flag, and search. `per_page` is capped at 50 (TEC hard limit). Returns id, title, start_date, end_date, all_day, venue, organizer, and permalink. Note: `organizer` in the result is only the first organizer\'s name, even if the event has multiple organizers. Requires The Events Calendar plugin active.';
    }

    public function get_category() {
        return 'events-calendar';
    }

    public function get_required_capability() {
        return 'edit_tribe_events';
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
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of events per page (TEC caps this at 50).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 50,
                ),
                'page'       => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'search'     => array(
                    'type'        => 'string',
                    'description' => 'Search term to filter events.',
                ),
                'start_date' => array(
                    'type'        => 'string',
                    'description' => 'Filter events starting on or after this date (YYYY-MM-DD).',
                ),
                'end_date'   => array(
                    'type'        => 'string',
                    'description' => 'Filter events ending on or before this date (YYYY-MM-DD).',
                ),
                'venue'      => array(
                    'type'        => 'integer',
                    'description' => 'Filter by venue ID.',
                ),
                'organizer'  => array(
                    'type'        => 'integer',
                    'description' => 'Filter by organizer ID.',
                ),
                'categories' => array(
                    'type'        => 'array',
                    'description' => 'Filter by event category term IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'tags'       => array(
                    'type'        => 'array',
                    'description' => 'Filter by event tag term IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'featured'   => array(
                    'type'        => 'boolean',
                    'description' => 'Limit results to featured events only.',
                ),
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'One status, or several joined by commas ("publish,draft"). Omit it, or pass "any", to get every status except trash.',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            throw new \RuntimeException( 'The Events Calendar is not active on this site. Please install and activate The Events Calendar plugin.' );
        }

        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $per_page = isset( $arguments['per_page'] ) ? min( 50, max( 1, absint( $arguments['per_page'] ) ) ) : 10;

        
        
        
        $params = array(
            'per_page' => $per_page,
            'page'     => $page,
        );
        if ( isset( $arguments['search'] ) )     $params['search']     = sanitize_text_field( $arguments['search'] );
        if ( isset( $arguments['start_date'] ) ) $params['start_date'] = sanitize_text_field( $arguments['start_date'] );
        if ( isset( $arguments['end_date'] ) )   $params['end_date']   = sanitize_text_field( $arguments['end_date'] );
        if ( isset( $arguments['venue'] ) )      $params['venue']      = absint( $arguments['venue'] );
        if ( isset( $arguments['organizer'] ) )  $params['organizer']  = absint( $arguments['organizer'] );
        if ( isset( $arguments['featured'] ) )   $params['featured']   = (bool) $arguments['featured'];
        if ( isset( $arguments['status'] ) ) {
            
            
            
            
            
            
            
            $statuses = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $arguments['status'] ) ) ) );
            if ( ! empty( $statuses ) && ! in_array( 'any', $statuses, true ) ) {
                $params['status'] = implode( ',', $statuses );
            }
        }
        if ( ! empty( $arguments['categories'] ) ) {
            $params['categories'] = array_values( array_filter( array_map( 'absint', $this->parse_json_param( $arguments['categories'], 'categories' ) ) ) );
        }
        if ( ! empty( $arguments['tags'] ) ) {
            $params['tags'] = array_values( array_filter( array_map( 'absint', $this->parse_json_param( $arguments['tags'], 'tags' ) ) ) );
        }

        
        
        
        
        
        $request = new \WP_REST_Request( 'GET', '/tribe/events/v1/events' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            $code  = (string) $error->get_error_code();
            $status = $error->get_error_data();
            $status = is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 0;
            if (
                'event-archive-page-not-found' === $code
                || false !== strpos( $code, 'not-found' )
                || false !== strpos( $code, 'not_found' )
                || 404 === $status
            ) {
                return array_merge(
                    array( 'events' => array() ),
                    $this->pagination_meta( null, $page, $per_page, 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data   = $response->get_data();
        $events = $data['events'] ?? $data;

        if ( ! is_array( $events ) ) {
            return array_merge(
                array( 'events' => array() ),
                $this->pagination_meta( $response, $page, $per_page, 0 )
            );
        }

        $mapped = array_map( function ( $e ) {
            return array(
                'id'         => $e['id'],
                'title'      => $e['title'],
                'start_date' => $e['start_date'],
                'end_date'   => $e['end_date'],
                'all_day'    => $e['all_day'],
                'venue'      => $e['venue']['venue'] ?? null,
                'organizer'  => $e['organizer'][0]['organizer'] ?? null,
                'permalink'  => $e['url'],
            );
        }, $events );

        return array_merge(
            array( 'events' => $mapped ),
            $this->pagination_meta( $response, $page, $per_page, count( $mapped ) )
        );
    }
}
