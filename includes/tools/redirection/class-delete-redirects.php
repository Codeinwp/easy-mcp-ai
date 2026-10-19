<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Redirects extends Base_Tool {

    use Redirection_Rest;

    




    const MAX_IDS = 100;

    public function get_name() {
        return 'wp_redirection_delete_redirects';
    }

    public function get_description() {
        return 'Permanently deletes redirects from the Redirection plugin (there is no trash). Required: `redirect_ids` (redirect ids from wp_redirection_list_redirects, at most 100). Every id is checked first: if any names no redirect, the call is refused and nothing is deleted. To stop a redirect without losing it, use wp_redirection_update_redirect with enabled: false. Returns { deleted: true, count, redirects: [{ id, source, target }] }. Requires the Redirection plugin active.';
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
                    'description' => 'Ids of the redirects to delete (1-100). Duplicates are ignored.',
                    'items'       => array( 'type' => 'integer' ),
                    'minItems'    => 1,
                    'maxItems'    => self::MAX_IDS,
                ),
            ),
            'required'   => array( 'redirect_ids' ),
        );
    }

    
    public function describe( array $arguments ) {
        try {
            $items = $this->existing( $this->parse_ids( $arguments ) );
        } catch ( \Throwable $e ) {
            return '';
        }
        if ( ! empty( $items['missing'] ) ) {
            return '';
        }
        $lines = array();
        foreach ( $items['found'] as $item ) {
            $lines[] = self::summary_line( self::normalize_redirect( $item ) );
        }
        return sprintf(
            /* translators: 1: number of redirects, 2: list of redirects */
            _n( 'Permanently delete %1$d redirect: %2$s', 'Permanently delete %1$d redirects: %2$s', count( $lines ), 'easy-mcp-ai' ),
            count( $lines ),
            implode( '; ', $lines )
        );
    }

    public function execute( array $arguments ) {
        $this->require_redirection();
        $ids   = $this->parse_ids( $arguments );
        $items = $this->existing( $ids );
        if ( ! empty( $items['missing'] ) ) {
            
            
            
            throw new \InvalidArgumentException( sprintf( 'No redirect with id %s. Nothing was deleted. List redirects with wp_redirection_list_redirects.', implode( ', ', $items['missing'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->rest_request( 'POST', self::$redirection_ns . '/bulk/redirect/delete', array( 'items' => $ids ) );

        $left = $this->existing( $ids );
        if ( ! empty( $left['found'] ) ) {
            $still = array_map(
                function ( array $item ): int {
                    return self::int_of( $item, 'id' );
                },
                $left['found']
            );
            throw new \RuntimeException( sprintf( 'Redirection answered success, but redirect %s still exists.', implode( ', ', $still ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $deleted = array();
        foreach ( $items['found'] as $item ) {
            $n         = self::normalize_redirect( $item );
            $deleted[] = array(
                'id'     => $n['id'],
                'source' => $n['source'],
                'target' => $n['target'],
            );
        }
        return array(
            'deleted'   => true,
            'count'     => count( $deleted ),
            'redirects' => $deleted,
        );
    }

    




    private function parse_ids( array $arguments ): array {
        $this->validate_required( $arguments, array( 'redirect_ids' ) );
        if ( ! is_array( $arguments['redirect_ids'] ) || empty( $arguments['redirect_ids'] ) ) {
            throw new \InvalidArgumentException( 'redirect_ids must be a non-empty array of redirect ids.' );
        }
        $ids = array();
        foreach ( $arguments['redirect_ids'] as $value ) {
            $ids[] = $this->parse_required_id( $value, 'redirect_ids item' );
        }
        $ids = array_values( array_unique( $ids ) );
        if ( count( $ids ) > self::MAX_IDS ) {
            throw new \InvalidArgumentException( sprintf( 'At most %d ids per call.', self::MAX_IDS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $ids;
    }

    





    private function existing( array $ids ): array {
        $found   = array();
        $missing = array();
        foreach ( $ids as $id ) {
            $item = $this->find_redirect( $id );
            if ( null === $item ) {
                $missing[] = $id;
            } else {
                $found[] = $item;
            }
        }
        return array(
            'found'   => $found,
            'missing' => $missing,
        );
    }

    





    private static function summary_line( array $redirect ): string {
        return sprintf( '#%d %s → %s', $redirect['id'], $redirect['source'], null !== $redirect['target'] ? $redirect['target'] : $redirect['action_type'] );
    }
}
