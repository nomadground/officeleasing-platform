/**
 * 매물/빌딩 상세 갤러리 썸네일 전환. 프로토타입에 있던 동작을 이식.
 * 썸네일 클릭 -> 메인 이미지 교체 + active 표시.
 * 요소가 없으면 조용히 종료(다른 페이지/구조 변경에도 안전).
 *
 * [버그 수정] Hero가 <picture><source media="(max-width:900px)">...<img srcset>...</picture>
 * 구조로 바뀐 뒤, 예전처럼 메인 img의 src만 바꾸면 두 가지로 깨졌다:
 *  - 모바일(<=900px): <source>의 srcset이 계속 첫 번째 이미지를 가리켜서 브라우저가 그쪽을 우선
 *    선택 - src를 바꿔도 화면이 안 바뀌거나 전환이 불안정했다.
 *  - 데스크톱: 메인 img에 남아있는 srcset 후보가 새 src보다 우선 선택될 수 있었다.
 * 그래서 클릭 시 <source>의 srcset과 메인 img의 src/srcset을 함께, 동일한 단일 URL로 갱신한다
 * (사용된 URL은 900x600 - 900x600 하나짜리라 srcset에 후보가 하나뿐이므로 뷰포트와 무관하게
 * 항상 그 이미지로 확정된다. 처음 로드 시의 desktop/mobile 분리 전달은 그대로 유지되고,
 * "클릭해서 확대 보기" 상태에서만 반응형 분기를 내려놓는다).
 */
(function () {
	'use strict';

	function init() {
		var main = document.querySelector( '.olx-gallery-main img' );
		var source = document.querySelector( '.olx-gallery-main picture source' );
		var thumbs = document.querySelectorAll( '.olx-gallery-thumbs button' );
		if ( ! main || ! thumbs.length ) {
			return;
		}

		thumbs.forEach( function ( btn, i ) {
			btn.addEventListener( 'click', function () {
				var full = btn.getAttribute( 'data-full' );
				if ( full ) {
					if ( source ) {
						source.setAttribute( 'srcset', full );
					}
					main.setAttribute( 'src', full );
					main.setAttribute( 'srcset', full );
					var alt = btn.getAttribute( 'data-full-alt' );
					if ( alt ) {
						main.setAttribute( 'alt', alt );
					}
				}
				thumbs.forEach( function ( b ) {
					b.classList.remove( 'is-active' );
				} );
				btn.classList.add( 'is-active' );
			} );
		} );
	}

	/**
	 * 매물 2~3건 빌딩의 "면적 버튼 토글" (single-building.php).
	 * 각 매물의 표시값은 PHP가 <script type="application/json" id="olx-toggle-data">에 미리 임베드해둔다 -
	 * 버튼 클릭 시 이 값들 사이에서만 DOM 텍스트를 바꿔치기하고, 서버 재쿼리는 절대 하지 않는다
	 * (카드 렌더 시 매물 재쿼리 금지 원칙, single-building.php 상단 주석과 동일 취지).
	 * 상단 Hero(.olx-specs3/.olx-price)와 하단 "임대 정보" 섹션의 매물 카드가 같은 선택 상태를 공유한다 -
	 * 다만 카드 자체(listing-card.php, 다른 페이지에서도 재사용되는 컴포넌트)는 그대로 빌딩 링크이므로
	 * 클릭을 가로채지 않는다 - 동기화는 "상단 버튼 -> 상단 값 + 하단 카드 하이라이트" 단방향이다.
	 */
	function initListingToggle() {
		var dataEl = document.getElementById( 'olx-toggle-data' );
		var buttons = document.querySelectorAll( '.olx-listing-toggle button' );
		if ( ! dataEl || ! buttons.length ) {
			return;
		}
		var listings;
		try {
			listings = JSON.parse( dataEl.textContent );
		} catch ( e ) {
			return;
		}
		var fieldEls = document.querySelectorAll( '#olx-toggle-specs [data-toggle-field], .olx-price [data-toggle-field]' );
		var cards = document.querySelectorAll( '#olx-toggle-cards .olx-toggle-card' );

		function select( index ) {
			var data = listings[ index ];
			if ( ! data ) {
				return;
			}
			fieldEls.forEach( function ( el ) {
				var field = el.getAttribute( 'data-toggle-field' );
				if ( field && Object.prototype.hasOwnProperty.call( data, field ) ) {
					el.textContent = data[ field ];
				}
			} );
			buttons.forEach( function ( btn ) {
				var isActive = btn.getAttribute( 'data-listing-index' ) === String( index );
				btn.classList.toggle( 'is-active', isActive );
				btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			cards.forEach( function ( card ) {
				card.classList.toggle( 'is-active', card.getAttribute( 'data-listing-index' ) === String( index ) );
			} );
		}

		buttons.forEach( function ( btn ) {
			// [listing-detail-ux-pass3] "마우스오버나 선택 시" 요청 반영 - 클릭(선택 고정)에 더해
			// hover만으로도 미리보기 전환이 되도록 mouseenter를 추가한다. 별도 상태 플래그 없이
			// 그냥 동일한 select()를 재사용한다 - 마우스가 버튼을 떠나도 원래 값으로 되돌리지 않는
			// 이유는, 다른 버튼에도 곧 hover가 옮겨가거나 클릭으로 이어지는 경우가 대부분이라
			// "마지막으로 본 값"을 유지하는 편이 자연스럽고, 포커스/터치 기기(hover 자체가 없음)에서도
			// click만으로 동일하게 동작해 일관성이 깨지지 않는다.
			btn.addEventListener( 'click', function () {
				select( btn.getAttribute( 'data-listing-index' ) );
			} );
			btn.addEventListener( 'mouseenter', function () {
				select( btn.getAttribute( 'data-listing-index' ) );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
			initListingToggle();
		} );
	} else {
		init();
		initListingToggle();
	}
})();
