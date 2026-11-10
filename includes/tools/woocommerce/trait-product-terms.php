<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Posts\Update_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Product_Terms {

    protected function product_terms_param( $raw, string $field, string $taxonomy, string $kind ): array {
        $items = $this->parse_json_param( $raw, $field );
        if ( array_values( $items ) !== $items ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a list of objects with id, e.g. [{"id": 12}].', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $params = array();
        foreach ( $items as $index => $item ) {
            $label = $field . '[' . $index . ']';
            if ( 'product_tag' === $taxonomy && is_array( $item ) && isset( $item['name'] ) ) {
                if ( ! is_string( $item['name'] ) || '' === trim( $item['name'] ) ) {
                    throw new \InvalidArgumentException( sprintf( '%s.name must be the name of a product tag.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $params[] = $item;
                continue;
            }
            $id = is_array( $item ) && isset( $item['id'] ) ? $item['id'] : null;
            if ( ! Update_Post::is_whole_number( $id ) || (int) $id < 1 ) {
                throw new \InvalidArgumentException( sprintf( '%1$s.id must be a whole number: the ID of a %2$s.', $label, $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $id   = (int) $id;
            $term = get_term( $id );
            if ( ! is_object( $term ) || is_wp_error( $term ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: %2$s %3$d not found.', $label, $kind, $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( $taxonomy !== $term->taxonomy ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: %2$d is not the ID of a %3$s; it is a term of %4$s.', $label, $id, $kind, $term->taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $params[] = array( 'id' => $id );
        }
        return $params;
    }

    protected function product_ids_param( array $ids, string $field ): array {
        if ( array_values( $ids ) !== $ids ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a list of product IDs, e.g. [12, 34].', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $checked = array();
        foreach ( $ids as $index => $id ) {
            $label = $field . '[' . $index . ']';
            if ( ! Update_Post::is_whole_number( $id ) || (int) $id < 1 ) {
                throw new \InvalidArgumentException( sprintf( '%s must be a whole number: the ID of a product.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $id   = (int) $id;
            $post = get_post( $id );
            if ( ! $post ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: product %2$d not found.', $label, $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: %2$d is not the ID of a product; it is a post of type %3$s.', $label, $id, $post->post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $checked[] = $id;
        }
        return $checked;
    }

    protected function refuse_unknown_attribute_ids( array $attributes, string $field, bool $rest_validated, string $outcome ): void {
        foreach ( $attributes as $index => $attribute ) {
            if ( ! is_array( $attribute ) || ! isset( $attribute['id'] ) ) {
                continue;
            }
            $id = $attribute['id'];
            if ( $rest_validated ) {
                if ( ! Update_Post::is_whole_number( $id ) ) {
                    continue;
                }
                $id = (int) $id;
            }
            if ( empty( $id ) || '' !== wc_attribute_taxonomy_name_by_id( absint( $id ) ) ) {
                continue;
            }
            throw new \InvalidArgumentException( sprintf( '%1$s.id: product attribute %2$s not found. %3$s', self::place( $field, $index ), self::shown_value( $attribute['id'] ), $outcome ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function refuse_unknown_line_item_products( array $line_items, string $field, bool $rest_validated, string $outcome ): void {
        foreach ( $line_items as $index => $item ) {
            if ( ! is_array( $item ) || ! empty( $item['sku'] ) ) {
                continue;
            }
            $variation_id = $item['variation_id'] ?? null;
            if ( $rest_validated && array_key_exists( 'variation_id', $item ) ) {
                if ( ! Update_Post::is_whole_number( $variation_id ) ) {
                    continue;
                }
                $variation_id = (int) $variation_id;
            }
            if ( ! empty( $item['product_id'] ) && empty( $variation_id ) ) {
                $key  = 'product_id';
                $kind = 'product';
                $id   = (int) $item['product_id'];
            } elseif ( ! empty( $variation_id ) ) {
                $key  = 'variation_id';
                $kind = 'product variation';
                $id   = (int) $variation_id;
            } else {
                continue;
            }
            if ( $id > 0 && wc_get_product( $id ) ) {
                continue;
            }
            $place = self::place( $field, $index ) . '.' . $key;
            $post  = $id > 0 ? get_post( $id ) : null;
            if ( $post && ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: %2$d is not the ID of a %3$s; it is a post of type %4$s. %5$s', $place, $id, $kind, $post->post_type, $outcome ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            throw new \InvalidArgumentException( sprintf( '%1$s: %2$s %3$s not found. %4$s', $place, $kind, self::shown_value( $item[ $key ] ), $outcome ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function place( string $field, $index ): string {
        return $field . '[' . ( is_int( $index ) ? $index : self::shown_value( (string) $index ) ) . ']';
    }

    private static function shown_value( $value ): string {
        $json  = wp_json_encode( $value );
        $shown = is_int( $value ) ? (string) $value : ( false === $json ? gettype( $value ) : $json );
        return strlen( $shown ) > 40 ? substr( $shown, 0, 37 ) . '...' : $shown;
    }
}
