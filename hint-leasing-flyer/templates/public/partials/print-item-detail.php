<?php
/**
 * 리스트 인쇄물에 끼워 넣는 매물 상세 1개 페이지(요청서 6: 인쇄 3~N페이지). public-flyer-list.php가
 * 매물마다 한 번씩 include한다 — 화면에서는 항상 숨겨져 있고(public.css .hlf-print-item-detail),
 * 인쇄 시 선택된 항목만 보인다(print.css + JS 토글, assets/js/public-flyer.js bindPrintButton).
 *
 * 상세 페이지(public-flyer-detail.php)와 내용은 같지만 이 파일이 그 템플릿을 include하지는
 * 않는다 — 그쪽은 라이트박스/공유·인쇄 버튼 등 화면 전용 상호작용이 얽혀 있어 그대로 재사용하면
 * 오히려 위험하고, 여기서 필요한 건 인쇄에 실제로 쓰이는 정적 마크업(대표 사진+지도+지표+기본정보
 * +문의처)뿐이다.
 *
 * 필요한 입력 변수(호출부 public-flyer-list.php가 이미 갖고 있는 값 그대로 넘긴다):
 *   $flyer (array), $item (array, HLF_Item_Repository::to_array 결과), $i (0-based 표시 순서),
 *   $kakao_js_key (string)
 */
defined( 'ABSPATH' ) || exit;

$print_metrics = $item['metrics'];
$print_address_parts = hlf_format_address( $item['road_address'], $item['lot_address'] );
$print_address = $print_address_parts['main'];

$print_photo_ids = array();
if ( ! empty( $item['exterior_image_id'] ) ) {
	$print_photo_ids[] = (int) $item['exterior_image_id'];
}
foreach ( $item['interior_image_ids'] as $print_image_id ) {
	$print_photo_ids[] = (int) $print_image_id;
}
$print_photo_ids = array_values( array_unique( array_filter( $print_photo_ids ) ) );

$print_has_coords = $item['latitude'] && $item['longitude'];
$print_map_items  = $print_has_coords ? array( array(
	'key'     => $item['item_number'],
	'order'   => 0,
	'lat'     => (float) $item['latitude'],
	'lng'     => (float) $item['longitude'],
	'address' => $print_address,
	'url'     => '',
) ) : array();

$print_basic = array();
if ( $item['floor_current'] || $item['floor_total'] ) {
	$print_basic['해당층'] = array( 'value' => trim( ( $item['floor_current'] ?: '-' ) . ' / ' . ( $item['floor_total'] ?: '-' ) . '층' ) );
}
if ( $item['available_date_text'] ) {
	$print_basic['입주가능일'] = array( 'value' => $item['available_date_text'] );
}
if ( $item['lease_area_sqm'] ) {
	$print_basic['임대면적'] = array(
		'main' => number_format( (float) $item['lease_area_sqm'], 1 ) . '㎡',
		'sub'  => number_format( $print_metrics['lease_pyeong'], 1 ) . '평',
	);
}
if ( $item['exclusive_area_sqm'] ) {
	$print_basic['전용면적'] = array(
		'main' => number_format( (float) $item['exclusive_area_sqm'], 1 ) . '㎡',
		'sub'  => number_format( $print_metrics['exclusive_pyeong'], 1 ) . '평',
	);
}
if ( $item['building_use'] ) {
	$print_basic['건축물용도'] = array( 'value' => $item['building_use'] );
}
if ( $item['approval_date'] ) {
	$print_basic['사용승인일'] = array( 'value' => $item['approval_date'] );
}
if ( $item['direction'] ) {
	$print_basic['방향(주된출입구)'] = array( 'value' => $item['direction'] );
}
$print_basic['엘리베이터'] = array( 'value' => $item['elevator_available'] ? '있음' : '없음' );
$print_basic['주차']       = array( 'value' => $item['parking_available'] ? ( $item['total_parking'] ?: '가능' ) : '불가' );

$print_basic_wide_labels = array( '건축물용도' );
$print_contact = HLF_Flyer_Repository::public_contact( $flyer, $item );
?>
<div class="hlf-print-item-detail" data-hlf-print-section="item-<?php echo esc_attr( $item['item_number'] ); ?>">
	<div class="hlf-header hlf-print-item-header">
		<span class="hlf-item-badge hlf-detail-badge" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $i ) ); ?>"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
		<span class="hlf-header-address">
			<span class="hlf-header-address-main"><?php echo esc_html( $print_address ); ?></span>
			<?php if ( $print_address_parts['sub'] ) : ?>
				<span class="hlf-header-address-sub"><?php echo esc_html( $print_address_parts['sub'] ); ?></span>
			<?php endif; ?>
			<?php if ( $item['building_name'] ) : ?>
				<span class="hlf-building-name"><?php echo esc_html( $item['building_name'] ); ?></span>
			<?php endif; ?>
		</span>
	</div>

	<div class="hlf-detail-hero<?php echo empty( $print_photo_ids ) ? ' hlf-detail-hero--map-only' : ''; ?>">
		<?php if ( ! empty( $print_photo_ids ) ) : ?>
			<section class="hlf-gallery">
				<div class="hlf-gallery-main">
					<?php echo wp_get_attachment_image( $print_photo_ids[0], 'hlf-item-photo', false, array( 'alt' => esc_attr( $print_address ), 'loading' => 'lazy' ) ); ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $print_has_coords ) : ?>
			<section class="hlf-detail-map-panel" aria-labelledby="hlf-print-map-title-<?php echo esc_attr( $item['item_number'] ); ?>">
				<div class="hlf-comparison-map-heading">
					<h2 id="hlf-print-map-title-<?php echo esc_attr( $item['item_number'] ); ?>">위치</h2>
				</div>
				<?php
				// 인쇄에서 이 항목이 선택됐을 때만 지도를 실제로 그린다(data-hlf-lazy-map) — 매물이
				// 많은 안내문에서 인쇄 버튼을 누르기도 전에 카카오 지도를 매물 수만큼 미리 만들어두면
				// 이번에 고친 성능 문제(REST 워터폴 등)와 같은 종류의 낭비가 된다.
				?>
				<div
					class="hlf-comparison-map hlf-detail-map"
					data-hlf-kakao-key="<?php echo esc_attr( $kakao_js_key ); ?>"
					data-hlf-map-items="<?php echo esc_attr( wp_json_encode( $print_map_items ) ); ?>"
					data-hlf-lazy-map="1"
				>
					<p class="hlf-map-empty">지도를 불러오는 중입니다…</p>
				</div>
				<div class="hlf-map-print-fallback">
					<div class="hlf-map-print-fallback-item">
						<span class="hlf-item-badge hlf-map-print-fallback-index" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $i ) ); ?>"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
						<span><?php echo esc_html( $print_address ); ?></span>
					</div>
				</div>
			</section>
		<?php else : ?>
			<p class="hlf-map-unavailable">이 매물은 아직 좌표가 등록되지 않아 위치 지도를 표시할 수 없습니다.</p>
		<?php endif; ?>
	</div>

	<section class="hlf-panel">
		<div class="hlf-panel-heading-row">
			<h2 class="hlf-panel-heading">Leasing Info</h2>
			<?php if ( $item['features'] ) : ?>
				<p class="hlf-panel-note"><?php echo esc_html( $item['features'] ); ?></p>
			<?php endif; ?>
		</div>
		<div class="hlf-lease-metrics hlf-detail-metrics">
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">보증금</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['deposit_manwon'] ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">임대평당 <?php echo esc_html( number_format( $print_metrics['deposit_per_lease_pyeong'], 1 ) ); ?>만원</span>
			</div>
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">임대료</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">임대평당 <?php echo esc_html( number_format( $print_metrics['rent_per_lease_pyeong'], 1 ) ); ?>만원</span>
			</div>
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">관리비</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">임대평당 <?php echo esc_html( number_format( $print_metrics['maintenance_per_lease_pyeong'], 1 ) ); ?>만원</span>
			</div>
			<div class="hlf-lease-metric hlf-lease-metric--noc">
				<span class="hlf-lease-metric-label">환산임대료</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( $print_metrics['noc'], 1 ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">NOC</span>
			</div>
		</div>
	</section>

	<section class="hlf-panel">
		<h2 class="hlf-panel-heading">Property Details</h2>
		<dl class="hlf-property-details">
			<?php foreach ( $print_basic as $print_label => $print_entry ) :
				$print_is_wide = in_array( $print_label, $print_basic_wide_labels, true );
				?>
				<div class="hlf-basic-item<?php echo $print_is_wide ? ' hlf-basic-item--wide' : ''; ?>">
					<dt><?php echo esc_html( $print_label ); ?></dt>
					<?php if ( isset( $print_entry['main'] ) ) : ?>
						<dd class="hlf-basic-item--accent"><span class="hlf-basic-value-main"><?php echo esc_html( $print_entry['main'] ); ?></span><span class="hlf-basic-value-sub"><?php echo esc_html( $print_entry['sub'] ); ?></span></dd>
					<?php else : ?>
						<dd><?php echo esc_html( $print_entry['value'] ); ?></dd>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</dl>
	</section>

	<footer class="hlf-footer">
		<div class="hlf-footer-row">
			<p class="hlf-footer-copyright">© HINT Co., Ltd. All Rights Reserved. 무단 복제 및 재배포 금지</p>
			<span class="hlf-footer-contact">
				<?php if ( $print_contact['name'] ) : ?>
					<?php echo esc_html( $print_contact['name'] ); ?> ·
				<?php endif; ?>
				<a href="<?php echo esc_attr( 'tel:' . preg_replace( '/[^0-9+]/', '', $print_contact['phone'] ) ); ?>"><?php echo esc_html( $print_contact['phone'] ); ?></a>
			</span>
		</div>
	</footer>
</div>
