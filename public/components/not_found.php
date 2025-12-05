<?php
// Centered 404 component
// Optional vars before include:
// $nf_title, $nf_message, $nf_cta_href, $nf_cta_label
if (!function_exists('web_base_url')) {
  require_once __DIR__ . '/../../server/utils.php';
}
$title = isset($nf_title) ? (string)$nf_title : '404 — Page Not Found';
$message = isset($nf_message) ? (string)$nf_message : 'No cookies here. The page you’re looking for has wandered off.';
$ctaHref = isset($nf_cta_href) ? (string)$nf_cta_href : abs_url('dashboard');
$ctaLabel = isset($nf_cta_label) ? (string)$nf_cta_label : 'Back to Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo htmlspecialchars($title); ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
  <style>body{font-family:'Inter',sans-serif}</style>
  <link rel="icon" href="data:,">
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center px-4">
  <div class="text-center">
    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-100 text-yellow-700 text-2xl mb-4">🍪</div>
    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($title); ?></h1>
    <p class="text-gray-600 max-w-md mx-auto mb-6"><?php echo htmlspecialchars($message); ?></p>
    <div class="space-x-2">
      <a href="<?php echo htmlspecialchars($ctaHref); ?>" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700"><?php echo htmlspecialchars($ctaLabel); ?></a>
      <a href="/" class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-900 hover:bg-gray-50">Go Home</a>
    </div>
  </div>
</body>
</html>
