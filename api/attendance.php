<?php
// Disable error reporting in production for security, but useful for debugging
ini_set('display_errors', 1); // <--- UNCOMMENTED for debugging
error_reporting(E_ALL);     // <--- UNCOMMENTED for debugging

// Set content type to application/json for API response
header('Content-Type: application/json');

// Include the database connection file
// Adjust path if your 'includes' folder is not one level up from 'api'
require_once '../includes/db_connection.php';

// Function to send JSON response and exit
function sendJsonResponse($status, $message, $data = []) {
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit();
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Invalid request method. Only POST is allowed.');
}

// Get raw POST data (JSON from ESP32)
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true); // Decode JSON into associative array

// Validate incoming data
if (!isset($data['rfid_tag_id']) || empty($data['rfid_tag_id']) ||
    !isset($data['timestamp']) || empty($data['timestamp'])) {
    sendJsonResponse('error', 'Missing RFID tag ID or timestamp.');
}

$rfid_tag_id = filter_var($data['rfid_tag_id'], FILTER_SANITIZE_STRING);
$timestamp_str = filter_var($data['timestamp'], FILTER_SANITIZE_STRING);

// Validate timestamp format (e.g., YYYY-MM-DD HH:MM:SS)
if (!preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $timestamp_str)) {
    sendJsonResponse('error', 'Invalid timestamp format. Expected YYYY-MM-DD HH:MM:SS.');
}

$scan_date = date('Y-m-d', strtotime($timestamp_str));
$scan_time = date('H:i:s', strtotime($timestamp_str));

try {
    // 1. Find the student_id based on RFID tag ID
    $stmt_student = $pdo->prepare("SELECT student_id, user_id FROM students WHERE rfid_tag_id = :rfid_tag_id LIMIT 1");
    $stmt_student->execute([':rfid_tag_id' => $rfid_tag_id]);
    $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        sendJsonResponse('error', 'RFID tag ID not registered to any student.', ['rfid_tag_id' => $rfid_tag_id]);
    }
    $student_id = $student['student_id'];
    $user_id = $student['user_id']; // Useful if we need user details later

    // 2. Get semester start date from settings
    $stmt_settings = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'semester_start_date' LIMIT 1");
    $stmt_settings->execute();
    $semester_start_date_str = $stmt_settings->fetchColumn();

    if (!$semester_start_date_str) {
        sendJsonResponse('error', 'Semester start date not configured in system settings. Please set it in admin panel.');
    }
    $semester_start_date = new DateTime($semester_start_date_str);
    $scan_datetime = new DateTime($timestamp_str);

    // Calculate semester week
    // This calculates the difference in weeks from the semester start date to the scan date
    // If the scan date is before the semester start date, interval will be negative.
    // For simplicity, we are considering days. Difference in seconds might be more precise for "same week".
    // $interval->days gives the absolute difference in days
    $diff_days = $scan_datetime->diff($semester_start_date)->days;

    // Check if scan date is before semester start date
    if ($scan_datetime < $semester_start_date) {
        sendJsonResponse('error', 'Attendance scan date (' . $scan_date . ') is before the configured Semester Start Date (' . $semester_start_date_str . ').');
    }

    $semester_week_number = floor($diff_days / 7) + 1;

    // Ensure semester_week_number is within reasonable bounds, e.g., 1 to 20
    // The max value (20) should ideally match or be slightly higher than semester_weeks from settings
    // For now, let's keep 20 as a hardcoded sanity check.
    if ($semester_week_number < 1 || $semester_week_number > 20) { // Adjust max week as needed
         sendJsonResponse('error', 'Attendance scan date outside expected semester weeks range. Calculated Week: ' . $semester_week_number . '. Please check semester settings or scan date.');
    }


    // 3. Find the subject being taught at this time and day for this semester week
    // This assumes classes are consistently scheduled by day/time/semester week
    $day_of_week = date('l', strtotime($scan_date)); // e.g., "Monday", "Tuesday"

    $stmt_class = $pdo->prepare("
        SELECT subject_id
        FROM classes
        WHERE day_of_week = :day_of_week
          AND start_time <= :scan_time_start  -- Changed placeholder
          AND end_time >= :scan_time_end      -- Changed placeholder
          AND semester_week = :semester_week
        LIMIT 1
    ");
    $stmt_class->execute([
        ':day_of_week' => $day_of_week,
        ':scan_time_start' => $scan_time, // Bound to new placeholder
        ':scan_time_end' => $scan_time,   // Bound to new placeholder
        ':semester_week' => $semester_week_number
    ]);
    $class_info = $stmt_class->fetch(PDO::FETCH_ASSOC);

    if (!$class_info) {
        sendJsonResponse('error', 'No class scheduled for this time, day (' . $day_of_week . '), and semester week (' . $semester_week_number . ').', ['date' => $scan_date, 'time' => $scan_time]);
    }
    $subject_id = $class_info['subject_id'];

    // 4. Check for duplicate attendance (prevent multiple entries for same student, subject, date)
    $stmt_duplicate = $pdo->prepare("SELECT attendance_id FROM attendance WHERE student_id = :student_id AND subject_id = :subject_id AND date = :date LIMIT 1");
    $stmt_duplicate->execute([
        ':student_id' => $student_id,
        ':subject_id' => $subject_id,
        ':date' => $scan_date
    ]);
    if ($stmt_duplicate->fetch()) {
        sendJsonResponse('warning', 'Attendance already recorded for this student, subject, and date.', ['student_id' => $student_id, 'subject_id' => $subject_id, 'date' => $scan_date]);
    }

    // 5. Record attendance
    $stmt_insert = $pdo->prepare("INSERT INTO attendance (student_id, subject_id, date, time_in, status) VALUES (:student_id, :subject_id, :date, :time_in, 'Present')");
    $stmt_insert->execute([
        ':student_id' => $student_id,
        ':subject_id' => $subject_id,
        ':date' => $scan_date,
        ':time_in' => $scan_time
    ]);

    sendJsonResponse('success', 'Attendance recorded successfully.', ['student_id' => $student_id, 'subject_id' => $subject_id, 'date' => $scan_date, 'time_in' => $scan_time]);

    } catch (PDOException $e) {
        // Log the detailed error for debugging, but send a generic message to the client
        error_log("API Attendance Error: " . $e->getMessage());
        sendJsonResponse('error', 'A database error occurred while recording attendance. Details: ' . $e->getMessage());
    } catch (Exception $e) {
        // Catch general exceptions (e.g., from DateTime)
        error_log("API General Error: " . $e->getMessage());
        sendJsonResponse('error', 'An unexpected error occurred. Details: ' . $e->getMessage());
    }

?>
