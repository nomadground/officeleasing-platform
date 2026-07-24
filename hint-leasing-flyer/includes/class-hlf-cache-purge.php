<?php
/**
 * 요청서(실사용 버그): "매물 새로 등록/삭제/저장하거나 임대안내문을 수정해도, 워드프레스 관리자
 * 화면에서 수동으로 캐시를 지워야 공개 화면에 반영된다" — 페이지 캐시 플러그인(호스팅 환경에
 * 흔한 LiteSpeed Cache/WP Super Cache/W3 Total Cache/WP Rocket/SG Optimizer/Cache Enabler 등)이
 * 공개 /list/·/listup/ 페이지의 HTML을 통째로 캐시해 두면, 이 플러그인이 DB를 바로 바꿔도 그
 * 캐시가 알아서 갱신되지 않는다. 이 플러그인은 어떤 캐시 플러그인이 설치돼 있는지 알 수 없으므로,
 * 흔히 쓰이는 캐시 플러그인들이 제공하는 "전체 캐시 비우기" 함수/훅을 function_exists로 확인해
 * 있는 것만 호출한다(없으면 조용히 넘어감 — 캐시 플러그인이 아예 없는 사이트에서도 안전).
 *
 * 개별 postmeta 갱신마다(예: set_images가 사진 하나 뗄 때마다) 매번 캐시를 지우면 낭비이므로,
 * 요청 종료 시점(shutdown)에 한 번만 실행되도록 플래그만 세워 둔다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Cache_Purge {

	private static bool $dirty = false;
	private static bool $shutdown_hooked = false;

	public static function init(): void {
		foreach ( array( HLF_Post_Types::FLYER, HLF_Post_Types::ITEM, HLF_Post_Types::SOURCE ) as $post_type ) {
			add_action( "save_post_{$post_type}", array( __CLASS__, 'mark_dirty' ) );
		}
		// 삭제/휴지통 이동은 post_type이 인자로 오지 않으므로, 훅 안에서 직접 확인한다.
		add_action( 'deleted_post', array( __CLASS__, 'mark_dirty_if_relevant_post' ) );
		add_action( 'trashed_post', array( __CLASS__, 'mark_dirty_if_relevant_post' ) );
		// set_images()/apply_fields() 등은 post 자체(wp_update_post)가 아니라 postmeta만 바꾸는
		// 경우가 대부분이라(save_post가 아예 안 뜬다) meta 훅에서도 잡아야 한다.
		add_action( 'updated_post_meta', array( __CLASS__, 'mark_dirty_if_relevant_meta' ), 10, 2 );
		add_action( 'added_post_meta', array( __CLASS__, 'mark_dirty_if_relevant_meta' ), 10, 2 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'mark_dirty_if_relevant_meta' ), 10, 2 );
	}

	public static function mark_dirty(): void {
		self::$dirty = true;
		self::hook_shutdown();
	}

	public static function mark_dirty_if_relevant_post( int $post_id ): void {
		$type = get_post_type( $post_id );
		if ( in_array( $type, array( HLF_Post_Types::FLYER, HLF_Post_Types::ITEM, HLF_Post_Types::SOURCE ), true ) ) {
			self::mark_dirty();
		}
	}

	/** @param int $meta_id 실제로는 안 쓰지만 훅 시그니처(첫 인자)를 맞추기 위해 받는다. */
	public static function mark_dirty_if_relevant_meta( $meta_id, int $post_id ): void {
		$type = get_post_type( $post_id );
		if ( in_array( $type, array( HLF_Post_Types::FLYER, HLF_Post_Types::ITEM, HLF_Post_Types::SOURCE ), true ) ) {
			self::mark_dirty();
		}
	}

	private static function hook_shutdown(): void {
		if ( self::$shutdown_hooked ) {
			return;
		}
		self::$shutdown_hooked = true;
		add_action( 'shutdown', array( __CLASS__, 'purge_if_dirty' ) );
	}

	public static function purge_if_dirty(): void {
		if ( ! self::$dirty ) {
			return;
		}
		self::$dirty = false;
		self::purge_all();
	}

	/** 흔히 쓰이는 캐시 플러그인의 "전체 비우기" API를 있는 것만 호출한다. */
	public static function purge_all(): void {
		// 오브젝트 캐시(Redis/Memcached 등 영구 오브젝트 캐시가 있는 환경) — 코어 함수라 항상 안전.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		// WP Super Cache.
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		// W3 Total Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		// WP Rocket.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		// WP Fastest Cache.
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		}
		// Cache Enabler.
		if ( has_action( 'cache_enabler_clear_complete_cache' ) ) {
			do_action( 'cache_enabler_clear_complete_cache' );
		}
		// LiteSpeed Cache.
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		// SiteGround SG Optimizer.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		// WP Engine.
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_memcached' ) ) {
			WpeCommon::purge_memcached();
			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache();
			}
		}
	}
}
