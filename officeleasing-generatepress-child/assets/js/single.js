/**
 * 매물/빌딩 상세 갤러리 썸네일 전환. 프로토타입에 있던 동작을 이식.
 * 썸네일 클릭 -> 메인 이미지 교체 + active 표시 + 인덱스(01 / 04) 갱신.
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

	function pad2( n ) {
		return ( '0' + n ).slice( -2 );
	}

	function init() {
		var main = document.querySelector( '.olx-gallery-main img' );
		var source = document.querySelector( '.olx-gallery-main picture source' );
		var thumbs = document.querySelectorAll( '.olx-gallery-thumbs button' );
		var indexEl = document.querySelector( '.olx-image-index' );
		if ( ! main || ! thumbs.length ) {
			return;
		}
		var total = thumbs.length;

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
				if ( indexEl ) {
					indexEl.textContent = pad2( i + 1 ) + ' / ' + pad2( total );
				}
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
