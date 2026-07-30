/**
 * .olx-map-mini(data-lat/data-lng/data-name)에 카카오맵을 렌더링한다.
 *
 * 실패 처리 원칙: 어떤 이유로든(좌표 없음, SDK 로드 실패, 도메인 제한, 런타임 예외 등)
 * 지도 초기화가 끝까지 성공하지 못하면 .olx-map-fallback을 그대로 둔다.
 * fallback은 "성공이 확정된 시점"에만 숨긴다 - SDK 스크립트가 로드됐다는 사실만으로는 숨기지 않는다.
 * 사용자 화면에는 에러 메시지를 띄우지 않고, 개발 확인용 console.warn만 남긴다.
 */
(function () {
	'use strict';

	function safeWarn( message ) {
		if ( window.console && typeof console.warn === 'function' ) {
			console.warn( '[OfficeLeasing Map] ' + message );
		}
	}

	/** InfoWindow 내용을 textContent로만 채워 HTML 파싱 경로를 아예 차단(빌딩명에 특수문자가 섞여도 안전) */
	function buildInfoWindowContent( name ) {
		var wrap = document.createElement( 'div' );
		wrap.style.cssText = 'padding:6px 10px;font-size:12px;font-weight:700;white-space:nowrap;';
		wrap.textContent = name;
		return wrap;
	}

	function initMap( container ) {
		try {
			var lat = parseFloat( container.getAttribute( 'data-lat' ) );
			var lng = parseFloat( container.getAttribute( 'data-lng' ) );
			var name = container.getAttribute( 'data-name' ) || '';

			if ( ! isFinite( lat ) || ! isFinite( lng ) ) {
				return; // 좌표 없음/형식 오류 -> fallback 유지
			}

			var canvas = container.querySelector( '.olx-map-canvas' );
			var fallback = container.querySelector( '.olx-map-fallback' );
			if ( ! canvas ) {
				return; // 컨테이너 구조 이상 -> fallback 유지
			}

			var center = new kakao.maps.LatLng( lat, lng );
			var map = new kakao.maps.Map( canvas, { center: center, level: 4 } );
			var marker = new kakao.maps.Marker( { position: center, map: map } );

			if ( name ) {
				var infoWindow = new kakao.maps.InfoWindow( {
					content: buildInfoWindowContent( name ),
					removable: false
				} );
				kakao.maps.event.addListener( marker, 'click', function () {
					infoWindow.open( map, marker );
				} );
			}

			// 탭/아코디언 등 지연 레이아웃에서 컨테이너 크기가 늦게 확정되는 경우 대비 - 1회만 재계산
			window.setTimeout( function () {
				try {
					map.relayout();
					map.setCenter( center );
				} catch ( relayoutErr ) {
					safeWarn( 'relayout 실패: ' + relayoutErr.message );
				}
			}, 300 );

			// 여기까지 예외 없이 도달한 경우에만 "성공"으로 간주하고 fallback을 숨긴다
			if ( fallback ) {
				fallback.style.display = 'none';
			}
		} catch ( err ) {
			// 허용 도메인 오류 등 SDK 내부 런타임 예외 포함 - 화면엔 아무 것도 띄우지 않고 fallback 유지
			safeWarn( '지도 초기화 실패, fallback 유지: ' + err.message );
		}
	}

	function boot() {
		var containers = document.querySelectorAll( '.olx-map-mini[data-lat][data-lng]' );
		if ( ! containers.length ) {
			return;
		}
		if ( typeof kakao === 'undefined' || ! kakao.maps ) {
			safeWarn( 'Kakao SDK가 로드되지 않았습니다(네트워크 실패 또는 미설정).' );
			return;
		}
		try {
			// autoload=false로 로드했으므로 이 콜백 이후에만 kakao.maps.* API를 사용한다.
			// 여러 컨테이너가 있어도 SDK load()는 이 한 번만 호출된다(중복 로드 방지).
			kakao.maps.load( function () {
				containers.forEach( initMap );
			} );
		} catch ( err ) {
			safeWarn( 'SDK load() 실패: ' + err.message );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
})();
