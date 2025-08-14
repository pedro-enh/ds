<?php
/**
 * Database Setup Script
 * Run this script to set up the database for Discord Broadcaster Pro
 */

require_once 'database.php';

echo "Discord Broadcaster Pro - Database Setup\n";
echo "========================================\n\n";

try {
    // Initialize database
    echo "Initializing database connection...\n";
    $db = new Database();
    
    echo "✅ Database connected successfully!\n";
    
    // Check database type
    $pdo = $db->getPdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "📊 Database type: " . strtoupper($driver) . "\n";
    
    // Test database operations
    echo "\nTesting database operations...\n";
    
    // Test user creation
    $testUser = [
        'id' => 'test123456789',
        'username' => 'TestUser',
        'discriminator' => '0001',
        'avatar' => null,
        'email' => null
    ];
    
    $user = $db->createOrUpdateUser($testUser);
    echo "✅ User creation/update test passed\n";
    
    // Test credit operations
    $db->addCredits('test123456789', 100, 'Test credit addition');
    echo "✅ Credit addition test passed\n";
    
    $credits = $db->getUserCredits('test123456789');
    echo "✅ Credit retrieval test passed (Credits: {$credits})\n";
    
    // Clean up test data
    $stmt = $pdo->prepare("DELETE FROM users WHERE discord_id = ?");
    $stmt->execute(['test123456789']);
    
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE discord_id = ?");
    $stmt->execute(['test123456789']);
    
    echo "✅ Test cleanup completed\n";
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "Your Discord Broadcaster Pro database is ready to use.\n\n";
    
    // Show environment info
    echo "Environment Information:\n";
    echo "- DATABASE_URL: " . (getenv('DATABASE_URL') ? 'Set (using cloud database)' : 'Not set (using SQLite)') . "\n";
    echo "- PHP Version: " . PHP_VERSION . "\n";
    echo "- PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n";
    
} catch (Exception $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure your DATABASE_URL environment variable is set correctly\n";
    echo "2. Verify your database credentials and connection\n";
    echo "3. Check that the required PHP extensions are installed\n";
    exit(1);
}
?>
