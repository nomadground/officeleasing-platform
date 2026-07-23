<?php
/**
 * 업로드 이미지 최적화("Smart Image Pipeline" 요청 검토 결과 반영).
 *
 * 워드프레스 코어가 이미 갖고 있는, 실전에서 검증된 훅만 조합한다 — 직접 이미지 처리 코드를 새로
 * 짜지 않는다(GD/Imagick 버전 차이, 메모리 한도, 실패 시 원본 파일 손상 등 위험이 큰 영역이라, 코어가
 * 이미 매 릴리스마다 검증하는 경로를 그대로 쓰는 쪽이 더 안전하다):
 *
 * 1) big_image_size_threshold(코어 5.3+) — 업로드 원본이 지정한 긴 변보다 크면, 코어가 자동으로
 *    그 크기로 축소한 사본을 "실제 사용되는 원본"(-scaled 파일, 첨부 URL이 가리키는 대상)으로 삼고,
 *    진짜 원본은 그대로 별도 보관한다(디스크에 남지만 어디서도 참조되지 않음 — 되돌릴 수 있는
 *    안전한 부수효과). 이 축소 사본을 만드는 코어 경로(wp_generate_attachment_metadata) 자체가
 *    EXIF Orientation을 먼저 바로잡은 뒤 리사이즈하므로, "EXIF 방향 보정"도 별도 코드 없이 함께
 *    해결된다.
 * 2) wp_editor_set_quality(코어 5.8+) — 코어가 만드는 모든 리사이즈 결과물(위 축소 원본 포함, 그리고
 *    class-hlf-plugin.php가 등록한 hlf-item-photo/hlf-item-thumb 두 사이즈)의 JPEG 압축 품질을 지정한다.
 * 3) image_editor_output_format(코어 5.3+, 원래 WebP/AVIF 자동 생성용으로 추가된 훅) — PNG로 올라온
 *    사진은 위 리사이즈/사이즈 생성 결과물을 PNG보다 훨씬 가벼운 JPEG로 출력하게 한다("자동 포맷
 *    JPEG" 요청). 실제 첨부의 원본 파일 자체를 바꾸는 게 아니라, 코어가 만드는 파생 이미지의 출력
 *    포맷만 바꾸는 표준 확장 지점이라 원본 파일 치환/재작성 코드가 필요 없다. 투명 배경이 필요한
 *    포맷(PNG)은 정말 필요하면 그대로 원본에 남아 있으므로 완전히 잃는 것은 아니다.
 *
 * 위 네 필터는 워드프레스 코어 훅 특성상 전부 전역(사이트의 모든 이미지 업로드에 적용)이라, HLF
 * 업로드에만 적용되도록 UPLOAD_FLAG($_POST['hlf_upload'])로 범위를 좁힌다(GPT 코드 감사 P0#2 —
 * 처음 버전은 이 스코프가 없어 테마 로고·ACF 이미지·다른 플러그인 업로드까지 전부 900px/JPEG82로
 * 바꾸고 있었다). is_hlf_upload_request() 참고.
 *
 * 검토했지만 이번에 넣지 않은 것(과도한 범위/위험 대비 이득이 낮다고 판단):
 * - WebP 생성: Smush Pro 같은 전용 플러그인의 영역이다 — 이 플러그인은 다른 플러그인에 의존하지
 *   않는다는 기존 원칙(officeleasing-core/ACF 없이도 동작)과 같은 이유로, 서드파티 플러그인을
 *   전제로 한 기능을 이 코드베이스 안에 넣지 않는다. 설치돼 있으면 그 플러그인이 알아서 이 파이프라인
 *   결과물(이미 작아진 이미지)을 그대로 사용해 WebP를 만들 수 있다.
 * - "OCR용 임시 파일 자동삭제": 이 플러그인의 OCR(Tesseract.js)은 서버에 아무것도 올리지 않고
 *   브라우저 안에서만 캡처 이미지를 처리한다(assets/js/admin-ocr.js) — 애초에 서버 임시 파일이
 *   생기지 않으므로 지울 대상이 없다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Image_Pipeline {

	// 요청서: 매물 사진이 900px보다 커야 할 이유가 없다 — 코어 기본값(2560)보다 훨씬 낮게 잡는다
	// (hlf-item-photo 960×640 표시 사이즈보다도 살짝 작지만, 하드크롭 대상 원본이라 실제 화질 손실은
	// 미미하고 업로드/OCR/저장 용량 이득이 더 크다는 판단).
	const MAX_ORIGINAL_DIMENSION = 900;
	const JPEG_QUALITY           = 82;

	// GPT 코드 감사 P0#1: 이전 값(1MB/1000px)은 리사이즈 "전" 원본 업로드 자체에 거는 상한이었다 —
	// wp_handle_upload_prefilter는 코어가 900px로 축소하기 훨씬 전, 업로드된 원본 그대로의 크기를
	// 검사하므로 스마트폰 사진(예: 4032×3024, 3~8MB)이 리사이즈될 기회조차 없이 전부 거절되고 있었다
	// (실질적으로 "자동 리사이즈"가 아니라 "업로드 차단"으로 동작). 이 상한은 정상적인 사진을 절대
	// 걸러선 안 되고, 서버 리사이즈 처리 중 메모리를 과도하게 잡아먹는 비정상적으로 큰 원본(압축 폭탄
	// 등)만 막는 안전망이어야 한다 — 최신 스마트폰의 고해상도 센서(1억 화소급도 긴 변이 12000px
	// 내외)까지 감안해 넉넉하게 잡는다.
	const MAX_UPLOAD_BYTES     = 12 * 1024 * 1024; // 12MB
	const MAX_UPLOAD_DIMENSION = 10000; // px(가로/세로 각각의 상한).

	/**
	 * wp.media 업로더가 이 값과 함께 보내는 요청만 HLF 전용 처리 대상으로 본다(GPT 코드 감사 P0#2).
	 * assets/js/admin-listup.js·portal.js·admin-flyer-edit.js가 wp.media({ uploader: { params: {
	 * hlf_upload: '1' } } })로 매 업로드 요청에 이 값을 실어 보낸다. 이 플래그가 없는 업로드(테마
	 * 로고, ACF 필드, 다른 플러그인, 관리자 계정의 일반 미디어 업로드 등)는 이 클래스의 필터가 전혀
	 * 손대지 않는다 — 이전에는 네 필터 전부가 이 워드프레스 설치의 모든 이미지 업로드에 전역으로
	 * 적용되고 있었고, 특히 PNG→JPEG 강제 변환은 투명 배경이 필요한 로고·아이콘 등을 깨뜨릴 수 있는
	 * 위험한 부수효과였다. 인증/권한 검사가 아니라 "이 업로드가 어느 화면에서 왔는지" 구분용 표시일
	 * 뿐이므로 nonce 검증은 필요 없다(스푸핑해도 본인 업로드에 이 최적화가 적용되거나 안 되는 차이만
	 * 생길 뿐, 보안 경계를 넘지 않는다).
	 */
	const UPLOAD_FLAG = 'hlf_upload';

	public static function init(): void {
		add_filter( 'big_image_size_threshold', array( __CLASS__, 'max_original_dimension' ) );
		add_filter( 'wp_editor_set_quality', array( __CLASS__, 'jpeg_quality' ) );
		add_filter( 'image_editor_output_format', array( __CLASS__, 'prefer_jpeg_output' ) );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'restrict_image_upload' ) );
	}

	private static function is_hlf_upload_request(): bool {
		return isset( $_POST[ self::UPLOAD_FLAG ] ) && '1' === $_POST[ self::UPLOAD_FLAG ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	public static function max_original_dimension( $threshold ) {
		return self::is_hlf_upload_request() ? self::MAX_ORIGINAL_DIMENSION : $threshold;
	}

	public static function jpeg_quality( $quality ) {
		return self::is_hlf_upload_request() ? self::JPEG_QUALITY : $quality;
	}

	/** PNG로 올라온 사진의 파생 이미지(리사이즈·등록 사이즈)는 JPEG로 출력한다 — JPEG/WebP 등 이미
	 * 압축이 잘 되는 포맷은 그대로 둔다. HLF 업로드가 아니면 원래 포맷 맵을 그대로 돌려준다(투명
	 * PNG가 필요한 로고·아이콘 등을 이 플러그인이 실수로 JPEG로 바꾸지 않도록). */
	public static function prefer_jpeg_output( array $format_map ): array {
		if ( ! self::is_hlf_upload_request() ) {
			return $format_map;
		}
		$format_map['image/png'] = 'image/jpeg';
		return $format_map;
	}

	/**
	 * wp_handle_upload_prefilter는 이 워드프레스 설치의 모든 업로드(이미지가 아닌 파일 포함)에
	 * 걸리는 전역 훅이다 — HLF 업로드가 아니면 즉시 통과시키고, 이미지가 아닌 업로드도 그대로
	 * 통과시켜 사이트의 다른 용도(있다면)를 실수로 막지 않는다.
	 */
	public static function restrict_image_upload( array $file ): array {
		if ( ! self::is_hlf_upload_request() ) {
			return $file;
		}

		$filetype = wp_check_filetype( $file['name'] ?? '' );
		$is_image = $filetype['type'] && 0 === strpos( (string) $filetype['type'], 'image/' );
		if ( ! $is_image ) {
			return $file;
		}

		if ( ( $file['size'] ?? 0 ) > self::MAX_UPLOAD_BYTES ) {
			$file['error'] = sprintf(
				'이미지 파일이 너무 큽니다(최대 %dMB). 더 작은 파일로 다시 시도해 주세요.',
				(int) ( self::MAX_UPLOAD_BYTES / 1024 / 1024 )
			);
			return $file;
		}

		$tmp_name = $file['tmp_name'] ?? '';
		if ( $tmp_name && file_exists( $tmp_name ) ) {
			// getimagesize()는 실제 이미지 헤더를 읽어 크기를 확인한다(확장자 위장 방지 — wp_check_filetype는
			// 파일명만 본다). 손상된 파일 등으로 false가 나오면 이 검사는 건너뛰고 워드프레스의 나머지
			// 표준 업로드 검증(핸들러 자체)에 맡긴다.
			$dimensions = @getimagesize( $tmp_name );
			if ( $dimensions && ( $dimensions[0] > self::MAX_UPLOAD_DIMENSION || $dimensions[1] > self::MAX_UPLOAD_DIMENSION ) ) {
				$file['error'] = sprintf(
					'이미지 해상도가 너무 큽니다(최대 %dpx). 더 작은 이미지로 다시 시도해 주세요.',
					self::MAX_UPLOAD_DIMENSION
				);
			}
		}

		return $file;
	}
}
