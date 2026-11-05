<?php
namespace Easy_MCP_AI\Tools\Builders;

use Easy_MCP_AI\Builders\Builder_Registry;
use Easy_MCP_AI\Builders\Elementor_Editor;
use Easy_MCP_AI\Builders\Layout_Adapter;
use Easy_MCP_AI\Builders\Layout_Lock;
use Easy_MCP_AI\Builders\Shortcode_Editor;
use Easy_MCP_AI\Builders\Shortcode_Structure;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Page_Layout extends Base_Tool {

    public function get_name() {
        return 'wp_update_page_layout';
    }

    public function get_description() {
        return 'Changes elements of a page built with Elementor, Breakdance, WPBakery, Flatsome\'s UX Builder or Divi (4 or 5), saved the way the builder saves, keeping a revision. Read the layout with wp_get_page_layout first: required `post_id`, `version` (from that read; refused if stale) and `ops`, applied in order, all or none. Ops: `set_text` { element, value, field? } (default field: the element\'s main text; HTML where the field is HTML); `set_link` { element, url, new_tab?, nofollow?, field? }; `set_image` { element, attachment_id, field? }; `set_fields` { element, fields: { name: value } } for other fields wp_list_layout_elements lists. Elementor only: `list` and `item` (from the read\'s `lists`) aim a field op at one list row, and `add_item`, `remove_item` and `move_item` { element, list, item?, index? or after?, copy_of?, fields? } change the list. `element` is the address from wp_get_page_layout: an Elementor id, a Breakdance id ("102"), or a path such as "0.1.2" for the other builders. Elementor, Flatsome and Divi also take `insert` { element_type, fields?, ref? }, `remove` { element }, `move` { element } and `duplicate` { element, ref? }, placed by `parent`/`index`, `after` or `before` as each property says; the builder\'s own rules decide what may go where (Elementor: widgets and containers, never sections or columns). Addresses mean the layout as read, even after earlier ops moved or removed elements; `ref` names a new element for later ops. Any op may carry `expect` (its required element type). `dry_run: true` saves nothing. Returns { changed, saved, version (send it with the next edit), results (one per op and per field written), parents?, revision_id?, warnings? }. Elementor V4 elements (flag `atomic`) are edited with Elementor\'s own abilities. Breakdance: no code, dynamic data or `nofollow`; its own abilities insert, move and remove elements. Breakdance, WPBakery, Flatsome and Divi are edited only on measured versions; the read says `editable` and why not. Requires edit access to the item.';
    }

    public function get_category() {
        return 'builders';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post, page or template.',
                ),
                'version' => array(
                    'type'        => 'string',
                    'description' => 'The `version` wp_get_page_layout (or the previous edit) returned. The call is refused when the layout changed since.',
                ),
                'ops'     => array(
                    'type'        => 'array',
                    'description' => 'Operations, applied in order; if any is refused, nothing is saved.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'op'            => array(
                                'type' => 'string',
                                'enum' => array_values( array_unique( array_merge( Elementor_Editor::OPS, Shortcode_Editor::OPS ) ) ),
                            ),
                            'element'       => array(
                                'type'        => 'string',
                                'description' => 'The element\'s address from wp_get_page_layout: an Elementor id such as "a1b2c3d", a Breakdance id such as "102", or a path such as "0.1.2" for the other builders, as read with `version`; or the `ref` of an element added earlier in the call. Not for insert.',
                            ),
                            'list'          => array(
                                'type'        => 'string',
                                'description' => 'Elementor: the name of a list of items (a repeater, from the read\'s `lists`). With `item` on a set op, writes that item\'s fields; the item ops change the list.',
                            ),
                            'item'          => array(
                                'type'        => 'string',
                                'description' => 'Elementor: the id of one item of `list`, from the read\'s `lists`.',
                            ),
                            'element_type'  => array(
                                'type'        => 'string',
                                'description' => 'insert: the new element\'s type, from wp_list_layout_elements (e.g. "heading", "ux_banner", "et_pb_button", "divi/button"), or "container" on Elementor.',
                            ),
                            'before'        => array(
                                'type'        => 'string',
                                'description' => 'Flatsome and Divi, insert, move: the element it goes right before.',
                            ),
                            'after'         => array(
                                'type'        => 'string',
                                'description' => 'add_item, move_item: the item it goes after. insert, move (and duplicate on Elementor): the element (or `ref`) it goes right after; a copy goes right after its original by default.',
                            ),
                            'parent'        => array(
                                'type'        => 'string',
                                'description' => 'insert, move (and duplicate on Elementor): the element (or `ref`) it goes into, with `index`. Flatsome and Divi: "" for the page itself. Elementor: a container or column; `index` alone, without `parent`, places it at the top level.',
                            ),
                            'index'         => array(
                                'type'        => 'integer',
                                'description' => 'add_item, move_item: the item\'s position in the list, from 0. insert, move, duplicate: the position among the parent\'s items once placed, from 0. Default: the end.',
                                'minimum'     => 0,
                            ),
                            'copy_of'       => array(
                                'type'        => 'string',
                                'description' => 'add_item: an item of the list to copy; `fields` then change the copy.',
                            ),
                            'children'      => array(
                                'type'        => 'array',
                                'description' => 'Elementor, insert of a container: elements to insert inside it, each { element_type, fields?, children?, ref? }.',
                                'items'       => array( 'type' => 'object' ),
                            ),
                            'ref'           => array(
                                'type'        => 'string',
                                'description' => 'insert, duplicate: a name for the new element that later ops of this call may use in place of its address. Flatsome and Divi: it starts with $ (e.g. "$promo"), "$promo.0" is its first child, and without one it is "$op" and the op\'s number.',
                            ),
                            'field'         => array(
                                'type'        => 'string',
                                'description' => 'set_text, set_link, set_image: the field to write; default the element\'s main text, link or image ("content" is the HTML a WPBakery, Flatsome or Divi 4 element encloses; a Divi 5 field is a path such as "button.innerContent.text").',
                            ),
                            'value'         => array(
                                'type'        => 'string',
                                'description' => 'set_text, insert: the new text (HTML where the field is HTML).',
                            ),
                            'url'           => array(
                                'type'        => 'string',
                                'description' => 'set_link: the new URL; "" removes the link.',
                            ),
                            'new_tab'       => array(
                                'type'        => 'boolean',
                                'description' => 'set_link: open in a new tab. Omitted keeps the stored choice.',
                            ),
                            'nofollow'      => array(
                                'type'        => 'boolean',
                                'description' => 'set_link: add rel="nofollow". Omitted keeps the stored choice.',
                            ),
                            'attachment_id' => array(
                                'type'        => 'integer',
                                'description' => 'set_image: ID of an image in the media library.',
                            ),
                            'fields'        => array(
                                'type'        => 'object',
                                'description' => 'set_fields, add_item, insert: field name to new value, for fields wp_list_layout_elements lists for the element type, or in `item_fields` for a list item (a select takes one of its options, or a list of them where wp_list_layout_elements marks it `multiple`, a true/false field a boolean, a colour a value such as "#1a2b3c", a code field (an Elementor HTML widget\'s `html`) its HTML or script, saved as sent and only for an account that may post unfiltered HTML; for WPBakery and Flatsome "" removes the field so the builder\'s default applies). On a Flatsome or Divi insert, an image field takes an attachment ID and a link field a URL.',
                            ),
                            'expect'        => array(
                                'type'        => 'string',
                                'description' => 'The element type the element must be (e.g. "heading"); the op is refused otherwise.',
                            ),
                        ),
                        'required'   => array( 'op' ),
                    ),
                ),
                'dry_run' => array(
                    'type'        => 'boolean',
                    'description' => 'Check the ops and return the result without saving. Default false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'post_id', 'version', 'ops' ),
        );
    }

    public function get_approval_policy( array $arguments ) {
        return self::is_dry_run( $arguments ) ? 'never' : 'inherit';
    }

    public function is_read_only_call( array $arguments ) {
        return self::is_dry_run( $arguments );
    }

    public function describe( array $arguments ) {
        $post_id = isset( $arguments['post_id'] ) && is_scalar( $arguments['post_id'] ) ? (string) $arguments['post_id'] : '?';
        $ops     = isset( $arguments['ops'] ) && is_array( $arguments['ops'] ) ? $arguments['ops'] : array();
        $parts   = array();
        foreach ( array_slice( $ops, 0, 5 ) as $op ) {
            if ( is_array( $op ) ) {
                $parts[] = self::describe_op( $op );
            }
        }
        if ( count( $ops ) > 5 ) {
            $parts[] = sprintf( '%d more', count( $ops ) - 5 );
        }
        return sprintf( 'Edit the page builder layout of post #%s: %s', $post_id, array() === $parts ? 'no operations' : implode( ', ', $parts ) );
    }

    private static function describe_op( array $op ): string {
        $name    = isset( $op['op'] ) && is_scalar( $op['op'] ) ? (string) $op['op'] : '?';
        $element = isset( $op['element'] ) && is_scalar( $op['element'] ) ? (string) $op['element'] : '?';
        if ( ! in_array( $name, array( 'insert', 'move' ), true ) ) {
            return sprintf( '%s on %s', $name, $element );
        }
        $where = '';
        foreach ( Shortcode_Structure::POSITIONS as $kind ) {
            if ( isset( $op[ $kind ] ) && is_scalar( $op[ $kind ] ) ) {
                $target = (string) $op[ $kind ];
                $where  = 'parent' === $kind ? 'into ' . ( '' === $target ? 'the page' : $target ) . ( isset( $op['index'] ) && is_scalar( $op['index'] ) ? ' at ' . (string) $op['index'] : '' ) : $kind . ' ' . $target;
                break;
            }
        }
        $what = 'insert' === $name ? ( isset( $op['element_type'] ) && is_scalar( $op['element_type'] ) ? (string) $op['element_type'] : '?' ) : $element;
        return trim( sprintf( '%s %s %s', $name, $what, $where ) );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id', 'version', 'ops' ) );
        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $dry_run = self::is_dry_run( $arguments );

        $post = get_post( $post_id );
        if ( ! is_object( $post ) || empty( $post->ID ) || 'revision' === ( $post->post_type ?? '' ) ) {
            throw new \RuntimeException( sprintf( 'No post with ID %d.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( sprintf( 'You cannot edit post %d.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $ops = $arguments['ops'];
        if ( ! is_array( $ops ) || array() === $ops ) {
            throw new \InvalidArgumentException( '`ops` must be a non-empty array of operations.' );
        }
        $adapter = Builder_Registry::for_post( $post );
        if ( null === $adapter ) {
            $blocks = function_exists( 'has_blocks' ) && has_blocks( $post->post_content );
            throw new \InvalidArgumentException( sprintf( 'Post %d is not built with a supported page builder; edit it with %s.', $post_id, $blocks ? 'wp_update_post_blocks' : 'wp_update_post' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $refusal = $adapter->edit_refusal( $post );
        if ( null !== $refusal ) {
            throw Layout_Adapter::refusal( array( $refusal ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $lock = $dry_run ? '' : Layout_Lock::acquire( $post_id );
        if ( ! $dry_run && '' === $lock ) {
            throw new \RuntimeException( sprintf( 'Another edit of post %d is being saved. Read the layout again with wp_get_page_layout, then redo the edit.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        try {
            if ( ! $dry_run ) {
                $post = self::fresh_post( $post_id, $post );
            }
            $version = is_scalar( $arguments['version'] ) ? trim( (string) $arguments['version'] ) : '';
            $current = $adapter->version_token( $post );
            if ( $version !== $current ) {
                throw new \RuntimeException( sprintf( 'The layout of post %d changed since it was read (version "%s", now "%s"). Read it again with wp_get_page_layout and redo the edit.', $post_id, $version, $current ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $revision = self::latest_revision( $post_id );
            $outcome  = $adapter->edit( $post, array_values( $ops ), $dry_run, '' === $lock ? null : self::fence( $post_id, $lock ) );
        } finally {
            if ( '' !== $lock ) {
                Layout_Lock::release( $post_id, $lock );
            }
        }
        $after = $outcome['saved'] ? get_post( $post_id ) : $post;

        $reply = array(
            'post_id' => $post_id,
            'builder' => $adapter->id(),
            'dry_run' => $dry_run,
            'changed' => $outcome['saved'],
            'saved'   => $outcome['saved'],
            'version' => $adapter->version_token( is_object( $after ) ? $after : $post ),
            'results' => $outcome['results'],
        );
        if ( $dry_run ) {
            $reply['would_change'] = $outcome['changed'];
            $reply['note']         = $outcome['changed']
                ? 'Dry run: nothing was saved. Send the same call without dry_run to apply it.'
                : 'Dry run: the operations leave the layout as it is, so the call would change nothing.';
        } elseif ( ! $outcome['changed'] ) {
            $reply['note'] = 'The operations leave the layout as it was; nothing was saved.';
        }
        $latest = $outcome['saved'] ? self::latest_revision( $post_id ) : 0;
        if ( $latest > 0 && $latest !== $revision ) {
            $reply['revision_id'] = $latest;
        }
        if ( isset( $outcome['parents'] ) ) {
            $reply['parents'] = $outcome['parents'];
        }
        if ( array() !== $outcome['warnings'] ) {
            $reply['warnings'] = $outcome['warnings'];
        }
        return $reply;
    }

    private static function fence( int $post_id, string $lock ): callable {
        return static function () use ( $post_id, $lock ): void {
            if ( ! Layout_Lock::holds( $post_id, $lock ) ) {
                throw new \RuntimeException( sprintf( 'This edit of post %d ran longer than %d seconds and another edit took over, so nothing was saved. Read the layout again with wp_get_page_layout and redo the edit.', $post_id, Layout_Lock::TTL ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        };
    }

    private static function fresh_post( int $post_id, object $fallback ): object {
        wp_cache_delete( $post_id, 'posts' );
        wp_cache_delete( $post_id, 'post_meta' );
        $post = get_post( $post_id );
        return is_object( $post ) ? $post : $fallback;
    }

    private static function latest_revision( int $post_id ): int {
        $revisions = wp_get_post_revisions(
            $post_id,
            array(
                'numberposts' => 1,
                'fields'      => 'ids',
            )
        );
        $first = is_array( $revisions ) ? reset( $revisions ) : false;
        return is_numeric( $first ) ? (int) $first : 0;
    }

    private static function is_dry_run( array $arguments ): bool {
        return isset( $arguments['dry_run'] ) && rest_sanitize_boolean( $arguments['dry_run'] );
    }
}
