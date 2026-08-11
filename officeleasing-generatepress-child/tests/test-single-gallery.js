/**
 * single.js의 갤러리 썸네일 전환 로직을 최소 DOM fixture로 검증한다.
 * jsdom 등 외부 의존성 없이, single.js가 실제로 건드리는 요소만 손으로 흉내낸
 * fake document/element로 Node의 vm 모듈에서 실제 파일을 그대로 실행시킨다.
 *
 * 검증 대상 버그(수정 전): Hero가 <picture><source>...<img srcset>...</picture> 구조로 바뀐 뒤
 * 메인 img의 src만 바꾸면 (1) 모바일에서 <source>의 stale한 srcset이 우선돼 전환이 안 먹히고,
 * (2) 데스크톱에서 메인 img에 남은 srcset 후보가 새 src보다 우선될 수 있었다.
 * 이 테스트는 클릭 시 source.srcset과 main의 src/srcset/alt가 전부 갱신되는지 확인한다.
 *
 * 실행: node tests/test-single-gallery.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0;
let fail = 0;
function check(label, got, expected) {
    const ok = JSON.stringify(got) === JSON.stringify(expected);
    if (ok) {
        pass++;
        console.log(`[PASS] ${label}`);
    } else {
        fail++;
        console.log(`[FAIL] ${label}\n  got:      ${JSON.stringify(got)}\n  expected: ${JSON.stringify(expected)}`);
    }
}

/** single.js가 실제로 쓰는 최소 API만 지원하는 가짜 DOM 엘리먼트. */
function makeElement(initialAttrs, initialClasses) {
    const attrs = Object.assign({}, initialAttrs);
    const classes = new Set(initialClasses || []);
    const listeners = {};
    return {
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        setAttribute(name, value) {
            attrs[name] = value;
        },
        classList: {
            add: (c) => classes.add(c),
            remove: (c) => classes.delete(c),
            contains: (c) => classes.has(c),
        },
        addEventListener(type, fn) {
            (listeners[type] = listeners[type] || []).push(fn);
        },
        click() {
            (listeners.click || []).forEach((fn) => fn());
        },
        textContent: '',
        _attrs: attrs,
        _classes: classes,
    };
}

/**
 * 시나리오 하나(모바일 <source> 있음 / 없음)를 구성해 1~4번 썸네일 클릭을 검증한다.
 * @param {boolean} withSource true면 데스크톱+모바일 <picture><source> 구조, false면 <source> 없는 단순 <img>(첨부 ID 없는 fallback) 상황을 흉내낸다.
 */
function runScenario(withSource, label) {
    const mainImg = makeElement({
        src: 'https://example.test/ol-hero-desktop-1.jpg',
        srcset: 'https://example.test/ol-hero-desktop-1.jpg 1600w',
        alt: '파르나스타워 외관',
    });
    const sourceEl = withSource
        ? makeElement({ srcset: 'https://example.test/ol-hero-mobile-1.jpg', media: '(max-width: 900px)' })
        : null;

    const thumbData = [
        { full: 'https://example.test/ol-interior-large-1.jpg', alt: '외관' },
        { full: 'https://example.test/ol-interior-large-2.jpg', alt: '오피스' },
        { full: 'https://example.test/ol-interior-large-3.jpg', alt: '라운지' },
        { full: 'https://example.test/ol-interior-large-4.jpg', alt: '회의실' },
    ];
    const thumbs = thumbData.map((t, i) =>
        makeElement({ 'data-full': t.full, 'data-full-alt': t.alt }, i === 0 ? ['is-active'] : [])
    );

    const selectorMap = {
        '.olx-gallery-main img': mainImg,
        '.olx-gallery-main picture source': sourceEl,
    };

    const fakeDocument = {
        readyState: 'complete',
        querySelector(sel) {
            return Object.prototype.hasOwnProperty.call(selectorMap, sel) ? selectorMap[sel] : null;
        },
        querySelectorAll(sel) {
            if (sel === '.olx-gallery-thumbs button') {
                return thumbs;
            }
            return [];
        },
        // single.js는 로드 시 initListingToggle()도 함께 실행한다(매물 2~3건 빌딩 전용) - 이 페이지엔
        // 해당 요소가 없으므로 getElementById가 null을 반환해 조용히 early-return해야 한다(실제 DOM과
        // 동일한 동작). 이 메서드가 없으면 vm.runInContext가 TypeError로 전체 스크립트를 멈춘다.
        getElementById() {
            return null;
        },
        addEventListener() {
            /* DOMContentLoaded 안 씀(readyState=complete) */
        },
    };

    const src = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'single.js'), 'utf8');
    const context = { document: fakeDocument, console };
    vm.createContext(context);
    vm.runInContext(src, context);

    // 1~4번 썸네일을 순서대로 클릭하며 검증
    thumbs.forEach((btn, i) => {
        btn.click();
        const full = thumbData[i].full;
        check(`[${label}] 썸네일 ${i + 1} 클릭 - 메인 img src 갱신`, mainImg.getAttribute('src'), full);
        check(`[${label}] 썸네일 ${i + 1} 클릭 - 메인 img srcset 갱신(구 srcset 잔존 아님)`, mainImg.getAttribute('srcset'), full);
        check(`[${label}] 썸네일 ${i + 1} 클릭 - 메인 img alt 갱신`, mainImg.getAttribute('alt'), thumbData[i].alt);
        if (withSource) {
            check(`[${label}] 썸네일 ${i + 1} 클릭 - <source> srcset도 함께 갱신(모바일 전환 버그 수정)`, sourceEl.getAttribute('srcset'), full);
        }
        check(`[${label}] 썸네일 ${i + 1} 클릭 - active 클래스가 클릭한 버튼에만`, btn.classList.contains('is-active'), true);
        thumbs.forEach((other, j) => {
            if (j !== i) {
                check(`[${label}] 썸네일 ${i + 1} 클릭 - 나머지(${j + 1}) active 해제`, other.classList.contains('is-active'), false);
            }
        });
    });
}

runScenario(true, '데스크톱+모바일 picture/source 있음');
runScenario(false, 'source 없음(첨부 ID 없는 폴백)');

console.log(`\n${pass} passed, ${fail} failed`);
if (fail > 0) {
    process.exit(1);
}
