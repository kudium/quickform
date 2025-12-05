<?php
// Central bootstrap for server-side logic.
// Defines base paths and shared configuration.

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Users directory (filesystem-based storage)
if (!defined('USERS_DIR')) {
    define('USERS_DIR', APP_ROOT . '/users/');
}

// Ensure users directory exists
if (!is_dir(USERS_DIR)) {
    @mkdir(USERS_DIR, 0777, true);
}

// Session is started lazily in auth helpers where required.

