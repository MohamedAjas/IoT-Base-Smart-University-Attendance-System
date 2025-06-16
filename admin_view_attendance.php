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
$attendance_records = []; // To store fetched attendance records
$students_list = []; // For student filter dropdown
$subjects_list = []; // For subject filter dropdown

// --- Fetch lists for filter dropdowns ---
try {
    // FIX: Qualified 'reg_no' and 'full_name' with table aliases to resolve ambiguity
    $stmt_students = $pdo->query("SELECT s.student_id, s.reg_no, u.full_name FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY u.full_name");
    $students_list = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

    $stmt_subjects = $pdo->query("SELECT subject_id, subject_code, subject_name FROM subjects ORDER BY subject_name");
    $subjects_list = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error loading filter options: ' . $e->getMessage() . '</div>';
}

// --- Handle Attendance Status Update (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $attendance_id = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);

    if ($attendance_id && in_array($new_status, ['Present', 'Absent', 'Medical'])) {
        try {
            // Fetch current attendance record to check status and student_id
            $stmt_current = $pdo->prepare("SELECT student_id, status FROM attendance WHERE attendance_id = :attendance_id LIMIT 1");
            $stmt_current->execute([':attendance_id' => $attendance_id]);
            $current_record = $stmt_current->fetch(PDO::FETCH_ASSOC);

            if ($current_record) {
                $student_id = $current_record['student_id'];
                $old_status = $current_record['status'];

                // Logic for Medical status updates
                if ($new_status === 'Medical' && $old_status === 'Absent') {
                    // Check student's current medical count
                    $stmt_medical_count = $pdo->prepare("SELECT medical_count FROM students WHERE student_id = :student_id LIMIT 1");
                    $stmt_medical_count->execute([':student_id' => $student_id]);
                    $medical_count = $stmt_medical_count->fetchColumn();

                    if ($medical_count < 2) { // Allow up to 2 medical reasons
                        // Update attendance status
                        $stmt_update_attendance = $pdo->prepare("UPDATE attendance SET status = :new_status WHERE attendance_id = :attendance_id");
                        $stmt_update_attendance->execute([':new_status' => $new_status, ':attendance_id' => $attendance_id]);

                        // Increment medical count for the student
                        $stmt_increment_medical = $pdo->prepare("UPDATE students SET medical_count = medical_count + 1 WHERE student_id = :student_id");
                        $stmt_increment_medical->execute([':student_id' => $student_id]);

                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Attendance status updated to Medical, and medical count incremented.</div>';
                    } else {
                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Cannot set to Medical: Student has already reached the limit of 2 medical reasons.</div>';
                    }
                } elseif ($new_status === 'Absent' && $old_status === 'Medical') {
                    // Changing from Medical to Absent, decrement medical count
                    $stmt_update_attendance = $pdo->prepare("UPDATE attendance SET status = :new_status WHERE attendance_id = :attendance_id");
                    $stmt_update_attendance->execute([':new_status' => $new_status, ':attendance_id' => $attendance_id]);

                    $stmt_decrement_medical = $pdo->prepare("UPDATE students SET medical_count = medical_count - 1 WHERE student_id = :student_id");
                    $stmt_decrement_medical->execute([':student_id' => $student_id]);
                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Attendance status updated to Absent, and medical count decremented.</div>';
                } else if ($new_status !== $old_status) {
                    // For other valid status changes (e.g., Present to Absent, Absent to Present)
                    $stmt_update_attendance = $pdo->prepare("UPDATE attendance SET status = :new_status WHERE attendance_id = :attendance_id");
                    $stmt_update_attendance->execute([':new_status' => $new_status, ':attendance_id' => $attendance_id]);
                    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Attendance status updated successfully.</div>';
                } else {
                    $message = '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">Status is already ' . htmlspecialchars($new_status) . '. No change made.</div>';
                }
            } else {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Attendance record not found.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error updating attendance: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid update request.</div>';
    }
    // Redirect to preserve state after POST and display message
    header('Location: admin_view_attendance.php?message=' . urlencode(strip_tags($message)));
    exit();
}

// --- Fetch Attendance Records (with Filters) ---
$filter_student_id = filter_input(INPUT_GET, 'filter_student_id', FILTER_VALIDATE_INT);
$filter_subject_id = filter_input(INPUT_GET, 'filter_subject_id', FILTER_VALIDATE_INT);
$filter_start_date = filter_input(INPUT_GET, 'filter_start_date', FILTER_SANITIZE_STRING);
$filter_end_date = filter_input(INPUT_GET, 'filter_end_date', FILTER_SANITIZE_STRING);

$sql = "
    SELECT
        a.attendance_id,
        a.date,
        a.time_in,
        a.status,
        u.full_name AS student_name,
        stu.reg_no,
        s.subject_code,
        s.subject_name
    FROM
        attendance a
    JOIN
        students stu ON a.student_id = stu.student_id
    JOIN
        users u ON stu.user_id = u.user_id
    JOIN
        subjects s ON a.subject_id = s.subject_id
    WHERE 1=1
";
$params = [];

if ($filter_student_id) {
    $sql .= " AND stu.student_id = :student_id";
    $params[':student_id'] = $filter_student_id;
}
if ($filter_subject_id) {
    $sql .= " AND s.subject_id = :subject_id";
    $params[':subject_id'] = $filter_subject_id;
}
if ($filter_start_date) {
    $sql .= " AND a.date >= :start_date";
    $params[':start_date'] = $filter_start_date;
}
if ($filter_end_date) {
    $sql .= " AND a.date <= :end_date";
    $params[':end_date'] = $filter_end_date;
}

$sql .= " ORDER BY a.date DESC, a.time_in DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching attendance records: ' . $e->getMessage() . '</div>';
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
    <title>Admin - View All Attendance</title>
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
        input[type="date"],
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Filter Attendance Records</h2>
            <form action="admin_view_attendance.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="filter_student_id">Student:</label>
                    <select id="filter_student_id" name="filter_student_id">
                        <option value="">All Students</option>
                        <?php foreach ($students_list as $student): ?>
                            <option value="<?php echo htmlspecialchars($student['student_id']); ?>"
                                <?php echo ($filter_student_id == $student['student_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['reg_no'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_subject_id">Subject:</label>
                    <select id="filter_subject_id" name="filter_subject_id">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects_list as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>"
                                <?php echo ($filter_subject_id == $subject['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_start_date">Start Date:</label>
                    <input type="date" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>
                <div>
                    <label for="filter_end_date">End Date:</label>
                    <input type="date" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>
                <div class="md:col-span-2 lg:col-span-4 flex items-end justify-end">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="admin_view_attendance.php" class="btn-secondary ml-2">Clear Filters</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">All Attendance Records</h2>
            <?php if (empty($attendance_records)): ?>
                <p class="text-gray-600">No attendance records found matching your criteria.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Student Name (Reg No)</th>
                                <th>Subject (Code)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['date']); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($record['time_in']))); ?></td>
                                    <td><?php echo htmlspecialchars($record['student_name'] . ' (' . $record['reg_no'] . ')'); ?></td>
                                    <td><?php echo htmlspecialchars($record['subject_name'] . ' (' . $record['subject_code'] . ')'); ?></td>
                                    <td>
                                        <span class="btn-status-update
                                            <?php
                                                if ($record['status'] === 'Present') echo 'status-present';
                                                elseif ($record['status'] === 'Absent') echo 'status-absent';
                                                else echo 'status-medical';
                                            ?>">
                                            <?php echo htmlspecialchars($record['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <!-- Form to change status to Absent -->
                                            <?php if ($record['status'] !== 'Absent'): ?>
                                                <form action="admin_view_attendance.php" method="POST" onsubmit="return confirm('Change status to ABSENT?');">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="attendance_id" value="<?php echo htmlspecialchars($record['attendance_id']); ?>">
                                                    <input type="hidden" name="new_status" value="Absent">
                                                    <button type="submit" class="btn-secondary">Set Absent</button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Form to change status to Medical -->
                                            <?php if ($record['status'] !== 'Medical'): ?>
                                                <form action="admin_view_attendance.php" method="POST" onsubmit="return confirm('Change status to MEDICAL? This will increment the student\'s medical count.');">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="attendance_id" value="<?php echo htmlspecialchars($record['attendance_id']); ?>">
                                                    <input type="hidden" name="new_status" value="Medical">
                                                    <button type="submit" class="btn-secondary">Set Medical</button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Form to change status to Present -->
                                            <?php if ($record['status'] !== 'Present'): ?>
                                                <form action="admin_view_attendance.php" method="POST" onsubmit="return confirm('Change status to PRESENT?');">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="attendance_id" value="<?php echo htmlspecialchars($record['attendance_id']); ?>">
                                                    <input type="hidden" name="new_status" value="Present">
                                                    <button type="submit" class="btn-secondary">Set Present</button>
                                                </form>
                                            <?php endif; ?>
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
