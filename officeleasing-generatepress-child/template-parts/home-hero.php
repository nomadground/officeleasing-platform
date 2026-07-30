<?php
/**
 * Home Hero (재사용 컴포넌트).
 * 대형 배경 이미지 없는 "정보형" Hero - 상세페이지 .olx-head의 타이포 언어(eyebrow / mark 밑줄 / 네이비 CTA)를 계승.
 * 페이지 전체에서 H1은 이 파일의 것 하나뿐이다.
 *
 * ACF/Core에 의존하지 않는다 - 플러그인이 꺼져도 Hero는 정상 노출되어야 한다(Home V1 예외처리 요구사항).
 */
defined( 'ABSPATH' ) || exit;

// 전체 매물 목록 URL은 문자열로 조립하지 않고 WordPress API로 실제 URL을 얻는다.
// Core가 꺼져 building CPT가 없으면 false가 되므로, 그때는 버튼 자체를 렌더하지 않는다(깨진 링크 금지).
$archive_url = get_post_type_archive_link( 'building' );
?>
<section class="olx-home-hero" id="hero" aria-labelledby="home-title">
	<p class="olx-eyebrow">SEOUL OFFICE LEASING</p>
	<h1 id="home-title">서울 사무실 임대,<br><mark>더 명확한 기준</mark>으로 찾습니다.</h1>
	<p class="olx-home-hero-desc">
		OFFICE LEASING은 강남 GBD, 도심 CBD, 여의도 YBD를 중심으로
		서울 주요 업무지구의 빌딩 정보와 사무실 임대 정보를 제공합니다.
	</p>
	<div class="olx-home-hero-cta">
		<?php if ( $archive_url ) : ?>
			<a class="olx-btn olx-btn-online" href="<?php echo esc_url( $archive_url ); ?>">전체 사무실 매물 보기</a>
		<?php endif; ?>
		<a class="olx-btn olx-btn-call" href="#contact">임대 조건 상담</a>
	</div>
</section>
