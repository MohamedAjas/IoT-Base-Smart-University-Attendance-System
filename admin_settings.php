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
$semester_weeks = 15; // Default value

// --- Handle Form Submission (Update Settings) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $new_semester_weeks = filter_input(INPUT_POST, 'semester_weeks', FILTER_VALIDATE_INT);

    if ($new_semester_weeks === false || $new_semester_weeks < 1 || $new_semester_weeks > 52) { // Assuming max 52 weeks for a semester
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid number of semester weeks. Please enter a positive integer.</div>';
    } else {
        try {
            // Check if the setting already exists (it should, from initial INSERT)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_name = 'semester_weeks'");
            $stmt_check->execute();
            $exists = $stmt_check->fetchColumn();

            if ($exists) {
                // Update the existing setting
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = :setting_value WHERE setting_name = 'semester_weeks'");
            } else {
                // Insert if for some reason it doesn't exist (shouldn't happen if initial insert ran)
                $stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES ('semester_weeks', :setting_value)");
            }
            $stmt->execute([':setting_value' => $new_semester_weeks]);

            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Semester weeks updated successfully to ' . htmlspecialchars($new_semester_weeks) . '.</div>';

            // Update the local variable with the new value
            $semester_weeks = $new_semester_weeks;

        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Database error updating settings: ' . $e->getMessage() . '</div>';
        }
    }
    // Redirect to prevent form resubmission and show clean URL
    header('Location: admin_settings.php?message=' . urlencode(strip_tags($message)));
    exit();
}

// --- Fetch current setting value on page load ---
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'semester_weeks' LIMIT 1");
    $stmt->execute();
    $current_setting = $stmt->fetchColumn();

    if ($current_setting !== false) {
        $semester_weeks = (int)$current_setting; // Cast to integer
    } else {
        // If the setting doesn't exist (e.g., initial setup issue), insert default
        $stmt_insert_default = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES ('semester_weeks', '15')");
        $stmt_insert_default->execute();
        $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">Default semester weeks set to 15. Please update if needed.</div>';
        $semester_weeks = 15; // Set to default after inserting
    }
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching settings: ' . $e->getMessage() . '</div>';
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
        input[type="number"] {
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
                    <button type="submit" class="btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
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
$semester_weeks = 15; // Default value

// --- Handle Form Submission (Update Settings) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $new_semester_weeks = filter_input(INPUT_POST, 'semester_weeks', FILTER_VALIDATE_INT);

    if ($new_semester_weeks === false || $new_semester_weeks < 1 || $new_semester_weeks > 52) { // Assuming max 52 weeks for a semester
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid number of semester weeks. Please enter a positive integer.</div>';
    } else {
        try {
            // Check if the setting already exists (it should, from initial INSERT)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_name = 'semester_weeks'");
            $stmt_check->execute();
            $exists = $stmt_check->fetchColumn();

            if ($exists) {
                // Update the existing setting
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = :setting_value WHERE setting_name = 'semester_weeks'");
            } else {
                // Insert if for some reason it doesn't exist (shouldn't happen if initial insert ran)
                $stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES ('semester_weeks', :setting_value)");
            }
            $stmt->execute([':setting_value' => $new_semester_weeks]);

            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Semester weeks updated successfully to ' . htmlspecialchars($new_semester_weeks) . '.</div>';

            // Update the local variable with the new value
            $semester_weeks = $new_semester_weeks;

        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Database error updating settings: ' . $e->getMessage() . '</div>';
        }
    }
    // Redirect to prevent form resubmission and show clean URL
    header('Location: admin_settings.php?message=' . urlencode(strip_tags($message)));
    exit();
}

// --- Fetch current setting value on page load ---
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'semester_weeks' LIMIT 1");
    $stmt->execute();
    $current_setting = $stmt->fetchColumn();

    if ($current_setting !== false) {
        $semester_weeks = (int)$current_setting; // Cast to integer
    } else {
        // If the setting doesn't exist (e.g., initial setup issue), insert default
        $stmt_insert_default = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES ('semester_weeks', '15')");
        $stmt_insert_default->execute();
        $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">Default semester weeks set to 15. Please update if needed.</div>';
        $semester_weeks = 15; // Set to default after inserting
    }
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching settings: ' . $e->getMessage() . '</div>';
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
        input[type="number"] {
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
                    <button type="submit" class="btn-primary">Update Settings</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
