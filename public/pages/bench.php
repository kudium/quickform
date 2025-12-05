<?php
require_once __DIR__ . '/../../server/forms.php';
require_once __DIR__ . '/../../server/utils.php';

header('Content-Type: application/json');

$enabled = getenv('BENCH_ENABLED');
if (!$enabled || (string)$enabled !== '1') {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Benchmark disabled']);
    exit;
}

$mode = $_GET['mode'] ?? 'csv'; // 'csv' or 'raw'
$n = (int)($_GET['n'] ?? 30);
if ($n < 1) $n = 30; if ($n > 500) $n = 500;

function ms($seconds) { return $seconds * 1000.0; }

$samples = [];
$startAll = microtime(true);

if ($mode === 'raw') {
    $tmp = sys_get_temp_dir() . '/widgets_bench_' . bin2hex(random_bytes(4)) . '.log';
    for ($i=0; $i<$n; $i++) {
        $t0 = microtime(true);
        file_put_contents($tmp, "line $i\n", FILE_APPEND);
        $samples[] = ms(microtime(true) - $t0);
    }
    @unlink($tmp);
} else {
    // Benchmark appendSubmissionCSV path with encryption
    $user = 'bench-user';
    $slug = 'bench';
    $fields = [ ['name'=>'fullName','label'=>'Full name','type'=>'text'], ['name'=>'message','label'=>'Message','type'=>'text'] ];
    ensureUserFormsDir($user);
    // Ensure headers
    ensureCSVHeaders($user, $slug, $fields);
    list($headers,) = getHeadersAndTypesFromConfig(['fields'=>$fields]);
    for ($i=0; $i<$n; $i++) {
        $rowVals = [ 'fullName' => 'Bench ' . $i, 'message' => 'Hello' ];
        $row = [];
        foreach ($headers as $h) { $row[] = $rowVals[$h] ?? ''; }
        $t0 = microtime(true);
        appendSubmissionCSV($user, $slug, $headers, $row);
        $samples[] = ms(microtime(true) - $t0);
    }
}

$elapsed = ms(microtime(true) - $startAll);
sort($samples);
$count = count($samples);
$p50 = $samples[(int)floor(0.50 * ($count-1))] ?? 0.0;
$p95 = $samples[(int)floor(0.95 * ($count-1))] ?? 0.0;
$min = $samples[0] ?? 0.0;
$max = $samples[$count-1] ?? 0.0;

echo json_encode([
  'ok' => true,
  'mode' => $mode,
  'n' => $n,
  'p50_ms' => round($p50, 2),
  'p95_ms' => round($p95, 2),
  'min_ms' => round($min, 2),
  'max_ms' => round($max, 2),
  'total_ms' => round($elapsed, 2)
]);
exit;

