<?php
namespace Easy_MCP_AI\Tools\History;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\History\Change_Log_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class History_Diff extends Base_Tool {
    public function get_name() { return 'wp_history_diff'; }
    public function get_description() {
        return 'Diff two change-history entries (or one entry against current live state if "id_b" is omitted). Returns per-field before/after for keys that differ. Both entries must refer to the same object_type and object_id, or the call fails with an error. Non-admin callers (without the `easy_mcp_ai_view_all_history` capability) can only diff entries they originated. Sensitive fields (e.g. passwords, emails) are shown as the literal string "[REDACTED]" on both sides rather than their real values. When comparing against live state, the current value cannot always be read — the object type may have no live reader, the plugin that owns it may be inactive, or reading it may need a capability the caller lacks. In that case the response carries "live_state": "unreadable" with an empty "diff" and a "note" explaining why: an empty diff there means no comparison was made, NOT that nothing changed. An "explanations" object may also be present: these are notes the log recorded about the entry itself (e.g. why a post\'s previous body text is not recoverable), not fields of the object, so they are reported separately rather than compared. A "not_compared" object may also be present, on either kind of comparison: it maps each field that appears on only ONE side to the reason why — recorded but not returned by the live reader, present now but never captured, or recorded in only one of the two entries. Those fields were not compared at all, so their absence from "diff" is a limit of the comparison, not evidence that the object did or did not change.';
    }
    public function get_category() { return 'history'; }
    public function get_required_capability() { return 'read'; }

    public function get_annotations() {
        
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id_a' => array( 'type' => 'integer', 'description' => 'Older history entry id' ),
                'id_b' => array( 'type' => 'integer', 'description' => 'Newer history entry id (optional; defaults to live state)' ),
            ),
            'required' => array( 'id_a' ),
        );
    }

    public function execute( array $args ) {
        $this->validate_required( $args, array( 'id_a' ) );
        $repo         = new Change_Log_Repository();
        $can_view_all = \current_user_can( 'easy_mcp_ai_view_all_history' );
        $current_uid  = (int) \get_current_user_id();

        $row_a = $repo->find( (int) $args['id_a'] );
        if ( ! $row_a ) {
            throw new \RuntimeException( 'id_a not found' );
        }
        if ( ! $can_view_all && (int) ( $row_a['wp_user_id'] ?? 0 ) !== $current_uid ) {
            throw new \RuntimeException( 'id_a not found' );
        }
        $a_after = ! empty( $row_a['after_value'] ) ? json_decode( $row_a['after_value'], true ) : array();

        
        
        
        
        
        
        
        
        $explanations = $this->take_explanations( $a_after );

        if ( isset( $args['id_b'] ) ) {
            $row_b = $repo->find( (int) $args['id_b'] );
            if ( ! $row_b ) {
                throw new \RuntimeException( 'id_b not found' );
            }
            if ( ! $can_view_all && (int) ( $row_b['wp_user_id'] ?? 0 ) !== $current_uid ) {
                throw new \RuntimeException( 'id_b not found' );
            }
            
            
            
            
            if ( $row_a['object_type'] !== $row_b['object_type']
                || (string) $row_a['object_id'] !== (string) $row_b['object_id']
            ) {
                throw new \RuntimeException( 'id_a and id_b refer to different objects; diff is only meaningful between history entries of the same object_type and object_id' );
            }
            $b_after = ! empty( $row_b['after_value'] ) ? json_decode( $row_b['after_value'], true ) : array();
            $b_label = (int) $row_b['id'];
        } else {
            $b_after = $this->live_state( $row_a['object_type'], $row_a['object_id'], $can_view_all, $row_a['object_subtype'] ?? null );
            
            
            
            
            
            
            
            
            if ( null === $b_after ) {
                $out = array(
                    'a'           => (int) $row_a['id'],
                    'b'           => 'live',
                    'object_type' => $row_a['object_type'],
                    'object_id'   => $row_a['object_id'],
                    'diff'        => array(),
                    'live_state'  => 'unreadable',
                    'note'        => __( 'The current value could not be read — either this object type has no live reader, the plugin that owns it is not active, or reading it needs a capability you do not hold. No comparison was made; the absence of differences below does not mean nothing changed.', 'easy-mcp-ai' ),
                );
                if ( $explanations ) {
                    $out['explanations'] = $explanations;
                }
                return $out;
            }
            
            
            
            
            
            
            
            
            
            if ( is_array( $b_after ) ) {
                $redacted = \Easy_MCP_AI\History\Change_Redactor::redact( $b_after );
                $b_after  = $redacted['value'];
            }
            $b_label = 'live';
        }

        
        
        $explanations = array_merge( $explanations, $this->take_explanations( $b_after ) );

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $uncomparable = array();
        $a_keys       = array_keys( (array) $a_after );
        $b_keys       = array_keys( (array) $b_after );

        if ( empty( $a_after ) || empty( $b_after ) ) {
            $keys = array_unique( array_merge( $a_keys, $b_keys ) );
        } else {
            $keys = array_values( array_intersect( $a_keys, $b_keys ) );
            foreach ( array_diff( $a_keys, $b_keys ) as $only_a ) {
                $uncomparable[ $only_a ] = ( 'live' === $b_label )
                    ? __( 'recorded, but this object type\'s live reader does not return it', 'easy-mcp-ai' )
                    : __( 'recorded in the older entry only', 'easy-mcp-ai' );
            }
            foreach ( array_diff( $b_keys, $a_keys ) as $only_b ) {
                $uncomparable[ $only_b ] = ( 'live' === $b_label )
                    ? __( 'present now, but the recorded entry did not capture it', 'easy-mcp-ai' )
                    : __( 'recorded in the newer entry only', 'easy-mcp-ai' );
            }
        }

        $diff = array();
        foreach ( $keys as $k ) {
            $av = $a_after[ $k ] ?? null;
            $bv = $b_after[ $k ] ?? null;
            if ( $av !== $bv ) {
                $diff[ $k ] = array( 'a' => $av, 'b' => $bv );
            }
        }
        $out = array(
            'a'           => (int) $row_a['id'],
            'b'           => $b_label,
            'object_type' => $row_a['object_type'],
            'object_id'   => $row_a['object_id'],
            'diff'        => $diff,
        );
        
        
        
        if ( $uncomparable ) {
            $out['not_compared'] = $uncomparable;
            $out['not_compared_note'] = __( 'These fields appear on only one side of the comparison, so they were not compared. Each entry says why. Their absence from "diff" is a limit of the comparison, not evidence that the object changed or did not change.', 'easy-mcp-ai' );
        }
        if ( $explanations ) {
            $out['explanations'] = $explanations;
        }
        return $out;
    }

    








    private function take_explanations( &$payload ) {
        $found = array();
        if ( ! is_array( $payload ) ) {
            return $found;
        }
        foreach ( \Easy_MCP_AI\History\Change_Recorder::EXPLANATION_FIELDS as $field ) {
            if ( array_key_exists( $field, $payload ) ) {
                $found[ $field ] = $payload[ $field ];
                unset( $payload[ $field ] );
            }
        }
        return $found;
    }

    







    private function live_state( $type, $id, $can_view_all = false, $object_subtype = null ) {
        return \Easy_MCP_AI\History\Live_State::read( $type, $id, $can_view_all, $object_subtype );
    }
}
