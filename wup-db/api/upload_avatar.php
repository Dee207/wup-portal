<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — AVATAR UPLOAD API
//  POST /api/upload_avatar.php  (multipart/form-data with field "avatar")
// ══════════════════════════════════════════════════
require_once 'config.php';
setHeaders();

$user = requireAuth();
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Method not allowed.', 405);

if (empty($_FILES['avatar'])) error('No file uploaded.');

$file    = $_FILES['avatar'];
$maxSize = 5 * 1024 * 1024; // 3 MB
$allowed = ['image/jpeg','image/png','image/gif','image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK)  error('Upload error.');
if ($file['size'] > $maxSize)          error('File too large. Maximum allowed size is 5 MB.');
if (!in_array(mime_content_type($file['tmp_name']), $allowed)) error('Only JPG, PNG, GIF, or WebP allowed.');

// Ensure avatars directory exists
$avatarDir = __DIR__ . '/../assets/avatars/';
if (!is_dir($avatarDir)) mkdir($avatarDir, 0755, true);

// Remove old avatar if present
$stmt = $db->prepare('SELECT avatar_url FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$old = $stmt->fetchColumn();
if ($old) {
    $oldPath = __DIR__ . '/../' . ltrim($old, '/');
    if (file_exists($oldPath)) unlink($oldPath);
}

// Save new file with unique name
$ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
$filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
$destPath = $avatarDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) error('Failed to save file.');

$avatarUrl = 'assets/avatars/' . $filename;

// Update DB with graceful fallback if column doesn't exist yet
try {
    $db->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')
       ->execute([$avatarUrl, $user['id']]);
} catch (PDOException $e) {
    // Column may not exist — run api/add_avatar_column.php
    unlink($destPath);
    error('avatar_url column missing. Run api/add_avatar_column.php first.');
}

success(['avatar_url' => $avatarUrl], 'Avatar updated!');
