<?php

/**
 * Generate Revantage CRM Project Documentation PDF.
 *
 * Usage: php docs/generate-documentation-pdf.php
 */

require __DIR__ . '/../vendor/autoload.php';

$htmlPath = __DIR__ . '/Revantage_CRM_Project_Documentation.html';
$pdfPath = __DIR__ . '/Revantage_CRM_Project_Documentation.pdf';

if (! file_exists($htmlPath)) {
    fwrite(STDERR, "HTML file not found: {$htmlPath}\n");
    exit(1);
}

$html = file_get_contents($htmlPath);

// Try dompdf if available
if (class_exists(\Dompdf\Dompdf::class)) {
    $dompdf = new \Dompdf\Dompdf([
        'isRemoteEnabled' => false,
        'isHtml5ParserEnabled' => true,
    ]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    file_put_contents($pdfPath, $dompdf->output());
    echo "PDF generated: {$pdfPath}\n";
    exit(0);
}

// Fallback: instruct user to install dompdf
fwrite(STDERR, "dompdf not installed. Run: composer require dompdf/dompdf --dev\n");
exit(1);
