# SOC 2 developer evidence package — Revantage CRM

**Application:** Revantage CRM (healthcare credentialing)  
**Repository:** https://github.com/TriSparkSS/Rev-Credentialing  
**Local workspace:** Laravel Herd project `revantage-crm`  
**Document date:** 18 September 2026  
**HEAD (at time of this write-up):** `651718fa6d398b529b148016fba6117e7bcbc080` (`main`)  
**Scope:** Developer SDLC evidence for environment segregation, testing, review, and deployment. No passwords, API keys, tokens, or patient data are included.

This package records **what the repository and git history actually show**. Items that require a live server screenshot or a named owner are listed as **collector actions** rather than invented controls.

---

## Index

| Label | Status in this package |
| --- | --- |
| Environment Segregation | Documented from config/docs. Staging URL not in repo. Screenshots still needed. |
| Security and Dependency Testing | SAST tool not in repo. One-off Composer + npm audit run on 18 Sep 2026. |
| Integration Testing | Pest Feature/Unit suites exist; run locally (`php artisan test`). No CI gate. |
| Code Review and Approval | GitHub Pull Requests page is empty. Independent PR approval is **not** evidenced. Actual workflow documented below. |
| Production Deployment | Checklist in project docs. IIS-oriented `web.config` present. Dated deploy log still needed. |

---

## Environment Segregation

### What is maintained separately

| Environment | How it is separated | Evidence in repo |
| --- | --- | --- |
| Development | Developer machines (Laravel Herd or `php artisan serve`). `APP_ENV=local`, SQLite by default. URL such as `http://revantage-crm.test`. | [`.env.example`](../.env.example), [README.md](../README.md) |
| Testing | Pest/PHPUnit forces `APP_ENV=testing`, in-memory SQLite, array cache/session, array mailer. Does not use the developer SQLite file or production SQL Server. | [`phpunit.xml`](../phpunit.xml) |
| Staging / UAT | **Not defined in this repository.** No `APP_ENV=staging`, no staging URL, no staging compose/host docs. | Unavailable |
| Production | Intended `APP_ENV=production`, `APP_DEBUG=false`, SQL Server (`sqlsrv`) or MySQL, HTTPS, IIS rewrite via `web.config`. Commit `2409120` (14 Aug 2026, Nitisha Goyal) message: `server setup`. | [docs/Revantage_CRM_Project_Documentation.md](Revantage_CRM_Project_Documentation.md) §23, [`web.config`](../web.config), [`.htaccess`](../.htaccess) |

Laravel also defaults `env()` to `production` if `APP_ENV` is missing ([`config/app.php`](../config/app.php)). That is a fail-closed default, not proof of three hosted environments.

### Compensating controls if staging does not exist

Use this wording only if it matches operations:

1. Feature work is done on **local copies**, not on the live production database.  
2. Automated tests run against an **isolated in-memory SQLite** database (`DB_DATABASE=:memory:`).  
3. Production uses a **different database engine/host and secrets** than development (SQL Server vs local SQLite).  
4. Production `.env` is not committed (`.gitignore`). Graph and DB secrets are environment-only.  
5. Deployment checklist requires `APP_DEBUG=false`, HTTPS, and changing seed/default admin passwords.

This is **two hosted/runtime tracks (local + production) plus an automated test runtime**, not a three-environment SDLC.

### Collector screenshots (attach after this page)

Label each file **Environment Segregation**. Show application/repo name and the OS date. Mask secrets.

1. **Development:** Browser on the Herd URL + `APP_ENV=local` with values redacted.  
2. **Testing:** Terminal output of `php artisan test` and a snippet of `phpunit.xml` showing `APP_ENV=testing`.  
3. **Staging:** Only if a separate URL and database exist. Otherwise omit and keep the gap note above.  
4. **Production:** Live URL with `APP_ENV=production` / `APP_DEBUG=false` redacted (hosting panel or SSH `php artisan env`, not a dump of `.env`).

---

## Security and Dependency Testing

### Static application security testing (SAST)

**Currently unavailable.** This repository has no PHPStan/Larastan, Psalm, Semgrep, SonarQube, or GitHub CodeQL workflow (no `.github/workflows`).

**Gap note:** Code quality relies on Laravel conventions, Laravel Pint (formatter, not a SAST tool), and human review. There is no stored SAST report to attach.

### Dependency / third-party library scan (one-off, 18 September 2026)

Commands run from the project root (no application code was changed):

- `composer audit --format=plain`  
- `npm audit --omit=dev`

**npm (production frontend dependencies):** `found 0 vulnerabilities`.

**Composer:** **28 advisories across 5 packages** (Composer exits non-zero when advisories exist). Packages reported:

| Package | Role | Notes for auditors |
| --- | --- | --- |
| `livewire/livewire` | Production UI | Medium: DOM-based XSS in client-side state (CVE-2026-81887); affected range includes 4.x through 4.3.3. |
| `guzzlehttp/guzzle` | HTTP client (transitive/direct) | Multiple medium/high cookie, redirect, and proxy issues; upgrade path cited in advisories (e.g. 7.15.x). |
| `guzzlehttp/psr7` | HTTP messages | Medium host-confusion / CRLF issues. |
| `league/commonmark` | Markdown (Laravel) | Multiple high DoS/XSS-class advisories; upgrade toward 2.9.x / 2.10.x per advisories. |
| `dompdf/dompdf` | **require-dev** | Several medium/low SVG/path issues; not a production `require` in `composer.json`. |

**Gap note:** This was an **ad hoc** scan, not a scheduled CI job or ticketed remediation log. Recurring scans (Dependabot, `composer audit` in CI) are not configured.

**Collector:** Screenshot the Composer summary line and the npm `0 vulnerabilities` line with the repo folder name and date. Label **Security and Dependency Testing**. Do not paste secrets. Full CVE URLs are in Composer output if the auditor wants the raw log stored privately.

---

## Integration Testing

Automated tests use **Pest PHP** with Laravel’s HTTP/Livewire test harness. They hit routes, policies, and services against a throwaway SQLite database. That is the project’s **integration testing** evidence.

**How to run:** `composer test` or `php artisan test` ([`composer.json`](../composer.json) script `test`).

**Isolation:** [`phpunit.xml`](../phpunit.xml) sets `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `MAIL_MAILER=array`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`.

### Feature suites (application behavior)

| File | What it validates |
| --- | --- |
| `tests/Feature/AdminRbacAlignmentTest.php` | Role/permission matrix, practice scoping, billing cannot mutate tracker |
| `tests/Feature/AssignedWorkDashboardTest.php` | Admin assigned work vs other admins; provider open/closed cases |
| `tests/Feature/CredentialEscalationTest.php` | Escalate/de-escalate by role |
| `tests/Feature/EmailDashboardPageTest.php` | Compose modal send/fail behavior |
| `tests/Feature/EntityDocumentsSectionTest.php` | Provider/practice document upload to hub |
| `tests/Feature/FacilityFiltersAndZipLookupTest.php` | Practice/provider location filters and zip lookup |
| `tests/Feature/GraphMailboxSyncTest.php` | Microsoft Graph mailbox, attachments, HTML sanitizer (HTTP faked) |
| `tests/Feature/MailSettingsTest.php` | SMTP from-address resolution and Message-ID |
| `tests/Feature/ProviderLocationsAndCredentialsTest.php` | Location linking and multi-state licenses |
| `tests/Feature/ProviderMalpracticeCoverageTest.php` | Malpractice coverage fields |
| `tests/Feature/ReportPreviewAndEmailAttachmentTest.php` | Credentialing report filters, Excel export, email attachment download |
| `tests/Feature/SpreadsheetImportTest.php` | Practice/provider Excel import and templates |
| `tests/Feature/TaskBulkActionsTest.php` | Bulk assign/complete/follow-up and permission denial |
| `tests/Feature/ExampleTest.php` | HTTP 200 on `/` |
| `tests/Unit/ExampleTest.php` | Unit suite placeholder |

Manual UAT steps (login, document upload, SLA command) are described in project documentation §22. That is **not** a signed release checklist.

**Gap note:** Tests are executed on a **developer machine**. There is no GitHub Action (or other CI) that must pass before production deploy. Therefore “integration testing performed before deployment” is a **process claim** (developer ran Pest) unless you attach a dated test log next to a release.

**Collector:** Attach Pest summary (counts + duration) dated the same day as a release. Label **Integration Testing**.

---

## Code Review and Approval

### GitHub pull requests

Public Pull Requests UI for `TriSparkSS/Rev-Credentialing` showed the empty-state (“Welcome to pull requests”) on 18 September 2026. **No PR numbers, reviewers, or approvals are available to attach.**

Branches present locally: `main` (checked out, tracking `origin/main`), `ai-code`. Recent history on `main` is **direct commits**, predominantly author `greathimanshu` / `Himanshu kumar`. A second committer (`Nitisha Goyal`, `server setup`, 14 Aug 2026) and `TriSparkSS` appear in history. Merge commits exist (`Merge branch 'ai-code'…`) but that is not the same as a reviewed GitHub pull request with a non-author approver.

### Actual workflow (from repo evidence)

1. **Code development:** Local Laravel Herd / Git on `main` or `ai-code`.  
2. **Testing and validation:** Pest Feature tests plus manual checks in docs §22 (not gated by CI).  
3. **Code review:** **Not evidenced** as a required, independent GitHub review.  
4. **Approval:** **Not evidenced** (no PR approval, no ticket template in repo).  
5. **Production deployment:** Server files include IIS `web.config` and Apache `.htaccess`; a “server setup” commit exists. Host, deployer, and change ticket are **not** in the application repo.

**Gap note for SOC 2 CC8.1:** Independent review before production is **currently unavailable** as a system-enforced control. Do not submit this repository as proof of four-eyes merge. Compensating evidence (if true) would be: a manager email/ticket approving a dated release, or starting required PR reviews with a second GitHub user.

**Collector:** If any PR was opened in a private view we could not see, attach 2–3 PRs showing **another person** approved. Otherwise attach this section as the workflow statement. Label **Code Review and Approval**.

**Recent commits suitable for a “code changes” screenshot** (mask any PHI in diffs):

| Date | SHA | Author | Subject |
| --- | --- | --- | --- |
| 2026-09-18 | `651718f` | greathimanshu | feat: enhance Excel export with improved styling and color coding for reports |
| 2026-09-16 | `c16aa7b` | greathimanshu | feat: add Excel download functionality and update report email attachment |
| 2026-09-14 | `47f4cc2` | greathimanshu | Add feature tests for facility filters, provider locations, malpractice coverage, spreadsheet imports, and task bulk actions |

---

## Production Deployment

**Documented checklist** (project documentation §23): `APP_ENV=production`, `APP_DEBUG=false`, database configured, migrations, `storage:link`, mail, scheduler, `npm run build`, secure `APP_URL`, change default admin password, HTTPS.

**Technical hosting hints:** IIS URL rewrite to `public/` (`web.config`); optional Apache root `.htaccess` doing the same. Database target in `.env.example` comments: SQL Server for production.

**Currently unavailable in-repo:** release calendar, who can deploy, Forge/Plesk/FTP procedure, production URL, post-deploy smoke-test sign-off.

**Collector:** For one recent go-live, attach: date, who deployed, ticket ID if any, ticked §23 checklist, production homepage screenshot (no PHI). Label **Production Deployment**. Start a simple release log going forward if none exists.

---

## Controls unavailable (summary)

| Control | Why unavailable | Compensating / next step |
| --- | --- | --- |
| Dedicated staging environment | Not in config or docs | Local + isolated Pest DB + separate production secrets |
| SAST report | No SAST tool or CI | Pest + Pint; optional later PHPStan/CodeQL |
| Recurring dependency scanning | No Dependabot/CI audit | One-off Composer/npm audit on 18 Sep 2026; plan upgrades for Livewire/Guzzle/CommonMark |
| Independent PR approval | Empty GitHub PR list; pushes to `main` | Document solo/pair process honestly; enable required reviews if policy requires it |
| CI must-pass before deploy | No workflows directory | Developer-run `php artisan test` |
| Dated production deploy evidence | Not stored in git | Collector screenshot + checklist |

---

## Hygiene reminder for screenshots

- Show **Revantage CRM** / `TriSparkSS/Rev-Credentialing` and a visible date.  
- Mask `APP_KEY`, DB passwords, `GRAPH_CLIENT_SECRET`, Graph tokens, SMTP passwords, and any patient/provider identifiers.  
- Do not screenshot README default seed passwords; state only that production passwords are changed per the deploy checklist.
