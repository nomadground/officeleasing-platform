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

	// 매물별 고정 accent color. 리스트 번호 배지(서버 렌더링, includes/class-hlf-display-helpers.php의
	// hlf_item_accent_color)와 여기(NOC 차트 막대·지도 마커, 클라이언트 렌더링)가 완전히 같은 배열/규칙을
	// 써야 색이 어긋나지 않는다 — 이 배열을 바꾸면 PHP 쪽 팔레트도 함께 바꿀 것.
	// PHP hlf_item_accent_color()(class-hlf-display-helpers.php)와 값이 완전히 같아야 한다 — 5번째
	// 색은 흰색 텍스트 대비가 WCAG AA(4.5:1) 미달(3.24:1)이라 #936b25(4.81:1)로 함께 교체했다.
	var ACCENT_PALETTE = [ '#355c73', '#a8582c', '#3d7a4f', '#7a4a9c', '#936b25', '#3d6e8a', '#8a3d4a', '#4a7a3d' ];
	function accentColor( order ) {
		return ACCENT_PALETTE[ order % ACCENT_PALETTE.length ];
	}

	/* ---------------- 리스트 · 차트 · 지도 3자 연동 ---------------- */

	// 매물 식별 키는 item_number(현재 플러그인에서 Flyer 내부 매물을 가리키는 유일하고 불변인 식별자)
	// 로 통일한다. 리스트 행(서버가 data-hlf-listing-key로 렌더링), NOC 차트 막대, 지도 마커(둘 다 이
	// 파일이 그린다) — 이 중 하나에 마우스오버/포커스하면 같은 key를 가진 나머지 엘리먼트에도
	// .is-active를 함께 토글한다.
	var ListingSync = ( function () {
		var groups = {};
		// 요청서: 리스트 행/NOC 차트 막대/지도 마커 중 어느 하나에 마우스를 올려도, 그 매물의 지도
		// 좌표가 등록돼 있으면(registerMapTarget) 지도를 그 위치로 이동시킨다(줌 레벨은 그대로 —
		// panTo만 호출).
		var mapTargets = {};
		function setActive( key ) {
			Object.keys( groups ).forEach( function ( k ) {
				var isActive = ( k === key );
				groups[ k ].forEach( function ( el ) { el.classList.toggle( 'is-active', isActive ); } );
			} );
			var target = mapTargets[ key ];
			if ( target && window.kakao && window.kakao.maps ) {
				target.map.panTo( new kakao.maps.LatLng( target.lat, target.lng ) );
			}
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
		function registerMapTarget( key, map, lat, lng ) {
			if ( ! key ) { return; }
			mapTargets[ key ] = { map: map, lat: lat, lng: lng };
		}
		return { register: register, registerMapTarget: registerMapTarget };
	} )();

	function registerListingRows() {
		document.querySelectorAll( '[data-hlf-listing-key]' ).forEach( function ( el ) {
			ListingSync.register( el.getAttribute( 'data-hlf-listing-key' ), el );
		} );
	}

	/* ---------------- NOC 비교 차트 ---------------- */

	// 0을 기준선으로 실제 값에 비례해 막대 높이를 계산한다(요청서: "실제 금액 차이에 비해 막대
	// 차이가 너무 크게 나온다"). 이전에는 매물들의 최댓값-최솟값 범위만 확대해서 보여줬는데(예:
	// NOC가 50/48/45로 10%밖에 안 차이나도 그 좁은 범위를 전체 막대 높이로 확대하면 82%/13%처럼
	// 실제보다 훨씬 크게 벌어져 보였다) — 0부터 시작하는 절대값 비례라야 막대 높이 차이가 실제
	// 값 차이(%)와 맞아떨어진다.
	function barHeightPercent( value, chartMax ) {
		var ratio = chartMax > 0 ? Math.min( 1, Math.max( 0, value / chartMax ) ) : 0;
		// 최댓값도 100%가 아니라 88%까지만 채운다 — 호버 시 scale(1.15)로 커져도 위 숫자 표시줄과
		// 겹치지 않을 여유 공간을 항상 남겨둔다.
		return Math.round( ( 0.08 + ratio * 0.80 ) * 100 );
	}

	// 막대 하단 라벨: 지번주소를 "동" 부분과 "번지" 부분 2줄로 나눈다(마지막 공백 기준) —
	// 예) "삼성동 159-8" -> 1줄 "삼성동", 2줄 "159-8".
	function splitAddressLabel( address ) {
		var text = String( address || '' );
		var idx = text.lastIndexOf( ' ' );
		if ( idx === -1 ) { return { line1: text, line2: '' }; }
		return { line1: text.slice( 0, idx ), line2: text.slice( idx + 1 ) };
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
		var dataMax = Math.max.apply( null, values );
		// 최댓값 막대도 100%가 아니라 여유가 남게, 살짝만(10%) 위로 띄운 값을 기준선으로 쓴다.
		var chartMax = Math.max( dataMax * 1.1, 1 );

		el.innerHTML = items.map( function ( it ) {
			var noc = Number( it.noc );
			var height = barHeightPercent( noc, chartMax );
			var addr = splitAddressLabel( it.address );
			var labelText = addr.line1 + ( addr.line2 ? ' ' + addr.line2 : '' );
			var title = it.address + ' NOC ' + noc.toFixed( 1 ) + '만원';
			// 다른 순번 배지(리스트/지도/상세)와 같은 형식 — 1자리면 앞에 0을 채운다.
			var index = String( it.order + 1 );
			if ( index.length < 2 ) { index = '0' + index; }
			return (
				'<a class="hlf-noc-chart-item" href="' + escapeAttr( it.url ) + '" data-hlf-listing-key="' + escapeAttr( it.key ) + '" title="' + escapeAttr( title ) + '" aria-label="' + escapeAttr( labelText + ' 매물 상세보기' ) + '" style="--hlf-item-accent:' + accentColor( it.order ) + '">' +
					'<span class="hlf-noc-chart-value">' + noc.toFixed( 1 ) + '</span>' +
					'<span class="hlf-noc-chart-bar-wrap"><span class="hlf-noc-chart-bar" style="height:' + height + '%"><span class="hlf-noc-chart-bar-index">' + index + '</span></span></span>' +
					'<span class="hlf-noc-chart-label">' + escapeHtml( addr.line1 ) + '<br>' + escapeHtml( addr.line2 ) + '</span>' +
				'</a>'
			);
		} ).join( '' );

		el.querySelectorAll( '[data-hlf-listing-key]' ).forEach( function ( barEl ) {
			ListingSync.register( barEl.getAttribute( 'data-hlf-listing-key' ), barEl );
			// 요청서: 모바일에서 막대를 탭하면(진짜 마우스 hover가 없어 mouseenter 없이 곧장 클릭
			// 내비게이션만 발생) 지도 이동 없이 바로 상세페이지로 넘어가 버린다 — 모바일에서는
			// 링크 이동 대신 hover와 같은 지도 이동만 하고 그 자리에 머문다.
			barEl.addEventListener( 'click', function ( event ) {
				if ( ! window.matchMedia( '(max-width: 700px)' ).matches ) { return; }
				event.preventDefault();
				barEl.dispatchEvent( new Event( 'mouseenter' ) );
			} );
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

	/* ---------------- 썸네일 호버 → 대표 사진 확대 표시 ---------------- */

	// 썸네일에 마우스오버하면 위 대표 사진(.hlf-gallery-main)이 그 사진으로 바뀐다(라이트박스에서
	// 쓰는 것과 같은 원본 크기 URL, data-hlf-photos). 클릭 시 라이트박스가 여전히 "지금 보이는
	// 사진" 기준으로 열리도록 대표 사진 버튼의 data-hlf-lightbox-index도 함께 맞춰준다. 마우스가
	// 썸네일 스트립 전체를 벗어나면(각 썸네일이 아니라 스트립 기준 — 썸네일 사이를 이동할 때 매번
	// 원본으로 깜빡였다가 바뀌는 걸 막기 위해) 원래 대표 사진으로 되돌린다.
	function bindGalleryHoverSwap() {
		var gallery = document.querySelector( '.hlf-gallery[data-hlf-photos]' );
		if ( ! gallery ) { return; }
		var mainImg = gallery.querySelector( '.hlf-gallery-main img' );
		var mainButton = gallery.querySelector( '.hlf-gallery-main .hlf-photo-open' );
		var thumbsWrap = gallery.querySelector( '.hlf-gallery-thumbs' );
		if ( ! mainImg || ! mainButton || ! thumbsWrap ) { return; }

		var photos;
		try {
			photos = JSON.parse( gallery.getAttribute( 'data-hlf-photos' ) || '[]' );
		} catch ( e ) {
			return;
		}
		// 요청서: 워터마크(블러)는 사진마다 켜고 끌 수 있다 — 대표 사진이 바뀔 때마다 그 사진의 설정을
		// 따라간다. data-hlf-photos와 순서가 같은 배열(HLF_Meta_Schema::PHOTO_BLUR, attachment 메타).
		var blurFlags;
		try {
			blurFlags = JSON.parse( gallery.getAttribute( 'data-hlf-photo-blur' ) || '[]' );
		} catch ( e ) {
			blurFlags = [];
		}
		var watermark = gallery.querySelector( '.hlf-gallery-main .hlf-gallery-watermark' );

		var originalSrc = mainImg.getAttribute( 'src' );
		var originalSrcset = mainImg.getAttribute( 'srcset' );
		var originalSizes = mainImg.getAttribute( 'sizes' );
		var originalIndex = mainButton.getAttribute( 'data-hlf-lightbox-index' );
		var thumbButtons = thumbsWrap.querySelectorAll( '.hlf-photo-open' );

		// 실사용 환경(실제 서버 네트워크 지연)에서는 썸네일에 마우스를 잠깐 스쳐 지나가듯 올리면,
		// 대표 사진 자리의 큰 이미지(썸네일 자체보다 해상도가 큰 'hlf-item-photo' 사이즈라 아직 브라우저
		// 캐시에 없음)가 다운로드되기 전에 마우스가 이미 떠나 mouseleave가 원래 사진으로 되돌려버려,
		// 마치 "호버 효과는 있는데 사진은 안 바뀐다"처럼 보일 수 있다 — 페이지를 열자마자 갤러리
		// 사진을 전부 미리 받아 브라우저 캐시에 데워 두면, 실제로 마우스를 올렸을 때 src만 바꿔도
		// 이미 캐시된 이미지라 즉시 나타난다.
		photos.forEach( function ( url ) {
			if ( ! url ) { return; }
			var preload = new Image();
			preload.src = url;
		} );

		function clearActiveThumb() {
			thumbButtons.forEach( function ( b ) { b.classList.remove( 'hlf-gallery-thumb-active' ); } );
		}

		// 대표 사진 <img>가 반응형 사이즈가 여러 개 등록된 첨부(예: 원본 해상도가 hlf-item-photo
		// 크롭 기준보다 작아 코어가 medium/medium_large 등 다른 사이즈들로 srcset을 채운 경우)면
		// 워드프레스가 srcset/sizes를 함께 렌더링해 둔다 — srcset이 남아 있으면 브라우저는 src를
		// 바꿔도 그 srcset 후보(이전 사진 것) 중에서 계속 골라 그리므로, 실제 화면은 안 바뀐 채로
		// src 속성값만 바뀐 것처럼 보인다(실사용 버그, 매물마다 사진 원본 해상도가 달라 srcset
		// 유무가 갈려 "어떤 매물은 되고 어떤 매물은 안 된다"로 나타났다). src를 바꿀 때마다
		// srcset/sizes를 지워 브라우저가 반드시 지금 지정한 src만 쓰게 한다.
		function swapTo( index, url, thumbButton ) {
			mainImg.removeAttribute( 'srcset' );
			mainImg.removeAttribute( 'sizes' );
			mainImg.setAttribute( 'src', url );
			mainButton.setAttribute( 'data-hlf-lightbox-index', String( index ) );
			if ( watermark ) { watermark.hidden = ! blurFlags[ index ]; }
			clearActiveThumb();
			if ( thumbButton ) { thumbButton.classList.add( 'hlf-gallery-thumb-active' ); }
		}

		thumbButtons.forEach( function ( thumbButton ) {
			var index = Number( thumbButton.getAttribute( 'data-hlf-lightbox-index' ) );
			var url = photos[ index ];
			if ( ! url ) { return; }
			thumbButton.addEventListener( 'mouseenter', function () { swapTo( index, url, thumbButton ); } );
			thumbButton.addEventListener( 'focus', function () { thumbButton.dispatchEvent( new Event( 'mouseenter' ) ); } );
			// 요청서: 모바일은 진짜 "계속 hover 상태 유지"가 없다 — 라이트박스를 여는 대신, 탭하면
			// 데스크톱 호버와 같은 대표 사진 전환만 하고 그대로 유지한다(마우스가 없으니 되돌아갈
			// mouseleave도 없음 — 다른 썸네일을 탭하기 전까지 계속 그 사진을 보여준다).
			thumbButton.addEventListener( 'click', function ( event ) {
				if ( ! window.matchMedia( '(max-width: 700px)' ).matches ) { return; }
				event.preventDefault();
				swapTo( index, url, thumbButton );
			} );
		} );

		thumbsWrap.addEventListener( 'mouseleave', function () {
			// 대표 사진으로 되돌아갈 때는 원래 갖고 있던 srcset/sizes도 그대로 복원한다(그 사진은
			// 반응형 후보를 계속 누릴 자격이 있다 — 지운 채로 두면 이 사진만 계속 원본 한 장짜리로
			// 고정된다).
			mainImg.setAttribute( 'src', originalSrc );
			if ( originalSrcset ) { mainImg.setAttribute( 'srcset', originalSrcset ); } else { mainImg.removeAttribute( 'srcset' ); }
			if ( originalSizes ) { mainImg.setAttribute( 'sizes', originalSizes ); } else { mainImg.removeAttribute( 'sizes' ); }
			mainButton.setAttribute( 'data-hlf-lightbox-index', originalIndex );
			if ( watermark ) { watermark.hidden = ! blurFlags[ Number( originalIndex ) ]; }
			clearActiveThumb();
		} );
	}

	/* ---------------- 인쇄 버튼 ---------------- */

	// 요청서 6: 목록 페이지는 인쇄 버튼을 누르면 바로 인쇄하지 않고 "인쇄할 페이지 선택" 패널
	// (#hlf-print-panel, public-flyer-list.php가 렌더링)이 먼저 뜬다 — 1페이지(목록)/2페이지(비교
	// 차트·지도)/매물별 상세 페이지 중 체크한 것만 실제로 인쇄된다. 그 패널이 없는 페이지(상세
	// 페이지는 원래부터 1페이지뿐이라 고를 게 없다)에서는 이전과 동일하게 바로 인쇄한다.
	// 인쇄 버튼을 눌러 실제로 window.print()를 부르기 직전 준비 단계 — (1) 체크한 매물별 인쇄 전용
	// 지도를 그때 가서 만들고, (2) 인쇄 전용 사진을 채워 넣고, (3) 이미 화면에 떠 있던 지도(비교
	// 지도·상세 페이지 자체 지도)는 인쇄 레이아웃 크기로 다시 맞춘다 — 이 세 가지가 전부 끝난(사진
	// 로드 완료 + 지도 타일 로드 완료) 뒤에야 인쇄를 시작한다. 인쇄 선택 패널이 있는 목록 페이지와
	// 패널이 없어 곧장 인쇄하는 상세 페이지 둘 다 이 함수 하나를 그대로 쓴다.
	function prepareAndPrint() {
		Promise.all( [
			initLazyPrintMaps( document ),
			loadPendingPrintPhotos( document ),
			relayoutMapsForPrint(),
		] ).then( function () {
			// 지도 준비를 위해 잠깐 visibility:hidden으로 켜둔 .hlf-print-item-detail은 여기서 떼지
			// 않는다 — window.print() 바로 직전에 큰 레이아웃 변화(여러 블록이 한꺼번에 다시
			// display:none으로 접힘)를 일으키면, 일부 모바일 브라우저의 인쇄 파이프라인이 그 순간의
			// 콘텐츠 형태로 페이지 방향을 잘못 판단해 세로로 인쇄되는 회귀가 있었다(요청서). 이
			// 클래스는 어차피 실제 인쇄에서는 @media print 규칙(display:block !important)이 항상
			// 이기므로 켜져 있어도 무해하다 — 인쇄가 끝난 뒤(afterprint)에만 정리한다.
			window.print();
		} );
	}

	function bindPrintButton() {
		var panel = document.getElementById( 'hlf-print-panel' );
		document.querySelectorAll( '[data-hlf-print]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				if ( ! panel ) { prepareAndPrint(); return; }
				panel.hidden = false;
			} );
		} );
		if ( ! panel ) { return; }

		var cancelButton = panel.querySelector( '[data-hlf-print-cancel]' );
		var confirmButton = panel.querySelector( '[data-hlf-print-confirm]' );
		if ( cancelButton ) { cancelButton.addEventListener( 'click', function () { panel.hidden = true; } ); }
		if ( confirmButton ) {
			confirmButton.addEventListener( 'click', function () {
				var selected = {};
				panel.querySelectorAll( '[data-hlf-print-toggle]' ).forEach( function ( checkbox ) {
					selected[ checkbox.getAttribute( 'data-hlf-print-toggle' ) ] = checkbox.checked;
				} );
				var sections = Array.prototype.slice.call( document.querySelectorAll( '[data-hlf-print-section]' ) );
				sections.forEach( function ( section ) {
					var key = section.getAttribute( 'data-hlf-print-section' );
					section.classList.toggle( 'hlf-print-section-excluded', false === selected[ key ] );
					section.classList.remove( 'hlf-print-section-last' );
				} );
				// break-after:page가 실제로 인쇄에 포함되는 마지막 섹션에도 걸려 있으면 그 뒤에
				// 빈 페이지가 한 장 더 붙는다(A4 인쇄로 실측 확인) — 지금 선택된 것 중 문서상 마지막
				// 섹션에서만 그 break를 꺼서 없앤다(print.css .hlf-print-section-last).
				var included = sections.filter( function ( section ) { return ! section.classList.contains( 'hlf-print-section-excluded' ); } );
				if ( included.length ) { included[ included.length - 1 ].classList.add( 'hlf-print-section-last' ); }
				// 매물 상세(item-*)는 그 안에 이미 문의처 푸터를 자체적으로 담고 있다 — 그중 하나라도
				// 인쇄에 포함되면(항상 문서 맨 뒤에 온다) 문서 끝의 전역 푸터(public-flyer-list.php,
				// .hlf-shell 바로 아래)가 그 바로 뒤에 이어 붙어 마지막 페이지에 푸터가 두 번
				// 찍힌다(실사용 버그). 전역 푸터는 매물 상세가 하나도 없을 때(목록/비교 차트만 인쇄)만
				// 필요하므로 그 경우에만 보이게 한다.
				var includesItemDetail = included.some( function ( section ) {
					return 0 === section.getAttribute( 'data-hlf-print-section' ).indexOf( 'item-' );
				} );
				document.body.classList.toggle( 'hlf-print-hide-global-footer', includesItemDetail );
				panel.hidden = true;
				prepareAndPrint();
			} );
		}
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
		// 요청서: 워터마크(블러)는 사진마다 켜고 끌 수 있다 — 라이트박스에서 이전/다음으로 넘길 때도
		// 그 사진의 설정을 따라간다(data-hlf-photos와 같은 순서).
		var blurFlags;
		try {
			blurFlags = JSON.parse( gallery.getAttribute( 'data-hlf-photo-blur' ) || '[]' );
		} catch ( e ) {
			blurFlags = [];
		}
		var lightboxWatermark = document.getElementById( 'hlf-lightbox-watermark' );

		var imageEl = document.getElementById( 'hlf-lightbox-image' );
		var currentIndex = 0;
		// 라이트박스를 연 트리거 버튼 — 닫을 때 포커스를 여기로 되돌린다(포커스가 사라진 배경 요소나
		// 문서 맨 위로 튀지 않게 함, 키보드/스크린리더 사용자 기준).
		var lastFocused = null;

		function focusableButtons() {
			return Array.prototype.slice.call( lightbox.querySelectorAll( 'button' ) );
		}

		function show( index ) {
			currentIndex = ( index % photos.length + photos.length ) % photos.length;
			imageEl.src = photos[ currentIndex ];
			if ( lightboxWatermark ) { lightboxWatermark.hidden = ! blurFlags[ currentIndex ]; }
			lightbox.hidden = false;
		}
		function close() {
			lightbox.hidden = true;
			imageEl.src = '';
			if ( lastFocused && document.contains( lastFocused ) ) { lastFocused.focus(); }
			lastFocused = null;
		}

		gallery.querySelectorAll( '[data-hlf-lightbox-open]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				// 요청서: 모바일에서는 확대해도 대표 사진 크기와 별 차이가 없어 라이트박스를 열
				// 필요가 없다 — 썸네일 탭은 bindGalleryHoverSwap의 클릭 핸들러가 대표 사진 전환만
				// 대신한다(이 핸들러는 그 경우 그냥 아무 것도 하지 않고 넘어간다).
				if ( window.matchMedia( '(max-width: 700px)' ).matches ) { return; }
				lastFocused = button;
				show( Number( button.getAttribute( 'data-hlf-lightbox-index' ) ) );
				// hidden 해제 직후에 포커스를 옮겨야 스크린리더가 "대화상자 열림"을 인식한다.
				var closeBtn = lightbox.querySelector( '[data-hlf-lightbox-close]' );
				if ( closeBtn ) { closeBtn.focus(); }
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
			// Tab이 라이트박스 밖(배경 페이지)으로 빠져나가지 않도록 버튼 목록 안에서만 순환시킨다
			// (라이트박스 안 포커스 가능한 요소는 버튼뿐 — 별도 라이브러리 없는 최소 focus trap).
			if ( event.key === 'Tab' ) {
				var buttons = focusableButtons();
				if ( ! buttons.length ) { return; }
				var first = buttons[ 0 ];
				var last = buttons[ buttons.length - 1 ];
				if ( event.shiftKey && document.activeElement === first ) {
					event.preventDefault();
					last.focus();
				} else if ( ! event.shiftKey && document.activeElement === last ) {
					event.preventDefault();
					first.focus();
				}
			}
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

	// 인쇄 화면은 폭/높이가 화면과 전혀 다르다(A4 landscape, 2단 grid 폭 등) — 카카오 지도는 생성
	// 시점의 컨테이너 크기로 내부 캔버스를 굳혀버리므로, 인쇄 시작 시점에 이미 만들어둔 지도마다
	// relayout()+중심 재설정을 다시 걸어줘야 인쇄 레이아웃 크기에 맞게 다시 그려진다.
	var initializedMaps = [];

	function fitMapToItems( map, items ) {
		if ( items.length === 1 ) {
			map.setCenter( new kakao.maps.LatLng( items[ 0 ].lat, items[ 0 ].lng ) );
			map.setLevel( 4 );
		} else {
			var bounds = new kakao.maps.LatLngBounds();
			items.forEach( function ( it ) { bounds.extend( new kakao.maps.LatLng( it.lat, it.lng ) ); } );
			map.setBounds( bounds );
		}
	}

	// relayout() 직후에는 카카오 지도가 새 캔버스 크기에 맞는 타일을 다시 비동기로 받아온다 — 그 타일이
	// 전부 도착해 실제로 그려졌다는 신호(tilesloaded)가 (다시) 한 번 더 뜰 때까지 기다린 뒤에야
	// window.print()를 불러야 인쇄 스냅샷에 빈 지도가 찍히지 않는다. 네트워크 문제 등으로 이 이벤트가
	// 영영 안 올 수도 있으니 인쇄가 무한정 멈추지 않도록 안전 타임아웃을 둔다.
	function waitForTilesLoaded( map ) {
		return new Promise( function ( resolve ) {
			var done = false;
			function finish() {
				if ( done ) { return; }
				done = true;
				resolve();
			}
			kakao.maps.event.addListener( map, 'tilesloaded', finish );
			setTimeout( finish, 2500 );
		} );
	}

	// 인쇄 시작 직전에 호출된다 — 이미 만들어둔 지도 전부를 인쇄 레이아웃 크기로 다시 맞추고, 그
	// 크기에 맞는 타일이 실제로 다 그려질 때까지 기다리는 Promise를 모아서 돌려준다.
	function relayoutMapsForPrint() {
		if ( ! ( window.kakao && window.kakao.maps ) ) { return Promise.resolve(); }
		var waits = initializedMaps.map( function ( entry ) {
			entry.map.relayout();
			fitMapToItems( entry.map, entry.items );
			return waitForTilesLoaded( entry.map );
		} );
		return Promise.all( waits );
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
		if ( ! items.length ) { return Promise.resolve(); }

		return loadKakaoMapSdk( key ).then( function ( maps ) {
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

			// 요청서: 리스트페이지의 "위치 확인" 비교 지도만(id로 구분 — 상세페이지 지도는 클래스는
			// 같아도 이 id를 갖지 않는다) 기본보다 더 줌아웃한다 — 모바일은 2단계, 데스크톱은 1단계
			// (요청서: "데스크탑화면에서도... 줌아웃 1단계"). 인쇄 시(relayoutMapsForPrint) 다시
			// fitMapToItems()로 맞춰지므로 이 조정은 화면 표시에만 남는다. getLevel()이 유효한 값을
			// 안 돌려주는 경우(방어적) setLevel(NaN) 같은 잘못된 호출로 지도 전체가 깨지지 않게 건너뛴다.
			if ( 'hlf-comparison-map' === container.id ) {
				var baseLevel = map.getLevel();
				if ( isFinite( baseLevel ) ) {
					map.setLevel( baseLevel + ( window.matchMedia( '(max-width: 700px)' ).matches ? 2 : 1 ) );
				}
			}

			var tilesPromise = waitForTilesLoaded( map );

			items.forEach( function ( it ) {
				var label = String( it.order + 1 );
				if ( label.length < 2 ) { label = '0' + label; }

				var marker = document.createElement( 'div' );
				marker.className = 'hlf-map-marker';
				marker.textContent = label;
				marker.title = it.address || '';
				marker.style.setProperty( '--hlf-item-accent', accentColor( it.order ) );

				new maps.CustomOverlay( {
					map: map,
					position: new maps.LatLng( it.lat, it.lng ),
					content: marker,
					yAnchor: 1,
				} );

				if ( it.url ) {
					marker.style.cursor = 'pointer';
					// 순수 <div>는 기본적으로 포커스를 받지 못하고 스크린리더에도 상호작용 요소로
					// 알려지지 않는다(마우스 클릭만 가능) — 카카오 CustomOverlay가 실제 <a>/<button>을
					// 못 받으므로(임의 DOM 노드만 허용) role/tabindex/키보드 핸들러를 직접 부여한다.
					marker.setAttribute( 'role', 'button' );
					marker.setAttribute( 'tabindex', '0' );
					marker.setAttribute( 'aria-label', ( it.address || '' ) + ' 매물 상세보기' );
					var goToItem = function () { window.location.href = it.url; };
					marker.addEventListener( 'click', goToItem );
					marker.addEventListener( 'keydown', function ( event ) {
						if ( event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar' ) {
							event.preventDefault();
							goToItem();
						}
					} );
				}
				if ( it.key ) {
					ListingSync.register( it.key, marker );
					// 요청서: 리스트 행/차트 막대에 마우스를 올려도(마커 자신이 아니어도) 이 매물
					// 위치로 지도가 이동한다 — 줌 레벨은 그대로 두고 중심만 옮긴다(panTo).
					ListingSync.registerMapTarget( it.key, map, it.lat, it.lng );
				}
			} );

			initializedMaps.push( { map: map, items: items, container: container } );
			return tilesPromise;
		} ).catch( function ( error ) {
			container.innerHTML = '<p class="hlf-map-empty">카카오 지도를 불러오지 못했습니다. (' + escapeHtml( error.message ) + ')</p>';
		} );
	}

	// data-hlf-lazy-map이 붙은 지도(리스트 인쇄물에 끼워 넣는 매물별 상세 지도)는 여기서 건너뛴다 —
	// 목록 페이지를 열 때마다 매물 수만큼 카카오 지도를 미리 만들면 낭비다. 인쇄 버튼을 눌러 실제로
	// 그 항목을 선택했을 때만(initLazyPrintMaps) 만든다.
	function initMaps() {
		document.querySelectorAll( '[data-hlf-map-items]:not([data-hlf-lazy-map])' ).forEach( initMapContainer );
	}

	// 요청서: 드래그/줌으로 지도를 옮겨본 뒤 다시 매물 위치로 되돌리는 버튼 — 지도를 처음 만들 때 쓴
	// 것과 같은 계산(fitMapToItems, 좌표 1개면 그 지점+레벨4, 여러 개면 전부 보이게 bounds)을 그대로
	// 재사용한다.
	function bindMapRecenterButtons() {
		document.querySelectorAll( '[data-hlf-map-recenter]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var section = button.closest( '.hlf-detail-map-panel, .hlf-comparison-map-panel' );
				var container = section && section.querySelector( '[data-hlf-map-items]' );
				if ( ! container ) { return; }
				var entry = initializedMaps.filter( function ( e ) { return e.container === container; } )[ 0 ];
				if ( entry ) { fitMapToItems( entry.map, entry.items ); }
			} );
		} );
	}

	// initLazyPrintMaps()가 지도를 만들기 전 잠깐 보이게 해둔 .hlf-print-item-detail들 — 인쇄가
	// 끝나면(afterprint) 다시 원래대로(화면 전용 display:none) 되돌린다.
	var primedPrintDetails = [];
	function unprimePrintDetails() {
		primedPrintDetails.forEach( function ( el ) { el.classList.remove( 'hlf-print-priming' ); } );
		primedPrintDetails = [];
	}

	// 인쇄 선택 패널에서 "인쇄" 확정 시(또는 패널이 없는 상세 페이지에서 인쇄 버튼 클릭 시) 호출된다 —
	// 지금 화면에 남아있는(=사용자가 체크한) 매물별 인쇄 전용 지도 중 아직 만들지 않은 것만 그때 가서
	// 만든다. data-hlf-map-initialized로 한 번 만든 뒤 다시 만들지 않는다.
	function initLazyPrintMaps( root ) {
		var pending = [];
		root.querySelectorAll( '[data-hlf-print-section]:not(.hlf-print-section-excluded) [data-hlf-map-items][data-hlf-lazy-map]:not([data-hlf-map-initialized])' ).forEach( function ( container ) {
			container.setAttribute( 'data-hlf-map-initialized', '1' );
			// 이 컨테이너는 화면에서 항상 display:none인 .hlf-print-item-detail 안에 있다(public.css) —
			// 지도를 만드는 시점(new kakao.maps.Map(container, ...))에 컨테이너 크기가 0이면 카카오
			// 지도가 빈 채로 굳어버린다(실사용 버그: 인쇄 시 지도가 안 나옴). 지도를 만들기 직전에
			// 실제로 보이게 해 정상적인 크기를 갖게 한다.
			var detail = container.closest( '.hlf-print-item-detail' );
			if ( detail && ! detail.classList.contains( 'hlf-print-priming' ) ) {
				detail.classList.add( 'hlf-print-priming' );
				primedPrintDetails.push( detail );
			}
			pending.push( initMapContainer( container ) );
		} );
		return Promise.all( pending );
	}

	// 매물 인쇄 상세(print-item-detail.php)의 대표사진은 항상 display:none 컨테이너 안에 있어(공개
	// public.css .hlf-print-item-detail) src를 미리 채워두면 브라우저가 언제 실제로 fetch를
	// 시작할지 보장할 수 없다(loading="lazy"가 "화면 근처"를 display:none에는 적용하지 않음) — 지도와
	// 같은 방식으로, 인쇄 확정 시점에만 src를 채우고 로드 완료(또는 실패)를 기다린 뒤에 인쇄를
	// 시작한다.
	function loadPendingPrintPhotos( root ) {
		var pending = [];
		root.querySelectorAll( '[data-hlf-print-section]:not(.hlf-print-section-excluded) [data-hlf-lazy-src]:not([data-hlf-photo-initialized])' ).forEach( function ( img ) {
			img.setAttribute( 'data-hlf-photo-initialized', '1' );
			var src = img.getAttribute( 'data-hlf-lazy-src' );
			if ( ! src ) { return; }
			pending.push( new Promise( function ( resolve ) {
				img.addEventListener( 'load', resolve, { once: true } );
				img.addEventListener( 'error', resolve, { once: true } );
				img.src = src;
			} ) );
		} );
		return Promise.all( pending );
	}

	// 인쇄 버튼(bindPrintButton/prepareAndPrint)이 아니라 브라우저 자체 단축키(Ctrl+P 등)로 인쇄에
	// 들어가는 경우를 위한 최소한의 보완 — window.print()를 우리가 가로챌 수 없으므로 로드 완료를
	// 보장하진 못하지만, beforeprint에서라도 relayout을 걸어두면 완전히 빈 지도보다는 낫다.
	function bindPrintMapRelayout() {
		window.addEventListener( 'beforeprint', function () { relayoutMapsForPrint(); } );
		// 인쇄(또는 인쇄 다이얼로그 취소)가 끝나면 initLazyPrintMaps()가 지도를 만들려고 잠깐 보이게
		// 해뒀던 .hlf-print-item-detail을 다시 화면 전용(display:none) 상태로 되돌린다.
		window.addEventListener( 'afterprint', function () { relayoutMapsForPrint(); unprimePrintDetails(); } );
		if ( window.matchMedia ) {
			var mql = window.matchMedia( 'print' );
			var handler = function ( e ) { if ( e.matches ) { relayoutMapsForPrint(); } };
			if ( mql.addEventListener ) { mql.addEventListener( 'change', handler ); }
			else if ( mql.addListener ) { mql.addListener( handler ); }
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		registerListingRows();
		renderNocChart();
		bindShareButtons();
		bindPrintButton();
		bindLightbox();
		bindGalleryHoverSwap();
		initMaps();
		bindMapRecenterButtons();
		bindPrintMapRelayout();
	} );
} )();
