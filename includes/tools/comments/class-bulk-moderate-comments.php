<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












class Bulk_Moderate_Comments extends Base_Tool {

    use Comment_Moderation;

    
    const MAX_IDS = 100;

    
    const ACTIONS = array( 'approve', 'unapprove', 'spam', 'trash' );

    public function get_name() {
        return 'wp_bulk_moderate_comments';
    }

    public function get_description() {
        return 'Moderates up to 100 WordPress comments in one call, like the Bulk actions menu on the Comments screen. Required: `comment_ids` (array of comment IDs, 1–100; duplicates are ignored), `action` — "approve", "unapprove" (back to pending), "spam" or "trash". Each comment is handled on its own: one that is missing, that the caller may not edit (WordPress\'s edit_comment check — the caller must be able to edit the comment\'s post), or whose post is in the Trash is reported with an `error` and the rest still run. A comment already in the target status is not rewritten and reports changed=false. "trash" never deletes permanently: on a site with trash disabled (EMPTY_TRASH_DAYS = 0) the whole call is refused before anything changes — use `wp_delete_comment` with force=true instead. Returns { action, requested (unique IDs), changed (how many comments actually changed), results: [{ id, status, changed } or { id, error }] }. For one comment use `wp_approve_comment`, `wp_unapprove_comment`, `wp_spam_comment` or `wp_trash_comment`; to undo spam or trash use `wp_unspam_comment` / `wp_untrash_comment`; `wp_list_comments` finds the IDs (status="hold" for the moderation queue); `wp_get_comment` reads one back.';
    }

    public function get_category() {
        return 'comments';
    }

    public function get_required_capability() {
        
        
        return 'moderate_comments';
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
                'comment_ids' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'minItems'    => 1,
                    'maxItems'    => self::MAX_IDS,
                    'description' => 'IDs of the comments to moderate (1–100). Duplicates are ignored.',
                ),
                'action'      => array(
                    'type'        => 'string',
                    'enum'        => self::ACTIONS,
                    'description' => 'What to do with every listed comment: "approve", "unapprove" (return to pending), "spam" or "trash".',
                ),
            ),
            'required'   => array( 'comment_ids', 'action' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'comment_ids', 'action' ) );

        $action = is_string( $arguments['action'] ) ? $arguments['action'] : '';
        if ( ! in_array( $action, self::ACTIONS, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid action: must be one of %s.', implode( ', ', self::ACTIONS ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $ids = $this->parse_comment_ids( $arguments['comment_ids'] );

        
        
        if ( 'trash' === $action ) {
            $this->refuse_trash_when_disabled();
        }

        $results = array();
        $changed = 0;
        foreach ( $ids as $id ) {
            try {
                $comment    = $this->load_moderatable_comment( $id );
                $did_change = $this->apply_comment_action( $comment, $action );
                
                
                $current = get_comment( $id );

                if ( $did_change ) {
                    ++$changed;
                }
                $results[] = array(
                    'id'      => $id,
                    'status'  => $this->comment_rest_status( $current ? $current : $comment ),
                    'changed' => $did_change,
                );
            } catch ( \RuntimeException $e ) {
                $results[] = array(
                    'id'    => $id,
                    'error' => $e->getMessage(),
                );
            }
        }

        return array(
            'action'    => $action,
            'requested' => count( $ids ),
            'changed'   => $changed,
            'results'   => $results,
        );
    }

    











    private function parse_comment_ids( $value ) {
        $raw = $this->parse_json_param( $value, 'comment_ids' );
        if ( empty( $raw ) ) {
            throw new \InvalidArgumentException( 'comment_ids must contain at least one comment ID.' );
        }
        if ( count( $raw ) > self::MAX_IDS ) {
            throw new \InvalidArgumentException( sprintf( 'comment_ids carries %d IDs; at most %d are allowed per call. Split the list and call again.', count( $raw ), self::MAX_IDS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $ids = array();
        foreach ( array_values( $raw ) as $i => $item ) {
            $ids[] = $this->parse_required_id( $item, 'comment_ids[' . $i . ']' );
        }
        return array_values( array_unique( $ids ) );
    }
}
