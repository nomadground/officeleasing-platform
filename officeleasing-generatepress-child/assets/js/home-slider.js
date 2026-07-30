/**
 * Home 권역 빌딩 슬라이더 — 좌우 버튼만 담당하는 Vanilla JS.
 * is_front_page()에서만 로드된다.
 *
 * 설계:
 * - 이동/스냅 자체는 CSS(overflow-x + scroll-snap)가 담당한다. JS가 없거나 실패해도
 *   손가락 스와이프/트랙패드/키보드로 트랙을 스크롤할 수 있다 -> 이 파일은 순수 개선(progressive enhancement).
 * - 카드 실제 너비 + gap을 DOM에서 측정해 "한 화면 분량"만큼 이동시킨다.
 *   고정 픽셀값을 쓰지 않으므로 CSS의 카드 개수/gap이 바뀌어도 JS를 고칠 필요가 없다.
 * - MutationObserver 같은 상시 감시는 쓰지 않는다. scroll/resize 이벤트만 듣는다.
 * - 외부 라이브러리, jQuery, 자동재생, 무한루프 없음.
 */
( function () {
	'use strict';

	/** 카드 1장 + gap 폭. 카드가 없으면 0. */
	function stepWidth( track ) {
		var slide = track.querySelector( '.olx-home-slide' );
		if ( ! slide ) {
			return 0;
		}
		var styles = window.getComputedStyle( track );
		var gap = parseFloat( styles.columnGap || styles.gap || '0' ) || 0;
		return slide.getBoundingClientRect().width + gap;
	}

	/**
	 * 한 번 클릭에 이동할 거리 = 현재 보이는 카드 수 * (카드폭 + gap).
	 * 카드 경계에 정확히 맞도록 항상 카드 단위의 정수배로 이동시킨다
	 * (데스크탑 4개 노출이면 1~4 -> 5~8, 모바일 2개면 2장씩).
	 */
	function scrollAmount( track ) {
		var step = stepWidth( track );
		if ( ! step ) {
			return track.clientWidth;
		}
		var visible = Math.max( 1, Math.round( track.clientWidth / step ) );
		return step * visible;
	}

	/** 현재 스크롤 위치에 따라 좌우 버튼 disabled 갱신. */
	function syncButtons( slider, track ) {
		var prev = slider.querySelector( '[data-olx-slide="prev"]' );
		var next = slider.querySelector( '[data-olx-slide="next"]' );
		if ( ! prev && ! next ) {
			return;
		}
		// 소수점 스크롤 위치 때문에 끝에 도달해도 1~2px가 남을 수 있어 여유값을 둔다.
		var maxScroll = track.scrollWidth - track.clientWidth;
		var atStart = track.scrollLeft <= 2;
		var atEnd = track.scrollLeft >= maxScroll - 2;

		if ( prev ) {
			prev.disabled = atStart;
		}
		if ( next ) {
			// 스크롤할 여백이 아예 없으면(카드가 화면에 다 들어옴) 양쪽 모두 비활성
			next.disabled = atEnd || maxScroll <= 2;
		}
	}

	function initSlider( slider ) {
		var track = slider.querySelector( '[data-olx-track]' );
		if ( ! track ) {
			return;
		}

		slider.querySelectorAll( '[data-olx-slide]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var dir = button.getAttribute( 'data-olx-slide' ) === 'prev' ? -1 : 1;
				track.scrollBy( { left: dir * scrollAmount( track ), behavior: 'smooth' } );
			} );
		} );

		// scroll은 연속 발화하므로 rAF로 한 프레임에 한 번만 갱신한다.
		var ticking = false;
		track.addEventListener( 'scroll', function () {
			if ( ticking ) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame( function () {
				syncButtons( slider, track );
				ticking = false;
			} );
		}, { passive: true } );

		syncButtons( slider, track );
		return function () {
			syncButtons( slider, track );
		};
	}

	function init() {
		var sliders = document.querySelectorAll( '[data-olx-slider]' );
		if ( ! sliders.length ) {
			return;
		}

		var resyncers = [];
		sliders.forEach( function ( slider ) {
			var resync = initSlider( slider );
			if ( resync ) {
				resyncers.push( resync );
			}
		} );

		// 리사이즈로 노출 카드 수가 바뀌면 버튼 상태도 다시 계산해야 한다(디바운스).
		var resizeTimer = null;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( function () {
				resyncers.forEach( function ( fn ) {
					fn();
				} );
			}, 150 );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
