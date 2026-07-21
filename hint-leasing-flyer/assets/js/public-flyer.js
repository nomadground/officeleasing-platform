/**
 * 공개 Flyer 화면(목록/상세) 전용 JS. 빌드 없이 <script> 태그로 그대로 로드된다(관리자
 * 화면의 admin-common.js/admin-flyer-*.js와 완전히 분리된 별도 전역 네임스페이스).
 *
 * 이 파일은 순수 "시각적 표시" 로직만 담당한다 — 데이터 계산(NOC 등)은 전부 서버
 * (HLF_Calculations)가 이미 끝낸 값을 HTML data-* 속성으로 그대로 받아 그리기만 하고,
 * REST 호출이나 postmeta 저장은 하지 않는다(공개 화면은 저장 로직을 갖지 않는다).
 */
( function () {
	'use strict';

	function escapeHtml( value ) {
		if ( value === null || value === undefined ) { return ''; }
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function escapeAttr( value ) {
		return escapeHtml( value ).replace( /"/g, '&quot;' ).replace( /'/g, '&#039;' );
	}

	/* ---------------- NOC 비교 차트 ---------------- */

	// MVP 기준본(renderNocChart)과 동일한 적응형 스케일 알고리즘 — 현재 매물 범위(최댓값-최솟값)의
	// 1.5배를 표시 범위로 잡아 막대 높이 차이가 지나치게 크거나(한 매물만 삐죽) 작게(다 비슷해
	// 보임) 뭉개지지 않게 한다. 최소 표시 범위는 1(전부 같은 값이어도 막대가 납작해지지 않도록).
	function barHeightPercent( value, chartMin, chartMax ) {
		var ratio = Math.min( 1, Math.max( 0, ( value - chartMin ) / ( chartMax - chartMin ) ) );
		return Math.round( ( 0.25 + ratio * 0.7 ) * 100 );
	}

	function renderNocChart() {
		var el = document.getElementById( 'hlf-noc-chart' );
		if ( ! el ) { return; }

		var items;
		try {
			items = JSON.parse( el.getAttribute( 'data-hlf-noc-items' ) || '[]' );
		} catch ( e ) {
			return;
		}
		if ( ! items.length ) { return; }

		var values = items.map( function ( it ) { return Number( it.noc ); } );
		var dataMin = Math.min.apply( null, values );
		var dataMax = Math.max.apply( null, values );
		var displayRange = Math.max( ( dataMax - dataMin ) * 1.5, 1 );
		var center = ( dataMin + dataMax ) / 2;
		var chartMin = Math.max( 0, center - displayRange / 2 );
		var chartMax = Math.max( chartMin + 1, center + displayRange / 2 );

		el.innerHTML = items.map( function ( it ) {
			var noc = Number( it.noc );
			var height = barHeightPercent( noc, chartMin, chartMax );
			var label = String( it.order + 1 );
			if ( label.length < 2 ) { label = '0' + label; }
			var title = it.address + ' NOC ' + noc.toFixed( 1 ) + '만원';
			return (
				'<a class="hlf-noc-chart-item" href="' + escapeAttr( it.url ) + '" title="' + escapeAttr( title ) + '" aria-label="' + escapeAttr( label + '번 매물 상세보기' ) + '">' +
					'<span class="hlf-noc-chart-value">' + noc.toFixed( 1 ) + '</span>' +
					'<span class="hlf-noc-chart-bar-wrap"><span class="hlf-noc-chart-bar" style="height:' + height + '%"></span></span>' +
					'<span class="hlf-noc-chart-label">' + escapeHtml( label ) + '</span>' +
				'</a>'
			);
		} ).join( '' );
	}

	/* ---------------- 공유 링크(Clipboard API + fallback) ---------------- */

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		return new Promise( function ( resolve, reject ) {
			var textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();
			try {
				var ok = document.execCommand( 'copy' );
				document.body.removeChild( textarea );
				ok ? resolve() : reject( new Error( 'copy command failed' ) );
			} catch ( e ) {
				document.body.removeChild( textarea );
				reject( e );
			}
		} );
	}

	function bindShareButtons() {
		var buttons = document.querySelectorAll( '[data-hlf-share-url]' );
		buttons.forEach( function ( button ) {
			var statusEl = button.querySelector( '[data-hlf-share-status]' );
			button.addEventListener( 'click', function () {
				var url = button.getAttribute( 'data-hlf-share-url' );
				copyText( url )
					.then( function () {
						if ( statusEl ) {
							statusEl.textContent = '링크 복사됨';
							statusEl.classList.remove( 'is-error' );
							statusEl.classList.add( 'is-success' );
						}
					} )
					.catch( function () {
						if ( statusEl ) {
							statusEl.textContent = '복사 실패 — 직접 복사해 주세요';
							statusEl.classList.remove( 'is-success' );
							statusEl.classList.add( 'is-error' );
						}
					} );
			} );
		} );
	}

	/* ---------------- 갤러리 라이트박스(이전/다음) ---------------- */

	function bindLightbox() {
		var gallery = document.querySelector( '[data-hlf-photos]' );
		var lightbox = document.getElementById( 'hlf-lightbox' );
		if ( ! gallery || ! lightbox ) { return; }

		var photos;
		try {
			photos = JSON.parse( gallery.getAttribute( 'data-hlf-photos' ) || '[]' );
		} catch ( e ) {
			return;
		}
		if ( ! photos.length ) { return; }

		var imageEl = document.getElementById( 'hlf-lightbox-image' );
		var currentIndex = 0;

		function show( index ) {
			currentIndex = ( index % photos.length + photos.length ) % photos.length;
			imageEl.src = photos[ currentIndex ];
			lightbox.hidden = false;
		}
		function close() {
			lightbox.hidden = true;
			imageEl.src = '';
		}

		gallery.querySelectorAll( '[data-hlf-lightbox-open]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				show( Number( button.getAttribute( 'data-hlf-lightbox-index' ) ) );
			} );
		} );

		var closeButton = lightbox.querySelector( '[data-hlf-lightbox-close]' );
		var prevButton = lightbox.querySelector( '[data-hlf-lightbox-prev]' );
		var nextButton = lightbox.querySelector( '[data-hlf-lightbox-next]' );
		if ( closeButton ) { closeButton.addEventListener( 'click', close ); }
		if ( prevButton ) { prevButton.addEventListener( 'click', function () { show( currentIndex - 1 ); } ); }
		if ( nextButton ) { nextButton.addEventListener( 'click', function () { show( currentIndex + 1 ); } ); }

		lightbox.addEventListener( 'click', function ( event ) {
			if ( event.target === lightbox ) { close(); }
		} );
		document.addEventListener( 'keydown', function ( event ) {
			if ( lightbox.hidden ) { return; }
			if ( event.key === 'Escape' ) { close(); }
			if ( event.key === 'ArrowLeft' && prevButton ) { show( currentIndex - 1 ); }
			if ( event.key === 'ArrowRight' && nextButton ) { show( currentIndex + 1 ); }
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		renderNocChart();
		bindShareButtons();
		bindLightbox();
	} );
} )();
