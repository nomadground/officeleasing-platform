<?php
/**
 * Why Office Leasing (재사용 컴포넌트).
 * 상세페이지 KEY POINTS와 완전히 동일한 .olx-summary 컴포넌트를 그대로 재사용한다
 * (번호형 3열 - 새 카드 디자인을 만들지 않는다는 확정 원칙).
 *
 * 중개업 등록번호를 여기서 한 번 노출한다: 방문자가 스크롤 초반부에 신뢰 신호를 접하도록 하는 것이
 * 이 프로젝트를 시작한 계기(루트 도메인 신뢰도)와 직접 연결된다. Footer에도 있지만 중복 노출이 의도된 것.
 * 회사 정보는 하드코딩하지 않고 단일 소스(olt_company)를 참조한다.
 */
defined( 'ABSPATH' ) || exit;

$points = array(
	array(
		'title' => '검증된 빌딩과 공실 정보',
		'desc'  => '빌딩 기본 정보와 실제 임대 조건을 구분해 제공합니다.',
	),
	array(
		'title' => '주요 업무지구별 탐색',
		'desc'  => 'GBD·CBD·YBD와 서울 주요 권역의 빌딩을 비교할 수 있습니다.',
	),
	array(
		'title' => '전문 중개사의 조건 확인',
		'desc'  => '공실 여부와 임대 조건은 담당 중개사가 최신 상태로 다시 확인합니다.',
	),
);
?>
<section class="olx-summary olx-home-why" id="why" aria-labelledby="why-title">
	<div>
		<p>WHY OFFICE LEASING</p>
		<h2 id="why-title">서울 오피스 임대를<br>더 쉽게 비교할 수 있도록</h2>
		<small class="olx-home-license">
			<?php echo esc_html( olt_company( 'legal_name' ) ); ?> ·
			중개업 등록번호 <?php echo esc_html( olt_company( 'license_number' ) ); ?>
		</small>
	</div>
	<ul>
		<?php foreach ( $points as $n => $point ) : ?>
			<li>
				<i><?php echo esc_html( str_pad( (string) ( $n + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></i>
				<span><b><?php echo esc_html( $point['title'] ); ?></b><?php echo esc_html( $point['desc'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
