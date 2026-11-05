<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tool_Policy {
    const OPTION = 'easy_mcp_ai_tool_policy';
    const HOLD_POLICIES = array( 'write_destructive', 'all', 'destructive', 'none' );
    const LOCK      = 'easy_mcp_ai_tool_policy_lock';
    const LOCK_TTL  = 30;
    const LOCK_WAIT = 2;

    private static $lock = '';

    private static $memo = array( 'set' => false, 'raw' => null, 'state' => array() );

    public static function state(): array {
        $value = \get_option( self::OPTION, null );
        if ( self::$memo['set'] && self::$memo['raw'] === $value ) {
            return self::$memo['state'];
        }
        $result     = self::normalize( $value );
        self::$memo = array( 'set' => true, 'raw' => $value, 'state' => $result );
        return $result;
    }

    private static function normalize( $value ) {
        if ( null === $value ) {
            return array();
        }
        $value = is_array( $value ) ? $value : array();
        if ( ! array_key_exists( 'known', $value ) && array() !== $value ) {
            return array( 'holdPolicy' => self::valid_hold_policy( $value['holdPolicy'] ?? null ) );
        }
        $state = array(
            'known' => array(),
            'choices' => array(),
            'holdPolicy' => self::valid_hold_policy( $value['holdPolicy'] ?? null ),
        );
        if ( array() === ( $value['known'] ?? null ) ) {
            $state['unlisted'] = true;
        }
        foreach ( (array) ( $value['known'] ?? array() ) as $name => $flags ) {
            if ( is_string( $name ) ) {
                $state['known'][ $name ] = array(
                    'held' => is_array( $flags ) && isset( $flags['held'] ) && is_bool( $flags['held'] ) ? $flags['held'] : true,
                );
            }
        }
        foreach ( (array) ( $value['choices'] ?? array() ) as $name => $choice ) {
            if ( ! is_string( $name ) || ! is_array( $choice ) ) {
                continue;
            }
            foreach ( array( 'enabled', 'requireApproval' ) as $field ) {
                if ( array_key_exists( $field, $choice ) ) {
                    $state['choices'][ $name ][ $field ] = is_bool( $choice[ $field ] ) ? $choice[ $field ] : 'requireApproval' === $field;
                }
            }
        }
        return $state;
    }

    private static function valid_hold_policy( $value ) {
        return in_array( $value, self::HOLD_POLICIES, true ) ? $value : 'write_destructive';
    }

    public static function hold_policy(): string {
        return self::state()['holdPolicy'] ?? 'write_destructive';
    }

    public static function category( array $annotations, string $group = '' ): string {
        if ( in_array( $group, array( 'cli', 'rest' ), true ) ) {
            return 'unrestricted';
        }
        if ( ! empty( $annotations['readOnlyHint'] ) ) {
            return 'read';
        }
        return \Easy_MCP_AI\Approvals\Approval_Gate::annotations_destructive( $annotations ) ? 'destructive' : 'write';
    }

    public static function held_by( string $category, string $policy ): bool {
        return 'all' === $policy || ( 'write_destructive' === $policy && 'read' !== $category )
            || ( 'destructive' === $policy && in_array( $category, array( 'destructive', 'unrestricted' ), true ) );
    }

    public static function synchronize( array $categories, bool $persist = true ): array {
        list( $state, $changed, $baseline ) = self::synchronized( self::state(), $categories );
        if ( ! $changed || ! $persist ) {
            return $state;
        }
        $stored = false;
        self::locked( static function () use ( $categories, &$state, &$baseline, &$stored ) {
            list( $state, $changed, $baseline, $retire_patterns ) = self::synchronized( self::fresh(), $categories );
            $stored = ! $changed || self::persist( $state );
            if ( $changed && $stored && $retire_patterns ) {
                \Easy_MCP_AI\Config::update( 'easy_mcp_ai_allowed_tool_patterns', array() );
            }
        } );
        if ( ! $stored && ! $baseline ) {
            self::$memo['state'] = $state;
        }
        return $state;
    }

    private static function synchronized( array $state, array $categories ): array {
        $first = ! isset( $state['known'] ) || ! is_array( $state['known'] );
        if ( $first ) {
            $state = array( 'known' => array(), 'choices' => array(), 'holdPolicy' => $state['holdPolicy'] ?? 'write_destructive' );
        }
        $changed = $first;
        $baseline = $first || ! empty( $state['unlisted'] );
        unset( $state['unlisted'] );
        foreach ( $categories as $name => $category ) {
            if ( ! array_key_exists( $name, $state['known'] ) ) {
                $state['known'][ $name ] = array(
                    'held' => ! $baseline && self::held_by( $category, $state['holdPolicy'] ?? 'write_destructive' ),
                );
                if ( ! $baseline && in_array( $category, array( 'destructive', 'unrestricted' ), true ) && ! isset( $state['choices'][ $name ]['requireApproval'] ) ) {
                    $state['choices'][ $name ]['requireApproval'] = true;
                }
                $changed = true;
            }
        }
        $retire_patterns = false;
        if ( $first && ! \Easy_MCP_AI\Config::is_locked( 'allowed_tool_patterns' ) ) {
            $patterns = (array) \Easy_MCP_AI\Config::get( 'allowed_tool_patterns' );
            $left_out = array();
            foreach ( $categories as $name => $category ) {
                if ( ! \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns ) ) {
                    $left_out[] = $name;
                }
            }
            foreach ( (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() ) as $slug ) {
                if ( ! is_string( $slug ) || '' === $slug ) {
                    continue;
                }
                $name = \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar::build_tool_name( $slug );
                if ( ! array_key_exists( $name, $categories ) && ! \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns ) && ! in_array( $name, $left_out, true ) ) {
                    $left_out[] = $name;
                }
            }
            $migratable = ! \Easy_MCP_AI\Config::is_locked( 'disabled_tools' );
            foreach ( $left_out as $name ) {
                if ( true === Tool_State::override_of( $name ) ) {
                    $migratable = false;
                    break;
                }
            }
            if ( $patterns && $migratable ) {
                foreach ( $left_out as $name ) {
                    $state['choices'][ $name ]['enabled'] = false;
                }
                $retire_patterns = true;
            }
        }
        return array( $state, $changed, $baseline, $retire_patterns );
    }

    public static function observe( Base_Tool $tool ): void {
        if ( self::observing() ) {
            self::synchronize( array( $tool->get_name() => self::category( $tool->get_annotations(), $tool->get_category() ) ) );
        }
    }

    public static function observe_tools( array $categories ): void {
        if ( $categories && self::observing() ) {
            self::synchronize( $categories );
        }
    }

    private static function observing(): bool {
        $state = self::state();
        return isset( $state['known'] ) && empty( $state['unlisted'] );
    }

    public static function observe_abilities( array $abilities ): bool {
        if ( ! self::added_abilities( self::state(), $abilities ) ) {
            return true;
        }
        $recorded = false;
        $locked   = self::locked( static function () use ( $abilities, &$recorded ) {
            $state = self::fresh();
            $added = self::added_abilities( $state, $abilities );
            if ( ! $added ) {
                $recorded = true;
                return;
            }
            $tick = array();
            foreach ( $added as $name => $entry ) {
                if ( ! self::held_by( $entry[1], $state['holdPolicy'] ?? 'write_destructive' ) && self::may_switch_on( $name ) ) {
                    $tick[] = $entry[0];
                }
            }
            if ( $tick ) {
                $enabled = array_values( array_unique( array_merge( (array) ( \Easy_MCP_AI\Config::read_uncached( 'easy_mcp_ai_enabled_abilities' ) ?? array() ), $tick ) ) );
                \update_option( 'easy_mcp_ai_enabled_abilities', $enabled );
                if ( \Easy_MCP_AI\Config::read_uncached( 'easy_mcp_ai_enabled_abilities' ) !== $enabled ) {
                    return;
                }
            }
            self::synchronize( array_map( static function ( $entry ) {
                return $entry[1];
            }, $added ) );
            $recorded = true;
        } );
        return $locked && $recorded;
    }

    private static function added_abilities( array $state, array $abilities ): array {
        if ( ! isset( $state['known'] ) || ! empty( $state['unlisted'] ) ) {
            return array();
        }
        $added = array();
        foreach ( $abilities as $ability ) {
            if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_name' ) ) {
                continue;
            }
            $slug = (string) $ability->get_name();
            $name = Dynamic_Tool_Registrar::build_tool_name( $slug );
            if ( '' === $slug || array_key_exists( $name, $state['known'] ) || isset( $added[ $name ] ) ) {
                continue;
            }
            $added[ $name ] = array( $slug, self::category( Dynamic_Tool_Registrar::annotations_of( $ability ), 'abilities' ) );
        }
        return $added;
    }

    private static function may_switch_on( $name ) {
        if ( null !== Tool_State::override_of( $name ) || in_array( $name, Tool_State::disabled_list(), true ) || false === self::enabled_choice( $name ) ) {
            return false;
        }
        $patterns = (array) \Easy_MCP_AI\Config::get( 'allowed_tool_patterns' );
        return ! $patterns || \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns );
    }

    public static function enabled_choice( string $name ): ?bool {
        $state = self::state();
        if ( isset( $state['choices'][ $name ]['enabled'] ) && is_bool( $state['choices'][ $name ]['enabled'] ) ) {
            return $state['choices'][ $name ]['enabled'];
        }
        return null;
    }

    public static function is_held( string $name ): bool {
        return ! empty( self::state()['known'][ $name ]['held'] );
    }

    public static function approval_choice( string $name ): ?bool {
        $state = self::state();
        $value = $state['choices'][ $name ]['requireApproval'] ?? null;
        return is_bool( $value ) ? $value : null;
    }

    private static function persist( array $state ) {
        unset( $state['unlisted'] );
        \update_option( self::OPTION, $state, false );
        return \Easy_MCP_AI\Config::read_uncached( self::OPTION ) === $state;
    }

    private static function fresh(): array {
        return self::normalize( \Easy_MCP_AI\Config::read_uncached( self::OPTION ) );
    }

    public static function locked( callable $write ): bool {
        if ( '' !== self::$lock ) {
            $write();
            return true;
        }
        try {
            self::$lock = \Easy_MCP_AI\Option_Lock::acquire_waiting( self::LOCK, self::LOCK_TTL, self::LOCK_WAIT, 'tool policy save' );
        } catch ( \RuntimeException $e ) {
            return false;
        }
        try {
            $write();
        } finally {
            \Easy_MCP_AI\Option_Lock::release( self::LOCK, self::$lock );
            self::$lock = '';
        }
        return true;
    }

    private static function storage_error() {
        return new \WP_Error( 'easy_mcp_ai_policy_not_saved', \__( 'The tool policy could not be saved. Nothing was changed; try again.', 'easy-mcp-ai' ), array( 'status' => 500 ) );
    }

    public static function save( array $choices ) {
        $result = self::storage_error();
        self::locked( static function () use ( $choices, &$result ) {
            $state = self::fresh();
            if ( ! isset( $state['known'] ) ) {
                return;
            }
            foreach ( $choices as $name => $choice ) {
                if ( ! empty( $state['known'][ $name ]['held'] ) && ! array_key_exists( 'enabled', $choice ) && ! isset( $state['choices'][ $name ]['enabled'] ) ) {
                    $choice['enabled'] = false;
                }
                $state['choices'][ $name ] = array_merge( $state['choices'][ $name ] ?? array(), $choice );
                if ( isset( $state['known'][ $name ] ) ) {
                    $state['known'][ $name ] = array( 'held' => false );
                }
            }
            $result = self::persist( $state ) ? true : self::storage_error();
        } );
        return $result;
    }

    public static function forget( array $names, string $field ) {
        $has = static function ( array $state ) use ( $names, $field ) {
            foreach ( $names as $name ) {
                if ( isset( $state['choices'][ $name ] ) && array_key_exists( $field, $state['choices'][ $name ] ) ) {
                    return true;
                }
            }
            return false;
        };
        if ( ! $has( self::state() ) ) {
            return true;
        }
        $result = self::storage_error();
        self::locked( static function () use ( $names, $field, $has, &$result ) {
            $state = self::fresh();
            if ( ! $has( $state ) ) {
                $result = true;
                return;
            }
            foreach ( $names as $name ) {
                if ( ! isset( $state['choices'][ $name ] ) || ! array_key_exists( $field, $state['choices'][ $name ] ) ) {
                    continue;
                }
                unset( $state['choices'][ $name ][ $field ] );
                if ( ! $state['choices'][ $name ] ) {
                    unset( $state['choices'][ $name ] );
                }
            }
            $result = self::persist( $state ) ? true : self::storage_error();
        } );
        return $result;
    }

    public static function set_hold_policy( $policy ) {
        if ( ! in_array( $policy, self::HOLD_POLICIES, true ) ) {
            return self::storage_error();
        }
        $result = self::storage_error();
        self::locked( static function () use ( $policy, &$result ) {
            $state = self::fresh();
            $state = isset( $state['known'] ) ? $state : array();
            $state['holdPolicy'] = $policy;
            $result = self::persist( $state ) ? true : self::storage_error();
        } );
        return $result;
    }
}
