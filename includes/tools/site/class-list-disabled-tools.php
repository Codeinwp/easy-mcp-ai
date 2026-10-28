<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Feedback\Feedback_Availability;
use Easy_MCP_AI\Tools\Switched_Off_Inventory;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Disabled_Tools extends Base_Tool {

    const NAME = 'wp_list_disabled_tools';

    const DEFAULT_LIMIT = 20;
    const MAX_LIMIT     = 50;

    const QUERY_MAX = 200;

    const NOTE = 'Nothing listed here can be called until it is switched on, and this tool cannot switch anything on. First check that no tool you have does the task another way (a plugin\'s own REST routes, for example). Otherwise tell the user what to switch on and where. Name a tool or an ability by its exact `name`, never by a description of what it does, because the screens list them by that name alone; name an integration or a provider by its `label`. For an item with `requires`, say that the user\'s WordPress account must hold that capability as well, or that an administrator has to do the task. Once something is switched on, a new chat or a reconnect reads the tool list again.';

    const NOTHING_OFF = 'Nothing on this site is switched off. A tool you still cannot see is outside this connection\'s allowed tools or above the user\'s WordPress role.';

    const STOP_WORDS = 'a an and the of to for in on at by with from or my me is are it this that all can you please how do does wp wordpress';

    public function get_name() {
        return self::NAME;
    }

    public function get_description() {
        return 'List what this WordPress site has but has switched off: tools, plugin integrations (WooCommerce, ACF, SEO plugins and others), abilities that plugins add, and external data providers that are not connected. None of these is in your tool list. '
            . 'Call it before telling the user that something cannot be done. '
            . 'With no arguments it returns the groups and how many items each holds. With `query` (a few words of the task, e.g. "woocommerce orders") or `group` it returns the items, each with where an administrator switches it on. '
            . 'Read-only: it switches nothing on.';
    }

    public function get_category() {
        return 'site';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => 'List Disabled Tools',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'query'  => array(
                    'type'        => 'string',
                    'description' => 'A few words of what the user wants, e.g. "woocommerce orders" or "search console clicks". Items are ranked by how many of these words they have.',
                ),
                'group'  => array(
                    'type'        => 'string',
                    'description' => 'A `group` value from the summary, e.g. "integration:woocommerce", to list only that group.',
                ),
                'limit'  => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => self::MAX_LIMIT,
                    'default'     => self::DEFAULT_LIMIT,
                    'description' => 'Items per page.',
                ),
                'offset' => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'default'     => 0,
                    'description' => 'Items to skip, for the next page.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $entries = Switched_Off_Inventory::all( $this->registry() );
        $query   = isset( $arguments['query'] ) && is_string( $arguments['query'] ) ? trim( mb_substr( $arguments['query'], 0, self::QUERY_MAX ) ) : '';
        $group   = isset( $arguments['group'] ) && is_string( $arguments['group'] ) ? trim( $arguments['group'] ) : '';

        if ( array() === $entries ) {
            return array( 'total' => 0, 'groups' => array(), 'note' => self::NOTHING_OFF );
        }
        if ( '' === $query && '' === $group ) {
            return self::summary( $entries );
        }

        $result = array();
        if ( '' !== $group ) {
            $entries         = self::in_group( $entries, $group );
            $result['group'] = $group;
        }
        if ( '' !== $query ) {
            $entries         = self::ranked( $entries, $query );
            $result['query'] = $query;
        }
        return $result + self::page( $entries, $arguments, '' !== $query ? $query : $group );
    }

    private static function summary( array $entries ): array {
        $groups = array();
        foreach ( $entries as $entry ) {
            $key = $entry['group'];
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = array( 'group' => $key, 'label' => $entry['group_label'], 'count' => 0, 'states' => array(), 'places' => array() );
            }
            if ( in_array( $entry['kind'], array( Switched_Off_Inventory::INTEGRATION, Switched_Off_Inventory::PROVIDER ), true ) ) {
                $groups[ $key ]['summary'] = $entry['summary'];
            } else {
                ++$groups[ $key ]['count'];
            }
            $groups[ $key ]['states'][ $entry['state'] ] = true;
            $groups[ $key ]['places'][ $entry['where'] ] = true;
        }
        $rows = array();
        foreach ( $groups as $group ) {
            $row = array( 'group' => $group['group'], 'label' => $group['label'] );
            if ( $group['count'] > 0 ) {
                $row['count'] = $group['count'];
            }
            if ( 1 === count( $group['states'] ) ) {
                $row['state'] = (string) key( $group['states'] );
            }
            if ( 1 === count( $group['places'] ) ) {
                $row['where'] = (string) key( $group['places'] );
            }
            if ( isset( $group['summary'] ) && '' !== $group['summary'] ) {
                $row['summary'] = $group['summary'];
            }
            $rows[] = $row;
        }
        return array(
            'total'  => count( $entries ),
            'groups' => $rows,
            'note'   => 'Call again with `query` (a few words of the task) or `group` to see the items. ' . self::NOTE,
        );
    }

    private static function in_group( array $entries, string $group ): array {
        $kept  = array();
        $known = array();
        foreach ( $entries as $entry ) {
            $known[ $entry['group'] ] = true;
            if ( $entry['group'] === $group ) {
                $kept[] = $entry;
            }
        }
        if ( array() === $kept ) {
            throw new \InvalidArgumentException( sprintf( 'No group "%s" has anything switched off. Groups: %s.', $group, implode( ', ', array_keys( $known ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $kept;
    }

    private static function ranked( array $entries, string $query ): array {
        $wanted = array();
        foreach ( array_diff( Feedback_Availability::words( $query ), Feedback_Availability::words( self::STOP_WORDS ) ) as $word ) {
            if ( strlen( $word ) > 1 && ! ctype_digit( $word ) ) {
                $wanted[ $word ] = true;
            }
        }
        $wanted = array_map( 'strval', array_keys( $wanted ) );
        $scores = array();
        $groups = array();
        foreach ( $entries as $index => $entry ) {
            $scores[ $index ] = self::score( $entry, $wanted );
            $best             = isset( $groups[ $entry['group'] ] ) ? $groups[ $entry['group'] ] : array( 0, 0 );
            $groups[ $entry['group'] ] = array( max( $best[0], $scores[ $index ][0] ), max( $best[1], $scores[ $index ][1] ) );
        }
        $scored = array();
        $most   = 0;
        foreach ( $entries as $index => $entry ) {
            list( $matched, $score, $covered ) = $scores[ $index ];
            if ( Switched_Off_Inventory::INTEGRATION === $entry['kind'] && $groups[ $entry['group'] ][0] > 0 ) {
                list( $matched, $score, $covered ) = array( $groups[ $entry['group'] ][0], $groups[ $entry['group'] ][1] + 1, 0 );
            }
            if ( $matched > 0 ) {
                $scored[] = array( $matched, $score, $index, 'read' === $entry['access'] ? 0 : 1, $covered );
                $most     = max( $most, $matched );
            }
        }
        $needed = min( $most, count( $wanted ) >= 3 ? 2 : 1 );
        $scored = array_values( array_filter( $scored, static function ( array $row ) use ( $needed ): bool {
            return $row[0] >= $needed;
        } ) );
        usort( $scored, static function ( array $a, array $b ): int {
            return array( $b[1], $a[4], $a[3], $a[2] ) <=> array( $a[1], $b[4], $b[3], $b[2] );
        } );
        $ranked = array();
        foreach ( $scored as $row ) {
            $ranked[] = $entries[ $row[2] ];
        }
        return $ranked;
    }

    private static function score( array $entry, array $wanted ): array {
        $name    = Feedback_Availability::words( str_replace( '_', ' ', $entry['name'] ) . ' ' . ( isset( $entry['slug'] ) ? $entry['slug'] : '' ) );
        $covers  = array();
        $shared  = array();
        if ( isset( $entry['covers'] ) || isset( $entry['match'] ) ) {
            $stop    = Feedback_Availability::words( self::STOP_WORDS );
            $phrases = array();
            $seen    = array();
            $all     = ( isset( $entry['covers'] ) ? $entry['covers'] : '' ) . ',' . ( isset( $entry['match'] ) ? $entry['match'] : '' );
            foreach ( explode( ',', $all ) as $phrase ) {
                $words     = array_values( array_unique( array_diff( Feedback_Availability::words( $phrase ), $stop ) ) );
                $phrases[] = $words;
                foreach ( $words as $word ) {
                    $seen[ $word ] = isset( $seen[ $word ] ) ? $seen[ $word ] + 1 : 1;
                }
            }
            foreach ( $phrases as $words ) {
                $whole = array() !== $words && array() === array_diff( $words, $wanted );
                foreach ( $words as $word ) {
                    if ( $whole || 1 === $seen[ $word ] ) {
                        $covers[] = (string) $word;
                    }
                    if ( $seen[ $word ] > 1 ) {
                        $shared[] = (string) $word;
                    }
                }
            }
        }
        $label   = Feedback_Availability::words( $entry['label'] . ' ' . $entry['group_label'] );
        $summary = Feedback_Availability::words( $entry['summary'] );
        $matched = 0;
        $score   = 0;
        $covered = 0;
        foreach ( $wanted as $word ) {
            $own    = in_array( $word, $name, true );
            $weight = $own || in_array( $word, $covers, true ) ? 3 : ( in_array( $word, $label, true ) ? 2 : ( in_array( $word, $summary, true ) ? 1 : 0 ) );
            if ( $weight > 0 ) {
                ++$matched;
                $score += $weight;
            }
            if ( ! $own && 3 === $weight && in_array( $word, $shared, true ) ) {
                $covered += 3;
            }
        }
        return array( $matched, $score, $covered );
    }

    private static function page( array $entries, array $arguments, string $asked ): array {
        $total = count( $entries );
        if ( 0 === $total ) {
            return array(
                'total' => 0,
                'items' => array(),
                'note'  => sprintf( 'Nothing that is switched off on this site matches "%s". If no tool in your list does it either, this site cannot do it yet.', $asked ),
            );
        }
        $limit  = isset( $arguments['limit'] ) && is_numeric( $arguments['limit'] ) ? max( 1, min( self::MAX_LIMIT, (int) $arguments['limit'] ) ) : self::DEFAULT_LIMIT;
        $offset = isset( $arguments['offset'] ) && is_numeric( $arguments['offset'] ) ? max( 0, (int) $arguments['offset'] ) : 0;
        $items  = array_map( array( __CLASS__, 'item' ), array_slice( $entries, $offset, $limit ) );
        $page   = array(
            'total'    => $total,
            'items'    => $items,
            'has_more' => $offset + count( $items ) < $total,
        );
        if ( $page['has_more'] ) {
            $page['next_offset'] = $offset + count( $items );
        }
        $page['note'] = array() === $items ? sprintf( 'No items at offset %d: there are %d.', $offset, $total ) : self::NOTE;
        return $page;
    }

    private static function item( array $entry ): array {
        $item = array( 'kind' => $entry['kind'], 'name' => $entry['name'] );
        if ( Switched_Off_Inventory::TOOL !== $entry['kind'] ) {
            $item['label'] = $entry['label'];
        }
        if ( isset( $entry['slug'] ) ) {
            $item['ability'] = $entry['slug'];
        }
        $item['group'] = $entry['group'];
        if ( in_array( $entry['kind'], array( Switched_Off_Inventory::TOOL, Switched_Off_Inventory::ABILITY ), true ) ) {
            $item['access'] = $entry['access'];
        }
        if ( isset( $entry['destructive'] ) ) {
            $item['destructive'] = true;
        }
        if ( isset( $entry['requires'] ) ) {
            $item['requires'] = $entry['requires'];
        }
        if ( isset( $entry['tools'] ) ) {
            $item['tools'] = $entry['tools'];
        }
        if ( isset( $entry['covers'] ) ) {
            $item['covers'] = $entry['covers'];
        }
        $item['state']   = $entry['state'];
        $item['where']   = $entry['where'];
        $item['summary'] = $entry['summary'];
        return $item;
    }

    protected function registry(): ?\Easy_MCP_AI\Tools\Tool_Registry {
        return class_exists( '\\Easy_MCP_AI\\Plugin' ) ? \Easy_MCP_AI\Plugin::instance()->get_tool_registry() : null;
    }
}
