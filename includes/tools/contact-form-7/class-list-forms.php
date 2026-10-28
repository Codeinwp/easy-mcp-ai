<?php
namespace Easy_MCP_AI\Tools\Contact_Form_7;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Forms extends Base_Tool {

    use CF7_Rest;

    const ORDERS = array( 'ID', 'title', 'date', 'modified' );

    public function get_name() {
        return 'wp_cf7_list_forms';
    }

    public function get_description() {
        return 'Lists the contact forms of the Contact Form 7 plugin. Optional: `search` (words in the title), `orderby` (ID, title, date, modified; default ID), `order` (ASC or DESC; default ASC), `per_page` (1-100, default 20), `page` (1-based). Returns { forms: [{ id, title, slug, locale, hash, shortcode }], total, page, per_page, total_pages }. `shortcode` is what places the form in a page or post. Read one form\'s fields, mail and messages with wp_cf7_get_form. Requires the Contact Form 7 plugin active.';
    }

    public function get_required_capability() {
        return self::cf7_capability( 'wpcf7_read_contact_forms' );
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
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Words to find in the form title.',
                ),
                'orderby'  => array(
                    'type'        => 'string',
                    'enum'        => self::ORDERS,
                    'description' => 'Sort field. Default: ID.',
                ),
                'order'    => array(
                    'type'        => 'string',
                    'enum'        => array( 'ASC', 'DESC' ),
                    'description' => 'Sort direction. Default: ASC.',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Forms per page, 1-100.',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number, starting at 1.',
                    'default'     => 1,
                    'minimum'     => 1,
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_cf7();
        $per_page = isset( $arguments['per_page'] ) && is_numeric( $arguments['per_page'] ) ? min( 100, max( 1, (int) $arguments['per_page'] ) ) : 20;
        $page     = isset( $arguments['page'] ) && is_numeric( $arguments['page'] ) ? max( 1, (int) $arguments['page'] ) : 1;
        $params   = array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        );
        if ( isset( $arguments['search'] ) && '' !== trim( (string) $arguments['search'] ) ) {
            $params['search'] = (string) $arguments['search'];
        }
        if ( isset( $arguments['orderby'] ) && '' !== $arguments['orderby'] ) {
            $this->refuse_unless_one_of( 'orderby', $arguments['orderby'], self::ORDERS, $arguments['orderby'], implode( ', ', self::ORDERS ) );
            $params['orderby'] = 'ID' === $arguments['orderby'] ? 'ID' : $arguments['orderby'] . ' ID';
        }
        if ( isset( $arguments['order'] ) && '' !== $arguments['order'] ) {
            $order = strtoupper( (string) $arguments['order'] );
            $this->refuse_unless_one_of( 'order', $order, array( 'ASC', 'DESC' ), $arguments['order'], 'ASC or DESC' );
            $params['order'] = $order;
        }

        $data  = $this->rest_request( 'GET', self::$cf7_route, $params );
        $forms = array();
        foreach ( is_array( $data ) ? $data : array() as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $id      = isset( $row['id'] ) ? (int) $row['id'] : 0;
            $forms[] = array(
                'id'        => $id,
                'title'     => isset( $row['title'] ) ? (string) $row['title'] : '',
                'slug'      => isset( $row['slug'] ) ? (string) $row['slug'] : '',
                'locale'    => isset( $row['locale'] ) ? (string) $row['locale'] : '',
                'hash'      => isset( $row['hash'] ) ? (string) $row['hash'] : '',
                'shortcode' => self::shortcode_of( $id ),
            );
        }
        $total = (int) \WPCF7_ContactForm::count();
        if ( empty( $forms ) && $page > 1 ) {
            unset( $params['per_page'], $params['offset'] );
            $all   = $this->rest_request( 'GET', self::$cf7_route, $params );
            $total = is_array( $all ) ? count( $all ) : 0;
        }
        return array(
            'forms'       => $forms,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil( $total / $per_page ),
        );
    }
}
