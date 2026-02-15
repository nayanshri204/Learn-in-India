<?php
// Generate a simple one-page PDF certificate for a completed project belonging to the logged-in intern
require __DIR__ . '/includes/header.php';

if (empty($_SESSION['intern_email'])) {
    header('Location: login.php'); 
    exit;
}

$projectId = $_GET['project_id'] ?? '';
if ($projectId === '') {
    echo '<div class="card"><h1>Invalid request</h1></div>'; require 'footer.php'; exit;
}

$usersFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.json';
$users = [];
if (file_exists($usersFile)) $users = json_decode(file_get_contents($usersFile), true) ?: [];
$me = null; $project = null;
foreach ($users as $u) if (isset($u['email']) && strcasecmp($u['email'], $_SESSION['intern_email']) === 0) { $me = $u; break; }
if (!$me) { echo '<div class="card"><h1>User not found</h1></div>'; require 'footer.php'; exit; }

foreach ($me['projects'] as $p) { if ($p['id'] === $projectId) { $project = $p; break; } }
if (!$project) { echo '<div class="card"><h1>Project not found</h1></div>'; require 'footer.php'; exit; }
if (empty($project['completed'])) { echo '<div class="card"><h1>Certificate not available</h1><p>Project is not marked complete.</p></div>'; require 'footer.php'; exit; }

// Build a minimal PDF with basic text. This is a small, self-contained PDF writer.
function build_pdf_bytes($title, $lines) {
    $objects = [];

    // fonts and resources
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

    // content stream
    $content = "BT\n/F1 24 Tf 72 700 Td (" . pdf_escape($title) . ") Tj\nET\n";
    $y = 660;
    foreach ($lines as $line) {
        $content .= "BT /F1 16 Tf 72 $y Td (" . pdf_escape($line) . ") Tj ET\n";
        $y -= 26;
    }

    $contentStream = "stream\n" . $content . "endstream\n";
    $len = strlen($content);
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\n" . $contentStream . "endobj\n";

    // assemble PDF with xref
    $pdf = "%PDF-1.1\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    $pos = strlen($pdf);
    foreach ($objects as $obj) {
        $offsets[] = $pos;
        $pdf .= $obj;
        $pos = strlen($pdf);
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $o) {
        $pdf .= sprintf('%010d 00000 n \n', $o);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";
    return $pdf;
}

function pdf_escape($s) {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

$title = 'Certificate of Completion';
$lines = [
    'This certifies that: ' . ($me['name'] ?? ''),
    'Has successfully completed the project:',
    ($project['title'] ?? ''),
    'Date: ' . date('Y-m-d', strtotime($project['completed_at'] ?? $project['created_at']))
];

$pdf = build_pdf_bytes($title, $lines);

// send PDF to browser
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="certificate_' . preg_replace('/[^a-z0-9_\-]/i', '_', $project['id']) . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;

require __DIR__ . '/includes/footer.php';
