<?php
// network/notification-helper.php
// Reusable function para sa lahat ng notification inserts sa system

/**
 * Magpadala ng notification papunta sa isang role/position (broadcast)
 * o sa isang specific user (kung may $forUserId).
 *
 * @param mysqli      $conn         Active DB connection
 * @param string      $forRole      Role constant (e.g. ROLE_WAREHOUSE)
 * @param string|null $forPosition  Position constant, o null kung lahat ng position sa role
 * @param int|null    $forUserId    Specific account id, o null kung broadcast
 * @param string      $title        Notification title
 * @param string      $message      Notification message
 * @param string|null $link         URL link, o null kung walang link
 * @return bool                     True kung successful ang insert
 */


function sendNotification(
    mysqli $conn,
    string $forRole,
    ?string $forPosition,
    ?int $forUserId,
    string $title,
    string $message,
    ?string $link
): bool {
    $stmt = $conn->prepare("
        INSERT INTO noblenotification (for_role, for_position, for_user_id, title, message, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");

    if (!$stmt) {
        error_log('sendNotification prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param("ssisss", $forRole, $forPosition, $forUserId, $title, $message, $link);
    $ok = $stmt->execute();

    if (!$ok) {
        error_log('sendNotification execute failed: ' . $stmt->error);
    }

    $stmt->close();
    return $ok;
}