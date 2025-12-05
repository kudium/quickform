<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/utils.php';

define('FORMS_DIR_NAME', 'forms');

function getUserDir($username) {
    return USERS_DIR . $username;
}

function ensureUserFormsDir($username) {
    $base = getUserDir($username);
    $formsDir = $base . '/' . FORMS_DIR_NAME;
    if (!is_dir($base)) {
        mkdir($base, 0777, true);
    }
    if (!is_dir($formsDir)) {
        mkdir($formsDir, 0777, true);
    }
    return $formsDir;
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    if (empty($text)) { return 'form-' . substr(md5(uniqid('', true)), 0, 6); }
    return $text;
}

function getFormDir($username, $formSlug) {
    return ensureUserFormsDir($username) . '/' . $formSlug;
}

function getFormConfigPath($username, $formSlug) {
    return getFormDir($username, $formSlug) . '/form.json';
}

function getFormCSVPath($username, $formSlug) {
    return getFormDir($username, $formSlug) . '/data.csv';
}

function getFormUploadsDir($username, $formSlug) {
    $dir = getFormDir($username, $formSlug) . '/uploads';
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    // Harden uploads: ensure Apache .htaccess denies PHP execution
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        $rules = <<<'HT'
<FilesMatch "\.(php|phtml|pht|phps|php3|php4|php5|php7|php8|phar)$">
    Require all denied
</FilesMatch>
RemoveHandler .php .phtml .pht .phps .php3 .php4 .php5 .php7 .php8 .phar
RemoveType .php .phtml .pht .phps .php3 .php4 .php5 .php7 .php8 .phar
Options -ExecCGI
HT;
        @file_put_contents($ht, $rules);
    }
    return $dir;
}

function rmdirRecursive($dir) {
    if (!is_dir($dir)) return true;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            rmdirRecursive($path);
        } else {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}

function deleteForm($username, $formSlug) {
    $dir = getFormDir($username, $formSlug);
    if (!is_dir($dir)) return false;
    return rmdirRecursive($dir);
}

function getAllowedFieldTypes() {
    return ['text','password','textarea','file','select','select_multiple','radio','checkbox','checkbox_group','email','number','url','tel','date','time','datetime-local'];
}

function updateFormLabels($username, $formSlug, $newName, $labelMap) {
    return updateFormFields($username, $formSlug, $newName, $labelMap, null, null);
}

function updateFormFields($username, $formSlug, $newName, $labelMap = null, $typeMap = null, $requiredMap = null) {
    $cfgPath = getFormConfigPath($username, $formSlug);
    if (!is_file($cfgPath)) return false;
    $config = json_decode(file_get_contents($cfgPath), true);
    if (!$config) return false;
    if ($newName !== null && $newName !== '') {
        $config['name'] = $newName;
    }
    $allowed = getAllowedFieldTypes();
    if (is_array($labelMap) || is_array($typeMap) || is_array($requiredMap)) {
        $fields = $config['fields'] ?? [];
        foreach ($fields as &$fld) {
            $n = $fld['name'] ?? '';
            if ($n === '') continue;
            if (is_array($labelMap) && array_key_exists($n, $labelMap)) {
                $fld['label'] = trim((string)$labelMap[$n]);
            }
            if (is_array($typeMap) && array_key_exists($n, $typeMap)) {
                $t = (string)$typeMap[$n];
                if (in_array($t, $allowed, true)) {
                    $fld['type'] = $t;
                }
            }
            if (is_array($requiredMap)) {
                // If provided, set true only when present and truthy; false otherwise
                $val = $requiredMap[$n] ?? '0';
                $fld['required'] = ($val === '1' || $val === 'on' || $val === 'true' || $val === 1 || $val === true);
                if ($fld['required'] === false) { unset($fld['required']); }
            }
        }
        unset($fld);
        $config['fields'] = $fields;
    }
    file_put_contents($cfgPath, json_encode($config, JSON_PRETTY_PRINT));
    return $config;
}

// Update full form structure (fields array) and align CSV headers/rows
function updateFormStructure($username, $formSlug, $newName, $fields) {
    $cfgPath = getFormConfigPath($username, $formSlug);
    if (!is_file($cfgPath)) return false;
    $config = json_decode(file_get_contents($cfgPath), true);
    if (!$config) return false;

    // Validate and normalize fields
    $allowed = getAllowedFieldTypes();
    $norm = [];
    $seen = [];
    foreach ($fields as $f) {
        $name = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($f['name'] ?? ''));
        $label = trim((string)($f['label'] ?? ''));
        $type = (string)($f['type'] ?? 'text');
        if ($name === '' || $label === '') continue;
        if (!in_array($type, $allowed, true)) { $type = 'text'; }
        if (isset($seen[$name])) { continue; }
        $seen[$name] = true;
        $rec = ['name' => $name, 'label' => $label, 'type' => $type];
        if (!empty($f['required'])) { $rec['required'] = true; }
        if (in_array($type, ['select','select_multiple','radio','checkbox_group'], true)) {
            $opts = array_values(array_filter(array_map('trim', (array)($f['options'] ?? [])), function($v){ return $v !== ''; }));
            if (!empty($opts)) { $rec['options'] = $opts; }
        }
        $norm[] = $rec;
    }
    if (empty($norm)) return false;

    $oldConfig = $config;
    $config['fields'] = $norm;
    if ($newName !== null && $newName !== '') {
        $config['name'] = $newName;
    }
    file_put_contents($cfgPath, json_encode($config, JSON_PRETTY_PRINT));

    // Align CSV: rewrite header and map existing rows to new columns
    $newHeaders = array_map(function($f){ return $f['name']; }, $norm);
    rewriteFormCSVToHeaders($username, $formSlug, $newHeaders);

    // Re-load and return merged structure
    $config['slug'] = $formSlug;
    $config['public_url'] = '/form?u=' . rawurlencode($username) . '&f=' . rawurlencode($formSlug);
    return $config;
}

function rewriteFormCSVToHeaders($username, $formSlug, $newFieldNames) {
    // Ensure CSV exists with correct headers even if no data yet
    $csv = getFormCSVPath($username, $formSlug);
    $newHeaders = array_values($newFieldNames);
    // Always include timestamp as final column
    if (empty($newHeaders) || end($newHeaders) !== '_submitted_at') {
        $newHeaders[] = '_submitted_at';
    }

    if (!is_file($csv)) {
        // Create new CSV with header only
        $line = csvLineFromArray($newHeaders);
        $enc = encryptLineForUser($username, $line);
        file_put_contents($csv, $enc . "\n");
        return true;
    }

    list($oldHeaders, $rows) = readFormCSV($username, $formSlug);
    if (empty($oldHeaders)) {
        // No parsable old header, just rewrite header line
        $line = csvLineFromArray($newHeaders);
        $enc = encryptLineForUser($username, $line);
        file_put_contents($csv, $enc . "\n");
        return true;
    }
    // Build index map for old headers
    $oldIndex = [];
    foreach ($oldHeaders as $i => $h) { $oldIndex[$h] = $i; }

    // Write new file
    $out = fopen($csv, 'w');
    if ($out === false) return false;
    // Header
    $headerLine = csvLineFromArray($newHeaders);
    fwrite($out, encryptLineForUser($username, $headerLine) . "\n");
    // Rows
    foreach ($rows as $row) {
        $assoc = [];
        foreach ($oldHeaders as $i => $h) {
            $assoc[$h] = $row[$i] ?? '';
        }
        $newRow = [];
        foreach ($newHeaders as $h) {
            $newRow[] = $assoc[$h] ?? '';
        }
        $line = csvLineFromArray($newRow);
        fwrite($out, encryptLineForUser($username, $line) . "\n");
    }
    fclose($out);
    return true;
}

function loadForms($username) {
    $formsDir = ensureUserFormsDir($username);
    $forms = [];
    foreach (scandir($formsDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $dir = $formsDir . '/' . $entry;
        if (!is_dir($dir)) continue;
        $configPath = $dir . '/form.json';
        if (!is_file($configPath)) continue;
        $cfg = json_decode(file_get_contents($configPath), true);
        if (!$cfg) continue;
        $cfg['slug'] = $entry;
        // Derive public URL (relative) for convenience
        $cfg['public_url'] = '/form?u=' . rawurlencode($username) . '&f=' . rawurlencode($entry);
        $forms[] = $cfg;
    }
    // sort by name
    usort($forms, function($a, $b) { return strcmp($a['name'] ?? '', $b['name'] ?? ''); });
    return $forms;
}

function createForm($username, $name, $fields) {
    $slug = slugify($name);
    $dir = getFormDir($username, $slug);
    if (!is_dir($dir)) { mkdir($dir, 0777, true); }
    // Generate an API key for this form (hex string)
    $apiKey = bin2hex(random_bytes(12));
    $config = [
        'name' => $name,
        'fields' => $fields,
        'api_key' => $apiKey,
        'created_at' => time(),
    ];
    file_put_contents($dir . '/form.json', json_encode($config, JSON_PRETTY_PRINT));
    return [
        'slug' => $slug,
        'name' => $name,
        'public_url' => '/form?u=' . rawurlencode($username) . '&f=' . rawurlencode($slug),
        'api_key' => $apiKey,
        'dir'  => $dir,
    ];
}

// Set or clear form privacy (when private, public page 404s; API still works)
function setFormPrivacy($username, $formSlug, $private) {
    $cfgPath = getFormConfigPath($username, $formSlug);
    if (!is_file($cfgPath)) return false;
    $config = json_decode(file_get_contents($cfgPath), true);
    if (!$config) return false;
    if ($private) {
        $config['private'] = true;
    } else {
        unset($config['private']);
    }
    file_put_contents($cfgPath, json_encode($config, JSON_PRETTY_PRINT));
    return $config;
}

function saveFormFieldsFromPost($username, $slug, $input) {
    $name = trim((string)($input['name'] ?? 'Untitled Form'));
    $fieldNames = $input['field_name'] ?? [];
    $fieldLabels = $input['field_label'] ?? [];
    $fieldTypes = $input['field_type'] ?? [];
    $fieldRequired = $input['field_required'] ?? [];
    $fields = [];
    foreach ($fieldNames as $i => $n) {
        $n = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$n);
        if ($n === '') continue;
        $label = trim((string)($fieldLabels[$i] ?? $n));
        $type = (string)($fieldTypes[$i] ?? 'text');
        $rec = ['name' => $n, 'label' => $label, 'type' => $type];
        if (!empty($fieldRequired[$i])) { $rec['required'] = true; }
        // Capture option lists for select/radio/checkbox_group
        if (in_array($type, ['select','select_multiple','radio','checkbox_group'], true)) {
            $optsKey = 'field_options_' . $i;
            $optRaw = (string)($input[$optsKey] ?? '');
            $opts = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $optRaw))));
            $rec['options'] = $opts;
        }
        $fields[] = $rec;
    }
    return updateFormFields($username, $slug, $name, null, null, null) !== false
        ? updateFormFields($username, $slug, $name, null, null, null)
        : false;
}

function loadForm($username, $slug) {
    $cfg = getFormConfigPath($username, $slug);
    if (!is_file($cfg)) return null;
    $conf = json_decode(file_get_contents($cfg), true);
    if (!$conf) return null;
    $conf['slug'] = $slug;
    $conf['public_url'] = '/form?u=' . rawurlencode($username) . '&f=' . rawurlencode($slug);
    return $conf;
}

function loadPublicForm($username, $slug) {
    return loadForm($username, $slug);
}

function ensureCSVHeaders($username, $slug, $fields) {
    $csv = getFormCSVPath($username, $slug);
    if (!is_file($csv)) {
        $headers = array_map(function($f){ return $f['name']; }, $fields);
        // Append timestamp header
        $headers[] = '_submitted_at';
        file_put_contents($csv, csvLineFromArray($headers) . "\n");
    }
}

function getHeadersAndTypesFromConfig($cfg) {
    $headers = [];
    $types = [];
    foreach (($cfg['fields'] ?? []) as $f) {
        $headers[] = $f['name'];
        $types[] = $f['type'] ?? 'text';
    }
    $headers[] = '_submitted_at';
    $types[] = 'text';
    return [$headers, $types];
}

function appendSubmissionCSV($username, $slug, $headers, $row) {
    $csv = getFormCSVPath($username, $slug);
    $line = csvLineFromArray($row);
    $line = encryptLineForUser($username, $line);
    file_put_contents($csv, $line . "\n", FILE_APPEND);
}

function handleApiSubmit($username, $slug, $post, $files) {
    $cfg = loadForm($username, $slug);
    if (!$cfg) {
        return ['status' => 404, 'message' => 'Form not found'];
    }
    list($headers, $headerTypes) = getHeadersAndTypesFromConfig($cfg);
    // Validate and map values
    $values = [];
    foreach (($cfg['fields'] ?? []) as $field) {
        $name = $field['name'];
        $type = $field['type'] ?? 'text';
        $required = !empty($field['required']);

        if ($type === 'file') {
            $fileValue = null;
            if (isset($files[$name]) && is_array($files[$name]) && ($files[$name]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $tmp = $files[$name]['tmp_name'];
                $origName = $files[$name]['name'] ?? '';
                $mime = detect_mime_from_file($tmp);
                $ext = pathinfo($origName, PATHINFO_EXTENSION);
                $safeExt = sanitize_extension($ext);
                if (!upload_extension_allowed($safeExt, $mime)) {
                    return ['status' => 400, 'message' => 'Invalid file type'];
                }
                $uploads = getFormUploadsDir($username, $slug);
                $target = $uploads . '/' . $name . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . ($safeExt ? ('.' . $safeExt) : '');
                if (move_uploaded_file($tmp, $target)) {
                    $fileValue = to_web_relative($target);
                }
            }
            if ($required && !$fileValue) {
                return ['status' => 400, 'message' => 'Missing required file: ' . $name];
            }
            $values[$name] = $fileValue ?? '';
            continue;
        }

        $v = $post[$name] ?? '';
        if (is_array($v)) {
            // For multi-select or checkbox groups, join by semicolon
            $v = implode('; ', array_map('trim', $v));
        } else {
            $v = trim((string)$v);
        }
        if ($required && $v === '') {
            return ['status' => 400, 'message' => 'Missing required field: ' . $name];
        }
        $values[$name] = safe_csv_value($v);
    }

    // Append timestamp
    $values['_submitted_at'] = date('c');

    // Ensure CSV has headers
    ensureCSVHeaders($username, $slug, $cfg['fields'] ?? []);

    // Assemble row aligned to headers
    $row = [];
    foreach ($headers as $h) {
        $row[] = $values[$h] ?? '';
    }
    appendSubmissionCSV($username, $slug, $headers, $row);

    return ['status' => 200, 'message' => 'Submission recorded'];
}

function handlePublicSubmit($username, $slug, $post, $files) {
    return handleApiSubmit($username, $slug, $post, $files);
}

function readFormCSV($username, $slug) {
    $csv = getFormCSVPath($username, $slug);
    if (!is_file($csv)) return [[], []];
    $rows = [];
    $fh = fopen($csv, 'r');
    if (!$fh) return [[], []];
    while (($line = fgets($fh)) !== false) {
        $line = rtrim($line, "\r\n");
        $plain = decryptLineForUser($username, $line);
        $row = str_getcsv($plain ?? $line, ",", '"', "\\");
        $rows[] = $row;
    }
    fclose($fh);
    if (empty($rows)) return [[], []];
    $headers = array_shift($rows);
    return [$headers, $rows];
}

// Back-compat style helper: read CSV by path for a user (decrypt each line)
function readCSV($csvPath, $username) {
    if (!is_file($csvPath)) return [];
    $rows = [];
    $fh = fopen($csvPath, 'r');
    if (!$fh) return [];
    while (($line = fgets($fh)) !== false) {
        $line = rtrim($line, "\r\n");
        $plain = decryptLineForUser($username, $line);
        $rows[] = str_getcsv($plain ?? $line, ",", '"', "\\");
    }
    fclose($fh);
    return $rows;
}

// Delete a specific data row (1-based, excludes header) from a form's CSV
function deleteFormDataRow($username, $slug, $rowIndex) {
    $csv = getFormCSVPath($username, $slug);
    if (!is_file($csv) || $rowIndex <= 0) return false;
    $in = fopen($csv, 'r');
    if (!$in) return false;
    $tmp = $csv . '.tmp';
    $out = fopen($tmp, 'w');
    if (!$out) { fclose($in); return false; }
    $lineNum = 0;
    while (($line = fgets($in)) !== false) {
        $lineNum++;
        if ($lineNum === 1) { // header always kept
            fwrite($out, $line);
            continue;
        }
        // Skip the requested data row
        if (($lineNum - 1) === $rowIndex) { // subtract header
            continue;
        }
        fwrite($out, $line);
    }
    fclose($in);
    fclose($out);
    return @rename($tmp, $csv);
}

function exportCSV($username, $slug, $decrypt = false) {
    list($headers, $rows) = readFormCSV($username, $slug);
    if (empty($headers)) return '';
    $out = fopen('php://temp', 'r+');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        if ($decrypt) {
            // When decrypting, rows are already decrypted by readFormCSV
            fputcsv($out, $row);
        } else {
            // When not decrypting, load raw encrypted lines
            $csvPath = getFormCSVPath($username, $slug);
            $fp = fopen($csvPath, 'r');
            if ($fp) {
                // Skip header
                fgets($fp);
                while (($line = fgets($fp)) !== false) {
                    fwrite($out, rtrim($line, "\r\n") . "\n");
                }
                fclose($fp);
            }
            break; // already wrote all lines
        }
    }
    rewind($out);
    $csvData = stream_get_contents($out);
    fclose($out);
    return $csvData;
}

function saveFormConfig($username, $slug, $config) {
    $cfgPath = getFormConfigPath($username, $slug);
    file_put_contents($cfgPath, json_encode($config, JSON_PRETTY_PRINT));
}

function saveNewFormConfig($username, $name, $fields) {
    $created = createForm($username, $name, $fields);
    return $created['slug'];
}

function handleBase64FileField($username, $slug, $fieldName, $dataUri) {
    // Supports data URI like data:image/png;base64,AAAA
    if (!is_string($dataUri)) return null;
    if (!preg_match('~^data:([^;,]+)?;base64,(.+)$~', $dataUri, $m)) {
        return null;
    }
    $mime = $m[1] ?? null;
    $base64 = $m[2] ?? '';
    $defaultExt = 'bin';
    if ($mime) {
        $data = base64_decode($base64);
        $ext = explode('/', $mime)[1] ?? $defaultExt;
    } else {
        $data = base64_decode($base64);
        $ext = $defaultExt;
        $mime = null;
    }
    if ($data === false) return null;
    $safeExt = sanitize_extension($ext);
    $realMime = detect_mime_from_buffer($data) ?: $mime;
    if (!upload_extension_allowed($safeExt, $realMime)) {
        return null; // block PHP-like uploads
    }
    $uploads = getFormUploadsDir($username, $slug);
    $target = $uploads . '/' . $fieldName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . ($safeExt ? ('.' . $safeExt) : '');
    if (file_put_contents($target, $data) !== false) {
        return to_web_relative($target);
    }
    return null;
}

// ===== CSV encryption helpers (obfuscation) =====

function getUserPasswordHash($username) {
    $userDir = getUserDir($username);
    $cfg = $userDir . '/config.php';
    if (!is_file($cfg)) return null;
    $conf = include($cfg);
    return $conf['password'] ?? null;
}

function getUserKey($username) {
    $hash = getUserPasswordHash($username);
    if (!$hash) { // fallback to random-like static
        $hash = 'fallback-secret-' . $username;
    }
    // Derive a 32-byte key from stored password hash
    return hash('sha256', $hash . '::widgets_form_key::' . $username, true);
}

function getUserKeyFromHash($username, $passwordHash) {
    $hash = $passwordHash ?: ('fallback-secret-' . $username);
    return hash('sha256', $hash . '::widgets_form_key::' . $username, true);
}

function encryptLineForUser($username, $plain) {
    $key = getUserKey($username);
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return $plain; // best-effort fallback
    return 'ENCv1:' . base64_encode($iv) . ':' . base64_encode($cipher);
}

function decryptLineForUser($username, $line) {
    if (strpos($line, 'ENCv1:') !== 0) return $line;
    $key = getUserKey($username);
    $parts = explode(':', $line, 3);
    if (count($parts) !== 3) return null;
    [$prefix, $biv, $bcipher] = $parts;
    $iv = base64_decode($biv, true);
    $cipher = base64_decode($bcipher, true);
    if ($iv === false || $cipher === false) return null;
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

function decryptLineWithKey($key, $line) {
    if (strpos($line, 'ENCv1:') !== 0) return $line;
    $parts = explode(':', $line, 3);
    if (count($parts) !== 3) return null;
    [$prefix, $biv, $bcipher] = $parts;
    $iv = base64_decode($biv, true);
    $cipher = base64_decode($bcipher, true);
    if ($iv === false || $cipher === false) return null;
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

function csvLineFromArray($arr) {
    $fp = fopen('php://temp', 'r+');
    fputcsv($fp, $arr, ",", '"', "\\");
    rewind($fp);
    $line = stream_get_contents($fp);
    fclose($fp);
    return rtrim($line, "\r\n");
}

function reencryptUserCSVs($username, $oldPasswordHash, $newPasswordHash) {
    $formsDir = ensureUserFormsDir($username);
    $oldKey = getUserKeyFromHash($username, $oldPasswordHash);
    $newKey = getUserKeyFromHash($username, $newPasswordHash);
    foreach (scandir($formsDir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $csv = $formsDir . '/' . $entry . '/data.csv';
        if (!is_file($csv)) continue;
        // Check if encrypted; if not, skip
        $fh = fopen($csv, 'r');
        if ($fh === false) continue;
        $first = fgets($fh);
        if ($first === false) { fclose($fh); continue; }
        $isEnc = (strpos($first, 'ENCv1:') === 0);
        rewind($fh);
        if (!$isEnc) { fclose($fh); continue; }
        // Re-encrypt: decrypt with oldKey, encrypt with newKey
        $tmp = $csv . '.tmp';
        $out = fopen($tmp, 'w');
        if ($out === false) { fclose($fh); continue; }
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            $plain = decryptLineWithKey($oldKey, $line);
            if ($plain === null) { // if cannot decrypt, preserve raw line
                fwrite($out, $line . "\n");
                continue;
            }
            $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $cipher = openssl_encrypt($plain, 'aes-256-cbc', $newKey, OPENSSL_RAW_DATA, $iv);
            if ($cipher === false) {
                fwrite($out, $line . "\n");
                continue;
            }
            $newline = 'ENCv1:' . base64_encode($iv) . ':' . base64_encode($cipher);
            fwrite($out, $newline . "\n");
        }
        fclose($fh);
        fclose($out);
        @rename($tmp, $csv);
    }
}
