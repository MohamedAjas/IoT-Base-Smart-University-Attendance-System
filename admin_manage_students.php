<?php
// Include the database database connection file
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
                    $message = '<div class="alert alert-danger" role="alert">All fields except Medical Count are required.</div>';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $message = '<div class="alert alert-danger" role="alert">Invalid email format.</div>';
                } else {
                    if ($action === 'add') {
                        // Check if reg_no, email, or rfid_tag_id already exist before adding
                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM users u LEFT JOIN students s ON u.user_id = s.user_id WHERE u.email = :email OR u.reg_no = :reg_no OR s.rfid_tag_id = :rfid_tag_id");
                        $stmt_check->execute([':email' => $email, ':reg_no' => $reg_no, ':rfid_tag_id' => $rfid_tag_id]);
                        if ($stmt_check->fetchColumn() > 0) {
                            $message = '<div class="alert alert-danger" role="alert">A user with this email, registration number, or RFID tag already exists.</div>';
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
                            $message = '<div class="alert alert-success" role="alert">Student added successfully.</div>';
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
                                $message = '<div class="alert alert-success" role="alert">Student updated successfully.</div>';
                            } else {
                                $message = '<div class="alert alert-danger" role="alert">Student not found for update.</div>';
                            }
                        } else {
                            $message = '<div class="alert alert-danger" role="alert">Invalid student ID for edit.</div>';
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

                        $message = '<div class="alert alert-success" role="alert">Student and associated user/attendance records deleted successfully.</div>';
                    } else {
                        $message = '<div class="alert alert-danger" role="alert">Student not found for deletion.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger" role="alert">Invalid student ID for deletion.</div>';
                }
            }
        } catch (PDOException $e) {
            // Handle duplicate entry errors for unique fields
            if ($e->getCode() == 23000) { // SQLSTATE for Integrity constraint violation
                $message = '<div class="alert alert-danger" role="alert">A record with this registration number, email, or RFID tag already exists.</div>';
            } else {
                $message = '<div class="alert alert-danger" role="alert">Database error: ' . $e->getMessage() . '</div>';
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
                $message = '<div class="alert alert-danger" role="alert">Student not found for editing.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger" role="alert">Error fetching student data for edit: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger" role="alert">Invalid student ID for editing.</div>';
    }
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="alert alert-success" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
}


// --- Fetch all students to display in the table ---
try {
    $stmt = $pdo->query("SELECT s.student_id, s.reg_no, u.full_name, u.email, s.faculty, s.rfid_tag_id, s.medical_count
                          FROM students s JOIN users u ON s.user_id = u.user_id ORDER BY u.full_name");
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger" role="alert">Error fetching students: ' . $e->getMessage() . '</div>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Students</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Inter Font -->
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <header class="header-bar">
        <!-- Top Row: University Logo and Name -->
        <div class="header-top-row">
            <div class="header-left">
                <img src="images/logo.png" alt="University Logo" class="university-logo-header">
                <h1>University Attendance Admin Panel</h1>
            </div>
        </div>
        
        <!-- Bottom Row: Navigation and User Info -->
        <div class="header-bottom-row">
            <nav class="header-nav">
                <ul>
                    <li><a href="admin_dashboard.php" class="nav-link">Dashboard</a></li>
                    <li><a href="admin_manage_students.php" class="nav-link current-page">Manage Students</a></li>
                    <li><a href="admin_manage_subjects.php" class="nav-link">Manage Subjects</a></li>
                    <li><a href="admin_manage_classes.php" class="nav-link">Manage Classes</a></li>
                    <li><a href="admin_view_attendance.php" class="nav-link">View Attendance</a></li>
                    <li><a href="admin_settings.php" class="nav-link">Settings</a></li>
                </ul>
            </nav>

            <div class="header-right">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="container-fluid container-wrapper">
        <?php echo $message; // Display any messages ?>

        <div class="card shadow-lg mb-5 border-0 rounded-3 card-custom">
            <div class="card-body p-4 p-md-5">
                <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">
                    <?php echo $editing_student ? 'Edit Student' : 'Add New Student'; ?>
                </h2>
                <form action="admin_manage_students.php" method="POST" class="row g-4">
                    <?php if ($editing_student): ?>
                        <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($editing_student['student_id']); ?>">
                        <input type="hidden" name="action" value="edit">
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label for="reg_no" class="form-label">Registration Number:</label>
                        <input type="text" id="reg_no" name="reg_no" class="form-control form-control-custom" value="<?php echo htmlspecialchars($editing_student['reg_no'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full Name:</label>
                        <input type="text" id="full_name" name="full_name" class="form-control form-control-custom" value="<?php echo htmlspecialchars($editing_student['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email:</label>
                        <input type="email" id="email" name="email" class="form-control form-control-custom" value="<?php echo htmlspecialchars($editing_student['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="faculty" class="form-label">Faculty:</label>
                        <input type="text" id="faculty" name="faculty" class="form-control form-control-custom" value="<?php echo htmlspecialchars($editing_student['faculty'] ?? ''); ?>" required placeholder="e.g., Faculty of Computing">
                    </div>
                    <div class="col-md-6">
                        <label for="rfid_tag_id" class="form-label">RFID Tag ID:</label>
                        <input type="text" id="rfid_tag_id" name="rfid_tag_id" class="form-control form-control-custom" value="<?php echo htmlspecialchars($editing_student['rfid_tag_id'] ?? ''); ?>" required placeholder="Scan or enter RFID tag ID">
                    </div>
                    <div class="col-md-6">
                        <label for="medical_count" class="form-label">Medical Count (0-2):</label>
                        <input type="number" id="medical_count" name="medical_count" class="form-control form-control-custom" min="0" max="2" value="<?php echo htmlspecialchars($editing_student['medical_count'] ?? 0); ?>">
                    </div>

                    <div class="col-12 d-flex justify-content-end mt-4">
                        <?php if ($editing_student): ?>
                            <a href="admin_manage_students.php" class="btn btn-secondary btn-custom me-3">
                                <i class="fas fa-times-circle me-2"></i> Cancel Edit
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary btn-custom">
                            <i class="fas fa-save me-2"></i> <?php echo $editing_student ? 'Update Student' : 'Add Student'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-lg border-0 rounded-3 card-custom">
            <div class="card-body p-4 p-md-5">
                <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">All Students</h2>
                <?php if (empty($students)): ?>
                    <p class="text-center text-muted py-5">No students registered yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-custom">
                            <thead>
                                <tr>
                                    <th>Reg No</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Faculty</th>
                                    <th>RFID Tag ID</th>
                                    <th class="text-center">Medical Count</th>
                                    <th class="text-center">Actions</th>
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
                                        <td class="text-center"><?php echo htmlspecialchars($student['medical_count']); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center flex-wrap gap-2">
                                                <a href="admin_manage_students.php?action=edit&student_id=<?php echo htmlspecialchars($student['student_id']); ?>" class="btn btn-sm btn-edit-custom">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-delete-custom" onclick="showDeleteModal(<?php echo htmlspecialchars($student['student_id']); ?>)">
                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                </button>
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
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-custom-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pt-0 pb-4 text-center">
                    <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 3rem;"></i>
                    <h3 class="h4 modal-title mb-3" id="deleteConfirmationModalLabel">Confirm Deletion</h3>
                    <p class="mb-4 text-muted">Are you sure you want to delete this student? All associated user and attendance records will also be deleted. This action cannot be undone.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-secondary btn-custom-sm" data-bs-dismiss="modal">Cancel</button>
                        <form id="deleteForm" action="admin_manage_students.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="student_id" id="modalStudentId">
                            <button type="submit" class="btn btn-danger btn-custom-sm">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS Bundle (popper.js included) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to show the delete confirmation modal
        function showDeleteModal(studentId) {
            document.getElementById('modalStudentId').value = studentId;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>

<style>
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(to bottom right, #f8f9fa, #e9ecef); /* Light gray gradient background */
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        color: #343a40; /* Darker text for better contrast */
        padding-top: 130px; /* Adjusted padding-top to account for two-row header */
    }

    .container-wrapper {
        max-width: 1500px;
        margin: 0 auto;
        padding: 2.5rem;
        flex-grow: 1;
        width: 100%;
        box-sizing: border-box;
    }

    /* Header Bar - Replicated from Dashboard */
    .header-bar {
        background: linear-gradient(90deg, #1f2937, #374151); /* Dark grey to slightly lighter grey for professional look */
        color: white;
        padding: 1rem 3rem; /* Adjusted padding for top/bottom rows */
        border-bottom-left-radius: 25px; 
        border-bottom-right-radius: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); 
        margin-bottom: 0; 
        display: flex;
        flex-direction: column; /* Stack children vertically */
        align-items: center; /* Center items horizontally within the column */
        position: fixed; 
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000; 
    }

    .header-top-row {
        display: flex;
        justify-content: flex-start; /* Align logo and title to the left */
        align-items: center;
        width: 100%; /* Take full width of the header */
        padding-bottom: 0.5rem; /* Space between top and bottom rows */
    }

    .header-left {
        display: flex;
        align-items: center;
    }

    .university-logo-header {
        height: 60px; /* Larger logo */
        width: auto;
        margin-right: 1rem; /* Added margin for spacing */
    }

    .header-bar h1 {
        font-size: 2.25rem; 
        font-weight: 800; 
        letter-spacing: -0.025em; 
        text-shadow: 2px 2px 5px rgba(0,0,0,0.2); 
        white-space: nowrap; /* Prevent text wrapping */
    }

    .header-bottom-row {
        display: flex;
        justify-content: space-between; /* Space out nav and user info */
        align-items: center;
        width: 100%; /* Take full width */
        padding-top: 0.5rem; /* Space from top row */
        flex-wrap: nowrap; /* Ensure elements stay in one line on large screens */
    }

    .header-nav {
        display: flex; /* Make nav a flex container itself */
        flex-grow: 1; /* Allow navigation to take available space */
        justify-content: flex-start; /* Align navigation links to the left */
        margin-right: auto; /* Push nav to the left, pushing header-right to the far right */
    }

    .header-nav ul {
        display: flex;
        list-style: none; /* Remove bullet points */
        padding: 0;
        margin: 0;
        gap: 0.4rem; /* Space between items */
    }

    .nav-link {
        padding: 0.7rem 1rem; 
        color: rgba(255, 255, 255, 0.75); 
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        background-color: transparent;
        border: none;
        white-space: nowrap; /* Keep links in one line */
    }

    .nav-link::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 3px; 
        background: linear-gradient(90deg, #9f7aea, #8b5cf6); /* Purple accent */
        border-radius: 2px;
        transform: translateX(-50%);
        transition: width 0.3s ease-out;
    }

    .nav-link:hover {
        color: #fff;
        background-color: rgba(255, 255, 255, 0.1); 
        transform: translateY(-2px);
    }

    .nav-link:hover::before {
        width: 100%;
    }

    .nav-link.current-page,
    .nav-link.active[aria-current="page"] { 
        color: #fff;
        background: linear-gradient(45deg, #a78bfa, #8b5cf6); 
        box-shadow: 0 3px 10px rgba(139, 92, 246, 0.4);
        font-weight: 600;
        transform: translateY(-1px);
        text-shadow: none; 
    }

    .nav-link.current-page::before,
    .nav-link.active[aria-current="page"]::before {
        width: 0; 
    }

    .header-right {
        display: flex;
        align-items: center; /* Align items vertically in the middle */
        gap: 0.5rem; /* Reduced gap for closer alignment */
    }

    .header-right span {
        color: white;
        font-size: 1.125rem; /* text-lg */
        font-weight: 600; /* font-semibold */
        white-space: nowrap; /* Prevent text wrapping */
    }

    .logout-btn {
        background: none; /* Remove background */
        border: none;
        color: white; /* Make text white */
        font-weight: 600;
        padding: 0; /* Remove all padding */
        border-radius: 0; /* Remove border-radius */
        box-shadow: none; /* Remove shadow */
        transition: all 0.3s ease;
        display: inline-flex; /* Use inline-flex for better alignment with text */
        align-items: center; /* Align icon and text vertically in the middle */
        gap: 0.25rem; /* Reduced gap between icon and text */
        text-decoration: none; /* Ensure it looks like a link */
        /* No margin-left here, rely on justify-content: space-between on .header-bottom-row */
    }

    .logout-btn:hover {
        transform: translateY(-1px); /* Slight lift on hover */
        text-decoration: underline; /* Underline on hover for plain text */
        color: #e0e0e0; /* Slightly lighter on hover */
        box-shadow: none; /* Ensure no shadow on hover */
        background: none; /* Ensure no background on hover */
    }

    /* Card Customization */
    .card-custom {
        border-radius: 1rem; /* More rounded cards */
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1); /* Softer shadow */
        transition: all 0.3s ease-in-out;
        background: #fff;
    }
    .card-custom:hover {
        transform: translateY(-5px); /* Lift effect on hover */
        box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.15); /* More pronounced shadow on hover */
    }

    .border-bottom-title {
        border-bottom: 2px solid #e9ecef; /* Subtle border for titles */
        padding-bottom: 0.75rem;
        margin-bottom: 1.5rem !important;
        font-weight: 700;
        color: #495057;
    }

    /* Form Control Customization */
    .form-control-custom {
        border-radius: 0.5rem; /* Rounded input fields */
        padding: 0.75rem 1rem;
        font-size: 1rem;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05); /* Inset shadow for depth */
    }
    .form-control-custom:focus {
        border-color: #8a2be2; /* Purple focus border */
        box-shadow: 0 0 0 0.25rem rgba(138, 43, 226, 0.25); /* Glow effect */
        background-color: #f8f9fa; /* Slightly lighter background on focus */
    }
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
    }

    /* Custom Buttons for Form */
    .btn-custom {
        font-weight: 600;
        padding: 0.8rem 1.8rem;
        border-radius: 0.75rem; /* More rounded buttons */
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .btn-custom.btn-primary {
        background: linear-gradient(45deg, #6a0dad, #8a2be2); /* Purple gradient */
        border: none;
        box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
    }
    .btn-custom.btn-primary:hover {
        background: linear-gradient(45deg, #8a2be2, #6a0dad); /* Reverse gradient on hover */
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
    }

    .btn-custom.btn-secondary {
        background: linear-gradient(45deg, #6c757d, #5a6268); /* Darker gray gradient */
        border: none;
        color: white;
        box-shadow: 0 5px 15px rgba(108, 117, 125, 0.3);
    }
    .btn-custom.btn-secondary:hover {
        background: linear-gradient(45deg, #5a6268, #6c757d);
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
    }

    /* Table Customization */
    .table-custom {
        border-collapse: separate; /* Allow border-radius on cells */
        border-spacing: 0;
        margin-bottom: 0; /* Remove default table margin */
    }

    .table-custom thead th {
        background-color: #e9ecef; /* Light gray for table header */
        color: #495057;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1.2rem 1rem;
        border-bottom: 2px solid #dee2e6;
        vertical-align: middle;
    }

    .table-custom tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
        color: #495057;
    }

    .table-custom.table-striped tbody tr:nth-of-type(odd) {
        background-color: #f8f9fa; /* Lighter stripe */
    }

    .table-custom.table-hover tbody tr:hover {
        background-color: #e2f0ff; /* Light blue on hover */
        transform: translateY(-2px); /* Slight lift */
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05); /* Subtle shadow on hover */
    }

    /* Table Action Buttons (Edit/Delete) */
    .btn-edit-custom {
        background: linear-gradient(45deg, #0d6efd, #0b5ed7); /* Bootstrap primary blue */
        border: none;
        color: white;
        font-weight: 500;
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 5px rgba(13, 110, 253, 0.2);
        transition: all 0.3s ease;
    }
    .btn-edit-custom:hover {
        background: linear-gradient(45deg, #0b5ed7, #0d6efd);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(13, 110, 253, 0.3);
    }

    .btn-delete-custom {
        background: linear-gradient(45deg, #dc3545, #c82333); /* Bootstrap danger red */
        border: none;
        color: white;
        font-weight: 500;
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        box-shadow: 0 2px 5px rgba(220, 53, 69, 0.2);
        transition: all 0.3s ease;
    }
    .btn-delete-custom:hover {
        background: linear-gradient(45deg, #c82333, #dc3545);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(220, 53, 69, 0.3);
    }

    /* Custom Modal Styling (mimicking Bootstrap's look but independent for `showDeleteModal`) */
    .modal-custom-content {
        border-radius: 1rem;
        box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.2);
        animation: modalFadeIn 0.3s ease-out forwards;
    }

    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }

    .modal-backdrop-custom { /* Custom backdrop for the modal function */
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
    }

    .btn-custom-sm {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
        border-radius: 0.6rem;
    }

    /* Responsive Adjustments */
    @media (max-width: 992px) { /* Adjust breakpoint for collapsing navigation */
        body {
            padding-top: 150px; /* Adjusted padding-top for smaller screens with two rows */
        }
        .header-bar {
            padding: 0.8rem 1rem; /* Reduced padding */
        }
        .header-top-row {
            padding-bottom: 0.2rem;
            flex-direction: column; /* Stack logo and title */
            text-align: center;
        }
        .header-left {
            flex-direction: column;
            margin-bottom: 0.5rem;
        }
        .university-logo-header {
            height: 45px;
            margin-right: 0;
            margin-bottom: 0.5rem;
        }
        .header-bar h1 {
            font-size: 1.8rem;
        }
        .header-bottom-row {
            flex-direction: column; /* Stack nav and user info vertically */
            align-items: center;
            padding-top: 0.2rem;
            flex-wrap: wrap; /* Allow elements to wrap on smaller screens */
        }
        .header-nav {
            width: 100%;
            justify-content: center; /* Center nav links when stacked */
            margin-bottom: 0.5rem;
        }
        .header-nav ul {
            flex-direction: column; /* Stack nav items */
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }
        .nav-link {
            width: 90%; /* Make nav links wider */
            text-align: center;
            padding: 0.6rem 0.8rem;
        }
        .header-right {
            flex-direction: column; /* Stack welcome and logout */
            gap: 0.5rem;
            width: 100%;
            text-align: center;
        }
        .logout-btn {
            width: 90%; /* Make logout button wider */
            padding: 0.5rem 0; /* Adjust padding for plain text button */
        }
        .header-right span {
            font-size: 1rem;
        }

        .container-wrapper {
            padding: 1.5rem;
        }
        .card-body {
            padding: 1.5rem !important;
        }
        .card-title {
            font-size: 1.5rem;
        }
        .form-control-custom {
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
        }
        .form-label {
            font-size: 0.9rem;
        }
        .btn-custom {
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
        }
        .table-custom thead th, .table-custom tbody td {
            padding: 0.8rem 0.6rem;
            font-size: 0.8rem;
        }
        .table-custom tbody td .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
        }
        .modal-content {
            padding: 1.5rem;
        }
        .modal-title {
            font-size: 1.2rem;
        }
        .modal-body p {
            font-size: 0.9rem;
        }
        .btn-custom-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }
    }

    @media (max-width: 576px) {
        body {
            padding-top: 140px; /* Further adjust padding for very small screens */
        }
        .header-bar {
            padding: 0.5rem 0.8rem;
        }
        .university-logo-header {
            height: 30px; /* Even smaller logo */
        }
        .header-bar h1 {
            font-size: 1rem; /* Smaller title */
        }
    }
</style>
