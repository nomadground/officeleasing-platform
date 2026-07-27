<?php
/**
 * 공개 Flyer 상세 (Phase 1 골격 + Phase 4: 사진 라이트박스/평당단가/공유). 지도는 Phase 4+ 후속.
 * $hlf_context: ['flyer'=>[], 'status'=>string, 'item'=>[...], 'items'=>[...]]
 */
defined( 'ABSPATH' ) || exit;

$flyer   = $hlf_context['flyer'];
$status  = $hlf_context['status'];
$item    = $hlf_context['item'];
$items   = $hlf_context['items'];
$metrics = $item['metrics'];
$noindex = ( 'published' !== $status );
$address_parts = hlf_format_address( $item['road_address'], $item['lot_address'] );
$address = $address_parts['main'];

// 리스트/차트/지도와 같은 번호·색을 쓰기 위해 items 배열에서 이 매물의 표시 순서(0-based)를 찾는다
// (get_items()가 이미 display_order 순으로 정렬해 반환하므로 list 페이지의 foreach($items as $i=>...)
// 와 동일한 순번이 나온다).
$item_order = 0;
foreach ( $items as $idx => $list_item ) {
	if ( $list_item['item_number'] === $item['item_number'] ) {
		$item_order = $idx;
		break;
	}
}

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

// 요청서: HINT 워터마크(블러 처리)는 사진마다 켜고 끌 수 있다(관리자 화면에서 업로드/선택 시
// 체크박스로 설정, 기본값은 꺼짐) — photo_urls와 같은 순서의 배열로 내려보내 JS가 대표 사진
// 교체(hover)·라이트박스 이전/다음 넘길 때마다 그 사진의 설정을 따라가게 한다.
$photo_blur = array_map( static function ( $id ) {
	return (bool) get_post_meta( $id, HLF_Meta_Schema::PHOTO_BLUR, true );
}, $photo_ids );

$kakao_js_key = defined( 'HLF_KAKAO_JS_KEY' ) ? HLF_KAKAO_JS_KEY : '';
$has_coords   = $item['latitude'] && $item['longitude'];
$map_items    = $has_coords ? array( array(
	'key'     => $item['item_number'],
	// 리스트/차트와 같은 순번 배지가 지도 마커에도 나오게 한다 — 0으로 고정돼 있으면(회귀 버그) 어느
	// 매물을 보든 마커가 항상 "01"로 나온다.
	'order'   => $item_order,
	'lat'     => (float) $item['latitude'],
	'lng'     => (float) $item['longitude'],
	'address' => $address,
	'url'     => '',
) ) : array();

// 값이 없는 항목은 아예 출력하지 않는다(빈 항목은 모바일 1행 2열 grid에서 자리만 차지하고 정보가
// 없다). 주차/엘리베이터는 예외 — "불가"/"없음"도 그 자체로 유효한 답이므로 항상 표시한다.
// 순서는 미리보기 목업의 Property Details 순서(해당층/입주가능일 → 임대·전용면적 → 건축물용도·
// 사용승인일 → 방향 → 엘리베이터·주차)를 따른다.
// 각 항목은 ['value'=>단순 텍스트], ['main'=>..., 'sub'=>...](임대/전용면적처럼 강조색+보조줄 2단
// 표기가 필요한 경우), 또는 ['floor'=>['current'=>.., 'total'=>..]](기준층 — 해당층만 색으로 구분)
// 형태로 담는다.
$basic = array();
if ( $item['floor_current'] || $item['floor_total'] ) {
	$basic['기준층'] = array(
		'floor' => array(
			'current' => $item['floor_current'] ?: '-',
			'total'   => $item['floor_total'] ?: '-',
		),
	);
}
if ( $item['available_date_text'] ) {
	$basic['입주가능일'] = array( 'value' => $item['available_date_text'] );
}
if ( $item['lease_area_sqm'] ) {
	$basic['임대면적'] = array(
		'main' => number_format( (float) $item['lease_area_sqm'], 1 ) . '㎡',
		'sub'  => number_format( $metrics['lease_pyeong'], 1 ) . '평',
	);
}
if ( $item['exclusive_area_sqm'] ) {
	$basic['전용면적'] = array(
		'main' => number_format( (float) $item['exclusive_area_sqm'], 1 ) . '㎡',
		'sub'  => number_format( $metrics['exclusive_pyeong'], 1 ) . '평',
	);
}
if ( $item['building_use'] ) {
	$basic['건축물용도'] = array( 'value' => $item['building_use'] );
}
// 위반건축물 여부는 건축물용도와 짝을 이뤄 한 행(2칸)을 채운다(요청서) — 항상 표시(엘리베이터/주차와
// 같은 원칙: "해당없음"도 그 자체로 유효한 답이라 값이 없다고 행을 생략하지 않는다).
$basic['위반건축물 여부'] = array( 'value' => $item['illegal_building'] ? '해당' : '해당없음' );
if ( $item['approval_date'] ) {
	$basic['사용승인일'] = array( 'value' => $item['approval_date'] );
}
if ( $item['direction'] ) {
	$basic['방향(주된출입구)'] = array( 'value' => $item['direction'] );
}
$basic['엘리베이터'] = array( 'value' => $item['elevator_available'] ? '있음' : '없음' );
$basic['주차']       = array( 'value' => $item['parking_available'] ? ( $item['total_parking'] ?: '가능' ) : '불가' );

// 요청서: 카카오톡 등 SNS 공유 썸네일(og:image) — 목록 페이지와 같은 방식으로, 이 매물에 실제
// 적용되는 문의처(Item override 우선, HLF_Flyer_Repository::public_contact 참고) 이름으로 담당자
// 디렉터리의 명함 이미지를 찾는다.
$og_title       = $address . ' · ' . $flyer['flyer_number'] . ' ' . $item['item_number'];
$og_description = number_format( (float) $item['deposit_manwon'] ) . '만원 / ' . number_format( (float) $item['monthly_rent_manwon'] ) . '만원 · 전용 ' . number_format( $metrics['exclusive_pyeong'], 1 ) . '평';
$og_contact     = HLF_Flyer_Repository::public_contact( $flyer, $item );
$og_image_url   = HLF_Contact_Directory::find_image_url_by_name( $og_contact['name'] );
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
	<meta property="og:type" content="website">
	<meta property="og:url" content="<?php echo esc_url( HLF_Routes::item_url( $flyer['id'], $item['item_number'] ) ); ?>">
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
		 * 요청서(item 3, 페이지 이동 속도): preconnect만으로는 브라우저가 이 스크립트의 존재를
		 * DOMContentLoaded 이후 JS 실행 시점(loadKakaoMapSdk, assets/js/public-flyer.js)에야 알게
		 * 된다 — preload를 추가하면 HTML 파싱 중 preload scanner가 곧바로 미리 받아오기 시작한다.
		 * href는 loadKakaoMapSdk가 실제로 넣는 <script src>와 정확히 같은 URL이어야 브라우저가 같은
		 * 요청으로 인식해 캐시를 재사용한다(다르면 두 번 받아옴).
		 */
		?>
		<link rel="preload" as="script" href="<?php echo esc_url( 'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' . rawurlencode( $kakao_js_key ) . '&autoload=false' ); ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/public.css?v=' . HLF_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/print.css?v=' . HLF_VERSION ); ?>" media="print">
</head>
<body class="hlf-public hlf-detail">
	<div class="hlf-shell">
		<header class="hlf-header">
			<a class="hlf-back hlf-brand-main" href="<?php echo esc_url( $flyer['url'] ); ?>">HINT</a>
			<span class="hlf-item-badge hlf-detail-badge" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $item_order ) ); ?>"><?php echo esc_html( sprintf( '%02d', $item_order + 1 ) ); ?></span>
			<span class="hlf-header-address">
				<h1 class="hlf-header-address-main"><?php echo esc_html( $address ); ?></h1>
				<?php if ( $address_parts['sub'] || $item['building_name'] ) : ?>
					<span class="hlf-header-address-subrow">
						<?php if ( $address_parts['sub'] ) : ?>
							<span class="hlf-header-address-sub"><?php echo esc_html( $address_parts['sub'] ); ?></span>
						<?php endif; ?>
						<?php if ( $item['building_name'] ) : ?>
							<?php if ( $address_parts['sub'] ) : ?><span class="hlf-building-name-sep">·</span><?php endif; ?>
							<span class="hlf-building-name"><?php echo esc_html( $item['building_name'] ); ?></span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
			</span>
			<span class="hlf-header-actions">
				<button type="button" class="hlf-share-button" data-hlf-share-url="<?php echo esc_attr( HLF_Routes::item_url( $flyer['id'], $item['item_number'] ) ); ?>">공유<span class="hlf-share-status" data-hlf-share-status></span></button>
				<button type="button" class="hlf-print-button" data-hlf-print>인쇄</button>
			</span>
		</header>

		<?php
		// 좌측: 사진 갤러리 / 우측: 지도(가로 50:50, 높이도 맞춤) — 사진이 없으면 지도(또는 좌표 없음
		// 안내)만 전체 너비로 넓어진다(hlf-detail-hero--map-only). 지도 쪽은 좌표 유무와 무관하게
		// 항상 뭔가 렌더링되므로(실제 지도 또는 안내문) 오른쪽 칸이 비어 보이는 일은 없다.
		?>
		<div class="hlf-detail-hero<?php echo empty( $photo_ids ) ? ' hlf-detail-hero--map-only' : ''; ?>">
			<?php if ( ! empty( $photo_ids ) ) : ?>
				<section class="hlf-gallery" data-hlf-photos="<?php echo esc_attr( wp_json_encode( $photo_urls ) ); ?>" data-hlf-photo-blur="<?php echo esc_attr( wp_json_encode( $photo_blur ) ); ?>">
					<div class="hlf-gallery-main">
						<button type="button" class="hlf-photo-open" data-hlf-lightbox-open data-hlf-lightbox-index="0" aria-label="사진 크게 보기">
							<?php echo wp_get_attachment_image( $photo_ids[0], 'hlf-item-photo', false, array( 'alt' => esc_attr( $address ), 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
						</button>
						<span class="hlf-gallery-watermark" aria-hidden="true"<?php echo $photo_blur[0] ? '' : ' hidden'; ?>>HINT</span>
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
						<h2 id="hlf-detail-map-title">Location</h2>
						<button type="button" class="hlf-map-recenter" data-hlf-map-recenter aria-label="지도를 매물 위치로 다시 이동">← 매물 위치</button>
					</div>
					<div
						class="hlf-comparison-map hlf-detail-map"
						id="hlf-detail-map"
						data-hlf-kakao-key="<?php echo esc_attr( $kakao_js_key ); ?>"
						data-hlf-map-items="<?php echo esc_attr( wp_json_encode( $map_items ) ); ?>"
					>
						<p class="hlf-map-empty">지도를 불러오는 중입니다…</p>
					</div>
					<div class="hlf-map-print-fallback">
						<div class="hlf-map-print-fallback-item">
							<span class="hlf-item-badge hlf-map-print-fallback-index" style="--hlf-item-accent:<?php echo esc_attr( hlf_item_accent_color( $item_order ) ); ?>"><?php echo esc_html( sprintf( '%02d', $item_order + 1 ) ); ?></span>
							<span><?php echo esc_html( $address ); ?></span>
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
					<span class="hlf-lease-metric-sub">임대평당 <?php echo esc_html( number_format( $metrics['deposit_per_lease_pyeong'], 1 ) ); ?>만원</span>
				</div>
				<div class="hlf-lease-metric">
					<span class="hlf-lease-metric-label">임대료</span>
					<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?>만원</span>
					<span class="hlf-lease-metric-sub">임대평당 <?php echo esc_html( number_format( $metrics['rent_per_lease_pyeong'], 1 ) ); ?>만원</span>
				</div>
				<div class="hlf-lease-metric">
					<span class="hlf-lease-metric-label">관리비</span>
					<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span>
					<span class="hlf-lease-metric-sub">임대평당 <?php echo esc_html( number_format( $metrics['maintenance_per_lease_pyeong'], 1 ) ); ?>만원</span>
				</div>
				<div class="hlf-lease-metric hlf-lease-metric--noc">
					<span class="hlf-lease-metric-label">환산임대료</span>
					<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( $metrics['noc'], 1 ) ); ?>만원</span>
					<span class="hlf-lease-metric-sub">전용평당</span>
				</div>
			</div>
		</section>

		<section class="hlf-panel">
			<h2 class="hlf-panel-heading">Property Details</h2>
			<?php
			// 건축물용도는 요청서 이후 위반건축물 여부와 짝을 이뤄 2칸을 나눠 쓴다(더 이상 단독으로
			// 한 행 전체를 쓰지 않는다) — 그래서 wide 목록은 비워둔다. 짝을 짓는 다른 필드가 생기면
			// 여기에 라벨을 추가한다.
			$basic_wide_labels = array();
			?>
			<dl class="hlf-property-details">
				<?php foreach ( $basic as $label => $entry ) :
					$is_wide = in_array( $label, $basic_wide_labels, true );
					// 요청서: 전용면적의 평수(sub)만 어울리는 파란색으로 구분한다 — 임대면적은 같은
					// main/sub 마크업을 쓰므로 라벨로만 구분해 이 항목에만 색상용 클래스를 붙인다.
					$is_exclusive = ( '전용면적' === $label );
					?>
					<div class="hlf-basic-item<?php echo $is_wide ? ' hlf-basic-item--wide' : ''; ?>">
						<dt><?php echo esc_html( $label ); ?></dt>
						<?php if ( isset( $entry['floor'] ) ) : ?>
							<dd><span class="hlf-floor-current"><?php echo esc_html( $entry['floor']['current'] ); ?></span> / <?php echo esc_html( $entry['floor']['total'] ); ?>층</dd>
						<?php elseif ( isset( $entry['main'] ) ) : ?>
							<dd class="hlf-basic-item--accent<?php echo $is_exclusive ? ' hlf-basic-item--exclusive' : ''; ?>"><span class="hlf-basic-value-main"><?php echo esc_html( $entry['main'] ); ?></span><span class="hlf-basic-value-sub"><?php echo esc_html( $entry['sub'] ); ?></span></dd>
						<?php else : ?>
							<dd><?php echo esc_html( $entry['value'] ); ?></dd>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</dl>
		</section>

		<div class="hlf-detail-footer-note">
			<p class="hlf-footer-copyright"><a class="hlf-footer-admin-link" href="<?php echo esc_url( wp_logout_url( HLF_Portal::portal_url() ) ); ?>">© HINT</a> Co., Ltd. All Rights Reserved. 무단 복제 및 재배포 금지</p>
		</div>

		<?php
		// 문의처 우선순위: 이 매물의 개별 담당자(override, item_fields의 contact_name/contact_phone)
		// → Flyer 기본 담당자(flyer_fields) → 대표번호. HLF_Flyer_Repository::public_contact()가
		// 단일 기준으로 계산한다(목록/상세 화면이 서로 다른 규칙을 갖지 않도록).
		$contact = HLF_Flyer_Repository::public_contact( $flyer, $item );
		?>
		<footer class="hlf-footer">
			<div class="hlf-footer-row">
				<a class="hlf-detail-list-return-link" href="<?php echo esc_url( $flyer['url'] ); ?>">← 목록</a>
				<span class="hlf-footer-contact">
					<?php if ( $contact['name'] ) : ?>
						<?php echo esc_html( $contact['name'] ); ?> ·
					<?php endif; ?>
					<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a>
				</span>
			</div>
		</footer>
	</div>

	<div class="hlf-lightbox" id="hlf-lightbox" role="dialog" aria-modal="true" aria-label="사진 크게 보기" hidden>
		<div class="hlf-lightbox-content">
			<img class="hlf-lightbox-image" id="hlf-lightbox-image" src="" alt="">
			<span class="hlf-gallery-watermark hlf-gallery-watermark--lightbox" id="hlf-lightbox-watermark" aria-hidden="true"<?php echo ! empty( $photo_blur[0] ) ? '' : ' hidden'; ?>>HINT</span>
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
