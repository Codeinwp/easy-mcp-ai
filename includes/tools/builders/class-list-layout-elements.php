<?php
namespace Easy_MCP_AI\Tools\Builders;

use Easy_MCP_AI\Builders\Builder_Registry;
use Easy_MCP_AI\Builders\Layout_Adapter;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class List_Layout_Elements extends Base_Tool {

    public function get_name() {
        return 'wp_list_layout_elements';
    }

    public function get_description() {
        return 'Lists the element types of a page builder active on this site (Elementor, WPBakery or Flatsome UX Builder), or the fields of one type: what a layout element can hold before you read or plan an edit. Optional: `builder` (`elementor`, `wpbakery` or `flatsome`; default: the only one active, required when several are), `type` (an element type such as `heading`, `vc_btn` or `ux_banner`; returns its fields), `search` (filters the list by type or label). Returns { builder, builder_version, types: [{ type, label, category, atomic?, code? }] } or { builder, type, fields: [{ name, kind, label, responsive?, options?, default? }] }. Kinds: text, html, link, image, select, number, size, color, boolean, spacing, list, icon, code, other. Elementor and WPBakery read their own registries; Flatsome\'s list is a map shipped with this plugin, because its builder registry loads only inside UX Builder.';
    }

    public function get_category() {
        return 'builders';
    }

    public function get_required_capability() {
        return 'edit_posts';
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
                'builder' => array(
                    'type'        => 'string',
                    'description' => 'The builder: "elementor", "wpbakery" or "flatsome". Default: the only one active on this site.',
                ),
                'type'    => array(
                    'type'        => 'string',
                    'description' => 'An element type, e.g. "heading", "vc_btn" or "ux_banner". Returns its fields instead of the list.',
                ),
                'search'  => array(
                    'type'        => 'string',
                    'description' => 'Only types whose name or label contains this text (case-insensitive).',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $adapter = $this->adapter( isset( $arguments['builder'] ) ? strtolower( trim( (string) $arguments['builder'] ) ) : '' );
        $type    = isset( $arguments['type'] ) ? trim( (string) $arguments['type'] ) : '';

        if ( '' !== $type ) {
            $fields = $adapter->element_fields( $type );
            if ( null === $fields ) {
                throw new \InvalidArgumentException( sprintf( '%1$s has no element type "%2$s". Call this tool without `type` to list them.', $adapter->label(), $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return array(
                'builder'         => $adapter->id(),
                'builder_version' => $adapter->version(),
                'type'            => $type,
                'fields'          => $fields,
            );
        }

        $types  = $adapter->element_types();
        $search = isset( $arguments['search'] ) ? strtolower( trim( (string) $arguments['search'] ) ) : '';
        if ( '' !== $search ) {
            $types = array_values(
                array_filter(
                    $types,
                    static function ( array $entry ) use ( $search ): bool {
                        return false !== strpos( strtolower( $entry['type'] . ' ' . $entry['label'] ), $search );
                    }
                )
            );
        }
        return array(
            'builder'         => $adapter->id(),
            'builder_version' => $adapter->version(),
            'count'           => count( $types ),
            'types'           => $types,
        );
    }

    






    private function adapter( string $id ): Layout_Adapter {
        $present = Builder_Registry::present();
        $ids     = array_map(
            static function ( Layout_Adapter $adapter ): string {
                return $adapter->id();
            },
            $present
        );
        if ( '' === $id ) {
            if ( 1 === count( $present ) ) {
                return $present[0];
            }
            if ( array() === $present ) {
                throw new \RuntimeException( 'No supported page builder (Elementor, WPBakery or Flatsome UX Builder) is active on this site.' );
            }
            throw new \InvalidArgumentException( sprintf( 'Several page builders are active (%s); name one in `builder`.', implode( ', ', $ids ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $adapter = Builder_Registry::get( $id );
        if ( null === $adapter ) {
            throw new \InvalidArgumentException( sprintf( '"%1$s" is not an active page builder here. Active: %2$s.', $id, array() === $ids ? 'none' : implode( ', ', $ids ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $adapter;
    }
}
