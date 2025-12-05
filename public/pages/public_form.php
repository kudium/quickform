<?php
require_once __DIR__ . '/../../server/forms.php';

$u = $_GET['u'] ?? '';
$f = $_GET['f'] ?? '';
$form = ($u && $f) ? loadForm($u, $f) : null;
if (!$form || !empty($form['private'])) {
  http_response_code(404);
  $nf_title = 'Form not found';
  $nf_message = 'No cookies here. The form may be private or removed.';
  $nf_cta_href = abs_url('dashboard');
  $nf_cta_label = 'Back to Dashboard';
  include __DIR__ . '/../components/not_found.php';
  exit;
}

$submitted = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = handlePublicSubmit($u, $f, $_POST, $_FILES);
    if (($res['status'] ?? 500) === 200) {
        $submitted = true;
    } else {
        $error = $res['message'] ?? 'Unable to save submission.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($form['name']); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
  <style>body{font-family:'Inter',sans-serif}</style>
  <meta name="robots" content="noindex" />
  <link rel="icon" href="data:,">
  <style>.container{max-width:700px}</style>
  </head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md">
    <div class="bg-white shadow-lg rounded-2xl border border-gray-200 p-7">
      <h1 class="text-xl font-semibold text-gray-900 mb-4 text-center"><?php echo htmlspecialchars($form['name']); ?></h1>
      <!--<p class="text-xs text-gray-500 mb-6 text-center">&nbsp; Powered by QuickForm</p>-->

      <?php if ($submitted): ?>
        <div class="rounded-lg bg-green-50 p-4 mb-6 border border-green-100">
            <p class="text-green-800 text-sm text-center">Thank you! Your response has been recorded.</p>
        </div>
      <?php elseif ($error): ?>
        <div class="rounded-lg bg-red-50 p-4 mb-6 border border-red-100">
            <p class="text-red-800 text-sm text-center"><?php echo htmlspecialchars($error); ?></p>
        </div>
      <?php endif; ?>

      <?php if (!$submitted): ?>
      <form method="post" enctype="multipart/form-data" class="space-y-5">
        <?php foreach ($form['fields'] as $fld): ?>
            <div>
              <label class="block text-sm font-medium text-gray-800 mb-1">
                <?php echo htmlspecialchars($fld['label']); ?>
                <?php if (!empty($fld['required'])): ?>
                  <span class="text-red-600" title="Required">*</span>
                <?php endif; ?>
              </label>
              <?php if ($fld['type'] === 'textarea'): ?>
                <textarea name="<?php echo htmlspecialchars($fld['name']); ?>" rows="4" <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?>
                  class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition"></textarea>
              <?php elseif ($fld['type'] === 'select'): ?>
                <select name="<?php echo htmlspecialchars($fld['name']); ?>" <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?>
                  class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition">
                  <?php foreach (($fld['options'] ?? []) as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($fld['type'] === 'select_multiple'): ?>
                <select name="<?php echo htmlspecialchars($fld['name']); ?>[]" multiple <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?>
                  class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition">
                  <?php foreach (($fld['options'] ?? []) as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($fld['type'] === 'radio'): ?>
                <div class="space-y-2">
                  <?php foreach (($fld['options'] ?? []) as $opt): ?>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                      <input type="radio" name="<?php echo htmlspecialchars($fld['name']); ?>" value="<?php echo htmlspecialchars($opt); ?>" class="border-gray-300" <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?> />
                      <span><?php echo htmlspecialchars($opt); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php elseif ($fld['type'] === 'checkbox_group'): ?>
                <div class="space-y-2">
                  <?php foreach (($fld['options'] ?? []) as $opt): ?>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                      <input type="checkbox" name="<?php echo htmlspecialchars($fld['name']); ?>[]" value="<?php echo htmlspecialchars($opt); ?>" class="border-gray-300" <?php echo !empty($fld['required']) ? 'aria-required="true"' : ''; ?> />
                      <span><?php echo htmlspecialchars($opt); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php elseif ($fld['type'] === 'file'): ?>
                <input type="file" name="<?php echo htmlspecialchars($fld['name']); ?>" <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?>
                  class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent file:mr-3 file:px-3 file:py-2 file:rounded-md file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
              <?php elseif ($fld['type'] === 'checkbox'): ?>
                <input type="checkbox" name="<?php echo htmlspecialchars($fld['name']); ?>" value="1" <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?>
                  class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              <?php else: ?>
                <input type="<?php echo htmlspecialchars($fld['type']); ?>" name="<?php echo htmlspecialchars($fld['name']); ?>" <?php echo !empty($fld['required']) ? 'required aria-required="true"' : ''; ?>
                  class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition" />
              <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="w-full py-2.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Submit</button>
      </form>
      <?php endif; ?>
    </div>
    <p class="text-xs text-center text-gray-400 mt-6">&copy; <?php echo date('Y'); ?> QuickForm</p>
  </div>
</body>
</html>
