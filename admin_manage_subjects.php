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
$subjects = []; // To store fetched subjects
$editing_subject = null; // To store subject data if in edit mode

// --- Handle Form Submissions (Add/Edit/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'add' || $action === 'edit') {
                $subject_code = filter_input(INPUT_POST, 'subject_code', FILTER_SANITIZE_STRING);
                $subject_name = filter_input(INPUT_POST, 'subject_name', FILTER_SANITIZE_STRING);

                if (empty($subject_code) || empty($subject_name)) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Subject Code and Subject Name are required.</div>';
                } else {
                    if ($action === 'add') {
                        // Check if subject code already exists
                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = :subject_code");
                        $stmt_check->execute([':subject_code' => $subject_code]);
                        if ($stmt_check->fetchColumn() > 0) {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">A subject with this code already exists.</div>';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name) VALUES (:subject_code, :subject_name)");
                            $stmt->execute([
                                ':subject_code' => $subject_code,
                                ':subject_name' => $subject_name
                            ]);
                            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Subject added successfully.</div>';
                        }
                    } elseif ($action === 'edit') {
                        $subject_id_to_edit = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
                        if ($subject_id_to_edit) {
                            // Check for duplicate subject code, excluding the current subject being edited
                            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = :subject_code AND subject_id != :subject_id");
                            $stmt_check->execute([':subject_code' => $subject_code, ':subject_id' => $subject_id_to_edit]);
                            if ($stmt_check->fetchColumn() > 0) {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Another subject with this code already exists.</div>';
                            } else {
                                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = :subject_code, subject_name = :subject_name WHERE subject_id = :subject_id");
                                $stmt->execute([
                                    ':subject_code' => $subject_code,
                                    ':subject_name' => $subject_name,
                                    ':subject_id' => $subject_id_to_edit
                                ]);
                                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Subject updated successfully.</div>';
                            }
                        } else {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid subject ID for edit.</div>';
                        }
                    }
                }
            } elseif ($action === 'delete') {
                $subject_id_to_delete = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
                if ($subject_id_to_delete) {
                    // Check if the subject is referenced in the 'classes' or 'attendance' table
                    $stmt_check_references = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM classes WHERE subject_id = :subject_id_classes) AS class_count,
                            (SELECT COUNT(*) FROM attendance WHERE subject_id = :subject_id_attendance) AS attendance_count
                    ");
                    $stmt_check_references->execute([
                        ':subject_id_classes' => $subject_id_to_delete,
                        ':subject_id_attendance' => $subject_id_to_delete
                    ]);
                    $references = $stmt_check_references->fetch(PDO::FETCH_ASSOC);

                    if ($references['class_count'] > 0 || $references['attendance_count'] > 0) {
                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Cannot delete subject: It is referenced in existing classes or attendance records. Delete those first.</div>';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = :subject_id");
                        $stmt->execute([':subject_id' => $subject_id_to_delete]);
                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Subject deleted successfully.</div>';
                    }
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid subject ID for deletion.</div>';
                }
            }
        } catch (PDOException $e) {
            // Handle duplicate entry errors specifically for unique constraints
            if ($e->getCode() == 23000) { // SQLSTATE for Integrity constraint violation
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">A subject with this code already exists. Please use a different one.</div>';
            } else {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Database error: ' . $e->getMessage() . '</div>';
            }
        }
        // Redirect to avoid form resubmission on refresh
        header('Location: admin_manage_subjects.php?message=' . urlencode(strip_tags($message)));
        exit();
    }
}

// --- Handle GET requests for editing ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['subject_id'])) {
    $subject_id = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
    if ($subject_id) {
        try {
            $stmt = $pdo->prepare("SELECT subject_id, subject_code, subject_name FROM subjects WHERE subject_id = :subject_id LIMIT 1");
            $stmt->execute([':subject_id' => $subject_id]);
            $editing_subject = $stmt->fetch();
            if (!$editing_subject) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Subject not found for editing.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching subject data for edit: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid subject ID for editing.</div>';
    }
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
}

// --- Fetch all subjects to display in the table ---
try {
    $stmt = $pdo->query("SELECT subject_id, subject_code, subject_name FROM subjects ORDER BY subject_code");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching subjects: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Subjects</title>
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
        input[type="text"] {
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
                <?php echo $editing_subject ? 'Edit Subject' : 'Add New Subject'; ?>
            </h2>
            <form action="admin_manage_subjects.php" method="POST" class="space-y-4">
                <?php if ($editing_subject): ?>
                    <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($editing_subject['subject_id']); ?>">
                    <input type="hidden" name="action" value="edit">
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                <?php endif; ?>

                <div>
                    <label for="subject_code">Subject Code:</label>
                    <input type="text" id="subject_code" name="subject_code" value="<?php echo htmlspecialchars($editing_subject['subject_code'] ?? ''); ?>" required placeholder="e.g., IT3010">
                </div>
                <div>
                    <label for="subject_name">Subject Name:</label>
                    <input type="text" id="subject_name" name="subject_name" value="<?php echo htmlspecialchars($editing_subject['subject_name'] ?? ''); ?>" required placeholder="e.g., Software Engineering">
                </div>

                <div>
                    <button type="submit" class="btn-primary">
                        <?php echo $editing_subject ? 'Update Subject' : 'Add Subject'; ?>
                    </button>
                    <?php if ($editing_subject): ?>
                        <a href="admin_manage_subjects.php" class="btn-secondary ml-2">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">All Subjects</h2>
            <?php if (empty($subjects)): ?>
                <p class="text-gray-600">No subjects defined yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject ID</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_id']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="admin_manage_subjects.php?action=edit&subject_id=<?php echo htmlspecialchars($subject['subject_id']); ?>" class="btn-secondary">Edit</a>
                                            <form action="admin_manage_subjects.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this subject? This will fail if classes or attendance records are linked.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($subject['subject_id']); ?>">
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
