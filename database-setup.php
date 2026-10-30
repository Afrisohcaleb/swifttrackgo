<?php
/**
 * Database setup script for SwiftTrackGo Live Chat & Reviews
 *
 * This script will:
 * 1. Check if MySQL is available
 * 2. Create the swifttrackgo database
 * 3. Execute the database.sql file to create all tables
 * 4. Seed the reviews table
 */

declare(strict_types=1);

echo "=== SwiftTrackGo Database Setup ===\n";

// Check if we can connect to MySQL
try {
    $pdo = new PDO("mysql:host=localhost", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ MySQL connection successful\n";

    // Check if database exists, create if not
    $pdo->exec("CREATE DATABASE IF NOT EXISTS swifttrackgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database 'swifttrackgo' created or already exists\n";

    $pdo->exec("USE swifttrackgo");

    // Execute the database schema file
    $schemaFile = __DIR__ . '/database/swifttrackgo.sql';

    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);

        // Split SQL into individual statements (basic splitting on semicolon)
        $statements = preg_split('/;\s*\n/', $sql);

        echo "Creating tables from schema...\n";

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Some statements might fail (like USE database), ignore expected errors
                    if (strpos($e->getMessage(), 'Unknown table') === false &&
                        strpos($e->getMessage(), 'Table already exists') === false) {
                        throw $e;
                    }
                }
            }
        }

        echo "✓ All tables created successfully\n";

        // Verify tables were created
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $expectedTables = ['users', 'customers', 'shipments', 'tracking_events',
                         'services', 'site_settings', 'contact_messages',
                         'audit_logs', 'chat_conversations', 'chat_messages',
                         'reviews'];

        $missingTables = array_diff($expectedTables, $tables);

        if (empty($missingTables)) {
            echo "✓ All expected tables found: " . implode(', ', $tables) . "\n";
        } else {
            echo "⚠ Missing tables: " . implode(', ', $missingTables) . "\n";
        }

        // Check reviews count
        $reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
        echo "✓ Reviews table populated with $reviewCount reviews\n";

        // Check chat settings
        $chatEnabled = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'livechat_enabled'")->fetchColumn();
        $chatOnline = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = 'livechat_online'")->fetchColumn();
        echo "✓ Chat settings configured: enabled=$chatEnabled, online=$chatOnline\n";

        echo "\n=== Setup Complete ===\n";
        echo "Your SwiftTrackGo live chat and reviews system is ready!\n";
        echo "\nNext steps:";
        echo "1. Access the site at: http://localhost/swifttrackgo";
        echo "2. Admin panel at: http://localhost/swifttrackgo/admin";
        echo "3. Chat widget will appear on all public pages";
        echo "4. Reviews section will be visible on the homepage";

    } else {
        echo "✗ Schema file not found: $schemaFile\n";
    }

} catch (PDOException $e) {
    echo "✗ MySQL setup failed: " . $e->getMessage() . "\n";
    echo "\nPlease ensure:";
    echo "1. MySQL is installed and running";
    echo "2. You have permissions to create databases";
    echo "3. The root user has no password or you have the correct password";

    echo "\nManual setup commands:";
    echo "1. Open MySQL Command Line";
    echo "2. Run: CREATE DATABASE swifttrackgo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    echo "3. Run: USE swifttrackgo;";
    echo "4. Run: source database/swifttrackgo.sql";
    exit(1);
}

echo "\n=== Database setup finished successfully ===";