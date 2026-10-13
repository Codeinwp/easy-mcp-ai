<?php
namespace Easy_MCP_AI\Tools\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















trait Block_Tree {

    






    protected function is_addressable_block( $block ) {
        if ( ! is_array( $block ) ) {
            return false;
        }
        if ( ! empty( $block['blockName'] ) ) {
            return true;
        }
        return '' !== trim( isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '' );
    }

    





    protected function is_whitespace_block( $block ) {
        return is_array( $block ) && empty( $block['blockName'] ) && ! $this->is_addressable_block( $block );
    }

    





    protected function addressable_indexes( array $blocks ) {
        $map = array();
        foreach ( $blocks as $raw => $block ) {
            if ( $this->is_addressable_block( $block ) ) {
                $map[] = (int) $raw;
            }
        }
        return $map;
    }

    





    protected function block_display_name( array $block ) {
        return ! empty( $block['blockName'] ) ? (string) $block['blockName'] : 'core/freeform';
    }

    






    protected function parse_block_path( $path ) {
        if ( is_int( $path ) ) {
            $path = (string) $path;
        }
        $path = is_string( $path ) ? trim( $path ) : '';
        if ( '' === $path || ! preg_match( '/^\d+(\.\d+)*$/', $path ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid path %s: use zero-based indexes joined by dots, e.g. "0.2.1".', json_encode( $path ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return array_map( 'intval', explode( '.', $path ) );
    }

    









    protected function raw_index_at( array $list, $segment, $path_label, $depth ) {
        $map = $this->addressable_indexes( $list );
        if ( isset( $map[ $segment ] ) ) {
            return $map[ $segment ];
        }
        if ( empty( $map ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Path "%s" is invalid at depth %d: there are no blocks at that level.', $path_label, $depth ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        throw new \InvalidArgumentException(
            sprintf( 'Path "%s" is out of range at depth %d: allowed indexes are 0 to %d.', $path_label, $depth, count( $map ) - 1 ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }

    












    protected function &resolve_container( array &$root, array $segments, $path_label ) {
        $container = &$root;
        $parents   = array_slice( $segments, 0, -1 );
        foreach ( $parents as $depth => $segment ) {
            $list = isset( $container['innerBlocks'] ) && is_array( $container['innerBlocks'] ) ? $container['innerBlocks'] : array();
            $raw  = $this->raw_index_at( $list, $segment, $path_label, $depth );
            if ( empty( $container['innerBlocks'][ $raw ]['innerBlocks'] ) || ! is_array( $container['innerBlocks'][ $raw ]['innerBlocks'] ) ) {
                throw new \InvalidArgumentException(
                    sprintf( 'Path "%s" is invalid at depth %d: block %s has no inner blocks.', $path_label, $depth + 1, $this->block_display_name( $container['innerBlocks'][ $raw ] ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            $container = &$container['innerBlocks'][ $raw ];
        }
        return $container;
    }

    








    protected function build_block_tree( array $blocks, array $parent, $level, $depth ) {
        $out      = array();
        $position = 0;
        foreach ( $blocks as $block ) {
            if ( ! $this->is_addressable_block( $block ) ) {
                continue;
            }
            $segments = array_merge( $parent, array( $position ) );
            $children = ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) ? $block['innerBlocks'] : array();
            $count    = count( $this->addressable_indexes( $children ) );
            $attrs    = ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) && ! empty( $block['attrs'] ) ) ? $block['attrs'] : new \stdClass();

            $item = array(
                'path'               => implode( '.', $segments ),
                'name'               => $this->block_display_name( $block ),
                'attrs'              => $attrs,
                'inner_html_preview' => $this->inner_html_preview( $block ),
                'children_count'     => $count,
            );
            if ( $level < $depth ) {
                $item['children'] = $this->build_block_tree( $children, $segments, $level + 1, $depth );
            } else {
                $item['has_children'] = $count > 0;
            }
            $out[] = $item;
            ++$position;
        }
        return $out;
    }

    







    protected function inner_html_preview( array $block, $length = 160 ) {
        $html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
        $text = preg_replace( '/\s+/u', ' ', $html );
        if ( null === $text ) {
            $text = preg_replace( '/\s+/', ' ', $html );
        }
        $text = trim( (string) $text );
        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length, 'UTF-8' ) : substr( $text, 0, $length );
    }

    






    protected function collect_block_names( array $blocks, array &$names ) {
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }
            if ( ! empty( $block['blockName'] ) ) {
                $names[ (string) $block['blockName'] ] = true;
            }
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $this->collect_block_names( $block['innerBlocks'], $names );
            }
        }
    }

    





    protected function is_block_type_registered( $name ) {
        return class_exists( 'WP_Block_Type_Registry' )
            && \WP_Block_Type_Registry::get_instance()->is_registered( (string) $name );
    }

    




















    protected function block_has_saved_markup( array $block ) {
        if ( isset( $block['innerHTML'] ) && '' !== trim( (string) $block['innerHTML'] ) ) {
            return true;
        }
        if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
            foreach ( $block['innerContent'] as $chunk ) {
                if ( is_string( $chunk ) && '' !== trim( $chunk ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    





    protected function unregistered_block_names( array $blocks ) {
        $names = array();
        $this->collect_block_names( $blocks, $names );
        $missing = array();
        foreach ( array_keys( $names ) as $name ) {
            if ( ! $this->is_block_type_registered( $name ) ) {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    








    protected function parse_markup_for_insert( $markup, $label ) {
        $blocks = parse_blocks( (string) $markup );
        $out    = array();
        foreach ( $blocks as $block ) {
            if ( empty( $block['blockName'] ) ) {
                if ( $this->is_whitespace_block( $block ) ) {
                    continue;
                }
                throw new \InvalidArgumentException(
                    sprintf( '%s must be Gutenberg block markup (<!-- wp:... -->); plain HTML is not accepted.', $label ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            $out[] = $block;
        }
        if ( empty( $out ) ) {
            throw new \InvalidArgumentException( sprintf( '%s contains no blocks.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $missing = $this->unregistered_block_names( $out );
        if ( ! empty( $missing ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%s uses block types that are not registered on this server: %s. A block registered only in the editor JavaScript cannot be validated here; wp_list_block_types shows the server-registered types.',
                    $label, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    implode( ', ', $missing ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                )
            );
        }
        return $out;
    }

    




    protected function separator_block() {
        return array(
            'blockName'    => null,
            'attrs'        => array(),
            'innerBlocks'  => array(),
            'innerHTML'    => "\n\n",
            'innerContent' => array( "\n\n" ),
        );
    }

    





    protected function with_separators( array $blocks ) {
        $out = array();
        foreach ( array_values( $blocks ) as $i => $block ) {
            if ( $i > 0 ) {
                $out[] = $this->separator_block();
            }
            $out[] = $block;
        }
        return $out;
    }

    






    protected function marker_position( array $inner_content, $k ) {
        $seen = -1;
        foreach ( array_values( $inner_content ) as $pos => $chunk ) {
            if ( null === $chunk ) {
                ++$seen;
                if ( $seen === $k ) {
                    return $pos;
                }
            }
        }
        return null;
    }

    





    protected function sync_inner_html( array &$block ) {
        if ( ! isset( $block['innerContent'] ) || ! is_array( $block['innerContent'] ) ) {
            return;
        }
        $html = '';
        foreach ( $block['innerContent'] as $chunk ) {
            if ( is_string( $chunk ) ) {
                $html .= $chunk;
            }
        }
        $block['innerHTML'] = $html;
    }

    





    protected function is_root_container( array $container ) {
        return ! isset( $container['innerContent'] ) || ! is_array( $container['innerContent'] );
    }

    








    protected function container_insert_relative( array &$container, $raw, array $new_blocks, $before ) {
        $container['innerBlocks'] = array_values( $container['innerBlocks'] );
        if ( $this->is_root_container( $container ) ) {
            $chunk = $before
                ? array_merge( $this->with_separators( $new_blocks ), array( $this->separator_block() ) )
                : array_merge( array( $this->separator_block() ), $this->with_separators( $new_blocks ) );
            array_splice( $container['innerBlocks'], $before ? $raw : $raw + 1, 0, $chunk );
            return;
        }
        $container['innerContent'] = array_values( $container['innerContent'] );
        $pos = $this->marker_position( $container['innerContent'], $raw );
        if ( null === $pos ) {
            throw new \RuntimeException( 'Block tree is inconsistent: inner block has no innerContent marker.' );
        }
        $markers = array();
        $count   = count( $new_blocks );
        for ( $i = 0; $i < $count; $i++ ) {
            if ( $before ) {
                $markers[] = null;
                $markers[] = "\n\n";
            } else {
                $markers[] = "\n\n";
                $markers[] = null;
            }
        }
        array_splice( $container['innerContent'], $before ? $pos : $pos + 1, 0, $markers );
        array_splice( $container['innerBlocks'], $before ? $raw : $raw + 1, 0, array_values( $new_blocks ) );
        $this->sync_inner_html( $container );
    }

    







    protected function root_insert_end( array &$root, array $new_blocks, $prepend ) {
        $root['innerBlocks'] = array_values( $root['innerBlocks'] );
        $has_existing        = count( $this->addressable_indexes( $root['innerBlocks'] ) ) > 0;
        $chunk               = $this->with_separators( $new_blocks );
        if ( $prepend ) {
            if ( $has_existing ) {
                $chunk[] = $this->separator_block();
            }
            $root['innerBlocks'] = array_merge( $chunk, $root['innerBlocks'] );
            return;
        }
        if ( $has_existing ) {
            array_unshift( $chunk, $this->separator_block() );
        }
        $root['innerBlocks'] = array_merge( $root['innerBlocks'], $chunk );
    }

    






    protected function container_remove( array &$container, $raw ) {
        $container['innerBlocks'] = array_values( $container['innerBlocks'] );
        if ( $this->is_root_container( $container ) ) {
            array_splice( $container['innerBlocks'], $raw, 1 );
            if ( isset( $container['innerBlocks'][ $raw ] ) && $this->is_whitespace_block( $container['innerBlocks'][ $raw ] ) ) {
                array_splice( $container['innerBlocks'], $raw, 1 );
            } elseif ( $raw > 0 && isset( $container['innerBlocks'][ $raw - 1 ] ) && $this->is_whitespace_block( $container['innerBlocks'][ $raw - 1 ] ) ) {
                array_splice( $container['innerBlocks'], $raw - 1, 1 );
            }
            return;
        }
        $container['innerContent'] = array_values( $container['innerContent'] );
        $pos = $this->marker_position( $container['innerContent'], $raw );
        if ( null === $pos ) {
            throw new \RuntimeException( 'Block tree is inconsistent: inner block has no innerContent marker.' );
        }
        array_splice( $container['innerContent'], $pos, 1 );
        if ( isset( $container['innerContent'][ $pos ] ) && is_string( $container['innerContent'][ $pos ] ) && '' === trim( $container['innerContent'][ $pos ] ) ) {
            array_splice( $container['innerContent'], $pos, 1 );
        } elseif ( $pos > 0 && isset( $container['innerContent'][ $pos - 1 ] ) && is_string( $container['innerContent'][ $pos - 1 ] ) && '' === trim( $container['innerContent'][ $pos - 1 ] ) ) {
            array_splice( $container['innerContent'], $pos - 1, 1 );
        }
        array_splice( $container['innerBlocks'], $raw, 1 );
        $this->sync_inner_html( $container );
    }

    







    protected function container_replace( array &$container, $raw, array $new_blocks ) {
        $container['innerBlocks'] = array_values( $container['innerBlocks'] );
        if ( $this->is_root_container( $container ) ) {
            array_splice( $container['innerBlocks'], $raw, 1, $this->with_separators( $new_blocks ) );
            return;
        }
        $container['innerContent'] = array_values( $container['innerContent'] );
        $pos = $this->marker_position( $container['innerContent'], $raw );
        if ( null === $pos ) {
            throw new \RuntimeException( 'Block tree is inconsistent: inner block has no innerContent marker.' );
        }
        $markers = array();
        $count   = count( $new_blocks );
        for ( $i = 0; $i < $count; $i++ ) {
            if ( $i > 0 ) {
                $markers[] = "\n\n";
            }
            $markers[] = null;
        }
        array_splice( $container['innerContent'], $pos, 1, $markers );
        array_splice( $container['innerBlocks'], $raw, 1, array_values( $new_blocks ) );
        $this->sync_inner_html( $container );
    }

    






    protected function block_attribute_schema( $name ) {
        $attributes = array();
        if ( class_exists( 'WP_Block_Type_Registry' ) ) {
            $type = \WP_Block_Type_Registry::get_instance()->get_registered( (string) $name );
            if ( $type ) {
                if ( method_exists( $type, 'get_attributes' ) ) {
                    $attributes = $type->get_attributes();
                } elseif ( isset( $type->attributes ) && is_array( $type->attributes ) ) {
                    $attributes = $type->attributes;
                }
            }
        }
        if ( ! is_array( $attributes ) ) {
            $attributes = array();
        }

        






        $filtered = apply_filters( 'easy_mcp_ai_block_attribute_schema', $attributes, (string) $name );
        return is_array( $filtered ) ? $filtered : $attributes;
    }

    







    protected function attribute_type_matches( $value, $types ) {
        if ( null === $types || '' === $types || array() === $types ) {
            return true;
        }
        foreach ( (array) $types as $type ) {
            switch ( $type ) {
                case 'string':
                    if ( is_string( $value ) ) {
                        return true;
                    }
                    break;
                case 'number':
                    if ( is_int( $value ) || is_float( $value ) ) {
                        return true;
                    }
                    break;
                case 'integer':
                    if ( is_int( $value ) || ( is_float( $value ) && floor( $value ) === $value ) ) {
                        return true;
                    }
                    break;
                case 'boolean':
                    if ( is_bool( $value ) ) {
                        return true;
                    }
                    break;
                case 'array':
                    if ( is_array( $value ) && ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) ) ) {
                        return true;
                    }
                    break;
                case 'object':
                    if ( is_object( $value ) || ( is_array( $value ) && ( array() === $value || array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) ) ) {
                        return true;
                    }
                    break;
                case 'null':
                    if ( null === $value ) {
                        return true;
                    }
                    break;
                default:
                    return true;
            }
        }
        return false;
    }

    





    protected function json_type_label( $value ) {
        if ( is_null( $value ) ) {
            return 'null';
        }
        if ( is_bool( $value ) ) {
            return 'boolean';
        }
        if ( is_int( $value ) ) {
            return 'integer';
        }
        if ( is_float( $value ) ) {
            return 'number';
        }
        if ( is_string( $value ) ) {
            return 'string';
        }
        if ( is_array( $value ) ) {
            return ( array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) ) ? 'array' : 'object';
        }
        return 'object';
    }
}
