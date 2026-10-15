<?php











if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'cli alias add'      => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli alias delete'   => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli alias get'      => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli alias is-group' => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli alias list'     => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli alias update'   => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli cache clear'    => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli cache prune'    => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli check-update'   => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli cmd-dump'       => array( 'route' => 'catalog', 'method' => 'cli_cmd_dump', 'paged' => true ),
    'cli completions'    => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli has-command'    => array( 'route' => 'catalog', 'method' => 'cli_has_command', 'answer' => true ),
    'cli info'           => array( 'route' => 'catalog', 'method' => 'cli_info' ),
    'cli param-dump'     => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli update'         => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
    'cli version'        => array( 'route' => 'catalog', 'method' => 'cli_version' ),
);
