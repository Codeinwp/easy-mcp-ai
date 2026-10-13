<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Organizers extends Base_Tool {

    public function get_name() {
        return 'wp_tec_list_organizers';
    }

    public function get_description() {
        return 'Lists organizers in The Events Calendar. Optional: `search`, `per_page` (default 20), `page`. Returns { organizers: [{ id, organizer (name), phone, website, email, permalink }], total }. Use the `id` when creating or updating events via the `organizer` parameter. Requires The Events Calendar plugin active.';
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
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of organizers per page (capped at 50 — The Events Calendar\'s hard limit).',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 50,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search term to filter organizers.',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            throw new \RuntimeException( 'The Events Calendar is not active on this site. Please install and activate The Events Calendar plugin.' );
        }

        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 50, max( 1, absint( $arguments['per_page'] ) ) ) : 20,
            'page'     => isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1,
        );

        if ( isset( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        $data       = $this->rest_request( 'GET', '/tribe/events/v1/organizers', $params );
        $organizers = $data['organizers'] ?? $data;

        if ( ! is_array( $organizers ) ) {
            return array( 'organizers' => array(), 'total' => 0 );
        }

        $result = array_map( function( $o ) {
            return array(
                'id'        => $o['id'] ?? null,
                'organizer' => $o['organizer'] ?? null,
                'phone'     => $o['phone'] ?? null,
                'website'   => $o['website'] ?? null,
                'email'     => $o['email'] ?? null,
                'permalink' => $o['url'] ?? null,
            );
        }, $organizers );

        return array(
            'organizers' => $result,
            'total'      => $data['total'] ?? count( $result ),
        );
    }
}
