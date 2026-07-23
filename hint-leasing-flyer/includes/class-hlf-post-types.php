<?php
/**
 * CPT 등록: leasing_flyer(공개 발행 단위) + leasing_flyer_item(비공개 스냅샷 항목).
 *
 * 설계(요청서 1-A):
 * - leasing_flyer:      public=false, show_ui=false, show_in_rest=false, publicly_queryable=false.
 *                       기본 워드프레스 관리자 화면(post-new.php 등)도, 코어 REST(/wp-json/wp/v2/
 *                       leasing_flyer)도 쓰지 않는다 — 이 플러그인 전용 REST 네임스페이스(hlf/v1,
 *                       class-hlf-rest-controller.php)와 그 REST를 쓰는 관리자 UI(class-hlf-admin-ui.php
 *                       + assets/js/admin-listup.js 등)로만 다룬다. 공개 노출은 CPT 기본 rewrite가
 *                       아니라 /list/ 커스텀 rewrite + template_include로만 한다.
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
	/** 원본 매물(전체 매물) — 어떤 Flyer에도 속하지 않는 독립 매물 카탈로그. Flyer에 "포함"하면
	 *  이 값을 복사해 leasing_flyer_item 스냅샷을 새로 만든다(HLF_Source_Listing_Repository 참고). */
	const SOURCE = 'hlf_source_listing';

	/** 커스텀 포스트 상태: 보관(읽기전용). */
	const STATUS_ARCHIVED = 'hlf_archived';

	public static function register(): void {
		self::register_flyer();
		self::register_item();
		self::register_source();
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
			// 기본 워드프레스 글 편집기(post.php)는 title/author만 지원해 커스텀 필드(담당자·주소·
			// 금액 등)와 item 관리 UI를 전혀 노출하지 못한다. 그 화면을 그대로 두면 "Leasing Flyer"
			// 메뉴가 두 개(기본 편집기 vs HLF_Admin_UI 커스텀 화면) 생겨 혼란만 준다 → 기본 UI는
			// 끄고, 편집은 HLF_Admin_UI가 등록하는 커스텀 REST 기반 화면 하나로만 진입하게 한다.
			'show_ui'             => false,
			'show_in_menu'        => false,
			// public=>false와 show_in_rest는 서로 무관하다 — show_in_rest=>true였던 이전 설정은
			// 코어가 /wp-json/wp/v2/leasing_flyer를 자동 등록해 "링크를 받은 사람만 접근"이라는
			// 설계 전제를 깨뜨렸다(publish 상태 Flyer가 이 코어 REST로 그대로 열람 가능해짐). 이
			// 플러그인은 모든 CRUD를 자체 hlf/v1 네임스페이스(HLF_REST_Controller)로만 처리하므로
			// 코어 REST 노출이 애초에 필요 없다 — Item/Source는 처음부터 false였다.
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false, // 공개 URL은 /list/ 커스텀 rewrite가 전담(HLF_Routes).
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author' ),
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

	/**
	 * 원본 매물(전체 매물) CPT. Flyer/Item과 달리 부모-자식 관계가 없는 독립 게시물이며, 공개 URL로
	 * 노출되지 않는다(관리자 카탈로그 전용). Item과 동일한 cap 집합을 공유해 같은 담당자가 다룬다 —
	 * REST permission_callback(can_edit_flyers 등)이 그대로 재사용된다. status는 'publish' 하나만
	 * 쓴다(발행/보관 개념이 없는 단순 카탈로그이므로 별도 커스텀 상태를 만들지 않는다). */
	private static function register_source(): void {
		register_post_type( self::SOURCE, array(
			'label'               => '전체 매물',
			'labels'              => array(
				'name'          => '전체 매물',
				'singular_name' => '매물',
				'add_new_item'  => '매물 등록',
				'edit_item'     => '매물 수정',
				'all_items'     => '전체 매물',
				'not_found'     => '등록된 매물이 없습니다',
			),
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'author' ),
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
