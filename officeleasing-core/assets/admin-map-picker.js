/**
 * Building 편집화면 전용. 카카오맵을 임베드해 주소검색/지도클릭으로
 * building_lat(field_ol_bld_lat) / building_lng(field_ol_bld_lng) ACF 입력칸을 자동 채운다.
 * 이미 입력된 도로명주소(field_ol_bld_address_road)를 재사용해서 재입력 없이 바로 검색하고,
 * 실패 시에는(관리자 전용 도구이므로) 화면에 원인 힌트를 보여준다 - 프론트엔드 지도와 달리
 * 여기서는 조용히 숨기지 않고 자가진단이 가능하게 한다.
 */
(function () {
	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var canvas = document.getElementById( 'ol-map-picker-canvas' );
		var statusEl = document.getElementById( 'ol-map-picker-status' );
		var addressInput = document.getElementById( 'ol-map-picker-address' );
		var searchBtn = document.getElementById( 'ol-map-picker-search' );
		var latInput = document.getElementById( 'acf-field_ol_bld_lat' );
		var lngInput = document.getElementById( 'acf-field_ol_bld_lng' );
		var roadAddressInput = document.getElementById( 'acf-field_ol_bld_address_road' );
		var jibunAddressInput = document.getElementById( 'acf-field_ol_bld_address_jibun' );

		if ( ! canvas || ! latInput || ! lngInput ) {
			return;
		}

		function showStatus( message, isError ) {
			if ( ! statusEl ) {
				return;
			}
			if ( ! message ) {
				statusEl.style.display = 'none';
				return;
			}
			statusEl.style.display = 'block';
			statusEl.style.color = isError ? '#b32d2e' : '#666';
			statusEl.textContent = message;
		}

		// 이미 입력된 도로명주소를 검색창 기본값으로 채워서 재입력 없이 바로 검색 가능하게 한다
		if ( addressInput && roadAddressInput && roadAddressInput.value && ! addressInput.value ) {
			addressInput.value = roadAddressInput.value;
		}

		if ( typeof kakao === 'undefined' || ! kakao.maps ) {
			showStatus( '카카오맵 SDK를 불러오지 못했습니다. 카카오 디벨로퍼스 콘솔에서 이 사이트 도메인이 Web 플랫폼에 등록됐는지 확인해 주세요.', true );
			return;
		}

		kakao.maps.load( function () {
			if ( ! kakao.maps.services ) {
				showStatus( '주소 검색 기능을 불러오지 못했습니다(services 라이브러리). 페이지를 새로고침해 다시 시도해 주세요.', true );
				return;
			}

			try {
				var hasInitial = !! ( parseFloat( latInput.value ) && parseFloat( lngInput.value ) );
				var initialLat = hasInitial ? parseFloat( latInput.value ) : 37.5665;
				var initialLng = hasInitial ? parseFloat( lngInput.value ) : 126.978; // 기본값: 서울시청
				var center = new kakao.maps.LatLng( initialLat, initialLng );

				var map = new kakao.maps.Map( canvas, { center: center, level: 4 } );
				var marker = new kakao.maps.Marker( { position: center, map: map } );
				var geocoder = new kakao.maps.services.Geocoder();

				showStatus( '' );

				function setCoords( lat, lng ) {
					latInput.value = lat.toFixed( 7 );
					lngInput.value = lng.toFixed( 7 );
					latInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					lngInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );

					var pos = new kakao.maps.LatLng( lat, lng );
					marker.setPosition( pos );
					map.setCenter( pos );
				}

				/** 검색 결과의 도로명/지번 주소를 해당 ACF 필드에 덮어쓴다. 값이 없는 항목은 건드리지 않는다. */
				function fillAddressFields( geocodeResult ) {
					if ( roadAddressInput && geocodeResult.road_address && geocodeResult.road_address.address_name ) {
						roadAddressInput.value = geocodeResult.road_address.address_name;
						roadAddressInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
					if ( jibunAddressInput && geocodeResult.address && geocodeResult.address.address_name ) {
						jibunAddressInput.value = geocodeResult.address.address_name;
						jibunAddressInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );
					}
				}

				kakao.maps.event.addListener( map, 'click', function ( mouseEvent ) {
					// 지도 클릭은 좌표만 갱신한다(역geocoding 없음) - 주소는 검색 결과로만 채운다
					setCoords( mouseEvent.latLng.getLat(), mouseEvent.latLng.getLng() );
				} );

				function doSearch() {
					var query = addressInput.value.trim();
					if ( ! query ) {
						return;
					}
					showStatus( '"' + query + '" 검색 중...' );
					geocoder.addressSearch( query, function ( result, status ) {
						if ( status === kakao.maps.services.Status.OK && result.length ) {
							setCoords( parseFloat( result[ 0 ].y ), parseFloat( result[ 0 ].x ) );
							fillAddressFields( result[ 0 ] );
							showStatus( '' );
						} else {
							showStatus( '"' + query + '"의 좌표를 찾지 못했습니다. 표현을 바꿔 다시 검색하거나 지도를 직접 클릭해 주세요.', true );
						}
					} );
				}

				searchBtn.addEventListener( 'click', doSearch );
				addressInput.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						doSearch();
					}
				} );

				if ( hasInitial ) {
					marker.setPosition( center );
				} else if ( addressInput.value ) {
					// 도로명주소가 이미 있고 좌표만 비어있는 흔한 케이스 - 진입 즉시 자동 검색
					doSearch();
				}

				window.setTimeout( function () {
					map.relayout();
					map.setCenter( center );
				}, 200 );
			} catch ( err ) {
				showStatus( '지도 초기화 중 오류가 발생했습니다: ' + err.message, true );
			}
		} );
	} );
})();
