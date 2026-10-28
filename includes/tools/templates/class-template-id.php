<?php
namespace Easy_MCP_AI\Tools\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Template_Id {

    const FOLDER = '[A-Za-z0-9_-][A-Za-z0-9._-]*';

    public static function validate( $value ) {
        $pattern = '#^' . self::FOLDER . '(?:/' . self::FOLDER . ')?//[A-Za-z0-9_-]+\z#';
        if ( ! is_string( $value ) || 1 !== preg_match( $pattern, $value ) ) {
            throw new \InvalidArgumentException(
                'Invalid template_id format. Pass the ID exactly as wp_list_templates returns it: theme-slug//template-slug. The theme slug may contain letters, numbers, dots, hyphens and underscores, with at most one parent folder (folder/theme-slug); the template slug letters, numbers, hyphens and underscores only.'
            );
        }
        return $value;
    }
}
