<?php
/**
 * Home V1 — 플랫폼형 허브 메인.
 *
 * 흐름: Hero(서비스 이해) -> Why(신뢰) -> 권역 4개(권역 선택 -> 세부지역 -> 실제 빌딩 비교 -> 권역 전체보기) -> Contact.
 * Header/Footer/Contact CTA/Building Card는 전부 기존 공통 컴포넌트를 재사용한다(중복 작성 금지).
 *
 * 향후 확장 지점: Hero와 권역 섹션 사이에 OFFICE CHECKLIST / AI OFFICE FINDER를 넣을 수 있도록
 * do_action( 'olt_home_after_hero' ) 훅을 뒀다. 그 기능이 추가돼도 이 파일 구조를 다시 쓸 필요가 없다.
 *
 * 데이터 선정 로직은 이 파일에 두지 않는다 - 플러그인의 ol_get_home_region_buildings()만 호출한다.
 */
defined( 'ABSPATH' ) || exit;

get_header();

get_template_part( 'template-parts/home-hero' );

/**
 * Checklist / AI Office Finder 삽입 슬롯.
 * 별도 코드에서 add_action( 'olt_home_after_hero', ... )로 섹션을 주입한다. 지금은 아무것도 렌더하지 않음.
 */
do_action( 'olt_home_after_hero' );

get_template_part( 'template-parts/home-why' );

/**
 * 권역 섹션.
 * Core(+ACF)가 비활성이면 권역/매물 부분만 안내 문구로 대체한다 - Hero/Why/Contact/Footer는 계속 정상 노출.
 * ol_get_home_region_buildings()는 플러그인 함수이므로 존재 확인 후에만 호출한다.
 */
if ( olt_core_active() && function_exists( 'ol_get_home_region_buildings' ) ) {
	$parents = olt_home_region_parents();

	if ( ! empty( $parents ) ) {
		// 배경 교차(흰색 -> 브랜드 틴트 -> 흰색 -> 웜그레이)로 반복감만 줄인다. 권역별로 다른 디자인을 만들지 않는다.
		$variants = array( '', 'is-tint', '', 'is-warm' );

		foreach ( $parents as $index => $parent_term ) {
			$building_ids = ol_get_home_region_buildings( (int) $parent_term->term_id, 8 );

			get_template_part(
				'template-parts/home-region-section',
				null,
				array(
					'term'         => $parent_term,
					'building_ids' => $building_ids,
					'variant'      => $variants[ $index % count( $variants ) ],
					// 첫 권역의 데스크탑 첫 화면(4장)만 즉시 로드 - 나머지는 lazy
					'eager_count'  => ( 0 === $index ) ? 4 : 0,
				)
			);
		}
	}
} else {
	// olt_render_core_inactive_notice()가 자체적으로 <section>을 출력하므로 여기서 감싸지 않는다.
	olt_render_core_inactive_notice();
}

get_template_part(
	'template-parts/contact-cta',
	null,
	array(
		'title' => '찾는 조건에 맞는 사무실이 보이지 않으신가요?',
		'desc'  => '희망 지역, 전용면적, 예산, 입주시기를 알려주시면 전문 중개사가 적합한 빌딩과 공실을 확인해 드립니다.',
	)
);

get_footer();
