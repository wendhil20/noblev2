<?php
// user/auth/google-callback.php
// Step 2: Google redirects back here with ?code= — process login

require_once ROOT_PATH . '/user/auth/google-client.php';
require_once ROOT_PATH . '/network/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('nobleuser');
    session_start();
}

// ─── Guard: must have ?code ───────────────────────────────────────────────────
if (empty($_GET['code'])) {
    header('Location: ' . BASE_URL . '?login_error=1');
    exit;
}

// ─── Process callback ─────────────────────────────────────────────────────────
try {
    $client = makeGoogleClient();
    $token  = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        throw new Exception('Token error: ' . $token['error']);
    }

    $client->setAccessToken($token);

    $oauth2   = new Google\Service\Oauth2($client);
    $userInfo = $oauth2->userinfo->get();

    $googleId = $userInfo->getId();
    $name     = $userInfo->getName();
    $email    = $userInfo->getEmail();
    $avatar   = $userInfo->getPicture();

    // ─── Upsert into nobleuseraccount ─────────────────────────────────────────
    $stmt = $conn->prepare("
        INSERT INTO nobleuseraccount (google_id, name, email, avatar)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            name       = VALUES(name),
            email      = VALUES(email),
            avatar     = VALUES(avatar),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param('ssss', $googleId, $name, $email, $avatar);
    $stmt->execute();
    $stmt->close();

    // ─── Get the user's id ────────────────────────────────────────────────────
    $stmt = $conn->prepare("SELECT id FROM nobleuseraccount WHERE google_id = ?");
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $stmt->bind_result($userId);
    $stmt->fetch();
    $stmt->close();

    // ─── Set session ──────────────────────────────────────────────────────────
    $_SESSION['user_id']     = $userId;
    $_SESSION['user_name']   = $name;
    $_SESSION['user_email']  = $email;
    $_SESSION['user_avatar'] = $avatar;

    header('Location: ' . BASE_URL);
    exit;

} catch (Exception $e) {
    die('OAuth Error: ' . $e->getMessage());
}