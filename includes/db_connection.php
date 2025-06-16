    <?php

    // --- Database Connection Configuration ---
    // Make sure these match your MySQL database credentials
    define('DB_HOST', 'localhost'); // Your database host (e.g., 'localhost' or an IP address)
    define('DB_NAME', 'smart_attendance_db'); // The name of the database you created earlier
    define('DB_USER', 'root'); // Your MySQL username (default for XAMPP/WAMP is 'root')
    define('DB_PASS', ''); // Your MySQL password (default for XAMPP/WAMP is empty)

    // --- Establish Database Connection using PDO ---
    try {
        // Create a new PDO instance
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch results as associative arrays by default
                PDO::ATTR_EMULATE_PREPARES => false, // Disable emulation for better security and performance
            ]
        );
        // echo "Database connection successful!<br>"; // For testing: remove in production

    } catch (PDOException $e) {
        // Handle connection errors gracefully
        // In a real application, you might log this error and display a user-friendly message.
        die("Database connection failed: " . $e->getMessage());
    }

    ?>
    