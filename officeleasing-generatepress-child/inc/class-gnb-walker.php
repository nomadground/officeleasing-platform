<?php
/**
 * GNB 워커: wp_nav_menu를 .olx-gnb의 마크업(직계 <a> 태그, ul/li 없음)에 맞춰 출력한다.
 * .olx-gnb가 flex 컨테이너라 자식이 <a>여야 디자인이 맞기 때문.
 * 현재 페이지에 해당하는 항목엔 is-active 클래스를 붙인다.
 */
defined( 'ABSPATH' ) || exit;

class Olt_Gnb_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$is_active = in_array( 'current-menu-item', $classes, true )
			|| in_array( 'current-menu-parent', $classes, true )
			|| in_array( 'current-menu-ancestor', $classes, true );

		$output .= sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( $item->url ),
			$is_active ? ' class="is-active"' : '',
			esc_html( $item->title )
		);
	}
}
