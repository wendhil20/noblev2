<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unauthorized</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="text-center">
        <h1 class="text-4xl font-bold text-red-500 mb-2">403</h1>
        <p class="text-gray-600 mb-4">You don't have permission to access this page.</p>
        <a href="<?= BASE_URL ?>/loginadmin" 
           class="text-sm text-yellow-600 hover:underline">← Back to Login</a>
    </div>
</body>
</html>