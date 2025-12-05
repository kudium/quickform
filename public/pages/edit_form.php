<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/forms.php';

if (!isLoggedIn()) {
    $baseUrl = web_base_url();
    header('Location: ' . $baseUrl . 'auth/login');
    exit;
}

$username = getCurrentUser();
$slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['f'] ?? '');
if (!$slug) {
    $baseUrl = web_base_url();
    header('Location: ' . $baseUrl . 'dashboard');
    exit;
}

$form = loadForm($username, $slug);
if (!$form) {
  http_response_code(404);
  $nf_title = 'Form not found';
  $nf_message = 'No cookies here. This form may not exist or you may not have access.';
  $nf_cta_href = abs_url('dashboard');
  $nf_cta_label = 'Back to Dashboard';
  include __DIR__ . '/../components/not_found.php';
  exit;
}

$alert = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_form'])) {
    $newName = trim($_POST['form_name'] ?? '');
    // Two modes: legacy partial update, or builder-style full fields array
    $fieldNames = $_POST['field_name'] ?? null;
    if (is_array($fieldNames)) {
        // Builder mode: reconstruct full fields list (supports add/remove)
        $labels  = $_POST['field_label'] ?? [];
        $types   = $_POST['field_type'] ?? [];
        $options = $_POST['field_options'] ?? [];
        $requireds = $_POST['field_required'] ?? [];
        $fields = [];
        $allowed = getAllowedFieldTypes();
        $count = max(count($labels), count($fieldNames), count($types));
        for ($i = 0; $i < $count; $i++) {
            $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim((string)($fieldNames[$i] ?? '')));
            $label = trim((string)($labels[$i] ?? ''));
            $t = (string)($types[$i] ?? 'text');
            $type = in_array($t, $allowed, true) ? $t : 'text';
            if ($name === '' || $label === '') { continue; }
            $field = ['name' => $name, 'label' => $label, 'type' => $type];
            $isReq = isset($requireds[$i]) && ($requireds[$i] === '1' || $requireds[$i] === 'on');
            if ($isReq) { $field['required'] = true; }
            if (in_array($type, ['select','select_multiple','radio','checkbox_group'], true)) {
                $raw = (string)($options[$i] ?? '');
                $opts = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw)), function($v){ return $v !== ''; }));
                $field['options'] = $opts;
            }
            $fields[] = $field;
        }
        // Save structure and rewrite CSV headers/rows accordingly
        $updated = updateFormStructure($username, $slug, $newName ?: $form['name'], $fields);
        if ($updated) {
            $form = $updated;
            $saved = true;
            $alert = 'Form updated. CSV headers were aligned to the new fields.';
        } else {
            $alert = 'Unable to update form structure.';
        }
    } else {
        // Legacy path: update labels/types/required only
        $labels = $_POST['label'] ?? [];
        $types  = $_POST['type'] ?? [];
        $required = $_POST['required'] ?? [];
        $labelMap = [];
        $typeMap = [];
        foreach ($form['fields'] as $fld) {
            $name = $fld['name'];
            if (isset($labels[$name])) {
                $labelMap[$name] = trim($labels[$name]);
            }
            if (isset($types[$name])) {
                $typeMap[$name] = trim($types[$name]);
            }
        }
        $updated = updateFormFields($username, $slug, $newName ?: $form['name'], $labelMap, $typeMap, $required);
        if ($updated) {
            $form = $updated;
            $saved = true;
            $alert = 'Form updated successfully.';
        } else {
            $alert = 'Unable to update form.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Form • QuickForm</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
  <style>body{font-family:'Inter',sans-serif}</style>
  <link rel="icon" href="data:,">
</head>
<body class="bg-gray-50 min-h-screen">
  <nav class="bg-white shadow">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <div class="flex items-center gap-6">
          <a href="dashboard" class="text-xl font-bold text-indigo-600">QuickForm</a>
          <span class="text-sm text-gray-900">Edit Form</span>
        </div>
        <div class="flex items-center gap-3">
          <a href="dashboard" class="px-3 py-2 text-sm rounded-md border border-gray-300 bg-white text-gray-900 hover:bg-gray-50">Back</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">
      <div class="bg-white rounded-lg border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($form['name']); ?></h1>
        <p class="text-xs text-gray-500 mb-4">Slug: <span class="font-mono"><?php echo htmlspecialchars($form['slug']); ?></span></p>

        <?php if ($alert): ?>
          <div class="mb-4 rounded-md <?php echo $saved ? 'bg-green-50 border border-green-100' : 'bg-red-50 border border-red-100'; ?> p-4">
            <p class="text-sm <?php echo $saved ? 'text-green-800' : 'text-red-800'; ?>"><?php echo htmlspecialchars($alert); ?></p>
          </div>
        <?php endif; ?>

        <form method="post" class="space-y-5" id="editForm">
          <input type="hidden" name="save_form" value="1" />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Form name</label>
            <input name="form_name" value="<?php echo htmlspecialchars($form['name']); ?>" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Fields</label>
            <p class="text-xs text-gray-500 mb-2">Add, remove, or edit fields.</p>
            <?php $builder_fields = $form['fields'] ?? []; $builder_editable_names = false; $builder_auto_add = false; include __DIR__ . '/../components/fields_builder.php'; ?>
          </div>

          <div class="flex items-center justify-end gap-3">
            <a href="dashboard" class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-900 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </main>
  <p class="text-xs text-center text-gray-400 mt-6">&copy; <?php echo date('Y'); ?> QuickForm</p>
  
</body>
</html>
