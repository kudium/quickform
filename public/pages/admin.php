<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/forms.php';

if (!isLoggedIn() || !isAdmin()) {
    $baseUrl = web_base_url();
    header('Location: ' . $baseUrl . 'auth/login');
    exit;
}

// Collect users and analytics
$users = [];
$totalUsers = 0;

if (is_dir(USERS_DIR)) {
    foreach (scandir(USERS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $userDir = USERS_DIR . $entry;
        if (!is_dir($userDir)) continue;
        $configFile = $userDir . '/config.php';
        $email = '';
        if (is_file($configFile)) {
            $cfg = include($configFile);
            $email = $cfg['email'] ?? '';
        }
        // Count forms
        $formsDir = $userDir . '/' . FORMS_DIR_NAME;
        $formCount = 0;
        if (is_dir($formsDir)) {
            foreach (scandir($formsDir) as $f) {
                if ($f === '.' || $f === '..') continue;
                if (is_file($formsDir . '/' . $f . '/form.json')) {
                    $formCount++;
                }
            }
        }
        $users[] = [
            'username' => $entry,
            'email' => $email,
            'forms' => $formCount,
        ];
        $totalUsers++;
    }
}

// Sort by forms desc then username
usort($users, function($a, $b) {
    $cmp = ($b['forms'] <=> $a['forms']);
    if ($cmp !== 0) return $cmp;
    return strcmp($a['username'], $b['username']);
});

$currentUser = getCurrentUser();

// ===== Users search + pagination =====
$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$perPage = 20;

$filteredUsers = [];
if ($query !== '') {
  foreach ($users as $u) {
    $hay = strtolower(($u['username'] ?? '') . ' ' . ($u['email'] ?? ''));
    if (strpos($hay, strtolower($query)) !== false) { $filteredUsers[] = $u; }
  }
} else {
  $filteredUsers = $users;
}

$total = count($filteredUsers);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) { $page = $totalPages; }
$offset = ($page - 1) * $perPage;
$pagedUsers = array_slice($filteredUsers, $offset, $perPage);
$from = $total ? ($offset + 1) : 0;
$to = min($offset + count($pagedUsers), $total);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin • QuickForm</title>
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
          <span class="text-sm font-semibold text-gray-900">Admin</span>
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
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <div class="text-sm text-gray-500">Total users</div>
          <div class="text-3xl font-bold text-gray-900 mt-1"><?php echo (int)$totalUsers; ?></div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <div class="text-sm text-gray-500">Total forms</div>
          <div class="text-3xl font-bold text-gray-900 mt-1"><?php echo array_sum(array_column($users, 'forms')); ?></div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-5">
          <div class="text-sm text-gray-500">Admins</div>
          <div class="text-sm text-gray-900 mt-1">
            <?php
              $env = getenv('ADMIN_USERS');
              $admins = $env ? array_map('trim', explode(',', $env)) : ['admin'];
              echo htmlspecialchars(implode(', ', $admins));
            ?>
          </div>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between gap-3">
          <h2 class="text-lg font-semibold text-gray-900">Users</h2>
          <div class="flex items-center gap-3">
            <form method="get" action="dashboard/admin" class="flex items-center gap-2">
              <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search users" class="w-56 px-3 py-1.5 text-sm rounded-md border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
              <input type="hidden" name="page" value="1" />
              <button class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700" type="submit">Search</button>
              <?php if ($query !== ''): ?>
                <a href="dashboard/admin" class="px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-800 bg-white hover:bg-gray-50">Clear</a>
              <?php endif; ?>
            </form>
            <div class="text-xs text-gray-600">Showing <?php echo $from; ?>–<?php echo $to; ?> of <?php echo $total; ?></div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Forms</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <?php if (empty($pagedUsers)): ?>
                <tr>
                  <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="4">No users found.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($pagedUsers as $u): ?>
                <tr>
                  <td class="px-4 py-3 text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($u['username']); ?></td>
                  <td class="px-4 py-3 text-sm text-gray-700 break-all"><?php echo htmlspecialchars($u['email']); ?></td>
                  <td class="px-4 py-3 text-sm text-gray-900"><?php echo (int)$u['forms']; ?></td>
                  <td class="px-4 py-3 text-sm text-right">
                    <a class="inline-flex items-center px-3 py-1.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 text-xs font-medium" href="dashboard/admin/user?u=<?php echo rawurlencode($u['username']); ?>">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php 
          // Pagination links: preserve q
          $params = [];
          if ($query !== '') { $params['q'] = $query; }
          // Prev
          $prev = max(1, $page - 1); $paramsPrev = $params; $paramsPrev['page'] = $prev;
          $prevQs = http_build_query($paramsPrev); $prevHref = 'dashboard/admin' . ($prevQs ? ('?' . $prevQs) : '');
          // Next
          $next = min($totalPages, $page + 1); $paramsNext = $params; $paramsNext['page'] = $next;
          $nextQs = http_build_query($paramsNext); $nextHref = 'dashboard/admin' . ($nextQs ? ('?' . $nextQs) : '');
        ?>
        <div class="p-3 flex items-center justify-between text-sm">
          <button class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-50" onclick="location.href='<?php echo htmlspecialchars($prevHref); ?>'" <?php echo $page <= 1 ? 'disabled' : ''; ?>>Previous</button>
          <div class="text-gray-600">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
          <button class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 disabled:opacity-50" onclick="location.href='<?php echo htmlspecialchars($nextHref); ?>'" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>Next</button>
        </div>
      </div>
    </div>
  </main>

  <p class="text-xs text-center text-gray-400 mt-6">&copy; <?php echo date('Y'); ?> QuickForm</p>
</body>
</html>
