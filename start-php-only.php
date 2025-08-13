<?php
/**
 * Simple PHP-only startup script
 * Use this if Node.js deployment fails
 */

echo "🚀 Starting Discord Broadcaster Pro (PHP Only Mode)\n";
echo "🌐 Web server starting on port " . ($_ENV['PORT'] ?? '8080') . "\n";

// Check if Discord bot token is configured
$botToken = $_ENV['DISCORD_BOT_TOKEN'] ?? 'your_bot_token_here';
if ($botToken === 'your_bot_token_here') {
    echo "⚠️ Discord Bot disabled (no token configured)\n";
    echo "💡 Add DISCORD_BOT_TOKEN to Zeabur environment variables\n";
    echo "🔧 Use emergency-add-credits.php for manual credit addition\n";
} else {
    echo "⚠️ Discord Bot token found but Node.js not available\n";
    echo "🔧 Use emergency-add-credits.php for manual credit addition\n";
}

echo "✅ PHP web server ready!\n";
echo "🌐 Visit: https://discord-brodcast.zeabur.app\n";
echo "🚨 Emergency tool: https://discord-brodcast.zeabur.app/emergency-add-credits.php\n";

// Keep the script running (Zeabur will handle the actual web server)
while (true) {
    sleep(60);
    echo "💓 Heartbeat: " . date('Y-m-d H:i:s') . "\n";
}
?>
