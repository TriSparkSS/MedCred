# Revantage CRM — Complete Project Documentation

**Version:** 1.0  
**Date:** June 2026  
**Product:** Revantage Healthcare Credentialing Portal  
**Prepared for:** Developers, Testers, Project Managers, and Clients

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Who Uses This System](#2-who-uses-this-system)
3. [Technology Overview](#3-technology-overview)
4. [Getting Started (Installation)](#4-getting-started-installation)
5. [Login and Access](#5-login-and-access)
6. [System Architecture](#6-system-architecture)
7. [Admin Portal — Complete Module Guide](#7-admin-portal--complete-module-guide)
8. [Provider Portal](#8-provider-portal)
9. [Practice Portal](#9-practice-portal)
10. [Credentialing Case Workflow](#10-credentialing-case-workflow)
11. [Documents and Checklists](#11-documents-and-checklists)
12. [Tasks and Kanban Board](#12-tasks-and-kanban-board)
13. [Email Center and Templates](#13-email-center-and-templates)
14. [SLA, Reminders, and Delay Ownership](#14-sla-reminders-and-delay-ownership)
15. [Reports and Analytics](#15-reports-and-analytics)
16. [Bulk Import](#16-bulk-import)
17. [API Access (Mobile / External)](#17-api-access-mobile--external)
18. [Roles and Permissions](#18-roles-and-permissions)
19. [Database Overview](#19-database-overview)
20. [File Uploads and Storage](#20-file-uploads-and-storage)
21. [Scheduled Jobs and Automation](#21-scheduled-jobs-and-automation)
22. [Testing Guide](#22-testing-guide)
23. [Deployment Checklist](#23-deployment-checklist)
24. [Glossary](#24-glossary)
25. [Support and Maintenance](#25-support-and-maintenance)

---

## 1. Introduction

### 1.1 What Is Revantage CRM?

Revantage CRM is a **healthcare credentialing management system**. It helps a credentialing team (Revantage HBS) track when doctors (providers) and medical groups (practices) get approved to work with insurance companies (payers).

The system replaces spreadsheets and email chains with one central place to:

- Store provider and practice information
- Track each credentialing application from start to finish
- Collect and store required documents
- Assign tasks and send reminders
- Report on delays, productivity, and compliance

### 1.2 Business Goal

When a provider joins a practice or a practice wants to bill a new payer, a **credentialing application** must be submitted. This process can take weeks or months. Revantage CRM makes that process visible, measurable, and auditable.

### 1.3 What This Document Covers

This guide walks through **every module** of the system in simple, step-by-step language. You do not need to be a Laravel expert to understand how the product works. Developers will also find technical details where helpful.

---

## 2. Who Uses This System

| User Type | Portal | What They Do |
|-----------|--------|--------------|
| **Admin / Credentialing Staff** | Admin portal (`/sp/login`) | Manage all data, cases, documents, emails, reports |
| **Provider (Doctor)** | Provider portal (`/portal/login`) | View application status, upload requested documents |
| **Practice (Medical Group)** | Practice portal (`/portal/login`) | View practice applications, upload docs, see linked providers |
| **Billing Staff** | (Future admin view) | Read-only provider readiness (permission defined, UI optional) |
| **API Consumer** | REST API | Mobile apps or integrations for provider case/document data |

---

## 3. Technology Overview

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.3, Laravel 13 |
| Frontend | Livewire 4, Blade templates, Bootstrap-based admin theme |
| CSS Build | Tailwind CSS 4, Vite |
| Database | SQLite (development) or MySQL (production) |
| Authentication | Session-based (Admin + User guards), Laravel Sanctum for API tokens |
| Permissions | Spatie Laravel Permission (portal users) |
| Notifications | PHP Flasher (SweetAlert-style toasts) |
| Email | Laravel Mail |

### 3.1 Project Folder Structure (High Level)

```
app/
  Livewire/       → All UI pages (Admin, Provider, Practice, Auth)
  Models/         → Database entities
  Services/       → Business logic (dashboard, SLA, email, reports)
  Http/           → Controllers, Middleware
database/
  migrations/     → Database schema
  seeders/        → Default data (admin, roles, master data)
resources/views/  → Blade templates
routes/           → web.php, api.php, console.php
docs/             → Project documentation (this file)
public/           → Web assets and uploaded files (via storage link)
```

---

## 4. Getting Started (Installation)

### 4.1 Requirements

- PHP 8.3 or higher
- Composer
- Node.js and npm
- SQLite or MySQL database

### 4.2 Step-by-Step Setup

**Step 1 — Install PHP dependencies**

```bash
composer install
```

**Step 2 — Environment file**

```bash
cp .env.example .env
php artisan key:generate
```

**Step 3 — Database**

For SQLite (default):

```bash
touch database/database.sqlite
php artisan migrate
```

For MySQL, set `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`, then run migrate.

**Step 4 — Seed default data**

```bash
php artisan db:seed
```

This creates:
- Super admin account
- Roles (provider, practice, billing, manager, executive)
- Portal permissions
- Master data (statuses, case types, document types, etc.)

**Step 5 — Optional Phase 2 data (email templates + SLA rules)**

```bash
php artisan db:seed --class=Phase2Seeder
```

**Step 6 — File storage link**

```bash
php artisan storage:link
```

**Step 7 — Frontend assets**

```bash
npm install
npm run build
```

**Step 8 — Run the application**

```bash
php artisan serve
```

Or use Laravel Herd if installed (project path: `E:\Herd\revantage-crm`).

### 4.3 Default Login After Seeding

| Portal | URL | Username / Email | Password |
|--------|-----|------------------|----------|
| Admin | `/sp/login` | `superadmin` or `admin@hrm.com` | `112233` |

Portal users (provider/practice) are created through the Admin UI or CSV import.

---

## 5. Login and Access

### 5.1 Admin Login

1. Open `/sp/login`
2. Enter admin email or username and password
3. You are redirected to `/admin/dashboard`

Admin users are stored in the `admins` table. They do **not** use Spatie roles.

### 5.2 Provider and Practice Login (Unified Portal)

1. Open `/portal/login` (also `/provider/login` or `/practice/login`)
2. Enter the email and password for a portal user
3. The system checks the user's role:
   - **Provider role** → `/provider/dashboard`
   - **Practice role** → `/practice/dashboard`

If the account is not linked to a provider or practice profile, login is rejected.

### 5.3 Logout

- Admin: User menu in the top-right header → Logout
- Provider/Practice: Logout button in the portal header

### 5.4 Session Security

- Sessions are stored in the database by default
- CSRF protection is enabled on all forms
- Portal routes require authentication middleware before any page loads

---

## 6. System Architecture

### 6.1 Three Portals, One Database

All portals share the same database. Data entered in Admin is visible in Provider/Practice portals (where permissions allow).

```
                    ┌─────────────────┐
                    │   Admin Portal   │
                    │  (Full Control)  │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        ┌──────────┐   ┌──────────┐   ┌──────────┐
        │ Provider │   │ Practice │   │ REST API │
        │  Portal  │   │  Portal  │   │ (Sanctum)│
        └──────────┘   └──────────┘   └──────────┘
              │              │              │
              └──────────────┴──────────────┘
                             │
                    ┌────────▼────────┐
                    │    Database     │
                    └─────────────────┘
```

### 6.2 Main Data Relationships (Simple View)

- A **User** can be linked to one **Provider** OR one **Practice**
- A **Provider** can belong to many **Practices** (and vice versa)
- A **Credentialing Case** links: Provider + Practice + Payer + Status
- Each **Case** has a **Document Checklist**, **Tasks**, **Emails**, and **SLA Timers**
- **Documents** can belong to a provider, practice, or specific case

### 6.3 Case Number Format

Every credentialing application gets a unique ID like:

**APP-2026-0001**

This is generated automatically when a new case is created.

---

## 7. Admin Portal — Complete Module Guide

Access all admin modules from the left sidebar after logging in at `/sp/login`.

### 7.1 Dashboard (`/admin/dashboard`)

**Purpose:** High-level view of credentialing operations.

**What you see:**
- Total active applications
- Cases waiting on provider/practice
- Cases waiting on payer
- Approved cases
- Overdue follow-ups
- Expiring documents
- Delay ownership breakdown (chart)
- Recent activity feed

**Who uses it:** Managers, executives, daily operations staff.

---

### 7.2 Providers Module

| Page | URL | Purpose |
|------|-----|---------|
| List | `/admin/providers` | Search and filter all providers |
| Create | `/admin/providers/create` | Add new provider + portal login |
| Details | `/admin/providers/{id}/show` | View full profile, cases, documents |
| Edit | `/admin/providers/{id}/edit` | Update credentials and contact info |

**Key provider fields:**
- NPI, CAQH ID, specialty
- State license, DEA, CDS
- PECOS enrollment, malpractice insurance
- Board certification, licensed states
- Work history

**When a provider is created:**
- A portal **User** account is created
- The `provider` role is assigned
- Provider can log in at `/portal/login`

---

### 7.3 Practices Module

| Page | URL | Purpose |
|------|-----|---------|
| List | `/admin/practices` | All medical groups/practices |
| Create | `/admin/practices/create` | New practice + portal login |
| Details | `/admin/practices/{id}/show` | Profile, contacts, locations |
| Edit | `/admin/practices/{id}/edit` | Update practice information |

**Practice includes:**
- Legal name, DBA, EIN/TIN, Group NPI
- Taxonomy code, phone, email, website
- Bank information (for billing setup)
- **Contacts** — office manager, billing contact, etc.
- **Locations** — service addresses with NPI per location

**When a practice is created:**
- A portal **User** account is created
- The `practice` role is assigned

---

### 7.4 Provider–Practice Assignment (`/admin/provider-practices`)

**Purpose:** Link doctors to the practices where they work.

**Steps:**
1. Open Provider–Practice Assignment page
2. Select a provider and a practice
3. Set primary flag and start/end dates if needed
4. Save

This link is required before creating credentialing cases for that provider at that practice.

---

### 7.5 Payers Module

| Page | URL | Purpose |
|------|-----|---------|
| List | `/admin/payers` | Insurance companies / payers |
| Create | `/admin/payers/create` | Add new payer |
| Edit | `/admin/payers/{id}/edit` | Update payer + document requirements |

**Payer document requirements:**
When editing a payer, you define which documents are required per state. When a new credentialing case is opened for that payer, the checklist is built automatically from these rules.

---

### 7.6 Credentialing Cases (`/admin/credentials`)

| Page | URL | Purpose |
|------|-----|---------|
| List | `/admin/credentials` | All applications with filters |
| Create | `/admin/credentials/create` | Open new credentialing case |
| Packet | `/admin/credentials/{case}/packet` | Download ZIP of all case documents |

**Creating a new case — step by step:**

1. Go to **Credentials → Create**
2. Select **Provider**, **Practice**, **Payer**, and **Location** (if applicable)
3. Choose **Case Type** (New Application, Renewal, Recredentialing, etc.)
4. Set **Priority** and **Assigned Admin**
5. Save — system assigns case number and initial status
6. Document checklist is seeded from payer requirements
7. SLA timers start based on current status category

**Case detail view includes tabs for:**
- Overview (status, dates, delay owner)
- Document checklist
- Activity timeline
- Tasks
- Emails
- SLA information
- Delay override (manual)

---

### 7.7 Documents Library (`/admin/documents`)

**Purpose:** Central repository for all uploaded files.

**Features:**
- Upload new documents with type, provider, practice, case linkage
- Version history (each re-upload creates a new version)
- Expiry date tracking
- Filter by expiring/expired documents

**Supported file types:** PDF, DOC, DOCX, JPG, PNG (max 10MB in portal; admin may allow similar types)

---

### 7.8 Tasks — Kanban Board (`/admin/tasks/kanban`)

**Purpose:** Track follow-up work items.

**Columns:**
- Overdue
- Due Today
- Upcoming
- Completed

**Tasks can be:**
- Created manually by admin staff
- Auto-created by SLA engine (e.g., payer follow-up reminder)

Each task links to a credentialing case and can be assigned to an admin user.

---

### 7.9 Email Center (`/admin/emails`)

**Purpose:** Send and track credentialing-related emails.

**Features:**
- Send templated emails to providers/practices
- Log inbound emails and link them to cases
- Import email attachments as documents
- View send statistics

**Templates** are managed under Master Data → Email Templates.

---

### 7.10 Reports (`/admin/reports`)

**Purpose:** Export data for management and compliance.

**Available CSV reports:**

| Report | Description |
|--------|-------------|
| Open Applications | All active credentialing cases |
| Document Compliance | Checklist completion status |
| Case Aging | How long cases have been open |
| Expiring Documents | Documents nearing expiry |
| Productivity by Executive | Cases handled per assigned admin |
| Turnaround by Payer | Average time per payer |
| Upcoming Recredentialing | Cases due for renewal |

Click **Export** next to each report to download CSV.

---

### 7.11 Analytics — Productivity Dashboard (`/admin/analytics/productivity`)

**Purpose:** Management view of team performance.

**Includes:**
- Cases per executive
- Average turnaround time
- Payer-level statistics
- Upcoming recredentialing pipeline

---

### 7.12 Bulk Import (`/admin/imports/bulk`)

**Purpose:** Import many providers at once from CSV.

**Steps:**
1. Download or prepare CSV with columns: name, email, NPI, specialty, etc.
2. Upload file on Bulk Import page
3. Review success/error report per row
4. Successfully imported providers receive portal accounts

Default password for imported users (if not in CSV): `ChangeMe123!`

---

### 7.13 Settings

| Page | URL | Purpose |
|------|-----|---------|
| General Settings | `/admin/settings` | Application settings |
| Specialties | `/admin/settings/specialties` | Medical specialty list |

---

### 7.14 Master Data Management

Master data controls dropdown values and business rules across the system.

| Page | URL | What It Controls |
|------|-----|------------------|
| Statuses | `/admin/master/statuses` | Case workflow stages + dashboard categories |
| Case Types | `/admin/master/case-types` | New Application, Renewal, etc. |
| Delay Owners | `/admin/master/delay-owners` | Who is causing delay (Team, Provider, Payer) |
| Priorities | `/admin/master/priorities` | Critical, High, Medium, Low |
| Document Types | `/admin/master/document-types` | W-9, License, DEA, etc. |
| Email Templates | `/admin/master/email-templates` | Merge-field email templates |

**Important:** Status records include a **dashboard category** (e.g., in progress, approved, provider action) and a default **delay owner**. Changing statuses affects dashboards, SLA rules, and reports.

---

## 8. Provider Portal

**Base URL:** `/provider/*`  
**Login:** `/portal/login` with provider role account

### 8.1 Provider Permissions

| Permission | Access |
|------------|--------|
| Dashboard | View summary stats |
| Cases | View own application status (read-only) |
| Documents | View requests and upload files |
| Profile | View own profile (read-only) |

Providers **cannot** change case status, assign tasks, or see internal admin notes.

### 8.2 Provider Dashboard

Shows:
- Active applications count
- Actions needed from provider
- Approved count
- Expiring documents
- Checklist items still pending
- Outstanding document requests
- Recent applications list

### 8.3 My Applications

Table of all credentialing cases for this provider:
- Case number, payer, status
- Checklist progress (e.g., 3/5 — 60%)
- Intake date

### 8.4 Upload Center

**Step-by-step upload:**
1. Select the application (case)
2. Choose document type (optional)
3. Enter a title
4. Choose file (PDF, DOC, JPG — max 10MB)
5. Click Upload

The system:
- Saves the document
- Marks matching checklist items as received
- Logs activity on the case

### 8.5 My Profile

Read-only view of NPI, specialty, email, and status.

---

## 9. Practice Portal

**Base URL:** `/practice/*`  
**Login:** `/portal/login` with practice role account

### 9.1 Practice Permissions

Practice users have all provider permissions **plus:**
- View linked providers
- View practice locations

### 9.2 Practice Dashboard

Practice-wide stats:
- Active applications across all linked providers
- Outstanding documents (with provider name)
- Linked provider count
- Recent applications

### 9.3 Applications

All credentialing cases where `practice_id` matches this practice. Shows provider name, payer, status, checklist progress.

### 9.4 Upload Center

Same as provider portal, but cases are scoped to the practice. Practice staff can upload documents on behalf of cases at their practice.

### 9.5 Linked Providers

List of all providers assigned to this practice with NPI, specialty, status, and case count.

### 9.6 Practice Profile

Read-only view of legal name, contact info, locations, and contacts.

---

## 10. Credentialing Case Workflow

### 10.1 Typical Lifecycle

```
Intake → Document Collection → Internal Review → Submitted to Payer
  → Payer Review → Approved (or Denied/Withdrawn)
```

Statuses are configurable in Master Data but commonly include:

| Status | Meaning |
|--------|---------|
| Not Started | Case created, work not begun |
| In Progress | Active work by Revantage team |
| Waiting on Provider | Provider must submit documents/info |
| Waiting on Practice | Practice must submit documents/info |
| Submitted to Payer | Application sent to insurance company |
| Payer Review | Payer is processing |
| Approved | Credentialing complete |
| Denied / Withdrawn | Closed without approval |

### 10.2 Status Change Rules

When an admin changes case status:
1. Status history record is saved
2. Activity log entry is created
3. Delay owner may auto-update based on status
4. SLA timers are synced for the new status category

### 10.3 Delay Ownership

Every active case has a **delay owner** — who is currently blocking progress:

- Revantage Team
- Provider / Practice
- Payer
- No Delay / Closed

Admins can manually override delay owner with a reason (audited).

### 10.4 Assigned Admin

Each case can be assigned to a credentialing executive. Used for workload tracking and productivity reports.

---

## 11. Documents and Checklists

### 11.1 How Checklists Work

1. Admin creates a case for Provider + Practice + Payer
2. System reads **payer document requirements** for that state
3. Checklist items are created on the case
4. When a document is uploaded (admin or portal), matching items are marked **received**
5. Checklist completion percentage appears on case lists and portals

### 11.2 Document Versioning

- Each document can have multiple versions
- Only one version is marked **current**
- Previous versions are kept for audit purposes

### 11.3 Expiry Tracking

Documents can have expiry dates (e.g., malpractice insurance, license). The dashboard and reports flag documents expiring within 30 days.

### 11.4 Credentialing Packet (ZIP Download)

From a case in admin, click **Download Packet** to get a ZIP file containing all documents linked to that case and provider.

---

## 12. Tasks and Kanban Board

### 12.1 Creating a Task (Admin)

1. Open Tasks → Kanban or create from a case
2. Enter title, description, due date
3. Assign to an admin user
4. Link to credentialing case
5. Set priority

### 12.2 Task States

Tasks appear in kanban columns based on due date and completion status. Overdue tasks appear in dashboard notification counts.

### 12.3 SLA-Generated Tasks

The SLA engine can automatically create tasks (e.g., "Follow up with payer — case APP-2026-0042") when rules trigger.

---

## 13. Email Center and Templates

### 13.1 Sending Email

1. Open Email Center
2. Choose a case (optional but recommended)
3. Select a template or write custom content
4. Enter recipient
5. Send

Email is logged in `email_messages` with sender, timestamp, and case link.

### 13.2 Templates and Merge Fields

Templates support placeholders such as:

- `{{case_number}}`
- Provider name, payer name, due dates (as configured in template)

Manage templates at `/admin/master/email-templates`.

### 13.3 Inbound Email Logging

Staff can log received emails and attach them to cases. Attachments can be imported as formal documents.

---

## 14. SLA, Reminders, and Delay Ownership

### 14.1 What Is SLA?

SLA (Service Level Agreement) timers track how long a case stays in a given state. When a timer expires, the system takes automated action.

### 14.2 Default SLA Rules (Phase2Seeder)

| Rule | Days | Action |
|------|------|--------|
| Internal review | 2 business days | Reminder |
| Provider document request | 3 days | Reminder email |
| Provider document request | 7 days | Second reminder |
| Escalation | 10 days | Escalate to manager |
| Payer follow-up | 7 days | Create follow-up task |

### 14.3 Daily SLA Check

Command: `php artisan credentialing:run-sla-checks`  
Schedule: Daily at 8:00 AM (configured in `routes/console.php`)

**What it does:**
- Finds due SLA timers
- Sends reminder emails
- Creates escalation tasks
- Shifts delay ownership when configured
- Logs all actions

### 14.4 Business Days

SLA calculations skip weekends and configurable holidays (see `config/credentialing.php`).

---

## 15. Reports and Analytics

### 15.1 When to Use Each Report

| Need | Report |
|------|--------|
| How many open cases? | Open Applications |
| Who has missing documents? | Document Compliance |
| Which cases are stuck longest? | Case Aging |
| Which licenses expire soon? | Expiring Documents |
| How is each executive performing? | Productivity by Executive |
| Which payers are slowest? | Turnaround by Payer |
| What renewals are coming? | Upcoming Recredentialing |

### 15.2 Productivity Dashboard

Real-time charts and tables for management meetings. Complements CSV exports with live data.

### 15.3 Global Search (Admin Header)

Search from the admin header bar:
- Provider name or NPI
- Case / application ID
- Payer name

Results open the relevant detail page.

---

## 16. Bulk Import

### 16.1 CSV Format (Typical Columns)

- `name` — Full name
- `email` — Portal login email
- `password` — Optional (defaults to ChangeMe123!)
- `npi` — National Provider Identifier
- `specialty` — Specialty name (must exist in system)
- Additional fields as supported by import service

### 16.2 Import Results

After upload, the system shows:
- Total rows processed
- Success count
- Per-row errors (duplicate email, invalid specialty, etc.)

Import batches are stored for audit in `import_batches` table.

---

## 17. API Access (Mobile / External)

**Base URL:** `/api/v1`

### 17.1 Authentication

**Step 1 — Log in via web session** (provider portal login in browser)

**Step 2 — Create token:**

```
POST /api/v1/auth/token
Body: { "device_name": "My Phone" }
```

Returns a Sanctum bearer token.

**Step 3 — Use token on requests:**

```
Authorization: Bearer {token}
```

### 17.2 Available Endpoints

| Method | Endpoint | Returns |
|--------|----------|---------|
| GET | `/api/v1/provider/cases` | Provider's cases with status and checklist |
| GET | `/api/v1/provider/documents` | Provider's documents with type and expiry |

Practice API endpoints can be added in future phases.

---

## 18. Roles and Permissions

### 18.1 Admin Users

- Stored in `admins` table
- Use `admin` authentication guard
- Full access to admin portal (no Spatie permission checks currently)

### 18.2 Portal Roles (Spatie)

| Role | Description |
|------|-------------|
| `provider` | Individual doctor portal access |
| `practice` | Medical group portal access |
| `billing` | Read-only billing readiness (permission only) |
| `manager` | Reserved for future admin RBAC |
| `executive` | Reserved for future admin RBAC |

### 18.3 Permission List

| Permission | Provider | Practice |
|------------|:--------:|:--------:|
| portal.dashboard.view | ✓ | ✓ |
| portal.cases.view | ✓ | ✓ |
| portal.documents.view | ✓ | ✓ |
| portal.documents.upload | ✓ | ✓ |
| portal.profile.view | ✓ | ✓ |
| portal.providers.view | | ✓ |
| portal.locations.view | | ✓ |

Seed permissions: `php artisan db:seed --class=PermissionSeeder`

---

## 19. Database Overview

### 19.1 Core Tables

| Table | Purpose |
|-------|---------|
| admins | Admin users |
| users | Portal users |
| provider_details | Provider profiles |
| practices | Practice profiles |
| provider_practice | Provider–practice links |
| payers | Insurance companies |
| credentialing_cases | Main application records |
| case_document_items | Per-case checklist |
| documents / document_versions | File storage metadata |
| tasks | Follow-up tasks |
| email_messages | Sent/received emails |
| notification_templates | Email templates |
| sla_rules / case_sla_timers | Automation timers |
| statuses, case_types, priorities, document_types | Master data |

### 19.2 Running Migrations

```bash
php artisan migrate
```

All schema changes are in `database/migrations/` with dated filenames.

---

## 20. File Uploads and Storage

### 20.1 Storage Locations

| Type | Disk | Path |
|------|------|------|
| Document files | public | `storage/app/public/documents/{id}/` |
| Practice files | public | `storage/app/public/practice-documents/` |
| Credentialing packet ZIP | local (temp) | Deleted after download |

### 20.2 Public Access

Run once per server:

```bash
php artisan storage:link
```

Files are then accessible at `/storage/...`

### 20.3 Upload Limits (Portal)

- Max size: 10 MB
- Types: PDF, DOC, DOCX, JPG, JPEG, PNG

---

## 21. Scheduled Jobs and Automation

### 21.1 Scheduler Setup (Production)

Add to server crontab:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 21.2 Registered Schedule

| Command | Frequency | Purpose |
|---------|-----------|---------|
| credentialing:run-sla-checks | Daily 8:00 AM | Process SLA timers |

### 21.3 Queue Worker (Optional)

For async email sending in future:

```bash
php artisan queue:listen
```

---

## 22. Testing Guide

### 22.1 Manual Test Scenarios

**Admin flow:**
1. Login as admin
2. Create payer with document requirements
3. Create provider and practice
4. Link provider to practice
5. Create credentialing case
6. Upload document → verify checklist updates
7. Change status → verify activity log
8. Export a report CSV

**Provider flow:**
1. Login at portal with provider account
2. Verify dashboard shows correct case counts
3. Upload document for a case
4. Verify checklist progress updates in admin

**Practice flow:**
1. Login with practice account
2. View linked providers
3. Upload document for practice case
4. Verify admin sees upload in activity log

**SLA flow:**
1. Seed Phase2Seeder
2. Run `php artisan credentialing:run-sla-checks`
3. Verify reminders/tasks created for due cases

### 22.2 Automated Tests

```bash
php artisan test
```

Test files are in the `tests/` directory using Pest PHP.

---

## 23. Deployment Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure MySQL database in `.env`
- [ ] Run migrations and seeders
- [ ] Run `php artisan storage:link`
- [ ] Configure mail settings (`MAIL_*` in `.env`)
- [ ] Set `CREDENTIALING_MAIL_FROM` for outbound emails
- [ ] Set up cron for `schedule:run`
- [ ] Build frontend: `npm run build`
- [ ] Set secure `APP_URL`
- [ ] Change default admin password
- [ ] Enable HTTPS

---

## 24. Glossary

| Term | Definition |
|------|------------|
| **Credentialing** | Process of verifying a provider's qualifications with a payer |
| **Payer** | Insurance company or health plan |
| **NPI** | National Provider Identifier (10-digit ID) |
| **CAQH** | Council for Affordable Quality Healthcare — common provider data registry |
| **PECOS** | Medicare enrollment system |
| **DEA** | Drug Enforcement Administration number (prescribing controlled substances) |
| **Recredentialing** | Re-verification required periodically by payers |
| **Checklist** | Required documents list for a specific case |
| **SLA** | Service Level Agreement — timed follow-up rules |
| **Delay Owner** | Party currently responsible for case delay |

---

## 25. Support and Maintenance

### 25.1 Common Commands

| Command | Purpose |
|---------|---------|
| `php artisan migrate` | Apply database changes |
| `php artisan db:seed` | Load default data |
| `php artisan credentialing:run-sla-checks` | Run SLA processing manually |
| `php artisan cache:clear` | Clear application cache |
| `php artisan config:clear` | Clear config cache |
| `php artisan route:list` | View all registered routes |

### 25.2 Business Requirements Document

Original BRD: `docs/Credentialing_Portal_Requirements_Revantage_v2.pdf`

### 25.3 Version History

| Version | Date | Notes |
|---------|------|-------|
| 1.0 | June 2026 | Initial complete project documentation |

---

**End of Document**

*Revantage CRM — Healthcare Credentialing Management System*  
*© 2026 Revantage HBS. Internal use.*
