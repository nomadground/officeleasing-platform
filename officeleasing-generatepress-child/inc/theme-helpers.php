<?php
/**
 * 테마 전용 헬퍼. 데이터/계산 로직이 아니라 "출력 표현"만 담당한다.
 * 함수 프리픽스는 officeleasing-core 플러그인(ol_)과 충돌하지 않도록 olt_ (OfficeLeasing Theme)를 쓴다.
 * 금액 포맷 등 계산성 함수는 플러그인 helpers.php의 ol_* 함수를 래핑하되,
 * 플러그인이 비활성화된 상태에서 화이트스크린이 나지 않도록 fallback을 둔다.
 */
defined( 'ABSPATH' ) || exit;

/**
 * officeleasing-core 플러그인 + ACF가 둘 다 활성 상태인지.
 * get_field()(ACF)만 보면 안 된다 - officeleasing-core가 꺼져도 ACF는 켜져 있을 수 있고,
 * 그러면 이 체크는 true를 반환하지만 olt_get_building_listings() 같은 플러그인 의존 함수는
 * 여전히 없어서 fatal이 난다. 플러그인 고유 함수(ol_recount_building_cache)까지 함께 확인한다.
 *
 * [주의] region-bar.php는 get_header() 안에서 이 가드보다 먼저 렌더링되므로, 이 함수 자체는
 * 아무것도 막아주지 못한다. region-bar.php가 get_field()를 직접 호출하지 않고 wp_get_object_terms()
 * (코어 함수)만 쓰기 때문에 지금은 우연히 안전한 것 - 앞으로 region-bar.php나 header.php에
 * 플러그인 의존 호출을 추가할 때는 그 안에서 개별적으로 이 함수로 방어해야 한다.
 */
function olt_core_active() {
	return function_exists( 'get_field' ) && function_exists( 'ol_recount_building_cache' );
}

/** core 비활성 안내를 출력한다(템플릿에서 get_footer 전에 호출). */
function olt_render_core_inactive_notice() {
	echo '<section class="olx-section"><p class="olx-search-message">'
		. '데이터 플러그인이 비활성화되어 정보를 표시할 수 없습니다. 잠시 후 다시 시도해 주세요.'
		. '</p></section>';
}

/** 원 단위 정수를 "1,532만원"으로 (억 단위로 쪼개지 않고 항상 만원 콤마표기). 플러그인 함수 우선, 없으면 최소 fallback. */
function olt_won( $won ) {
	if ( function_exists( 'ol_format_manwon' ) ) {
		return ol_format_manwon( (float) $won );
	}
	return number_format( round( (float) $won / 10000 ) ) . '만원';
}

/** 평당가 등 "479.6만원" 표기. */
function olt_pyeong_price( $won ) {
	if ( function_exists( 'ol_format_krw_per_pyeong' ) ) {
		return ol_format_krw_per_pyeong( $won );
	}
	return number_format( (float) $won / 10000, 1 ) . '만원';
}

/**
 * [building-card.php 전용] olt_won()/olt_format_money_range()가 만든 "252,250만원" 또는
 * "252,250만원 ~ 300,000만원" 문자열을 받아, 숫자와 "만원" 단위를 별도 <span>으로 감싼 HTML을 반환한다.
 * 포맷 로직(반올림 등) 자체는 olt_won()에게 그대로 맡기고(재구현하지 않음) 이 함수는 화면 표기(span 분리)만
 * 담당한다 - Schema(schema.php)와 관리자 요약박스는 여전히 ol_format_manwon()/올t_won()의 plain string을
 * 그대로 쓰므로 이 헬퍼를 추가해도 그쪽 출력엔 영향이 없다.
 *
 * 범위값("A만원 ~ B만원")은 "숫자+단위" 한 쌍을 .olx-money-pair 하나로 더 감싼다 - 좁은 모바일 카드에서
 * 긴 범위가 줄바꿈될 때, 줄바꿈이 항상 " ~ " 자리에서만 일어나고 숫자와 "만원" 사이에서 끊기지 않게 하기
 * 위함(CSS의 .olx-money-pair{white-space:nowrap}과 짝).
 *
 * 반환값은 이미 각 부분이 esc_html() 처리된 HTML이므로, 호출부는 esc_html() 없이 그대로 echo하면 된다.
 */
function olt_won_html( $formatted ) {
	$parts      = explode( ' ~ ', (string) $formatted );
	$html_parts = array();
	foreach ( $parts as $part ) {
		if ( preg_match( '/^([\d,]+)(만원)$/u', trim( $part ), $m ) ) {
			$html_parts[] = '<span class="olx-money-pair"><span class="olx-money-num">' . esc_html( $m[1] ) . '</span><span class="olx-money-unit">' . esc_html( $m[2] ) . '</span></span>';
		} else {
			// 예상 밖 형식(빈 문자열 등)이면 새 마크업을 만들지 않고 안전하게 그대로 이스케이프해서 반환.
			$html_parts[] = esc_html( $part );
		}
	}
	return implode( ' ~ ', $html_parts );
}

/** ㎡ 표기: 1081.0㎡ */
function olt_sqm( $value ) {
	return $value ? number_format( (float) $value, 1 ) . '㎡' : '';
}

/**
 * 평 표기: 327평.
 * [listing-detail-ux-pass4] 예전엔 "(327평)"처럼 괄호로 감쌌다 - 요청("자꾸 면적에 평을 괄호안에
 * 넣는데 그렇게 말고")에 따라 괄호를 뺀다. 이 함수를 쓰는 모든 화면(Hero/빌딩정보/임대정보/매물
 * 카드)에 한 번에 적용된다 - 개별 호출부를 따로 고칠 필요 없음.
 */
function olt_pyeong( $value ) {
	return $value ? number_format( (float) $value ) . '평' : '';
}

/** 서울 지하철 노선 색상(원형 뱃지 배경). 목업 .line-* 대신 인라인 style로 임의 노선 대응. */
function olt_line_color( $line ) {
	$map = array(
		'1' => '#0d3692', '2' => '#00a84d', '3' => '#ef7c1c', '4' => '#00a5de',
		'5' => '#996cac', '6' => '#cd7c2f', '7' => '#747f00', '8' => '#e6186c',
		'9' => '#aa9872', 'SB' => '#d31145', 'BD' => '#f5a200', 'SU' => '#f5a200',
		'GJ' => '#77c4a3', 'AR' => '#0090d2', 'GTX-A' => '#9a4d33',
	);
	return isset( $map[ $line ] ) ? $map[ $line ] : '#8d9aa1';
}

/** 노선 뱃지 안에 표시할 짧은 라벨(숫자 노선은 숫자, 그 외는 약자) */
function olt_line_badge( $line ) {
	return is_numeric( $line ) ? $line : substr( (string) $line, 0, 1 );
}

/**
 * 권역 부모 term 이름("강남사무실임대" 등, permalinks.php 마이그레이션 이후의 실제 term->name) ->
 * 화면 라벨 "강남(GBD)". 구 영문 코드("GBD" 등)가 넘어와도 그대로 매핑되도록 둘 다 키로 둔다
 * (마이그레이션 전 캐시/미실행 상태에 대한 방어).
 */
function olt_region_label( $name ) {
	$map = array(
		'강남사무실임대'     => '강남(GBD)',
		'도심권사무실임대'   => '도심권(CBD)',
		'여의도사무실임대'   => '여의도(YBD)',
		'기타권역사무실임대' => '기타권역(ETC)',
		'GBD' => '강남(GBD)',
		'CBD' => '도심권(CBD)',
		'YBD' => '여의도(YBD)',
		'ETC' => '기타권역(ETC)',
	);
	$name = (string) $name;
	if ( isset( $map[ $name ] ) ) {
		return $map[ $name ];
	}
	$upper = strtoupper( $name );
	return isset( $map[ $upper ] ) ? $map[ $upper ] : $name;
}

/**
 * 권역 부모 term 이름 -> 카드 뱃지용 짧은 영문 코드("GBD" 등).
 * 한글 term 이름 자체(예: "강남사무실임대")는 카드 뱃지에 넣기엔 너무 길어 별도로 짧은 코드를 둔다.
 */
function olt_region_short_code( $name ) {
	$map = array(
		'강남사무실임대'     => 'GBD',
		'도심권사무실임대'   => 'CBD',
		'여의도사무실임대'   => 'YBD',
		'기타권역사무실임대' => 'ETC',
	);
	$name = (string) $name;
	if ( isset( $map[ $name ] ) ) {
		return $map[ $name ];
	}
	$upper = strtoupper( $name );
	return in_array( $upper, $map, true ) ? $upper : $name;
}

/** 권역 카드 뱃지 클래스: region-gangnam 등 (한글 term 이름을 안전한 CSS 클래스 slug로) */
function olt_region_class( $name ) {
	$map = array(
		'강남사무실임대'     => 'region-gbd',
		'도심권사무실임대'   => 'region-cbd',
		'여의도사무실임대'   => 'region-ybd',
		'기타권역사무실임대' => 'region-etc',
	);
	if ( isset( $map[ (string) $name ] ) ) {
		return $map[ (string) $name ];
	}
	return 'region-' . strtolower( (string) $name );
}

/** 지하/지상 층수 두 숫자로 "지하 7층 ~ 지상 40층" 문구를 조합 (별도 텍스트 필드 없이 생성) */
function olt_format_building_scale( $basement, $ground ) {
	$basement = (int) $basement;
	$ground   = (int) $ground;
	if ( ! $ground ) {
		return '';
	}
	if ( $basement > 0 ) {
		return sprintf( '지하 %d층 ~ 지상 %d층', $basement, $ground );
	}
	return sprintf( '지상 %d층', $ground );
}

/**
 * 입주가능일 표시 (D1 확정 규격): move_in_type이 immediate/negotiable이면 그 라벨 그대로,
 * scheduled면 move_in_date를 "2026년 9월 1일 입주가능" 형태로 포맷.
 */
function olt_format_move_in( $move_in_type, $move_in_date ) {
	$labels = array( 'immediate' => '즉시입주', 'negotiable' => '협의가능' );
	if ( isset( $labels[ $move_in_type ] ) ) {
		return $labels[ $move_in_type ];
	}
	if ( 'scheduled' === $move_in_type && $move_in_date ) {
		return date_i18n( 'Y년 n월 j일', strtotime( $move_in_date ) ) . ' 입주가능';
	}
	return '협의가능';
}

/** 매물 상태값 -> [라벨, badge 표시 여부(available/reserved 계열만 초록 뱃지)] */
function olt_status_label( $status ) {
	$map = array(
		'available'        => '임대가능',
		'reserved'         => '협의중',
		'contract_pending' => '계약진행중',
		'leased'           => '거래완료',
		'temporarily_hidden' => '노출중지',
		'expired'          => '만료',
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : $status;
}

/** 공개 노출 대상 매물 상태 (빌딩 페이지에 카운트/렌더할 상태) */
function olt_public_listing_statuses() {
	return array( 'available', 'reserved', 'contract_pending' );
}

/**
 * 특정 빌딩에 연결된, 공개 노출 대상 매물들을 반환.
 * related_building = building_id 이고 listing_status가 공개 상태인 매물.
 */
function olt_get_building_listings( $building_id, $only_public = true ) {
	$meta_query = array(
		array(
			'key'   => 'related_building',
			'value' => $building_id,
		),
	);
	if ( $only_public ) {
		$meta_query[] = array(
			'key'     => 'listing_status',
			'value'   => olt_public_listing_statuses(),
			'compare' => 'IN',
		);
	}
	// [버그 수정] 이전엔 monthly_total_cost DESC(최고가 우선)였다. single-building.php는 이 배열의
	// 첫 번째 매물([0])을 "대표 매물"로 Hero에 노출하는데, 최고가 매물을 대표로 내세우면
	// 사이트 전반에서 강조하는 "최저 임대료"(building_min_rent 캐시) 브랜딩과 어긋난다.
	// 가장 저렴한 매물을 대표로 - 카드/캐시가 보여주는 값과 일치시킨다.
	return get_posts( array(
		'post_type'      => 'listing',
		'posts_per_page' => -1,
		'meta_query'     => $meta_query,
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'monthly_total_cost',
		'order'          => 'ASC',
	) );
}

/**
 * 번호형 이미지 필드(building_image_1..N / listing_image_1..N)를 배열로 수집.
 * 반환: [ ['id'=>, 'url'=>, 'alt'=>], ... ] (값이 있는 것만)
 *
 * $size: docs/IMAGE_PERFORMANCE_GUIDELINES.md 용도별 등록 크기(ol-building-thumb 등)를 넘긴다.
 * 'url'은 wp_get_attachment_image()를 못 쓰는 호출부(첨부 ID가 없는 예외 상황)의 fallback용으로만
 * 남겨뒀다 - 정상 경로는 반환된 'id'로 wp_get_attachment_image()를 호출해 srcset을 받는다.
 */
function olt_collect_images( $post_id, $prefix, $count, $size = 'ol-interior' ) {
	$images = array();
	for ( $i = 1; $i <= $count; $i++ ) {
		$img = get_field( $prefix . $i, $post_id );
		if ( ! $img ) {
			continue;
		}
		// ACF image return_format = array
		if ( is_array( $img ) ) {
			$images[] = array(
				'id'  => $img['ID'] ?? 0,
				'url' => $img['sizes'][ $size ] ?? ( $img['sizes']['large'] ?? ( $img['url'] ?? '' ) ),
				'alt' => $img['alt'] ?? '',
			);
		}
	}
	return $images;
}

/**
 * 두 숫자(빌딩 캐시의 min/max)를 값 포맷 콜백에 넘겨 범위 문자열로 합친다.
 * min==max(매물 1건) 또는 둘 중 하나만 있으면 단일값, 둘 다 없으면 빈 문자열.
 *
 * [면적 전용] "0 = 데이터 없음"이 항상 성립하는 필드에만 써야 한다(전용/임대면적 등 - 매물이 실제
 * 면적 0으로 존재할 수는 없으므로 0을 "미입력"으로 취급해도 안전하다). 보증금/임대료/관리비처럼 0이
 * 실제 유효값일 수 있는 금액 필드에는 이 함수를 쓰면 안 된다 - olt_format_money_range()를 쓸 것
 * (2차 리뷰에서 지적됨: 이 함수를 금액에도 그대로 재사용해서 "0원~50만원"이 "50만원"으로,
 * "0원~0원"이 빈 문자열로 잘못 나오는 문제가 있었다).
 *
 * @param callable $formatter 값 하나를 받아 단위 포함 문자열로 포맷하는 콜백(예: olt_pyeong).
 */
function olt_format_range( $min, $max, callable $formatter ) {
	$min = (float) $min;
	$max = (float) $max;
	if ( $min <= 0 && $max <= 0 ) {
		return '';
	}
	if ( $min > 0 && $max > 0 && round( $min, 4 ) !== round( $max, 4 ) ) {
		return call_user_func( $formatter, $min ) . ' ~ ' . call_user_func( $formatter, $max );
	}
	return call_user_func( $formatter, $max > 0 ? $max : $min );
}

/**
 * [building-card.php 전용] 빌딩 캐시의 평 min/max 하나로 ㎡ 표기와 평 표기 한 쌍을 만든다.
 * 캐시엔 평 min/max만 있으므로(building-cache.php), ol_calc_sqm_from_pyeong()(Core, 순수 변환 함수)로
 * 렌더 시점에 ㎡를 환산한다 - 새 building_min/max_*_sqm 캐시 필드를 추가하지 않는다(단순 단위 변환이라
 * min/max 관계가 sqm으로 바꿔도 그대로 유지되므로 안전, 매물 재쿼리도 없음).
 * 내부적으로 기존 olt_format_range()(면적 전용, 0=데이터 없음)를 그대로 재사용한다 - 이 함수를 수정하면
 * building-card.php 밖의 다른 호출부에 영향을 줄 수 있어 손대지 않고 그 위에 새로 얹는다.
 *
 * 두 값 모두 괄호 없이 반환한다("298평 ~ 342평", "985.1㎡ ~ 1,130.6㎡") - 어느 쪽을 주표기(큰 글씨)로,
 * 어느 쪽을 보조표기(괄호 안 작은 글씨)로 쓸지는 호출부(building-card.php)가 정한다. 괄호를 여기서
 * 미리 씌우면 호출부가 주/보조를 바꿀 때마다 이 함수를 또 고쳐야 해서, 표기 순서는 호출부 책임으로 둔다.
 *
 * @return array{sqm: string, pyeong: string} 데이터가 없으면 두 값 모두 빈 문자열.
 */
function olt_format_area_sqm_pyeong( $min_pyeong, $max_pyeong ) {
	$sqm_plain    = function ( $v ) {
		return number_format( (float) $v, 1 ) . '㎡';
	};
	$pyeong_plain = function ( $v ) {
		return number_format( (float) $v ) . '평';
	};
	$min_pyeong = (float) $min_pyeong;
	$max_pyeong = (float) $max_pyeong;
	return array(
		'sqm'    => olt_format_range( ol_calc_sqm_from_pyeong( $min_pyeong ), ol_calc_sqm_from_pyeong( $max_pyeong ), $sqm_plain ),
		'pyeong' => olt_format_range( $min_pyeong, $max_pyeong, $pyeong_plain ),
	);
}

/**
 * [금액 전용] 두 숫자(빌딩 캐시의 min/max 보증금/임대료/관리비)를 범위 문자열로 합친다.
 * building-cache.php는 "이 필드를 입력한 매물이 하나도 없음"을 -1로 저장한다(0은 관리비 없음 등
 * 실제 유효값이라 캐시 sentinel로 못 쓴다 - ol_money_field_value() 참고). 그래서 olt_format_range()의
 * "0 이하면 없는 값" 판정과 달리, 여기서는 min/max가 음수(-1)일 때만 "데이터 없음"으로 본다.
 *
 * @param callable $formatter 값 하나를 받아 단위 포함 문자열로 포맷하는 콜백(예: olt_won).
 */
function olt_format_money_range( $min, $max, callable $formatter ) {
	// [3차 리뷰 수정] -1 sentinel만 보고 (float)로 먼저 캐스팅하면, 이 캐시 필드가 아직 한 번도
	// 쓰인 적 없는 경우(신규 빌딩에 매물이 아직 하나도 연결 안 됨 / 이 필드가 추가되기 전부터 있던
	// 빌딩이 재계산 전인 경우)에 get_field()가 돌려주는 null/false/''가 전부 (float)로 0이 되어
	// "데이터 없음"이 "0원"으로 잘못 보인다. -1 체크보다 먼저 걸러야 한다.
	if ( null === $min || false === $min || '' === $min || null === $max || false === $max || '' === $max ) {
		return '';
	}
	$min = (float) $min;
	$max = (float) $max;
	if ( $min < 0 || $max < 0 ) {
		return '';
	}
	if ( round( $min, 4 ) !== round( $max, 4 ) ) {
		return call_user_func( $formatter, $min ) . ' ~ ' . call_user_func( $formatter, $max );
	}
	return call_user_func( $formatter, $max );
}

/** 캐시된 층수(정수, 지하는 음수) 하나를 "17층"/"지하1층"으로 표기. 0은 "데이터 없음"이라 호출부에서 걸러야 한다. */
function olt_floor_label( $n ) {
	$n = (int) $n;
	return $n < 0 ? sprintf( '지하%d층', abs( $n ) ) : sprintf( '%d층', $n );
}

/**
 * 빌딩 캐시의 최소/최대 층수를 "3~7층"/"지하1층~7층" 또는 단일 "17층"으로 표기.
 * 두 값 모두 0(데이터 없음)이면 빈 문자열 - 카드에서 이 값이 비면 층수 행 자체를 렌더하지 않는다.
 */
function olt_floor_range( $min, $max ) {
	$min = (int) $min;
	$max = (int) $max;
	if ( ! $min && ! $max ) {
		return '';
	}
	if ( $min && $max && $min !== $max ) {
		// 지상층만 섞인 흔한 경우엔 단위를 한 번만 붙여 "3~7층"으로 - 지하가 섞이면
		// "지하1층~7층"처럼 각 값에 라벨을 다 붙여야 부호가 헷갈리지 않는다.
		if ( $min > 0 && $max > 0 ) {
			return sprintf( '%d~%d층', $min, $max );
		}
		if ( $min < 0 && $max < 0 ) {
			// 둘 다 지하일 땐 숫자(음수) 크기 순이 아니라 "얕은 지하 → 깊은 지하" 순으로 읽혀야
			// 자연스럽다(지하1층~지하3층 O, 지하3층~지하1층 X) - 절댓값이 작은 쪽(=더 큰 정수)이 먼저.
			return olt_floor_label( max( $min, $max ) ) . '~' . olt_floor_label( min( $min, $max ) );
		}
		// 지상/지하가 섞이면 지하 쪽이 항상 더 작은 정수라 $min이 그대로 지하 값이 된다.
		return olt_floor_label( $min ) . '~' . olt_floor_label( $max );
	}
	return olt_floor_label( $max ?: $min );
}

/**
 * [listing-detail-ux-pass3] 매물 상세 Hero의 층수 표기를 정확한 층 번호 대신 "고층/중층/저층" 3단계로
 * 단순화한다(요청: "층수도 고층 중층 저층 정도면 문제 없을것 같에") - 지상층을 총 층수 대비 위치로
 * 3등분한다(상위 1/3=고층, 중간 1/3=중층, 하위 1/3=저층). 지하층은 등급 구분 없이 "지하"로 통일.
 * floor_display는 매물 관리자가 자유 입력하는 텍스트(예: "17층")라 ol_extract_floor_number()(Core,
 * anchored 파서)로 먼저 숫자를 뽑는다 - 파싱이 안 되거나(범위 표기 등) 총 층수를 모르면 tier 계산이
 * 불가능하므로 원문을 그대로 돌려준다(추측으로 등급을 잘못 매기지 않기 위한 안전한 폴백).
 */
function olt_floor_tier( $floor_display, $total_floors ) {
	if ( ! function_exists( 'ol_extract_floor_number' ) ) {
		return $floor_display;
	}
	$floor = ol_extract_floor_number( $floor_display );
	if ( null === $floor ) {
		return $floor_display;
	}
	if ( $floor < 0 ) {
		return '지하';
	}
	$total_floors = (int) $total_floors;
	if ( $total_floors <= 0 ) {
		return $floor_display;
	}
	$ratio = $floor / $total_floors;
	if ( $ratio > 2 / 3 ) {
		return '고층';
	}
	if ( $ratio > 1 / 3 ) {
		return '중층';
	}
	return '저층';
}

/**
 * [listing-detail-ux-pass4/5] "면적 슬라이더" - 매물 임대면적을 최소~최대 순으로 늘어놓은 점-선 UI.
 * 요청: "슬라이드 형식으로 최소면적ㅇㅡㅇㅡㅇ최대면적, 동그라미 위에 임대면적, 동그라미 아래 전용면적
 * 색깔 다르게, 마우스오버나 클릭, 터치시 그에 맞는 층수/보증금/임대료/관리비로 전환".
 * [pass5 추가 요청] 왼쪽에 "임대면적"/"전용면적" 축 라벨 추가 - 각 점 위/아래 값이 무엇을 뜻하는지
 * 색깔만으로 구분하지 않고 텍스트로도 명확히.
 * [listing-detail-ux-pass6 3차 피드백] "최소면적/최대면적 텍스트는 없어도 괜찮다" - 트랙 양 끝의
 * .olx-area-slider-end 라벨을 완전히 뺐다(매물 1건/여러 건 구분 없이 항상 없음).
 *
 * single.js의 initListingToggle()이 이미 구현한 select(index) 메커니즘을 그대로 재사용한다 -
 * 이 함수는 그 트리거 역할을 하던 기존 "칩" 버튼 행을 대체하는 새 마크업만 만든다
 * (.olx-area-slider-dot에 동일한 data-listing-index를 붙여서, JS 쪽 변경 없이 셀렉터만
 * 넓히면 된다). 매물이 1건이면 점도 하나뿐이라 실질적 토글 동작은 없지만, Hero/임대정보 양쪽에서
 * 같은 시각 언어를 쓰기 위해 단일 점 형태로도 렌더한다.
 *
 * 점 간격은 실제 면적 비율에 비례하지 않고 균등 배치한다(단순 스텝퍼) - 면적 차이가 작은 매물들이
 * 한 점에 겹쳐 보이는 문제를 피하기 위한 의도적 단순화.
 *
 * @param array $stops 임대면적 오름차순으로 이미 정렬된 [ ['index'=>원본 $toggle_listings 인덱스,
 *                      'lease_pyeong'=>, 'lease_sqm'=>, 'exclusive_pyeong'=>, 'exclusive_sqm'=> ], ... ].
 *                      정렬 자체는 호출부 책임 - 카드 그리드(#olx-toggle-cards)는 별도로 가격순 원본
 *                      순서를 유지해야 하므로, 이 함수는 순서를 건드리지 않고 넘겨받은 그대로 그린다.
 * @param int $default_index 처음 활성 상태로 표시할 원본 인덱스(기준층면적에 가장 가까운 매물).
 * @return string 이미 이스케이프된 HTML(호출부는 그대로 echo).
 */
function olt_area_slider( $stops, $default_index ) {
	if ( empty( $stops ) ) {
		return '';
	}
	$multi = count( $stops ) > 1;
	ob_start();
	?>
	<div class="olx-area-slider<?php echo $multi ? '' : ' olx-area-slider--single'; ?>">
		<div class="olx-area-slider-axis">
			<span class="olx-area-slider-axis-lease">임대면적</span>
			<span class="olx-area-slider-axis-exclusive">전용면적</span>
		</div>
		<div class="olx-area-slider-line" role="tablist" aria-label="면적으로 매물 비교">
			<div class="olx-area-slider-dots">
				<?php foreach ( $stops as $stop ) :
					$i         = (int) $stop['index'];
					$is_active = ( $i === (int) $default_index );
					?>
					<button type="button" class="olx-area-slider-dot<?php echo $is_active ? ' is-active' : ''; ?>"
						role="tab" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
						data-listing-index="<?php echo esc_attr( (string) $i ); ?>">
						<span class="olx-area-slider-lease"><b><?php echo esc_html( $stop['lease_pyeong'] ); ?></b><small><?php echo esc_html( $stop['lease_sqm'] ); ?></small></span>
						<i class="olx-area-slider-node"></i>
						<span class="olx-area-slider-exclusive"><b><?php echo esc_html( $stop['exclusive_pyeong'] ); ?></b><small><?php echo esc_html( $stop['exclusive_sqm'] ); ?></small></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * slug로 페이지를 찾아, "지금 이 사이트 방문자에게 실제로 보여줘도 되는" 상태일 때만 permalink를 반환.
 * contact-cta.php(contact/insight)와 single-building.php(checklist)가 각자 get_page_by_path()만
 * 호출하던 것을 여기로 모았다 - 페이지가 draft/private/비밀번호 보호 상태여도 "존재는 한다"는 이유로
 * 버튼이 노출되던 문제를 여기 한 곳에서 막는다.
 *
 * 검사 항목:
 *  - get_page_by_path()가 실제 WP_Post를 반환하는지(post_type='page' 조건은 get_page_by_path() 3번째
 *    인자 기본값 자체가 이미 'page'라 사실상 중복이지만, 향후 이 함수가 다른 곳에서 호출될 때를 대비해 명시)
 *  - post_status가 정확히 publish인지(draft/pending/future 등 전부 제외)
 *  - post_password가 비어있는지(비밀번호 보호 페이지는 존재해도 공개 링크로 노출하지 않는다 -
 *    관리자가 미리보기로 비밀번호를 입력해둔 상태와 무관하게, 일반 방문자 기준으로 판단해야 하므로
 *    런타임 쿠키 확인 함수인 post_password_required()가 아니라 post_password 값 자체를 본다)
 *  - is_post_publicly_viewable()이 있으면(WP 4.4+) 추가로 확인 - private 페이지 등 위 조건들만으론
 *    못 걸러내는 경우의 이중 방어
 *  - get_permalink()이 실제 URL을 반환하는지(실패 시 false)
 * 하나라도 걸리면 빈 문자열 - 호출부는 지금처럼 "값이 있을 때만 버튼 렌더"만 하면 된다.
 */
function olt_get_public_page_url( $slug ) {
	$page = get_page_by_path( $slug );
	if ( ! ( $page instanceof WP_Post ) || 'page' !== $page->post_type ) {
		return '';
	}
	if ( 'publish' !== $page->post_status ) {
		return '';
	}
	if ( ! empty( $page->post_password ) ) {
		return '';
	}
	if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $page ) ) {
		return '';
	}
	$url = get_permalink( $page );
	return $url ? $url : '';
}

/**
 * 아카이브(/사무실임대/) 상단 SEO 인트로.
 * 플러그인의 ol_default_archive_intro()가 정본 - schema-hub.php(FAQPage/CollectionPage)도 같은 함수를
 * 불러서 화면과 스키마 문구가 갈라지지 않게 한다. 플러그인 비활성 시에만 이 사본으로 폴백.
 */
function olt_archive_seo_intro() {
	if ( function_exists( 'ol_default_archive_intro' ) ) {
		return ol_default_archive_intro();
	}
	return '서울 프라임 오피스 임대 매물을 권역별로 확인하세요. 강남(GBD)·도심권(CBD)·여의도(YBD)를 중심으로 검증된 빌딩 정보와 실시간 공실 현황을 제공합니다.';
}

/** 아카이브 하단 서술형 SEO 블록. */
function olt_archive_seo_content() {
	return '오피스리싱은 힌트부동산중개법인이 운영하는 서울 프라임 오피스 임대 전문 플랫폼입니다. 각 빌딩의 전용면적·임대 조건·교통·주변 인프라를 표준화된 형식으로 정리해, 기업 이전 담당자가 빠르게 비교하고 판단할 수 있도록 돕습니다. 관심 있는 권역을 선택하면 해당 지역의 임대 가능 매물을 한눈에 확인할 수 있습니다.';
}

/**
 * 아카이브 기본 FAQ (지역 컨텍스트 없는 전체 목록용).
 * 플러그인의 ol_default_archive_faqs()가 정본 - schema-hub.php의 FAQPage가 같은 함수를 불러
 * 화면에 보이는 Q&A와 스키마가 항상 1:1로 일치하게 한다.
 */
function olt_archive_faqs() {
	if ( function_exists( 'ol_default_archive_faqs' ) ) {
		return ol_default_archive_faqs();
	}
	return array(
		array( 'q' => '오피스 임대 상담은 어떻게 진행되나요?', 'a' => '관심 빌딩의 문의 버튼으로 연락 주시면, 담당 중개사가 공실 현황과 임대 조건을 확인해 안내해 드립니다.' ),
		array( 'q' => '표시된 임대 조건은 확정 금액인가요?', 'a' => '임대료·관리비는 시장 상황과 공실 현황에 따라 변동될 수 있어, 상담 시 최신 조건을 다시 확인해 드립니다. 부가세는 모두 별도입니다.' ),
		array( 'q' => '원하는 지역의 매물이 목록에 없으면 어떻게 하나요?', 'a' => '희망 지역·면적·예산을 알려주시면 등록되지 않은 매물까지 포함해 확인 가능한 범위에서 찾아 안내해 드립니다.' ),
	);
}

/** 지역(term)의 FAQ. region_faq_q1~5 / a1~5 term 필드를 읽고, 비어있으면 아카이브 기본값으로 폴백. */
function olt_region_faqs( $term ) {
	$faqs = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$q = get_field( 'region_faq_q' . $i, $term );
		$a = get_field( 'region_faq_a' . $i, $term );
		if ( $q && $a ) {
			$faqs[] = array( 'q' => $q, 'a' => $a );
		}
	}
	return ! empty( $faqs ) ? $faqs : olt_archive_faqs();
}

/**
 * Empty state용 인근 빌딩 추천. 현재 term의 형제(같은 부모의 다른 자식) 또는 부모 권역 내 빌딩을 반환.
 * @return int[] building post IDs
 */
function olt_get_nearby_buildings( $term, $limit = 4 ) {
	if ( ! $term || is_wp_error( $term ) ) {
		return array();
	}
	$parent_id = (int) $term->parent ? (int) $term->parent : (int) $term->term_id;
	return get_posts( array(
		'post_type'      => 'building',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'post__not_in'   => array(),
		'tax_query'      => array( array(
			'taxonomy'         => 'office_region',
			'field'            => 'term_id',
			'terms'            => $parent_id,
			'include_children' => true,
		) ),
	) );
}

/**
 * 빌딩의 office_region 자식 term과 그 부모를 반환.
 * 반환: [ 'child' => WP_Term|null, 'parent' => WP_Term|null ]
 */
function olt_get_region_terms( $post_id ) {
	// wp_get_object_terms()가 아니라 get_the_terms()를 쓴다: 전자는 매번 DB를 직접 조회하므로
	// 카드를 32개 렌더하는 Home에서 term 쿼리가 32번 나가는 N+1이 된다. get_the_terms()는
	// WP_Query가 이미 채워둔 object term 캐시를 읽으므로 추가 쿼리가 발생하지 않는다.
	// (반환 형태는 둘 다 WP_Term 배열이라 이 아래 로직은 그대로 동작한다.)
	$terms = get_the_terms( $post_id, 'office_region' );
	$out = array( 'child' => null, 'parent' => null );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $out;
	}
	foreach ( $terms as $t ) {
		if ( $t->parent ) {
			$out['child'] = $t;
			$parent = get_term( $t->parent, 'office_region' );
			if ( $parent && ! is_wp_error( $parent ) ) {
				$out['parent'] = $parent;
			}
		} elseif ( ! $out['parent'] ) {
			$out['parent'] = $t;
		}
	}
	return $out;
}

/**
 * 회사 공통정보. 플러그인의 ol_company_info()를 우선 쓰고, 플러그인 비활성 시에도
 * Footer/회사정보는 정상 노출되어야 하므로(Home V1 예외처리 요구사항) 동일한 값을 fallback으로 둔다.
 * 값을 바꿀 때는 플러그인 helpers.php의 ol_company_info()가 정본 - 여기는 비상용 사본이다.
 */
function olt_company( $key ) {
	if ( function_exists( 'ol_company' ) ) {
		return ol_company( $key );
	}
	$fallback = array(
		'legal_name'     => '힌트부동산중개법인',
		'brand'          => 'OFFICE LEASING',
		'tagline'        => '서울 프라임 오피스 임대 플랫폼',
		'phone'          => '02-553-5988',
		'address_full'   => '서울 강남구 언주로 550 청광빌딩 2층',
		'license_number' => '11680-2026-00163',
		'hours'          => '평일 09:00 – 18:00',
		'email'          => '',
	);
	return isset( $fallback[ $key ] ) ? $fallback[ $key ] : '';
}

/** 전화번호 tel: 링크용 정규화. */
function olt_tel_href( $phone = null ) {
	if ( function_exists( 'ol_tel_href' ) ) {
		return ol_tel_href( $phone );
	}
	$phone = ( null === $phone ) ? olt_company( 'phone' ) : $phone;
	return preg_replace( '/[^0-9+]/', '', (string) $phone );
}

/**
 * Home 권역 섹션의 표시 문구(Eyebrow/제목/설명).
 * 실제 term은 DB가 정본이고, 이 배열은 "그 term을 화면에 어떻게 소개할지"라는 표현 레이어이므로 테마에 둔다.
 * 키는 마이그레이션 이후의 실제 부모 term 이름. 구 영문 코드도 함께 받아 방어한다.
 * 매핑에 없는 term은 term 이름을 그대로 제목으로 쓰고 설명은 비운다(가짜 문구를 만들지 않음).
 */
function olt_home_region_copy( $term_name ) {
	$map = array(
		'강남사무실임대' => array(
			'eyebrow' => 'GBD',
			'title'   => '강남 주요 업무지구',
			'desc'    => '역삼동, 삼성동, 대치동, 논현동, 신사동, 청담동을 포함하는 서울의 대표 업무권역입니다. 테헤란로와 강남대로를 중심으로 IT·금융·외국계 기업과 대기업 본사 수요가 집중되어 있습니다.',
			'all'     => '강남 사무실 매물 리스트',
		),
		'도심권사무실임대' => array(
			'eyebrow' => 'CBD',
			'title'   => '도심 주요 업무지구',
			'desc'    => '광화문, 종로, 을지로, 서울역 일대를 중심으로 금융·법률·공공기관·대기업 본사 수요가 형성된 서울의 전통적인 핵심 업무권역입니다.',
			'all'     => '도심권 사무실 매물 리스트',
		),
		'여의도사무실임대' => array(
			'eyebrow' => 'YBD',
			'title'   => '여의도 업무지구',
			'desc'    => '여의도역, 국회의사당, IFC를 중심으로 금융회사·증권사·자산운용사와 대기업이 밀집한 서울의 대표 금융 업무권역입니다.',
			'all'     => '여의도 사무실 매물 리스트',
		),
		'기타권역사무실임대' => array(
			// 화면에 'ETC'를 크게 노출하지 않는다(확정 사항). 관리 데이터·카드 뱃지는 계속 ETC 코드를 쓴다.
			'eyebrow' => 'OTHER BUSINESS DISTRICTS',
			'title'   => '서울 주요 업무권역',
			'desc'    => '서초·성수·송파·용산 등 기업 수요가 확장되고 있는 서울의 주요 업무지역을 함께 소개합니다.',
			'all'     => '기타 권역 사무실 매물 리스트',
		),
	);
	$legacy = array( 'GBD' => '강남사무실임대', 'CBD' => '도심권사무실임대', 'YBD' => '여의도사무실임대', 'ETC' => '기타권역사무실임대' );
	$term_name = (string) $term_name;
	if ( isset( $legacy[ strtoupper( $term_name ) ] ) ) {
		$term_name = $legacy[ strtoupper( $term_name ) ];
	}
	if ( isset( $map[ $term_name ] ) ) {
		return $map[ $term_name ];
	}
	return array(
		'eyebrow' => olt_region_short_code( $term_name ),
		'title'   => $term_name,
		'desc'    => '',
		'all'     => '전체 보기',
	);
}

/**
 * Home에서 쓸 권역 부모 term 목록(정렬 순서 고정: 강남 → 도심권 → 여의도 → 기타권역).
 * get_terms의 기본 정렬(name)은 한글 가나다순이라 원하는 순서가 안 나오므로 명시적으로 재정렬한다.
 * 매핑에 없는 부모 term(운영 중 새로 추가된 권역)은 뒤에 이름순으로 붙인다.
 */
function olt_home_region_parents() {
	$terms = get_terms( array(
		'taxonomy'   => 'office_region',
		'parent'     => 0,
		'hide_empty' => false,
	) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}
	$order = array( '강남사무실임대' => 0, '도심권사무실임대' => 1, '여의도사무실임대' => 2, '기타권역사무실임대' => 3 );
	usort( $terms, function ( $a, $b ) use ( $order ) {
		$ia = isset( $order[ $a->name ] ) ? $order[ $a->name ] : 99;
		$ib = isset( $order[ $b->name ] ) ? $order[ $b->name ] : 99;
		if ( $ia !== $ib ) {
			return $ia <=> $ib;
		}
		return strcmp( $a->name, $b->name );
	} );
	return $terms;
}
