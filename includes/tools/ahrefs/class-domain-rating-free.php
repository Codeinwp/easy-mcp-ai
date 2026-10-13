<?php
namespace Easy_MCP_AI\Tools\Ahrefs;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
















class Domain_Rating_Free extends Base_Tool {

	
	
	
	const ENDPOINT     = \Easy_MCP_AI\Ahrefs\Ahrefs_Client::ENDPOINT;
	const ALLOWED_HOST = \Easy_MCP_AI\Ahrefs\Ahrefs_Client::ALLOWED_HOST;

	public function get_name() {
		return 'wp_ahrefs_domain_rating_free';
	}

	public function get_description() {
		return 'Ahrefs Domain Rating — returns the target\'s Domain Rating on a 100-point logarithmic scale, reflecting the strength of its backlink profile. target accepts a bare domain (example.com), a subdomain, or a full URL. Returns domain_rating plus the Ahrefs license URL; attribution "Domain Rating by Ahrefs" is required when displaying the value. Requires a free Ahrefs APIv3 key saved at Easy MCP AI → Tools → External data → Ahrefs — calls to this endpoint are free and consume NO API units, but Ahrefs stopped serving it unauthenticated. (meter: free, key required)';
	}

	public function get_category() {
		return 'ahrefs';
	}

	public function get_required_capability() {
		return 'read';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Ahrefs domain rating (free)',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'target' ),
			'properties' => array(
				'target' => array(
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 2048,
					'description' => 'Domain, subdomain, or URL to look up (e.g. "example.com" or "https://example.com/page").',
				),
			),
		);
	}

	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'target' ) );
			if ( ! is_string( $arguments['target'] ) ) {
				throw new \InvalidArgumentException( 'target must be a string.' );
			}
			$target = trim( $arguments['target'] );
			if ( '' === $target ) {
				throw new \InvalidArgumentException( 'target must not be empty.' );
			}
			if ( strlen( $target ) > 2048 ) {
				throw new \InvalidArgumentException( 'target is too long (max 2048 characters).' );
			}

			
			
			$host = \wp_parse_url( self::ENDPOINT, PHP_URL_HOST );
			if ( self::ALLOWED_HOST !== $host ) {
				throw new \RuntimeException( 'Ahrefs client: refusing to call non-Ahrefs host.' );
			}

			
			
			
			$url = \add_query_arg(
				array(
					'target' => rawurlencode( $target ),
					'output' => 'json',
				),
				self::ENDPOINT
			);

			
			
			
			
			$api_key = ( new \Easy_MCP_AI\Ahrefs\Ahrefs_Client() )->get_api_key();

			$response = \wp_remote_get( $url, array(
				
				
				'timeout'             => 10,
				
				
				
				
				'redirection'         => 0,
				'user-agent'          => 'EasyMCPAI/' . ( defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0' ),
				'headers'             => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				
				'limit_response_size' => 1048576,
			) );

			if ( \is_wp_error( $response ) ) {
				throw new \RuntimeException( 'Ahrefs request failed (transport error): ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$code = (int) \wp_remote_retrieve_response_code( $response );
			$body = (string) \wp_remote_retrieve_body( $response );

			if ( 429 === $code ) {
				throw new \RuntimeException( 'Ahrefs request limit exceeded — back off and retry.' );
			}
			if ( $code < 200 || $code >= 300 ) {
				$detail = '';
				$parsed = json_decode( $body, true );
				if ( is_array( $parsed ) && isset( $parsed['error'] ) && is_string( $parsed['error'] ) ) {
					
					
					
					$detail = ' (' . str_replace( $api_key, '[REDACTED]', $parsed['error'] ) . ')';
				}
				
				
				if ( 401 === $code || 403 === $code ) {
					throw new \RuntimeException( "Ahrefs rejected the API key (HTTP {$code}).{$detail} Check the key at Easy MCP AI → Tools → External data → Ahrefs; it must be an APIv3 key from your Ahrefs account under Account settings → API keys." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				throw new \RuntimeException( "Ahrefs HTTP error: status {$code}.{$detail}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}

			$data = json_decode( $body, true );
			if ( ! is_array( $data ) || ! isset( $data['domain_rating'] ) || ! is_array( $data['domain_rating'] ) ) {
				throw new \RuntimeException( 'Ahrefs returned an unexpected response shape.' );
			}

			$dr = $data['domain_rating'];

			return array(
				'target'        => $target,
				'domain_rating' => isset( $dr['domain_rating'] ) ? (float) $dr['domain_rating'] : null,
				'license'       => isset( $dr['license'] ) ? (string) $dr['license'] : '',
				'attribution'   => 'Domain Rating by Ahrefs',
			);
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
