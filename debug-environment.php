<?php
/**
 * Environment Debug Script
 * Use this to check your environment variables and configuration
 */

require_once 'env-loader.php';

echo "Discord Broadcaster Pro - Environment Debug\n";
echo "==========================================\n\n";

// Check environment variables
echo "Environment Variables:\n";
echo "----------------------\n";

$envVars = [
    'DISCORD_CLIENT_ID',
    'DISCORD_CLIENT_SECRET', 
    'DISCORD_BOT_TOKEN',
    'DISCORD_REDIRECT_URI',
    'DATABASE_URL',
    'DB_PATH'
];

foreach ($envVars as $var) {
    $value = env($var);
    if ($value) {
        if (in_array($var, ['DISCORD_CLIENT_SECRET', 'DISCORD_BOT_TOKEN', 'DATABASE_URL'])) {
            // Hide sensitive values but show they exist
            echo "✅ {$var}: [SET - " . strlen($value) . " characters]\n";
        } else {
            echo "✅ {$var}: {$value}\n";
        }
    } else {
        echo "❌ {$var}: [NOT SET]\n";
    }
}

echo "\nConfiguration Test:\n";
echo "-------------------\n";

try {
    $config = require_once 'config.php';
    
    echo "✅ Config file loaded successfully\n";
    
    // Test bot token
    $botToken = $config['BOT_TOKEN'];
    if ($botToken && !empty(trim($botToken))) {
        echo "✅ Bot token configured (" . strlen($botToken) . " characters)\n";
        
        // Test if bot token format looks correct
        if (strlen($botToken) >= 50 && strlen($botToken) <= 100) {
            echo "✅ Bot token length looks correct\n";
        } else {
            echo "⚠️  Bot token length seems unusual (expected 50-100 chars)\n";
        }
    } else {
        echo "❌ Bot token not configured\n";
    }
    
    // Test Discord API connection
    echo "\nTesting Discord API Connection:\n";
    echo "-------------------------------\n";
    
    if ($botToken) {
        $testUrl = "https://discord.com/api/users/@me";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bot ' . $botToken,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            echo "❌ CURL Error: {$curlError}\n";
        } elseif ($httpCode === 200) {
            $botData = json_decode($response, true);
            echo "✅ Bot API connection successful\n";
            echo "✅ Bot Username: " . ($botData['username'] ?? 'Unknown') . "\n";
            echo "✅ Bot ID: " . ($botData['id'] ?? 'Unknown') . "\n";
        } elseif ($httpCode === 401) {
            echo "❌ Bot token is invalid (HTTP 401)\n";
        } else {
            echo "❌ API Error: HTTP {$httpCode}\n";
            echo "Response: {$response}\n";
        }
    } else {
        echo "❌ Cannot test API - bot token not configured\n";
    }
    
} catch (Exception $e) {
    echo "❌ Config error: " . $e->getMessage() . "\n";
}

echo "\nDatabase Test:\n";
echo "-------------\n";

try {
    require_once 'database.php';
    $db = new Database();
    echo "✅ Database connection successful\n";
    
    $pdo = $db->getPdo();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    echo "✅ Database type: " . strtoupper($driver) . "\n";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\nPHP Information:\n";
echo "---------------\n";
echo "✅ PHP Version: " . PHP_VERSION . "\n";
echo "✅ CURL Extension: " . (extension_loaded('curl') ? 'Enabled' : 'Disabled') . "\n";
echo "✅ PDO Extension: " . (extension_loaded('pdo') ? 'Enabled' : 'Disabled') . "\n";
echo "✅ PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled') . "\n";
echo "✅ PDO SQLite: " . (extension_loaded('pdo_sqlite') ? 'Enabled' : 'Disabled') . "\n";

echo "\nRecommendations:\n";
echo "---------------\n";

if (!env('DISCORD_BOT_TOKEN')) {
    echo "🔧 Set DISCORD_BOT_TOKEN environment variable in your Zeabur deployment\n";
}

if (!env('DATABASE_URL')) {
    echo "🔧 Set DATABASE_URL environment variable for persistent database\n";
    echo "   Format: mysql://root:eDwIb7210q6n8xC9o34RghJrlyzKFj5L@sjc1.clusters.zeabur.com:31931/zeabur\n";
}

echo "\n✅ Debug completed!\n";
?>
