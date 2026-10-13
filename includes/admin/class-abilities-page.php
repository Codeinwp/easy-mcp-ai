<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}





class Abilities_Page {

	







	public static function ability_annotations( $ability ) {
		if ( method_exists( $ability, 'get_meta_item' ) ) {
			return (array) $ability->get_meta_item( 'annotations' );
		}
		if ( method_exists( $ability, 'get_annotations' ) ) {
			return (array) $ability->get_annotations();
		}
		return array();
	}

	










	public static function compute_enabled( array $current_enabled, array $rendered, array $checked ) {
		$new = array_diff( $current_enabled, $rendered );
		$new = array_merge( $new, $checked );
		return array_values( array_unique( $new ) );
	}
}
