<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Venue extends Base_Tool {

    public function get_name() {
        return 'wp_tec_get_venue';
    }

    public function get_description() {
        return 'Gets a single venue from The Events Calendar by ID. Required: `id` (venue post ID — get it from `wp_tec_list_venues`). Returns the raw TEC venue object including: id, venue (name), address, city, state, province, zip, country, phone, website, show_map, geo_lat, geo_lng, permalink. Requires The Events Calendar plugin active.';
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
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The venue post ID.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            throw new \RuntimeException( 'The Events Calendar is not active on this site. Please install and activate The Events Calendar plugin.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id   = $this->parse_required_id( $arguments['id'], 'id' );
        $data = $this->rest_request( 'GET', '/tribe/events/v1/venues/' . $id, array() );

        return $data;
    }
}
