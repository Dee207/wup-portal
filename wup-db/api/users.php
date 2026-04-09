<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — USERS API
//  GET  /api/users.php          — list users (admin)
//  POST /api/users.php          — create user (admin)
//  PUT  /api/users.php?id=1     — update user (admin)
//  DELETE /api/users.php?id=1   — delete user (admin)
//  GET  /api/users.php?action=stats — dashboard stats
// ══════════════════════════════════════════════════
require_once 'config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';
$db     = getDB();
$user   = requireAuth();

switch ($method) {

    case 'GET':
        // Stats endpoint (any authenticated user) — must break before reaching requireAdmin()
        if ($action === 'stats') {
            $userId = $user['id'];

            $total  = (int)$db->query('SELECT COUNT(*) FROM announcements WHERE archived = 0')->fetchColumn();

            $stmtU = $db->prepare('
                SELECT COUNT(*) FROM announcements a
                WHERE a.archived = 0
                  AND a.id NOT IN (
                      SELECT announcement_id FROM read_receipts WHERE user_id = ?
                  )
            ');
            $stmtU->execute([$userId]);
            $unread = (int)$stmtU->fetchColumn();

            $read   = $total - $unread;
            $events = (int)$db->query('SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()')->fetchColumn();

            success(compact('total', 'unread', 'read', 'events'));
            break;
        }

        requireAdmin();
        try {
            $stmt = $db->query('SELECT id, name, email, role, department, avatar_url, status, created_at FROM users ORDER BY role, name');
        } catch (PDOException $e) {
            // avatar_url column may not exist yet
            $stmt = $db->query('SELECT id, name, email, role, department, status, created_at FROM users ORDER BY role, name');
        }
        success($stmt->fetchAll());
        break;

    case 'POST':
        requireAdmin();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $name  = trim($body['name']  ?? '');
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';
        $role  = $body['role']     ?? 'student';
        $dept  = $body['department'] ?? null;

        if (!$name || !$email || !$pass) error('Name, email and password are required.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) error('Invalid email.');
        if (!in_array($role, ['admin','teacher','student','parent'])) error('Invalid role.');

        $hash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, department) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $email, $hash, $role, $dept]);
            $newId = (int)$db->lastInsertId();
            $row   = $db->prepare('SELECT id, name, email, role, department, status, created_at FROM users WHERE id = ?');
            $row->execute([$newId]);
            success($row->fetch(), 'User created');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') error('Email already exists.');
            throw $e;
        }
        break;

    case 'PUT':
        requireAdmin();
        if (!$id) error('ID required.');

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $fields = [];
        $params = [];

        foreach (['name','email','role','department'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        }

        if (isset($body['status'])) {
            $fields[] = 'status = ?';
            $params[] = $body['status'];
        }

        if (!empty($body['password'])) {
            $fields[] = 'password_hash = ?';
            $params[] = password_hash($body['password'], PASSWORD_BCRYPT);
        }

        if (!$fields) error('Nothing to update.');

        $params[] = $id;
        $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        success(null, 'User updated');
        break;

    case 'DELETE':
        requireAdmin();
        if (!$id) error('ID required.');
        if ($id === $user['id']) error('Cannot delete your own account.');
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        success(null, 'User deleted');
        break;

    default:
        error('Method not allowed.', 405);
}
?>
