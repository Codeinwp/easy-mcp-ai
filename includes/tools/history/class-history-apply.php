<?php
namespace Easy_MCP_AI\Tools\History;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\History\Change_Log_Repository;
use Easy_MCP_AI\History\Rebuild_Planner;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class History_Apply extends Base_Tool {

    private $dispatcher = null;

    public function get_name() {
        return 'wp_history_apply';
    }

    public function get_description() {
        return 'Apply change history to put back what an AI client updated or deleted: give `entry_id` for one entry, or `audit_id` for every entry one MCP call wrote (newest first, at most 50). Each value is written back by calling the ordinary tools (wp_update_post, wp_add_post_terms, wp_create_term, wp_update_term, wp_update_post_meta, wp_update_comment, wp_untrash_comment, wp_update_site_settings and others) under their normal permission checks — nothing here can do what those tools would refuse. Never deletes: an entry that records a creation is skipped and names the delete tool. Refuses an entry when the object has changed since it was recorded, or cannot be read, unless `force` is true. A recreated post, term or comment gets a new ID, which later entries in the same call use. Run with `dry_run: true` first to see the planned tool calls, what cannot be restored, and any drift. Returns `entries` (per entry: status restored / partial / refused / skipped / incidental / failed / needs_approval / not_run — `incidental` is WordPress\'s own bookkeeping such as the trash record or a category-hierarchy cache, nothing lost —, the tools called, `new_id`, `not_restored` with reasons, `drift`) and a `summary`. Read an entry first with wp_history_get.';
    }

    public function get_category() {
        return 'history';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'idempotentHint'  => false,
            'openWorldHint'   => false,
        );
    }

    public function is_composite( array $arguments = array() ) {
        return true;
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'entry_id' => array(
                    'type'        => 'integer',
                    'description' => 'One change-history entry id, as wp_history_list returns it. Give this or audit_id, not both.',
                ),
                'audit_id' => array(
                    'type'        => 'integer',
                    'description' => 'The audit id of one MCP call: every entry that call wrote is applied, newest first. Give this or entry_id, not both.',
                ),
                'dry_run'  => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Plan and check only: report the tool calls that would run, what cannot be restored, and any drift. Writes nothing.',
                ),
                'force'    => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Write even when an object has changed since the entry was recorded, or its current state cannot be read. Overwrites those later changes.',
                ),
            ),
            'required'   => array(),
        );
    }

    public function set_dispatcher( callable $dispatcher ) {
        $this->dispatcher = $dispatcher;
    }

    public function describe( array $arguments ) {
        try {
            $out = $this->run( $arguments, true, true );
        } catch ( \Throwable $e ) {
            return '';
        }
        $lines  = array();
        $others = array();
        foreach ( $out['entries'] as $entry ) {
            if ( 'would_restore' !== $entry['status'] ) {
                $others[ $entry['status'] ] = ( $others[ $entry['status'] ] ?? 0 ) + 1;
                continue;
            }
            $line = $entry['object'] . ' (entry ' . $entry['entry_id'] . '): ' . implode( ' then ', self::call_texts( (array) ( $entry['planned'] ?? array() ) ) );
            if ( isset( $entry['drift'] ) ) {
                $line .= is_array( $entry['drift'] )
                    /* translators: %s: field names */
                    ? sprintf( __( ' — overwrites a later change to %s', 'easy-mcp-ai' ), implode( ', ', $entry['drift'] ) )
                    : ' — ' . $entry['drift'];
            }
            if ( ! empty( $entry['not_restored'] ) ) {
                /* translators: %s: field names */
                $line .= sprintf( __( ' (not put back: %s)', 'easy-mcp-ai' ), implode( ', ', array_keys( $entry['not_restored'] ) ) );
            }
            $lines[] = $line;
        }
        $text = sprintf(
            /* translators: 1: number of entries, 2: one line per write */
            _n( 'Put back %1$d change-history entry: %2$s.', 'Put back %1$d change-history entries: %2$s.', count( $out['entries'] ), 'easy-mcp-ai' ),
            count( $out['entries'] ),
            $lines ? implode( '; ', $lines ) : __( 'nothing is written', 'easy-mcp-ai' )
        );
        if ( $others ) {
            $parts = array();
            foreach ( $others as $status => $n ) {
                $parts[] = $n . ' ' . $status;
            }
            /* translators: %s: counts by status, e.g. "2 incidental, 1 refused" */
            $text .= ' ' . sprintf( __( 'Not written: %s — a dry run says why.', 'easy-mcp-ai' ), implode( ', ', $parts ) );
        }
        return $text;
    }

    public function approval_refusal( array $arguments ): ?string {
        $length = \Easy_MCP_AI\Approvals\Approval_Gate::preview_overflow( (string) $this->describe( $arguments ) );
        if ( null === $length ) {
            return null;
        }
        return sprintf(
            'This plan is %1$d characters long and the approval page shows %2$d, so it was not sent for approval: a person must see every write they approve. Apply fewer entries: one entry_id at a time, newest first. A single entry this long cannot be approved here; read it with wp_history_get and make its calls one at a time.',
            $length,
            \Easy_MCP_AI\Approvals\Approval_Gate::PREVIEW_MAX
        );
    }

    private static function call_texts( array $calls ): array {
        $texts = array();
        $count = count( $calls );
        for ( $i = 0; $i < $count; ) {
            $group = array( $calls[ $i ] );
            $key   = null;
            for ( $j = $i + 1; $j < $count; $j++ ) {
                $differs = self::only_difference( $calls[ $i ], $calls[ $j ] );
                if ( null === $differs || ( null !== $key && $differs !== $key ) ) {
                    break;
                }
                $key     = $differs;
                $group[] = $calls[ $j ];
            }
            if ( null === $key ) {
                $texts[] = self::call_text( $calls[ $i ]['tool'], $calls[ $i ]['arguments'] );
            } else {
                $values = array();
                foreach ( $group as $call ) {
                    $values[] = self::shown_value( $key, $call['arguments'][ $key ] );
                }
                $rest = $calls[ $i ]['arguments'];
                unset( $rest[ $key ] );
                $texts[] = sprintf( '%s ×%d for %s %s', self::call_text( $calls[ $i ]['tool'], $rest ), count( $group ), $key, implode( ', ', $values ) );
            }
            $i += count( $group );
        }
        return $texts;
    }

    private static function only_difference( array $a, array $b ): ?string {
        if ( $a['tool'] !== $b['tool'] || array_keys( $a['arguments'] ) !== array_keys( $b['arguments'] ) ) {
            return null;
        }
        $differ = array();
        foreach ( $a['arguments'] as $key => $value ) {
            if ( $value !== $b['arguments'][ $key ] ) {
                $differ[] = $key;
            }
        }
        return 1 === count( $differ ) ? $differ[0] : null;
    }

    private static function call_text( string $tool, array $arguments ): string {
        $parts = array();
        foreach ( $arguments as $key => $value ) {
            $parts[] = $key . ' ' . self::shown_value( $key, $value );
        }
        return $parts ? $tool . ' (' . implode( ', ', $parts ) . ')' : $tool;
    }

    private static function shown_value( string $key, $value ): string {
        if ( 'content' === $key && is_string( $value ) ) {
            $length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
            /* translators: %d: number of characters */
            return sprintf( __( '[the earlier body, %d characters]', 'easy-mcp-ai' ), $length );
        }
        if ( Rebuild_Planner::NEW_ID === $value ) {
            return __( '[the new id]', 'easy-mcp-ai' );
        }
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( null === $value || is_int( $value ) || is_float( $value ) ) {
            return null === $value ? 'null' : (string) $value;
        }
        if ( is_array( $value ) ) {
            array_walk_recursive(
                $value,
                static function ( &$item ) {
                    if ( Rebuild_Planner::NEW_ID === $item ) {
                        $item = '[the new id]';
                    }
                }
            );
        }
        $text = is_string( $value ) ? '"' . $value . '"' : (string) \wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
        if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text ) ) > 80 ) {
            $text = ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 79 ) : substr( $text, 0, 79 ) ) . '…';
        }
        return $text;
    }

    public function execute( array $arguments ) {
        $dry_run = isset( $arguments['dry_run'] ) && \rest_sanitize_boolean( $arguments['dry_run'] );
        return $this->run( $arguments, $dry_run );
    }

    private function run( array $arguments, $dry_run, $with_calls = false ) {
        $force        = isset( $arguments['force'] ) && \rest_sanitize_boolean( $arguments['force'] );
        $can_view_all = \current_user_can( 'easy_mcp_ai_view_all_history' );
        $rows         = $this->load_rows( $arguments, $can_view_all );
        $planner      = new Rebuild_Planner( $rows );

        $plans = array();
        foreach ( $rows as $row ) {
            $plans[] = $planner->plan( $row );
        }
        $total = Rebuild_Planner::count_calls( $plans );
        if ( $total > Rebuild_Planner::MAX_CALLS ) {
            $message = sprintf(
                'Putting these entries back needs %1$d tool calls; one wp_history_apply call makes at most %2$d. Apply them one at a time with entry_id, newest first.',
                $total,
                Rebuild_Planner::MAX_CALLS
            );
            throw new \InvalidArgumentException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $approval = ( ! $dry_run && 'redeem' === \Easy_MCP_AI\MCP\Server::current_call_approval_kind() ) ? 'skip' : null;

        $entries = array();
        $touched = array();
        $stopped = false;
        foreach ( $rows as $row ) {
            $entry = array(
                'entry_id' => (int) $row['id'],
                'object'   => $row['object_type'] . ':' . $row['object_id'],
                'action'   => (string) $row['action'],
            );
            if ( $stopped ) {
                $entry['status'] = 'not_run';
                $entries[]       = $entry;
                continue;
            }
            $plan = $planner->plan( $row );
            if ( 'restore' !== $plan['status'] ) {
                $by_plan         = array( 'skip' => 'skipped', 'incidental' => 'incidental' );
                $entry['status'] = $by_plan[ $plan['status'] ] ?? 'refused';
                $entry['reason'] = $plan['reason'];
                if ( $plan['not_restored'] ) {
                    $entry['not_restored'] = $plan['not_restored'];
                }
                $entries[] = $entry;
                continue;
            }
            $entry['calls'] = array_column( $plan['calls'], 'tool' );
            if ( $plan['not_restored'] ) {
                $entry['not_restored'] = $plan['not_restored'];
            }

            $keys = self::object_keys( $row );
            if ( $dry_run && array_intersect( $keys, array_keys( $touched ) ) ) {
                $entry['drift'] = __( 'checked when the call runs, after the newer entry for this object', 'easy-mcp-ai' );
            } else {
                $drift = $planner->drift( $row, $plan, $can_view_all );
                if ( ! $drift['readable'] ) {
                    $entry['drift'] = __( 'the current state could not be read, so a later change cannot be ruled out', 'easy-mcp-ai' );
                } elseif ( $drift['moved'] ) {
                    $entry['drift'] = $drift['moved'];
                }
                if ( isset( $entry['drift'] ) && ! $force ) {
                    $entry['status'] = 'refused';
                    $entry['reason'] = $drift['readable']
                        ? __( 'the object has changed since this entry was recorded; pass force: true to overwrite that change', 'easy-mcp-ai' )
                        : __( 'the current state could not be read; pass force: true to write anyway', 'easy-mcp-ai' );
                    $entries[] = $entry;
                    continue;
                }
            }

            if ( $dry_run ) {
                $entry['status'] = 'would_restore';
                if ( $with_calls ) {
                    $entry['planned'] = $plan['calls'];
                }
                foreach ( $keys as $key ) {
                    $touched[ $key ] = true;
                }
                $entries[] = $entry;
                continue;
            }

            $entry   = $this->run_plan( $row, $plan, $planner, $approval, $entry );
            $stopped = in_array( $entry['status'], array( 'failed', 'needs_approval' ), true );
            $entries[] = $entry;
        }

        $summary = array();
        foreach ( $entries as $entry ) {
            $summary[ $entry['status'] ] = ( $summary[ $entry['status'] ] ?? 0 ) + 1;
        }
        return array(
            'dry_run' => $dry_run,
            'force'   => $force,
            'entries' => $entries,
            'summary' => $summary,
        );
    }

    private function run_plan( array $row, array $plan, Rebuild_Planner $planner, $approval, array $entry ) {
        $new_id    = null;
        $audit_ids = array();
        foreach ( $plan['calls'] as $index => $call ) {
            $arguments = self::with_new_id( $call['arguments'], $new_id );
            $out       = $this->dispatch( $call['tool'], $arguments, $approval );
            if ( null === $out ) {
                throw new \RuntimeException( 'wp_history_apply runs only inside an MCP tools/call.' );
            }
            if ( ! empty( $out['audit_id'] ) ) {
                $audit_ids[] = (int) $out['audit_id'];
            }
            if ( 'paused' === ( $out['approval']['kind'] ?? '' ) ) {
                $entry['status'] = 'needs_approval';
                /* translators: %s: tool name */
                $entry['reason'] = sprintf( __( '%s needs a person\'s approval on this site; approve it, then apply this entry again', 'easy-mcp-ai' ), $call['tool'] );
                break;
            }
            $error = self::error_of( $out['response'] ?? array() );
            if ( null !== $error ) {
                $entry['status'] = 'failed';
                $entry['reason'] = $call['tool'] . ': ' . $error;
                break;
            }
            $payload = self::payload_of( $out['response'] ?? array() );
            if ( 0 === $index && null !== $plan['new_id_kind'] ) {
                $new_id = (int) ( $payload['id'] ?? 0 );
                if ( $new_id <= 0 ) {
                    $entry['status'] = 'failed';
                    /* translators: %s: tool name */
                    $entry['reason'] = sprintf( __( '%s did not return the new id', 'easy-mcp-ai' ), $call['tool'] );
                    break;
                }
                $planner->remember_new_id( $plan['new_id_kind'], (int) $row['object_id'], $new_id );
                $entry['new_id'] = $new_id;
            }
            if ( isset( $call['ignored_key'] ) && in_array( $call['ignored_key'], (array) ( $payload['ignored_keys'] ?? array() ), true ) ) {
                $entry['not_restored'][ $call['ignored_key'] ] = __( 'not registered for the REST API, so wp_update_post_meta could not write it', 'easy-mcp-ai' );
                $entry['partial'] = true;
            }
        }
        if ( $audit_ids ) {
            $entry['audit_ids'] = $audit_ids;
        }
        if ( ! isset( $entry['status'] ) ) {
            $lost = 'update' === $row['action'] && array_intersect(
                array_keys( (array) ( $entry['not_restored'] ?? array() ) ),
                is_array( $row['changed_fields'] ?? null ) ? $row['changed_fields'] : array()
            );
            $entry['status'] = ( $lost || ! empty( $entry['partial'] ) ) ? 'partial' : 'restored';
        }
        unset( $entry['partial'] );
        return $entry;
    }

    private function dispatch( string $tool, array $arguments, ?string $approval ): ?array {
        if ( null !== $this->dispatcher ) {
            return call_user_func( $this->dispatcher, $tool, $arguments, $approval );
        }
        return \Easy_MCP_AI\MCP\Server::call_as_current_caller( $tool, $arguments, $approval );
    }

    private function load_rows( array $arguments, $can_view_all ) {
        $has_entry = isset( $arguments['entry_id'] ) && '' !== $arguments['entry_id'] && null !== $arguments['entry_id'];
        $has_audit = isset( $arguments['audit_id'] ) && '' !== $arguments['audit_id'] && null !== $arguments['audit_id'];
        if ( $has_entry === $has_audit ) {
            throw new \InvalidArgumentException( 'Give exactly one of entry_id or audit_id.' );
        }
        $repo = new Change_Log_Repository();
        $me   = (int) \get_current_user_id();
        if ( $has_entry ) {
            $row = $repo->find( (int) $arguments['entry_id'] );
            if ( ! $row || ( ! $can_view_all && (int) ( $row['wp_user_id'] ?? 0 ) !== $me ) ) {
                throw new \RuntimeException( 'History entry not found' );
            }
            $rows = array( $row );
        } else {
            $filters = array( 'audit_id' => (int) $arguments['audit_id'] );
            if ( ! $can_view_all ) {
                $filters['wp_user_id'] = $me;
            }
            $rows = (array) $repo->query( $filters, Rebuild_Planner::MAX_CALLS + 1, 0 );
            if ( ! $rows ) {
                throw new \RuntimeException( 'No history entries found for that audit_id' );
            }
            if ( count( $rows ) > Rebuild_Planner::MAX_CALLS ) {
                $message = sprintf( 'That call wrote more than %d entries; apply them one at a time with entry_id, newest first.', Rebuild_Planner::MAX_CALLS );
                throw new \InvalidArgumentException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        foreach ( $rows as &$row ) {
            foreach ( array( 'before_value', 'after_value', 'changed_fields' ) as $key ) {
                $row[ $key ] = ! empty( $row[ $key ] ) && is_string( $row[ $key ] ) ? json_decode( $row[ $key ], true ) : ( is_array( $row[ $key ] ?? null ) ? $row[ $key ] : null );
            }
        }
        unset( $row );
        usort( $rows, static function ( $a, $b ) {
            return (int) $b['id'] <=> (int) $a['id'];
        } );
        return $rows;
    }

    private static function object_keys( array $row ) {
        $type = (string) $row['object_type'];
        $id   = (string) $row['object_id'];
        $keys = array( $type . ':' . $id );
        if ( in_array( $type, array( 'meta', 'object_terms' ), true ) ) {
            $keys[] = 'post:' . explode( ':', $id, 2 )[0];
        } elseif ( 'term_meta' === $type ) {
            $keys[] = 'term:' . explode( ':', $id, 2 )[0];
        }
        return $keys;
    }

    private static function with_new_id( $value, $new_id ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) {
                $value[ $k ] = self::with_new_id( $v, $new_id );
            }
            return $value;
        }
        return ( Rebuild_Planner::NEW_ID === $value && null !== $new_id ) ? (int) $new_id : $value;
    }

    private static function error_of( array $response ): ?string {
        if ( isset( $response['error'] ) ) {
            return (string) ( $response['error']['message'] ?? 'the call was refused' );
        }
        $result = $response['result'] ?? array();
        if ( ! empty( $result['isError'] ) ) {
            $text = (string) ( $result['content'][0]['text'] ?? 'the tool reported an error' );
            return 0 === strpos( $text, 'Error: ' ) ? substr( $text, 7 ) : $text;
        }
        return null;
    }

    private static function payload_of( array $response ): array {
        $result = $response['result'] ?? array();
        if ( isset( $result['structuredContent'] ) && is_array( $result['structuredContent'] ) ) {
            return $result['structuredContent'];
        }
        $decoded = json_decode( (string) ( $result['content'][0]['text'] ?? '' ), true );
        return is_array( $decoded ) ? $decoded : array();
    }
}
