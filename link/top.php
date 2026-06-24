<?php
// top.php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// ─── QR Secret ──────────────────────────────────────────────────────────────
define('QR_SECRET', 'warehouse_secret_2024');
?>

<!-- link this page to cdn-->
<script src="https://cdn.tailwindcss.com"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

<link rel="icon" type="image/png" href="<?= BASE_URL ?>/icon/logo.png">



<style>
     * {
    font-family: 'Plus Jakarta Sans', sans-serif;
  }
</style>
