<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Diagnostic_Result {

    const STATUS_PASS    = 'PASS';
    const STATUS_WARN    = 'WARN';
    const STATUS_FAIL    = 'FAIL';
    const STATUS_UNKNOWN = 'UNKNOWN';

    const TIER_BLOCKER = 'blocker';
    const TIER_WARNING = 'warning';
    const TIER_INFO    = 'info';

    private $id;

    private $status;

    private $tier;

    private $label;

    private $detail;

    private $fix;

    private $evidence;

    private $measured_at = null;

    private $group = '';

    private function __construct( $id, $status, $tier, $label, $detail = '', $fix = '', array $evidence = array() ) {
        $this->id       = (string) $id;
        $this->status   = $status;
        $this->tier     = $tier;
        $this->label    = (string) $label;
        $this->detail   = (string) $detail;
        $this->fix      = (string) $fix;
        $this->evidence = $evidence;
    }

    public static function pass( $id, $tier, $label, $detail = '', array $evidence = array() ) {
        return new self( $id, self::STATUS_PASS, $tier, $label, $detail, '', $evidence );
    }

    public static function warn( $id, $tier, $label, $detail = '', $fix = '', array $evidence = array() ) {
        return new self( $id, self::STATUS_WARN, $tier, $label, $detail, $fix, $evidence );
    }

    public static function fail( $id, $tier, $label, $detail = '', $fix = '', array $evidence = array() ) {
        return new self( $id, self::STATUS_FAIL, $tier, $label, $detail, $fix, $evidence );
    }

    public static function unknown( $id, $tier, $label, $reason = '', array $evidence = array() ) {
        return new self( $id, self::STATUS_UNKNOWN, $tier, $label, $reason, '', $evidence );
    }

    public function id() { return $this->id; }
    public function status() { return $this->status; }
    public function tier() { return $this->tier; }
    public function label() { return $this->label; }
    public function detail() { return $this->detail; }
    public function fix() { return $this->fix; }
    public function evidence() { return $this->evidence; }
    public function measured_at(): ?int { return $this->measured_at; }
    public function group(): string { return $this->group; }

    public function with_measurement( $group, $measured_at ) {
        $copy              = clone $this;
        $copy->group       = (string) $group;
        $copy->measured_at = null === $measured_at ? null : (int) $measured_at;
        return $copy;
    }

    public function is_problem() {
        return self::STATUS_WARN === $this->status || self::STATUS_FAIL === $this->status;
    }

    public function renders_in_notice() {
        return self::TIER_BLOCKER === $this->tier && self::STATUS_FAIL === $this->status;
    }

    public function renders_in_site_health() {
        return self::TIER_BLOCKER === $this->tier || self::TIER_WARNING === $this->tier;
    }

    public function problem_badge_html() {
        if ( self::STATUS_FAIL === $this->status ) {
            $symbol = "\u{26D4}";
            $text   = \__( 'Failed', 'easy-mcp-ai' );
            $color  = '#b32d2e';
        } elseif ( self::STATUS_WARN === $this->status ) {
            $symbol = "\u{26A0}";
            $text   = \__( 'Warning', 'easy-mcp-ai' );
            $color  = '#996800';
        } else {
            return '';
        }

        return '<span style="color:' . \esc_attr( $color ) . ';font-weight:600;white-space:nowrap;">'
            . \esc_html( $symbol . ' ' . $text )
            . '</span> ';
    }

    public function to_array() {
        return array(
            'id'       => $this->id,
            'status'   => $this->status,
            'tier'     => $this->tier,
            'label'    => $this->label,
            'detail'   => $this->detail,
            'fix'      => $this->fix,
            'evidence' => $this->evidence,
        ) + ( '' !== $this->group ? array(
            'group'       => $this->group,
            'measured_at' => $this->measured_at,
        ) : array() );
    }

    public static function from_array( $row ) {
        if ( ! is_array( $row ) ) {
            $row = array();
        }

        $status = isset( $row['status'] ) ? $row['status'] : null;
        if ( ! in_array( $status, array( self::STATUS_PASS, self::STATUS_WARN, self::STATUS_FAIL, self::STATUS_UNKNOWN ), true ) ) {
            $status = self::STATUS_UNKNOWN;
        }

        $tier = isset( $row['tier'] ) ? $row['tier'] : null;
        if ( ! in_array( $tier, array( self::TIER_BLOCKER, self::TIER_WARNING, self::TIER_INFO ), true ) ) {
            $tier = self::TIER_INFO;
        }

        $result = new self(
            isset( $row['id'] ) ? $row['id'] : '',
            $status,
            $tier,
            isset( $row['label'] ) ? $row['label'] : '',
            isset( $row['detail'] ) ? $row['detail'] : '',
            isset( $row['fix'] ) ? $row['fix'] : '',
            ( isset( $row['evidence'] ) && is_array( $row['evidence'] ) ) ? $row['evidence'] : array()
        );
        if ( isset( $row['group'] ) && is_string( $row['group'] ) && '' !== $row['group'] ) {
            $result = $result->with_measurement(
                $row['group'],
                isset( $row['measured_at'] ) && is_numeric( $row['measured_at'] ) && (int) $row['measured_at'] > 0 ? (int) $row['measured_at'] : null
            );
        }
        return $result;
    }
}
