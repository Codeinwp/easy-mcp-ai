<?php
namespace Easy_MCP_AI\Tasks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

















class Task_Contract {
    const MODE_CURSOR = 'cursor';
    const MODE_POLL   = 'poll';

    const STATES = array( 'working', 'completed', 'failed', 'cancelled' );

    




    public static function normalize( $meta, $input_schema = null ) {
        if ( is_object( $meta ) ) {
            $meta = (array) $meta;
        }
        if ( ! is_array( $meta ) || ! isset( $meta['mode'] ) || ! is_string( $meta['mode'] ) ) {
            return null;
        }
        $mode = $meta['mode'];
        if ( self::MODE_CURSOR !== $mode && self::MODE_POLL !== $mode ) {
            return null;
        }
        $status_ability = self::ability_slug( isset( $meta['status_ability'] ) ? $meta['status_ability'] : null );
        $cancel_ability = self::ability_slug( isset( $meta['cancel_ability'] ) ? $meta['cancel_ability'] : null );
        if ( self::MODE_POLL === $mode && null === $status_ability ) {
            return null; 
        }
        $properties = self::schema_properties( $input_schema );
        if ( self::MODE_CURSOR === $mode && ! isset( $properties['cursor'] ) ) {
            return null; 
        }
        $results_key = null;
        if ( isset( $meta['results_key'] ) && is_string( $meta['results_key'] ) && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/D', $meta['results_key'] ) ) {
            $results_key = $meta['results_key'];
        }
        $max_budget = 60;
        if ( isset( $properties['time_budget'] ) ) {
            $budget_schema = is_object( $properties['time_budget'] ) ? (array) $properties['time_budget'] : $properties['time_budget'];
            if ( is_array( $budget_schema ) && isset( $budget_schema['maximum'] ) && is_numeric( $budget_schema['maximum'] ) && $budget_schema['maximum'] >= 1 ) {
                $max_budget = (int) $budget_schema['maximum'];
            }
        }
        return array(
            'mode'                => $mode,
            'results_key'         => $results_key,
            'status_ability'      => $status_ability,
            'cancel_ability'      => $cancel_ability,
            'accepts_time_budget' => isset( $properties['time_budget'] ),
            'max_time_budget'     => $max_budget,
        );
    }

    private static function ability_slug( $value ) {
        if ( ! is_string( $value ) || ! preg_match( '#^[a-z0-9\-]+/[a-z0-9\-/]+$#D', $value ) || strlen( $value ) > 191 ) {
            return null;
        }
        return $value;
    }

    private static function schema_properties( $schema ) {
        if ( is_object( $schema ) ) {
            $schema = (array) $schema;
        }
        if ( ! is_array( $schema ) || ! isset( $schema['properties'] ) ) {
            return array();
        }
        $properties = is_object( $schema['properties'] ) ? (array) $schema['properties'] : $schema['properties'];
        return is_array( $properties ) ? $properties : array();
    }

    





    public static function read_chunk( $data ) {
        if ( ! is_array( $data ) || ! array_key_exists( 'done', $data ) || ! is_bool( $data['done'] ) ) {
            return null;
        }
        $cursor = isset( $data['cursor'] ) && is_string( $data['cursor'] ) ? $data['cursor'] : '';
        if ( ! $data['done'] && '' === $cursor ) {
            return null; 
        }
        return array(
            'done'     => $data['done'],
            'cursor'   => $cursor,
            'progress' => self::read_progress( $data ),
            'job_id'   => self::read_job_id( $data ),
        );
    }

    




    public static function read_status( $data ) {
        if ( ! is_array( $data ) || ! isset( $data['state'] ) || ! in_array( $data['state'], self::STATES, true ) ) {
            return null;
        }
        return array( 'state' => $data['state'], 'progress' => self::read_progress( $data ) );
    }

    public static function read_job_id( $data ) {
        if ( is_array( $data ) && isset( $data['job_id'] ) && ( is_string( $data['job_id'] ) || is_int( $data['job_id'] ) ) && '' !== (string) $data['job_id'] ) {
            return substr( (string) $data['job_id'], 0, 20000 );
        }
        return '';
    }

    public static function read_progress( $data ) {
        $progress = is_array( $data ) && isset( $data['progress'] ) && is_array( $data['progress'] ) ? $data['progress'] : array();
        $number   = static function ( $value ) {
            return is_numeric( $value ) && $value > 0 ? (int) min( $value, PHP_INT_MAX ) : 0;
        };
        return array(
            'current' => $number( isset( $progress['current'] ) ? $progress['current'] : 0 ),
            'total'   => $number( isset( $progress['total'] ) ? $progress['total'] : 0 ),
            'message' => isset( $progress['message'] ) && is_string( $progress['message'] ) ? substr( $progress['message'], 0, 500 ) : '',
        );
    }

    




    public static function merge_chunk( $accumulated, array $chunk, $results_key ) {
        if ( ! is_array( $accumulated ) ) {
            return $chunk;
        }
        $merged = $chunk;
        if ( null !== $results_key && isset( $accumulated[ $results_key ] ) && is_array( $accumulated[ $results_key ] ) ) {
            $new = isset( $chunk[ $results_key ] ) && is_array( $chunk[ $results_key ] ) ? $chunk[ $results_key ] : array();
            $old = $accumulated[ $results_key ];
            $merged[ $results_key ] = self::is_list( $old ) && self::is_list( $new )
                ? array_merge( $old, $new )
                : array_replace( $old, $new );
        }
        return $merged;
    }

    private static function is_list( array $value ) {
        return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
    }
}
