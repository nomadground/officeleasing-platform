<?php
/**
 * 상담 CTA 섹션 (재사용 컴포넌트).
 *
 * [listing-detail-ux-pass5] "차라리 네모난 직사각형을 하나의 배너로, 각 섹션 클릭하면 이동하게" 요청으로
 * 전면 재구성했다 - 이전엔 좌측에 제목+설명 텍스트, 우측에 버튼 묶음이 분리된 2단 레이아웃이었는데,
 * 이제 제목/체크리스트/지역링크/전화/온라인/인사이트를 전부 같은 격자(grid)의 셀로 통일해서 하나의
 * 배너처럼 보이게 한다. 설명 문구($desc)는 이 배너에 들어갈 자리가 없어 완전히 뺐다(요청: "그냥
 * Office Leasing CheckList 버튼 추가").
 *
 * [listing-detail-ux-pass6 4차, HTML 시안으로 먼저 맞춘 뒤 포팅] 다시 한번 재구성:
 *  - 여백/테두리를 전부 없애고(요청: "위아래왼쪽오른쪽 여백 아예 없이") 배너가 .olx-contact 섹션을
 *    그대로 꽉 채운다 - 그래서 .olx-contact-banner 자체엔 더 이상 배경/테두리/둥근모서리가 없다
 *    (뒤에 있는 .olx-contact의 브랜드 색이 그대로 비친다).
 *  - 좌측 열: 예전엔 제목(h2)과 "OFFICE LEASING CHECKLIST" 버튼이 위/아래로 분리돼 있었는데, 이제
 *    좌측 전체가 체크리스트 페이지로 가는 버튼 하나다(요청: "좌측이... 체크리스트 버튼으로") - 체크리스트
 *    페이지가 아직 공개 안 됐으면(olt_get_public_page_url()이 빈 값) 클릭 불가능한 일반 텍스트로
 *    대체한다(가짜 링크를 만들지 않는다는 이 파일의 기존 원칙과 동일).
 *  - 우측 열: $action_cells 배열/2열 그리드 로직은 그대로 재사용한다 - district_link/region_link를
 *    넘기는 페이지(현재는 single-building.php)는 자연히 [지역 2개 위 / 전화·온라인 아래]인 2열 2행이
 *    되고, 안 넘기는 페이지는 예전처럼 전화·온라인만 있는 1행, 인사이트까지 있으면 홀수라 마지막 셀이
 *    전체 폭으로 펼쳐지는 기존 동작을 그대로 유지한다(요청 그림은 building 상세 페이지의 4셀 케이스를
 *    가리킨 것이라 이 로직 자체를 바꿀 필요가 없었다).
 *
 * [Home 시안 라운드, 이 컴포넌트가 front-page.php 하단에도 재사용되면서 추가 요청] :
 *  - 좌측 eyebrow "Check List" -> "Office Leasing"으로, 제목 끝에 화살표 + "Check List"를 붙이는 걸로
 *    자리를 바꿨다(체크리스트로 실제로 이동 가능할 때만 - 페이지가 없어 클릭 불가능한 정적 상태에서는
 *    갈 곳 없는 "Check List" 안내를 보여주지 않는다). "Check List" 글자는 은은한 pulse 효과로 눈에
 *    띄게 한다(문자 그대로 On/Off 깜빡이는 blink는 접근성상 권장하지 않아 채택하지 않음).
 *  - 우측 셀이 정확히 2개(district_link/region_link를 안 넘기는 호출부 - 지금은 Home)일 때는
 *    바깥 그리드를 1.1:2 대신 1:1로 좁혀서 체크리스트 50% : 나머지 두 칸 각 25%가 되게 한다
 *    (요청: "CheckList 50% + 전화상담 25% + 온라인문의 25%"). 셀이 4개인 building 상세 페이지의
 *    2열 2행 비율(1.1:2)은 그대로 둔다 - .olx-contact-banner.is-compact 수정자로 분리.
 *
 * $args: [
 *   'title' => 상담 제목, 'phone' => 전화번호, 'kakao_url' => 카카오 채널 URL,
 *   'district_link' => [ 'label' => ..., 'url' => ... ] (선택, 세부지역 사무실임대 링크),
 *   'region_link'   => [ 'label' => ..., 'url' => ... ] (선택, 권역 사무실임대 링크),
 * ]
 * district_link/region_link는 single-building.php처럼 호출부가 특정 빌딩의 권역 컨텍스트를 아는
 * 경우에만 넘긴다 - Home/아카이브 등 컨텍스트가 없는 호출부는 안 넘기면 그만이라 이 컴포넌트를
 * 쓰는 다른 페이지에는 영향이 없다(그런 페이지는 우측 그리드가 전화/온라인만으로 시작한다).
 */
defined( 'ABSPATH' ) || exit;

$title = $args['title'] ?? '전문 중개사와 바로 상담하세요';
$phone = $args['phone'] ?? olt_company( 'phone' );
$tel   = olt_tel_href( $phone );

// 온라인 문의 링크 결정 순서: 명시 인자 -> 회사정보의 카카오 채널 -> 실제 존재하는 contact 페이지 ->
// 회사정보의 이메일(mailto:). 넷 다 없으면 동작하지 않는 임시 링크를 만들지 않고 셀 자체를 숨긴다.
$online_url    = $args['kakao_url'] ?? olt_company( 'kakao_url' );
$online_label  = '카카오톡 상담';
$online_sub    = '실시간 상담';
$online_target = true;
if ( ! $online_url ) {
	// olt_get_public_page_url()이 publish 상태의 공개 페이지일 때만 URL을 준다 - draft/private/
	// 비밀번호 보호 상태인 "contact" 페이지가 있어도 조용히 숨겨진다.
	$contact_url = olt_get_public_page_url( 'contact' );
	if ( $contact_url ) {
		$online_url    = $contact_url;
		$online_label  = '온라인상담';
		$online_sub    = '문의 양식';
		$online_target = false;
	}
}
if ( ! $online_url ) {
	// 회사 이메일이 설정돼 있으면(ol_company_info()의 'email') mailto:로라도 마지막 폴백을 준다 -
	// 이 값도 비어있으면 여전히 셀을 숨긴다(가짜 링크를 만들지 않는다는 원칙 유지).
	$email = olt_company( 'email' );
	if ( $email ) {
		$online_url    = 'mailto:' . $email;
		$online_label  = '이메일 문의';
		$online_sub    = $email;
		$online_target = false;
	}
}

// 인사이트(체크리스트/가이드) 페이지도 아직 만들어지지 않았을 수 있다 - slug "insight" 페이지이 실제
// 공개 상태일 때만 셀을 노출한다.
$insight_url = olt_get_public_page_url( 'insight' );
// 체크리스트 페이지도 동일한 패턴 - slug "checklist"가 공개 상태일 때만.
$checklist_url = olt_get_public_page_url( 'checklist' );

$district_link = $args['district_link'] ?? null;
$region_link   = $args['region_link'] ?? null;

// 우측 2열 그리드에 들어갈 셀들을 순서대로 쌓는다: 지역 링크(있으면) -> 전화(항상) -> 온라인(있으면) ->
// 인사이트(있으면). 2개씩 자연스럽게 행을 이루고, 마지막에 홀로 남는 셀은 전체 폭으로 펼친다
// (빌딩정보 표의 "주변 인프라" 홀로 남는 행과 동일한 패턴, officeleasing.css).
$action_cells = array();
if ( $district_link ) {
	$action_cells[] = array(
		'class' => 'district',
		'url'   => $district_link['url'],
		'label' => $district_link['label'],
	);
}
if ( $region_link ) {
	$action_cells[] = array(
		'class' => 'region',
		'url'   => $region_link['url'],
		'label' => $region_link['label'],
	);
}
$action_cells[] = array(
	'class' => 'phone',
	'url'   => 'tel:' . $tel,
	'label' => '전화상담',
	'sub'   => $phone,
);
if ( $online_url ) {
	$action_cells[] = array(
		'class'  => 'online',
		'url'    => $online_url,
		'label'  => $online_label,
		'sub'    => $online_sub,
		'target' => $online_target,
	);
}
if ( $insight_url ) {
	$action_cells[] = array(
		'class' => 'insight',
		'url'   => $insight_url,
		'label' => '인사이트',
		'sub'   => '임대 가이드',
	);
}
$action_count = count( $action_cells );
// 우측이 지역 링크 없이 전화/온라인 2칸뿐일 때만(=Home처럼 district/region 컨텍스트가 없는 호출부)
// 체크리스트:나머지를 1:1로 좁힌다 - building 상세 페이지의 4셀(2열 2행)에는 영향 없음.
$banner_class = 'olx-contact-banner' . ( $action_count <= 2 ? ' is-compact' : '' );
?>
<section class="olx-contact" id="contact" aria-labelledby="contact-title">
	<div class="<?php echo esc_attr( $banner_class ); ?>">
		<?php if ( $checklist_url ) : ?>
			<a class="olx-contact-banner-checklist" href="<?php echo esc_url( $checklist_url ); ?>">
				<span class="olx-contact-banner-eyebrow">Office Leasing</span>
				<h2 id="contact-title"><?php echo esc_html( $title ); ?> <span class="olx-contact-banner-pulse">→ Check List</span></h2>
			</a>
		<?php else : ?>
			<div class="olx-contact-banner-checklist">
				<h2 id="contact-title"><?php echo esc_html( $title ); ?></h2>
			</div>
		<?php endif; ?>
		<div class="olx-contact-banner-right">
			<?php foreach ( $action_cells as $i => $cell ) :
				$is_trailing_odd = ( $i === $action_count - 1 ) && ( 1 === $action_count % 2 );
				$cell_class      = 'olx-contact-banner-cell olx-contact-banner-cell--' . $cell['class'] . ( $is_trailing_odd ? ' olx-contact-banner-cell--full' : '' );
				?>
				<a class="<?php echo esc_attr( $cell_class ); ?>" href="<?php echo esc_url( $cell['url'] ); ?>"<?php echo ! empty( $cell['target'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<b><?php echo esc_html( $cell['label'] ); ?></b>
					<?php if ( ! empty( $cell['sub'] ) ) : ?><small><?php echo esc_html( $cell['sub'] ); ?></small><?php endif; ?>
				</a>
			<?php endforeach; ?>
			<?php
			/**
			 * 추후 Lead(AI 선택형 대화창) 진입점 슬롯.
			 * 별도 플러그인/파일에서 add_action('olt_contact_lead_slot', ...)로 셀을 주입한다.
			 * 지금은 아무것도 렌더하지 않으므로 디자인에 영향 없음. 실제로 주입하게 되면 그 셀에도
			 * olx-contact-banner-cell(+역할별 색상 클래스)을 붙여야 다른 셀과 어긋나지 않는다.
			 */
			do_action( 'olt_contact_lead_slot' );
			?>
		</div>
	</div>
</section>
