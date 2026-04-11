<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — ANNOUNCEMENT IMAGE UPLOAD API
//  POST /api/upload_image.php  (multipart/form-data with field "image")
//  Returns: { success: true, data: { image_url: "assets/uploads/…" } }
// ══════════════════════════════════════════════════
require_once 'config.php';
setHeaders();

$user = requireAuth();

// Only admins can upload announcement images
if (!in array($user['role'], ['admin', 'teacher'])) error("Only admins and teachers can upload images., 403);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Method not allowed.', 403);

if (empty($_FILES['image'])) error('No file uploaded.');

$file    = $_FILES['image'];
$maxSize = 5 * 1024 * 1024; // 5 MB
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK)  error('Upload error: ' . $file['error']);
if ($file['size'] > $maxSize)          error('File too large. Max 5 MB.');

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowed)) error('Only JPG, PNG, GIF, or WebP allowed.');

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/../assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Generate unique filename
$ext      = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'jpg'
};
$filename = 'img_' . $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) error('Failed to save file.');

$imageUrl = 'assets/uploads/' . $filename;

success(['image_url' => $imageUrl], 'Image uploaded successfully!');
