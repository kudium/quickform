<?php
require_once __DIR__ . '/../../server/auth.php';
require_once __DIR__ . '/../../server/utils.php';

$message = '';
$username = $_GET['user'] ?? '';
$token = $_GET['token'] ?? '';
$validToken = false;

if ($username && $token) {
    if (verifyResetToken($username, $token)) {
        $validToken = true;
    } else {
        $message = "Invalid or expired password reset token.";
    }
} else {
    $message = "Missing reset token.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    
    $result = resetPassword($username, $token, $newPassword);
    $message = $result['message'];
    if ($result['success']) {
        // Redirect to login or show success
        $baseUrl = web_base_url();
        header('Location: ' . $baseUrl . 'auth/login?reset=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Auth System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <base href="<?php echo htmlspecialchars(web_base_url()); ?>">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Set new password
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Please enter your new password below.
            </p>
        </div>

        <?php if ($message): ?>
            <div class="rounded-md bg-red-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <!-- Heroicon name: solid/x-circle -->
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Error
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p><?php echo htmlspecialchars($message); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
        <form class="mt-8 space-y-6" method="POST">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="password" class="sr-only">New Password</label>
                    <input id="password" name="password" type="password" required class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="New Password">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <!-- Heroicon name: solid/lock-closed -->
                        <svg class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400 transition ease-in-out duration-150" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Update Password
                </button>
            </div>
        </form>
        <?php endif; ?>
        
        <div class="text-center">
            <a href="auth/login" class="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 ease-in-out">
                Back to Login
            </a>
        </div>
    </div>
</body>
</html>
