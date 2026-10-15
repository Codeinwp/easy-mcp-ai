<?php











if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'package browse'    => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'package install'   => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'package list'      => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'package path'      => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'package uninstall' => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'package update'    => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
);
