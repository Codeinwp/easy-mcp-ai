<?php
namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_User_Fields extends Base_Tool {
    use Acf_Rest_Tool;

    public function get_name() {
        return 'wp_acf_get_user_fields';
    }

    public function get_description() {
        return 'Gets ACF (Advanced Custom Fields) field values attached to a WordPress user. Field groups need location rules targeting Users; groups with "Show in REST API" off are included when you can edit that user.';
    }

    public function get_category() {
        return 'acf';
    }

    public function get_required_capability() {
        return 'edit_users';
    }

    public function get_annotations() {
        return array( 'title' => $this->get_title(), 'readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'user_id' => array( 'type' => 'integer', 'description' => 'The ID of the WordPress user to retrieve ACF fields from.' ),
            ),
            'required' => array( 'user_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_acf();
        $user_id = $this->parse_required_id( $arguments['user_id'] ?? null, 'user_id' );
        return array( 'user_id' => $user_id )
            + $this->acf_read( 'user', $user_id, '/wp/v2/users/' . $user_id );
    }
}
