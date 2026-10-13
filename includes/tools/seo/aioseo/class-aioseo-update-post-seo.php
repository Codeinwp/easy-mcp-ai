<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Aioseo_Update_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_aioseo_update_post_seo';
	}

	public function get_description() {
		return 'Updates AIOSEO SEO metadata on a post or page via the WP REST API aioseo_meta_data field. AIOSEO 4.9.8+ — REST API is included free in core; on AIOSEO < 4.9.8 free, writes require the legacy REST API addon. Pass fields as an object keyed by AIOSEO friendly field-map keys (NOT column names): title, description, canonical_url, og_title, og_description, og_object_type, og_image_type, og_image_custom_url, og_article_section, og_article_tags, twitter_title, twitter_description, twitter_use_og, twitter_card, twitter_image_type, twitter_image_custom_url, FLAT robots keys noindex/nofollow/noarchive/nosnippet/noimageindex/notranslate (booleans), maxSnippet/maxVideoPreview (int, -1=default), maxImagePreview (string large/standard/none), pillar_content (boolean), primary_term, keyphrases (object), schema (object). Setting any robots flag auto-sets default:false so AIOSEO honours the per-post override (it ignores per-post robots while default is on). canonical_url may not persist on the AIOSEO free plan.';
	}

	public function get_category() {
		return 'aioseo';
	}

	public function get_required_capability() {
		return 'edit_posts';
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
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'   => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'The REST API base for the post type (e.g. posts, pages).',
					'default'     => 'posts',
				),
				'fields'    => array(
					'type'                 => 'object',
					'description'          => 'AIOSEO fields to update, keyed by friendly field-map keys (NOT column names). Freeform passthrough to the aioseo_meta_data REST field; patch semantics (only supplied keys are written, others preserved). Robots flags are FLAT top-level keys (e.g. {"noindex":true}), NOT nested under a "robots" object; the key is "noindex" (no underscore). Setting any robots flag auto-injects default:false (AIOSEO ignores per-post robots while robots_default is on). canonical_url may not persist on the AIOSEO free plan. Keys not listed below are still accepted and forwarded unchanged.',
					
					
					
					
					
					
					
					'properties'           => array(
						'title'                    => array( 'type' => 'string', 'description' => 'SEO title.' ),
						'description'              => array( 'type' => 'string', 'description' => 'Meta description.' ),
						'canonical_url'            => array( 'type' => 'string', 'description' => 'Canonical URL (may not persist on AIOSEO free).' ),
						'og_title'                 => array( 'type' => 'string', 'description' => 'Open Graph title.' ),
						'og_description'           => array( 'type' => 'string', 'description' => 'Open Graph description.' ),
						'og_object_type'           => array( 'type' => 'string', 'description' => 'Open Graph object type (e.g. default, article).' ),
						'og_image_type'            => array( 'type' => 'string', 'description' => 'OG image source (e.g. default, featured, custom); pair with og_image_custom_url for a custom image.' ),
						'og_image_custom_url'      => array( 'type' => 'string', 'description' => 'Custom Open Graph image URL (used when og_image_type is the custom option).' ),
						'og_article_section'       => array( 'type' => 'string', 'description' => 'Open Graph article section.' ),
						'og_article_tags'          => array( 'type' => 'string', 'description' => 'Open Graph article tags (comma-separated).' ),
						'twitter_title'            => array( 'type' => 'string', 'description' => 'Twitter card title.' ),
						'twitter_description'      => array( 'type' => 'string', 'description' => 'Twitter card description.' ),
						'twitter_use_og'           => array( 'type' => 'boolean', 'description' => 'Reuse the Open Graph values for Twitter.' ),
						'twitter_card'             => array( 'type' => 'string', 'description' => 'Twitter card type (e.g. summary, summary_large_image).' ),
						'twitter_image_type'       => array( 'type' => 'string', 'description' => 'Twitter image source (as og_image_type).' ),
						'twitter_image_custom_url' => array( 'type' => 'string', 'description' => 'Custom Twitter image URL.' ),
						'default'                  => array( 'type' => 'boolean', 'description' => 'Robots: use AIOSEO site defaults. Auto-set to false when any robots flag below is supplied.' ),
						'noindex'                  => array( 'type' => 'boolean', 'description' => 'Robots: noindex this post.' ),
						'nofollow'                 => array( 'type' => 'boolean', 'description' => 'Robots: nofollow.' ),
						'noarchive'                => array( 'type' => 'boolean', 'description' => 'Robots: noarchive.' ),
						'nosnippet'                => array( 'type' => 'boolean', 'description' => 'Robots: nosnippet.' ),
						'noimageindex'             => array( 'type' => 'boolean', 'description' => 'Robots: noimageindex.' ),
						'notranslate'              => array( 'type' => 'boolean', 'description' => 'Robots: notranslate.' ),
						'maxSnippet'               => array( 'type' => 'integer', 'description' => 'Robots: max snippet length (-1 = default / no limit).' ),
						'maxVideoPreview'          => array( 'type' => 'integer', 'description' => 'Robots: max video preview seconds (-1 = default).' ),
						'maxImagePreview'          => array( 'type' => 'string', 'description' => 'Robots: max image preview size (large, standard, or none).' ),
						'pillar_content'           => array( 'type' => 'boolean', 'description' => 'Mark as pillar / cornerstone content.' ),
						'primary_term'             => array( 'type' => 'object', 'description' => 'Primary term mapping (AIOSEO structure, keyed by taxonomy). Not restricted to specific keys.' ),
						'keyphrases'               => array( 'type' => 'object', 'description' => 'Focus + additional keyphrases (AIOSEO structure: { focus: { keyphrase }, additional: [...] }). Not restricted to specific keys.' ),
						'schema'                   => array( 'type' => 'object', 'description' => 'Schema / structured-data options (AIOSEO structure). Not restricted to specific keys.' ),
					),
				),
			),
			'required'   => array( 'post_id', 'fields' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'aioseo' ) ) {
			throw new \RuntimeException( 'All in One SEO (AIOSEO) is not active on this site. Please install and activate AIOSEO to use this tool.' );
		}

		$aioseo_instance = aioseo();

		
		
		
		$rest_in_core = ( defined( 'AIOSEO_VERSION' ) && version_compare( AIOSEO_VERSION, '4.9.8', '>=' ) )
			|| ( $aioseo_instance && isset( $aioseo_instance->version ) && version_compare( (string) $aioseo_instance->version, '4.9.8', '>=' ) );

		$addon_active = $aioseo_instance
			&& isset( $aioseo_instance->addons )
			&& method_exists( $aioseo_instance->addons, 'isAddonActive' )
			&& $aioseo_instance->addons->isAddonActive( 'aioseo-rest-api' );

		if ( ! $rest_in_core && ! $addon_active ) {
			
			$plan = 'free';
			if ( $aioseo_instance && isset( $aioseo_instance->license ) ) {
				$license = $aioseo_instance->license;
				if ( method_exists( $license, 'getLicenseLevel' ) ) {
					$plan = $license->getLicenseLevel() ?: 'free';
				} elseif ( isset( $license->level ) ) {
					$plan = $license->level ?: 'free';
				}
			}

			return array(
				'error'          => 'AIOSEO REST API write support requires AIOSEO Plus plan or higher with the REST API addon active. Read-only access is available on the free plan via wp_aioseo_get_post_seo.',
				'current_plan'   => strtolower( (string) $plan ),
				'requires_plan'  => 'plus',
				'requires_addon' => 'aioseo-rest-api',
				'skip_reason'    => 'plan_limitation',
			);
		}

		$this->validate_required( $arguments, array( 'post_id', 'fields' ) );

		$post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
		
		
		
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( 'You do not have permission to edit this post.' );
		}
		$post_type = ! empty( $arguments['post_type'] ) ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' ) : 'posts';
		$fields    = $this->parse_json_param( $arguments['fields'], 'fields' );

		
		
		
		
		if ( is_array( $fields ) && ! array_key_exists( 'default', $fields ) ) {
			$robots_flags = array( 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex', 'notranslate', 'noodp', 'maxSnippet', 'maxVideoPreview', 'maxImagePreview' );
			foreach ( $robots_flags as $robots_flag ) {
				if ( array_key_exists( $robots_flag, $fields ) ) {
					$fields['default'] = false;
					break;
				}
			}
		}

		$data = $this->rest_request( 'POST', '/wp/v2/' . $post_type . '/' . $post_id, array( 'aioseo_meta_data' => $fields ) );

		return array(
			'post_id'          => $post_id,
			'aioseo_meta_data' => $data['aioseo_meta_data'] ?? array(),
		);
	}
}
