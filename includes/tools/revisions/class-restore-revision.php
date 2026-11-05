<?php
namespace Easy_MCP_AI\Tools\Revisions;

use Easy_MCP_AI\Builders\Builder_Registry;
use Easy_MCP_AI\Builders\Layout_Lock;
use Easy_MCP_AI\Cache\Cache_Purger;
use Easy_MCP_AI\Cache\Purge_Request;
use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Restore_Revision extends Base_Tool {

    private $purger = null;

    public function get_name() {
        return 'wp_restore_revision';
    }

    public function get_description() {
        return 'Restores a post to a specific revision. The current post state becomes a new revision (so the restore is itself undoable). Required: `revision_id`. Returns { restored, post_id, revision_id }. Note: WordPress core\'s `wp_restore_post_revision()` returns the parent post ID, not a revision ID. If you need the latest revision ID created as a side effect, call `wp_list_revisions` after this tool. Verifies edit_post capability on the parent post before restoring. On an Elementor page it also clears Elementor\'s CSS and element cache for that page, which would otherwise keep showing the old version for up to a day, and reports it as `elementor_cache`. On a Breakdance page it also puts back the Breakdance layout kept in the revision, through Breakdance\'s own save, and reports it as `layout`; `restored` is false when that layout could not be put back (`layout.note` says why).';
    }

    public function get_category() {
        return 'revisions';
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
                'revision_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the revision to restore.',
                ),
            ),
            'required'   => array( 'revision_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'revision_id' ) );
        $revision_id = $this->parse_required_id( $arguments['revision_id'], 'revision_id' );
        $revision    = wp_get_post_revision( $revision_id );
        if ( ! $revision ) {
            throw new \RuntimeException( 'Revision not found.' );
        }
        $parent_id = (int) $revision->post_parent;
        if ( ! $parent_id || ! current_user_can( 'edit_post', $parent_id ) ) {
            throw new \RuntimeException( 'You do not have permission to restore this revision.' );
        }
        $post    = get_post( $parent_id );
        $adapter = is_object( $post ) ? Builder_Registry::for_post( $post ) : null;
        $lock    = null === $adapter ? '' : Layout_Lock::acquire( $parent_id );
        if ( null !== $adapter && '' === $lock ) {
            throw new \RuntimeException( sprintf( 'Another edit of post %d is being saved, so revision %d was not restored and nothing was written. Try again once it is saved.', $parent_id, $revision_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $held = static function () use ( $parent_id, $lock ): bool {
            return Layout_Lock::holds( $parent_id, $lock );
        };
        try {
            if ( '' !== $lock ) {
                wp_cache_delete( $parent_id, 'post_meta' );
            }
            $refusal = null === $adapter ? null : $adapter->restore_refusal( $post, $revision_id );
            if ( null !== $refusal ) {
                throw new Detailed_Tool_Error( sprintf( 'Revision %d was not restored, and nothing was written; the reason follows.', $revision_id ), ucfirst( $refusal ) . ' A restore here changes only what wp_update_page_layout could; restore anything else in the builder.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( '' !== $lock && ! $held() ) {
                throw new \RuntimeException( sprintf( 'This restore of post %d ran longer than %d seconds and another edit took over, so revision %d was not restored and nothing was written.', $parent_id, Layout_Lock::TTL, $revision_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $result = wp_restore_post_revision( $revision_id );
            if ( false === $result ) {
                throw new \RuntimeException( 'Restore failed.' );
            }
            $reply = array( 'restored' => true, 'post_id' => $parent_id, 'revision_id' => $revision_id );
            $layer = $this->clear_elementor_caches( $parent_id );
            if ( null !== $layer ) {
                $reply['elementor_cache'] = $layer;
            }
            $layout = $this->restore_builder_layout( $parent_id, $revision_id, '' === $lock ? null : $held );
            if ( null !== $layout ) {
                $reply['restored'] = $layout['restored'];
                $reply['layout']   = $layout;
            }
            return $reply;
        } finally {
            if ( '' !== $lock ) {
                Layout_Lock::release( $parent_id, $lock );
            }
        }
    }

    private function clear_elementor_caches( int $post_id ): ?array {
        if ( 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
            return null;
        }
        $request         = new Purge_Request( Purge_Request::SCOPE_POSTS, array(), array( $post_id ) );
        $request->caches = array( 'elementor' );
        foreach ( $this->purger()->run( $request )['layers'] as $layer ) {
            if ( 'elementor' === ( $layer['id'] ?? '' ) ) {
                return $layer;
            }
        }
        return null;
    }

    private function restore_builder_layout( int $post_id, int $revision_id, ?callable $still_held ): ?array {
        $post    = get_post( $post_id );
        $adapter = is_object( $post ) ? Builder_Registry::for_post( $post ) : null;
        $layout  = null === $adapter ? null : $adapter->restore_layout( $post, $revision_id, $still_held );
        return null === $layout ? null : array( 'builder' => $adapter->id() ) + $layout;
    }

    public function with_purger( Cache_Purger $purger ): self {
        $this->purger = $purger;
        return $this;
    }

    private function purger(): Cache_Purger {
        if ( null === $this->purger ) {
            $this->purger = new Cache_Purger();
        }
        return $this->purger;
    }
}
