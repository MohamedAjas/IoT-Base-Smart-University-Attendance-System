    <?php
    // Include the database connection file
    require_once 'includes/db_connection.php';

    // If the script reaches this point, the connection was successful (due to die() on failure)
    echo "<h1>Database Connection Test</h1>";
    echo "<p>Successfully connected to the database: <strong>" . DB_NAME . "</strong></p>";
    echo "<p>If you see this message, your PHP environment and database connection are set up correctly.</p>";

    // You can even try a simple query to verify
    try {
        $stmt = $pdo->query("SELECT 1"); // A very simple query to just get a result
        if ($stmt) {
            echo "<p>Basic query successful.</p>";
        }
    } catch (PDOException $e) {
        echo "<p>Error running basic query: " . $e->getMessage() . "</p>";
    }

    ?>
    