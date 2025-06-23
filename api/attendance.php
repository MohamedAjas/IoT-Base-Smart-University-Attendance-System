<?php
// Include the database connection file
require_once '../includes/db_connection.php';

// --- Telegram Bot API Configuration ---
// IMPORTANT: Replace with your actual Telegram Bot Token and Chat ID
define('TELEGRAM_BOT_TOKEN', '8178353984:AAFvUbxJdF3HVXH0qOjGOwDe5ZsVwLo7__4'); // Your new token from BotFather
define('TELEGRAM_CHAT_ID', '1515728886');   // Your Telegram Chat ID, extracted from getUpdates

/**
 * Sends a message to a Telegram chat via the Bot API.
 * @param string $message_text The text message to send. Supports HTML parse_mode.
 * @return bool True on success, false on failure.
 */
function sendTelegramMessage($message_text) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message_text,
        'parse_mode' => 'HTML' // Allows for basic HTML formatting like bold, italics
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    // Suppress warnings with @ and handle errors explicitly
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log("Telegram API call failed.");
        return false;
    } else {
        $response = json_decode($result, true);
        if ($response['ok'] === false) {
            error_log("Telegram API error: " . $response['description']);
            return false;
        }
    }
    return true;
}

// Set content type to JSON for API response
header('Content-Type: application/json');

$response = [
    'status' => 'error',
    'message' => 'An unknown error occurred.'
];

// Get the raw POST data from ESP8266
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true); // Decode JSON to associative array

// Basic validation for JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    $response['message'] = 'Invalid JSON input.';
    echo json_encode($response);
    exit();
}

if (!isset($data['rfid_tag_id']) || !isset($data['timestamp'])) {
    $response['message'] = 'Missing RFID tag ID or timestamp.';
    echo json_encode($response);
    exit();
}

// Sanitize inputs
$rfid_tag_id = filter_var($data['rfid_tag_id'], FILTER_SANITIZE_STRING);
$timestamp_str = filter_var($data['timestamp'], FILTER_SANITIZE_STRING);

// Extract date and time components from the provided timestamp
$date = date('Y-m-d', strtotime($timestamp_str));
$time_in = date('H:i:s', strtotime($timestamp_str));
$day_of_week = date('l', strtotime($timestamp_str)); // e.g., "Monday"

// Initialize current semester week from admin settings
$current_semester_week = 1; // Default
$semester_start_date = null;

try {
    $stmt_settings = $pdo->query("SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('semester_weeks', 'semester_start_date')");
    $settings = $stmt_settings->fetchAll(PDO::FETCH_KEY_PAIR); // Fetches as ['setting_name' => 'setting_value']

    if (isset($settings['semester_start_date'])) {
        $semester_start_date = $settings['semester_start_date'];
        // Calculate current semester week based on semester start date and attendance date
        if ($semester_start_date) {
            $start_timestamp = strtotime($semester_start_date);
            $current_timestamp = strtotime($date); // Use the attendance date for calculation
            
            if ($current_timestamp >= $start_timestamp) {
                $diff_seconds = $current_timestamp - $start_timestamp;
                $diff_weeks = floor($diff_seconds / (7 * 24 * 60 * 60)); // Weeks since start date
                $current_semester_week = $diff_weeks + 1; // Week 1 starts on the start date
            }
        }
    }

} catch (PDOException $e) {
    error_log("Error fetching settings for semester week calculation: " . $e->getMessage());
    // Continue with default current_semester_week if settings fetch fails
}


try {
    // 1. Find the student by RFID tag ID
    $stmt_student = $pdo->prepare("SELECT student_id, user_id, reg_no FROM students WHERE rfid_tag_id = :rfid_tag_id LIMIT 1");
    $stmt_student->execute([':rfid_tag_id' => $rfid_tag_id]);
    $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        $response['message'] = 'RFID tag not registered.';
        sendTelegramMessage("<b>⚠️ Unregistered Tag Scanned!</b>\n\nRFID Tag ID: <code>" . htmlspecialchars($rfid_tag_id) . "</code>\nTimestamp: " . htmlspecialchars($timestamp_str));
        echo json_encode($response);
        exit();
    }

    $student_id = $student['student_id'];
    $student_reg_no = $student['reg_no'];

    // Fetch full name from users table using user_id
    $stmt_full_name = $pdo->prepare("SELECT full_name FROM users WHERE user_id = :user_id LIMIT 1");
    $stmt_full_name->execute([':user_id' => $student['user_id']]);
    $student_full_name = $stmt_full_name->fetchColumn();


    // 2. Find matching class schedule for today's day and current semester week
    $stmt_class = $pdo->prepare("
        SELECT
            c.class_id, s.subject_id, s.subject_code, s.subject_name
        FROM
            classes c
        JOIN
            subjects s ON c.subject_id = s.subject_id
        WHERE
            c.day_of_week = :day_of_week AND
            :time_in BETWEEN c.start_time AND c.end_time AND
            c.semester_week = :semester_week
        LIMIT 1
    ");
    $stmt_class->execute([
        ':day_of_week' => $day_of_week,
        ':time_in' => $time_in,
        ':semester_week' => $current_semester_week
    ]);
    $class = $stmt_class->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        $response['message'] = 'No active class schedule found for this time, day, and semester week.';
        sendTelegramMessage("<b>⚠️ No Class Found!</b>\n\nStudent: " . htmlspecialchars($student_full_name) . " (Reg No: " . htmlspecialchars($student_reg_no) . ")\nTime: " . htmlspecialchars(date('h:i:s A', strtotime($time_in))) . " on " . htmlspecialchars($day_of_week) . " (Week " . htmlspecialchars($current_semester_week) . ")");
        echo json_encode($response);
        exit();
    }

    $subject_id = $class['subject_id'];
    $subject_name = $class['subject_name'];
    $subject_code = $class['subject_code'];

    // 3. Check if attendance already recorded for this student, subject, and date
    $stmt_check_attendance = $pdo->prepare("SELECT attendance_id, status FROM attendance WHERE student_id = :student_id AND subject_id = :subject_id AND date = :date LIMIT 1");
    $stmt_check_attendance->execute([
        ':student_id' => $student_id,
        ':subject_id' => $subject_id,
        ':date' => $date
    ]);
    $existing_attendance = $stmt_check_attendance->fetch(PDO::FETCH_ASSOC);

    $attendance_status = 'Present'; // Default status for new attendance

    if ($existing_attendance) {
        // Attendance already recorded for today for this subject
        $response['status'] = 'warning';
        $response['message'] = 'Attendance already recorded for ' . $subject_name . ' today. Status: ' . $existing_attendance['status'];
        $response['data'] = [
            'student_id' => $student_id,
            'subject_id' => $subject_id,
            'date' => $date,
            'time_in' => $time_in,
            'status' => $existing_attendance['status']
        ];
        
        // Send a Telegram notification for re-scan/already recorded
        $telegram_message = "<b>ℹ️ Attendance Already Recorded:</b>\n\n";
        $telegram_message .= "<b>Student:</b> " . htmlspecialchars($student_full_name) . " (Reg No: " . htmlspecialchars($student_reg_no) . ")\n";
        $telegram_message .= "<b>Subject:</b> " . htmlspecialchars($subject_name) . " (" . htmlspecialchars($subject_code) . ")\n";
        $telegram_message .= "<b>Date:</b> " . htmlspecialchars($date) . "\n";
        $telegram_message .= "<b>Time In:</b> " . htmlspecialchars(date('h:i:s A', strtotime($time_in))) . "\n";
        $telegram_message .= "<b>Status:</b> " . htmlspecialchars($existing_attendance['status']) . "\n";
        $telegram_message .= "<i>(Attendance for this class on this date was already recorded.)</i>";

        sendTelegramMessage($telegram_message);

        echo json_encode($response);
        exit();

    } else {
        // No existing attendance, record new attendance as 'Present'
        $stmt_insert_attendance = $pdo->prepare("INSERT INTO attendance (student_id, subject_id, date, time_in, status) VALUES (:student_id, :subject_id, :date, :time_in, :status)");
        $stmt_insert_attendance->execute([
            ':student_id' => $student_id,
            ':subject_id' => $subject_id,
            ':date' => $date,
            ':time_in' => $time_in,
            ':status' => $attendance_status
        ]);
        $attendance_id = $pdo->lastInsertId();

        $response['status'] = 'success';
        $response['message'] = 'Attendance recorded successfully.';
        $response['data'] = [
            'student_id' => $student_id,
            'subject_id' => $subject_id,
            'date' => $date,
            'time_in' => $time_in,
            'status' => $attendance_status
        ];

        // --- Send Telegram Notification for New Attendance ---
        $telegram_message = "<b>✅ New Attendance Recorded!</b>\n\n";
        $telegram_message .= "<b>Student:</b> " . htmlspecialchars($student_full_name) . " (Reg No: " . htmlspecialchars($student_reg_no) . ")\n";
        $telegram_message .= "<b>Subject:</b> " . htmlspecialchars($subject_name) . " (" . htmlspecialchars($subject_code) . ")\n";
        $telegram_message .= "<b>Date:</b> " . htmlspecialchars($date) . "\n";
        $telegram_message .= "<b>Time In:</b> " . htmlspecialchars(date('h:i:s A', strtotime($time_in))) . "\n";
        $telegram_message .= "<b>Status:</b> " . htmlspecialchars($attendance_status) . "\n";

        sendTelegramMessage($telegram_message);
        // You can check the return value of sendTelegramMessage if you want to log success/failure
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Attendance API Database Error: " . $e->getMessage()); // Log detailed error
    // Also send a Telegram notification for critical errors
    sendTelegramMessage("<b>❌ System Error:</b>\n\nFailed to record attendance!\nError: " . htmlspecialchars($e->getMessage()) . "\nRFID: <code>" . htmlspecialchars($rfid_tag_id) . "</code>\nTimestamp: " . htmlspecialchars($timestamp_str));
}

echo json_encode($response);
exit();

?>