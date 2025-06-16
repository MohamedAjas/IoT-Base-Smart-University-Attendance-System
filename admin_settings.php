<?php
// Include the database connection file
require_once 'includes/db_connection.php';

// Start a session to manage user data
session_start();

// --- Authentication Check ---
// Redirect to login page if user is not logged in or is not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = ''; // Initialize message variable
$semester_weeks = 15; // Default value for semester weeks
$semester_start_date = ''; // Initialize semester start date

// --- Fetch current setting values on page load ---
try {
    // Fetch semester_weeks
    $stmt_weeks = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'semester_weeks' LIMIT 1");
    $stmt_weeks->execute();
    $current_weeks = $stmt_weeks->fetchColumn();
    if ($current_weeks !== false) {
        $semester_weeks = (int)$current_weeks;
    } else {
        // If 'semester_weeks' setting doesn't exist, insert default
        $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES ('semester_weeks', '15')")
            ->execute();
        $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">Default semester weeks set to 15.</div>';
        $semester_weeks = 15;
    }

    // Fetch semester_start_date
    $stmt_date = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'semester_start_date' LIMIT 1");
    $stmt_date->execute();
    $current_date = $stmt_date->fetchColumn();
    if ($current_date !== false) {
        $semester_start_date = htmlspecialchars($current_date);
    } else {
        // If 'semester_start_date' setting doesn't exist, insert default (e.g., today's date)
        $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES ('semester_start_date', CURDATE())")
            ->execute();
        $message .= '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">Default semester start date set to today.</div>';
        $semester_start_date = date('Y-m-d'); // Set to today's date
    }

} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching settings: ' . $e->getMessage() . '</div>';
}


// --- Handle Form Submission (Update Settings) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $new_semester_weeks = filter_input(INPUT_POST, 'semester_weeks', FILTER_VALIDATE_INT);
    $new_semester_start_date = filter_input(INPUT_POST, 'semester_start_date', FILTER_SANITIZE_STRING);

    if ($new_semester_weeks === false || $new_semester_weeks < 1 || $new_semester_weeks > 52) {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid number of semester weeks. Please enter a positive integer.</div>';
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $new_semester_start_date)) {
         $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid semester start date format. Please use YYYY-MM-DD.</div>';
    }
    else {
        try {
            // Update semester_weeks setting
            $stmt_update_weeks = $pdo->prepare("UPDATE settings SET setting_value = :setting_value WHERE setting_name = 'semester_weeks'");
            $stmt_update_weeks->execute([':setting_value' => $new_semester_weeks]);

            // Update semester_start_date setting
            $stmt_update_date = $pdo->prepare("UPDATE settings SET setting_value = :setting_value WHERE setting_name = 'semester_start_date'");
            $stmt_update_date->execute([':setting_value' => $new_semester_start_date]);

            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Settings updated successfully. Semester weeks: ' . htmlspecialchars($new_semester_weeks) . ', Start Date: ' . htmlspecialchars($new_semester_start_date) . '.</div>';

            // Update local variables with the new values
            $semester_weeks = $new_semester_weeks;
            $semester_start_date = $new_semester_start_date;

        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Database error updating settings: ' . $e->getMessage() . '</div>';
        }
    }
    // Redirect to prevent form resubmission and show clean URL
    header('Location: admin_settings.php?message=' . urlencode(strip_tags($message)));
    exit();
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Settings</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Inter Font -->
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5;
        }
        .container-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .header-bar {
            @apply flex justify-between items-center bg-indigo-800 text-white p-4 rounded-b-lg shadow-md mb-8;
        }
        .nav-link {
            @apply px-4 py-2 text-white hover:bg-indigo-700 rounded-md transition-colors duration-200;
        }
        .card {
            @apply bg-white p-6 rounded-xl shadow-lg;
        }
        .btn-primary {
            @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
        input[type="number"],
        input[type="date"] { /* Added date input type */
            @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm;
        }
        label {
            @apply block text-sm font-medium text-gray-700;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="header-bar">
        <h1 class="text-2xl font-bold">University Attendance Admin Panel</h1>
        <nav>
            <ul class="flex space-x-4">
                <li><a href="admin_dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="admin_manage_students.php" class="nav-link">Manage Students</a></li>
                <li><a href="admin_manage_subjects.php" class="nav-link">Manage Subjects</a></li>
                <li><a href="admin_manage_classes.php" class="nav-link">Manage Classes</a></li>
                <li><a href="admin_view_attendance.php" class="nav-link">View Attendance</a></li>
                <li><a href="admin_settings.php" class="nav-link">Settings</a></li>
                <li><a href="logout.php" class="nav-link">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container-wrapper">
        <?php echo $message; // Display any messages ?>

        <div class="card mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">System Settings</h2>
            <form action="admin_settings.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_settings">

                <div>
                    <label for="semester_weeks">Number of Semester Weeks (e.g., 13, 14, 15):</label>
                    <input type="number" id="semester_weeks" name="semester_weeks" min="1" max="52"
                           value="<?php echo htmlspecialchars($semester_weeks); ?>" required
                           class="mt-1">
                    <p class="mt-2 text-sm text-gray-500">This value will be used for calculating total lectures and final attendance reports.</p>
                </div>

                <div>
                    <label for="semester_start_date">Semester Start Date:</label>
                    <input type="date" id="semester_start_date" name="semester_start_date"
                           value="<?php echo htmlspecialchars($semester_start_date); ?>" required
                           class="mt-1">
                    <p class="mt-2 text-sm text-gray-500">This date determines Week 1 of the semester for attendance calculation.</p>
                </div>

                <div>
                    <button type="submit" class="btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
