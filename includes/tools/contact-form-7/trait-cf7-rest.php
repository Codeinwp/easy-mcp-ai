<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















trait CF7_Rest {

    




    protected static string $cf7_route = '/contact-form-7/v1/contact-forms';

    




    protected static array $cf7_string_fields = array( 'title', 'locale', 'form', 'additional_settings' );

    




    protected static array $cf7_keyed_fields = array( 'mail', 'mail_2', 'messages' );

    




    protected static array $cf7_mail_flags = array( 'active', 'use_html', 'exclude_blank' );

    public function get_category() {
        return 'contact-form-7';
    }

    








    protected static function cf7_capability( string $cap ): string {
        return class_exists( '\\WPCF7_ContactForm' ) ? $cap : 'manage_options';
    }

    


    protected function require_cf7(): void {
        if ( ! class_exists( '\\WPCF7_ContactForm' ) ) {
            throw new \RuntimeException( 'Contact Form 7 is not active on this site. Install and activate the Contact Form 7 plugin.' );
        }
    }

    




    protected static function write_properties(): array {
        $mail = array(
            'subject'            => array(
                'type'        => 'string',
                'description' => 'Subject line; mail-tags such as [your-subject] are filled in.',
            ),
            'sender'             => array(
                'type'        => 'string',
                'description' => 'From, e.g. "[_site_title] <wordpress@example.com>".',
            ),
            'recipient'          => array(
                'type'        => 'string',
                'description' => 'To: one or more addresses, comma-separated.',
            ),
            'body'               => array(
                'type'        => 'string',
                'description' => 'Message body with mail-tags such as [your-name] and [your-message].',
            ),
            'additional_headers' => array(
                'type'        => 'string',
                'description' => 'Extra headers, one per line, e.g. "Reply-To: [your-email]".',
            ),
            'attachments'        => array(
                'type'        => 'string',
                'description' => 'File form-tags or paths to attach, one per line.',
            ),
            'use_html'           => array(
                'type'        => 'boolean',
                'description' => 'Send as HTML.',
            ),
            'exclude_blank'      => array(
                'type'        => 'boolean',
                'description' => 'Leave out lines whose mail-tags are empty.',
            ),
        );
        $mail_2 = array_merge(
            array(
                'active' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether Mail (2) is sent.',
                ),
            ),
            $mail
        );
        return array(
            'title'               => array(
                'type'        => 'string',
                'description' => 'Form title, shown in wp-admin and in the shortcode.',
            ),
            'locale'              => array(
                'type'        => 'string',
                'description' => 'Language of the form, e.g. "en_US" or "de_DE".',
            ),
            'form'                => array(
                'type'        => 'string',
                'description' => 'The whole form template: Contact Form 7 form-tags such as [text* your-name], [email* your-email], [textarea your-message] and [submit "Send"], with HTML around them. Replaces the stored template.',
            ),
            'mail'                => array(
                'type'        => 'object',
                'description' => 'The email sent when the form is submitted. Only the keys you send change; the others keep their values.',
                'properties'  => $mail,
            ),
            'mail_2'              => array(
                'type'        => 'object',
                'description' => 'The optional second email, e.g. an autoresponder to the visitor; send active: true to switch it on. Only the keys you send change.',
                'properties'  => $mail_2,
            ),
            'messages'            => array(
                'type'        => 'object',
                'description' => 'Texts shown to visitors, by message id, e.g. {"mail_sent_ok": "Thank you!", "validation_error": "Please check the fields."}. Read the ids and current texts with wp_cf7_get_form. Only the ids you send change.',
            ),
            'additional_settings' => array(
                'type'        => 'string',
                'description' => 'Additional settings, one "name: value" per line, e.g. "subscribers_only: true". Replaces the stored settings.',
            ),
        );
    }

    










    protected function require_form_capability( int $id ): void {
        if ( ! current_user_can( $this->get_required_capability(), $id ) ) {
            throw new \RuntimeException( 'You are not allowed to access the requested contact form.' );
        }
    }

    






    protected function read_form( int $id ): array {
        $data = $this->rest_request( 'GET', self::$cf7_route . '/' . $id );
        return is_array( $data ) ? $data : array();
    }

    







    protected static function normalize_form( array $data ): array {
        $props    = isset( $data['properties'] ) && is_array( $data['properties'] ) ? $data['properties'] : array();
        $form     = isset( $props['form'] ) && is_array( $props['form'] ) ? $props['form'] : array();
        $settings = isset( $props['additional_settings'] ) && is_array( $props['additional_settings'] ) ? $props['additional_settings'] : array();
        $id       = isset( $data['id'] ) ? (int) $data['id'] : 0;
        return array(
            'id'                  => $id,
            'title'               => isset( $data['title'] ) ? (string) $data['title'] : '',
            'slug'                => isset( $data['slug'] ) ? (string) $data['slug'] : '',
            'locale'              => isset( $data['locale'] ) ? (string) $data['locale'] : '',
            'shortcode'           => self::shortcode_of( $id ),
            'form'                => isset( $form['content'] ) ? (string) $form['content'] : '',
            'fields'              => isset( $form['fields'] ) && is_array( $form['fields'] ) ? array_values( $form['fields'] ) : array(),
            'mail'                => isset( $props['mail'] ) && is_array( $props['mail'] ) ? $props['mail'] : array(),
            'mail_2'              => isset( $props['mail_2'] ) && is_array( $props['mail_2'] ) ? $props['mail_2'] : array(),
            'messages'            => isset( $props['messages'] ) && is_array( $props['messages'] ) ? $props['messages'] : array(),
            'additional_settings' => isset( $settings['content'] ) ? (string) $settings['content'] : '',
        );
    }

    




    protected static function shortcode_of( int $id ): ?string {
        if ( $id < 1 || ! function_exists( 'wpcf7_contact_form' ) ) {
            return null;
        }
        $contact_form = wpcf7_contact_form( $id );
        return is_object( $contact_form ) && method_exists( $contact_form, 'shortcode' ) ? (string) $contact_form->shortcode() : null;
    }

    









    protected function save_body( array $arguments, array $base ): array {
        $body = array( 'context' => 'save' );
        foreach ( self::$cf7_string_fields as $field ) {
            if ( self::sent( $arguments, $field ) ) {
                $body[ $field ] = self::string_argument( $arguments[ $field ], $field );
            }
        }
        foreach ( self::$cf7_keyed_fields as $field ) {
            if ( self::sent( $arguments, $field ) ) {
                $stored         = isset( $base[ $field ] ) && is_array( $base[ $field ] ) ? $base[ $field ] : array();
                $body[ $field ] = array_merge( $stored, self::keyed_argument( $arguments[ $field ], $field ) );
            }
        }
        return $body;
    }

    





    protected static function sent_fields( array $arguments ): array {
        return array_values(
            array_filter(
                array_merge( self::$cf7_string_fields, self::$cf7_keyed_fields ),
                function ( string $field ) use ( $arguments ): bool {
                    return self::sent( $arguments, $field );
                }
            )
        );
    }

    





    protected static function sent( array $arguments, string $field ): bool {
        return array_key_exists( $field, $arguments ) && null !== $arguments[ $field ];
    }

    







    private static function string_argument( $value, string $field ): string {
        if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a string.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return (string) $value;
    }

    








    private static function keyed_argument( $value, string $field ): array {
        if ( ! is_array( $value ) || ( ! empty( $value ) && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an object, e.g. {"recipient": "sales@example.com"}.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $out = array();
        foreach ( $value as $key => $item ) {
            $key = (string) $key;
            if ( 'messages' !== $field && in_array( $key, self::$cf7_mail_flags, true ) ) {
                $out[ $key ] = rest_sanitize_boolean( $item );
                continue;
            }
            $out[ $key ] = self::string_argument( $item, $field . '.' . $key );
        }
        return $out;
    }

    







    protected static function changed_fields( array $before, array $after ): array {
        $changed = array();
        foreach ( array_merge( self::$cf7_string_fields, self::$cf7_keyed_fields ) as $field ) {
            $old = $before[ $field ] ?? null;
            $new = $after[ $field ] ?? null;
            if ( is_array( $old ) && is_array( $new ) ) {
                $old = self::comparable( $field, $old );
                $new = self::comparable( $field, $new );
            }
            if ( $old !== $new ) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    











    private static function comparable( string $field, array $value ): array {
        if ( 'mail' === $field ) {
            unset( $value['active'] );
        }
        if ( 'messages' !== $field ) {
            foreach ( self::$cf7_mail_flags as $flag ) {
                if ( array_key_exists( $flag, $value ) ) {
                    $value[ $flag ] = (bool) $value[ $flag ];
                }
            }
        }
        ksort( $value );
        return $value;
    }

    





    protected static function config_errors_of( $data ): array {
        return is_array( $data ) && isset( $data['config_errors'] ) && is_array( $data['config_errors'] ) ? $data['config_errors'] : array();
    }

    








    protected function preview_target( array $arguments ): ?array {
        $id    = isset( $arguments['id'] ) && is_numeric( $arguments['id'] ) ? (int) $arguments['id'] : 0;
        $title = $id > 0 && current_user_can( $this->get_required_capability(), $id ) ? self::title_of( $id ) : null;
        if ( null === $title ) {
            return null;
        }
        return array(
            'id'    => $id,
            'title' => $title,
        );
    }

    






    protected static function title_of( int $id ): ?string {
        if ( ! function_exists( 'wpcf7_contact_form' ) ) {
            return null;
        }
        $contact_form = wpcf7_contact_form( $id );
        return is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : null;
    }
}
