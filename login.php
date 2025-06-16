<?php
// Include the database connection file
require_once 'includes/db_connection.php';

// Start a session to manage user data across pages
session_start();

// If a user is already logged in, redirect them to their respective dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit(); // Always exit after a header redirect
}

$message = ''; // Initialize a message variable for feedback to the user

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve form data
    $email_or_reg_no = filter_input(INPUT_POST, 'email_or_reg_no', FILTER_SANITIZE_STRING);
    $password = $_POST['password']; // Password will be verified, so no direct sanitization with FILTER_SANITIZE_STRING for the raw password

    // Basic server-side validation
    if (empty($email_or_reg_no) || empty($password)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Please enter both email/registration number and password.</div>';
    } else {
        try {
            // Prepare SQL query to fetch user by email or registration number
            // FIX: Changed placeholders to be distinct for each condition in the OR clause
            $stmt = $pdo->prepare("SELECT user_id, full_name, reg_no, email, password, role FROM users WHERE email = :email_param OR reg_no = :reg_no_param LIMIT 1");

            // FIX: Provided values for both new distinct parameters
            $stmt->execute([
                ':email_param' => $email_or_reg_no,
                ':reg_no_param' => $email_or_reg_no
            ]);
            $user = $stmt->fetch();

            // Verify user exists and password is correct
            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['reg_no'] = $user['reg_no']; // Will be null for admins, but harmless
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on user role
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: student_dashboard.php');
                }
                exit(); // Important to exit after header redirect
            } else {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid email, registration number, or password.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Login failed due to a database error: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Attendance - Login</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Inter Font -->
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5; /* Light grey background */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .form-container {
            width: 100%;
            max-width: 500px;
            background-color: #ffffff;
            border-radius: 0.75rem; /* rounded-xl */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); /* shadow-lg */
            padding: 2.5rem; /* p-10 */
        }
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            @apply block w-full px-4 py-2 mt-1 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm;
        }
        button {
            @apply w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="form-container">
        <div class="text-center">
            <h2 class="text-3xl font-extrabold text-gray-900">
                Sign in to University Attendance
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Or
                <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                    register for a new account
                </a>
            </p>
        </div>

        <?php echo $message; // Display login message ?>

        <form class="mt-8 space-y-6" action="login.php" method="POST">
            <div>
                <label for="email_or_reg_no" class="block text-sm font-medium text-gray-700">Email address or Registration Number</label>
                <div class="mt-1">
                    <input id="email_or_reg_no" name="email_or_reg_no" type="text" autocomplete="email" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="you@university.ac.lk or IT12345">
                </div>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="Enter your password">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</body>
</html>