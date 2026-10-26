<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Form extends Base_Tool {

    use CF7_Rest;

    public function get_name() {
        return 'wp_cf7_delete_form';
    }

    public function get_description() {
        return 'Permanently deletes a contact form of the Contact Form 7 plugin (there is no trash). Pages that place it with its shortcode show a "not found" notice instead. Required: `id` (from wp_cf7_list_forms). Returns { deleted: true, form: { id, title } }. Requires the Contact Form 7 plugin active.';
    }

    public function get_required_capability() {
        return self::cf7_capability( 'wpcf7_delete_contact_form' );
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The contact form id (from wp_cf7_list_forms).',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $target = $this->preview_target( $arguments );
        if ( null === $target ) {
            return '';
        }
        return sprintf(
            /* translators: 1: contact form id, 2: contact form title */
            __( 'Permanently delete contact form #%1$d "%2$s"', 'easy-mcp-ai' ),
            $target['id'],
            $target['title']
        );
    }

    public function execute( array $arguments ) {
        $this->require_cf7();
        $this->validate_required( $arguments, array( 'id' ) );
        $id   = $this->parse_required_id( $arguments['id'], 'id' );
        $this->require_form_capability( $id );
        $title = self::title_of( $id );
        
        $data = $this->rest_request( 'DELETE', self::$cf7_route . '/' . $id );
        if ( ! is_array( $data ) || empty( $data['deleted'] ) ) {
            throw new \RuntimeException( sprintf( 'Contact Form 7 did not confirm deleting form %d. Check it under Contact › Contact Forms.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'deleted' => true,
            'form'    => array(
                'id'    => $id,
                'title' => null !== $title ? $title : '',
            ),
        );
    }
}
