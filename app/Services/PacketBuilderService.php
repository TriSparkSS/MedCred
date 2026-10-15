<?php

namespace App\Services;

use App\Models\CredentialingCase;
use App\Models\FormTemplate;
use App\Models\PacketGeneration;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PacketBuilderService
{
    public function __construct(
        protected CredentialingPacketService $legacyPacket,
    ) {
    }

    public function validateCompleteness(CredentialingCase $case, ?FormTemplate $template = null): array
    {
        $missing = app(CredentialingCaseService::class)->readinessWarnings($case);

        if ($template && empty($template->field_mapping)) {
            $missing[] = 'Form template has no field mapping configured.';
        }

        return $missing;
    }

    public function generate(CredentialingCase $case, FormTemplate $template, int $adminId): PacketGeneration
    {
        $case->load(['provider.user', 'practice', 'payer', 'location', 'documentItems.documentType']);

        $html = $this->buildHtml($case, $template);
        $pdfPath = $this->renderPdf($case, $html);

        $generation = PacketGeneration::create([
            'credentialing_case_id' => $case->id,
            'form_template_id' => $template->id,
            'generated_by_admin_id' => $adminId,
            'file_path' => $pdfPath,
            'form_version' => $template->version,
            'included_document_ids' => $case->documents()->pluck('id')->toArray(),
            'metadata' => ['case_number' => $case->case_number],
        ]);

        $case->addActivity('packet', "Packet generated: {$template->name}", $adminId);

        return $generation;
    }

    public function buildZip(CredentialingCase $case): ?string
    {
        return $this->legacyPacket->build($case);
    }

    public function buildHtml(CredentialingCase $case, ?FormTemplate $template = null): string
    {
        $case->loadMissing([
            'provider.user',
            'provider.specialty',
            'practice',
            'payer',
            'caseType',
            'priority',
            'status',
            'documents.documentType',
        ]);

        $provider = $case->provider;
        $mapping = $template?->field_mapping ?? [];

        $caseNumber = e((string) ($case->case_number ?? 'N/A'));
        $providerName = e((string) ($provider?->user?->name ?? 'N/A'));
        $npi = e((string) ($provider?->npi ?? 'N/A'));
        $taxId = e((string) ($case->practice?->ein_tin ?? $case->practice?->tax_id ?? 'N/A'));
        $specialty = e((string) ($provider?->specialty?->name ?? 'N/A'));
        $practiceName = e((string) ($case->practice?->legal_name ?? $provider?->practice ?? 'N/A'));
        $payerName = e((string) ($case->payer?->name ?? 'N/A'));
        $caseTypeName = e((string) ($case->caseType?->name ?? 'N/A'));
        $priorityName = e((string) ($case->priority?->name ?? 'N/A'));
        $statusName = e((string) ($case->status?->name ?? 'N/A'));

        $rows = '';
        foreach ($mapping as $label => $field) {
            $raw = data_get($case, $field) ?? data_get($provider, $field) ?? data_get($case->practice, $field) ?? '—';
            if (is_array($raw)) {
                $raw = implode(', ', array_map('strval', $raw));
            }
            $safeLabel = e((string) $label);
            $safeValue = e((string) $raw);
            $rows .= "<tr><td><strong>{$safeLabel}</strong></td><td>{$safeValue}</td></tr>";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="2">No mapped fields — configure template field mapping in Admin Settings.</td></tr>';
        }

        $docRows = '';
        foreach ($case->documents as $d) {
            $docTitle = e((string) ($d->title ?? '—'));
            $docType = e((string) ($d->documentType?->name ?? '—'));
            $docNumber = e((string) ($d->document_number ?? '—'));
            $issuingAuthority = e((string) ($d->issuing_authority ?? '—'));
            $verificationStatus = e((string) ($d->verification_status ?? $d->status ?? '—'));
            $docRows .= "<tr><td>{$docTitle}</td><td>{$docType}</td><td>{$docNumber}</td><td>{$issuingAuthority}</td><td>{$verificationStatus}</td></tr>";
        }

        $documentsSection = $docRows !== ''
            ? "<h2>Documents</h2><table border=\"1\" cellpadding=\"6\" cellspacing=\"0\" width=\"100%\"><thead><tr><th>Title</th><th>Type</th><th>Number</th><th>Issuing Authority</th><th>Verification Status</th></tr></thead><tbody>{$docRows}</tbody></table>"
            : '';

        return <<<HTML
        <html><body>
        <h1>Credentialing Packet — {$caseNumber}</h1>
        <p>Provider: {$providerName} | NPI: {$npi} | Specialty: {$specialty}</p>
        <p>Practice: {$practiceName} | Tax ID: {$taxId} | Payer: {$payerName}</p>
        <p>Case Type: {$caseTypeName} | Priority: {$priorityName} | Status: {$statusName}</p>
        <table border="1" cellpadding="6" cellspacing="0" width="100%">{$rows}</table>
        {$documentsSection}
        </body></html>
        HTML;
    }

    protected function renderPdf(CredentialingCase $case, string $html): string
    {
        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter');
        $dompdf->render();

        $safeCaseNumber = preg_replace('/[^A-Za-z0-9._\-]/', '_', (string) $case->case_number);
        $filename = "packets/{$safeCaseNumber}_" . now()->format('YmdHis') . '.pdf';
        Storage::disk('public')->put($filename, $dompdf->output());

        return $filename;
    }
}
