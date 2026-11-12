<?php
/**
 * Development router for the PHP built-in server.
 *
 * Serves real static files directly so CSS, JS, images, and other assets
 * keep their correct content types. Falls back to the main app entry point
 * for site routes like "/" when no file exists.
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);
$file = __DIR__ . $path;

if (is_dir($file) && is_file($file . '/index.php')) {
    require $file . '/index.php';
    return true;
}

if ($path === '/kindadmin' || $path === '/kindadmin/') {
    header('Location: /Frontend/admin/login.php', true, 302);
    exit;
}

if ($path === '/dashboard' || $path === '/dashboard/' || $path === '/customer' || $path === '/customer/' || $path === '/my-account') {
    require __DIR__ . '/Frontend/pages/dashboard.php';
    return true;
}

if ($path === '/login' || $path === '/login/') {
    require __DIR__ . '/Frontend/pages/login.php';
    return true;
}

if ($path === '/register' || $path === '/register/') {
    require __DIR__ . '/Frontend/pages/register.php';
    return true;
}

if ($path === '/logout' || $path === '/logout/') {
    require __DIR__ . '/Frontend/pages/logout.php';
    return true;
}

if ($path === '/admin' || $path === '/admin/') {
    require __DIR__ . '/Frontend/admin/dashboard.php';
    return true;
}

if ($path === '/admin/customers' || $path === '/admin/customers/') {
    require __DIR__ . '/Frontend/admin/customers.php';
    return true;
}

if (str_starts_with($path, '/admin/')) {
    $adminFile = __DIR__ . '/Frontend' . $path;
    if (is_file($adminFile . '.php')) {
        require $adminFile . '.php';
        return true;
    }
}

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
