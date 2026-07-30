<?php
/**
 * HLF 전용 환산 계산 (officeleasing-core와 완전히 분리).
 *
 * 이 파일은 순수 계산 함수만 정의하며 워드프레스에 의존하지 않는다. 그래서 helpers.php와 같은
 * 이유로 ABSPATH 가드를 두지 않는다 — tests/test-calculations.php에서 WP 부트스트랩 없이 단독
 * 실행 검증이 가능하다. (직접 로드해도 함수 정의만 하고 아무것도 실행하지 않으므로 안전하다.)
 *
 * [분리 원칙] officeleasing-core의 noc_per_exclusive_pyeong = (임대료+관리비)/전용평,
 * deposit_per_exclusive_pyeong = 보증금/전용평 은 "보증금 환산이자"를 포함하지 않는 별개 지표다.
 * HLF의 NOC는 보증금을 연 3.5% 단리로 환산해 월 비용에 더한 값이므로 공식이 다르다.
 * core의 함수/필드를 참조하거나 재사용하지 않고 여기서 독립적으로 계산한다.
 *
 * 내부 단위 규약: 금액은 전부 "만원", 면적은 "평". 입력 sqm은 hlf_sqm_to_pyeong으로 변환해 쓴다.
 */

if ( ! defined( 'HLF_PYEONG_TO_SQM' ) ) {
	// core의 OL_PYEONG_TO_SQM(3.3058)과 동일 상수. core 상수에 의존하지 않도록 HLF가 독립 정의한다.
	define( 'HLF_PYEONG_TO_SQM', 3.3058 );
}

if ( ! defined( 'HLF_DEPOSIT_ANNUAL_RATE' ) ) {
	// 보증금 환산이자율(연). NOC = ((보증금 × 0.035 ÷ 12) + 임대료 + 관리비) ÷ 전용평.
	define( 'HLF_DEPOSIT_ANNUAL_RATE', 0.035 );
}

if ( ! function_exists( 'hlf_to_float' ) ) {
	/** 콤마/공백/문자 혼재 입력을 안전하게 float로. 음수·비수치는 0. */
	function hlf_to_float( $value ): float {
		if ( is_numeric( $value ) ) {
			return max( 0.0, (float) $value );
		}
		$raw = preg_replace( '/[^0-9.]/', '', (string) $value );
		return $raw === '' ? 0.0 : max( 0.0, (float) $raw );
	}
}

if ( ! function_exists( 'hlf_safe_divide' ) ) {
	function hlf_safe_divide( $numerator, $denominator ): float {
		$denominator = (float) $denominator;
		if ( $denominator <= 0 ) {
			return 0.0;
		}
		return (float) $numerator / $denominator;
	}
}

if ( ! function_exists( 'hlf_sqm_to_pyeong' ) ) {
	/** ㎡ → 평. 소수 둘째자리 반올림. 0 이하 입력은 0. */
	function hlf_sqm_to_pyeong( $sqm ): float {
		$sqm = hlf_to_float( $sqm );
		if ( $sqm <= 0 ) {
			return 0.0;
		}
		return round( $sqm / HLF_PYEONG_TO_SQM, 2 );
	}
}

if ( ! function_exists( 'hlf_calculate_deposit_per_lease_pyeong' ) ) {
	/** 공급평당 보증금 (만원/공급평). 표시용 소수 1자리. */
	function hlf_calculate_deposit_per_lease_pyeong( $deposit, $lease_pyeong ): float {
		return round( hlf_safe_divide( hlf_to_float( $deposit ), $lease_pyeong ), 1 );
	}
}

if ( ! function_exists( 'hlf_calculate_rent_per_lease_pyeong' ) ) {
	/** 공급평당 임대료 (만원/공급평). */
	function hlf_calculate_rent_per_lease_pyeong( $rent, $lease_pyeong ): float {
		return round( hlf_safe_divide( hlf_to_float( $rent ), $lease_pyeong ), 1 );
	}
}

if ( ! function_exists( 'hlf_calculate_maintenance_per_lease_pyeong' ) ) {
	/** 공급평당 관리비 (만원/공급평). */
	function hlf_calculate_maintenance_per_lease_pyeong( $fee, $lease_pyeong ): float {
		return round( hlf_safe_divide( hlf_to_float( $fee ), $lease_pyeong ), 1 );
	}
}

if ( ! function_exists( 'hlf_calculate_noc' ) ) {
	/**
	 * HLF NOC (전용평당 환산임대료, 만원/전용평).
	 * NOC = ((보증금 × HLF_DEPOSIT_ANNUAL_RATE ÷ 12) + 임대료 + 관리비) ÷ 전용평.
	 * 전용평이 0 이하이면 0.
	 */
	function hlf_calculate_noc( $deposit, $rent, $fee, $exclusive_pyeong ): float {
		$deposit = hlf_to_float( $deposit );
		$rent    = hlf_to_float( $rent );
		$fee     = hlf_to_float( $fee );

		$deposit_monthly_interest = $deposit * HLF_DEPOSIT_ANNUAL_RATE / 12;
		$monthly_effective        = $deposit_monthly_interest + $rent + $fee;

		return round( hlf_safe_divide( $monthly_effective, $exclusive_pyeong ), 1 );
	}
}

if ( ! function_exists( 'hlf_calculate_item_metrics' ) ) {
	/**
	 * flyer item 하나의 모든 파생 지표를 한 번에 계산한다.
	 * $item 은 연관배열 또는 객체. 기대 키(만원/㎡ 단위):
	 *   deposit_manwon, monthly_rent_manwon, maintenance_fee_manwon,
	 *   lease_area_sqm, exclusive_area_sqm
	 * @return array{
	 *   lease_pyeong: float, exclusive_pyeong: float,
	 *   deposit_per_lease_pyeong: float, rent_per_lease_pyeong: float,
	 *   maintenance_per_lease_pyeong: float, noc: float
	 * }
	 */
	function hlf_calculate_item_metrics( $item ): array {
		$get = static function ( $key ) use ( $item ) {
			if ( is_array( $item ) ) {
				return $item[ $key ] ?? 0;
			}
			if ( is_object( $item ) ) {
				return $item->$key ?? 0;
			}
			return 0;
		};

		$deposit     = hlf_to_float( $get( 'deposit_manwon' ) );
		$rent        = hlf_to_float( $get( 'monthly_rent_manwon' ) );
		$fee         = hlf_to_float( $get( 'maintenance_fee_manwon' ) );
		$lease_p     = hlf_sqm_to_pyeong( $get( 'lease_area_sqm' ) );
		$exclusive_p = hlf_sqm_to_pyeong( $get( 'exclusive_area_sqm' ) );

		return array(
			'lease_pyeong'                 => $lease_p,
			'exclusive_pyeong'             => $exclusive_p,
			'deposit_per_lease_pyeong'     => hlf_calculate_deposit_per_lease_pyeong( $deposit, $lease_p ),
			'rent_per_lease_pyeong'        => hlf_calculate_rent_per_lease_pyeong( $rent, $lease_p ),
			'maintenance_per_lease_pyeong' => hlf_calculate_maintenance_per_lease_pyeong( $fee, $lease_p ),
			'noc'                          => hlf_calculate_noc( $deposit, $rent, $fee, $exclusive_p ),
		);
	}
}

if ( ! function_exists( 'hlf_normalize_parking_available' ) ) {
	/**
	 * building_parking 자유텍스트 → 주차가능 여부(boolean).
	 * "주차 가능", "자주식 10대", "기계식", "가능" 등 → true.
	 * "주차 불가", "불가", "없음", "무" 또는 빈 값 → false.
	 * (Phase 1에서는 core를 건드리지 않고 Flyer snapshot 쪽에서만 정규화한다.)
	 */
	function hlf_normalize_parking_available( string $parking_text ): bool {
		$text = trim( $parking_text );
		if ( $text === '' ) {
			return false;
		}
		// 부정 표현이 있으면 우선 false 판정.
		if ( preg_match( '/(불가|불가능|없음|없슴|무\b|주차\s*안)/u', $text ) ) {
			return false;
		}
		// 긍정 신호: 명시적 "가능", 대수 표기(N대), 주차 방식 키워드.
		if ( preg_match( '/(가능|자주식|기계식|주차장|\d+\s*대)/u', $text ) ) {
			return true;
		}
		// 그 외 텍스트가 존재하면(예: "지하 1층") 주차 정보가 있다고 보고 true.
		return true;
	}
}
