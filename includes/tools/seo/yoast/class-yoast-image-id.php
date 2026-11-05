<?php

namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Yoast_Image_Id {

	public static function for_url( string $url ): int {
		if ( '' === $url ) {
			return 0;
		}

		$id = (int) attachment_url_to_postid( $url );
		if ( $id > 0 ) {
			return $id;
		}
		if ( ! preg_match( '#^(.+/)([^/]+)-\d+x\d+(\.[a-z0-9]+)$#i', $url, $parts ) ) {
			return 0;
		}
		$folder = self::uploads_folder( $parts[1] );
		if ( null === $folder ) {
			return 0;
		}

		$file = self::file_name( $url );
		foreach ( array( $parts[2], $parts[2] . '-scaled' ) as $original ) {
			$id = (int) attachment_url_to_postid( $parts[1] . $original . $parts[3] );
			if ( $id > 0 && self::lists_size( wp_get_attachment_metadata( $id ), $file ) ) {
				return $id;
			}
		}

		foreach ( self::attachments_naming( $folder, $file ) as $candidate => $metadata ) {
			if ( self::lists_size( $metadata, $file ) && self::folder_of( $metadata['file'] ) === $folder ) {
				return $candidate;
			}
		}
		return 0;
	}

	public static function store_for_post( int $post_id, string $id_key, string $url ): bool {
		$attachment_id = self::for_url( $url );
		if ( $attachment_id > 0 ) {
			return false !== update_post_meta( $post_id, $id_key, (string) $attachment_id );
		}
		return (bool) delete_post_meta( $post_id, $id_key );
	}

	private static function uploads_folder( string $folder_url ): ?string {
		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['baseurl'] ) || ! is_string( $uploads['baseurl'] ) ) {
			return null;
		}
		$base   = preg_replace( '#^[a-z][a-z0-9+.-]*:#i', '', $uploads['baseurl'] ) . '/';
		$folder = (string) preg_replace( '#^[a-z][a-z0-9+.-]*:#i', '', $folder_url );
		return 0 === strpos( $folder, $base ) ? substr( $folder, strlen( $base ) ) : null;
	}

	private static function attachments_naming( string $folder, string $file ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata' AND meta_value LIKE %s AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( '"' . $file . '"' ) . '%',
				'%' . $wpdb->esc_like( '"' . $folder ) . '%'
			),
			ARRAY_A
		);
		$found = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$found[ (int) $row['post_id'] ] = maybe_unserialize( $row['meta_value'] );
		}
		return $found;
	}

	private static function lists_size( $metadata, string $file ): bool {
		if ( ! is_array( $metadata ) || ! isset( $metadata['file'], $metadata['sizes'] ) || ! is_string( $metadata['file'] ) || ! is_array( $metadata['sizes'] ) ) {
			return false;
		}
		foreach ( $metadata['sizes'] as $size ) {
			if ( is_array( $size ) && isset( $size['file'] ) && $file === $size['file'] ) {
				return true;
			}
		}
		return false;
	}

	private static function folder_of( string $path ): string {
		$slash = strrpos( $path, '/' );
		return false === $slash ? '' : substr( $path, 0, $slash + 1 );
	}

	private static function file_name( string $path ): string {
		$slash = strrpos( $path, '/' );
		return false === $slash ? $path : substr( $path, $slash + 1 );
	}
}
