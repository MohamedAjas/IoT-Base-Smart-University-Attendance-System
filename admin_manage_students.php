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
$students = []; // To store fetched students
$editing_student = null; // To store student data if in edit mode

// --- Handle Form Submissions (Add/Edit/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'add' || $action === 'edit') {
                $reg_no = filter_input(INPUT_POST, 'reg_no', FILTER_SANITIZE_STRING);
                $full_name = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
                $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
                $faculty = filter_input(INPUT_POST, 'faculty', FILTER_SANITIZE_STRING);
                $rfid_tag_id = filter_input(INPUT_POST, 'rfid_tag_id', FILTER_SANITIZE_STRING);
                $medical_count = filter_input(INPUT_POST, 'medical_count', FILTER_VALIDATE_INT);

                if (empty($reg_no) || empty($full_name) || empty($email) || empty($faculty) || empty($rfid_tag_id)) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">All fields except Medical Count are required.</div>';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid email format.</div>';
                } else {
                    if ($action === 'add') {
                        // Check if reg_no, email, or rfid_tag_id already exist before adding
                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN students s ON u.user_id = s.user_id WHERE u.email = :email OR u.reg_no = :reg_no OR s.rfid_tag_id = :rfid_tag_id");
                        $stmt_check->execute([':email' => $email, ':reg_no' => $reg_no, ':rfid_tag_id' => $rfid_tag_id]);
                        if ($stmt_check->fetchColumn() > 0) {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">A user with this email, registration number, or RFID tag already exists.</div>';
                        } else {
                            // First, create the user entry (default password can be auto-generated or left blank, user can reset)
                            $default_password = password_hash(uniqid(), PASSWORD_DEFAULT); // Auto-generate a strong unique password
                            $stmt_user = $pdo->prepare("INSERT INTO users (reg_no, full_name, email, password, role) VALUES (:reg_no, :full_name, :email, :password, 'student')");
                            $stmt_user->execute([
                                ':reg_no' => $reg_no,
                                ':full_name' => $full_name,
                                ':email' => $email,
                                ':password' => $default_password
                            ]);
                            $new_user_id = $pdo->lastInsertId();

                            // Then, create the student entry
                            $stmt_student = $pdo->prepare("INSERT INTO students (user_id, reg_no, faculty, rfid_tag_id, medical_count) VALUES (:user_id, :reg_no, :faculty, :rfid_tag_id, :medical_count)");
                            $stmt_student->execute([
                                ':user_id' => $new_user_id,
                                ':reg_no' => $reg_no,
                                ':faculty' => $faculty,
                                ':rfid_tag_id' => $rfid_tag_id,
                                ':medical_count' => ($medical_count !== false) ? $medical_count : 0
                            ]);
                            $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Student added successfully.</div>';
                        }
                    } elseif ($action === 'edit') {
                        $student_id_to_edit = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
                        if ($student_id_to_edit) {
                            // Fetch current user_id associated with this student_id
                            $stmt_get_user_id = $pdo->prepare("SELECT user_id FROM students WHERE student_id = :student_id");
                            $stmt_get_user_id->execute([':student_id' => $student_id_to_edit]);
                            $current_user_id = $stmt_get_user_id->fetchColumn();

                            if ($current_user_id) {
                                // Update users table
                                $stmt_update_user = $pdo->prepare("UPDATE users SET reg_no = :reg_no, full_name = :full_name, email = :email WHERE user_id = :user_id");
                                $stmt_update_user->execute([
                                    ':reg_no' => $reg_no,
                                    ':full_name' => $full_name,
                                    ':email' => $email,
                                    ':user_id' => $current_user_id
                                ]);

                                // Update students table
                                $stmt_update_student = $pdo->prepare("UPDATE students SET reg_no = :reg_no, faculty = :faculty, rfid_tag_id = :rfid_tag_id, medical_count = :medical_count WHERE student_id = :student_id");
                                $stmt_update_student->execute([
                                    ':reg_no' => $reg_no,
                                    ':faculty' => $faculty,
                                    ':rfid_tag_id' => $rfid_tag_id,
                                    ':medical_count' => ($medical_count !== false) ? $medical_count : 0,
                                    ':student_id' => $student_id_to_edit
                                ]);
                                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Student updated successfully.</div>';
                            } else {
                                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Student not found for update.</div>';
                            }
                        } else {
                            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid student ID for edit.</div>';
                        }
                    }
                }
            } elseif ($action === 'delete') {
                $student_id_to_delete = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
                if ($student_id_to_delete) {
                    // Fetch user_id associated with this student_id
                    $stmt_get_user_id = $pdo->prepare("SELECT user_id FROM students WHERE student_id = :student_id");
                    $stmt_get_user_id->execute([':student_id' => $student_id_to_delete]);
                    $user_id_to_delete = $stmt_get_user_id->fetchColumn();

                    if ($user_id_to_delete) {
                        // Delete from students table (due to CASCADE, attendance records will also be deleted)
                        $stmt_delete_student = $pdo->prepare("DELETE FROM students WHERE student_id = :student_id");
                        $stmt_delete_student->execute([':student_id' => $student_id_to_delete]);

                        // Delete from users table
                        $stmt_delete_user = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                        $stmt_delete_user->execute([':user_id' => $user_id_to_delete]);

                        $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">Student and associated user/attendance records deleted successfully.</div>';
                    } else {
                        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Student not found for deletion.</div>';
                    }
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid student ID for deletion.</div>';
                }
            }
        } catch (PDOException $e) {
            // Handle duplicate entry errors for unique fields
            if ($e->getCode() == 23000) { // SQLSTATE for Integrity constraint violation
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">A record with this registration number, email, or RFID tag already exists.</div>';
            } else {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Database error: ' . $e->getMessage() . '</div>';
            }
        }
        // Redirect to avoid form resubmission on refresh
        header('Location: admin_manage_students.php?message=' . urlencode(strip_tags($message)));
        exit();
    }
}

// --- Handle GET requests for editing ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['student_id'])) {
    $student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    if ($student_id) {
        try {
            $stmt = $pdo->prepare("SELECT s.student_id, s.reg_no, u.full_name, u.email, s.faculty, s.rfid_tag_id, s.medical_count FROM students s JOIN users u ON s.user_id = u.user_id WHERE s.student_id = :student_id LIMIT 1");
            $stmt->execute([':student_id' => $student_id]);
            $editing_student = $stmt->fetch();
            if (!$editing_student) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Student not found for editing.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching student data for edit: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Invalid student ID for editing.</div>';
    }
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
}


// --- Fetch all students to display in the table ---
try {
    $stmt = $pdo->query("SELECT s.student_id, s.reg_no, u.full_name, u.email, s.faculty, s.rfid_tag_id, s.medical_count
                          FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY u.full_name");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching students: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Students</title>
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
            @apply py-3 px-4 border-b border-gray-200 align-top; /* Align to top for multi-line content */
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
            @apply inline-flex items-center px-3 py-1 border border-gray-300 text-xs font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500;
        }
        .btn-danger {
            @apply inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500;
        }
        input[type="text"],
        input[type="email"],
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
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">
                <?php echo $editing_student ? 'Edit Student' : 'Add New Student'; ?>
            </h2>
            <form action="admin_manage_students.php" method="POST" class="space-y-4">
                <?php if ($editing_student): ?>
                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($editing_student['student_id']); ?>">
                    <input type="hidden" name="action" value="edit">
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                <?php endif; ?>

                <div>
                    <label for="reg_no">Registration Number:</label>
                    <input type="text" id="reg_no" name="reg_no" value="<?php echo htmlspecialchars($editing_student['reg_no'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="full_name">Full Name:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($editing_student['full_name'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editing_student['email'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="faculty">Faculty:</label>
                    <input type="text" id="faculty" name="faculty" value="<?php echo htmlspecialchars($editing_student['faculty'] ?? ''); ?>" required placeholder="e.g., Faculty of Computing">
                </div>
                <div>
                    <label for="rfid_tag_id">RFID Tag ID:</label>
                    <input type="text" id="rfid_tag_id" name="rfid_tag_id" value="<?php echo htmlspecialchars($editing_student['rfid_tag_id'] ?? ''); ?>" required placeholder="Scan or enter RFID tag ID">
                </div>
                <div>
                    <label for="medical_count">Medical Count (0-2):</label>
                    <input type="number" id="medical_count" name="medical_count" min="0" max="2" value="<?php echo htmlspecialchars($editing_student['medical_count'] ?? 0); ?>">
                </div>

                <div>
                    <button type="submit" class="btn-primary">
                        <?php echo $editing_student ? 'Update Student' : 'Add Student'; ?>
                    </button>
                    <?php if ($editing_student): ?>
                        <a href="admin_manage_students.php" class="btn-secondary ml-2">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">All Students</h2>
            <?php if (empty($students)): ?>
                <p class="text-gray-600">No students registered yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Reg No</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Faculty</th>
                                <th>RFID Tag ID</th>
                                <th>Medical Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['reg_no']); ?></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['faculty']); ?></td>
                                    <td><?php echo htmlspecialchars($student['rfid_tag_id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['medical_count']); ?></td>
                                    <td>
                                        <div class="flex space-x-2">
                                            <a href="admin_manage_students.php?action=edit&student_id=<?php echo htmlspecialchars($student['student_id']); ?>" class="btn-secondary">Edit</a>
                                            <form action="admin_manage_students.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this student? All associated attendance records will also be deleted.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
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