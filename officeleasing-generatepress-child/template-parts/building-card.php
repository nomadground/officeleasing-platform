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

// 아래 값들은 모두 building-cache.php가 매물 저장/상태변경 시 미리 계산해둔 캐시 필드다.
// 카드 렌더 시 매물을 재쿼리하지 않는다는 원칙(상단 주석)을 지키기 위해, 매물이 여러 건이어도
// 이 캐시 min/max만으로 "3~7층" 같은 범위 표기를 만든다(olt_floor_range/olt_format_range 참고).
$floor_display = olt_floor_range(
	get_field( 'building_min_floor', $building_id ),
	get_field( 'building_max_floor', $building_id )
);
// listing-card.php의 "해당층 / 총층F" 표기와 동일하게, 건물 총 지상층수를 뒤에 붙인다.
// building_ground_floors는 빌딩 자체 필드라 매물 재쿼리 없이 바로 읽을 수 있다.
$total_floors = (int) get_field( 'building_ground_floors', $building_id );
if ( $floor_display && $total_floors ) {
	$floor_display .= ' / ' . $total_floors . 'F';
}

// 임대/전용면적은 listing-card.php와 동일하게 ㎡(큰 숫자) + 평(괄호, 보조) 둘 다 보여준다.
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
// NOC(전용평당 환산임대료)는 임대료·관리비를 합쳐 면적당으로 정규화한 "비교 지표"라 보증금/임대료/
// 관리비(실제 비용 항목)와 성격이 다르다 - 같은 칩 줄에 나란히 놓으면 "네 번째 비용"처럼 보여
// 오해를 살 수 있어, 가격 칩 아래 별도의 보조 지표 줄로 분리한다. olt_won() 대신 올림평당가 표기
// (olt_pyeong_price, "51.1만원" 형식)를 쓴다 - single-building.php 임대정보표의 "환산임대료" 표기와 동일.
$noc_range = olt_format_money_range(
	get_field( 'building_min_noc', $building_id ),
	get_field( 'building_max_noc', $building_id ),
	'olt_pyeong_price'
);
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
		<h3><?php echo esc_html( $building_name ); ?></h3>
		<?php if ( $floor_display || $lease_areas['sqm'] || $exclusive_areas['sqm'] ) : ?>
			<div class="olx-card-areas olx-card-areas--building">
				<?php if ( $floor_display ) : ?>
					<span class="olx-card-floor"><?php echo esc_html( $floor_display ); ?></span>
				<?php endif; ?>
				<?php if ( $lease_areas['sqm'] ) : ?>
					<span>
						<b>임대</b>
						<strong><?php echo esc_html( $lease_areas['sqm'] ); ?></strong>
						<?php if ( $lease_areas['pyeong'] ) : ?><small><?php echo esc_html( $lease_areas['pyeong'] ); ?></small><?php endif; ?>
					</span>
				<?php endif; ?>
				<?php if ( $exclusive_areas['sqm'] ) : ?>
					<span>
						<b>전용</b>
						<strong><?php echo esc_html( $exclusive_areas['sqm'] ); ?></strong>
						<?php if ( $exclusive_areas['pyeong'] ) : ?><small><?php echo esc_html( $exclusive_areas['pyeong'] ); ?></small><?php endif; ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $address ) : ?>
			<p><?php echo esc_html( $address ); ?></p>
		<?php endif; ?>
		<?php if ( $deposit_range || $rent_range || $maintenance_range ) : ?>
			<div class="olx-card-prices olx-card-prices--building">
				<?php if ( $deposit_range ) : ?><span><i class="chip-deposit">보</i><span class="olx-money-group"><?php echo olt_won_html( $deposit_range ); ?></span></span><?php endif; ?>
				<?php if ( $rent_range ) : ?><span><i class="chip-rent">월</i><span class="olx-money-group"><?php echo olt_won_html( $rent_range ); ?></span></span><?php endif; ?>
				<?php if ( $maintenance_range ) : ?><span><i class="chip-maintenance">관</i><span class="olx-money-group"><?php echo olt_won_html( $maintenance_range ); ?></span></span><?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $noc_range ) : ?>
			<p class="olx-card-noc">전용평당 NOC <b><?php echo esc_html( $noc_range ); ?></b></p>
		<?php endif; ?>
	</div>
</a>
