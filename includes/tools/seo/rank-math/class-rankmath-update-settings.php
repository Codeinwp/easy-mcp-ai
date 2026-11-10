<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Option_Lock;
use Easy_MCP_AI\Tools\Base_Tool;

class Rankmath_Update_Settings extends Base_Tool {

	use Rank_Math_Setup;

	const LOCK = 'easy_mcp_ai_rank_math_settings_lock';

	const LOCK_TTL = 30;

	const LOCK_WAIT = 10;

	const ROUTE = '/rankmath/v1/updateSettings';

	const KEPT = array(
		'general' => array(
			'console_email_reports'   => 'toggle',
			'console_email_frequency' => 'text',
		),
	);

	const SWITCH_WORDS = array( 'on', 'off', 'true', 'false', '0', '1' );

	const BOOLEAN_WORDS = array( 'on', 'off', 'true', 'false' );

	const TEXT_KINDS = array( 'text', 'handle', 'textarea', 'email', 'phone', 'secret', 'geo', 'business' );

	const PHONE_PATTERN = '/^[0-9+()\-. ]*[0-9][0-9+()\-. ]*\z/';

	const TIME_PATTERN = '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]-(?:(?:[01][0-9]|2[0-3]):[0-5][0-9]|24:00)\z/';

	public function get_name() {
		return 'wp_rm_update_settings';
	}

	public function get_description() {
		return 'Updates Rank Math SEO\'s site-wide settings, as Rank Math\'s settings screen saves them. Send only what changes; wp_rm_get_settings shows the current values and what /robots.txt serves. Covers the noindex switches for paginated pages, search results and password-protected pages, the 404 title, the social profiles used in the site\'s schema, the Image SEO formats (they need Rank Math\'s Image SEO module on), the custom robots.txt text, and the Local SEO tab (identity, logo, contact details, address, business type, opening hours, Google Maps API key, geo, About and Contact pages). A Local SEO value Rank Math\'s own settings screen would not offer (its module off, or a Person field on an Organization) is refused; clearing one ("", [], 0) is always accepted. A call that changes robots_txt always asks for approval while approvals are on, and robots_txt is refused wherever Rank Math\'s own screen locks it: a robots.txt file in the site root, search engines discouraged, DISALLOW_FILE_EDIT, or a user who is not a super admin. An unknown setting or a bad value is refused and nothing is changed. Returns { updated, changed_fields, unchanged_fields, stored_differently, not_saved, previous, warnings, settings }: updated is false when every value was already stored as Rank Math would store it; stored_differently lists settings Rank Math\'s sanitiser changed (it strips HTML, collapses whitespace and removes % followed by two digits from text); not_saved lists settings nothing was stored for; previous holds the old value of each changed setting. Calls made at the same moment are saved one after another. Pages already in a page cache keep the old output until it is cleared: use wp_clear_cache with scope "site"; a CDN may keep serving the old /robots.txt until it is purged there too. Requires Rank Math\'s rank_math_titles capability (administrators by default); the Image SEO and robots.txt settings also rank_math_general.';
	}

	public function get_category() {
		return 'rank-math';
	}

	public function get_required_capability() {
		return function_exists( 'rank_math' ) ? 'rank_math_titles' : 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => $this->get_title(),
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_approval_policy( array $arguments ) {
		return array_key_exists( 'robots_txt', $arguments ) ? 'always' : 'inherit';
	}

	public function get_redacted_arguments() {
		return array( 'maps_api_key' );
	}

	public function get_input_schema() {
		$properties = array();
		foreach ( array_keys( Rankmath_Site_Settings::FIELDS ) as $field ) {
			$properties[ $field ] = self::property( $field );
		}
		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_ready( 'change its settings' );
		$wanted = self::planned( $arguments );

		$lock = Option_Lock::acquire_waiting( self::LOCK, static::LOCK_TTL, static::LOCK_WAIT, 'wp_rm_update_settings' );
		try {
			$before = self::current( $wanted );
			self::refuse_not_applied( $wanted, self::clears( $arguments ) );
			$wanted = self::merged( $wanted, $before );
			$expected = self::expected( $wanted );
			self::refuse_values_rank_math_would_not_use( $expected );
			$changes = self::changes( $wanted, $expected, $before );
			$after    = $before;
			foreach ( $changes as $type => $fields ) {
				$lock = self::renewed( $lock, $changes, $before, $after );
				try {
					$this->save( $type, $fields );
				} catch ( \RuntimeException $e ) {
					$after[ $type ] = Rankmath_Site_Settings::stored( $type );
					throw new \RuntimeException( self::not_saved_message( sprintf( 'Saving Rank Math\'s %s settings failed (%s)', $type, rtrim( $e->getMessage(), '.' ) ), $changes, $before, $after ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				$after[ $type ] = Rankmath_Site_Settings::stored( $type );
			}
			$settings = Rankmath_Site_Settings::values();
		} finally {
			Option_Lock::release( self::LOCK, $lock );
		}
		return self::report( $wanted, $expected, $before, $after, $settings );
	}

	private static function planned( array $arguments ): array {
		$accepted = array_keys( Rankmath_Site_Settings::FIELDS );
		$unknown  = array_diff( array_keys( $arguments ), $accepted );
		if ( $unknown ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown setting(s): %s. Accepted: %s. Nothing was changed.', implode( ', ', array_map( 'sanitize_key', $unknown ) ), implode( ', ', $accepted ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$wanted = array();
		foreach ( Rankmath_Site_Settings::FIELDS as $field => $definition ) {
			if ( array_key_exists( $field, $arguments ) ) {
				$wanted[ $field ] = self::checked( $field, $definition['kind'], $arguments[ $field ] );
			}
		}
		if ( ! $wanted ) {
			throw new \InvalidArgumentException( 'Nothing to update: pass at least one setting. wp_rm_get_settings shows the current ones.' );
		}
		self::refuse_without_capability( array_keys( $wanted ) );
		self::refuse_not_applied( $wanted, self::clears( $arguments ) );
		return $wanted;
	}

	private static function clears( array $arguments ): array {
		return array_keys( array_filter( $arguments, array( self::class, 'is_clear' ) ) );
	}

	private static function is_clear( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $part ) {
				if ( '' !== $part ) {
					return false;
				}
			}
			return true;
		}
		return '' === $value || 0 === $value;
	}

	private static function checked( string $field, string $kind, $value ) {
		switch ( $kind ) {
			case 'choice':
				return self::checked_choice( $field, $value );
			case 'page':
				return self::checked_page( $field, $value );
			case 'image':
				return self::checked_image( $field, $value );
			case 'address':
				return self::checked_address( $field, $value );
			case 'hours':
			case 'phones':
			case 'info':
				return self::checked_rows( $field, $kind, $value );
		}
		if ( 'toggle' === $kind ) {
			if ( ! is_bool( $value ) ) {
				throw new \InvalidArgumentException( sprintf( '%s must be true or false. Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			return $value;
		}
		if ( 'urls' === $kind ) {
			if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
				throw new \InvalidArgumentException( sprintf( '%s must be a list of URLs ([] clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			foreach ( $value as $i => $url ) {
				if ( ! is_string( $url ) || '' === $url || ! self::is_web_url( $url ) ) {
					throw new \InvalidArgumentException( sprintf( '%s[%d] must be an http or https URL. Nothing was changed.', $field, $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
			}
			return $value;
		}
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a string ("" clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$default = Rankmath_Site_Settings::default_of( $field );
		if ( '' !== $default && '' === trim( $value ) ) {
			return $default;
		}
		if ( in_array( $kind, array( 'email', 'phone', 'secret', 'geo', 'business' ), true ) && '' !== $value ) {
			return self::checked_text( $field, $kind, $value );
		}
		if ( 'url' === $kind && '' !== $value && ! self::is_web_url( $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be an http or https URL ("" clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 'handle' === $kind ) {
			$value = ltrim( $value, '@' );
			if ( '' !== $value && ! preg_match( '/^[A-Za-z0-9_]{1,15}\z/', $value ) ) {
				throw new \InvalidArgumentException( sprintf( '%s must be a Twitter/X username: up to 15 letters, digits or underscores, with or without the leading @ ("" clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
		if ( 'robots' === $kind ) {
			$value = trim( $value );
			if ( '' !== $value && wp_strip_all_tags( $value ) !== $value ) {
				throw new \InvalidArgumentException( sprintf( '%s cannot contain HTML: Rank Math removes anything that looks like a tag (<…>), which would change the rules. Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
		return $value;
	}

	private static function is_web_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
			&& '' !== (string) ( $parts['host'] ?? '' )
			&& esc_url_raw( $url, array( 'http', 'https' ) ) === $url;
	}

	private static function checked_text( string $field, string $kind, string $value ): string {
		switch ( $kind ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					throw new \InvalidArgumentException( sprintf( '%s must be an email address ("" clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				return $value;
			case 'phone':
				if ( ! preg_match( self::PHONE_PATTERN, $value ) ) {
					throw new \InvalidArgumentException( sprintf( '%s must be a telephone number of digits, spaces and + ( ) - . such as +1-401-555-1212 ("" clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				return $value;
			case 'secret':
				if ( ! preg_match( '/^[A-Za-z0-9_\-]+\z/', $value ) ) {
					throw new \InvalidArgumentException( sprintf( '%s must be a Google API key: letters, digits, _ and - only ("" removes it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				return $value;
			case 'geo':
				if ( ! preg_match( '/^\s*(-?[0-9]{1,3}(?:\.[0-9]+)?)\s*,\s*(-?[0-9]{1,3}(?:\.[0-9]+)?)\s*\z/', $value, $m ) || abs( (float) $m[1] ) > 90 || abs( (float) $m[2] ) > 180 ) {
					throw new \InvalidArgumentException( sprintf( '%s must be "latitude,longitude" in decimal degrees, such as "40.7128,-74.0060" ("" clears it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				return $m[1] . ',' . $m[2];
		}
		$types = self::business_types();
		if ( ! in_array( $value, $types, true ) ) {
			$near = self::near_business_types( $types, $value );
			throw new \InvalidArgumentException( sprintf( '%s "%s" is not a business type Rank Math offers. %s Nothing was changed.', $field, $value, $near ? 'Did you mean: ' . implode( ', ', array_slice( $near, 0, 8 ) ) . '?' : 'Use the schema.org type name without spaces, such as LocalBusiness, Restaurant or Dentist ("" means Organization).' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $value;
	}

	private static function near_business_types( array $types, string $value ): array {
		$wanted    = strtolower( str_replace( ' ', '', $value ) );
		$contained = array();
		$distance  = array();
		foreach ( $types as $type ) {
			if ( '' === $type ) {
				continue;
			}
			$name = strtolower( $type );
			if ( '' !== $wanted && false !== strpos( $name, $wanted ) ) {
				$contained[] = $type;
			} elseif ( strlen( $wanted ) <= 255 && abs( strlen( $name ) - strlen( $wanted ) ) <= 2 ) {
				$edits = levenshtein( $name, $wanted );
				if ( $edits <= 2 ) {
					$distance[ $type ] = $edits;
				}
			}
		}
		asort( $distance );
		return array_merge( $contained, array_map( 'strval', array_keys( $distance ) ) );
	}

	private static function business_types(): array {
		if ( ! is_callable( array( '\\RankMath\\Helper', 'choices_business_types' ) ) ) {
			throw new \RuntimeException( 'This version of Rank Math has no list of business types (Helper::choices_business_types()), so local_business_type cannot be checked. Nothing was changed.' );
		}
		return array_map( 'strval', array_keys( (array) \RankMath\Helper::choices_business_types( true ) ) );
	}

	private static function checked_choice( string $field, $value ): string {
		$options = array_map( 'strval', array_keys( Rankmath_Site_Settings::FIELDS[ $field ]['options'] ) );
		if ( ! is_string( $value ) || ! in_array( $value, $options, true ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be one of: %s. Nothing was changed.', $field, implode( ', ', $options ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $value;
	}

	private static function checked_page( string $field, $value ): int {
		if ( ! is_int( $value ) || $value < 0 ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a page id (0 removes it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 0 === $value ) {
			return 0;
		}
		$post = get_post( $value );
		if ( ! $post || 'page' !== $post->post_type || ! current_user_can( 'read_post', $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s: page %d was not found. Nothing was changed.', $field, $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 'publish' !== $post->post_status ) {
			throw new \InvalidArgumentException( sprintf( '%s: page %d is %s, not published, so no visitor or search engine would see it. Publish it first. Nothing was changed.', $field, $value, $post->post_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $value;
	}

	private static function checked_image( string $field, $value ): array {
		if ( ! is_int( $value ) || $value < 0 ) {
			throw new \InvalidArgumentException( sprintf( '%s must be the id of an image in the media library (0 removes it). Nothing was changed.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 0 === $value ) {
			return array(
				'id'  => 0,
				'url' => '',
			);
		}
		$post = get_post( $value );
		$url  = $post && 'attachment' === $post->post_type && current_user_can( 'read_post', $value ) && wp_attachment_is_image( $value ) ? (string) wp_get_attachment_url( $value ) : '';
		if ( '' === $url ) {
			throw new \InvalidArgumentException( sprintf( '%s: %d is not an image in the media library. Nothing was changed.', $field, $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return array(
			'id'  => $value,
			'url' => $url,
		);
	}

	private static function checked_address( string $field, $value ): array {
		$parts = Rankmath_Site_Settings::ADDRESS_PARTS;
		if ( array() === $value ) {
			return array_fill_keys( $parts, '' );
		}
		if ( ! is_array( $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be an object with at least one of %s ("" clears a part; [] clears the address). Nothing was changed.', $field, implode( ', ', $parts ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$unknown = array_diff( array_map( 'strval', array_keys( $value ) ), $parts );
		if ( $unknown ) {
			throw new \InvalidArgumentException( sprintf( '%s has no part %s. Its parts: %s. Nothing was changed.', $field, implode( ', ', array_map( 'sanitize_key', $unknown ) ), implode( ', ', $parts ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$out = array();
		foreach ( $parts as $part ) {
			if ( ! array_key_exists( $part, $value ) ) {
				continue;
			}
			if ( ! is_string( $value[ $part ] ) ) {
				throw new \InvalidArgumentException( sprintf( '%s.%s must be a string ("" clears it). Nothing was changed.', $field, $part ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$out[ $part ] = $value[ $part ];
		}
		return $out;
	}

	private static function checked_rows( string $field, string $kind, $value ): array {
		list( $choice, $text ) = Rankmath_Site_Settings::ROW_FIELDS[ $kind ];
		if ( ! is_array( $value ) || array_values( $value ) !== $value ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a list of { %s, %s } ([] removes them). Nothing was changed.', $field, $choice, $text ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$rows = array();
		foreach ( $value as $i => $row ) {
			if ( ! is_array( $row ) || ! array_key_exists( $choice, $row ) || ! array_key_exists( $text, $row ) || 2 !== count( $row ) ) {
				throw new \InvalidArgumentException( sprintf( '%s[%d] must be an object with exactly %s and %s. Nothing was changed.', $field, $i, $choice, $text ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			if ( ! is_string( $row[ $choice ] ) || ! in_array( $row[ $choice ], Rankmath_Site_Settings::ROW_CHOICES[ $kind ], true ) ) {
				throw new \InvalidArgumentException( sprintf( '%s[%d].%s must be one of: %s. Nothing was changed.', $field, $i, $choice, implode( ', ', Rankmath_Site_Settings::ROW_CHOICES[ $kind ] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$rows[] = array(
				$choice => $row[ $choice ],
				$text   => self::checked_row_text( sprintf( '%s[%d].%s', $field, $i, $text ), $kind, $row[ $text ] ),
			);
		}
		return $rows;
	}

	private static function checked_row_text( string $path, string $kind, $value ): string {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a non-empty string. Nothing was changed.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 'hours' === $kind && ! preg_match( self::TIME_PATTERN, $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a time range HH:MM-HH:MM in 24-hour time, such as 09:00-17:00. Nothing was changed.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 'phones' === $kind && ! preg_match( self::PHONE_PATTERN, $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a telephone number of digits, spaces and + ( ) - . such as +1-401-555-1212. Nothing was changed.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $value;
	}

	private static function refuse_without_capability( array $fields ): void {
		$missing = array();
		foreach ( $fields as $field ) {
			$capability = 'rank_math_' . Rankmath_Site_Settings::FIELDS[ $field ]['type'];
			if ( ! current_user_can( $capability ) ) {
				$missing[ $capability ][] = $field;
			}
		}
		if ( ! $missing ) {
			return;
		}
		$parts = array();
		foreach ( $missing as $capability => $names ) {
			$parts[] = sprintf( '%s (needed for %s)', $capability, implode( ', ', $names ) );
		}
		throw new \RuntimeException( sprintf( 'This account lacks Rank Math\'s %s capability. Rank Math gives it to administrators by default; other roles get it under Rank Math › Role Manager. Nothing was changed.', implode( ' and ', $parts ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	private static function refuse_not_applied( array $wanted, array $clears ): void {
		$entity  = isset( $wanted['knowledgegraph_type'] ) ? (string) $wanted['knowledgegraph_type'] : '';
		$reasons = array();
		foreach ( array_keys( $wanted ) as $field ) {
			$definition = Rankmath_Site_Settings::FIELDS[ $field ];
			if ( in_array( $field, $clears, true ) && ( 'local-seo' === ( $definition['module'] ?? '' ) || isset( $definition['entity'] ) ) ) {
				continue;
			}
			$reason = 'robots_txt' === $field ? Rankmath_Site_Settings::robots_lock_reason() : Rankmath_Site_Settings::why_not_applied( $field, $entity );
			if ( '' !== $reason ) {
				$reasons[] = $field . ': ' . $reason;
			}
		}
		if ( ! $reasons ) {
			return;
		}
		$whole = implode( ' ', $reasons ) . ' Nothing was changed.';
		if ( \Easy_MCP_AI\MCP\Refusal_Text::fits( $whole ) ) {
			throw new \RuntimeException( $whole ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$message = \Easy_MCP_AI\MCP\Refusal_Text::fit( 1 === count( $reasons ) ? '%s Nothing was changed; the whole reason is below.' : '%s Nothing was changed; every reason is below.', array( implode( ' ', $reasons ) ) );
		throw new \Easy_MCP_AI\MCP\Detailed_Tool_Error( $message, implode( "\n", $reasons ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	private static function current( array $wanted ): array {
		$before = array();
		foreach ( array_keys( $wanted ) as $field ) {
			$type            = Rankmath_Site_Settings::FIELDS[ $field ]['type'];
			$before[ $type ] = $before[ $type ] ?? Rankmath_Site_Settings::stored( $type );
		}
		$settings = rank_math()->settings ?? null;
		if ( ! is_object( $settings ) || ! is_callable( array( $settings, 'reset' ) ) ) {
			throw new \RuntimeException( 'This version of Rank Math has no settings reset (rank_math()->settings->reset()), so a save could undo another request\'s change. Nothing was changed.' );
		}
		$settings->reset();
		return $before;
	}

	private static function merged( array $wanted, array $before ): array {
		foreach ( $wanted as $field => $value ) {
			$definition = Rankmath_Site_Settings::FIELDS[ $field ];
			if ( 'address' !== $definition['kind'] ) {
				continue;
			}
			$stored = Rankmath_Site_Settings::value( $field, $before[ $definition['type'] ] );
			$full   = array();
			foreach ( Rankmath_Site_Settings::ADDRESS_PARTS as $part ) {
				$full[ $part ] = $value[ $part ] ?? $stored[ $part ] ?? '';
			}
			$clean            = self::stored_address( $definition['key'], $full );
			$wanted[ $field ] = array_filter(
				$full,
				static function ( string $part ) use ( $clean ): bool {
					return '' !== ( $clean[ $part ] ?? '' );
				},
				ARRAY_FILTER_USE_KEY
			);
		}
		return $wanted;
	}

	private static function stored_address( string $key, array $address ): array {
		if ( ! is_callable( array( '\\RankMath\\Admin\\Sanitize_Settings', 'sanitize' ) ) ) {
			return array_map( 'trim', $address );
		}
		$clean = \RankMath\Admin\Sanitize_Settings::sanitize( array( $key => $address ), array( $key => 'group' ) );
		$out   = array();
		foreach ( is_array( $clean ) && is_array( $clean[ $key ] ?? null ) ? $clean[ $key ] : array() as $part => $text ) {
			$out[ (string) $part ] = is_scalar( $text ) ? (string) $text : '';
		}
		return $out;
	}

	private static function expected( array $wanted ): array {
		if ( ! is_callable( array( '\\RankMath\\Admin\\Sanitize_Settings', 'sanitize' ) ) ) {
			return $wanted;
		}
		$expected = array();
		foreach ( $wanted as $field => $value ) {
			list( $settings, $types ) = self::wire( $field, $value );
			$clean                    = \RankMath\Admin\Sanitize_Settings::sanitize( $settings, $types );
			$expected[ $field ]       = Rankmath_Site_Settings::value( $field, is_array( $clean ) ? $clean : array() );
		}
		return $expected;
	}

	private static function refuse_values_rank_math_would_not_use( array $expected ): void {
		foreach ( $expected as $field => $value ) {
			$kind = Rankmath_Site_Settings::FIELDS[ $field ]['kind'];
			if ( in_array( $kind, self::TEXT_KINDS, true ) && in_array( $value, self::SWITCH_WORDS, true ) ) {
				$word = 'secret' === $kind ? 'one of ' . implode( ', ', self::SWITCH_WORDS ) : '"' . $value . '"';
				throw new \InvalidArgumentException( sprintf( '%s cannot be %s: Rank Math reads that word as a switch value, not as text, so it would not use it. Nothing was changed.', $field, $word ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			if ( ! in_array( $kind, array( 'address', 'hours', 'phones', 'info' ), true ) ) {
				continue;
			}
			foreach ( 'address' === $kind ? array( $value ) : $value as $i => $row ) {
				foreach ( $row as $key => $text ) {
					if ( in_array( $text, self::BOOLEAN_WORDS, true ) ) {
						throw new \InvalidArgumentException( sprintf( '%s.%s cannot be "%s": Rank Math reads that word as true or false, not as text. Nothing was changed.', $field, $key, $text ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					}
				}
				$text_field = 'address' === $kind ? '' : Rankmath_Site_Settings::ROW_FIELDS[ $kind ][1];
				if ( '' !== $text_field && in_array( $row[ $text_field ], array( '', '0' ), true ) ) {
					throw new \InvalidArgumentException( sprintf( '%s[%d].%s would be stored as "%s", and Rank Math skips a row whose %s is empty or 0. Nothing was changed.', $field, $i, $text_field, $row[ $text_field ], $text_field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
			}
		}
	}

	private static function changes( array $wanted, array $expected, array $before ): array {
		$changes = array();
		foreach ( $expected as $field => $value ) {
			$type = Rankmath_Site_Settings::FIELDS[ $field ]['type'];
			if ( Rankmath_Site_Settings::value( $field, $before[ $type ] ) !== $value ) {
				$changes[ $type ][ $field ] = $wanted[ $field ];
			}
		}
		return $changes;
	}

	private function save( string $type, array $fields ): void {
		$settings = array();
		$types    = array();
		foreach ( $fields as $field => $value ) {
			list( $sent, $sent_types ) = self::wire( $field, $value );
			$settings                 += $sent;
			$types                    += $sent_types;
		}
		$updated = array_keys( $settings );
		foreach ( self::KEPT[ $type ] ?? array() as $key => $field_type ) {
			$settings[ $key ] = \RankMath\Helper::get_settings( $type . '.' . $key );
			$types[ $key ]    = $field_type;
		}
		$data = $this->rest_request(
			'POST',
			self::ROUTE,
			array(
				'type'       => $type,
				'settings'   => $settings,
				'fieldTypes' => $types,
				'updated'    => $updated,
			)
		);
		if ( ! is_array( $data ) || ! array_key_exists( 'settings', $data ) ) {
			throw new \RuntimeException( sprintf( 'Rank Math answered "%s"', is_string( $data ) ? $data : (string) wp_json_encode( $data ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	private static function wire( string $field, $value ): array {
		$definition = Rankmath_Site_Settings::FIELDS[ $field ];
		$key        = $definition['key'];
		$type       = Rankmath_Site_Settings::FIELD_TYPES[ $definition['kind'] ];
		switch ( $definition['kind'] ) {
			case 'toggle':
				$sent = (bool) $value;
				break;
			case 'urls':
				$sent = implode( "\n", $value );
				break;
			case 'choice':
				$sent = $definition['options'][ $value ];
				break;
			case 'page':
				$sent = $value ? (string) $value : '';
				break;
			case 'image':
				return array(
					array(
						$key         => $value['url'],
						$key . '_id' => $value['id'] ? (string) $value['id'] : '',
					),
					array(
						$key         => $type,
						$key . '_id' => 'text',
					),
				);
			case 'address':
			case 'hours':
			case 'phones':
			case 'info':
				$sent = $value;
				break;
			default:
				$sent = (string) $value;
		}
		return array( array( $key => $sent ), array( $key => $type ) );
	}

	private static function renewed( string $lock, array $changes, array $before, array $after ): string {
		$renewed = Option_Lock::renew( self::LOCK, $lock );
		if ( '' !== $renewed ) {
			return $renewed;
		}
		throw new \RuntimeException( self::not_saved_message( Option_Lock::lost_cause( 'wp_rm_update_settings', static::LOCK_TTL ), $changes, $before, $after ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	private static function not_saved_message( string $cause, array $changes, array $before, array $after ): string {
		$done = array();
		$lost = array();
		foreach ( $changes as $type => $fields ) {
			foreach ( array_keys( $fields ) as $field ) {
				if ( Rankmath_Site_Settings::value( $field, $after[ $type ] ) !== Rankmath_Site_Settings::value( $field, $before[ $type ] ) ) {
					$done[] = $field;
				} else {
					$lost[] = $field;
				}
			}
		}
		if ( ! $lost ) {
			return sprintf( '%s, although these were saved: %s. Read the settings with wp_rm_get_settings to check them.', $cause, implode( ', ', $done ) );
		}
		return sprintf( '%s, so these were not saved: %s.%s Read the settings with wp_rm_get_settings and send them again.', $cause, implode( ', ', $lost ), $done ? ' Saved before that: ' . implode( ', ', $done ) . '.' : '' );
	}

	private static function report( array $wanted, array $expected, array $before, array $after, array $settings ): array {
		$changed     = array();
		$unchanged   = array();
		$differently = array();
		$not_saved   = array();
		$previous    = array();
		foreach ( $wanted as $field => $value ) {
			$type = Rankmath_Site_Settings::FIELDS[ $field ]['type'];
			$was  = Rankmath_Site_Settings::value( $field, $before[ $type ] );
			$now  = Rankmath_Site_Settings::value( $field, $after[ $type ] );
			if ( $was === $expected[ $field ] ) {
				$unchanged[] = $field;
			} elseif ( $now === $was ) {
				$not_saved[] = $field;
			} else {
				$changed[]          = $field;
				$previous[ $field ] = Rankmath_Site_Settings::shown( $field, $was );
			}
			if ( $now !== $value && ! in_array( $field, $not_saved, true ) ) {
				$differently[] = $field;
			}
		}
		return array(
			'updated'            => (bool) $changed,
			'changed_fields'     => $changed,
			'unchanged_fields'   => $unchanged,
			'stored_differently' => $differently,
			'not_saved'          => $not_saved,
			'previous'           => (object) $previous,
			'warnings'           => array_merge(
				in_array( 'robots_txt', $changed, true ) ? self::robots_warnings( (string) $wanted['robots_txt'] ) : array(),
				self::kept_warnings( $before, $after )
			),
			'settings'           => $settings,
		);
	}

	private static function kept_warnings( array $before, array $after ): array {
		$warnings = array();
		foreach ( self::KEPT as $type => $keys ) {
			foreach ( array_keys( isset( $before[ $type ], $after[ $type ] ) ? $keys : array() ) as $key ) {
				$was = \RankMath\Helper::normalize_data( $before[ $type ][ $key ] ?? false ) ?: false;
				$now = \RankMath\Helper::normalize_data( $after[ $type ][ $key ] ?? false ) ?: false;
				if ( $was !== $now ) {
					$warnings[] = sprintf( 'Rank Math stored its %s setting differently while saving (a filter on its sanitiser changed it); this call did not ask for that change.', $key );
				}
			}
		}
		return $warnings;
	}

	private static function robots_warnings( string $text ): array {
		if ( '' === $text || false !== stripos( $text, 'sitemap:' ) || ! \RankMath\Helper::is_module_active( 'sitemap' ) ) {
			return array();
		}
		$url = is_callable( array( '\\RankMath\\Sitemap\\Router', 'get_base_url' ) ) && is_callable( array( '\\RankMath\\Sitemap\\Sitemap', 'get_sitemap_index_slug' ) )
			? (string) \RankMath\Sitemap\Router::get_base_url( \RankMath\Sitemap\Sitemap::get_sitemap_index_slug() . '.xml' )
			: '';
		return array( sprintf( 'robots.txt now has no Sitemap line, so search engines no longer find Rank Math\'s sitemap there. Add the line "Sitemap: %s" to keep it.', '' !== $url ? $url : '<your sitemap index URL>' ) );
	}

	private static function property( string $field ): array {
		$definition = Rankmath_Site_Settings::FIELDS[ $field ];
		$where      = Rankmath_Site_Settings::about( $field );
		$clears = '' === Rankmath_Site_Settings::default_of( $field ) ? ' "" clears it.' : '';
		switch ( $definition['kind'] ) {
			case 'toggle':
				return array( 'type' => 'boolean', 'description' => $where );
			case 'urls':
				return array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => $where . ' A list of http or https URLs; [] clears it.',
				);
			case 'url':
				return array( 'type' => 'string', 'description' => $where . ' An http or https URL.' . $clears );
			case 'handle':
				return array( 'type' => 'string', 'description' => $where . ' The username, with or without the leading @; "" clears it.' );
			case 'robots':
				return array( 'type' => 'string', 'description' => $where . ' The whole robots.txt text, which replaces the current one; "" goes back to WordPress\'s default. No HTML.' );
			case 'choice':
				return array(
					'type'        => 'string',
					'enum'        => array_map( 'strval', array_keys( $definition['options'] ) ),
					'description' => $where,
				);
			case 'page':
			case 'image':
				return array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => $where,
				);
			case 'address':
				$parts = array();
				foreach ( Rankmath_Site_Settings::ADDRESS_PARTS as $part ) {
					$parts[ $part ] = array( 'type' => 'string' );
				}
				return array(
					'type'        => 'object',
					'properties'  => $parts,
					'description' => $where . ' Parts not sent are kept; "" clears a part; {} clears the address.',
				);
			case 'hours':
			case 'phones':
			case 'info':
				list( $choice, $text ) = Rankmath_Site_Settings::ROW_FIELDS[ $definition['kind'] ];
				return array(
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							$choice => array(
								'type' => 'string',
								'enum' => Rankmath_Site_Settings::ROW_CHOICES[ $definition['kind'] ],
							),
							$text   => array( 'type' => 'string' ),
						),
						'required'   => array( $choice, $text ),
					),
					'description' => $where,
				);
			case 'email':
				return array( 'type' => 'string', 'description' => $where . ' An email address;' . $clears );
			case 'phone':
				return array( 'type' => 'string', 'description' => $where . ' Digits, spaces and + ( ) - ., such as +1-401-555-1212;' . $clears );
			default:
				return array( 'type' => 'string', 'description' => $where . $clears );
		}
	}
}
