<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Groups extends Base_Tool {

    use Redirection_Rest;

    public function get_name() {
        return 'wp_redirection_list_groups';
    }

    public function get_description() {
        return 'Lists the Redirection plugin\'s redirect groups. Every redirect belongs to one group; use a group\'s `id` as `group_id` in wp_redirection_create_redirect. No arguments. Returns { groups: [{ id, name, redirects (count), enabled, module, default }], total }. An empty list with a `note` means Redirection\'s setup was never finished, and no redirect can be created until it is. Requires the Redirection plugin active.';
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
            'properties' => new \stdClass(),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_redirection();

        $groups = array_map(
            function ( array $g ): array {
                return array(
                    'id'        => self::int_of( $g, 'id' ),
                    'name'      => self::str_of( $g, 'name' ),
                    'redirects' => self::int_of( $g, 'redirects' ),
                    'enabled'   => ! empty( $g['enabled'] ),
                    'module'    => self::str_of( $g, 'moduleName' ),
                    'default'   => ! empty( $g['default'] ),
                );
            },
            $this->fetch_groups()
        );

        $result = array(
            'groups' => $groups,
            'total'  => count( $groups ),
        );
        if ( empty( $groups ) ) {
            $result['note'] = self::no_groups_message();
        }
        return $result;
    }
}
