<?php
// user/ui-page/page-4/profile-main.php
include ROOT_PATH . '/network/connect.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — NobleHome</title>
  <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

<?php include ROOT_PATH . '/user/navigation/top.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-8 flex-1 w-full">

    <!-- Profile Card -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 md:p-8">
      <div class="flex flex-col md:flex-row items-center md:items-start gap-6">

        <!-- Avatar -->
        <div class="flex-shrink-0">
          <?php if (!empty($_SESSION['user_avatar'])): ?>
            <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>" alt="Profile Photo"
              class="w-24 h-24 md:w-28 md:h-28 rounded-full object-cover border-4 border-amber-400">
          <?php else: ?>
            <div class="w-24 h-24 md:w-28 md:h-28 rounded-full bg-amber-100 border-4 border-amber-400 flex items-center justify-center">
              <span class="text-amber-600 text-4xl font-bold">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
              </span>
            </div>
          <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="flex flex-col items-center md:items-start text-center md:text-left gap-1">

          <p class="text-xs font-semibold text-amber-500 uppercase tracking-widest">My Profile</p>

          <h2 class="text-2xl font-bold text-gray-900">
            <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
          </h2>

          <p class="text-sm text-gray-500">
            <?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>
          </p>

        </div>

      </div>

      <!-- Nav Cards -->
      <div class="border-t border-gray-100 mt-6 pt-6 grid grid-cols-2 md:grid-cols-4 gap-4">

        <!-- Orders -->
        <a href="<?= BASE_URL ?>/orders"
          class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-amber-300 hover:bg-amber-50 transition-all group">
          <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-all">
            <i class="fa-solid fa-box-open text-amber-600"></i>
          </div>
          <span class="text-sm font-medium text-gray-700 group-hover:text-amber-600">Orders</span>
        </a>

        <!-- Address -->
        <a href="<?= BASE_URL ?>/profilemap"
          class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-amber-300 hover:bg-amber-50 transition-all group">
          <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-all">
            <i class="fa-solid fa-map-marker-alt text-amber-600"></i>
          </div>
          <span class="text-sm font-medium text-gray-700 group-hover:text-amber-600">Address</span>
        </a>

        <!-- Receipt -->
        <a href="/user/receipts"
          class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-amber-300 hover:bg-amber-50 transition-all group">
          <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-all">
            <i class="fa-solid fa-receipt text-amber-600"></i>
          </div>
          <span class="text-sm font-medium text-gray-700 group-hover:text-amber-600">Receipt</span>
        </a>

        <!-- Recent View -->
        <a href="/user/recent"
          class="flex flex-col items-center gap-2 p-4 rounded-xl border border-gray-100 hover:border-amber-300 hover:bg-amber-50 transition-all group">
          <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition-all">
            <i class="fa-solid fa-clock text-amber-600"></i>
          </div>
          <span class="text-sm font-medium text-gray-700 group-hover:text-amber-600">Recent View</span>
        </a>

      </div>

    </div>

  </div>

  <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>

</html>