<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class On_Page_Instant_Pages extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_on_page_instant_pages';
	}

	public function get_description() {
		return 'Synchronous on-page SEO audit for a single URL. Fetches the page, runs SEO checks, and returns score plus findings. Extra cost multipliers apply for enable_javascript, enable_browser_rendering, and load_resources. browser_preset requires enable_javascript or enable_browser_rendering. (meter: varies; JS/browser rendering add multipliers)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'On-page instant audit',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'url'                         => array(
					'type'        => 'string',
					'description' => 'The URL to audit (required).',
				),
				'custom_user_agent'           => array(
					'type'        => 'string',
					'description' => 'Custom user agent string.',
				),
				'browser_preset'              => array(
					'type'        => 'string',
					'enum'        => array( 'desktop', 'mobile', 'tablet' ),
					'description' => 'Browser preset (desktop, mobile, or tablet). Requires enable_javascript or enable_browser_rendering.',
				),
				'browser_screen_width'        => array(
					'type'        => 'integer',
					'minimum'     => 240,
					'maximum'     => 9999,
					'description' => 'Browser screen width in pixels (240–9999). Mutually exclusive with browser_preset.',
				),
				'browser_screen_height'       => array(
					'type'        => 'integer',
					'minimum'     => 240,
					'maximum'     => 9999,
					'description' => 'Browser screen height in pixels (240–9999). Mutually exclusive with browser_preset.',
				),
				'browser_screen_scale_factor' => array(
					'type'        => 'number',
					'minimum'     => 0.5,
					'maximum'     => 3,
					'description' => 'Browser screen scale factor (0.5–3.0). Mutually exclusive with browser_preset.',
				),
				'store_raw_html'              => array(
					'type'        => 'boolean',
					'description' => 'Whether to store and return raw HTML.',
				),
				'accept_language'             => array(
					'type'        => 'string',
					'description' => 'Accept-Language header value.',
				),
				'load_resources'              => array(
					'type'        => 'boolean',
					'description' => 'Whether to load external resources (applies cost multiplier).',
				),
				'enable_javascript'           => array(
					'type'        => 'boolean',
					'description' => 'Whether to enable JavaScript execution (applies cost multiplier).',
				),
				'enable_browser_rendering'    => array(
					'type'        => 'boolean',
					'description' => 'Whether to enable browser rendering (applies cost multiplier).',
				),
				'enable_xhr'                  => array(
					'type'        => 'boolean',
					'description' => 'Whether to enable XHR. Requires enable_javascript=true.',
				),
				'custom_js'                   => array(
					'type'        => 'string',
					'description' => 'Custom JavaScript to execute. Requires enable_javascript=true.',
				),
				'validate_micromarkup'        => array(
					'type'        => 'boolean',
					'description' => 'Whether to validate micromarkup (structured data).',
				),
				'check_spell'                 => array(
					'type'        => 'boolean',
					'description' => 'Whether to perform spell check.',
				),
				'checks_threshold'            => array(
					'type'        => 'object',
					'description' => 'Threshold object for check scoring.',
				),
			),
			'required'   => array( 'url' ),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['url'] ) || empty( $arguments['url'] ) ) {
			throw new \RuntimeException( 'url is required.' );
		}
		$url = (string) $arguments['url'];

		
		if ( ! \wp_http_validate_url( $url ) ) {
			throw new \RuntimeException( 'url must be a valid absolute URL.' );
		}

		
		if ( isset( $arguments['browser_preset'] ) ) {
			$preset = (string) $arguments['browser_preset'];
			if ( ! in_array( $preset, array( 'desktop', 'mobile', 'tablet' ), true ) ) {
				throw new \RuntimeException(
					'browser_preset must be one of: desktop, mobile, tablet.'
				);
			}
		}

		
		if ( isset( $arguments['browser_screen_width'] ) ) {
			$width = (int) $arguments['browser_screen_width'];
			if ( $width < 240 || $width > 9999 ) {
				throw new \RuntimeException(
					'browser_screen_width must be between 240 and 9999.'
				);
			}
		}

		
		if ( isset( $arguments['browser_screen_height'] ) ) {
			$height = (int) $arguments['browser_screen_height'];
			if ( $height < 240 || $height > 9999 ) {
				throw new \RuntimeException(
					'browser_screen_height must be between 240 and 9999.'
				);
			}
		}

		
		if ( isset( $arguments['browser_screen_scale_factor'] ) ) {
			$scale = (float) $arguments['browser_screen_scale_factor'];
			if ( $scale < 0.5 || $scale > 3 ) {
				throw new \RuntimeException(
					'browser_screen_scale_factor must be between 0.5 and 3.0.'
				);
			}
		}

		
		$has_preset = isset( $arguments['browser_preset'] );
		$has_js     = isset( $arguments['enable_javascript'] ) && $arguments['enable_javascript'];
		$has_render = isset( $arguments['enable_browser_rendering'] ) && $arguments['enable_browser_rendering'];
		if ( $has_preset && ! $has_js && ! $has_render ) {
			throw new \RuntimeException(
				'browser_preset requires enable_javascript=true or enable_browser_rendering=true.'
			);
		}

		
		$has_width  = isset( $arguments['browser_screen_width'] );
		$has_height = isset( $arguments['browser_screen_height'] );
		$has_scale  = isset( $arguments['browser_screen_scale_factor'] );
		if ( $has_preset && ( $has_width || $has_height || $has_scale ) ) {
			throw new \RuntimeException(
				'browser_preset is mutually exclusive with browser_screen_width/height/scale_factor — pick one.'
			);
		}

		
		if ( isset( $arguments['enable_xhr'] ) && $arguments['enable_xhr'] && ! $has_js ) {
			throw new \RuntimeException(
				'enable_xhr requires enable_javascript=true.'
			);
		}

		
		if ( isset( $arguments['custom_js'] ) && ! empty( $arguments['custom_js'] ) && ! $has_js ) {
			throw new \RuntimeException(
				'custom_js requires enable_javascript=true.'
			);
		}

		
		$task = array( 'url' => $url );

		
		if ( isset( $arguments['custom_user_agent'] ) ) {
			$task['custom_user_agent'] = (string) $arguments['custom_user_agent'];
		}
		if ( isset( $arguments['browser_preset'] ) ) {
			$task['browser_preset'] = (string) $arguments['browser_preset'];
		}
		if ( isset( $arguments['browser_screen_width'] ) ) {
			$task['browser_screen_width'] = (int) $arguments['browser_screen_width'];
		}
		if ( isset( $arguments['browser_screen_height'] ) ) {
			$task['browser_screen_height'] = (int) $arguments['browser_screen_height'];
		}
		if ( isset( $arguments['browser_screen_scale_factor'] ) ) {
			$task['browser_screen_scale_factor'] = (float) $arguments['browser_screen_scale_factor'];
		}
		if ( isset( $arguments['store_raw_html'] ) ) {
			$task['store_raw_html'] = (bool) $arguments['store_raw_html'];
		}
		if ( isset( $arguments['accept_language'] ) ) {
			$task['accept_language'] = (string) $arguments['accept_language'];
		}
		if ( isset( $arguments['load_resources'] ) ) {
			$task['load_resources'] = (bool) $arguments['load_resources'];
		}
		if ( isset( $arguments['enable_javascript'] ) ) {
			$task['enable_javascript'] = (bool) $arguments['enable_javascript'];
		}
		if ( isset( $arguments['enable_browser_rendering'] ) ) {
			$task['enable_browser_rendering'] = (bool) $arguments['enable_browser_rendering'];
		}
		if ( isset( $arguments['enable_xhr'] ) ) {
			$task['enable_xhr'] = (bool) $arguments['enable_xhr'];
		}
		if ( isset( $arguments['custom_js'] ) ) {
			$task['custom_js'] = (string) $arguments['custom_js'];
		}
		if ( isset( $arguments['validate_micromarkup'] ) ) {
			$task['validate_micromarkup'] = (bool) $arguments['validate_micromarkup'];
		}
		if ( isset( $arguments['check_spell'] ) ) {
			$task['check_spell'] = (bool) $arguments['check_spell'];
		}
		if ( isset( $arguments['checks_threshold'] ) && is_array( $arguments['checks_threshold'] ) ) {
			$task['checks_threshold'] = $arguments['checks_threshold'];
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/on_page/instant_pages',
			$task
		);

		
		return array_merge(
			$result['tasks'][0]['result'][0]['items'][0] ?? array(),
			array( '_cost_usd' => $result['cost'] ?? 0 )
		);

	}
}
