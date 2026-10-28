<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return array(
    'eval-file' => array( 'route' => 'refuse', 'reason' => 'Runs arbitrary PHP.' ),
);
