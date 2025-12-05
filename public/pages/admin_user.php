<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/forms.php';

if (!isLoggedIn() || !isAdmin()) {
    $baseUrl = web_base_url();
    header('Location: ' . $baseUrl . 'auth/login');
    exit;
}

$username = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['u'] ?? '');
if (!$username || !is_dir(USERS_DIR . $username)) {
    http_response_code(404);
    $nf_title = 'User not found';
    $nf_message = 'No cookies here. That user might have left the chat.';
    $nf_cta_href = abs_url('admin');
    $nf_cta_label = 'Back to Admin';
    include __DIR__ . '/../components/not_found.php';
    exit;
}

$config = [];
$email = '';
$cfgFile = USERS_DIR . $username . '/config.php';
if (is_file($cfgFile)) {
    $config = include($cfgFile);
    $email = $config['email'] ?? '';
}

$forms = loadForms($username);

// Helper to get absolute public URL
// Base URL for public links
$baseUrl = web_base_url();

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin • User • <?php echo htmlspecialchars($username); ?></title>
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
          <a href="dashboard" class="text-sm text-gray-700 hover:text-gray-900">Dashboard</a>
          <a href="dashboard/admin" class="text-sm text-gray-700 hover:text-gray-900">Admin</a>
          <span class="text-sm font-semibold text-gray-900">User</span>
        </div>
        <div class="flex items-center">
          <span class="text-gray-700 mr-4">Welcome, <?php echo htmlspecialchars($currentUser); ?></span>
          <a href="auth/logout" class="ml-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto pt-16 py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0 space-y-6">
      <div class="bg-white border border-gray-200 rounded-lg p-5">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($username); ?></h1>
            <div class="text-sm text-gray-600">Email: <span class="break-all"><?php echo htmlspecialchars($email); ?></span></div>
          </div>
          <div class="text-sm text-gray-600"><?php echo count($forms); ?> forms</div>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">Forms</h2>
          <div class="text-sm text-gray-600"><?php echo count($forms); ?> total</div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submissions</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Public</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($forms as $form): ?>
                <?php 
                  $slug = $form['slug'];
                  $csvPath = getFormCSVPath($username, $slug);
                  $rows = readCSV($csvPath, $username);
                  $submissionCount = max(count($rows) - 1, 0);
        $publicRel = $form['public_url'] ?? ('/form?u=' . rawurlencode($username) . '&f=' . rawurlencode($slug));
                  $publicAbs = abs_url($publicRel);
                ?>
                <tr>
                  <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($form['name'] ?? $slug); ?></td>
                  <td class="px-4 py-3 text-sm text-gray-700 font-mono"><?php echo htmlspecialchars($slug); ?></td>
                  <td class="px-4 py-3 text-sm text-gray-700"><?php echo isset($form['created_at']) ? date('Y-m-d H:i', $form['created_at']) : '-'; ?></td>
                  <td class="px-4 py-3 text-sm text-gray-900"><?php echo (int)$submissionCount; ?></td>
                  <td class="px-4 py-3 text-sm text-right">
                    <a class="text-indigo-700 underline break-all" href="<?php echo htmlspecialchars($publicAbs); ?>" target="_blank">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <p class="text-xs text-center text-gray-400 mt-6">&copy; <?php echo date('Y'); ?> QuickForm</p>
</body>
</html>
