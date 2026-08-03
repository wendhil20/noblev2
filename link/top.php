<?php
// top.php
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

// ─── QR Secret ──────────────────────────────────────────────────────────────
define('QR_SECRET', 'warehouse_secret_2024');
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" />

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<!-- Sarili mong pinag-build na Tailwind CSS, hindi CDN script -->
<link rel="stylesheet" href="<?= BASE_URL ?>/link/css/tailwind.min.css">

<link rel="stylesheet" href="<?= BASE_URL ?>/link/css/custom-icons.css">


<link rel="icon" type="image/png" href="<?= BASE_URL ?>/icon/logo.png">

<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

<style>
     * {
    font-family: 'Plus Jakarta Sans', sans-serif;
  }

  /* Save/bookmark button: laging visible sa mobile, hover-only sa desktop */
  .save-btn {
      opacity: 1;
  }

  @media (min-width: 768px) {
      .save-btn {
          opacity: 0;
          transition: opacity 0.2s ease;
      }
      .group:hover .save-btn {
          opacity: 1;
      }
  }

  @keyframes placeholderSlideOut {
    0% {
        transform: translateY(0);
        opacity: 1;
    }
    100% {
        transform: translateY(-100%);
        opacity: 0;
    }
}

@keyframes placeholderSlideIn {
    0% {
        transform: translateY(100%);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

.placeholder-slide-out {
    animation: placeholderSlideOut 0.35s ease forwards;
}

.placeholder-slide-in {
    animation: placeholderSlideIn 0.35s ease forwards;
}
</style>