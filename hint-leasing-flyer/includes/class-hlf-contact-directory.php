<?php
/**
 * 담당자 디렉터리(요청서 2-2). 담당자 이름+연락처를 저장해두고 재사용하는 작은 목록.
 *
 * 항목이 몇 개 안 되므로 별도 CPT/테이블을 만들지 않고 wp_option 하나에 배열로 저장한다(과설계 금지).
 * 이 디렉터리는 "값 채우기 편의"만 제공한다 — 공개 화면 문의처 계산(Item override → Flyer 기본값 →
 * 대표번호)과 Flyer/Item의 contact_name/contact_phone 필드는 전혀 바꾸지 않는다. 관리자 폼이 여기서
 * 담당자를 고르면 기존 contact_name/contact_phone 텍스트 입력란에 값만 채워 넣을 뿐이다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Contact_Directory {

	const OPTION = 'hlf_contact_directory';

	/** 저장 구조: array( 'contacts' => [ ['name'=>,'phone'=>], ... ], 'default_index' => int|null ). */
	private static function read(): array {
		$raw = get_option( self::OPTION, array() );
		$contacts = array();
		if ( is_array( $raw ) && isset( $raw['contacts'] ) && is_array( $raw['contacts'] ) ) {
			foreach ( $raw['contacts'] as $c ) {
				$contacts[] = array(
					'name'  => isset( $c['name'] ) ? (string) $c['name'] : '',
					'phone' => isset( $c['phone'] ) ? (string) $c['phone'] : '',
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

	/** REST 응답용: { contacts: [{index,name,phone,is_default}], default_index }. */
	public static function to_array(): array {
		$data = self::read();
		$out  = array();
		foreach ( $data['contacts'] as $i => $c ) {
			$out[] = array(
				'index'      => $i,
				'name'       => $c['name'],
				'phone'      => $c['phone'],
				'is_default' => ( $data['default_index'] === $i ),
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
		$data['contacts'][]  = array( 'name' => $name, 'phone' => $phone );
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
		$data['contacts'][ $index ] = array(
			'name'  => sanitize_text_field( $name ),
			'phone' => sanitize_text_field( $phone ),
		);
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
}
