<?php
// user/auth/google-client.php
// Shared Google Client factory — included by google.php and google-callback.php

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

require_once ROOT_PATH . '/vendor/autoload.php';

// ─── Load .env if constants not yet defined ───────────────────────────────────
if (!defined('GOOGLE_CLIENT_ID')) {
    $envFile = ROOT_PATH . '/.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
    define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']     ?? '');
    define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
    define('BASE_URL',             $_ENV['APP_URL']               ?? 'http://localhost/noblev2');
}

// ─── Returns a configured Google Client ───────────────────────────────────────
function makeGoogleClient(): Google\Client {
    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(BASE_URL . '/callback');
    $client->addScope('email');
    $client->addScope('profile');
    $client->setPrompt('select_account'); // always show account picker
    return $client;
}