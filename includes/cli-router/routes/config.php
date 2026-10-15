<?php











if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'config create'        => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config delete'        => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config edit'          => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config get'           => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config has'           => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config is-true'       => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config list'          => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config path'          => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config set'           => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
    'config shuffle-salts' => array( 'route' => 'refuse', 'reason' => 'Edits or prints wp-config.php; config get returns the database password and salts.' ),
);
