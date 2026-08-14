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

    protected function buildHtml(CredentialingCase $case, FormTemplate $template): string
    {
        $provider = $case->provider;
        $mapping = $template->field_mapping ?? [];

        $rows = '';
        foreach ($mapping as $label => $field) {
            $value = data_get($case, $field) ?? data_get($provider, $field) ?? data_get($case->practice, $field) ?? '—';
            $rows .= "<tr><td><strong>{$label}</strong></td><td>{$value}</td></tr>";
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="2">No mapped fields — configure template field mapping in Admin Settings.</td></tr>';
        }

        return <<<HTML
        <html><body>
        <h1>Credentialing Packet — {$case->case_number}</h1>
        <p>Provider: {$provider->user->name ?? 'N/A'} | Payer: {$case->payer->name ?? 'N/A'}</p>
        <table border="1" cellpadding="6" cellspacing="0" width="100%">{$rows}</table>
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

        $filename = "packets/{$case->case_number}_" . now()->format('YmdHis') . '.pdf';
        Storage::disk('public')->put($filename, $dompdf->output());

        return $filename;
    }
}
