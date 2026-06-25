<?php
// user/navigation/system-notifications-functions.php

// ─── Kunin lahat ng delivered/picked-up items na may o walang review pa ───
function fetchDeliveredItemsForReview($conn, $userId)
{
    $sql = "
        SELECT
            pl.id AS order_id,
            pl.nhccreference,
            pl.delivery_method,
            pl.created_at AS order_created_at,
            pi.id AS order_item_id,
            pi.product_id,
            pi.product_name,
            pi.colorname,
            pi.sizename,
            pi.quantity,
            r.id AS review_id,
            r.rating,
            r.comment,
            r.created_at AS review_created_at
        FROM noblepaidproductlist pl
        INNER JOIN noblepaidproductitems pi ON pi.order_id = pl.id
        LEFT JOIN noblereview r ON r.order_item_id = pi.id
        WHERE pl.user_id = ?
          AND (
                (pl.delivery_method = 'pickup' AND EXISTS (
                    SELECT 1 FROM nobleordertracking ot
                    WHERE ot.order_id = pl.id AND ot.current_step >= 3
                ))
                OR
                (pl.delivery_method != 'pickup' AND EXISTS (
                    SELECT 1 FROM nobledeliverybooking db
                    WHERE db.order_id = pl.id AND db.status = 'delivered'
                ))
              )
        ORDER BY pl.created_at DESC, pi.id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    return $items;
}

// ─── Insert o update ng review (UPSERT — kasi unique key sa order_item_id) ───
function saveReview($conn, $userId, $orderId, $orderItemId, $productId, $rating, $comment)
{
    $sql = "
        INSERT INTO noblereview (user_id, order_id, order_item_id, product_id, rating, comment)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            comment = VALUES(comment),
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiiiis', $userId, $orderId, $orderItemId, $productId, $rating, $comment);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}