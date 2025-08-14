<?php
/**
 * Simple SQLite Database Handler for Discord Broadcaster Pro
 * Handles wallet system and credit transactions
 */

class Database {
    private $pdo;
    private $dbPath;
    private $dbType;
    
    public function __construct($dbPath = null) {
        $this->dbPath = $dbPath;
        $this->connect();
        $this->createTables();
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    private function connect() {
        try {
            // Check for DATABASE_URL environment variable (common in cloud deployments)
            $databaseUrl = getenv('DATABASE_URL');
            
            if ($databaseUrl) {
                // Parse DATABASE_URL for cloud deployment (PostgreSQL/MySQL)
                $this->connectFromUrl($databaseUrl);
            } else {
                // Fallback to SQLite for local development
                $dbPath = $this->dbPath ?: ($_ENV['DB_PATH'] ?? 'broadcaster.db');
                $this->pdo = new PDO("sqlite:" . $dbPath);
                $this->dbType = 'sqlite';
            }
            
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function connectFromUrl($databaseUrl) {
        $parsed = parse_url($databaseUrl);
        
        $host = $parsed['host'];
        $port = $parsed['port'] ?? 5432;
        $database = ltrim($parsed['path'], '/');
        $username = $parsed['user'];
        $password = $parsed['pass'];
        $scheme = $parsed['scheme'];
        
        if ($scheme === 'postgres' || $scheme === 'postgresql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode=require";
            $this->dbType = 'postgresql';
        } elseif ($scheme === 'mysql') {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $this->dbType = 'mysql';
        } else {
            throw new Exception("Unsupported database scheme: {$scheme}");
        }
        
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    
    private function createTables() {
        if ($this->dbType === 'postgresql') {
            $this->createPostgreSQLTables();
        } elseif ($this->dbType === 'mysql') {
            $this->createMySQLTables();
        } else {
            $this->createSQLiteTables();
        }
    }
    
    private function createSQLiteTables() {
        // Users table for wallet system
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                discord_id TEXT UNIQUE NOT NULL,
                username TEXT NOT NULL,
                discriminator TEXT NOT NULL,
                avatar TEXT,
                email TEXT,
                credits INTEGER DEFAULT 0,
                total_spent INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Transactions table for credit history
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discord_id TEXT NOT NULL,
                type TEXT NOT NULL,
                amount INTEGER NOT NULL,
                description TEXT,
                probot_transaction_id TEXT,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ");
        
        // Broadcast history
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS broadcasts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                discord_id TEXT NOT NULL,
                guild_id TEXT NOT NULL,
                guild_name TEXT,
                message TEXT NOT NULL,
                target_type TEXT NOT NULL,
                messages_sent INTEGER DEFAULT 0,
                messages_failed INTEGER DEFAULT 0,
                credits_used INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ");
        
        // ProBot payment monitoring
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_monitoring (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                discord_id TEXT NOT NULL,
                expected_amount INTEGER NOT NULL,
                status TEXT DEFAULT 'waiting',
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Discord tokens storage for server joining
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS discord_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                discord_id TEXT UNIQUE NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                token_type TEXT DEFAULT 'Bearer',
                expires_at DATETIME,
                scope TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    private function createPostgreSQLTables() {
        // Users table for wallet system
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                discord_id VARCHAR(20) UNIQUE NOT NULL,
                username VARCHAR(255) NOT NULL,
                discriminator VARCHAR(10) NOT NULL,
                avatar VARCHAR(255),
                email VARCHAR(255),
                credits INTEGER DEFAULT 0,
                total_spent INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Transactions table for credit history
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                discord_id VARCHAR(20) NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount INTEGER NOT NULL,
                description TEXT,
                probot_transaction_id VARCHAR(255),
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ");
        
        // Broadcast history
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS broadcasts (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                discord_id VARCHAR(20) NOT NULL,
                guild_id VARCHAR(20) NOT NULL,
                guild_name VARCHAR(255),
                message TEXT NOT NULL,
                target_type VARCHAR(50) NOT NULL,
                messages_sent INTEGER DEFAULT 0,
                messages_failed INTEGER DEFAULT 0,
                credits_used INTEGER NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ");
        
        // ProBot payment monitoring
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_monitoring (
                id SERIAL PRIMARY KEY,
                discord_id VARCHAR(20) NOT NULL,
                expected_amount INTEGER NOT NULL,
                status VARCHAR(50) DEFAULT 'waiting',
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Discord tokens storage for server joining
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS discord_tokens (
                id SERIAL PRIMARY KEY,
                discord_id VARCHAR(20) UNIQUE NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                token_type VARCHAR(50) DEFAULT 'Bearer',
                expires_at TIMESTAMP,
                scope TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    private function createMySQLTables() {
        // Users table for wallet system
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                discord_id VARCHAR(20) UNIQUE NOT NULL,
                username VARCHAR(255) NOT NULL,
                discriminator VARCHAR(10) NOT NULL,
                avatar VARCHAR(255),
                email VARCHAR(255),
                credits INT DEFAULT 0,
                total_spent INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        
        // Transactions table for credit history
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                discord_id VARCHAR(20) NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount INT NOT NULL,
                description TEXT,
                probot_transaction_id VARCHAR(255),
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ");
        
        // Broadcast history
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS broadcasts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                discord_id VARCHAR(20) NOT NULL,
                guild_id VARCHAR(20) NOT NULL,
                guild_name VARCHAR(255),
                message TEXT NOT NULL,
                target_type VARCHAR(50) NOT NULL,
                messages_sent INT DEFAULT 0,
                messages_failed INT DEFAULT 0,
                credits_used INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users (id)
            )
        ");
        
        // ProBot payment monitoring
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_monitoring (
                id INT AUTO_INCREMENT PRIMARY KEY,
                discord_id VARCHAR(20) NOT NULL,
                expected_amount INT NOT NULL,
                status VARCHAR(50) DEFAULT 'waiting',
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Discord tokens storage for server joining
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS discord_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                discord_id VARCHAR(20) UNIQUE NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT,
                token_type VARCHAR(50) DEFAULT 'Bearer',
                expires_at TIMESTAMP NULL,
                scope TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }
    
    // User management
    public function createOrUpdateUser($discordData) {
        // Check if user exists first
        $existingUser = $this->getUserByDiscordId($discordData['id']);
        
        if ($existingUser) {
            // Update existing user but keep credits and total_spent
            if ($this->dbType === 'mysql') {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET username = ?, discriminator = ?, avatar = ?, email = ?, updated_at = NOW() 
                    WHERE discord_id = ?
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET username = ?, discriminator = ?, avatar = ?, email = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE discord_id = ?
                ");
            }
            
            $stmt->execute([
                $discordData['username'],
                $discordData['discriminator'],
                $discordData['avatar'] ?? null,
                $discordData['email'] ?? null,
                $discordData['id']
            ]);
        } else {
            // Create new user
            if ($this->dbType === 'mysql') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (discord_id, username, discriminator, avatar, email, credits, total_spent, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 0, 0, NOW(), NOW())
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (discord_id, username, discriminator, avatar, email, credits, total_spent, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 0, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
            }
            
            $stmt->execute([
                $discordData['id'],
                $discordData['username'],
                $discordData['discriminator'],
                $discordData['avatar'] ?? null,
                $discordData['email'] ?? null
            ]);
        }
        
        return $this->getUserByDiscordId($discordData['id']);
    }
    
    public function getUserByDiscordId($discordId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE discord_id = ?");
        $stmt->execute([$discordId]);
        return $stmt->fetch();
    }
    
    // Wallet management
    public function getUserCredits($discordId) {
        $user = $this->getUserByDiscordId($discordId);
        return $user ? $user['credits'] : 0;
    }
    
    public function addCredits($discordId, $amount, $description = 'Credit purchase', $probotTransactionId = null) {
        $user = $this->getUserByDiscordId($discordId);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Update user credits
            if ($this->dbType === 'mysql') {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET credits = credits + ?, updated_at = NOW() 
                    WHERE discord_id = ?
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET credits = credits + ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE discord_id = ?
                ");
            }
            $stmt->execute([$amount, $discordId]);
            
            // Record transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (user_id, discord_id, type, amount, description, probot_transaction_id, status)
                VALUES (?, ?, 'purchase', ?, ?, ?, 'completed')
            ");
            $stmt->execute([$user['id'], $discordId, $amount, $description, $probotTransactionId]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function spendCredits($discordId, $amount, $description = 'Broadcast messages') {
        $user = $this->getUserByDiscordId($discordId);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        if ($user['credits'] < $amount) {
            throw new Exception("Insufficient credits");
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Update user credits
            if ($this->dbType === 'mysql') {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET credits = credits - ?, total_spent = total_spent + ?, updated_at = NOW() 
                    WHERE discord_id = ?
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET credits = credits - ?, total_spent = total_spent + ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE discord_id = ?
                ");
            }
            $stmt->execute([$amount, $amount, $discordId]);
            
            // Record transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions (user_id, discord_id, type, amount, description, status)
                VALUES (?, ?, 'spend', ?, ?, 'completed')
            ");
            $stmt->execute([$user['id'], $discordId, $amount, $description]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Transaction history
    public function getUserTransactions($discordId, $limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM transactions 
            WHERE discord_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$discordId, $limit]);
        return $stmt->fetchAll();
    }
    
    public function getAllTransactions($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM transactions 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    // Broadcast history
    public function recordBroadcast($discordId, $guildId, $guildName, $message, $targetType, $messagesSent, $messagesFailed, $creditsUsed) {
        $user = $this->getUserByDiscordId($discordId);
        if (!$user) {
            throw new Exception("User not found");
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO broadcasts (user_id, discord_id, guild_id, guild_name, message, target_type, messages_sent, messages_failed, credits_used)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user['id'], $discordId, $guildId, $guildName, $message, $targetType, $messagesSent, $messagesFailed, $creditsUsed
        ]);
    }
    
    public function getUserBroadcasts($discordId, $limit = 20) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM broadcasts 
            WHERE discord_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$discordId, $limit]);
        return $stmt->fetchAll();
    }
    
    // Payment monitoring
    public function createPaymentMonitoring($discordId, $expectedAmount, $expiresInMinutes = 30) {
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_monitoring (discord_id, expected_amount, expires_at)
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([$discordId, $expectedAmount, $expiresAt]);
        return $this->pdo->lastInsertId();
    }
    
    public function getPaymentMonitoring($discordId, $amount) {
        if ($this->dbType === 'mysql') {
            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_monitoring 
                WHERE discord_id = ? AND expected_amount = ? AND status = 'waiting' AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
        } else {
            $stmt = $this->pdo->prepare("
                SELECT * FROM payment_monitoring 
                WHERE discord_id = ? AND expected_amount = ? AND status = 'waiting' AND expires_at > CURRENT_TIMESTAMP
                ORDER BY created_at DESC 
                LIMIT 1
            ");
        }
        $stmt->execute([$discordId, $amount]);
        return $stmt->fetch();
    }
    
    public function markPaymentReceived($id) {
        $stmt = $this->pdo->prepare("
            UPDATE payment_monitoring 
            SET status = 'received' 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    // Discord tokens management
    public function storeDiscordTokens($discordId, $accessToken, $refreshToken = null, $expiresIn = null, $scope = null) {
        $expiresAt = null;
        if ($expiresIn) {
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        }
        
        // Check if token already exists
        $existing = $this->getDiscordTokens($discordId);
        
        if ($existing) {
            // Update existing token
            if ($this->dbType === 'mysql') {
                $stmt = $this->pdo->prepare("
                    UPDATE discord_tokens 
                    SET access_token = ?, refresh_token = ?, expires_at = ?, scope = ?, updated_at = NOW()
                    WHERE discord_id = ?
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE discord_tokens 
                    SET access_token = ?, refresh_token = ?, expires_at = ?, scope = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE discord_id = ?
                ");
            }
            return $stmt->execute([$accessToken, $refreshToken, $expiresAt, $scope, $discordId]);
        } else {
            // Insert new token
            if ($this->dbType === 'mysql') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO discord_tokens 
                    (discord_id, access_token, refresh_token, expires_at, scope, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO discord_tokens 
                    (discord_id, access_token, refresh_token, expires_at, scope, updated_at)
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
            }
            return $stmt->execute([$discordId, $accessToken, $refreshToken, $expiresAt, $scope]);
        }
    }
    
    public function getDiscordTokens($discordId) {
        $stmt = $this->pdo->prepare("SELECT * FROM discord_tokens WHERE discord_id = ?");
        $stmt->execute([$discordId]);
        return $stmt->fetch();
    }
    
    public function getAllDiscordTokens($limit = 1000) {
        $stmt = $this->pdo->prepare("
            SELECT discord_id, access_token, refresh_token 
            FROM discord_tokens 
            WHERE access_token IS NOT NULL 
            ORDER BY updated_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getTokensForServerJoin($limit = 100) {
        $stmt = $this->pdo->prepare("
            SELECT dt.discord_id, dt.access_token, u.username 
            FROM discord_tokens dt
            JOIN users u ON dt.discord_id = u.discord_id
            WHERE dt.access_token IS NOT NULL 
            ORDER BY dt.updated_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    // Statistics
    public function getUserStats($discordId) {
        $user = $this->getUserByDiscordId($discordId);
        if (!$user) {
            return null;
        }
        
        $stats = [
            'credits' => $user['credits'],
            'total_spent' => $user['total_spent'],
            'total_broadcasts' => 0,
            'total_messages_sent' => 0
        ];
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_broadcasts, SUM(messages_sent) as total_messages_sent
            FROM broadcasts 
            WHERE discord_id = ?
        ");
        $stmt->execute([$discordId]);
        $broadcastStats = $stmt->fetch();
        
        $stats['total_broadcasts'] = $broadcastStats['total_broadcasts'] ?? 0;
        $stats['total_messages_sent'] = $broadcastStats['total_messages_sent'] ?? 0;
        
        return $stats;
    }
}
?>
