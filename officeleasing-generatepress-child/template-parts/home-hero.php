<?php
/**
 * Home Hero (재사용 컴포넌트).
 * 대형 배경 이미지 없는 "정보형" Hero - 상세페이지 .olx-head의 타이포 언어(eyebrow / mark 밑줄 / 네이비 CTA)를 계승.
 * 페이지 전체에서 H1은 이 파일의 것 하나뿐이다.
 *
 * ACF/Core에 의존하지 않는다 - 플러그인이 꺼져도 Hero는 정상 노출되어야 한다(Home V1 예외처리 요구사항).
 *
 * [Home 시안 라운드] "타이핑하는 듯한 효과 넣어줘, 합리적인에 강조" 요청으로 h1에 타이핑 인트로를 추가했다.
 * SEO/무자바스크립트 안전을 위해 두 벌을 함께 둔다:
 *  - .olx-vh(스크린리더/크롤러 전용, 화면엔 안 보이지만 항상 존재)에 완성된 문장을 그대로 넣는다.
 *  - #olx-hero-type(화면에 보이는 실제 타이핑 대상)에도 "완성된 최종 모습"을 서버에서 미리 렌더한다 -
 *    JS가 없거나 비활성화된 방문자도 깜빡이는 빈 제목이 아니라 완성 문장을 그대로 본다.
 *    JS가 있으면(home-slider.js initHeroTypeIntro()) 이 내용을 지우고 한 글자씩 다시 그려 넣는다.
 *  - data-full/data-mark로 문구 자체는 여기(PHP)에만 있고 JS는 그 값을 읽기만 한다(하드코딩 금지).
 *  - prefers-reduced-motion인 방문자는 JS가 애니메이션을 걍너뛰고 완성 상태를 그대로 둔다.
 *
 * "맨 위 로고를 타이핑 뒤에 등장시킬까?"라는 질문엔 헤더 로고는 그대로 즉시 노출을 유지하기로 했다 -
 * 로고/헤더는 방문자에게 "지금 이 사이트에 있다"를 바로 알려주는 역할이라, 타이핑이 끝날 때까지
 * 안 보이면 페이지가 덜 로드된 것처럼 보일 위험이 더 크다고 판단했다(header.php는 이 파일과 무관하게
 * 항상 그대로 렌더된다 - 별도 처리 없음).
 */
defined( 'ABSPATH' ) || exit;

// 전체 매물 목록 URL은 문자열로 조립하지 않고 WordPress API로 실제 URL을 얻는다.
// Core가 꺼져 building CPT가 없으면 false가 되므로, 그때는 버튼 자체를 렌더하지 않는다(깨진 링크 금지).
$archive_url = get_post_type_archive_link( 'building' );

$hero_title_plain = '사무실 임대, 가장 합리적인 선택';
$hero_title_mark  = '합리적인';
?>
<section class="olx-home-hero" id="hero" aria-labelledby="home-title">
	<p class="olx-eyebrow">OFFICE LEASING</p>
	<h1 id="home-title">
		<span class="olx-vh"><?php echo esc_html( $hero_title_plain ); ?></span>
		<span class="olx-hero-type" id="olx-hero-type" aria-hidden="true"
			data-full="<?php echo esc_attr( $hero_title_plain ); ?>"
			data-mark="<?php echo esc_attr( $hero_title_mark ); ?>"
		>사무실 임대, 가장 <span class="hl is-done"><?php echo esc_html( $hero_title_mark ); ?></span> 선택</span><span class="olx-hero-caret" id="olx-hero-caret" aria-hidden="true"></span>
	</h1>
	<p class="olx-home-hero-desc">
		강남권역<span class="olx-code olx-code-gbd">GBD</span>, 도심권역<span class="olx-code olx-code-cbd">CBD</span>, 여의도권역<span class="olx-code olx-code-ybd">YBD</span> 등
		서울 주요 업무권역 빌딩의 사무실 임대 정보를 제공합니다.
	</p>
	<div class="olx-home-hero-cta">
		<?php if ( $archive_url ) : ?>
			<a class="olx-btn olx-btn-online" href="<?php echo esc_url( $archive_url ); ?>">전체 매물 리스트</a>
		<?php endif; ?>
		<a class="olx-btn olx-btn-call" href="#contact">빠른문의</a>
	</div>
</section>
