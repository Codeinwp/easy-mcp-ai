<?php






namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















abstract class Shortcode_Adapter extends Layout_Adapter {

    
    private $save_filter;

    


    public function __construct( ?callable $save_filter = null ) {
        $this->save_filter = $save_filter ?? array( __CLASS__, 'saved_content' );
    }

    










    public static function saved_content( string $content ): string {
        $filtered = apply_filters( 'pre_post_content', wp_slash( $content ) );
        return wp_unslash( (string) apply_filters( 'content_save_pre', $filtered ) );
    }

    





    abstract public function tested_versions(): array;

    










    abstract public function encode_attribute( string $tag, string $name, array $spec, string $value ): string;

    














    abstract public function link_attributes( string $tag, string $field, array $spec, array $decoded, string $url, ?bool $new_tab, ?bool $nofollow, array $fields );

    




    abstract public function field_kinds(): array;

    








    public function encode_content( string $tag, string $value ): string {
        unset( $tag );
        return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $value );
    }

    





    public function element_refusal( string $tag ): ?string {
        if ( in_array( $tag, $this->code_tags(), true ) ) {
            return 'it holds code (HTML or script), which these operations do not write; change it in the builder.';
        }
        return null;
    }

    






    public function element_access_refusal( string $tag ): ?string {
        unset( $tag );
        return null;
    }

    





    public function masked( string $source ): string {
        return $this->mask( $source );
    }

    







    public function link_view( string $tag, string $field, array $decoded ) {
        unset( $tag );
        return $decoded[ $field ] ?? null;
    }

    





    public function layout_items( string $source ): array {
        return Shortcode_Tree::parse( $this->mask( $source ), $this->tags() );
    }

    






    protected function mask( string $source ): string {
        return $source;
    }

    



    public function element_roles( string $tag ): array {
        return $this->roles( $tag );
    }

    




    public function decoded_atts( string $tag, array $atts ): array {
        return $this->decode_atts( $tag, $atts );
    }

    




    public function decoded_content( string $tag, string $content ): string {
        return $this->decode_content( $tag, $content );
    }

    






    public function edit_refusal( object $post ): ?string {
        if ( ! $this->is_present() ) {
            return sprintf( '%s is not active, so this layout can be read but not saved.', $this->label() );
        }
        $version = $this->version();
        $tested  = $this->tested_versions();
        if ( ! in_array( $version, $tested, true ) ) {
            $last  = (string) array_pop( $tested );
            $named = array() === $tested ? 'version ' . $last : 'versions ' . implode( ', ', $tested ) . ' and ' . $last;
            return sprintf( 'Editing %1$s layouts is measured on %2$s; this site runs %3$s, so its layouts can be read but not edited.', $this->label(), $named, '' === $version ? 'an unknown version' : $version );
        }
        return $this->builder_access_refusal( $post );
    }

    




    protected function builder_access_refusal( object $post ): ?string {
        unset( $post );
        return null;
    }

    






    public function edit( object $post, array $ops, bool $dry_run, ?callable $fence = null ): array {
        $post_id = (int) $post->ID;
        $refusal = $this->edit_refusal( $post );
        if ( null !== $refusal ) {
            throw self::refusal( array( $refusal ) );
        }
        $original   = (string) $post->post_content;
        $unfiltered = current_user_can( 'unfiltered_html' );
        $editor     = new Shortcode_Editor( $this, array( __CLASS__, 'library_image' ), $unfiltered );
        $plan       = $editor->plan( $original, $ops );
        if ( array() !== $plan['errors'] ) {
            throw self::refusal( $plan['errors'] );
        }
        $problems = $this->save_problems( $plan['content'] );
        if ( array() !== $problems ) {
            throw self::refusal( $problems );
        }

        $warnings = array();
        $editing  = self::editing_user( $post_id );
        if ( null !== $editing ) {
            $open = sprintf( '%s has post %d open in the editor; their next save there replaces the whole layout, this change included.', $editing, $post_id );
            if ( ! $dry_run ) {
                throw self::refusal( array( $open . ' Try again once they close it.' ) );
            }
            $warnings[] = $open;
        }

        $changed = $plan['content'] !== $original;
        if ( $dry_run || ! $changed ) {
            return array(
                'changed'  => $changed,
                'saved'    => false,
                'results'  => self::public_results( $plan['results'] ),
                'warnings' => $warnings,
            );
        }

        if ( null !== $fence ) {
            $fence();
        }
        
        if ( self::stored_content( $post_id ) !== $original ) {
            throw new \RuntimeException( sprintf( 'The layout of post %d changed while this edit was being prepared, so nothing was saved. Read it again with wp_get_page_layout and redo the edit.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $saved = wp_update_post(
            wp_slash(
                array(
                    'ID'           => $post_id,
                    'post_content' => $plan['content'],
                )
            ),
            true
        );
        if ( is_wp_error( $saved ) || ! $saved ) {
            throw new \RuntimeException( sprintf( 'WordPress did not save post %d: %s', $post_id, is_wp_error( $saved ) ? $saved->get_error_message() : 'wp_update_post() returned 0.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $stored = self::stored_content( $post_id );
        if ( $stored === $original ) {
            throw new \RuntimeException( sprintf( 'WordPress reported post %d saved, but its content did not change: something stopped the write (a plugin filter or a database error).', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'changed'  => true,
            'saved'    => true,
            'results'  => self::public_results( $this->read_back( $plan['results'], $plan['content'], $stored, $warnings ) ),
            'warnings' => $warnings,
        );
    }

    







    private static function fields_of( array $result ): array {
        $named = self::named_fields( $result );
        return isset( $result['written'] ) && is_array( $result['written'] ) ? array_values( array_unique( array_merge( $named, $result['written'] ) ) ) : $named;
    }

    





    private static function named_fields( array $result ): array {
        return 'set_fields' === $result['op'] ? array_map( 'trim', explode( ',', $result['field'] ) ) : array( $result['field'] );
    }

    






    private static function public_results( array $results ): array {
        foreach ( $results as $index => $result ) {
            unset( $results[ $index ]['written'] );
        }
        return $results;
    }

    




    private static function stored_content( int $post_id ): string {
        wp_cache_delete( $post_id, 'posts' );
        $post = get_post( $post_id );
        return is_object( $post ) ? (string) $post->post_content : '';
    }

    











    private function save_problems( string $content ): array {
        if ( Shortcode_Editor::same_text( (string) ( $this->save_filter )( $content ), $content ) ) {
            return array();
        }
        $affected = array();
        $this->filter_losses( $this->layout_items( $content ), $content, '', $affected );
        $where = array() === $affected ? 'the page' : implode( ', ', array_slice( $affected, 0, 10 ) );
        $more = count( $affected ) - 10;
        if ( $more > 0 ) {
            $where .= sprintf( ' and %d more %s', $more, 1 === $more ? 'element' : 'elements' );
        }
        return array( sprintf( 'When this account saves, WordPress\'s save filters also change the rest of the page: this save would change %s too (HTML the account may not post is removed, by WordPress or by the page builder\'s Role Manager). Ask someone whose account may post that HTML to make this edit, or change those elements in the builder first.', $where ) );
    }

    









    private function filter_losses( array $items, string $content, string $prefix, array &$affected ): void {
        foreach ( array_values( $items ) as $index => $item ) {
            $address = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            $parent  = array() !== Shortcode_Editor::shape( $item['children'] );
            $end     = $parent && $item['content_at'] >= 0 ? $item['content_at'] : $item['end'];
            $own     = substr( $content, $item['start'], $end - $item['start'] );
            if ( ! Shortcode_Editor::same_text( (string) ( $this->save_filter )( $own ), $own ) ) {
                $affected[] = sprintf( 'element %s (%s)', $address, $item['tag'] );
            }
            if ( $parent ) {
                $this->filter_losses( $item['children'], $content, $address, $affected );
            }
        }
    }

    










    private function read_back( array $results, string $planned, string $stored, array &$warnings ): array {
        if ( $planned === $stored ) {
            return $results;
        }
        $planned_items = $this->layout_items( $planned );
        $stored_items  = $this->layout_items( $stored );
        
        $last = array();
        foreach ( $results as $index => $result ) {
            foreach ( self::fields_of( $result ) as $field ) {
                $last[ $result['element'] . '|' . $field ] = $index;
            }
        }
        foreach ( $results as $index => $result ) {
            $was   = Shortcode_Editor::find( $planned_items, $result['element'] );
            $now   = Shortcode_Editor::find( $stored_items, $result['element'] );
            $after = 'set_fields' === $result['op'] && is_array( $result['after'] ) ? $result['after'] : array( $result['field'] => $result['after'] );
            foreach ( self::named_fields( $result ) as $field ) {
                if ( $last[ $result['element'] . '|' . $field ] !== $index ) {
                    continue;
                }
                $planned_value = null === $was ? null : Shortcode_Editor::value( $this, $was, $result['op'], $field );
                $stored_value  = null === $now ? null : Shortcode_Editor::value( $this, $now, $result['op'], $field );
                if ( $planned_value !== $stored_value ) {
                    $warnings[] = sprintf( 'WordPress stored a different value for field "%s" of element %s than was sent; `after` shows what it stored.', $field, $result['element'] );
                }
                $after[ $field ] = $stored_value;
            }
            $results[ $index ]['after']   = 'set_fields' === $result['op'] ? $after : reset( $after );
            $results[ $index ]['changed'] = wp_json_encode( $result['before'] ) !== wp_json_encode( $results[ $index ]['after'] );
        }
        if ( array() === $warnings ) {
            $warnings[] = 'WordPress stored the content differently from the plan outside the fields written (a plugin filter changed it on save).';
        }
        return $results;
    }

    




    abstract protected function tags(): array;

    






    abstract protected function decode_atts( string $tag, array $atts ): array;

    







    protected function decode_content( string $tag, string $content ): string {
        unset( $tag );
        return $content;
    }

    







    abstract protected function roles( string $tag ): array;

    





    abstract protected function tag_label( string $tag ): string;

    




    protected function code_tags(): array {
        return array();
    }

    





    public function source( object $post ): string {
        return (string) $post->post_content;
    }

    public function nodes( object $post ): array {
        return $this->build( $this->layout_items( $this->source( $post ) ), '' );
    }

    






    private function build( array $items, string $prefix ): array {
        $nodes = array();
        foreach ( array_values( $items ) as $index => $item ) {
            $address = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            if ( Shortcode_Tree::TEXT === $item['tag'] ) {
                $nodes[] = self::node(
                    array(
                        'address'  => $address,
                        'type'     => Shortcode_Tree::TEXT,
                        'label'    => 'Text',
                        'text'     => self::excerpt( $item['content'] ),
                        'settings' => array( 'content' => $item['content'] ),
                    )
                );
                continue;
            }
            $tag      = (string) $item['tag'];
            $atts     = $this->decode_atts( $tag, $item['atts'] );
            if ( null !== $item['content'] ) {
                $decoded = $this->decode_content( $tag, $item['content'] );
                
                
                if ( $decoded !== $item['content'] ) {
                    $item['content']  = $decoded;
                    $item['children'] = Shortcode_Tree::parse( $decoded, array() );
                }
            }
            $roles    = $this->roles( $tag );
            $children = $this->build( $item['children'], $address );
            
            
            if ( null !== $item['content'] && ! self::has_elements( $item['children'] ) ) {
                $atts['content'] = $item['content'];
            }
            $nodes[]  = self::node(
                array(
                    'address'  => $address,
                    'type'     => $tag,
                    'label'    => $this->tag_label( $tag ),
                    'text'     => $this->role_text( $roles, $atts, $item, $children ),
                    'image'    => isset( $roles['image'] ) ? self::image( $atts[ $roles['image'] ] ?? null ) : null,
                    'link'     => isset( $roles['link'] ) ? self::link( $atts[ $roles['link'] ] ?? null ) : null,
                    'settings' => $atts,
                    'flags'    => in_array( $tag, $this->code_tags(), true ) ? array( 'code' ) : array(),
                    'children' => $children,
                )
            );
        }
        return $nodes;
    }

    





    private static function has_elements( array $items ): bool {
        foreach ( $items as $item ) {
            if ( Shortcode_Tree::TEXT !== $item['tag'] ) {
                return true;
            }
        }
        return false;
    }

    









    private function role_text( array $roles, array $atts, array $item, array $children ): ?string {
        if ( ! isset( $roles['text'] ) ) {
            return null;
        }
        if ( 'content' !== $roles['text'] ) {
            return self::excerpt( $atts[ $roles['text'] ] ?? null );
        }
        if ( array() === $children ) {
            return self::excerpt( $item['content'] );
        }
        $own = array();
        foreach ( $item['children'] as $child ) {
            if ( Shortcode_Tree::TEXT === $child['tag'] ) {
                $own[] = $child['content'];
            }
        }
        return self::excerpt( implode( ' ', $own ) );
    }

    





    private static function link( $value ): ?string {
        if ( is_array( $value ) ) {
            $value = $value['url'] ?? null;
        }
        return is_string( $value ) && '' !== $value ? $value : null;
    }

    





    private static function image( $value ): ?array {
        if ( is_numeric( $value ) && (int) $value > 0 ) {
            return array( 'id' => (int) $value );
        }
        if ( is_string( $value ) && preg_match( '#^(https?:)?//|^/#', $value ) ) {
            return array( 'url' => $value );
        }
        return null;
    }
}
