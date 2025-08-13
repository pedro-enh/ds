<?php
// Discord Broadcaster Pro Configuration
// This file loads configuration from environment variables (.env file)

// Load environment variables
require_once 'env-loader.php';

return [
    // Discord OAuth Configuration
    // These values are loaded from .env file or Zeabur environment variables
    'DISCORD_CLIENT_ID' => env('DISCORD_CLIENT_ID', ''),
    'DISCORD_CLIENT_SECRET' => env('DISCORD_CLIENT_SECRET', ''),
    
    // Your website URL
    // This value is loaded from .env file or Zeabur environment variables
    'REDIRECT_URI' => env('DISCORD_REDIRECT_URI', 'https://discord-brodcast.zeabur.app/complete-auth.php'),
    
    // Bot Token (required for broadcasting functionality)
    // This value is loaded from .env file or Zeabur environment variables
    'BOT_TOKEN' => env('DISCORD_BOT_TOKEN', ''),
    
    // Admin Configuration
    // Add Discord IDs of users who can access admin features
    'ADMIN_DISCORD_IDS' => [
        '675332512414695441', 
'767757877850800149',
'870727219436208211', // pedr_o.1#0 - Main admin ID
        // Add more admin IDs if needed
    ],
    
    // Optional configuration
    'APP_ENV' => env('APP_ENV', 'production'),
    'DEBUG' => env('DEBUG', false)
];
?>

