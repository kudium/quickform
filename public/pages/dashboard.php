<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/forms.php';

if (!isLoggedIn()) {
    $baseUrl = web_base_url();
    header('Location: ' . $baseUrl . 'auth/login');
    exit;
}

$username = getCurrentUser();
$alert = '';

// Delete form submission row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_row_data'])) {
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['form_slug'] ?? '');
    $rowIndex = (int)($_POST['row_index'] ?? 0);
    if ($slug && $rowIndex > 0) {
        if (deleteFormDataRow($username, $slug, $rowIndex)) {
            $alert = 'Deleted one submission from "' . htmlspecialchars($slug) . '".';
        } else {
            $alert = 'Unable to delete submission.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_form'])) {
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['delete_slug'] ?? '');
    if ($slug) {
        if (deleteForm($username, $slug)) {
            $alert = 'Form "' . htmlspecialchars($slug) . '" deleted.';
        } else {
            $alert = 'Unable to delete form.';
        }
    }
}

// Set private/public toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_private'])) {
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['slug'] ?? '');
    $isPrivate = isset($_POST['private']) && ($_POST['private'] === '1' || $_POST['private'] === 'on');
    if ($slug) {
        $updated = setFormPrivacy($username, $slug, $isPrivate);
        if ($updated !== false) {
            $alert = 'Form "' . htmlspecialchars($slug) . '" privacy updated to ' . ($isPrivate ? 'private' : 'public') . '.';
        } else {
            $alert = 'Unable to update form privacy.';
        }
    }
}

$forms = loadForms($username);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard • QuickForm</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
    <style>body{font-family:'Inter',sans-serif}</style>
    <link rel="icon" href="data:,">
  </head>
<body class="bg-gray-50 min-h-screen overflow-x-hidden">
    <nav class="fixed top-0 inset-x-0 z-50 border-b border-white/60 bg-white/60 backdrop-blur supports-[backdrop-filter]:bg-white/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-6">
                    <a href="/" class="text-xl font-bold text-indigo-600">QuickForm</a>
                    <a href="/" class="text-sm text-gray-700 hover:text-gray-900">Home</a>
                </div>
                <div class="flex items-center gap-3">
                    <?php if (function_exists('isAdmin') && isAdmin()): ?>
                        <a href="dashboard/admin" class="px-3 py-2 text-sm font-medium rounded-md text-gray-900 bg-white border border-gray-300 hover:bg-gray-50">Admin</a>
                    <?php endif; ?>
                    <span class="text-gray-700 mr-1">Welcome, <?php echo htmlspecialchars($username); ?></span>
                    <a href="auth/logout" class="ml-2 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto pt-16 py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-bold text-gray-900">Your Forms</h1>
                    <a href="dashboard/create" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Create form</a>
                </div>
                <?php if ($alert): ?>
                    <div class="mb-4 rounded-md bg-green-50 p-4 border border-green-100">
                        <p class="text-sm text-green-800"><?php echo $alert; ?></p>
                    </div>
                <?php endif; ?>

                <div class="space-y-6">
                    <div>
                        <?php if (empty($forms)): ?>
                            <div class="bg-white rounded-lg border border-dashed border-gray-300 p-10 text-center text-gray-500">
                                No forms yet. Click “Create form” to get started.
                            </div>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($forms as $form): 
                                    $csvPath = getFormCSVPath($username, $form['slug']);
                                    $rows = readCSV($csvPath, $username);
                                    // Derive expected headers/types from config to align with current CSV format
                                    list($headers, $headerTypes) = getHeadersAndTypesFromConfig($form);
                                    // Map field name -> label for pretty display
                                    $labelByName = [];
                                    foreach (($form['fields'] ?? []) as $fdef) {
                                        $nm = $fdef['name'] ?? '';
                                        if ($nm !== '') { $labelByName[$nm] = $fdef['label'] ?? $nm; }
                                    }
                                    // Remove header row from CSV, if present in $rows
                                    if (!empty($rows)) { array_shift($rows); }
                                    // Search + pagination params (per form via namespaced GET arrays)
                                    $perPage = 20;
                                    $slugKey = $form['slug'];
                                    $qAll = $_GET['q'] ?? [];
                                    $pageAll = $_GET['page'] ?? [];
                                    $query = trim((string)($qAll[$slugKey] ?? ''));
                                    $page = (int)($pageAll[$slugKey] ?? 1);
                                    if ($page < 1) $page = 1;
                                    // Filter rows by query (case-insensitive substr match across any cell)
                                    $filtered = [];
                                    if ($query !== '') {
                                        foreach ($rows as $r) {
                                            $hit = false;
                                            foreach ($r as $cell) {
                                                if ($cell !== null && stripos((string)$cell, $query) !== false) { $hit = true; break; }
                                            }
                                            if ($hit) { $filtered[] = $r; }
                                        }
                                    } else {
                                        $filtered = $rows;
                                    }
                                    $total = count($filtered);
                                    $totalPages = max(1, (int)ceil($total / $perPage));
                                    if ($page > $totalPages) $page = $totalPages;
                                    $offset = ($page - 1) * $perPage;
                                    $dataRows = array_slice($filtered, $offset, $perPage);
                                    $from = $total ? $offset + 1 : 0;
                                    $to = min($offset + count($dataRows), $total);
                                    $shareUrl = $form['public_url'];
                                    // Base URL for links
                                    $baseUrl = web_base_url();
                                    $absoluteShare = abs_url($shareUrl);
                                    $apiEndpoint = $baseUrl . 'api/submit';
                                ?>
                                <div class="bg-white rounded-lg border border-gray-200">
                                    <div class="p-4 border-b border-gray-100">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($form['name']); ?></h3>
                                                <p class="text-xs text-gray-500">Slug: <span class="font-mono"><?php echo htmlspecialchars($form['slug']); ?></span></p>
                                            </div>
                                            <div class="text-right space-x-1">
                                                <a class="inline-flex items-center justify-center p-2 rounded border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100" href="<?php echo htmlspecialchars($shareUrl); ?>" target="_blank" title="Open Form" aria-label="Open Form">
                                                    <!-- external-link icon -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18m0 0v4.5M18 6l-7.5 7.5" />
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75H6A2.25 2.25 0 003.75 9v9A2.25 2.25 0 006 20.25h9a2.25 2.25 0 002.25-2.25v-2.25" />
                                                    </svg>
                                                </a>
                                                <a class="inline-flex items-center justify-center p-2 rounded border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100" href="<?php echo 'dashboard/edit?f=' . urlencode($form['slug']); ?>" title="Edit" aria-label="Edit">
                                                    <!-- pencil icon -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125L16.875 4.5" />
                                                    </svg>
                                                </a>
                                                <a class="inline-flex items-center justify-center p-2 rounded border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100" href="<?php echo 'dashboard/download-csv-decrypted?u=' . urlencode($username) . '&f=' . urlencode($form['slug']); ?>" title="Download CSV" aria-label="Download CSV">
                                                    <!-- download icon -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5L16.5 12M12 3v13.5" />
                                                    </svg>
                                                </a>
                                                <button type="button" class="inline-flex items-center justify-center p-2 rounded border border-gray-300 text-gray-700 bg-white hover:bg-gray-50" data-settings-target="settings-modal-<?php echo htmlspecialchars($form['slug']); ?>" title="Settings" aria-label="Settings">
                                                    <!-- settings cog (Heroicons Cog6Tooth) -->
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.38.79.78.93.398.14.854.006 1.114-.332l.993-.993a1.12 1.12 0 011.596-.118l.773.773c.39.39.44 1.005.118 1.453l-.993.993c-.338.26-.472.716-.332 1.114.14.4.506.71.93.78l.894.15c.542.09.94.56.94 1.11v1.094c0 .55-.398 1.02-.94 1.11l-.894.15c-.424.07-.79.38-.93.78-.14.398-.006.854.332 1.114l.993.993c.322.448.272 1.063-.118 1.453l-.773.773a1.12 1.12 0 01-1.596-.118l-.993-.993a1.12 1.12 0 00-1.114-.332c-.4.14-.71.506-.78.93l-.15.894c-.09.542-.56.94-1.11.94H11.45c-.55 0-1.02-.398-1.11-.94l-.15-.894a1.12 1.12 0 00-.78-.93c-.398-.14-.854-.006-1.114.332l-.993.993a1.12 1.12 0 01-1.596.118l-.773-.773a1.12 1.12 0 01.118-1.596l.993-.993c.338-.26.472-.716.332-1.114a1.12 1.12 0 00-.93-.78l-.894-.15A1.125 1.125 0 013 11.45V10.357c0-.55.398-1.02.94-1.11l.894-.15c.424-.07.79-.38.93-.78.14-.398.006-.854-.332-1.114L4.43 6.21a1.12 1.12 0 01-.118-1.596l.773-.773a1.12 1.12 0 011.596.118l.993.993c.26.338.716.472 1.114.332.4-.14.71-.506.78-.93l.15-.894z" />
                                                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    </svg>
                                                </button>
                                                <form method="post" class="inline" onsubmit="return confirm('Delete this form and all submissions?');">
                                                    <input type="hidden" name="delete_form" value="1" />
                                                    <input type="hidden" name="delete_slug" value="<?php echo htmlspecialchars($form['slug']); ?>" />
                                                    <button type="submit" class="inline-flex items-center justify-center p-2 rounded border border-red-200 text-red-700 bg-red-50 hover:bg-red-100" title="Delete" aria-label="Delete">
                                                        <!-- trash icon -->
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0115.916 21.75H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <!-- Table toolbar: search -->
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <form method="get" action="dashboard" class="flex items-center gap-2">
                                                <input type="text" name="q[<?php echo htmlspecialchars($form['slug']); ?>]" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search submissions" class="w-56 px-3 py-1.5 text-sm rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                                                <input type="hidden" name="page[<?php echo htmlspecialchars($form['slug']); ?>]" value="1" />
                                                <button class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700" type="submit">Search</button>
                                                <?php if ($query !== ''): ?>
                                                    <a href="dashboard" class="px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-800 bg-white hover:bg-gray-50">Clear</a>
                                                <?php endif; ?>
                                            </form>
                                            <div class="text-xs text-gray-600">Showing <?php echo $from; ?>–<?php echo $to; ?> of <?php echo $total; ?></div>
                                        </div>
                                        <!-- Settings Modal (hidden) -->
                                        <div id="settings-modal-<?php echo htmlspecialchars($form['slug']); ?>" class="hidden fixed inset-0 z-40 items-center justify-center">
                                          <div class="absolute inset-0 bg-black/30" data-close-modal></div>
                                          <div class="relative z-10 bg-white w-full max-w-2xl mx-auto rounded-lg shadow-lg border border-gray-200">
                                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                              <h4 class="text-sm font-semibold text-gray-900">Settings • <?php echo htmlspecialchars($form['name']); ?></h4>
                                              <button type="button" class="text-gray-500 hover:text-gray-700" data-close-modal>&times;</button>
                                            </div>
                                            <div class="p-4 text-sm">
                                              <div class="mb-3 flex items-center justify-between">
                                                <div>
                                                  <div class="text-xs uppercase text-gray-500">Privacy</div>
                                                  <div class="text-gray-800">This form is <span class="font-semibold"><?php echo !empty($form['private']) ? 'Private' : 'Public'; ?></span></div>
                                                  <p class="text-xs text-gray-500 mt-1">Private forms return 404 on the public page, but still accept API submissions.</p>
                                                </div>
                                                <form method="post" class="ml-4">
                                                  <input type="hidden" name="set_private" value="1" />
                                                  <input type="hidden" name="slug" value="<?php echo htmlspecialchars($form['slug']); ?>" />
                                                  <input type="hidden" name="private" value="0" />
                                                  <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                                    <input type="checkbox" name="private" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?php echo !empty($form['private']) ? 'checked' : ''; ?> onchange="this.form.submit()" />
                                                    <span>Private</span>
                                                  </label>
                                                </form>
                                              </div>
                                              <p class="mb-2"><span class="font-semibold">Share URL:</span> <a class="text-indigo-700 underline break-all" href="<?php echo htmlspecialchars($shareUrl); ?>" target="_blank"><?php echo htmlspecialchars($absoluteShare); ?></a></p>
                                              <p class="mb-2"><span class="font-semibold">API Key:</span> <span class="font-mono text-gray-800"><?php echo htmlspecialchars($form['api_key']); ?></span></p>
                                              <?php ob_start(); ?>
{
  "user": "<?php echo htmlspecialchars($username); ?>",
  "form": "<?php echo htmlspecialchars($form['slug']); ?>",
  "api_key": "<?php echo htmlspecialchars($form['api_key']); ?>",
  "data": {
<?php foreach ($form['fields'] as $i => $fld): ?>
    "<?php echo $fld['name']; ?>": <?php
        $t = $fld['type'] ?? '';
        if ($t === 'file') {
            echo '"data:image/png;base64,...."';
        } elseif ($t === 'select' || $t === 'radio') {
            $opt = ($fld['options'][0] ?? 'option');
            echo '"' . htmlspecialchars($opt) . '"';
        } elseif ($t === 'select_multiple' || $t === 'checkbox_group') {
            $o1 = $fld['options'][0] ?? 'optA';
            $o2 = $fld['options'][1] ?? 'optB';
            echo '["' . htmlspecialchars($o1) . '", "' . htmlspecialchars($o2) . '"]';
        } elseif ($t === 'checkbox') {
            echo 'true';
        } elseif ($t === 'textarea') {
            echo '"long text value"';
        } else {
            echo '"value"';
        }
    ?><?php echo $i < count($form['fields'])-1 ? ',' : ''; ?>
<?php endforeach; ?>
  }
}
<?php $__jsonBody = trim(ob_get_clean()); ?>

                                              <div class="rounded-xl shadow-sm border border-slate-800 bg-slate-900 text-slate-100 p-4 mt-3" data-api-tabs>
                                                <div class="flex items-center justify-between mb-2">
                                                  <div class="flex items-center gap-2">
                                                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                                                    <span class="text-sm font-semibold">Submit via API</span>
                                                  </div>
                                                  <div class="flex items-center gap-1">
                                                    <div class="inline-flex rounded-lg bg-slate-800/60 border border-slate-700 p-0.5">
                                                      <button type="button" data-tab="curl" class="px-2 py-1 rounded-md text-[11px] text-slate-300 hover:text-white">cURL</button>
                                                      <button type="button" data-tab="fetch" class="px-2 py-1 rounded-md text-[11px] text-slate-300 hover:text-white">Fetch</button>
                                                    </div>
                                                    <button type="button" data-copy class="ml-2 px-2 py-1 rounded-md bg-slate-800 border border-slate-700 text-[11px] text-slate-200 hover:bg-slate-700">Copy</button>
                                                  </div>
                                                </div>
                                                <p class="text-xs text-slate-300 mb-2">POST JSON to:</p>
                                                <code class="block bg-slate-800/80 border border-slate-700 rounded p-2 overflow-auto text-[11px]"><?php echo htmlspecialchars($apiEndpoint); ?></code>
<pre data-code="curl" class="mt-3 bg-slate-800/80 border border-slate-700 rounded p-3 text-[11px] leading-5 overflow-auto">curl -s -X POST \
  -H 'Content-Type: application/json' \
  -d '<?php echo $__jsonBody; ?>' \
  '<?php echo htmlspecialchars($apiEndpoint); ?>'</pre>
<pre data-code="fetch" class="hidden mt-3 bg-slate-800/80 border border-slate-700 rounded p-3 text-[11px] leading-5 overflow-auto">fetch('<?php echo htmlspecialchars($apiEndpoint); ?>', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify(<?php echo $__jsonBody; ?>)
}).then(r => r.json()).then(console.log).catch(console.error);</pre>
                                                <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-400">
                                                  <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 border border-slate-700">JSON</span>
                                                  <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 border border-slate-700">POST</span>
                                                  <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 border border-slate-700">HTTPS</span>
                                                </div>
                                              </div>
                                              <p class="text-xs text-gray-500 mt-2">For file fields, send a base64 string (raw or data URL). The server saves the file and stores a link in the CSV.</p>
                                              <div class="mt-4 text-right">
                                                <button type="button" class="px-3 py-1.5 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-900 text-sm" data-close-modal>Close</button>
                                              </div>
                                            </div>
                                          </div>
                                        </div>

                                        <div class="mt-3 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full text-sm">
                                                    <thead class="bg-indigo-50">
                                                        <tr>
                                                            <?php foreach ($headers as $h): ?>
                                                                <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-indigo-900"><?php echo htmlspecialchars($h); ?></th>
                                                            <?php endforeach; ?>
                                                            <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-indigo-900">Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100">
                                                        <?php if (empty($dataRows)): ?>
                                                            <tr>
                                                                <td class="px-4 py-6 text-gray-500" colspan="<?php echo count($headers) + 1; ?>">No submissions yet.</td>
                                                            </tr>
                                                        <?php else: ?>
                                                            <?php $__row_i = 0; foreach ($dataRows as $r): $__row_i++; ?>
                                                                <tr class="odd:bg-white even:bg-gray-50 hover:bg-indigo-50 cursor-pointer" data-open-row-modal="row-modal-<?php echo htmlspecialchars($form['slug']); ?>-<?php echo $__row_i; ?>">
                                                                    <?php foreach ($r as $idx => $cell): ?>
                                                                        <?php if (($headerTypes[$idx] ?? 'text') === 'file' && !empty($cell)): ?>
                                                                            <?php
                                                                                $val = trim((string)$cell);
                                                                                if (preg_match('~^https?://~i', $val)) {
                                                                                    $fileUrl = $val;
                                                                                } else {
                                                                                    $fileUrl = to_public_url($val);
                                                                                }
                                                                            ?>
                                                                            <td class="px-4 py-2.5 whitespace-nowrap max-w-xs truncate" title="<?php echo htmlspecialchars($fileUrl); ?>">
                                                                                <a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" class="text-indigo-700 underline break-all"><?php echo htmlspecialchars($fileUrl); ?></a>
                                                                            </td>
                                                                        <?php else: ?>
                                                                            <td class="px-4 py-2.5 text-gray-800 whitespace-nowrap max-w-xs truncate" title="<?php echo htmlspecialchars($cell); ?>"><?php echo htmlspecialchars($cell); ?></td>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                                                        <form method="post" onsubmit="return confirm('Delete this submission?');" class="inline">
                                                                            <input type="hidden" name="delete_row_data" value="1" />
                                                                            <input type="hidden" name="form_slug" value="<?php echo htmlspecialchars($form['slug']); ?>" />
                                                                            <input type="hidden" name="row_index" value="<?php echo $__row_i; ?>" />
                                                                            <button type="submit" class="inline-flex items-center justify-center p-1.5 rounded border border-red-200 text-red-700 bg-red-50 hover:bg-red-100" title="Delete row" aria-label="Delete row">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                                                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0115.916 21.75H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                                                </svg>
                                                                            </button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                                <!-- Row details modal -->
                                                                <div id="row-modal-<?php echo htmlspecialchars($form['slug']); ?>-<?php echo $__row_i; ?>" class="hidden fixed inset-0 z-40 items-center justify-center">
                                                                  <div class="absolute inset-0 bg-black/30" data-close-modal></div>
                                                                  <div class="relative z-10 bg-white w-full max-w-xl mx-auto rounded-lg shadow-lg border border-gray-200">
                                                                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                                                      <h4 class="text-sm font-semibold text-gray-900">Submission • <?php echo htmlspecialchars($form['name']); ?></h4>
                                                                      <button type="button" class="text-gray-500 hover:text-gray-700" data-close-modal>&times;</button>
                                                                    </div>
                                                                    <div class="p-4 text-sm space-y-3">
                                                                      <?php foreach ($headers as $idx => $h): 
                                                                            $val = $r[$idx] ?? '';
                                                                            $label = $labelByName[$h] ?? $h;
                                                                            $isFile = (($headerTypes[$idx] ?? 'text') === 'file');
                                                                            $displayId = 'rowval-' . htmlspecialchars($form['slug']) . '-' . $__row_i . '-' . $idx;
                                                                            if ($isFile && !empty($val)) {
                                                                                $vtrim = trim((string)$val);
                                                                                if (preg_match('~^https?://~i', $vtrim)) { $fileUrl = $vtrim; }
                                                                                else { $fileUrl = to_public_url($vtrim); }
                                                                            }
                                                                      ?>
                                                                      <div class="flex items-start justify-between gap-2">
                                                                        <div class="min-w-0">
                                                                          <div class="text-xs uppercase text-gray-500"><?php echo htmlspecialchars($label); ?></div>
                                                                          <div class="text-sm text-gray-900 break-words" id="<?php echo $displayId; ?>">
                                                                            <?php if ($isFile && !empty($val)): ?>
                                                                              <a class="text-indigo-700 underline break-all" href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank"><?php echo htmlspecialchars($fileUrl); ?></a>
                                                                            <?php else: ?>
                                                                              <?php echo htmlspecialchars($val); ?>
                                                                            <?php endif; ?>
                                                                          </div>
                                                                        </div>
                                                                        <button type="button" class="shrink-0 inline-flex items-center justify-center p-2 rounded border border-gray-300 text-gray-700 bg-white hover:bg-gray-50" title="Copy" aria-label="Copy" data-copy-target="<?php echo $displayId; ?>">
                                                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7.5V6A2.25 2.25 0 0110.25 3.75h7.5A2.25 2.25 0 0120 6v7.5A2.25 2.25 0 0117.75 15.75H16.5M6.75 8.25h7.5A2.25 2.25 0 0116.5 10.5v7.5a2.25 2.25 0 01-2.25 2.25h-7.5A2.25 2.25 0 014.5 18V10.5a2.25 2.25 0 012.25-2.25z" />
                                                                          </svg>
                                                                        </button>
                                                                      </div>
                                                                      <?php endforeach; ?>
                                                                      <div class="pt-2 text-xs text-gray-500">Submitted at: <?php echo htmlspecialchars($r[array_search('_submitted_at', $headers)] ?? ''); ?></div>
                                                                      <div class="pt-3 flex items-center justify-end gap-2">
                                                                        <button type="button" class="px-3 py-1.5 rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-900 text-sm" data-close-modal>Close</button>
                                                                        <form method="post" onsubmit="return confirm('Delete this submission?');">
                                                                          <input type="hidden" name="delete_row_data" value="1" />
                                                                          <input type="hidden" name="form_slug" value="<?php echo htmlspecialchars($form['slug']); ?>" />
                                                                          <input type="hidden" name="row_index" value="<?php echo $__row_i; ?>" />
                                                                          <button type="submit" class="px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700 text-sm">Delete</button>
                                                                        </form>
                                                                      </div>
                                                                    </div>
                                                                  </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <!-- Pagination -->
                                        <?php 
                                            // Build Prev/Next links preserving other query params
                                            $params = $_GET; 
                                            // Prev
                                            $prevPage = max(1, $page - 1);
                                            $paramsPrev = $params; $paramsPrev['page'][$slugKey] = $prevPage;
                                            $prevQs = http_build_query($paramsPrev);
                                            $prevHref = 'dashboard' . ($prevQs ? ('?' . $prevQs) : '');
                                            // Next
                                            $nextPage = min($totalPages, $page + 1);
                                            $paramsNext = $params; $paramsNext['page'][$slugKey] = $nextPage;
                                            $nextQs = http_build_query($paramsNext);
                                            $nextHref = 'dashboard' . ($nextQs ? ('?' . $nextQs) : '');
                                        ?>
                                        <div class="p-3 flex items-center justify-between text-sm">
                                            <button class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-50" onclick="location.href='<?php echo htmlspecialchars($prevHref); ?>'" <?php echo $page <= 1 ? 'disabled' : ''; ?>>Previous</button>
                                            <div class="text-gray-600">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
                                            <button class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-50" onclick="location.href='<?php echo htmlspecialchars($nextHref); ?>'" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>Next</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
</main>
</body>
<script>
// Settings modal toggling
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-settings-target]');
  if (btn) {
    const id = btn.getAttribute('data-settings-target');
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  }
  const row = e.target.closest('tr[data-open-row-modal]');
  if (row) {
    // Avoid triggering when clicking on inline buttons/links inside the row
    if (e.target.closest('a, button, input, svg')) return;
    const id = row.getAttribute('data-open-row-modal');
    const modal = document.getElementById(id);
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  }
  if (e.target.matches('[data-close-modal]')) {
    const modal = e.target.closest('[id^="settings-modal-"]');
    const rowModal = e.target.closest('[id^="row-modal-"]');
    const target = modal || rowModal;
    if (target) { target.classList.add('hidden'); target.classList.remove('flex'); }
  }
});
// API tabs + copy in settings dialog
(function(){
  document.querySelectorAll('[data-api-tabs]').forEach(card => {
    const tabButtons = card.querySelectorAll('[data-tab]');
    const codeBlocks = card.querySelectorAll('[data-code]');
    const copyBtn = card.querySelector('[data-copy]');
    const activate = (name) => {
      tabButtons.forEach(btn => {
        const active = btn.dataset.tab === name;
        btn.classList.toggle('bg-slate-700', active);
        btn.classList.toggle('text-white', active);
      });
      codeBlocks.forEach(pre => {
        const show = pre.dataset.code === name;
        pre.classList.toggle('hidden', !show);
      });
    };
    tabButtons.forEach(btn => btn.addEventListener('click', () => activate(btn.dataset.tab)));
    if (copyBtn) {
      copyBtn.addEventListener('click', () => {
        const active = card.querySelector('[data-code]:not(.hidden)');
        if (!active) return;
        const text = active.innerText;
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(() => {
            copyBtn.textContent = 'Copied';
            setTimeout(() => copyBtn.textContent = 'Copy', 1200);
          }).catch(()=>{});
        } else {
          const ta = document.createElement('textarea');
          ta.value = text; document.body.appendChild(ta); ta.select();
          try { document.execCommand('copy'); copyBtn.textContent='Copied'; setTimeout(()=>copyBtn.textContent='Copy',1200); } catch(e) {}
          ta.remove();
        }
      });
    }
    activate('curl');
  });
})();

// Copy buttons for row details
(function(){
  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-copy-target]');
    if (!btn) return;
    const id = btn.getAttribute('data-copy-target');
    const el = document.getElementById(id);
    if (!el) return;
    const text = el.innerText || '';
    const doDone = () => { btn.title = 'Copied'; setTimeout(()=>{ btn.title='Copy'; }, 1200); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(doDone).catch(()=>{});
    } else {
      const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); doDone(); } catch(e) {}
      ta.remove();
    }
  });
})();
</script>
</html>
