<?php
/**
 * 공개 Flyer 상세 (Phase 1 골격 + Phase 4: 사진 라이트박스/평당단가/공유). 지도는 Phase 4+ 후속.
 * $hlf_context: ['flyer'=>[], 'status'=>string, 'item'=>[...], 'items'=>[...]]
 */
defined( 'ABSPATH' ) || exit;

$flyer   = $hlf_context['flyer'];
$status  = $hlf_context['status'];
$item    = $hlf_context['item'];
$metrics = $item['metrics'];
$noindex = ( 'published' !== $status );
$address = $item['road_address'] ?: $item['lot_address'];

// 대표 이미지(exterior_image_id) 우선, 나머지(interior_image_ids)는 썸네일 스트립으로 — 갤러리
// 계약은 이 순서(대표 먼저) 하나뿐이라 목록/상세 어디서 이미지를 추가하더라도 그대로 유지된다.
$photo_ids = array();
if ( ! empty( $item['exterior_image_id'] ) ) {
	$photo_ids[] = (int) $item['exterior_image_id'];
}
foreach ( $item['interior_image_ids'] as $image_id ) {
	$photo_ids[] = (int) $image_id;
}
$photo_ids = array_values( array_unique( array_filter( $photo_ids ) ) );

// 라이트박스(이전/다음)용 원본 크기 URL — 썸네일 클릭 시 축소판이 아니라 큰 사진을 보여준다.
$photo_urls = array_map( static function ( $id ) {
	return wp_get_attachment_image_url( $id, 'hlf-item-photo' );
}, $photo_ids );

$kakao_js_key = defined( 'HLF_KAKAO_JS_KEY' ) ? HLF_KAKAO_JS_KEY : '';
$has_coords   = $item['latitude'] && $item['longitude'];
$map_items    = $has_coords ? array( array(
	'key'     => $item['item_number'],
	'order'   => 0,
	'lat'     => (float) $item['latitude'],
	'lng'     => (float) $item['longitude'],
	'address' => $address,
	'url'     => '',
) ) : array();

$basic = array(
	'해당층'       => trim( ( $item['floor_current'] ?: '-' ) . ' / ' . ( $item['floor_total'] ?: '-' ) . '층' ),
	'공급면적'     => $item['lease_area_sqm'] ? number_format( (float) $item['lease_area_sqm'], 1 ) . '㎡ (' . number_format( $metrics['lease_pyeong'], 1 ) . '평)' : '-',
	'전용면적'     => $item['exclusive_area_sqm'] ? number_format( (float) $item['exclusive_area_sqm'], 1 ) . '㎡ (' . number_format( $metrics['exclusive_pyeong'], 1 ) . '평)' : '-',
	'방향'         => $item['direction'] ?: '-',
	'주차'         => $item['parking_available'] ? ( $item['total_parking'] ?: '가능' ) : '불가',
	'엘리베이터'   => $item['elevator_available'] ? '있음' : '없음',
	'입주가능일'   => $item['available_date_text'] ?: '-',
	'사용승인일'   => $item['approval_date'] ?: '-',
	'건축물용도'   => $item['building_use'] ?: '-',
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $address . ' · ' . $flyer['flyer_number'] . ' ' . $item['item_number'] ); ?></title>
	<?php if ( $noindex ) : ?>
		<meta name="robots" content="noindex,nofollow">
	<?php endif; ?>
	<link rel="canonical" href="<?php echo esc_url( HLF_Routes::item_url( $flyer['id'], $item['item_number'] ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/public.css?v=' . HLF_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/print.css?v=' . HLF_VERSION ); ?>" media="print">
</head>
<body class="hlf-public hlf-detail">
	<div class="hlf-shell">
		<header class="hlf-header">
			<a class="hlf-back" href="<?php echo esc_url( $flyer['url'] ); ?>">← 목록</a>
			<span class="hlf-flyer-number"><?php echo esc_html( $flyer['flyer_number'] . ' · ' . $item['item_number'] ); ?></span>
			<button type="button" class="hlf-share-button" data-hlf-share-url="<?php echo esc_attr( HLF_Routes::item_url( $flyer['id'], $item['item_number'] ) ); ?>">
				공유 <span class="hlf-share-status" data-hlf-share-status></span>
			</button>
		</header>

		<h1 class="hlf-title"><?php echo esc_html( $address ); ?></h1>
		<?php if ( $item['lot_address'] && $item['lot_address'] !== $address ) : ?>
			<p class="hlf-subaddress"><?php echo esc_html( $item['lot_address'] ); ?></p>
		<?php endif; ?>

		<?php
		// 좌측: 사진 갤러리 / 우측: 지도(MVP 레이아웃) — 사진이 없으면 지도(또는 좌표 없음 안내)만
		// 전체 너비로 넓어진다(hlf-detail-hero--map-only). 지도 쪽은 좌표 유무와 무관하게 항상 뭔가
		// 렌더링되므로(실제 지도 또는 안내문) 오른쪽 칸이 비어 보이는 일은 없다.
		?>
		<div class="hlf-detail-hero<?php echo empty( $photo_ids ) ? ' hlf-detail-hero--map-only' : ''; ?>">
			<?php if ( ! empty( $photo_ids ) ) : ?>
				<section class="hlf-gallery" data-hlf-photos="<?php echo esc_attr( wp_json_encode( $photo_urls ) ); ?>">
					<div class="hlf-gallery-main">
						<button type="button" class="hlf-photo-open" data-hlf-lightbox-open data-hlf-lightbox-index="0" aria-label="사진 크게 보기">
							<?php echo wp_get_attachment_image( $photo_ids[0], 'hlf-item-photo', false, array( 'alt' => esc_attr( $address ), 'loading' => 'eager' ) ); ?>
						</button>
					</div>
					<?php if ( count( $photo_ids ) > 1 ) : ?>
						<div class="hlf-gallery-thumbs">
							<?php foreach ( array_slice( $photo_ids, 1 ) as $idx => $photo_id ) : ?>
								<button type="button" class="hlf-photo-open" data-hlf-lightbox-open data-hlf-lightbox-index="<?php echo esc_attr( $idx + 1 ); ?>" aria-label="사진 크게 보기">
									<?php echo wp_get_attachment_image( $photo_id, 'hlf-item-thumb', false, array( 'alt' => esc_attr( $address ), 'loading' => 'lazy' ) ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $has_coords ) : ?>
				<section class="hlf-detail-map-panel" aria-labelledby="hlf-detail-map-title">
					<div class="hlf-comparison-map-heading">
						<h2 id="hlf-detail-map-title">위치</h2>
					</div>
					<div
						class="hlf-comparison-map hlf-detail-map"
						id="hlf-detail-map"
						data-hlf-kakao-key="<?php echo esc_attr( $kakao_js_key ); ?>"
						data-hlf-map-items="<?php echo esc_attr( wp_json_encode( $map_items ) ); ?>"
					>
						<p class="hlf-map-empty">지도를 불러오는 중입니다…</p>
					</div>
					<p class="hlf-map-address-fallback">
						<?php echo esc_html( $address ); ?> ·
						<a href="<?php echo esc_url( 'https://map.kakao.com/link/map/' . rawurlencode( $address ) . ',' . $item['latitude'] . ',' . $item['longitude'] ); ?>" target="_blank" rel="noreferrer">카카오맵에서 보기 ↗</a>
					</p>
					<div class="hlf-map-print-fallback">
						<div class="hlf-map-print-fallback-item">
							<span class="hlf-map-print-fallback-index">01</span>
							<span><?php echo esc_html( $address ); ?></span>
						</div>
					</div>
				</section>
			<?php else : ?>
				<p class="hlf-map-unavailable">이 매물은 아직 좌표가 등록되지 않아 위치 지도를 표시할 수 없습니다.</p>
			<?php endif; ?>
		</div>

		<section class="hlf-lease-metrics hlf-detail-metrics">
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">보증금</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['deposit_manwon'] ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">공급평당 <?php echo esc_html( number_format( $metrics['deposit_per_lease_pyeong'], 1 ) ); ?>만원</span>
			</div>
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">임대료</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">공급평당 <?php echo esc_html( number_format( $metrics['rent_per_lease_pyeong'], 1 ) ); ?>만원</span>
			</div>
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">관리비</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span>
				<span class="hlf-lease-metric-sub">공급평당 <?php echo esc_html( number_format( $metrics['maintenance_per_lease_pyeong'], 1 ) ); ?>만원</span>
			</div>
			<div class="hlf-lease-metric">
				<span class="hlf-lease-metric-label">환산임대료(NOC)</span>
				<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( $metrics['noc'], 1 ) ); ?>만원/전용평</span>
			</div>
		</section>

		<?php if ( $item['features'] ) : ?>
			<p class="hlf-features"><?php echo esc_html( $item['features'] ); ?></p>
		<?php endif; ?>

		<dl class="hlf-property-details">
			<?php foreach ( $basic as $label => $value ) : ?>
				<div class="hlf-basic-item">
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>

		<?php
		// 문의처 우선순위: 이 매물의 개별 담당자(override, item_fields의 contact_name/contact_phone)
		// → Flyer 기본 담당자(flyer_fields) → 대표번호. HLF_Flyer_Repository::public_contact()가
		// 단일 기준으로 계산한다(목록/상세 화면이 서로 다른 규칙을 갖지 않도록).
		$contact = HLF_Flyer_Repository::public_contact( $flyer, $item );
		?>
		<footer class="hlf-footer">
			<span>© HINT <?php echo esc_html( gmdate( 'Y' ) ); ?> · <?php echo esc_html( $flyer['flyer_number'] ); ?></span>
			<span class="hlf-footer-contact">
				<?php if ( $contact['name'] ) : ?>
					<?php echo esc_html( $contact['name'] ); ?> ·
				<?php endif; ?>
				<a href="<?php echo esc_attr( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a>
			</span>
		</footer>
	</div>

	<div class="hlf-lightbox" id="hlf-lightbox" hidden>
		<div class="hlf-lightbox-content">
			<img class="hlf-lightbox-image" id="hlf-lightbox-image" src="" alt="">
			<button type="button" class="hlf-lightbox-button hlf-lightbox-close" data-hlf-lightbox-close aria-label="닫기">✕</button>
			<?php if ( count( $photo_ids ) > 1 ) : ?>
				<button type="button" class="hlf-lightbox-button hlf-lightbox-prev" data-hlf-lightbox-prev aria-label="이전 사진">‹</button>
				<button type="button" class="hlf-lightbox-button hlf-lightbox-next" data-hlf-lightbox-next aria-label="다음 사진">›</button>
			<?php endif; ?>
		</div>
	</div>
	<script src="<?php echo esc_url( HLF_URL . 'assets/js/public-flyer.js?v=' . HLF_VERSION ); ?>" defer></script>
</body>
</html>
<?php
