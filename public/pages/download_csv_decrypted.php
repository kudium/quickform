<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/forms.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$current = getCurrentUser();
$u = $_GET['u'] ?? '';
$f = $_GET['f'] ?? '';

// Only allow owner to download decrypted
if ($u !== $current) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$form = loadForm($u, $f);
if (!$form) {
    http_response_code(404);
    $nf_title = 'Form not found';
    $nf_message = 'No cookies here. The form may be private or removed.';
    $nf_cta_href = abs_url('dashboard');
    $nf_cta_label = 'Back to Dashboard';
    include __DIR__ . '/../components/not_found.php';
    exit;
}

$csv = getFormCSVPath($u, $f);
if (!is_file($csv)) {
    http_response_code(404);
    $nf_title = 'CSV not found';
    $nf_message = 'No cookies here. This form has no data yet.';
    $nf_cta_href = abs_url('dashboard');
    $nf_cta_label = 'Back to Dashboard';
    include __DIR__ . '/../components/not_found.php';
    exit;
}

$rows = readCSV($csv, $u);

header('Content-Type: text/csv');
$safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $f);
header('Content-Disposition: attachment; filename="' . $safeName . '.decrypted.csv"');
$out = fopen('php://output', 'w');
foreach ($rows as $row) {
    fputcsv($out, $row, ",", '"', "\\");
}
fclose($out);
exit;
