<?php

namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Elementor_Frontend_Controls extends Compat_Shim {

    const PERFORMANCE = 'Elementor\\Core\\Frontend\\Performance';

    public function id() {
        return 'elementor-frontend-controls';
    }

    public function applies_to( $tool ) {
        return is_object( $tool ) && method_exists( $tool, 'get_category' ) && 'builders' === $tool->get_category();
    }

    public function is_needed() {
        return defined( 'REST_REQUEST' ) && REST_REQUEST && null !== self::controls_manager() && self::readable() && false !== self::cached();
    }

    public function enter( array $arguments = array(), $tool = null ) {
        $manager = self::controls_manager();
        if ( null === $manager || ! self::readable() || false === self::cached() ) {
            return null;
        }
        $state           = new \stdClass();
        $state->previous = self::cached();
        self::write_cache( false );
        $manager->clear_stack_cache();
        return $state;
    }

    public function leave( $state = null ): void {
        $manager = self::controls_manager();
        if ( ! $state instanceof \stdClass || ! property_exists( $state, 'previous' ) || null === $manager ) {
            return;
        }
        self::write_cache( $state->previous );
        $manager->clear_stack_cache();
    }

    private static function readable(): bool {
        return class_exists( self::PERFORMANCE ) && property_exists( self::PERFORMANCE, 'is_frontend' );
    }

    private static function cached(): ?bool {
        if ( ! self::readable() ) {
            return null;
        }
        $value = \Closure::bind(
            static function () {
                return static::$is_frontend;
            },
            null,
            self::PERFORMANCE
        )();
        return is_bool( $value ) ? $value : null;
    }

    private static function write_cache( ?bool $value ): void {
        \Closure::bind(
            static function () use ( $value ) {
                static::$is_frontend = $value;
            },
            null,
            self::PERFORMANCE
        )();
    }

    private static function controls_manager(): ?object {
        if ( ! class_exists( 'Elementor\\Plugin', false ) || ! isset( \Elementor\Plugin::$instance->controls_manager ) ) {
            return null;
        }
        $manager = \Elementor\Plugin::$instance->controls_manager;
        return is_object( $manager ) && method_exists( $manager, 'clear_stack_cache' ) ? $manager : null;
    }
}
