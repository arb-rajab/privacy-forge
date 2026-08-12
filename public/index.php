<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Serve pre-built static assets directly, matching Laravel's default
// front controller behaviour, so Vite build output under public/build
// is served without hitting the framework at all.
if ($uri = $_SERVER['REQUEST_URI'] ?? null) {
    $publicPath = __DIR__.parse_url($uri, PHP_URL_PATH);
    if ($uri !== '/' && file_exists($publicPath) && !is_dir($publicPath)) {
        return false;
    }
}

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
