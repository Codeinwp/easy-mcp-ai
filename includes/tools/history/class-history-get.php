<?php
namespace Easy_MCP_AI\Tools\History;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\History\Change_Log_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class History_Get extends Base_Tool {
    public function get_name() { return 'wp_history_get'; }
    public function get_description() {
        return 'Get one change-history entry by id, including before/after payloads. For a post, page or media entry whose body changed or that was permanently deleted, `content_before` is the body BEFORE the change; when it cannot be given, `content_before_unavailable` says why. `revision_content` is the body AFTER the change (the revision WordPress saved with it), returned only when you can read the parent post. Body text is shown only when you can read the post now. `unrecoverable`, when present, maps each value this entry cannot give back to a reason: redacted, truncated, value_unavailable (path "*" means the whole previous state), not_stored, revision_gone or not_recorded. Non-admin callers (without the `easy_mcp_ai_view_all_history` capability) can only fetch entries they originated; entries belonging to another user are reported as "not found" rather than a permission error. The `ip_address` field is included only for callers with `easy_mcp_ai_view_all_history` (admins).';
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
            'properties' => array( 'id' => array( 'description' => 'The change-log row ID, as returned by wp_history_list.', 'type' => 'integer' ) ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $args ) {
        $this->validate_required( $args, array( 'id' ) );
        $row = ( new Change_Log_Repository() )->find( (int) $args['id'] );
        if ( ! $row ) {
            throw new \RuntimeException( 'History entry not found' );
        }

        $can_view_all = \current_user_can( 'easy_mcp_ai_view_all_history' );
        if ( ! $can_view_all && (int) ( $row['wp_user_id'] ?? 0 ) !== (int) \get_current_user_id() ) {
            throw new \RuntimeException( 'History entry not found' );
        }

        $out = array(
            'id'              => (int) $row['id'],
            'audit_id'        => isset( $row['audit_id'] ) ? (int) $row['audit_id'] : null,
            'tool_name'       => $row['tool_name'],
            'action'          => $row['action'],
            'object_type'     => $row['object_type'],
            'object_id'       => $row['object_id'],
            'object_subtype'  => $row['object_subtype'] ?? null,
            'before_value'    => ! empty( $row['before_value'] )   ? json_decode( $row['before_value'],   true ) : null,
            'after_value'     => ! empty( $row['after_value'] )    ? json_decode( $row['after_value'],    true ) : null,
            'changed_fields'  => ! empty( $row['changed_fields'] ) ? json_decode( $row['changed_fields'], true ) : null,
            'revision_id'     => isset( $row['revision_id'] ) ? (int) $row['revision_id'] : null,
            'wp_user_id'      => (int) ( $row['wp_user_id'] ?? 0 ),
            'oauth_client_id' => $row['oauth_client_id'] ?? null,
            'auth_source'     => $row['auth_source'] ?? null,
            'created_at'      => $row['created_at'] ?? null,
            'truncated'       => ! empty( $row['truncated'] ),
            'capture_mode'    => ( isset( $row['capture_mode'] ) && '' !== $row['capture_mode'] ) ? $row['capture_mode'] : 'hooked',
        );
        if ( $can_view_all && isset( $row['ip_address'] ) ) {
            $out['ip_address'] = $row['ip_address'];
        }

        $content_code = 'post' === $out['object_type'] ? $this->add_content_before( $out, $can_view_all ) : null;

        $after_payload = is_array( $out['after_value'] ?? null ) ? $out['after_value'] : array();
        if ( ! $out['revision_id'] && isset( $after_payload['content_capture'] ) ) {
            $out['revision_content_unavailable'] = (string) $after_payload['content_capture'];
        }

        if ( $out['revision_id'] && function_exists( '\wp_get_post_revision' ) ) {
            $rev = \wp_get_post_revision( $out['revision_id'] );
            if ( ! $rev ) {
                $out['revision_content_unavailable'] = __( 'unavailable — the linked revision no longer exists; it was pruned by the revision limit, or removed with its parent post', 'easy-mcp-ai' );
            }

            if ( $rev && isset( $rev->post_content ) ) {
                $parent_id = isset( $rev->post_parent ) ? (int) $rev->post_parent : 0;
                $allowed   = $can_view_all
                    || ( $parent_id > 0 && \current_user_can( 'read_post', $parent_id ) );
                if ( $allowed ) {
                    $out['revision_content'] = $rev->post_content;
                }
            }
        }

        $unrecoverable = $this->unrecoverable( $out, ! empty( $row['truncated'] ), $content_code );
        if ( $unrecoverable ) {
            $out['unrecoverable'] = $unrecoverable;
        }
        return $out;
    }

    private function add_content_before( array &$out, $can_view_all ) {
        $post_id  = (int) $out['object_id'];
        $can_read = $can_view_all || ( $post_id > 0 && \current_user_can( 'read_post', $post_id ) );
        $before   = is_array( $out['before_value'] ) ? $out['before_value'] : array();
        $after    = is_array( $out['after_value'] ) ? $out['after_value'] : array();

        if ( ! $can_read ) {
            foreach ( array( 'before_value', 'after_value' ) as $side ) {
                if ( is_array( $out[ $side ] ) ) {
                    unset( $out[ $side ]['post_content'] );
                }
            }
        }

        if ( 'revision' === ( $out['object_subtype'] ?? '' ) ) {
            return null;
        }
        $changed      = is_array( $out['changed_fields'] ) ? $out['changed_fields'] : array();
        $is_delete    = 'delete' === $out['action'];
        $body_changed = in_array( 'post_content', $changed, true );
        if ( ! $is_delete && ! $body_changed ) {
            return null;
        }

        $hidden = __( 'hidden — you cannot read this post now', 'easy-mcp-ai' );
        if ( array_key_exists( 'post_content', $before ) ) {
            if ( $can_read ) {
                $out['content_before'] = (string) $before['post_content'];
            } else {
                $out['content_before_unavailable'] = $hidden;
            }
            return null;
        }
        if ( isset( $before['content_revision_id'] ) ) {
            $revision_id = (int) $before['content_revision_id'];
            $revision    = \function_exists( '\wp_get_post_revision' ) ? \wp_get_post_revision( $revision_id ) : null;
            if ( ! $revision ) {
                $out['content_before_unavailable'] = __( 'unavailable — the revision that held the previous body no longer exists; it was pruned by the revision limit, or removed with its parent post', 'easy-mcp-ai' );
                return 'revision_gone';
            }
            if ( ! $can_read ) {
                $out['content_before_unavailable'] = $hidden;
                return null;
            }
            $out['content_before'] = (string) ( $revision->post_content ?? '' );
            return null;
        }
        if ( isset( $after['content_capture'] ) ) {
            $out['content_before_unavailable'] = (string) $after['content_capture'];
            return 'not_stored';
        }
        $out['content_before_unavailable'] = $is_delete
            ? __( 'not recorded — this entry was written before permanent deletes kept the body', 'easy-mcp-ai' )
            : __( 'not recorded — this entry was written before the previous body was kept; an older revision may hold it (wp_list_revisions)', 'easy-mcp-ai' );
        return 'not_recorded';
    }

    private function unrecoverable( array $out, $row_truncated, $content_code ) {
        $before = $out['before_value'];
        if ( ! is_array( $before ) ) {
            return array();
        }
        $map = array();
        if ( array_key_exists( 'value_unavailable', $before ) ) {
            $map[ array_key_exists( 'value', $before ) ? 'value' : '*' ] = 'value_unavailable';
        }
        $max = class_exists( '\\Easy_MCP_AI\\History\\Change_Redactor' ) ? \Easy_MCP_AI\History\Change_Redactor::MAX_BYTES : 262144;
        $this->collect_markers( $before, '', $row_truncated, $max, $map );
        if ( ! empty( $before['object_ids_truncated'] ) ) {
            $map['object_ids'] = 'truncated';
        }
        if ( null !== $content_code ) {
            $map['post_content'] = $content_code;
        }

        if ( 'update' === $out['action'] && is_array( $out['changed_fields'] ) ) {
            $changed = $out['changed_fields'];
            foreach ( array_keys( $map ) as $path ) {
                $top = explode( '.', (string) $path, 2 )[0];
                if ( '*' !== $path && ! in_array( $top, $changed, true ) ) {
                    unset( $map[ $path ] );
                }
            }
        }
        ksort( $map );
        return $map;
    }

    private function collect_markers( $value, $path, $row_truncated, $max, array &$map ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $inner ) {
                if ( '' === $path && 'value_unavailable' === $key ) {
                    continue;
                }
                $this->collect_markers( $inner, '' === $path ? (string) $key : $path . '.' . $key, $row_truncated, $max, $map );
            }
            return;
        }
        if ( '[REDACTED]' === $value ) {
            $map[ $path ] = 'redacted';
        } elseif ( $row_truncated && is_string( $value ) && strlen( $value ) === $max ) {
            $map[ $path ] = 'truncated';
        }
    }
}
