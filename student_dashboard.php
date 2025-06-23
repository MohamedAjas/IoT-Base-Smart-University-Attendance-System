<?php
// Include the database connection file
require_once 'includes/db_connection.php';
// Removed: require_once 'includes/tcpdf/tcpdf.php'; // No longer using TCPDF directly here

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
    // Fetch student's profile information (needed for filename for CSV)
    $stmt_profile = $pdo->prepare("
        SELECT
            u.full_name,
            u.reg_no,
            u.email,
            s.faculty
        FROM
            users u
        JOIN
            students s ON u.user_id = s.user_id
        WHERE
            u.user_id = :user_id
        LIMIT 1
    ");
    $stmt_profile->execute([':user_id' => $user_id]);
    $student_info = $stmt_profile->fetch(PDO::FETCH_ASSOC);

    if (!$student_info) {
        // This should ideally not happen for a logged-in student but good to handle
        die('Student profile not found. Please contact support.');
    }

    // Fetch student's attendance records (common for display and export)
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
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Only set message, don't die, so the page can still load with error
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">Error fetching attendance data: ' . $e->getMessage() . '</div>';
}


// --- Handle CSV Generation Request (Directly in this file) ---
// This part MUST be BEFORE any HTML output, as it sends HTTP headers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_csv') {
    $requested_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    // Security check: Ensure the requested user_id matches the logged-in user's ID
    if ($requested_user_id !== $user_id) {
        die('Unauthorized attempt to generate CSV report.');
    }

    if (empty($attendance_records)) {
        // If no records, redirect back with a message (or generate empty CSV)
        header('Location: student_dashboard.php?message=' . urlencode('No attendance records available to generate CSV.'));
        exit();
    }

    // --- CSV Generation ---
    $filename = 'Attendance_Report_' . $student_info['reg_no'] . '.csv';

    // Set headers to force download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Clear any output buffer that might exist (crucial for headers to work)
    ob_end_clean(); 

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write the CSV header row
    fputcsv($output, ['Subject Code', 'Subject Name', 'Date', 'Time In', 'Status']);

    // Write attendance data rows
    foreach ($attendance_records as $record) {
        fputcsv($output, [
            $record['subject_code'],
            $record['subject_name'],
            $record['date'],
            date('h:i A', strtotime($record['time_in'])), // Format time for CSV
            $record['status']
        ]);
    }

    fclose($output); // Close the output stream
    exit(); // Crucial to stop script execution after file download
}

// Display messages coming from redirection (e.g., from CSV error)
if (isset($_GET['message'])) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">' . htmlspecialchars($_GET['message']) . '</div>';
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
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <header class="header-bar">
        <!-- Top Row: University Logo and Name -->
        <div class="header-top-row">
            <div class="header-left">
                <img src="images/logo.png" alt="University Logo" class="university-logo-header">
                <h1>University Attendance System</h1>
            </div>
        </div>
        
        <!-- Bottom Row: Navigation and User Info -->
        <div class="header-bottom-row">
            <nav class="header-nav">
                <ul>
                    <li><a href="student_dashboard.php" class="nav-link current-page">Dashboard</a></li>
                </ul>
            </nav>

            <div class="header-right">
                <span>Welcome, <?php echo htmlspecialchars($full_name); ?></span>
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
            <h2 class="card-title text-center mb-4 pb-2 border-bottom-title">Your Profile</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4">
                <div class="info-card flex flex-col items-center justify-center p-4 rounded-lg shadow-smooth bg-purple-50 transform transition-transform duration-300 hover:scale-[1.02] hover:shadow-lg">
                    <i class="fas fa-user-graduate text-4xl text-purple-600 mb-3 bounce-on-hover"></i>
                    <p class="text-sm text-gray-600 font-medium">Your Role:</p>
                    <p class="text-xl font-extrabold text-purple-800 animate-pulse-text">Student</p>
                </div>
                <div class="info-card flex flex-col items-center justify-center p-4 rounded-lg shadow-smooth bg-blue-50 transform transition-transform duration-300 hover:scale-[1.02] hover:shadow-lg">
                    <i class="fas fa-id-badge text-4xl text-blue-600 mb-3 bounce-on-hover"></i>
                    <p class="text-sm text-gray-600 font-medium">Registration No:</p>
                    <p class="text-xl font-extrabold text-blue-800 animate-pulse-text"><?php echo htmlspecialchars($reg_no); ?></p>
                </div>
                <div class="info-card flex flex-col items-center justify-center p-4 rounded-lg shadow-smooth bg-green-50 transform transition-transform duration-300 hover:scale-[1.02] hover:shadow-lg">
                    <i class="fas fa-envelope text-4xl text-green-600 mb-3 bounce-on-hover"></i>
                    <p class="text-sm text-gray-600 font-medium">Email:</p>
                    <p class="text-xl font-extrabold text-green-800 animate-pulse-text"><?php echo htmlspecialchars($email); ?></p>
                </div>
            </div>
        </div>

        <div class="card card-custom fade-in-up">
            <div class="flex flex-col md:flex-row justify-center md:justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-gray-800 mb-3 md:mb-0">Your Attendance History</h3>
                <div class="flex flex-col sm:flex-row justify-center sm:justify-end space-y-2 sm:space-y-0 sm:space-x-4 w-full md:w-auto mt-4 md:mt-0">
                    <!-- Buttons for Generate PDF/CSV -->
                    <!-- PDF button will trigger browser print -->
                    <button type="button" onclick="window.print()" class="btn-custom btn-accent group">
                        <i class="fas fa-file-pdf mr-2 transition-transform duration-300 group-hover:scale-110"></i> Generate PDF
                    </button>
                    <!-- CSV button will submit a form for server-side generation -->
                    <form action="student_dashboard.php" method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="generate_csv">
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                        <button type="submit" class="btn-custom btn-accent group">
                            <i class="fas fa-file-csv mr-2 transition-transform duration-300 group-hover:scale-110"></i> Generate CSV
                        </button>
                    </form>
                </div>
            </div>

            <?php if (empty($attendance_records)): ?>
                <div class="text-center p-8 bg-gray-50 rounded-lg border border-gray-200 shadow-sm empty-state-message">
                    <i class="fas fa-frown text-5xl text-gray-400 mb-4 animate-bounce-slow"></i>
                    <p class="text-gray-700 text-lg font-semibold">No attendance records found yet!</p>
                    <p class="text-gray-500 mt-2">Your attendance data will appear here after your first class. Stay tuned!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table-custom table-striped table-hover">
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
                                <tr class="transition-all duration-200 ease-in-out">
                                    <td><?php echo htmlspecialchars($record['subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['date']); ?></td>
                                    <td><?php echo htmlspecialchars(date('h:i A', strtotime($record['time_in']))); ?></td>
                                    <td>
                                        <span class="status-badge 
                                            <?php
                                                if ($record['status'] === 'Present') echo 'status-present';
                                                elseif ($record['status'] === 'Absent') echo 'status-absent';
                                                else echo 'status-medical'; // Medical
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
    </script>
</body>
</html>

<style>
    /* Styling for print media to hide header/footer and only show content */
    @media print {
        header, .header-bar, .container-wrapper > .card:first-of-type, .message-container, .btn-custom {
            display: none !important; /* Hide header, profile card, messages, and buttons */
        }
        body {
            padding-top: 0 !important;
            background: none !important; /* No background in print */
            color: black !important; /* Ensure black text for print */
        }
        .container-wrapper {
            max-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 100% !important;
        }
        .card-custom {
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            background: none !important;
        }
        h1, h2, h3 {
            color: black !important;
            text-align: center;
        }
        /* Ensure table borders and styling remain for readability */
        .table-custom {
            border-collapse: collapse;
            width: 100%;
        }
        .table-custom th, .table-custom td {
            border: 1px solid #ddd; /* Light grey border */
            padding: 8px;
        }
        .table-custom thead th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .status-badge {
            border: 1px solid #ccc;
            padding: 2px 5px;
            border-radius: 5px;
        }
        .status-present { background-color: #e6ffe6; color: #006600; border-color: #009900;}
        .status-absent { background-color: #ffe6e6; color: #cc0000; border-color: #ff0000;}
        .status-medical { background-color: #fffbe6; color: #996600; border-color: #ffcc00;}
    }


    /* General Styling (from previous version, ensuring responsiveness) */
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

    .btn-custom.btn-accent { /* New button color */
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
