<?php

namespace Easy_MCP_AI\Skills;

use Easy_MCP_AI\Resources\Base_Resource;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Skill_Resource extends Base_Resource {

    private $skill;

    private $file;

    public function __construct( array $skill, array $file ) {
        $this->skill = $skill;
        $this->file  = $file;
    }

    public function get_uri() {
        return Skill_Set::uri( $this->skill, $this->file['path'] );
    }

    public function get_name() {
        return 'SKILL.md' === $this->file['path'] ? $this->skill['name'] : basename( $this->file['path'] );
    }

    public function get_description() {
        if ( 'SKILL.md' === $this->file['path'] ) {
            return Skill_Set::description( $this->skill );
        }
        return 'Part of the "' . $this->skill['name'] . '" skill.';
    }

    public function get_mime_type() {
        switch ( strtolower( (string) pathinfo( $this->file['path'], PATHINFO_EXTENSION ) ) ) {
            case 'md':
                return 'text/markdown';
            case 'json':
                return 'application/json';
            case 'csv':
                return 'text/csv';
            case 'yaml':
            case 'yml':
                return 'application/yaml';
            default:
                return 'text/plain';
        }
    }

    public function read() {
        return $this->file['text'];
    }

    public function skill(): array {
        return $this->skill;
    }
}
