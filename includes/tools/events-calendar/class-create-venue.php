<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Venue extends Base_Tool {

    public function get_name() {
        return 'wp_tec_create_venue';
    }

    public function get_description() {
        return 'Creates a new venue in The Events Calendar. Required: `venue` (venue name). Optional: `address`, `city`, `country`, `state`, `zip`, `phone`, `url`, `allow_duplicate`. Returns { id, venue (name), website }. If a venue with the same name already exists, nothing is written and the error names it: run `wp_tec_list_venues` with `search` first and reuse the existing ID, or set `allow_duplicate` to true when a second venue with that name is really wanted. Requires The Events Calendar plugin active.';
    }

    public function get_category() {
        return 'events-calendar';
    }

    public function get_required_capability() {
        return 'edit_tribe_venues';
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
                'venue'   => array(
                    'type'        => 'string',
                    'description' => 'The venue name.',
                ),
                'address' => array(
                    'type'        => 'string',
                    'description' => 'Street address of the venue.',
                ),
                'city'    => array(
                    'type'        => 'string',
                    'description' => 'City of the venue.',
                ),
                'country' => array(
                    'type'        => 'string',
                    'description' => 'Country of the venue.',
                ),
                'state'   => array(
                    'type'        => 'string',
                    'description' => 'State or province of the venue.',
                ),
                'zip'     => array(
                    'type'        => 'string',
                    'description' => 'ZIP or postal code of the venue.',
                ),
                'phone'   => array(
                    'type'        => 'string',
                    'description' => 'Phone number of the venue.',
                ),
                'url' => array(
                    'type'        => 'string',
                    'description' => 'Website URL of the venue.',
                ),
                'allow_duplicate' => array(
                    'type'        => 'boolean',
                    'description' => 'Create the venue even when one with the same name already exists. Default false: the call is refused and the existing one is named.',
                ),
            ),
            'required'   => array( 'venue' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            throw new \RuntimeException( 'The Events Calendar is not active on this site. Please install and activate The Events Calendar plugin.' );
        }

        $this->validate_required( $arguments, array( 'venue' ) );

        $name = sanitize_text_field( $arguments['venue'] );
        if ( ! $this->allows_duplicate( $arguments ) ) {
            $this->refuse_existing_title( 'tribe_venue', $name, array( '_VenueAddress', '_VenueCity' ), 'venue', 'Pass that ID as `venue` to `wp_tec_create_event` or `wp_tec_update_event` to use it.', 'wp_tec_list_venues' );
        }

        
        
        $this->require_compat_shim( 'tec-no-duplicate-reuse', $arguments );

        $params = array(
            'venue' => $name,
        );

        if ( isset( $arguments['address'] ) ) {
            $params['address'] = sanitize_text_field( $arguments['address'] );
        }
        if ( isset( $arguments['city'] ) ) {
            $params['city'] = sanitize_text_field( $arguments['city'] );
        }
        if ( isset( $arguments['country'] ) ) {
            $params['country'] = sanitize_text_field( $arguments['country'] );
        }
        if ( isset( $arguments['state'] ) ) {
            $params['state'] = sanitize_text_field( $arguments['state'] );
        }
        if ( isset( $arguments['zip'] ) ) {
            $params['zip'] = sanitize_text_field( $arguments['zip'] );
        }
        if ( isset( $arguments['phone'] ) ) {
            $params['phone'] = sanitize_text_field( $arguments['phone'] );
        }
        if ( isset( $arguments['url'] ) ) {
            $params['website'] = sanitize_text_field( $arguments['url'] );
        }

        $data = $this->rest_request( 'POST', '/tribe/events/v1/venues', $params );

        return array(
            'id'      => $data['id'],
            'venue'   => $data['venue'],
            'website' => $data['website'] ?? null,
        );
    }
}
