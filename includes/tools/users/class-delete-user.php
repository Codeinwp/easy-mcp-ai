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
        return 'Deletes a WordPress user by ID. Required: `user_id`, and EITHER `reassign` (the user ID to transfer the deleted user\'s posts and links to) OR `delete_content: true` (delete the user\'s posts and links with the account, as WordPress does when no one inherits them). Omitting both is refused. Single site: the account is removed permanently. Multisite (super admins only, as in WordPress): without `network`, the user is removed from THIS site (their posts here reassigned or deleted, the network account kept); with `network: true` (needs manage_network_users, and `delete_content: true` — WordPress deletes the user\'s posts on every site and cannot reassign them), the account is deleted from the whole network; super admins cannot be deleted that way. Deletion is immediate and irreversible (no trash for users). Returns { deleted, previous: { id, name, email }, scope ("account", "site" or "network"), content ("reassigned" or "deleted"), reassigned_to }. Cannot delete the current authenticated user or the last admin.';
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
                'user_id'        => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to delete.',
                ),
                'reassign'       => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to reassign the deleted user\'s posts and links to. Required unless delete_content is true.',
                ),
                'delete_content' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'When true (and `reassign` is omitted), the user\'s posts and links are deleted with the account instead of being reassigned (REST reassign=false; WP-CLI\'s user delete without --reassign).',
                ),
                'network'        => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Multisite only: delete the account from the whole network, which deletes the user\'s posts and links on every site. Needs a super admin with manage_network_users, and delete_content: true; cannot be combined with reassign.',
                ),
            ),
            'required'   => array( 'user_id' ),
        );
    }

    public function describe( array $arguments ) {
        $id   = isset( $arguments['user_id'] ) ? (int) $arguments['user_id'] : 0;
        $user = $id > 0 ? $this->peek( '/wp/v2/users/' . $id, 'id,name,slug,username', array( 'context' => 'edit' ) ) : null;
        if ( ! $user ) {
            return '';
        }
        return self::deletion_preview( $arguments, self::rest_title( $user, 'name' ), self::preview_login( $user ), $id );
    }

    private static function preview_login( array $user ): string {
        $login = isset( $user['username'] ) ? $user['username'] : ( isset( $user['slug'] ) ? $user['slug'] : '' );
        return '' !== $login ? $login : '?';
    }

    private static function deletion_preview( array $arguments, string $name, string $login, int $id ): string {
        if ( self::flag( $arguments, 'network' ) ) {
            return sprintf(
                /* translators: 1: display name, 2: login, 3: user id */
                __( "Permanently delete user '%1\$s' (%2\$s, ID %3\$d) from the whole network; their posts and links on every site are deleted.", 'easy-mcp-ai' ),
                $name, $login, $id
            );
        }
        if ( self::flag( $arguments, 'delete_content' ) && ! isset( $arguments['reassign'] ) ) {
            return sprintf(
                /* translators: 1: display name, 2: login, 3: user id */
                __( "Permanently delete user '%1\$s' (%2\$s, ID %3\$d); their posts and links are deleted too.", 'easy-mcp-ai' ),
                $name, $login, $id
            );
        }
        $reassign = isset( $arguments['reassign'] ) ? (int) $arguments['reassign'] : 0;
        return sprintf(
            /* translators: 1: display name, 2: login, 3: user id, 4: id of the user who inherits the content */
            __( "Permanently delete user '%1\$s' (%2\$s, ID %3\$d); their posts are reassigned to user #%4\$d.", 'easy-mcp-ai' ),
            $name, $login, $id, $reassign
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'user_id' ) );

        $user_id  = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        $network  = self::flag( $arguments, 'network' );
        $reassign = $this->reassign_argument( $arguments, $network );

        if ( $user_id === get_current_user_id() ) {
            throw new \RuntimeException( 'Cannot delete the currently authenticated user.' );
        }

        if ( $network ) {
            return $this->delete_from_network( $user_id );
        }

        $target = get_userdata( $user_id );
        self::refuse_last_administrator( $target );

        if ( is_multisite() ) {
            return $this->remove_from_this_site( $user_id, $target, $reassign );
        }

        return $this->delete_account( $user_id, $reassign );
    }

    private static function flag( array $arguments, string $name ): bool {
        return ! empty( $arguments[ $name ] ) && rest_sanitize_boolean( $arguments[ $name ] );
    }

    private function reassign_argument( array $arguments, bool $network ): ?int {
        $has_reassign = isset( $arguments['reassign'] ) && '' !== $arguments['reassign'];
        self::refuse_unclear_content_choice( $network, self::flag( $arguments, 'delete_content' ), $has_reassign );
        return $has_reassign ? $this->parse_required_id( $arguments['reassign'], 'reassign' ) : null;
    }

    private static function refuse_unclear_content_choice( bool $network, bool $purge, bool $has_reassign ): void {
        if ( $network && $has_reassign ) {
            throw new \InvalidArgumentException( 'network deletes the user from every site and cannot reassign their content; drop reassign and pass delete_content: true.' );
        }
        if ( $purge && $has_reassign ) {
            throw new \InvalidArgumentException( 'Pass either reassign (someone inherits the posts and links) or delete_content: true (they are deleted), not both.' );
        }
        if ( ! $purge && ! $has_reassign ) {
            throw new \InvalidArgumentException( $network
                ? 'Missing required parameters: delete_content. Deleting a user from the whole network deletes their posts and links on every site; pass delete_content: true to confirm.'
                : 'Missing required parameters: reassign (or pass delete_content: true to delete the user\'s posts and links instead of reassigning them).' );
        }
    }

    private function delete_account( int $user_id, ?int $reassign ): array {
        $data = $this->rest_request( 'DELETE', '/wp/v2/users/' . $user_id, array(
            'force'    => true,
            'reassign' => null === $reassign ? false : $reassign,
        ) );

        return array(
            'deleted'       => $data['deleted'] ?? false,
            'previous'      => array(
                'id'    => $data['previous']['id'] ?? null,
                'name'  => $data['previous']['name'] ?? null,
                'email' => $data['previous']['email'] ?? null,
            ),
            'scope'         => 'account',
            'content'       => null === $reassign ? 'deleted' : 'reassigned',
            'reassigned_to' => $reassign,
        );
    }

    private function remove_from_this_site( int $user_id, $target, ?int $reassign ): array {
        self::refuse_site_removal( $user_id, $target, $reassign );
        $previous = self::previous_of( $target, $user_id );
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if ( ! wp_delete_user( $user_id, $reassign ) ) {
            throw new \RuntimeException( sprintf( 'WordPress did not remove user %d from this site.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'deleted'       => true,
            'previous'      => $previous,
            'scope'         => 'site',
            'content'       => null === $reassign ? 'deleted' : 'reassigned',
            'reassigned_to' => $reassign,
        );
    }

    private static function refuse_site_removal( int $user_id, $target, ?int $reassign ): void {
        if ( ! $target ) {
            throw new \InvalidArgumentException( sprintf( 'User %d does not exist.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
            throw new \InvalidArgumentException( sprintf( 'User %d is not a member of this site, so there is nothing to remove here. Pass network: true to delete the account from the whole network.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'remove_user', $user_id ) || ! current_user_can( 'delete_user', $user_id ) ) {
            throw new \RuntimeException( sprintf( 'You are not allowed to delete user %d from this site.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( null !== $reassign && ( $reassign === $user_id || ! get_userdata( $reassign ) ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid user ID for reassignment: %d is the user being deleted or does not exist.', $reassign ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function delete_from_network( int $user_id ): array {
        $target = self::network_deletion_target( $user_id );
        self::refuse_last_administrator( $target );
        $previous = self::previous_of( $target, $user_id );
        if ( ! function_exists( 'wpmu_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/ms.php';
        }
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        if ( ! wpmu_delete_user( $user_id ) ) {
            throw new \RuntimeException( sprintf( 'WordPress did not delete user %d from the network.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'deleted'       => true,
            'previous'      => $previous,
            'scope'         => 'network',
            'content'       => 'deleted',
            'reassigned_to' => null,
        );
    }

    private static function network_deletion_target( int $user_id ): object {
        if ( ! is_multisite() ) {
            throw new \InvalidArgumentException( 'network applies only to a multisite network; this site is not one.' );
        }
        if ( ! is_super_admin() || ! current_user_can( 'manage_network_users' ) || ! current_user_can( 'delete_user', $user_id ) ) {
            throw new \RuntimeException( 'Deleting a user from the whole network needs a super admin with the manage_network_users capability.' );
        }
        $target = get_userdata( $user_id );
        if ( ! $target ) {
            throw new \InvalidArgumentException( sprintf( 'User %d does not exist.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( self::is_listed_super_admin( $target, $user_id ) ) {
            throw new \RuntimeException( sprintf( 'User %d is a super admin; WordPress does not delete super admins. Revoke their super admin status first.', $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $target;
    }

    private static function is_listed_super_admin( object $target, int $user_id ): bool {
        if ( function_exists( 'get_super_admins' ) ) {
            return in_array( isset( $target->user_login ) ? $target->user_login : '', (array) get_super_admins(), true );
        }
        return is_super_admin( $user_id );
    }

    private static function refuse_last_administrator( $target ): void {
        if ( ! $target || ! in_array( 'administrator', (array) ( isset( $target->roles ) ? $target->roles : array() ), true ) ) {
            return;
        }
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

    private static function previous_of( object $user, int $user_id ): array {
        return array(
            'id'    => $user_id,
            'name'  => isset( $user->display_name ) ? $user->display_name : null,
            'email' => isset( $user->user_email ) ? $user->user_email : null,
        );
    }
}
