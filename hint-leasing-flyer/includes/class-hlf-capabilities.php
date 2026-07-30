<?php
/**
 * Capability 등록/부여 (요청서 1-F).
 *
 * - administrator : 전체 cap.
 * - editor        : 생성/수정/발행 cap.
 * - leasing_flyer_staff : 생성/수정/발행 전용 커스텀 역할(관리자 화면 최소 권한).
 * - 비로그인 사용자 : cap 없음 → published Flyer만 공개 템플릿(template_include)으로 읽기.
 *
 * add_caps()는 활성화 훅에서 1회 호출된다. cap 세트를 바꿀 때는 HLF_CAPS_VERSION을 올려
 * 관리자 접속 시 자동 재적용되게 한다(대량 사이트에서도 안전한 1회성 마이그레이션).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Capabilities {

	const VERSION_OPTION = 'hlf_caps_version';
	const VERSION        = 2; // 2: staff_caps()에 upload_files 추가(P0-1) — 기존 설치에도 admin_init 시 재적용되도록 버전 상향.

	/** 관리자 전체 cap. */
	public static function all_caps(): array {
		return array(
			'edit_leasing_flyer',
			'read_leasing_flyer',
			'delete_leasing_flyer',
			'edit_leasing_flyers',
			'edit_others_leasing_flyers',
			'publish_leasing_flyers',
			'read_private_leasing_flyers',
			'delete_leasing_flyers',
			'manage_leasing_flyer_settings',
		);
	}

	/**
	 * 생성/수정/발행 담당(설정 관리 cap 제외).
	 *
	 * upload_files는 워드프레스 코어 cap이다 — 이게 없으면 wp.media 업로더 자체가 열리지 않아
	 * leasing_flyer_staff로 로그인한 직원은 매물 사진 업로드/선택이 불가능하다(administrator/
	 * editor는 코어가 기본으로 이미 갖고 있어 지금까지는 드러나지 않았던 문제).
	 */
	public static function staff_caps(): array {
		return array(
			'edit_leasing_flyer',
			'read_leasing_flyer',
			'delete_leasing_flyer',
			'edit_leasing_flyers',
			'edit_others_leasing_flyers',
			'publish_leasing_flyers',
			'read_private_leasing_flyers',
			'delete_leasing_flyers',
			'upload_files',
		);
	}

	public static function add_caps(): void {
		// 전용 스태프 역할 생성(이미 있으면 유지).
		if ( ! get_role( 'leasing_flyer_staff' ) ) {
			add_role( 'leasing_flyer_staff', 'Leasing Flyer 담당자', array( 'read' => true ) );
		}

		$grants = array(
			'administrator'       => self::all_caps(),
			'editor'              => self::staff_caps(),
			'leasing_flyer_staff' => self::staff_caps(),
		);

		foreach ( $grants as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	public static function remove_caps(): void {
		$roles = array( 'administrator', 'editor', 'leasing_flyer_staff' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all_caps() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		remove_role( 'leasing_flyer_staff' );
	}

	/** cap 세트 버전이 바뀌면 관리자 요청 시 1회 재적용(활성화 훅을 못 탄 기존 설치 대비). */
	public static function maybe_upgrade(): void {
		if ( (int) get_option( self::VERSION_OPTION ) === self::VERSION ) {
			return;
		}
		self::add_caps();
	}
}

add_action( 'admin_init', array( 'HLF_Capabilities', 'maybe_upgrade' ) );
