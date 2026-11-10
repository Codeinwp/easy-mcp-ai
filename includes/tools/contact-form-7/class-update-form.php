<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

use Easy_MCP_AI\Approvals\Preview_Values;
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
        return 'Updates a contact form of the Contact Form 7 plugin. Only what you send changes. Required: `id` (from wp_cf7_list_forms). Optional: `title`, `locale`, `form` (the whole template; replaces it), `mail`, `mail_2`, `messages` (in these three only the keys you send change, e.g. mail: {"recipient": "sales@example.com"} keeps the subject and body), `additional_settings` (replaces them). Read the form first with wp_cf7_get_form to edit its template. Returns { updated, changed_fields, form, config_errors }, read back from Contact Form 7; `updated: false` means the form already had these values. `config_errors` lists the problems Contact Form 7\'s own configuration check found; it runs on a save, so a call that would store what the form already holds saves nothing and has no `config_errors`. Requires the Contact Form 7 plugin active.';
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
        $values = Preview_Values::mask( $arguments, (array) $this->get_redacted_arguments() );
        $parts  = array();
        foreach ( $fields as $field ) {
            $parts[] = Preview_Values::named( $field, $values[ $field ] );
        }
        return sprintf(
            /* translators: 1: contact form id, 2: contact form title, 3: each part sent with its value, separated by semicolons */
            __( 'Update contact form #%1$d "%2$s": %3$s', 'easy-mcp-ai' ),
            $target['id'],
            $target['title'],
            implode( '; ', $parts )
        );
    }

    private static function save_may_filter(): bool {
        return ! current_user_can( 'unfiltered_html' );
    }

    public function approval_details( array $arguments ): ?array {
        return self::generic_details( $this, $arguments );
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
        $body   = $this->save_body( $arguments, $before );
        if ( ! self::save_may_filter() && empty( self::changed_fields( $before, array_merge( $before, $body ) ) ) ) {
            return array(
                'updated'        => false,
                'changed_fields' => array(),
                'form'           => $before,
                'note'           => 'Nothing changed: the form already has these values, so Contact Form 7 was not asked to save it and its configuration check did not run.',
            );
        }
        $data  = $this->rest_request( 'POST', self::$cf7_route . '/' . $id, $body );
        $after = self::normalize_form( $this->read_form( $id ) );

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
