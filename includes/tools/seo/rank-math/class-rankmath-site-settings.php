<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rankmath_Site_Settings {

	const OPTIONS = array(
		'titles'  => 'rank-math-options-titles',
		'general' => 'rank-math-options-general',
	);

	const FIELDS = array(
		'noindex_archive_subpages'   => array( 'type' => 'titles', 'key' => 'noindex_archive_subpages', 'kind' => 'toggle', 'label' => 'Noindex Subpages' ),
		'noindex_paginated_pages'    => array( 'type' => 'titles', 'key' => 'noindex_paginated_pages', 'kind' => 'toggle', 'label' => 'Noindex Paginated Single Pages' ),
		'noindex_search'             => array( 'type' => 'titles', 'key' => 'noindex_search', 'kind' => 'toggle', 'label' => 'Noindex Search Results' ),
		'noindex_password_protected' => array( 'type' => 'titles', 'key' => 'noindex_password_protected', 'kind' => 'toggle', 'label' => 'Noindex Password Protected Pages' ),
		'title_404'                  => array( 'type' => 'titles', 'key' => '404_title', 'kind' => 'text', 'label' => '404 Title' ),
		'social_url_facebook'        => array( 'type' => 'titles', 'key' => 'social_url_facebook', 'kind' => 'url', 'label' => 'Facebook Page URL' ),
		'twitter_author_names'       => array( 'type' => 'titles', 'key' => 'twitter_author_names', 'kind' => 'handle', 'label' => 'Twitter Username' ),
		'social_additional_profiles' => array( 'type' => 'titles', 'key' => 'social_additional_profiles', 'kind' => 'urls', 'label' => 'Additional Profiles' ),
		'add_img_alt'                => array( 'type' => 'general', 'key' => 'add_img_alt', 'kind' => 'toggle', 'label' => 'Add missing ALT attributes', 'module' => 'image-seo' ),
		'img_alt_format'             => array( 'type' => 'general', 'key' => 'img_alt_format', 'kind' => 'text', 'label' => 'Alt attribute format', 'module' => 'image-seo' ),
		'add_img_title'              => array( 'type' => 'general', 'key' => 'add_img_title', 'kind' => 'toggle', 'label' => 'Add missing TITLE attributes', 'module' => 'image-seo' ),
		'img_title_format'           => array( 'type' => 'general', 'key' => 'img_title_format', 'kind' => 'text', 'label' => 'Title attribute format', 'module' => 'image-seo' ),
		'robots_txt'                 => array( 'type' => 'general', 'key' => 'robots_txt_content', 'kind' => 'robots', 'label' => 'Edit robots.txt' ),
		'knowledgegraph_type'        => array( 'type' => 'titles', 'key' => 'knowledgegraph_type', 'kind' => 'choice', 'label' => 'Person or Company', 'options' => array( 'person' => 'person', 'company' => 'company' ) ),
		'website_name'               => array( 'type' => 'titles', 'key' => 'website_name', 'kind' => 'text', 'label' => 'Website Name' ),
		'website_alternate_name'     => array( 'type' => 'titles', 'key' => 'website_alternate_name', 'kind' => 'text', 'label' => 'Website Alternate Name' ),
		'knowledgegraph_name'        => array( 'type' => 'titles', 'key' => 'knowledgegraph_name', 'kind' => 'text', 'label' => 'Person/Organization Name' ),
		'knowledgegraph_logo'        => array( 'type' => 'titles', 'key' => 'knowledgegraph_logo', 'kind' => 'image', 'label' => 'Logo' ),
		'url'                        => array( 'type' => 'titles', 'key' => 'url', 'kind' => 'url', 'label' => 'URL' ),
		'organization_description'   => array( 'type' => 'titles', 'key' => 'organization_description', 'kind' => 'textarea', 'label' => 'Description', 'module' => 'local-seo', 'entity' => 'company' ),
		'email'                      => array( 'type' => 'titles', 'key' => 'email', 'kind' => 'email', 'label' => 'Email', 'module' => 'local-seo' ),
		'phone'                      => array( 'type' => 'titles', 'key' => 'phone', 'kind' => 'phone', 'label' => 'Phone', 'module' => 'local-seo', 'entity' => 'person' ),
		'local_address'              => array( 'type' => 'titles', 'key' => 'local_address', 'kind' => 'address', 'label' => 'Address', 'module' => 'local-seo' ),
		'local_address_format'       => array( 'type' => 'titles', 'key' => 'local_address_format', 'kind' => 'textarea', 'label' => 'Address Format', 'module' => 'local-seo', 'entity' => 'company' ),
		'local_business_type'        => array( 'type' => 'titles', 'key' => 'local_business_type', 'kind' => 'business', 'label' => 'Business Type', 'module' => 'local-seo', 'entity' => 'company' ),
		'opening_hours'              => array( 'type' => 'titles', 'key' => 'opening_hours', 'kind' => 'hours', 'label' => 'Opening Hours', 'module' => 'local-seo', 'entity' => 'company' ),
		'opening_hours_format'       => array( 'type' => 'titles', 'key' => 'opening_hours_format', 'kind' => 'choice', 'label' => 'Opening Hours Format', 'module' => 'local-seo', 'entity' => 'company', 'options' => array( '24:00' => 'off', '12:00' => 'on' ) ),
		'phone_numbers'              => array( 'type' => 'titles', 'key' => 'phone_numbers', 'kind' => 'phones', 'label' => 'Phone Number', 'module' => 'local-seo', 'entity' => 'company' ),
		'price_range'                => array( 'type' => 'titles', 'key' => 'price_range', 'kind' => 'text', 'label' => 'Price Range', 'module' => 'local-seo', 'entity' => 'company' ),
		'additional_info'            => array( 'type' => 'titles', 'key' => 'additional_info', 'kind' => 'info', 'label' => 'Additional Info', 'module' => 'local-seo', 'entity' => 'company' ),
		'maps_api_key'               => array( 'type' => 'titles', 'key' => 'maps_api_key', 'kind' => 'secret', 'label' => 'Google Maps API Key', 'module' => 'local-seo', 'entity' => 'company' ),
		'geo'                        => array( 'type' => 'titles', 'key' => 'geo', 'kind' => 'geo', 'label' => 'Geo Coordinates', 'module' => 'local-seo', 'entity' => 'company' ),
		'local_seo_about_page'       => array( 'type' => 'titles', 'key' => 'local_seo_about_page', 'kind' => 'page', 'label' => 'About Page', 'module' => 'local-seo' ),
		'local_seo_contact_page'     => array( 'type' => 'titles', 'key' => 'local_seo_contact_page', 'kind' => 'page', 'label' => 'Contact Page', 'module' => 'local-seo' ),
	);

	const MODULES = array(
		'image-seo' => 'Image SEO',
		'local-seo' => 'Local SEO',
	);

	const ADDRESS_PARTS = array( 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry' );

	const ROW_FIELDS = array(
		'hours'  => array( 'day', 'time' ),
		'phones' => array( 'type', 'number' ),
		'info'   => array( 'type', 'value' ),
	);

	const ROW_CHOICES = array(
		'hours'  => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
		'phones' => array( 'customer support', 'technical support', 'billing support', 'bill payment', 'sales', 'reservations', 'credit card support', 'emergency', 'baggage tracking', 'roadside assistance', 'package tracking' ),
		'info'   => array( 'legalName', 'foundingDate', 'iso6523Code', 'duns', 'leiCode', 'naics', 'globalLocationNumber', 'vatID', 'taxID', 'numberOfEmployees' ),
	);

	const EFFECTS = array(
		'noindex_archive_subpages'   => 'Page 2 and later of every archive and of the blog become noindex.',
		'noindex_paginated_pages'    => 'Page 2 and later of the blog become noindex; not the pages of a post split with <!--nextpage-->, whatever the label says.',
		'noindex_search'             => 'Search result pages become noindex.',
		'noindex_password_protected' => 'Password-protected posts and pages become noindex.',
		'title_404'                  => 'The 404 page\'s title; Rank Math variables such as %sep% and %sitename% are kept.',
		'social_url_facebook'        => 'Listed in the site\'s schema (sameAs).',
		'twitter_author_names'       => 'Printed in twitter:site and listed in the site\'s schema.',
		'social_additional_profiles' => 'Listed in the site\'s schema (sameAs).',
		'add_img_alt'                => 'Needs Rank Math\'s Image SEO module on.',
		'img_alt_format'             => 'Needs Rank Math\'s Image SEO module on.',
		'add_img_title'              => 'Needs Rank Math\'s Image SEO module on.',
		'img_title_format'           => 'Needs Rank Math\'s Image SEO module on.',
		'robots_txt'                 => 'It replaces WordPress\'s default text entirely, including the Sitemap line Rank Math adds to it.',
		'knowledgegraph_type'        => 'Whether the site\'s schema names a Person ("person") or an Organization ("company") as its publisher; most Local SEO settings apply only to an Organization.',
		'website_name'               => 'The site name in search results (og:site_name and the WebSite schema). "" goes back to the site title, as Rank Math\'s screen does.',
		'website_alternate_name'     => 'Another name for the site in the WebSite schema, such as an acronym.',
		'knowledgegraph_name'        => 'The person\'s or organization\'s name in the schema. "" goes back to the site title, as Rank Math\'s screen does.',
		'knowledgegraph_logo'        => 'The logo in the schema. Read as { id, url }; written as the id of an image in the media library, 0 to remove it.',
		'url'                        => 'The person\'s or organization\'s URL in the schema. "" goes back to the site\'s home URL, as Rank Math\'s screen does.',
		'organization_description'   => 'The organization\'s description in its schema. Needs Rank Math\'s Local SEO module on and an Organization.',
		'email'                      => 'The contact email in the schema. Needs Rank Math\'s Local SEO module on.',
		'phone'                      => 'The person\'s telephone in the schema. Needs Rank Math\'s Local SEO module on and a Person; an Organization uses phone_numbers.',
		'local_address'              => 'The postal address in the schema and the [rank_math_contact_info] shortcode: streetAddress, addressLocality, addressRegion, postalCode and addressCountry (a 2-letter ISO 3166-1 code). Parts not sent are kept; "" clears a part, and the address is removed when no part is left. Needs Rank Math\'s Local SEO module on.',
		'local_address_format'       => 'How the [rank_math_contact_info] shortcode prints the address, with the tags {address}, {locality}, {region}, {postalcode}, {country} and {gps}. "" goes back to Rank Math\'s default. Needs the Local SEO module on and an Organization.',
		'local_business_type'        => 'The organization\'s schema type as schema.org names it, without spaces (LocalBusiness, Restaurant, Dentist, …); "" means Organization. Needs the Local SEO module on and an Organization.',
		'opening_hours'              => 'The opening hours in the schema and the shortcode: one row per day and time range, time as HH:MM-HH:MM in 24-hour time. Replaces the whole list; [] removes them. Needs the Local SEO module on and an Organization.',
		'opening_hours_format'       => 'Whether the [rank_math_contact_info] shortcode prints hours in 24-hour ("24:00") or 12-hour ("12:00") time. Needs the Local SEO module on and an Organization.',
		'phone_numbers'              => 'The organization\'s contact points in the schema. Replaces the whole list; [] removes them. Needs the Local SEO module on and an Organization.',
		'price_range'                => 'The price range in the schema, for example $$$. Needs the Local SEO module on and an Organization.',
		'additional_info'            => 'More properties of the organization in its schema, such as legalName or vatID. Replaces the whole list; [] removes them. Needs the Local SEO module on and an Organization.',
		'maps_api_key'               => 'The Google Maps API key the [rank_math_contact_info] shortcode embeds its map with. Never shown: it reads as "" or as "set, ending in" its last 4 characters. "" removes it. Needs the Local SEO module on and an Organization.',
		'geo'                        => 'The business\'s latitude and longitude in the schema, as "latitude,longitude". Needs the Local SEO module on and an Organization.',
		'local_seo_about_page'       => 'The page whose schema becomes AboutPage, as a page id; 0 removes it. Needs the Local SEO module on.',
		'local_seo_contact_page'     => 'The page whose schema becomes ContactPage, as a page id; 0 removes it. Needs the Local SEO module on.',
	);

	const FIELD_TYPES = array(
		'toggle'   => 'toggle',
		'text'     => 'text',
		'url'      => 'text',
		'handle'   => 'text',
		'urls'     => 'textarea',
		'robots'   => 'textarea',
		'textarea' => 'textarea',
		'choice'   => 'toggleGroup',
		'email'    => 'text',
		'phone'    => 'text',
		'secret'   => 'text',
		'geo'      => 'text',
		'business' => 'selectSearch',
		'page'     => 'searchPage',
		'address'  => 'group',
		'hours'    => 'repeatableGroup',
		'phones'   => 'repeatableGroup',
		'info'     => 'repeatableGroup',
		'image'    => 'file',
	);

	public static function about( string $field ): string {
		return sprintf( 'Rank Math\'s "%s". %s', self::FIELDS[ $field ]['label'], self::EFFECTS[ $field ] );
	}

	public static function fields_for_current_user(): array {
		return array_filter(
			self::FIELDS,
			static function ( array $field ): bool {
				return current_user_can( 'rank_math_' . $field['type'] );
			}
		);
	}

	public static function stored( string $type ): array {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( self::OPTIONS[ $type ], 'options' );
		$value = get_option( self::OPTIONS[ $type ], array() );
		return is_array( $value ) ? $value : array();
	}

	public static function value( string $field, array $stored ) {
		$definition = self::FIELDS[ $field ];
		$raw        = $stored[ $definition['key'] ] ?? '';
		switch ( $definition['kind'] ) {
			case 'toggle':
				return ! in_array( $raw, array( 'off', 'false', '0', '', false, 0, null ), true );
			case 'choice':
				$text = is_bool( $raw ) ? ( $raw ? 'on' : 'off' ) : ( is_scalar( $raw ) ? (string) $raw : '' );
				$key  = array_search( $text, $definition['options'], true );
				return false === $key ? (string) array_key_first( $definition['options'] ) : (string) $key;
			case 'page':
				return is_scalar( $raw ) ? absint( $raw ) : 0;
			case 'image':
				$id = $stored[ $definition['key'] . '_id' ] ?? 0;
				return array(
					'id'  => is_scalar( $id ) ? absint( $id ) : 0,
					'url' => is_scalar( $raw ) ? (string) $raw : '',
				);
			case 'address':
				return self::parts( $raw, self::ADDRESS_PARTS );
			case 'hours':
			case 'phones':
			case 'info':
				$rows = array();
				foreach ( is_array( $raw ) ? $raw : array() as $row ) {
					if ( is_array( $row ) ) {
						$rows[] = array_merge( array_fill_keys( self::ROW_FIELDS[ $definition['kind'] ], '' ), self::parts( $row, self::ROW_FIELDS[ $definition['kind'] ] ) );
					}
				}
				return $rows;
		}
		$text = is_scalar( $raw ) ? (string) $raw : '';
		if ( 'urls' === $definition['kind'] ) {
			return array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ), 'strlen' ) );
		}
		return $text;
	}

	private static function parts( $raw, array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			if ( is_array( $raw ) && isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ) {
				$out[ $key ] = is_bool( $raw[ $key ] ) ? ( $raw[ $key ] ? 'on' : 'off' ) : (string) $raw[ $key ];
			}
		}
		return $out;
	}

	public static function default_of( string $field ): string {
		switch ( $field ) {
			case 'website_name':
			case 'knowledgegraph_name':
				return (string) get_bloginfo( 'name' );
			case 'url':
				return (string) home_url();
			case 'local_address_format':
				return '{address} {locality}, {region} {postalcode}';
		}
		return '';
	}

	public static function shown( string $field, $value ) {
		$kind = self::FIELDS[ $field ]['kind'];
		if ( 'address' === $kind ) {
			return (object) $value;
		}
		if ( 'secret' === $kind && '' !== $value ) {
			return 'set, ending in ' . substr( (string) $value, -4 );
		}
		return $value;
	}

	public static function values(): array {
		$stored = array();
		$out    = array();
		foreach ( self::fields_for_current_user() as $field => $definition ) {
			$stored[ $definition['type'] ] = $stored[ $definition['type'] ] ?? self::stored( $definition['type'] );
			$out[ $field ]                 = self::shown( $field, self::value( $field, $stored[ $definition['type'] ] ) );
		}
		return $out;
	}

	public static function why_not_applied( string $field, string $entity = '' ): string {
		if ( 'robots_txt' === $field ) {
			return self::robots_unserved_reason();
		}
		$module = self::FIELDS[ $field ]['module'] ?? '';
		if ( 'image-seo' === $module && ! \RankMath\Helper::is_module_active( $module ) ) {
			return 'Rank Math\'s Image SEO module is off, so this setting does nothing. Switch it on under Rank Math › Dashboard › Modules in wp-admin.';
		}
		if ( 'local-seo' === $module && ! \RankMath\Helper::is_module_active( $module ) ) {
			return 'Rank Math\'s Local SEO module is off, so Rank Math\'s settings screen does not offer this setting, and the Organization or Person in the site\'s schema carries only its name, logo and social profiles. Switch the module on under Rank Math › Dashboard › Modules in wp-admin.';
		}
		$needs  = self::FIELDS[ $field ]['entity'] ?? '';
		$entity = '' !== $entity ? $entity : (string) self::value( 'knowledgegraph_type', self::stored( 'titles' ) );
		if ( '' !== $needs && $needs !== $entity ) {
			return sprintf( 'Rank Math\'s settings screen offers this setting only when knowledgegraph_type is "%s", and its %s schema does not use it; this site\'s is "%s".', $needs, 'company' === $entity ? 'Organization' : 'Person', $entity );
		}
		return '';
	}

	public static function robots_lock_reason(): string {
		if ( ! is_super_admin() ) {
			return 'Rank Math lets only a super admin edit robots.txt (on a single site, a user who can delete users: administrators).';
		}
		if ( is_callable( array( '\\RankMath\\Helper', 'is_edit_allowed' ) ) && ! \RankMath\Helper::is_edit_allowed() ) {
			return 'Rank Math does not allow robots.txt to be edited on this site: DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS is set in wp-config.php, or a rank_math/can_edit_file filter says no.';
		}
		return self::robots_unserved_reason();
	}

	private static function robots_unserved_reason(): string {
		if ( self::has_physical_robots_file() ) {
			return 'A robots.txt file exists in the site\'s root folder, so the web server serves that file and Rank Math\'s text is never used. Rank Math locks its editor in this case; change or remove that file instead.';
		}
		if ( ! self::is_public() ) {
			return 'Settings › Reading › Search engine visibility is set to discourage search engines, so WordPress does not use Rank Math\'s robots.txt text. Rank Math locks its editor in this case.';
		}
		return '';
	}

	public static function robots_served_from( array $general ): string {
		if ( self::has_physical_robots_file() ) {
			return 'physical_file';
		}
		return self::is_public() && '' !== self::value( 'robots_txt', $general ) ? 'rank_math' : 'wordpress';
	}

	private static function has_physical_robots_file(): bool {
		return file_exists( ABSPATH . 'robots.txt' );
	}

	private static function is_public(): bool {
		return 0 !== absint( get_option( 'blog_public' ) );
	}
}
