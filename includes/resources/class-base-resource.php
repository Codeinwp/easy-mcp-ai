<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Base_Resource {

    abstract public function get_uri();
    abstract public function get_name();
    abstract public function get_description();
    abstract public function read();

    public function get_mime_type() {
        return 'application/json';
    }

    public function get_definition() {
        return array(
            'uri'         => $this->get_uri(),
            'name'        => $this->get_name(),
            'description' => $this->get_description(),
            'mimeType'    => $this->get_mime_type(),
        );
    }
}
