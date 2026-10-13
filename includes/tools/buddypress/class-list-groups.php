<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Groups extends Base_Tool {

    public function get_name() {
        return 'wp_bp_list_groups';
    }

    public function get_description() {
        return 'Lists BuddyPress groups. Optional: `search`, `status` (public/private/hidden — default: all visible to user), `per_page` (default 20), `page`. Returns { groups: [...], page } where each group includes id, name, slug, description, status, total_member_count, date_created, link. Private and hidden groups are only returned if the authenticated user is a member or admin. Requires BuddyPress Groups component enabled.';
    }

    public function get_category() {
        return 'buddypress';
    }

    public function get_required_capability() {
        return 'read';
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
                    'type'    => 'integer',
                    'default' => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'    => 'integer',
                    'default' => 1,
                ),
                'search'   => array(
                    'type' => 'string',
                ),
                'status'   => array(
                    'type' => 'string',
                    'enum' => array( 'public', 'private', 'hidden' ),
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'groups' ) ) {
            throw new \RuntimeException( 'The BuddyPress Groups component is not enabled. Go to Settings → BuddyPress → Components and enable it.' );
        }

        $page   = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $params = array(
            'per_page'        => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 20,
            'page'            => $page,
            
            'populate_extras' => true,
        );

        if ( isset( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }
        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }

        $data = $this->rest_request( 'GET', '/buddypress/v1/groups', $params );

        return array(
            'groups' => $data,
            'page'   => $page,
        );
    }
}
