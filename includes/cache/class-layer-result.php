<?php

namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Layer_Result {

    const CLEARED     = 'cleared';
    const QUEUED      = 'queued';
    const SKIPPED     = 'skipped';
    const FAILED      = 'failed';
    const WOULD_CLEAR = 'would_clear';

    const CHANGED = array( self::CLEARED, self::QUEUED );

    const STATUSES = array( self::CLEARED, self::QUEUED, self::SKIPPED, self::FAILED, self::WOULD_CLEAR );

    const REASONS = array(
        'not_selected',
        'disabled_by_filter',
        'not_deliverable',
        'no_url_api',
        'api_missing',
        'would_purge_site',
        'cdn_not_requested',
        'object_cache_not_requested',
        'network_scope',
        'read_only',
        'time_budget',
    );

    const APPLIES = array( 'now', 'after_response', 'async' );

    private $fields;

    public function __construct( string $id, string $label, string $kind, string $status, array $extra = array() ) {
        if ( ! in_array( $status, self::STATUSES, true ) ) {
            throw new \InvalidArgumentException( 'Unknown cache layer status: ' . $status ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $extra['reason'] ) && ! in_array( $extra['reason'], self::REASONS, true ) ) {
            throw new \InvalidArgumentException( 'Unknown skip reason: ' . (string) $extra['reason'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $extra['applies'] ) && ! in_array( $extra['applies'], self::APPLIES, true ) ) {
            throw new \InvalidArgumentException( 'Unknown applies value: ' . (string) $extra['applies'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->fields = array_merge(
            array(
                'id'     => $id,
                'label'  => $label,
                'kind'   => $kind,
                'status' => $status,
            ),
            array_filter(
                $extra,
                static function ( $value ): bool {
                    return '' !== $value && array() !== $value && null !== $value;
                }
            )
        );
    }

    public function status(): string {
        return (string) $this->fields['status'];
    }

    public function id(): string {
        return (string) $this->fields['id'];
    }

    public function get( string $key ) {
        return isset( $this->fields[ $key ] ) ? $this->fields[ $key ] : '';
    }

    public function changed(): bool {
        return in_array( $this->status(), self::CHANGED, true );
    }

    public function with( array $extra ): self {
        $fields = array_merge( $this->fields, $extra );
        $id     = (string) $fields['id'];
        $label  = (string) $fields['label'];
        $kind   = (string) $fields['kind'];
        $status = (string) $fields['status'];
        unset( $fields['id'], $fields['label'], $fields['kind'], $fields['status'] );
        return new self( $id, $label, $kind, $status, $fields );
    }

    public function to_array(): array {
        return $this->fields;
    }
}
