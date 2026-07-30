<?php
/**
 * 상담 CTA 섹션 (재사용 컴포넌트).
 * 현재: 전화 상담 + 카카오톡. 추후 Lead(선택형 대화창) 기능은 olt_contact_lead_slot 액션 훅에 붙인다.
 * 구조를 확장 가능하게 두기 위해 하단에 do_action 슬롯을 마련했다.
 *
 * $args: [ 'title' => 상담 제목, 'desc' => 설명, 'phone' => 전화번호, 'kakao_url' => 카카오 채널 URL ]
 */
defined( 'ABSPATH' ) || exit;

$title = $args['title'] ?? '전문 중개사와 바로 상담하세요';
$desc  = $args['desc'] ?? '공실 현황·임대 조건 협의·면적 분할·확장까지 담당 중개사가 직접 확인해 드립니다. 편하신 방법으로 문의해 주세요.';
$phone = $args['phone'] ?? olt_company( 'phone' );
$tel   = olt_tel_href( $phone );
$hours = olt_company( 'hours' );

// 온라인 문의 링크 결정 순서: 명시 인자 -> 회사정보의 카카오 채널 -> 실제 존재하는 contact 페이지.
// 셋 다 없으면 동작하지 않는 임시 링크를 만들지 않고 버튼 자체를 숨긴다.
$online_url    = $args['kakao_url'] ?? olt_company( 'kakao_url' );
$online_label  = '카카오톡으로 문의하기';
$online_note   = '실시간 상담';
$online_target = true;
if ( ! $online_url ) {
	$contact_page = get_page_by_path( 'contact' );
	if ( $contact_page ) {
		$online_url    = get_permalink( $contact_page );
		$online_label  = '온라인 문의';
		$online_note   = '문의 양식';
		$online_target = false;
	}
}
?>
<section class="olx-contact" id="contact" aria-labelledby="contact-title">
	<div>
		<p>CONTACT</p>
		<h2 id="contact-title"><?php echo esc_html( $title ); ?></h2>
		<span><?php echo esc_html( $desc ); ?></span>
	</div>
	<div class="olx-contact-actions">
		<a href="tel:<?php echo esc_attr( $tel ); ?>">
			<b>전화 상담 · <?php echo esc_html( $phone ); ?></b>
			<small><?php echo esc_html( $hours ); ?></small>
		</a>
		<?php if ( $online_url ) : ?>
			<a href="<?php echo esc_url( $online_url ); ?>"<?php echo $online_target ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
				<b><?php echo esc_html( $online_label ); ?></b>
				<small><?php echo esc_html( $online_note ); ?></small>
			</a>
		<?php endif; ?>
		<?php
		/**
		 * 추후 Lead(AI 선택형 대화창) 진입점 슬롯.
		 * 별도 플러그인/파일에서 add_action('olt_contact_lead_slot', ...)로 버튼을 주입한다.
		 * 지금은 아무것도 렌더하지 않으므로 디자인에 영향 없음.
		 */
		do_action( 'olt_contact_lead_slot' );
		?>
	</div>
</section>
