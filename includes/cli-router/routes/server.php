<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'server' => array( 'route' => 'refuse', 'reason' => 'Developer tooling with no meaning inside a web request.' ),
);
