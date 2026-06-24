<?php


include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        $error = 'Department name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO nobledepartment (name) VALUES (?)");
        $stmt->bind_param("s", $name);

        if ($stmt->execute()) {
            $success = 'Department "' . htmlspecialchars($name) . '" has been added successfully.';
        } else {
            $error = 'Failed to insert department. Please try again.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Department</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="ml-60 min-h-screen bg-slate-100 p-6">
        <div class="max-w-xl mx-auto mt-16 px-4">

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-md overflow-hidden">

                <!-- Header -->
                <div class="bg-indigo-600 px-6 py-5">
                    <h1 class="text-xl font-semibold text-white tracking-wide">Add New Department</h1>
                    <p class="text-indigo-200 text-sm mt-1">Fill in the form below to insert a department record.</p>
                </div>

                <!-- Alerts -->
                <div class="px-6 pt-5">
                    <?php if ($success): ?>
                        <div
                            class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div
                            class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Form -->
                <form method="POST" action="" class="px-6 py-6 space-y-5">

                    <!-- Department Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                            Department Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            placeholder="e.g. Human Resources" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-800
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                               transition placeholder-gray-400">
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white
                               text-sm font-semibold py-2.5 rounded-lg transition-colors duration-150">
                            Save Department
                        </button>
                        <button type="reset" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-600
                               text-sm font-semibold py-2.5 rounded-lg transition-colors duration-150">
                            Clear
                        </button>
                    </div>

                </form>
            </div>

            <!-- Footer note -->
            <p class="text-center text-xs text-gray-400 mt-6">
                All fields marked with <span class="text-red-400">*</span> are required.
            </p>

        </div>
    </div>
</body>

</html>