<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Form extends Base_Tool {

    use CF7_Rest;

    public function get_name() {
        return 'wp_cf7_create_form';
    }

    public function get_description() {
        return 'Creates a contact form in the Contact Form 7 plugin. Every field is optional: what you leave out comes from Contact Form 7\'s default form (name, email, subject and message fields; an email to the site admin; the standard messages). Optional: `title`, `locale`, `form` (the template with form-tags), `mail`, `mail_2`, `messages`, `additional_settings`. In `mail`, `mail_2` and `messages`, only the keys you send replace the defaults. Returns { created: true, form, config_errors }: `form` as wp_cf7_get_form returns it, including the `shortcode` that places it in a page; `config_errors` lists the problems Contact Form 7\'s own configuration check found (e.g. an invalid sender), empty when there are none. Requires the Contact Form 7 plugin active.';
    }

    public function get_required_capability() {
        return self::cf7_capability( 'wpcf7_edit_contact_forms' );
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
            'properties' => self::write_properties(),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_cf7();
        $template = \WPCF7_ContactForm::get_template();
        $data     = $this->rest_request( 'POST', self::$cf7_route, $this->save_body( $arguments, $template->get_properties() ) );
        $id       = is_array( $data ) && isset( $data['id'] ) ? (int) $data['id'] : 0;
        if ( $id < 1 ) {
            throw new \RuntimeException( 'Contact Form 7 answered without saving the form. Nothing was created.' );
        }
        $form = current_user_can( self::cf7_capability( 'wpcf7_edit_contact_form' ), $id ) ? $this->read_form( $id ) : self::with_stored_names( $data, $id );
        return array(
            'created'       => true,
            'form'          => self::normalize_form( $form ),
            'config_errors' => self::config_errors_of( $data ),
        );
    }

    private static function with_stored_names( array $data, int $id ): array {
        $stored = function_exists( 'wpcf7_contact_form' ) ? wpcf7_contact_form( $id ) : null;
        if ( is_object( $stored ) && method_exists( $stored, 'name' ) && method_exists( $stored, 'title' ) ) {
            $data['slug']  = (string) $stored->name();
            $data['title'] = (string) $stored->title();
        }
        return $data;
    }
}
