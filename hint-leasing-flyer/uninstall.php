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
delete_option( 'hlf_contact_directory' );

// hlf_flyer_seq_*(HLF_Flyer_Repository::next_daily_sequence())는 날짜별로 하루 하나씩 계속 쌓이는
// 옵션이라 개별 delete_option()으로는 지울 수 없다(이름을 전부 알 수 없음) — 삭제 시점에 와일드카드로
// 한 번에 정리한다. autoload='no'로 저장돼 사이트 동작 중에는 부담이 없었지만(옵션 오토로드 캐시에
// 안 실림), 삭제 후에도 wp_options 행 자체는 영구히 남아 있었다.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'hlf_flyer_seq_%'" );

// capability/역할 회수(포스트 데이터는 보존).
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hlf-post-types.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-hlf-capabilities.php';
if ( class_exists( 'HLF_Capabilities' ) ) {
	HLF_Capabilities::remove_caps();
}
