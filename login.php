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
        /* Base styles */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #e0f2f7, #c1dff0); /* Very subtle, calming blue gradient */
            background-size: 400% 400%;
            animation: gradientBackgroundAnimation 20s ease infinite alternate; /* Slower, smoother animation */
            overflow-y: auto; /* Ensure scrolling is always possible */
            color: #333; /* Default dark text color */
        }

        @keyframes gradientBackgroundAnimation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Main content wrapper for two-column layout */
        .login-wrapper {
            display: flex;
            flex-direction: column; /* Stack vertically on small screens */
            align-items: center;
            max-width: 1080px; /* Increased max width for more expansive feel */
            width: 100%;
            padding: 2rem; /* Consistent padding for overall wrapper */
            box-sizing: border-box;
        }

        @media (min-width: 768px) { /* Medium screens and up (md breakpoint) */
            .login-wrapper {
                flex-direction: row; /* Two columns on larger screens */
                justify-content: center; /* Center content horizontally */
                gap: 4rem; /* Gap between left and right sections */
                padding: 3rem;
            }
        }

        /* Left section: Branding/Marketing text */
        .left-section {
            text-align: center;
            margin-bottom: 3rem; /* Space below on small screens */
            width: 100%;
            max-width: 500px; /* Wider limit */
            animation: fadeInRight 1s ease-out forwards; /* Fade in from right */
            opacity: 0; /* Start hidden for animation */
            transform: translateX(-20px); /* Start slightly off-screen */
        }

        @media (min-width: 768px) {
            .left-section {
                text-align: left;
                margin-bottom: 0;
                transform: translateX(0); /* Reset transform for animation direction */
            }
        }

        @keyframes fadeInRight {
            to { opacity: 1; transform: translateX(0); }
        }

        .university-logo {
            font-size: 4.5rem; /* Even larger logo text */
            font-weight: 900; /* Super bold */
            /* Text gradient for dynamic branding - using a calming blue-green */
            background: linear-gradient(45deg, #007bff, #00c6ff, #00e0b2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1.1;
            margin-bottom: 1.2rem;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.1); /* Subtle text shadow */
        }

        .tagline-text {
            font-size: 1.75rem; /* Larger tagline */
            color: #4a5568; /* Darker gray for strong readability */
            line-height: 1.4;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.05); /* Very subtle text shadow */
        }

        /* Right section: Login form container - ENHANCED FOR CLARITY */
        .form-container {
            width: 100%;
            max-width: 450px;
            background-color: #ffffff; /* Solid white background for maximum contrast */
            border-radius: 1rem; /* Consistent rounding */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 0 4px 10px rgba(0, 0, 0, 0.08); /* Clearer, softer shadow */
            padding: 2.5rem; /* Standard generous padding */
            box-sizing: border-box;
            animation: fadeInLeft 1s ease-out forwards;
            opacity: 0;
            transform: translateX(20px);
            animation-delay: 0.2s;
            position: relative;
            z-index: 10; /* Ensure it's above background elements */
        }

        @media (min-width: 768px) {
            .form-container {
                padding: 3rem; /* Slightly more padding on larger screens */
                transform: translateX(0);
            }
        }

        @keyframes fadeInLeft {
            to { opacity: 1; transform: translateX(0); }
        }

        /* Input field styling - ENHANCED FOR USABILITY */
        .input-field {
            @apply block w-full px-5 py-3.5 border border-gray-300 rounded-md shadow-sm /* Standard border and shadow */
                   text-gray-800 placeholder-gray-500
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 /* Clear blue focus */
                   transition duration-200 ease-in-out;
        }
        
        .input-field::placeholder {
            opacity: 0.8;
            color: #888; /* Slightly darker placeholder for better visibility */
        }

        /* Button styling - ENHANCED FOR IMPACT & FEEDBACK */
        .submit-button {
            @apply w-full flex justify-center py-3.5 px-6 border-transparent rounded-md shadow-md /* Standard rounded-md */
                   text-lg font-bold text-white uppercase tracking-wide
                   bg-blue-600 hover:bg-blue-700 /* Solid blue for primary action, simple hover */
                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
                   transition duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg; /* Clear scale and shadow on hover */
        }

        /* Register link button - ENHANCED FOR DISTINCTNESS */
        .register-button {
            @apply w-full flex justify-center py-3 px-6 border border-transparent rounded-md shadow-sm /* Standard rounded-md */
                   text-lg font-semibold text-white
                   bg-green-500 hover:bg-green-600 /* Distinct green for secondary action */
                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-400
                   transition duration-200 ease-in-out transform hover:scale-105 hover:shadow-md;
        }

        /* Divider style - CLEANER */
        .divider {
            border-bottom: 1px solid #e2e8f0; /* Tailwind gray-200 for a very light line */
            margin: 2rem 0; /* Consistent spacing */
        }

        /* Message alert styling - CLEAR & FRIENDLY */
        .message-alert {
            margin-bottom: 2rem; /* Clear space before form */
            padding: 1rem 1.25rem;
            border-radius: 0.5rem; /* Standard rounding for alerts */
            text-align: center;
            font-size: 0.95rem;
            font-weight: 500;
            line-height: 1.4;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }

        .message-alert.error {
            background-color: #ffe7e6; /* Very light red */
            border: 1px solid #ff4d4d; /* Clear red border */
            color: #cc0000; /* Dark red text */
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Section: Branding and Tagline -->
        <div class="left-section">
            <h1 class="university-logo">University Attendance</h1>
            <h2 class="tagline-text">
                Seamlessly track, manage, and verify student attendance.<br>Empowering academic success through efficiency.
            </h2>
        </div>

        <!-- Right Section: Login Form -->
        <div class="form-container">
            <?php 
            // Display login message
            if (!empty($message)) {
                echo '<div class="message-alert error">' . $message . '</div>';
            }
            ?>

            <form class="space-y-5" action="login.php" method="POST">
                <div>
                    <input id="email_or_reg_no" name="email_or_reg_no" type="text" autocomplete="email" required
                           class="input-field"
                           placeholder="Email address or Registration Number">
                </div>
                <div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="input-field"
                           placeholder="Password">
                </div>

                <div class="pt-3">
                    <button type="submit" class="submit-button">
                        Log In
                    </button>
                </div>
            </form>

            <div class="divider"></div>

            <div class="text-center pt-2">
                <a href="register.php" class="register-button">
                    Create New Account
                </a>
            </div>
        </div>
    </div>
</body>
</html>
