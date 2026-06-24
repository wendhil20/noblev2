<?php
// user/auth/google.php
// Step 1: Redirect the user to Google's OAuth consent screen

require_once ROOT_PATH . '/user/auth/google-client.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('nobleuser');
    session_start();
}

// Already logged in — go home
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit;
}

$client  = makeGoogleClient();
$authUrl = $client->createAuthUrl();

header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;