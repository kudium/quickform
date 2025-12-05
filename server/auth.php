<?php
// Server-side auth and user management
require_once __DIR__ . '/bootstrap.php';

// Ensure users directory exists
if (!is_dir(USERS_DIR)) {
    @mkdir(USERS_DIR, 0777, true);
}

function registerUser($username, $password, $email) {
    // Basic validation
    if (empty($username) || empty($password) || empty($email)) {
        return ['success' => false, 'message' => 'Username, password, and email are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format.'];
    }

    // Sanitize username to be safe for filesystem
    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    if ($safeUsername !== $username) {
         return ['success' => false, 'message' => 'Username contains invalid characters.'];
    }

    $userDir = USERS_DIR . $safeUsername;

    if (is_dir($userDir)) {
        return ['success' => false, 'message' => 'User already exists.'];
    }
    
    // Check if email is already used (inefficient but necessary for file-based)
    if (findUserByEmail($email)) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }

    if (!mkdir($userDir, 0777, true)) {
        return ['success' => false, 'message' => 'Failed to create user directory.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    // Store email in config
    $configContent = "<?php\nreturn ['password' => '$hashedPassword', 'email' => '$email'];\n";

    if (file_put_contents($userDir . '/config.php', $configContent) === false) {
        return ['success' => false, 'message' => 'Failed to save user configuration.'];
    }

    return ['success' => true, 'message' => 'User registered successfully.'];
}

function loginUser($username, $password) {
     if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required.'];
    }

    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    $userDir = USERS_DIR . $safeUsername;
    $configFile = $userDir . '/config.php';

    if (!is_dir($userDir) || !file_exists($configFile)) {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }

    $config = include($configFile);
    
    if (password_verify($password, $config['password'])) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user'] = $safeUsername;
        return ['success' => true, 'message' => 'Login successful.'];
    } else {
        return ['success' => false, 'message' => 'Invalid username or password.'];
    }
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return $_SESSION['user'];
    }
    return null;
}

// Simple role check for admin access
// Configure admins via environment variable ADMIN_USERS (comma-separated),
// otherwise defaults to username 'admin'.
function isAdmin() {
    $user = getCurrentUser();
    if (!$user) return false;
    $env = getenv('ADMIN_USERS');
    $admins = $env ? array_map('trim', explode(',', $env)) : ['admin'];
    return in_array($user, $admins, true);
}

function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_destroy();
}

function findUserByEmail($email) {
    $users = scandir(USERS_DIR);
    foreach ($users as $user) {
        if ($user === '.' || $user === '..') continue;
        
        $configFile = USERS_DIR . $user . '/config.php';
        if (file_exists($configFile)) {
            $config = include($configFile);
            if (isset($config['email']) && $config['email'] === $email) {
                return $user; // Return username
            }
        }
    }
    return null;
}

function generateResetToken($username) {
    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    $userDir = USERS_DIR . $safeUsername;
    $configFile = $userDir . '/config.php';
    
    if (!file_exists($configFile)) return false;
    
    $config = include($configFile);
    $token = bin2hex(random_bytes(16));
    $expiry = time() + 3600; // 1 hour expiry
    
    $config['reset_token'] = $token;
    $config['reset_expiry'] = $expiry;
    
    // Re-save config
    $content = "<?php\nreturn " . var_export($config, true) . ";\n";
    file_put_contents($configFile, $content);
    
    return $token;
}

function verifyResetToken($username, $token) {
    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    $userDir = USERS_DIR . $safeUsername;
    $configFile = $userDir . '/config.php';
    
    if (!file_exists($configFile)) return false;
    
    $config = include($configFile);
    
    if (isset($config['reset_token']) && 
        $config['reset_token'] === $token && 
        isset($config['reset_expiry']) && 
        $config['reset_expiry'] > time()) {
        return true;
    }
    
    return false;
}

function resetPassword($username, $token, $newPassword) {
    if (!verifyResetToken($username, $token)) {
        return ['success' => false, 'message' => 'Invalid or expired token.'];
    }
    
    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    $userDir = USERS_DIR . $safeUsername;
    $configFile = $userDir . '/config.php';
    
    $config = include($configFile);
    $oldHash = $config['password'] ?? null;
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    // Re-encrypt user CSVs before writing new hash (lazy-load to avoid circular requires)
    require_once __DIR__ . '/forms.php';
    if ($oldHash) {
        reencryptUserCSVs($safeUsername, $oldHash, $newHash);
    }
    $config['password'] = $newHash;
    
    // Remove token
    unset($config['reset_token']);
    unset($config['reset_expiry']);
    
    $content = "<?php\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($configFile, $content)) {
        return ['success' => true, 'message' => 'Password updated successfully.'];
    }
    
    return ['success' => false, 'message' => 'Failed to update password.'];
}

