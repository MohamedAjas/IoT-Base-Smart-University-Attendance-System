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
                    $message = '<div class="alert alert-danger" role="alert">Subject Code and Subject Name are required.</div>';
                } else {
                    if ($action === 'add') {
                        // Check if subject code already exists
                        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = :subject_code");
                        $stmt_check->execute([':subject_code' => $subject_code]);
                        if ($stmt_check->fetchColumn() > 0) {
                            $message = '<div class="alert alert-danger" role="alert">A subject with this code already exists.</div>';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name) VALUES (:subject_code, :subject_name)");
                            $stmt->execute([
                                ':subject_code' => $subject_code,
                                ':subject_name' => $subject_name
                            ]);
                            $message = '<div class="alert alert-success" role="alert">Subject added successfully.</div>';
                        }
                    } elseif ($action === 'edit') {
                        $subject_id_to_edit = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
                        if ($subject_id_to_edit) {
                            // Check for duplicate subject code, excluding the current subject being edited
                            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_code = :subject_code AND subject_id != :subject_id");
                            $stmt_check->execute([':subject_code' => $subject_code, ':subject_id' => $subject_id_to_edit]);
                            if ($stmt_check->fetchColumn() > 0) {
                                $message = '<div class="alert alert-danger" role="alert">Another subject with this code already exists.</div>';
                            } else {
                                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = :subject_code, subject_name = :subject_name WHERE subject_id = :subject_id");
                                $stmt->execute([
                                    ':subject_code' => $subject_code,
                                    ':subject_name' => $subject_name,
                                    ':subject_id' => $subject_id_to_edit
                                ]);
                                $message = '<div class="alert alert-success" role="alert">Subject updated successfully.</div>';
                            }
                        } else {
                            $message = '<div class="alert alert-danger" role="alert">Invalid subject ID for edit.</div>';
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
                        $message = '<div class="alert alert-danger" role="alert">Cannot delete subject: It is referenced in existing classes or attendance records. Delete those first.</div>';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = :subject_id");
                        $stmt->execute([':subject_id' => $subject_id_to_delete]);
                        $message = '<div class="alert alert-success" role="alert">Subject deleted successfully.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger" role="alert">Invalid subject ID for deletion.</div>';
                }
            }
        } catch (PDOException $e) {
            // Handle duplicate entry errors specifically for unique constraints
            if ($e->getCode() == 23000) { // SQLSTATE for Integrity constraint violation
                $message = '<div class="alert alert-danger" role="alert">A subject with this code already exists. Please use a different one.</div>';
            } else {
                $message = '<div class="alert alert-danger" role="alert">Database error: ' . $e->getMessage() . '</div>';
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
                $message = '<div class="alert alert-danger" role="alert">Subject not found for editing.</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger" role="alert">Error fetching subject data for edit: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-danger" role="alert">Invalid subject ID for editing.</div>';
    }
}

// Display messages coming from redirection after POST
if (isset($_GET['message'])) {
    $message = '<div class="alert alert-success" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
}

// --- Fetch all subjects to display in the table ---
try {
    $stmt = $pdo->query("SELECT subject_id, subject_code, subject_name FROM subjects ORDER BY subject_code");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger" role="alert">Error fetching subjects: ' . $e->getMessage() . '</div>';
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
                    <li><a href="admin_manage_subjects.php" class="nav-link current-page">Manage Subjects</a></li>
                    <li><a href="admin_manage_classes.php" class="nav-link">Manage Classes</a></li>
                    <li><a href="admin_view_attendance.php" class="nav-link">View Attendance</a></li>
                    <li><a href="admin_settings.php" class="nav-link">Settings</a></li>
                </ul>
            </nav>

            <div class="header-right">
                <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
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
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">
                <?php echo $editing_subject ? 'Edit Subject' : 'Add New Subject'; ?>
            </h2>
            <form action="admin_manage_subjects.php" method="POST" class="space-y-6">
                <?php if ($editing_subject): ?>
                    <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($editing_subject['subject_id']); ?>">
                    <input type="hidden" name="action" value="edit">
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                <?php endif; ?>

                <div>
                    <label for="subject_code" class="form-label">Subject Code:</label>
                    <input type="text" id="subject_code" name="subject_code" class="form-control-custom" value="<?php echo htmlspecialchars($editing_subject['subject_code'] ?? ''); ?>" required placeholder="e.g., IT3010">
                </div>
                <div>
                    <label for="subject_name" class="form-label">Subject Name:</label>
                    <input type="text" id="subject_name" name="subject_name" class="form-control-custom" value="<?php echo htmlspecialchars($editing_subject['subject_name'] ?? ''); ?>" required placeholder="e.g., Software Engineering">
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <?php if ($editing_subject): ?>
                        <a href="admin_manage_subjects.php" class="btn-custom btn-secondary">
                            <i class="fas fa-times-circle mr-2"></i> Cancel Edit
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn-custom btn-primary">
                        <i class="fas fa-save mr-2"></i> <?php echo $editing_subject ? 'Update Subject' : 'Add Subject'; ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="card card-custom">
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">All Subjects</h2>
            <?php if (empty($subjects)): ?>
                <p class="text-center text-gray-600 py-8">No subjects defined yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Subject ID</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_id']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td class="text-center">
                                        <div class="flex justify-center space-x-2">
                                            <a href="admin_manage_subjects.php?action=edit&subject_id=<?php echo htmlspecialchars($subject['subject_id']); ?>" class="btn-custom-sm btn-edit-custom">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <button type="button" class="btn-custom-sm btn-delete-custom" onclick="showDeleteModal(<?php echo htmlspecialchars($subject['subject_id']); ?>)">
                                                <i class="fas fa-trash-alt mr-1"></i> Delete
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

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-xl shadow-2xl modal-custom-content transform scale-90 transition-transform duration-300 ease-out">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-red-500 mb-4" style="font-size: 3rem;"></i>
                <h3 class="text-2xl font-bold text-gray-800 mb-3">Confirm Deletion</h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this subject? This action cannot be undone and will fail if there are associated classes or attendance records.</p>
                <div class="flex justify-center space-x-4">
                    <button type="button" class="btn-custom-sm btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                    <form id="deleteForm" action="admin_manage_subjects.php" method="POST" class="inline-block">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="subject_id" id="modalSubjectId">
                        <button type="submit" class="btn-custom-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to show the delete confirmation modal
        function showDeleteModal(subjectId) {
            document.getElementById('modalSubjectId').value = subjectId;
            document.getElementById('deleteConfirmationModal').classList.remove('hidden');
        }

        // Function to hide the delete confirmation modal
        function hideDeleteModal() {
            document.getElementById('deleteConfirmationModal').classList.add('hidden');
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deleteConfirmationModal');
            if (event.target == modal) {
                modal.classList.add('hidden');
            }
        }

        // Improved message display (if needed, though PHP redirection handles it)
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
        font-size: 2.00rem; /* Increased font size for desktop */
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
    }
</style>
