<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;














class Rankmath_Update_Redirect extends Base_Tool {

	use Rank_Math_Redirects;

	




	const FIELDS = array( 'sources', 'target', 'code', 'status' );

	public function get_name() {
		return 'wp_rm_update_redirect';
	}

	public function get_description() {
		return 'Changes a redirect of Rank Math SEO\'s Redirections module and keeps every field not sent. Required: `id` (from wp_rm_list_redirects). Send at least one of: `sources` (replaces the whole list; each { pattern, comparison, ignore_case }), `target`, `code` (301, 302, 307, 410, 451; changing to 410 or 451 clears the target), `status` ("active" or "inactive"; "active" also restores a redirect from the trash). As on create, a source Rank Math would drop, a target equal to a source and a missing target are refused. Returns { updated, changed_fields, redirect }; `updated` is false when the redirect already had these values.';
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
		$create = ( new Rankmath_Create_Redirect() )->get_input_schema()['properties'];
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'      => array(
					'type'        => 'integer',
					'description' => 'Redirect id.',
				),
				'sources' => array_merge( $create['sources'], array( 'description' => 'Replaces every source of the redirect.' ) ),
				'target'  => array(
					'type'        => 'string',
					'description' => 'Where to send visitors: a URL, or a path on this site. Refused for 410 and 451.',
				),
				'code'    => array(
					'type'        => 'integer',
					'description' => '301, 302, 307, 410 or 451. Changing to 410 or 451 clears the target; changing from them needs a target.',
				),
				'status'  => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'description' => '"active" also restores a redirect from the trash.',
				),
			),
			'required'   => array( 'id' ),
		);
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_redirects();

		$id      = $this->parse_required_id( $arguments['id'] ?? null, 'id' );
		$current = $this->require_redirect( $id );
		$sent    = array_values(
			array_filter(
				self::FIELDS,
				static function ( $field ) use ( $arguments ) {
					return isset( $arguments[ $field ] );
				}
			)
		);
		if ( array() === $sent ) {
			throw new \InvalidArgumentException( 'Nothing to update: send at least one of ' . implode( ', ', self::FIELDS ) . '.' );
		}

		$data  = $this->merged( $current, $arguments );
		$model = isset( $data['sources'] ) ? $this->build_model( $data ) : $this->model_with_stored_sources( $data );
		$this->refuse_loop( $model );

		$before   = self::normalise( $current );
		$intended = self::intended( $model );
		$changed  = self::differing_fields( $before, $intended );
		if ( array() === $changed ) {
			return array(
				'updated'        => false,
				'changed_fields' => array(),
				'redirect'       => $before,
			);
		}

		$links = $this->object_links( $id );
		$model->save();
		
		
		$this->restore_object_links( $id, $links );

		
		
		$stored = self::normalise( $this->require_redirect( $id ) );
		$missed = self::differing_fields( $stored, $intended );
		if ( array() !== $missed ) {
			throw new \RuntimeException( 'Rank Math did not store the change to ' . implode( ', ', $missed ) . '. Read the redirect with wp_rm_get_redirect to see what it holds.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return array(
			'updated'        => true,
			'changed_fields' => $changed,
			'redirect'       => $stored,
		);
	}

	






	private function merged( array $current, array $arguments ): array {
		$code    = isset( $arguments['code'] ) ? $this->parse_code( $arguments['code'] ) : (int) $current['header_code'];
		$target  = isset( $arguments['target'] ) ? (string) $arguments['target'] : (string) $current['url_to'];
		
		
		if ( in_array( $code, self::$rm_error_codes, true ) && ! isset( $arguments['target'] ) ) {
			$target = '';
		}
		$this->check_target( $code, $target );
		$status = (string) $current['status'];
		if ( isset( $arguments['status'] ) ) {
			$this->refuse_unless_one_of( 'status', $arguments['status'], array( 'active', 'inactive' ), $arguments['status'], 'active or inactive' );
			$status = $arguments['status'];
		}
		$data = array(
			'id'          => (int) $current['id'],
			'url_to'      => $target,
			'header_code' => $code,
			'status'      => $status,
		);
		if ( isset( $arguments['sources'] ) ) {
			$data['sources'] = $this->parse_sources( $arguments['sources'] );
		}
		return $data;
	}

	










	private function model_with_stored_sources( array $data ): \RankMath\Redirections\Redirection {
		$sources = $this->raw_sources( $data['id'] );
		if ( null === $sources || array() === $sources ) {
			throw new \RuntimeException( sprintf( 'Redirect %d holds no sources Rank Math can read. Send sources to replace them.', $data['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$model = new \RankMath\Redirections\Redirection( array_merge( $data, array( 'sources' => $sources, 'url_to' => '' ) ) );
		$model->add_destination( $data['url_to'] );
		return $model;
	}

	







	private static function intended( \RankMath\Redirections\Redirection $model ): array {
		return self::normalise(
			array(
				'sources'     => self::decoded_sources( $model->sources ),
				'url_to'      => in_array( (int) $model->header_code, self::$rm_error_codes, true ) ? '' : $model->url_to,
				'header_code' => $model->header_code,
				'status'      => $model->status,
			)
		);
	}

	






	private static function differing_fields( array $a, array $b ): array {
		$fields = array();
		foreach ( self::FIELDS as $field ) {
			if ( $a[ $field ] !== $b[ $field ] ) {
				$fields[] = $field;
			}
		}
		return $fields;
	}
}
