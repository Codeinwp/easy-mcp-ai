<?php





























if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$emai_user_q19  = 'Change history records term relationships by post ID, so a user term change could later be restored onto the post with the same ID. `user term list` runs.';
$emai_user_show = "it prints the new password, a credential, into this MCP response. Reset the password without it; WordPress emails the user a link unless --skip-email is given";

return array(
    
    'user add-cap'                           => array(
        'route'        => 'own',
        'handler'      => 'User_Handler',
        'method'       => 'add_cap',
        'cap'          => 'promote_users',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'user', 'cap' ),
        'preflight'    => 'preflight_cap',
        'describe'     => 'describe_add_cap',
        'not_captured' => array( 'User capability changes are stored in the user\'s capabilities meta, which change history records only while user meta capture is on.' ),
    ),
    'user remove-cap'                        => array(
        'route'        => 'own',
        'handler'      => 'User_Handler',
        'method'       => 'remove_cap',
        'cap'          => 'promote_users',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'user', 'cap', 'force' ),
        'preflight'    => 'preflight_cap',
        'describe'     => 'describe_remove_cap',
        'not_captured' => array( 'User capability changes are stored in the user\'s capabilities meta, which change history records only while user meta capture is on.' ),
    ),

    
    'user add-role'                          => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'add_role',
        'tools'   => array( 'wp_update_user' ),
        'write'   => true,
        'params'  => array( 'user', 'role' ),
    ),
    'user remove-role'                       => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'remove_role',
        'tools'   => array( 'wp_update_user' ),
        'write'   => true,
        'params'  => array( 'user', 'role' ),
    ),
    'user set-role'                          => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'set_role',
        'tools'   => array( 'wp_update_user' ),
        'write'   => true,
        'params'  => array( 'user', 'role' ),
    ),

    
    'user application-password create'       => array( 'route' => 'refuse', 'reason' => 'it creates a credential that works on the whole REST API, outside this connection\'s allowlist and audit. Create it on the user\'s profile.' ),
    'user application-password delete'       => array(
        'route'        => 'own',
        'handler'      => 'User_Application_Password_Handler',
        'method'       => 'delete',
        'cap'          => 'read',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'user', 'uuid', 'all' ),
        'bulk'         => 50,
        'describe'     => 'describe_delete',
        'not_captured' => array( 'Application passwords live in user meta that change history stores redacted, so a deleted or renamed password cannot be restored from it.' ),
    ),
    'user application-password exists'       => array(
        'route'   => 'own',
        'handler' => 'User_Application_Password_Handler',
        'method'  => 'exists',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'answer'  => true,
        'params'  => array( 'user', 'app-name' ),
    ),
    'user application-password get'          => array(
        'route'   => 'own',
        'handler' => 'User_Application_Password_Handler',
        'method'  => 'get',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'params'  => array( 'user', 'uuid', 'field', 'fields', 'format' ),
    ),
    'user application-password list'         => array(
        'route'   => 'own',
        'handler' => 'User_Application_Password_Handler',
        'method'  => 'list_',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'generic' => true,
        'params'  => array( 'user', 'field', 'fields', 'format', 'orderby', 'order' ),
    ),
    'user application-password record-usage' => array( 'route' => 'refuse', 'reason' => 'it rewrites the last-used record of a credential, which only a real login with that application password should write.' ),
    'user application-password update'       => array(
        'route'        => 'own',
        'handler'      => 'User_Application_Password_Handler',
        'method'       => 'update',
        'cap'          => 'read',
        'network'      => null,
        'write'        => true,
        'generic'      => true,
        'params'       => array( 'user', 'uuid' ),
        'preflight'    => 'preflight_update',
        'describe'     => 'describe_update',
        'not_captured' => array( 'Application passwords live in user meta that change history stores redacted, so a deleted or renamed password cannot be restored from it.' ),
    ),
    'user check-password'                    => array( 'route' => 'refuse', 'reason' => 'it is a password oracle: it tests a password without the login checks and limiters that slow down guessing.' ),

    
    'user create'                            => array(
        'route'          => 'delegate',
        'adapter'        => 'User_Adapter',
        'method'         => 'create',
        'tools'          => array( 'wp_create_user' ),
        'write'          => true,
        'plan'           => 'plan_create',
        'preflight'      => 'preflight_create',
        'params'         => array( 'user-login', 'user-email', 'role', 'user_pass', 'user_registered', 'display_name', 'user_nicename', 'user_url', 'nickname', 'first_name', 'last_name', 'description', 'rich_editing', 'send-email', 'porcelain' ),
    ),
    'user delete'                            => array(
        'route'     => 'delegate',
        'adapter'   => 'User_Adapter',
        'method'    => 'delete',
        'tools'     => array( 'wp_delete_user' ),
        'write'     => true,
        'plan'      => 'plan_delete',
        'preflight' => 'preflight_delete',
        'bulk'      => 50,
        'params'    => array( 'user', 'network', 'reassign', 'yes' ),
    ),
    'user exists'                            => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'exists',
        'tools'   => array( 'wp_get_user' ),
        'write'   => false,
        'answer'  => true,
        'params'  => array( 'id' ),
    ),
    'user generate'                          => array( 'route' => 'refuse', 'reason' => 'Dummy-content generator.' ),
    'user get'                               => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'get',
        'tools'   => array( 'wp_get_user' ),
        'write'   => false,
        'params'  => array( 'user', 'field', 'fields', 'format' ),
    ),
    'user import-csv'                        => array( 'route' => 'refuse', 'reason' => 'Reads a CSV file on the server; reading server files belongs to the privileged file tools.' ),
    'user list'                              => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_list_users' ),
        'write'   => false,
        'generic' => true,
        'params'  => array( 'role', 'network', 'field', 'fields', 'format' ),
    ),
    'user list-caps'                         => array(
        'route'   => 'delegate',
        'adapter' => 'User_Adapter',
        'method'  => 'list_caps',
        'tools'   => array( 'wp_get_user' ),
        'write'   => false,
        'params'  => array( 'user', 'format', 'origin', 'exclude-role-names' ),
    ),

    
    'user meta add'                          => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'add',
        'tools'   => array( 'wp_update_user_meta' ),
        'write'   => true,
        'params'  => array( 'user', 'key', 'value', 'format' ),
    ),
    'user meta delete'                       => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'delete',
        'tools'   => array( 'wp_delete_user_meta' ),
        'write'   => true,
        'params'  => array( 'user', 'key', 'value' ),
    ),
    'user meta get'                          => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'get',
        'tools'   => array( 'wp_get_user_meta' ),
        'write'   => false,
        'params'  => array( 'user', 'key', 'format' ),
    ),
    'user meta list'                         => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'list_',
        'tools'   => array( 'wp_get_user_meta' ),
        'write'   => false,
        'params'  => array( 'user', 'keys', 'fields', 'format', 'orderby', 'order', 'unserialize' ),
    ),
    'user meta patch'                        => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'patch',
        'tools'   => array( 'wp_get_user_meta', 'wp_update_user_meta' ),
        'write'   => true,
        'params'  => array( 'action', 'id', 'key', 'key-path', 'value', 'format' ),
    ),
    'user meta pluck'                        => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'pluck',
        'tools'   => array( 'wp_get_user_meta' ),
        'write'   => false,
        'params'  => array( 'id', 'key', 'key-path', 'format' ),
    ),
    'user meta update'                       => array(
        'route'   => 'delegate',
        'adapter' => 'User_Meta_Adapter',
        'method'  => 'update',
        'tools'   => array( 'wp_get_user_meta', 'wp_update_user_meta' ),
        'write'   => true,
        'params'  => array( 'user', 'key', 'value', 'format' ),
    ),

    
    'user reset-password'                    => array(
        'route'          => 'delegate',
        'adapter'        => 'User_Adapter',
        'method'         => 'reset_password',
        'tools'          => array( 'wp_update_user' ),
        'write'          => true,
        'plan'           => 'plan_reset_password',
        'preflight'      => 'preflight_own_password',
        'bulk'           => 50,
        'params'         => array( 'user', 'skip-email' ),
        'refused_params' => array(
            'show-password' => $emai_user_show,
            'porcelain'     => $emai_user_show,
        ),
    ),

    
    'user session destroy'                   => array(
        'route'        => 'own',
        'handler'      => 'User_Session_Handler',
        'method'       => 'destroy',
        'cap'          => 'read',
        'network'      => null,
        'write'        => true,
        'params'       => array( 'user', 'token', 'all' ),
        'describe'     => 'describe_destroy',
        'not_captured' => array( 'Login sessions are never recorded by change history, so a destroyed session cannot be restored.' ),
    ),
    'user session list'                      => array(
        'route'   => 'own',
        'handler' => 'User_Session_Handler',
        'method'  => 'list_',
        'cap'     => 'read',
        'network' => null,
        'write'   => false,
        'params'  => array( 'user', 'fields', 'format' ),
    ),

    
    'user signup activate'                   => array(
        'route'        => 'own',
        'handler'      => 'User_Signup_Handler',
        'method'       => 'activate',
        'cap'          => 'manage_options',
        'network'      => 'manage_network_users',
        'write'        => true,
        'bulk'         => 50,
        'params'       => array( 'signup' ),
        'describe'     => 'describe_activate',
        'not_captured' => array( 'The signups table is written with direct database queries, which change history records only while database capture is on.' ),
    ),
    'user signup delete'                     => array(
        'route'        => 'own',
        'handler'      => 'User_Signup_Handler',
        'method'       => 'delete',
        'cap'          => 'manage_options',
        'network'      => 'manage_network_users',
        'write'        => true,
        'bulk'         => 50,
        'params'       => array( 'signup', 'all' ),
        'describe'     => 'describe_delete',
        'not_captured' => array( 'The signups table is written with direct database queries, which change history records only while database capture is on.' ),
    ),
    'user signup get'                        => array(
        'route'   => 'own',
        'handler' => 'User_Signup_Handler',
        'method'  => 'get',
        'cap'     => 'manage_options',
        'network' => 'manage_network_users',
        'write'   => false,
        'params'  => array( 'signup', 'field', 'fields', 'format' ),
    ),
    'user signup list'                       => array(
        'route'   => 'own',
        'handler' => 'User_Signup_Handler',
        'method'  => 'list_',
        'cap'     => 'manage_options',
        'network' => 'manage_network_users',
        'write'   => false,
        'generic' => true,
        'params'  => array( 'field', 'fields', 'format', 'per_page' ),
    ),

    
    'user spam'                              => array(
        'route'        => 'own',
        'handler'      => 'User_Handler',
        'method'       => 'spam',
        'cap'          => 'manage_options',
        'network'      => 'manage_network_users',
        'write'        => true,
        'bulk'         => 50,
        'params'       => array( 'user' ),
        'describe'     => 'describe_spam',
        'not_captured' => array( 'The spam flags of the user\'s sites are written to the sites table with direct database queries, which change history records only while database capture is on.' ),
    ),
    'user unspam'                            => array(
        'route'        => 'own',
        'handler'      => 'User_Handler',
        'method'       => 'unspam',
        'cap'          => 'manage_options',
        'network'      => 'manage_network_users',
        'write'        => true,
        'bulk'         => 50,
        'params'       => array( 'user' ),
        'describe'     => 'describe_unspam',
        'not_captured' => array( 'The spam flags of the user\'s sites are written to the sites table with direct database queries, which change history records only while database capture is on.' ),
    ),

    
    'user term add'                          => array( 'route' => 'refuse', 'reason' => $emai_user_q19 ),
    'user term list'                         => array(
        'route'   => 'own',
        'handler' => 'User_Term_Handler',
        'method'  => 'list_',
        'cap'     => 'list_users',
        'network' => null,
        'write'   => false,
        'params'  => array( 'id', 'taxonomy', 'field', 'fields', 'format' ),
    ),
    'user term remove'                       => array( 'route' => 'refuse', 'reason' => $emai_user_q19 ),
    'user term set'                          => array( 'route' => 'refuse', 'reason' => $emai_user_q19 ),

    
    'user update'                            => array(
        'route'     => 'delegate',
        'adapter'   => 'User_Adapter',
        'method'    => 'update',
        'tools'     => array( 'wp_update_user' ),
        'write'     => true,
        'plan'      => 'plan_update',
        'preflight' => 'preflight_own_password',
        'bulk'      => 50,
        'generic'   => true,
        'params'    => array( 'user', 'user_pass', 'user_nicename', 'user_url', 'user_email', 'display_name', 'nickname', 'first_name', 'last_name', 'description', 'rich_editing', 'user_registered', 'role', 'skip-email' ),
    ),
);
