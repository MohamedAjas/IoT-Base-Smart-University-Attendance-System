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
$classes = []; // To store fetched classes
$subjects = []; // To store fetched subjects for the dropdown
$editing_class = null; // To store class data if in edit mode

// --- Fetch all subjects for the dropdown in the form ---
try {
    $stmt_subjects = $pdo->query("SELECT subject_id, subject_code, subject_name FROM subjects ORDER BY subject_code");
    $subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching subjects for dropdown: ' . $e->getMessage() . '</div>';
}

// --- Handle Form Submissions (Add/Edit/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'add' || $action === 'edit') {
                $subject_id = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
                $day_of_week = filter_input(INPUT_POST, 'day_of_week', FILTER_SANITIZE_STRING);
                $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
                $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
                $semester_week = filter_input(INPUT_POST, 'semester_week', FILTER_VALIDATE_INT);

                if (empty($subject_id) || empty($day_of_week) || empty($start_time) || empty($end_time) || $semester_week === false) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">All fields are required and valid.</div>';
                } else {
                    // Basic time validation: Ensure end time is after start time
                    if (strtotime($end_time) <= strtotime($start_time)) {
                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">End time must be after start time.</div>';
                    } else {
                        if ($action === 'add') {
                            $stmt = $pdo->prepare("INSERT INTO classes (subject_id, day_of_week, start_time, end_time, semester_week) VALUES (:subject_id, :day_of_week, :start_time, :end_time, :semester_week)");
                            $stmt->execute([
                                ':subject_id' => $subject_id,
                                ':day_of_week' => $day_of_week,
                                ':start_time' => $start_time,
                                ':end_time' => $end_time,
                                ':semester_week' => $semester_week
                            ]);
                            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Class schedule added successfully.</div>';
                        } elseif ($action === 'edit') {
                            $class_id_to_edit = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
                            if ($class_id_to_edit) {
                                $stmt = $pdo->prepare("UPDATE classes SET subject_id = :subject_id, day_of_week = :day_of_week, start_time = :start_time, end_time = :end_time, semester_week = :semester_week WHERE class_id = :class_id");
                                $stmt->execute([
                                    ':subject_id' => $subject_id,
                                    ':day_of_week' => $day_of_week,
                                    ':start_time' => $start_time,
                                    ':end_time' => $end_time,
                                    ':semester_week' => $semester_week,
                                    ':class_id' => $class_id_to_edit
                                ]);
                                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Class schedule updated successfully.</div>';
                            } else {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid class ID for edit.</div>';
                            }
                        }
                    }
                }
            } elseif ($action === 'delete') {
                $class_id_to_delete = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
                if ($class_id_to_delete) {
                    // It's generally safer to restrict deletion if attendance records rely on this class
                    // However, our current attendance table is linked to subject_id, not class_id directly.
                    // If your future design links attendance to class_id, you'd add a check here.
                    $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = :class_id");
                    $stmt->execute([':class_id' => $class_id_to_delete]);
                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Class schedule deleted successfully.</div>';
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid class ID for deletion.</div>';
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Database error: ' . $e->getMessage() . '</div>';
        }
        // Redirect to avoid form resubmission on refresh
        header('Location: admin_manage_classes.php?message=' . urlencode(strip_tags($message)));
        exit();
    }
}

// --- Handle GET requests for editing ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['class_id'])) {
    $class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    if ($class_id) {
        try {
            $stmt = $pdo->prepare("SELECT class_id, subject_id, day_of_week, start_time, end_time, semester_week FROM classes WHERE class_id = :class_id LIMIT 1");
            $stmt->execute([':class_id' => $class_id]);
            $editing_class = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$editing_class) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Class schedule not found for editing.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching class data for edit: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid class ID for editing.</div>';
    }
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
}

// --- Fetch all classes to display in the table ---
try {
    $stmt = $pdo->query("
        SELECT
            c.class_id,
            s.subject_code,
            s.subject_name,
            c.day_of_week,
            c.start_time,
            c.end_time,
            c.semester_week
        FROM
            classes c
        JOIN
            subjects s ON c.subject_id = s.subject_id
        ORDER BY
            c.day_of_week, c.start_time
    ");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching classes: ' . $e->getMessage() . '</div>';
}

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Classes</title>
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
        .table-responsive {
            overflow-x: auto;
        }
        table {
            @apply w-full text-left border-collapse;
        }
        th, td {
            @apply py-3 px-4 border-b border-gray-200 align-top;
        }
        th {
            @apply bg-gray-50 text-gray-700 text-sm font-semibold uppercase tracking-wider;
        }
        tr:nth-child(odd) {
            background-color: #f9fafb;
        }
        .btn-primary {
            @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
        .btn-secondary {
            @apply inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
        .btn-danger {
            @apply inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500;
        }
        input[type="text"],
        input[type="time"],
        input[type="number"],
        select {
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                <?php echo $editing_class ? 'Edit Class Schedule' : 'Add New Class Schedule'; ?>
            </h2>
            <form action="admin_manage_classes.php" method="POST" class="space-y-4">
                <?php if ($editing_class): ?>
                    <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($editing_class['class_id']); ?>">
                    <input type="hidden" name="action" value="edit">
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                <?php endif; ?>

                <div>
                    <label for="subject_id">Subject:</label>
                    <select id="subject_id" name="subject_id" required
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Select a Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>"
                                <?php echo ($editing_class && $editing_class['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="day_of_week">Day of Week:</label>
                    <select id="day_of_week" name="day_of_week" required
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">Select Day</option>
                        <?php foreach ($days_of_week as $day): ?>
                            <option value="<?php echo htmlspecialchars($day); ?>"
                                <?php echo ($editing_class && $editing_class['day_of_week'] == $day) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($day); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="start_time">Start Time:</label>
                        <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($editing_class['start_time'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="end_time">End Time:</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($editing_class['end_time'] ?? ''); ?>" required>
                    </div>
                </div>
                <div>
                    <label for="semester_week">Semester Week:</label>
                    <input type="number" id="semester_week" name="semester_week" min="1" max="15" value="<?php echo htmlspecialchars($editing_class['semester_week'] ?? 1); ?>" required placeholder="e.g., 1 (for week 1)">
                </div>

                <div>
                    <button type="submit" class="btn-primary">
                        <?php echo $editing_class ? 'Update Class' : 'Add Class'; ?>
                    </button>
                    <?php if ($editing_class): ?>
                        <a href="admin_manage_classes.php" class="btn-secondary ml-2">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">All Class Schedules</h2>
            <?php if (empty($classes)): ?>
                <p class="text-gray-600">No class schedules defined yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Class ID</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Semester Week</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($class['class_id']); ?></td>
                                    <td><?php echo htmlspecialchars($class['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['day_of_week']); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($class['start_time']))); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($class['end_time']))); ?></td>
                                    <td><?php echo htmlspecialchars($class['semester_week']); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="admin_manage_classes.php?action=edit&class_id=<?php echo htmlspecialchars($class['class_id']); ?>" class="btn-secondary">Edit</a>
                                            <form action="admin_manage_classes.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this class schedule?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class['class_id']); ?>">
                                                <button type="submit" class="btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
