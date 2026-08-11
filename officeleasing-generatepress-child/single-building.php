<?php
/**
 * single-building.php — 매물 상세(빌딩 URL) 메인 템플릿.
 *
 * URL 정책(확정): 빌딩 URL 하나에 매물을 병합 렌더한다. 매물 개별 URL은 없다.
 *  - 활성 매물 1개  → 이 목업(v1 디자인)과 동일하게 매물 상세를 병합 렌더
 *  - 활성 매물 2개+ → Hero는 빌딩 요약, "임대 정보" 자리에 매물 카드 그리드
 *  - 활성 매물 0개  → 빌딩 정보 + "현재 임대가능 매물 없음" 안내
 *
 * 모든 값은 get_field()/WP 함수로 출력. 하드코딩 없음. 컴포넌트는 template-parts/* 재사용.
 */
defined( 'ABSPATH' ) || exit;

get_header();

if ( ! olt_core_active() ) {
	olt_render_core_inactive_notice();
	get_footer();
	return;
}

$building_id  = get_queried_object_id();
$listings     = olt_get_building_listings( $building_id, true );
$count        = count( $listings );
$primary      = $count > 0 ? $listings[0] : null;
$primary_id   = $primary ? $primary->ID : 0;

$regions      = olt_get_region_terms( $building_id );
$parent_term  = $regions['parent'];
$child_term   = $regions['child'];
$region_code  = $parent_term ? $parent_term->name : '';
$district     = $child_term ? $child_term->name : '';

$building_name = get_the_title( $building_id );
$address_road  = get_field( 'building_address_road', $building_id );
$address_jibun = get_field( 'building_address_jibun', $building_id );
$basement_floors = get_field( 'building_basement_floors', $building_id );
$ground_floors   = get_field( 'building_ground_floors', $building_id );
$total_floors  = $ground_floors; // "해당층/총층" 등 기존 표기에서 총층은 지상층수를 가리킨다
$standard_floor_area_pyeong = get_field( 'building_standard_floor_area_pyeong', $building_id );

// 매물 2~3건일 때만 쓰는 "면적 슬라이더"용 데이터. 4건 이상은 기존 카드 그리드를 그대로 쓴다
// (버튼이 4개 이상이면 한눈에 비교하기보다 오히려 산만해진다는 판단, 확정 임계값).
// Hero(.olx-side)와 아래쪽 "임대 정보" 섹션 둘 다 이 배열을 쓰므로 여기서 한 번만 계산해둔다 -
// 매물 재쿼리 금지 원칙을 지키면서, 전환 시 서버 재쿼리 없이 클라이언트 JS가 이 값들 사이를
// 오갈 수 있도록 각 매물의 표시용 값을 미리 문자열로 포맷해 배열에 담는다(값 계산 로직 자체는
// 기존 함수(olt_sqm/olt_pyeong/olt_won/olt_pyeong_price)를 그대로 재사용).
$toggle_listings = array();
if ( $count >= 2 && $count <= 3 ) {
	foreach ( $listings as $l ) {
		$lid = $l->ID;
		// [listing-detail-ux-pass4] 슬라이더 점 정렬/기본 선택 계산에 쓸 원값(평, 숫자)도 같이 담아둔다 -
		// 아래 표시용 문자열(lease_pyeong 등)은 이미 포맷된 텍스트라 정렬/거리 비교에 못 쓴다.
		$lease_pyeong_raw = (float) get_field( 'lease_area_pyeong', $lid );
		$toggle_listings[] = array(
			'id'                          => $lid,
			'lease_pyeong_raw'            => $lease_pyeong_raw,
			'lease_pyeong'                => olt_pyeong( $lease_pyeong_raw ),
			'lease_sqm'                   => olt_sqm( get_field( 'lease_area_sqm', $lid ) ),
			'exclusive_pyeong'            => olt_pyeong( get_field( 'exclusive_area_pyeong', $lid ) ),
			'exclusive_sqm'               => olt_sqm( get_field( 'exclusive_area_sqm', $lid ) ),
			'deposit'                     => olt_won( get_field( 'deposit_amount', $lid ) ),
			'deposit_per_lease_pyeong'    => olt_pyeong_price( get_field( 'deposit_per_lease_pyeong', $lid ) ),
			'rent'                        => olt_won( get_field( 'monthly_rent', $lid ) ),
			'rent_per_lease_pyeong'       => olt_pyeong_price( get_field( 'rent_per_lease_pyeong', $lid ) ),
			'maintenance'                 => olt_won( get_field( 'maintenance_fee', $lid ) ),
			'maintenance_per_lease_pyeong' => olt_pyeong_price( get_field( 'maintenance_per_lease_pyeong', $lid ) ),
		);
	}
}

// [listing-detail-ux-pass4] 면적 슬라이더 점 순서(임대면적 오름차순, 최소→최대)와 기본 선택 매물
// (기준층면적에 가장 가까운 매물 - 요청: "기본세팅은 기준층면적으로")을 여기서 한 번만 계산해서
// Hero/임대정보 두 군데 슬라이더가 그대로 재사용한다. $toggle_listings 자체의 순서(가격순, 카드
// 그리드가 쓰는 순서)는 건드리지 않고, 인덱스만 별도로 재배열한다.
$area_slider_stops = array();
$area_slider_default_index = 0;
if ( ! empty( $toggle_listings ) ) {
	$area_slider_stops = array_map(
		function ( $i, $tl ) {
			return array(
				'index'            => $i,
				'lease_pyeong'     => $tl['lease_pyeong'],
				'lease_sqm'        => $tl['lease_sqm'],
				'exclusive_pyeong' => $tl['exclusive_pyeong'],
				'exclusive_sqm'    => $tl['exclusive_sqm'],
			);
		},
		array_keys( $toggle_listings ),
		$toggle_listings
	);
	usort( $area_slider_stops, function ( $a, $b ) use ( $toggle_listings ) {
		return $toggle_listings[ $a['index'] ]['lease_pyeong_raw'] <=> $toggle_listings[ $b['index'] ]['lease_pyeong_raw'];
	} );

	if ( $standard_floor_area_pyeong ) {
		$closest_diff = null;
		foreach ( $toggle_listings as $i => $tl ) {
			$diff = abs( $tl['lease_pyeong_raw'] - (float) $standard_floor_area_pyeong );
			if ( null === $closest_diff || $diff < $closest_diff ) {
				$closest_diff = $diff;
				$area_slider_default_index = $i;
			}
		}
	}
}

// [listing-detail-ux-pass4] 매물 1건일 때는 비교 대상이 없어 실제 토글은 못 하지만, Hero/임대정보
// 양쪽에서 매물 2~3건일 때와 동일한 "점 하나짜리" 슬라이더 시각 언어로 통일한다(요청: "면적에
// 대한 부분만 이런 방식이 좋을것 같아").
$area_slider_stop_single = array();
if ( 1 === $count ) {
	$area_slider_stop_single = array( array(
		'index'            => 0,
		'lease_pyeong'     => olt_pyeong( get_field( 'lease_area_pyeong', $primary_id ) ),
		'lease_sqm'        => olt_sqm( get_field( 'lease_area_sqm', $primary_id ) ),
		'exclusive_pyeong' => olt_pyeong( get_field( 'exclusive_area_pyeong', $primary_id ) ),
		'exclusive_sqm'    => olt_sqm( get_field( 'exclusive_area_sqm', $primary_id ) ),
	) );
}

// 갤러리: 매물 사진 우선(1개 매물 케이스), 없으면 빌딩 사진.
// 'ol-interior'(600x400)는 썸네일 스트립(olx-gallery-thumbs)용 크기 - 대표 Hero 이미지는
// 'id'로 별도 조회해 ol-hero-desktop/mobile 반응형 <picture>를 구성한다(아래 Hero 마크업 참고).
$gallery = $primary_id ? olt_collect_images( $primary_id, 'listing_image_', 6, 'ol-interior' ) : array();
if ( empty( $gallery ) ) {
	$gallery = olt_collect_images( $building_id, 'building_image_', 8, 'ol-interior' );
}
$gallery_captions = array( '외관', '오피스', '라운지', '회의실', '', '', '', '' );
?>

<nav class="olx-crumb" aria-label="현재 위치">
	<?php if ( $parent_term ) : ?>
		<a href="<?php echo esc_url( get_term_link( $parent_term ) ); ?>"><?php echo esc_html( olt_region_label( $region_code ) ); ?></a>
		<span>›</span>
	<?php endif; ?>
	<?php if ( $child_term ) : ?>
		<a href="<?php echo esc_url( get_term_link( $child_term ) ); ?>"><?php echo esc_html( $district ); ?></a>
		<span>›</span>
	<?php endif; ?>
	<b><?php echo esc_html( $building_name ); ?></b>
</nav>

<header class="olx-head">
	<div class="olx-head-meta">
		<?php if ( $primary ) :
			$status = get_field( 'listing_status', $primary_id );
			$verified = get_field( 'verified_at', $primary_id );
			?>
			<span class="olx-badge"><i></i><?php echo esc_html( olt_status_label( $status ) ); ?></span>
			<?php if ( $verified ) : ?>
				<span class="olx-verified">최근 확인 <?php echo esc_html( date_i18n( 'Y.m.d', strtotime( $verified ) ) ); ?></span>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<h1><?php if ( $district ) : ?><span class="olx-head-district"><?php echo esc_html( $district ); ?></span><?php endif; ?><mark><?php echo esc_html( $building_name ); ?></mark> 사무실 임대</h1>
	<p><?php
		echo esc_html( $address_road );
		if ( $address_jibun ) {
			echo ' (' . esc_html( $address_jibun ) . ')';
		}
		if ( $region_code ) {
			echo ' · ' . esc_html( $region_code ) . ' 프라임 오피스';
		}
	?></p>
</header>

<section class="olx-hero" aria-label="매물 핵심 정보">
	<div class="olx-gallery">
		<div class="olx-gallery-main">
			<?php
			// Hero(LCP) 이미지: docs/IMAGE_PERFORMANCE_GUIDELINES.md 기준 데스크톱/모바일 분리 전달.
			// ol-hero-desktop/ol-hero-mobile은 soft resize(crop=false)로 등록돼 있다(functions.php 참고) -
			// .olx-gallery-main img가 이미 object-fit:cover라 브라우저가 실제 박스에 맞춰 채워주므로,
			// 서버에서 임의 비율로 미리 자르지 않고 <picture>로 뷰포트별 적절한 원본만 나눠 보낸다.
			// lazy 미적용 + fetchpriority=high: 이 이미지가 페이지의 LCP 요소이기 때문(가이드라인 6장).
			if ( ! empty( $gallery ) ) :
				$hero_id  = (int) ( $gallery[0]['id'] ?? 0 );
				$hero_alt = $gallery[0]['alt'] ?: $building_name . ' 외관';
				if ( $hero_id ) :
					$hero_mobile_src = wp_get_attachment_image_url( $hero_id, 'ol-hero-mobile' );
					?>
					<picture>
						<?php if ( $hero_mobile_src ) : ?>
							<source media="(max-width: 900px)" srcset="<?php echo esc_url( $hero_mobile_src ); ?>">
						<?php endif; ?>
						<?php
						// loading 키 자체를 안 넣는다(false로 넣으면 WP/브라우저에 따라 빈 속성으로 남을 여지가
						// 있다는 지적 반영) - LCP 이미지이므로 lazy를 아예 안 쓰는 게 의도이므로 생략이 명확하다.
						echo wp_get_attachment_image( $hero_id, 'ol-hero-desktop', false, array(
							'alt'           => $hero_alt,
							'fetchpriority' => 'high',
							'decoding'      => 'async',
						) );
						?>
					</picture>
				<?php else : ?>
					<img src="<?php echo esc_url( $gallery[0]['url'] ); ?>" alt="<?php echo esc_attr( $hero_alt ); ?>" fetchpriority="high" decoding="async">
				<?php endif;
			endif;
			// [listing-detail-ux-pass3] "외관 01/01" 같은 인덱스/캡션 오버레이 텍스트 삭제(요청) -
			// 어떤 사진이 선택됐는지는 아래 썸네일의 is-active 테두리로 이미 충분히 드러난다.
			?>
		</div>
		<?php if ( count( $gallery ) > 1 ) : ?>
			<div class="olx-gallery-thumbs">
				<?php foreach ( array_slice( $gallery, 0, 4 ) as $i => $g ) : ?>
					<?php
					// 썸네일 화면 표시는 ol-interior(600x400) 그대로. 클릭 후 Hero에 확대할 때는 이
					// 작은 썸네일 URL을 재사용하지 않고 ol-interior-large(900x600)를 data-full에 담아둔다 -
					// single.js가 클릭 시 이 값으로 <picture> source와 메인 img의 src/srcset을 함께 갱신한다.
					$thumb_full_alt = ( $gallery_captions[ $i ] ?? '' ) ?: $building_name . ' 외관';
					$thumb_full_url = ! empty( $g['id'] )
						? wp_get_attachment_image_url( (int) $g['id'], 'ol-interior-large' )
						: $g['url'];
					?>
					<button class="<?php echo 0 === $i ? 'is-active' : ''; ?>"
						aria-label="<?php echo esc_attr( ( $gallery_captions[ $i ] ?? '' ) . ' 이미지 보기' ); ?>"
						data-full="<?php echo esc_url( $thumb_full_url ); ?>"
						data-full-alt="<?php echo esc_attr( $thumb_full_alt ); ?>">
						<?php
						// ol-interior(600x400 hard crop) - docs/IMAGE_PERFORMANCE_GUIDELINES.md "내부 갤러리" 기준.
						if ( ! empty( $g['id'] ) ) {
							echo wp_get_attachment_image( (int) $g['id'], 'ol-interior', false, array( 'alt' => '' ) );
						} else {
							printf( '<img src="%s" alt="">', esc_url( $g['url'] ) );
						}
						?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="olx-side">
		<?php
		$lat = get_field( 'building_lat', $building_id );
		$lng = get_field( 'building_lng', $building_id );
		// 외부 "카카오맵에서 보기" 링크는 주소가 있으면 주소 기준, 없으면 빌딩명 기준으로 검색
		$kakao_map_query = $address_road ?: $building_name;
		$kakao_search    = 'https://map.kakao.com/link/search/' . rawurlencode( $kakao_map_query );
		?>
		<div class="olx-map-mini" aria-label="<?php echo esc_attr( $building_name ); ?> 지도"
			data-lat="<?php echo esc_attr( $lat ); ?>" data-lng="<?php echo esc_attr( $lng ); ?>"
			data-name="<?php echo esc_attr( $building_name ); ?>">
			<div class="olx-map-canvas"></div>
			<div class="olx-map-fallback">
				<span class="olx-map-road road-a"></span><span class="olx-map-road road-b"></span><span class="olx-map-road road-c"></span>
				<span class="olx-map-pin"><i></i></span>
				<strong><?php echo esc_html( $building_name ); ?></strong>
				<a href="<?php echo esc_url( $kakao_search ); ?>" target="_blank" rel="noopener noreferrer">카카오맵에서 보기 ↗</a>
			</div>
		</div>

		<?php if ( 1 === $count ) : ?>
			<?php
			// [listing-detail-ux-pass5] 층수 표시 칸 삭제 요청 - 면적(임대/전용)만 면적 슬라이더로 보여준다.
			// 매물이 1건이라 실제로 고를 대상은 없지만, Hero/임대정보 두 군데가 같은 시각 언어를 쓰도록
			// 점 하나짜리 슬라이더를 그대로 재사용한다(olt_area_slider()가 1건이면 최소/최대 라벨을 뺀다).
			echo olt_area_slider( $area_slider_stop_single, 0 );
			?>
			<div class="olx-price">
				<div>
					<span>보증금</span>
					<strong><?php echo esc_html( olt_won( get_field( 'deposit_amount', $primary_id ) ) ); ?></strong>
					<em>임대평당 <?php echo esc_html( olt_pyeong_price( get_field( 'deposit_per_lease_pyeong', $primary_id ) ) ); ?></em>
				</div>
				<div>
					<span>임대료</span>
					<strong><?php echo esc_html( olt_won( get_field( 'monthly_rent', $primary_id ) ) ); ?></strong>
					<em>공급평당 <?php echo esc_html( olt_pyeong_price( get_field( 'rent_per_lease_pyeong', $primary_id ) ) ); ?></em>
				</div>
				<div>
					<span>관리비</span>
					<strong><?php echo esc_html( olt_won( get_field( 'maintenance_fee', $primary_id ) ) ); ?></strong>
					<em>공급평당 <?php echo esc_html( olt_pyeong_price( get_field( 'maintenance_per_lease_pyeong', $primary_id ) ) ); ?></em>
				</div>
			</div>
		<?php elseif ( $count >= 2 && $count <= 3 ) : ?>
			<?php
			$t0 = $toggle_listings[ $area_slider_default_index ];
			// [listing-detail-ux-pass5] 층수 표시 칸 삭제 요청. 아래 면적 슬라이더가 여전히 매물 선택
			// 트리거 역할을 한다 - 점 클릭/hover 시 select(index)가 보증금/임대료/관리비를 갱신한다.
			echo olt_area_slider( $area_slider_stops, $area_slider_default_index );
			?>
			<div class="olx-price">
				<div>
					<span>보증금</span>
					<strong data-toggle-field="deposit"><?php echo esc_html( $t0['deposit'] ); ?></strong>
					<em>임대평당 <span data-toggle-field="deposit_per_lease_pyeong"><?php echo esc_html( $t0['deposit_per_lease_pyeong'] ); ?></span></em>
				</div>
				<div>
					<span>임대료</span>
					<strong data-toggle-field="rent"><?php echo esc_html( $t0['rent'] ); ?></strong>
					<em>공급평당 <span data-toggle-field="rent_per_lease_pyeong"><?php echo esc_html( $t0['rent_per_lease_pyeong'] ); ?></span></em>
				</div>
				<div>
					<span>관리비</span>
					<strong data-toggle-field="maintenance"><?php echo esc_html( $t0['maintenance'] ); ?></strong>
					<em>공급평당 <span data-toggle-field="maintenance_per_lease_pyeong"><?php echo esc_html( $t0['maintenance_per_lease_pyeong'] ); ?></span></em>
				</div>
			</div>
			<?php
			// JSON_HEX_TAG: floor_display 등은 관리자가 자유 입력하는 텍스트 필드라, 이론상 "</script>"
			// 같은 문자열이 들어가면 HTML 파서가 이 스크립트 블록을 조기 종료시킬 수 있다 - <, >를
			// <, >로 이스케이프해 <script> 안에 안전하게 JSON을 넣는 표준 패턴.
			?>
			<script type="application/json" id="olx-toggle-data"><?php echo wp_json_encode( $toggle_listings, JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<?php else : ?>
			<div class="olx-specs3">
				<div><span>총 층수</span><strong><?php echo esc_html( $total_floors ); ?>F</strong></div>
				<div><span>기준층면적</span><strong><?php echo esc_html( olt_sqm( get_field( 'building_standard_floor_area_sqm', $building_id ) ) ); ?></strong><small><?php echo esc_html( olt_pyeong( get_field( 'building_standard_floor_area_pyeong', $building_id ) ) ); ?></small></div>
				<div><span>임대가능 매물</span><strong><?php echo esc_html( (string) $count ); ?>건</strong></div>
			</div>
		<?php endif; ?>

		<div class="olx-cta">
			<a class="olx-btn olx-btn-call" href="tel:<?php echo esc_attr( olt_tel_href() ); ?>">유선 문의</a>
			<a class="olx-btn olx-btn-online" href="#contact">온라인 문의</a>
		</div>
	</div>
</section>

<?php
// ── KEY POINTS: 1개 매물이면 그 매물의 핵심포인트(kp1~3), 아니면 빌딩 특징요약 ──
$key_points = array();
if ( $primary_id ) {
	for ( $i = 1; $i <= 3; $i++ ) {
		$kp = get_field( 'listing_key_point_' . $i, $primary_id );
		if ( ! empty( $kp['kp' . $i . '_title'] ) ) {
			$key_points[] = array(
				'title' => $kp[ 'kp' . $i . '_title' ],
				'desc'  => $kp[ 'kp' . $i . '_desc' ] ?? '',
			);
		}
	}
}
if ( ! empty( $key_points ) ) : ?>
	<section class="olx-summary" id="about" aria-labelledby="summary-title">
		<div>
			<p>KEY POINTS</p>
			<h2 id="summary-title">핵심 포인트</h2>
		</div>
		<ul>
			<?php foreach ( $key_points as $n => $kp ) : ?>
				<li>
					<i><?php echo esc_html( str_pad( (string) ( $n + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></i>
					<span><b><?php echo esc_html( $kp['title'] ); ?></b><?php echo esc_html( $kp['desc'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<section class="olx-section" id="building-info">
	<div class="olx-section-head">
		<div><p class="olx-eyebrow">BUILDING INFO</p><h2>빌딩 정보</h2></div>
		<p>건물과 입지를 판단하는 핵심 정보만 정리했습니다.</p>
	</div>
	<div class="olx-bldinfo">
		<div class="olx-specs olx-bldinfo-specs">
			<?php
			// [listing-detail-ux-pass4, 요청 재조정] 표기 순서: 주소·건물명 / 권역·교통 / 건물규모·연면적 /
			// 사용승인일·기준층면적 / 엘리베이터·주차 / 방향·주변인프라(정확히 2열 6행). 지난 라운드엔
			// 주소가 좁은 칸(약 260px)에서 줄바꿈되는 문제를 grid-column:1/-1(전체 폭)로 풀었는데,
			// 이번엔 "전체적으로 한 칸씩 땡겨서 2열 6행"으로 되돌려 달라는 요청이라 정상적인 짝(주소+
			// 건물명 한 행)으로 복귀한다 - 대신 줄바꿈 문제는 이 행(row-address)만 라벨 폭을 좁히고
			// (82px -> 44px) 값 글자를 살짝 줄여서 완화한다(officeleasing.css). 다만 이건 근본적으로
			// "칸 폭 안에서 최대한 줄이는" 미봉책이라, 매우 긴 도로명주소는 여전히 2줄로 넘어갈 수
			// 있다 - 완전한 보장은 지난 라운드의 전체 폭 방식뿐이었다는 점을 명확히 알려드린다.
			?>
			<div class="row row-address"><span>주소</span><b><?php echo esc_html( $address_road ); ?></b></div>
			<div class="row"><span>건물명</span><b><?php echo esc_html( $building_name ); ?></b></div>
			<div class="row"><span>권역</span><b><?php echo esc_html( trim( olt_region_label( $region_code ) . ( $district ? ' · ' . $district : '' ) ) ); ?></b></div>
			<div class="row"><span>교통</span><b class="olx-transit">
				<?php
				for ( $s = 1; $s <= 2; $s++ ) {
					$st = get_field( 'building_subway' . $s . '_station', $building_id );
					if ( ! $st ) {
						continue;
					}
					$ln = get_field( 'building_subway' . $s . '_line', $building_id );
					printf(
						'<span><i style="background:%s">%s</i>%s</span>',
						esc_attr( olt_line_color( $ln ) ),
						esc_html( olt_line_badge( $ln ) ),
						esc_html( $st )
					);
				}
				?>
			</b></div>
			<?php
			// 주차: 관리자가 이미 "대"를 포함해 입력했을 수 있어(예: "464대(지상92/지하372)") 무조건
			// 이어붙이면 "대대"가 될 수 있다 - 문자열에 "대"가 없을 때만 단위를 붙인다.
			$parking = get_field( 'building_parking', $building_id );
			if ( $parking && false === mb_strpos( (string) $parking, '대' ) ) {
				$parking .= '대';
			}
			$completion = get_field( 'building_completion_date', $building_id );
			$elevator   = get_field( 'building_elevator_count', $building_id );
			// 연면적/기준층 면적: 둘 다 이미 있던 ACF 필드다(building_total_area_*, 사이드바 count>3
			// 케이스의 specs3가 기준층 면적을 이미 쓰고 있었다) - 새 필드 추가 없이 표시만 추가한다.
			// [listing-detail-ux-pass4] 평 값을 <b>에, ㎡는 <small>로 분리한다("괄호 삭제, 제곱미터만
			// 볼드 제거·차콜색" 요청) - 이전엔 "1,200평 3,966.9㎡"처럼 한 덩어리 문자열이라 좁은 칸에서
			// ㎡ 부분이 다음 줄로 넘어갈 수 있었다. row-nowrap 클래스(officeleasing.css)로 이 두 행만
			// 줄바꿈을 막는다 - 값 자체는 평/㎡ 둘 다 esc_html() 처리 후 조립하므로 안전.
			$total_area_html = '';
			$pyeong_total = olt_pyeong( get_field( 'building_total_area_pyeong', $building_id ) );
			if ( $pyeong_total ) {
				$sqm_total = olt_sqm( get_field( 'building_total_area_sqm', $building_id ) );
				$total_area_html = esc_html( $pyeong_total ) . ( $sqm_total ? ' <small>' . esc_html( $sqm_total ) . '</small>' : '' );
			}
			$standard_floor_area_html = '';
			$pyeong_standard = olt_pyeong( get_field( 'building_standard_floor_area_pyeong', $building_id ) );
			if ( $pyeong_standard ) {
				$sqm_standard = olt_sqm( get_field( 'building_standard_floor_area_sqm', $building_id ) );
				$standard_floor_area_html = esc_html( $pyeong_standard ) . ( $sqm_standard ? ' <small>' . esc_html( $sqm_standard ) . '</small>' : '' );
			}
			$rows = array(
				'건물 규모'   => array( 'text' => olt_format_building_scale( $basement_floors, $ground_floors ) ),
				'연면적'      => array( 'html' => $total_area_html, 'nowrap' => true ),
				'사용승인일'  => array( 'text' => $completion ? date_i18n( 'Y.m.d', strtotime( $completion ) ) : '' ),
				'기준층 면적' => array( 'html' => $standard_floor_area_html, 'nowrap' => true ),
				'엘리베이터'  => array( 'text' => $elevator ? $elevator . '대' : '' ),
				'주차'        => array( 'text' => $parking ),
				'방향'        => array( 'text' => get_field( 'building_orientation', $building_id ) ),
				'주변 인프라' => array( 'text' => get_field( 'building_nearby_infra', $building_id ) ),
				// [listing-detail-ux-pass3, 리뷰 반영] 원 요청은 "임대정보 섹션"에 넣는 것이었지만,
				// 그 섹션은 활성 매물이 1건일 때만 표(.row) 형태로 렌더되고 2건 이상/0건이면 카드
				// 그리드나 안내 문구로 바뀌어 표 자체가 없다 - 용도/냉난방방식을 거기 두면 매물이
				// 1건이 아닌 빌딩에서는 화면에 아예 안 보이는데, schema.php는 매물 수와 무관하게
				// 값이 있으면 항상 additionalProperty에 넣고 있어 "화면에 없는데 구조화 데이터엔
				// 있다"는 불일치가 생겼다(GPT/Codex 교차 리뷰 공통 지적, P1). 이 두 필드는 애초에
				// 매물이 아니라 건물 자체의 물리적 속성(방향/주차/엘리베이터와 동일 성격)이라, 항상
				// 렌더되는 이 빌딩정보 표로 옮기면 매물 개수(0/1/2~3/4+)와 무관하게 화면·schema가
				// 항상 일치한다 - 화면 위치만 바뀔 뿐 ACF 필드/schema 로직은 그대로다.
				'용도'        => array( 'text' => get_field( 'building_usage_type', $building_id ) ),
				'냉난방방식'  => array( 'text' => get_field( 'building_hvac_type', $building_id ) ),
			);
			foreach ( $rows as $label => $row ) {
				$content = isset( $row['html'] ) ? $row['html'] : esc_html( $row['text'] ?? '' );
				if ( '' === $content ) {
					continue;
				}
				$class = 'row' . ( ! empty( $row['nowrap'] ) ? ' row-nowrap' : '' );
				printf( '<div class="%s"><span>%s</span><b>%s</b></div>', esc_attr( $class ), esc_html( $label ), $content );
			}
			?>
		</div>
		<?php
		// 빌딩 자체 사진(building_image_1~8) - 매물 Hero 갤러리와 별개로 항상 렌더한다.
		// [버그 수정] 이전엔 Hero(.olx-hero)만 매물 사진 우선/빌딩 사진 폴백으로 노출했기 때문에,
		// 매물에 사진을 올린 순간 빌딩 자체 사진은 페이지 어디에도 안 보이게 됐다 - 이 갤러리가
		// 그 사진의 상시 노출 자리다. 크기는 Hero 썸네일 스트립과 동일한 ol-interior(600x400)로 통일.
		$building_gallery = olt_collect_images( $building_id, 'building_image_', 8, 'ol-interior' );
		// [listing-detail-ux-pass4/5] "그림을 표에 맞추지 말고, 표를 사진 4장 구조 높이에 맞춰줘" 요청 -
		// 사진은 고정 가로비(aspect-ratio:1.3, officeleasing.css)의 2×2 구조로 두고, 왼쪽 표
		// (.olx-bldinfo-specs)에 그 높이만큼 max-height(420px) + overflow-y:auto를 줘서 표가 사진
		// 높이를 따라가게 한다(내용이 넘치면 표 안에서 스크롤). [pass5] 처음 aspect-ratio:1.6·
		// max-height:340px 조합에서 표 쪽에 세로 스크롤바가 실제로 생겼다는 피드백을 받아, 사진 비율을
		// 세로로 더 키우고(1.6->1.3) max-height도 함께 늘렸다(340px->420px) - 여전히 이 열 너비(약
		// 260px) 기준의 근사 고정값이라, 사이드바 레이아웃 너비가 크게 바뀌면 재조정이 필요할 수 있고
		// 건물 정보 항목이 유난히 많이 채워진 경우 스크롤이 다시 나타날 수 있다(overflow-y:auto가
		// 안전장치로 남아있음 - 완벽한 픽셀 일치를 CSS만으로 보장하진 않는다).
		// 사진을 항상 4장(2행)까지만 보이게 고정하는 것도 이 근사가 유효하려면 필요하다 - Hero 썸네일
		// (.olx-gallery-thumbs)이 같은 이유로 최대 4개만 렌더하는 것과 동일한 패턴. ACF엔 여전히 최대 8장 저장
		// 가능하고(위 olt_collect_images 호출은 그대로 8), 화면에 처음 4장만 노출할 뿐이다.
		$building_gallery = array_slice( $building_gallery, 0, 4 );
		if ( ! empty( $building_gallery ) ) : ?>
			<div class="olx-bldinfo-gallery">
				<?php foreach ( $building_gallery as $g ) : ?>
					<div class="olx-bldinfo-gallery-item">
						<?php
						if ( ! empty( $g['id'] ) ) {
							echo wp_get_attachment_image( (int) $g['id'], 'ol-interior', false, array(
								'alt'      => $g['alt'] ? $g['alt'] : $building_name . ' 건물 사진',
								'loading'  => 'lazy',
								'decoding' => 'async',
								'sizes'    => '(max-width: 900px) 45vw, 280px',
							) );
						} else {
							printf(
								'<img src="%s" alt="%s" loading="lazy" decoding="async">',
								esc_url( $g['url'] ),
								esc_attr( $building_name . ' 건물 사진' )
							);
						}
						?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( 1 === $count ) : ?>
	<section class="olx-section" id="lease-info">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">LEASING INFO</p><h2>임대 정보</h2></div>
			<p>임대 조건은 시장 상황과 공실 현황에 따라 변동이 있을 수 있습니다.</p>
		</div>
		<div class="olx-specs olx-lease-specs">
			<div class="row"><span>해당층</span><b><?php echo esc_html( get_field( 'floor_display', $primary_id ) ); ?></b></div>
			<div class="row"><span>입주가능일</span><b><?php
				echo esc_html( olt_format_move_in( get_field( 'move_in_type', $primary_id ), get_field( 'move_in_date', $primary_id ) ) );
			?></b></div>
			<?php
			// [listing-detail-ux-pass4] 임대면적/전용면적 두 행을 Hero와 같은 면적 슬라이더로 교체
			// (요청: "임대정보 섹션에서도 임대면적과 전용면적에 ㅇㅡㅇㅡㅇ 구조 평수 슬라이드").
			// 매물 1건이라 실제 토글 대상은 없어 점 하나만 표시되지만, Hero와 동일한 시각 언어를 쓴다.
			?>
			<div class="olx-lease-area-slider"><?php echo olt_area_slider( $area_slider_stop_single, 0 ); ?></div>
			<div class="row"><span>보증금</span><b class="accent"><?php echo esc_html( olt_won( get_field( 'deposit_amount', $primary_id ) ) ); ?> <small>임대평당 <?php echo esc_html( olt_pyeong_price( get_field( 'deposit_per_lease_pyeong', $primary_id ) ) ); ?></small></b></div>
			<div class="row"><span>임대료</span><b class="accent"><?php echo esc_html( olt_won( get_field( 'monthly_rent', $primary_id ) ) ); ?> <small>임대평당 <?php echo esc_html( olt_pyeong_price( get_field( 'rent_per_lease_pyeong', $primary_id ) ) ); ?></small></b></div>
			<div class="row"><span>관리비</span><b class="accent"><?php echo esc_html( olt_won( get_field( 'maintenance_fee', $primary_id ) ) ); ?> <small>임대평당 <?php echo esc_html( olt_pyeong_price( get_field( 'maintenance_per_lease_pyeong', $primary_id ) ) ); ?></small></b></div>
			<div class="row"><span>환산임대료</span><b>전용평당 <?php echo esc_html( olt_pyeong_price( get_field( 'noc_per_exclusive_pyeong', $primary_id ) ) ); ?> <small>NOC</small></b></div>
		</div>
	</section>
<?php elseif ( $count >= 2 && $count <= 3 ) : ?>
	<section class="olx-section" id="lease-info">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">LEASING INFO</p><h2>임대 가능 매물 <?php echo esc_html( (string) $count ); ?>건</h2></div>
			<p>위 또는 아래에서 면적을 선택하면 조건이 함께 바뀝니다.</p>
		</div>
		<?php
		// [listing-detail-ux-pass4] Hero와 동일한 면적 슬라이더를 여기도 둔다(요청: "임대정보
		// 섹션에서도... 슬라이드 넣어서") - Hero 것과 데이터/인덱스가 동일해서 어느 쪽을 조작해도
		// single.js의 select(index)가 둘 다(그리고 아래 카드 하이라이트까지) 함께 갱신한다.
		?>
		<div class="olx-lease-area-slider"><?php echo olt_area_slider( $area_slider_stops, $area_slider_default_index ); ?></div>
		<div class="olx-rel" id="olx-toggle-cards">
			<?php foreach ( $listings as $i => $l ) : ?>
				<div class="olx-toggle-card <?php echo $area_slider_default_index === $i ? 'is-active' : ''; ?>" data-listing-index="<?php echo esc_attr( (string) $i ); ?>">
					<?php get_template_part( 'template-parts/listing-card', null, array( 'listing_id' => $l->ID ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
<?php elseif ( $count > 3 ) : ?>
	<section class="olx-section" id="lease-info">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">LEASING INFO</p><h2>임대 가능 매물 <?php echo esc_html( (string) $count ); ?>건</h2></div>
			<p>이 빌딩에서 임대 가능한 매물입니다.</p>
		</div>
		<div class="olx-rel">
			<?php foreach ( $listings as $l ) {
				get_template_part( 'template-parts/listing-card', null, array( 'listing_id' => $l->ID ) );
			} ?>
		</div>
	</section>
<?php else : ?>
	<section class="olx-section" id="lease-info">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">LEASING INFO</p><h2>임대 정보</h2></div>
		</div>
		<p class="olx-search-message">현재 임대 가능한 매물이 없습니다. 유사 빌딩을 아래에서 확인하시거나 문의해 주세요.</p>
	</section>
<?php endif; ?>

<?php
// [listing-detail-ux-pass3] "AT A GLANCE"(Leasing Point) 섹션 전체 삭제 요청 - building_location_summary/
// building_transportation_summary/building_feature_summary/building_recommended_tenant_summary ACF
// 필드 자체도 group_ol_building.json에서 삭제했다(admin-hidden-fields.php/admin-summary-box.php/
// calculations.php의 ol_sync_aio_status()/save-hooks.php 호출부/schema.php의 description·additionalProperty
// 참조까지 전부 함께 정리 - README-ACF.md 참고). 이 섹션에 있던 체크리스트 안내 링크는 Contact 카드에
// 이미 동일한 목적의 "OFFICE LEASING CHECKLIST" 버튼이 있어(template-parts/contact-cta.php) 중복 없이
// 그대로 대체된다.
?>

<?php
// ── 추천 매물: 같은 권역(동)의 다른 빌딩 매물 ──
$related = array();
if ( $child_term ) {
	$related = get_posts( array(
		'post_type'      => 'listing',
		'posts_per_page' => 6,
		'post__not_in'   => wp_list_pluck( $listings, 'ID' ),
		'tax_query'      => array(
			array(
				'taxonomy' => 'office_region',
				'field'    => 'term_id',
				'terms'    => $child_term->term_id,
			),
		),
		'meta_query'     => array(
			array( 'key' => 'listing_status', 'value' => olt_public_listing_statuses(), 'compare' => 'IN' ),
		),
	) );
}
if ( ! empty( $related ) ) : ?>
	<section class="olx-section" id="related-listings">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">RELATED LISTINGS</p><h2>추천 매물</h2></div>
			<p>비슷한 조건의 매물 비교해보세요.</p>
		</div>
		<div class="olx-rel">
			<?php foreach ( $related as $r ) {
				get_template_part( 'template-parts/listing-card', null, array( 'listing_id' => $r->ID ) );
			} ?>
		</div>
	</section>
<?php endif; ?>

<?php
// ── FAQ: building_faq_q1~5 / a1~5 ──
$faqs = array();
for ( $i = 1; $i <= 5; $i++ ) {
	$q = get_field( 'building_faq_q' . $i, $building_id );
	$a = get_field( 'building_faq_a' . $i, $building_id );
	if ( $q && $a ) {
		$faqs[] = array( 'q' => $q, 'a' => $a );
	}
}
if ( ! empty( $faqs ) ) : ?>
	<section class="olx-section olx-faq-section" id="insight">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">FAQ</p><h2>계약 전 자주 묻는 질문</h2></div>
			<p>기업 이전 담당자가 가장 먼저 확인하는 내용을 담았습니다.</p>
		</div>
		<div class="olx-faq">
			<?php foreach ( $faqs as $n => $faq ) : ?>
				<details <?php echo 0 === $n ? 'open' : ''; ?>>
					<summary><span><?php echo esc_html( str_pad( (string) ( $n + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $faq['q'] ); ?><i></i></summary>
					<div><?php echo esc_html( $faq['a'] ); ?></div>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php
// 세부지역/권역 링크는 별도 nav가 아니라 Contact 카드 안으로 이동했다(전체 사무실 매물 링크는 삭제).
// [listing-detail-ux-pass3] 라벨 문구 변경 요청: "~ 사무실 임대" -> "~ 사무실 더보기".
$district_link = $child_term ? array(
	'label' => $district . ' 사무실 더보기',
	'url'   => get_term_link( $child_term ),
) : null;
$region_link = $parent_term ? array(
	'label' => preg_replace( '/\(.+\)/', '', olt_region_label( $region_code ) ) . ' 사무실 더보기',
	'url'   => get_term_link( $parent_term ),
) : null;

get_template_part( 'template-parts/contact-cta', null, array(
	// [listing-detail-ux-pass5] 배너형 재구성과 함께 문구도 새로 요청됨(참고 이미지).
	'title'         => '사무실 임대차, 한번 더 확인하세요!',
	'district_link' => $district_link,
	'region_link'   => $region_link,
) );

get_footer();
