<?php

require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.45; }
  h1 { font-size: 18px; color: #163B65; margin: 0 0 6px; }
  h2 { font-size: 13px; color: #163B65; margin: 16px 0 8px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
  .meta { color: #6b7280; font-size: 10px; margin-bottom: 14px; }
  p { margin: 0 0 8px; }
  ol { margin: 0 0 8px; padding-left: 22px; }
  li { margin-bottom: 8px; }
  .note { background: #eef4fb; padding: 8px 10px; border-radius: 4px; margin: 10px 0; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
  th, td { border: 1px solid #e5e7eb; padding: 5px 7px; text-align: left; vertical-align: top; }
  th { background: #163B65; color: #fff; font-size: 10px; }
  .done { color: #166534; }
</style>
</head>
<body>
<h1>Revantage CRM — Pending Items</h1>
<p class="meta">
  Compared with: Credentialing Portal requirements document, Version 2.0 (1 April 2026)<br>
  Application: Revantage CRM<br>
  GitHub repository: TriSparkSS/Rev-Credentialing<br>
  Date: 25 September 2026<br>
  This list is based on the current source code. It is not a live server audit.
</p>

<div class="note">
  Some pages in the requirements PDF are screenshots and could not be read as text. Payer form templates and the credentialing packet ZIP download are already built, so they are not listed below.
</div>

<h2>Not included in Phase 1 (do not treat as pending)</h2>
<p>The requirements document says these are out of scope unless leadership later approves them:</p>
<ol>
  <li>Automatically filling or submitting applications on payer websites (robotic / portal automation).</li>
  <li>Connecting the portal to an electronic health record (EHR) or practice management system.</li>
  <li>Electronic signatures, other than a simple attestation check.</li>
</ol>

<h2>Security, hosting, and system rules</h2>
<ol>
  <li><strong>Use SQL Server for live data.</strong> The requirements call for Microsoft SQL Server in production. The app can connect to SQL Server, but local development still defaults to SQLite, and there are no ready-made database views for reporting.</li>
  <li><strong>Two-factor login (optional MFA).</strong> The database has a flag for two-factor authentication, but staff cannot turn it on or enter a code at login.</li>
  <li><strong>Stronger password rules.</strong> Admin passwords only need 8 characters. There is no rule for mixed characters, password expiry, or lockout after failed attempts.</li>
  <li><strong>Virus scan on file uploads.</strong> Uploads are checked for file type and size only. Files are not scanned for malware.</li>
  <li><strong>Encrypt stored files.</strong> Mail passwords can be stored encrypted, and the site can use HTTPS. Document files on disk are not encrypted at rest.</li>
  <li><strong>How long to keep records.</strong> There is no setting to auto-delete or archive old emails, documents, or case history after a retention period.</li>
  <li><strong>Backup and restore from the product.</strong> There is no in-app backup, restore, or archive feature. That would be handled outside the application today.</li>
  <li><strong>Complete audit trail.</strong> Status changes, document actions, emails sent or linked, tasks, and delay overrides are logged. Logins are logged. File download, delete, and many edit actions are not fully covered.</li>
  <li><strong>Document access by file, not only by role.</strong> Access is by user role and assigned practices. There is no extra permission layer per document.</li>
</ol>

<h2>Search, home dashboard, and provider screens</h2>
<ol start="10">
  <li><strong>Header search is incomplete.</strong> Staff can search provider name, NPI, case number, and payer. They cannot search tax ID, CAQH ID, practice name, or status from the top search box.</li>
  <li><strong>Provider list is missing key columns and filters.</strong> The list does not show overall credentialing status, how many payers are still pending, or expiry alerts. Filters for state, payer, and missing documents are not on this screen.</li>
  <li><strong>Home dashboard does not show “revenue stuck”.</strong> The requirements want a clear picture of work blocking billing. Delay and aging widgets exist, but there is no revenue-stuck tile.</li>
  <li><strong>Delay report for management.</strong> Each case has a delay owner. There is no dedicated screen that shows the share of delay by team, provider, and payer (for example percentages) for management review.</li>
  <li><strong>Approval, denial, and panel-closed reports.</strong> Those statuses exist on cases. There are no separate reports for approval rate, denial trends, or panel-closed counts.</li>
  <li><strong>Reports as screens, not only downloads.</strong> Team productivity has a page. Several other reports are CSV or Excel downloads, or a filtered list, instead of a live dashboard (document compliance, email operations detail, delay breakdown).</li>
</ol>

<h2>Documents, tasks, and communication log</h2>
<ol start="16">
  <li><strong>Drag-and-drop document upload.</strong> Users pick a file with a browse button. They cannot drop files onto the page to upload.</li>
  <li><strong>Document folders by provider, type, and version.</strong> Older versions of a file are kept. The screen does not show an automatic folder tree (provider, then document type, then version).</li>
  <li><strong>Log faxes, calls, and payer-portal checks.</strong> Fax numbers can be stored on payers and practices. The case activity log is not a full journal of faxes, phone calls, and portal checks.</li>
  <li><strong>Send email from a task.</strong> Staff can send template emails from a case or from Email Center. The task detail panel does not have a dedicated “send email” button as specified.</li>
  <li><strong>Payer contact people.</strong> The database can store payer contacts, but there is no admin screen to add or edit them.</li>
</ol>

<h2>Email Center and reminders</h2>
<ol start="21">
  <li><strong>Smart email tagging.</strong> Later-phase requirements mention AI tagging. Today an email is linked only if it contains a case number, a 10-digit NPI, or a known sender address.</li>
  <li><strong>Save emails on the case.</strong> The mailbox is read live from Microsoft 365. The requirements want sent and received mail stored on the case so history remains even if the live mailbox changes.</li>
  <li><strong>Bounces and unread priority.</strong> Failed and unlinked messages can be filtered. Full bounce handling and an unread-priority queue are not complete.</li>
  <li><strong>Reminders by payer, practice group, and case type.</strong> SLA rules and email templates exist. Staff cannot yet turn reminders on or off for every combination of template, payer, provider group, and case type.</li>
  <li><strong>Stop duplicate automated emails.</strong> The requirements say reminders must not send twice, must skip closed cases, and must respect manual overrides. Those guards are not clearly finished as dedicated rules.</li>
</ol>

<h2>Live production go-live checks</h2>
<ol start="26">
  <li><strong>Confirm production hosting.</strong> Documentation lists SQL Server, HTTPS, and turning off debug mode. This review did not verify a live production server, so that confirmation is still pending.</li>
</ol>

<h2>Already built (not pending)</h2>
<p class="done">
  Practices, providers, payers, and locations; configurable statuses, SLA, and delay owners; credentialing cases (including EFT, ERA, roster, and similar types); automatic delay ownership with logged overrides; document version history; Email Center using Microsoft 365; scheduled reminder command; tasks and kanban board; provider and practice portals; Excel bulk import; credentialing packet ZIP download; form templates; business calendar; provider API tokens; Total Credentialing report (Excel and CSV).
</p>

<table>
  <tr><th>Count</th><th>Area</th></tr>
  <tr><td>9</td><td>Security, hosting, and system rules</td></tr>
  <tr><td>6</td><td>Search, home dashboard, and provider screens</td></tr>
  <tr><td>5</td><td>Documents, tasks, and communication log</td></tr>
  <tr><td>5</td><td>Email Center and reminders</td></tr>
  <tr><td>1</td><td>Live production go-live checks</td></tr>
  <tr><td><strong>26</strong></td><td><strong>Total pending items</strong></td></tr>
</table>
</body>
</html>
HTML;

$options = new Options;
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter');
$dompdf->render();

$out = __DIR__.'/BRD_Pending_Items.pdf';
file_put_contents($out, $dompdf->output());
echo $out.PHP_EOL;
echo 'bytes='.filesize($out).PHP_EOL;
