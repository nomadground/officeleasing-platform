# NMD Product Architecture v0.1

---

## Document Purpose

본 문서는 NMD(Nomad Ground)의 Product Architecture를 정의한다.

Blueprint는 NMD의 존재 이유와 장기 방향을 정의한다.

Business Plan은 NMD의 사업 구조와 성장 전략을 정의한다.

PRD 00 Product Principles는 Product 설계 및 개발 의사결정의 최상위 원칙을 정의한다.

본 문서는 이러한 상위 기준을 실제 Product 구조로 전환한다.

본 문서는 다음 질문에 답한다.

- NMD Product는 어떤 영역으로 구성되는가?
- 고객, 공급, 상품, 문의, 운영, 데이터는 어떻게 연결되는가?
- NMD Nest와 NMD Residence는 Product 안에서 어떤 역할을 하는가?
- Public Website, CMS, Admin, AI Agent는 어떤 관계를 가지는가?
- MVP에서 어떤 Module을 우선 구축해야 하는가?
- WordPress MVP가 향후 자체 Platform으로 어떻게 확장될 수 있는가?
- 각 후속 PRD 문서는 어떤 Architecture를 기준으로 작성되어야 하는가?

본 문서는 다음 Product 문서의 기준이 된다.

- Information Architecture
- User Journey
- Data Model
- Website Specification
- Listing Specification
- Property Detail Specification
- Inquiry Workflow
- CMS Specification
- Admin Dashboard Specification
- AI Agent Specification
- Technical Architecture
- Design System
- Development Specification

---

# 1. Document Scope

본 문서는 NMD Product의 논리적 구조와 주요 Module 간 관계를 정의한다.

본 문서가 정의하는 범위는 다음과 같다.

- Product Domain
- User Structure
- Product Line
- Inventory Structure
- Supply Structure
- Discovery Structure
- Inquiry Structure
- Operation Structure
- Korea Housing Trust Layer
- AI Structure
- Content Structure
- Admin Structure
- Data Flow
- Module Boundary
- MVP Boundary
- Platform Evolution Direction

본 문서는 다음을 최종 확정하지 않는다.

- 세부 Database Schema
- 최종 Field Name
- WordPress CPT 및 Taxonomy의 최종 구성
- ACF Field Group의 세부 항목
- 화면별 UI Design
- 페이지별 최종 Copy
- API Specification
- 상세 권한 정책
- 계약서 및 법률 문구
- 상세 운영 SOP

위 항목은 후속 PRD에서 구체화한다.

---

# 2. Product Architecture Definition

NMD Product Architecture는 단순한 웹사이트 메뉴 구조가 아니다.

NMD Product Architecture는 다음을 하나의 시스템으로 연결하는 구조이다.

Corporate Demand

+

Resident Needs

+

Housing Supply

+

Product Standard

+

Korea Housing Trust Layer

+

Inquiry Workflow

+

Operation System

+

Content & Brand Experience

+

Data

+

AI

NMD는 단순히 Property를 검색하고 문의하는 Website에 머무르지 않는다.

NMD Product는 기업과 전문인력이 Corporate Housing을 발견하고, 검토하고, 문의하고, 계약과 입주를 준비하며, 장기적으로 운영까지 관리할 수 있도록 지원하는 Living Product이다.

---

# 3. Architecture Principles

NMD Product Architecture는 PRD 00 Product Principles를 따른다.

본 Architecture 문서는 PRD 00 Product Principles에서 정의한 전체 원칙 중 Product Domain, Module Boundary, Data Relationship, Operation, Trust, AI, Scalability와 직접 관련된 원칙을 중심으로 구체화한다.

Curated Inventory, Simple Experience, Trust Before Conversion 등 화면과 고객 경험에 보다 직접적으로 관련된 원칙은 각 Module 및 후속 Information Architecture, User Journey, Feature Specification에서 구체화한다.

---

## 3.1 Corporate First Demand

Product는 기업 고객의 반복적인 Housing Needs를 구조적으로 수용해야 한다.

기업 고객은 단순 방문자가 아니다.

기업은 다음 역할을 수행할 수 있다.

- Buyer
- Account
- Cost Bearer
- Contract Party
- Request Organization
- Repeat Customer

Product는 기업 담당자가 여러 직원의 주거 요청을 관리할 수 있는 방향으로 확장 가능해야 한다.

---

## 3.2 Resident-Centered Experience

기업이 비용을 부담하거나 계약하더라도 실제 생활하는 사람은 Resident이다.

Product는 Buyer와 User가 다를 수 있다는 전제에서 설계한다.

기업 담당자의 관리 편의성과 Resident의 실제 생활 경험을 동시에 고려해야 한다.

---

## 3.3 Partner First Supply

NMD는 모든 Property를 직접 소유하거나 운영하는 구조를 전제로 하지 않는다.

Product는 다양한 공급 파트너와 Inventory Control Level을 수용해야 한다.

공급 방식이 달라도 공통된 데이터 구조, 정보 정확성, 고객 안내 기준 및 최소 품질 기준을 유지해야 한다.

---

## 3.4 Korea Housing Trust Layer

Korea Housing Trust Layer는 별도의 콘텐츠 영역에만 존재하지 않는다.

다음 Product 영역에 구조적으로 연결되어야 한다.

- Property Data
- Listing Data
- Inquiry Workflow
- Contract Readiness
- AI Guidance
- Admin Review
- Customer Communication
- Partner Management
- Content
- Operation

Korea Housing Trust Layer는 법률 또는 행정 판단을 자동으로 확정하는 기능이 아니다.

정확한 정보 수집, 확인 상태 관리, 고객 안내 및 전문가 연결을 지원하는 Product Layer이다.

---

## 3.5 Operation First

Product는 고객에게 보이는 화면뿐 아니라 실제 운영 과정을 지원해야 한다.

새로운 기능은 다음 중 하나 이상에 기여해야 한다.

- 반복 업무 감소
- 정보 정확성 향상
- 응답 속도 향상
- 운영 상태 가시화
- 파트너 협업 개선
- 고객 커뮤니케이션 개선
- 데이터 축적
- 운영 품질 표준화

---

## 3.6 Data First

UI보다 Data Structure를 먼저 정의한다.

동일한 데이터는 하나의 기준 출처에서 관리한다.

Website, Admin, AI Agent, CRM 및 향후 Platform은 동일한 기준 데이터를 사용해야 한다.

---

## 3.7 AI Native

AI는 Product Architecture 외부에 부착되는 별도 기능이 아니다.

Inquiry, Matching, Korea Housing Trust Layer, Content, Administration, Operation Workflow와 연결되는 공통 Intelligence Layer로 설계한다.

다만 AI 없이도 핵심 Workflow와 기준 데이터는 작동해야 한다.

AI는 기준 데이터를 생성하는 원본이 아니라, 구조화된 데이터를 활용하여 고객과 운영자의 판단을 지원하는 Layer이다.

---

## 3.8 Scalable Architecture

NMD Product는 초기 MVP의 단순성과 장기 Platform 확장성을 동시에 고려한다.

Architecture는 특정 화면, CMS 또는 WordPress 구현 방식에 종속되지 않아야 한다.

### Modular Architecture

각 Product Module은 역할과 책임이 명확해야 한다.

Module은 가능한 한 독립적으로 수정하고 확장할 수 있어야 한다.

하나의 기능 변경이 전체 Product 구조를 불필요하게 변경시키지 않아야 한다.

---

# 4. Product Architecture Overview

NMD Product는 다음 Architecture Layer로 구성된다.

1. Experience Layer
2. Discovery & Content Layer
3. Inquiry & Matching Layer
4. Product & Inventory Layer
5. Customer & Account Layer
6. Partner & Supply Layer
7. Operation Layer
8. Korea Housing Trust Layer
9. Data & Intelligence Layer
10. Administration Layer
11. Platform Foundation Layer

각 Layer는 독립된 목적을 가지지만 하나의 Product Flow 안에서 연결된다.

---

# 5. Experience Layer

Experience Layer는 사용자가 NMD와 직접 상호작용하는 영역이다.

주요 사용자 유형은 다음과 같다.

- Corporate Buyer
- Corporate Requester
- Individual Requester
- Resident
- Supply Partner
- NMD Operator
- Administrator

초기 MVP의 주요 Experience는 다음과 같다.

- Public Website
- Property Discovery
- Property Detail
- Corporate Inquiry
- Individual Inquiry
- Tour Request
- AI Housing Assistant
- Insight Content
- Company & Trust Information

향후 Product는 동일한 Architecture를 기반으로 역할별 Experience를 확장할 수 있어야 한다.

Experience Layer는 Data를 직접 소유하지 않는다.

각 화면은 Product 및 Data Module의 기준 정보를 불러와 사용자에게 적절하게 표현한다.

---

# 6. Discovery & Content Layer

Discovery & Content Layer는 고객이 NMD와 Housing Product를 이해하고 적합한 선택지를 발견하도록 지원한다.

주요 Module은 다음과 같다.

- Homepage
- Product Line Page
- Property Listing
- Property Detail
- Location Content
- Insight
- Corporate Housing Guide
- Korea Housing Trust Content
- Company Information
- Partner Information
- Search
- Filter
- Recommendation Entry Point

Discovery는 단순 조건 검색에 머무르지 않는다.

고객은 다음 기준을 통해 Housing을 탐색할 수 있어야 한다.

- Location
- Product Line
- Housing Type
- Unit Type
- Budget
- Stay Period
- Move-in Date
- Number of Residents
- Corporate Suitability
- Resident Suitability
- Furnished Status
- Availability
- Contract Readiness
- Korea Housing Trust Information
- Service Availability

모든 Discovery 기능은 Listing Data를 기준으로 작동해야 한다.

---

# 7. Product & Inventory Layer

Product & Inventory Layer는 NMD가 고객에게 제공하는 Housing Product를 구조화한다.

Product와 Inventory는 동일한 개념이 아니다.

Property는 물리적 또는 법적 주거 자산을 의미한다.

Product는 Property에 NMD의 상품 기준, 운영 기준, 정보 기준 및 고객 경험을 적용한 결과를 의미한다.

Listing은 Product를 특정 고객에게 발견 및 제안할 수 있도록 표현한 공개 또는 제한 공개 단위이다.

---

## 7.1 Core Product Lines

NMD의 초기 Product Line은 다음 두 가지이다.

- NMD Nest
- NMD Residence

Product Line은 공간의 기본 상품 포지션과 경험 기준을 정의한다.

---

### NMD Nest

## Efficient Furnished Living

NMD Nest는 효율적인 Compact Furnished Living Product이다.

주요 특성은 다음과 같다.

- Compact Living
- Move-in Ready
- Furnished
- Business District Accessibility
- Efficient Cost Structure
- Individual or Corporate Use
- Standardized Basic Experience

---

### NMD Residence

## Premium Corporate Housing

NMD Residence는 높은 공간 품질과 Corporate Housing 적합성을 제공하는 Premium Living Product이다.

주요 특성은 다음과 같다.

- Premium Housing
- Corporate Suitability
- Family or Executive Suitability
- Higher Space and Service Standard
- Contract and Operation Reliability
- Enhanced Resident Experience

---

## 7.2 Product Line Classification Principle

Product Line은 다음 요소 중 하나만으로 결정하지 않는다.

- 가격
- 면적
- 건물명
- 주택 유형
- 지역

Product Line은 다음 요소를 종합하여 결정한다.

- 공간 품질
- 면적 및 구조
- 가구·가전 수준
- 건물 및 공용부 수준
- Corporate Suitability
- Resident Experience
- 운영 가능성
- 서비스 가능 범위
- 가격 포지션
- NMD Brand Standard

상세 분류 기준은 후속 Product Standard 문서에서 정의한다.

---

## 7.3 Core Housing Entities

NMD Product Architecture의 주요 Housing Entity는 다음과 같다.

### Property

NMD가 상품화 가능성을 검토하거나 관리하는 주거 자산의 상위 개념이다.

Property는 물리적 자산, 공급 관계 및 기본 운영 정보를 연결한다.

### Building

하나 이상의 Unit이 위치하는 건물 단위이다.

건물 주소, 위치, 시설, 접근성, 공용부 및 관리 정보를 관리한다.

### Unit

실제 거주 및 계약의 대상이 되는 개별 공간 단위이다.

면적, 구조, 가구·가전, 가격, 상태 및 입주 가능 정보를 관리한다.

### Listing

고객에게 발견, 추천 또는 제안되는 노출 단위이다.

Listing은 Building 또는 Unit의 기준 데이터를 사용하되, 공개 범위와 판매·제안 정보를 별도로 관리할 수 있다.

NMD Residence는 Product Line 명칭이며 공통 데이터 Entity 명칭으로 사용하지 않는다.

---

## 7.4 Inventory Status

Inventory는 단순히 등록 여부만으로 관리하지 않는다.

최소한 다음 상태 개념을 수용해야 한다.

- Candidate
- Under Review
- Approved
- Published
- Available
- Temporarily Unavailable
- Reserved
- Contract Pending
- Occupied
- Inactive
- Archived

최종 상태명과 상태 전환 규칙은 Data Model 및 Admin Specification에서 확정한다.

---

# 8. Service Purpose Layer

Product Line은 공간의 상품 유형을 정의한다.

Service Purpose는 고객이 해당 공간을 이용하는 이유와 요구사항을 정의한다.

Product Line과 Service Purpose는 분리한다.

하나의 Listing은 여러 Service Purpose에 적합할 수 있다.

Service Purpose는 다음과 같은 수요를 표현할 수 있다.

- Corporate Employee Housing
- Expat Housing
- Relocation Housing
- Project Workforce Housing
- Professional Long-stay Housing
- Family Housing
- Individual Furnished Living

위 명칭은 고객 목적을 설명하기 위한 논리적 분류이며, 별도의 Product Brand가 아니다.

최종 Taxonomy와 공개 명칭은 Information Architecture 및 Data Model에서 결정한다.

---

# 9. Service & Option Layer

Service와 Option은 Product Line과 구분한다.

Service는 고객 또는 Resident에게 제공되는 지원 범위를 의미한다.

Option은 특정 Property 또는 계약에 추가될 수 있는 선택 요소를 의미한다.

예시는 다음과 같다.

- Internet
- Utility Coordination
- Housekeeping
- Bedding
- Move-in Support
- Move-out Support
- Maintenance Coordination
- Parking
- Furniture Setup
- Appliance Setup
- Foreign Resident Guide
- Corporate Documentation Support

모든 Service와 Option이 모든 Listing에 제공되는 것은 아니다.

제공 가능 여부, 제공 주체, 비용, 조건 및 책임 범위를 데이터로 구분해야 한다.

---

# 10. Supply Architecture

Supply Architecture는 Property의 소유 관계만을 의미하지 않는다.

다음 요소를 분리하여 관리할 수 있어야 한다.

- Supply Source
- Ownership Relationship
- Contract Relationship
- Operation Responsibility
- Customer Communication Responsibility
- Maintenance Responsibility
- Listing Control
- Pricing Control
- Availability Control
- Quality Control Level

---

## 10.1 Supply Types

초기 Product는 다음 Supply Type을 수용한다.

- Partner Inventory
- Brokerage-Sourced Inventory
- NMD Managed Inventory
- NMD Owned Inventory

Supply Type은 고객-facing Product Line이 아니다.

동일한 NMD Nest 또는 NMD Residence라도 Supply Type은 다를 수 있다.

---

## 10.2 Supply Type Principles

### Partner Inventory

소유자 또는 기존 운영자가 공급과 기본 운영을 담당하고, NMD는 Listing, Demand, Inquiry 및 고객 연결을 지원한다.

### Brokerage-Sourced Inventory

NMD 또는 적법한 라이선스 구조를 통해 확보하고 제안하는 Inventory이다.

중개행위가 필요한 경우 관련 법령과 라이선스 구조에 따라 처리한다.

### NMD Managed Inventory

NMD가 일정 범위 이상의 운영, 고객 경험 및 품질 관리를 담당하는 Inventory이다.

### NMD Owned Inventory

NMD가 소유하거나 높은 수준으로 통제하는 Inventory이다.

---

## 10.3 Service Disclosure

Supply Type 자체를 고객에게 반드시 그대로 노출할 필요는 없다.

다만 고객에게 영향을 미치는 다음 사항은 명확하게 표시해야 한다.

- 계약 주체
- 운영 주체
- 고객 문의 대응 주체
- 유지보수 책임 범위
- 제공 서비스
- 가격 및 추가 비용
- 입주 및 퇴실 절차
- NMD의 관리 및 보장 범위

NMD는 통제할 수 없는 품질이나 서비스를 통제 가능한 것처럼 표현하지 않는다.

---

# 11. Customer & Account Layer

NMD는 Buyer, Requester, Resident 및 Contract Party가 다를 수 있다는 전제에서 설계한다.

---

## 11.1 Company

Corporate Housing을 요청하거나 반복적으로 이용하는 기업 또는 조직이다.

Company는 다음과 연결될 수 있다.

- Multiple Requesters
- Multiple Residents
- Multiple Inquiries
- Multiple Contracts
- Multiple Listings
- Billing Information
- Communication History

---

## 11.2 Buyer / Account

비용 부담, 구매 의사결정 또는 고객 관계의 기준이 되는 주체이다.

Buyer는 Company일 수도 있고 개인일 수도 있다.

---

## 11.3 Requester

실제 Inquiry를 생성하고 NMD와 소통하는 사람이다.

Requester는 다음일 수 있다.

- HR Manager
- General Affairs Manager
- Relocation Manager
- Executive Assistant
- Employee
- Resident
- Individual Customer

---

## 11.4 Resident

실제 공간에 거주하는 사람이다.

한 Inquiry 또는 Contract에는 한 명 이상의 Resident가 연결될 수 있어야 한다.

---

## 11.5 Contract Party

실제 계약상 권리와 의무를 부담하는 당사자이다.

Contract Party는 Buyer, Requester 또는 Resident와 동일할 수도 있고 다를 수도 있다.

구체적인 관계 구조는 Data Model에서 정의한다.

---

# 12. Inquiry & Matching Layer

Inquiry는 단순 Contact Form 제출이 아니다.

Inquiry는 Customer Need를 구조화하고 적합한 Housing Product와 연결하는 핵심 Product Entity이다.

---

## 12.1 Inquiry Inputs

Inquiry는 다음 정보를 수집할 수 있어야 한다.

- Customer Type
- Company
- Requester
- Resident
- Preferred Location
- Alternative Location
- Move-in Date
- Stay Period
- Budget
- Number of Residents
- Unit Requirement
- Product Preference
- Corporate Contract Need
- Foreigner Support Need
- Registration or Documentation Need
- Parking Requirement
- Service Requirement
- Special Request
- Consent and Contact Information

모든 항목을 한 번에 필수로 요구하지 않는다.

고객 경험과 상담 효율을 고려하여 단계적으로 수집할 수 있어야 한다.

---

## 12.2 Inquiry Lifecycle

Inquiry는 최소한 다음 과정을 수용해야 한다.

New Inquiry

↓

Qualification

↓

Requirement Clarification

↓

Matching

↓

Proposal

↓

Tour or Viewing

↓

Negotiation or Condition Review

↓

Contract Preparation

↓

Converted or Closed

최종 상태명과 자동화 규칙은 Inquiry Workflow 문서에서 확정한다.

---

## 12.3 Matching

Matching은 단순히 동일 조건의 Listing을 검색하는 기능이 아니다.

Matching은 다음 데이터를 종합한다.

- Customer Need
- Company Requirement
- Resident Requirement
- Location
- Budget
- Move-in Date
- Stay Period
- Availability
- Product Line
- Housing Type
- Corporate Suitability
- Resident Suitability
- Contract Readiness
- Korea Housing Trust Data
- Service Availability
- Operation Status
- Partner Reliability
- NMD Review Status

초기에는 운영자가 Matching 결과를 검토하고 확정한다.

AI는 Matching을 지원하지만 최종 판단을 자동으로 대체하지 않는다.

---

# 13. Tour & Proposal Layer

Tour Request는 일반 Inquiry와 연결되는 하위 Workflow이다.

독립적으로 생성되더라도 기준 Inquiry 또는 Customer Record와 연결될 수 있어야 한다.

Proposal은 하나 이상의 Listing을 특정 Inquiry에 연결하여 고객에게 제안하는 구조이다.

Proposal은 다음 정보를 포함할 수 있다.

- Recommended Listings
- Recommendation Reason
- Availability
- Pricing
- Contract Conditions
- Service Availability
- Trust Notes
- Comparison Information
- Tour Availability
- Expiration or Update Time
- Internal Notes
- Customer-facing Notes

초기 MVP에서는 Proposal을 운영자가 수동 또는 반자동으로 생성할 수 있다.

---

# 14. Operation Layer

Operation Layer는 NMD Product가 실제 Housing Service로 작동하도록 지원한다.

초기 MVP에서는 모든 운영 기능을 구현하지 않더라도 향후 연결 가능한 구조를 유지해야 한다.

Operation Layer는 다음 Domain을 포함할 수 있다.

- Property Onboarding
- Product Review
- Listing Approval
- Availability Management
- Pricing Update
- Inquiry Assignment
- Tour Coordination
- Contract Preparation
- Move-in Preparation
- Resident Support
- Maintenance Coordination
- Cleaning Coordination
- Move-out Coordination
- Settlement
- Partner Communication
- Quality Review
- Issue Management
- Operation Log

Operation Module은 Inventory Control Level에 따라 책임 범위가 달라질 수 있다.

---

## 14.1 Operation Responsibility

각 Property 또는 Unit은 다음 책임 주체를 구분할 수 있어야 한다.

- Listing Manager
- Customer Communication Manager
- Contract Coordinator
- Property Operator
- Maintenance Manager
- Cleaning Provider
- Move-in Coordinator
- Move-out Coordinator
- Partner Contact
- Internal Owner

한 사람이 여러 역할을 담당할 수 있으나, 데이터 구조에서는 역할을 구분할 수 있어야 한다.

---

## 14.2 Operation Status

운영 상태는 Listing 공개 상태와 분리한다.

예를 들어 Listing이 Published 상태여도 다음 운영 이슈가 존재할 수 있다.

- Availability Verification Required
- Price Verification Required
- Maintenance Required
- Cleaning Required
- Documentation Review Required
- Partner Confirmation Required
- Temporary Hold

구체적인 Operation Status는 Data Model과 Admin Specification에서 정의한다.

---

# 15. Korea Housing Trust Layer

Korea Housing Trust Layer는 NMD의 핵심 Product Layer이다.

이 Layer는 한국 주거시장과 Corporate Housing 이용 과정에서 발생하는 불확실성을 구조화한다.

---

## 15.1 Trust Data Domains

Korea Housing Trust Data는 다음 영역을 포함할 수 있다.

- Corporate Lease Availability
- Individual Lease Availability
- Contract Party Requirement
- Move-in Registration Information
- Foreigner Residence Documentation
- Visa-related Housing Documentation
- Fixed Date Information
- Jeonse Right Information
- Deposit Structure
- Deposit Return Risk Notes
- Guarantee Insurance Review Information
- Utility and Maintenance Fee Structure
- Move-in Settlement
- Move-out Settlement
- Corporate Documentation Support
- Verification Status
- Source of Information
- Last Verified Date
- Internal Review Notes
- Customer-facing Notes

---

## 15.2 Verification Principle

Trust Data는 확인 상태와 출처를 함께 관리해야 한다.

가능한 상태 개념은 다음과 같다.

- Unconfirmed
- Partner Provided
- Document Verified
- NMD Reviewed
- Expert Review Required
- Not Applicable
- Outdated

Trust Data는 확인되지 않은 내용을 확정된 사실처럼 표시해서는 안 된다.

---

## 15.3 Legal Boundary

NMD Product는 법률·세무·행정 자문을 자동으로 확정하지 않는다.

전문 자격 또는 공식 확인이 필요한 영역은 다음 방식으로 처리한다.

- General Information
- Verification Checklist
- Required Document Guide
- Expert Referral
- Official Institution Guide
- Manual Review

AI Agent 역시 동일한 경계를 따라야 한다.

---

# 16. AI Layer

AI Layer는 독립된 Product가 아니라 NMD Product 전반을 지원하는 Intelligence Layer이다.

AI는 다음 영역을 지원할 수 있다.

- Requirement Collection
- Inquiry Structuring
- Location Recommendation
- Listing Recommendation
- Matching Explanation
- Missing Information Detection
- Trust Information Guidance
- Inquiry Summary
- Internal Handoff
- Content Assistance
- Data Quality Review
- Operation Task Assistance

---

## 16.1 AI Architecture Principle

AI는 기준 데이터 없이 독립적으로 판단하지 않는다.

AI Response는 가능한 한 다음 정보를 기반으로 한다.

- Approved Content
- Property Data
- Listing Data
- Availability Data
- Customer Requirement
- Korea Housing Trust Data
- Operation Rule
- Product Rule
- Admin-approved Knowledge

AI가 확인할 수 없는 내용은 추정하지 않는다.

---

## 16.2 Human Review

다음 영역은 Human Review를 기본으로 한다.

- Final Listing Recommendation
- Contract Condition Confirmation
- Legal or Administrative Interpretation
- Deposit Safety Assessment
- Pricing Commitment
- Availability Commitment
- Exception Approval
- Partner Dispute
- Customer Complaint
- High-risk Decision

AI는 상담과 운영을 준비하고 지원하지만, 책임이 필요한 최종 판단을 자동으로 대체하지 않는다.

---

# 17. Content Layer

Content는 Marketing만을 위한 별도 영역이 아니다.

Content는 고객 교육, 신뢰 형성, Discovery, AI Guidance 및 Inquiry Conversion을 지원한다.

Content Domain은 다음과 같다.

- Corporate Housing
- Furnished Living
- Seoul Business District
- Location Guide
- Contract Guide
- Foreigner Housing Guide
- Korea Housing Trust Guide
- Move-in Guide
- Resident Guide
- Company Guide
- Partner Guide
- Insight

Content는 관련 Product 및 Inquiry Flow와 연결되어야 한다.

예를 들어 법인 임대차 콘텐츠는 다음과 연결될 수 있다.

- Corporate Inquiry
- Contract Readiness
- Trust Data
- AI Guidance
- Property Detail

Content는 별도의 고립된 Blog로 설계하지 않는다.

---

# 18. Administration Layer

Administration Layer는 NMD 운영자가 Product Data와 Workflow를 관리하는 내부 Product이다.

Admin은 단순 WordPress 게시물 편집 화면에 머무르지 않는다.

초기 Admin은 다음 기능을 지원해야 한다.

- Building Management
- Unit Management
- Listing Management
- Product Line Assignment
- Availability Management
- Pricing Management
- Partner Management
- Company Management
- Customer Management
- Inquiry Management
- Tour Request Management
- Proposal Management
- Trust Data Management
- Content Management
- Status Management
- Internal Notes
- Assignment
- Data Quality Review

MVP에서는 WordPress Admin을 활용할 수 있다.

다만 정보 구조는 향후 독립 Admin System으로 이전 가능한 형태를 유지한다.

---

## 18.1 Role-based Administration

Admin Architecture는 역할별 접근을 수용할 수 있어야 한다.

예상 역할은 다음과 같다.

- Super Admin
- Product Manager
- Listing Manager
- Sales Manager
- Operation Manager
- Content Manager
- Partner Manager
- Viewer

초기에는 역할을 단순화할 수 있다.

그러나 민감정보, 계약정보, 고객정보 및 내부 메모는 공개 콘텐츠와 분리해야 한다.

---

# 19. Data & Intelligence Layer

Data & Intelligence Layer는 모든 Product Module을 연결한다.

주요 Data Domain은 다음과 같다.

- Building
- Unit
- Property
- Listing
- Product Line
- Service Purpose
- Service
- Option
- Partner
- Company
- Buyer / Account
- Requester
- Resident
- Inquiry
- Match
- Recommendation
- Proposal
- Tour Request
- Contract
- Operation
- Trust Data
- Content
- AI Interaction
- Activity Log

본 목록은 논리적 Domain을 정의한다.

최종 Entity, Field 및 Relation은 Data Model 문서에서 확정한다.

---

## 19.1 Single Source of Truth

각 정보에는 기준 출처가 하나만 존재해야 한다.

예시는 다음과 같다.

- Building Address는 Building에서 관리한다.
- Unit Area는 Unit에서 관리한다.
- Customer Requirement는 Inquiry에서 관리한다.
- Company 정보는 Company에서 관리한다.
- Availability는 Inventory 또는 Unit 상태에서 관리한다.
- 고객-facing 노출 문구는 Listing에서 관리할 수 있다.
- Trust Verification은 Trust Data에서 관리한다.

다른 Module은 기준 데이터를 복제하지 않고 참조한다.

---

## 19.2 Data Quality

주요 데이터는 다음 속성을 가져야 한다.

- Owner
- Source
- Status
- Last Updated Date
- Verification Status
- Visibility
- Change History
- Internal Note

모든 Field에 위 속성을 직접 추가한다는 의미는 아니다.

시스템 차원에서 데이터 책임과 최신성을 추적할 수 있어야 한다.

---

# 20. Platform Foundation Layer

초기 Product는 WordPress 기반으로 구현한다.

예상 기술 기반은 다음과 같다.

- WordPress
- GeneratePress
- GenerateBlocks
- Advanced Custom Fields
- Custom Post Types
- Custom Taxonomies
- Form or Inquiry Workflow
- Search and Filter
- Analytics
- SEO Structure
- AI Integration Interface

기술 선택은 Product Architecture를 제한해서는 안 된다.

WordPress 구현은 논리적 Domain과 Module을 가능한 한 명확하게 반영해야 한다.

---

## 20.1 Implementation Independence

본 문서의 다음 개념은 특정 WordPress 구조와 동일하지 않다.

- Domain
- Entity
- Module
- Product Line
- Supply Type
- Workflow
- Status
- Relationship

예를 들어 하나의 Domain이 반드시 하나의 CPT가 되는 것은 아니다.

반대로 하나의 CPT 안에 여러 Domain 책임을 무리하게 합쳐서도 안 된다.

최종 WordPress Mapping은 Technical Architecture에서 결정한다.

---

## 20.2 Portability

Product Data는 향후 다음 환경으로 이전할 수 있어야 한다.

- Custom Database
- Independent Admin System
- CRM
- Partner Portal
- Corporate Account System
- AI Matching Engine
- API-based Platform

이를 위해 다음 원칙을 따른다.

- 구조화된 데이터 사용
- 명확한 Entity ID
- 관계형 참조
- 공개 데이터와 내부 데이터 분리
- 표시 문구와 기준 데이터 분리
- Status 표준화
- Export 가능성
- API 연결 가능성

---

# 21. Layer-to-Module Mapping

Architecture Layer와 Product Module은 동일한 분류 체계가 아니다.

Layer는 Product의 논리적 책임 영역을 정의한다.

Module은 해당 책임을 실제 기능과 Workflow로 구현하는 단위이다.

하나의 Layer는 여러 Module로 구현될 수 있으며, 하나의 Module이 여러 Layer를 지원할 수도 있다.

| Architecture Layer | Primary Product Modules |
|---|---|
| Experience Layer | Public Website, Administration |
| Discovery & Content Layer | Public Website, Content & Insight |
| Inquiry & Matching Layer | Inquiry & Customer, Matching & Recommendation, Tour & Proposal |
| Product & Inventory Layer | Product & Inventory |
| Customer & Account Layer | Inquiry & Customer |
| Partner & Supply Layer | Partner & Supply |
| Operation Layer | Operation & Workflow, Administration |
| Korea Housing Trust Layer | Korea Housing Trust, AI Assistance |
| Data & Intelligence Layer | 전체 Module의 데이터 기반이며, Analytics & Activity 및 AI Assistance가 이를 직접 분석·활용 |
| Administration Layer | Administration |
| Platform Foundation Layer | 전체 Module의 기술 기반 |

본 Mapping은 논리적 책임 관계를 설명하기 위한 것이다.

최종 기술 구현 구조와 동일하지 않다.

---

# 22. Core Product Modules

NMD MVP의 Core Product Module은 다음과 같다.

---

## Module 01: Public Website

역할:

- Brand Experience
- Product Discovery
- Content Delivery
- Trust Building
- Inquiry Entry

---

## Module 02: Product & Inventory

역할:

- Building
- Unit
- Property
- Listing
- Product Line
- Availability
- Pricing
- Service Information

---

## Module 03: Inquiry & Customer

역할:

- Company
- Requester
- Resident
- Customer Requirement
- Inquiry Status
- Communication Handoff

---

## Module 04: Matching & Recommendation

역할:

- Requirement Matching
- Listing Recommendation
- Recommendation Reason
- Operator Review
- AI Assistance

---

## Module 05: Tour & Proposal

역할:

- Tour Request
- Proposal
- Listing Comparison
- Customer Follow-up
- Decision Support

---

## Module 06: Partner & Supply

역할:

- Partner Information
- Supply Relationship
- Responsibility
- Inventory Source
- Partner Communication
- Quality and Reliability Information

---

## Module 07: Operation & Workflow

역할:

- Property Onboarding
- Product Review
- Listing Approval
- Availability Verification
- Pricing Verification
- Inquiry Assignment
- Tour Coordination
- Contract Preparation Handoff
- Move-in Preparation
- Resident Support Coordination
- Maintenance Coordination
- Cleaning Coordination
- Move-out Coordination
- Settlement
- Partner Communication
- Quality Review
- Issue Management
- Operation Log

MVP 단계에서는 Operation & Workflow Module의 일부 기능을 Administration Module 안에서 수동 또는 반자동 방식으로 관리할 수 있다.

다만 Product Architecture상 Operation은 Administration과 구분되는 독립 Domain으로 유지한다.

---

## Module 08: Korea Housing Trust

역할:

- Trust Data
- Verification Status
- Contract Readiness
- Documentation Guide
- Customer-facing Trust Information
- Internal Review

---

## Module 09: Content & Insight

역할:

- Educational Content
- Location Content
- Corporate Housing Content
- Trust Content
- SEO / GEO / AI Discoverability
- Inquiry Support

---

## Module 10: Administration

역할:

- Data Management
- Workflow Management
- Status Management
- Assignment
- Review
- Quality Control

MVP 단계에서 Administration Module은 Operation & Workflow Module의 일부 기능을 운영자가 관리하는 내부 Interface 역할을 함께 수행할 수 있다.

---

## Module 11: AI Assistance

역할:

- Requirement Collection
- Inquiry Structuring
- Matching Support
- Guidance
- Summary
- Internal Handoff

---

## Module 12: Analytics & Activity

역할:

- Inquiry Source
- Customer Behavior
- Conversion Flow
- Product Performance
- Data Quality
- Operation Activity
- AI Interaction

---

# 23. End-to-End Product Flow

NMD MVP의 기본 Product Flow는 다음과 같다.

Customer Entry

↓

Brand and Content Discovery

↓

Housing Requirement Identification

↓

Property Discovery or AI Assistance

↓

Listing Review

↓

Inquiry Submission

↓

Requirement Qualification

↓

Matching and Recommendation

↓

Proposal or Tour Request

↓

Condition and Trust Review

↓

Contract Preparation Handoff

↓

Conversion Tracking

초기 MVP는 계약 체결 이후의 전체 운영 기능을 모두 구현하지 않는다.

다만 Inquiry, Customer, Listing 및 Trust Data가 향후 Contract와 Operation Module로 연결될 수 있도록 설계한다.

---

# 24. Architecture Boundaries

Product Architecture의 책임 경계를 명확히 한다.

---

## Website

Website는 고객 Experience와 Discovery를 담당한다.

Website가 기준 데이터를 독립적으로 소유하지 않는다.

---

## CMS

CMS는 공개 콘텐츠와 Listing 표현을 관리한다.

CMS가 전체 운영 시스템을 대체하지 않는다.

---

## Admin

Admin은 기준 데이터와 Workflow를 관리한다.

Admin은 고객-facing Experience와 분리한다.

---

## CRM Function

CRM Function은 Company, Customer, Inquiry 및 Communication Relationship을 관리한다.

초기에는 별도 CRM 제품이 아닌 Admin Module로 구현할 수 있다.

---

## AI Agent

AI Agent는 고객과 운영자를 지원한다.

AI가 기준 데이터의 원본이나 최종 의사결정자가 되지 않는다.

---

## Operation System

Operation System은 계약, 입주, 유지보수, 퇴실 및 파트너 협업을 지원한다.

초기 MVP에서는 구조적 연결만 준비하고 단계적으로 구현한다.

---

# 25. MVP Architecture Scope

MVP에서 우선 구현하는 Module은 다음과 같다.

## Required

- Public Website
- NMD Nest / NMD Residence Product Structure
- Building / Unit / Listing Management
- Property Listing
- Property Detail
- Basic Search and Filter
- Company Information
- Insight Content
- Inquiry
- Corporate Inquiry
- Tour Request
- Basic Customer Records
- Basic Inquiry Status
- Partner Records
- Korea Housing Trust Information
- Admin Management
- Basic Analytics
- AI Housing Assistant Foundation

---

## Limited or Manual-assisted

- Matching
- Proposal Creation
- Availability Verification
- Trust Data Verification
- Customer Qualification
- Partner Coordination
- Inquiry Assignment
- AI Recommendation Review
- Operation Workflow Management

초기에는 운영자가 검토하고 처리하는 Human-in-the-loop 방식으로 구현할 수 있다.

---

## Deferred

- Direct Booking
- Online Payment
- Full Contract Automation
- Resident Portal
- Corporate Dashboard
- Partner Dashboard
- Full Maintenance System
- Full Move-in Management
- Full Move-out Management
- Automated Settlement
- Full ERP-like Operation Platform

Deferred 기능은 현재 MVP에서 구현하지 않더라도 기존 Architecture를 파괴하지 않고 추가할 수 있어야 한다.

---

# 26. Non-Goals

본 Product Architecture의 초기 목표는 다음이 아니다.

- 모든 서울 주거 매물을 수집하는 것
- 일반 부동산 포털을 만드는 것
- 호텔 예약 플랫폼을 만드는 것
- 여행객 중심 숙박 Marketplace를 만드는 것
- 모든 운영 업무를 처음부터 자동화하는 것
- 모든 법률 및 행정 판단을 AI로 자동화하는 것
- 모든 기능을 하나의 거대한 Module에 통합하는 것
- 초기부터 독립 SaaS 또는 ERP를 완성하는 것
- WordPress 구조에 Product Architecture를 종속시키는 것

---

# 27. Architecture Decision Rules

새로운 Module 또는 기능을 추가하기 전에 다음을 확인한다.

1. 어느 Product Domain에 속하는가?
2. 어떤 사용자의 문제를 해결하는가?
3. Corporate First Demand와 일치하는가?
4. Resident-Centered Experience를 개선하는가?
5. Partner First Supply 구조를 지원하는가?
6. Korea Housing Trust Layer와 충돌하지 않는가?
7. 기준 데이터는 어디에서 관리되는가?
8. 기존 Module과 책임이 중복되지 않는가?
9. 운영 효율을 높이는가?
10. 유용하고 재사용 가능한 데이터를 생성하는가?
11. AI 없이도 기본 Workflow가 작동하는가?
12. AI가 사용할 수 있는 구조화된 데이터가 존재하는가?
13. WordPress 이후에도 유지 가능한 개념인가?
14. MVP에 반드시 필요한가?
15. Human Review가 필요한 영역인가?

위 질문에 명확히 답할 수 없는 기능은 즉시 구현하지 않는다.

먼저 Architecture와 책임 경계를 정리한다.

---

# 28. Dependencies on Subsequent PRDs

본 Product Architecture는 다음 후속 문서에서 구체화한다.

---

## PRD 02: Information Architecture

정의할 내용:

- Website Structure
- Navigation
- Page Hierarchy
- Content Relationship
- User Entry Point
- Public Information Structure

---

## PRD 03: User Journey

정의할 내용:

- Corporate Buyer Journey
- Corporate Requester Journey
- Resident Journey
- Individual Customer Journey
- Partner Journey
- Internal Operator Journey

---

## PRD 04: Data Model

정의할 내용:

- Entity
- Field
- Relation
- Status
- Source of Truth
- Visibility
- Verification
- WordPress Mapping Principle

---

## PRD 05–08: Feature Specifications

정의할 내용:

- Listing
- Property Detail
- Inquiry
- Tour Request
- Proposal
- CMS
- Admin
- AI Agent
- Content
- Analytics

Feature Specification의 최종 문서 분할과 번호는 PRD 02–04 작성 후 확정한다.

---

## PRD 09: Technical Architecture

정의할 내용:

- WordPress Implementation
- CPT
- Taxonomy
- ACF
- Search
- Form
- Integration
- Security
- Performance
- Migration
- API

---

# 29. AI Collaboration Rules

모든 AI Tool은 본 문서를 Product Module 및 Domain 구조의 기준으로 사용한다.

AI Tool은 다음 순서로 문서를 참조한다.

README

↓

Blueprint

↓

Business Plan

↓

PRD 00 Product Principles

↓

PRD 01 Product Architecture

↓

Relevant Subsequent PRD

AI Tool은 다음 규칙을 따른다.

- 기존 Product Line을 임의로 추가하지 않는다.
- Product Line과 Service Purpose를 혼동하지 않는다.
- Product Line과 Supply Type을 혼동하지 않는다.
- Building, Unit, Listing을 동일한 Entity로 단정하지 않는다.
- Buyer, Requester, Resident 및 Contract Party를 동일 인물로 전제하지 않는다.
- Korea Housing Trust Layer를 단순 콘텐츠로 축소하지 않는다.
- Operation Layer와 Administration Layer를 동일한 개념으로 취급하지 않는다.
- AI를 기준 데이터의 원본으로 사용하지 않는다.
- WordPress CPT 구조를 논리적 Product Architecture와 동일시하지 않는다.
- 후속 문서에서 새로운 용어를 만들기 전에 기존 용어를 먼저 확인한다.
- 상위 문서와 충돌이 발견되면 임의로 수정하지 않고 Conflict로 보고한다.
- 구현 편의를 위해 Product 원칙을 변경하지 않는다.

---

# 30. Open Decisions

다음 항목은 후속 PRD 또는 실제 운영 검증을 통해 결정한다.

- Property와 Building의 최종 Entity Boundary
- Unit과 Listing의 관계
- 하나의 Unit에 복수 Listing을 허용할지 여부
- Listing 공개 범위
- Product Line 판정 기준
- Service Purpose 최종 분류
- Supply Type과 Operation Control Level의 관계
- Availability Status 최종 구조
- Inquiry Status 최종 구조
- Proposal Entity 필요 여부
- Match와 Recommendation의 Entity 분리 여부
- Customer와 Person Entity의 관계
- Company Account 구조
- Contract Party 관계
- Trust Data Verification Workflow
- AI Recommendation 저장 구조
- CRM을 WordPress 내부에 구현할 범위
- Admin Role과 Permission
- 민감정보 저장 범위
- 외부 CRM 또는 Automation Tool 연결 시점

Open Decision은 미확정 상태를 의미한다.

AI Tool은 Open Decision을 확정된 요구사항으로 가정하지 않는다.

---

# 31. Product Architecture Statement

NMD Product Architecture는

Housing Supply,

Corporate Demand,

Resident Experience,

Operation,

Korea Housing Trust Layer,

Data,

AI를

하나의 구조로 연결한다.

NMD Product는 단순한 Property Website가 아니다.

NMD Product는 기업과 전문인력이

Corporate Housing을 발견하고,

검토하고,

문의하고,

선택하고,

운영할 수 있도록 지원하는

확장 가능한 Corporate Housing & Furnished Living Platform이다.

초기 구현은 단순하게 시작한다.

그러나 Product Domain,

Data Relationship,

Module Boundary,

Trust Structure는

장기적으로 Living Infrastructure로 확장할 수 있도록 설계한다.
