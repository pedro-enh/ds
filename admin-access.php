<?php
require_once 'admin-helper.php';
require_once 'database.php';

session_start();

// Check if user is admin
if (!isAdmin()) {
    if (!isset($_SESSION['discord_user'])) {
        header('Location: index.php?error=login_required');
    } else {
        header('Location: index.php?error=access_denied');
    }
    exit;
}

$user = $_SESSION['discord_user'];

// Initialize database
$db = new Database();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_credits') {
        $targetUserId = trim($_POST['user_id'] ?? '');
        $creditsAmount = intval($_POST['credits'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Admin credit addition');
        
        // Validation
        if (empty($targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Discord ID is required']);
            exit;
        }
        
        if (!preg_match('/^\d{17,19}$/', $targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Discord ID format']);
            exit;
        }
        
        if ($creditsAmount < 1 || $creditsAmount > 10000) {
            echo json_encode(['success' => false, 'message' => 'Credits must be between 1 and 10,000']);
            exit;
        }
        
        try {
            $pdo = $db->getPdo();
            
            // Check if user exists
            $targetUser = $db->getUserByDiscordId($targetUserId);
            
            if ($targetUser) {
                // Update existing user
                $newBalance = $targetUser['credits'] + $creditsAmount;
                $stmt = $pdo->prepare("UPDATE users SET credits = ?, updated_at = NOW() WHERE discord_id = ?");
                $stmt->execute([$newBalance, $targetUserId]);
            } else {
                // Create new user with minimal data
                $stmt = $pdo->prepare("INSERT INTO users (discord_id, username, discriminator, credits, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$targetUserId, 'Unknown', '0000', $creditsAmount]);
                $newBalance = $creditsAmount;
                $targetUser = ['id' => $pdo->lastInsertId()];
            }
            
            // Record transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, discord_id, type, amount, description, status, created_at) VALUES (?, ?, 'purchase', ?, ?, 'completed', NOW())");
            $stmt->execute([$targetUser['id'], $targetUserId, $creditsAmount, "Admin: " . $reason]);
            
            echo json_encode([
                'success' => true, 
                'message' => "Successfully added {$creditsAmount} credits to user {$targetUserId}",
                'new_balance' => $newBalance
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_user_info') {
        $targetUserId = trim($_POST['user_id'] ?? '');
        
        if (empty($targetUserId) || !preg_match('/^\d{17,19}$/', $targetUserId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Discord ID']);
            exit;
        }
        
        try {
            $targetUser = $db->getUserByDiscordId($targetUserId);
            
            if ($targetUser) {
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'discord_id' => $targetUser['discord_id'],
                        'credits' => $targetUser['credits'],
                        'created_at' => $targetUser['created_at'],
                        'updated_at' => $targetUser['updated_at']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'user' => null,
                    'message' => 'User not found in database'
                ]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Server Management Actions
    if ($_POST['action'] === 'verify_bot_in_server') {
        $guildId = trim($_POST['guild_id'] ?? '');
        
        if (empty($guildId)) {
            echo json_encode(['success' => false, 'message' => 'Guild ID is required']);
            exit;
        }
        
        if (!preg_match('/^\d{17,19}$/', $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Guild ID format']);
            exit;
        }
        
        try {
            $config = require_once 'config.php';
            $botToken = $config['BOT_TOKEN'];
            
            if (!$botToken || empty(trim($botToken))) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Bot token not configured. Please set DISCORD_BOT_TOKEN environment variable.',
                    'debug' => [
                        'bot_token_set' => !empty($botToken),
                        'config_loaded' => true
                    ]
                ]);
                exit;
            }
            
            // Check if bot is in the server
            $url = "https://discord.com/api/guilds/{$guildId}";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Discord Broadcaster Pro/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $botToken,
                'Content-Type: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Network error: ' . $curlError,
                    'debug' => [
                        'curl_error' => $curlError,
                        'http_code' => $httpCode
                    ]
                ]);
                exit;
            }
            
            if ($httpCode === 200) {
                $guildData = json_decode($response, true);
                if ($guildData) {
                    echo json_encode([
                        'success' => true,
                        'bot_in_server' => true,
                        'guild_name' => $guildData['name'] ?? 'Unknown',
                        'member_count' => $guildData['approximate_member_count'] ?? 'Unknown'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid response from Discord API',
                        'debug' => [
                            'http_code' => $httpCode,
                            'response' => $response
                        ]
                    ]);
                }
            } elseif ($httpCode === 401) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid bot token. Please check DISCORD_BOT_TOKEN environment variable.',
                    'debug' => [
                        'http_code' => $httpCode,
                        'token_length' => strlen($botToken)
                    ]
                ]);
            } elseif ($httpCode === 403) {
                echo json_encode([
                    'success' => true,
                    'bot_in_server' => false,
                    'message' => 'Bot does not have access to this server'
                ]);
            } elseif ($httpCode === 404) {
                echo json_encode([
                    'success' => true,
                    'bot_in_server' => false,
                    'message' => 'Server not found or bot is not in this server'
                ]);
            } else {
                $errorData = json_decode($response, true);
                echo json_encode([
                    'success' => false,
                    'message' => 'Discord API error: ' . ($errorData['message'] ?? 'Unknown error'),
                    'debug' => [
                        'http_code' => $httpCode,
                        'response' => $response
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Error verifying bot: ' . $e->getMessage(),
                'debug' => [
                    'exception' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'add_users_to_server') {
        $guildId = trim($_POST['guild_id'] ?? '');
        $userCount = intval($_POST['user_count'] ?? 0);
        
        if (empty($guildId)) {
            echo json_encode(['success' => false, 'message' => 'Guild ID is required']);
            exit;
        }
        
        if (!preg_match('/^\d{17,19}$/', $guildId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Guild ID format']);
            exit;
        }
        
        if ($userCount < 1 || $userCount > 100) {
            echo json_encode(['success' => false, 'message' => 'User count must be between 1 and 100']);
            exit;
        }
        
        try {
            $config = require_once 'config.php';
            $botToken = $config['BOT_TOKEN'];
            if (!$botToken) {
                echo json_encode(['success' => false, 'message' => 'Bot token not configured']);
                exit;
            }
            
            // Get users with tokens
            $users = $db->getTokensForServerJoin($userCount);
            
            if (empty($users)) {
                echo json_encode(['success' => false, 'message' => 'No users with valid tokens found']);
                exit;
            }
            
            $successCount = 0;
            $failCount = 0;
            $results = [];
            
            foreach ($users as $userData) {
                $joinUrl = "https://discord.com/api/guilds/{$guildId}/members/{$userData['discord_id']}";
                
                $joinData = [
                    'access_token' => $userData['access_token']
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $joinUrl);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($joinData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bot ' . $botToken,
                    'Content-Type: application/json'
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 201 || $httpCode === 204) {
                    $successCount++;
                    $results[] = [
                        'user' => $userData['username'],
                        'status' => 'success'
                    ];
                } else {
                    $failCount++;
                    $results[] = [
                        'user' => $userData['username'],
                        'status' => 'failed',
                        'error' => "HTTP {$httpCode}"
                    ];
                }
                
                // Add delay to prevent rate limiting
                usleep(500000); // 0.5 second delay
            }
            
            echo json_encode([
                'success' => true,
                'total_attempted' => count($users),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results
            ]);
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error adding users: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'get_token_stats') {
        try {
            $tokens = $db->getAllDiscordTokens();
            
            // Get total registered users
            $stmt = $db->getPdo()->prepare("SELECT COUNT(*) as total FROM users");
            $stmt->execute();
            $totalUsers = $stmt->fetch()['total'];
            
            // Get recent users (last 24 hours)
            $stmt = $db->getPdo()->prepare("
                SELECT COUNT(*) as recent 
                FROM users 
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute();
            $recentUsers = $stmt->fetch()['recent'];
            
            echo json_encode([
                'success' => true,
                'total_users_with_tokens' => count($tokens),
                'total_registered_users' => $totalUsers,
                'recent_users_count' => $recentUsers
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error getting stats: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Get recent transactions for admin view
try {
    $pdo = $db->getPdo();
    $stmt = $pdo->prepare("SELECT t.*, u.discord_id as user_discord_id FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE t.type = 'purchase' AND t.description LIKE 'Admin:%' ORDER BY t.created_at DESC LIMIT 20");
    $stmt->execute();
    $recentTransactions = $stmt->fetchAll();
} catch (Exception $e) {
    $recentTransactions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access - Discord Broadcaster Pro</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 2.5em;
        }
        
        .admin-header .admin-badge {
            background: #333;
            color: #ffd700;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 10px;
            display: inline-block;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .admin-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 2px solid #ffd700;
        }
        
        .admin-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #ffd700;
        }
        
        .btn-admin {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }
        
        .btn-admin:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 10px;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
        }
        
        .user-info.show {
            display: block;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .transactions-table th,
        .transactions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .transactions-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: #28a745;
        }
        
        .toast.error {
            background: #dc3545;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #333;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1><i class="fas fa-crown"></i> Admin Access Panel</h1>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i> 
                <?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?>
            </div>
        </div>
        
        <div class="admin-grid">
            <!-- Add Credits Section -->
            <div class="admin-card">
                <h3><i class="fas fa-coins"></i> Add Credits to User</h3>
                
                <form id="addCreditsForm">
                    <div class="form-group">
                        <label for="targetUserId">Discord User ID:</label>
                        <input type="text" id="targetUserId" name="user_id" placeholder="Enter Discord ID (17-19 digits)" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="creditsAmount">Credits Amount:</label>
                        <input type="number" id="creditsAmount" name="credits" min="1" max="10000" placeholder="Enter credits amount" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Reason (Optional):</label>
                        <textarea id="reason" name="reason" rows="3" placeholder="Enter reason for adding credits"></textarea>
                    </div>
                    
                    <button type="button" class="btn-admin btn-secondary" onclick="getUserInfo()">
                        <i class="fas fa-search"></i> Check User Info
                    </button>
                    
                    <button type="submit" class="btn-admin">
                        <i class="fas fa-plus"></i> Add Credits
                    </button>
                </form>
                
                <div id="userInfo" class="user-info">
                    <!-- User info will be displayed here -->
                </div>
            </div>
            
            <!-- Quick Actions Section -->
            <div class="admin-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                
                <button class="btn-admin" onclick="addQuickCredits(100)">
                    <i class="fas fa-plus"></i> Add 100 Credits
                </button>
                
                <button class="btn-admin" onclick="addQuickCredits(500)" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Add 500 Credits
                </button>
                
                <button class="btn-admin" onclick="addQuickCredits(1000)" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Add 1000 Credits
                </button>
                
                <button class="btn-admin btn-secondary" onclick="window.location.href='wallet.php'" style="margin-top: 20px;">
                    <i class="fas fa-wallet"></i> Go to Wallet
                </button>
                
                <button class="btn-admin btn-secondary" onclick="window.location.href='payment-checker.php'" style="margin-top: 10px;">
                    <i class="fas fa-chart-line"></i> Payment Checker
                </button>
            </div>
        </div>
        
        <!-- Server Manager Section -->
        <div class="admin-grid">
            <div class="admin-card">
                <h3><i class="fas fa-server"></i> Discord Server Manager</h3>
                
                <!-- Token Statistics -->
                <div style="display: flex; gap: 15px; margin-bottom: 20px; font-size: 14px;">
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #007bff;" id="totalUsersCount">Loading...</div>
                        <div>Users with Tokens</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #28a745;" id="totalRegisteredUsers">Loading...</div>
                        <div>Total Users</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-weight: bold; color: #ffc107;" id="recentUsersCount">Loading...</div>
                        <div>Recent Active</div>
                    </div>
                </div>
                
                <form id="serverManagerForm">
                    <div class="form-group">
                        <label for="guildId">Discord Server ID:</label>
                        <input type="text" id="guildId" name="guild_id" placeholder="Enter Discord Server ID (17-19 digits)" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userCount">Number of Users to Add:</label>
                        <input type="number" id="userCount" name="user_count" min="1" max="100" value="10" placeholder="Enter number of users" required>
                    </div>
                    
                    <button type="button" class="btn-admin btn-secondary" onclick="verifyBotInServer()">
                        <i class="fas fa-search"></i> Verify Bot in Server
                    </button>
                    
                    <button type="button" class="btn-admin" onclick="addUsersToServer()" id="addUsersBtn" disabled>
                        <i class="fas fa-user-plus"></i> Add Users to Server
                    </button>
                </form>
                
                <div id="serverInfo" class="user-info">
                    <!-- Server info will be displayed here -->
                </div>
            </div>
            
            <!-- Server Results Section -->
            <div class="admin-card">
                <h3><i class="fas fa-chart-bar"></i> Server Operation Results</h3>
                
                <div id="resultsSection" style="display: none;">
                    <div id="resultsSummary" style="margin-bottom: 15px;"></div>
                    <div id="resultsDetails" style="max-height: 300px; overflow-y: auto;"></div>
                </div>
                
                <div id="noResults" style="text-align: center; color: #666; padding: 40px;">
                    <i class="fas fa-server" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                    <p>No server operations performed yet.</p>
                    <p>Use the server manager to add users to Discord servers.</p>
                </div>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="admin-card">
            <h3><i class="fas fa-history"></i> Recent Admin Transactions</h3>
            
            <?php if (empty($recentTransactions)): ?>
                <p>No admin transactions found.</p>
            <?php else: ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User ID</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($transaction['discord_id']); ?></td>
                                <td>+<?php echo number_format($transaction['amount']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description'] ?? 'Admin credit addition'); ?></td>
                                <td>Admin</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add Credits Form Handler
        document.getElementById('addCreditsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const userId = document.getElementById('targetUserId').value.trim();
            const credits = parseInt(document.getElementById('creditsAmount').value);
            const reason = document.getElementById('reason').value.trim();
            
            if (!userId || !credits) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            if (!/^\d{17,19}$/.test(userId)) {
                showToast('Invalid Discord ID format', 'error');
                return;
            }
            
            if (credits < 1 || credits > 10000) {
                showToast('Credits must be between 1 and 10,000', 'error');
                return;
            }
            
            addCredits(userId, credits, reason);
        });
        
        // Add Credits Function
        function addCredits(userId, credits, reason) {
            const submitBtn = document.querySelector('#addCreditsForm button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loading"></span> Adding Credits...';
            
            fetch('admin-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_credits&user_id=${encodeURIComponent(userId)}&credits=${credits}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    document.getElementById('addCreditsForm').reset();
                    document.getElementById('userInfo').classList.remove('show');
                    
                    // Refresh page after 2 seconds to show updated transactions
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }
        
        // Quick Add Credits
        function addQuickCredits(amount) {
            const userId = document.getElementById('targetUserId').value.trim();
            
            if (!userId) {
                showToast('Please enter a Discord ID first', 'error');
                document.getElementById('targetUserId').focus();
                return;
            }
            
            if (!/^\d{17,19}$/.test(userId)) {
                showToast('Invalid Discord ID format', 'error');
                return;
            }
            
            addCredits(userId, amount, `Quick add ${amount} credits`);
        }
        
        // Get User Info
        function getUserInfo() {
            const userId = document.getElementById('targetUserId').value.trim();
            
            if (!userId) {
                showToast('Please enter a Discord ID', 'error');
                return;
            }
            
            if (!/^\d{17,19}$/.test(userId)) {
                showToast('Invalid Discord ID format', 'error');
                return;
            }
            
            fetch('admin-access.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_user_info&user_id=${encodeURIComponent(userId)}`
            })
            .then(response => response.json())
            .then(data => {
                const userInfoDiv = document.getElementById('userInfo');
                
                if (data.success) {
                    if (data.user) {
                        userInfoDiv.innerHTML = `
                            <h4><i class="fas fa-user"></i> User Information</h4>
                            <p><strong>Discord ID:</strong> ${data.user.discord_id}</p>
                            <p><strong>Current Credits:</strong> ${data.user.credits}</p>
                            <p><strong>Account Created:</strong> ${new Date(data.user.created_at).toLocaleString()}</p>
                            <p><strong>Last Updated:</strong> ${new Date(data.user.updated_at).toLocaleString()}</p>
                        `;
                    } else {
                        userInfoDiv.innerHTML = `
                            <h4><i class="fas fa-user-plus"></i> New User</h4>
                            <p>This Discord ID is not in the database yet. Adding credits will create a new account.</p>
                        `;
                    }
                    userInfoDiv.classList.add('show');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                showToast('Network error occurred', 'error');
                console.error('Error:', error);
            });
        }
        
        // Toast Notification
        function showToast(message, type) {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Hide toast after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 4000);
        }
        
        // Load token statistics on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTokenStats();
        });

        async function loadTokenStats() {
            try {
                const response = await fetch('admin-access.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_token_stats'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('totalUsersCount').textContent = 
                        new Intl.NumberFormat().format(data.total_users_with_tokens);
                    document.getElementById('totalRegisteredUsers').textContent = 
                        new Intl.NumberFormat().format(data.total_registered_users);
                    document.getElementById('recentUsersCount').textContent = 
                        new Intl.NumberFormat().format(data.recent_users_count);
                } else {
                    document.getElementById('totalUsersCount').textContent = 'Error';
                    document.getElementById('totalRegisteredUsers').textContent = 'Error';
                    document.getElementById('recentUsersCount').textContent = 'Error';
                }
            } catch (error) {
                console.error('Error loading token stats:', error);
                document.getElementById('totalUsersCount').textContent = 'Error';
                document.getElementById('totalRegisteredUsers').textContent = 'Error';
                document.getElementById('recentUsersCount').textContent = 'Error';
            }
        }

        async function verifyBotInServer() {
            const guildId = document.getElementById('guildId').value.trim();
            const button = event.target;
            const originalText = button.innerHTML;
            
            if (!guildId) {
                showToast('Please enter a Discord Server ID', 'error');
                return;
            }
            
            if (!/^\d{17,19}$/.test(guildId)) {
                showToast('Invalid Discord Server ID format', 'error');
                return;
            }
            
            button.innerHTML = '<span class="loading"></span> Verifying...';
            button.disabled = true;
            
            try {
                const response = await fetch('admin-access.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_bot_in_server&guild_id=${encodeURIComponent(guildId)}`
                });
                
                const data = await response.json();
                
                if (data.success && data.bot_in_server) {
                    document.getElementById('serverInfo').innerHTML = `
                        <h4><i class="fas fa-check-circle" style="color: green;"></i> Bot Verified in Server</h4>
                        <p><strong>Server Name:</strong> ${data.guild_name}</p>
                        <p><strong>Member Count:</strong> ${data.member_count}</p>
                        <p style="color: green;">Bot is present in this server. You can proceed with adding users.</p>
                    `;
                    document.getElementById('serverInfo').classList.add('show');
                    document.getElementById('addUsersBtn').disabled = false;
                    showToast('Bot verified in server: ' + data.guild_name, 'success');
                } else {
                    document.getElementById('serverInfo').innerHTML = `
                        <h4><i class="fas fa-times-circle" style="color: red;"></i> Bot Not Found</h4>
                        <p style="color: red;">${data.message || 'Bot is not in this server or server does not exist'}</p>
                    `;
                    document.getElementById('serverInfo').classList.add('show');
                    document.getElementById('addUsersBtn').disabled = true;
                    showToast(data.message || 'Bot is not in this server', 'error');
                }
            } catch (error) {
                console.error('Error verifying bot:', error);
                showToast('Network error occurred', 'error');
                document.getElementById('serverInfo').classList.remove('show');
                document.getElementById('addUsersBtn').disabled = true;
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        async function addUsersToServer() {
            const guildId = document.getElementById('guildId').value.trim();
            const userCount = parseInt(document.getElementById('userCount').value);
            const button = event.target;
            const originalText = button.innerHTML;
            
            if (!guildId || !/^\d{17,19}$/.test(guildId)) {
                showToast('Please verify the server first', 'error');
                return;
            }
            
            if (!userCount || userCount < 1 || userCount > 100) {
                showToast('User count must be between 1 and 100', 'error');
                return;
            }
            
            button.innerHTML = '<span class="loading"></span> Adding Users...';
            button.disabled = true;
            
            try {
                const response = await fetch('admin-access.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=add_users_to_server&guild_id=${encodeURIComponent(guildId)}&user_count=${userCount}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show results
                    const resultsSection = document.getElementById('resultsSection');
                    const resultsSummary = document.getElementById('resultsSummary');
                    const resultsDetails = document.getElementById('resultsDetails');
                    const noResults = document.getElementById('noResults');
                    
                    resultsSummary.innerHTML = `
                        <div style="display: flex; gap: 15px; justify-content: center; margin-bottom: 15px;">
                            <div style="text-align: center; padding: 10px; background: #d4edda; border-radius: 8px; color: #155724;">
                                <div style="font-weight: bold; font-size: 18px;">${data.success_count}</div>
                                <div>Successful</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f8d7da; border-radius: 8px; color: #721c24;">
                                <div style="font-weight: bold; font-size: 18px;">${data.fail_count}</div>
                                <div>Failed</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #d1ecf1; border-radius: 8px; color: #0c5460;">
                                <div style="font-weight: bold; font-size: 18px;">${data.total_attempted}</div>
                                <div>Total</div>
                            </div>
                        </div>
                    `;
                    
                    let detailsHtml = '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">';
                    data.results.forEach(result => {
                        const statusColor = result.status === 'success' ? '#28a745' : '#dc3545';
                        const icon = result.status === 'success' ? 'fa-check' : 'fa-times';
                        detailsHtml += `
                            <div style="display: flex; align-items: center; gap: 10px; padding: 5px 0; border-bottom: 1px solid #dee2e6;">
                                <i class="fas ${icon}" style="color: ${statusColor};"></i>
                                <span style="flex: 1;">${result.user}</span>
                                ${result.error ? `<small style="color: #6c757d;">${result.error}</small>` : ''}
                            </div>
                        `;
                    });
                    detailsHtml += '</div>';
                    
                    resultsDetails.innerHTML = detailsHtml;
                    resultsSection.style.display = 'block';
                    noResults.style.display = 'none';
                    
                    showToast(`Operation completed: ${data.success_count} users added successfully`, 'success');
                } else {
                    showToast('Error: ' + (data.message || 'Failed to add users'), 'error');
                }
            } catch (error) {
                console.error('Error adding users:', error);
                showToast('Network error occurred', 'error');
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
    </script>
</body>
</html>
