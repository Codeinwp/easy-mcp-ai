<?php
namespace Easy_MCP_AI\Tools\ACF;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Field_Groups extends Base_Tool {

    public function get_name() {
        return 'wp_acf_list_field_groups';
    }

    public function get_description() {
        return 'Lists all registered ACF field groups with their field keys, labels, and types. Use this to discover which fields are available before calling wp_acf_get_fields or wp_acf_update_fields.';
    }

    public function get_category() {
        return 'acf';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array( 'title' => $this->get_title(), 'readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'ACF' ) ) {
            throw new \RuntimeException( 'Advanced Custom Fields (ACF) is not active on this site. Note: Secure Custom Fields (SCF) uses the same ACF class name so this check covers both.' );
        }
        if ( ! function_exists( 'acf_get_field_groups' ) ) {
            throw new \RuntimeException( 'ACF function acf_get_field_groups() is not available.' );
        }
        $groups = acf_get_field_groups();
        $result = array();
        foreach ( $groups as $group ) {
            $fields      = function_exists( 'acf_get_fields' ) ? acf_get_fields( $group['key'] ) : array();
            $field_list  = array();
            if ( is_array( $fields ) ) {
                foreach ( $fields as $field ) {
                    $field_list[] = array(
                        'key'   => $field['key'],
                        'name'  => $field['name'],
                        'label' => $field['label'],
                        'type'  => $field['type'],
                    );
                }
            }
            $result[] = array(
                'key'      => $group['key'],
                'title'    => $group['title'],
                'active'   => $group['active'] ?? true,
                'location' => $group['location'] ?? array(),
                'fields'   => $field_list,
            );
        }
        return array( 'field_groups' => $result );
    }
}
