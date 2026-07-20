<?php
/**
 * 이미지 다운로드 전 SSRF 방어 전담(요청서 5). 이 클래스가 하는 일은 딱 하나 —
 * "이 URL로 실제 요청을 보내도 안전한가"만 판단한다. HTTP 호출 자체와 바디 처리는
 * HLF_Image_Import_Service의 몫이다.
 *
 * 허용: http/https 스킴 + 공인 IP로 해석되는 호스트만.
 * 차단: 그 외 스킴(file/ftp/data 등), 사설 대역(RFC1918)/루프백/링크로컬/예약 대역, DNS 해석 실패.
 * 리다이렉트: wp_safe_remote_get()이 내부적으로 302를 자동으로 따라가게 두면 마지막 목적지를
 * 검증할 방법이 없어진다 — 그래서 redirection=0으로 강제하고 이 클래스가 한 hop씩 직접 검증하며
 * 따라간다(요청서: "redirect 후 최종 URL도 재검증").
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Image_Url_Guard {

	const MAX_REDIRECTS = 5;

	/**
	 * URL이 안전한 외부 호스트를 가리키는지 확인한다(요청을 보내지는 않음).
	 *
	 * @return true|WP_Error
	 */
	public static function assert_safe_url( string $url ) {
		$parts = wp_parse_url( $url );
		if ( ! $parts || empty( $parts['host'] ) ) {
			return new WP_Error( 'hlf_image_bad_url', '이미지 주소 형식이 올바르지 않습니다.', array( 'status' => 400 ) );
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error( 'hlf_image_bad_scheme', 'http/https 주소만 허용됩니다.', array( 'status' => 400 ) );
		}

		$host = $parts['host'];
		$ips  = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : self::resolve( $host );

		if ( empty( $ips ) ) {
			return new WP_Error( 'hlf_image_dns_failed', '이미지 주소를 확인할 수 없습니다.', array( 'status' => 400 ) );
		}

		foreach ( $ips as $ip ) {
			if ( self::is_blocked_ip( $ip ) ) {
				return new WP_Error( 'hlf_image_blocked_host', '허용되지 않은 이미지 주소입니다.', array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * SSRF 안전 GET: 매 hop마다 assert_safe_url()로 검증한 뒤에만 요청을 보내고, 3xx 응답이면
	 * Location을 검증하고서 다음 hop으로 넘어간다. 자동 리다이렉트(redirection>0)는 절대 쓰지 않는다.
	 *
	 * @return array{response: array, final_url: string}|WP_Error
	 */
	public static function safe_get( string $url, array $args = array() ) {
		$args               = wp_parse_args( $args, array( 'timeout' => 10 ) );
		$args['redirection'] = 0;

		$current = $url;
		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$safe = self::assert_safe_url( $current );
			if ( is_wp_error( $safe ) ) {
				return $safe;
			}

			$response = wp_safe_remote_get( $current, $args );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'hlf_image_fetch_failed', '이미지를 불러오지 못했습니다.', array( 'status' => 502 ) );
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 300 && $code < 400 ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( empty( $location ) || ! is_string( $location ) ) {
					return new WP_Error( 'hlf_image_redirect_invalid', '이미지 주소를 확인할 수 없습니다.', array( 'status' => 400 ) );
				}
				$current = self::resolve_redirect_target( $current, $location );
				continue;
			}

			return array( 'response' => $response, 'final_url' => $current );
		}

		return new WP_Error( 'hlf_image_too_many_redirects', '이미지 주소 확인이 너무 여러 번 반복되었습니다.', array( 'status' => 400 ) );
	}

	/** Location 헤더가 상대경로일 수 있어(드물지만 표준상 허용) 현재 URL 기준으로 절대경로화한다. */
	private static function resolve_redirect_target( string $current_url, string $location ): string {
		if ( preg_match( '#^https?://#i', $location ) ) {
			return $location;
		}
		$base = wp_parse_url( $current_url );
		$scheme = $base['scheme'] ?? 'https';
		$host   = $base['host'] ?? '';
		$port   = isset( $base['port'] ) ? ':' . $base['port'] : '';
		if ( '' === $host ) {
			return $location; // 판단 불가 — 다음 assert_safe_url()에서 걸러진다.
		}
		if ( 0 === strpos( $location, '/' ) ) {
			return $scheme . '://' . $host . $port . $location;
		}
		$path = $base['path'] ?? '/';
		$dir  = substr( $path, 0, strrpos( $path, '/' ) + 1 );
		return $scheme . '://' . $host . $port . $dir . $location;
	}

	/** A/AAAA 조회. 실패하면 빈 배열(호출측이 DNS 실패로 처리). */
	private static function resolve( string $host ): array {
		$ips = array();
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_A + DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( is_array( $records ) ) {
				foreach ( $records as $record ) {
					if ( ! empty( $record['ip'] ) ) {
						$ips[] = $record['ip'];
					}
					if ( ! empty( $record['ipv6'] ) ) {
						$ips[] = $record['ipv6'];
					}
				}
			}
		}
		if ( empty( $ips ) ) {
			$resolved = gethostbyname( $host );
			if ( $resolved !== $host ) {
				$ips[] = $resolved;
			}
		}
		return array_unique( $ips );
	}

	/** 루프백/사설대역(RFC1918)/링크로컬/예약대역이면 true(차단 대상). 공인 IP만 통과. */
	private static function is_blocked_ip( string $ip ): bool {
		return false === filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
