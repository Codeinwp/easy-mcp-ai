<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Redirect extends Base_Tool {

    use Redirection_Rest;

    public function get_name() {
        return 'wp_redirection_get_redirect';
    }

    public function get_description() {
        return 'Gets one redirect from the Redirection plugin. Required: `id` (from wp_redirection_list_redirects). Returns { redirect: { id, source, match_type, action_type, code, target, title, group_id, position, enabled, regex, ignore_case, ignore_trailing_slash, query_handling, hits, last_access } }; a match type other than "url" also carries `action_data`. Refused when no redirect has that id. Requires the Redirection plugin active.';
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
                    'description' => 'The redirect id.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_redirection();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        return array( 'redirect' => self::normalize_redirect( $this->require_redirect( $id ) ) );
    }
}
