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
			?>
			<?php
			// [버그 수정] 썸네일은 최대 4개만 렌더(디자인 그리드 4열 고정)되므로, 인덱스 총계도
			// 실제 갤러리 전체 개수(최대 8)가 아니라 "클릭 가능한 썸네일 수"와 일치시킨다.
			// 안 그러면 초기 "01/06"이 썸네일 클릭 후 JS가 계산하는 "02/04"와 어긋난다.
			$thumb_total = max( 1, min( count( $gallery ), 4 ) );
			?>
			<span class="olx-image-index">01 / <?php echo esc_html( str_pad( (string) $thumb_total, 2, '0', STR_PAD_LEFT ) ); ?></span>
			<?php if ( ! empty( $gallery_captions[0] ) ) : ?><span class="olx-image-caption"><?php echo esc_html( $gallery_captions[0] ); ?></span><?php endif; ?>
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
			<div class="olx-specs3">
				<div>
					<span>해당층 / 총층</span>
					<strong><?php echo esc_html( get_field( 'floor_display', $primary_id ) ); ?><?php echo $total_floors ? ' / ' . esc_html( $total_floors ) . 'F' : ''; ?></strong>
				</div>
				<div>
					<span>임대면적</span>
					<strong><?php echo esc_html( olt_sqm( get_field( 'lease_area_sqm', $primary_id ) ) ); ?></strong>
					<small><?php echo esc_html( olt_pyeong( get_field( 'lease_area_pyeong', $primary_id ) ) ); ?></small>
				</div>
				<div>
					<span>전용면적</span>
					<strong><?php echo esc_html( olt_sqm( get_field( 'exclusive_area_sqm', $primary_id ) ) ); ?></strong>
					<small><?php echo esc_html( olt_pyeong( get_field( 'exclusive_area_pyeong', $primary_id ) ) ); ?></small>
				</div>
			</div>
			<div class="olx-price">
				<div>
					<span>보증금</span>
					<strong><?php echo esc_html( olt_won( get_field( 'deposit_amount', $primary_id ) ) ); ?></strong>
					<em>전용평당 <?php echo esc_html( olt_pyeong_price( get_field( 'deposit_per_exclusive_pyeong', $primary_id ) ) ); ?></em>
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
		<p class="olx-cta-note">공실 현황 · 조건 협의 · 면적 분할/확장까지 전문 중개사가 확인합니다.</p>
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
			<div class="row"><span>건물명</span><b><?php echo esc_html( $building_name ); ?></b></div>
			<div class="row"><span>주소</span><b><?php echo esc_html( $address_road ); ?></b></div>
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
			$rows = array(
				'주변 인프라' => get_field( 'building_nearby_infra', $building_id ),
				'건물 규모'   => olt_format_building_scale( $basement_floors, $ground_floors ),
				'방향'        => get_field( 'building_orientation', $building_id ),
				'주차'        => $parking,
			);
			$completion = get_field( 'building_completion_date', $building_id );
			$elevator   = get_field( 'building_elevator_count', $building_id );
			foreach ( $rows as $label => $val ) {
				if ( $val ) {
					printf( '<div class="row"><span>%s</span><b>%s</b></div>', esc_html( $label ), esc_html( $val ) );
				}
			}
			if ( $completion ) {
				printf( '<div class="row"><span>사용승인일</span><b>%s</b></div>', esc_html( date_i18n( 'Y.m.d', strtotime( $completion ) ) ) );
			}
			if ( $elevator ) {
				printf( '<div class="row"><span>엘리베이터</span><b>%s대</b></div>', esc_html( $elevator ) );
			}
			?>
		</div>
		<?php
		// 빌딩 자체 사진(building_image_1~8) - 매물 Hero 갤러리와 별개로 항상 렌더한다.
		// [버그 수정] 이전엔 Hero(.olx-hero)만 매물 사진 우선/빌딩 사진 폴백으로 노출했기 때문에,
		// 매물에 사진을 올린 순간 빌딩 자체 사진은 페이지 어디에도 안 보이게 됐다 - 이 갤러리가
		// 그 사진의 상시 노출 자리다. 크기는 Hero 썸네일 스트립과 동일한 ol-interior(600x400)로 통일.
		$building_gallery = olt_collect_images( $building_id, 'building_image_', 8, 'ol-interior' );
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

<?php
// ── AIO 요약(입지/교통/특징/추천 입주업종): ACF엔 있지만 지금까지 어느 템플릿에서도 안 쓰던
// 필드였다(저장만 되는 죽은 데이터). 비어있는 항목은 렌더하지 않는다.
$aio_blocks = array(
	'입지'         => get_field( 'building_location_summary', $building_id ),
	'교통'         => get_field( 'building_transportation_summary', $building_id ),
	'특징'         => get_field( 'building_feature_summary', $building_id ),
	'추천 입주업종' => get_field( 'building_recommended_tenant_summary', $building_id ),
);
$aio_blocks = array_filter( $aio_blocks );
if ( ! empty( $aio_blocks ) ) : ?>
	<section class="olx-section" id="building-summary">
		<div class="olx-section-head">
			<div><p class="olx-eyebrow">AT A GLANCE</p><h2><?php echo esc_html( $building_name ); ?> 한눈에 보기</h2></div>
		</div>
		<div class="olx-specs">
			<?php foreach ( $aio_blocks as $label => $text ) : ?>
				<div class="row"><span><?php echo esc_html( $label ); ?></span><b><?php echo esc_html( $text ); ?></b></div>
			<?php endforeach; ?>
		</div>
		<?php
		// 체크리스트 페이지는 아직 별도로 만들어지지 않았다(PROJECT_OVERVIEW.md의 Insight 콘텐츠 계획 참고).
		// 관리자가 나중에 slug "checklist"로 공개(publish) 페이지를 만들면 이 버튼이 자동으로 나타난다 -
		// draft/private/비밀번호 보호 상태일 때는 olt_get_public_page_url()이 숨겨준다.
		$checklist_url = olt_get_public_page_url( 'checklist' );
		if ( $checklist_url ) : ?>
			<a class="olx-inline-link" href="<?php echo esc_url( $checklist_url ); ?>">
				사무실 임대 체크리스트 보기 <span>→</span>
			</a>
		<?php endif; ?>
	</section>
<?php endif; ?>

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
			<div class="row"><span>임대면적</span><b><?php echo esc_html( olt_sqm( get_field( 'lease_area_sqm', $primary_id ) ) . ' ' . olt_pyeong( get_field( 'lease_area_pyeong', $primary_id ) ) ); ?></b></div>
			<div class="row"><span>전용면적</span><b><?php echo esc_html( olt_sqm( get_field( 'exclusive_area_sqm', $primary_id ) ) . ' ' . olt_pyeong( get_field( 'exclusive_area_pyeong', $primary_id ) ) ); ?></b></div>
			<div class="row"><span>보증금</span><b class="accent"><?php echo esc_html( olt_won( get_field( 'deposit_amount', $primary_id ) ) ); ?> <small>전용평당 <?php echo esc_html( olt_pyeong_price( get_field( 'deposit_per_exclusive_pyeong', $primary_id ) ) ); ?></small></b></div>
			<div class="row"><span>임대료</span><b class="accent"><?php echo esc_html( olt_won( get_field( 'monthly_rent', $primary_id ) ) ); ?> <small>임대평당 <?php echo esc_html( olt_pyeong_price( get_field( 'rent_per_lease_pyeong', $primary_id ) ) ); ?></small></b></div>
			<div class="row"><span>관리비</span><b class="accent"><?php echo esc_html( olt_won( get_field( 'maintenance_fee', $primary_id ) ) ); ?> <small>임대평당 <?php echo esc_html( olt_pyeong_price( get_field( 'maintenance_per_lease_pyeong', $primary_id ) ) ); ?></small></b></div>
			<div class="row"><span>환산임대료</span><b>전용평당 <?php echo esc_html( olt_pyeong_price( get_field( 'noc_per_exclusive_pyeong', $primary_id ) ) ); ?> <small>NOC</small></b></div>
		</div>
	</section>
<?php elseif ( $count > 1 ) : ?>
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
$district_link = $child_term ? array(
	'label' => $district . ' 사무실 임대',
	'url'   => get_term_link( $child_term ),
) : null;
$region_link = $parent_term ? array(
	'label' => preg_replace( '/\(.+\)/', '', olt_region_label( $region_code ) ) . ' 사무실 임대',
	'url'   => get_term_link( $parent_term ),
) : null;

get_template_part( 'template-parts/contact-cta', null, array(
	'title'         => $building_name . ', 전문 중개사와 바로 상담하세요',
	'district_link' => $district_link,
	'region_link'   => $region_link,
) );

get_footer();
