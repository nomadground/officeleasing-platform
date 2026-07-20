<?php
/**
 * 이미지 검색 provider 계약(요청서 3). 특정 검색 서비스에 결합하지 않기 위한 최소 경계 —
 * 지금은 HLF_Naver_Image_Search_Provider 하나뿐이지만, 나중에 다른 provider를 추가할 때
 * HLF_Image_Search_Service/REST/관리자 JS 어느 쪽도 건드릴 필요가 없게 한다.
 */
defined( 'ABSPATH' ) || exit;

interface HLF_Image_Search_Provider_Interface {

	/** 이 provider가 실제로 호출 가능한 상태인지(인증정보 등). */
	public function is_configured(): bool;

	/**
	 * @param string $query
	 * @param int    $limit
	 * @return array<int, array{source:string,title:string,thumbnail_url:string,image_url:string,source_url:string,width:int,height:int}>|WP_Error
	 */
	public function search( string $query, int $limit );
}
