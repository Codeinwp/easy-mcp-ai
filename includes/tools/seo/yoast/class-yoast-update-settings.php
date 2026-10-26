<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Option_Lock;
use Easy_MCP_AI\Tools\Base_Tool;






















class Yoast_Update_Settings extends Base_Tool {

	const MAX_ITEMS = 50;

	
	const LOCK = 'easy_mcp_ai_yoast_settings_lock';

	
	const LOCK_TTL = 30;

	
	const LOCK_WAIT = 10;

	public function get_name() {
		return 'wp_yoast_update_settings';
	}

	public function get_description() {
		return 'Updates Yoast SEO\'s site-wide title and social settings, as Yoast\'s Settings screen does. Send only what changes: `separator` (a key such as sc-pipe; wp_yoast_get_settings lists them), `templates` (a list of { content_type, field, value } with field title, description, social_title or social_description; "" clears it; Yoast variables such as %%title%% and %%sep%% are kept), `social_images` (a list of { content_type, image_id }, the attachment ID of an image in the media library; 0 clears it) and `default_social_image_id` (the image used when a page has none; 0 clears it). Content types are the ones wp_yoast_get_settings lists, e.g. post_type:post, archive:product, taxonomy:category, home (used only while the homepage shows the latest posts), author_archive, date_archive, search, 404. A content type, field or separator Yoast does not have here, an image ID that is not an image, or a setting a network administrator locked is refused, and nothing is changed. Returns { updated, changed_fields, unchanged_fields, stored_differently, not_saved, previews, settings }: updated is false when every value sent was already stored; stored_differently lists settings Yoast\'s sanitiser changed (HTML is stripped from templates); not_saved lists settings WordPress stored nothing for (the database refused the write, or another process deleted Yoast\'s option during the call); previews shows each post type title or description template applied to that type\'s newest published post. Calls made at the same moment are saved one after another, each over what the one before saved. Pages already in a page cache keep the old titles until it is cleared: use wp_clear_cache with scope "site", and wp_yoast_get_head to check a page. Requires Yoast\'s wpseo_manage_options capability (administrators and SEO managers).';
	}

	public function get_category() {
		return 'yoast-seo';
	}

	public function get_required_capability() {
		
		return 'wpseo_manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => $this->get_title(),
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		$separator = array(
			'type'        => 'string',
			'description' => 'Title separator key, e.g. sc-dash (-), sc-ndash (–), sc-pipe (|), sc-bull (•). wp_yoast_get_settings lists every key with its character.',
		);
		$keys      = $this->separator_keys();
		if ( $keys ) {
			$separator['enum'] = $keys;
		}
		return array(
			'type'       => 'object',
			'properties' => array(
				'separator'               => $separator,
				'templates'               => array(
					'type'        => 'array',
					'maxItems'    => self::MAX_ITEMS,
					'description' => 'Template changes, one per content type and field.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'content_type' => array( 'type' => 'string', 'description' => 'E.g. post_type:post, archive:product, taxonomy:category, home, author_archive, date_archive, search, 404.' ),
							'field'        => array( 'type' => 'string', 'enum' => array_keys( Yoast_Site_Settings::TEXT_PREFIXES ), 'description' => 'Which template: the SEO title, the meta description, or the social (Open Graph) title or description.' ),
							'value'        => array( 'type' => 'string', 'description' => 'The template, e.g. "%%title%% %%sep%% %%sitename%%"; "" clears it.' ),
						),
						'required'   => array( 'content_type', 'field', 'value' ),
					),
				),
				'social_images'           => array(
					'type'        => 'array',
					'maxItems'    => self::MAX_ITEMS,
					'description' => 'Social image changes, one per content type.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'content_type' => array( 'type' => 'string', 'description' => 'E.g. post_type:post, taxonomy:category, home.' ),
							'image_id'     => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Attachment ID of an image in the media library; 0 clears it.' ),
						),
						'required'   => array( 'content_type', 'image_id' ),
					),
				),
				'default_social_image_id' => array( 'type' => 'integer', 'minimum' => 0, 'description' => 'Attachment ID of the image used when a page has no social image of its own; 0 clears it.' ),
			),
		);
	}

	public function execute( array $arguments ) {
		list( $titles, $social ) = Yoast_Site_Settings::instances();
		$defaults                = (array) $titles->get_defaults();
		$planned                 = $this->planned_changes( $arguments, $titles, $social, $defaults );

		
		
		$lock = self::lock();
		try {
			$before = array(
				Yoast_Site_Settings::TITLES => self::fresh( Yoast_Site_Settings::TITLES ),
				Yoast_Site_Settings::SOCIAL => self::fresh( Yoast_Site_Settings::SOCIAL ),
			);
			$unsaved = array();
			$written = array();
			foreach ( $before as $option => $current ) {
				$lock = self::renewed( $lock, $planned, $written );
				if ( false === $this->write( $option, $current, $planned ) ) {
					$unsaved[] = $option;
				} else {
					$written[] = $option;
				}
			}
			$after = array(
				Yoast_Site_Settings::TITLES => Yoast_Site_Settings::stored( Yoast_Site_Settings::TITLES ),
				Yoast_Site_Settings::SOCIAL => Yoast_Site_Settings::stored( Yoast_Site_Settings::SOCIAL ),
			);
		} finally {
			Option_Lock::release( self::LOCK, $lock );
		}

		$changed     = array();
		$unchanged   = array();
		$differently = array();
		$not_saved   = array();
		foreach ( $planned as $change ) {
			$keys = array_keys( $change['values'] );
			$sent = array_map( 'strval', $change['values'] );
			$was  = self::values_of( $before[ $change['option'] ], $keys );
			$now  = self::values_of( $after[ $change['option'] ], $keys );
			if ( $now !== $was ) {
				$changed[] = $change['label'];
			} elseif ( $was === $sent ) {
				$unchanged[] = $change['label'];
			}
			if ( $now !== $sent ) {
				
				
				
				if ( $now === $was && in_array( $change['option'], $unsaved, true ) ) {
					$not_saved[] = $change['label'];
				} else {
					$differently[] = $change['label'];
				}
			}
		}

		return array(
			'updated'            => (bool) $changed,
			'changed_fields'     => $changed,
			'unchanged_fields'   => $unchanged,
			'stored_differently' => $differently,
			'not_saved'          => $not_saved,
			'previews'           => $this->previews( $planned, $after[ Yoast_Site_Settings::TITLES ] ),
			'settings'           => Yoast_Site_Settings::read( $titles, $social ),
		);
	}

	











	private function planned_changes( array $arguments, object $titles, object $social, array $defaults ): array {
		$allowed = array( 'separator', 'templates', 'social_images', 'default_social_image_id' );
		$unknown = array_diff( array_keys( $arguments ), $allowed );
		if ( $unknown ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown argument(s): %s. Accepted: %s.', implode( ', ', array_map( 'sanitize_key', $unknown ) ), implode( ', ', $allowed ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$planned = array();
		if ( array_key_exists( 'separator', $arguments ) ) {
			$planned[] = $this->separator_change( $arguments['separator'], $titles );
		}
		foreach ( $this->items( $arguments, 'templates', array( 'content_type', 'field', 'value' ) ) as $i => $item ) {
			$planned[] = $this->template_change( $item, $i, $defaults );
		}
		foreach ( $this->items( $arguments, 'social_images', array( 'content_type', 'image_id' ) ) as $i => $item ) {
			$planned[] = $this->social_image_change( $item, $i, $defaults );
		}
		if ( array_key_exists( 'default_social_image_id', $arguments ) ) {
			$id        = $this->image_id( $arguments['default_social_image_id'], 'default_social_image_id' );
			$planned[] = array(
				'label'  => 'default_social_image',
				'option' => Yoast_Site_Settings::SOCIAL,
				'values' => $this->image_values( $id, 'og_default_image', 'og_default_image_id', (array) $social->get_defaults() ),
				'check'  => 'og_default_image_id',
			);
		}

		if ( ! $planned ) {
			throw new \InvalidArgumentException( 'Nothing to update: pass at least one of separator, templates, social_images or default_social_image_id. wp_yoast_get_settings shows the current settings.' );
		}
		$this->refuse_duplicates( $planned );
		$this->refuse_locked( $planned, $titles, $social );
		return $planned;
	}

	




	private function separator_change( $value, object $titles ): array {
		$separators = Yoast_Site_Settings::separators( $titles );
		if ( ! is_string( $value ) || ! isset( $separators[ $value ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'separator must be one of the separators Yoast SEO offers: %s. Nothing was changed.', implode( ', ', array_keys( $separators ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return array(
			'label'  => 'separator',
			'option' => Yoast_Site_Settings::TITLES,
			'values' => array( 'separator' => $value ),
			'check'  => 'separator',
		);
	}

	





	private function template_change( array $item, int $i, array $defaults ): array {
		$type  = $this->content_type( $item['content_type'], "templates[$i]", $defaults );
		$field = $item['field'];
		if ( ! is_string( $field ) || ! isset( Yoast_Site_Settings::TEXT_PREFIXES[ $field ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'templates[%d].field must be one of: %s.', $i, implode( ', ', array_keys( Yoast_Site_Settings::TEXT_PREFIXES ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( ! is_string( $item['value'] ) ) {
			throw new \InvalidArgumentException( sprintf( 'templates[%d].value must be a string ("" clears it).', $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$key = Yoast_Site_Settings::text_key( $type, $field, $defaults );
		if ( null === $key ) {
			throw new \InvalidArgumentException( sprintf( 'templates[%d]: Yoast SEO has no %s setting for %s. Nothing was changed.', $i, $field, $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return array(
			'label'  => $type . ' ' . $field,
			'option' => Yoast_Site_Settings::TITLES,
			'values' => array( $key => $item['value'] ),
			'check'  => $key,
		);
	}

	





	private function social_image_change( array $item, int $i, array $defaults ): array {
		$type = $this->content_type( $item['content_type'], "social_images[$i]", $defaults );
		$keys = Yoast_Site_Settings::image_keys( $type, $defaults );
		if ( null === $keys ) {
			throw new \InvalidArgumentException( sprintf( 'social_images[%d]: Yoast SEO has no social image setting for %s. Nothing was changed.', $i, $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$id = $this->image_id( $item['image_id'], "social_images[$i].image_id" );
		return array(
			'label'  => $type . ' social_image',
			'option' => Yoast_Site_Settings::TITLES,
			'values' => $this->image_values( $id, $keys[0], $keys[1], $defaults ),
			'check'  => $keys[1],
		);
	}

	







	private function items( array $arguments, string $name, array $fields ): array {
		if ( ! array_key_exists( $name, $arguments ) ) {
			return array();
		}
		$list = $arguments[ $name ];
		if ( ! is_array( $list ) || array_values( $list ) !== $list ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a list.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( count( $list ) > self::MAX_ITEMS ) {
			throw new \InvalidArgumentException( sprintf( '%s takes at most %d items.', $name, self::MAX_ITEMS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		foreach ( $list as $i => $item ) {
			$keys = is_array( $item ) ? array_keys( $item ) : array();
			if ( ! is_array( $item ) || array_diff( $fields, $keys ) || array_diff( $keys, $fields ) ) {
				throw new \InvalidArgumentException( sprintf( '%s[%d] must be an object with exactly: %s.', $name, $i, implode( ', ', $fields ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
		return $list;
	}

	







	private function content_type( $value, string $where, array $defaults ): string {
		$types = Yoast_Site_Settings::content_types( $defaults );
		if ( ! is_string( $value ) || ! in_array( $value, $types, true ) ) {
			throw new \InvalidArgumentException( sprintf( '%s.content_type must be one Yoast SEO has settings for on this site: %s. Nothing was changed.', $where, implode( ', ', $types ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $value;
	}

	







	private function image_id( $value, string $where ): int {
		if ( ! is_int( $value ) || $value < 0 ) {
			throw new \InvalidArgumentException( sprintf( '%s must be the attachment ID of an image (an integer), or 0 to clear it. Nothing was changed.', $where ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 0 === $value ) {
			return 0;
		}
		$post = get_post( $value );
		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s: attachment %d is not an image in the media library. Nothing was changed.', $where, $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( ! current_user_can( 'read_post', $value ) ) {
			throw new \RuntimeException( sprintf( '%s: WordPress does not let the current user read attachment %d. Nothing was changed.', $where, $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $value;
	}

	










	private function image_values( int $id, string $url_key, string $id_key, array $defaults ): array {
		if ( 0 === $id ) {
			return array(
				$url_key => $defaults[ $url_key ] ?? '',
				$id_key  => $defaults[ $id_key ] ?? 0,
			);
		}
		$url = wp_get_attachment_url( $id );
		if ( ! is_string( $url ) || '' === $url ) {
			throw new \RuntimeException( sprintf( 'WordPress returned no URL for attachment %d. Nothing was changed.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return array(
			$url_key => $url,
			$id_key  => $id,
		);
	}

	





	private function refuse_duplicates( array $planned ): void {
		$labels = array_column( $planned, 'label' );
		$twice  = array_unique( array_diff_assoc( $labels, array_unique( $labels ) ) );
		if ( $twice ) {
			throw new \InvalidArgumentException( sprintf( 'Each setting may be sent once; sent more than once: %s. Nothing was changed.', implode( ', ', $twice ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	








	private function refuse_locked( array $planned, object $titles, object $social ): void {
		$locked = array();
		foreach ( $planned as $change ) {
			$instance = Yoast_Site_Settings::TITLES === $change['option'] ? $titles : $social;
			foreach ( array_keys( $change['values'] ) as $key ) {
				if ( $instance->is_disabled( $key ) ) {
					$locked[] = $change['label'];
					break;
				}
			}
		}
		if ( $locked ) {
			throw new \RuntimeException( sprintf( 'The network administrator locked these Yoast SEO settings for this site: %s. A site administrator cannot change them; nothing was changed.', implode( ', ', $locked ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	





	private static function lock(): string {
		global $wpdb;
		$deadline = microtime( true ) + static::LOCK_WAIT;
		do {
			$lock = Option_Lock::acquire( self::LOCK, static::LOCK_TTL );
			if ( '' !== $lock ) {
				return $lock;
			}
			if ( '' !== (string) ( $wpdb->last_error ?? '' ) ) {
				throw new \RuntimeException( sprintf( 'The database refused the lock this call takes before saving (%s), so nothing was changed.', $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			usleep( 100000 );
		} while ( microtime( true ) < $deadline );
		throw new \RuntimeException( sprintf( 'Another wp_yoast_update_settings call has been saving for more than %d seconds, so nothing was changed. Call again.', static::LOCK_WAIT ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	













	private static function renewed( string $lock, array $planned, array $written ): string {
		$saved = array();
		$lost  = array();
		foreach ( $planned as $change ) {
			if ( in_array( $change['option'], $written, true ) ) {
				$saved[] = $change['label'];
			} else {
				$lost[] = $change['label'];
			}
		}
		if ( ! $lost ) {
			return $lock;
		}
		$renewed = Option_Lock::renew( self::LOCK, $lock );
		if ( '' !== $renewed ) {
			return $renewed;
		}
		
		global $wpdb;
		$error = (string) ( $wpdb->last_error ?? '' );
		$cause = '' !== $error
			? sprintf( 'The database refused the renewal of this call\'s lock (%s)', $error )
			: sprintf( 'Another wp_yoast_update_settings call took this call\'s lock over (possible only after this call held it for %d seconds)', static::LOCK_TTL );
		throw new \RuntimeException( sprintf( '%s, so these were not saved: %s.%s Read the settings with wp_yoast_get_settings and send them again.', $cause, implode( ', ', $lost ), $saved ? ' Saved before that: ' . implode( ', ', $saved ) . '.' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	







	private static function fresh( string $name ): array {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( $name, 'options' );
		return Yoast_Site_Settings::stored( $name );
	}

	









	private function write( string $option, array $current, array $planned ): ?bool {
		$next = $current;
		foreach ( $planned as $change ) {
			if ( $option === $change['option'] ) {
				$next = array_merge( $next, $change['values'] );
			}
		}
		if ( $next === $current ) {
			return null;
		}
		global $wpdb;
		
		
		
		
		
		$wpdb->last_error = '';
		if ( update_option( $option, $next ) ) {
			return true;
		}
		if ( '' !== (string) $wpdb->last_error ) {
			return false;
		}
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- whether the row exists now, not as cached.
		return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
	}

	







	private function previews( array $planned, array $titles ): array {
		if ( ! function_exists( 'wpseo_replace_vars' ) ) {
			return array();
		}
		$out = array();
		foreach ( $planned as $change ) {
			if ( ! preg_match( '/^post_type:(\S+) (title|description)$/', $change['label'], $m ) ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'   => $m[1],
					'post_status' => 'publish',
					'numberposts' => 1,
					'orderby'     => 'date',
					'order'       => 'DESC',
				)
			);
			if ( ! $posts ) {
				continue;
			}
			$template = self::value_of( $titles, $change['check'] );
			$out[]    = array(
				'content_type' => 'post_type:' . $m[1],
				'field'        => $m[2],
				'post_id'      => (int) $posts[0]->ID,
				'text'         => html_entity_decode( (string) wpseo_replace_vars( $template, $posts[0] ), ENT_QUOTES, 'UTF-8' ),
			);
		}
		return $out;
	}

	







	private static function value_of( array $stored, string $key ): string {
		return isset( $stored[ $key ] ) && is_scalar( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
	}

	







	private static function values_of( array $stored, array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = self::value_of( $stored, $key );
		}
		return $out;
	}

	





	private function separator_keys(): array {
		try {
			list( $titles ) = Yoast_Site_Settings::instances();
		} catch ( \RuntimeException $e ) {
			return array();
		}
		return array_values( array_filter( array_map( 'strval', array_keys( Yoast_Site_Settings::separators( $titles ) ) ) ) );
	}
}
