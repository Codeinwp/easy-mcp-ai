<?php
namespace Easy_MCP_AI\Tools\Events_Calendar;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Event extends Base_Tool {

    




    const FIELDS = array(
        'title'              => 'title',
        'start_date'         => 'start_date',
        'end_date'           => 'end_date',
        'description'        => 'description',
        'all_day'            => 'all_day',
        'timezone'           => 'timezone',
        'venue'              => 'venue',
        'organizer'          => 'organizer',
        'cost'               => 'cost',
        'url'                => 'website',
        'status'             => 'status',
        'image'              => 'image',
        'featured'           => 'featured',
        'sticky'             => 'sticky',
        'show_map'           => 'show_map',
        'show_map_link'      => 'show_map_link',
        'hide_from_listings' => 'hide_from_listings',
    );

    const BOOLEAN_FIELDS = array( 'all_day', 'featured', 'sticky', 'show_map', 'show_map_link', 'hide_from_listings' );

    public function get_name() {
        return 'wp_tec_update_event';
    }

    public function get_description() {
        return 'Updates an existing event in The Events Calendar. Only the fields you send change; every other field keeps its value. Required: `id` (event post ID). Optional: `title`, `start_date` and `end_date` (YYYY-MM-DD HH:MM:SS, in the event\'s timezone; either may be sent alone), `description` (HTML allowed), `all_day` (boolean), `timezone` (e.g. "America/New_York"), `venue` (venue ID — use `wp_tec_list_venues`), `organizer` (array of organizer IDs — use `wp_tec_list_organizers`; an empty array removes every organizer), `cost` (string, e.g. "10.00"), `url` (event website URL — sets the event\'s "website" field), `status` (publish/draft/pending), `image` (attachment ID of the featured image; 0 removes it), `featured`, `sticky`, `show_map`, `show_map_link`, `hide_from_listings` (booleans). Returns { id, title, start_date, end_date, url }. Note: the returned `url` is the event\'s permalink, NOT the website URL you set via the input `url` param — same field name, different meaning on input vs output. Requires The Events Calendar plugin active.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id'                 => array(
                    'type'        => 'integer',
                    'description' => 'The event post ID.',
                ),
                'title'              => array(
                    'type'        => 'string',
                    'description' => 'The event title.',
                ),
                'start_date'         => array(
                    'type'        => 'string',
                    'description' => 'Event start date and time (YYYY-MM-DD HH:MM:SS).',
                ),
                'end_date'           => array(
                    'type'        => 'string',
                    'description' => 'Event end date and time (YYYY-MM-DD HH:MM:SS).',
                ),
                'description'        => array(
                    'type'        => 'string',
                    'description' => 'Event description. HTML is allowed and filtered by WordPress for the calling user.',
                ),
                'all_day'            => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the event is an all-day event.',
                ),
                'timezone'           => array(
                    'type'        => 'string',
                    'description' => 'Event timezone, as a PHP timezone name (e.g. "America/New_York") or a UTC offset (e.g. "UTC+2").',
                ),
                'venue'              => array(
                    'type'        => 'integer',
                    'description' => 'Venue post ID.',
                ),
                'organizer'          => array(
                    'type'        => 'array',
                    'description' => 'Array of organizer post IDs. Pass a single ID as a one-element array. An empty array removes every organizer.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'cost'               => array(
                    'type'        => 'string',
                    'description' => 'Event cost.',
                ),
                'url'                => array(
                    'type'        => 'string',
                    'description' => 'Event website URL.',
                ),
                'status'             => array(
                    'type'        => 'string',
                    'description' => 'Event post status.',
                    'enum'        => array( 'publish', 'draft', 'pending' ),
                ),
                'image'              => array(
                    'type'        => 'integer',
                    'description' => 'Attachment ID of an image in the media library to use as the featured image. 0 removes the featured image.',
                ),
                'featured'           => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the event is featured.',
                ),
                'sticky'             => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the event is sticky (pinned) in month view.',
                ),
                'show_map'           => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the venue map is shown on the event page.',
                ),
                'show_map_link'      => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the venue map link is shown on the event page.',
                ),
                'hide_from_listings' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the event is hidden from event listings.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    










    public function sent_fields( array $arguments ) {
        $sent = array();
        foreach ( self::FIELDS as $argument => $rest_name ) {
            if ( isset( $arguments[ $argument ] ) ) {
                $sent[] = $rest_name;
            }
        }
        return $sent;
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'Tribe__Events__Main' ) ) {
            throw new \RuntimeException( 'The Events Calendar is not active on this site. Please install and activate The Events Calendar plugin.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );
        
        
        
        if ( ! current_user_can( 'edit_post', $id ) ) {
            throw new \RuntimeException( 'You are not allowed to edit this event, or it does not exist.' );
        }
        $params = $this->rest_params( $arguments );

        
        
        $this->require_compat_shim( 'tec-event-update-fill', $arguments );

        $data = $this->rest_request( 'PUT', '/tribe/events/v1/events/' . $id, $params );

        return array(
            'id'         => $data['id'],
            'title'      => $data['title'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'url'        => $data['url'],
        );
    }

    





    private function rest_params( array $arguments ) {
        $params = array();

        foreach ( array( 'title', 'start_date', 'end_date', 'cost', 'status', 'timezone' ) as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $params[ $field ] = sanitize_text_field( $arguments[ $field ] );
            }
        }
        
        
        if ( isset( $arguments['description'] ) ) {
            $params['description'] = (string) $arguments['description'];
        }
        foreach ( self::BOOLEAN_FIELDS as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $params[ $field ] = rest_sanitize_boolean( $arguments[ $field ] );
            }
        }
        if ( isset( $arguments['venue'] ) ) {
            $params['venue'] = absint( $arguments['venue'] );
        }
        if ( isset( $arguments['organizer'] ) ) {
            $organizers = $this->organizer_ids( $arguments['organizer'] );
            
            
            
            if ( $organizers ) {
                $params['organizer'] = $organizers;
            }
        }
        if ( isset( $arguments['url'] ) ) {
            $params['website'] = sanitize_url( $arguments['url'] );
        }
        if ( isset( $arguments['image'] ) ) {
            $image = $this->image_id( $arguments['image'] );
            
            if ( $image ) {
                
                $params['image'] = (string) $image;
            }
        }

        return $params;
    }

    






    private function organizer_ids( $value ) {
        $raw = is_string( $value ) ? json_decode( $value, true ) : $value;
        if ( array() === $raw ) {
            return array();
        }
        $ids = is_array( $raw )
            ? array_values( array_filter( array_map( 'absint', $raw ) ) )
            : array_values( array_filter( array( absint( $raw ) ) ) );
        if ( ! $ids ) {
            throw new \InvalidArgumentException( 'Invalid organizer: send an array of organizer post IDs, or an empty array to remove every organizer.' );
        }
        return $ids;
    }

    






    private function image_id( $value ) {
        if ( 0 === $value || '0' === $value ) {
            return 0;
        }
        $id = $this->parse_required_id( $value, 'image' );
        if ( ! wp_attachment_is_image( $id ) ) {
            throw new \InvalidArgumentException( 'Invalid image: must be the attachment ID of an image in the media library, or 0 to remove the featured image.' );
        }
        return $id;
    }
}
