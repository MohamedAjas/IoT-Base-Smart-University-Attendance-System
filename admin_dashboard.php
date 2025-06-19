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
$full_name = $_SESSION['full_name'] ?? 'Admin'; // Provide a default value if not set
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
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">Error fetching dashboard data: ' . $e->getMessage() . '</div>';
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
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <header class="header-bar">
        <!-- University Logo and Name -->
        <div class="flex items-center space-x-4">
            <img src="images/logo.png" alt="University Logo" class="university-logo-header">
            <h1 class="text-3xl font-extrabold text-white hidden md:block">University Attendance Admin Panel</h1>
        </div>
        
        <nav class="flex-grow flex justify-center">
            <ul class="flex space-x-3 md:space-x-6">
                <li><a href="admin_dashboard.php" class="nav-link current-page px-5 py-3.5 rounded-[10px] text-white font-semibold transition-all duration-300 shadow-md">Dashboard</a></li>
                <li><a href="admin_manage_students.php" class="nav-link">Manage Students</a></li>
                <li><a href="admin_manage_subjects.php" class="nav-link">Manage Subjects</a></li>
                <li><a href="admin_manage_classes.php" class="nav-link">Manage Classes</a></li>
                <li><a href="admin_view_attendance.php" class="nav-link">View Attendance</a></li>
                <li><a href="admin_settings.php" class="nav-link">Settings</a></li>
            </ul>
        </nav>

        <div class="flex items-center space-x-4">
            <span class="text-white text-lg font-semibold hidden md:block">Welcome, <?php echo htmlspecialchars($full_name); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </header>

    <div class="container-wrapper">
        <?php 
        // Display any error messages
        if (!empty($message)) {
            echo $message;
        }
        ?>

        <div class="card mb-10 welcome-card">
            <h2 class="text-3xl font-extrabold text-gray-800 mb-6 text-center">Admin Dashboard</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="info-box shadow-lg">
                    <div class="icon-circle bg-blue-500 text-white"><i class="fas fa-user-shield"></i></div>
                    <p class="text-md text-gray-600 mt-2">Your Role:</p>
                    <p class="text-2xl font-extrabold text-blue-800">Administrator</p>
                </div>
                <div class="info-box shadow-lg">
                    <div class="icon-circle bg-purple-500 text-white"><i class="fas fa-envelope"></i></div>
                    <p class="text-md text-gray-600 mt-2">Email:</p>
                    <p class="text-2xl font-extrabold text-purple-800"><?php echo htmlspecialchars($email); ?></p>
                </div>
                <div class="info-box shadow-lg">
                    <div class="icon-circle bg-green-500 text-white"><i class="fas fa-users"></i></div>
                    <p class="text-md text-gray-600 mt-2">Total Students:</p>
                    <p class="text-2xl font-extrabold text-green-800"><?php echo count($students); ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 mb-10">
            <!-- Recent Attendance Overview Card -->
            <div class="card">
                <h3 class="text-2xl font-bold text-gray-800 mb-5">Recent Attendance Overview</h3>
                <?php if (empty($attendance_overview)): ?>
                    <p class="text-gray-600 text-center py-8">No attendance data available yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th class="text-left">Date</th>
                                    <th class="text-center">Present</th>
                                    <th class="text-center">Absent</th>
                                    <th class="text-center">Medical</th>
                                    <th class="text-center">Total Records</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_overview as $record): ?>
                                    <tr>
                                        <td class="text-left"><?php echo htmlspecialchars($record['date']); ?></td>
                                        <td class="text-center"><span class="status-badge-present"><?php echo htmlspecialchars($record['present_count']); ?></span></td>
                                        <td class="text-center"><span class="status-badge-absent"><?php echo htmlspecialchars($record['absent_count']); ?></span></td>
                                        <td class="text-center"><span class="status-badge-medical"><?php echo htmlspecialchars($record['medical_count']); ?></span></td>
                                        <td class="text-center"><?php echo htmlspecialchars($record['total_records']); ?></td> 
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Links / Actions Card -->
            <div class="card">
                <h3 class="text-2xl font-bold text-gray-800 mb-5">Quick Actions</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <a href="admin_manage_students.php" class="btn-action btn-blue-alt">
                        <i class="fas fa-user-graduate icon"></i>
                        <span>Manage Students</span>
                    </a>
                    <a href="admin_manage_subjects.php" class="btn-action btn-purple-alt">
                        <i class="fas fa-book icon"></i>
                        <span>Manage Subjects</span>
                    </a>
                    <a href="admin_manage_classes.php" class="btn-action btn-orange-alt">
                        <i class="fas fa-chalkboard icon"></i>
                        <span>Manage Classes</span>
                    </a>
                    <a href="admin_view_attendance.php" class="btn-action btn-green-alt">
                        <i class="fas fa-eye icon"></i>
                        <span>View All Attendance</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Placeholder for overall attendance statistics / charts -->
        <div class="card mb-8">
            <h3 class="text-2xl font-bold text-gray-800 mb-5">Overall Attendance Statistics</h3>
            <p class="text-gray-600 text-center py-4">This section will contain summarized attendance data and potentially charts (e.g., total present/absent across all students).
            <br> Further charts and detailed statistics can be integrated here to provide deeper insights into attendance patterns.
            </p>
            <!-- Content will be added here later -->
        </div>

    </div>
</body>
</html>

<style>
    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(to bottom right, #edf2f7, #e2e8f0); /* A refined, very light background gradient */
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        color: #333; /* Default text color */
        padding-top: 100px; /* Space for the fixed header */
    }
    .container-wrapper {
        max-width: 1500px; /* Slightly wider container */
        margin: 0 auto;
        padding: 2.5rem; /* Increased padding */
        flex-grow: 1; 
        width: 100%; 
        box-sizing: border-box;
    }
    .header-bar {
        background: linear-gradient(90deg, #1f2937, #374151); /* Dark grey to slightly lighter grey for professional look */
        color: white;
        padding: 1.25rem 3rem; 
        border-bottom-left-radius: 25px; 
        border-bottom-right-radius: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4); /* Stronger, professional shadow */
        margin-bottom: 0; /* No margin-bottom as it's fixed */
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap; 
        gap: 1.5rem; 
        position: fixed; /* Make the header fixed */
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000; /* Ensure it stays on top */
    }
    .university-logo-header {
        height: 60px; /* Larger logo */
        width: auto;
    }
    .header-bar h1 {
        font-size: 2.25rem; 
        font-weight: 800; 
        letter-spacing: -0.025em; 
        text-shadow: 2px 2px 5px rgba(0,0,0,0.2); 
    }
    .nav-link {
        @apply px-5 py-2.5 text-white font-semibold rounded-full transition-all duration-300;
        background-color: transparent; 
        box-shadow: none; /* No individual shadow */
        position: relative;
        overflow: hidden;
        border: none; 
        opacity: 0.8; /* Slightly less opaque by default */
    }
    .nav-link::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 3px;
        background: linear-gradient(90deg, #f0abfc, #a78bfa); /* Bright accent for hover underline */
        border-radius: 2px;
        transform: translateX(-50%);
        transition: width 0.3s ease-out;
    }
    .nav-link:hover {
        opacity: 1; /* Fully opaque on hover */
        transform: translateY(-2px);
    }
    .nav-link:hover::before {
        width: 100%; /* Expand underline on hover */
    }
    .nav-link.current-page {
        background: linear-gradient(45deg, #a78bfa, #8b5cf6); /* Solid gradient for active page */
        box-shadow: 0 4px 10px rgba(139, 92, 246, 0.4);
        font-weight: 800; 
        opacity: 1;
    }
    .nav-link.current-page::before {
        width: 0; /* No underline for active page, as it has a background */
    }

    .logout-btn {
        @apply px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-full transition-colors duration-300 flex items-center space-x-2 shadow-lg;
        font-weight: 700;
        letter-spacing: 0.03em;
        box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3); 
    }
    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
    }
    .card {
        @apply bg-white p-8 rounded-3xl shadow-xl transition-all duration-400 hover:shadow-2xl; /* Refined shadow for elegance */
        border: none; 
        background: #ffffff; 
        overflow: hidden; 
        position: relative;
        z-index: 1; 
        border: 1px solid #e5e7eb; /* Very subtle border for crispness */
    }
    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.03); /* More subtle inner highlight */
        border-radius: inherit;
        z-index: -1;
    }

    .welcome-card {
        background: linear-gradient(135deg, #667eea, #764ba2); /* Elegant purple-blue gradient */
        color: white;
        padding: 3.5rem; /* More internal padding */
        text-align: center;
        box-shadow: 0 18px 45px rgba(0, 0, 0, 0.25); /* Deeper, more diffused shadow */
        position: relative;
        overflow: hidden;
    }
    .welcome-card h2 {
        color: #fff; 
        font-size: 2.8rem; 
        text-shadow: 1px 1px 4px rgba(0,0,0,0.3);
    }
    .welcome-card::before, .welcome-card::after {
        content: '';
        position: absolute;
        background-color: rgba(255, 255, 255, 0.08); /* More subtle background bubbles */
        border-radius: 50%;
        filter: blur(50px);
        z-index: 0;
    }
    .welcome-card::before { 
        top: -80px; left: -80px; width: 200px; height: 200px; transform: rotate(-30deg);
    }
    .welcome-card::after { 
        bottom: -60px; right: -60px; width: 180px; height: 180px; transform: rotate(45deg);
    }
    .welcome-card > * { 
        position: relative; z-index: 1;
    }
    .info-box {
        background: #ffffff; 
        padding: 1.8rem; /* Increased padding */
        border-radius: 18px; /* Slightly less rounded than card, but still soft */
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08); /* Cleaner shadow */
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        min-height: 130px; /* Taller boxes */
        transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        border: 1px solid #e0e7ee; /* Light border */
    }
    .info-box:hover {
        transform: translateY(-8px) scale(1.03); /* More dynamic hover */
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }
    .info-box p {
        margin-bottom: 0.5rem;
        color: #666; 
    }
    .info-box .text-2xl {
        font-weight: 900; 
        letter-spacing: -0.03em;
        margin-top: 0.6rem;
    }
    .icon-circle {
        width: 55px; 
        height: 55px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem; /* Larger icons */
        margin-bottom: 1rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.25); /* More prominent icon shadow */
    }
    .icon-circle.bg-blue-500 { background: linear-gradient(45deg, #4299e1, #63b3ed); } /* Professional blue */
    .icon-circle.bg-purple-500 { background: linear-gradient(45deg, #9f7aea, #b79ff8); } /* Softer purple */
    .icon-circle.bg-green-500 { background: linear-gradient(45deg, #48bb78, #68d391); } /* Balanced green */

    .table-responsive {
        overflow-x: auto;
        border-radius: 20px; 
        box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
        background-color: #ffffff; 
        border: 1px solid #e0e0e0; 
    }
    .attendance-table {
        @apply w-full text-left border-collapse;
        min-width: 750px; 
        border-spacing: 0; 
    }
    .attendance-table th, .attendance-table td {
        @apply py-4 px-7 border-b border-gray-200; /* More padding, refined borders */
        font-size: 0.98rem; /* Slightly larger text */
    }
    .attendance-table th {
        @apply bg-gray-100 text-gray-700 font-bold uppercase tracking-wide;
        background-color: #f8faff; 
        color: #4c5a6d; /* Darker grey for professionalism */
        font-size: 0.9rem; /* Slightly larger heading font */
        padding-top: 1.2rem;
        padding-bottom: 1.2rem;
        border-top: 1px solid #e0e0e0; /* Top border for header */
    }
    .attendance-table tbody tr:nth-child(odd) {
        background-color: #ffffff; 
    }
    .attendance-table tbody tr:nth-child(even) {
        background-color: #fcfdfe; 
    }
    .attendance-table tbody tr:hover {
        background-color: #eef7ff; 
        cursor: pointer;
        transform: scale(1.005);
        box-shadow: 0 3px 12px rgba(0,0,0,0.08); /* More noticeable row hover shadow */
        position: relative;
        z-index: 1;
    }
    .attendance-table td {
        color: #3f516d; /* Professional dark blue-grey for data */
        font-weight: 500; /* Medium weight for readability */
    }
    .status-badge-present {
        @apply px-4 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full;
        background: linear-gradient(45deg, #48bb78, #68d391); 
        color: #fff;
        box-shadow: 0 2px 8px rgba(72, 187, 120, 0.3);
        min-width: 80px; 
        justify-content: center;
        transition: all 0.2s ease-in-out;
    }
    .status-badge-present:hover { transform: translateY(-2px); box-shadow: 0 5px 10px rgba(72, 187, 120, 0.4); }

    .status-badge-absent {
        @apply px-4 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full;
        background: linear-gradient(45deg, #ef4444, #f87171); 
        color: #fff;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        min-width: 80px;
        justify-content: center;
        transition: all 0.2s ease-in-out;
    }
    .status-badge-absent:hover { transform: translateY(-2px); box-shadow: 0 5px 10px rgba(239, 68, 68, 0.4); }

    .status-badge-medical {
        @apply px-4 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full;
        background: linear-gradient(45deg, #4299e1, #63b3ed); 
        color: #fff;
        box-shadow: 0 2px 8px rgba(66, 153, 225, 0.3);
        min-width: 80px;
        justify-content: center;
        transition: all 0.2s ease-in-out;
    }
    .status-badge-medical:hover { transform: translateY(-2px); box-shadow: 0 5px 10px rgba(66, 153, 225, 0.4); }

    .btn-action {
        @apply inline-flex items-center justify-center px-8 py-4.5 border-transparent text-xl font-bold rounded-2xl shadow-xl text-white transition-all duration-400;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        gap: 1rem; 
        position: relative;
        overflow: hidden;
        z-index: 1;
        font-size: 1.15rem; /* Larger text for actions */
        background-size: 200% auto; /* For gradient animation */
        box-shadow: 0 10px 25px rgba(0,0,0,0.2); /* Enhanced shadow */
    }
    .btn-action::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.2); /* Stronger hover overlay */
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.4s ease-out;
        z-index: -1;
    }
    .btn-action:hover::before {
        transform: scaleX(1);
    }
    .btn-action:hover {
        transform: translateY(-8px); /* More pronounced lift */
        box-shadow: 0 20px 40px rgba(0,0,0,0.3); /* Larger, softer shadow */
        background-position: right center; /* Move gradient on hover */
    }
    .btn-action .icon {
        font-size: 1.6rem; /* Larger icons for actions */
    }

    /* Specific button colors - tuned for professional vibrancy */
    .btn-blue-alt {
        background-image: linear-gradient(45deg, #3498db 0%, #2980b9 50%, #3498db 100%); /* Softer, more professional blue */
        box-shadow: 0 6px 18px rgba(52, 152, 219, 0.4);
    }
    .btn-blue-alt:hover {
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.5);
    }
    .btn-purple-alt {
        background-image: linear-gradient(45deg, #9b59b6 0%, #8e44ad 50%, #9b59b6 100%); /* Deeper, richer purple */
        box-shadow: 0 6px 18px rgba(155, 89, 182, 0.4);
    }
    .btn-purple-alt:hover {
        box-shadow: 0 8px 25px rgba(155, 89, 182, 0.5);
    }
    .btn-orange-alt {
        background-image: linear-gradient(45deg, #e67e22 0%, #d35400 50%, #e67e22 100%); /* Earthy, professional orange */
        box-shadow: 0 6px 18px rgba(230, 126, 34, 0.4);
    }
    .btn-orange-alt:hover {
        box-shadow: 0 8px 25px rgba(230, 126, 34, 0.5);
    }
    .btn-green-alt {
        background-image: linear-gradient(45deg, #2ecc71 0%, #27ae60 50%, #2ecc71 100%); /* Fresh, vibrant green */
        box-shadow: 0 6px 18px rgba(46, 204, 113, 0.4);
    }
    .btn-green-alt:hover {
        box-shadow: 0 8px 25px rgba(46, 204, 113, 0.5);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        body {
            padding-top: 130px; /* Adjust padding for fixed header on mobile */
        }
        .header-bar {
            flex-direction: column;
            align-items: center; 
            padding: 1rem 1.5rem;
            border-bottom-left-radius: 15px; 
            border-bottom-right-radius: 15px;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        .header-bar h1 {
            font-size: 1.75rem;
            margin-top: 0.5rem;
            margin-bottom: 1rem; /* Reduced space */
            text-align: center;
        }
        .university-logo-header {
            height: 45px; /* Adjusted size for mobile header */
        }
        .header-bar nav ul {
            flex-direction: column;
            width: 100%;
            align-items: center;
            margin-top: 0.5rem;
        }
        .nav-link {
            width: 90%;
            text-align: center;
            padding: 0.7rem 1rem;
            margin-bottom: 0.5rem; 
            font-size: 0.9rem;
        }
        .header-bar > div:last-child { 
            width: 100%;
            justify-content: center; 
            margin-top: 0.8rem; /* Reduced space */
        }
        .logout-btn {
            width: 90%;
            justify-content: center; 
            font-size: 0.9rem;
            padding: 0.7rem 1rem;
        }
        .container-wrapper {
            padding: 1.2rem; /* Reduced padding */
        }
        .welcome-card, .card {
            padding: 1.8rem; /* Reduced padding */
            border-radius: 15px;
        }
        .welcome-card h2 {
            font-size: 2.2rem;
            margin-bottom: 1.2rem;
        }
        .info-box {
            min-height: auto; 
            padding: 1rem;
            border-radius: 12px; /* Smaller radius */
        }
        .info-box .text-2xl {
            font-size: 1.6rem;
        }
        .icon-circle {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            margin-bottom: 0.6rem;
        }
        .table-responsive {
            margin-top: 1rem; 
            border-radius: 12px; /* Smaller radius */
        }
        th, td {
            padding: 0.7rem 1rem;
            font-size: 0.8rem;
        }
        th {
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
            font-size: 0.75rem;
        }
        .status-badge-present, .status-badge-absent, .status-badge-medical {
            padding: 0.3rem 0.6rem;
            font-size: 0.7rem;
            min-width: 55px;
        }
        .btn-action {
            width: 100%; 
            padding: 0.8rem 1rem;
            font-size: 1rem;
            border-radius: 12px;
            gap: 0.8rem;
        }
        .btn-action .icon {
            font-size: 1.1rem;
        }
    }
</style>
