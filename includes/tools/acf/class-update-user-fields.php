<?php
namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_User_Fields extends Base_Tool {
    use Acf_Rest_Tool;

    public function get_name() {
        return 'wp_acf_update_user_fields';
    }

    public function get_description() {
        return 'Updates ACF field values attached to a WordPress user. Pass field names and values as an object in the "fields" parameter (e.g. {"my_field_name": "value"}). Field names are the canonical documented form and are resolved by the ACF REST write path (the same underlying mechanism used by wp_acf_update_fields); field keys (e.g. field_abc123) also work. Reaches every field group assigned to the user, including groups with "Show in REST API" off, when you can edit that user. Any field that was not saved is listed in `fields_ignored` with a `notice` giving the reason.';
    }

    public function get_category() {
        return 'acf';
    }

    public function get_required_capability() {
        return 'edit_users';
    }

    public function get_annotations() {
        return array( 'title' => $this->get_title(), 'readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'user_id' => array( 'type' => 'integer', 'description' => 'The ID of the WordPress user to update ACF fields on.' ),
                'fields'  => array( 'type' => 'object',  'description' => 'Key-value pairs of ACF field names (canonical) and their new values; field keys (e.g. field_abc123) also work.' ),
            ),
            'required' => array( 'user_id', 'fields' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_acf();
        $this->validate_required( $arguments, array( 'user_id', 'fields' ) );
        $user_id = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        $fields  = $this->parse_json_param( $arguments['fields'], 'fields' );
        return array( 'user_id' => $user_id )
            + $this->acf_write( 'user', $user_id, '/wp/v2/users/' . $user_id, $fields );
    }
}
