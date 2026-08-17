<?php
// =============================================
// Smart Classroom System — Database Config
// =============================================

// ── Load .env file (local development only) ──
$_envFile = __DIR__ . '/../.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_k = trim($_k); $_v = trim($_v);
        if (!getenv($_k)) putenv("$_k=$_v");  // don't override real env vars (Railway)
    }
}
unset($_envFile, $_line, $_k, $_v);

// In production (Railway), these are set as environment variables.
// Locally (XAMPP), the .env file above supplies them.
define('DB_HOST',  getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USER',  getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',  getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME',  getenv('MYSQLDATABASE') ?: 'smart_classroom');
define('MYSQL_PORT', getenv('MYSQLPORT')   ?: '3306');
define('BASE_URL', getenv('APP_URL')       ?: 'http://localhost/Smart_Class');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// PDO Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . MYSQL_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper Functions
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(string $redirect = '/index.php'): void {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . $redirect);
        exit;
    }
}

function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function userRole(): string {
    return $_SESSION['user']['role'] ?? '';
}

function generateCode(int $length = 7): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
