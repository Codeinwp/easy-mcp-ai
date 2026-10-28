<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Form extends Base_Tool {

    use CF7_Rest;

    public function get_name() {
        return 'wp_cf7_update_form';
    }

    public function get_description() {
        return 'Updates a contact form of the Contact Form 7 plugin. Only what you send changes. Required: `id` (from wp_cf7_list_forms). Optional: `title`, `locale`, `form` (the whole template; replaces it), `mail`, `mail_2`, `messages` (in these three only the keys you send change, e.g. mail: {"recipient": "sales@example.com"} keeps the subject and body), `additional_settings` (replaces them). Read the form first with wp_cf7_get_form to edit its template. Returns { updated, changed_fields, form, config_errors }, read back from Contact Form 7; `updated: false` means the form already had these values. `config_errors` lists the problems Contact Form 7\'s own configuration check found. Requires the Contact Form 7 plugin active.';
    }

    public function get_required_capability() {
        return self::cf7_capability( 'wpcf7_edit_contact_form' );
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
            'properties' => array_merge(
                array(
                    'id' => array(
                        'type'        => 'integer',
                        'description' => 'The contact form id (from wp_cf7_list_forms).',
                    ),
                ),
                self::write_properties()
            ),
            'required'   => array( 'id' ),
        );
    }

    public function describe( array $arguments ) {
        $target = $this->preview_target( $arguments );
        $fields = self::sent_fields( $arguments );
        if ( null === $target || empty( $fields ) ) {
            return '';
        }
        return sprintf(
            /* translators: 1: contact form id, 2: contact form title, 3: comma-separated field names */
            __( 'Update contact form #%1$d "%2$s": %3$s', 'easy-mcp-ai' ),
            $target['id'],
            $target['title'],
            implode( ', ', $fields )
        );
    }

    public function execute( array $arguments ) {
        $this->require_cf7();
        $this->validate_required( $arguments, array( 'id' ) );
        $id   = $this->parse_required_id( $arguments['id'], 'id' );
        if ( empty( self::sent_fields( $arguments ) ) ) {
            throw new \InvalidArgumentException( 'Nothing to update: send at least one field besides id.' );
        }

        $this->require_form_capability( $id );
        $stored = $this->read_form( $id );
        $before = self::normalize_form( $stored );
        $data   = $this->rest_request( 'POST', self::$cf7_route . '/' . $id, $this->save_body( $arguments, $before ) );
        $after  = self::normalize_form( $this->read_form( $id ) );

        $changed = self::changed_fields( $before, $after );
        $result  = array(
            'updated'        => ! empty( $changed ),
            'changed_fields' => $changed,
            'form'           => $after,
            'config_errors'  => self::config_errors_of( $data ),
        );
        if ( empty( $changed ) ) {
            $result['note'] = 'Nothing changed: the form already had these values, or Contact Form 7 dropped what was sent (an unknown message id or mail key is not stored).';
        }
        return $result;
    }
}
