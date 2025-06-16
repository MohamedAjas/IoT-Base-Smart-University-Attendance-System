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

// User is logged in and is an admin
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$email = $_SESSION['email']; // Admin might not have a reg_no

$message = '';
$students = [];
$subjects = [];
$attendance_overview = [];

try {
    // Fetch all students (for 'Manage Students' section)
    $stmt_students = $pdo->query("SELECT s.student_id, s.reg_no, u.full_name, u.email, s.faculty, s.rfid_tag_id, s.medical_count
                                  FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY u.full_name");
    $students = $stmt_students->fetchAll();

    // Fetch all subjects (for 'Manage Subjects' section)
    $stmt_subjects = $pdo->query("SELECT subject_id, subject_code, subject_name FROM subjects ORDER BY subject_code");
    $subjects = $stmt_subjects->fetchAll();

    // Fetch an overview of recent attendance (for dashboard summary)
    // This query is a simple overview, we'll build more detailed views later
    $stmt_attendance_overview = $pdo->query("
        SELECT
            a.date,
            COUNT(CASE WHEN a.status = 'Present' THEN 1 END) AS present_count,
            COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) AS absent_count,
            COUNT(CASE WHEN a.status = 'Medical' THEN 1 END) AS medical_count,
            COUNT(a.attendance_id) AS total_records
        FROM
            attendance a
        GROUP BY
            a.date
        ORDER BY
            a.date DESC
        LIMIT 7
    ");
    $attendance_overview = $stmt_attendance_overview->fetchAll();

} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching dashboard data: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Attendance - Admin Dashboard</title>
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
            @apply py-3 px-4 border-b border-gray-200;
        }
        th {
            @apply bg-gray-50 text-gray-700 text-sm font-semibold uppercase tracking-wider;
        }
        tr:nth-child(odd) {
            background-color: #f9fafb; /* Lighter stripe */
        }
        .btn-primary {
            @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
        .btn-secondary {
            @apply inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
        .btn-danger {
            @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500;
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
        <?php echo $message; // Display any error messages ?>

        <div class="card mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Welcome, Admin <?php echo htmlspecialchars($full_name); ?>!</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-indigo-50 p-4 rounded-lg shadow-sm">
                    <p class="text-sm text-gray-600">Your Role:</p>
                    <p class="text-lg font-medium text-indigo-800">Administrator</p>
                </div>
                <div class="bg-indigo-50 p-4 rounded-lg shadow-sm">
                    <p class="text-sm text-gray-600">Email:</p>
                    <p class="text-lg font-medium text-indigo-800"><?php echo htmlspecialchars($email); ?></p>
                </div>
                <!-- You can add more admin specific info here -->
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Attendance Overview Card -->
            <div class="card">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Recent Attendance Overview</h3>
                <?php if (empty($attendance_overview)): ?>
                    <p class="text-gray-600">No attendance data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Medical</th>
                                    <th>Total Records</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_overview as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['date']); ?></td>
                                        <td><span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><?php echo htmlspecialchars($record['present_count']); ?></span></td>
                                        <td><span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"><?php echo htmlspecialchars($record['absent_count']); ?></span></td>
                                        <td><span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"><?php echo htmlspecialchars($record['medical_count']); ?></span></td>
                                        <td><?php echo htmlspecialchars($record['total_records']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links / Actions Card -->
            <div class="card">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <a href="admin_manage_students.php" class="btn-primary flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                        <span>Manage Students</span>
                    </a>
                    <a href="admin_manage_subjects.php" class="btn-primary flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 1.5V6a1 1 0 011-1h2a1 1 0 011 1v.5h-4z" clip-rule="evenodd"></path></svg>
                        <span>Manage Subjects</span>
                    </a>
                    <a href="admin_manage_classes.php" class="btn-primary flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path></svg>
                        <span>Manage Classes</span>
                    </a>
                    <a href="admin_view_attendance.php" class="btn-primary flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path></svg>
                        <span>View All Attendance</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Placeholder for overall attendance statistics / charts -->
        <div class="card mb-8">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Overall Attendance Statistics</h3>
            <p class="text-gray-600">This section will contain summarized attendance data and potentially charts (e.g., total present/absent across all students).</p>
            <!-- Content will be added here later -->
        </div>

    </div>
</body>
</html>
