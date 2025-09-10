<?php

function pdf_available(): bool {
    return file_exists(__DIR__ . '/../vendor/autoload.php');
}

function render_pdf(string $html, string $filename = 'document.pdf', bool $landscape=false) {
    if (!pdf_available()) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<h3>PDF export not installed</h3>';
        echo '<p>Please install dependencies with Composer to enable PDF export:</p>';
        echo '<pre>composer install</pre>';
        exit;
    }
    require_once __DIR__ . '/../vendor/autoload.php';
    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', $landscape ? 'landscape' : 'portrait');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

