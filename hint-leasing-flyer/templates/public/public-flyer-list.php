<?php
/**
 * 공개 Flyer 목록 (Phase 1 골격 + Phase 4: NOC 비교차트/위치 비교 지도 + UX 완성 패치). 인쇄 밀도는
 * Phase 4+ 후속.
 * $hlf_context: ['flyer'=>[], 'status'=>string, 'items'=>[[...]]] — HLF_Routes::render()가 주입.
 *
 * 서버 렌더링 이유(요청서 1-E): SEO/공유 안정성, 공개 데이터 노출 최소화.
 */
defined( 'ABSPATH' ) || exit;

$flyer  = $hlf_context['flyer'];
$status = $hlf_context['status'];
$items  = $hlf_context['items'];

// draft 미리보기 및 archived는 검색엔진 색인 대상에서 제외.
$noindex = ( 'published' !== $status );

// NOC(전용평당 환산임대료) 비교 차트 데이터 — 값은 전부 서버 계산(hlf_calculate_item_metrics) 결과를
// 그대로 쓴다. JS(assets/js/public-flyer.js)는 막대 높이를 그리는 순수 표시 로직만 담당하고 계산을
// 다시 하지 않는다. NOC가 0 이하(면적 미입력 등 계산 불가)인 항목은 차트에서 안전하게 제외한다.
//
// 리스트 순번(위 목록의 %02d)·차트 막대·지도 마커는 전부 같은 item_number를 key로 연결한다
// (data-hlf-listing-key) — 하나에 마우스오버하면 나머지 둘도 함께 강조된다(assets/js/public-flyer.js
// ListingSync). order는 화면 표시 순서(display_order 기준, 위 목록과 동일한 순회)를 그대로 쓴다.
$chart_items = array();
$map_items   = array();
foreach ( $items as $i => $item ) {
	$address = hlf_format_address( $item['road_address'], $item['lot_address'] )['main'];
	$url     = HLF_Routes::item_url( $flyer['id'], $item['item_number'] );

	$noc = $item['metrics']['noc'];
	if ( $noc > 0 ) {
		$chart_items[] = array(
			'key'     => $item['item_number'],
			'order'   => $i,
			'noc'     => round( $noc, 1 ),
			'address' => $address,
			'url'     => $url,
		);
	}

	// 좌표가 없는 매물 때문에 전체 지도가 실패하지 않도록 여기서 미리 걸러낸다(빈 문자열/0 모두 제외).
	if ( $item['latitude'] && $item['longitude'] ) {
		$map_items[] = array(
			'key'     => $item['item_number'],
			'order'   => $i,
			'lat'     => (float) $item['latitude'],
			'lng'     => (float) $item['longitude'],
			'address' => $address,
			'url'     => $url,
		);
	}
}

$kakao_js_key = defined( 'HLF_KAKAO_JS_KEY' ) ? HLF_KAKAO_JS_KEY : '';

// 요청서: 카카오톡 등 SNS에 안내문 링크를 공유하면 미리보기 썸네일이 없어 링크만 덩그러니 갔다 —
// Open Graph 태그가 아예 없었던 게 원인이다. og:image는 이 안내문의 담당자 이름으로 담당자
// 디렉터리(설정 화면에서 등록하는 명함 이미지)를 찾아 있으면 쓴다(HLF_Contact_Directory
// find_image_url_by_name — Flyer/Item의 문의처는 이름/전화만 저장하므로 이름으로 역매칭한다).
// 명함을 등록하지 않은 담당자라면 이미지 태그 자체를 생략한다(크롤러가 다른 걸 추측해서 끌어오는
// 것보다 아예 없는 편이 낫다).
$og_title       = $flyer['title'] . ' · ' . $flyer['flyer_number'];
$og_description = count( $items ) . '개 매물 안내 — ' . get_bloginfo( 'name' );
$og_contact     = HLF_Flyer_Repository::public_contact( $flyer );
$og_image_url   = HLF_Contact_Directory::find_image_url_by_name( $og_contact['name'] );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $flyer['title'] . ' · ' . $flyer['flyer_number'] ); ?></title>
	<?php if ( $noindex ) : ?>
		<meta name="robots" content="noindex,nofollow">
	<?php endif; ?>
	<link rel="canonical" href="<?php echo esc_url( $flyer['url'] ); ?>">
	<meta property="og:type" content="website">
	<meta property="og:url" content="<?php echo esc_url( $flyer['url'] ); ?>">
	<meta property="og:title" content="<?php echo esc_attr( $og_title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $og_description ); ?>">
	<?php if ( $og_image_url ) : ?>
		<meta property="og:image" content="<?php echo esc_url( $og_image_url ); ?>">
	<?php endif; ?>
	<?php if ( $kakao_js_key && ! empty( $map_items ) ) : ?>
		<?php // 카카오 지도 SDK 도메인에 미리 연결(DNS/TLS handshake)해 지도 스크립트가 실제 필요할 때 더 빨리 붙게 한다. ?>
		<link rel="preconnect" href="https://dapi.kakao.com">
		<?php
		/*
		 * 요청서(item 3, 페이지 이동 속도): 이전에는 preconnect만 있어서 브라우저가 이 스크립트의
		 * 존재를 DOMContentLoaded 이후 JS 실행 시점(loadKakaoMapSdk, assets/js/public-flyer.js)에야
		 * 알게 됐다 — preload를 추가하면 HTML 파싱 중 preload scanner가 곧바로 이 스크립트를 미리
		 * 받아오기 시작한다. href는 loadKakaoMapSdk가 실제로 넣는 <script src>와 정확히 같은 URL이어야
		 * 브라우저가 같은 요청으로 인식해 캐시를 재사용한다(다르면 두 번 받아옴).
		 */
		?>
		<link rel="preload" as="script" href="<?php echo esc_url( 'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' . rawurlencode( $kakao_js_key ) . '&autoload=false' ); ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/public.css?v=' . HLF_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/print.css?v=' . HLF_VERSION ); ?>" media="print">
</head>
<body class="hlf-public hlf-list">
	<div class="hlf-shell">
		<header class="hlf-header">
			<h1 class="hlf-brand"><span class="hlf-brand-main">HINT</span><span class="hlf-brand-sub">㈜힌트부동산중개법인</span></h1>
			<?php if ( 'archived' === $status ) : ?>
				<span class="hlf-badge hlf-badge--archived">보관된 목록</span>
			<?php elseif ( 'draft' === $status ) : ?>
				<span class="hlf-badge hlf-badge--draft">미발행 미리보기</span>
			<?php endif; ?>
			<span class="hlf-header-actions">
				<?php
				/*
				 * 요청서: 인쇄에서는 공유/인쇄 버튼이 숨어(print.css) 헤더 우측이 비므로, 화면에서는
				 * 아래 .hlf-title-row에 있는 매물 수 문구를 인쇄에서만 이 자리로 옮겨 헤더-리스트 사이
				 * 불필요한 간격(.hlf-title-row의 margin-top)을 없앤다. 화면에서는 항상 숨어 있다가
				 * (public.css) 인쇄에서만 보인다(print.css).
				 */
				?>
				<span class="hlf-print-result-count"><?php echo esc_html( count( $items ) ); ?>개 매물</span>
				<button type="button" class="hlf-share-button" data-hlf-share-url="<?php echo esc_attr( $flyer['url'] ); ?>">공유<span class="hlf-share-status" data-hlf-share-status></span></button>
				<button type="button" class="hlf-print-button" data-hlf-print>인쇄</button>
			</span>
		</header>

		<?php if ( empty( $items ) ) : ?>
			<p class="hlf-empty">등록된 매물이 없습니다.</p>
		<?php else : ?>
			<ul class="hlf-listing-grid" data-hlf-print-section="list">
				<?php foreach ( $items as $i => $item ) :
					$metrics = $item['metrics'];
					$detail_url = HLF_Routes::item_url( $flyer['id'], $item['item_number'] );
					$address_parts = hlf_format_address( $item['road_address'], $item['lot_address'] );
					$address = $address_parts['main'];
					$sub_address = $address_parts['sub'];
					// 요청서: 층수(예: "2/5층")에서 해당층(분자, floor_current)만 눈에 띄는 색으로 구분한다 —
					// 문자열 하나가 아니라 조각으로 나눠 렌더링(아래 hlf-listing-floor-value)해야 그 부분만
					// 감쌀 수 있다.
					$floor_current = $item['floor_current'] ?: '-';
					$floor_total   = $item['floor_total'] ?: '-';
					// 요청서: 관리비 0원은 "0만원(평당 0.0만원)"이 아니라 "포함"으로 표시만 바꾼다 —
					// NOC 등 계산에는 실제 0 값을 그대로 쓰므로 이 표시 분기는 화면에만 영향을 준다.
					$maintenance_included = (float) $item['maintenance_fee_manwon'] <= 0;
					?>
					<li class="hlf-listing-card">
						<a class="hlf-listing-link" href="<?php echo esc_url( $detail_url ); ?>" data-hlf-listing-key="<?php echo esc_attr( $item['item_number'] ); ?>">
							<span class="hlf-listing-main">
								<span class="hlf-listing-idaddr">
									<span class="hlf-item-badge hlf-listing-index" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $i ) ); ?>"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
									<span class="hlf-listing-address-block">
										<span class="hlf-listing-address"><span class="hlf-address-highlight"><?php echo esc_html( $address ); ?></span></span>
										<?php if ( $sub_address || $item['building_name'] ) : ?>
											<span class="hlf-listing-subaddress-row">
												<?php if ( $sub_address ) : ?>
													<span class="hlf-listing-subaddress"><?php echo esc_html( $sub_address ); ?></span>
												<?php endif; ?>
												<?php if ( $item['building_name'] ) : ?>
													<?php if ( $sub_address ) : ?><span class="hlf-building-name-sep">·</span><?php endif; ?>
													<span class="hlf-building-name"><?php echo esc_html( $item['building_name'] ); ?></span>
												<?php endif; ?>
											</span>
										<?php endif; ?>
									</span>
								</span>
								<span class="hlf-listing-meta">
									<span class="hlf-listing-floor">
										<span class="hlf-lease-metric-label<?php echo $item['building_name'] ? ' hlf-lease-metric-label--has-building' : ''; ?>">층수</span>
										<?php if ( $item['building_name'] ) : ?>
											<span class="hlf-lease-metric-label hlf-listing-floor-building"><?php echo esc_html( $item['building_name'] ); ?></span>
										<?php endif; ?>
										<span class="hlf-listing-floor-value"><span class="hlf-floor-current"><?php echo esc_html( $floor_current ); ?></span>/<?php echo esc_html( $floor_total ); ?>층</span>
									</span>
									<span class="hlf-listing-lease-area">
										<span class="hlf-lease-metric-label">임대면적</span>
										<span class="hlf-listing-area-value"><?php echo esc_html( $item['lease_area_sqm'] ? number_format( (float) $item['lease_area_sqm'], 1 ) . '㎡' : '-' ); ?></span>
										<span class="hlf-listing-area-sub"><?php echo esc_html( number_format( $metrics['lease_pyeong'], 1 ) ); ?>평</span>
									</span>
									<span class="hlf-listing-area">
										<span class="hlf-lease-metric-label">전용면적</span>
										<span class="hlf-listing-area-value"><?php echo esc_html( $item['exclusive_area_sqm'] ? number_format( (float) $item['exclusive_area_sqm'], 1 ) . '㎡' : '-' ); ?></span>
										<span class="hlf-listing-area-sub"><?php echo esc_html( number_format( $metrics['exclusive_pyeong'], 1 ) ); ?>평</span>
									</span>
								</span>
								<span class="hlf-lease-metrics">
									<span class="hlf-lease-metric">
										<span class="hlf-lease-metric-label">보증금</span>
										<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['deposit_manwon'] ) ); ?>만원</span>
										<span class="hlf-lease-metric-sub">평당 <?php echo esc_html( number_format( $metrics['deposit_per_lease_pyeong'], 1 ) ); ?>만원</span>
									</span>
									<span class="hlf-lease-metric">
										<span class="hlf-lease-metric-label">임대료</span>
										<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?>만원</span>
										<span class="hlf-lease-metric-sub">평당 <?php echo esc_html( number_format( $metrics['rent_per_lease_pyeong'], 1 ) ); ?>만원</span>
									</span>
									<span class="hlf-lease-metric">
										<span class="hlf-lease-metric-label">관리비</span>
										<?php if ( $maintenance_included ) : ?>
											<span class="hlf-lease-metric-value">포함</span>
										<?php else : ?>
											<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span>
											<span class="hlf-lease-metric-sub">평당 <?php echo esc_html( number_format( $metrics['maintenance_per_lease_pyeong'], 1 ) ); ?>만원</span>
										<?php endif; ?>
									</span>
									<span class="hlf-lease-metric hlf-lease-metric--noc">
										<span class="hlf-lease-metric-label">환산임대료</span>
										<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( $metrics['noc'], 1 ) ); ?>만원</span>
										<span class="hlf-lease-metric-sub">(NOC)</span>
									</span>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<div data-hlf-print-section="chart-map">
			<?php if ( ! empty( $chart_items ) ) : ?>
				<section class="hlf-noc-chart-panel" aria-labelledby="hlf-noc-chart-title">
					<div class="hlf-noc-chart-heading">
						<div class="hlf-noc-chart-title-row">
							<h2 id="hlf-noc-chart-title">환산임대료 비교</h2>
							<span class="hlf-noc-chart-eyebrow">NOC COMPARISON</span>
						</div>
					</div>
					<div
						class="hlf-noc-chart"
						id="hlf-noc-chart"
						role="img"
						aria-label="현재 리스트 매물의 NOC(환산임대료) 비교 차트"
						data-hlf-noc-items="<?php echo esc_attr( wp_json_encode( $chart_items ) ); ?>"
					></div>
					<p class="hlf-noc-chart-unit">단위:만원/전용면적(평)</p>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $map_items ) ) : ?>
				<section class="hlf-comparison-map-panel" aria-labelledby="hlf-comparison-map-title">
					<div class="hlf-comparison-map-heading">
						<h2 id="hlf-comparison-map-title">위치 확인</h2>
						<span class="hlf-comparison-map-unit">LOCATION REVIEW</span>
					</div>
					<div
						class="hlf-comparison-map"
						id="hlf-comparison-map"
						data-hlf-kakao-key="<?php echo esc_attr( $kakao_js_key ); ?>"
						data-hlf-map-items="<?php echo esc_attr( wp_json_encode( $map_items ) ); ?>"
					>
						<p class="hlf-map-empty">지도를 불러오는 중입니다…</p>
					</div>
					<?php
					// 인쇄물에는 지도 대신 순번-주소 목록을 출력한다(카카오 지도 SDK는 인쇄에서
					// 재현하기 어렵고, 실제로 필요한 정보는 "어디에 있는지" 텍스트로도 충분하다).
					?>
					<div class="hlf-map-print-fallback">
						<?php foreach ( $map_items as $map_item ) : ?>
							<div class="hlf-map-print-fallback-item">
								<span class="hlf-item-badge hlf-map-print-fallback-index" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $map_item['order'] ) ); ?>"><?php echo esc_html( sprintf( '%02d', $map_item['order'] + 1 ) ); ?></span>
								<span><?php echo esc_html( $map_item['address'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php elseif ( ! empty( $items ) ) : ?>
				<p class="hlf-map-unavailable">등록된 매물 중 좌표가 있는 매물이 없어 위치 비교를 표시할 수 없습니다.</p>
			<?php endif; ?>
			</div>

			<?php
			// 요청서 6: 인쇄 3페이지부터 매물마다 한 페이지씩 상세 내용을 끼워 넣는다 — 화면에서는
			// 항상 숨어 있고(public.css .hlf-print-item-detail), 인쇄 버튼을 누르면 뜨는 선택 패널
			// (아래 #hlf-print-panel)에서 체크한 매물만 실제 인쇄에 포함된다.
			foreach ( $items as $i => $item ) :
				include HLF_DIR . 'templates/public/partials/print-item-detail.php';
			endforeach;
			?>
		<?php endif; ?>

		<?php $contact = HLF_Flyer_Repository::public_contact( $flyer ); ?>
		<footer class="hlf-footer">
			<div class="hlf-footer-row">
				<p class="hlf-footer-copyright"><a class="hlf-footer-admin-link" href="<?php echo esc_url( wp_logout_url( HLF_Portal::portal_url() ) ); ?>">© HINT</a> Co., Ltd. All Rights Reserved. 무단 복제 및 재배포 금지</p>
				<span class="hlf-footer-contact">
					<?php if ( $contact['name'] ) : ?>
						<?php echo esc_html( $contact['name'] ); ?> ·
					<?php endif; ?>
					<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a>
				</span>
			</div>
		</footer>
	</div>

	<?php if ( ! empty( $items ) ) : ?>
		<?php
		// 요청서 6: 인쇄 버튼을 누르면 바로 인쇄하지 않고 이 패널이 먼저 뜬다 — 1페이지(목록)/
		// 2페이지(비교 차트·지도)/매물별 상세 페이지 중 어떤 걸 실제로 인쇄할지 직접 체크해
		// 고른다. 전부 기본 체크(지금까지의 "전부 인쇄" 동작과 동일)이고, 취소하면 아무것도
		// 바뀌지 않는다. JS(assets/js/public-flyer.js bindPrintButton)가 체크 상태를 읽어
		// data-hlf-print-section 값이 일치하는 블록에 hlf-print-section-excluded를 토글한 뒤
		// window.print()를 호출한다.
		?>
		<div class="hlf-print-panel" id="hlf-print-panel" hidden>
			<div class="hlf-print-panel-content">
				<h2>인쇄할 페이지 선택</h2>
				<ul class="hlf-print-panel-list">
					<li><label><input type="checkbox" checked data-hlf-print-toggle="list"> 1페이지 — 매물 목록</label></li>
					<?php if ( ! empty( $chart_items ) || ! empty( $map_items ) ) : ?>
						<li><label><input type="checkbox" checked data-hlf-print-toggle="chart-map"> 2페이지 — 환산임대료 비교 차트·위치 비교 지도</label></li>
					<?php endif; ?>
					<?php foreach ( $items as $i => $item ) :
						$print_panel_address = hlf_format_address( $item['road_address'], $item['lot_address'] )['main'];
						$print_panel_page_no = ( ! empty( $chart_items ) || ! empty( $map_items ) ) ? $i + 3 : $i + 2;
						?>
						<li><label><input type="checkbox" checked data-hlf-print-toggle="item-<?php echo esc_attr( $item['item_number'] ); ?>"> <?php echo esc_html( $print_panel_page_no . '페이지 — ' . sprintf( '%02d', $i + 1 ) . ' ' . $print_panel_address . ' 상세' ); ?></label></li>
					<?php endforeach; ?>
				</ul>
				<div class="hlf-print-panel-actions">
					<button type="button" class="button" data-hlf-print-cancel>취소</button>
					<button type="button" class="button button-primary" data-hlf-print-confirm>인쇄</button>
				</div>
			</div>
		</div>
	<?php endif; ?>
	<script src="<?php echo esc_url( HLF_URL . 'assets/js/public-flyer.js?v=' . HLF_VERSION ); ?>" defer></script>
</body>
</html>
<?php
