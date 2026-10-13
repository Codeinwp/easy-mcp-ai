<?php
namespace Easy_MCP_AI\Tools\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












final class Template_Type {

    const TEMPLATE      = 'template';
    const TEMPLATE_PART = 'template_part';

    






    public static function resolve( array $arguments ) {
        if ( ! isset( $arguments['type'] ) || '' === $arguments['type'] ) {
            return self::TEMPLATE;
        }
        $type = is_string( $arguments['type'] ) ? strtolower( trim( $arguments['type'] ) ) : '';
        if ( ! in_array( $type, array( self::TEMPLATE, self::TEMPLATE_PART ), true ) ) {
            throw new \InvalidArgumentException( 'Invalid type. Use "template" or "template_part".' );
        }
        return $type;
    }

    





    public static function rest_base( $type ) {
        return self::TEMPLATE_PART === $type ? 'template-parts' : 'templates';
    }

    





    public static function label( $type ) {
        return self::TEMPLATE_PART === $type ? 'Template parts' : 'Templates';
    }

    






    public static function area_of( array $item ) {
        return isset( $item['area'] ) && is_string( $item['area'] ) ? $item['area'] : '';
    }
}
