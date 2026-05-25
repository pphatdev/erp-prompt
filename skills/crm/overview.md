# Feature: Customer Relationship Management (CRM)

## Overview
The Customer Relationship Management (CRM) module is the hub of customer acquisition, relationship management, and sales funnel intelligence. Decoupled from Sales Billing/Invoicing, CRM focuses entirely on pre-sale lead capturing, opportunity stages progression, account contact mapping, activity logging, and sales forecasting analytics.

---

## Core Components

### 1. Lead Management
* **Prospect Capture:** Capture raw leads from self-service portal submissions, manual imports, or API integrations.
* **Lead Sourcing:** Track effectiveness per acquisition channel (e.g., website, campaign, partner referral, cold outreach).
* **Validation & Qualification:** Qualify leads to filter out low-intent prospects. A qualified lead converts to a formal **Opportunity** and/or creates/associates with an active **Customer** profile.

### 2. Opportunity (Deal) Pipeline
* **Visual Kanban Progression:** Track qualified deals across logical phases (e.g., Discovery, Proposal, Negotiation, Won, Lost) via an interactive Kanban board.
* **Sales Probability Mapping:** Model win probabilities per stage to calculate accurate weighted forecasts.
* **Deal Size Valuation:** Log estimated deal values and projected close dates to drive cashflow expectations.
* **Win/Loss Analysis:** Mandate feedback logging on deal closures to capture insights into product-market fit or competitive pressures.

### 3. Accounts & Contacts Hub
* **Unified 360-Degree View:** Aggregated interaction timelines, active quotes, related orders, open tasks, and files for every customer.
* **B2B Structural Model:**
  * **Accounts (Customers):** The primary organization or corporate entity (e.g., `Acme Corp`), owning tenant credentials if `customer_type = tenant`.
  * **Contacts:** The actual human decision-makers (e.g., `Jane Doe, VP Procurement`) linked to the primary corporate account.

### 4. Activity & Interaction Logging
* **Polymorphic Timeline:** Log activities (calls, emails, virtual meetings, physical tasks) against Leads, Opportunities, or Accounts.
* **Collaboration Notes:** Share internal comments, tag team members, and record minutes of meetings.
* **Follow-up Reminders:** Create scheduled follow-up tasks to ensure sales touchpoints are never missed.

### 5. Sales Forecasting & Analytics
* **Funnel Conversion Rates:** Track transition rates between pipeline stages.
* **Pipeline Velocity:** Measure the average number of days a deal spends in each stage.
* **Revenue Projections:** View visual revenue forecasting bars based on weighted pipeline probability.
