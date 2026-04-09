<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — AUTH API
//  POST /api/auth.php?action=login
//  POST /api/auth.php?action=logout
//  GET  /api/auth.php?action=me
// ══════════════════════════════════════════════════
require_once 'config.php';
setHeaders();

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    // ── LOGIN ──
    case 'login':
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';

        if (!$email || !$pass) error('Email and password are required.');

        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password_hash'])) {
            error('Invalid email or password.', 401);
        }

        // Generate session token
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+8 hours'));

        $db->prepare('UPDATE users SET session_token = ?, token_expires = ? WHERE id = ?')
           ->execute([$token, $expires, $user['id']]);

        success([
            'token' => $token,
            'user'  => [
                'id'         => $user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'department' => $user['department'],
            ]
        ], 'Login successful');
        break;

    // ── LOGOUT ──
    case 'logout':
        $user = requireAuth();
        getDB()->prepare('UPDATE users SET session_token = NULL, token_expires = NULL WHERE id = ?')
               ->execute([$user['id']]);
        success(null, 'Logged out');
        break;

    // ── ME (verify token & get current user) ──
    case 'me':
        $user = requireAuth();
        success([
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'department' => $user['department'],
        ]);
        break;

    default:
        error('Invalid action.', 404);
}
?>
