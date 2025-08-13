<?php
session_start();

// Load configuration and admin helper
try {
    $config = require_once 'config.php';
    require_once 'admin-helper.php';
} catch (Exception $e) {
    die('Configuration error: ' . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    echo '<h1>Debug: User ID Checker</h1>';
    echo '<p>You need to login first to see your Discord ID.</p>';
    echo '<a href="login.php">Login with Discord</a>';
    exit;
}

$user = $_SESSION['discord_user'];
$isAdmin = isAdmin();
$adminIds = $config['ADMIN_DISCORD_IDS'] ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug: User ID Checker</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .info-box { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .admin-box { background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error-box { background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { display: inline-block; padding: 10px 20px; background: #5865f2; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug: User ID Checker</h1>
        
        <div class="info-box">
            <h3>Your Discord Information:</h3>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?></p>
            <p><strong>Discord ID:</strong> <code><?php echo htmlspecialchars($user['id']); ?></code></p>
            <p><strong>Discord ID Type:</strong> <code><?php echo gettype($user['id']); ?></code></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></p>
        </div>
        
        <div class="info-box">
            <h3>Debug Information:</h3>
            <p><strong>Session Data:</strong></p>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px;">
<?php echo htmlspecialchars(json_encode($_SESSION['discord_user'], JSON_PRETTY_PRINT)); ?>
            </pre>
        </div>
        
        <?php if ($isAdmin): ?>
        <div class="admin-box">
            <h3>✅ Admin Status: ACTIVE</h3>
            <p>You have admin privileges and can access the server manager.</p>
            <a href="server-manager.php" class="btn">Go to Server Manager</a>
        </div>
        <?php else: ?>
        <div class="error-box">
            <h3>❌ Admin Status: NOT ADMIN</h3>
            <p>Your Discord ID is not in the admin list.</p>
            <p>To become an admin, your Discord ID <code><?php echo htmlspecialchars($user['id']); ?></code> needs to be added to the config.php file.</p>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>Current Admin IDs in Config:</h3>
            <?php if (empty($adminIds)): ?>
                <p>No admin IDs configured.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($adminIds as $adminId): ?>
                        <li>
                            <code><?php echo htmlspecialchars($adminId); ?></code>
                            <?php if ($adminId === $user['id']): ?>
                                <strong style="color: green;">(This is you!)</strong>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <div class="info-box">
            <h3>How to Add Admin Access:</h3>
            <p>If you need admin access, add your Discord ID to the config.php file:</p>
            <pre style="background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;">
'ADMIN_DISCORD_IDS' => [
    '675332512414695441', 
    '767757877850800149',
    '870727219436208211',
    '<?php echo htmlspecialchars($user['id']); ?>', // Add this line
],</pre>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="index.php" class="btn">← Back to Home</a>
            <a href="wallet.php" class="btn">Go to Wallet</a>
            <?php if ($isAdmin): ?>
            <a href="server-manager.php" class="btn">Server Manager</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
