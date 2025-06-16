<?php
// Include the database connection file
require_once 'includes/db_connection.php';

// Start a session to manage user data across pages (e.g., success messages)
session_start();

$message = ''; // Initialize a message variable for feedback to the user

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve form data
    // Use filter_input for safer data retrieval
    $reg_no = filter_input(INPUT_POST, 'reg_no', FILTER_SANITIZE_STRING);
    $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password']; // Password will be hashed, so no direct sanitization with FILTER_SANITIZE_STRING for the raw password
    $confirm_password = $_POST['confirm_password'];

    // Basic server-side validation
    if (empty($reg_no) || empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">All fields are required.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid email format.</div>';
    } elseif ($password !== $confirm_password) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Passwords do not match.</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Password must be at least 6 characters long.</div>';
    } else {
        // Hash the password for secure storage
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Check if email or registration number already exists
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email OR reg_no = :reg_no");
            $stmt_check->execute([':email' => $email, ':reg_no' => $reg_no]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Email or Registration Number already exists.</div>';
            } else {
                // Prepare the SQL query to insert a new user
                // By default, new users registered via this form will be 'student' role
                $stmt = $pdo->prepare("INSERT INTO users (reg_no, full_name, email, password, role) VALUES (:reg_no, :full_name, :email, :password, 'student')");

                // Execute the query with sanitized data
                $stmt->execute([
                    ':reg_no' => $reg_no,
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':password' => $hashed_password
                ]);

                // Get the last inserted user_id to also insert into the students table
                $user_id = $pdo->lastInsertId();

                // Placeholder for RFID tag ID. In a real scenario, this would be scanned by admin
                // or provided by the student and verified, but for now, we'll use a placeholder.
                // For demonstration, let's use a simple placeholder based on reg_no for now.
                // You will need an admin interface to properly assign and manage RFID tags later.
                $rfid_tag_id_placeholder = 'RFID_' . strtoupper(str_replace(' ', '', $reg_no));

                // Insert into students table
                $stmt_student = $pdo->prepare("INSERT INTO students (user_id, reg_no, faculty, rfid_tag_id) VALUES (:user_id, :reg_no, :faculty, :rfid_tag_id)");
                $stmt_student->execute([
                    ':user_id' => $user_id,
                    ':reg_no' => $reg_no,
                    ':faculty' => 'Default Faculty', // This should be selected by the user or set by admin
                    ':rfid_tag_id' => $rfid_tag_id_placeholder
                ]);

                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Registration successful! You can now <a href="login.php" class="font-bold text-green-800 hover:underline">login</a>.</div>';
            }
        } catch (PDOException $e) {
            // Handle database errors (e.g., duplicate entry for unique fields)
            if ($e->getCode() == 23000) { // SQLSTATE for Integrity constraint violation
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Registration number or Email already exists. Please use a different one.</div>';
            } else {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Registration failed: ' . $e->getMessage() . '</div>';
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
    <title>University Attendance - Register</title>
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
                Register for University Attendance
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Already have an account?
                <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                    Sign in here
                </a>
            </p>
        </div>

        <?php echo $message; // Display registration message ?>

        <form class="mt-8 space-y-6" action="register.php" method="POST">
            <div>
                <label for="reg_no" class="block text-sm font-medium text-gray-700">Registration Number</label>
                <div class="mt-1">
                    <input id="reg_no" name="reg_no" type="text" autocomplete="off" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="e.g., IT12345">
                </div>
            </div>
            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                <div class="mt-1">
                    <input id="full_name" name="full_name" type="text" autocomplete="name" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="John Doe">
                </div>
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                <div class="mt-1">
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="you@university.ac.lk">
                </div>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="mt-1">
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="Min. 6 characters">
                </div>
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <div class="mt-1">
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required
                           class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                           placeholder="Re-enter password">
                </div>
            </div>

            <div>
                <button type="submit"
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Register
                </button>
            </div>
        </form>
    </div>
</body>
</html>
