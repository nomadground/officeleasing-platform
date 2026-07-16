<?php
/**
 * 플러그인 "삭제" 시에만 실행(비활성화와 다름). officeleasing-core/uninstall.php와 같은 원칙:
 * 플러그인이 만든 옵션·capability만 정리한다. Flyer/Item 포스트와 메타는 삭제하지 않는다
 * (플러그인을 지웠다고 발행 이력이 사라지면 안 됨). 데이터까지 지우려면 관리자가 별도로 수행.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'hlf_rewrite_version' );
delete_option( 'hlf_caps_version' );

// capability/역할 회수(포스트 데이터는 보존).
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hlf-post-types.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hlf-capabilities.php';
if ( class_exists( 'HLF_Capabilities' ) ) {
	HLF_Capabilities::remove_caps();
}
