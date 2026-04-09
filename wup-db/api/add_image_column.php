<?php
// One-time migration: adds image_url column to announcements table
// Visit this file ONCE, then delete it.
require_once 'config.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) DEFAULT NULL AFTER content");
    echo "<p style='color:green;font-family:sans-serif'>✅ <strong>image_url</strong> column added (or already exists). You can delete this file now.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;font-family:sans-serif'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
