<?php
/**
 * 상담 CTA 섹션 (재사용 컴포넌트).
 * 현재: 전화 상담 + 온라인 문의(또는 카카오톡) + 인사이트. 추후 Lead(선택형 대화창) 기능은
 * olt_contact_lead_slot 액션 훅에 붙인다. 구조를 확장 가능하게 두기 위해 하단에 do_action 슬롯을 마련했다.
 *
 * $args: [
 *   'title' => 상담 제목, 'desc' => 설명, 'phone' => 전화번호, 'kakao_url' => 카카오 채널 URL,
 *   'district_link' => [ 'label' => ..., 'url' => ... ] (선택, 세부지역 사무실임대 링크),
 *   'region_link'   => [ 'label' => ..., 'url' => ... ] (선택, 권역 사무실임대 링크),
 * ]
 * district_link/region_link는 single-building.php처럼 호출부가 특정 빌딩의 권역 컨텍스트를 아는
 * 경우에만 넘긴다 - Home/아카이브 등 컨텍스트가 없는 호출부는 안 넘기면 그만이라 이 컴포넌트를
 * 쓰는 다른 페이지에는 영향이 없다.
 */
defined( 'ABSPATH' ) || exit;

$title = $args['title'] ?? '전문 중개사와 바로 상담하세요';
$desc  = $args['desc'] ?? '공실 현황·임대 조건 협의·면적 분할·확장까지 담당 중개사가 직접 확인해 드립니다. 편하신 방법으로 문의해 주세요.';
$phone = $args['phone'] ?? olt_company( 'phone' );
$tel   = olt_tel_href( $phone );

// 온라인 문의 링크 결정 순서: 명시 인자 -> 회사정보의 카카오 채널 -> 실제 존재하는 contact 페이지 ->
// 회사정보의 이메일(mailto:). 넷 다 없으면 동작하지 않는 임시 링크를 만들지 않고 버튼 자체를 숨긴다.
$online_url    = $args['kakao_url'] ?? olt_company( 'kakao_url' );
$online_label  = '카카오톡으로 문의하기';
$online_note   = '실시간 상담';
$online_target = true;
if ( ! $online_url ) {
	// olt_get_public_page_url()이 publish 상태의 공개 페이지일 때만 URL을 준다 - draft/private/
	// 비밀번호 보호 상태인 "contact" 페이지가 있어도 조용히 숨겨진다(온라인 문의 버튼 자체가 안 뜸).
	$contact_url = olt_get_public_page_url( 'contact' );
	if ( $contact_url ) {
		$online_url    = $contact_url;
		$online_label  = '온라인 문의';
		$online_note   = '문의 양식';
		$online_target = false;
	}
}
if ( ! $online_url ) {
	// [listing-detail-ux-pass4] "전화상담 좌측, 온라인문의 우측 50:50" 요청 - kakao_url도 contact
	// 페이지도 아직 없는 사이트에서는 이 버튼 자체가 계속 숨어 있어 전화 버튼 혼자 100%로 보였다.
	// 회사 이메일이 설정돼 있으면(ol_company_info()의 'email') mailto:로라도 마지막 폴백을 준다 -
	// 이 값도 비어있으면 여전히 버튼을 숨긴다(가짜 링크를 만들지 않는다는 원칙은 유지).
	$email = olt_company( 'email' );
	if ( $email ) {
		$online_url    = 'mailto:' . $email;
		$online_label  = '이메일 문의';
		$online_note   = $email;
		$online_target = false;
	}
}

// 인사이트(체크리스트/가이드) 페이지도 아직 만들어지지 않았을 수 있다 - slug "insight" 페이지가
// 실제 공개 상태일 때만 버튼을 노출한다.
$insight_url = olt_get_public_page_url( 'insight' );
// 체크리스트 페이지도 동일한 패턴 - slug "checklist"가 공개 상태일 때만(single-building.php의
// AT A GLANCE 섹션이 쓰는 것과 같은 헬퍼, 같은 slug).
$checklist_url = olt_get_public_page_url( 'checklist' );

$district_link = $args['district_link'] ?? null;
$region_link   = $args['region_link'] ?? null;
// [listing-detail-ux-pass3] 두 링크가 50:50 가로폭으로 나오도록 - 위 $primary_count/$secondary_count와
// 동일한 "버튼 개수 기반 grid modifier" 패턴을 재사용한다.
$links_count = ( $district_link ? 1 : 0 ) + ( $region_link ? 1 : 0 );

// 버튼을 두 그룹으로 나눈다 - "전화/온라인"(주요 CTA)은 항상 50:50 한 줄로, "인사이트/체크리스트"
// (보조 정보 링크)는 있는 만큼만 그 아래 별도 줄로. 기존엔 이 넷을 한 grid에 다 넣어서 인사이트까지
// 있으면 3등분이 되어 전화/온라인이 33%씩으로 눌렸다 - 이제 전화/온라인은 항상 50:50이 보장된다.
// 그리드 열 수 자체는 기존 --1/--2 modifier(officeleasing.css)를 그대로 재사용한다(둘 다 최대 2개라
// --3은 이제 이 컴포넌트에서는 안 쓰지만, 다른 곳에서 쓸 수 있어 CSS는 그대로 둔다).
$primary_count = 1 + ( $online_url ? 1 : 0 );
$secondary_count = ( $insight_url ? 1 : 0 ) + ( $checklist_url ? 1 : 0 );
?>
<section class="olx-contact" id="contact" aria-labelledby="contact-title">
	<div>
		<h2 id="contact-title"><?php echo esc_html( $title ); ?></h2>
		<span><?php echo esc_html( $desc ); ?></span>
	</div>
	<div>
		<?php if ( $links_count > 0 ) : ?>
			<div class="olx-contact-links olx-contact-links--<?php echo esc_attr( (string) $links_count ); ?>">
				<?php if ( $district_link ) : ?>
					<a class="olx-contact-links-district" href="<?php echo esc_url( $district_link['url'] ); ?>"><?php echo esc_html( $district_link['label'] ); ?></a>
				<?php endif; ?>
				<?php if ( $region_link ) : ?>
					<a class="olx-contact-links-region" href="<?php echo esc_url( $region_link['url'] ); ?>"><?php echo esc_html( $region_link['label'] ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div class="olx-contact-actions olx-contact-actions--<?php echo esc_attr( (string) $primary_count ); ?>">
			<a class="olx-contact-action olx-contact-action--phone" href="tel:<?php echo esc_attr( $tel ); ?>">
				<b>전화 상담 · <?php echo esc_html( $phone ); ?></b>
			</a>
			<?php if ( $online_url ) : ?>
				<a class="olx-contact-action olx-contact-action--online" href="<?php echo esc_url( $online_url ); ?>"<?php echo $online_target ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<b><?php echo esc_html( $online_label ); ?></b>
					<small><?php echo esc_html( $online_note ); ?></small>
				</a>
			<?php endif; ?>
			<?php
			/**
			 * 추후 Lead(AI 선택형 대화창) 진입점 슬롯.
			 * 별도 플러그인/파일에서 add_action('olt_contact_lead_slot', ...)로 버튼을 주입한다.
			 * 지금은 아무것도 렌더하지 않으므로 디자인에 영향 없음. 실제로 버튼을 주입하게 되면
			 * 그 버튼에도 olx-contact-action(+역할별 색상 클래스)을 붙이고 위 $primary_count 계산에
			 * 포함시켜야 grid 열 수와 어긋나지 않는다(전화/온라인과 같은 "주요 CTA" 그룹에 속한다고 판단).
			 */
			do_action( 'olt_contact_lead_slot' );
			?>
		</div>
		<?php if ( $secondary_count > 0 ) : ?>
			<div class="olx-contact-actions olx-contact-actions--<?php echo esc_attr( (string) $secondary_count ); ?>">
				<?php if ( $insight_url ) : ?>
					<a class="olx-contact-action olx-contact-action--insight" href="<?php echo esc_url( $insight_url ); ?>">
						<b>인사이트</b>
						<small>임대 가이드·체크리스트</small>
					</a>
				<?php endif; ?>
				<?php if ( $checklist_url ) : ?>
					<a class="olx-contact-action olx-contact-action--checklist" href="<?php echo esc_url( $checklist_url ); ?>">
						<b>OFFICE LEASING CHECKLIST</b>
						<small>임대 체크리스트 보기</small>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
