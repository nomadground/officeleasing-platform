# NMD Product Principles v1.0



---



## Document Purpose



본 문서는 NMD(Nomad Ground)의 모든 Product 설계 및 개발 의사결정의 기준을 정의한다.



Blueprint는 NMD의 존재 이유(Why)를 정의하고,



Business Plan은 NMD의 사업 전략(How Business)을 정의한다.



본 Product Principles는 이를 실제 제품(Product)으로 구현하기 위한 설계 원칙(How Product)을 정의한다.



본 문서는 다음 영역의 최상위 기준으로 사용된다.



- Website

- UX/UI

- Information Architecture

- CMS

- Admin Dashboard

- AI Agent

- Database

- API

- WordPress

- Future Platform Development



모든 Product 문서는 본 원칙을 기반으로 작성되어야 한다.



---



# 1. Product Philosophy



NMD는 부동산 플랫폼이 아니다.



NMD는 숙박 예약 플랫폼이 아니다.



NMD는 단순 단기임대 플랫폼이 아니다.



NMD는



Corporate Housing &

Furnished Living Platform이며,



장기적으로는



Global Mobility Living Infrastructure를 구축하기 위한 Product이다.



제품은 "방"을 보여주는 것이 아니라,



기업과 전문인력의 주거 문제를 해결하는 경험을 제공해야 한다.



제품은 단순히 공간을 연결하는 것이 아니라,



기업과 전문인력의 이동(Mobility)을 지원하는 Living Experience를 제공해야 한다.



---



# 2. Product Identity



NMD Product는 다음 세 가지 역할을 수행한다.



## Discovery



적합한 주거를 쉽고 빠르게 찾는다.



---



## Operation



주거 운영 과정을 효율적으로 관리한다.



---



## Infrastructure

주거 Discovery와 Operation이 축적되며 장기적으로 Living Infrastructure로 확장된다.



---



모든 기능은 위 세 가지 목적 중 하나 이상에 기여해야 한다.



---



# 3. Product Mission



NMD Product의 목표는



서울 Business District를 중심으로



기업과 전문인력이



가장 쉽고 신뢰할 수 있는 방식으로



Corporate Housing을 찾고,



문의하고,



운영할 수 있는 플랫폼을 만드는 것이다.



---



# 4. Product Positioning



NMD Product는



Traditional Brokerage



↓



Housing Platform



↓



Corporate Housing



↓



Operation Platform



↓



Living Infrastructure



순으로 발전한다.



제품은 항상



**Corporate Housing Platform**



으로 인식되어야 한다.



---



# 5. Core Product Principles



## Principle 01



Corporate First



제품은 개인 고객보다 기업 고객의 사용 경험을 우선 고려한다.



기업 HR 담당자,



Relocation Manager,



Corporate Client가 가장 쉽게 사용할 수 있어야 한다.



---



## Principle 02



Curated Inventory



모든 매물을 보여주는 것이 목표가 아니다.



NMD 기준을 충족하는 Residence만 제공한다.



Quantity보다



Quality를 우선한다.



---



## Principle 03



Simple Experience



Housing은 복잡하지만



사용 경험은 단순해야 한다.



사용자는



생각하지 않아도



자연스럽게 다음 단계로 이동할 수 있어야 한다.



---



## Principle 04



Operation First



제품은 운영을 쉽게 만들어야 한다.



관리자가 반복하는 업무는



가능한 자동화한다.



제품은 운영자의 생산성을 높여야 한다.



---



## Principle 05



AI Native



AI는 부가 기능이 아니다.



AI는 Product Experience의 일부이다.



모든 AI 기능은



사용자의 의사결정과



운영 효율을 향상시키기 위해 존재한다.



---



## Principle 06



Scalable Architecture



MVP는 단순하게 만든다.



그러나



데이터 구조와 시스템 구조는



향후 수천 개 Residence를 운영할 수 있도록 설계한다.



---



## Principle 07



Trust Before Conversion



예약보다 신뢰가 먼저다.



계약보다 정보가 먼저다.



판매보다 경험이 먼저다.



모든 화면은



사용자의 신뢰를 높이는 방향으로 설계한다.



---



# 6. Product Scope

Corporate Housing Discovery와

Inquiry Workflow를 검증하는 것을 목표로 한다.



MVP 단계에서는



운영 효율과 고객 상담 프로세스 구축을 우선하며,



다음 기능에 집중한다.



- Residence Listing

- Residence Detail

- AI Housing Assistant

- Corporate Inquiry

- Tour Request

- CMS

- Admin Dashboard

- Insight

- Company Information



예약,



결제,



입주관리,



운영관리 등은



차기 단계에서 확장한다.



---



# 7. Product Design Principles



제품 디자인은 다음 원칙을 따른다.



## Professional



기업 고객에게 신뢰를 주는 디자인



---



## Clean



불필요한 요소를 최소화한다.



---



## Informative



필요한 정보는 충분히 제공한다.



---



## Fast



최소한의 클릭으로 목적을 달성한다.



---



## Consistent



모든 화면은 동일한 디자인 시스템을 따른다.



---



# 8. Data Principles



제품은 화면보다 데이터를 먼저 설계한다.



모든 기능은



Data Model을 기준으로 개발한다.



UI는 변경될 수 있지만



Data Architecture는 쉽게 변경되지 않는다.



WordPress MVP 역시



향후 자체 플랫폼 이전을 고려하여 설계한다.



모든 데이터는



단일 출처(Single Source of Truth)를 유지한다.



동일한 정보를



여러 곳에서 관리하지 않는다.



Residence,



Inquiry,



Customer,



Contract는



하나의 기준 데이터를 중심으로 연결되어야 한다.



---



# 9. AI Principles



AI는 상담을 대체하지 않는다.



AI는 상담을 준비한다.



AI는 다음 업무를 지원한다.



- 고객 요구사항 수집

- 지역 추천

- Residence 추천

- 계약 조건 확인

- 상담 내용 요약

- 관리자 전달



AI의 목적은



더 빠르고 정확한 상담이다.



---



# 10. Product Decision Rules



새로운 기능을 추가하기 전



항상 다음 질문을 확인한다.



### Does this improve customer experience?



사용자 경험을 향상시키는가?



---



### Does this improve operational efficiency?



운영 효율을 높이는가?



---



### Does this support long-term scalability?



장기 확장성을 지원하는가?



---



### Can this be managed without developers?



운영자가 쉽게 사용할 수 있는가?



---



### Is this aligned with Corporate Housing?



Corporate Housing 브랜드와 일치하는가?



### Does this strengthen the NMD Brand?



---



위 질문 중 대부분에 "Yes"라고 답할 수 없다면



해당 기능은 추가하지 않는다.



---



# 11. Document Usage Principles



모든 AI Tool은



본 문서를 Product 설계의 최상위 기준으로 사용한다.



Blueprint는



Why를 정의한다.



Business Plan은



Business를 정의한다.



Product Principles는



Product를 정의한다.



AI는



Blueprint,



Business Plan,



Product Principles



세 문서를 함께 참고하여



항상 일관된 결과를 생성해야 한다.



# 12. Brand Principles



제품은 브랜드의 연장선이다.



NMD Product는 단순한 웹사이트가 아니라

NMD 브랜드 경험을 전달하는 플랫폼이다.



모든 제품은 Blueprint와 Brand Strategy에서 정의한 브랜드 아이덴티티를 유지해야 한다.



## Brand Consistency



모든 화면과 기능은 NMD 브랜드 체계를 유지한다.



초기 Product는 다음 두 브랜드를 중심으로 설계한다.



- NMD Nest

- NMD Residence



향후 확장 브랜드(NMD Town, NMD Tower 등)는 동일한 구조와 원칙을 기반으로 확장한다.



제품은 항상



Corporate Housing &

Furnished Living



브랜드 포지셔닝을 유지해야 하며,



일반 부동산 플랫폼 또는 단기숙박 플랫폼처럼 인식되어서는 안 된다.



모든 Residence는



브랜드보다



운영 기준이 먼저 정의되어야 한다.



브랜드는



운영 철학을 사용자에게 전달하기 위한 체계이다.



제품은



브랜드를 위한 운영이 아니라,



운영을 브랜드화해야 한다.



---



# 13. Partnership Principles



초기 NMD는 직접 운영만을 전제로 하지 않는다.



제품은 다양한 공급 구조를 수용할 수 있도록 설계되어야 한다.



초기 공급 구조는 다음을 포함한다.



- Partner Inventory

- Brokerage Inventory

- NMD Managed Inventory

- NMD Owned Inventory



모든 Residence는 동일한 사용자 경험을 제공하되,



운영 방식에 따라 관리 구조만 달라질 수 있다.



제품은 특정 공급 방식에 종속되지 않아야 한다.



Partner Inventory와



NMD Managed Inventory는



사용자에게 동일한 품질 경험을 제공해야 한다.



운영 방식이 달라도



브랜드 경험은 동일해야 한다.



---



# 14. Platform Evolution Principles



NMD Product는 MVP를 목표로 개발하지만,



MVP를 목표로 설계하지 않는다.



초기 구현은 WordPress를 기반으로 하되,



제품 구조는 향후 자체 플랫폼으로의 확장을 전제로 한다.



모든 기능은 다음 원칙을 따른다.



Data First



↓



Architecture First



↓



Interface Second



↓



Implementation Last



즉,



UI보다 데이터 구조를 먼저 설계하고,



현재 구현 방식보다 장기 확장성을 우선 고려한다.



WordPress는 MVP를 위한 기술 선택일 뿐,



Product 자체를 제한하는 요소가 되어서는 안 된다.



모든 Product 설계는



서울 중심 Corporate Housing Platform에서



Global Living Infrastructure Platform으로 확장 가능한 구조를 유지해야 한다.



---



# 15. MVP Principles



MVP의 목적은 기능을 많이 만드는 것이 아니다.



MVP의 목적은



가장 작은 제품으로



가장 큰 가설을 검증하는 것이다.



새로운 기능을 추가하기 전에 항상 다음을 검토한다.



Can this be released later?



Can this be solved operationally?



Does this create unnecessary complexity?



초기 Product는



Simple



Reliable



Maintainable



를 최우선으로 한다.



기능보다



제품의 완성도를 우선한다.



---



# 16. User Experience Principles



사용자는 부동산을 찾는 것이 아니다.



사용자는



문제를 해결하기 위해



NMD를 방문한다.



모든 화면은



다음 질문에 답할 수 있어야 한다.



- 내가 무엇을 해야 하는가?

- 다음 단계는 무엇인가?

- 지금 이 정보가 충분한가?



제품은



복잡한 정보를



쉽게 이해하도록 만들어야 한다.



사용자가 생각하는 시간을 줄이는 것이



좋은 UX이다.



---



# 17. Content Principles



콘텐츠는 SEO를 위해 존재하지 않는다.



콘텐츠는



신뢰를 만들기 위해 존재한다.



모든 콘텐츠는



- 정확해야 한다.

- 일관되어야 한다.

- 브랜드와 일치해야 한다.

- AI가 이해하기 쉬워야 한다.



모든 페이지는



사람과 AI 모두에게



동일한 의미를 전달해야 한다.



---



# 18. Data Ownership Principles



NMD의 가장 중요한 자산은



웹사이트가 아니다.



데이터이다.



제품은



다음 데이터를



지속적으로 축적해야 한다.



- Residence

- Customer

- Inquiry

- Company

- Contract

- Operation

- AI Interaction

- Operation Log

- Partner

- AI Recommendation



모든 기능은



데이터 축적을 고려하여 설계한다.



수집하지 않는 데이터는



활용할 수 없다.



---



# 19. AI Collaboration Principles



모든 Product 문서는



사람뿐 아니라



AI도 이해할 수 있도록 작성한다.



문서는



명확하고



구조적이며



일관성을 유지해야 한다.



AI Tool은



Blueprint



↓



Business Plan



↓



Product(PRD)



↓



Development



순으로 문서를 참조한다.



새로운 문서는



기존 문서와 충돌해서는 안 된다.



중복 정의보다



기존 문서를 참조하는 것을 우선한다.



새로운 문서를 작성할 경우



기존 문서를 수정하지 않고



기존 정의를 확장하는 방식으로 작성한다.



---



# 20. Evolution Principles



NMD Product는



완성된 제품을 만드는 것이 목표가 아니다.



지속적으로 발전하는 제품을 만드는 것이 목표이다.



초기 제품은



현재의 문제를 해결해야 한다.



동시에



미래의 확장을 막아서는 안 된다.



모든 Product는



Versioning을 전제로 한다.



v0.1



↓



v0.2



↓



v1.0



↓



v2.0



↓



Platform



↓



Infrastructure



제품은



계속 진화하는 것을 전제로 설계한다.


---


# 21. Product Decision Hierarchy

NMD Product는

완성된 제품을 만드는 것이 목표가 아니다.

지속적으로 발전하는 제품을 만드는 것이 목표이다.

초기 제품은

현재의 문제를 해결해야 한다.

동시에

미래의 확장을 막아서는 안 된다.

모든 Product는

Versioning을 전제로 한다.

v0.1

↓

v0.2

↓

v1.0

↓

v2.0

↓

Platform

↓

Infrastructure

제품은

계속 진화하는 것을 전제로 설계한다.

---

# 21. Product Architecture Principles

모든 NMD Product는

Module 기반으로 설계한다.

각 Module은

독립적으로 개발,

수정,

확장 가능해야 한다.

제품은

Homepage가 아니라

Platform을 만드는 것을 목표로 한다.

새로운 기능은

기존 구조를 변경하기보다

새로운 Module을 추가하는 방식으로 확장한다.

Loose Coupling

High Cohesion

원칙을 유지한다.

---

# 22. Product Decision Hierarchy

제품 개발 과정에서 판단이 어려운 경우

항상 다음 우선순위를 따른다.

1. Blueprint

Vision & Mission

↓

2. Business Plan

Business Strategy

↓

3. Product Principles

Product Philosophy

↓

4. Product Architecture

System Structure

↓

5. UX/UI

User Experience

↓

6. Implementation

Development

상위 문서가 항상 하위 문서보다 우선한다.

구현의 편의성을 위해

Vision을 변경해서는 안 된다.

Business Strategy를 위해

Product Identity를 훼손해서는 안 된다.

모든 Product 의사결정은

Blueprint에서 시작하여

Development로 이어져야 한다.

---

# Product Principle Statement

NMD Product는

단순한 부동산 웹사이트가 아니라,

기업과 전문인력을 위한

Corporate Housing &
Furnished Living 경험을 설계하고 운영하는

확장 가능한

Living Infrastructure Platform이다.

모든 Product 의사결정은

사용자 경험,

운영 효율,

데이터 구조,

AI 활용,

장기 확장성을 기준으로 이루어진다.
