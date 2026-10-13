<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Category extends Base_Tool {

    public function get_name() {
        return 'wp_delete_category';
    }

    public function get_description() {
        return 'Permanently deletes a WordPress category. Required: `category_id`. Only posts left with ZERO categories after deletion (i.e. this was their only category) are reassigned — to the category given in `reassign`, or to the default "Uncategorized" category if `reassign` is omitted. Posts that also have other categories simply lose this category assignment; they are NOT reassigned. Child categories are NOT deleted — they are reparented to the deleted category\'s parent (so a deleted top-level category\'s children become top-level, but a deleted sub-category\'s children move up to its parent, not to 0). There is no trash for categories; deletion is irreversible. Use `wp_list_categories` to find the category_id first.';
    }

    public function get_category() {
        return 'taxonomy';
    }

    public function get_required_capability() {
        return 'manage_categories';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'category_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the category to delete.',
                ),
                'reassign'    => array(
                    'type'        => 'integer',
                    'description' => 'Optional: ID of the category to reassign posts to, but ONLY for posts left with zero categories after deletion (i.e. this was their only category). Posts that also have other categories are unaffected. Defaults to "Uncategorized" if omitted.',
                ),
            ),
            'required'   => array( 'category_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'category_id' ) );

        $category_id = $this->parse_required_id( $arguments['category_id'], 'category_id' );

        
        $default_category_id = get_option( 'default_category' );
        if ( (int) $category_id === (int) $default_category_id ) {
            throw new \RuntimeException( 'WordPress does not allow deleting the default category.' );
        }

        
        
        $args = array();
        if ( isset( $arguments['reassign'] ) ) {
            $reassign_id = absint( $arguments['reassign'] );
            if ( $reassign_id > 0 ) {
                $args['default'] = $reassign_id;
            }
        }

        $result = wp_delete_term( $category_id, 'category', $args );

        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( false === $result ) {
            throw new \RuntimeException( 'Category not found or could not be deleted.' );
        }

        return array(
            'deleted' => true,
            'id'      => $category_id,
        );
    }
}
