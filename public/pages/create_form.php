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
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_form'])) {
    $formName = trim($_POST['form_name'] ?? '');
    $labels = $_POST['field_label'] ?? [];
    $names  = $_POST['field_name'] ?? [];
    $types  = $_POST['field_type'] ?? [];
    $options = $_POST['field_options'] ?? [];
    $requireds = $_POST['field_required'] ?? [];
    $fields = [];
    for ($i=0; $i<count($labels); $i++) {
        $label = trim($labels[$i] ?? '');
        $name  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', trim($names[$i] ?? ''));
        $t = $types[$i] ?? '';
        $allowed = ['text','password','textarea','file','select','select_multiple','radio','checkbox','checkbox_group','email','number','url','tel','date','time','datetime-local'];
        $type  = in_array($t, $allowed, true) ? $t : 'text';
        if ($label && $name) {
            $field = ['label'=>$label,'name'=>$name,'type'=>$type];
            if (in_array($type, ['select','select_multiple','radio','checkbox_group'], true)) {
                $raw = $options[$i] ?? '';
                $opts = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)$raw)), function($v){ return $v !== ''; }));
                $field['options'] = $opts;
            }
            $isReq = isset($requireds[$i]) && ($requireds[$i] === '1' || $requireds[$i] === 'on');
            if ($isReq) { $field['required'] = true; }
            $fields[] = $field;
        }
    }
    if (!$formName || empty($fields)) {
        $alert = 'Form name and at least one valid field are required.';
    } else {
        $created = createForm($username, $formName, $fields);
        $alert = 'Form "' . htmlspecialchars($created['name']) . '" created successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
    <style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="dashboard" class="text-xl font-bold text-indigo-600">QuickForm</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="dashboard" class="mr-3 text-sm text-gray-700 hover:text-gray-900">Dashboard</a>
                    <a href="auth/logout" class="ml-4 px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150 ease-in-out">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="px-4 py-6 sm:px-0">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h1 class="text-2xl font-bold text-gray-900">Create a new form</h1>
                        <a href="dashboard" class="inline-flex items-center px-3 py-2 rounded-md border border-gray-300 bg-white text-gray-900 hover:bg-gray-50 text-sm font-medium">Back</a>
                    </div>

                    <?php if ($alert): ?>
                        <div class="mb-4 rounded-md <?php echo $created ? 'bg-green-50 border border-green-100' : 'bg-red-50 border border-red-100'; ?> p-4">
                            <p class="text-sm <?php echo $created ? 'text-green-800' : 'text-red-800'; ?>"><?php echo $alert; ?></p>
                            <?php if ($created): ?>
                                <div class="mt-3 text-sm">
                                    <?php $shareUrl = $created['public_url'];
                                        $absoluteShare = abs_url($shareUrl); ?>
                                    <a href="<?php echo htmlspecialchars($shareUrl); ?>" target="_blank" class="text-indigo-700 underline">Open Public Form</a>
                                    <span class="mx-2 text-gray-400">•</span>
                                    <a href="dashboard" class="text-gray-700 hover:underline">Go to Dashboard</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="space-y-5" id="createForm">
                        <input type="hidden" name="create_form" value="1" />
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Form name</label>
                            <input name="form_name" placeholder="e.g. Contact Us" required class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:border-transparent transition" />
                        </div>

                        <div class="mt-2">
                            <div class="mb-2">
                                <label class="block text-sm font-medium text-gray-700">Fields</label>
                            </div>
                            <p class="text-xs text-gray-500 mb-3">Types: text, password, textarea, file, select, select_multiple, radio, checkbox, checkbox_group, email, number, url, tel, date, time, datetime-local. Field name must be unique.</p>
                            <?php $builder_fields = []; $builder_editable_names = true; $builder_auto_add = true; include __DIR__ . '/../components/fields_builder.php'; ?>
                        </div>
                        <button type="submit" class="w-full mt-2 px-4 py-2.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Create form</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <script>
    function toTitleCase(str) {
        return str.replace(/\S+/g, (w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
    }
    </script>
</body>
</html>
