/**
 * 매물/빌딩 상세 갤러리 썸네일 전환. 프로토타입에 있던 동작을 이식.
 * 썸네일 클릭 -> 메인 이미지 교체 + active 표시 + 인덱스(01 / 04) 갱신.
 * 요소가 없으면 조용히 종료(다른 페이지/구조 변경에도 안전).
 */
(function () {
	'use strict';

	function pad2( n ) {
		return ( '0' + n ).slice( -2 );
	}

	function init() {
		var main = document.querySelector( '.olx-gallery-main img' );
		var thumbs = document.querySelectorAll( '.olx-gallery-thumbs button' );
		var indexEl = document.querySelector( '.olx-image-index' );
		if ( ! main || ! thumbs.length ) {
			return;
		}
		var total = thumbs.length;

		thumbs.forEach( function ( btn, i ) {
			btn.addEventListener( 'click', function () {
				var img = btn.querySelector( 'img' );
				if ( img && img.getAttribute( 'src' ) ) {
					main.setAttribute( 'src', img.getAttribute( 'src' ) );
					var alt = img.getAttribute( 'alt' );
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
