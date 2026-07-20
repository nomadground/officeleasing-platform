<?php
/**
 * 선택한 검색 결과를 실제로 내려받아 미디어 라이브러리 Attachment로 만들고, Item Repository를 통해
 * Item의 이미지 필드(exterior_image_id/interior_image_ids)에 반영하는 오케스트레이션(요청서 6).
 *
 * 책임 분리(요청서 3의 "검색 요청/응답 정규화/이미지 다운로드/Attachment 생성/Item Repository 저장"
 * 5단계 중 뒤 3개를 담당):
 *   - 다운로드는 HLF_Image_Url_Guard(SSRF 방어)를 거친다.
 *   - Attachment 생성은 이 클래스가 직접 한다(워드프레스에 "Attachment Repository" 같은 기존
 *     추상화가 없어 여기서 새로 만들 이유가 없다 — sideload는 원래 이 계층의 책임).
 *   - Item의 이미지 필드 자체는 반드시 HLF_Item_Repository::set_images()를 통해서만 쓴다
 *     (요청서: "직접 update_post_meta()를 남발하지 마십시오" — Attachment 자체의 provenance
 *     메타는 Item 스키마 밖의 값이라 이 클래스가 직접 기록한다).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Image_Import_Service {

	const MAX_BYTES = 8 * 1024 * 1024; // 8MB.

	const EXTENSION_BY_MIME = array(
		'image/jpeg' => 'jpg',
		'image/jpg'  => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
		'image/gif'  => 'gif',
	);

	/** 중복 방지 색인에 쓰는 postmeta 키(같은 Item + 같은 원본 URL이면 새로 만들지 않는다). */
	const META_SOURCE_URL_HASH = '_hlf_source_url_hash';

	/**
	 * 선택된 검색 결과들을 이 Item에 실제로 가져온다.
	 *
	 * @param array<int, array{image_url:string,title?:string,source_url?:string,source?:string}> $image_descriptors
	 * @return array|WP_Error 갱신된 item(HLF_Item_Repository::to_array 형태)
	 */
	public static function import_selected( int $flyer_id, int $item_id, array $image_descriptors, string $search_term ) {
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer에 속한 매물이 아닙니다.', array( 'status' => 404 ) );
		}

		if ( empty( $image_descriptors ) ) {
			return new WP_Error( 'hlf_image_none_selected', '가져올 이미지를 선택해 주세요.', array( 'status' => 400 ) );
		}

		$current  = HLF_Meta_Schema::read_item( $item_id );
		$exterior = (int) $current['exterior_image_id'];
		$interior = $current['interior_image_ids'];

		foreach ( $image_descriptors as $descriptor ) {
			if ( ! is_array( $descriptor ) ) {
				continue;
			}
			$attachment_id = self::import_one( $item_id, $descriptor, $search_term );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			if ( $attachment_id === $exterior || in_array( $attachment_id, $interior, true ) ) {
				continue; // 이미 이 Item에 연결됨(중복 가져오기 방지로 같은 attachment가 재사용된 경우).
			}
			if ( 0 === $exterior ) {
				$exterior = $attachment_id; // 대표 이미지가 아직 없으면 첫 이미지를 대표로 자동 지정.
			} else {
				$interior[] = $attachment_id;
			}
		}

		$saved = HLF_Item_Repository::set_images( $flyer_id, $item_id, $exterior, $interior );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return HLF_Item_Repository::to_array( get_post( $item_id ) );
	}

	/**
	 * 이미지 하나를 실제로 내려받아 Attachment로 만든다(이미 있으면 기존 attachment_id 재사용).
	 *
	 * @return int|WP_Error
	 */
	private static function import_one( int $item_id, array $descriptor, string $search_term ) {
		$image_url = esc_url_raw( (string) ( $descriptor['image_url'] ?? '' ) );
		if ( '' === $image_url ) {
			return new WP_Error( 'hlf_image_missing_url', '이미지 주소가 없습니다.', array( 'status' => 400 ) );
		}

		$existing = self::find_existing_attachment( $item_id, $image_url );
		if ( $existing ) {
			return $existing;
		}

		$fetch = HLF_Image_Url_Guard::safe_get( $image_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $fetch ) ) {
			return $fetch;
		}
		$response = $fetch['response'];

		$content_type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$mime         = strtolower( trim( explode( ';', $content_type )[0] ?? '' ) );
		if ( 0 !== strpos( $mime, 'image/' ) ) {
			return new WP_Error( 'hlf_image_bad_content_type', '지원하지 않는 이미지 형식입니다.', array( 'status' => 415 ) );
		}

		$extension = self::EXTENSION_BY_MIME[ $mime ] ?? null;
		if ( ! $extension ) {
			return new WP_Error( 'hlf_image_bad_extension', '지원하지 않는 이미지 형식입니다.', array( 'status' => 415 ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return new WP_Error( 'hlf_image_empty_body', '이미지를 내려받지 못했습니다.', array( 'status' => 502 ) );
		}
		if ( strlen( $body ) > self::MAX_BYTES ) {
			return new WP_Error( 'hlf_image_too_large', '파일 크기가 너무 큽니다.', array( 'status' => 413 ) );
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// 파일명은 원본 파일명/검색어에 의존하지 않고 매번 새로 생성한다(충돌·특수문자 방지 — 요청서 6).
		$filename = 'hlf-' . $item_id . '-' . substr( md5( $image_url . microtime() ), 0, 12 ) . '.' . $extension;

		$upload = wp_upload_bits( $filename, null, $body );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'hlf_image_upload_failed', '이미지 저장에 실패했습니다.', array( 'status' => 500 ) );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime,
				'post_title'     => sanitize_text_field( (string) ( $descriptor['title'] ?? $search_term ) ),
				'post_status'    => 'inherit',
				'post_parent'    => $item_id,
			),
			$upload['file'],
			$item_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $upload['file'] );
			return $attachment_id;
		}
		$attachment_id = (int) $attachment_id;

		$attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		$flyer_id_of_item = (int) get_post_field( 'post_parent', $item_id );
		update_post_meta( $attachment_id, '_hlf_source_search_term', sanitize_text_field( $search_term ) );
		update_post_meta( $attachment_id, '_hlf_source_image_url', $image_url );
		update_post_meta( $attachment_id, '_hlf_source_page_url', esc_url_raw( (string) ( $descriptor['source_url'] ?? '' ) ) );
		update_post_meta( $attachment_id, '_hlf_source_provider', sanitize_key( (string) ( $descriptor['source'] ?? 'unknown' ) ) );
		update_post_meta( $attachment_id, '_hlf_imported_at', current_time( 'mysql', true ) );
		update_post_meta( $attachment_id, '_hlf_flyer_id', $flyer_id_of_item );
		update_post_meta( $attachment_id, '_hlf_item_id', $item_id );
		update_post_meta( $attachment_id, self::META_SOURCE_URL_HASH, md5( $image_url ) );

		return $attachment_id;
	}

	/** 같은 Item + 같은 원본 URL로 이미 가져온 attachment가 있으면 그 ID, 없으면 null. */
	private static function find_existing_attachment( int $item_id, string $image_url ): ?int {
		$found = get_posts( array(
			'post_type'      => 'attachment',
			'post_parent'    => $item_id,
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::META_SOURCE_URL_HASH,
			'meta_value'     => md5( $image_url ),
		) );
		return $found ? (int) $found[0] : null;
	}
}
