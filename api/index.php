<?php
// Vercel PHP Entry Point
// This file handles all requests and routes them to the appropriate handler

// Get the request URI
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Remove trailing slash if present
$path = rtrim($path, '/');

// If path is empty or is index, serve the home page
if ($path === '' || $path === '/index') {
    $_SERVER['SCRIPT_NAME'] = '/public/index.php';
    require __DIR__ . '/public/index.php';
    exit;
}

// Route static files directly
$static_extensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.webp', '.woff', '.woff2', '.ttf'];
foreach ($static_extensions as $ext) {
    if (str_ends_with($path, $ext)) {
        $file = __DIR__ . '/public' . $path;
        if (file_exists($file)) {
            $mime_types = [
                '.css' => 'text/css',
                '.js' => 'application/javascript',
                '.jpg' => 'image/jpeg',
                '.jpeg' => 'image/jpeg',
                '.png' => 'image/png',
                '.gif' => 'image/gif',
                '.svg' => 'image/svg+xml',
                '.ico' => 'image/x-icon',
                '.webp' => 'image/webp',
                '.woff' => 'font/woff',
                '.woff2' => 'font/woff2',
                '.ttf' => 'font/ttf',
            ];
            $mime = $mime_types[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            readfile($file);
            exit;
        }
    }
}

// Route PHP files in public folder
$public_path = __DIR__ . '/public' . $path . '.php';
if (file_exists($public_path)) {
    $_SERVER['SCRIPT_NAME'] = '/public' . $path . '.php';
    require $public_path;
    exit;
}

// Route API calls
$api_path = __DIR__ . '/api' . $path . '.php';
if (file_exists($api_path)) {
    $_SERVER['SCRIPT_NAME'] = '/api' . $path . '.php';
    require $api_path;
    exit;
}

// If nothing found, show 404
http_response_code(404);
echo "404 - Page not found";
