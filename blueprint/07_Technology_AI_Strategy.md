# 07_Technology_AI_Strategy.md

# NMD Technology & AI Strategy

Version: 1.0
Category: Blueprint
Brand: Nomad Ground
Short Name / Logo: NMD
Tagline: Corporate Housing & Furnished Living

---

## 1. Purpose

본 문서는 NMD(Nomad Ground)의 기술 전략과 AI 활용 방향을 정의한다.

Technology는 NMD의 보조 기능이 아니다.

Technology는 Corporate Housing & Furnished Living 서비스를 확장 가능하게 만드는 핵심 인프라이다.

NMD는 단순한 부동산 홈페이지를 만드는 회사가 아니다.

NMD는 기업 고객, 최종 거주자, 공급 파트너, 운영팀이 하나의 시스템 안에서 연결되는 Living Operation Platform을 구축한다.

NMD의 기술 전략은 다음 목표를 가진다.

* 고객 경험 개선
* 운영 비용 절감
* 반복 업무 자동화
* 데이터 기반 의사결정
* 기업 고객 관리 체계 구축
* 최종 거주자 경험 개선
* 파트너 공급망 관리
* Korea Housing Trust Layer 데이터화
* NMD Nest / NMD Residence 운영 표준화
* 장기적으로 NMD Living OS 구축

본 문서는 GPT, Claude, Codex 등 AI 도구가 NMD의 기술 구조, AI 활용 방향, WordPress MVP, Admin System, CRM, Operation Platform, AI Matching System, ERP-like Platform을 일관되게 이해하고 협업하기 위한 기준 문서로 사용된다.

---

## 2. Technology Philosophy

## Technology Enables Scale

NMD는 사람이 모든 문제를 수작업으로 해결하는 운영회사가 되는 것을 목표로 하지 않는다.

NMD는 반복 가능한 업무를 시스템화하고, 반복 가능한 판단을 데이터화하며, 반복 가능한 커뮤니케이션을 AI 기반으로 자동화한다.

NMD 기술의 목표는 다음과 같다.

* Better Customer Experience
* Lower Operation Cost
* Higher Scalability
* Faster Decision Making
* Consistent Quality
* Data-driven Operation
* AI-assisted Workflow
* Platform Value Creation

NMD의 기술 철학은 다음과 같다.

운영은 데이터를 만든다.
데이터는 시스템을 만든다.
시스템은 자동화를 만든다.
자동화는 확장성을 만든다.
AI는 사람의 판단을 대체하는 것이 아니라, 더 빠르고 일관된 운영을 돕는다.

NMD는 처음부터 거대한 소프트웨어 회사를 만들 필요는 없다.

초기에는 WordPress + GeneratePress + ACF 기반으로 빠르게 검증한다.

그러나 초기 설계부터 장기적인 Admin System, CRM, Partner System, AI Matching System, ERP-like Internal Platform 확장 가능성을 고려해야 한다.

---

## 3. Core Technology Principles

NMD의 모든 기술 의사결정은 다음 원칙을 따른다.

---

### 3.1 Product-Operation-Data Alignment

NMD의 기술은 Product와 Operation을 분리해서 설계하지 않는다.

NMD Nest와 NMD Residence는 단순한 상품명이 아니라, 데이터 구조와 운영 구조의 기준이다.

예를 들어 Property 하나는 다음 정보를 함께 가져야 한다.

* Product Line
* Location
* Price
* Furnished Status
* Operation Status
* Resident Suitability
* Corporate Suitability
* Korea Housing Trust Data
* Financial Data
* AI Matching Tags

즉, NMD의 기술 구조는 상품, 운영, 고객, 정산, 행정 신뢰, AI 추천이 연결될 수 있도록 설계한다.

---

### 3.2 Corporate First Demand

NMD의 수요 전략은 Corporate First Demand이다.

따라서 기술 시스템은 초기부터 기업 고객을 관리할 수 있어야 한다.

필요한 기능은 다음과 같다.

* Company Database
* Corporate Inquiry Management
* Buyer / User 구분
* 기업 담당자 정보 관리
* 직원 주거 요청 관리
* 제안 상품 기록
* 계약 진행 상태 관리
* 기업 보고 자료 생성
* 반복 고객 관리
* Corporate Account History

NMD의 기술은 단순 B2C 예약 시스템이 아니라, 기업 고객의 반복 수요를 관리할 수 있는 구조여야 한다.

---

### 3.3 Resident-Centered Experience

NMD는 기업 중심으로 판매하지만, 실제 서비스 평가는 최종 거주자인 Resident의 경험에서 결정된다.

따라서 기술 시스템은 Resident Experience를 관리할 수 있어야 한다.

필요한 기능은 다음과 같다.

* Resident Profile
* Preferred Language
* Move-in Requirement
* Stay Period
* Maintenance Requests
* Communication History
* Satisfaction Notes
* Renewal Potential
* Resident Feedback

NMD는 B2B 회사처럼 판매하지만, B2C 브랜드처럼 경험을 설계해야 한다.

---

### 3.4 Partner First Supply

NMD의 공급 전략은 Partner First Supply이다.

따라서 기술 시스템은 공급 파트너를 관리할 수 있어야 한다.

필요한 기능은 다음과 같다.

* Partner Database
* Property Owner Management
* Building Owner Management
* Cleaning Partner Management
* Maintenance Partner Management
* Furniture / Appliance Partner Management
* Partner Quality Score
* Partner Response Time
* Partner Issue History
* Partnership Status

NMD는 자산을 모두 소유하는 회사가 아니라, 파트너 공급망을 NMD 기준으로 운영하는 회사다.

---

### 3.5 Korea Housing Trust Layer

한국 주거 제도와 행정 절차는 NMD의 중요한 차별화 요소다.

따라서 기술 시스템은 Korea Housing Trust Layer를 데이터로 관리해야 한다.

주요 데이터는 다음과 같다.

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

이 데이터는 외부에 모두 노출할 필요는 없지만, 상담, 계약, 운영, 기업 제안, AI 추천에서 활용 가능해야 한다.

단, NMD는 법률·세무·행정 대행사가 아니다.

법률·세무·행정 대행 자격이 필요한 영역은 전문가 협력 또는 전문기관 안내로 처리한다.

AI도 이 영역을 단정적으로 답변해서는 안 된다.

---

### 3.6 Build for Scale, Start with MVP

NMD는 처음부터 완전한 자체 플랫폼을 만들 필요는 없다.

초기 목표는 빠르게 검증 가능한 MVP를 만드는 것이다.

초기 시스템은 다음 기준을 충족하면 된다.

* 상품 등록 가능
* 검색 가능
* 문의 수집 가능
* 기업 고객 요청 기록 가능
* 운영 상태 관리 가능
* Korea Housing Trust Notes 관리 가능
* SEO 콘텐츠 발행 가능
* AI가 활용할 수 있는 구조화 데이터 확보 가능

초기에는 WordPress + GeneratePress + ACF 기반으로 시작할 수 있다.

그러나 설계는 향후 자체 Web Application, Admin System, CRM, Partner Dashboard, AI Matching System으로 확장 가능해야 한다.

---

## 4. Long-Term Technology Vision

NMD의 장기 기술 비전은 NMD Living OS를 구축하는 것이다.

## NMD Living OS

NMD Living OS는 Corporate Housing & Furnished Living 운영을 위한 기술 기반 운영 시스템이다.

NMD Living OS는 다음 요소를 하나의 시스템으로 연결한다.

* Property
* Building
* Unit
* Product Line
* Customer
* Company
* Resident
* Partner
* Contract
* Inquiry
* Maintenance
* Cleaning
* Move-in
* Move-out
* Korea Housing Trust Data
* Financial Data
* Operation Data
* AI Matching
* Reporting

NMD Living OS는 단순 관리자 페이지가 아니다.

NMD Living OS는 많은 공간을 일관된 품질로 운영하고, 기업 고객과 최종 거주자에게 신뢰 가능한 경험을 제공하기 위한 운영 인프라다.

Future Vision:

## NMD Living OS

A technology layer for managing Corporate Housing & Furnished Living infrastructure.

---

## 5. Technology Evolution by Phase

NMD 기술 전략은 00_Index.md의 Phase 기준과 일치해야 한다.

Phase 명칭은 다음 기준을 따른다.

* Phase 1: Seoul Corporate Housing Platform
* Phase 2: Korea Furnished Living Network
* Phase 3: Asia Corporate Living Infrastructure
* Phase 4: Global Mobility Housing Partner

---

### 5.1 Phase 1: Seoul Corporate Housing Platform

Phase 1의 기술 목표는 Digital Foundation 구축이다.

이 단계에서는 서울 주요 업무지구를 중심으로 NMD Nest와 NMD Residence의 상품성, 운영성, 고객 수요를 검증한다.

핵심 목표는 다음과 같다.

* Website 구축
* CMS 구축
* Property Database 구축
* Product Line 관리
* Inquiry Management 구축
* Basic Admin System 구축
* SEO Infrastructure 구축
* Korea Housing Trust Data 입력 구조 구축
* Resident Experience Data 입력 구조 구축
* Corporate Inquiry 관리 구조 구축

초기 기술 스택은 다음을 고려한다.

* WordPress
* GeneratePress
* GenerateBlocks
* ACF
* CPT
* SEO Plugin
* Form / Inquiry Plugin
* CRM 연동 가능 구조
* Analytics
* AI Writing / Summary Workflow

Phase 1의 목적은 완성형 플랫폼이 아니라, 빠른 검증과 데이터 축적이다.

---

### 5.2 Phase 2: Korea Furnished Living Network

Phase 2의 기술 목표는 Operation Platform 구축이다.

이 단계에서는 서울에서 검증한 운영 모델을 한국 주요 도시와 업무지구로 확장한다.

핵심 목표는 다음과 같다.

* Unit Management System
* Contract Management
* Customer CRM
* Corporate Account Management
* Maintenance System
* Cleaning Management
* Partner Management
* Performance Dashboard
* Reporting System
* Korea Housing Trust Data Management
* Resident Feedback System

이 단계부터는 단순 WordPress CMS를 넘어 운영 관리 시스템의 중요성이 커진다.

WordPress를 유지하더라도, 별도의 CRM, Airtable, Notion, Retool, 자체 Admin, API 기반 데이터베이스 등으로 확장할 수 있다.

핵심은 데이터 구조의 일관성이다.

---

### 5.3 Phase 3: Asia Corporate Living Infrastructure

Phase 3의 기술 목표는 AI Powered Living Platform 구축이다.

이 단계에서는 한국에서 검증한 모델을 아시아 주요 도시로 확장하고, AI 기반 운영 자동화를 고도화한다.

핵심 목표는 다음과 같다.

* AI Customer Agent
* AI Operation Assistant
* AI Matching Engine
* AI Revenue Optimization
* AI Quality Monitoring
* Partner Dashboard
* Multi-city Data Structure
* Enterprise Housing Portal
* Localization System
* Cross-city Inventory Management

이 단계에서 NMD는 단순 홈페이지나 운영툴이 아니라, Corporate Living Infrastructure를 운영하는 기술 플랫폼으로 발전한다.

---

### 5.4 Phase 4: Global Mobility Housing Partner

Phase 4의 기술 목표는 Global Mobility Housing Platform 구축이다.

이 단계에서 NMD는 글로벌 기업이 여러 도시의 직원 주거를 관리할 수 있는 파트너가 된다.

핵심 목표는 다음과 같다.

* Global Enterprise Account
* Cross-city Housing Matching
* Global Mobility Housing Portal
* AI Personalization
* Predictive Demand Analysis
* Partner Network OS
* Multi-language Customer Experience
* Global Reporting Dashboard
* API-based Platform Architecture

NMD는 기업이 인재를 새로운 도시로 이동시킬 때 신뢰하고 이용할 수 있는 Global Mobility Housing Partner로 성장한다.

---

## 6. Website Strategy

NMD Website는 단순한 회사 소개 페이지가 아니다.

NMD Website는 첫 번째 Product Platform이다.

NMD Website는 브랜드 신뢰, 고객 확보, 상품 탐색, 기업 문의, 글로벌 SEO, AI 검색 대응의 중심이다.

---

### 6.1 Primary Goals

NMD Website의 핵심 목표는 다음과 같다.

* Brand Trust
* Customer Acquisition
* Global SEO
* GEO / AIO 대응
* Lead Generation
* Property Discovery
* Corporate Request
* Resident Inquiry
* Partner Acquisition
* Content Asset Building

Website는 단순히 예쁘게 보이는 페이지가 아니라, NMD의 첫 번째 데이터 수집 채널이다.

---

### 6.2 Core Functions

NMD Website의 핵심 기능은 다음과 같다.

* Brand Introduction
* Product Introduction
* NMD Nest Page
* NMD Residence Page
* Property Search
* Property Detail Page
* Corporate Request Form
* Resident Inquiry Form
* Partner Inquiry Form
* Insight / Guide Content
* City / District Guide
* Living Guide
* AI Assistant
* Multi-language Experience

---

### 6.3 Property Discovery

NMD Website는 고객이 자신의 상황에 맞는 공간을 찾을 수 있어야 한다.

필터 기준은 다음을 고려한다.

* Product Line
* NMD Nest / NMD Residence
* Business District
* District
* Subway / Transportation
* Budget
* Stay Period
* Move-in Date
* Room Type
* Furnished Status
* Corporate Contract Suitability
* Foreigner Friendly Status
* Resident Type Fit
* Korea Housing Trust Availability

초기에는 모든 필터를 구현할 필요는 없다.

그러나 데이터 구조는 향후 확장을 고려해 설계해야 한다.

---

### 6.4 SEO / GEO / AIO Strategy

NMD는 초기부터 검색과 AI 인용 가능성을 고려해야 한다.

콘텐츠 전략은 다음을 포함한다.

* Corporate Housing in Seoul
* Furnished Living in Seoul
* Expat Housing in Seoul
* Seoul Business District Housing
* GBD / YBD / CBD Living Guide
* Foreigner Housing Guide Korea
* Korea Lease Guide for Foreigners
* Residence Registration Housing Guide
* Corporate Housing for Global Teams
* NMD Nest / NMD Residence Product Pages

SEO는 단기 트래픽을 위한 블로그가 아니라, 기업 고객과 글로벌 고객이 NMD를 신뢰하게 만드는 지식 자산이다.

GEO / AIO 대응을 위해 문서는 구조화된 제목, 명확한 답변, FAQ, Schema, 내부 링크 구조를 갖춰야 한다.

---

## 7. CMS Strategy

NMD는 모든 데이터를 구조화한다.

CMS는 단순 콘텐츠 관리 도구가 아니라, NMD의 초기 데이터베이스 역할을 한다.

---

### 7.1 CMS Manages Property Data

Property Data는 다음을 포함한다.

* Location
* Building
* Unit
* Size
* Type
* Price
* Deposit
* Monthly Fee
* Availability
* Product Line
* Furnished Status
* Corporate Suitability
* Foreigner Friendly Status
* Korea Housing Trust Notes

---

### 7.2 CMS Manages Product Data

Product Data는 다음을 포함한다.

* NMD Nest
* NMD Residence
* Product Positioning
* Customer Fit
* Space Type
* Furnished Standard
* Operation Standard
* Resident Experience Standard
* Trust Standard

NMD Collection은 초기 전략에서 제외한다.

향후 명확한 고객 문제와 수요 데이터가 확인될 때 재검토한다.

---

### 7.3 CMS Manages Content Data

Content Data는 다음을 포함한다.

* City Guide
* District Guide
* Living Guide
* Corporate Housing Insights
* Furnished Living Guide
* Expat Housing Guide
* Korea Housing Trust Guide
* FAQ
* Customer Education Content

콘텐츠는 단순 홍보글이 아니라, 고객 신뢰와 검색 유입을 만드는 자산이다.

---

### 7.4 CMS Goal

CMS의 목표는 다음과 같다.

## Content and property data become reusable assets.

한 번 입력한 데이터는 다음 용도로 재사용되어야 한다.

* 홈페이지 노출
* 검색 필터
* 고객 추천
* 기업 제안서
* AI Summary
* SEO 콘텐츠
* 내부 운영
* KPI 분석
* AI Matching

---

## 8. Admin System Strategy

Admin은 내부 운영팀의 Command Center이다.

Admin System은 직원이 코드를 수정하지 않고도 상품 등록, 문의 관리, 입주 관리, 유지보수 관리, 파트너 관리, 기업 고객 관리를 수행할 수 있어야 한다.

---

### 8.1 Property Management

Property Management 기능은 다음과 같다.

* Add Units
* Update Availability
* Manage Photos
* Manage Pricing
* Manage Product Line
* Manage Furnished Items
* Manage Korea Housing Trust Notes
* Manage Operation Status
* Manage Visibility
* Manage Similar Units

---

### 8.2 Customer Management

Customer Management 기능은 다음과 같다.

* Leads
* Requests
* Buyer / User 구분
* Corporate / Individual 구분
* Company Information
* Resident Information
* Preferred Location
* Budget
* Stay Period
* Communication History
* Proposal History
* Contract Status

---

### 8.3 Operation Management

Operation Management 기능은 다음과 같다.

* Tasks
* Move-in Checklist
* Move-out Checklist
* Cleaning Status
* Maintenance Tickets
* Issue History
* Partner Assigned
* Staff Assigned
* Inspection Date
* Quality Score
* Customer Feedback

---

### 8.4 Partner Management

Partner Management 기능은 다음과 같다.

* Property Owner
* Building Owner
* Asset Manager
* Cleaning Partner
* Maintenance Partner
* Furniture / Appliance Partner
* Legal / Tax / Admin Expert Network
* Partner Quality Score
* Partner Response Time
* Partner Issue History
* Partnership Status

---

### 8.5 Analytics

Analytics 기능은 다음과 같다.

* Occupancy
* Revenue
* Margin
* Performance
* Customer Metrics
* Corporate Metrics
* Resident Experience Metrics
* Partner Metrics
* Maintenance Metrics
* Korea Housing Trust Metrics
* Units per Employee
* Automation Rate

---

## 9. Data Strategy

NMD의 장기 경쟁력은 데이터에서 나온다.

데이터는 운영 기록이 아니라, NMD의 장기 기업가치다.

---

### 9.1 Property Data

공간 정보 데이터이다.

* Location
* Building
* Unit
* Product Line
* Size
* Type
* Price
* Availability
* Furnished Status
* Quality Score

Property Data는 검색, 추천, 운영, 수익성 분석의 기반이다.

---

### 9.2 Customer Data

수요와 선호 데이터이다.

* Customer Type
* Buyer / User
* Corporate / Individual
* Preferred Location
* Budget
* Stay Period
* Language
* Resident Type
* Inquiry Source
* Conversion Status

Customer Data는 매칭, 영업, 콘텐츠 전략의 기반이다.

---

### 9.3 Corporate Data

기업 고객 데이터이다.

* Company Name
* Department
* Buyer Contact
* Employee Housing Needs
* Contract History
* Proposal History
* Repeat Potential
* Reporting Needs
* Corporate Account Status

Corporate Data는 NMD의 반복 수요와 장기 매출의 기반이다.

---

### 9.4 Resident Data

최종 거주자 경험 데이터이다.

* Resident Type
* Nationality
* Preferred Language
* Move-in Requirement
* Stay Period
* Maintenance Requests
* Satisfaction Notes
* Renewal Potential
* Feedback

Resident Data는 Resident-Centered Experience 개선의 기반이다.

개인정보는 관련 법령과 내부 기준에 따라 필요한 범위에서만 수집하고 관리해야 한다.

---

### 9.5 Operation Data

운영 효율 데이터이다.

* Occupancy
* Vacancy Days
* Cleaning Turnaround
* Maintenance Response
* Issue History
* Complaint Rate
* Renewal Rate
* Operation Cost
* Units per Employee

Operation Data는 비용 절감, 품질 개선, 자동화 우선순위 판단의 기반이다.

---

### 9.6 Korea Housing Trust Data

한국 주거 신뢰 데이터이다.

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

Korea Housing Trust Data는 기업 고객과 외국인 Resident의 신뢰를 높이는 핵심 데이터다.

---

### 9.7 Market Data

가격과 지역 분석 데이터이다.

* District Price
* Business District Demand
* Comparable Units
* Seasonality
* Corporate Demand Signals
* Expat Demand Signals
* Competitor Pricing
* Occupancy Trends

Market Data는 가격 전략, 공급 확장, 지역 확장 판단의 기반이다.

---

### 9.8 Financial Data

수익성 데이터이다.

* Revenue
* Expense
* Margin
* Setup Cost
* Payback Period
* Maintenance Cost
* Cleaning Cost
* Partner Revenue Share
* Unit Economics

Financial Data는 Business Plan, IR 자료, 투자 판단, 확장 전략의 기반이다.

---

## 10. AI Agent Strategy

AI Agent는 NMD 직원의 대체가 아니라 확장 도구이다.

AI는 반복 업무를 줄이고, 응답 속도를 높이며, 데이터 기반 의사결정을 보조한다.

---

### 10.1 Customer AI Agent

Customer AI Agent의 역할은 고객 경험 개선이다.

주요 기능은 다음과 같다.

* 고객 문의 대응 초안 작성
* 고객 니즈 파악
* 고객 유형 분류
* 공간 추천
* 유사 상품 추천
* 생활 안내
* FAQ 응답
* 다국어 응대 보조
* 입주 안내 초안 작성

Customer AI Agent는 고객에게 빠른 응답을 제공하되, 계약, 가격, 행정, 법률 관련 중요한 내용은 운영자가 확인해야 한다.

---

### 10.2 Operation AI Agent

Operation AI Agent의 역할은 운영 효율 향상이다.

주요 기능은 다음과 같다.

* 문제 분류
* Maintenance Ticket 생성
* 업무 배정 보조
* 일정 관리
* 체크리스트 확인
* 데이터 누락 감지
* SOP 기반 응답 초안 작성
* 파트너 작업 요청 초안 작성
* 운영 이슈 요약

Operation AI Agent는 직원의 반복 업무를 줄이고 운영 일관성을 높인다.

---

### 10.3 Management AI Agent

Management AI Agent의 역할은 CEO & Manager Assistant이다.

주요 기능은 다음과 같다.

* KPI Monitoring
* Business Analysis
* Report Generation
* Decision Support
* Unit Economics Analysis
* Occupancy Analysis
* Partner Performance Analysis
* Maintenance Cost Analysis
* Customer Feedback Summary
* Expansion Recommendation

Management AI Agent는 NMD가 데이터 기반 운영 회사로 성장하는 데 중요한 역할을 한다.

---

### 10.4 AI Matching Engine

AI Matching Engine은 고객 조건과 NMD Property를 연결하는 추천 시스템이다.

추천 기준은 다음과 같다.

* 고객 유형
* 기업 고객 여부
* Resident 유형
* 예산
* 희망 지역
* 업무지구
* 체류 기간
* 입주 가능일
* 가족 동반 여부
* 외국인 친화성
* 법인 계약 필요 여부
* 전입신고 또는 거소등록 관련 필요 여부
* NMD Nest / NMD Residence 적합성
* Korea Housing Trust Score

AI Matching Engine은 단순 가격 매칭이 아니라, 고객 상황과 운영 가능성을 함께 고려해야 한다.

---

### 10.5 AI Quality Monitoring

AI Quality Monitoring은 운영 품질을 감지하는 시스템이다.

감지 대상은 다음과 같다.

* 고객 불만 증가
* 유지보수 지연
* 청소 품질 저하
* 사진과 실제 상태 불일치 가능성
* 파트너 응대 지연
* 반복 이슈 발생
* 공실 위험 증가
* Korea Housing Trust 데이터 누락
* 상품 정보 누락

AI는 운영 리스크를 미리 감지하고, 운영팀이 빠르게 대응할 수 있도록 보조해야 한다.

---

## 11. AI Guardrails

AI는 강력한 도구이지만, NMD의 신뢰를 훼손하지 않도록 명확한 가드레일이 필요하다.

---

### 11.1 Legal / Admin Boundary

AI는 계약, 법률, 세무, 행정 판단을 단정해서는 안 된다.

특히 다음 영역은 주의가 필요하다.

* 법인 임대차
* 전입신고
* 거소등록
* 비자 관련 주거 증빙
* 확정일자
* 전세권 설정
* 보증금 반환 안정성
* 보증금 반환보증보험
* 세무 처리
* 법률 분쟁

AI는 확인된 데이터와 내부 기준에 따라 안내 초안을 작성할 수 있으나, 최종 안내는 운영자 또는 전문가 검토를 거쳐야 한다.

---

### 11.2 Accuracy Boundary

AI는 확인되지 않은 정보를 고객에게 제공해서는 안 된다.

주의할 점은 다음과 같다.

* 실제 가격과 다른 가격 안내 금지
* 입주 가능일 오안내 금지
* 사진과 실제 상태가 다른 설명 금지
* 불확실한 행정 가능성 단정 금지
* 보증금 안정성 과장 금지
* 계약 조건 임의 해석 금지

AI는 신뢰를 높이는 도구여야 하며, 불확실성을 숨기는 도구가 되어서는 안 된다.

---

### 11.3 Privacy Boundary

AI는 개인정보를 불필요하게 수집하거나 노출해서는 안 된다.

관리 기준은 다음과 같다.

* 필요한 정보만 수집
* 민감 정보 최소화
* 내부 메모와 외부 답변 구분
* 고객 데이터 접근 권한 관리
* 기업 고객 정보 보호
* Resident 개인정보 보호
* 파트너 정보 보호

개인정보와 기업 정보는 NMD의 신뢰 자산이므로 엄격하게 관리해야 한다.

---

### 11.4 Brand Boundary

AI가 생성하는 문구는 NMD 브랜드 기준을 따라야 한다.

기준은 다음과 같다.

* 과장하지 않는다.
* 불확실한 내용을 단정하지 않는다.
* 전문적이고 명확하게 설명한다.
* 기업 고객과 최종 거주자 모두가 이해할 수 있어야 한다.
* NMD를 단순 숙박업체, Airbnb 호스트, 일반 임대관리 회사처럼 표현하지 않는다.
* Corporate Housing & Furnished Living Platform으로 설명한다.

---

## 12. Automation Strategy

반복 업무는 사람이 아니라 시스템이 처리하도록 설계해야 한다.

모든 수동 업무는 장기적으로 다음 흐름을 따라야 한다.

Manual Work
↓
Process
↓
System
↓
Automation
↓
Platform

---

### 12.1 Automation Targets

자동화 대상은 다음과 같다.

* Customer Inquiry
* Customer Segmentation
* Property Matching
* Similar Unit Recommendation
* Listing Status Update
* Contract Task Checklist
* Payment / Settlement Reminder
* Check-in Guide
* Move-in Checklist
* Maintenance Request
* Customer Support
* Corporate Reporting
* Partner Task Assignment
* KPI Dashboard
* Data Missing Alert
* Korea Housing Trust Data Alert

---

### 12.2 Automation Priority

초기 자동화 우선순위는 다음과 같다.

1. 문의 분류
2. 고객 조건 정리
3. 상품 추천
4. 입주 안내
5. 유지보수 요청 분류
6. 체크리스트 관리
7. 기업 보고 자료 생성
8. 데이터 누락 확인
9. 운영 KPI 집계
10. Korea Housing Trust 정보 누락 감지

자동화는 고객 경험과 운영 품질을 해치지 않는 범위에서 단계적으로 도입한다.

---

## 13. Global & Localization Strategy

NMD는 초기부터 글로벌 구조를 고려한다.

단순히 한국어 홈페이지를 영어로 번역하는 것만으로는 충분하지 않다.

글로벌 고객은 다른 검색 의도, 다른 불안, 다른 정보 요구를 가진다.

---

### 13.1 Website Architecture

다국어 구조는 다음을 고려한다.

* /ko
* /en
* /ja
* /zh

초기에는 한국어와 영어를 우선할 수 있다.

향후 일본어, 중국어, 기타 언어로 확장할 수 있다.

---

### 13.2 Localization Includes

Localization은 단순 번역이 아니다.

포함 요소는 다음과 같다.

* Language
* Search Intent
* Customer Needs
* Culture
* Contract Understanding
* Housing Administration Explanation
* Location Explanation
* Corporate Decision Process
* Resident Anxiety Reduction

예를 들어 외국인 고객에게는 전입신고, 거소등록, 비자 관련 주거 증빙, 보증금 구조, 관리비 구조가 매우 중요한 설명 요소가 될 수 있다.

---

### 13.3 Global SEO / GEO / AIO

글로벌 고객을 위해 다음 콘텐츠를 고려한다.

* Corporate Housing in Seoul
* Furnished Apartments in Seoul
* Expat Housing in Korea
* Seoul Business District Housing
* GBD Housing Guide
* YBD Housing Guide
* CBD Housing Guide
* Korea Lease Guide for Foreigners
* Residence Registration Housing Guide
* Company Housing in Seoul
* Long-stay Furnished Living in Seoul

NMD는 검색엔진뿐 아니라 AI 검색과 AI 인용 환경에서도 신뢰 가능한 정보 출처가 되어야 한다.

---

## 14. Recommended Initial Tech Stack

초기 NMD는 빠른 검증을 위해 가벼운 기술 스택으로 시작한다.

---

### 14.1 Website / CMS

초기 추천 구성은 다음과 같다.

* WordPress
* GeneratePress
* GenerateBlocks
* ACF
* CPT
* SEO Plugin
* Form Plugin
* Analytics Tool
* Security Plugin
* Backup System

이 구성은 빠르게 시작하고, 콘텐츠와 상품 데이터를 구조화하기에 적합하다.

---

### 14.2 Data / CRM

초기에는 다음 도구를 조합할 수 있다.

* WordPress Admin
* ACF
* Google Sheets
* Airtable
* Notion
* CRM Tool
* Form-to-CRM Automation

중요한 것은 도구 자체가 아니라 데이터 구조의 일관성이다.

---

### 14.3 Automation / AI

초기 AI 활용은 다음 방식으로 시작할 수 있다.

* AI 기반 상품 설명 생성
* AI Summary 생성
* 고객 문의 답변 초안
* 기업 제안서 초안
* 입주 안내문 초안
* 유지보수 요청 분류
* 운영 체크리스트 생성
* KPI 요약

초기 AI는 내부 운영 보조부터 시작하는 것이 안전하다.

고객 직접 응대 AI는 데이터와 가드레일이 충분히 준비된 후 단계적으로 도입한다.

---

## 15. Recommended Data Model

초기 데이터 모델은 다음 Entity를 기준으로 설계한다.

---

### 15.1 Core Entities

* Property
* Building
* District
* Product Line
* Company
* Buyer
* Resident
* Partner
* Inquiry
* Contract
* Operation Task
* Maintenance Ticket
* Content
* FAQ

---

### 15.2 Property Relationships

Property는 다음 Entity와 연결된다.

* Building
* District
* Product Line
* Partner
* Inquiry
* Contract
* Resident
* Maintenance Ticket
* Operation Task
* Korea Housing Trust Data

---

### 15.3 Customer Relationships

Customer는 Buyer와 Resident로 구분한다.

Buyer는 다음과 연결된다.

* Company
* Inquiry
* Proposal
* Contract
* Reporting

Resident는 다음과 연결된다.

* Property
* Move-in
* Maintenance
* Feedback
* Move-out
* Renewal

이 구조는 B2B와 B2B2C를 동시에 관리하기 위한 핵심이다.

---

## 16. Technology Metrics

NMD는 기술 성과를 다음 지표로 관리한다.

---

### 16.1 Website Metrics

* Organic Traffic
* Global SEO Traffic
* Corporate Landing Page Conversion
* Property Page Conversion
* Inquiry Conversion Rate
* Search Usage Rate
* Multi-language Page Performance

---

### 16.2 CMS Metrics

* Property Data Completeness
* Korea Housing Trust Data Completeness
* Product Line Accuracy
* Photo Completeness
* SEO Field Completeness
* AI Summary Coverage
* Content Publishing Frequency

---

### 16.3 Admin Metrics

* ACF Input Accuracy
* Admin Workflow Completion Rate
* Data Missing Rate
* Property Update Frequency
* Inquiry Response Time
* Staff Task Completion Rate

---

### 16.4 AI Metrics

* AI-assisted Response Rate
* AI Recommendation Accuracy
* AI Summary Usage Rate
* AI Error Rate
* Human Review Rate
* Automation Rate
* Data Missing Alert Accuracy

---

### 16.5 Operation Platform Metrics

* Units per Employee
* Operation Cost per Unit
* Maintenance Ticket Resolution Time
* Cleaning Turnaround Time
* Partner Response Time
* Corporate Reporting Time
* Resident Satisfaction Tracking Rate

---

## 17. Technology Risks

NMD는 기술 리스크를 사전에 관리해야 한다.

---

### 17.1 Overbuilding Risk

초기부터 너무 복잡한 자체 플랫폼을 만들면 시간과 비용이 과도하게 들어간다.

관리 방법은 다음과 같다.

* MVP 우선
* WordPress 기반 빠른 검증
* 고객 수요와 운영 문제 확인 후 개발
* 필요한 기능만 단계적으로 확장
* 데이터 구조는 미리 설계하되, 개발은 순차 진행

---

### 17.2 Data Fragmentation Risk

데이터가 여러 도구에 흩어지면 운영이 어려워진다.

관리 방법은 다음과 같다.

* 공통 데이터 구조 정의
* Property ID 기준 관리
* Company / Resident / Partner 구조 분리
* ACF 필드명 표준화
* 데이터 입력 규칙 문서화
* 향후 마이그레이션 고려

---

### 17.3 AI Hallucination Risk

AI가 부정확한 내용을 생성하면 고객 신뢰가 훼손된다.

관리 방법은 다음과 같다.

* AI Guardrails 설정
* 고객 노출 전 운영자 검토
* Korea Housing Trust 관련 내용은 단정 금지
* 확인된 데이터 기반 답변
* 내부 메모와 외부 답변 구분

---

### 17.4 Privacy Risk

고객, 기업, Resident, Partner 데이터는 신중히 관리해야 한다.

관리 방법은 다음과 같다.

* 최소한의 정보 수집
* 접근 권한 관리
* 민감 정보 분리
* 개인정보 보호 기준 수립
* 외부 AI 도구 입력 시 주의
* 내부 데이터 공유 기준 마련

---

### 17.5 Tool Dependency Risk

특정 플러그인이나 외부 도구에 과도하게 의존하면 장기 확장에 문제가 될 수 있다.

관리 방법은 다음과 같다.

* 데이터 export 가능성 확인
* 표준 필드 구조 유지
* 핵심 데이터는 독립적으로 관리
* 플러그인 교체 가능성 고려
* 장기적으로 자체 시스템 전환 가능성 확보

---

## 18. Technology Principles

모든 기술 의사결정 기준은 다음과 같다.

1. Does it improve customer experience?
2. Does it improve Resident-Centered Experience?
3. Does it increase corporate trust?
4. Does it reduce manual operation?
5. Does it create reusable data?
6. Can it scale to 10,000 units?
7. Does it support NMD Nest and NMD Residence?
8. Does it support Partner First Supply?
9. Does it strengthen Korea Housing Trust Layer?
10. Does it improve operation quality?
11. Does it support AI automation?
12. Does it strengthen NMD platform value?

기술은 유행을 따라 도입하지 않는다.

기술은 고객 경험, 운영 효율, 데이터 자산, 플랫폼 가치에 기여할 때 도입한다.

---

## 19. AI First Company Principle

NMD는 AI를 추가 기능으로 보지 않는다.

AI는 회사 운영 방식의 일부이다.

모든 업무는 다음 질문을 통과해야 한다.

Can AI improve this?
Can this become automated?
Can this become a system?
Can this create reusable data?
Can this improve customer trust?

다만 AI First는 Humanless를 의미하지 않는다.

NMD에서 AI는 운영자를 대체하는 것이 아니라, 운영자가 더 빠르고 정확하게 일할 수 있도록 돕는 도구다.

특히 Korea Housing Trust Layer, 계약, 법률, 세무, 행정 관련 영역에서는 AI가 단정하지 않고, 전문가와 운영자의 확인을 보조하는 방식으로 사용되어야 한다.

---

## 20. AI Collaboration Guideline

본 문서는 AI 협업을 위한 Technology & AI Strategy 기준 문서로 사용된다.

AI 도구는 NMD 관련 기술 전략, 홈페이지 구조, CPT/ACF 설계, 관리자 UX, CRM, AI Agent, 자동화, 데이터 구조, Business Plan, IR 자료를 작성할 때 다음 기준을 따른다.

* NMD의 공식 브랜드명은 Nomad Ground이다.
* NMD는 로고 및 Short Name으로 사용한다.
* 공식 슬로건은 Corporate Housing & Furnished Living이다.
* NMD는 Corporate Housing & Furnished Living Platform이다.
* NMD는 단순 부동산 홈페이지, 숙박 예약 사이트, Airbnb 호스트, 일반 임대관리 회사로 설명하지 않는다.
* 수요 전략은 Corporate First Demand이다.
* 서비스 경험은 Resident-Centered Experience를 기준으로 설계한다.
* 공급 전략은 Partner First Supply이다.
* 초기 상품 구조는 NMD Nest와 NMD Residence 투트랙이다.
* NMD Collection은 초기 전략에서 제외한다.
* Phase 명칭은 00_Index.md 기준을 따른다.
* 초기 기술 스택은 WordPress + GeneratePress + ACF 기반을 고려한다.
* 초기 목표는 완성형 자체 플랫폼이 아니라 빠른 검증과 구조화 데이터 축적이다.
* 장기 목표는 NMD Living OS 구축이다.
* 기술 구조는 Admin System, CRM, Partner System, AI Matching System, ERP-like Internal Platform으로 확장 가능해야 한다.
* Korea Housing Trust Layer는 별도 데이터 구조로 다룬다.
* 전입신고, 거소등록, 비자 관련 주거 증빙, 전세권, 보증보험, 보증금 안정성 등은 AI가 단정하지 않고 확인된 데이터와 전문가 검토를 기반으로 안내한다.
* AI는 운영자를 대체하는 것이 아니라, 반복 업무를 줄이고 의사결정을 보조하는 도구로 다룬다.
* 문서는 한글 중심으로 작성하되 핵심 전략 용어는 영어로 고정한다.
* 모든 기술 의사결정은 Product, Operation, Resident Experience, Corporate Trust, Partner Network, Korea Housing Trust Layer, Data, AI 확장성과 연결되어야 한다.

---

## 21. Core Statement

NMD의 기술 목표는 또 하나의 부동산 홈페이지를 만드는 것이 아니다.

NMD는 Corporate Housing & Furnished Living 운영을 위한 Living OS를 만든다.

Software manages complexity.
Data creates intelligence.
AI improves decisions.
Technology enables scale.
Trust creates the brand.

NMD는 WordPress 기반의 초기 Product Platform에서 시작하여, Admin System, CRM, Operation Platform, AI Matching System, Partner Dashboard, NMD Living OS로 확장한다.

NMD의 장기 기술 가치는 보유한 코드의 양이 아니라, 기업 고객, 최종 거주자, 공급 파트너, 운영팀을 하나의 신뢰 가능한 시스템으로 연결하는 능력에서 나온다.
