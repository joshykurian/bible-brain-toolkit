<?php
/**
 * Bible Brain Players - standalone proxy (one file + BibleBrainCore.php).
 *
 * Keeps your Bible Brain API key on the server, caches catalog data and rate-limits visitors.
 * The widget calls it like:  bbp-proxy.php?ep=catalog&country=IN&media=audio
 *
 * Setup: copy config.sample.php to config.php and put your free API key in it
 * (request one at https://4.dbt.io/api_key/request), or set the BIBLE_BRAIN_KEY environment variable.
 * Needs PHP 7.4+ and the cURL extension (or allow_url_fopen). Make the cache/ folder writable.
 */

$config = array(
    'api_key'           => getenv('BIBLE_BRAIN_KEY') ? getenv('BIBLE_BRAIN_KEY') : '',
    'cache_hours'       => 12,
    'allowed_countries' => '',
    'allowed_origins'   => getenv('BIBLE_BRAIN_ALLOWED_ORIGINS') ? getenv('BIBLE_BRAIN_ALLOWED_ORIGINS') : '*',
    'rate_limit'        => 300,
    'cache_dir'         => getenv('BIBLE_BRAIN_CACHE_DIR') ? getenv('BIBLE_BRAIN_CACHE_DIR') : __DIR__ . '/cache',
    'api_base'          => getenv('BIBLE_BRAIN_API_BASE') ? getenv('BIBLE_BRAIN_API_BASE') : '',
    'debug'             => false,
);
if (is_file(__DIR__ . '/config.php')) {
    $local = include __DIR__ . '/config.php';
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

require_once __DIR__ . '/BibleBrainCore.php';

function bbp_send($data, $status = 200, $maxAge = 0)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header($maxAge > 0 ? 'Cache-Control: public, max-age=' . (int) $maxAge : 'Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed = trim((string) $config['allowed_origins']);
if ($allowed === '*') {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin !== '' && in_array($origin, array_map('trim', explode(',', $allowed)), true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Accept');
    http_response_code(204);
    exit;
}

$ep = isset($_GET['ep']) ? preg_replace('/[^a-z]/', '', strtolower((string) $_GET['ep'])) : '';
$cache = new \BibleBrain\FileCache($config['cache_dir']);

if ($ep === 'status') {
    bbp_send(array('ok' => true, 'key_set' => trim((string) $config['api_key']) !== '', 'php' => PHP_VERSION));
}
if ($ep === 'flush' && isset($config['flush_token']) && $config['flush_token'] !== '' && isset($_GET['token']) && hash_equals((string) $config['flush_token'], (string) $_GET['token'])) {
    $cache->flush();
    bbp_send(array('ok' => true));
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
if (!\BibleBrain\RateLimiter::allow($cache, md5($ip), (int) $config['rate_limit'], 300)) {
    bbp_send(array('code' => 'bbp_rate', 'message' => 'Too many requests. Please wait a few minutes.'), 429);
}

$query = $_GET;
unset($query['ep']);

try {
    $core = new \BibleBrain\Core($config['api_key'], null, $cache, array(
        'cache_hours'       => $config['cache_hours'],
        'allowed_countries' => $config['allowed_countries'],
        'base'              => $config['api_base'],
    ));
    $maxAge = in_array($ep, array('countries', 'catalog', 'books', 'copyright'), true) ? 300 : 0;
    bbp_send($core->handle($ep, $query), 200, $maxAge);
} catch (\BibleBrain\ApiException $e) {
    bbp_send(array('code' => 'bbp_error', 'message' => $e->getMessage()), $e->status);
} catch (\Exception $e) {
    bbp_send(array('code' => 'bbp_error', 'message' => $config['debug'] ? $e->getMessage() : 'Server error.'), 500);
}
