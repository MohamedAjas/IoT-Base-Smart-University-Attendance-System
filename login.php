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
        } catch (PDOExceptionD $e) {
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
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="main-container bg-white rounded-lg shadow-xl flex flex-col md:flex-row overflow-hidden w-full">
        <!-- Left Section: Welcome & Branding -->
        <div class="left-panel p-4 md:p-5 flex flex-col justify-between items-center text-center md:text-left">
            <div class="mb-4 md:mb-5">
                <!-- University Attendance text in image has line breaks -->
                <h1 class="university-logo text-white font-extrabold mb-2">IoT Base Smart<br>University<br>Attendance System</h1>
                <p class="tagline-text text-white text-sm md:text-base leading-relaxed">
                    <br><br><br><br>Sign in to continue access
                </p>
            </div>
            <div class="w-full text-center md:text-left mt-auto">
                <!-- Company URL at the bottom left -->
                <span class="text-xs text-white text-opacity-70">www.seu.ac.lk</span>
            </div>
        </div>

        <!-- Right Section: Login Form -->
        <div class="right-panel p-4 md:p-5 flex flex-col justify-center w-full">
            <h2 class="login-title text-xl md:text-2xl font-extrabold text-gray-800 mb-3 text-center">Login</h2>

            <?php 
            // Display login message
            if (!empty($message)) {
                echo '<div class="message-alert error mb-3">' . $message . '</div>';
            }
            ?>

            <form class="space-y-3" action="login.php" method="POST">
                <div class="relative">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input id="email_or_reg_no" name="email_or_reg_no" type="text" autocomplete="username" required
                           class="input-field pl-8"
                           placeholder="University email">
                </div>
                <div class="relative">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="input-field pl-8"
                           placeholder="Password">
                </div>

                <div class="text-right">
                    <a href="#" class="forgot-password-link text-xs hover:underline font-medium">Forgot password ?</a>
                </div>

                <div class="pt-2">
                    <button type="submit" class="submit-button">
                        LOGIN
                    </button>
                </div>
            </form>

            <div class="flex items-center my-4">
                <div class="flex-grow border-t border-gray-300"></div>
                <span class="flex-shrink mx-2 text-gray-500 text-xs">Or Sig</span>
                <div class="flex-grow border-t border-gray-300"></div>
            </div>

            <div class="text-center mt-2 text-xs text-gray-500">
                <p class="mb-1">Sign Up Using</p>
                <div class="flex justify-center gap-2">
                    <!-- Placeholder social buttons -->
                    <a href="#" class="social-icon bg-blue-600 hover:bg-blue-700"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon bg-red-600 hover:bg-red-700"><i class="fab fa-google"></i></a>
                    <a href="#" class="social-icon bg-black hover:bg-gray-800"><i class="fab fa-apple"></i></a>
                </div>
            </div>

            <div class="text-center mt-4"> <!-- Added margin-top to separate from social icons -->
                <a href="register.php" class="create-account-button">
                    Create New Account
                </a>
            </div>
        </div>
    </div>

    <style>
        /* Base styles already provided by Tailwind/Inter */

        /* Custom styles for the new design */
        body {
            /* Background image updated to log_back.png in images folder */
            background-image: url('images/log.png'); 
            background-size: cover; /* Cover the entire background */
            background-position: center; /* Center the image */
            background-repeat: no-repeat; /* Do not repeat the image */
            font-family: 'Inter', sans-serif;
            min-height: 100vh; /* Ensure full viewport height */
            display: flex;
            justify-content: center;
            align-items: center;
            /* CRUCIAL FIX: Allow vertical scrolling if content overflows */
            overflow-y: auto; 
            overflow-x: hidden; /* Prevent horizontal scroll */
            padding: 5px; /* Further reduced padding on body to give maximum space */
            box-sizing: border-box; /* Include padding in element's total width and height */
        }

        .main-container {
            /* No fixed min-height, let content dictate height */
            width: 100%; /* Responsive width */
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2); /* Slightly reduced shadow */
            display: flex;
            flex-direction: column; /* Stack on mobile */
            overflow: hidden; /* Ensure rounded corners clip content inside */
            /* IMPORTANT: Adjusted max-width to allow content to fit better on smaller screens */
            max-width: 550px; /* Significantly reduced max-width for the entire card */
        }

        @media (min-width: 768px) {
            .main-container {
                flex-direction: row; /* Side-by-side on desktop */
                min-height: 380px; /* Further reduced min-height for desktop layout */
            }
        }

        .left-panel {
            /* Gradient matching the image's left panel */
            background: linear-gradient(135deg, #8A2BE2, #FF1493); /* BlueViolet to DeepPink */
            flex: 1;
            padding: 1rem; /* Reduced padding */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            text-align: center;
            color: white;
            position: relative; /* For the subtle circle on the left panel */
            overflow: hidden;
            /* No fixed min-height for mobile left panel. */
        }

        /* Subtle circle on the left panel, as seen in the image */
        .left-panel::before {
            content: '';
            position: absolute;
            top: -20px; /* Adjusted position */
            left: -20px; /* Adjusted position */
            width: 120px; /* Reduced size */
            height: 120px;
            background: rgba(255, 255, 255, 0.1); /* White with transparency */
            border-radius: 50%;
            filter: blur(15px); /* Soft blur */
            transform: rotate(45deg); /* Slight rotation */
            z-index: 0;
            pointer-events: none;
        }


        @media (min-width: 768px) {
            .left-panel {
                flex: 0 0 45%; /* Proportion similar to image */
                border-top-left-radius: 12px;
                border-bottom-left-radius: 12px;
                border-top-right-radius: 0; /* No rounding on right for desktop */
                min-height: 380px; /* Ensure height matches right panel on desktop */
            }
        }
        @media (max-width: 767px) {
            .left-panel {
                border-bottom-left-radius: 0; /* No rounding at bottom left on mobile */
                border-top-right-radius: 12px; /* Top right rounded on mobile */
                border-bottom-right-radius: 0; /* No rounding bottom right on mobile */
            }
        }

        .university-logo {
            font-size: 1.8rem; /* Further reduced font size */
            font-weight: 800;
            margin-bottom: 0.4rem; /* Further reduced margin */
            /* Using a gradient for the logo text matching the image's example */
            background: linear-gradient(45deg, #FFD700, #FF69B4); /* Gold to HotPink, as seen in the image */
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .left-panel .tagline-text {
            font-size: 0.9rem; /* Further reduced font size */
            line-height: 1.4;
            color: white; /* Ensure text is white despite gradient for logo */
        }

        .right-panel {
            flex: 1;
            background-color: #ffffff;
            padding: 1.25rem; /* Further reduced padding */
            display: flex;
            flex-direction: column;
            justify-content: center; /* This centers content vertically within the right panel */
            text-align: center;
            /* Allow right panel to take its natural height based on content */
        }
        @media (min-width: 768px) {
            .right-panel {
                border-top-right-radius: 12px;
                border-bottom-right-radius: 12px;
                border-top-left-radius: 0; /* No rounding on left for desktop */
            }
        }
        @media (max-width: 767px) {
            .right-panel {
                border-top-left-radius: 0; /* No rounding on top left on mobile */
                border-bottom-right-radius: 12px; /* Bottom right rounded on mobile */
                border-bottom-left-radius: 12px; /* Bottom left rounded on mobile */
            }
        }


        .login-title {
            font-size: 1.5rem; /* Further reduced font size */
            font-weight: 800;
            color: #333;
            margin-bottom: 1rem; /* Further reduced margin */
        }

        .input-field {
            border: none;
            border-bottom: 1px solid #e0e0e0;
            padding-left: 2rem; /* Adjusted space for icon */
            padding-right: 0.5rem;
            padding-top: 0.4rem; /* Further reduced padding */
            padding-bottom: 0.4rem;
            height: auto;
            font-size: 0.85rem; /* Further reduced font size */
            outline: none;
            box-shadow: none;
            background-color: transparent;
            transition: border-color 0.2s ease-in-out;
        }
        .input-field:focus {
            border-bottom-color: #1877f2;
            box-shadow: none;
        }

        .input-icon {
            position: absolute;
            left: 0.3rem; /* Adjusted icon position */
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 0.8rem; /* Further reduced icon size */
        }

        .forgot-password-link {
            color: #4a90e2;
            font-size: 0.7rem; /* Further reduced font size */
            font-weight: 500;
        }
        .forgot-password-link:hover {
            text-decoration: underline;
        }


        .submit-button {
            background: linear-gradient(90deg, #00C6FF, #0072FF, #8A2BE2);
            border: none;
            font-size: 0.85rem; /* Further reduced font size */
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.6rem 0.8rem; /* Further reduced padding */
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 114, 255, 0.3); /* Further reduced shadow */
            transition: all 0.3s ease-in-out;
            color: white;
        }
        .submit-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(0, 114, 255, 0.4);
            background-position: right center;
        }

        /* OR divider */
        .flex.items-center.my-4 {
            margin-top: 1rem; /* Further reduced margin */
            margin-bottom: 1rem; /* Further reduced margin */
        }
        .flex-grow.border-t {
            border-color: #e0e0e0;
        }
        .flex-shrink.mx-4 {
            color: #777;
            font-size: 0.7rem; /* Further reduced font size */
        }

        /* Social buttons */
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px; /* Reduced size */
            height: 32px;
            border-radius: 50%;
            color: white;
            font-size: 0.9rem; /* Reduced icon size */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2); /* Reduced shadow */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .social-icon:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .create-account-button {
            background: #f0f2f5;
            color: #1877f2;
            padding: 0.5rem 0.8rem; /* Further reduced padding */
            border-radius: 8px;
            font-size: 0.8rem; /* Further reduced font size */
            font-weight: 700;
            border: 1px solid #d8dbe0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease-in-out;
            display: inline-block;
        }
        .create-account-button:hover {
            background-color: #e9ebf0;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        .message-alert {
            background-color: #fef2f2;
            border: 1px solid #ef4444;
            color: #b91c1c;
            padding: 0.4rem 0.6rem; /* Further reduced padding */
            margin-bottom: 0.6rem; /* Further reduced margin */
            border-radius: 0.375rem;
            font-size: 0.75rem; /* Further reduced font size */
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Override specific Tailwind classes if needed for exact match */
        .text-blue-600 { color: #1877f2 !important; }
    </style>
</body>
</html>
