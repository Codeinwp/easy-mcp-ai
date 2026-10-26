<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Form extends Base_Tool {

    use CF7_Rest;

    public function get_name() {
        return 'wp_cf7_get_form';
    }

    public function get_description() {
        return 'Gets one contact form of the Contact Form 7 plugin, in the shape wp_cf7_update_form takes. Required: `id` (from wp_cf7_list_forms). Returns { form: { id, title, slug, locale, shortcode, form, fields, mail, mail_2, messages, additional_settings } }: `form` is the template (form-tags such as [text* your-name] and HTML), `fields` the form-tags Contact Form 7 parsed from it, `mail` and `mail_2` the two emails (subject, sender, recipient, body, additional_headers, attachments, use_html, exclude_blank; mail_2 also active), `messages` the texts shown to visitors by message id, `additional_settings` one "name: value" per line. Requires the Contact Form 7 plugin active.';
    }

    public function get_required_capability() {
        return self::cf7_capability( 'wpcf7_edit_contact_form' );
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
                    'description' => 'The contact form id.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_cf7();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );
        $this->require_form_capability( $id );
        return array( 'form' => self::normalize_form( $this->read_form( $id ) ) );
    }
}
