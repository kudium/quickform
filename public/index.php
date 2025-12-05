<?php
require_once __DIR__ . '/../server/auth.php';
require_once __DIR__ . '/../server/forms.php';

// Front controller: supports both path-based routes and ?p=route.
// - With Apache/nginx rewrite or built-in server router.php: path-based (/dashboard)
// - Without rewrites: query param (?p=dashboard)
$route = $_GET['p'] ?? '';
if ($route === '' && isset($_SERVER['REQUEST_URI'])) {
    $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    $path = '/' . ltrim($uriPath, '/');
    if ($base && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $route = trim($path, '/');
}

$routes = [
    '' => null, // fall through to the landing page below
    // Dashboard (user-related)
    'dashboard' => 'pages/dashboard.php',
    'dashboard/create' => 'pages/create_form.php',
    'dashboard/edit' => 'pages/edit_form.php',
    'dashboard/admin' => 'pages/admin.php',
    'dashboard/admin/user' => 'pages/admin_user.php',
    'dashboard/download-csv' => 'pages/download_csv.php',
    'dashboard/download-csv-decrypted' => 'pages/download_csv_decrypted.php',
    // Auth
    'auth/login' => 'pages/login.php',
    'auth/register' => 'pages/register.php',
    'auth/logout' => 'pages/logout.php',
    'auth/forgot-password' => 'pages/forgot_password.php',
    'auth/reset-password' => 'pages/reset_password.php',
    // Public
    // Support both with and without '/public' prefix depending on docroot
    'public/form' => 'pages/public_form.php',
    'form' => 'pages/public_form.php',
    'public/api/submit' => 'api_submit.php',
    'api/submit' => 'api_submit.php',
    // Bench (optional; guarded in the page itself)
    'bench' => 'pages/bench.php',
    'public/bench' => 'pages/bench.php',
];

if ($route !== '' && isset($routes[$route]) && $routes[$route]) {
    require __DIR__ . '/' . $routes[$route];
    exit;
}

// Unknown non-empty route -> 404 page
if ($route !== '' && !isset($routes[$route])) {
    http_response_code(404);
    $nf_title = '404 — Page Not Found';
    $nf_message = 'No cookies here. This page took a detour.';
    $nf_cta_href = abs_url('dashboard');
    $nf_cta_label = 'Back to Dashboard';
    include __DIR__ . '/components/not_found.php';
    exit;
}

$loggedIn = isLoggedIn();
$username = getCurrentUser();

$alert = '';
$forms = [];

// Handle delete form (only when logged in)
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_form'])) {
    $slug = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['delete_slug'] ?? '');
    if ($slug) {
        if (deleteForm($username, $slug)) {
            $alert = 'Form "' . htmlspecialchars($slug) . '" deleted.';
        } else {
            $alert = 'Unable to delete form.';
        }
    }
}

// (Form creation moved to create_form.php)

$forms = $loggedIn ? loadForms($username) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickForm | Simple Forms & API</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Hero gradient + subtle noise */
        .hero-bg {
            background-image:
              radial-gradient(1200px 600px at 10% -10%, rgba(99,102,241,0.15), transparent 55%),
              radial-gradient(1000px 500px at 90% 0%, rgba(16,185,129,0.12), transparent 60%),
              linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }
        .noise {
            pointer-events: none;
            position: absolute; inset: 0;
            background-image: url('data:image/svg+xml;utf8,\
              <svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160">\
                <filter id="n">\
                  <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" stitchTiles="stitch"/>\
                  <feColorMatrix type="saturate" values="0"/>\
                  <feComponentTransfer>\
                    <feFuncA type="table" tableValues="0 0 0 0 0 0.05 0.08 0.1"/>\
                  </feComponentTransfer>\
                </filter>\
                <rect width="100%" height="100%" filter="url(%23n)"/>\
              </svg>');
            opacity: .25;
            mix-blend-mode: multiply;
        }
        /* Parallax helper */
        [data-parallax] { will-change: transform; transition: transform .08s linear; }
        /* Subtle float for blobs */
        @keyframes floaty { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-10px) } }
    </style>
</head>
<body class="bg-gray-50 min-h-screen overflow-x-hidden">
    <nav class="fixed top-0 inset-x-0 z-50 border-b border-white/60 bg-white/60 backdrop-blur supports-[backdrop-filter]:bg-white/50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-xl font-bold text-indigo-600">QuickForm</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                        <?php if ($loggedIn): ?>
                        <?php if (function_exists('isAdmin') && isAdmin()): ?>
                            <a href="dashboard/admin" class="px-3 py-2 text-sm font-medium rounded-md text-gray-900 bg-white border border-gray-300 hover:bg-gray-50">Admin</a>
                        <?php endif; ?>
                        <a href="dashboard" class="px-3 py-2 text-sm font-medium rounded-md text-gray-900 bg-white border border-gray-300 hover:bg-gray-50">Dashboard</a>
                        <a href="auth/logout" class="px-3 py-2 text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">Logout</a>
                    <?php else: ?>
                        <a href="auth/login" class="px-3 py-2 text-sm font-medium rounded-md text-gray-900 bg-white border border-gray-300 hover:bg-gray-50">Login</a>
                        <a href="auth/register" class="px-3 py-2 text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <?php 
            $baseUrl = web_base_url();
            $userForSample = $loggedIn ? $username : 'your-username';
            $samplePublic = $baseUrl . 'form?u=' . rawurlencode($userForSample) . '&f=sample-form';
            $apiEndpoint = $baseUrl . 'api/submit';
            $benchEnabled = getenv('BENCH_ENABLED') === '1';
        ?>
        <!-- Hero -->
        <section id="hero" class="relative hero-bg overflow-hidden pt-16">
            <div class="noise"></div>
            <div class="absolute -top-20 -left-10 w-72 h-72 rounded-full bg-indigo-400/20 blur-3xl animate-[floaty_10s_ease-in-out_infinite]"></div>
            <div class="absolute top-10 -right-10 w-80 h-80 rounded-full bg-emerald-400/20 blur-3xl animate-[floaty_12s_ease-in-out_infinite]"></div>
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-24 sm:py-28 lg:py-32">
                <div class="text-center">
                    <div class="flex items-center justify-center gap-2" data-parallax="-0.06">
                        <p class="inline-flex items-center gap-2 rounded-full bg-white/60 backdrop-blur px-3 py-1 text-xs text-gray-700 ring-1 ring-black/5 shadow-sm">
                            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                            Filesystem‑based forms · No DB required
                        </p>
                        <p class="inline-flex items-center gap-2 rounded-full bg-white/60 backdrop-blur px-3 py-1 text-xs text-gray-700 ring-1 ring-black/5 shadow-sm">
                            <span class="inline-block h-2 w-2 rounded-full bg-indigo-500"></span>
                            ≈ 5 ms local write
                        </p>
                    </div>
                    <h1 class="mt-6 text-4xl sm:text-5xl md:text-6xl font-extrabold tracking-tight text-gray-900" data-parallax="-0.12">
                        Collect data without the database
                    </h1>
                    <p class="mx-auto mt-4 max-w-2xl text-base sm:text-lg text-gray-600" data-parallax="-0.08">
                        Open‑source forms and API for hassle‑free submissions. Share public links, accept files, download encrypted CSVs, and manage everything from a simple dashboard.
                    </p>
                    <div class="mt-8 flex items-center justify-center gap-3" data-parallax="-0.04">
                        <?php if ($loggedIn): ?>
                            <a href="dashboard" class="px-5 py-3 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm font-semibold">Go to Dashboard</a>
                        <?php else: ?>
                            <a href="auth/register" class="px-5 py-3 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm text-sm font-semibold">Get started</a>
                            <a href="auth/login" class="px-5 py-3 rounded-lg border border-gray-300 bg-white text-gray-900 hover:bg-gray-50 shadow-sm text-sm font-semibold">Login</a>
                        <?php endif; ?>
                    </div>
                    <p class="mt-4 text-xs text-gray-600">Open source: <a href="https://github.com/kudium/quickform" target="_blank" class="text-indigo-700 underline">github.com/kudium/quickform</a> · MIT License</p>
                    <p class="mt-1 text-[11px] text-gray-500">Speed from local tests; production varies by network and disk.</p>
                </div>
            </div>
            <div class="absolute inset-x-0 bottom-0 h-24 pointer-events-none" aria-hidden="true">
                <div class="h-full w-full bg-gradient-to-b from-transparent to-gray-50"></div>
            </div>
        </section>

        <!-- Why it's fast -->
        <section class="py-6 bg-gray-50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                        <h2 class="text-base font-semibold text-gray-900 mb-2">Why it's fast</h2>
                        <ul class="list-disc pl-5 text-sm text-gray-700 space-y-1">
                            <li>No database round‑trip — direct filesystem append</li>
                            <li>Minimal stack and zero ORMs</li>
                            <li>Append‑only CSV with lightweight encryption</li>
                        </ul>
                    </div>
                    <div class="bg-white border border-indigo-200 rounded-xl shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-gray-900 mb-1">Compare</h3>
                        <p class="text-sm text-gray-700">Typical DB insert: <span class="font-semibold">20–80 ms</span></p>
                        <p class="text-sm text-gray-700">Our local write: <span class="font-semibold">~5 ms</span></p>
                        <?php if ($benchEnabled): ?>
                            <p class="mt-2 text-xs text-gray-600">Server benchmark: p50 <span id="bench-p50">—</span> ms · p95 <span id="bench-p95">—</span> ms</p>
                        <?php else: ?>
                            <p class="mt-2 text-xs text-gray-600">Enable BENCH_ENABLED=1 to show live server numbers.</p>
                        <?php endif; ?>
                        <p class="mt-2 text-[11px] text-gray-500">Numbers vary by network, disk, and hosting.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Content -->
        <section class="py-12 sm:py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-2">Create a form</h2>
                            <p class="text-sm text-gray-700 mb-2">Form name: <span class="font-mono">Sample Contact</span></p>
                            <ul class="list-disc pl-5 text-sm text-gray-700">
                                <li>Fields: <span class="font-mono">fullName</span> (text), <span class="font-mono">message</span> (textarea), <span class="font-mono">avatar</span> (file)</li>
                                <li>Plus <span class="font-mono">select</span> fields with custom options</li>
                            </ul>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-2">Security</h2>
                            <p class="text-sm text-gray-700">Your submissions are secured at rest. Each CSV line is encrypted using a key derived from your unique password (hash) and username, with AES‑256‑CBC and a random IV. The dashboard decrypts data after you log in; if a CSV is downloaded directly, it remains encrypted to maintain privacy.</p>
                        </div>
                        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
                            <h2 class="text-base font-semibold text-gray-900 mb-2">Share the public URL</h2>
                            <p class="text-sm text-gray-700">Anyone can fill your form at:</p>
                            <a href="<?php echo htmlspecialchars($samplePublic); ?>" target="_blank" class="text-indigo-700 underline break-all text-xs"><?php echo htmlspecialchars($samplePublic); ?></a>
                            <p class="text-xs text-gray-600 mt-3">Submissions append to CSV at <span class="font-mono">users/&lt;user&gt;/forms/&lt;slug&gt;/data.csv</span>. Files are saved under <span class="font-mono">uploads/</span> and linked in the CSV.</p>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <!-- Submit via API: dark code-focused card -->
                        <div class="rounded-xl shadow-sm border border-slate-800 bg-slate-900 text-slate-100 p-5" data-api-tabs>
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                                    <h2 class="text-base font-semibold">Submit via API</h2>
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
  -d '{
    "user": "<?php echo htmlspecialchars($userForSample); ?>",
    "form": "sample-form",
    "api_key": "YOUR_FORM_API_KEY",
    "data": {
      "fullName": "Jane Doe",
      "message": "Hello from the API!",
      "avatar": "data:image/png;base64,...."
    }
  }' \
  '<?php echo htmlspecialchars($apiEndpoint); ?>'</pre>
<pre data-code="fetch" class="hidden mt-3 bg-slate-800/80 border border-slate-700 rounded p-3 text-[11px] leading-5 overflow-auto">fetch('<?php echo htmlspecialchars($apiEndpoint); ?>', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    user: '<?php echo htmlspecialchars($userForSample); ?>',
    form: 'sample-form',
    api_key: 'YOUR_FORM_API_KEY',
    data: {
      fullName: 'Jane Doe',
      message: 'Hello from the API!',
      avatar: 'data:image/png;base64,....'
    }
  })
}).then(r => r.json()).then(console.log).catch(console.error);</pre>
                            <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-400">
                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 border border-slate-700">JSON</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 border border-slate-700">POST</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-800 border border-slate-700">HTTPS</span>
                            </div>
                        </div>
                        <!-- Lightweight DB: white card with table preview -->
                        <div class="bg-white border border-emerald-200 rounded-xl shadow-sm p-5">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                <h2 class="text-base font-semibold text-gray-900">Use it as a lightweight database</h2>
                            </div>
                            <p class="text-sm text-gray-700 mb-3">Treat a form like a collection and append records via the API. Great for quick app logging, contact capture, feedback, or analytics events without deploying a database.</p>
                            <div class="overflow-x-auto -mx-1">
                                <table class="min-w-full text-left text-xs border border-gray-200 rounded-md overflow-hidden">
                                    <thead class="bg-gray-50 text-gray-700">
                                        <tr>
                                            <th class="px-3 py-2 border-b border-gray-200">timestamp</th>
                                            <th class="px-3 py-2 border-b border-gray-200">level</th>
                                            <th class="px-3 py-2 border-b border-gray-200">message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="bg-white">
                                            <td class="px-3 py-2 border-t border-gray-200 font-mono text-[11px] text-gray-600">2025-01-01T12:00:00Z</td>
                                            <td class="px-3 py-2 border-t border-gray-200"><span class="inline-flex px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">info</span></td>
                                            <td class="px-3 py-2 border-t border-gray-200">User logged in</td>
                                        </tr>
                                        <tr class="bg-white">
                                            <td class="px-3 py-2 border-t border-gray-200 font-mono text-[11px] text-gray-600">2025-01-01T12:03:10Z</td>
                                            <td class="px-3 py-2 border-t border-gray-200"><span class="inline-flex px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">warn</span></td>
                                            <td class="px-3 py-2 border-t border-gray-200">Missing avatar file</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
<pre class="mt-3 bg-gray-50 border border-gray-200 rounded p-3 text-[11px] overflow-auto">POST <?php echo htmlspecialchars($apiEndpoint); ?>
{
  "user": "<?php echo htmlspecialchars($userForSample); ?>",
  "form": "app-logs",
  "api_key": "YOUR_FORM_API_KEY",
  "data": {
    "level": "info",
    "message": "User logged in",
    "context": "req-12345"
  }
}
</pre>
                            <p class="text-xs text-gray-600 mt-2">Each call appends a CSV row (encrypted at rest) you can view or download from the dashboard.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
      // Simple parallax: move elements with data-parallax by depth factor
      (function(){
        const hero = document.getElementById('hero');
        const nodes = hero ? hero.querySelectorAll('[data-parallax]') : [];
        const update = () => {
          if (!hero) return;
          const rect = hero.getBoundingClientRect();
          const visibleTop = Math.max(0, -rect.top);
          nodes.forEach(el => {
            const depth = parseFloat(el.getAttribute('data-parallax') || '0');
            el.style.transform = `translateY(${visibleTop * depth}px)`;
          });
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
      })();

      // API tabs + copy
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
          copyBtn && copyBtn.addEventListener('click', () => {
            const active = card.querySelector('[data-code]:not(.hidden)');
            if (!active) return;
            const text = active.innerText;
            if (navigator.clipboard && window.isSecureContext) {
              navigator.clipboard.writeText(text).then(() => {
                copyBtn.textContent = 'Copied';
                setTimeout(() => copyBtn.textContent = 'Copy', 1200);
              }).catch(()=>{});
            } else {
              // Fallback
              const ta = document.createElement('textarea');
              ta.value = text; document.body.appendChild(ta); ta.select();
              try { document.execCommand('copy'); copyBtn.textContent='Copied'; setTimeout(()=>copyBtn.textContent='Copy',1200); } catch(e) {}
              ta.remove();
            }
          });
          // Default tab
          activate('curl');
        });
      })();

      // Live bench fetch (if enabled server-side)
      (function(){
        var p50 = document.getElementById('bench-p50');
        var p95 = document.getElementById('bench-p95');
        if (!p50 || !p95) return;
        fetch('bench?mode=csv&n=30').then(function(r){ return r.json();}).then(function(d){
          if (!d || !d.ok) return;
          p50.textContent = d.p50_ms;
          p95.textContent = d.p95_ms;
        }).catch(function(){});
      })();
    </script>
</body>
</html>
