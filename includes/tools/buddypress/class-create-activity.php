<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Activity extends Base_Tool {

    public function get_name() {
        return 'wp_bp_create_activity';
    }

    public function get_description() {
        return 'Posts a new activity item to the BuddyPress activity stream. Required: `content`. Optional: `type` (activity type slug — default "activity_update"; other values include new_blog_post, new_blog_comment, friendship_created), `component` (default "activity"), `primary_item_id` (primary object ID, e.g. group ID for group-scoped activity — you MUST also set `component` to "groups". With the default `component` of "activity" the call does not merely lose group scoping, it FAILS: on any site with the Groups component active BuddyPress routes an activity_update carrying primary_item_id through groups_post_update(), which resolves group_id to 0, refuses, and returns HTTP 500 bp_rest_user_cannot_create_activity), `secondary_item_id`, `hidden` (boolean — hides from sitewide stream, shows only in group/member streams). For an activity_update, which BuddyPress saves without either, a `secondary_item_id` or `hidden: true` is refused: it shows a member\'s update sitewide and hides a group update exactly when the group is not public. Returns { id, type, content }. Requires BuddyPress Activity component enabled.';
    }

    public function get_category() {
        return 'buddypress';
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
                'content'           => array(
                    'description' => 'The activity text to post.', 'type' => 'string',
                ),
                'type'              => array(
                    'type'    => 'string',
                    'default' => 'activity_update',
                ),
                'component'         => array(
                    'type'    => 'string',
                    'default' => 'activity',
                ),
                'primary_item_id'   => array(
                    'type'        => 'integer',
                    'description' => 'ID of the primary item being acted on (e.g. group ID). Maps to the BuddyPress REST primary_item_id field.',
                ),
                'secondary_item_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of a secondary item. Refused for an activity_update, which BuddyPress saves without one.',
                ),
                'hidden'            => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to hide the activity from the global sitewide stream. `true` is refused for an activity_update: BuddyPress shows a member\'s update sitewide and hides a group update exactly when the group is not public.',
                ),
            ),
            'required'   => array( 'content' ),
        );
    }

    public function precheck( array $arguments ): void {
        self::refuse_dropped_arguments( $arguments );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'activity' ) ) {
            throw new \RuntimeException( 'The BuddyPress Activity component is not enabled. Go to Settings → BuddyPress → Components and enable Activity Streams.' );
        }

        $this->validate_required( $arguments, array( 'content' ) );
        self::refuse_dropped_arguments( $arguments );

        $secondary = self::secondary_item_id( $arguments );
        $params    = array(
            'content'   => wp_kses_post( $arguments['content'] ),
            'type'      => self::activity_type( $arguments ),
            'component' => sanitize_text_field( $arguments['component'] ?? 'activity' ),
        );

        if ( isset( $arguments['primary_item_id'] ) && absint( $arguments['primary_item_id'] ) > 0 ) {
            $params['primary_item_id'] = absint( $arguments['primary_item_id'] );
        }
        if ( $secondary > 0 ) {
            $params['secondary_item_id'] = $secondary;
        }
        if ( isset( $arguments['hidden'] ) ) {
            $params['hidden'] = (bool) $arguments['hidden'];
        }

        $data = $this->rest_request( 'POST', '/buddypress/v1/activity', $params );

        $item = isset( $data[0] ) ? $data[0] : $data;

        return array(
            'id'      => $item['id'] ?? null,
            'type'    => $item['type'] ?? null,
            'content' => $item['content']['rendered'] ?? $item['content'] ?? null,
        );
    }

    private static function activity_type( array $arguments ): string {
        return sanitize_text_field( $arguments['type'] ?? 'activity_update' );
    }

    private static function secondary_item_id( array $arguments ): int {
        return isset( $arguments['secondary_item_id'] ) ? absint( $arguments['secondary_item_id'] ) : 0;
    }

    private static function refuse_dropped_arguments( array $arguments ): void {
        if ( 'activity_update' !== self::activity_type( $arguments ) ) {
            return;
        }
        $value     = $arguments['hidden'] ?? null;
        $hidden    = null !== $value && ! ( rest_is_boolean( $value ) && ! rest_sanitize_boolean( $value ) );
        $secondary = self::secondary_item_id( $arguments );
        $dropped   = array();
        $details   = array();
        if ( $hidden ) {
            $dropped[] = '`hidden`';
            $details[] = 'BuddyPress decides an update\'s visibility itself: a member\'s update shows sitewide, and a group update is hidden exactly when the group is not public.';
        }
        if ( $secondary > 0 ) {
            $dropped[] = sprintf( '`secondary_item_id` (%d)', $secondary );
            $details[] = 'BuddyPress saves an update, a member\'s or a group\'s, with no secondary item.';
        }
        if ( array() === $dropped ) {
            return;
        }
        throw new Detailed_Tool_Error( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- an MCP error message, never printed as HTML.
            sprintf(
                'Nothing was created: BuddyPress never reads %1$s for an activity_update, the default type. Leave %2$s out.',
                implode( ' or ', $dropped ),
                1 === count( $dropped ) ? 'it' : 'them'
            ),
            implode( ' ', $details )
        );
    }
}
