<?php
/**
 * single-listing.php — 개별 매물 URL은 공개하지 않는다(URL 정책: 빌딩 URL 고정).
 * 워드프레스가 자동 생성하는 매물 단일 URL로 접근하면 연결 빌딩으로 301 리다이렉트한다.
 * 연결 빌딩이 없으면 홈으로 보낸다. (검색엔진 인덱스에 고아 매물 URL이 쌓이는 것을 방지)
 *
 * 참고: 최종 SEO URL(/강남사무실임대/…) 커스텀 rewrite는 플러그인 URL 단계에서 별도 구현.
 * 그 단계에서 이 리다이렉트를 template_redirect 훅으로 옮기면 더 이르게 처리할 수 있다.
 */
defined( 'ABSPATH' ) || exit;

$building_id = (int) get_field( 'related_building', get_queried_object_id() );
$target = $building_id ? get_permalink( $building_id ) : home_url( '/' );

wp_safe_redirect( $target, 301 );
exit;
