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
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/public.css?v=' . HLF_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/print.css?v=' . HLF_VERSION ); ?>" media="print">
</head>
<body class="hlf-public hlf-list">
	<div class="hlf-shell">
		<header class="hlf-header">
			<span class="hlf-brand"><span class="hlf-brand-main">HINT</span><span class="hlf-brand-sub">㈜힌트부동산중개법인</span></span>
			<?php if ( 'archived' === $status ) : ?>
				<span class="hlf-badge hlf-badge--archived">보관된 목록</span>
			<?php elseif ( 'draft' === $status ) : ?>
				<span class="hlf-badge hlf-badge--draft">미발행 미리보기</span>
			<?php endif; ?>
			<span class="hlf-header-actions">
				<button type="button" class="hlf-share-button" data-hlf-share-url="<?php echo esc_attr( $flyer['url'] ); ?>">공유<span class="hlf-share-status" data-hlf-share-status></span></button>
				<button type="button" class="hlf-print-button" data-hlf-print>인쇄</button>
			</span>
		</header>

		<div class="hlf-title-row">
			<p class="hlf-result-count"><?php echo esc_html( count( $items ) ); ?>개 매물</p>
		</div>

		<?php if ( empty( $items ) ) : ?>
			<p class="hlf-empty">등록된 매물이 없습니다.</p>
		<?php else : ?>
			<ul class="hlf-listing-grid">
				<?php foreach ( $items as $i => $item ) :
					$metrics = $item['metrics'];
					$detail_url = HLF_Routes::item_url( $flyer['id'], $item['item_number'] );
					$address_parts = hlf_format_address( $item['road_address'], $item['lot_address'] );
					$address = $address_parts['main'];
					$sub_address = $address_parts['sub'];
					$floor = trim( ( $item['floor_current'] ?: '-' ) . '/' . ( $item['floor_total'] ?: '-' ) . '층' );
					?>
					<li class="hlf-listing-card">
						<a class="hlf-listing-link" href="<?php echo esc_url( $detail_url ); ?>" data-hlf-listing-key="<?php echo esc_attr( $item['item_number'] ); ?>">
							<span class="hlf-listing-main">
								<span class="hlf-listing-idaddr">
									<span class="hlf-item-badge hlf-listing-index" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $i ) ); ?>"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
									<span class="hlf-listing-address-block">
										<span class="hlf-listing-address"><span class="hlf-address-highlight"><?php echo esc_html( $address ); ?></span></span>
										<?php if ( $sub_address ) : ?>
											<span class="hlf-listing-subaddress"><?php echo esc_html( $sub_address ); ?></span>
										<?php endif; ?>
									</span>
								</span>
								<span class="hlf-listing-meta">
									<span class="hlf-listing-floor">
										<span class="hlf-lease-metric-label">층</span>
										<span class="hlf-listing-floor-value"><?php echo esc_html( $floor ); ?></span>
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
										<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span>
										<span class="hlf-lease-metric-sub">평당 <?php echo esc_html( number_format( $metrics['maintenance_per_lease_pyeong'], 1 ) ); ?>만원</span>
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

			<?php if ( ! empty( $chart_items ) ) : ?>
				<section class="hlf-noc-chart-panel" aria-labelledby="hlf-noc-chart-title">
					<div class="hlf-noc-chart-heading">
						<h2 id="hlf-noc-chart-title">매물별 환산임대료 비교</h2>
						<div class="hlf-noc-chart-heading-side">
							<span class="hlf-noc-chart-eyebrow">NOC COMPARISON</span>
							<p class="hlf-noc-chart-unit">단위:만원/전용면적(평)</p>
						</div>
					</div>
					<div
						class="hlf-noc-chart"
						id="hlf-noc-chart"
						role="img"
						aria-label="현재 리스트 매물의 NOC(환산임대료) 비교 차트"
						data-hlf-noc-items="<?php echo esc_attr( wp_json_encode( $chart_items ) ); ?>"
					></div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $map_items ) ) : ?>
				<section class="hlf-comparison-map-panel" aria-labelledby="hlf-comparison-map-title">
					<div class="hlf-comparison-map-heading">
						<h2 id="hlf-comparison-map-title">매물 위치 비교</h2>
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
		<?php endif; ?>

		<?php $contact = HLF_Flyer_Repository::public_contact( $flyer ); ?>
		<footer class="hlf-footer">
			<div class="hlf-footer-row">
				<p class="hlf-footer-copyright">© HINT Co., Ltd. All Rights Reserved. 무단 복제 및 재배포 금지</p>
				<span class="hlf-footer-contact">
					<?php if ( $contact['name'] ) : ?>
						<?php echo esc_html( $contact['name'] ); ?> ·
					<?php endif; ?>
					<a href="<?php echo esc_attr( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a>
				</span>
			</div>
		</footer>
	</div>
	<script src="<?php echo esc_url( HLF_URL . 'assets/js/public-flyer.js?v=' . HLF_VERSION ); ?>" defer></script>
</body>
</html>
<?php
