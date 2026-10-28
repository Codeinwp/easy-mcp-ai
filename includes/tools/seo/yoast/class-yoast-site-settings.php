<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Yoast_Site_Settings {

	const TITLES = 'wpseo_titles';
	const SOCIAL = 'wpseo_social';

	const TEXT_PREFIXES = array(
		'title'              => 'title-',
		'description'        => 'metadesc-',
		'social_title'       => 'social-title-',
		'social_description' => 'social-description-',
	);

	const HOME_SOCIAL = array(
		'social_title'       => 'open_graph_frontpage_title',
		'social_description' => 'open_graph_frontpage_desc',
	);

	const SPECIAL = array(
		'home'           => 'home-wpseo',
		'author_archive' => 'author-wpseo',
		'date_archive'   => 'archive-wpseo',
		'search'         => 'search-wpseo',
		'404'            => '404-wpseo',
	);

	public static function instances(): array {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			throw new \RuntimeException( 'Yoast SEO is not active on this site. Please install and activate Yoast SEO to use this tool.' );
		}
		$titles = \WPSEO_Options::get_option_instance( self::TITLES );
		$social = \WPSEO_Options::get_option_instance( self::SOCIAL );
		if ( ! is_object( $titles ) || ! is_object( $social ) || ! method_exists( $titles, 'get_defaults' ) || ! method_exists( $titles, 'get_separator_options' ) || ! method_exists( $titles, 'is_disabled' ) || ! method_exists( $social, 'is_disabled' ) ) {
			throw new \RuntimeException( 'This version of Yoast SEO does not offer the settings API this tool uses (WPSEO_Options::get_option_instance() for wpseo_titles and wpseo_social). Nothing was read or changed.' );
		}
		return array( $titles, $social );
	}

	public static function content_types( array $defaults ): array {
		$types    = array();
		$archives = array();
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			if ( array_key_exists( 'title-' . $post_type, $defaults ) ) {
				$types[] = 'post_type:' . $post_type;
			}
			if ( array_key_exists( 'title-ptarchive-' . $post_type, $defaults ) ) {
				$archives[] = 'archive:' . $post_type;
			}
		}
		$types = array_merge( $types, $archives );
		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
			if ( array_key_exists( 'title-tax-' . $taxonomy, $defaults ) ) {
				$types[] = 'taxonomy:' . $taxonomy;
			}
		}
		foreach ( self::SPECIAL as $type => $suffix ) {
			if ( array_key_exists( 'title-' . $suffix, $defaults ) ) {
				$types[] = (string) $type;
			}
		}
		return $types;
	}

	public static function text_key( string $content_type, string $field, array $defaults ): ?string {
		if ( ! isset( self::TEXT_PREFIXES[ $field ] ) ) {
			return null;
		}
		if ( 'home' === $content_type && isset( self::HOME_SOCIAL[ $field ] ) ) {
			$key = self::HOME_SOCIAL[ $field ];
		} else {
			$suffix = self::suffix( $content_type );
			$key    = null === $suffix ? null : self::TEXT_PREFIXES[ $field ] . $suffix;
		}
		return null !== $key && array_key_exists( $key, $defaults ) ? $key : null;
	}

	public static function image_keys( string $content_type, array $defaults ): ?array {
		if ( 'home' === $content_type ) {
			$keys = array( 'open_graph_frontpage_image', 'open_graph_frontpage_image_id' );
		} else {
			$suffix = self::suffix( $content_type );
			if ( null === $suffix ) {
				return null;
			}
			$keys = array( 'social-image-url-' . $suffix, 'social-image-id-' . $suffix );
		}
		return array_key_exists( $keys[0], $defaults ) && array_key_exists( $keys[1], $defaults ) ? $keys : null;
	}

	private static function suffix( string $content_type ): ?string {
		if ( isset( self::SPECIAL[ $content_type ] ) ) {
			return self::SPECIAL[ $content_type ];
		}
		$parts = explode( ':', $content_type, 2 );
		if ( 2 !== count( $parts ) || '' === $parts[1] ) {
			return null;
		}
		$prefixes = array(
			'post_type' => '',
			'archive'   => 'ptarchive-',
			'taxonomy'  => 'tax-',
		);
		return isset( $prefixes[ $parts[0] ] ) ? $prefixes[ $parts[0] ] . $parts[1] : null;
	}

	public static function separators( object $titles ): array {
		$out = array();
		foreach ( (array) $titles->get_separator_options() as $key => $character ) {
			if ( is_scalar( $character ) ) {
				$out[ (string) $key ] = html_entity_decode( (string) $character, ENT_QUOTES, 'UTF-8' );
			}
		}
		return $out;
	}

	public static function stored( string $name ): array {
		$value = get_option( $name );
		return is_array( $value ) ? $value : array();
	}

	public static function read( object $titles, object $social ): array {
		$defaults   = (array) $titles->get_defaults();
		$stored     = self::stored( self::TITLES );
		$stored_soc = self::stored( self::SOCIAL );
		$separators = self::separators( $titles );
		$separator  = self::text( $stored, 'separator' );

		$templates = array();
		foreach ( self::content_types( $defaults ) as $type ) {
			$entry = array( 'content_type' => $type );
			foreach ( array_keys( self::TEXT_PREFIXES ) as $field ) {
				$key = self::text_key( $type, $field, $defaults );
				if ( null !== $key ) {
					$entry[ $field ] = self::text( $stored, $key );
				}
			}
			$image = self::image_keys( $type, $defaults );
			if ( null !== $image ) {
				$entry['social_image'] = self::image( $stored, $image[0], $image[1] );
			}
			$templates[] = $entry;
		}

		return array(
			'separator'            => array(
				'key'       => $separator,
				'character' => $separators[ $separator ] ?? '',
				'options'   => self::separator_options( $separators ),
			),
			'templates'            => $templates,
			'default_social_image' => self::image( $stored_soc, 'og_default_image', 'og_default_image_id' ),
			'locked'               => self::locked( $titles, $social, $defaults ),
		);
	}

	private static function separator_options( array $separators ): array {
		$out = array();
		foreach ( $separators as $key => $character ) {
			$out[] = array( 'key' => $key, 'character' => $character );
		}
		return $out;
	}

	private static function image( array $stored, string $url_key, string $id_key ): array {
		return array(
			'id'  => isset( $stored[ $id_key ] ) && is_numeric( $stored[ $id_key ] ) ? (int) $stored[ $id_key ] : 0,
			'url' => self::text( $stored, $url_key ),
		);
	}

	private static function text( array $stored, string $key ): string {
		return isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
	}

	private static function locked( object $titles, object $social, array $defaults ): array {
		$locked = array();
		if ( $titles->is_disabled( 'separator' ) ) {
			$locked[] = 'separator';
		}
		foreach ( self::content_types( $defaults ) as $type ) {
			foreach ( array_keys( self::TEXT_PREFIXES ) as $field ) {
				$key = self::text_key( $type, $field, $defaults );
				if ( null !== $key && $titles->is_disabled( $key ) ) {
					$locked[] = $type . ' ' . $field;
				}
			}
			$image = self::image_keys( $type, $defaults );
			if ( null !== $image && ( $titles->is_disabled( $image[0] ) || $titles->is_disabled( $image[1] ) ) ) {
				$locked[] = $type . ' social_image';
			}
		}
		if ( $social->is_disabled( 'og_default_image' ) || $social->is_disabled( 'og_default_image_id' ) ) {
			$locked[] = 'default_social_image';
		}
		return $locked;
	}
}
