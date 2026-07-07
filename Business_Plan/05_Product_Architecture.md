# NMD Product Architecture v1.0

## Document Purpose

This document defines NMD's product structure, classification system, data architecture, and technology foundation.

It provides product context for:

- Website Development
- CMS Design
- AI Agent Development
- Database Structure
- Operations
- Future Scaling

---

# 1. Product Philosophy

## Simple Products.
## Flexible Services.

NMD does not create many product categories.

The physical space should be simple.

The customer purpose and service layer should be flexible.

---

Traditional Real Estate:

Location

+

Size

+

Price


NMD:

Product Type

+

Service Purpose

+

Customer Need

+

Operation Standard

---

# 2. Product System Overview


NMD Product Layer:


---

Example:

NMD Residence

+

Executive Collection

+

Corporate Stay

+

Housekeeping Option


---

# 3. Core Products


# Product 01

# NMD Nest

## Definition

Compact Furnished Living Product


Nest represents:

A personal base for modern professionals.

---

## Property Type

Suitable spaces:

- Studio
- One-room
- 1 Bedroom
- Compact Officetel
- Small Urban Housing

---

## Target Size

Typical:

- Compact units
- Efficient layouts
- Single residents

---

## Primary Users

- Individual professionals
- Corporate employees
- Foreign workers
- Project workers
- Medical visitors

---

## Core Value

Move-in ready.

Efficient.

Connected to business districts.

---

## Brand Meaning

Nest means:

A safe personal base.

A starting point in a new city.

---

# Product 02

# NMD Residence


## Definition

Full-size Furnished Living Product


Residence represents:

A complete home experience.

---

## Property Type

Suitable spaces:

- Apartments
- Multi-room homes
- Villas
- Family residences

---

## Primary Users

- Expat families
- Executives
- Long-term employees
- Professionals

---

## Core Value

Comfort.

Stability.

Long-term living.

---

# 4. Collection Layer


Collections represent quality level.

Collections are not based only on size.


---

# Executive Collection

## Definition

Premium curated housing collection.

---

## Qualification Factors

Properties selected by:

- Location
- Building quality
- Interior level
- Privacy
- Service capability

---

## Possible Property Types

Can include:

- Premium Nest
- Premium Residence
- Luxury Apartments

---

## Target Users

- Executives
- VIP customers
- Senior professionals
- Foreign leaders

---

# 5. Service Layer


Services define WHY customers use NMD.


A single property can support multiple services.


---

# Corporate Stay

Primary service category.


Users:

- Corporate employees
- Project teams
- Business travelers


Needs:

- Easy contracts
- Furnished housing
- Employee support


---

# Medical Stay

Users:

- International patients
- Recovery guests


Needs:

- Comfortable stay
- Short/mid-term flexibility
- Location convenience


---

# Executive Stay

Users:

- Executives
- VIP residents


Needs:

- Premium experience
- Privacy
- Service quality


---

# Long Stay

Users:

- Individuals
- Professionals


Needs:

- Flexible furnished living


---

# 6. Option Layer


Options customize customer experience.

---

## Living Options

Examples:

- Furniture Package
- Utility Included
- Internet
- Parking
- Pet Friendly


---

## Service Options

Examples:

- Housekeeping
- Airport Pickup
- Move-in Support
- Maintenance Support


---

## Foreign Support Options

Examples:

- Foreigner Registration Support
- Contract Guidance
- Living Information


---

# 7. Property Data Structure

For CMS / Database Design


Each property should include:


---

# Basic Information

- Property ID
- Product Type
- Collection Type
- Location
- Address
- District
- Status


---

# Space Information

- Room Type
- Area
- Bedrooms
- Bathrooms
- Floor
- Building Type


---

# Pricing Information

- Monthly Price
- Deposit
- Management Fee
- Utility Policy


---

# Availability

- Available Date
- Minimum Stay
- Maximum Stay


---

# Contract Information

Critical for AI Matching:

- Corporate Contract Available
- Residential Lease Available
- Foreigner Available
- Registration Available
- Deposit Requirements


---

# Options

- Furniture
- Appliances
- Parking
- Cleaning
- Internet


---

# Media

Priority:

1. Interior
2. Bedroom
3. Kitchen
4. Bathroom
5. View
6. Building Exterior


NMD sells living experience first.

Not buildings.

---

# 8. AI Matching Architecture


AI Agent should not simply filter properties.

AI should understand customer intent.

---

## Step 1

Identify Customer


Customer Type:

- Corporate
- Individual
- Foreigner


---

## Step 2

Identify Purpose


Purpose:

- Corporate
- Medical
- Executive
- Long Stay


---

## Step 3

Identify Requirements


Inputs:

- Location
- Budget
- Move-in date
- Duration
- Family size


---

## Step 4

Identify Contract Needs


Important:

- Lease Agreement
- Corporate Contract
- Registration
- Foreigner Requirements


---

## Step 5

Recommend Solution


Output:

Product

+

Service

+

Property


Example:

NMD Nest

Corporate Stay

Gangnam


---

# 9. Website Architecture


Main Navigation:

- Homes
- Corporate
- Services
- About NMD
- Contact


---

Property Filters:

Primary:

- Location
- Product Type
- Move-in Date
- Budget


Secondary:

- Service Type
- Options
- Contract Requirements


---

# 10. Admin Dashboard Requirements


Future CMS should manage:


## Inventory

- Property data
- Availability
- Pricing


---

## Customers

- Leads
- Requirements
- Status


---

## Operations

- Check-in
- Check-out
- Maintenance
- Cleaning


---

## Partners

- Owners
- Operators
- Contracts


---

# 11. Product Expansion Rule


NMD should avoid creating unnecessary product names.

New categories should be created only when:

- Customer is different
- Operation is different
- Pricing model is different


---

# Current Product Strategy


Products:

- Nest
- Residence


Collection:

- Executive Collection


Services:

- Corporate Stay
- Medical Stay
- Long Stay


---

# One Sentence Summary

NMD products define the space, services define the purpose, and technology connects customers to the right living solution.
