<?php
session_start();

// Load configuration and database
try {
    $config = require_once 'config.php';
    require_once 'database.php';
    require_once 'admin-helper.php';
} catch (Exception $e) {
    die('Configuration or database error: ' . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['discord_user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['discord_user'];
$isAdmin = isAdmin();

// Check if user is admin
if (!$isAdmin) {
    header('Location: debug-user-id.php');
    exit;
}

$db = new Database();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'verify_bot_in_server':
            $guildId = trim($input['guild_id'] ?? '');
            
            if (empty($guildId)) {
                echo json_encode(['success' => false, 'error' => 'Guild ID is required']);
                break;
            }
            
            if (!preg_match('/^\d{17,19}$/', $guildId)) {
                echo json_encode(['success' => false, 'error' => 'Invalid Guild ID format']);
                break;
            }
            
            try {
                $botToken = $config['BOT_TOKEN'];
                if (!$botToken) {
                    echo json_encode(['success' => false, 'error' => 'Bot token not configured']);
                    break;
                }
                
                // Check if bot is in the server
                $url = "https://discord.com/api/guilds/{$guildId}";
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bot ' . $botToken
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $guildData = json_decode($response, true);
                    echo json_encode([
                        'success' => true,
                        'bot_in_server' => true,
                        'guild_name' => $guildData['name'],
                        'member_count' => $guildData['approximate_member_count'] ?? 'Unknown'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'bot_in_server' => false,
                        'error' => 'Bot is not in this server or server does not exist'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Error verifying bot: ' . $e->getMessage()]);
            }
            break;
            
        case 'add_users_to_server':
            $guildId = trim($input['guild_id'] ?? '');
            $userCount = intval($input['user_count'] ?? 0);
            
            if (empty($guildId)) {
                echo json_encode(['success' => false, 'error' => 'Guild ID is required']);
                break;
            }
            
            if (!preg_match('/^\d{17,19}$/', $guildId)) {
                echo json_encode(['success' => false, 'error' => 'Invalid Guild ID format']);
                break;
            }
            
            if ($userCount < 1 || $userCount > 100) {
                echo json_encode(['success' => false, 'error' => 'User count must be between 1 and 100']);
                break;
            }
            
            try {
                $botToken = $config['BOT_TOKEN'];
                if (!$botToken) {
                    echo json_encode(['success' => false, 'error' => 'Bot token not configured']);
                    break;
                }
                
                // Get users with tokens
                $users = $db->getTokensForServerJoin($userCount);
                
                if (empty($users)) {
                    echo json_encode(['success' => false, 'error' => 'No users with valid tokens found']);
                    break;
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
                echo json_encode(['success' => false, 'error' => 'Error adding users: ' . $e->getMessage()]);
            }
            break;
            
        case 'get_token_stats':
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
                    WHERE updated_at >= datetime('now', '-1 day')
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
                echo json_encode(['success' => false, 'error' => 'Error getting stats: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discord Server Manager - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="logo">
                    <i class="fab fa-discord"></i>
                    <h1>Discord Server Manager</h1>
                </div>
                <div class="user-section">
                    <div class="user-profile">
                        <div class="user-info">
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="User Avatar" class="user-avatar">
                            <div class="user-details">
                                <span class="user-name"><?php echo htmlspecialchars($user['username'] . '#' . $user['discriminator']); ?></span>
                                <div class="connection-status">
                                    <span class="status-indicator online"></span>
                                    <span class="status-text">Admin Access</span>
                                </div>
                            </div>
                        </div>
                        <a href="index.php" class="btn btn-secondary btn-small">
                            <i class="fas fa-home"></i>
                            Home
                        </a>
                        <a href="wallet.php" class="btn btn-info btn-small">
                            <i class="fas fa-wallet"></i>
                            Wallet
                        </a>
                        <a href="logout.php" class="btn btn-secondary btn-small">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Server Manager -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-server"></i> Discord Server Manager</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h4>Administrator Tool</h4>
                            <p>This tool allows you to add users to any Discord server where your bot is present. Use responsibly and ensure you have permission to add users to the target server.</p>
                        </div>
                    </div>
                    
                    <!-- Token Statistics -->
                    <div class="stats-section">
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalUsersCount">Loading...</h3>
                                <p>Users with Valid Tokens</p>
                                <small>Available for server joining</small>
                            </div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="totalRegisteredUsers">Loading...</h3>
                                <p>Total Registered Users</p>
                                <small>All users in database</small>
                            </div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <h3 id="recentUsersCount">Loading...</h3>
                                <p>Recent Active Users</p>
                                <small>Last 24 hours</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Server Management Form -->
                    <div class="server-form">
                        <div class="input-group">
                            <label for="guildId">Discord Server ID</label>
                            <input type="text" id="guildId" name="guildId" 
                                   placeholder="123456789012345678" 
                                   pattern="[0-9]{17,19}" 
                                   required>
                            <small>Enter the Discord server ID where you want to add users</small>
                        </div>
                        
                        <div class="input-group">
                            <label for="userCount">Number of Users to Add</label>
                            <input type="number" id="userCount" name="userCount" 
                                   placeholder="10" 
                                   min="1" 
                                   max="100" 
                                   value="10"
                                   required>
                            <small>Maximum 100 users per operation</small>
                        </div>
                        
                        <div class="form-actions">
                            <button class="btn btn-info" onclick="verifyBotInServer()">
                                <i class="fas fa-search"></i>
                                Verify Bot in Server
                            </button>
                            <button class="btn btn-success" onclick="addUsersToServer()" id="addUsersBtn" disabled>
                                <i class="fas fa-user-plus"></i>
                                Add Users to Server
                            </button>
                        </div>
                    </div>
                    
                    <!-- Server Info -->
                    <div id="serverInfo" class="server-info" style="display: none;">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h4 id="serverName">Server Name</h4>
                                <p>Bot is present in this server. You can proceed with adding users.</p>
                                <small>Member Count: <span id="memberCount">Unknown</span></small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Results -->
                    <div id="resultsSection" class="results-section" style="display: none;">
                        <h3><i class="fas fa-chart-bar"></i> Operation Results</h3>
                        <div id="resultsSummary" class="results-summary"></div>
                        <div id="resultsDetails" class="results-details"></div>
                    </div>
                </div>
            </section>

            <!-- How to Use -->
            <section class="card">
                <div class="card-header">
                    <h2><i class="fas fa-question-circle"></i> How to Use</h2>
                </div>
                <div class="card-body">
                    <div class="steps-guide">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Get Server ID</h4>
                                <p>Right-click on the Discord server name and select "Copy Server ID" (Developer Mode must be enabled)</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Verify Bot Presence</h4>
                                <p>Click "Verify Bot in Server" to ensure your bot is present in the target server</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Set User Count</h4>
                                <p>Choose how many users you want to add (maximum 100 per operation)</p>
                            </div>
                        </div>
                        
                        <div class="step">
                            <div class="step-number">4</div>
                            <div class="step-content">
                                <h4>Add Users</h4>
                                <p>Click "Add Users to Server" to start the process. Results will be displayed below</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Toast Notifications -->
        <div class="toast-container" id="toastContainer"></div>
    </div>

    <script src="wallet.js"></script>
    <script>
        // Load token statistics on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadTokenStats();
        });

        async function loadTokenStats() {
            try {
                const response = await fetch('server-manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'get_token_stats'
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update all statistics
                    document.getElementById('totalUsersCount').textContent = 
                        new Intl.NumberFormat().format(data.total_users_with_tokens);
                    document.getElementById('totalRegisteredUsers').textContent = 
                        new Intl.NumberFormat().format(data.total_registered_users);
                    document.getElementById('recentUsersCount').textContent = 
                        new Intl.NumberFormat().format(data.recent_users_count);
                } else {
                    // Show error for all stats
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
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            button.disabled = true;
            
            try {
                const response = await fetch('server-manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'verify_bot_in_server',
                        guild_id: guildId
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.bot_in_server) {
                    document.getElementById('serverName').textContent = data.guild_name;
                    document.getElementById('memberCount').textContent = data.member_count;
                    document.getElementById('serverInfo').style.display = 'block';
                    document.getElementById('addUsersBtn').disabled = false;
                    showToast('Bot verified in server: ' + data.guild_name, 'success');
                } else {
                    document.getElementById('serverInfo').style.display = 'none';
                    document.getElementById('addUsersBtn').disabled = true;
                    showToast(data.error || 'Bot is not in this server', 'error');
                }
            } catch (error) {
                console.error('Error verifying bot:', error);
                showToast('Network error occurred', 'error');
                document.getElementById('serverInfo').style.display = 'none';
                document.getElementById('addUsersBtn').disabled = true;
            } finally {
                // Restore button
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
            
            // Show loading state
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Users...';
            button.disabled = true;
            
            try {
                const response = await fetch('server-manager.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'add_users_to_server',
                        guild_id: guildId,
                        user_count: userCount
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show results
                    const resultsSection = document.getElementById('resultsSection');
                    const resultsSummary = document.getElementById('resultsSummary');
                    const resultsDetails = document.getElementById('resultsDetails');
                    
                    resultsSummary.innerHTML = `
                        <div class="summary-stats">
                            <div class="stat success">
                                <i class="fas fa-check-circle"></i>
                                <span>${data.success_count} Successful</span>
                            </div>
                            <div class="stat error">
                                <i class="fas fa-times-circle"></i>
                                <span>${data.fail_count} Failed</span>
                            </div>
                            <div class="stat info">
                                <i class="fas fa-users"></i>
                                <span>${data.total_attempted} Total Attempted</span>
                            </div>
                        </div>
                    `;
                    
                    let detailsHtml = '<div class="results-list">';
                    data.results.forEach(result => {
                        const statusClass = result.status === 'success' ? 'success' : 'error';
                        const icon = result.status === 'success' ? 'fa-check' : 'fa-times';
                        detailsHtml += `
                            <div class="result-item ${statusClass}">
                                <i class="fas ${icon}"></i>
                                <span>${result.user}</span>
                                ${result.error ? `<small>${result.error}</small>` : ''}
                            </div>
                        `;
                    });
                    detailsHtml += '</div>';
                    
                    resultsDetails.innerHTML = detailsHtml;
                    resultsSection.style.display = 'block';
                    
                    showToast(`Operation completed: ${data.success_count} users added successfully`, 'success');
                } else {
                    showToast('Error: ' + (data.error || 'Failed to add users'), 'error');
                }
            } catch (error) {
                console.error('Error adding users:', error);
                showToast('Network error occurred', 'error');
            } finally {
                // Restore button
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
    </script>
</body>
</html>
