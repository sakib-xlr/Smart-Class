<?php
/**
 * Database Configuration — Advanced Classroom System
 *
 * Uses PDO for secure database connections with prepared statements.
 * In production (Railway), set environment variables.
 * Locally (XAMPP), values are loaded from the root .env file.
 */

// ── Load .env file (local development only) ──
$_envFile = __DIR__ . '/../../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_k = trim($_k); $_v = trim($_v);
        if (!getenv($_k)) putenv("$_k=$_v");
    }
}
unset($_envFile, $_line, $_k, $_v);

// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST',    getenv('MYSQLHOST')     ?: 'localhost');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: 'advanced_classroom');
define('DB_USER',    getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Application Configuration
// ============================================================
define('APP_NAME',    'Advanced Classroom');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    getenv('APP_URL') ?: 'http://localhost/Smart_Class/advance_classroom');
define('UPLOAD_DIR',  __DIR__ . '/../assets/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);  // 10 MB

// Allowed file types for uploads
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar']);

// ============================================================
// PDO Database Connection
// ============================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // In production, log this error instead of displaying it
    die('<div style="font-family:Inter,sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#EF4444;">⚠ Database Connection Failed</h2>
        <p style="color:#6B7280;">Please check your database configuration in <code>config/db.php</code></p>
        <p style="color:#9CA3AF;font-size:14px;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p style="color:#9CA3AF;font-size:13px;">Make sure MySQL is running and the database "' . DB_NAME . '" exists.</p>
    </div>');
}
