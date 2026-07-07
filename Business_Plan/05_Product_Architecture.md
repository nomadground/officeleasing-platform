# 05_Product_Architecture.md

# NMD Product Architecture

Version: 1.0
Category: Business Plan
Brand: Nomad Ground
Short Name / Logo: NMD
Tagline: Corporate Housing & Furnished Living

---

## 1. Document Purpose

본 문서는 NMD(Nomad Ground)의 상품 구조, 분류 체계, 데이터 구조, 홈페이지 구조, AI 매칭 구조, 운영 시스템의 기반을 정의한다.

NMD는 단순히 매물을 나열하는 플랫폼이 아니다.

NMD는 기존 도심 주거 자산을 NMD 기준으로 표준화하고, 기업과 글로벌 전문인력이 신뢰할 수 있는 Corporate Housing & Furnished Living 상품으로 전환하는 플랫폼이다.

본 문서는 다음 목적에 사용된다.

* Website Development
* CMS Design
* ACF Field Design
* AI Agent Development
* Database Structure
* Product Page Structure
* Operations
* CRM Design
* Admin Dashboard Design
* Future Scaling
* Business Plan Alignment
* AI Collaboration

본 문서는 GPT, Claude, Codex 등 AI 도구가 NMD의 상품 구조, 데이터 구조, AI 매칭 로직, 홈페이지 설계, 관리자 시스템 설계를 일관되게 이해하고 협업하기 위한 기준 문서로 사용된다.

---

## 2. Product Philosophy

## Simple Products. Flexible Services.

NMD는 불필요하게 많은 상품 카테고리를 만들지 않는다.

초기 Product Line은 단순해야 한다.

고객 목적과 서비스 조합은 유연해야 한다.

NMD의 상품 철학은 다음과 같다.

* Products define the space.
* Services define the purpose.
* Options define the experience.
* Data connects everything.
* AI recommends the right living solution.

전통적인 부동산 상품 구조는 주로 다음 요소로 설명된다.

Location
+
Size
+
Price

NMD의 상품 구조는 다음 요소까지 포함한다.

Product Type
+
Service Purpose
+
Customer Need
+
Operation Standard
+
Korea Housing Trust Layer
+
AI Matching Data

NMD는 단순히 위치와 가격으로 공간을 설명하지 않는다.

NMD는 고객의 목적, 계약 조건, 거주 경험, 운영 가능성, 신뢰 요소까지 포함해 상품을 설계한다.

---

## 3. Product Architecture Principle

NMD의 Product Architecture는 다음 원칙을 따른다.

---

### 3.1 Product Line은 단순하게 유지한다

초기 Product Line은 다음 두 가지다.

1. NMD Nest
2. NMD Residence

NMD Collection은 초기 전략에서 제외한다.

상품명이 많아지면 고객 이해, 홈페이지 구조, 관리자 입력, AI 매칭, 운영 기준이 모두 복잡해진다.

초기에는 Nest와 Residence 투트랙을 명확하게 만드는 것이 우선이다.

---

### 3.2 Service Segment는 고객 목적을 정의한다

Service Segment는 상품명이 아니다.

Service Segment는 고객이 왜 NMD를 이용하는지 정의한다.

예시는 다음과 같다.

* Corporate Stay
* Relocation Stay
* Young Professional Stay
* Executive Stay
* Medical Stay
* Academic Stay
* Startup Stay

하나의 Property는 여러 Service Segment에 대응할 수 있다.

예를 들어 하나의 NMD Residence는 Corporate Stay, Relocation Stay, Executive Stay에 모두 적합할 수 있다.

---

### 3.3 Option Layer는 고객 경험을 조정한다

Option Layer는 고객의 세부 요구에 따라 추가될 수 있는 기능 또는 서비스다.

예시는 다음과 같다.

* Housekeeping
* Parking
* Internet
* Utility Package
* Bedding
* Move-in Support
* Airport Pickup
* Furniture Upgrade
* Foreigner-friendly Guide

Option Layer는 Product Line과 구분한다.

Option은 상품의 본질을 바꾸는 것이 아니라 고객 경험을 보완한다.

---

### 3.4 Data Layer는 상품, 서비스, 고객, 운영을 연결한다

NMD의 Product Architecture는 데이터 구조와 연결되어야 한다.

모든 Property는 다음 정보를 가져야 한다.

* Product Line
* Service Segment Fit
* Location
* Pricing
* Furnished Status
* Availability
* Operation Status
* Corporate Suitability
* Resident Suitability
* Korea Housing Trust Data
* Financial Data
* AI Matching Tags

데이터 구조가 명확해야 AI Agent, 홈페이지 필터, CRM, Admin System, Partner Dashboard, NMD Living OS로 확장할 수 있다.

---

## 4. Product System Overview

NMD의 Product System은 다음 Layer로 구성된다.

---

### 4.1 Product Line

공간의 기본 상품 유형을 정의한다.

* NMD Nest
* NMD Residence

---

### 4.2 Service Segment

고객의 이용 목적을 정의한다.

* Corporate Stay
* Relocation Stay
* Young Professional Stay
* Executive Stay
* Medical Stay
* Academic Stay
* Startup Stay

---

### 4.3 Option Layer

고객 경험을 세부적으로 조정한다.

* Living Options
* Service Options
* Foreign Support Options
* Corporate Options

---

### 4.4 Data Layer

상품, 고객, 계약, 운영, AI 매칭을 연결한다.

* Property Data
* Customer Data
* Contract Data
* Operation Data
* Korea Housing Trust Data
* Financial Data
* AI Matching Data

---

### 4.5 Example Structure

예시는 다음과 같다.

NMD Nest
+
Young Professional Stay
+
Gangnam Business District
+
Internet / Move-in Support
+
Foreigner-friendly Notes

또 다른 예시는 다음과 같다.

NMD Residence
+
Corporate Stay
+
Relocation Stay
+
Family Suitability
+
Korea Housing Trust Data

NMD Product Architecture의 핵심은 Product, Service, Option, Data를 분리하되 서로 연결하는 것이다.

---

## 5. Core Product 01: NMD Nest

## Efficient Furnished Living

NMD Nest는 컴팩트형 Furnished Living 상품군이다.

NMD Nest는 새로운 도시에서 일하고 생활을 시작하는 개인 전문인력, 외국인 직원, 프로젝트 인력, 청년 전문인력, 장기 체류 고객을 위한 효율적인 주거 상품이다.

---

### 5.1 Definition

## Compact Furnished Living Product

NMD Nest는 작지만 완성된 생활 기반을 제공한다.

Nest는 다음을 의미한다.

A personal base for modern professionals.

NMD Nest는 단순한 원룸이나 오피스텔이 아니다.

NMD Nest는 업무지구 접근성, 빠른 입주, 가구·가전 완비, 운영 안정성, 합리적 비용을 결합한 Efficient Furnished Living이다.

---

### 5.2 Brand Meaning

Nest는 새로운 도시에서 시작하는 개인의 첫 번째 안정적인 기반을 의미한다.

Nest가 상징하는 가치는 다음과 같다.

* Safe
* Simple
* Compact
* Ready
* Efficient
* Personal
* Flexible

NMD Nest는 새로운 도시에서 생활을 시작하는 고객에게 작은 Ground가 된다.

---

### 5.3 Suitable Property Types

NMD Nest에 적합한 공간은 다음과 같다.

* Studio
* One-room
* 1 Bedroom
* Compact Officetel
* Small Urban Housing
* Compact Apartment
* Work-friendly Compact Unit

공간은 작을 수 있다.

하지만 생활 기능은 충분해야 한다.

---

### 5.4 Target Size

NMD Nest의 일반적 크기 기준은 다음과 같다.

* Compact Units
* Efficient Layouts
* Single Resident-oriented Spaces
* Work-friendly Layouts
* Easy-to-maintain Spaces

정확한 면적 기준은 지역, 상품성, 임대 조건, 운영 가능성에 따라 달라질 수 있다.

중요한 것은 크기 자체가 아니라, 1인 또는 소규모 장기 체류 고객에게 적합한 생활 완성도다.

---

### 5.5 Primary Users

NMD Nest의 주요 사용자는 다음과 같다.

* Individual Professionals
* Corporate Employees
* Foreign Workers
* Project Workers
* Young Professionals
* Researchers
* Startup Members
* Developers
* Consultants
* Business Travelers
* Long-stay Individual Residents
* Global Workers

NMD Nest는 B2C와 B2B2C 모두에 대응할 수 있다.

다만 NMD Nest는 초저가 숙박이나 단기 여행객을 위한 상품이 아니다.

NMD Nest는 일정 기간 서울에서 안정적으로 생활해야 하는 전문인력과 장기 체류 고객을 위한 상품이다.

---

### 5.6 Core Value

NMD Nest의 핵심 가치는 다음과 같다.

Move-in ready.
Efficient.
Connected to business districts.

고객에게 제공하는 가치는 다음과 같다.

* 빠른 입주
* 가구·가전 완비
* 안정적인 인터넷
* 업무지구 접근성
* 합리적 비용
* 명확한 정보
* 운영 지원
* 유지보수 대응
* 장기 체류 적합성

---

### 5.7 Service Segment Fit

NMD Nest와 잘 맞는 Service Segment는 다음과 같다.

* Young Professional Stay
* Corporate Stay
* Relocation Stay
* Startup Stay
* Academic Stay
* Medical Stay 일부
* Long-stay Individual Living

NMD Nest는 공급 확장성, 운영 효율, 초기 고객 데이터 축적에 중요한 상품이다.

---

## 6. Core Product 02: NMD Residence

## Premium Corporate Housing

NMD Residence는 프리미엄 Corporate Housing 상품군이다.

NMD Residence는 기업 고객, 외국인 주재원, 임원, 가족 동반 장기 체류 고객, 고소득 전문직을 위한 안정적이고 신뢰 가능한 주거 상품이다.

---

### 6.1 Definition

## Full-size Furnished Living Product

NMD Residence는 완성된 Home Experience를 제공한다.

Residence는 다음을 의미한다.

A complete home experience for companies and global talents.

NMD Residence는 단순히 큰 집이 아니다.

NMD Residence는 기업이 직원에게 제공해도 신뢰할 수 있는 Premium Corporate Housing이다.

---

### 6.2 Brand Meaning

Residence는 안정성, 품질, 신뢰, 장기 거주 가능성을 상징한다.

Residence가 상징하는 가치는 다음과 같다.

* Comfort
* Stability
* Trust
* Quality
* Professional
* Secure
* Family-ready

NMD Residence는 NMD의 기업 신뢰와 프리미엄 브랜드 가치를 만드는 핵심 상품이다.

---

### 6.3 Suitable Property Types

NMD Residence에 적합한 공간은 다음과 같다.

* Apartments
* Multi-room Homes
* Villas
* Family Residences
* Premium Officetels
* Premium Residential Units
* Expat-friendly Housing
* Executive Housing

NMD Residence는 면적뿐 아니라 위치, 품질, 안정성, 프라이버시, 유지관리 가능성, 기업 고객 적합성을 함께 고려한다.

---

### 6.4 Target Size

NMD Residence의 일반적 크기 기준은 다음과 같다.

* 1 Bedroom 이상의 고품질 주거
* 2~3 Bedroom 이상 가족형 주거
* 장기 체류에 적합한 생활 공간
* 수납, 주방, 세탁, 휴식, 업무 기능을 갖춘 공간
* 가족 또는 고소득 전문직이 생활 가능한 구조

정확한 면적 기준은 지역, 고객군, 상품성, 가격대에 따라 달라질 수 있다.

---

### 6.5 Primary Users

NMD Residence의 주요 사용자는 다음과 같다.

* Corporate Clients
* Expat Families
* Executives
* Long-term Employees
* Senior Professionals
* Global Professionals
* Relocation Customers
* Enterprise Employees
* Premium Residents
* Family Long-stay Residents

---

### 6.6 Core Value

NMD Residence의 핵심 가치는 다음과 같다.

Comfort.
Stability.
Professional Living.

고객에게 제공하는 가치는 다음과 같다.

* 프리미엄 주거 품질
* 장기 거주 안정성
* 기업 고객 신뢰
* 가족 거주 가능성
* 명확한 계약 및 정산
* 유지보수 대응
* 입주·퇴실 프로세스
* Korea Housing Trust Layer
* Resident Support

---

### 6.7 Service Segment Fit

NMD Residence와 잘 맞는 Service Segment는 다음과 같다.

* Corporate Stay
* Relocation Stay
* Executive Stay
* Family Stay
* Academic Stay 일부
* Medical Stay 일부
* Long-stay Premium Living

NMD Residence는 NMD의 Corporate Housing 전문성과 프리미엄 신뢰를 만드는 상품이다.

---

## 7. Collection Layer Policy

초기 NMD Product Architecture에서는 Collection Layer를 공식 상품 구조로 사용하지 않는다.

기존 초안의 Executive Collection은 초기 Product Line 또는 Collection으로 사용하지 않는다.

이유는 다음과 같다.

* 초기 상품 구조가 복잡해진다.
* 고객이 Nest / Residence 차이를 이해하기 어려워질 수 있다.
* 홈페이지와 AI 매칭 구조가 복잡해진다.
* 운영 기준과 품질 관리 기준이 늘어난다.
* 초기 공급이 충분하지 않은 상태에서 고급 Collection을 만들면 브랜드 약속을 지키기 어렵다.

따라서 초기 Product Architecture는 다음과 같이 유지한다.

Products:

* NMD Nest
* NMD Residence

Services:

* Corporate Stay
* Relocation Stay
* Young Professional Stay
* Executive Stay
* Medical Stay
* Academic Stay
* Startup Stay

Executive 수요는 초기에는 NMD Residence 안의 Premium Use Case로 다룬다.

향후 명확한 고객 수요, 운영 기준, 가격 구조, 브랜드 필요성이 검증되면 NMD Signature 또는 NMD Collection 등 별도 상품 확장을 검토할 수 있다.

---

## 8. Service Layer

Services define WHY customers use NMD.

Service Layer는 고객의 목적을 정의한다.

하나의 Property는 여러 Service Segment를 지원할 수 있다.

예를 들어 하나의 NMD Residence는 Corporate Stay, Relocation Stay, Executive Stay에 모두 적합할 수 있다.

예를 들어 하나의 NMD Nest는 Young Professional Stay, Startup Stay, Corporate Stay에 모두 적합할 수 있다.

---

## 9. Service Segment 01: Corporate Stay

## Primary Service Category

Corporate Stay는 NMD의 핵심 Service Segment이다.

---

### 9.1 Users

Corporate Stay의 사용자는 다음과 같다.

* Corporate Employees
* Project Teams
* Business Travelers
* Foreign Employees
* Domestic Transfer Employees
* HR-supported Residents
* Global Mobility Customers

---

### 9.2 Needs

Corporate Stay 고객의 Needs는 다음과 같다.

* Easy Contracts
* Corporate-friendly Housing
* Furnished Housing
* Employee Support
* Move-in Management
* Maintenance Support
* Clear Settlement
* Corporate Communication
* Korea Housing Trust Layer

---

### 9.3 Product Fit

Corporate Stay는 NMD Nest와 NMD Residence 모두에 연결될 수 있다.

* NMD Nest: 1인 직원, 프로젝트 인력, 비용 효율적 기업 주거
* NMD Residence: 주재원, 임원, 가족 동반, 장기 기업 주거

---

## 10. Service Segment 02: Relocation Stay

Relocation Stay는 국가 또는 도시 이동 고객을 위한 Service Segment이다.

---

### 10.1 Users

Relocation Stay의 사용자는 다음과 같다.

* Foreign Professionals
* Expatriates
* Foreign Employees
* Domestic Transfers
* New Hires
* Relocation Families
* Relocation Agency Customers

---

### 10.2 Needs

Relocation Stay 고객의 Needs는 다음과 같다.

* Move-in Ready Housing
* Furnished Setup
* Location Guidance
* Foreigner-friendly Information
* Living Preparation
* Residence Registration-related Notes
* Visa Proof-related Housing Notes
* Maintenance Support
* Family Suitability

---

### 10.3 Product Fit

Relocation Stay는 주로 NMD Residence와 연결된다.

다만 1인 외국인 직원, 연구원, 프로젝트 인력의 경우 NMD Nest도 적합할 수 있다.

---

## 11. Service Segment 03: Young Professional Stay

Young Professional Stay는 청년 전문인력과 글로벌 워커를 위한 Service Segment이다.

---

### 11.1 Users

Young Professional Stay의 사용자는 다음과 같다.

* Developers
* Consultants
* Finance Professionals
* Medical Professionals
* Researchers
* Startup Workers
* Designers
* Creators
* Project Workers
* Global Workers

---

### 11.2 Needs

Young Professional Stay 고객의 Needs는 다음과 같다.

* Business District Living
* Convenience
* Quality
* Fast Move-in
* Furnished Setup
* Stable Internet
* Work-friendly Environment
* Reasonable Cost
* Clear Information

---

### 11.3 Product Fit

Young Professional Stay는 주로 NMD Nest와 연결된다.

NMD Nest는 이들에게 새로운 도시에서의 첫 번째 안정적인 Ground가 된다.

---

## 12. Service Segment 04: Executive Stay

Executive Stay는 프리미엄 고객을 위한 Service Segment이다.

Executive Stay는 초기 Product Line이 아니다.

Executive Stay는 NMD Residence 안의 Premium Use Case로 다룬다.

---

### 12.1 Users

Executive Stay의 사용자는 다음과 같다.

* Executives
* VIP Residents
* Senior Professionals
* Foreign Company Executives
* Embassy-related Residents
* High-income Professionals
* Expat Families

---

### 12.2 Needs

Executive Stay 고객의 Needs는 다음과 같다.

* Premium Location
* High-quality Housing
* Privacy
* Security
* Professional Service
* Family Suitability
* Corporate Contract Suitability
* Reliable Maintenance
* Clear Settlement

---

### 12.3 Product Fit

Executive Stay는 주로 NMD Residence와 연결된다.

향후 수요와 운영 기준이 충분히 검증되면 별도 프리미엄 상품 확장을 검토할 수 있다.

---

## 13. Service Segment 05: Medical Stay

Medical Stay는 의료 목적의 장·중기 체류 고객을 위한 향후 확장 가능한 Use Case이다.

Medical Stay는 초기 회사 정체성이 아니다.

NMD의 핵심 정체성은 Corporate Housing & Furnished Living이다.

---

### 13.1 Users

Medical Stay의 사용자는 다음과 같다.

* International Patients
* Medical Tourists
* Recovery Guests
* Patient Companions
* Family Members

---

### 13.2 Needs

Medical Stay 고객의 Needs는 다음과 같다.

* Short / Mid-term Flexibility
* Comfortable Stay
* Hospital Accessibility
* Quiet Environment
* Privacy
* Furnished Housing
* Easy Move-in

---

### 13.3 Product Fit

Medical Stay는 상황에 따라 NMD Nest 또는 NMD Residence에 연결될 수 있다.

* NMD Nest: 1인 회복 체류, 단기·중기 체류
* NMD Residence: 가족 동반, 장기 회복, 프리미엄 체류

Medical Stay는 향후 병원, 의료기관, 에이전시와의 파트너십 및 수요 데이터가 검증될 때 확장한다.

---

## 14. Service Segment 06: Academic Stay

Academic Stay는 연구, 학술, 교육 목적의 장·중기 체류 고객을 위한 Use Case이다.

---

### 14.1 Users

Academic Stay의 사용자는 다음과 같다.

* Visiting Professors
* Researchers
* Exchange Scholars
* Graduate Researchers
* University-related Visitors
* Education Professionals

---

### 14.2 Needs

Academic Stay 고객의 Needs는 다음과 같다.

* University / Research District Access
* Quiet Living Environment
* Long-stay Suitability
* Furnished Housing
* Reasonable Cost
* Foreigner-friendly Guide
* Stable Internet

---

### 14.3 Product Fit

Academic Stay는 NMD Nest 또는 NMD Residence에 연결될 수 있다.

* NMD Nest: 1인 연구원, 방문 연구자, 합리적 비용
* NMD Residence: 가족 동반 교수, 장기 연구자, 프리미엄 학술 체류

---

## 15. Service Segment 07: Startup Stay

Startup Stay는 스타트업 구성원, 창업가, 프로젝트 팀을 위한 Use Case이다.

---

### 15.1 Users

Startup Stay의 사용자는 다음과 같다.

* Startup Founders
* Startup Employees
* Accelerator Participants
* Project Teams
* Engineers
* Remote-first Teams
* Global Startup Members

---

### 15.2 Needs

Startup Stay 고객의 Needs는 다음과 같다.

* Fast Move-in
* Flexible Stay
* Work-friendly Environment
* Business District Access
* Reasonable Cost
* Team Housing Possibility
* Stable Internet
* Simple Information

---

### 15.3 Product Fit

Startup Stay는 주로 NMD Nest와 연결된다.

팀 단위 또는 프리미엄 체류가 필요한 경우 NMD Residence도 검토할 수 있다.

---

## 16. Option Layer

Options customize customer experience.

Option Layer는 고객의 세부 요구를 반영하는 부가 기능 또는 서비스다.

Option은 Product Line이 아니다.

Option은 고객 경험을 조정하는 요소다.

---

### 16.1 Living Options

Living Options의 예시는 다음과 같다.

* Furniture Package
* Appliance Package
* Bedding
* Kitchen Essentials
* Utility Included
* Internet
* Parking
* Pet Friendly
* Work Desk
* Monitor
* Storage
* Family Setup

---

### 16.2 Service Options

Service Options의 예시는 다음과 같다.

* Housekeeping
* Airport Pickup
* Move-in Support
* Maintenance Support
* Laundry Service
* Bedding Replacement
* Moving Support
* Regular Inspection
* Emergency Support

---

### 16.3 Foreign Support Options

Foreign Support Options의 예시는 다음과 같다.

* Foreigner-friendly Guide
* Contract Guidance
* Living Information
* Utility Guide
* Local Area Guide
* Residence Registration-related Notes
* Visa Proof-related Housing Notes
* Move-in Registration-related Notes

단, NMD는 법률·세무·행정 대행사가 아니다.

외국인 거소등록, 비자, 전입신고 등 전문 자격이 필요한 영역은 전문가 협력 또는 전문기관 안내로 처리한다.

---

### 16.4 Corporate Options

Corporate Options의 예시는 다음과 같다.

* Corporate Report
* Monthly Billing Support
* Employee Housing Summary
* Multi-unit Proposal
* Company Account Management
* HR / GA Communication
* Move-in / Move-out Report
* Corporate Policy Notes

Corporate Options는 NMD가 기업 고객의 반복 수요를 관리하는 데 중요하다.

---

## 17. Property Data Structure

NMD의 Property Data Structure는 CMS, Database, Admin System, AI Matching, Website Filter의 기반이 된다.

각 Property는 구조화된 데이터로 관리되어야 한다.

---

## 18. Basic Information

Basic Information은 다음을 포함한다.

* Property ID
* Property Name
* Product Line
* NMD Nest / NMD Residence
* Service Segment Fit
* Location
* Address
* District
* Business District
* Building Name
* Status
* Visibility
* Partner ID
* Owner / Operator Type

Product Line에는 NMD Nest 또는 NMD Residence만 사용한다.

Collection Type은 초기 필드로 두지 않는다.

향후 별도 Collection이 필요해질 때 확장한다.

---

## 19. Space Information

Space Information은 다음을 포함한다.

* Room Type
* Area
* Exclusive Area
* Supply Area
* Bedrooms
* Bathrooms
* Floor
* Total Floors
* Building Type
* Elevator
* Parking
* View
* Light
* Noise Notes
* Work-friendly Notes
* Family Suitability
* Pet Policy

---

## 20. Pricing Information

Pricing Information은 다음을 포함한다.

* Monthly Price
* Deposit
* Management Fee
* Utility Policy
* Included Items
* Additional Fees
* Cleaning Fee
* Setup Fee
* Service Fee
* Payment Cycle
* Corporate Billing Notes

가격 정보는 고객 신뢰에 직접 영향을 주므로 명확하게 관리해야 한다.

---

## 21. Availability Information

Availability Information은 다음을 포함한다.

* Available Date
* Minimum Stay
* Maximum Stay
* Current Status
* Occupancy Status
* Reservation Status
* Move-in Ready Status
* Setup Status
* Cleaning Status
* Inspection Date

---

## 22. Contract Information

Contract Information은 AI Matching과 기업 상담에 매우 중요하다.

포함 항목은 다음과 같다.

* Corporate Contract Available
* Personal Lease Available
* Residential Lease Available
* Foreigner Available
* Minimum Contract Period
* Deposit Requirements
* Management Fee Policy
* Utility Settlement Policy
* Move-in / Move-out Settlement Policy
* Required Documents
* Contract Notes

---

## 23. Korea Housing Trust Data

Korea Housing Trust Data는 NMD의 핵심 차별화 데이터다.

포함 항목은 다음과 같다.

* Corporate Lease Suitability
* Personal Lease Suitability
* Move-in Registration Availability
* Residence Registration Notes
* Visa Proof Notes
* Fixed Date Notes
* Jeonse Rights Notes
* Deposit Safety Notes
* Deposit Insurance Notes
* Utility Settlement Policy
* Move-in / Move-out Settlement Notes
* Legal / Admin Expert Referral Notes

이 데이터는 고객 상담, 기업 제안, AI Matching, 운영 리스크 관리에 활용된다.

단, NMD는 법률·세무·행정 대행사가 아니다.

Korea Housing Trust 관련 내용은 확인된 데이터와 운영자 또는 전문가 검토를 기반으로 안내해야 한다.

---

## 24. Options Data

Options Data는 다음을 포함한다.

* Furniture
* Appliances
* Internet
* Parking
* Cleaning
* Bedding
* Kitchen Essentials
* Work Desk
* Pet Friendly
* Housekeeping
* Airport Pickup
* Move-in Support
* Maintenance Support
* Foreign Support Notes
* Corporate Support Notes

---

## 25. Media Data

NMD는 건물보다 생활 경험을 먼저 판매한다.

따라서 사진 우선순위는 생활 경험 중심이어야 한다.

Media Priority는 다음과 같다.

1. Living Area
2. Bedroom
3. Kitchen
4. Bathroom
5. Work Space
6. Storage
7. View
8. Building Exterior
9. Entrance
10. Neighborhood

사진은 실제 상태와 일치해야 한다.

과도한 보정은 브랜드 신뢰를 훼손할 수 있다.

---

## 26. Operation Data

Operation Data는 다음을 포함한다.

* Operation Status
* Cleaning Status
* Maintenance History
* Issue History
* Quality Score
* Resident Satisfaction
* Corporate Feedback
* Partner Quality Score
* Inspection Notes
* Move-in Checklist
* Move-out Checklist
* Renewal History

Operation Data는 NMD가 많은 공간을 일관된 품질로 운영하기 위한 핵심 데이터다.

---

## 27. Financial Data

Financial Data는 다음을 포함한다.

* Revenue
* Expense
* Gross Margin
* Net Margin
* Setup Cost
* Payback Period
* Maintenance Cost
* Cleaning Cost
* Partner Revenue Share
* Operation Cost
* ROI Notes

Financial Data는 Business Plan, IR 자료, Unit Economics, 가격 전략, 공급 판단에 활용된다.

---

## 28. AI Matching Architecture

AI Agent는 단순히 Property를 필터링해서는 안 된다.

AI는 고객의 의도, 상황, 목적, 계약 필요, 생활 요구를 이해해야 한다.

NMD AI Matching의 목표는 다음과 같다.

From Property Search
to
Living Solution Recommendation

---

### 28.1 Step 1: Identify Customer

AI Agent는 먼저 고객 유형을 파악한다.

Customer Type은 다음과 같다.

* Corporate
* Corporate Buyer
* Resident
* Individual
* Foreigner
* Young Professional
* Executive
* Supply Partner
* Housing Operator

Buyer와 Resident를 반드시 구분해야 한다.

기업 담당자가 문의하는 경우, 실제 거주자의 조건도 함께 확인해야 한다.

---

### 28.2 Step 2: Identify Purpose

AI Agent는 고객의 이용 목적을 파악한다.

Purpose는 다음과 같다.

* Corporate Stay
* Relocation Stay
* Young Professional Stay
* Executive Stay
* Medical Stay
* Academic Stay
* Startup Stay
* Long Stay

Purpose는 Product Line과 다르다.

Purpose는 고객이 왜 머무는지를 설명한다.

---

### 28.3 Step 3: Identify Requirements

AI Agent는 주거 요구 조건을 파악한다.

Inputs는 다음과 같다.

* Location
* Business District
* Budget
* Move-in Date
* Duration
* Room Type
* Family Size
* Number of Residents
* Furnished Requirement
* Work-friendly Requirement
* Parking
* Pet Policy
* Preferred Language

---

### 28.4 Step 4: Identify Contract & Trust Needs

AI Agent는 계약 및 신뢰 관련 요구를 파악한다.

Important Inputs는 다음과 같다.

* Lease Agreement
* Corporate Contract
* Personal Lease
* Deposit Structure
* Registration Need
* Foreigner Requirements
* Residence Registration Need
* Visa Proof-related Housing Need
* Utility Settlement
* Management Fee Policy
* Fixed Date / Jeonse Rights / Insurance Inquiry

AI Agent는 계약, 법률, 세무, 행정 판단을 단정하지 않는다.

Korea Housing Trust 관련 내용은 확인된 데이터와 운영자 또는 전문가 검토를 기반으로 안내한다.

---

### 28.5 Step 5: Recommend Solution

AI Agent는 최종적으로 다음 구조로 추천해야 한다.

Product
+
Service Segment
+
Property
+
Options
+
Trust Notes

예시는 다음과 같다.

NMD Nest
+
Young Professional Stay
+
Gangnam Business District
+
Internet / Move-in Support
+
Foreigner-friendly Notes

또 다른 예시는 다음과 같다.

NMD Residence
+
Corporate Stay
+
Relocation Stay
+
Family Suitability
+
Corporate Contract Suitability Notes

AI 추천은 운영자가 검토하고 확정한다.

AI는 추천 보조 도구이며 최종 책임은 NMD 운영자에게 있다.

---

## 29. Website Architecture

NMD Website는 단순 회사 소개 페이지가 아니라 첫 번째 Product Platform이다.

홈페이지 구조는 Product Architecture와 연결되어야 한다.

---

### 29.1 Recommended Main Navigation

초기 Main Navigation은 다음을 고려한다.

* Homes
* Corporate Housing
* NMD Nest
* NMD Residence
* Services
* Insights
* About NMD
* Contact

또는 단순화된 구조는 다음과 같다.

* Homes
* Corporate
* Services
* Insights
* About
* Contact

초기에는 고객 이해를 위해 너무 많은 메뉴를 만들지 않는다.

---

### 29.2 Core Pages

핵심 페이지는 다음과 같다.

* Home
* NMD Nest
* NMD Residence
* Corporate Housing
* Relocation Stay
* Young Professional Stay
* Executive Stay
* Property Listing
* Property Detail
* Partner Page
* Insight / Guide
* Contact / Request

Medical Stay, Academic Stay, Startup Stay는 초기에는 독립 메뉴보다 콘텐츠 또는 하위 Use Case로 다루는 것이 적합하다.

---

### 29.3 Property Filters

Property Filters는 고객 탐색과 AI Matching 모두에 활용된다.

Primary Filters는 다음과 같다.

* Location
* Business District
* Product Line
* Move-in Date
* Budget
* Room Type
* Stay Period

Secondary Filters는 다음과 같다.

* Service Segment
* Furnished Status
* Options
* Corporate Contract Suitability
* Foreigner Friendly Status
* Parking
* Family Suitability
* Pet Policy
* Korea Housing Trust Availability

초기에는 모든 필터를 한 번에 구현하지 않아도 된다.

하지만 데이터 구조는 향후 필터 확장을 고려해 설계해야 한다.

---

### 29.4 Property Detail Page Structure

Property Detail Page는 다음 구조를 고려한다.

* Hero Images
* Product Line Badge
* Service Segment Fit
* Location Summary
* Price Summary
* Availability
* Furnished Items
* Space Details
* Resident Fit
* Corporate Suitability
* Korea Housing Trust Notes
* Options
* Map
* Similar Properties
* Inquiry CTA
* Corporate Request CTA
* Move-in Guide Summary

Property Detail Page는 단순 매물 설명이 아니라 고객이 생활을 상상하고 신뢰할 수 있도록 설계해야 한다.

---

## 30. Admin Dashboard Requirements

Admin Dashboard는 내부 운영팀의 Command Center이다.

초기에는 WordPress + ACF 기반으로 구현할 수 있다.

장기적으로는 독립 Admin System, CRM, Partner Dashboard, NMD Living OS로 확장할 수 있다.

---

### 30.1 Inventory Management

Inventory Management는 다음을 관리한다.

* Property Data
* Product Line
* Service Segment Fit
* Availability
* Pricing
* Photos
* Furnished Items
* Contract Information
* Korea Housing Trust Data
* Operation Status

---

### 30.2 Customer Management

Customer Management는 다음을 관리한다.

* Leads
* Customer Type
* Buyer / Resident 구분
* Requirements
* Inquiry Source
* Proposal Status
* Contract Status
* Communication History
* Resident Notes
* Corporate Account Notes

---

### 30.3 Operation Management

Operation Management는 다음을 관리한다.

* Check-in
* Check-out
* Cleaning
* Maintenance
* Issues
* Inspection
* Move-in Checklist
* Move-out Checklist
* Partner Assignment
* Quality Score
* Resident Feedback

---

### 30.4 Partner Management

Partner Management는 다음을 관리한다.

* Owners
* Operators
* Service Partners
* Contracts
* Partner Quality Score
* Response Time
* Issue History
* Partnership Status
* Expansion Potential

---

### 30.5 Analytics

Analytics는 다음을 관리한다.

* Occupancy
* Revenue
* Margin
* Inquiry Conversion
* Product Performance
* Service Segment Performance
* Corporate Account Performance
* Partner Performance
* Maintenance Metrics
* Korea Housing Trust Data Completeness
* AI Matching Performance

---

## 31. Product Expansion Rule

NMD는 불필요한 상품명을 만들지 않는다.

새로운 Product Line은 다음 조건을 충족할 때만 만든다.

---

### 31.1 New Product Creation Criteria

새로운 Product Line을 만들기 위한 조건은 다음과 같다.

1. 고객군이 명확히 다르다.
2. 운영 기준이 다르다.
3. 가격 구조가 다르다.
4. 공급 기준이 다르다.
5. 브랜드 메시지가 다르다.
6. 데이터 구조가 다르다.
7. 충분한 수요가 검증되었다.
8. 기존 Nest / Residence로 설명하기 어렵다.
9. 장기적으로 유지 가능한 이름이다.
10. NMD 브랜드 신뢰를 강화한다.

---

### 31.2 Current Product Strategy

현재 Product Strategy는 다음과 같다.

Products:

* NMD Nest
* NMD Residence

Collection:

* 초기에는 사용하지 않음
* NMD Collection은 제외
* Executive Collection은 초기 제외

Services:

* Corporate Stay
* Relocation Stay
* Young Professional Stay
* Executive Stay
* Medical Stay
* Academic Stay
* Startup Stay

Options:

* Living Options
* Service Options
* Foreign Support Options
* Corporate Options

---

## 32. Product Metrics

NMD Product Architecture는 다음 지표로 관리한다.

---

### 32.1 Product Metrics

* NMD Nest Units
* NMD Residence Units
* Product Approval Rate
* Product Rejection Rate
* Product Quality Score
* Furnished Readiness Score
* Product Data Completeness
* Photo Completeness

---

### 32.2 Service Metrics

* Corporate Stay Inquiries
* Relocation Stay Inquiries
* Young Professional Stay Inquiries
* Executive Stay Inquiries
* Medical / Academic / Startup Use Case Inquiries
* Service Segment Conversion Rate
* Service Segment Satisfaction

---

### 32.3 Matching Metrics

* AI Matching Accuracy
* Inquiry-to-Match Rate
* Match-to-Contract Rate
* Similar Property Recommendation Usage
* Human Review Rate
* Matching Error Rate

---

### 32.4 Operation Metrics

* Move-in Readiness Rate
* Maintenance Resolution Time
* Cleaning Turnaround Time
* Complaint Rate
* Resident Satisfaction
* Corporate Feedback
* Partner Quality Score

---

### 32.5 Data Metrics

* ACF Field Completion Rate
* Korea Housing Trust Data Completeness
* Contract Data Completeness
* Availability Accuracy
* Pricing Accuracy
* Admin Data Accuracy

---

## 33. Product Risks

NMD는 Product Architecture 운영에서 다음 리스크를 관리해야 한다.

---

### 33.1 Product Complexity Risk

상품 구조가 복잡해지면 고객이 이해하기 어렵고 운영도 어려워진다.

관리 기준은 다음과 같다.

* Nest / Residence 투트랙 유지
* Collection 초기 제외
* Product Line과 Service Segment 구분
* Option을 상품명처럼 사용하지 않음
* 홈페이지 구조 단순화

---

### 33.2 Quality Inconsistency Risk

같은 Product Line 안에서 품질 차이가 크면 브랜드 신뢰가 떨어진다.

관리 기준은 다음과 같다.

* Product Approval Standard
* Furnished Standard
* Photo Standard
* Move-in Inspection
* Quality Score
* Partner Evaluation

---

### 33.3 Data Inconsistency Risk

데이터가 일관되지 않으면 AI Matching, 홈페이지 필터, 운영 관리가 어려워진다.

관리 기준은 다음과 같다.

* 표준 필드명 사용
* 필수 입력값 지정
* 내부 메모와 외부 노출 정보 구분
* Korea Housing Trust Data 별도 관리
* 정기 데이터 점검

---

### 33.4 Trust Risk

계약, 전입신고, 거소등록, 비자, 보증금 관련 정보를 부정확하게 관리하면 고객 신뢰가 훼손된다.

관리 기준은 다음과 같다.

* Korea Housing Trust Layer 운영
* 확인된 정보만 안내
* 전문가 협력 또는 전문기관 안내
* AI 답변 가드레일 운영
* 법률·세무·행정 대행처럼 표현하지 않음

---

## 34. AI Collaboration Guideline

본 문서는 AI 협업을 위한 Product Architecture 기준 문서로 사용된다.

AI 도구는 NMD 관련 Business Plan, IR 자료, Pitch Deck, 홈페이지 구조, CPT/ACF 설계, AI Agent 설계, 데이터베이스 설계, 상품 설명, 운영 매뉴얼, 고객 상담 흐름을 작성할 때 다음 기준을 따른다.

* NMD의 공식 브랜드명은 Nomad Ground이다.
* NMD는 로고 및 Short Name으로 사용한다.
* 공식 슬로건은 Corporate Housing & Furnished Living이다.
* NMD는 Corporate Housing & Furnished Living Platform이다.
* NMD는 단순 임대 플랫폼, 숙박업체, Airbnb 호스트, 일반 임대관리 회사, 단순 부동산 중개 플랫폼으로 설명하지 않는다.
* Product Line은 공간 유형을 정의한다.
* Service Segment는 고객 목적을 정의한다.
* Option Layer는 고객 경험을 조정한다.
* 초기 Product Line은 NMD Nest와 NMD Residence 투트랙이다.
* NMD Collection은 초기 전략에서 제외한다.
* Executive Collection은 초기 Product Line 또는 Collection으로 사용하지 않는다.
* Executive Stay는 NMD Residence 안의 Premium Use Case로 다룬다.
* NMD Nest는 Efficient Furnished Living으로 설명한다.
* NMD Residence는 Premium Corporate Housing으로 설명한다.
* Corporate Stay는 핵심 Service Segment이다.
* Relocation Stay, Young Professional Stay, Executive Stay는 주요 Service Segment이다.
* Medical Stay, Academic Stay, Startup Stay는 초기 Product Line이 아니라 향후 확장 가능한 Service Segment 또는 Use Case로 다룬다.
* 수요 전략은 Corporate First Demand이다.
* 서비스 경험은 Resident-Centered Experience를 기준으로 설계한다.
* B2C는 무시하지 않되, 단기 여행객이나 초저가 숙박 고객이 아니라 청년 전문인력, 외국인 직원, 장기 체류 고객 중심으로 이해한다.
* 공급 전략은 Partner First Supply이다.
* 한국 주거 행정, 법인 임대차, 전입신고, 거소등록, 비자 관련 주거 증빙, 전세권, 보증보험, 보증금 안정성은 Korea Housing Trust Layer로 다룬다.
* 법률·세무·행정 대행 자격이 필요한 영역은 전문가 협력 또는 전문기관 안내로 처리한다.
* AI Agent는 Buyer와 Resident를 구분해야 한다.
* AI Agent는 Product + Service Segment + Property + Options + Trust Notes 구조로 추천해야 한다.
* AI Agent는 계약, 법률, 세무, 행정 판단을 단정하지 않는다.
* Property Data는 향후 Website, CMS, Admin System, CRM, Partner Dashboard, AI Matching, NMD Living OS로 확장 가능해야 한다.
* 초기 기술 스택은 WordPress + GeneratePress + ACF 기반을 고려한다.
* Phase 명칭은 Blueprint 00_Index.md 기준을 따른다.
* 장기 기술 목표는 NMD Living OS이다.
* 문서는 한글 중심으로 작성하되 핵심 전략 용어는 영어로 고정한다.

---

## 35. One Sentence Summary

NMD products define the space, services define the purpose, options customize the experience, and technology connects customers to the right living solution.

---

## 36. Core Statement

NMD의 Product Architecture는 단순해야 한다.

초기 상품은 NMD Nest와 NMD Residence 두 가지로 충분하다.

NMD Nest는 Efficient Furnished Living으로 공급 확장성과 운영 효율을 담당한다.

NMD Residence는 Premium Corporate Housing으로 기업 신뢰와 프리미엄 수요를 담당한다.

Service Segment는 고객의 목적을 설명하고, Option Layer는 고객 경험을 조정하며, Data Layer는 상품, 고객, 운영, AI Matching을 연결한다.

NMD의 상품 구조는 단순한 매물 분류가 아니라, Corporate Housing & Furnished Living Platform으로 확장하기 위한 운영 및 기술 기반이다.
