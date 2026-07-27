<?php
/**
 * 담당자 디렉터리(요청서 2-2). 담당자 이름+연락처를 저장해두고 재사용하는 작은 목록.
 *
 * 항목이 몇 개 안 되므로 별도 CPT/테이블을 만들지 않고 wp_option 하나에 배열로 저장한다(과설계 금지).
 * 이 디렉터리는 "값 채우기 편의"만 제공한다 — 공개 화면 문의처 계산(Item override → Flyer 기본값 →
 * 대표번호)과 Flyer/Item의 contact_name/contact_phone 필드는 전혀 바꾸지 않는다. 관리자 폼이 여기서
 * 담당자를 고르면 기존 contact_name/contact_phone 텍스트 입력란에 값만 채워 넣을 뿐이다.
 *
 * 명함 이미지(요청서: 카카오톡 등 SNS 공유 시 링크 미리보기 썸네일): 담당자별로 이미지를
 * attachment ID로만 들고 있다(파일 복제 없음, Item 사진과 같은 원칙) — 공개 템플릿이 문의처의
 * 이름으로 이 디렉터리를 찾아 og:image에 쓴다(find_image_url_by_name()).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Contact_Directory {

	const OPTION = 'hlf_contact_directory';

	/** 저장 구조: array( 'contacts' => [ ['name'=>,'phone'=>,'image_id'=>int], ... ], 'default_index' => int|null ). */
	private static function read(): array {
		$raw = get_option( self::OPTION, array() );
		$contacts = array();
		if ( is_array( $raw ) && isset( $raw['contacts'] ) && is_array( $raw['contacts'] ) ) {
			foreach ( $raw['contacts'] as $c ) {
				$contacts[] = array(
					'name'     => isset( $c['name'] ) ? (string) $c['name'] : '',
					'phone'    => isset( $c['phone'] ) ? (string) $c['phone'] : '',
					'image_id' => isset( $c['image_id'] ) ? (int) $c['image_id'] : 0,
				);
			}
		}
		$default_index = isset( $raw['default_index'] ) && is_numeric( $raw['default_index'] ) ? (int) $raw['default_index'] : null;
		if ( null !== $default_index && ! isset( $contacts[ $default_index ] ) ) {
			$default_index = null;
		}
		return array( 'contacts' => $contacts, 'default_index' => $default_index );
	}

	private static function write( array $data ): void {
		update_option( self::OPTION, array(
			'contacts'      => array_values( $data['contacts'] ),
			'default_index' => $data['default_index'],
		) );
	}

	/** REST 응답용: { contacts: [{index,name,phone,is_default,image_id,image_url}], default_index }. */
	public static function to_array(): array {
		$data = self::read();
		$out  = array();
		foreach ( $data['contacts'] as $i => $c ) {
			$image_url = '';
			if ( ! empty( $c['image_id'] ) ) {
				// 목록 화면 미리보기용 — 워드프레스 기본 썸네일 크기(항상 생성돼 있음)만 쓴다.
				// 실제 og:image는 원본 URL을 그대로 쓴다(find_image_url_by_name() 참고).
				$thumb = wp_get_attachment_image_url( $c['image_id'], 'thumbnail' );
				$image_url = $thumb ?: ( wp_get_attachment_url( $c['image_id'] ) ?: '' );
			}
			$out[] = array(
				'index'      => $i,
				'name'       => $c['name'],
				'phone'      => $c['phone'],
				'is_default' => ( $data['default_index'] === $i ),
				'image_id'   => $c['image_id'],
				'image_url'  => $image_url,
			);
		}
		return array( 'contacts' => $out, 'default_index' => $data['default_index'] );
	}

	/**
	 * 신규 Flyer/매물 생성 시 미리 채울 기본 담당자. 우선순위: 설정된 기본값 → (없으면) 첫 번째 담당자.
	 * @return array{name:string,phone:string}|null 담당자가 하나도 없으면 null.
	 */
	public static function default_contact(): ?array {
		$data = self::read();
		if ( empty( $data['contacts'] ) ) {
			return null;
		}
		$index = null !== $data['default_index'] ? $data['default_index'] : 0;
		return isset( $data['contacts'][ $index ] ) ? $data['contacts'][ $index ] : $data['contacts'][0];
	}

	public static function add( string $name, string $phone ): int|WP_Error {
		$name  = sanitize_text_field( $name );
		$phone = sanitize_text_field( $phone );
		if ( '' === trim( $name ) && '' === trim( $phone ) ) {
			return new WP_Error( 'hlf_contact_empty', '담당자 이름이나 연락처 중 하나는 입력해 주세요.', array( 'status' => 400 ) );
		}
		$data                = self::read();
		$data['contacts'][]  = array( 'name' => $name, 'phone' => $phone, 'image_id' => 0 );
		$new_index           = count( $data['contacts'] ) - 1;
		if ( null === $data['default_index'] ) {
			$data['default_index'] = $new_index; // 첫 담당자는 자동으로 기본값.
		}
		self::write( $data );
		return $new_index;
	}

	public static function update( int $index, string $name, string $phone ): bool|WP_Error {
		$data = self::read();
		if ( ! isset( $data['contacts'][ $index ] ) ) {
			return new WP_Error( 'hlf_contact_not_found', '담당자를 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		// image_id는 여기서 건드리지 않는다 — 이름/연락처 수정과 명함 이미지 설정은 별개 작업이라
		// 이 메서드가 통째로 덮어쓰면 이름만 고쳐도 이미 등록해 둔 명함이 사라진다(실사용 버그).
		$data['contacts'][ $index ]['name']  = sanitize_text_field( $name );
		$data['contacts'][ $index ]['phone'] = sanitize_text_field( $phone );
		self::write( $data );
		return true;
	}

	public static function remove( int $index ): bool|WP_Error {
		$data = self::read();
		if ( ! isset( $data['contacts'][ $index ] ) ) {
			return new WP_Error( 'hlf_contact_not_found', '담당자를 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		$was_default = ( $data['default_index'] === $index );
		array_splice( $data['contacts'], $index, 1 );

		// 삭제로 인덱스가 당겨지므로 default_index를 보정한다.
		if ( $was_default ) {
			$data['default_index'] = empty( $data['contacts'] ) ? null : 0;
		} elseif ( null !== $data['default_index'] && $data['default_index'] > $index ) {
			$data['default_index']--;
		}
		self::write( $data );
		return true;
	}

	public static function set_default( int $index ): bool|WP_Error {
		$data = self::read();
		if ( ! isset( $data['contacts'][ $index ] ) ) {
			return new WP_Error( 'hlf_contact_not_found', '담당자를 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		$data['default_index'] = $index;
		self::write( $data );
		return true;
	}

	/**
	 * 명함 이미지 지정/해제. $attachment_id=0이면 해제(detach-only, 파일 자체는 지우지 않음 — Item
	 * 사진과 같은 원칙). Media Library(wp.media)에서 고른 attachment ID만 받으므로 "실제로 존재하는
	 * 이미지 attachment인지"와 "이 사용자가 읽을 수 있는지"만 확인한다(소유권/post_parent는 확인하지
	 * 않는다 — 사이트에 이미 있는 다른 미디어를 그대로 재사용할 수도 있어야 한다).
	 */
	public static function set_image( int $index, int $attachment_id ): bool|WP_Error {
		$data = self::read();
		if ( ! isset( $data['contacts'][ $index ] ) ) {
			return new WP_Error( 'hlf_contact_not_found', '담당자를 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		if ( $attachment_id > 0 ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error( 'hlf_contact_image_invalid', '선택한 항목이 유효한 이미지가 아닙니다.', array( 'status' => 400 ) );
			}
			if ( ! current_user_can( 'read_post', $attachment_id ) ) {
				return new WP_Error( 'hlf_contact_image_forbidden', '이 이미지를 사용할 권한이 없습니다.', array( 'status' => 403 ) );
			}
		}
		$data['contacts'][ $index ]['image_id'] = $attachment_id;
		self::write( $data );
		return true;
	}

	public static function remove_image( int $index ): bool|WP_Error {
		return self::set_image( $index, 0 );
	}

	/**
	 * 공개 화면(og:image)에서 쓴다 — Flyer/Item의 문의처는 이름/전화만 저장하고 이 디렉터리를
	 * 참조하지 않으므로(설계 원칙: 디렉터리는 "값 채우기 편의"일 뿐), 표시될 담당자 이름으로
	 * 역으로 디렉터리를 찾아 명함 이미지가 있으면 그 원본 URL을 돌려준다. 이름이 정확히
	 * 일치해야 하므로(공백 트림 후) 관리자 폼에서 디렉터리 선택으로 채운 이름을 그대로 두면
	 * 항상 맞는다 — 수동으로 다르게 고쳐 쓴 경우에는 매칭되지 않는다(알려진 한계, 문서화 필요시
	 * README 참고).
	 */
	public static function find_image_url_by_name( string $name ): ?string {
		$name = trim( $name );
		if ( '' === $name ) {
			return null;
		}
		$data = self::read();
		foreach ( $data['contacts'] as $c ) {
			if ( trim( $c['name'] ) === $name && ! empty( $c['image_id'] ) ) {
				$url = wp_get_attachment_url( (int) $c['image_id'] );
				if ( $url ) {
					return $url;
				}
			}
		}
		return null;
	}
}
