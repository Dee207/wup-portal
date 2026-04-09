<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — EVENTS API
//  GET    /api/events.php        — list all events
//  POST   /api/events.php        — create event (admin)
//  DELETE /api/events.php?id=1   — delete event (admin)
// ══════════════════════════════════════════════════
require_once 'config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$db     = getDB();
$user   = requireAuth();

switch ($method) {

    case 'GET':
        $stmt = $db->query('SELECT * FROM events ORDER BY event_date ASC');
        success($stmt->fetchAll());
        break;

    case 'POST':
        if ($user['role'] !== 'admin') error('Admins only.', 403);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $title = trim($body['title'] ?? '');
        $date  = $body['event_date'] ?? '';
        if (!$title || !$date) error('Title and date are required.');

        $stmt = $db->prepare('INSERT INTO events (title, event_date, event_time, location, description) VALUES (?,?,?,?,?)');
        $stmt->execute([$title, $date, $body['event_time'] ?? 'All Day', $body['location'] ?? null, $body['description'] ?? null]);

        $newId = (int)$db->lastInsertId();
        $row   = $db->prepare('SELECT * FROM events WHERE id = ?');
        $row->execute([$newId]);
        success($row->fetch(), 'Event created');
        break;

    case 'DELETE':
        if (!$id) error('ID required.');
        if ($user['role'] !== 'admin') error('Admins only.', 403);
        $db->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        success(null, 'Deleted');
        break;

    default:
        error('Method not allowed.', 405);
}
?>
