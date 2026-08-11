# Umrany Platform Overview

Source: Umrany System Analysis & Software Requirements Specification, Chapter 1 ("Project Overview") and Chapter 2 ("Platform Architecture").

## What Umrany Is

Umrany is a Construction Technology (ConTech) platform designed to digitally transform the construction industry by bringing all construction stakeholders into one unified ecosystem. It connects project owners with trusted service providers while offering a complete suite of digital solutions covering project procurement, material purchasing, business management, and decision-making.

Rather than operating as a traditional marketplace, Umrany provides an integrated environment where construction projects, building materials, business operations, and artificial intelligence services coexist within a single platform. This lets construction professionals manage their entire business lifecycle without relying on multiple disconnected systems.

The platform serves individuals, companies, contractors, suppliers, engineering offices, consultants, manufacturers, and other construction-related businesses through specialized products tailored to the industry's requirements.

Umrany is designed as a scalable Software-as-a-Service (SaaS) platform, built to support future expansion across multiple countries, multiple currencies, and multiple languages while maintaining a flexible architecture capable of accommodating new products and services over time.

## Vision

To become the leading digital ecosystem for the construction industry across the Middle East and beyond by providing intelligent digital solutions that simplify collaboration, improve transparency, and accelerate business growth for every construction stakeholder.

## Mission

To modernize the construction industry by replacing fragmented traditional workflows with a unified digital platform that enables project owners and service providers to discover opportunities, collaborate efficiently, manage their businesses, and leverage Artificial Intelligence to make better decisions.

## Problem Statement

Despite significant technological advancement in many industries, construction continues to depend heavily on:

- Manual processes.
- Fragmented communication channels.
- Disconnected software solutions.
- Inefficient procurement methods.

The practical effects of this fragmentation:

- Project owners struggle to identify qualified service providers, compare quotations fairly, and locate trusted suppliers.
- Service providers face challenges reaching new clients, managing ongoing projects, and organizing daily business operations efficiently.
- Construction material procurement is fragmented, requiring customers to search across multiple suppliers with limited pricing transparency and inconsistent purchasing experiences.

These challenges increase project costs, reduce transparency, create unnecessary delays, and negatively impact the overall efficiency of the construction industry.

## Solution Overview

Umrany addresses these problems by introducing a centralized digital ecosystem that connects all stakeholders through specialized business products designed specifically for the construction sector. It covers the major operational needs of the industry through four interconnected products:

- The **Project-Based Platform** enables project owners to publish construction projects and receive competitive quotations from qualified service providers through a transparent bidding process.
- The **E-Commerce Platform** provides a specialized multi-vendor marketplace dedicated to construction materials, allowing customers to purchase products directly from verified suppliers.
- The **ERP Platform** offers lightweight business management tools for service providers, letting them manage projects, clients, quotations, invoices, expenses, and customer relationships through a simple cloud-based system.
- **Artificial Intelligence services** enhance multiple platform operations by automating BOQ analysis, matching projects with suitable providers, and comparing quotations using intelligent algorithms.

Together, these products establish a complete digital ecosystem that simplifies construction procurement, improves operational efficiency, and creates new business opportunities for all participants.

## Architecture of the Business: Four Products + Shared Services

Umrany's business architecture is organized around four independent business products, a centralized administration portal, and a layer of shared platform services used by all products:

**The four core products** (each operates independently but remains fully integrated within the same platform):

1. Project-Based Platform (procurement/bidding).
2. Construction E-Commerce (multi-vendor marketplace).
3. ERP Platform (business management for service providers).
4. Artificial Intelligence Services (cross-platform intelligent automation).

**Administration Portal** — the centralized control center that manages every configurable aspect of the ecosystem: users, providers, subscriptions, payments, verification, reports, platform settings, and more.

**Shared Platform Services** — common infrastructure used by every product, including authentication and authorization, subscription management, payment processing, wallet management, notification delivery, internal chat, audit logging, reporting, file management, search, verification, API integration, government integration, multilingual support, multi-currency support, SEO management, analytics, and centralized configuration.

A defining architectural principle: the entire platform is centered around **one user account**. A single user can act as a project owner, service provider, supplier, or customer simultaneously without creating multiple accounts — the features available to each user depend on their profile configuration, subscriptions, verification status, and assigned permissions rather than on a fixed account type. See `docs/business/personas.md` for details.

This modular approach allows new products, modules, and services to be introduced over time while maintaining a consistent user experience and centralized management, without disrupting the rest of the ecosystem.

## Target Users

- **Project Owners** — individuals or organizations seeking construction-related services. They can publish projects, receive quotations from qualified service providers, communicate with providers through the platform, compare offers, purchase construction materials from the marketplace, and manage all their requests through a centralized dashboard.
- **Service Providers** — contractors, suppliers, engineering offices, consultants, manufacturers, and other specialized construction businesses. They can showcase their companies, submit quotations, sell products through the marketplace, subscribe to business services, manage their operations using the integrated ERP, and expand their market reach.
- **Platform Administrators** — responsible for operating and supervising the entire platform. They manage users, subscriptions, financial operations, verification processes, platform content, reports, system settings, and overall platform performance through a comprehensive administration portal.

See `docs/business/personas.md` for the full actor breakdown (Guest, Registered User, Project Owner, Service Provider, E-Commerce Customer, Supplier, ERP User, Admin User, Super Admin, External System).

## Project Scope (First Full Release)

The first full release of Umrany includes four fully integrated business products designed to operate as one digital ecosystem:

- **Project-Based Platform** — publish projects, receive quotations, communicate through integrated chat, compare offers, select providers.
- **Construction E-Commerce Platform** — verified suppliers sell construction materials through a specialized multi-vendor marketplace supporting product variants, online payments, supplier wallets, settlement management, order tracking, and customer reviews.
- **ERP Platform** — lightweight business management: project management, CRM, quotations, invoices, expense management, business reporting.
- **Artificial Intelligence services** — BOQ Analysis, Intelligent Provider Matching, and AI Offer Comparison, automating repetitive tasks and improving decision-making.

The platform also includes a comprehensive **Administration Portal** covering users, providers, subscriptions, payments, verification, reports, dynamic pages, SEO management, notifications, AI configuration, platform settings, and role-based permissions.

The platform is designed to support responsive web applications, mobile applications, multiple languages, multiple currencies, and future expansion into additional countries while maintaining a unified and scalable architecture.

For the order these four products (plus Admin) actually get built in, and why, see `docs/business/roadmap.md`.
