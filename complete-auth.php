<?php
session_start();

// Load configuration
try {
    $config = require_once 'config.php';
    require_once 'database.php';
} catch (Exception $e) {
    die('Configuration error: ' . $e->getMessage());
}

// Check if we have an authorization code from Discord
$auth_code = $_GET['code'] ?? null;

if (!$auth_code) {
    header('Location: index.php?error=no_auth_code');
    exit;
}

// Discord OAuth configuration
$client_id = $config['DISCORD_CLIENT_ID'];
$client_secret = $config['DISCORD_CLIENT_SECRET'];
$redirect_uri = $config['REDIRECT_URI'];

// Exchange authorization code for access token
$token_url = 'https://discord.com/api/oauth2/token';
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $auth_code,
    'redirect_uri' => $redirect_uri
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$token_response = curl_exec($ch);
$token_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($token_http_code !== 200) {
    header('Location: index.php?error=token_exchange_failed');
    exit;
}

$token_data = json_decode($token_response, true);

if (!isset($token_data['access_token'])) {
    header('Location: index.php?error=no_access_token');
    exit;
}

// Get user information from Discord
$user_url = 'https://discord.com/api/users/@me';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $user_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token']
]);

$user_response = curl_exec($ch);
$user_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($user_http_code !== 200) {
    header('Location: index.php?error=user_fetch_failed');
    exit;
}

$user_data = json_decode($user_response, true);

// Create avatar URL
$avatar_url = $user_data['avatar'] 
    ? "https://cdn.discordapp.com/avatars/{$user_data['id']}/{$user_data['avatar']}.png"
    : "https://cdn.discordapp.com/embed/avatars/" . (intval($user_data['discriminator']) % 5) . ".png";

// Store user data in session
$_SESSION['discord_user'] = [
    'id' => $user_data['id'],
    'username' => $user_data['username'],
    'discriminator' => $user_data['discriminator'],
    'avatar' => $user_data['avatar'],
    'avatar_url' => $avatar_url,
    'email' => $user_data['email'] ?? null,
    'access_token' => $token_data['access_token']
];

// Create or update user in database
try {
    $db = new Database();
    $db->createOrUpdateUser($user_data);
} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
    // Continue anyway, session is still valid
}

// Auto-join user to Discord server
try {
    $bot_token = $config['BOT_TOKEN'];
    
    if ($bot_token && !empty($bot_token)) {
        // First, resolve the invite to get the guild ID
        $invite_code = '9Yf8aPKCj5'; // From https://discord.gg/9Yf8aPKCj5
        $invite_url = "https://discord.com/api/invites/{$invite_code}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $invite_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bot ' . $bot_token
        ]);
        
        $invite_response = curl_exec($ch);
        $invite_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($invite_http_code === 200) {
            $invite_data = json_decode($invite_response, true);
            $guild_id = $invite_data['guild']['id'];
            
            // Now try to add the user to the server
            $join_url = "https://discord.com/api/guilds/{$guild_id}/members/{$user_data['id']}";
            
            $join_data = [
                'access_token' => $token_data['access_token']
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $join_url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($join_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bot ' . $bot_token,
                'Content-Type: application/json'
            ]);
            
            $join_response = curl_exec($ch);
            $join_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Log the result for debugging
            if ($join_http_code === 201 || $join_http_code === 204) {
                error_log("Successfully added user {$user_data['username']} to Discord server {$guild_id}");
            } else {
                error_log("Failed to add user {$user_data['username']} to Discord server {$guild_id}. HTTP Code: {$join_http_code}, Response: {$join_response}");
            }
        } else {
            error_log("Failed to resolve Discord invite. HTTP Code: {$invite_http_code}, Response: {$invite_response}");
        }
    }
} catch (Exception $e) {
    error_log('Auto-join Discord server error: ' . $e->getMessage());
    // Continue anyway, login is still successful
}

// Redirect to main page
header('Location: index.php?login=success');
exit;
?>
