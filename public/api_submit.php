<?php
require_once __DIR__ . '/../server/forms.php';

header('Content-Type: application/json');
// Basic CORS support for browser clients
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
// Honor common method override headers/params used behind some proxies/CDNs
$override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
    ?? $_SERVER['HTTP_X_METHOD_OVERRIDE']
    ?? ($_POST['_method'] ?? $_GET['_method'] ?? '');
if ($override) { $method = strtoupper((string)$override); }
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit; // preflight
}
// Some stacks tunnel JSON as PUT; accept it equivalently to POST
if ($method === 'PUT') {
    // proceed as POST below
} elseif ($method !== 'POST') {
    // If a non-POST method still sent a JSON body (rare), attempt to parse anyway
    $rawProbe = file_get_contents('php://input');
    if (is_string($rawProbe) && strlen($rawProbe) > 0) {
        // Continue as if POST with the probed body
    } else {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'error' => 'Use POST to submit',
            'endpoint' => '/public/api/submit',
            'example' => [
                'user' => 'username',
                'form' => 'form-slug',
                'api_key' => 'your-form-api-key',
                'data' => [ 'fieldName' => 'value' ]
            ]
        ]);
        exit;
    }
}

$raw = isset($rawProbe) && $rawProbe !== '' ? $rawProbe : file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!$payload) {
    echo json_encode(['ok'=>false,'error'=>'Invalid JSON']);
    exit;
}

$user = $payload['user'] ?? '';
$formSlug = $payload['form'] ?? '';
$apiKey = $payload['api_key'] ?? '';
$data = $payload['data'] ?? [];

if (!$user || !$formSlug || !$apiKey || !is_array($data)) {
    echo json_encode(['ok'=>false,'error'=>'Missing required fields']);
    exit;
}

$form = loadForm($user, $formSlug);
if (!$form) { echo json_encode(['ok'=>false,'error'=>'Form not found']); exit; }
if (($form['api_key'] ?? '') !== $apiKey) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Invalid API key']); exit; }

$values = [];
$missing = [];
foreach ($form['fields'] as $fld) {
    $name = $fld['name'];
    $type = $fld['type'] ?? 'text';
    $req = !empty($fld['required']);
    if ($type === 'file') {
        $val = $data[$name] ?? '';
        if (is_string($val) && $val !== '') {
            $rel = handleBase64FileField($user, $formSlug, $name, $val);
            if ($rel) {
                $values[$name] = $rel;
            } else {
                if ($req) { $missing[] = $name; }
                $values[$name] = '';
            }
        } else {
            if ($req) { $missing[] = $name; }
            $values[$name] = '';
        }
        continue;
    }
    if (in_array($type, ['select_multiple','checkbox_group'], true)) {
        $v = $data[$name] ?? [];
        if (is_array($v)) {
            $joined = implode('; ', array_map('strval', $v));
            if ($req && $joined === '') { $missing[] = $name; }
            $values[$name] = $joined;
        } else {
            $sv = trim((string)$v);
            if ($req && $sv === '') { $missing[] = $name; }
            $values[$name] = $sv;
        }
    } elseif ($type === 'checkbox') {
        $v = $data[$name] ?? '';
        $checked = ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on');
        if ($req && !$checked) { $missing[] = $name; }
        $values[$name] = $checked ? '1' : '0';
    } else {
        $sv = trim((string)($data[$name] ?? ''));
        if ($req && $sv === '') { $missing[] = $name; }
        $values[$name] = $sv;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Missing required fields','fields'=>$missing]);
    exit;
}

// Ensure CSV headers and append
ensureCSVHeaders($user, $formSlug, $form['fields'] ?? []);
list($headers,) = getHeadersAndTypesFromConfig($form);
$values['_submitted_at'] = date('c');
$row = [];
foreach ($headers as $h) { $row[] = $values[$h] ?? ''; }
appendSubmissionCSV($user, $formSlug, $headers, $row);

// Build response with absolute URLs for file fields
$responseValues = $values;
foreach (($form['fields'] ?? []) as $fld) {
    if (($fld['type'] ?? 'text') === 'file') {
        $n = $fld['name'];
        $v = $responseValues[$n] ?? '';
        if ($v !== '') {
            // Convert stored relative path to absolute URL
            $responseValues[$n] = to_public_url($v);
        }
    }
}

echo json_encode(['ok'=>true,'saved'=>$responseValues]);
exit;
