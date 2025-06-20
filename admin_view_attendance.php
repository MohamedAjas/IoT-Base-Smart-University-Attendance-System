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
    $message = '<div class="alert alert-danger" role="alert">Error loading filter options: ' . $e->getMessage() . '</div>';
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

                        $message = '<div class="alert alert-success" role="alert">Attendance status updated to Medical, and medical count incremented.</div>';
                    } else {
                        $message = '<div class="alert alert-danger" role="alert">Cannot set to Medical: Student has already reached the limit of 2 medical reasons.</div>';
                    }
                } elseif ($new_status === 'Absent' && $old_status === 'Medical') {
                    // Changing from Medical to Absent, decrement medical count
                    $stmt_update_attendance = $pdo->prepare("UPDATE attendance SET status = :new_status WHERE attendance_id = :attendance_id");
                    $stmt_update_attendance->execute([':new_status' => $new_status, ':attendance_id' => $attendance_id]);

                    $stmt_decrement_medical = $pdo->prepare("UPDATE students SET medical_count = medical_count - 1 WHERE student_id = :student_id");
                    $stmt_decrement_medical->execute([':student_id' => $student_id]);
                    $message = '<div class="alert alert-success" role="alert">Attendance status updated to Absent, and medical count decremented.</div>';
                } else if ($new_status !== $old_status) {
                    // For other valid status changes (e.g., Present to Absent, Absent to Present)
                    $stmt_update_attendance = $pdo->prepare("UPDATE attendance SET status = :new_status WHERE attendance_id = :attendance_id");
                    $stmt_update_attendance->execute([':new_status' => $new_status, ':attendance_id' => $attendance_id]);
                    $message = '<div class="alert alert-success" role="alert">Attendance status updated successfully.</div>';
                } else {
                    $message = '<div class="alert alert-warning" role="alert">Status is already ' . htmlspecialchars($new_status) . '. No change made.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger" role="alert">Attendance record not found.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger" role="alert">Error updating attendance: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger" role="alert">Invalid update request.</div>';
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
    $message = '<div class="alert alert-danger" role="alert">Error fetching attendance records: ' . $e->getMessage() . '</div>';
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="alert alert-success" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
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
                    <li><a href="admin_manage_students.php" class="nav-link">Manage Students</a></li>
                    <li><a href="admin_manage_subjects.php" class="nav-link">Manage Subjects</a></li>
                    <li><a href="admin_manage_classes.php" class="nav-link">Manage Classes</a></li>
                    <li><a href="admin_view_attendance.php" class="nav-link current-page">View Attendance</a></li>
                    <li><a href="admin_settings.php" class="nav-link">Settings</a></li>
                </ul>
            </nav>

            <div class="header-right">
                <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="container-wrapper">
        <?php 
        // Display any error/success messages
        if (!empty($message)) {
            echo $message;
        }
        ?>

        <div class="card card-custom mb-8">
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">Filter Attendance Records</h2>
            <form action="admin_view_attendance.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label for="filter_student_id" class="form-label">Student:</label>
                    <select id="filter_student_id" name="filter_student_id" class="form-control-custom">
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
                    <label for="filter_subject_id" class="form-label">Subject:</label>
                    <select id="filter_subject_id" name="filter_subject_id" class="form-control-custom">
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
                    <label for="filter_start_date" class="form-label">Start Date:</label>
                    <input type="date" id="filter_start_date" name="filter_start_date" class="form-control-custom" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>
                <div>
                    <label for="filter_end_date" class="form-label">End Date:</label>
                    <input type="date" id="filter_end_date" name="filter_end_date" class="form-control-custom" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>
                <div class="md:col-span-2 lg:col-span-4 flex items-center justify-end space-x-4 mt-4">
                    <button type="submit" class="btn-custom btn-primary">
                        <i class="fas fa-filter mr-2"></i> Apply Filters
                    </button>
                    <a href="admin_view_attendance.php" class="btn-custom btn-secondary">
                        <i class="fas fa-redo-alt mr-2"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <div class="card card-custom">
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">All Attendance Records</h2>
            <?php if (empty($attendance_records)): ?>
                <p class="text-center text-gray-600 py-8">No attendance records found matching your criteria.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Student Name (Reg No)</th>
                                <th>Subject (Code)</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
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
                                        <span class="status-badge
                                            <?php
                                                if ($record['status'] === 'Present') echo 'status-present';
                                                elseif ($record['status'] === 'Absent') echo 'status-absent';
                                                else echo 'status-medical';
                                            ?>">
                                            <?php echo htmlspecialchars($record['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex justify-center space-x-2">
                                            <!-- Form to change status to Absent -->
                                            <?php if ($record['status'] !== 'Absent'): ?>
                                                <button type="button" class="btn-custom-sm btn-status-absent" onclick="showStatusUpdateModal(<?php echo htmlspecialchars($record['attendance_id']); ?>, 'Absent', 'Change status to ABSENT? This will update the attendance record.');">
                                                    <i class="fas fa-times-circle mr-1"></i> Absent
                                                </button>
                                            <?php endif; ?>

                                            <!-- Form to change status to Medical -->
                                            <?php if ($record['status'] !== 'Medical'): ?>
                                                <button type="button" class="btn-custom-sm btn-status-medical" onclick="showStatusUpdateModal(<?php echo htmlspecialchars($record['attendance_id']); ?>, 'Medical', 'Change status to MEDICAL? This will increment the student\'s medical count.');">
                                                    <i class="fas fa-notes-medical mr-1"></i> Medical
                                                </button>
                                            <?php endif; ?>

                                            <!-- Form to change status to Present -->
                                            <?php if ($record['status'] !== 'Present'): ?>
                                                <button type="button" class="btn-custom-sm btn-status-present" onclick="showStatusUpdateModal(<?php echo htmlspecialchars($record['attendance_id']); ?>, 'Present', 'Change status to PRESENT?');">
                                                    <i class="fas fa-check-circle mr-1"></i> Present
                                                </button>
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

    <!-- Status Update Confirmation Modal -->
    <div id="statusUpdateModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-xl shadow-2xl modal-custom-content transform scale-90 transition-transform duration-300 ease-out">
            <div class="text-center">
                <i class="fas fa-question-circle text-blue-500 mb-4" style="font-size: 3rem;"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">Confirm Status Change</h3>
                <p id="modalMessage" class="text-gray-600 mb-6"></p>
                <div class="flex justify-center space-x-4">
                    <button type="button" class="btn-custom-sm btn-secondary" onclick="hideStatusUpdateModal()">Cancel</button>
                    <form id="statusUpdateForm" action="admin_view_attendance.php" method="POST" class="inline-block">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="attendance_id" id="modalAttendanceId">
                        <input type="hidden" name="new_status" id="modalNewStatus">
                        <button type="submit" class="btn-custom-sm btn-primary">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to show the status update confirmation modal
        function showStatusUpdateModal(attendanceId, newStatus, message) {
            document.getElementById('modalAttendanceId').value = attendanceId;
            document.getElementById('modalNewStatus').value = newStatus;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('statusUpdateModal').classList.remove('hidden');
        }

        // Function to hide the status update confirmation modal
        function hideStatusUpdateModal() {
            document.getElementById('statusUpdateModal').classList.add('hidden');
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('statusUpdateModal');
            if (event.target == modal) {
                modal.classList.add('hidden');
            }
        }

        // Improved message display
        document.addEventListener('DOMContentLoaded', function() {
            const messageDiv = document.querySelector('.alert');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.opacity = '0';
                    messageDiv.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(() => messageDiv.remove(), 500); // Remove after transition
                }, 5000); // Hide after 5 seconds
            }
        });
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

    /* Header Bar */
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
    }

    .header-nav ul {
        display: flex;
        list-style: none; /* Remove bullet points */
        padding: 0;
        margin: 0;
        gap: 0.3rem; /* Space between items */
    }

    .nav-link {
        padding: 0.7rem 1rem; 
        color: rgba(255, 255, 255, 0.75); 
        font-weight: 500;
        border-radius: 8px; /* Consistent rounded rectangular shape */
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
        margin-left: auto; /* Pushes this element to the far right */
        gap: 0.5rem; /* Gap between Welcome text and Logout button */
    }

    .header-right span {
        color: white;
        font-size: 1.125rem; /* text-lg */
        font-weight: 600; /* font-semibold */
        white-space: nowrap; /* Prevent text wrapping */
    }

    .logout-btn {
        background-color: transparent;
        border: none;
        color: white;
        font-weight: 600;
        padding: 0.7rem 1rem;
        border-radius: 8px;
        box-shadow: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .logout-btn:hover {
        transform: translateY(-2px);
        background-color: rgba(255, 255, 255, 0.1); /* Subtle background on hover */
        box-shadow: 0 3px 10px rgba(255, 255, 255, 0.2); /* Subtle shadow on hover */
        text-decoration: none;
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

    .card-title {
        font-size: 1.75rem; /* Increased font size for desktop */
    }

    .border-bottom-title {
        border-bottom: 2px solid #e9ecef; /* Subtle border for titles */
        padding-bottom: 1.0rem; /* Increased padding below text */
        margin-bottom: 2.5rem !important; /* Increased margin below the border line */
        font-weight: 700;
        color: #495057;
    }

    /* Form Control Customization */
    .form-control-custom {
        display: block;
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: 0.5rem; /* Rounded input fields */
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05); /* Inset shadow for depth */
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .form-control-custom:focus {
        color: #495057;
        background-color: #f8f9fa; /* Slightly lighter background on focus */
        border-color: #8a2be2; /* Purple focus border */
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(138, 43, 226, 0.25); /* Glow effect */
    }
    .form-label {
        display: block;
        text-align: left; /* Align label text to left */
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
    }

    /* Custom Buttons for Form */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        padding: 0.8rem 1.8rem;
        border-radius: 0.75rem; /* More rounded buttons */
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        text-decoration: none; /* Ensure no underline on anchor buttons */
    }

    .btn-custom.btn-primary {
        background: linear-gradient(45deg, #6a0dad, #8a2be2); /* Purple gradient */
        border: none;
        color: white;
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
    .table-responsive {
        overflow-x: auto;
        border-radius: 1rem; /* Match card border-radius */
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.05); /* Subtle shadow for table container */
        background-color: #fff;
    }
    .table-custom {
        width: 100%;
        text-align: left;
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
    .btn-custom-sm {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        padding: 0.5rem 1rem; /* Slightly larger small buttons */
        border-radius: 0.6rem;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        text-decoration: none;
    }

    .btn-edit-custom {
        background: linear-gradient(45deg, #0d6efd, #0b5ed7); /* Bootstrap primary blue */
        border: none;
        color: white;
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
    }
    .btn-delete-custom:hover {
        background: linear-gradient(45deg, #c82333, #dc3545);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(220, 53, 69, 0.3);
    }

    /* Custom Modal Styling (using Tailwind/CSS directly) */
    #deleteConfirmationModal {
        transition: opacity 0.3s ease-out;
    }
    .modal-custom-content {
        max-width: 500px;
        width: 90%;
        animation: modalSlideIn 0.3s ease-out forwards;
    }

    @keyframes modalSlideIn {
        from { opacity: 0; transform: translateY(-50px) scale(0.9); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Message Alert Styling */
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        text-align: center;
    }
    .alert.alert-success {
        background-color: #d1fae5; /* Green-100 */
        border-color: #34d399; /* Green-400 */
        color: #065f46; /* Green-700 */
    }
    .alert.alert-danger {
        background-color: #fee2e2; /* Red-100 */
        border-color: #ef4444; /* Red-400 */
        color: #991b1b; /* Red-700 */
    }
    .alert.alert-warning {
        background-color: #fffbeb; /* Yellow-100 */
        border-color: #fbbf24; /* Yellow-400 */
        color: #92400e; /* Yellow-700 */
    }


    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.4em 0.8em;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.85em;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border: 1px solid transparent;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .status-present {
        background-color: #d4edda; /* Light green */
        color: #155724; /* Dark green */
        border-color: #28a745;
    }

    .status-absent {
        background-color: #f8d7da; /* Light red */
        color: #721c24; /* Dark red */
        border-color: #dc3545;
    }

    .status-medical {
        background-color: #ffeeba; /* Light yellow */
        color: #856404; /* Dark yellow */
        border-color: #ffc107;
    }

    /* Specific Status Action Buttons */
    .btn-status-absent {
        background: linear-gradient(45deg, #dc3545, #c82333);
        color: white;
    }
    .btn-status-absent:hover {
        background: linear-gradient(45deg, #c82333, #dc3545);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(220, 53, 69, 0.3);
    }

    .btn-status-medical {
        background: linear-gradient(45deg, #ffc107, #e0a800);
        color: white;
    }
    .btn-status-medical:hover {
        background: linear-gradient(45deg, #e0a800, #ffc107);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(255, 193, 7, 0.3);
    }

    .btn-status-present {
        background: linear-gradient(45deg, #28a745, #218838);
        color: white;
    }
    .btn-status-present:hover {
        background: linear-gradient(45deg, #218838, #28a745);
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(40, 167, 69, 0.3);
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
            font-size: 1.5rem; /* Adjusted for smaller screens */
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
        .table-custom tbody td .flex.space-x-2 {
            flex-direction: column;
            gap: 0.5rem;
        }
    }
</style>
