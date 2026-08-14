<?php
/**
 * 빌딩 카드 (재사용 컴포넌트) — 목록/허브에서 사용.
 * listing-card.php(매물 1건)와 달리, 이 카드는 빌딩 1개 + 소속 매물 "집계값"을 보여준다.
 * 집계값은 building의 캐시 필드(building-cache.php가 채움)만 읽는다 — 카드 렌더 시 매물 재쿼리 금지.
 *
 * 호출: get_template_part( 'template-parts/building-card', null, array( 'building_id' => $id ) );
 *
 * $args (모두 선택):
 *   'building_id' int   대상 빌딩 (없으면 루프의 현재 글)
 *   'context'     string 렌더 위치. 'home'이면 modifier 클래스만 추가된다(데이터 구조는 동일).
 *   'eager'       bool  true면 loading="eager" (첫 화면에 즉시 보이는 카드에만). 기본은 lazy.
 */
defined( 'ABSPATH' ) || exit;

$building_id = isset( $args['building_id'] ) ? (int) $args['building_id'] : get_the_ID();
if ( ! $building_id ) {
	return;
}

$context     = isset( $args['context'] ) ? sanitize_html_class( (string) $args['context'] ) : '';
$is_eager    = ! empty( $args['eager'] );
$card_class  = 'olx-card olx-bcard' . ( $context ? ' olx-bcard--' . $context : '' );

$building_name = get_the_title( $building_id );
$building_link = get_permalink( $building_id );
$address       = get_field( 'building_address_road', $building_id );

$regions     = olt_get_region_terms( $building_id );
$region_code = $regions['parent'] ? $regions['parent']->name : '';
$district    = $regions['child'] ? $regions['child']->name : '';

$line    = get_field( 'building_subway1_line', $building_id );
$station = get_field( 'building_subway1_station', $building_id );

$images = olt_collect_images( $building_id, 'building_image_', 8, 'ol-building-thumb' );
$img    = $images[0] ?? null;

$active_count = (int) get_field( 'building_active_listing_count', $building_id );

// 개별 매물 층수 범위("3~7층" 등)는 이 카드에서 빼기로 했다(빌딩 카드는 매물 여러 건의 "집계"라
// 범위가 넓어질수록 정보 가치가 낮음 - 개별 매물의 정확한 층수는 single-building.php/listing-card.php에서
// 계속 보여준다). 대신 건물명 옆 남는 자리에 건물 총 층수만 작게 보여준다 - building_ground_floors는
// 빌딩 자체 필드라 매물 재쿼리 없이 바로 읽을 수 있다.
$total_floors = (int) get_field( 'building_ground_floors', $building_id );

// 임대/전용면적은 평(큰 숫자, 주표기) + ㎡(괄호, 보조) 둘 다 보여준다 - 평 쪽 숫자가 ㎡보다 짧아서
// (예: "534평" vs "1,765.3㎡") 카드 폭을 덜 잡아먹는다.
// 캐시엔 평 min/max만 있으므로, 렌더 시점에 olt_format_area_sqm_pyeong()(theme-helpers.php)이
// ol_calc_sqm_from_pyeong()(Core, 순수 변환 함수)으로 ㎡를 환산해준다 - 별도 building_min/max_*_sqm
// 캐시 필드를 새로 만들지 않는다(단순 단위 변환이라 캐시 시점의 min/max 관계가 sqm으로 바꿔도 그대로
// 유지되므로 안전, 매물 재쿼리도 없음).
$lease_areas     = olt_format_area_sqm_pyeong(
	get_field( 'building_min_lease_area_pyeong', $building_id ),
	get_field( 'building_max_lease_area_pyeong', $building_id )
);
$exclusive_areas = olt_format_area_sqm_pyeong(
	get_field( 'building_min_exclusive_area_pyeong', $building_id ),
	get_field( 'building_max_exclusive_area_pyeong', $building_id )
);
// 보증금/임대료/관리비는 0이 실제 유효값일 수 있어(캐시가 -1로 "데이터 없음"을 구분) 면적용
// olt_format_range()가 아니라 olt_format_money_range()를 쓴다 - "0원~50만원"이 "50만원"으로,
// "0원~0원"이 빈 문자열로 잘못 나오던 문제(2차 리뷰 지적)가 여기 있었다.
$deposit_range = olt_format_money_range(
	get_field( 'building_min_deposit', $building_id ),
	get_field( 'building_max_deposit', $building_id ),
	'olt_won'
);
$rent_range = olt_format_money_range(
	get_field( 'building_min_rent', $building_id ),
	get_field( 'building_max_rent', $building_id ),
	'olt_won'
);
$maintenance_range = olt_format_money_range(
	get_field( 'building_min_maintenance_fee', $building_id ),
	get_field( 'building_max_maintenance_fee', $building_id ),
	'olt_won'
);
// [Home 시안 라운드] "전용평당 NOC는 매물카드에서는 삭제해줘" 요청으로 이 카드(빌딩 집계 카드)에서는
// NOC 줄 자체를 뺐다 - building_min_noc/max_noc 필드 조회도 함께 제거(더 이상 이 카드에서 안 쓰임).
// single-building.php 상세페이지의 NOC 표시는 이 요청과 무관해 그대로 유지된다.
?>
<a class="<?php echo esc_attr( $card_class ); ?>" href="<?php echo esc_url( $building_link ); ?>">
	<div class="olx-card-img">
		<?php
		if ( $img ) {
			$alt = $img['alt'] ? $img['alt'] : $building_name . ' 외관';
			$attr = array(
				'alt'      => $alt,
				'decoding' => 'async',
				'loading'  => $is_eager ? 'eager' : 'lazy',
				// 카드 실폭: 데스크탑 4열 ≈ 272px, 모바일 2열 ≈ 화면의 45%.
				// 모바일에 데스크탑용 대형 이미지가 내려가지 않도록 명시한다.
				'sizes'    => '(max-width: 700px) 45vw, 280px',
			);
			if ( ! empty( $img['id'] ) ) {
				// 첨부 ID가 있으면 wp_get_attachment_image()로 출력한다 -
				// srcset/width/height가 자동으로 붙어 CLS와 모바일 과다전송을 함께 막는다(원본 직접 출력 금지 원칙).
				// ol-building-thumb(480x640 hard crop) - docs/IMAGE_PERFORMANCE_GUIDELINES.md 기준.
				echo wp_get_attachment_image( (int) $img['id'], 'ol-building-thumb', false, $attr );
			} elseif ( ! empty( $img['url'] ) ) {
				printf(
					'<img src="%s" alt="%s" loading="%s" decoding="async">',
					esc_url( $img['url'] ),
					esc_attr( $alt ),
					esc_attr( $attr['loading'] )
				);
			}
		}
		?>
		<?php if ( $active_count > 0 ) : ?>
			<span class="olx-card-status"><i></i>매물 <?php echo esc_html( (string) $active_count ); ?>건</span>
		<?php endif; ?>
	</div>
	<div class="olx-card-body">
		<small class="olx-card-meta">
			<?php if ( $region_code ) : ?>
				<span class="olx-card-region <?php echo esc_attr( olt_region_class( $region_code ) ); ?>"><?php echo esc_html( olt_region_short_code( $region_code ) ); ?></span>
			<?php endif; ?>
			<?php if ( $district ) : ?>
				<span class="olx-card-district"><?php echo esc_html( $district ); ?></span>
			<?php endif; ?>
			<?php if ( $station ) : ?>
				<span class="olx-card-transit"><i style="background:<?php echo esc_attr( olt_line_color( $line ) ); ?>"><?php echo esc_html( olt_line_badge( $line ) ); ?></i><?php echo esc_html( $station ); ?></span>
			<?php endif; ?>
		</small>
		<h3>
			<?php echo esc_html( $building_name ); ?>
			<?php if ( $total_floors ) : ?><span class="olx-card-floors-total"><?php echo esc_html( (string) $total_floors ); ?>F</span><?php endif; ?>
		</h3>
		<?php if ( $address ) : ?>
			<p><?php echo esc_html( $address ); ?></p>
		<?php endif; ?>
		<?php if ( $lease_areas['pyeong'] || $exclusive_areas['pyeong'] ) : ?>
			<?php
			// [Home 시안 라운드] "임대/전용 텍스트 색상을 매물 상세페이지 색상이랑 맞추고, 가로직사각형
			// 박스 아이콘 안에 흰색 글씨로 넣어줘" - single-building.php의 면적 슬라이더/표(임대=브랜드
			// 파랑, 전용=서브 주황)와 같은 색 규칙을 쓰고, 라벨은 보증금/임대료/관리비 칩(<i>)과 같은
			// 패턴의 사각 배지로 바꾼다(정사각형 아이콘 대신 2글자가 들어가는 가로로 넓은 배지).
			?>
			<div class="olx-card-areas olx-card-areas--building">
				<?php if ( $lease_areas['pyeong'] ) : ?>
					<span>
						<i class="olx-area-chip-lease">임대</i>
						<strong class="olx-area-lease"><?php echo esc_html( $lease_areas['pyeong'] ); ?></strong>
						<?php if ( $lease_areas['sqm'] ) : ?><small>(<?php echo esc_html( $lease_areas['sqm'] ); ?>)</small><?php endif; ?>
					</span>
				<?php endif; ?>
				<?php if ( $exclusive_areas['pyeong'] ) : ?>
					<span>
						<i class="olx-area-chip-excl">전용</i>
						<strong class="olx-area-excl"><?php echo esc_html( $exclusive_areas['pyeong'] ); ?></strong>
						<?php if ( $exclusive_areas['sqm'] ) : ?><small>(<?php echo esc_html( $exclusive_areas['sqm'] ); ?>)</small><?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $deposit_range || $rent_range || $maintenance_range ) : ?>
			<?php
			// [Home 시안 라운드] "만원 만원 만원 같은 열로 정렬해줘, 지금은 줄이 안 맞아서 이상해" -
			// 보증금/임대료/관리비 세 행이 하나의 CSS 그리드(4열: 아이콘/최소값/물결표/최대값)를 공유해서,
			// 행마다 자릿수가 달라도 "만원" 위치가 항상 같은 세로줄에 맞는다(olt_won_html_cells() 참고).
			?>
			<div class="olx-card-prices olx-card-prices--building">
				<?php if ( $deposit_range ) :
					list( $deposit_min, $deposit_tilde, $deposit_max ) = olt_won_html_cells( $deposit_range );
					?>
					<i class="chip-deposit">보</i>
					<span class="olx-money-min"><?php echo $deposit_min; ?></span>
					<span class="olx-money-tilde"><?php echo esc_html( $deposit_tilde ); ?></span>
					<span class="olx-money-max"><?php echo $deposit_max; ?></span>
				<?php endif; ?>
				<?php if ( $rent_range ) :
					list( $rent_min, $rent_tilde, $rent_max ) = olt_won_html_cells( $rent_range );
					?>
					<i class="chip-rent">월</i>
					<span class="olx-money-min"><?php echo $rent_min; ?></span>
					<span class="olx-money-tilde"><?php echo esc_html( $rent_tilde ); ?></span>
					<span class="olx-money-max"><?php echo $rent_max; ?></span>
				<?php endif; ?>
				<?php if ( $maintenance_range ) :
					list( $maintenance_min, $maintenance_tilde, $maintenance_max ) = olt_won_html_cells( $maintenance_range );
					?>
					<i class="chip-maintenance">관</i>
					<span class="olx-money-min"><?php echo $maintenance_min; ?></span>
					<span class="olx-money-tilde"><?php echo esc_html( $maintenance_tilde ); ?></span>
					<span class="olx-money-max"><?php echo $maintenance_max; ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php // [Home 시안 라운드] "전용평당 NOC는 매물카드에서는 삭제해줘" - 이 카드(빌딩 집계 카드)에서만
		// 뺀다. single-building.php 상세페이지의 NOC 표시는 이 요청과 무관해 그대로 둔다. ?>
	</div>
</a>
