<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — DATABASE CONFIGURATION
//  Edit these values to match your ByteHost MySQL
//  credentials from your cPanel / ByteHost dashboard.
// ══════════════════════════════════════════════════

define('DB_HOST', 'sql108.byethost13.com');  // ByteHost MySQL host
define('DB_NAME', 'b13_41186802_wup');      // your MySQL database name
define('DB_USER', 'b13_41186802');          // your MySQL username
define('DB_PASS', 'qwerty123');             // your MySQL password
define('DB_CHARSET', 'utf8mb4');

// ── CORS & JSON headers (called by all API files) ──
function setHeaders() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
}

// ── PDO connection ──
function getDB(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

// ── JSON response helpers ──
function respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function error(string $msg, int $code = 400): void {
    respond(['success' => false, 'message' => $msg], $code);
}

function success($data = null, string $msg = 'OK'): void {
    respond(['success' => true, 'message' => $msg, 'data' => $data]);
}

// ── Auth helper: read session token from header ──
function getAuthUser(): ?array {
    $db = getDB();

    // Apache on shared hosts (like ByteHost) often strips the Authorization
    // header. Check all known fallback locations.
    $token = $_SERVER['HTTP_AUTHORIZATION']
           ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
           ?? getallheaders()['Authorization']
           ?? '';

    $token = str_replace('Bearer ', '', $token);
    if (!$token) return null;

    $stmt = $db->prepare('SELECT * FROM users WHERE session_token = ? AND token_expires > NOW()');
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function requireAuth(): array {
    $user = getAuthUser();
    if (!$user) error('Unauthorized. Please log in.', 401);
    return $user;
}

function requireAdmin(): array {
    $user = requireAuth();
    if ($user['role'] !== 'admin') error('Access denied. Admins only.', 403);
    return $user;
}
?>
