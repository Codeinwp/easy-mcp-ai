<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'shell' => array( 'route' => 'refuse', 'reason' => 'Runs arbitrary PHP.' ),
);
