<?php
/**
 * CPT 등록: leasing_flyer(공개 발행 단위) + leasing_flyer_item(비공개 스냅샷 항목).
 *
 * 설계(요청서 1-A):
 * - leasing_flyer:      public=false, show_ui=true, show_in_rest=true, publicly_queryable=false.
 *                       공개 노출은 CPT 기본 rewrite가 아니라 /listup/ 커스텀 rewrite + template_include로만 한다.
 * - leasing_flyer_item: public=false, show_ui=false, show_in_menu=false. 발행 시점 조건 snapshot.
 *                       개별 wp-admin 편집 화면을 열지 않고, 항상 부모 flyer 권한으로 REST를 통해서만 다룬다.
 * - 커스텀 상태 'archived': 기존 공유 링크는 읽기 전용으로 유지하되 신규 공유만 중단(요청서 1-I).
 *
 * capability_type=leasing_flyer + map_meta_cap=true 로 두면 워드프레스가 요청서 1-F의 명명된 primitive
 * cap(edit_leasing_flyers 등)으로 자동 매핑한다. item도 같은 cap 집합을 공유해 edit_post 검사가 일관되게 흐른다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Post_Types {

	const FLYER = 'leasing_flyer';
	const ITEM  = 'leasing_flyer_item';

	/** 커스텀 포스트 상태: 보관(읽기전용). */
	const STATUS_ARCHIVED = 'hlf_archived';

	public static function register(): void {
		self::register_flyer();
		self::register_item();
		self::register_statuses();
	}

	private static function flyer_capabilities(): array {
		return array(
			'edit_post'              => 'edit_leasing_flyer',
			'read_post'              => 'read_leasing_flyer',
			'delete_post'            => 'delete_leasing_flyer',
			'edit_posts'             => 'edit_leasing_flyers',
			'edit_others_posts'      => 'edit_others_leasing_flyers',
			'publish_posts'          => 'publish_leasing_flyers',
			'read_private_posts'     => 'read_private_leasing_flyers',
			'delete_posts'           => 'delete_leasing_flyers',
			'delete_private_posts'   => 'delete_leasing_flyers',
			'delete_published_posts' => 'delete_leasing_flyers',
			'delete_others_posts'    => 'delete_leasing_flyers',
			'edit_private_posts'     => 'edit_leasing_flyers',
			'edit_published_posts'   => 'edit_leasing_flyers',
			'create_posts'           => 'edit_leasing_flyers',
		);
	}

	private static function register_flyer(): void {
		register_post_type( self::FLYER, array(
			'label'               => 'Leasing Flyer',
			'labels'              => array(
				'name'          => 'Leasing Flyer',
				'singular_name' => 'Leasing Flyer',
				'add_new_item'  => 'Flyer 추가',
				'edit_item'     => 'Flyer 수정',
				'all_items'     => '전체 Flyer',
				'not_found'     => '등록된 Flyer가 없습니다',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false, // 공개 URL은 /listup/ 커스텀 rewrite가 전담(HLF_Routes).
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author' ),
			'menu_icon'           => 'dashicons-media-spreadsheet',
			'menu_position'       => 22,
			'capability_type'     => array( 'leasing_flyer', 'leasing_flyers' ),
			'map_meta_cap'        => true,
			'capabilities'        => self::flyer_capabilities(),
		) );
	}

	private static function register_item(): void {
		register_post_type( self::ITEM, array(
			'label'               => 'Flyer 항목',
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			// item은 부모 flyer 권한(edit_post on flyer)으로만 다룬다. 같은 cap 집합을 공유.
			'capability_type'     => array( 'leasing_flyer', 'leasing_flyers' ),
			'map_meta_cap'        => true,
			'capabilities'        => self::flyer_capabilities(),
		) );
	}

	private static function register_statuses(): void {
		register_post_status( self::STATUS_ARCHIVED, array(
			'label'                     => '보관',
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			// translators: %s: 보관된 Flyer 개수.
			'label_count'               => _n_noop( '보관 <span class="count">(%s)</span>', '보관 <span class="count">(%s)</span>' ),
		) );
	}
}
