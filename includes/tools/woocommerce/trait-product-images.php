<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Product_Images {

    protected function image_item_schema(): array {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id'   => array(
                    'type'        => 'integer',
                    'description' => 'Media library image ID (wp_list_media, wp_upload_media_from_url). Preferred: nothing is downloaded.',
                ),
                'src'  => array(
                    'type'        => 'string',
                    'description' => 'Image URL, downloaded as a NEW media item on every call. Use `id` for images already in the library.',
                ),
                'alt'  => array(
                    'type'        => 'string',
                    'description' => 'Alt text, saved on the image itself. "" and "0" are ignored.',
                ),
                'name' => array(
                    'type'        => 'string',
                    'description' => 'Image title in the media library. "" and "0" are ignored.',
                ),
            ),
        );
    }

    protected function product_images_param( $raw ): array {
        $images = $this->parse_json_param( $raw, 'images' );
        if ( array_values( $images ) !== $images ) {
            throw new \InvalidArgumentException( 'images must be a list of image objects, e.g. [{"id": 12}, {"id": 15}].' );
        }
        $params = array();
        foreach ( $images as $index => $image ) {
            $params[] = $this->image_param( $image, 'images[' . $index . ']' );
        }
        return $params;
    }

    protected function image_param( $raw, string $label, bool $empty_removes = false ): array {
        $image = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
        if ( ! is_array( $image ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an object with `id` or `src`.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $param = array();
        if ( isset( $image['id'] ) ) {
            $id = $this->image_id( $image['id'] );
            if ( null === $id ) {
                throw new \InvalidArgumentException( sprintf( '%s.id must be a whole number: the ID of an image in the media library.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( $id > 0 ) {
                $param['id'] = $id;
            }
        }
        if ( isset( $image['src'] ) && is_string( $image['src'] ) && '' !== trim( $image['src'] ) ) {
            $param['src'] = trim( $image['src'] );
        }
        if ( ! isset( $param['id'] ) && ! isset( $param['src'] ) ) {
            if ( $empty_removes && ! array_diff_key( $image, array( 'id' => true ) ) ) {
                return array( 'id' => 0 );
            }
            throw new \InvalidArgumentException( sprintf( '%s needs `id` (an image already in the media library) or `src` (an image URL).', $label ) . ( $empty_removes ? ' To remove the image, send {}.' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        foreach ( array( 'alt', 'name' ) as $key ) {
            if ( isset( $image[ $key ] ) && is_scalar( $image[ $key ] ) && ! empty( $image[ $key ] ) ) {
                $param[ $key ] = (string) $image[ $key ];
            }
        }
        if ( isset( $param['id'] ) ) {
            $param = $this->without_unchanged_text( $param );
        }
        if ( isset( $param['id'] ) && ( isset( $param['alt'] ) || isset( $param['name'] ) ) && ! current_user_can( 'edit_post', $param['id'] ) ) {
            throw new \InvalidArgumentException( sprintf( 'You cannot edit image %1$d, so %2$s cannot change its alt text or title. Send it without `alt` and `name`.', $param['id'], $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $param;
    }

    private function without_unchanged_text( array $param ): array {
        if ( isset( $param['alt'] ) ) {
            $stored = (string) get_post_meta( $param['id'], '_wp_attachment_image_alt', true );
            if ( in_array( $stored, array( $param['alt'], sanitize_text_field( $param['alt'] ) ), true ) ) {
                unset( $param['alt'] );
            }
        }
        if ( isset( $param['name'] ) && (string) get_the_title( $param['id'] ) === $param['name'] ) {
            unset( $param['name'] );
        }
        return $param;
    }

    protected function variation_gallery_param( $raw ): array {
        $ids = $this->parse_json_param( $raw, 'gallery_image_ids' );
        if ( array_values( $ids ) !== $ids ) {
            throw new \InvalidArgumentException( 'gallery_image_ids must be a list of media library image IDs, e.g. [12, 15].' );
        }
        $params = array();
        foreach ( $ids as $index => $raw_id ) {
            $id = $this->image_id( $raw_id );
            if ( null === $id || 0 === $id || ! wp_attachment_is_image( $id ) ) {
                throw new \InvalidArgumentException( sprintf( 'gallery_image_ids[%d] is not the ID of an image in the media library.', $index ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $params[] = $id;
        }
        return $params;
    }

    private function image_id( $value ): ?int {
        if ( is_int( $value ) ) {
            return $value >= 0 ? $value : null;
        }
        return is_string( $value ) && ctype_digit( $value ) ? (int) $value : null;
    }

    protected function require_variation_gallery_support( int $product_id ): void {
        $options = $this->rest_request( 'OPTIONS', '/wc/v3/products/' . $product_id . '/variations' );
        if ( ! isset( $options['schema']['properties']['gallery_image_ids'] ) ) {
            throw new \RuntimeException( 'This WooCommerce version has no variation galleries (added in WooCommerce 11.1), so gallery_image_ids cannot be saved. Nothing was changed.' );
        }
    }

    protected function with_variation_images( array $params, array $data, array $reply ): array {
        if ( isset( $params['image'] ) ) {
            $reply['image'] = $this->image_reply( $data['image'] ?? null );
        }
        if ( isset( $params['gallery_image_ids'] ) ) {
            $reply['gallery_image_ids'] = array_map( 'intval', (array) ( $data['gallery_image_ids'] ?? array() ) );
        }
        return $reply;
    }

    protected function images_reply( $images ): array {
        if ( ! is_array( $images ) ) {
            return array();
        }
        $reply = array();
        foreach ( $images as $image ) {
            $summary = $this->image_reply( $image );
            if ( null !== $summary ) {
                $reply[] = $summary;
            }
        }
        return $reply;
    }

    protected function image_reply( $image ): ?array {
        if ( ! is_array( $image ) || empty( $image['id'] ) ) {
            return null;
        }
        return array(
            'id'   => (int) $image['id'],
            'src'  => (string) ( $image['src'] ?? '' ),
            'alt'  => (string) ( $image['alt'] ?? '' ),
            'name' => (string) ( $image['name'] ?? '' ),
        );
    }
}
