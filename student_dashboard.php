<?php
// Include the database connection file
require_once 'includes/db_connection.php';

// Start a session to manage user data
session_start();

// --- Authentication Check ---
// Redirect to login page if user is not logged in or is not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

// User is logged in and is a student, fetch their details from session
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$reg_no = $_SESSION['reg_no'];
$email = $_SESSION['email'];

$attendance_records = [];
$message = '';

try {
    // Fetch student's attendance records
    $stmt = $pdo->prepare("
        SELECT
            a.date,
            a.time_in,
            a.status,
            s.subject_code,
            s.subject_name
        FROM
            attendance a
        JOIN
            subjects s ON a.subject_id = s.subject_id
        JOIN
            students stu ON a.student_id = stu.student_id
        WHERE
            stu.user_id = :user_id
        ORDER BY
            a.date DESC, a.time_in DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $attendance_records = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching attendance data: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University Attendance - Student Dashboard</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .header-bar {
            @apply flex justify-between items-center bg-indigo-700 text-white p-4 rounded-b-lg shadow-md mb-8;
        }
        .nav-link {
            @apply px-4 py-2 text-white hover:bg-indigo-600 rounded-md transition-colors duration-200;
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
            @apply inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-50;
        }
    </style>
</head>
<body class="bg-gray-100">
    <header class="header-bar">
        <h1 class="text-2xl font-bold">University Attendance System</h1>
        <nav>
            <ul class="flex space-x-4">
                <li><a href="student_dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="logout.php" class="nav-link">Logout</a></li>
            </ul>
        </nav>
    </header>

    <div class="container-wrapper">
        <?php echo $message; // Display any error messages ?>

        <div class="card mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Welcome, <?php echo htmlspecialchars($full_name); ?>!</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-indigo-50 p-4 rounded-lg shadow-sm">
                    <p class="text-sm text-gray-600">Your Role:</p>
                    <p class="text-lg font-medium text-indigo-800">Student</p>
                </div>
                <div class="bg-indigo-50 p-4 rounded-lg shadow-sm">
                    <p class="text-sm text-gray-600">Registration No:</p>
                    <p class="text-lg font-medium text-indigo-800"><?php echo htmlspecialchars($reg_no); ?></p>
                </div>
                <div class="bg-indigo-50 p-4 rounded-lg shadow-sm">
                    <p class="text-sm text-gray-600">Email:</p>
                    <p class="text-lg font-medium text-indigo-800"><?php echo htmlspecialchars($email); ?></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Your Attendance History</h3>
                <div class="space-x-2">
                    <button class="btn-primary">Generate PDF</button>
                    <button class="btn-primary">Generate CSV</button>
                </div>
            </div>

            <?php if (empty($attendance_records)): ?>
                <p class="text-gray-600">No attendance records found yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['date']); ?></td>
                                    <td><?php echo htmlspecialchars($record['time_in']); ?></td>
                                    <td>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?php
                                                if ($record['status'] === 'Present') echo 'bg-green-100 text-green-800';
                                                elseif ($record['status'] === 'Absent') echo 'bg-red-100 text-red-800';
                                                else echo 'bg-blue-100 text-blue-800'; // Medical
                                            ?>">
                                            <?php echo htmlspecialchars($record['status']); ?>
                                        </span>
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
