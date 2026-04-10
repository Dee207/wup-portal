<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — ANNOUNCEMENTS API
//  GET    /api/announcements.php             — list
//  GET    /api/announcements.php?id=1        — single
//  POST   /api/announcements.php             — create (admin/teacher)
//  PUT    /api/announcements.php?id=1        — update (admin/teacher)
//  DELETE /api/announcements.php?id=1        — delete (admin)
//  POST   /api/announcements.php?action=read&id=1 — mark as read
// ══════════════════════════════════════════════════
require_once 'config.php';
setHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? '';
$db     = getDB();
$user   = requireAuth();

// ── Helper: fetch full announcement with author name ──
function fetchAnn(PDO $db, int $id, int $userId): ?array {
    $stmt = $db->prepare('
        SELECT a.*, u.name AS author_name,
               (SELECT COUNT(*) FROM read_receipts WHERE announcement_id = a.id AND user_id = ?) AS is_read
        FROM announcements a
        JOIN users u ON u.id = a.author_id
        WHERE a.id = ?
    ');
    $stmt->execute([$userId, $id]);
    return $stmt->fetch() ?: null;
}

switch ($method) {

    // ══ GET ══
    case 'GET':
        if ($id) {
            $ann = fetchAnn($db, $id, $user['id']);
            if (!$ann) error('Announcement not found.', 404);
            success($ann);
        }

        // Build filter query
        $where  = ['1=1'];
        $params = [];

        // Archived filter (default: not archived)
        $archived = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
        $where[]  = 'a.archived = ?';
        $params[] = $archived;

        // Category filter
        if (!empty($_GET['category'])) {
            $where[]  = 'a.category = ?';
            $params[] = $_GET['category'];
        }

        // Audience filter (always include 'all')
        if (!empty($_GET['audience'])) {
            $where[]  = '(a.audience = ? OR a.audience = "all")';
            $params[] = $_GET['audience'];
        }

        // Search
        if (!empty($_GET['q'])) {
            $where[]  = '(a.title LIKE ? OR a.content LIKE ?)';
            $like     = '%' . $_GET['q'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sort   = $_GET['sort'] ?? 'newest';
        $order  = $sort === 'oldest' ? 'ASC' : 'DESC';
        $limit  = min((int)($_GET['limit'] ?? 50), 100);
        $offset = (int)($_GET['offset'] ?? 0);

        $sql = '
            SELECT a.*, u.name AS author_name,
                   (SELECT COUNT(*) FROM read_receipts WHERE announcement_id = a.id AND user_id = ?) AS is_read
            FROM announcements a
            JOIN users u ON u.id = a.author_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.pinned DESC, a.created_at ' . $order . '
            LIMIT ? OFFSET ?
        ';

        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$user['id']], $params, [$limit, $offset]));
        $rows = $stmt->fetchAll();

        // Count total
        $cntSql  = 'SELECT COUNT(*) FROM announcements a WHERE ' . implode(' AND ', $where);
        $cntStmt = $db->prepare($cntSql);
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        success(['items' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset]);
        break;

    // ══ POST ══
    case 'POST':
        // Mark as read
        if ($action === 'read' && $id) {
            $db->prepare('INSERT IGNORE INTO read_receipts (user_id, announcement_id) VALUES (?,?)')
               ->execute([$user['id'], $id]);
            success(null, 'Marked as read');
        }

        // Create announcement (admin or teacher)
        if (!in_array($user['role'], ['admin', 'teacher'])) {
            error('Only admins and teachers can post announcements.', 403);
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $title      = trim($body['title']      ?? '');
        $content    = trim($body['content']    ?? '');
        $category   = $body['category']   ?? 'notice';
        $audience   = $body['audience']   ?? 'all';
        $pinned     = !empty($body['pinned'])   ? 1 : 0;
        $archived   = !empty($body['archived']) ? 1 : 0;
        $image_url  = trim($body['image_url']  ?? '');
        $event_date = !empty($body['event_date']) ? $body['event_date'] : null;

       if (!$title || !$content) error("Title and content are required.");
       if (strlen($title) > 255) error("Title is too long. Maximum 255 characters allowed.");
        $allowed_cats = ['event','exam','notice','activity','holiday'];
        $allowed_auds = ['all','student','teacher','parent','staff'];
        if (!in_array($category, $allowed_cats)) error('Invalid category.');
        if (!in_array($audience, $allowed_auds)) error('Invalid audience.');

        // Try with image_url + event_date columns; fall back gracefully
        try {
            $stmt = $db->prepare('
                INSERT INTO announcements (title, content, image_url, event_date, category, audience, author_id, pinned, archived)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$title, $content, $image_url ?: null, $event_date, $category, $audience, $user['id'], $pinned, $archived]);
        } catch (PDOException $e) {
            // Fallback: columns may not exist yet
            try {
                $stmt = $db->prepare('
                    INSERT INTO announcements (title, content, image_url, category, audience, author_id, pinned, archived)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$title, $content, $image_url ?: null, $category, $audience, $user['id'], $pinned, $archived]);
            } catch (PDOException $e2) {
                $stmt = $db->prepare('
                    INSERT INTO announcements (title, content, category, audience, author_id, pinned, archived)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$title, $content, $category, $audience, $user['id'], $pinned, $archived]);
            }
        }
        $newId = (int)$db->lastInsertId();

        success(fetchAnn($db, $newId, $user['id']), 'Announcement published');
        break;

    // ══ PUT ══
    case 'PUT':
        if (!$id) error('ID required.');
        if (!in_array($user['role'], ['admin', 'teacher'])) error('Forbidden.', 403);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $params = [];

        foreach (['title','content','image_url','category','audience'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = $body[$f]; }
        }

        foreach (['pinned','archived'] as $f) {
            if (isset($body[$f])) { $fields[] = "$f = ?"; $params[] = (int)$body[$f]; }
        }

        if (!$fields) error('Nothing to update.');

        $params[] = $id;
        $db->prepare('UPDATE announcements SET ' . implode(', ', $fields) . ' WHERE id = ?')
           ->execute($params);

        success(fetchAnn($db, $id, $user['id']), 'Updated');
        break;

    // ══ DELETE ══
    case 'DELETE':
        if (!$id) error('ID required.');
        if ($user['role'] !== 'admin') error('Only admins can delete.', 403);

        $db->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        success(null, 'Deleted');
        break;

    default:
        error('Method not allowed.', 405);
}
?>
