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
                    <li><a href="admin_manage_classes.php" class="nav-link current-page">Manage Classes</a></li>
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

    <div class="container-wrapper">
        <?php 
        // Display any error/success messages
        if (!empty($message)) {
            echo '
            <div id="alertMessage" class="message-container ' . (strpos($message, 'bg-green') ? 'success' : (strpos($message, 'bg-red') ? 'danger' : 'warning')) . '">
                ' . $message . '
                <button type="button" class="close-btn" onclick="document.getElementById(\'alertMessage\').remove();">
                    <i class="fas fa-times"></i>
                </button>
            </div>';
        }
        ?>

        <div class="card card-custom mb-8 fade-in-up">
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">
                <?php echo $editing_class ? 'Edit Class Schedule' : 'Add New Class Schedule'; ?>
            </h2>
            <form action="admin_manage_classes.php" method="POST" class="space-y-6 p-4"> <!-- Added padding -->
                <?php if ($editing_class): ?>
                    <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($editing_class['class_id']); ?>">
                    <input type="hidden" name="action" value="edit">
                <?php else: ?>
                    <input type="hidden" name="action" value="add">
                <?php endif; ?>

                <div class="form-group">
                    <label for="subject_id" class="form-label">Subject:</label>
                    <select id="subject_id" name="subject_id" required
                            class="form-control-custom">
                        <option value="">Select a Subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo htmlspecialchars($subject['subject_id']); ?>"
                                <?php echo ($editing_class && $editing_class['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="day_of_week" class="form-label">Day of Week:</label>
                    <select id="day_of_week" name="day_of_week" required
                            class="form-control-custom">
                        <option value="">Select Day</option>
                        <?php foreach ($days_of_week as $day): ?>
                            <option value="<?php echo htmlspecialchars($day); ?>"
                                <?php echo ($editing_class && $editing_class['day_of_week'] == $day) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($day); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label for="start_time" class="form-label">Start Time:</label>
                        <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($editing_class['start_time'] ?? ''); ?>" required class="form-control-custom">
                    </div>
                    <div class="form-group">
                        <label for="end_time" class="form-label">End Time:</label>
                        <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($editing_class['end_time'] ?? ''); ?>" required class="form-control-custom">
                    </div>
                </div>
                <div class="form-group">
                    <label for="semester_week" class="form-label">Semester Week:</label>
                    <input type="number" id="semester_week" name="semester_week" min="1" max="15" value="<?php echo htmlspecialchars($editing_class['semester_week'] ?? 1); ?>" required placeholder="e.g., 1 (for week 1)" class="form-control-custom">
                </div>

                <div class="flex justify-center mt-8 space-x-4"> <!-- Added space-x-4 -->
                    <button type="submit" class="btn-custom btn-primary group">
                        <?php echo $editing_class ? '<i class="fas fa-save mr-2 transition-transform duration-300 group-hover:scale-110"></i> Update Class' : '<i class="fas fa-plus-circle mr-2 transition-transform duration-300 group-hover:rotate-90"></i> Add Class'; ?>
                    </button>
                    <?php if ($editing_class): ?>
                        <a href="admin_manage_classes.php" class="btn-custom btn-secondary group">
                            <i class="fas fa-times-circle mr-2 transition-transform duration-300 group-hover:rotate-90"></i> Cancel Edit
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="card card-custom fade-in-up">
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">All Class Schedules</h2>
            <?php if (empty($classes)): ?>
                <div class="text-center p-8 bg-gray-50 rounded-lg border border-gray-200 shadow-sm empty-state-message">
                    <i class="fas fa-chalkboard-teacher text-5xl text-gray-400 mb-4 animate-bounce-slow"></i>
                    <p class="text-gray-700 text-lg font-semibold">No class schedules defined yet!</p>
                    <p class="text-gray-500 mt-2">Add new class schedules using the form above.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Class ID</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Semester Week</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                                <tr class="transition-all duration-200 ease-in-out">
                                    <td><?php echo htmlspecialchars($class['class_id']); ?></td>
                                    <td><?php echo htmlspecialchars($class['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($class['day_of_week']); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($class['start_time']))); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($class['end_time']))); ?></td>
                                    <td><?php echo htmlspecialchars($class['semester_week']); ?></td>
                                    <td class="flex flex-col sm:flex-row justify-center items-center space-y-2 sm:space-y-0 sm:space-x-2 py-4">
                                        <a href="admin_manage_classes.php?action=edit&class_id=<?php echo htmlspecialchars($class['class_id']); ?>" class="btn-custom-sm btn-edit-custom group">
                                            <i class="fas fa-edit transition-transform duration-300 group-hover:scale-110"></i> <span class="ml-1">Edit</span>
                                        </a>
                                        <button type="button" onclick="showDeleteConfirmationModal(<?php echo htmlspecialchars($class['class_id']); ?>);" class="btn-custom-sm btn-delete-custom group">
                                            <i class="fas fa-trash-alt transition-transform duration-300 group-hover:scale-110"></i> <span class="ml-1">Delete</span>
                                        </button>
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
        <div class="modal-custom-content bg-white p-8 rounded-lg shadow-2xl text-center transform scale-95 transition-transform duration-300">
            <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-yellow-500 mr-3 text-2xl"></i> Confirm Deletion
            </h3>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this class schedule? This action cannot be undone.</p>
            <div class="flex justify-center space-x-4">
                <button type="button" class="btn-custom btn-secondary group" onclick="hideDeleteConfirmationModal()">
                    <i class="fas fa-times-circle mr-2"></i> Cancel
                </button>
                <form id="deleteForm" action="admin_manage_classes.php" method="POST" class="inline-block">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="class_id" id="modalClassId">
                    <button type="submit" class="btn-custom btn-danger group">
                        <i class="fas fa-trash-alt mr-2"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>


    <script>
        // Improved message display with manual close
        document.addEventListener('DOMContentLoaded', function() {
            const alertMessage = document.getElementById('alertMessage');
            if (alertMessage) {
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    alertMessage.style.opacity = '0';
                    alertMessage.style.transform = 'translateY(-20px)';
                    alertMessage.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
                    setTimeout(() => alertMessage.remove(), 500); 
                }, 5000); 
            }
        });

        function showDeleteConfirmationModal(classId) {
            document.getElementById('modalClassId').value = classId;
            const modal = document.getElementById('deleteConfirmationModal');
            modal.classList.remove('hidden');
            modal.querySelector('.modal-custom-content').classList.add('scale-100');
            modal.querySelector('.modal-custom-content').classList.remove('scale-95');
        }

        function hideDeleteConfirmationModal() {
            const modal = document.getElementById('deleteConfirmationModal');
            modal.querySelector('.modal-custom-content').classList.remove('scale-100');
            modal.querySelector('.modal-custom-content').classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300); // Wait for transform transition to complete
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
        gap: 1.5rem; /* Space between items */
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
        font-size: 2.25rem; /* Increased font size for desktop */
    }

    .border-bottom-title {
        border-bottom: 2px solid #e9ecef; /* Subtle border for titles */
        padding-bottom: 1.0rem; /* Increased padding below text */
        margin-bottom: 2.5rem !important; /* Increased margin below the border line */
        font-weight: 700;
        color: #495057;
    }

    /* Info Card for Profile Details */
    .info-card {
        transition: all 0.3s ease-in-out;
        border: 1px solid rgba(0,0,0,0.05); /* Light border */
        box-shadow: 0 4px 12px rgba(0,0,0,0.05); /* Soft shadow */
    }
    .info-card:hover {
        transform: translateY(-3px) scale(1.01);
        box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }
    .info-card i {
        /* Adjust icon size and color based on context if needed */
    }

    /* Form Control Customization */
    .form-group {
        position: relative;
        margin-bottom: 1.5rem; /* Increased margin for better spacing between form fields */
    }

    .form-label {
        display: block;
        text-align: left;
        font-weight: 600;
        color: #495057;
        margin-bottom: 0.5rem;
        transition: color 0.2s ease-in-out; /* Smooth transition for label color */
    }

    .form-control-custom {
        display: block;
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fcfcfc;
        background-clip: padding-box;
        border: 1px solid #e2e8f0;
        border-radius: 0.6rem; /* Slightly more rounded inputs */
        box-shadow: inset 0 1px 4px rgba(0, 0, 0, 0.05); /* Enhanced inset shadow */
        transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out, background-color 0.2s ease-in-out, transform 0.2s ease-in-out;
    }

    .form-control-custom:focus {
        background-color: #ffffff;
        border-color: #6a0dad; /* Primary purple focus border */
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(106, 13, 173, 0.2), 0 4px 12px rgba(0,0,0,0.1); /* Subtle glow + soft shadow on focus */
        transform: translateY(-2px); /* Slight lift on focus */
    }
    
    .form-group .form-control-custom:focus + .form-label {
        color: #6a0dad; /* Change label color on input focus */
    }

    /* Custom Buttons for Form & Table */
    .btn-custom {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        padding: 0.85rem 2rem; /* Adjusted padding */
        border-radius: 0.75rem; /* More rounded buttons */
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        text-decoration: none; /* Ensure no underline on anchor buttons */
        position: relative; /* For the subtle overlay on hover */
        overflow: hidden; /* Hide overflow from the hover effect */
    }

    .btn-custom.btn-primary {
        background: linear-gradient(45deg, #6a0dad, #8a2be2); /* Purple gradient */
        border: none;
        color: white;
        box-shadow: 0 5px 15px rgba(138, 43, 226, 0.3);
    }
    .btn-custom.btn-primary:hover {
        background: linear-gradient(45deg, #8a2be2, #6a0dad); /* Reverse gradient on hover */
        transform: translateY(-3px) scale(1.02); /* Slight lift and scale */
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
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 20px rgba(108, 117, 125, 0.4);
    }

    .btn-custom.btn-accent { /* Accent button color (for PDF/CSV in student dashboard) */
        background: linear-gradient(45deg, #2563EB, #4F46E5); /* Blue to Indigo gradient */
        border: none;
        color: white;
        box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
    }
    .btn-custom.btn-accent:hover {
        background: linear-gradient(45deg, #4F46E5, #2563EB);
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
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
        padding: 1rem 1.25rem; /* Adjusted padding */
        border-bottom: 2px solid #dee2e6;
        vertical-align: middle;
    }

    .table-custom tbody td {
        padding: 0.8rem 1.25rem; /* Adjusted padding */
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

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.4em 0.8em;
        border-radius: 0.75rem; /* More rounded badges */
        font-weight: 600;
        font-size: 0.85em;
        white-space: nowrap;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border: 1px solid transparent;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: all 0.2s ease;
    }
    .status-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
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

    /* Message Alert Styling */
    .message-container {
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between; /* Space out content and close button */
        font-weight: 600;
        text-align: center;
        position: relative; /* For close button positioning */
        transition: opacity 0.5s ease-out, transform 0.5s ease-out; /* For auto-dismiss animation */
        animation: fadeInDown 0.5s ease-out; /* Entry animation */
    }

    .message-container.success {
        background-color: #d1fae5; /* Green-100 */
        border-color: #34d399; /* Green-400 */
        color: #065f46; /* Green-700 */
    }
    .message-container.danger {
        background-color: #fee2e2; /* Red-100 */
        border-color: #ef4444; /* Red-400 */
        color: #991b1b; /* Red-700 */
    }
    .message-container.warning {
        background-color: #fffbeb; /* Yellow-100 */
        border-color: #fbbf24; /* Yellow-400 */
        color: #92400e; /* Yellow-700 */
    }

    .message-container .close-btn {
        background: none;
        border: none;
        color: inherit;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0.2rem;
        margin-left: 1rem; /* Space from message text */
        line-height: 1; /* Align icon better */
        opacity: 0.7;
        transition: opacity 0.2s ease;
    }

    .message-container .close-btn:hover {
        opacity: 1;
    }

    /* Custom Modal Styling */
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

    /* New Animations */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .fade-in-up {
        animation: fadeInUp 0.7s ease-out forwards;
        opacity: 0; /* Start hidden */
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes bounce {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-5px);
        }
    }

    .bounce-on-hover:hover {
        animation: bounce 0.6s ease-in-out infinite;
    }

    @keyframes pulseText {
        0% { color: inherit; }
        50% { color: #8A2BE2; } /* A slight pulse to a vibrant color */
        100% { color: inherit; }
    }

    .animate-pulse-text {
        animation: pulseText 3s infinite ease-in-out;
    }

    @keyframes bounceSlow {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-10px);
        }
    }

    .animate-bounce-slow {
        animation: bounceSlow 3s ease-in-out infinite;
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
