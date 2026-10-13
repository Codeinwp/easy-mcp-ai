<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_User extends Base_Tool {

    public function get_name() {
        return 'wp_delete_user';
    }

    public function get_description() {
        return 'Deletes a WordPress user by ID. On single-site installs, this permanently removes the account. On multisite, WordPress core\'s REST delete endpoint does not support user deletion at all — the request always fails (HTTP 501), regardless of capability. Required: `user_id` AND `reassign` (the user ID to transfer the deleted user\'s posts and links to — MUST be provided; omitting it leaves content orphaned). Deletion is immediate and irreversible (no trash for users). Returns { deleted, previous: { id, name, email } }. Cannot delete the current authenticated user or the last admin.';
    }

    public function get_category() {
        return 'users';
    }

    public function get_required_capability() {
        return 'delete_users';
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
                'user_id'  => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to delete.',
                ),
                'reassign' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to reassign the deleted user\'s posts and links to.',
                ),
            ),
            'required'   => array( 'user_id', 'reassign' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
        $user = $id > 0 ? $this->peek( '/wp/v2/users/' . $id, 'id,name,slug,username', array( 'context' => 'edit' ) ) : null;
        if ( ! $user ) {
            return '';
        }
        $login = isset( $user['username'] ) ? $user['username'] : ( isset( $user['slug'] ) ? $user['slug'] : '' );
        $reassign = isset( $arguments['reassign'] ) ? (int) $arguments['reassign'] : 0;
        return sprintf(
            /* translators: 1: display name, 2: login, 3: user id, 4: id of the user who inherits the content */
            __( "Permanently delete user '%1\$s' (%2\$s, ID %3\$d); their posts are reassigned to user #%4\$d.", 'easy-mcp-ai' ),
            self::rest_title( $user, 'name' ), '' !== $login ? $login : '?', $id, $reassign
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'user_id', 'reassign' ) );

        $user_id  = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        $reassign = $this->parse_required_id( $arguments['reassign'], 'reassign' );

        if ( $user_id === get_current_user_id() ) {
            throw new \RuntimeException( 'Cannot delete the currently authenticated user.' );
        }

        $target = get_userdata( $user_id );
        if ( $target && in_array( 'administrator', (array) $target->roles, true ) ) {
            $admin_count = count(
                get_users( array(
                    'role'   => 'administrator',
                    'fields' => 'ID',
                    'number' => 2,
                ) )
            );
            if ( $admin_count <= 1 ) {
                throw new \RuntimeException( 'Cannot delete the last administrator. Promote another user to administrator first.' );
            }
        }

        $data = $this->rest_request( 'DELETE', '/wp/v2/users/' . $user_id, array(
            'force'    => true,
            'reassign' => $reassign,
        ) );

        return array(
            'deleted'  => $data['deleted'] ?? false,
            'previous' => array(
                'id'    => $data['previous']['id'] ?? null,
                'name'  => $data['previous']['name'] ?? null,
                'email' => $data['previous']['email'] ?? null,
            ),
        );
    }
}
