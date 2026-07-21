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

	/* ---------------- 리스트 · 차트 · 지도 3자 연동 ---------------- */

	// 매물 식별 키는 item_number(현재 플러그인에서 Flyer 내부 매물을 가리키는 유일하고 불변인 식별자)
	// 로 통일한다. 리스트 행(서버가 data-hlf-listing-key로 렌더링), NOC 차트 막대, 지도 마커(둘 다 이
	// 파일이 그린다) — 이 중 하나에 마우스오버/포커스하면 같은 key를 가진 나머지 엘리먼트에도
	// .is-active를 함께 토글한다.
	var ListingSync = ( function () {
		var groups = {};
		function setActive( key ) {
			Object.keys( groups ).forEach( function ( k ) {
				var isActive = ( k === key );
				groups[ k ].forEach( function ( el ) { el.classList.toggle( 'is-active', isActive ); } );
			} );
		}
		function clearActive() {
			Object.keys( groups ).forEach( function ( k ) {
				groups[ k ].forEach( function ( el ) { el.classList.remove( 'is-active' ); } );
			} );
		}
		function register( key, el ) {
			if ( ! key || ! el ) { return; }
			( groups[ key ] = groups[ key ] || [] ).push( el );
			el.addEventListener( 'mouseenter', function () { setActive( key ); } );
			el.addEventListener( 'mouseleave', clearActive );
			el.addEventListener( 'focus', function () { setActive( key ); } );
			el.addEventListener( 'blur', clearActive );
		}
		return { register: register };
	} )();

	function registerListingRows() {
		document.querySelectorAll( '[data-hlf-listing-key]' ).forEach( function ( el ) {
			ListingSync.register( el.getAttribute( 'data-hlf-listing-key' ), el );
		} );
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
				'<a class="hlf-noc-chart-item" href="' + escapeAttr( it.url ) + '" data-hlf-listing-key="' + escapeAttr( it.key ) + '" title="' + escapeAttr( title ) + '" aria-label="' + escapeAttr( label + '번 매물 상세보기' ) + '">' +
					'<span class="hlf-noc-chart-value">' + noc.toFixed( 1 ) + '</span>' +
					'<span class="hlf-noc-chart-bar-wrap"><span class="hlf-noc-chart-bar" style="height:' + height + '%"></span></span>' +
					'<span class="hlf-noc-chart-label">' + escapeHtml( label ) + '</span>' +
				'</a>'
			);
		} ).join( '' );

		el.querySelectorAll( '[data-hlf-listing-key]' ).forEach( function ( barEl ) {
			ListingSync.register( barEl.getAttribute( 'data-hlf-listing-key' ), barEl );
		} );
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

	/* ---------------- 카카오 지도(비교 지도 + 상세 개별 지도) ---------------- */

	var kakaoMapLoader = null;

	// SDK는 한 번만 불러온다(같은 페이지에 지도가 여러 개일 일은 없지만, 방어적으로 캐시).
	function loadKakaoMapSdk( key ) {
		if ( window.kakao && window.kakao.maps ) { return Promise.resolve( window.kakao.maps ); }
		if ( kakaoMapLoader ) { return kakaoMapLoader; }
		if ( ! key ) { return Promise.reject( new Error( '카카오 JavaScript 키가 설정되지 않았습니다.' ) ); }
		kakaoMapLoader = new Promise( function ( resolve, reject ) {
			var script = document.createElement( 'script' );
			script.src = 'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' + encodeURIComponent( key ) + '&autoload=false';
			script.onload = function () {
				if ( window.kakao && window.kakao.maps && window.kakao.maps.load ) {
					window.kakao.maps.load( function () { resolve( window.kakao.maps ); } );
				} else {
					reject( new Error( '카카오 지도 SDK를 찾을 수 없습니다.' ) );
				}
			};
			script.onerror = function () { reject( new Error( '카카오 지도 SDK를 불러오지 못했습니다.' ) ); };
			document.head.appendChild( script );
		} );
		return kakaoMapLoader;
	}

	// 비교 지도(여러 매물)와 상세 개별 지도(매물 1개)는 같은 렌더링 로직을 그대로 쓴다 — 좌표가 1개면
	// bounds 계산 없이 그 지점으로 센터를 맞추고, 여러 개면 LatLngBounds로 전부 화면에 들어오게 맞춘다.
	function initMapContainer( container ) {
		var key = container.getAttribute( 'data-hlf-kakao-key' ) || '';
		var items;
		try {
			items = JSON.parse( container.getAttribute( 'data-hlf-map-items' ) || '[]' );
		} catch ( e ) {
			items = [];
		}
		if ( ! items.length ) { return; }

		loadKakaoMapSdk( key ).then( function ( maps ) {
			container.innerHTML = '';
			var first = items[ 0 ];
			var map = new maps.Map( container, { center: new maps.LatLng( first.lat, first.lng ), level: 5 } );

			if ( items.length === 1 ) {
				map.setLevel( 4 );
			} else {
				var bounds = new maps.LatLngBounds();
				items.forEach( function ( it ) { bounds.extend( new maps.LatLng( it.lat, it.lng ) ); } );
				map.setBounds( bounds );
			}

			items.forEach( function ( it ) {
				var label = String( it.order + 1 );
				if ( label.length < 2 ) { label = '0' + label; }

				var marker = document.createElement( 'div' );
				marker.className = 'hlf-map-marker';
				marker.textContent = label;
				marker.title = it.address || '';

				new maps.CustomOverlay( {
					map: map,
					position: new maps.LatLng( it.lat, it.lng ),
					content: marker,
					yAnchor: 1,
				} );

				if ( it.url ) {
					marker.style.cursor = 'pointer';
					marker.addEventListener( 'click', function () { window.location.href = it.url; } );
				}
				if ( it.key ) { ListingSync.register( it.key, marker ); }
			} );
		} ).catch( function ( error ) {
			container.innerHTML = '<p class="hlf-map-empty">카카오 지도를 불러오지 못했습니다. (' + escapeHtml( error.message ) + ')</p>';
		} );
	}

	function initMaps() {
		document.querySelectorAll( '[data-hlf-map-items]' ).forEach( initMapContainer );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		registerListingRows();
		renderNocChart();
		bindShareButtons();
		bindLightbox();
		initMaps();
	} );
} )();
