<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;










class Rankmath_Delete_Redirects extends Base_Tool {

	use Rank_Math_Redirects;

	const MAX_IDS = 100;

	public function get_name() {
		return 'wp_rm_delete_redirects';
	}

	public function get_description() {
		return 'Moves redirects of Rank Math SEO\'s Redirections module to its trash (Rank Math empties the trash after 30 days; wp_rm_update_redirect with status "active" restores one), or with `force: true` deletes them permanently. Required: `redirect_ids` (1-100 ids from wp_rm_list_redirects). If any id names no redirect, nothing is changed and the call is refused. Returns { deleted, permanent, redirect_ids, already_in_trash }: `deleted` is false when nothing changed (every redirect was already in the trash).';
	}

	public function get_annotations() {
		return array(
			'title'           => $this->get_title(),
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'redirect_ids' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Redirect ids, 1-100.',
				),
				'force'        => array(
					'type'        => 'boolean',
					'description' => 'Delete permanently instead of moving to the trash. Default: false.',
					'default'     => false,
				),
			),
			'required'   => array( 'redirect_ids' ),
		);
	}

	







	public function describe( array $arguments ) {
		if ( ! class_exists( '\\RankMath\\Redirections\\DB' ) ) {
			return '';
		}
		try {
			$ids = $this->parse_ids( $arguments['redirect_ids'] ?? null );
		} catch ( \Throwable $e ) {
			return '';
		}
		$lines = array();
		foreach ( $ids as $id ) {
			$row = $this->find_redirect( $id );
			if ( null === $row ) {
				return '';
			}
			$lines[] = self::summary_line( self::normalise( $row ) );
		}
		$count = count( $lines );
		$list  = implode( '; ', $lines );
		if ( isset( $arguments['force'] ) && rest_sanitize_boolean( $arguments['force'] ) ) {
			/* translators: 1: number of redirects, 2: list of redirects */
			return sprintf( _n( 'Permanently delete %1$d Rank Math redirect: %2$s', 'Permanently delete %1$d Rank Math redirects: %2$s', $count, 'easy-mcp-ai' ), $count, $list );
		}
		/* translators: 1: number of redirects, 2: list of redirects */
		return sprintf( _n( 'Move %1$d Rank Math redirect to the trash: %2$s', 'Move %1$d Rank Math redirects to the trash: %2$s', $count, 'easy-mcp-ai' ), $count, $list );
	}

	







	public function approval_refusal( array $arguments ): ?string {
		$length = \Easy_MCP_AI\Approvals\Approval_Gate::preview_overflow( (string) $this->describe( $arguments ) );
		if ( null === $length ) {
			return null;
		}
		return sprintf( 'These redirects take %1$d characters to list and the approval page shows %2$d, so the call was not sent for approval: a person must see every redirect they approve deleting. Send fewer redirect_ids per call.', $length, \Easy_MCP_AI\Approvals\Approval_Gate::PREVIEW_MAX );
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_redirects();

		$ids   = $this->parse_ids( $arguments['redirect_ids'] ?? null );
		$force = isset( $arguments['force'] ) && rest_sanitize_boolean( $arguments['force'] );

		$missing = array();
		$trashed = array();
		foreach ( $ids as $id ) {
			$row = $this->find_redirect( $id );
			if ( null === $row ) {
				$missing[] = $id;
			} elseif ( 'trashed' === $row['status'] ) {
				$trashed[] = $id;
			}
		}
		if ( array() !== $missing ) {
			throw new \InvalidArgumentException( 'No Rank Math redirect with id ' . implode( ', ', $missing ) . '. Nothing was deleted. List redirects with wp_rm_list_redirects.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( $force ) {
			$links = array();
			foreach ( $ids as $id ) {
				$links[ $id ] = $this->object_links( $id );
			}
			\RankMath\Redirections\DB::delete( $ids );
			$left = array_values(
				array_filter(
					$ids,
					function ( $id ) {
						return null !== $this->find_redirect( $id );
					}
				)
			);
			if ( array() !== $left ) {
				
				
				foreach ( $left as $id ) {
					$this->restore_object_links( $id, $links[ $id ] );
				}
				throw new \RuntimeException( 'Rank Math did not delete redirect ' . implode( ', ', $left ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			return array(
				'deleted'          => true,
				'permanent'        => true,
				'redirect_ids'     => $ids,
				'already_in_trash' => array(),
			);
		}

		$to_trash = array_values( array_diff( $ids, $trashed ) );
		if ( array() !== $to_trash ) {
			\RankMath\Redirections\DB::change_status( $to_trash, 'trashed' );
			$left = array_values(
				array_filter(
					$to_trash,
					function ( $id ) {
						$row = $this->find_redirect( $id );
						return null === $row || 'trashed' !== $row['status'];
					}
				)
			);
			if ( array() !== $left ) {
				throw new \RuntimeException( 'Rank Math did not move redirect ' . implode( ', ', $left ) . ' to the trash.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}
		return array(
			'deleted'          => array() !== $to_trash,
			'permanent'        => false,
			'redirect_ids'     => $to_trash,
			'already_in_trash' => $trashed,
		);
	}

	




	private function parse_ids( $value ): array {
		if ( ! is_array( $value ) || array() === $value ) {
			throw new \InvalidArgumentException( 'redirect_ids must be a non-empty array of redirect ids.' );
		}
		$ids = array();
		foreach ( $value as $raw ) {
			$ids[] = $this->parse_required_id( $raw, 'redirect_ids entry' );
		}
		$ids = array_values( array_unique( $ids ) );
		if ( count( $ids ) > self::MAX_IDS ) {
			throw new \InvalidArgumentException( sprintf( 'At most %d redirect ids per call.', self::MAX_IDS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $ids;
	}

	








	private static function summary_line( array $redirect ): string {
		$sources = array();
		foreach ( $redirect['sources'] as $source ) {
			$flags     = array_filter( array( 'exact' !== $source['comparison'] ? $source['comparison'] : '', $source['ignore_case'] ? 'ignore case' : '' ) );
			$sources[] = self::quoted( $source['pattern'] ) . ( array() === $flags ? '' : ' (' . implode( ', ', $flags ) . ')' );
		}
		$target = null !== $redirect['target'] ? sprintf( '%1$s (%2$d)', self::quoted( $redirect['target'] ), $redirect['code'] ) : (string) $redirect['code'];
		return sprintf( '#%1$d %2$s → %3$s [%4$s]', $redirect['id'], implode( ', ', $sources ), $target, $redirect['status'] );
	}

	
	private static function quoted( string $text ): string {
		return (string) wp_json_encode( $text, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
