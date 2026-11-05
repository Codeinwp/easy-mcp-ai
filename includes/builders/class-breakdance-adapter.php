<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Breakdance_Adapter extends Layout_Adapter {

    const DATA_KEY = '_breakdance_data';

    const TESTED_VERSIONS = array( '3.0.0', '3.0.1' );

    const RESTORABLE_KINDS = array( 'text', 'html', 'select', 'number', 'boolean', 'color', 'link', 'image' );

    const UI_CONTROLS = array( 'alert_box', 'message', 'pro_only_alert_box', 'add_registered_children', 'builder_behavior', 'trigger_action_button' );

    const CONTROL_KINDS = array(
        'text'            => 'html',
        'richtext'        => 'html',
        'url'             => 'link',
        'link'            => 'link',
        'wpmedia'         => 'image',
        'dropdown'        => 'select',
        'button_bar'      => 'select',
        'toggle'          => 'boolean',
        'number'          => 'number',
        'color'           => 'color',
        'unit'            => 'size',
        'icon'            => 'icon',
        'repeater'        => 'list',
        'inline_repeater' => 'list',
        'code'            => 'code',
    );

    private $fields = array();

    public function id(): string {
        return 'breakdance';
    }

    public function label(): string {
        return 'Breakdance';
    }

    public function is_present(): bool {
        return defined( 'BREAKDANCE_MODE' ) && 'breakdance' === constant( 'BREAKDANCE_MODE' )
            && function_exists( '\\Breakdance\\Data\\get_tree' ) && function_exists( '\\Breakdance\\Data\\save_document' );
    }

    public function version(): string {
        return defined( '__BREAKDANCE_VERSION' ) ? (string) constant( '__BREAKDANCE_VERSION' ) : '';
    }

    public function owns( object $post ): bool {
        return null !== $this->stored_tree( (int) $post->ID );
    }

    public function renders_post_content( object $post ): bool {
        unset( $post );
        return false;
    }

    public function layout_meta_keys(): array {
        return array( self::DATA_KEY );
    }

    public function source( object $post ): string {
        $raw = get_post_meta( $post->ID, self::DATA_KEY, true );
        return is_string( $raw ) ? $raw : (string) wp_json_encode( $raw );
    }

    public function nodes( object $post ): array {
        $tree = $this->stored_tree( (int) $post->ID );
        if ( null === $tree ) {
            throw new \RuntimeException( sprintf( 'The Breakdance layout of post %d cannot be read.', (int) $post->ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $this->build( $tree['root']['children'] ?? array() );
    }

    public function element_types(): array {
        $types = array();
        foreach ( $this->element_classes() as $type ) {
            $class = '\\' . $type;
            if ( ! class_exists( $class ) ) {
                continue;
            }
            $entry = array(
                'type'     => $type,
                'label'    => method_exists( $class, 'name' ) ? (string) $class::name() : $type,
                'category' => method_exists( $class, 'category' ) ? (string) $class::category() : '',
            );
            if ( self::runs_code( (array) $this->element_fields( $type ) ) ) {
                $entry['code'] = true;
            }
            $types[] = $entry;
        }
        return $types;
    }

    public function element_fields( string $type ): ?array {
        if ( ! array_key_exists( $type, $this->fields ) ) {
            $class                 = '\\' . ltrim( $type, '\\' );
            $known                 = in_array( ltrim( $type, '\\' ), $this->element_classes(), true ) && class_exists( $class ) && method_exists( $class, 'contentControls' );
            $this->fields[ $type ] = $known ? self::fields_of( (array) $class::contentControls(), 'content' ) : null;
        }
        return $this->fields[ $type ];
    }

    public function edit_refusal( object $post ): ?string {
        if ( ! $this->is_present() ) {
            return 'Breakdance is not active, so this layout can be read but not saved.';
        }
        if ( ! in_array( $this->version(), self::TESTED_VERSIONS, true ) ) {
            return sprintf( 'Editing Breakdance layouts was checked on Breakdance %s; this site runs %s, so this layout can be read but not saved.', implode( ' and ', self::TESTED_VERSIONS ), '' === $this->version() ? 'an unknown version' : $this->version() );
        }
        if ( ! $this->can_edit( $post ) ) {
            return 'Breakdance does not let this account edit this item: its builder access (Breakdance › Settings › User Access) gives this role no access, or the item is a Breakdance template, header, footer, global block or popup, which only Full Interface users may edit.';
        }
        return null;
    }

    public function edit( object $post, array $ops, bool $dry_run, ?callable $fence = null ): array {
        $post_id = (int) $post->ID;
        $refusal = $this->edit_refusal( $post );
        if ( null !== $refusal ) {
            throw self::refusal( array( $refusal ) );
        }
        $original = $this->stored_tree( $post_id );
        if ( null === $original ) {
            throw self::refusal( array( sprintf( 'Post %d has no Breakdance layout to edit.', $post_id ) ) );
        }
        $editor = new Breakdance_Editor( array( $this, 'element_fields' ), array( $this, 'image_value' ), current_user_can( 'unfiltered_html' ), array( $this, 'kses' ) );
        $plan   = $editor->plan( $original, $ops );
        if ( array() !== $plan['errors'] ) {
            throw self::refusal( $plan['errors'] );
        }
        $encoded = wp_json_encode( $plan['tree'] );
        if ( false === $encoded ) {
            throw self::refusal( array( 'The edited layout cannot be stored as JSON (a value is not a finite number or not valid UTF-8), so nothing was saved.' ) );
        }

        $warnings = array();
        $editing  = self::editing_user( $post_id );
        if ( null !== $editing ) {
            $open = sprintf( '%s has post %d open in an editor; their next save there may replace this change.', $editing, $post_id );
            if ( ! $dry_run ) {
                throw self::refusal( array( $open . ' Try again once they close it.' ) );
            }
            $warnings[] = $open;
        }

        $changed = wp_json_encode( $original ) !== $encoded;
        if ( $dry_run || ! $changed ) {
            return array(
                'changed'  => $changed,
                'saved'    => false,
                'results'  => $plan['results'],
                'warnings' => $warnings,
            );
        }

        if ( null !== $fence ) {
            $fence();
        }
        wp_cache_delete( $post_id, 'post_meta' );
        if ( wp_json_encode( $this->stored_tree( $post_id ) ) !== wp_json_encode( $original ) ) {
            throw new \RuntimeException( sprintf( 'The layout of post %d changed while this edit was being prepared, so nothing was saved. Read it again with wp_get_page_layout and redo the edit.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->save_tree( $post_id, $encoded );
        wp_cache_delete( $post_id, 'post_meta' );
        $stored = $this->stored_tree( $post_id );
        if ( null === $stored || wp_json_encode( $stored ) === wp_json_encode( $original ) ) {
            throw new \RuntimeException( sprintf( 'Breakdance did not store the edit of post %d: its layout is unchanged, so something stopped the write of %s (a plugin filter or a database error).', $post_id, self::DATA_KEY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'changed'  => true,
            'saved'    => true,
            'results'  => self::read_back( $plan['results'], $stored, $warnings ),
            'warnings' => $warnings,
        );
    }

    public function restore_refusal( object $post, int $revision_id ): ?string {
        $layout  = $this->stored_tree( $revision_id );
        $current = $this->stored_tree( (int) $post->ID );
        if ( null === $layout || ( null !== $current && self::same( $layout, $current ) ) ) {
            return null;
        }
        $editing = self::editing_user( (int) $post->ID );
        if ( null !== $editing ) {
            return sprintf( '%s has post %d open in an editor; their next save there would replace the restored layout. Try again once they close it.', $editing, (int) $post->ID );
        }
        return $this->edit_refusal( $post ) ?? ( $this->rebuild( $layout, $current )['refusal'] ?? null );
    }

    private function rebuild( array $layout, ?array $current ): array {
        $refuse = static function ( string $why ): array {
            return array( 'refusal' => 'its Breakdance layout ' . $why );
        };
        if ( null === $current ) {
            return $refuse( 'cannot be put back: the post holds no Breakdance layout now.' );
        }
        if ( self::shape( $layout['root']['children'] ?? array() ) !== self::shape( $current['root']['children'] ?? array() ) ) {
            return $refuse( 'adds, removes, moves or retypes elements, which wp_update_page_layout does not do.' );
        }
        $outside = static function ( array $tree ): array {
            unset( $tree['root']['children'] );
            return $tree;
        };
        if ( ! self::same( $outside( $layout ), $outside( $current ) ) ) {
            return $refuse( 'changes settings of the layout itself, outside its elements, which wp_update_page_layout does not write.' );
        }
        $now     = self::elements( $current['root']['children'] ?? array() );
        $planned = $current;
        $ops     = array();
        foreach ( self::elements( $layout['root']['children'] ?? array() ) as $id => $element ) {
            $kept = $now[ $id ] ?? array();
            if ( ! self::same( self::outside_properties( $kept ), self::outside_properties( $element ) ) ) {
                return $refuse( sprintf( 'changes element %s outside its properties, which wp_update_page_layout does not write.', $id ) );
            }
            $properties = self::properties_of_node( $element );
            $changed    = self::differing_paths( self::properties_of_node( $kept ), $properties, array() );
            if ( array() === $changed ) {
                continue;
            }
            $fields = $this->element_fields( isset( $element['data']['type'] ) && is_string( $element['data']['type'] ) ? $element['data']['type'] : '' );
            if ( null === $fields ) {
                return $refuse( sprintf( 'changes element %s, a type Breakdance does not register here.', $id ) );
            }
            if ( self::runs_code( $fields ) ) {
                return $refuse( sprintf( 'changes element %s, which runs code; this tool never writes code.', $id ) );
            }
            $names = array();
            foreach ( $changed as $path ) {
                $name = self::restorable_field( $fields, $path );
                if ( null === $name ) {
                    return $refuse( sprintf( 'changes %s of element %s, which wp_update_page_layout does not write.', implode( '.', $path ), $id ) );
                }
                $names[ $name ] = true;
            }
            $set_fields = array();
            $after      = array();
            foreach ( $fields as $field ) {
                if ( ! isset( $names[ $field['name'] ] ) ) {
                    continue;
                }
                if ( ! self::has_path( $properties, $field['name'] ) ) {
                    return $refuse( sprintf( 'removes %s of element %s, which wp_update_page_layout does not do.', $field['name'], $id ) );
                }
                $value = Breakdance_Editor::value_at( $properties, $field['name'] );
                if ( 'link' === $field['kind'] || 'image' === $field['kind'] ) {
                    $op = self::restore_op( (string) $id, $field, $value );
                    if ( null === $op ) {
                        return $refuse( sprintf( 'holds a %s in element %s (%s) that wp_update_page_layout would not write.', $field['kind'], $id, $field['name'] ) );
                    }
                    $after[] = $op;
                    continue;
                }
                $set_fields[ $field['name'] ] = $value;
            }
            $ops[ $id ] = array_merge(
                array() === $set_fields ? array() : array(
                    array(
                        'op'      => 'set_fields',
                        'element' => (string) $id,
                        'fields'  => $set_fields,
                    ),
                ),
                $after
            );
        }
        $editor = new Breakdance_Editor( array( $this, 'element_fields' ), array( $this, 'image_value' ), current_user_can( 'unfiltered_html' ), array( $this, 'kses' ), false );
        foreach ( $ops as $id => $element_ops ) {
            $plan = $editor->plan( $planned, $element_ops );
            if ( array() !== $plan['errors'] ) {
                return $refuse( sprintf( 'holds a value in element %s that wp_update_page_layout would not write: %s', $id, preg_replace( '/^Operation \d+: /', '', $plan['errors'][0] ) ) );
            }
            $planned = $plan['tree'];
        }
        if ( false === wp_json_encode( $planned ) ) {
            return $refuse( 'holds a value that cannot be stored as JSON (a number too large, or text that is not UTF-8).' );
        }
        if ( ! self::same( $planned, $layout ) ) {
            return $refuse( 'holds values the edits wp_update_page_layout makes would not store as they are' . self::first_difference( $planned, $layout ) . '.' );
        }
        return array( 'tree' => $planned );
    }

    private static function restore_op( string $id, array $field, $value ): ?array {
        if ( 'image' === $field['kind'] ) {
            return is_array( $value ) && isset( $value['id'] ) && is_numeric( $value['id'] ) && (int) $value['id'] > 0 ? array(
                'op'            => 'set_image',
                'element'       => $id,
                'field'         => $field['name'],
                'attachment_id' => (int) $value['id'],
            ) : null;
        }
        if ( 'link' !== $field['control'] ) {
            return is_string( $value ) ? array(
                'op'      => 'set_link',
                'element' => $id,
                'field'   => $field['name'],
                'url'     => $value,
            ) : null;
        }
        if ( ! is_array( $value ) || ! isset( $value['url'] ) || ! is_string( $value['url'] ) ) {
            return null;
        }
        $op = array(
            'op'      => 'set_link',
            'element' => $id,
            'field'   => $field['name'],
            'url'     => $value['url'],
        );
        if ( array_key_exists( 'openInNewTab', $value ) ) {
            $op['new_tab'] = $value['openInNewTab'];
        }
        return $op;
    }

    private static function first_difference( array $planned, array $layout ): string {
        $now = self::elements( $planned['root']['children'] ?? array() );
        foreach ( self::elements( $layout['root']['children'] ?? array() ) as $id => $element ) {
            $paths = self::differing_paths( self::properties_of_node( $now[ $id ] ?? array() ), self::properties_of_node( $element ), array() );
            if ( array() !== $paths ) {
                return sprintf( ' (element %s, %s)', $id, implode( '.', $paths[0] ) );
            }
        }
        return '';
    }

    private static function restorable_field( array $fields, array $path ): ?string {
        foreach ( $fields as $field ) {
            $segments = explode( '.', $field['name'] );
            if ( array_slice( $path, 0, count( $segments ) ) === $segments && in_array( $field['kind'], self::RESTORABLE_KINDS, true ) && empty( $field['responsive'] ) ) {
                return $field['name'];
            }
        }
        return null;
    }

    private static function differing_paths( $kept, $value, array $path, bool $has_kept = true, bool $has_value = true ): array {
        if ( $has_kept && $has_value && ( ! is_array( $kept ) || ! is_array( $value ) ) ) {
            return self::fingerprint( $kept ) === self::fingerprint( $value ) ? array() : array( $path );
        }
        if ( ( ! $has_kept && ! is_array( $value ) ) || ( ! $has_value && ! is_array( $kept ) ) ) {
            return array( $path );
        }
        $kept  = $has_kept ? $kept : array();
        $value = $has_value ? $value : array();
        if ( ( $has_kept xor $has_value ) && array() === $kept + $value ) {
            return array( $path );
        }
        $paths = array();
        foreach ( array_keys( $kept + $value ) as $key ) {
            $paths = array_merge( $paths, self::differing_paths( $kept[ $key ] ?? null, $value[ $key ] ?? null, array_merge( $path, array( (string) $key ) ), $has_kept && array_key_exists( $key, $kept ), $has_value && array_key_exists( $key, $value ) ) );
        }
        return $paths;
    }

    private static function has_path( array $values, string $name ): bool {
        foreach ( explode( '.', $name ) as $key ) {
            if ( ! is_array( $values ) || ! array_key_exists( $key, $values ) ) {
                return false;
            }
            $values = $values[ $key ];
        }
        return true;
    }

    private static function same( $a, $b ): bool {
        return self::fingerprint( $a ) === self::fingerprint( $b );
    }

    private static function fingerprint( $value ): string {
        return serialize( self::marked( $value ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- a comparison key, never stored or read back.
    }

    private static function marked( $value ) {
        if ( is_float( $value ) ) {
            return array( 'float', bin2hex( pack( 'E', $value ) ) );
        }
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $list = array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
        if ( ! $list ) {
            ksort( $value, SORT_STRING );
        }
        return array( $list ? 'list' : 'map', array_map( array( self::class, 'marked' ), $value ) );
    }

    private static function shape( $children, string $parent = '' ): array {
        $shape = array();
        foreach ( is_array( $children ) ? $children : array() as $element ) {
            if ( ! is_array( $element ) || ! isset( $element['id'] ) || ! is_scalar( $element['id'] ) ) {
                $shape[] = $parent . '>?';
                continue;
            }
            $type    = $element['data']['type'] ?? '';
            $shape[] = $parent . '>' . $element['id'] . ':' . ( is_string( $type ) ? $type : '' );
            $shape   = array_merge( $shape, self::shape( $element['children'] ?? array(), (string) $element['id'] ) );
        }
        return $shape;
    }

    private static function outside_properties( array $element ): array {
        $data = isset( $element['data'] ) && is_array( $element['data'] ) ? $element['data'] : array();
        unset( $data['type'], $data['properties'] );
        return array( 'data' => $data ) + array_diff_key( $element, array_flip( array( 'id', 'data', 'children' ) ) );
    }

    private static function properties_of_node( array $element ): array {
        return isset( $element['data']['properties'] ) && is_array( $element['data']['properties'] ) ? $element['data']['properties'] : array();
    }

    private static function elements( $children ): array {
        $found = array();
        foreach ( is_array( $children ) ? $children : array() as $element ) {
            if ( is_array( $element ) && isset( $element['id'] ) && is_scalar( $element['id'] ) ) {
                $found[ $element['id'] ] = $element;
                $found                  += self::elements( $element['children'] ?? array() );
            }
        }
        return $found;
    }

    public function restore_layout( object $post, int $revision_id, ?callable $still_held = null ): ?array {
        $layout = $this->stored_tree( $revision_id );
        if ( null === $layout ) {
            return array(
                'restored' => false,
                'note'     => 'This revision holds no Breakdance layout, so the page keeps showing its current Breakdance layout.',
            );
        }
        $current = $this->stored_tree( (int) $post->ID );
        if ( null !== $current && self::same( $layout, $current ) ) {
            return array(
                'restored' => true,
                'changed'  => false,
            );
        }
        $refusal = $this->edit_refusal( $post );
        $rebuilt = null === $refusal ? $this->rebuild( $layout, $current ) : array( 'refusal' => $refusal );
        if ( ! isset( $rebuilt['tree'] ) ) {
            return array(
                'restored' => false,
                'note'     => 'The Breakdance layout was not put back: ' . ( $rebuilt['refusal'] ?? '' ),
            );
        }
        if ( null !== $still_held && ! $still_held() ) {
            return array(
                'restored' => false,
                'changed'  => false,
                'note'     => sprintf( 'This restore ran longer than %d seconds and another edit took the post over, so its Breakdance layout was not put back; WordPress restored the title, content and excerpt.', Layout_Lock::TTL ),
            );
        }
        wp_cache_delete( (int) $post->ID, 'post_meta' );
        $json = wp_json_encode( $rebuilt['tree'] );
        if ( false === $json || ! self::same( $this->stored_tree( (int) $post->ID ), $current ) ) {
            return array(
                'restored' => false,
                'changed'  => false,
                'note'     => sprintf( 'The Breakdance layout of post %d changed while this restore was being prepared, so it was not put back; WordPress restored the title, content and excerpt. Restore the revision again.', (int) $post->ID ),
            );
        }
        $this->save_tree( (int) $post->ID, $json );
        wp_cache_delete( (int) $post->ID, 'post_meta' );
        $stored = $this->stored_tree( (int) $post->ID );
        if ( ! self::same( $stored, $layout ) ) {
            return array(
                'restored' => false,
                'changed'  => ! self::same( $stored, $current ),
                'note'     => 'Breakdance did not store the revision\'s layout as it is in the revision (a plugin filter or a database error stopped the write), so the page may not show that version.',
            );
        }
        return array(
            'restored' => true,
            'changed'  => true,
        );
    }

    public function vendor_route( object $post, array $nodes ): ?array {
        unset( $post, $nodes );
        $off = array();
        if ( ! function_exists( 'wp_get_abilities' ) || ! isset( ( (array) wp_get_abilities() )['breakdance/edit-post'] ) ) {
            $off[] = 'Breakdance\'s abilities are not registered: they need Breakdance 3.0 or later in Breakdance mode on WordPress 6.9 or later, and a change in Breakdance\'s code or an error while loading them also stops them (an error is written to the PHP error log)';
        }
        $enabled = array_map( 'strval', array_filter( (array) get_option( 'easy_mcp_ai_enabled_abilities', array() ), 'is_scalar' ) );
        if ( ! in_array( 'breakdance/edit-post', $enabled, true ) ) {
            $off[] = 'Breakdance\'s edit-post ability is not switched on in Easy MCP AI › Tools';
        }
        if ( ! current_user_can( \Easy_MCP_AI\Compat\Breakdance_Abilities::CAPABILITY ) ) {
            $off[] = sprintf( 'Breakdance\'s abilities need the %s capability, which this account does not have', \Easy_MCP_AI\Compat\Breakdance_Abilities::CAPABILITY );
        }
        return array(
            'elements'  => 'structure',
            'tool'      => 'wp_ability_breakdance_edit_post',
            'ability'   => 'breakdance/edit-post',
            'available' => array() === $off,
            'switch_on' => $off,
            'note'      => 'Inserting, moving, duplicating and deleting elements, building from HTML, templates and global styles are Breakdance\'s own abilities.',
        );
    }

    public function kses( string $html ): string {
        return wp_kses_post( $html );
    }

    public function image_value( int $attachment_id ): ?array {
        return null === self::library_image( $attachment_id ) ? null : $this->media_value( $attachment_id );
    }

    protected function stored_tree( int $post_id ): ?array {
        if ( ! function_exists( '\\Breakdance\\Data\\get_tree' ) ) {
            return null;
        }
        $tree = \Breakdance\Data\get_tree( $post_id );
        return is_array( $tree ) && isset( $tree['root'] ) && is_array( $tree['root'] ) ? $tree : null;
    }

    protected function save_tree( int $post_id, string $json ): void {
        \Breakdance\Data\save_document( $json, '', '', '', '', '', '', '', '', $post_id );
    }

    protected function element_classes(): array {
        if ( ! function_exists( '\\Breakdance\\Elements\\get_element_classnames' ) ) {
            return array();
        }
        return array_values(
            array_map(
                static function ( $name ): string {
                    return ltrim( (string) $name, '\\' );
                },
                (array) \Breakdance\Elements\get_element_classnames()
            )
        );
    }

    protected function media_value( int $attachment_id ): ?array {
        if ( ! function_exists( '\\Breakdance\\Media\\Metadata\\prepareMedia' ) ) {
            return null;
        }
        $media = \Breakdance\Media\Metadata\prepareMedia( $attachment_id );
        return is_array( $media ) ? $media : null;
    }

    protected function can_edit( object $post ): bool {
        if ( ! function_exists( '\\Breakdance\\Permissions\\hasMinimumPermission' ) || ! function_exists( '\\Breakdance\\Permissions\\isPostTypeAllowed' ) ) {
            return false;
        }
        try {
            return \Breakdance\Permissions\hasMinimumPermission( 'edit' ) && \Breakdance\Permissions\isPostTypeAllowed( (string) $post->post_type );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    private function build( $elements ): array {
        $nodes = array();
        foreach ( is_array( $elements ) ? $elements : array() as $element ) {
            if ( ! is_array( $element ) || ! isset( $element['id'] ) || ! is_scalar( $element['id'] ) ) {
                continue;
            }
            $type       = isset( $element['data']['type'] ) && is_string( $element['data']['type'] ) ? $element['data']['type'] : '';
            $properties = isset( $element['data']['properties'] ) && is_array( $element['data']['properties'] ) ? $element['data']['properties'] : array();
            $fields     = (array) $this->element_fields( $type );
            $class      = '\\' . ltrim( $type, '\\' );
            $nodes[]    = self::node(
                array(
                    'address'  => (string) $element['id'],
                    'type'     => $type,
                    'label'    => '' !== $type && class_exists( $class ) && method_exists( $class, 'name' ) ? (string) $class::name() : $type,
                    'text'     => self::excerpt( self::first( $fields, $properties, array( 'html' ) ) ),
                    'image'    => self::image( self::first( $fields, $properties, array( 'image' ) ) ),
                    'link'     => self::link( self::first( $fields, $properties, array( 'link' ) ) ),
                    'settings' => $properties,
                    'flags'    => self::runs_code( $fields ) ? array( 'code' ) : array(),
                    'children' => $this->build( $element['children'] ?? array() ),
                )
            );
        }
        return $nodes;
    }

    private static function fields_of( array $controls, string $path ): array {
        $fields = array();
        foreach ( $controls as $control ) {
            if ( ! is_array( $control ) || ! isset( $control['slug'] ) || ! is_string( $control['slug'] ) ) {
                continue;
            }
            $options = isset( $control['options'] ) && is_array( $control['options'] ) ? $control['options'] : array();
            $type    = isset( $options['type'] ) && is_string( $options['type'] ) ? $options['type'] : '';
            $name    = $path . '.' . $control['slug'];
            if ( 'section' === $type ) {
                $fields = array_merge( $fields, self::fields_of( isset( $control['children'] ) && is_array( $control['children'] ) ? $control['children'] : array(), $name ) );
                continue;
            }
            if ( in_array( $type, self::UI_CONTROLS, true ) ) {
                continue;
            }
            $field = array(
                'name'    => $name,
                'kind'    => self::CONTROL_KINDS[ $type ] ?? 'other',
                'label'   => isset( $control['label'] ) && is_string( $control['label'] ) ? $control['label'] : '',
                'control' => $type,
            );
            if ( ! empty( $control['enableMediaQueries'] ) ) {
                $field['responsive'] = true;
            }
            $items = isset( $options['items'] ) && is_array( $options['items'] ) ? $options['items'] : array();
            if ( 'select' === $field['kind'] && array() !== $items ) {
                $field['options'] = array_values(
                    array_map(
                        static function ( $item ): string {
                            return is_array( $item ) && isset( $item['value'] ) && is_scalar( $item['value'] ) ? (string) $item['value'] : '';
                        },
                        $items
                    )
                );
            }
            $condition = self::condition( $options['condition'] ?? null );
            if ( array() !== $condition ) {
                $field['condition'] = $condition;
            }
            $fields[] = $field;
        }
        return $fields;
    }

    private static function condition( $condition ): array {
        if ( is_array( $condition ) && isset( $condition['path'] ) ) {
            $condition = array( array( $condition ) );
        }
        $groups = array();
        foreach ( is_array( $condition ) ? $condition : array() as $group ) {
            $clauses = array();
            foreach ( is_array( $group ) ? $group : array() as $clause ) {
                if ( is_array( $clause ) && isset( $clause['path'], $clause['operand'] ) && is_string( $clause['path'] ) && is_string( $clause['operand'] ) ) {
                    $clauses[] = array(
                        'path'    => $clause['path'],
                        'operand' => $clause['operand'],
                        'value'   => $clause['value'] ?? null,
                    );
                }
            }
            if ( array() !== $clauses ) {
                $groups[] = $clauses;
            }
        }
        return $groups;
    }

    private static function first( array $fields, array $properties, array $kinds ) {
        foreach ( $fields as $field ) {
            if ( in_array( $field['kind'], $kinds, true ) ) {
                $value = Breakdance_Editor::value_at( $properties, $field['name'] );
                if ( null !== $value && '' !== $value ) {
                    return $value;
                }
            }
        }
        return null;
    }

    private static function image( $media ): ?array {
        if ( ! is_array( $media ) || empty( $media['url'] ) || ! is_string( $media['url'] ) ) {
            return null;
        }
        $image = array( 'url' => $media['url'] );
        if ( isset( $media['id'] ) && is_numeric( $media['id'] ) && (int) $media['id'] > 0 ) {
            $image = array( 'id' => (int) $media['id'] ) + $image;
        }
        return $image;
    }

    private static function link( $link ): ?string {
        if ( is_array( $link ) ) {
            $link = $link['url'] ?? null;
        }
        return is_string( $link ) && '' !== $link ? $link : null;
    }

    private static function runs_code( array $fields ): bool {
        foreach ( $fields as $field ) {
            if ( 'code' === $field['kind'] ) {
                return true;
            }
        }
        return false;
    }

    private static function read_back( array $results, array $stored, array &$warnings ): array {
        $later = array();
        $found = array();
        for ( $index = count( $results ) - 1; $index >= 0; $index-- ) {
            $result     = $results[ $index ];
            $properties = (array) Breakdance_Editor::properties_of( $stored, $result['element'] );
            $several = 'set_fields' === $result['op'] || false !== strpos( $result['field'], ', ' );
            $planned = $several ? (array) $result['after'] : array( $result['field'] => $result['after'] );
            $after   = $planned;
            foreach ( $planned as $name => $value ) {
                $key = $result['element'] . "\0" . $name;
                if ( isset( $later[ $key ] ) ) {
                    continue;
                }
                $later[ $key ]  = true;
                $after[ $name ] = Breakdance_Editor::value_at( $properties, (string) $name );
                if ( $after[ $name ] !== $value ) {
                    $found[ $index ][] = sprintf( 'Breakdance stored %s of element %s differently from what was sent; `after` shows what it stored.', $name, $result['element'] );
                }
            }
            $results[ $index ]['after'] = $several ? $after : $after[ $result['field'] ];
        }
        ksort( $found );
        foreach ( $found as $messages ) {
            array_push( $warnings, ...$messages );
        }
        return $results;
    }
}
