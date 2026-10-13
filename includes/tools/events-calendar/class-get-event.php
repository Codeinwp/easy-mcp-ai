<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Event extends Base_Tool {

    public function get_name() {
        return 'wp_tec_get_event';
    }

    public function get_description() {
        return 'Gets a single event from The Events Calendar by ID. Required: `id`. Returns the raw TEC REST API event object — key fields: id, title, description, start_date (YYYY-MM-DD HH:MM:SS), end_date, all_day (boolean), url (event permalink), status, cost (string), categories (array), tags (array), venue (object with id/venue/address/city/state/zip/country/phone/website), organizer (array of objects with id/organizer/phone/website/email), timezone. Requires The Events Calendar plugin active.';
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
                    'description' => 'The event post ID.',
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
        $data = $this->rest_request( 'GET', '/tribe/events/v1/events/' . $id, array() );

        return $data;
    }
}
