<?php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


$success = '';
$error = '';

$departments = [];

$dept_result = $conn->query("SELECT id, name FROM nobledepartment ORDER BY name ASC");
if ($dept_result) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
}


if (!empty($_SESSION['reg_success'])) {
    $success = $_SESSION['reg_success'];
    unset($_SESSION['reg_success']); // clear agad
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email_prefix = trim($_POST['email_prefix'] ?? '');
    $email = $email_prefix . '@noble.com';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $dept_id = intval($_POST['department_id'] ?? 0);

    if (empty($name) || empty($email_prefix) || empty($password) || empty($confirm) || $dept_id === 0) {
        $error = 'All fields are required.';
    } elseif (!preg_match('/^[a-zA-Z0-9._\-]+$/', $email_prefix)) {
        $error = 'Email prefix contains invalid characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check duplicate email
        $check = $conn->prepare("SELECT id FROM noblerole WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'That email is already taken.';
            $check->close();
        } else {
            $check->close();

            $dept_stmt = $conn->prepare("SELECT name FROM nobledepartment WHERE id = ?");
            $dept_stmt->bind_param("i", $dept_id);
            $dept_stmt->execute();
            $dept_stmt->bind_result($dept_name);
            $dept_stmt->fetch();
            $dept_stmt->close();

            if (empty($dept_name)) {
                $error = 'Selected department is invalid.';
            } else {
                $role = $dept_name;
                $hashed = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conn->prepare("INSERT INTO noblerole (name, email, role, password) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $role, $hashed);

                if ($stmt->execute()) {
                    // Store success message in session THEN redirect
                    $_SESSION['reg_success'] = 'Account for "' . htmlspecialchars($name) . '" registered successfully as ' . htmlspecialchars($role) . '.';
                    header('Location: ' . BASE_URL . '/hr-registration-account');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                $stmt->close();
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>
</head>

<body>
  <div class="ml-60 min-h-screen bg-slate-100 p-6">
        <div class="max-w-lg mx-auto">
            <!-- Logo / Title -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-black shadow-lg mb-4">
                    <i class="fa-solid fa-circle-user text-4xl text-white"></i> 
                </div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Create an Account</h1>
                <p class="text-slate-500 text-sm mt-1">Fill in your details to register</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

                <!-- Alerts -->
                <?php if ($success): ?>
                    <div
                        class="flex items-start gap-3 bg-emerald-50 border-b border-emerald-200 text-emerald-700 px-5 py-4 text-sm">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="flex items-start gap-3 bg-red-50 border-b border-red-200 text-red-600 px-5 py-4 text-sm">
                        <svg class="w-5 h-5 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" action="" class="px-6 py-7 space-y-5">

                    <!-- Full Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                            <input type="text" id="name" name="name"
                                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Juan dela Cruz"
                                class="w-full pl-9 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-800
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                   placeholder-slate-400 transition">
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email_prefix" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <div
                            class="flex items-center border border-slate-300 rounded-lg overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 transition">
                            <span class="flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input type="text" id="email_prefix" name="email_prefix"
                                value="<?= htmlspecialchars($_POST['email_prefix'] ?? '') ?>"
                                placeholder="e.g. juan.delacruz"
                                class="flex-1 pl-2 pr-1 py-2.5 text-sm text-slate-800 bg-white focus:outline-none placeholder-slate-400">
                            <span
                                class="pr-3 py-2.5 text-sm font-medium text-slate-500 bg-slate-50 border-l border-slate-300 px-3 select-none">
                                @noble.com
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Only the prefix is needed — <strong>@noble.com</strong>
                            is
                            fixed.</p>
                    </div>

                    <!-- Department (Role) -->
                    <div>
                        <label for="department_id" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Department <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <select id="department_id" name="department_id" class="w-full pl-9 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-800
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                   bg-white appearance-none transition">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>" <?= (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if (empty($departments)): ?>
                                    <option value="" disabled>No departments found</option>
                                <?php endif; ?>
                            </select>
                            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Your department will be used as your role.</p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password" id="password" name="password" placeholder="Min. 6 characters" class="w-full pl-9 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-800
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                   placeholder-slate-400 transition">
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1.5">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password"
                                placeholder="Re-enter your password" class="w-full pl-9 pr-4 py-2.5 border border-slate-300 rounded-lg text-sm text-slate-800
                                   focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                                   placeholder-slate-400 transition">
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="border-t border-slate-100 pt-1"></div>

                    <!-- Submit -->
                    <button type="submit" class="w-full bg-black hover:bg-red-500 active:bg-indigo-800 text-white
                           font-semibold text-sm py-3 rounded-lg transition-colors duration-150 shadow-sm">
                        Register Account
                    </button>

                </form>
            </div>

            <p class="text-center text-xs text-slate-400 mt-5">
                All fields marked <span class="text-red-400">*</span> are required.
            </p>
        </div>
    </div>
</body>

</html>