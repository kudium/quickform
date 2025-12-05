<?php

// Utility helpers for URL and path normalization

function detect_scheme() {
    // Prefer HTTPS when indicated by env/proxy
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return 'https';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return 'https';
    return 'http';
}

function web_base_url() {
    $scheme = detect_scheme();
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Prefer SCRIPT_NAME to avoid PATH_INFO in PHP_SELF (which can include /index.php/...)
    $script = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    // Normalize any path under /public/... to app root
    if ($dir === '/public' || strpos($dir, '/public/') === 0) {
        $dir = '';
    }
    // Normalize grouped route roots (/dashboard, /auth, /public) to app root
    if (preg_match('~^/(dashboard|auth|public)(?:/.*)?$~', $dir)) {
        $dir = '';
    }
    // If the current script lives under /pages, treat its parent as the app base
    if ($dir === '/pages') { $dir = ''; }
    // Ensure exactly one trailing slash and no /index.php in base
    return $scheme . '://' . $host . ($dir && $dir !== '/' ? $dir : '') . '/';
}

function strip_fs_prefix($path) {
    $root = rtrim(str_replace('\\', '/', realpath(APP_ROOT)), '/');
    $norm = str_replace('\\', '/', (string)$path);
    $rel = $norm;
    if ($root && strpos($norm, $root) === 0) {
        $rel = substr($norm, strlen($root));
    } else {
        // Fallback for common shared hosting path leaks
        $pos = strpos($norm, 'public_html/');
        if ($pos !== false) {
            $rel = substr($norm, $pos + strlen('public_html/'));
        }
    }
    return ltrim($rel, '/');
}

function to_web_relative($path) {
    // If already an absolute URL, return as-is
    if (preg_match('~^https?://~i', (string)$path)) { return $path; }
    return strip_fs_prefix($path);
}

function to_public_url($path) {
    // If already an absolute URL, return as-is
    if (preg_match('~^https?://~i', (string)$path)) { return $path; }
    $rel = to_web_relative($path);
    return abs_url($rel);
}

// Build an absolute URL from a relative path, normalizing slashes.
function abs_url($rel) {
    return web_base_url() . ltrim((string)$rel, '/');
}

// ===== Upload validation helpers =====

function sanitize_extension($ext) {
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string)$ext));
}

function is_php_extension($ext) {
    $e = sanitize_extension($ext);
    $phpExts = ['php','phtml','pht','phps','php3','php4','php5','php7','php8','phar'];
    return in_array($e, $phpExts, true);
}

function is_php_mime($mime) {
    $m = strtolower(trim((string)$mime));
    if ($m === '') return false;
    $phpMimes = [
        'application/x-httpd-php',
        'application/php',
        'application/x-php',
        'text/x-php',
        'application/x-httpd-php-source'
    ];
    return in_array($m, $phpMimes, true);
}

function upload_extension_allowed($ext, $mime = null) {
    if (is_php_extension($ext)) return false;
    if ($mime !== null && is_php_mime($mime)) return false;
    return true;
}

function detect_mime_from_file($tmpPath) {
    if (!is_file($tmpPath)) return null;
    if (!function_exists('finfo_open')) return null;
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if (!$fi) return null;
    $mime = finfo_file($fi, $tmpPath);
    finfo_close($fi);
    return $mime ?: null;
}

function detect_mime_from_buffer($data) {
    if (!function_exists('finfo_open')) return null;
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    if (!$fi) return null;
    $mime = finfo_buffer($fi, $data);
    finfo_close($fi);
    return $mime ?: null;
}

// ===== CSV safety helpers =====

function safe_csv_value($v) {
    if (!is_string($v)) return $v;
    // Neutralize formula injection when opened in spreadsheets
    if ($v !== '' && preg_match('/^[=+\-@]/', $v)) {
        return "'" . $v;
    }
    return $v;
}
