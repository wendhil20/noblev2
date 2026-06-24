<?php
// search-suggest.php
// Returns JSON suggestions for the navbar search box.
// Strategy: 1) LIKE match on name/category/description (ranked).
//           2) If not enough results, fall back to fuzzy (levenshtein) matching on product names.

include ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json');

$uploadUrl = BASE_URL . '/uploads/';
$q = trim($_GET['q'] ?? '');
$limit = 8;

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['suggestions' => []]);
    exit;
}

$results = [];
$seenIds = [];

// ── Step 1: LIKE-based match, ranked by relevance ───────────────────────────
// name starts-with > name contains > category/description contains
$likeExact = $q . '%';
$likeContains = '%' . $q . '%';

$sql = "
    SELECT
        p.id, p.name, p.imageproduct, p.category,
        MIN(v.pricesize) AS min_price,
        CASE
            WHEN p.name LIKE ? THEN 1
            WHEN p.name LIKE ? THEN 2
            WHEN p.category LIKE ? THEN 3
            ELSE 4
        END AS relevance
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    WHERE p.name LIKE ? OR p.category LIKE ? OR p.description LIKE ?
    GROUP BY p.id
    ORDER BY relevance ASC, p.name ASC
    LIMIT ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssssssi",
    $likeExact,
    $likeContains,
    $likeContains,
    $likeContains,
    $likeContains,
    $likeContains,
    $limit
);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $seenIds[$row['id']] = true;
    $results[] = $row;
}
$stmt->close();

// ── Step 2: Fuzzy fallback (levenshtein) if not enough matches ─────────────
// Helps when the typed text doesn't substring-match anything (typos, missing letters).
if (count($results) < $limit) {
    $remaining = $limit - count($results);

    $allRes = $conn->query("
        SELECT
            p.id, p.name, p.imageproduct, p.category,
            MIN(v.pricesize) AS min_price
        FROM nobleproduct p
        LEFT JOIN nobleproductcolor c ON c.product_id = p.id
        LEFT JOIN nobleproductvariant v ON v.color_id = c.id
        GROUP BY p.id
    ");

    $qLower = mb_strtolower($q);
    $candidates = [];

    while ($row = $allRes->fetch_assoc()) {
        if (isset($seenIds[$row['id']])) continue; // already matched via LIKE

        $nameLower = mb_strtolower($row['name']);

        // compare against the name itself, and against each word in the name
        // (so "soffa" can still match "Modern Sofa Set" via the "sofa" word)
        $distances = [levenshtein($qLower, $nameLower)];
        foreach (explode(' ', $nameLower) as $word) {
            if ($word === '') continue;
            $distances[] = levenshtein($qLower, $word);
        }
        $bestDistance = min($distances);

        // allow distance proportional to query length so short queries
        // don't match everything, and longer queries tolerate more typos
        $threshold = max(2, (int) floor(mb_strlen($qLower) / 2));

        if ($bestDistance <= $threshold) {
            $row['distance'] = $bestDistance;
            $candidates[] = $row;
        }
    }

    usort($candidates, fn($a, $b) => $a['distance'] <=> $b['distance']);
    $candidates = array_slice($candidates, 0, $remaining);

    foreach ($candidates as $c) {
        unset($c['distance']);
        $results[] = $c;
    }
}

// ── Format response ──────────────────────────────────────────────────────────
$suggestions = array_map(function ($row) use ($uploadUrl) {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'category' => $row['category'],
        'image' => !empty($row['imageproduct']) ? $uploadUrl . $row['imageproduct'] : null,
        'price' => $row['min_price'] !== null ? floatval($row['min_price']) : null,
    ];
}, $results);

echo json_encode(['suggestions' => $suggestions]);